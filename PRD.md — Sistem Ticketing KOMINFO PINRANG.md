# PRD — Sistem Ticketing KOMINFO PINRANG

**Product:** Sistem Ticketing / Helpdesk KOMINFO PINRANG  
**Document Version:** 1.0  
**Status:** Draft  
**Target Platform:** Web Admin + WhatsApp  
**Primary Users:** Admin/Operator KOMINFO PINRANG dan Pegawai pelapor

---

## 1. Ringkasan Produk

Sistem Ticketing KOMINFO PINRANG adalah aplikasi helpdesk internal yang digunakan untuk menerima, mengelola, memantau, dan menyelesaikan aduan dari pegawai terkait layanan Teknologi Informasi dan Komunikasi.

Media utama pelaporan pegawai adalah **WhatsApp**. Pesan WhatsApp akan diterima melalui **OpenWA**, diteruskan ke aplikasi ticketing melalui webhook/API, kemudian sistem secara otomatis membuat ticket berdasarkan isi dan metadata pesan.

Pada tahap awal, sistem mendukung minimal:

1. Aduan jaringan/internet.
2. Aduan absensi.

Namun desain sistem **tidak boleh mengunci jenis aduan tersebut secara hard-code**. Kategori, jenis layanan, prioritas, status, SLA, dan field tambahan harus dirancang sebagai data/configuration sehingga dapat ditambahkan melalui admin tanpa perubahan besar pada struktur aplikasi.

---

# 2. Latar Belakang

Proses pengaduan melalui WhatsApp memiliki beberapa masalah apabila dikelola secara manual:

- Aduan sulit dilacak.
- Tidak ada nomor tiket yang konsisten.
- Sulit mengetahui siapa yang sedang menangani aduan.
- Riwayat percakapan dapat tertimbun oleh chat baru.
- Tidak tersedia dashboard statistik.
- Sulit mengukur waktu penyelesaian.
- Sulit membuat laporan periodik.
- Tidak ada mekanisme eskalasi yang terstruktur.
- Aduan yang sama dapat dilaporkan berulang kali.
- Pimpinan sulit memperoleh gambaran kondisi layanan secara real-time.

Sistem ticketing bertujuan mengubah percakapan WhatsApp menjadi proses layanan yang terstruktur.

---

# 3. Tujuan Produk

## 3.1 Tujuan Utama

Membangun sistem helpdesk internal yang:

- menerima aduan dari WhatsApp;
- membuat ticket secara otomatis;
- memberikan nomor ticket unik;
- mengklasifikasikan aduan;
- melakukan assignment kepada operator;
- menyediakan workflow status ticket;
- menyimpan seluruh riwayat aktivitas;
- mengirim notifikasi melalui WhatsApp;
- menyediakan dashboard monitoring;
- menyediakan laporan;
- memiliki audit trail;
- dapat dikembangkan untuk jenis layanan baru.

## 3.2 Tujuan Bisnis

Sistem diharapkan dapat:

- mempercepat respon terhadap pegawai;
- mengurangi ticket yang terlewat;
- meningkatkan transparansi penanganan;
- memberikan data performa layanan TI;
- membantu evaluasi SLA;
- menyediakan data historis untuk pengambilan keputusan.

---

# 4. Non-Goals

Versi awal sistem **tidak mencakup**:

- WhatsApp Cloud API resmi Meta;
- sistem HRIS;
- sistem payroll;
- sistem absensi utama;
- network monitoring/NMS;
- remote desktop;
- inventory asset management penuh;
- sistem procurement;
- public complaint management.

Integrasi dengan sistem-sistem tersebut dapat dipertimbangkan pada fase berikutnya.

---

# 5. Tech Stack

## 5.1 Backend

- PHP
- CodeIgniter 4
- MySQL
- REST API internal
- CodeIgniter CLI untuk scheduled/background jobs bila diperlukan

## 5.2 Frontend

- Bootstrap 5.3
- Xakti Admin Template
- JavaScript
- DataTables.net
- Chart.js bila diperlukan untuk visualisasi dashboard
- AJAX/fetch untuk komunikasi asynchronous

## 5.3 WhatsApp

- OpenWA
- WhatsApp Web engine yang digunakan oleh OpenWA
- REST API OpenWA
- Webhook OpenWA

OpenWA digunakan sebagai **WhatsApp Gateway**, bukan sebagai database utama aplikasi ticketing.

## 5.4 Database

MySQL.

Database ticketing harus menjadi **source of truth** untuk:

- ticket;
- user/pelapor;
- kategori;
- assignment;
- status;
- priority;
- SLA;
- conversation metadata;
- activity log;
- audit log.

---

# 6. Prinsip Arsitektur

Sistem harus menerapkan prinsip:

> **Configuration over hard-coding.**

Contoh:

Jangan membuat:

```php
if ($category === 'jaringan') {
    ...
} elseif ($category === 'absensi') {
    ...
}
```

Sebagai gantinya, kategori disimpan di database:

```text
categories
    id
    name
    code
    description
    icon
    color
    is_active
```

Dengan demikian admin dapat menambahkan:

```text
Jaringan
Absensi
Email
Aplikasi Internal
Perangkat
VPN
Website
Keamanan Informasi
Lainnya
```

tanpa mengubah core ticket engine.

---

# 7. Aktor Sistem

## 7.1 Super Admin

Memiliki akses penuh.

Kemampuan:

- mengelola user;
- mengelola role;
- mengelola kategori;
- mengelola jenis layanan;
- mengelola status;
- mengelola prioritas;
- mengelola SLA;
- konfigurasi WhatsApp;
- melihat seluruh ticket;
- mengakses laporan;
- melihat audit log;
- konfigurasi sistem.

## 7.2 Admin

Memiliki akses administratif operasional.

Kemampuan:

- melihat ticket;
- membuat ticket manual;
- mengubah kategori;
- mengubah prioritas;
- melakukan assignment;
- mengubah status;
- berkomunikasi dengan pelapor;
- melihat dashboard;
- membuat laporan.

## 7.3 Operator / Teknisi

Fokus pada penanganan ticket.

Kemampuan:

- melihat ticket yang ditugaskan;
- melihat detail ticket;
- menambahkan komentar;
- mengubah status sesuai permission;
- mengirim pesan ke pelapor;
- mencatat solusi;
- menutup ticket.

## 7.4 Supervisor

Kemampuan:

- monitoring seluruh ticket;
- melihat SLA;
- melakukan assignment/reassignment;
- melakukan eskalasi;
- melihat laporan;
- melihat performa operator.

## 7.5 Pegawai / Pelapor

Tidak wajib memiliki akun web.

