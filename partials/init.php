<?php
/**
 * init.php — pustaka bersama.
 *
 * Dimuat paling atas oleh setiap halaman (root maupun subfolder customer/,
 * admin/, database/). Berisi: session, koneksi, helper format, guard
 * autentikasi, proteksi CSRF, dan definisi tahap pesanan.
 */

// --------------------------------------------------------------------------
// Lokasi aplikasi — agar tautan & redirect benar walau dipasang di subfolder
// --------------------------------------------------------------------------

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));  // folder proyek
}

if (!defined('APP_BASE')) {
    $root = str_replace('\\', '/', realpath(APP_ROOT) ?: APP_ROOT);
    $base = '/';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: '') : '';
    if ($docRoot !== '' && strpos($root, $docRoot) === 0) {
        $rel = substr($root, strlen($docRoot));
        $base = ($rel === '' || $rel === false) ? '/' : rtrim($rel, '/') . '/';
    } elseif ($docRoot === '') {
        // Mode CLI: gunakan folder proyek sebagai akar.
        $base = '/';
    } else {
        // Proyek di luar document root — pakai nama folder.
        $base = '/' . basename($root) . '/';
    }
    define('APP_BASE', $base);
}

/** Bangun URL internal, mis: url('customer/dashboard.php'). */
if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return APP_BASE . ltrim($path, '/');
    }
}

/** Redirect lalu berhenti. */
function alihkan(string $path): void
{
    header('Location: ' . url($path));
    exit();
}

// --------------------------------------------------------------------------
// Session (cookie aman)
// --------------------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once APP_ROOT . '/koneksi.php';

// --------------------------------------------------------------------------
// Helper format
// --------------------------------------------------------------------------

if (!function_exists('e')) {
    function e($teks): string
    {
        return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka): string
    {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }
}

/** "15 November 2026" */
function tanggal_panjang($tanggal): string
{
    if (!$tanggal) {
        return '-';
    }
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime((string) $tanggal);
    return $t ? date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t) : (string) $tanggal;
}

/** "15 Nov 2026" */
if (!function_exists('tanggal_id')) {
    function tanggal_id($tanggal): string
    {
        $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $t = strtotime((string) $tanggal);
        return $t ? date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t) : (string) $tanggal;
    }
}

/** "14:30" */
function jam_singkat($jam): string
{
    if (!$jam) {
        return '-';
    }
    return substr((string) $jam, 0, 5);
}

/** "15 Nov 2026 · 14:30" */
function tanggal_waktu_id($waktu): string
{
    if (!$waktu) {
        return '-';
    }
    $t = strtotime((string) $waktu);
    return $t ? tanggal_id(date('Y-m-d', $t)) . ' · ' . date('H:i', $t) : (string) $waktu;
}

/** Samarkan nomor HP untuk halaman publik: 0812••••678 */
if (!function_exists('samarkan_hp')) {
    function samarkan_hp($hp): string
    {
        $hp = trim((string) $hp);
        $n  = strlen($hp);
        if ($n <= 7) {
            return $hp;
        }
        return substr($hp, 0, 4) . str_repeat('•', $n - 7) . substr($hp, -3);
    }
}

// --------------------------------------------------------------------------
// Tahap pesanan
// --------------------------------------------------------------------------

/**
 * Seluruh status booking. 'dibatalkan' sengaja terpisah karena bukan bagian
 * dari alur pengerjaan (tahap = false).
 */
