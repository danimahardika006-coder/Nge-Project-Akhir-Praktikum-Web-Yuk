# PANDUAN KODE — Belajar Dari Codingan Ini

Dokumen ini menjelaskan **cara kerja kode di proyek ini**, konsep di baliknya, dan keputusan desain yang diambil — ditulis untuk yang mau belajar atau lanjut mengembangkan. Panduan fitur untuk pengguna ada di [`README.md`](README.md).

---

## Daftar Isi

1. [Gambaran Arsitektur](#1-gambaran-arsitektur)
2. [Alur Satu Request](#2-alur-satu-request)
3. [init.php — Jantung Aplikasi](#3-initphp--jantung-aplikasi)
4. [Keamanan: Empat Lapis Pertahanan](#4-keamanan-empat-lapis-pertahanan)
5. [Sistem Autentikasi & Role](#5-sistem-autentikasi--role)
6. [Sistem Booking — Kasus Terpenting](#6-sistem-booking--kasus-terpenting)
7. [Sistem Status & Riwayat](#7-sistem-status--riwayat)
8. [Migrasi Database Idempotent](#8-migrasi-database-idempotent)
9. [Desain UI & CSS](#9-desain-ui--css)
10. [Testing E2E](#10-testing-e2e)
11. [Keputusan Desain: Kenapa Dibuat Begini](#11-keputusan-desain-kenapa-dibuat-begitu)
12. [Ide Pengembangan Lanjutan](#12-ide-pengembangan-lanjutan)

---

## 1. Gambaran Arsitektur

Proyek ini **PHP murni tanpa framework** — setiap halaman adalah file PHP yang berdiri sendiri dengan pola yang konsisten:

```
┌──────────────────────────────────────────────────────┐
│  1. require init.php                                 │  ← session, DB, helper, guard
│  2. $user = butuh_admin() / butuh_customer()         │  ← PENTING: selalu di awal
│  3. Proses POST (validasi + CSRF + query)            │  ← redirect kalau sukses
│  4. Query data untuk ditampilkan                     │  ← prepared statement
│  5. require header_*.php                             │  ← mulai HTML
│  6. HTML + echo data (semua lewat e())               │
│  7. require footer_customer.php                      │
└──────────────────────────────────────────────────────┘
```

Ini pola klasik **POST-Redirect-GET (PRG)**: form disubmit → diproses → redirect ke halaman lain. Tanpa ini, refresh browser akan mengirim form yang sama dua kali.

**Kenapa tidak pakai framework?** Proyek ini lanjutan dari kode yang sudah ada (situs kasir lama). Menulis ulang dengan framework berarti membuang semua yang sudah jalan. Pendekatan yang diambil: **pelihara gaya lama, tambatkan fitur baru dengan fondasi bersama** — semua logika penting dikumpulkan di `partials/init.php` supaya halaman-halaman baru tetap kecil dan konsisten.

### Peta dependensi

```
koneksi.php ←── init.php ←── semua halaman
                  ↑
     (session, helper, guard, CSRF)
```

`init.php` tidak pernah tahu soal halaman; halaman yang memanggil `init.php`. Ini **inversion** sederhana yang membuat urutan require selalu aman.

---

## 2. Alur Satu Request

Contoh: pelanggan submit form booking.

```
Browser                    customer/booking.php               Database
   │                              │                              │
   │ POST /customer/booking.php   │                              │
   │ (csrf_token, service_id, …)  │                              │
   │─────────────────────────────>│                              │
   │                              │ 1. init.php: session start   │
   │                              │ 2. butuh_customer():         │
   │                              │    query user by session id  │
   │                              │─────────────────────────────>│
   │                              │ 3. wajib_csrf(): token cocok?│
   │                              │ 4. validasi semua input      │
   │                              │ 5. ambil harga dari DB       │
   │                              │─────────────────────────────>│
   │                              │ 6. hitung total (SERVER)     │
   │                              │ 7. cek slot tersedia         │
   │                              │─────────────────────────────>│
   │                              │ 8. BEGIN TRANSACTION         │
   │                              │    INSERT bookings           │
   │                              │    INSERT order_status_history
   │                              │    COMMIT                    │
   │                              │─────────────────────────────>│
   │<─────────────────────────────│                              │
   │ 302 → detail_booking.php?id=X│                              │
   │ (flash: "Booking dibuat")    │                              │
   │                              │                              │
   │ GET /customer/detail_booking │                              │
   │─────────────────────────────>│  (render + baca flash)       │
```

Perhatikan dua prinsip yang muncul berulang:

1. **Harga tidak pernah percaya browser.** Form mengirim `service_id`, bukan harga. Harga diambil ulang dari database di langkah 5. Kalau user edit HTML dan kirim `total_harga=1`, nilainya diabaikan total.
2. **Semua tulisan penting dalam satu transaksi.** Booking tanpa baris riwayat status awal = data rusak. Transaksi memastikan keduanya terjadi bersamaan atau tidak sama sekali.

---

## 3. init.php — Jantung Aplikasi

Setiap halaman dimulai dengan `require init.php`. File ini menyediakan semuanya:

### 3a. Deteksi base URL

```php
define('APP_ROOT', dirname(__DIR__));
// bandingkan posisi proyek vs DOCUMENT_ROOT → dapatkan prefix URL
```

**Masalah yang diselesaikan:** proyek bisa jalan di `http://localhost/` (XAMPP) atau `http://localhost/nama-folder/` (subfolder). Tanpa ini, semua link rusak di salah satunya. Solusi: fungsi `url('customer/booking.php')` yang selalu menghasilkan path benar.

### 3b. Session aman

```php
session_set_cookie_params([
    'httponly' => true,    // JS tidak bisa baca cookie → anti-XSS cookie theft
    'samesite' => 'Lax',   // cookie tidak dikirim dari situs lain → anti-CSRF via cookie
]);
```

### 3c. Helper format

Semua format tampilan dikumpulkan jadi fungsi kecil:

| Fungsi | Output contoh |
|---|---|
| `e($teks)` | escape HTML — **wajib untuk semua output** |
| `rupiah(25000)` | `Rp 25.000` |
| `tanggal_id('2026-11-20')` | `20 Nov 2026` |
| `tanggal_panjang(...)` | `20 November 2026` |
| `tanggal_waktu_id(...)` | `20 Nov 2026 · 14:30` |
| `samarkan_hp('081234567890')` | `0812••••890` (untuk halaman publik) |

`e()` dibungkus `function_exists()` supaya file lama (tracking.php, dashboard.php) yang sudah punya fungsi sendiri tidak fatal-error saat include init.php.

### 3d. Peta status booking

```php
'menunggu_konfirmasi' => [
    'label' => 'Menunggu Konfirmasi',
    'pesan' => 'Booking kamu sudah masuk...',   // teks ramah untuk pelanggan
    'tahap' => true,                            // bagian alur progres normal?
],
```

Satu sumber kebenaran untuk: label, pesan pelanggan, urutan timeline, warna badge, dan pengecekan validitas (`status_valid()`). Menambah status baru = edit satu array ini saja.

### 3e. CSRF

```php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));   // 256-bit acak
    }
    return $_SESSION['csrf_token'];
}

function wajib_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa...');
    }
}
```

`hash_equals()` dipakai (bukan `===`) untuk perbandingan yang **amannya dari timing attack**. Setiap form mencetak `csrf_field()`, dan setiap handler POST memanggil `wajib_csrf()` sebagai baris pertama.

### 3f. Guard hak akses

```php
function butuh_customer(): array {
    $user = user_aktif($koneksi);        // dari DB, bukan cuma session
    if ($user === null)    alihkan('login.php?next=customer');
    if ($user['role'] !== 'customer') alihkan('dashboard.php');
    return $user;
}
```

Detail penting: `user_aktif()` **selalu membaca ulang dari database**, bukan cuma percaya isi session. Artinya kalau admin menonaktifkan akun yang sedang login, user itu langsung keluar pada request berikutnya — tidak perlu tunggu session expire.

---

## 4. Keamanan: Empat Lapis Pertahanan

### Lapis 1 — SQL Injection → Prepared Statement

```php
// ❌ JANGAN — bisa diinjeksi
mysqli_query($koneksi, "SELECT * FROM users WHERE email = '$email'");

// ✅ SELALU begini
$stmt = mysqli_prepare($koneksi, 'SELECT ... WHERE email = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $email);
```

Input tidak pernah digabung ke string SQL; dikirim terpisah lewat `bind_param`. Tipe yang dipakai: `s` = string, `i` = integer. Untuk query dengan jumlah parameter dinamis (filter pencarian), tipe dan argumen dikumpulkan dulu lalu di-spread:

```php
$tipe .= 'ssss';
$args[] = $like;
mysqli_stmt_bind_param($stmt, $tipe, ...$args);
```

### Lapis 2 — XSS → `e()` di SEMUA output

```php
<?php echo e($user['nama_lengkap']); ?>
```

`e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Aturan mainnya: **kalau datanya pernah diketik manusia, dia harus lewat `e()`**. Nama pelanggan, catatan, kode booking — semua dianggap berbahaya sampai dibuktikan sebaliknya.

### Lapis 3 — CSRF → token di setiap form

Skenario yang dicegah: kamu login di tab satu; di tab lain buka situs jahat yang otomatis submit form "ubah password" ke situs ini memakai cookie-mu. Dengan CSRF token, form jahat itu tidak tahu token-nya → ditolak 419.

### Lapis 4 — IDOR (akses data orang lain)

```php
// TIDAK: "SELECT * FROM bookings WHERE id = ?"          ← siapa pun bisa lihat
// TAPI: "SELECT * FROM bookings WHERE id = ? AND user_id = ?"
mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user['id']);
```

`user_id` berasal dari session (tidak bisa dimanipulasi user), jadi booking milik orang lain dianggap **tidak ada** → 404. Ini diuji eksplisit di test suite.

**Bonus layer:** password pakai `password_hash()`/`password_verify()` (bcrypt), login sukses memanggil `session_regenerate_id(true)` (anti session fixation), dan harga dihitung ulang di server (anti manipulasi harga).

---

## 5. Sistem Autentikasi & Role

### Satu tabel untuk dua role

```sql
role ENUM('admin','customer') NOT NULL DEFAULT 'customer'
```

Kenapa satu tabel, bukan dua? Karena admin dan pelanggan sama-sama punya email, password, dan nama. Diferensiasi cuma butuh **satu kolom**. Konsekuensinya: akun lama (yang dibuat sebelum sistem booking ada) cukup di-backfill `role='admin'` lewat migrasi — tidak ada duplikasi data, tidak ada migrasi akun yang rumit.

### Registrasi: username otomatis

```php
$dasar = preg_replace('/[^a-z0-9_]/', '', strtolower(strstr($email, '@', true)));
// "budi.santoso@gmail.com" → "budisantoso"
// kalau sudah dipakai → "budisantoso2", "budisantoso3", ...
```

User tidak perlu mikir username; sistem mengambil local-part email dan menambah angka bila bentrok. Validasi lengkapnya: nama 3–100 karakter, format email, format HP (`^(\+62|62|0)8[0-9]{7,12}$`), password min. 8 karakter + konfirmasi, email unik.

### Login: pesan error seragam

```php
$error = 'Email atau password tidak cocok...';
```

Email salah dan password salah menghasilkan **pesan yang sama**. Kalau beda, penyerang bisa pakai perbedaan itu untuk memeriksa email mana yang terdaftar (user enumeration).

### Redirect per-role

```php
alihkan($user['role'] === 'admin' ? 'dashboard.php' : 'customer/dashboard.php');
```

---

## 6. Sistem Booking — Kasus Terpenting

### 6a. Kode booking unik

```php
function buat_kode_booking(mysqli $db): string {
    for ($i = 0; $i < 10; $i++) {
        $kode = 'BK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        // cek ke DB; kalau bentrok, ulangi (maks. 10x)
        if (!$ada) return $kode;
    }
    return 'BK-' . date('Ymd-His') . '-' . random_int(1000, 9999);  // fallback
}
```

Format `BK-YYYYMMDD-XXXX` — 4 karakter hex acak = 65.536 kombinasi per hari. Cek-dan-ulang menangani tabrakan; fallback menambah timestamp kalau kebetulan 10x gagal (praktis mustahil, tapi kode harus tahan banting).

### 6b. Slot waktu & kapasitas

```php
define('KAPASITAS_SLOT', 3);
function slot_jam(): array { return ['09:00', '10:00', ..., '18:00']; }

function slot_terpakai(mysqli $db, $tanggal): array {
    // SELECT jam_booking, COUNT(*) ... WHERE tanggal = ? AND status <> 'dibatalkan'
    // GROUP BY jam_booking  →  ['10:00' => 2, '14:00' => 1]
}
```

Slot dicek **dua kali**: sekali di browser (JS menonaktifkan opsi penuh via `customer/slot.php`), sekali lagi di server saat submit. Validasi klien cuma untuk kenyamanan; **validasi server adalah garis pertahanan** — user bisa bypass JS kapan saja.

### 6c. Hitung harga di server

```php
// harga diambil FRESH dari DB, bukan dari form
$stmt = ... 'SELECT harga FROM services WHERE id = ? AND aktif = 1';
$total = hitung_total($svc['harga'], $pkt['tambahan_harga'], $jumlah);
// = (harga_layanan + tambahan_paket) × jumlah_sepatu
```

### 6d. Snapshot harga — keputusan desain terpenting

```sql
harga_layanan INT NOT NULL,   -- harga saat booking dibuat
harga_paket   INT NOT NULL,
total_harga   INT NOT NULL,
```

Kenapa tidak cukup simpan `service_id` dan hitung on-the-fly? Karena **harga berubah seiring waktu**. Kalau tahun depan harga Deep Clean naik ke 60.000, riwayat booking lama yang dulu dibayar 45.000 harus tetap menunjukkan 45.000 — kalau tidak, data keuangan dan riwayat pelanggan jadi bohong. Ini pola standar di sistem e-commerce/invoice (sering disebut *price snapshot* / *denormalization for history*).

### 6e. Transaksi

```php
mysqli_begin_transaction($koneksi);
try-path:
    INSERT bookings ...
    catat_status(... 'Booking dibuat pelanggan')   // INSERT order_status_history
    mysqli_commit($koneksi);
gagal:
    mysqli_rollback($koneksi);
```

Kedua INSERT harus berhasil bersama. Kalau hanya booking yang masuk tanpa baris riwayat pertama, timeline pelanggan jadi kosong di awal — inkonsistensi halus yang menyebabkan bug jangka panjang.

### 6f. Real-time UI di form (JS vanilla)

```js
[svc, pkt, jumlah].forEach(function (el) {
    el.addEventListener('change', perbaruiRingkasan);
});
```

Tanpa framework: tiap perubahan dropdown → baca `data-harga` dari `<option>` → hitung → tulis ke elemen ringkasan. Ketika tanggal berubah → `fetch('slot.php?tanggal=...')` → tandai slot penuh. Ini cukup untuk interaktivitas level form tanpa membawa React/Vue.

---

## 7. Sistem Status & Riwayat

### Append-only history

Tabel `order_status_history` **hanya menerima INSERT, tidak pernah UPDATE/DELETE**:

```
id | booking_id | status     | catatan          | diubah_oleh | dibuat_pada
 1 | 5          | menunggu_konfirmasi | Booking dibuat pelanggan | 7 | 2026-11-20 10:00
 2 | 5          | dikonfirmasi        | Dikonfirmasi admin       | 1 | 2026-11-20 11:30
 3 | 5          | dicuci              | (null)                   | 1 | 2026-11-21 09:15
```

Kolom `status` di `bookings` = posisi sekarang (dibaca untuk tampilan cepat).
Tabel history = **perjalanan lengkap** (dibaca untuk timeline & audit).

Kenapa bukan cuma history? Karena 90% kebutuhan cuma butuh posisi terkini — join ke history tiap render itu boros. Kenapa bukan cuma kolom status? Karena kalau status berubah 5x, 4 perubahan pertama hilang selamanya.

**Aturan pentingnya:** setiap kali `UPDATE bookings SET status=...` dijalankan, baris berikutnya harus `catat_status(...)`. Keduanya adalah satu pasangan yang tidak boleh dipisah (dan dibungkus transaksi di alur booking).

### Timeline di UI

Urutan tahap berasal dari urutan array di `status_booking_semua()`. Posisi sekarang = `array_search($status, status_tahap())`. Persentase progres = `(posisi / 6) * 100`.

---

## 8. Migrasi Database Idempotent

`database/migrate.php` bisa dijalankan **berulang kali tanpa merusak apa-apa**. Tiga teknik yang dipakai:

### Teknik 1 — cek sebelum buat

```php
if (!tabel_ada($koneksi, 'bookings')) { CREATE TABLE bookings ... }
if (!kolom_ada($koneksi, 'users', 'role')) { ALTER TABLE users ADD COLUMN role ... }
```

Dua run pertama menambah skema; run ketiga tidak melakukan apa-apa.

### Teknik 2 — seed terpisah dari struktur

**Ini sumber bug yang pernah terjadi:** seed ditulis di dalam blok `CREATE TABLE`, sehingga tabel yang sudah ada tapi kosong tidak pernah diisi. Perbaikannya:

```php
if (!tabel_ada($koneksi, 'tipe_sepatu')) { CREATE TABLE ...; }
// SELALU cek isi — terpisah dari pembuatan tabel
if ((int) $koneksi->query('SELECT COUNT(*) FROM tipe_sepatu')->fetch_assoc()['n'] === 0) {
    INSERT 8 tipe awal;
}
```

### Teknik 3 — seed by-name, jangan pernah menimpa

Template layanan/paket memakai **nama sebagai kunci**:

```php
foreach ($template_layanan as [$nama, $harga, $ket]) {
    // cek: apakah nama ini sudah ada di DB?
    if (!$sudah) { INSERT ...; }   // hanya tambah yang belum ada
}
```

Konsekuensinya: admin bebas mengubah harga, menonaktifkan, atau menambah layanan. Menjalankan ulang migrasi **tidak akan pernah menimpa** perubahan itu — template hanya melengkapi yang hilang.

### Teknik 4 — hapus cerdas di panel admin (satu filosofi dengan di atas)

Menghapus data master yang sudah dipakai booking akan merusak riwayat (FK `ON DELETE RESTRICT` bahkan akan menolaknya). Maka:

```php
$dipakai = SELECT COUNT(*) FROM bookings WHERE service_id = ?;
if ($dipakai > 0) { UPDATE ... SET aktif = 0;   // nonaktifkan
} else            { DELETE FROM ...;            // hapus permanen
}
```

Data tanpa riwayat boleh hilang; data dengan riwayat cukup disembunyikan dari form. Riwayat pelanggan tetap jujur.

---

## 9. Desain UI & CSS

Satu file `assets/style.css` (~1.300 baris) memuat semuanya, dengan **custom properties** sebagai design tokens:

```css
:root {
    --pine: #2E6B54;        /* warna utama */
    --surface: #FAFAF8;     /* latar */
    --radius: 10px;
    --s4: 16px;             /* skala spacing 4px */
}
```

Semua komponen baru (badge, timeline, kartu booking) memakai token yang sama — tidak ada warna hard-coded di komponen baru. Warna status lewat atribut `data-warna`:

```html
<span class="badge" data-warna="proses">Sedang Dicuci</span>
```

```css
.badge[data-warna="proses"] { --bg: #DDE9EF; --fg: #1F4A5F; }
```

PHP menentukan warna via `status_warna($status)`, CSS yang memutuskan tampilannya — pemisahan data vs presentasi. Responsif pakai breakpoint 1040px dan 720px.

---

## 10. Testing E2E

`tests/e2e.php` menjalankan **74 tes** HTTP sungguhan (curl) terhadap server yang hidup — bukan unit test, tapi *integration test* yang memeriksa seluruh stack: HTTP → PHP → session → database.

```php
function cek(string $nama, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $nama;
}
```

Cakupan (9 kelompok): registrasi + validasi → login & proteksi halaman → booking + harga + slot → **isolasi antar-akun** → alur status admin → pelanggan melihat status → profil & password → manajemen akun admin → **fitur lama tetap jalan**.

Kelompok paling berharga adalah **pengujian serangan**: harga dimanipulasi lewat POST dan diharapkan diabaikan, booking orang lain diakses dan diharapkan 404, form tanpa CSRF diharapkan 419. Tes keamanan yang tidak pernah dijalankan = tidak ada.

---

## 11. Keputusan Desain: Kenapa Dibuat Begini

| Keputusan | Alternatif yang ditolak | Alasan |
|---|---|---|
| Satu tabel `users` dengan kolom `role` | Tabel terpisah admin & pelanggan | Akun lama tetap jalan hanya dengan backfill; satu sumber kebenaran untuk kredensial |
| Booking di tabel `bookings` baru, bukan `transactions` | Pakai tabel `transactions` + kolom pembeda | Kasir lama jalan tanpa risiko; struktur walk-in dan online memang beda (walk-in tanpa akun) |
| Snapshot harga di tiap booking | Hitung on-the-fly dari `services` | Harga berubah; riwayat tidak boleh ikut berubah |
| History append-only | UPDATE kolom status saja | Audit trail lengkap; pelanggan melihat "kapan status berubah" |
| Migrasi idempotent + seed by-name | Dump SQL all-or-nothing | Aman dijalankan berulang; tidak menimpa perubahan admin |
| Data master dipakai → nonaktif, bukan hapus | Hard delete + ON DELETE CASCADE | Riwayat pelanggan selalu utuh; layanan bisa diaktifkan lagi |
| Prepared statement 100% | Query string langsung | SQL injection; tanpa kompromi |
| Guard baca ulang user dari DB tiap request | Percaya isi session | Nonaktif akun berlaku seketika |
| Pesan error login seragam | Pesan spesifik per kesalahan | Mencegah user enumeration |
| PHP murni tanpa framework | Laravel/CodeIgniter | Melanjutkan kode existing; kompleksitas proyek tidak butuh framework |

---

## 12. Ide Pengembangan Lanjutan

Urutan dari yang paling bernilai:

1. **Notifikasi email/WA** — kirim email saat booking dikonfirmasi / status berubah (hook-nya sudah ada: setiap `catat_status()` adalah titik sempurna untuk trigger).
2. **Pembayaran** — integrasi payment gateway (Midtrans/Xendit) untuk booking online; tambah kolom `payment_status` di `bookings`.
3. **Halaman cetak nota** — untuk admin, cetak invoice booking (CSS `@media print` cukup).
4. **Pagination** — `riwayat.php` dan `data_booking.php` memuat semua baris; tambahkan `LIMIT/OFFSET` bila data sudah ribuan.
5. **Upload foto sepatu** — sebelum & sesudah dicuci; simpan di folder dengan nama `BK-…-before.jpg`, validasi ekstensi + ukuran.
6. **Statistik admin** — pendapatan per bulan, layanan terlaris (query `GROUP BY` + chart JS ringan).
7. **Rate limiting login** — catat percobaan gagal per IP/email, blokir sementara setelah 5x.
8. **Password reset via email** — token sekali pakai dengan expiry di tabel baru.

---

*Ringkasan fitur untuk pengguna ada di [`README.md`](README.md).*