Pelaporan dilakukan melalui WhatsApp.

Identitas pelapor ditentukan berdasarkan nomor WhatsApp dan/atau data pegawai yang terdaftar.

---

# 8. Konsep Ticket

Setiap aduan yang valid harus memiliki ticket.

Contoh:

```text
TCK-20261007-0001
```

Format nomor ticket configurable, tetapi minimal harus:

- unik;
- mudah dibaca;
- mudah disebutkan melalui WhatsApp;
- mudah dicari oleh operator.

## 8.1 Informasi Ticket

Ticket minimal memiliki:

| Field | Keterangan |
|---|---|
| ID | Primary key |
| Ticket Number | Nomor ticket |
| Reporter | Pegawai pelapor |
| Channel | WhatsApp/Web/Admin |
| Category | Kategori aduan |
| Service Type | Jenis layanan |
| Subject | Ringkasan masalah |
| Description | Detail masalah |
| Priority | Prioritas |
| Status | Status ticket |
| Assignee | Operator |
| Team | Tim penanganan |
| SLA | SLA yang berlaku |
| Created At | Waktu dibuat |
| First Response At | Respon pertama |
| Resolved At | Waktu solusi |
| Closed At | Waktu ditutup |
| Due At | Batas SLA |
| Resolution | Solusi |
| Rating | Penilaian jika diaktifkan |

---

# 9. Channel Ticket

Ticket tidak boleh hanya bergantung pada WhatsApp.

Gunakan struktur channel:

```text
whatsapp
web
admin
api
```

Dengan demikian pada masa depan dapat ditambahkan:

```text
email
telegram
mobile
```

Tanpa mengubah ticket engine.

---

# 10. Kategori Aduan

Kategori merupakan master data.

Contoh awal:

### Infrastruktur

- Jaringan Internet
- WiFi
- LAN
- VPN

### Aplikasi

- Sistem Absensi
- Aplikasi Internal
- Website

### Hardware

- Komputer
- Printer
- Scanner
- Perangkat jaringan

### Lainnya

- Permintaan informasi
- Lainnya

Admin dapat membuat kategori baru.

---

# 11. Service Type

Kategori dan jenis layanan sebaiknya dipisahkan.

Contoh:

```text
Category:
Jaringan

Service:
Internet
WiFi
LAN
VPN
```

atau:

```text
Category:
Absensi

Service:
Mesin Absensi
Aplikasi Absensi
Data Kehadiran
Sinkronisasi Absensi
```

Struktur ini memungkinkan satu kategori memiliki banyak jenis layanan.

---

# 12. Dynamic Custom Fields

Agar aplikasi benar-benar extensible, setiap service dapat memiliki field tambahan.

Contoh service:

## Jaringan

Field:

```text
Lokasi
Ruangan
Nama perangkat
SSID
Jenis koneksi
Nomor kontak
```

## Absensi

Field:

```text
Tanggal kejadian
Jam kejadian
Lokasi
Jenis masalah
NIP
```

Field disimpan sebagai konfigurasi.

Contoh:

```text
custom_fields
    id
    service_type_id
    field_name
    field_key
    field_type
    is_required
    options
    sort_order
    is_active
```

`field_type` dapat berupa:

```text
text
textarea
number
date
datetime
select
radio
checkbox
file
```

Nilai custom field disimpan terpisah dari tabel ticket.

---

# 13. Workflow Ticket

Workflow default:

```text
NEW
 ↓
TRIAGED
 ↓
ASSIGNED
 ↓
IN_PROGRESS
 ↓
WAITING_REPORTER
 ↓
RESOLVED
 ↓
CLOSED
```

Status tambahan dapat dibuat melalui master data.

Contoh:

```text
REOPENED
ESCALATED
CANCELLED
DUPLICATE
```

## 13.1 Aturan Status

### NEW

Ticket baru diterima.

### TRIAGED

Ticket sudah diperiksa dan dikategorikan.

### ASSIGNED

Ticket telah diberikan kepada operator/team.

### IN_PROGRESS

Ticket sedang dikerjakan.

### WAITING_REPORTER

Menunggu informasi dari pelapor.

### RESOLVED

Masalah telah diberikan solusi.

### CLOSED

Ticket selesai dan tidak memerlukan tindakan lanjutan.

### REOPENED

Ticket dibuka kembali karena masalah belum selesai/terjadi kembali.

---

# 14. Status Tidak Hard-coded

Status ticket harus berasal dari database.

Contoh:

```text
ticket_statuses
    id
    code
    name
    description
    color
    is_initial
    is_final
    sort_order
    is_active
```

Workflow transition dapat dibuat configurable:

```text
ticket_status_transitions
    id
    from_status_id
    to_status_id
    allowed_role_id
```

Dengan demikian workflow dapat berkembang tanpa perubahan besar pada controller.

---

# 15. Priority

Minimal:

```text
LOW
MEDIUM
HIGH
CRITICAL
```

Priority juga merupakan master data.

Contoh:

| Priority | Contoh |
|---|---|
| Low | Pertanyaan/informasi |
| Medium | Gangguan satu pengguna |
| High | Gangguan beberapa pengguna |
| Critical | Gangguan layanan penting/masif |

Priority tidak boleh menjadi enum database yang sulit diubah.

---

# 16. SLA

Setiap service/priority dapat memiliki SLA.

Contoh:

| Priority | Response | Resolution |
|---|---:|---:|
| Low | 8 jam | 2 hari |
| Medium | 4 jam | 1 hari |
| High | 1 jam | 4 jam |
| Critical | 15 menit | 2 jam |

SLA harus configurable.

Struktur:

```text
sla_policies
    id
    name
    service_type_id
    priority_id
    response_minutes
    resolution_minutes
    business_hours_only
    is_active
```

Sistem menghitung:

```text
first_response_due_at
resolution_due_at
```

---

# 17. SLA Monitoring

Dashboard harus menunjukkan:

- ticket dalam SLA;
- ticket mendekati SLA;
- ticket overdue;
- rata-rata response time;
- rata-rata resolution time;
- SLA compliance rate.

Contoh indikator:

```text
SLA Compliance
92.4%
```

---

# 18. WhatsApp Integration

WhatsApp digunakan sebagai channel utama pelaporan.

Arsitektur:

```text
Pegawai
   │
   ▼
WhatsApp
   │
   ▼
OpenWA
   │
   │ Webhook
   ▼
CodeIgniter 4
   │
   ├── Message Parser
   ├── Reporter Resolver
   ├── Ticket Engine
   ├── Notification Engine
   │
   ▼
MySQL
```

---

