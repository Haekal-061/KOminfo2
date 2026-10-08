# Catatan Asumsi MVP

Semua keputusan berikut membatasi implementasi pada scope P0 PRD.

- Repository awal hanya berisi CodeIgniter 4 appstarter; aset/berkas Xakti Admin Template tidak disertakan. UI memakai Bootstrap 5.3, stylesheet Xakti Admin Template resmi (dipatok ke commit upstream), dan shell admin yang responsif.
- Status dan transisi status berasal dari tabel database; seeder memasukkan workflow default berurutan dari PRD dan engine hanya memeriksa konfigurasi tabel transisi.
- Role MVP yang disediakan: `super_admin`, `admin`, `supervisor`, `operator`. Permission disimpan pada database dan dijalankan di sisi server.
- Akun administrator awal harus disediakan melalui `ADMIN_EMAIL`, `ADMIN_PASSWORD` (minimal 12 karakter), dan opsional `ADMIN_NAME` di `.env`. Seeder gagal eksplisit jika kredensial aman belum diisi; tidak ada password bawaan.
- Master employee memakai field employee number, nama, nomor WhatsApp, email, department, posisi, serta flag aktif. Nomor disimpan apa adanya dan bentuk normalisasinya agar nomor lokal `08...` dan `+62...` cocok sebagai satu identitas.
- Ticket dari WhatsApp hanya dibuat untuk nomor pegawai aktif yang terdaftar. Bot menyediakan guided flow sederhana (mulai, pilih kategori, uraian); pesan bebas selain itu memulai ticket dengan kategori pertama yang aktif agar aduan tidak hilang.
- Pesan WhatsApp tidak otomatis ditempelkan ke tiket terbaru. Untuk melanjutkan ticket, pengirim menyebut referensi `#TCK-YYYYMMDD-NNNN`; pilihan nomor ticket hanya digunakan saat bot memang sedang meminta pilihan secara eksplisit.
- Receiver mengikuti envelope plugin OpenWA v5 (`sessionId`, `event`, `payload.message`) serta mempertahankan fallback untuk payload lama yang menyimpan pesan pada `data`. Event mentah diterima dan diantrikan sebelum HTTP 202 dikirim; worker memproses business flow secara asinkron dan retry sampai lima kali. Kunci deduplikasi memakai header `Idempotency-Key` atau field `idempotencyKey` jika tersedia, selain itu gabungan session/event/message ID atau hash payload; `webhookId` bukan ID event unik. Pesan `fromMe` dan grup diabaikan. Secret receiver dibaca dari `X-Webhook-Secret` atau `Authorization: Bearer`; atur custom header di plugin v5 atau reverse proxy.
- Pemrosesan setiap event dan efek databasenya berada pada transaksi yang sama agar retry setelah crash tidak membuat ticket/pesan/tanggapan antrean ganda. Lock event lebih lama dari 10 menit dapat dipulihkan oleh worker berikutnya.
- Adapter outbound diselaraskan dengan Easy API v5: `POST /api/messages/sendText`, header `X-API-Key`, body `to`/`content`. `OPENWA_SEND_PATH` tetap dapat mengubah route, tetapi skema body saat ini khusus v5. TODO deployment: verifikasi Swagger (`/api-docs`), readiness session, auth, dan kirim aktual terhadap gateway target; gateway/key target belum tersedia di lingkungan pengembangan untuk smoke test.
- Notification queue diproses melalui perintah Spark `notifications:work`; retry dilakukan sampai lima percobaan dengan jeda bertambah. Kegagalan gateway tidak membatalkan ticket.
- Laporan MVP diekspor sebagai CSV (dapat dibuka di Excel); PDF dan laporan SLA lanjutan tidak termasuk P0.
- Operator hanya dapat melihat ticket yang di-assign langsung kepadanya atau ke tim tempat ia terdaftar; keanggotaan tim diatur pada form master tim.
- SLA seed hanya contoh data master. Perhitungan SLA dan eskalasi termasuk P1 sehingga belum diproses oleh ticket engine.