function status_booking_semua(): array
{
    return [
        'menunggu_konfirmasi' => [
            'label' => 'Menunggu Konfirmasi',
            'pesan' => 'Booking kamu sudah masuk dan sedang menunggu konfirmasi admin.',
            'tahap' => true,
        ],
        'dikonfirmasi' => [
            'label' => 'Dikonfirmasi',
            'pesan' => 'Booking sudah dikonfirmasi. Datang sesuai tanggal dan jam yang dipilih.',
            'tahap' => true,
        ],
        'sepatu_diterima' => [
            'label' => 'Sepatu Diterima',
            'pesan' => 'Sepatu sudah kami terima di outlet.',
            'tahap' => true,
        ],
        'dicuci' => [
            'label' => 'Sedang Dicuci',
            'pesan' => 'Sepatu kamu sedang dalam proses pencucian.',
            'tahap' => true,
        ],
        'quality_control' => [
            'label' => 'Quality Control',
            'pesan' => 'Sepatu selesai dicuci dan sedang melewati pemeriksaan kualitas.',
            'tahap' => true,
        ],
        'siap_diambil' => [
            'label' => 'Siap Diambil',
            'pesan' => 'Sepatu sudah bersih dan siap diambil di outlet.',
            'tahap' => true,
        ],
        'selesai' => [
            'label' => 'Selesai',
            'pesan' => 'Pesanan selesai. Terima kasih sudah mempercayakan sepatu kamu.',
            'tahap' => true,
        ],
        'dibatalkan' => [
            'label' => 'Dibatalkan',
            'pesan' => 'Booking ini dibatalkan.',
            'tahap' => false,
        ],
    ];
}

/** Daftar status yang membentuk progres (tanpa 'dibatalkan'). */
function status_tahap(): array
{
    $out = [];
    foreach (status_booking_semua() as $kode => $info) {
        if ($info['tahap']) {
            $out[] = $kode;
        }
    }
    return $out;
}

function status_valid($status): bool
{
    return is_string($status) && array_key_exists($status, status_booking_semua());
}

function status_label($status): string
{
    $semua = status_booking_semua();
    return $semua[$status]['label'] ?? ucwords(str_replace('_', ' ', (string) $status));
}

function status_pesan($status): string
{
    $semua = status_booking_semua();
    return $semua[$status]['pesan'] ?? 'Status pesanan: ' . $status;
}

/** Status akhir — tidak diproses lagi. */
function status_final($status): bool
{
    return in_array($status, ['selesai', 'dibatalkan'], true);
}

/** Status yang dihitung sebagai "sedang berjalan". */
function status_berjalan(): array
{
    return ['menunggu_konfirmasi', 'dikonfirmasi', 'sepatu_diterima', 'dicuci', 'quality_control', 'siap_diambil'];
}

/** Token warna status untuk atribut data-warna CSS. */
function status_warna($status): string
{
    $map = [
        'menunggu_konfirmasi' => 'tunggu',
        'dikonfirmasi'        => 'info',
        'sepatu_diterima'     => 'info',
        'dicuci'              => 'proses',
        'quality_control'     => 'proses',
        'siap_diambil'        => 'siap',
        'selesai'             => 'selesai',
        'dibatalkan'          => 'batal',
    ];
    return $map[$status] ?? 'netral';
}

// --------------------------------------------------------------------------
// CSRF
// --------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $kirim = $_POST['csrf_token'] ?? '';
    return is_string($kirim)
        && $kirim !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $kirim);
}

/** Hentikan POST bila token CSRF tidak cocok. */
function wajib_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

// --------------------------------------------------------------------------
// Autentikasi & hak akses
// --------------------------------------------------------------------------

function sudah_login(): bool
{
    return isset($_SESSION['login'], $_SESSION['user_id']) && $_SESSION['login'] === true;
}

function user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function user_role(): string
{
    return (string) ($_SESSION['role'] ?? 'admin');
}

function is_admin(): bool
{
    return sudah_login() && user_role() === 'admin';
}

function is_customer(): bool
{
    return sudah_login() && user_role() === 'customer';
}

/** Simpan identitas ke session setelah login berhasil. */
function set_session_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['login']    = true;
    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama']     = $user['nama_lengkap'];
    $_SESSION['role']     = $user['role'];
}

/**
 * Ambil data pengguna yang sedang login langsung dari database.
 * Null bila akun hilang atau dinonaktifkan admin.
 */