# 19. OpenWA sebagai Gateway

OpenWA harus diperlakukan sebagai service terpisah dari aplikasi CodeIgniter.

Contoh deployment:

```text
                ┌─────────────────┐
                │    WhatsApp     │
                └────────┬────────┘
                         │
                         ▼
                ┌─────────────────┐
                │     OpenWA      │
                │ WhatsApp Gateway│
                └────────┬────────┘
                         │
                     Webhook
                         │
                         ▼
                ┌─────────────────┐
                │  CodeIgniter 4  │
                │ Ticketing App   │
                └────────┬────────┘
                         │
                         ▼
                     MySQL
```

OpenWA tidak boleh menjadi sumber data utama ticket.

---

# 20. Incoming WhatsApp Message

Ketika pesan masuk:

```text
message.received
```

sistem melakukan:

1. Validasi webhook.
2. Identifikasi WhatsApp number.
3. Cari pegawai berdasarkan nomor.
4. Simpan raw message/event.
5. Tentukan apakah pesan merupakan ticket baru atau balasan ticket.
6. Parse command/intent bila diperlukan.
7. Buat atau update ticket.
8. Kirim acknowledgement.
9. Masukkan aktivitas ke timeline.
10. Notifikasi operator jika diperlukan.

---

# 21. Identifikasi Pegawai

Nomor WhatsApp menjadi identifier utama.

Contoh:

```text
628123456789
```

Database pegawai minimal:

```text
employees
    id
    employee_number
    name
    whatsapp_number
    email
    department_id
    position
    is_active
```

Normalisasi nomor harus dilakukan.

Contoh:

```text
08123456789
+628123456789
628123456789
```

harus dapat dianggap sebagai nomor yang sama setelah normalization.

---

# 22. Reporter yang Belum Terdaftar

Jika nomor WhatsApp tidak ditemukan:

Bot memberikan response:

```text
Nomor Anda belum terdaftar sebagai pegawai KOMINFO PINRANG.

Silakan hubungi administrator untuk melakukan registrasi.
```

Alternatif yang dapat dipilih pada fase selanjutnya:

- self-registration;
- OTP;
- validasi NIP;
- validasi data kepegawaian.

Untuk MVP, **tidak membuat ticket dari nomor yang belum terverifikasi** apabila kebijakan keamanan internal mengharuskannya.

---

# 23. Mekanisme Pembuatan Aduan

Sistem harus mendukung dua mode.

## Mode A — Free Text

Pegawai cukup mengirim:

```text
Internet di ruang pelayanan tidak bisa.
```

Sistem membuat:

```text
Ticket: TCK-20261007-0001
Category: Jaringan
Status: NEW
Reporter: Nama Pegawai
Description:
Internet di ruang pelayanan tidak bisa.
```

Kategori dapat ditentukan operator jika confidence parsing rendah.

## Mode B — Guided Flow

Bot meminta informasi:

```text
Pilih jenis aduan:

1. Jaringan
2. Absensi
3. Aplikasi
4. Perangkat
5. Lainnya
```

Kemudian:

```text
Silakan jelaskan masalah Anda.
```

Kemudian:

```text
Di lokasi/ruangan mana masalah terjadi?
```

Setelah data lengkap:

```text
Aduan Anda telah dibuat.

No. Ticket: TCK-20261007-0001
Kategori: Jaringan
Prioritas: Medium
Status: Menunggu penanganan.
```

Untuk MVP, guided flow lebih disarankan karena data ticket lebih konsisten.

---

# 24. Ticket Thread melalui WhatsApp

Setiap ticket memiliki conversation/thread.

Contoh:

```text
TCK-20261007-0001
```

Pesan berikutnya dari nomor tersebut dapat dikaitkan ke ticket aktif.

Namun sistem harus menyediakan command eksplisit:

```text
#TCK-20261007-0001
```

agar pelapor dapat memastikan pesan ditujukan ke ticket tertentu.

Contoh:

```text
#TCK-20261007-0001

Masih belum bisa digunakan.
```

Sistem mengaitkan pesan dengan ticket tersebut.

---

# 25. Multiple Active Tickets

Seorang pegawai dapat memiliki lebih dari satu ticket aktif.

Karena itu sistem **tidak boleh menganggap semua pesan baru otomatis masuk ke ticket terakhir**.

Jika terdapat lebih dari satu ticket aktif:

```text
Anda memiliki beberapa ticket aktif:

1. TCK-20261007-0001 - Internet
2. TCK-20261006-0021 - Absensi

Balas dengan nomor ticket.
```

---

# 26. Outgoing WhatsApp

Sistem dapat mengirim:

### Ticket Created

```text
Aduan Anda telah diterima.

Nomor Ticket: TCK-20261007-0001
Kategori: Jaringan
Status: Baru

Tim KOMINFO akan menindaklanjuti aduan Anda.
```

### Assignment

```text
Ticket TCK-20261007-0001 sedang ditangani oleh tim teknis.
```

### Status Update

```text
Update Ticket TCK-20261007-0001

Status: Sedang Ditangani
```

### Resolved

```text
Ticket TCK-20261007-0001 telah ditangani.

Solusi:
Konfigurasi ulang perangkat jaringan.

Apakah masalah sudah terselesaikan?

1. Ya
2. Belum
```

---

# 27. Conversation / Message Storage

Semua pesan yang relevan harus disimpan.

Struktur konseptual:

```text
ticket_messages
    id
    ticket_id
    channel
    external_message_id
    direction
    sender
    recipient
    message_type
    body
    media_url
    metadata
    sent_at
    received_at
```

`external_message_id` harus memiliki unique constraint untuk mencegah duplicate webhook menyebabkan duplicate message.

---

# 28. Webhook Reliability

Webhook dari OpenWA harus diproses secara idempotent.

Jika webhook yang sama dikirim dua kali:

```text
external_message_id = abc123
```

sistem hanya boleh membuat satu record.

Gunakan:

```text
UNIQUE(channel, external_message_id)
```

atau mekanisme idempotency key.

---

# 29. Webhook Security

Endpoint webhook harus memiliki proteksi:

- secret/token;
- signature verification jika tersedia;
- IP allowlist bila environment memungkinkan;
- rate limiting;
- request logging;
- payload validation;
- replay protection bila memungkinkan.

Contoh:

```text
POST /api/webhooks/openwa
```

Webhook tidak boleh dapat membuat ticket melalui request arbitrary tanpa validasi.

---

# 30. Dashboard Admin

Dashboard utama harus menampilkan kondisi ticket secara real-time/near real-time.

## KPI Cards

Minimal:

