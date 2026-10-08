# Sistem Ticketing KOMINFO PINRANG

Aplikasi helpdesk internal berbasis CodeIgniter 4 dan MySQL. WhatsApp menjadi channel melalui OpenWA terpisah; MySQL ticketing tetap menjadi sumber kebenaran.

## Kebutuhan

- PHP 8.2+ dengan `intl`, `mbstring`, `mysqli`, `curl`, dan `json`
- Composer
- MySQL 8.0+ (InnoDB, utf8mb4)
- OpenWA terpisah dengan API key dan session WhatsApp aktif

## Instalasi lokal

```powershell
composer install
Copy-Item env .env
```

Atur `.env`:

```dotenv
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
app.appTimezone = 'Asia/Makassar'

database.default.hostname = '127.0.0.1'
database.default.database = 'ticketing'
database.default.username = 'ticketing_user'
database.default.password = 'password-database-anda'
database.default.DBDriver = MySQLi
database.default.port = 3306

ADMIN_EMAIL = 'admin@kominfo.pinrang.go.id'
ADMIN_PASSWORD = 'ganti-dengan-password-minimal-12-karakter'
ADMIN_NAME = 'Administrator KOMINFO'

OPENWA_BASE_URL = 'http://127.0.0.1:2785'
OPENWA_API_KEY = 'isi-api-key-openwa'
OPENWA_SESSION_ID = 'session-kominfo'
OPENWA_WEBHOOK_SECRET = 'buat-secret-random-yang-panjang'
# Kontrak kirim default belum diverifikasi untuk semua versi gateway; validasi di /api-docs OpenWA.
OPENWA_SEND_PATH = '/api/messages/sendText'
OPENWA_TIMEOUT = 10
```

Buat database MySQL terlebih dahulu, lalu jalankan:

```powershell
php spark migrate
php spark db:seed TicketingSeeder
php spark serve
```

Buka `http://localhost:8080/login`. Seeder membuat role, permission, employee/category/service/status/priority/team config awal, workflow transitions, SLA contoh, dan super admin. Admin credentials tidak memiliki default dan harus diatur sebelum seed.

## OpenWA

OpenWA berjalan sebagai gateway/service terpisah. Atur webhook pada session OpenWA agar mengirim event pesan masuk ke:

```text
POST https://<host-aplikasi>/api/webhooks/openwa
X-Webhook-Secret: <nilai OPENWA_WEBHOOK_SECRET>
Content-Type: application/json
```

OpenWA v5 menggunakan plugin `@open-wa/integration-webhook` yang dikonfigurasi pada `wa.config.mjs`; flag CLI `--webhook` saja tidak mengaktifkan delivery plugin. Contoh konfigurasi resmi v5 (isi URL aplikasi dan secret dari environment proses OpenWA):

```js
// wa.config.mjs pada host OpenWA
const webhookSecret = process.env.OPENWA_WEBHOOK_SECRET?.trim();
if (!webhookSecret) throw new Error('OPENWA_WEBHOOK_SECRET wajib diatur');

export default {
  sessionId: 'session-kominfo',
  plugins: ['@open-wa/integration-webhook'],
  pluginConfig: {
    webhook: {
      url: 'https://<host-aplikasi>/api/webhooks/openwa',
      events: ['message.received'],
      headers: { 'X-Webhook-Secret': webhookSecret },
      retries: 3,
      retryDelay: 1000,
      timeout: 30000,
      durability: { enabled: true, path: '.openwa/webhook-deliveries.sqlite', replayLimit: 1000 },
    },
  },
};
```

