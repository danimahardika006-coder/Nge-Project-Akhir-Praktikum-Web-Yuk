# Cuci Sepatu — Sistem Booking & Kasir

Aplikasi web cuci sepatu dengan dua sisi: **panel kasir** untuk pesanan walk-in (fitur lama) dan **sistem booking online** dengan akun pelanggan, riwayat cucian, dan pelacakan status (fitur baru). Dibangun dengan PHP murni + MySQL/MariaDB tanpa framework.

---

## Daftar Isi

- [Ringkasan Fitur](#ringkasan-fitur)
- [Alur Lengkap Pemakaian](#alur-lengkap-pemakaian)
- [Fitur Pelanggan](#fitur-pelanggan)
- [Fitur Admin](#fitur-admin)
- [Alur Status Booking](#alur-status-booking)
- [Instalasi](#instalasi)
- [Struktur Database](#struktur-database)
- [Struktur Folder](#struktur-folder)
- [Keamanan](#keamanan)
- [Pengujian](#pengujian)
- [Troubleshooting](#troubleshooting)

---

## Ringkasan Fitur

| Sisi | Fitur |
|---|---|
| **Publik** | Lacak pesanan kasir (`TRX-…`) & booking online (`BK-…`) |
| **Pelanggan** | Registrasi, login, dashboard, booking online dengan slot waktu, riwayat cucian **gabungan (booking online + pesanan outlet)**, detail booking + timeline 7 tahap, batalkan booking, edit profil, ganti password |
| **Admin** | Panel kasir walk-in (lama), kelola booking online (konfirmasi/tolak/**update status termasuk mundur** + catatan), kelola akun pelanggan (aktif/nonaktif, **lihat pesanan outlet pelanggan**), kelola layanan/paket/tipe sepatu (tambah/edit/**hapus cerdas**), template layanan otomatis, **pendapatan terpisah (kasir vs booking)** |

## Alur Lengkap Pemakaian

```
PELANGGAN                                ADMIN
─────────                                ─────
1. Daftar akun (registrasi.php)
2. Login → dashboard pelanggan
3. Isi form booking:
   - pilih layanan (harga muncul)
   - pilih paket pengerjaan
   - pilih tipe & jumlah sepatu
   - pilih tanggal + jam (slot
     penuh otomatis ditandai)
   - estimasi total muncul live
4. Kirim → dapat nomor BK-XXXX
   status: Menunggu Konfirmasi  ──→  5. Buka "Booking" di panel admin
                                        lihat booking masuk + data pelanggan
6. Pantau dashboard                   6. Klik Konfirmasi (atau Tolak)
   status berubah: Dikonfirmasi  ←──  7. Sepatu datang → update status:
                                        Sepatu Diterima → Sedang Dicuci
7. Status terus berupa perlahan        → Quality Control → Siap Diambil
   sampai Selesai                    8. Selesai
8. Melihat riwayat lengkap semua
   cucian + total belanja di dashboard
```

## Fitur Pelanggan

### Registrasi & Login
- Daftar dengan nama, email, nomor HP, password (min. 8 karakter + konfirmasi).
- Username dibuat **otomatis** dari email (dijaga unik dengan angka belakang bila bentrok).
- Password disimpan sebagai **hash** (bukan teks biasa).
- Satu halaman login untuk semua — admin diarahkan ke panel kasir, pelanggan ke dashboard-nya.

### Dashboard (`customer/dashboard.php`)
- 4 kartu statistik: **Total booking · Sedang berjalan · Selesai · Total belanja**, plus kartu **Pesanan outlet** bila nomor HP akun punya nota kasir yang cocok.
- Panel "Pesanan outlet terbaru" menampilkan nota walk-in yang tertaut — tersembunyi otomatis kalau nomor HP belum diisi atau belum ada nota.
- Kartu booking terbaru dengan ringkasan detail + tombol aksi cepat.
- Tabel 5 booking terakhir, semua data diambil langsung dari database.

### Booking Online (`customer/booking.php`)
- Dropdown layanan & paket diambil dari tabel `services` dan `pakets` (hanya yang aktif).
- **Estimasi harga muncul otomatis** sebelum submit: `(harga layanan + tambahan paket) × jumlah sepatu`.
- **Slot waktu**: 9 jam operasional (09:00–18:00, maksimal `KAPASITAS_SLOT = 3` booking per jam). Slot yang penuh otomatis ditandai "penuh" dan dinonaktifkan — dicek ulang lagi di server saat submit.
- Tanggal masa lalu dan jam di luar operasional ditolak.
- Catatan opsional (maks. 500 karakter).
- Nomor booking unik otomatis: format `BK-YYYYMMDD-XXXX`.

### Riwayat & Detail Booking
- **Riwayat** (`customer/riwayat.php`): semua cucian milik sendiri, dari **dua sumber**:
  - **Booking online** — pesanan yang dibuat lewat halaman booking
  - **Pesanan outlet** — pesanan walk-in yang dicatat kasir dengan **nomor HP yang sama** dengan nomor HP akun
- **Total belanja** dihitung dari **kedua sumber** yang sudah selesai (booking `selesai` + outlet `Selesai/Diambil`).
- Filter per status + pencarian berlaku untuk kedua jenis.
- **Detail** (`customer/detail_booking.php`): timeline visual 7 tahap, detail pesanan + harga, riwayat lengkap perubahan status lengkap dengan catatan admin dan waktu.
- Pelanggan hanya bisa melihat booking miliknya sendiri (percobaan akses booking orang lain → 404).

### Pesanan Outlet Otomatis Muncul di Akun
Pelanggan yang **langsung datang ke outlet** (tanpa booking online) tetap bisa melihat riwayatnya:

```
Kasir catat: nama Budi · HP 0812-3456-7890 → TRX-20261120-101
                        │
                        │  dicocokkan otomatis
                        ▼
Akun Budi (no_hp: 081234567890)
   └─ Riwayat → bagian "Pesanan langsung di outlet"
```

- Pencocokan memakai **bentuk kanonik** nomor HP, jadi format apa pun diterima:
  `081234567890` = `0812-3456-7890` = `+62 812 3456 7890`
- Kasir **tidak perlu mengubah cara kerjanya** — cukup tulis nomor HP pelanggan seperti biasa.
- Kalau nomor HP pelanggan belum diisi di profil, bagian ini menampilkan ajakan melengkapi profil (bukan error).
- Admin bisa **menautkan manual** pesanan tertentu ke akun (tombol "Tautkan" di detail pelanggan) untuk kasus nomor beda/typo.

### Batalkan Booking
- Bisa dibatalkan sendiri **selama masih "Menunggu Konfirmasi"**.
- Setelah dikonfirmasi admin, hubungi admin untuk pembatalan.

### Profil (`customer/profil.php`)
- Ubah nama, email (dicek tidak dipakai akun lain), dan nomor HP.
- Ganti password (wajib isi password lama yang benar).

## Fitur Admin

Semua halaman admin diawasi: hanya akun ber-role `admin` yang bisa masuk, pelanggan otomatis ditendang balik.

### Panel Kasir (`dashboard.php`) — fitur lama, tetap utuh
- Catat pesanan walk-in tanpa akun dengan layanan dan paket aktif dari database; total menggabungkan harga layanan dan tambahan harga paket.
- Update status (Antrean/Dicuci/Selesai/Diambil), hapus pesanan, dan lacak nama paket pada transaksi.
- Kartu pendapatan **kasir**: total walk-in Selesai/Diambil. Pendapatan booking online tampil terpisah di halaman Data Booking.
- Dilengkapi navigasi ke halaman-halaman booking.

### Data Booking (`admin/data_booking.php`)
- Semua booking online dalam kartu, **booking menunggu konfirmasi muncul paling atas**.
- Filter per status + pencarian (kode booking / nama / email / HP).
- Aksi: **Konfirmasi**, **Tolak** (wajib alasan), **Update status** (dropdown inline, opsi mundur ditandai `↩`), **Simpan catatan**.
- Statistik di atas halaman termasuk **Pendapatan booking** — total booking berstatus selesai (angka ini tidak ada di panel kasir).
- Badge jumlah booking yang menunggu konfirmasi tampil di navigasi.
- Statistik per status di atas halaman.

### Detail Booking (`admin/detail_booking.php`)
- Data lengkap: pelanggan (nama, email, HP), layanan/paket, harga per komponen, jadwal, catatan.
- Update status + catatan per perubahan — **termasuk memundurkan status** bila salah klik (pilihan yang mundur ditandai `↩` + konfirmasi sebelum disimpan).
- Riwayat lengkap: siapa mengubah apa dan kapan. Memundurkan status tetap tercatat sebagai baris baru.

### Data Pelanggan (`admin/data_pelanggan.php` + `admin/pelanggan.php`)
- Daftar semua akun + jumlah booking per orang.
- **Aktifkan / nonaktifkan akun** — akun nonaktif tidak bisa login.
- Profil per pelanggan + seluruh riwayat booking online, **plus pesanan outlet** yang tertaut lewat nomor HP.
- Tombol **Tautkan** untuk mengaitkan pesanan kasir tertentu ke akun secara eksplisit.

### Layanan, Paket & Tipe Sepatu (`admin/data_layanan.php`)
- **Layanan**: tambah, edit nama/harga/status aktif, **hapus**.
- **Paket**: tambahan harga di atas layanan + estimasi hari. Tambah & hapus.
- **Tipe sepatu**: dropdown di form booking. Tambah, edit, aktif/nonaktif, hapus.
- **Hapus cerdas**: kalau data sudah pernah dipakai booking, sistem **menonaktifkan** alih-alih menghapus — supaya riwayat pelanggan tidak rusak. Data yang belum pernah dipakai dihapus permanen.
- **Template otomatis**: layanan/paket/tipe bawaan terisi sendiri saat migrasi pertama (lihat bagian [Instalasi](#instalasi)).

## Alur Status Booking

7 tahap progres + 1 status luar jalur:

```
Menunggu Konfirmasi → Dikonfirmasi → Sepatu Diterima → Sedang Dicuci
    → Quality Control → Siap Diambil → Selesai

(dibatalkan — bisa dari status "Menunggu Konfirmasi", jalurnya terpisah)
```

- Pelanggan melihat sebagai **timeline horizontal** dengan penanda posisi sekarang + persentase progres.
- Setiap perubahan dicatat permanen di tabel `order_status_history` (status, catatan, admin pelaku, waktu) — tidak ada yang bisa diubah balik tanpa jejak.
- **Status boleh dimundurkan.** Admin kadang salah klik (misal menandai "Siap Diambil" padahal belum). Pilihan yang memundurkan ditandai `↩` di dropdown dan memunculkan konfirmasi sebelum disimpan. Karena riwayat bersifat append-only, memundurkan status tidak menghapus jejak — ia menambah baris baru.

## Instalasi

### Kebutuhan
- XAMPP (atau LAMP/MAMP) dengan PHP **7.4+** dan MySQL/MariaDB.

### Langkah

1. **Taruh folder proyek** di `htdocs` (XAMPP) atau set sebagai document root.

2. **Buat database kosong** — buka phpMyAdmin atau mysql CLI:
   ```sql
   CREATE DATABASE db_cuci_sepatu CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```

3. **Atur koneksi** bila perlu — `koneksi.php` sudah mengikuti default XAMPP (`root`, tanpa password). Kalau database-mu pakai port lain, set environment variable `DB_PORT`.

4. **Jalankan migrasi sekali** (dari folder proyek):
   ```bash
   php database/migrate.php
   ```
   Migrasi akan:
   - Membuat tabel baru: `pakets`, `tipe_sepatu`, `bookings`, `order_status_history`.
   - Menambah kolom `role`, `no_hp`, `status_akun` ke tabel `users` yang sudah ada — **semua akun lama otomatis menjadi admin**.
   - Menambah kolom `transactions.user_id` (nullable, `ON DELETE SET NULL`) untuk menautkan nota kasir ke akun pelanggan.
   - Mengisi **template awal**: 5 layanan, 5 paket, 8 tipe sepatu (hanya bila tabel masih kosong).
   - Tidak menghapus / mengubah data apa pun yang sudah ada. Jalankan berapa kali pun hasilnya sama.

5. **Buat akun admin pertama** — bila database masih kosong, daftar lewat `registrasi.php` lalu ubah role-nya:
   ```sql
   UPDATE users SET role='admin' WHERE email='email-kamu';
   ```

6. **Selesai.** Buka situsnya di browser. Migrasi aman dijalankan ulang kapan saja — hanya menambah data template yang namanya belum ada, tidak pernah menimpa harga/status yang sudah kamu ubah.

### Perubahan pada file lama

| File | Perubahan |
|---|---|
| `login.php` | Redirect per-role + cek akun nonaktif |
| `registrasi.php` | Kini untuk pendaftaran pelanggan (dengan no. HP) |
| `dashboard.php` | Ditambah navigasi baru & CSRF (perilaku kasir tidak berubah) |
| `tracking.php` | Sekarang juga mengenali kode `BK-…` |
| `koneksi.php` | Tambah dukungan `DB_PORT` (default tetap 3306) + koneksi dipaksa ke TCP `127.0.0.1` |

## Struktur Database

```
users ──┬──< bookings >──┬── services
        │        │       └── pakets (opsional)
        │        │
        │        └──< order_status_history >── users (admin pelaku)
        │
        └──< transactions   (walk-in; terhubung OPSIONAL lewat user_id
                             atau dicocokkan dari nomor HP)
```

| Tabel | Isi | Catatan penting |
|---|---|---|
| `users` | Admin + pelanggan (dibedakan kolom `role`) | Password di-hash; akun lama otomatis jadi admin |
| `services` | Layanan cuci + harga + status aktif | Template awal terisi otomatis |
| `pakets` | Paket pengerjaan (tambahan harga + estimasi) | Bisa kosong; tidak wajib di booking |
| `tipe_sepatu` | Jenis sepatu untuk dropdown | Disimpan sebagai teks di booking |
| `bookings` | Pesanan online | Menyimpan **snapshot harga** — riwayat tidak berubah meski harga layanan dinaikkan |
| `order_status_history` | Jejak setiap perubahan status | Append-only; sumber timeline |
| `transactions` | Pesanan kasir walk-in | Punya `user_id` **opsional** (`ON DELETE SET NULL`) untuk tautan eksplisit ke akun; pencocokan utama tetap lewat nomor HP |

**Tautan transaksi ke akun** — dua jalur, keduanya boleh aktif bersamaan:
1. **Otomatis** — nomor HP pada nota kasir sama dengan nomor HP di profil akun (setelah dinormalisasi).
2. **Manual** — admin klik "Tautkan" di halaman detail pelanggan → `transactions.user_id` diisi.

Skema referensi lengkap: [`database/schema.sql`](database/schema.sql).

## Struktur Folder

```
├── index ke halaman utama (login/registrasi/tracking ada di root)
├── login.php / registrasi.php / logout.php
├── dashboard.php            ← panel kasir (admin, lama)
├── tracking.php             ← lacak pesanan publik
├── koneksi.php              ← koneksi database
│
├── partials/
│   ├── init.php             ← PUSAT: session, helper, CSRF, guard, helper booking
│   ├── header_customer.php  ← topbar + nav pelanggan
│   ├── header_admin.php     ← topbar + nav admin
│   ├── footer_customer.php  ← penutup halaman (dipakai semua sisi)
│   └── auth_aside.php       ← panel samping halaman login/daftar
│
├── customer/                ← semua halaman pelanggan
│   ├── dashboard.php
│   ├── booking.php          ← form booking + JS estimasi/slot
│   ├── slot.php             ← API JSON ketersediaan slot
│   ├── detail_booking.php   ← timeline + riwayat
│   ├── riwayat.php
│   └── profil.php
│
├── admin/                   ← semua halaman admin (baru)
│   ├── data_booking.php
│   ├── detail_booking.php
│   ├── data_pelanggan.php
│   ├── pelanggan.php
│   └── data_layanan.php
│
├── database/
│   ├── migrate.php          ← migrasi idempotent (jalankan sekali)
│   ├── schema.sql           ← skema referensi
│   ├── generate_erd.py      ← gambar ERD gaya tabel (baca information_schema)
│   └── generate_erd_chen.py ← gambar ERD notasi Chen
│
├── docs/
│   ├── erd.png              ← ERD gaya database/Workbench
│   └── erd-chen.png         ← ERD notasi Chen (kotak/elips/belah ketupat)
│
├── tests/
│   └── e2e.php              ← 74 tes otomatis end-to-end
│
└── assets/
    └── style.css            ← seluruh desain (satu file)
```

### Membuat ulang gambar ERD

Kedua skrip membaca **database yang sedang jalan** (bukan skema hardcoded), jadi gambar selalu sesuai kondisi nyata:

```bash
python3 -m pip install --user Pillow     # sekali saja
python3 database/generate_erd.py         # → docs/erd.png
python3 database/generate_erd_chen.py    # → docs/erd-chen.png
```

## Keamanan

| Proteksi | Cara kerja |
|---|---|
| **SQL Injection** | 100% query pakai prepared statement (`mysqli_prepare` + `bind_param`) |
| **XSS** | Semua output lewat helper `e()` (htmlspecialchars) |
| **CSRF** | Token acak per session di semua form; POST tanpa token valid → HTTP 419 |
| **Password** | `password_hash()` + `password_verify()` + auto rehash |
| **Session fixation** | `session_regenerate_id(true)` setiap login sukses |
| **Cookie aman** | `httponly` + `samesite=Lax` |
| **Isolasi data** | Query booking selalu menyertakan `user_id` milik yang login (anti-IDOR) |
| **Hak akses** | Guard `butuh_admin()` / `butuh_customer()` di awal setiap halaman |
| **Manipulasi harga** | Harga total dihitung ulang di server dari database — nilai dari form diabaikan |
| **Akun nonaktif** | Tidak bisa login; sesi aktif langsung dianggap invalid |
| **Isolasi pesanan outlet** | Pencocokan nomor HP memakai bentuk kanonik (`hp_kanonik()`), bukan `LIKE`, sehingga nomor mirip tidak bisa membaca pesanan orang lain |
| **Tautan manual** | `transactions.user_id` hanya bisa diisi admin lewat form ber-CSRF; tanpa token → 419 |

## Pengujian

74 tes end-to-end otomatis mencakup: registrasi + validasi, login & proteksi halaman, booking + perhitungan harga + slot, isolasi antar-akun (IDOR), alur admin lengkap, profil & ganti password, nonaktif akun, dan fitur lama tetap jalan.

Di luar suite itu, beberapa perilaku diuji manual lewat HTTP:

| Yang diuji | Hasil yang diharapkan |
|---|---|
| Riwayat pelanggan dengan 3 format nomor HP berbeda (`08123456789`, `0812-3456-789`, `+62 812 3456 789`) | Ketiganya cocok ke akun yang sama |
| Pesanan milik nomor HP lain | Tidak muncul sama sekali (tidak bocor) |
| Total belanja: 3 booking selesai (Rp 170.000) + 2 nota kasir selesai (Rp 65.000) | Tampil Rp 235.000 |
| Status dimundurkan (`siap_diambil → dicuci`, `selesai → menunggu_konfirmasi`) | Tersimpan (302) + tercatat sebagai baris riwayat baru |
| POST ubah status tanpa token CSRF | HTTP 419 |

```bash
# 1. Jalankan server dev (atau pakai Apache XAMPP biasa)
DB_PORT=3306 php -S 127.0.0.1:8088 -t .

# 2. Jalankan tes
php tests/e2e.php
```

Output diakhiri dengan rekap `LULUS: N  GAGAL: N`.

## Troubleshooting

| Masalah | Solusi |
|---|---|
| Dropdown layanan/paket/tipe kosong | Jalankan `php database/migrate.php` — template diisi otomatis bila tabel masih kosong |
| "Sesi formulir kedaluwarsa" (HTTP 419) | Muat ulang halaman lalu submit lagi — token CSRF per halaman |
| Tidak bisa login padahal password benar | Akun mungkin dinonaktifkan admin — cek di `admin/data_pelanggan.php` |
| `Koneksi gagal` | Pastikan MySQL jalan dan database `db_cuci_sepatu` sudah dibuat; port non-standar pakai env `DB_PORT` |
| Mau ganti kapasitas slot per jam | Ubah `KAPASITAS_SLOT` di `partials/init.php` (baris `define('KAPASITAS_SLOT', 3)`) |
| Mau ganti jam operasional | Ubah daftar jam di fungsi `slot_jam()` di `partials/init.php` |
| Mau tambah layanan template bawaan | Tambah baris di array `$template_layanan` di `database/migrate.php`, lalu jalankan ulang migrasi |
| Pesanan outlet tidak muncul di akun pelanggan | Nomor HP di nota kasir harus sama dengan `no_hp` di profil akun (setelah dinormalisasi). Kalau beda/typo, admin bisa klik **Tautkan** di halaman detail pelanggan |
| Total belanja terasa kurang | Hanya pesanan **selesai** yang dihitung: booking `selesai` + nota kasir `Selesai`/`Diambil` |
| Pendapatan di dashboard tidak bertambah | Hanya nota kasir berstatus `Selesai`/`Diambil` yang dihitung. Pendapatan booking online ada di halaman **Data Booking** (kartu kelima) |

---

*Panduan memahami kodenya ada di [`PANDUAN-KODE.md`](PANDUAN-KODE.md).*