```text
Total Ticket
Ticket Baru
Sedang Ditangani
Menunggu
Overdue
Selesai Hari Ini
```

Contoh:

```text
┌────────────┐ ┌────────────┐ ┌────────────┐
│  Total 248 │ │ Baru  32   │ │ Progress 71│
└────────────┘ └────────────┘ └────────────┘

┌────────────┐ ┌────────────┐ ┌────────────┐
│ Waiting 18 │ │ Overdue  7 │ │ Closed 120 │
└────────────┘ └────────────┘ └────────────┘
```

---

# 31. Dashboard Charts

Minimal:

### Ticket per Hari

Line chart:

```text
Jumlah Ticket
    │
    │       ╭──╮
    │   ╭───╯  ╰──╮
    │───╯         ╰──
    └─────────────────
       Sen  Sel  Rab
```

### Ticket berdasarkan Kategori

Pie/doughnut:

```text
Jaringan     42%
Absensi      28%
Aplikasi     15%
Hardware     10%
Lainnya       5%
```

### Ticket berdasarkan Status

Bar chart.

### Ticket berdasarkan Priority

Bar chart.

### Performance Operator

Tampilkan:

- jumlah ticket;
- resolved;
- average response;
- average resolution;
- overdue.

---

# 32. Filter Dashboard

Dashboard harus memiliki filter:

- periode;
- kategori;
- service;
- status;
- priority;
- operator/team;
- channel.

Contoh:

```text
[7 Hari ▼] [Semua Kategori ▼] [Semua Operator ▼]
```

---

# 33. Ticket List

Menggunakan **DataTables.net**.

Kolom:

| Kolom |
|---|
| Ticket |
| Created |
| Reporter |
| Category |
| Service |
| Priority |
| Status |
| Assignee |
| SLA |
| Last Update |
| Action |

Fitur:

- server-side processing;
- search;
- sorting;
- pagination;
- filtering;
- column visibility;
- export;
- responsive;
- bulk action jika diperlukan.

Untuk data besar, gunakan **server-side DataTables**, jangan memuat seluruh ticket ke browser.

---

# 34. Ticket Detail

Halaman detail ticket menggunakan layout:

```text
┌────────────────────────────────────────────┐
│ TCK-20261007-0001             [HIGH]       │
│ Internet kantor tidak dapat digunakan      │
├────────────────────────────────────────────┤
│ Reporter       │ Status       │ Assignee   │
│ Budi           │ IN PROGRESS  │ Andi       │
├────────────────────────────────────────────┤
│                                             │
│ Timeline / Conversation                     │
│                                             │
│ Reporter                                    │
│ Internet tidak bisa                         │
│                                             │
│ Operator                                    │
│ Kami sedang melakukan pengecekan.           │
│                                             │
├────────────────────────────────────────────┤
│ [Tambahkan komentar...]                     │
└────────────────────────────────────────────┘
```

---

# 35. Ticket Timeline

Semua aktivitas penting harus masuk timeline:

```text
08:10 Ticket dibuat
08:11 Kategori: Jaringan
08:12 Priority: Medium
08:15 Assigned ke Andi
08:20 Operator mengirim pesan
09:00 Status → In Progress
10:15 Status → Resolved
10:20 Reporter mengonfirmasi
10:21 Status → Closed
```

Timeline merupakan komponen penting untuk audit dan monitoring.

---

# 36. Assignment

Ticket dapat di-assign kepada:

- user;
- team.

Contoh:

```text
Team:
Network Support

Operator:
Andi
```

Jika team digunakan, ticket dapat masuk queue team terlebih dahulu.

---

# 37. Auto Assignment

Pada fase lanjutan dapat dibuat rule:

```text
Category = Jaringan
→ Team = Network Support
```

```text
Category = Absensi
→ Team = Application Support
```

Struktur rule dapat dibuat configurable:

```text
assignment_rules
    id
    category_id
    service_type_id
    priority_id
    team_id
    user_id
    is_active
```

---

# 38. Escalation

Ticket dapat dieskalasi jika:

- SLA hampir habis;
- SLA terlewati;
- priority Critical;
- operator meminta bantuan;
- ticket membutuhkan approval supervisor.

Contoh:

```text
Ticket HIGH overdue 30 menit
        ↓
Supervisor notification
        ↓
Escalation
```

---

# 39. Notification Engine

Notifikasi harus dipisahkan dari business logic ticket.

Event:

```text
ticket.created
ticket.assigned
ticket.status_changed
ticket.priority_changed
ticket.sla_warning
ticket.sla_breached
ticket.resolved
ticket.closed
ticket.reopened
```

Channel:

```text
whatsapp
web
email (future)
```

Dengan pendekatan ini channel notifikasi dapat bertambah.

---

# 40. Notification Templates

Template pesan tidak boleh hard-coded di controller.

Buat master:

```text
notification_templates
    id
    event
    channel
    subject
    body
    is_active
```

Contoh:

```text
event:
ticket.created

channel:
whatsapp
```

Body:

```text
Aduan Anda telah diterima.

Ticket: {{ticket_number}}
Kategori: {{category}}
Status: {{status}}
```

---

# 41. Template Variables

Minimal mendukung:

```text
{{ticket_number}}
{{reporter_name}}
{{category}}
{{service}}
{{priority}}
{{status}}
{{assignee_name}}
{{created_at}}
{{resolution}}
```

---

# 42. Master Data

Admin menu harus menyediakan:

### Organisasi

- Department
- Unit kerja
- Jabatan

### Ticket

- Category
- Service Type
- Status
- Priority
- SLA
- Tags

### Assignment

- Team
- Operator
- Assignment Rule

### System

- Notification Template
- WhatsApp Configuration
- General Settings

---

# 43. Tags

Ticket dapat memiliki banyak tag.

Contoh:

```text
internet
urgent
lantai-2
wifi
```

Tags berguna untuk filtering dan reporting tanpa mengubah kategori utama.

---

# 44. Attachment

Ticket dapat memiliki attachment.

Contoh:

- screenshot;
- foto perangkat;
- dokumen;
- bukti error.

Metadata:

```text
ticket_attachments
    id
    ticket_id
    message_id
    filename
    mime_type
    size
    storage_path
    uploaded_by
    created_at
```

Media WhatsApp dari OpenWA perlu dipertimbangkan secara khusus karena OpenWA dapat mengembalikan media kepada consumer/webhook dan tidak otomatis menjadikannya persistent storage aplikasi ticketing.

---

# 45. Storage

File attachment sebaiknya tidak disimpan langsung sebagai BLOB MySQL.

Gunakan:

```text
storage/
    tickets/
        2026/
            10/
                TCK-20261007-0001/
```

