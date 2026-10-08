# Catatan Asumsi MVP

Semua keputusan berikut membatasi implementasi pada scope P0 PRD.

- Repository awal hanya berisi CodeIgniter 4 appstarter; aset/berkas Xakti Admin Template tidak disertakan. UI memakai Bootstrap 5.3, stylesheet Xakti Admin Template resmi (dipatok ke commit upstream), dan shell admin yang responsif.
- Status dan transisi status berasal dari tabel database; seeder memasukkan workflow default berurutan dari PRD dan engine hanya memeriksa konfigurasi tabel transisi.
- Role MVP yang disediakan: `super_admin`, `admin`, `supervisor`, `operator`. Permission disimpan pada database dan dijalankan di sisi server.
- Akun administrator awal harus disediakan melalui `ADMIN_EMAIL`, `ADMIN_PASSWORD` (minimal 12 karakter), dan opsional `ADMIN_NAME` di `.env`. Seeder gagal eksplisit jika kredensial aman belum diisi; tidak ada password bawaan.
- Master employee memakai field employee number, nama, nomor WhatsApp, email, department, posisi, serta flag aktif. Nomor disimpan apa adanya dan bentuk normalisasinya agar nomor lokal `08...` dan `+62...` cocok sebagai satu identitas.
- Ticket dari WhatsApp hanya dibuat untuk nomor pegawai aktif yang terdaftar. Bot menyediakan guided flow sederhana (mulai, pilih kategori, uraian); pesan bebas selain itu memulai ticket dengan kategori pertama yang aktif agar aduan tidak hilang.
- Pesan WhatsApp tidak otomatis ditempelkan ke tiket terbaru. Untuk melanjutkan ticket, pengirim menyebut referensi `#TCK-YYYYMMDD-NNNN`; pilihan nomor ticket hanya digunakan saat bot memang sedang meminta pilihan secara eksplisit.
- Payload webhook mengikuti envelope event OpenWA (`payload.event`, `payload.data`) dan juga menerima payload datar untuk deployment lain; event pesan `message.received` disimpan idempotent. Secret receiver dibaca dari `X-Webhook-Secret` atau `Authorization: Bearer`. TODO: pastikan konfigurasi delivery OpenWA mengirim header secret tersebut; bila versi gateway tidak mendukung custom header, pasang header melalui reverse proxy.
- Adapter mengikuti kontrak OpenWA REST saat ini untuk `POST /api/sessions/{sessionId}/messages/send-text`, header `X-API-Key`, dan body `chatId`/`text`. Path dapat dioverride melalui `OPENWA_SEND_PATH`. TODO: jalankan smoke test API menggunakan versi/session gateway target sebelum produksi dan sesuaikan jika target memakai kontrak lama.
- Notification queue diproses melalui perintah Spark `notifications:work`; retry dilakukan sampai lima percobaan dengan jeda bertambah. Kegagalan gateway tidak membatalkan ticket.
- Laporan MVP diekspor sebagai CSV (dapat dibuka di Excel); PDF dan laporan SLA lanjutan tidak termasuk P0.
- Operator hanya dapat melihat ticket yang di-assign langsung kepadanya atau ke tim tempat ia terdaftar; keanggotaan tim diatur pada form master tim.
- SLA seed hanya contoh data master. Perhitungan SLA dan eskalasi termasuk P1 sehingga belum diproses oleh ticket engine.