function user_aktif(mysqli $db): ?array
{
    if (!sudah_login()) {
        return null;
    }
    $stmt = $db->prepare(
        'SELECT id, username, nama_lengkap, email, no_hp, role, status_akun, dibuat_pada
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['status_akun'] !== 'aktif') {
        return null;
    }
    $_SESSION['nama']     = $user['nama_lengkap'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    return $user;
}

/** Halaman khusus pelanggan. */
function butuh_customer(): array
{
    global $koneksi;
    $user = user_aktif($koneksi);
    if ($user === null) {
        alihkan('login.php?next=customer');
    }
    if ($user['role'] !== 'customer') {
        alihkan('dashboard.php');
    }
    return $user;
}

/** Halaman khusus admin. */
function butuh_admin(): array
{
    global $koneksi;
    $user = user_aktif($koneksi);
    if ($user === null) {
        alihkan('login.php?next=admin');
    }
    if ($user['role'] !== 'admin') {
        alihkan('customer/dashboard.php');
    }
    return $user;
}

// --------------------------------------------------------------------------
// Flash message
// --------------------------------------------------------------------------

function flash_set($teks, $tipe = 'success'): void
{
    $_SESSION['flash'] = ['teks' => $teks, 'tipe' => $tipe];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// --------------------------------------------------------------------------
// Validasi
// --------------------------------------------------------------------------

function valid_email($email): bool
{
    return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
}

function valid_hp($hp): bool
{
    $bersih = str_replace([' ', '-', '.'], '', (string) $hp);
    return (bool) preg_match('/^(\+62|62|0)8[0-9]{7,12}$/', $bersih);
}

function normalisasi_hp($hp): string
{
    $hp = preg_replace('/[^0-9+]/', '', (string) $hp);
    if (strpos($hp, '+62') === 0) {
        return '0' . substr($hp, 3);
    }
    if (strpos($hp, '62') === 0) {
        return '0' . substr($hp, 2);
    }
    return $hp;
}

// --------------------------------------------------------------------------
// Booking — kode, slot waktu, harga
// --------------------------------------------------------------------------

/** Kode booking unik: BK-YYYYMMDD-XXXX */
function buat_kode_booking(mysqli $db): string
{
    for ($i = 0; $i < 10; $i++) {
        $kode = 'BK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $db->prepare('SELECT 1 FROM bookings WHERE kode_booking = ? LIMIT 1');
        $stmt->bind_param('s', $kode);
        $stmt->execute();
        $ada = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$ada) {
            return $kode;
        }
    }
    return 'BK-' . date('Ymd-His') . '-' . random_int(1000, 9999);
}

/** Jam operasional booking. */
function slot_jam(): array
{
    return ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
}

/** Kapasitas maksimum booking per slot per hari. */
define('KAPASITAS_SLOT', 3);

/** Jumlah booking terpakai per slot pada tanggal tertentu. */
function slot_terpakai(mysqli $db, $tanggal): array
{
    $stmt = $db->prepare(
        "SELECT jam_booking, COUNT(*) AS n FROM bookings
         WHERE tanggal_booking = ? AND status <> 'dibatalkan'
         GROUP BY jam_booking"
    );
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = [];
    foreach ($rows as $r) {
        $out[substr($r['jam_booking'], 0, 5)] = (int) $r['n'];
    }
    return $out;
}

/** Total harga: (layanan + paket) x jumlah sepatu. */
function hitung_total($harga_layanan, $tambahan_paket, $jumlah): int
{
    return ((int) $harga_layanan + (int) $tambahan_paket) * (int) $jumlah;
}

/** Booking milik pelanggan tertentu — mencegah akses lintas akun. */
function ambil_booking_customer(mysqli $db, $booking_id, $user_id): ?array
{
    $stmt = $db->prepare(
        'SELECT b.*, s.nama_layanan, p.nama_paket, p.estimasi_hari
         FROM bookings b
         JOIN services s ON s.id = b.service_id
         LEFT JOIN pakets p ON p.id = b.paket_id
         WHERE b.id = ? AND b.user_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('ii', $booking_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Riwayat perubahan status sebuah booking (terlama dulu). */
function riwayat_status(mysqli $db, $booking_id): array
{
    $stmt = $db->prepare(
        'SELECT status, catatan, dibuat_pada FROM order_status_history
         WHERE booking_id = ? ORDER BY dibuat_pada ASC, id ASC'
    );
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Catat satu baris riwayat status. */
function catat_status(mysqli $db, $booking_id, $status, $oleh, $catatan = null): void
{
    $stmt = $db->prepare(
        'INSERT INTO order_status_history (booking_id, status, catatan, diubah_oleh, dibuat_pada)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('issi', $booking_id, $status, $catatan, $oleh);
    $stmt->execute();
    $stmt->close();
}