Untuk deployment awal dapat menggunakan local storage.

Fase berikutnya dapat mendukung:

- S3;
- MinIO;
- object storage lainnya.

---

# 46. Search

Search ticket minimal berdasarkan:

- ticket number;
- nama pegawai;
- nomor WhatsApp;
- NIP/NIK internal;
- subject;
- description;
- category;
- status;
- assignee.

Untuk pencarian cepat, index database harus disiapkan.

---

# 47. Reporting

Laporan minimal:

## Ticket Report

Filter:

- tanggal;
- kategori;
- service;
- priority;
- status;
- operator.

Export:

- Excel;
- CSV;
- PDF bila diperlukan.

## SLA Report

Menampilkan:

- total ticket;
- within SLA;
- breached;
- compliance percentage;
- average response;
- average resolution.

## Operator Performance

Menampilkan:

- ticket assigned;
- ticket resolved;
- ticket closed;
- average response;
- average resolution;
- overdue.

---

# 48. Audit Log

Semua perubahan penting harus dicatat.

Contoh:

```text
audit_logs
    id
    user_id
    action
    entity_type
    entity_id
    old_values
    new_values
    ip_address
    user_agent
    created_at
```

Contoh event:

```text
Ticket assigned
Ticket priority changed
Ticket status changed
User created
Category deleted
SLA changed
WhatsApp configuration changed
```

Audit log tidak boleh dapat diedit oleh user biasa.

---

# 49. Authentication

Admin web menggunakan authentication CodeIgniter 4.

Minimal:

- login;
- logout;
- session management;
- password hashing;
- forgot password;
- change password.

Password wajib disimpan menggunakan hashing yang aman.

---

# 50. RBAC

Gunakan Role Based Access Control.

Contoh permission:

```text
dashboard.view

ticket.view
ticket.create
ticket.update
ticket.assign
ticket.delete
ticket.change_status
ticket.change_priority
ticket.close
ticket.reopen

report.view
report.export

master.category.view
master.category.create
master.category.update
master.category.delete

user.view
user.create
user.update
user.delete

settings.view
settings.update

audit.view
```

Jangan melakukan authorization hanya berdasarkan:

```php
if ($user->role === 'admin')
```

Gunakan permission.

---

# 51. Soft Delete

Data master penting sebaiknya menggunakan soft delete atau status aktif/nonaktif.

Contoh:

```text
categories.is_active
services.is_active
users.is_active
```

Kategori lama tidak boleh dihapus secara fisik jika sudah digunakan oleh ticket.

---

# 52. Database Design

Struktur konseptual:

```text
users
roles
permissions
role_permissions

employees
departments

teams
team_members

categories
service_types

ticket_statuses
ticket_status_transitions
priorities
sla_policies

tickets
ticket_custom_values
ticket_tags
tags

ticket_assignments
ticket_messages
ticket_attachments
ticket_activities

notification_templates
notification_logs

whatsapp_sessions
whatsapp_webhook_logs

audit_logs

system_settings
```

---

# 53. Relasi Utama

```text
employees
    │
    └──< tickets
            │
            ├── category
            ├── service_type
            ├── priority
            ├── status
            ├── assignments
            ├── messages
            ├── attachments
            ├── activities
            ├── custom_values
            └── tags
```

---

# 54. Ticket Table

Contoh struktur:

```text
tickets
------------------------------
id BIGINT
ticket_number VARCHAR(50)
reporter_id BIGINT
channel VARCHAR(30)
category_id BIGINT
service_type_id BIGINT
priority_id BIGINT
status_id BIGINT
subject VARCHAR(255)
description TEXT
first_response_at DATETIME NULL
due_response_at DATETIME NULL
due_resolution_at DATETIME NULL
resolved_at DATETIME NULL
closed_at DATETIME NULL
created_at DATETIME
updated_at DATETIME
deleted_at DATETIME NULL
```

Index minimal:

```text
ticket_number
reporter_id
category_id
service_type_id
priority_id
status_id
created_at
due_resolution_at
```

---

# 55. Ticket Activity

Gunakan event/activity log untuk histori.

Contoh:

```text
ticket_activities
------------------------------
id
ticket_id
user_id
activity_type
description
metadata
created_at
```

`activity_type`:

```text
created
assigned
reassigned
status_changed
priority_changed
commented
message_sent
message_received
attachment_added
resolved
closed
reopened
escalated
```

---

# 56. API Architecture

CodeIgniter 4 menyediakan API internal.

Contoh:

```text
/api/auth/...

/api/tickets
/api/tickets/{id}
/api/tickets/{id}/status
/api/tickets/{id}/assign
/api/tickets/{id}/messages
/api/tickets/{id}/attachments

/api/categories
/api/services
/api/statuses
/api/priorities

/api/reports/...

/api/webhooks/openwa
```

---

# 57. OpenWA Integration Service

Jangan memanggil OpenWA langsung dari banyak controller.

Gunakan service:

```text
OpenWAService
```

Contoh:

```php
$openwa->sendMessage(
    $phoneNumber,
    $message
);
```

Service bertanggung jawab terhadap:

- API authentication;
- HTTP request;
- timeout;
- retry;
- error handling;
- logging;
- response normalization.

---

# 58. OpenWA Configuration

Configuration:

```text
OPENWA_BASE_URL
OPENWA_API_KEY
OPENWA_SESSION_ID
OPENWA_WEBHOOK_SECRET
OPENWA_TIMEOUT
```

Secret tidak boleh disimpan di repository.

Gunakan:

```text
.env
```

---

# 59. WhatsApp Connection Monitoring

Dashboard menyediakan status:

```text
WhatsApp Gateway
● Connected
```

atau:

```text
● Disconnected
```

Informasi:

- session;
- connection status;
- last webhook;
- last successful message;
- failed messages.

---

# 60. Outgoing Message Queue

Pengiriman WhatsApp tidak sebaiknya selalu dilakukan secara synchronous di request web.

Gunakan queue/job bila infrastructure memungkinkan.

Flow:

```text
Ticket event
     ↓
Notification Job
     ↓
Message Queue
     ↓
OpenWA
     ↓
WhatsApp
```

Jika queue belum tersedia pada MVP, implementasikan service abstraction agar queue dapat ditambahkan kemudian.

---

# 61. Retry Mechanism

Jika OpenWA gagal:

```text
Attempt 1
   ↓
failed
   ↓
Attempt 2
   ↓
failed
   ↓
Attempt 3
   ↓
failed
   ↓
message_failed
```

Simpan:

```text
notification_logs
    status
    attempt_count
    error_message
    last_attempt_at
```