Jalankan CLI dengan `--config ./wa.config.mjs` dan `--session-id session-kominfo`. Karena secret dipakai oleh dua proses terpisah, atur nilai yang sama secara aman pada proses aplikasi dan proses OpenWA.
Referensi upstream: [Webhook payloads](https://openwa.dev/docs/client-and-integrations/webhook-payloads) dan [Easy API quick start](https://openwa.dev/docs/getting-started/easy-api).

Struktur event v5:

```json
{
  "webhookId": "webhook-instance-id",
  "sessionId": "session-kominfo",
  "event": "message.received",
  "timestamp": 1730000000000,
  "payload": {
    "message": {
      "id": "openwa-message-id",
      "from": "628123456789@c.us",
      "body": "Internet di ruang pelayanan tidak bisa",
      "fromMe": false,
      "isGroupMsg": false
    }
  }
}
```

Endpoint memvalidasi secret sebelum membaca JSON, membatasi body hingga 2 MiB dan membatasi laju request. Event mentah disimpan ke antrean lalu endpoint langsung merespons HTTP 202; pemrosesan bisnis dilakukan terpisah. Deduplikasi memakai header `Idempotency-Key` atau field envelope `idempotencyKey` jika tersedia, lalu session/event/message ID (fallback hash untuk payload tanpa ID). `webhookId` hanya mengidentifikasi instance plugin, bukan pesan unik. Pesan yang berasal dari akun gateway dan pesan grup diabaikan. Nomor pengirim harus terdaftar sebagai pegawai aktif; nomor tak terdaftar menerima pesan penolakan dan tidak dapat membuat ticket. Konfigurasikan plugin OpenWA atau reverse proxy agar mengirim header secret; endpoint tidak menerima secret di URL.

Untuk menguji dari PowerShell, ganti nilai host/secret/nomor/payload:

```powershell
$headers = @{ 'X-Webhook-Secret' = 'nilai OPENWA_WEBHOOK_SECRET' }
$body = @{ webhookId = 'local-test'; sessionId = 'session-kominfo'; event = 'message.received'; timestamp = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds(); payload = @{ message = @{ id = @{ _serialized = 'test-unique-001' }; from = '628123456789@c.us'; body = 'ADUAN'; fromMe = $false; isGroupMsg = $false } } } | ConvertTo-Json -Depth 8
Invoke-RestMethod -Method Post -Uri 'http://localhost:8080/api/webhooks/openwa' -Headers $headers -ContentType 'application/json' -Body $body
```

Jalankan worker event masuk dan worker notifikasi keluar secara terpisah:

```powershell
php spark openwa:webhooks:work 20
php spark notifications:work 20
```

Untuk pemrosesan terus-menerus, jalankan masing-masing loop dalam proses/worker terpisah:

```powershell
while ($true) { php spark openwa:webhooks:work 20; Start-Sleep -Seconds 2 }
while ($true) { php spark notifications:work 20; Start-Sleep -Seconds 15 }
```

Event yang gagal diproses dicoba ulang hingga lima kali dengan exponential backoff; lock yang lebih lama dari 10 menit akan dipulihkan.

Kirim `ADUAN` untuk guided flow kategori lalu uraian. Free text membuat ticket pada kategori aktif pertama hanya bila pelapor tidak punya ticket aktif; jika ada satu ticket aktif, pesan tidak ditempel otomatis dan bot meminta referensi eksplisit `#TCK-YYYYMMDD-NNNN`. Jika ada beberapa ticket aktif, bot meminta pemilihan bernomor lalu menerima satu pesan untuk ticket yang secara eksplisit dipilih. Gunakan `BARU: uraian` untuk membuat ticket baru walau masih ada ticket aktif.

**Kontrak API OpenWA keluar:** adapter mengikuti Easy API v5: `POST /api/messages/sendText`, header `X-API-Key`, dan JSON `to` (`<nomor>@c.us`) serta `content`. Base URL, API key, session ID, timeout, dan path dikonfigurasi dari environment; path dapat dioverride melalui `OPENWA_SEND_PATH`. Endpoint alternatif/alias dapat dilihat di Swagger gateway target pada `/api-docs/`. Smoke test tetap diperlukan untuk memeriksa versi, session readiness (`/health`), auth, dan pengiriman aktual sebelum produksi. Jangan menaruh API key di frontend atau log.

## Antrean notifikasi

Pesan acknowledgement dan update status disimpan ke `notification_queue`. Jalankan worker satu kali:

```powershell
php spark notifications:work 20
```

Untuk polling terus-menerus pada PowerShell: `while ($true) { php spark notifications:work 20; Start-Sleep -Seconds 15 }`. Kegagalan dikembalikan ke antrean dengan exponential backoff hingga lima percobaan, kemudian berstatus `failed`. Kegagalan OpenWA tidak membatalkan ticket.

## Fitur MVP

- Login/logout, session, password hash, role dan permission database; setiap route admin dilindungi autentikasi dan permission server-side.
- CRUD master untuk pegawai, kategori, jenis layanan, status, prioritas, tim, dan user. DataTables memakai server-side search, sort, dan pagination.
- Ticket engine untuk nomor `TCK-YYYYMMDD-NNNN`, create/read/update, assignment, priority, workflow transition dari tabel, activity timeline, filter, pencarian, dan DataTables server-side.
- OpenWA webhook dengan secret, raw event storage, idempotency, pencocokan nomor pegawai ternormalisasi, guided flow, acknowledgement dan update status melalui queue.
- Dashboard agregat per status/kategori/prioritas dan tren harian; filter tanggal.
- Laporan tiket dengan filter dan CSV yang aman dari formula injection.
- Bootstrap 5.3, DataTables.net, Chart.js dan stylesheet Xakti Admin Template.

Xakti Admin Template menggunakan lisensi MIT; stylesheet dilayani melalui jsDelivr dan dipatok pada commit upstream `d70e4bb6324c9f9a8c00d7aa135e7eadbe23232c` dari [repository Xakti](https://github.com/fhdjg/xakti-admin-template).

## Pengujian

PHP CLI yang menjalankan tes harus mengaktifkan ekstensi `sqlite3` dan `pdo_sqlite`. Tes database memakai SQLite in-memory melalui konfigurasi `database.tests` dan tidak boleh diarahkan ke database aplikasi.

```powershell
composer test
```

## Catatan scope

Implementasi berhenti pada P0. Perhitungan/monitoring SLA, escalation, assignment otomatis, attachment, custom fields, AI, PDF, dan performance operator termasuk scope setelah MVP dan belum dibuat. Keputusan tambahan tercatat di [docs/ASSUMPTIONS.md](docs/ASSUMPTIONS.md).
