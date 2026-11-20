# Cuci Sepatu — Sistem Booking & Kasir

Aplikasi web cuci sepatu dengan dua sisi: **panel kasir** untuk pesanan walk-in dan **sistem booking online** dengan akun pelanggan, riwayat cucian, dan pelacakan status (fitur baru). Dibangun dengan PHP murni + MySQL/MariaDB tanpa framework.

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
| **Pelanggan** | Registrasi, login, dashboard, booking online dengan slot waktu, riwayat cucian, detail booking + timeline 7 tahap, batalkan booking, edit profil, ganti password |
| **Admin** | Panel kasir walk-in (lama), kelola booking online (konfirmasi/tolak/update status + catatan), kelola akun pelanggan (aktif/nonaktif), kelola layanan/paket/tipe sepatu (tambah/edit/**hapus cerdas**), template layanan otomatis |

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
- 4 kartu statistik: **Total booking · Sedang berjalan · Selesai · Total belanja**.
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
- **Riwayat** (`customer/riwayat.php`): semua booking milik sendiri dengan filter per status.
- **Detail** (`customer/detail_booking.php`): timeline visual 7 tahap, detail pesanan + harga, riwayat lengkap perubahan status lengkap dengan catatan admin dan waktu.
- Pelanggan hanya bisa melihat booking miliknya sendiri (percobaan akses booking orang lain → 404).

### Batalkan Booking
- Bisa dibatalkan sendiri **selama masih "Menunggu Konfirmasi"**.
- Setelah dikonfirmasi admin, hubungi admin untuk pembatalan.

### Profil (`customer/profil.php`)
- Ubah nama, email (dicek tidak dipakai akun lain), dan nomor HP.
- Ganti password (wajib isi password lama yang benar).

## Fitur Admin

Semua halaman admin diawasi: hanya akun ber-role `admin` yang bisa masuk, pelanggan otomatis ditendang balik.

### Panel Kasir (`dashboard.php`) — fitur lama, tetap utuh
- Catat pesanan walk-in tanpa akun, update status (Antrean/Dicuci/Selesai/Diambil), hapus pesanan.
- Sekarang dilengkapi navigasi baru ke halaman-halaman booking.

### Data Booking (`admin/data_booking.php`)
- Semua booking online dalam kartu, **booking menunggu konfirmasi muncul paling atas**.
- Filter per status + pencarian (kode booking / nama / email / HP).
- Aksi: **Konfirmasi**, **Tolak** (wajib alasan), **Update status**, **Simpan catatan**.
- Badge jumlah booking yang menunggu konfirmasi tampil di navigasi.
- Statistik per status di atas halaman.

### Detail Booking (`admin/detail_booking.php`)
- Data lengkap: pelanggan (nama, email, HP), layanan/paket, harga per komponen, jadwal, catatan.
- Update status + catatan per perubahan.
- Riwayat lengkap: siapa mengubah apa dan kapan.

### Data Pelanggan (`admin/data_pelanggan.php` + `admin/pelanggan.php`)
- Daftar semua akun + jumlah booking per orang.
- **Aktifkan / nonaktifkan akun** — akun nonaktif tidak bisa login.
- Profil per pelanggan + seluruh riwayat bookingnya.

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
   - Mengisi **template awal**: 5 layanan, 5 paket, 8 tipe sepatu (hanya bila tabel masih kosong).
   - Tidak menghapus / mengubah data apa pun yang sudah ada.

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
| `koneksi.php` | Tambah dukungan `DB_PORT` (default tetap 3306) |

## Struktur Database

```
users ──┬──< bookings >──┬── services
        │        │       └── pakets (opsional)
        │        │
        │        └──< order_status_history >── users (admin pelaku)
        │
        └── transactions (walk-in, struktur lama, tidak diubah)
```

| Tabel | Isi | Catatan penting |
|---|---|---|
| `users` | Admin + pelanggan (dibedakan kolom `role`) | Password di-hash; akun lama otomatis jadi admin |
| `services` | Layanan cuci + harga + status aktif | Template awal terisi otomatis |
| `pakets` | Paket pengerjaan (tambahan harga + estimasi) | Bisa kosong; tidak wajib di booking |
| `tipe_sepatu` | Jenis sepatu untuk dropdown | Disimpan sebagai teks di booking |
| `bookings` | Pesanan online | Menyimpan **snapshot harga** — riwayat tidak berubah meski harga layanan dinaikkan |
| `order_status_history` | Jejak setiap perubahan status | Append-only; sumber timeline |
| `transactions` | Pesanan kasir walk-in (lama) | **Tidak disentuh sama sekali** |

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
│   └── schema.sql           ← skema referensi
│
├── tests/
│   └── e2e.php              ← 74 tes otomatis end-to-end
│
└── assets/
    └── style.css            ← seluruh desain (satu file)
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

## Pengujian

74 tes end-to-end otomatis mencakup: registrasi + validasi, login & proteksi halaman, booking + perhitungan harga + slot, isolasi antar-akun (IDOR), alur admin lengkap, profil & ganti password, nonaktif akun, dan fitur lama tetap jalan.

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

---

*Panduan memahami kodenya ada di [`PANDUAN-KODE.md`](PANDUAN-KODE.md).*