---

# 62. Error Handling

Error OpenWA tidak boleh membuat ticket gagal dibuat.

Contoh:

```text
Ticket berhasil dibuat
+
WhatsApp notification gagal
```

Ticket tetap:

```text
status = NEW
```

Notification:

```text
status = FAILED
```

Kemudian sistem dapat retry.

---

# 63. DataTables Server-side

Karena jumlah ticket dapat terus bertambah, endpoint DataTables harus menggunakan server-side processing.

Contoh:

```text
GET /admin/tickets/datatables
```

Parameter:

```text
draw
start
length
search[value]
order
columns
```

Backend melakukan query:

```text
WHERE
    ticket_number LIKE ...
    OR subject LIKE ...
    OR reporter.name LIKE ...
```

Pagination menggunakan:

```text
LIMIT
OFFSET
```

---

# 64. UI Guidelines

Gunakan Xakti Admin Template sebagai dasar.

Karakteristik:

- Bootstrap 5.3;
- responsive;
- light/dark mode bila diperlukan;
- sidebar;
- topbar;
- card;
- modal;
- toast;
- table;
- form;
- badge;
- dropdown.

Jangan melakukan modifikasi besar terhadap core template apabila cukup menggunakan Bootstrap classes dan CSS extension.

---

# 65. Status Badge

Gunakan warna yang konsisten.

Contoh:

```text
NEW             → primary
IN_PROGRESS     → info
WAITING         → warning
RESOLVED        → success
CLOSED          → secondary
OVERDUE         → danger
```

Warna harus configurable pada master status.

---

# 66. UX Ticket Priority

Critical harus mudah terlihat.

Contoh:

```text
🔴 CRITICAL
🟠 HIGH
🟡 MEDIUM
⚪ LOW
```

Namun jangan bergantung hanya pada warna; gunakan text/badge agar accessible.

---

# 67. Mobile Responsiveness

Admin dashboard harus usable pada:

- desktop;
- laptop;
- tablet;
- mobile.

Ticket detail harus tetap nyaman dibaca pada mobile.

WhatsApp tetap menjadi interface utama pegawai sehingga pegawai tidak wajib membuka website.

---

# 68. Security Requirements

Minimal:

- HTTPS;
- CSRF protection untuk web;
- authentication;
- authorization;
- password hashing;
- input validation;
- output escaping;
- SQL injection prevention melalui Query Builder/parameter binding;
- XSS prevention;
- upload validation;
- file size limitation;
- MIME validation;
- rate limiting;
- webhook authentication;
- API key protection;
- secret melalui environment variable;
- audit logging.

---

# 69. Sensitive Data

Data yang perlu dilindungi:

- nomor WhatsApp;
- data pegawai;
- NIP/employee number;
- isi percakapan;
- attachment;
- API key OpenWA;
- webhook secret.

API key dan secret tidak boleh muncul di:

- source code;
- Git repository;
- frontend;
- log biasa.

---

# 70. Logging

Application log minimal:

```text
INFO
WARNING
ERROR
CRITICAL
```

Log harus mencatat:

- timestamp;
- request ID;
- user;
- endpoint;
- response status;
- error.

Jangan mencatat API key/password atau isi data sensitif secara penuh.

---

# 71. Monitoring

System health minimal:

```text
Application
Database
OpenWA
WhatsApp Session
Webhook
Notification Queue
```

Dashboard health:

```text
Application       ● Healthy
Database          ● Healthy
OpenWA            ● Connected
WhatsApp          ● Connected
Webhook           ● Healthy
```

---

# 72. Backup

Database MySQL harus memiliki backup berkala.

Minimal:

```text
Daily backup
Weekly retention
```

Data penting:

- tickets;
- messages;
- employees;
- configuration;
- audit logs.

Attachment harus memiliki strategi backup tersendiri.

---

# 73. Retention

Kebijakan retention harus configurable.

Contoh awal:

```text
Ticket:
permanent / sesuai kebijakan instansi

Message:
sesuai kebijakan

Audit Log:
minimal 1 tahun

Attachment:
sesuai kebutuhan
```

Jangan melakukan automatic deletion sebelum kebijakan retention ditentukan.

---

# 74. Performance Requirements

Target awal:

- dashboard < 3 detik pada kondisi normal;
- ticket list menggunakan server-side pagination;
- API response normal < 1 detik di jaringan internal;
- webhook acknowledgement cepat;
- proses berat tidak dilakukan di webhook request;
- index database disiapkan sejak awal.

Webhook sebaiknya segera mengembalikan:

```text
HTTP 200
```

setelah payload tervalidasi dan dipersist, sementara proses lanjutan dilakukan asynchronous jika infrastructure memungkinkan.

---

# 75. Availability

Target MVP:

```text
Business Hours Availability
≥ 99%
```

Untuk fase production dapat ditingkatkan dengan:

- health check;
- restart policy;
- monitoring;
- database backup;
- redundant OpenWA/session strategy bila diperlukan.

---

# 76. MVP Scope

## P0 — Wajib

### Authentication

- Login
- Logout
- Role
- Permission

### Ticket

- Create
- Read
- Update
- Assignment
- Status
- Priority
- Timeline
- Search
- Filter

### WhatsApp

- OpenWA integration
- Incoming webhook
- Create ticket from WhatsApp
- Send acknowledgement
- Send status update
- Ticket reference

### Master

- Employee
- Category
- Service
- Status
- Priority
- Team
- User

### Dashboard

- Total ticket
- Ticket by status
- Ticket by category
- Ticket by priority
- Ticket trend

### Report

- Ticket report
- Basic export

---

# 77. P1 — Setelah MVP

- SLA engine;
- SLA warning;
- SLA breach;
- escalation;
- auto assignment;
- custom fields;
- attachment;
- advanced notification templates;
- operator performance;
- advanced reporting;
- audit dashboard.

---

# 78. P2 — Future

- AI classification;
- sentiment detection;
- automatic priority;
- automatic service classification;
- knowledge base;
- FAQ bot;
- suggested resolution;
- integration dengan sistem absensi;
- integration dengan HRIS;
- email channel;
- mobile application;
- public portal;
- SSO;
- advanced analytics.

---

# 79. AI Classification Future

AI tidak boleh menjadi dependency utama ticket engine.

Flow:

```text
WhatsApp Message
       ↓
AI Classifier
       ↓
Category
Service
Priority
Summary
       ↓
Ticket Engine
```

Jika AI gagal:

```text
Ticket tetap dibuat
```

Operator dapat melakukan koreksi.

---

# 80. Acceptance Criteria — WhatsApp

### AC-WA-001

**Given** pegawai terdaftar mengirim pesan WhatsApp  
**When** webhook OpenWA diterima  
**Then** sistem membuat ticket baru.

### AC-WA-002

Ticket mendapatkan nomor unik.

### AC-WA-003

Pelapor menerima acknowledgement.

### AC-WA-004

Webhook duplicate tidak membuat ticket duplicate.

### AC-WA-005

Pesan selanjutnya dapat dikaitkan ke ticket.

### AC-WA-006

Operator dapat membalas melalui dashboard.

### AC-WA-007

Balasan operator dikirim melalui OpenWA.

---

# 81. Acceptance Criteria — Ticket

### AC-TICKET-001

Admin dapat melihat seluruh ticket yang memiliki akses.

### AC-TICKET-002

Operator hanya dapat melihat ticket sesuai permission/team yang diberikan.

### AC-TICKET-003

Ticket memiliki status.

### AC-TICKET-004

Perubahan status tercatat pada timeline.

### AC-TICKET-005

Assignment tercatat pada timeline.

### AC-TICKET-006

Ticket dapat di-search berdasarkan nomor.

### AC-TICKET-007

Ticket dapat difilter berdasarkan category/status/priority/operator.

---

# 82. Acceptance Criteria — Dynamic Category

### AC-DYNAMIC-001

Admin dapat membuat kategori baru.

### AC-DYNAMIC-002

Kategori baru dapat dipilih pada ticket.

### AC-DYNAMIC-003

Kategori lama tidak menyebabkan ticket historis rusak.

### AC-DYNAMIC-004

Kategori dapat dinonaktifkan tanpa menghapus ticket lama.

---

# 83. Acceptance Criteria — Dashboard

Dashboard harus dapat menampilkan:

```text
Total
New
In Progress
Waiting
Resolved
Closed
Overdue
```

dan filter berdasarkan periode.

Angka dashboard harus konsisten dengan data ticket.

---

# 84. Acceptance Criteria — RBAC

User tanpa permission:

```text
ticket.delete
```

tidak dapat melakukan delete walaupun mengetahui URL endpoint.

Authorization harus diterapkan pada server-side.

---

# 85. Acceptance Criteria — Audit

Perubahan:

```text
status
priority
category
assignee
```

harus tercatat.

Contoh:

```text
Andi mengubah status
NEW → IN_PROGRESS
07 Oct 2026 08:30
```

---

# 86. Definition of Done

Sebuah fitur dianggap selesai apabila:

- backend selesai;
- validation tersedia;
- authorization tersedia;
- database migration tersedia;
- UI tersedia;
- error handling tersedia;
- audit/activity tersedia jika relevan;
- test tersedia;
- dokumentasi API tersedia jika endpoint baru;
- responsive;
- tidak menghasilkan error pada log;
- tidak merusak fitur existing.

---

# 87. Development Structure

Rekomendasi struktur CodeIgniter 4:

```text
app/
├── Config/
├── Controllers/
│   ├── Admin/
│   ├── Api/
│   └── Webhooks/
│
├── Models/
│   ├── TicketModel.php
│   ├── TicketMessageModel.php
│   ├── CategoryModel.php
│   └── ...
│
├── Services/
│   ├── TicketService.php
│   ├── TicketWorkflowService.php
│   ├── OpenWAService.php
│   ├── NotificationService.php
│   ├── SLAService.php
│   └── AssignmentService.php
│
├── Libraries/
│
├── Filters/
│   ├── AuthFilter.php
│   └── PermissionFilter.php
│
├── Entities/
│
└── Views/
    └── admin/
```

Business logic harus berada pada Service Layer, bukan menumpuk di Controller.

---

# 88. Recommended Service Layer

Minimal:

```text
TicketService
```

Untuk:

- create ticket;
- update ticket;
- assign;
- close;
- reopen.

```text
TicketWorkflowService
```

Untuk:

- status transition;
- validation;
- permission.

```text
OpenWAService
```

Untuk:

- send message;
- get session status;
- webhook integration.

```text
NotificationService
```

Untuk:

- notification;
- template rendering;
- retry.

```text
SLAService
```

Untuk:

- calculate due date;
- SLA monitoring;
- breach.

```text
AssignmentService
```

Untuk:

- manual assignment;
- automatic assignment;
- team routing.

---

# 89. Migration Strategy

Semua database schema menggunakan migration CodeIgniter 4.

Contoh:

```text
001_create_users
002_create_roles
003_create_permissions
004_create_employees
005_create_categories
006_create_services
007_create_ticket_statuses
008_create_priorities
009_create_sla_policies
010_create_tickets
011_create_ticket_messages
012_create_ticket_activities
013_create_attachments
014_create_notification_templates
015_create_audit_logs
```

Jangan mengandalkan SQL database manual pada production.

---

# 90. Seed Data

Seeder awal menyediakan:

### Roles

```text
super_admin
admin
supervisor
operator
```

### Categories

```text
Jaringan
Absensi
Aplikasi
Perangkat
Lainnya
```

### Status

```text
New
Triaged
Assigned
In Progress
Waiting Reporter
Resolved
Closed
Reopened
```

### Priority

```text
Low
Medium
High
Critical
```

---

# 91. Environment

Contoh `.env`:

```dotenv
CI_ENVIRONMENT=production

app.baseURL=https://ticketing.example.go.id/

database.default.hostname=127.0.0.1
database.default.database=ticketing
database.default.username=...
database.default.password=...
database.default.DBDriver=MySQLi

OPENWA_BASE_URL=http://openwa:2785
OPENWA_API_KEY=...
OPENWA_SESSION_ID=...
OPENWA_WEBHOOK_SECRET=...

UPLOAD_MAX_SIZE=10485760
```

Secret wajib berada di environment/secret manager.

---

# 92. Deployment Architecture

Rekomendasi awal:

```text
                    Internet / LAN
                          │
                          ▼
                    Reverse Proxy
                     Nginx/Caddy
                          │
             ┌────────────┴────────────┐
             │                         │
             ▼                         ▼
       CodeIgniter 4                OpenWA
       Ticketing App               WhatsApp API
             │                         │
             │                         │
             ▼                         ▼
           MySQL                  WhatsApp
             │
             ▼
          Storage
```

OpenWA dan CodeIgniter sebaiknya menjadi service/container terpisah.

---

# 93. Separation of Responsibility

### CodeIgniter

Bertanggung jawab atas:

- ticket;
- user;
- employee;
- workflow;
- SLA;
- dashboard;
- reporting;
- database;
- business rules.

### OpenWA

Bertanggung jawab atas:

- WhatsApp session;
- connection;
- receiving message;
- sending message;
- WhatsApp transport.

### MySQL

Bertanggung jawab atas:

- persistent application data.

Jangan menjadikan OpenWA sebagai database ticketing.

---

# 94. Observability

Setiap request penting sebaiknya memiliki correlation/request ID.

Contoh:

```text
request_id:
req_01HXYZ...
```

ID ini dapat ditelusuri dari:

```text
Webhook
→ Ticket
→ Activity
→ Notification
→ OpenWA
```

Hal ini sangat membantu ketika operator melaporkan:

> "Ticket sudah dibuat tetapi WhatsApp tidak terkirim."

---

# 95. Business Rules Penting

1. Satu ticket memiliki satu reporter utama.
2. Ticket number harus unik.
3. Setiap incoming message harus memiliki external message ID.
4. Duplicate webhook tidak boleh membuat duplicate ticket/message.
5. Ticket yang sudah CLOSED tidak boleh menerima perubahan biasa tanpa REOPEN.
6. Perubahan status harus mengikuti workflow.
7. Semua perubahan penting dicatat.
8. Kategori/service dapat dinonaktifkan tanpa menghapus histori.
9. Penghapusan data ticket harus dibatasi ketat.
10. Notification failure tidak boleh membatalkan pembuatan ticket.
11. OpenWA API key tidak boleh dikirim ke browser.
12. Semua webhook harus divalidasi.
13. Semua endpoint API harus memiliki authorization.
14. Dashboard menggunakan server-side DataTables untuk data besar.
15. Business logic tidak boleh bergantung pada nama kategori seperti `"jaringan"` atau `"absensi"`.

---

# 96. KPI Sistem

KPI utama:

### Operational

```text
Total Ticket
Open Ticket
Closed Ticket
Overdue Ticket
Reopened Ticket
```

### Response

```text
Average First Response Time
Median First Response Time
```

### Resolution

```text
Average Resolution Time
Median Resolution Time
```

### SLA

```text
SLA Compliance %
SLA Breach %
```

### Category

```text
Top Complaint Categories
```

### Operator

```text
Tickets per Operator
Resolution Rate
Average Resolution Time
```

---

# 97. Success Metrics

Produk dianggap berhasil apabila:

- >95% aduan WhatsApp berhasil menjadi ticket;
- duplicate ticket/message dapat ditekan;
- operator dapat menemukan ticket <10 detik;
- seluruh ticket memiliki status;
- seluruh ticket memiliki owner/queue;
- dashboard dapat memberikan kondisi ticket secara aktual;
- SLA dapat diukur;
- histori ticket dapat ditelusuri.

Angka target dapat disesuaikan setelah baseline operasional tersedia.

---

# 98. Roadmap

## Phase 1 — Foundation

```text
Authentication
RBAC
Employee
Master Data
Ticket CRUD
Ticket Workflow
Dashboard
DataTables
```

## Phase 2 — WhatsApp

```text
OpenWA
Webhook
Incoming Message
Auto Ticket
Outgoing Message
Conversation
Notification
```

## Phase 3 — Operations

```text
Assignment
Team
SLA
Escalation
Audit
Reporting
Attachment
```

## Phase 4 — Intelligence

```text
AI Classification
Auto Priority
Suggested Resolution
Knowledge Base
FAQ
```

## Phase 5 — Integration

```text
HRIS
Absensi
Email
SSO
Mobile
External Systems
```

---

# 99. Prioritas Implementasi

Prioritas development:

```text
P0
├── Authentication
├── RBAC
├── Employee
├── Category
├── Service
├── Ticket
├── Status
├── Priority
├── Dashboard
├── DataTables
└── OpenWA webhook

P1
├── WhatsApp conversation
├── Notification
├── Assignment
├── SLA
├── Audit
├── Attachment
└── Reporting

P2
├── Auto assignment
├── Escalation
├── Custom fields
├── Knowledge base
└── AI classification
```

---

# 100. Prinsip Final Arsitektur

Sistem harus dibangun dengan konsep:

```text
                CHANNEL
                   │
        ┌──────────┼──────────┐
        ▼          ▼          ▼
     WhatsApp     Web       Admin/API
        │          │          │
        └──────────┼──────────┘
                   ▼
             TICKET ENGINE
                   │
       ┌───────────┼───────────┐
       ▼           ▼           ▼
   Workflow       SLA       Assignment
       │           │           │
       └───────────┼───────────┘
                   ▼
              NOTIFICATION
                   │
       ┌───────────┼───────────┐
       ▼           ▼           ▼
   WhatsApp      Web         Email*
                   │
                   ▼
                 MySQL
```

Kunci desain:

> **WhatsApp hanyalah channel. Ticket adalah domain utama.**

Dengan prinsip ini, jika beberapa tahun ke depan KOMINFO PINRANG menambahkan channel email, portal web, mobile app, atau integrasi sistem lain, semuanya dapat menggunakan **Ticket Engine yang sama**.

Demikian pula:

> **Jaringan dan absensi hanyalah service/category awal, bukan bagian yang hard-coded di core system.**

Sehingga penambahan:

```text
Jaringan
Absensi
Aplikasi
Hardware
Email
VPN
Keamanan
Website
Permintaan Akses
Layanan Data
...
```

cukup dilakukan melalui master/configuration.

---

# 101. Referensi Teknologi

- CodeIgniter 4
- Bootstrap 5.3
- Xakti Admin Template
- MySQL
- DataTables.net
- OpenWA
- Chart.js

Referensi repository:

- Xakti Admin Template: https://github.com/fhdjg/xakti-admin-template
- OpenWA: https://github.com/rmyndharis/OpenWA

---

# 102. Catatan Implementasi

Xakti Admin Template menggunakan Bootstrap 5.3 dan menyediakan integration layer untuk DataTables Bootstrap 5. OpenWA menyediakan REST API dan webhook untuk event WhatsApp, sehingga integrasi yang disarankan adalah:

```text
OpenWA Webhook
       ↓
/api/webhooks/openwa
       ↓
Webhook Handler
       ↓
Message Persistence
       ↓
Ticket/Conversation Service
       ↓
Notification Service
```

Hindari memasukkan seluruh logic tersebut ke dalam satu controller.

Target arsitektur CodeIgniter:

```text
Controller
    ↓
Service
    ↓
Model/Repository
    ↓
MySQL
```

sedangkan integrasi eksternal:

```text
Service
    ↓
OpenWA Client
    ↓
OpenWA API
```

Dengan struktur tersebut, OpenWA juga dapat diganti di masa depan tanpa harus mengubah domain ticketing secara keseluruhan.