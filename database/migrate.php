<?php
/**
 * Migrasi database — aman dijalankan berulang kali (idempotent).
 *
 *   php database/migrate.php
 *
 * Yang dilakukan:
 *   1. Menambah kolom baru pada tabel users (no_hp, role, status_akun, dst).
 *   2. Menandai akun lama sebagai 'admin' agar login lama tetap berfungsi.
 *   3. Membuat tabel pakets, tipe_sepatu, bookings, order_status_history.
 *   4. Tidak menghapus / mengubah data pada tabel transactions.
 */

require_once dirname(__DIR__) . '/partials/init.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    // Lewat browser hanya boleh oleh admin yang sudah login.
    if (!is_admin()) {
        http_response_code(403);
        exit('Akses ditolak. Jalankan lewat terminal: php database/migrate.php');
    }
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Migrasi</title>'
       . '<link rel="stylesheet" href="' . e(url('assets/style.css')) . '"></head><body>'
       . '<main class="page page-narrow"><h1 class="page-title">Migrasi database</h1><pre>';
}

function baris(string $teks): void
{
    echo (PHP_SAPI === 'cli' ? '' : '') . $teks . PHP_EOL;
}

function kolom_ada(mysqli $db, string $tabel, string $kolom): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $tabel, $kolom);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    return $n > 0;
}

function tabel_ada(mysqli $db, string $tabel): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS n FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->bind_param('s', $tabel);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    return $n > 0;
}

function indeks_ada(mysqli $db, string $tabel, string $indeks): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS n FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->bind_param('ss', $tabel, $indeks);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    return $n > 0;
}

function jalankan(mysqli $db, string $sql): void
{
    if (!$db->query($sql)) {
        throw new RuntimeException('SQL gagal: ' . $db->error . "\n  → " . $sql);
    }
}

$langkah = 0;

// --------------------------------------------------------------------------
// 1. users
// --------------------------------------------------------------------------
if (!tabel_ada($koneksi, 'users')) {
    jalankan($koneksi, "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        nama_lengkap VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        no_hp VARCHAR(20) DEFAULT NULL,
        role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
        status_akun ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
        dibuat_pada DATETIME DEFAULT NULL,
        diperbarui_pada DATETIME DEFAULT NULL,
        UNIQUE KEY uq_users_email (email),
        KEY idx_users_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    baris('[+] tabel users dibuat');
    $langkah++;
} else {
    baris('[=] tabel users sudah ada — memeriksa kolom');
}

$kolom_users = [
    'no_hp'           => "ALTER TABLE users ADD COLUMN no_hp VARCHAR(20) DEFAULT NULL AFTER password",
    'role'            => "ALTER TABLE users ADD COLUMN role ENUM('admin','customer') NOT NULL DEFAULT 'customer' AFTER no_hp",
    'status_akun'     => "ALTER TABLE users ADD COLUMN status_akun ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' AFTER role",
    'dibuat_pada'     => "ALTER TABLE users ADD COLUMN dibuat_pada DATETIME DEFAULT NULL",
    'diperbarui_pada' => "ALTER TABLE users ADD COLUMN diperbarui_pada DATETIME DEFAULT NULL",
];

foreach ($kolom_users as $kolom => $sql) {
    if (!kolom_ada($koneksi, 'users', $kolom)) {
        jalankan($koneksi, $sql);
        baris("[+] users.$kolom ditambahkan");
        $langkah++;
    }
}

if (kolom_ada($koneksi, 'users', 'role')) {
    // Akun lama belum punya role → jadikan admin agar panel kasir tetap terbuka.
    $koneksi->query("UPDATE users SET role = 'admin' WHERE role IS NULL OR role = ''");
    $koneksi->query("UPDATE users SET status_akun = 'aktif' WHERE status_akun IS NULL OR status_akun = ''");
    if (!indeks_ada($koneksi, 'users', 'idx_users_role')) {
        jalankan($koneksi, 'CREATE INDEX idx_users_role ON users (role)');
        baris('[+] indeks users.idx_users_role dibuat');
        $langkah++;
    }
}

// --------------------------------------------------------------------------
// 3. services — kolom + template layanan awal
// --------------------------------------------------------------------------
if (!tabel_ada($koneksi, 'services')) {
    jalankan($koneksi, "CREATE TABLE services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_layanan VARCHAR(100) NOT NULL,
        harga INT NOT NULL,
        deskripsi VARCHAR(255) DEFAULT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    baris('[+] tabel services dibuat (kosong — isi layanan lewat panel admin)');
    $langkah++;
} else {
    if (!kolom_ada($koneksi, 'services', 'deskripsi')) {
        jalankan($koneksi, "ALTER TABLE services ADD COLUMN deskripsi VARCHAR(255) DEFAULT NULL AFTER harga");
        baris('[+] services.deskripsi ditambahkan');
        $langkah++;
    }
    if (!kolom_ada($koneksi, 'services', 'aktif')) {
        jalankan($koneksi, "ALTER TABLE services ADD COLUMN aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER deskripsi");
        baris('[+] services.aktif ditambahkan');
        $langkah++;
    }
}

/**
 * Template layanan awal.
 *
 * Dipakai sebagai titik mulai: harga / nama boleh diubah bebas dari panel admin.
 * Migrasi hanya menambah layanan yang namanya belum ada, jadi perubahan harga
 * yang sudah dilakukan tidak pernah ditimpa saat migrasi dijalankan ulang.
 */
$template_layanan = [
    // [nama, harga, deskripsi]
    ['Cuci Reguler',        20000, 'Cuci bagian upper & sol, dikeringkan dengan blower'],
    ['Cuci + Semir',        30000, 'Cuci bersih plus semir dan poles ulang warna'],
    ['Deep Clean',          45000, 'Pembersihan menyeluruh sampai midsole & bagian dalam'],
    ['Repaint / Cat Ulang', 75000, 'Pengecatan ulang seluruh badan sepatu'],
    ['Cuci Sandal',         15000, 'Khusus sandal, slip on, dan sepatu ringan'],
];

foreach ($template_layanan as [$nama, $harga, $ket]) {
    $cek = $koneksi->prepare('SELECT id FROM services WHERE nama_layanan = ? LIMIT 1');
    $cek->bind_param('s', $nama);
    $cek->execute();
    $sudah = $cek->get_result()->fetch_assoc();
    $cek->close();

    if (!$sudah) {
        $ins = $koneksi->prepare('INSERT INTO services (nama_layanan, harga, deskripsi, aktif) VALUES (?, ?, ?, 1)');
        $ins->bind_param('sis', $nama, $harga, $ket);
        $ins->execute();
        $ins->close();
        baris('[+] layanan "' . $nama . '" ditambahkan');
        $langkah++;
    }
}

// --------------------------------------------------------------------------
// 3. pakets
// --------------------------------------------------------------------------
if (!tabel_ada($koneksi, 'pakets')) {
    jalankan($koneksi, "CREATE TABLE pakets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_paket VARCHAR(60) NOT NULL,
        tambahan_harga INT NOT NULL DEFAULT 0,
        estimasi_hari TINYINT UNSIGNED NOT NULL DEFAULT 2,
        keterangan VARCHAR(255) DEFAULT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    baris('[+] tabel pakets dibuat');
    $langkah++;
} else {
    baris('[=] tabel pakets sudah ada');
}

// Seed paket dipisah dari pembuatan tabel: kalau tabel sudah ada tapi kosong
// (mis. dibuat manual / migrasi lama), isi default tetap masuk.
// Paket yang sudah ada tidak disentuh — menambah paket baru cukup lewat panel admin.
$n_paket = (int) $koneksi->query('SELECT COUNT(*) AS n FROM pakets')->fetch_assoc()['n'];
if ($n_paket === 0) {
    jalankan($koneksi, "INSERT INTO pakets (nama_paket, tambahan_harga, estimasi_hari, keterangan) VALUES
        ('Reguler', 0, 3, 'Pengerjaan normal 2-3 hari kerja'),
        ('Express', 15000, 1, 'Selesai dalam 24 jam'),
        ('Premium', 25000, 2, 'Deep clean + perawatan bahan + parfum')");
    baris('[+] 3 paket awal ditambahkan');
    $langkah++;
}

// Paket tambahan yang belum ada dilengkapi namanya (tidak menimpa harga yang sudah diubah admin).
$paket_tambahan = [
    ['Deep Clean',    35000, 3, 'Pembersihan menyeluruh sampai midsole & sol dalam'],
    ['Repaint Touch', 50000, 4, 'Pengecatan ulang warna sepatu oleh teknisi'],
];
foreach ($paket_tambahan as [$nama, $tambahan, $hari, $ket]) {
    $cek = $koneksi->prepare('SELECT id FROM pakets WHERE nama_paket = ? LIMIT 1');
    $cek->bind_param('s', $nama);
    $cek->execute();
    $sudah = $cek->get_result()->fetch_assoc();
    $cek->close();
    if (!$sudah) {
        $ins = $koneksi->prepare('INSERT INTO pakets (nama_paket, tambahan_harga, estimasi_hari, keterangan, aktif) VALUES (?, ?, ?, ?, 1)');
        $ins->bind_param('sii' . 's', $nama, $tambahan, $hari, $ket);
        $ins->execute();
        $ins->close();
        baris('[+] paket "' . $nama . '" ditambahkan');
        $langkah++;
    }
}

// --------------------------------------------------------------------------
// 4. tipe_sepatu
// --------------------------------------------------------------------------
if (!tabel_ada($koneksi, 'tipe_sepatu')) {
    jalankan($koneksi, "CREATE TABLE tipe_sepatu (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_tipe VARCHAR(60) NOT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    baris('[+] tabel tipe_sepatu dibuat');
    $langkah++;
} else {
    baris('[=] tabel tipe_sepatu sudah ada');
}

// Seed tipe dipisah dari pembuatan tabel agar tabel yang sudah ada tapi kosong
// tetap terisi (inilah yang membuat dropdown "Tipe sepatu" kosong sebelumnya).
$n_tipe = (int) $koneksi->query('SELECT COUNT(*) AS n FROM tipe_sepatu')->fetch_assoc()['n'];
if ($n_tipe === 0) {
    jalankan($koneksi, "INSERT INTO tipe_sepatu (nama_tipe) VALUES
        ('Sneakers'), ('Running'), ('Canvas'), ('Leather / Kulit'),
        ('Boots'), ('Heels'), ('Sandal / Slip On'), ('Bola / Futsal')");
    baris('[+] 8 tipe sepatu awal ditambahkan');
    $langkah++;
}

// --------------------------------------------------------------------------
// 5. bookings
// --------------------------------------------------------------------------
if (!tabel_ada($koneksi, 'bookings')) {
    jalankan($koneksi, "CREATE TABLE bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_booking VARCHAR(20) NOT NULL,
        user_id INT NOT NULL,
        service_id INT NOT NULL,
        paket_id INT DEFAULT NULL,
        tipe_sepatu VARCHAR(40) NOT NULL,
        jumlah_sepatu TINYINT UNSIGNED NOT NULL DEFAULT 1,
        catatan TEXT DEFAULT NULL,
        tanggal_booking DATE NOT NULL,
        jam_booking TIME NOT NULL,
        harga_layanan INT NOT NULL,
        harga_paket INT NOT NULL DEFAULT 0,
        total_harga INT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'menunggu_konfirmasi',
        catatan_admin TEXT DEFAULT NULL,
        dibuat_pada DATETIME NOT NULL,
        diperbarui_pada DATETIME NOT NULL,
        UNIQUE KEY uq_bookings_kode (kode_booking),
        KEY idx_bookings_user (user_id),
        KEY idx_bookings_status (status),
        KEY idx_bookings_slot (tanggal_booking, jam_booking),
        CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
        CONSTRAINT fk_bookings_paket FOREIGN KEY (paket_id) REFERENCES pakets(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    baris('[+] tabel bookings dibuat');
    $langkah++;
} else {
    baris('[=] tabel bookings sudah ada');
}

// --------------------------------------------------------------------------
// 6. order_status_history
// --------------------------------------------------------------------------
if (!tabel_ada($koneksi, 'order_status_history')) {
    jalankan($koneksi, "CREATE TABLE order_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        status VARCHAR(30) NOT NULL,
        catatan VARCHAR(255) DEFAULT NULL,
        diubah_oleh INT DEFAULT NULL,
        dibuat_pada DATETIME NOT NULL,
        KEY idx_history_booking (booking_id, dibuat_pada),
        CONSTRAINT fk_history_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        CONSTRAINT fk_history_user FOREIGN KEY (diubah_oleh) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    baris('[+] tabel order_status_history dibuat');
    $langkah++;
} else {
    baris('[=] tabel order_status_history sudah ada');
}

// --------------------------------------------------------------------------
// 7. transactions — tautan opsional pesanan kasir ke akun pelanggan
// --------------------------------------------------------------------------
// Kolom user_id bersifat OPSIONAL: pesanan lama tetap valid tanpa akun,
// dan pencocokan nomor HP tetap menjadi mekanisme utama. Kolom ini hanya
// memperkuat: begitu admin mengaitkan pesanan, tautannya eksplisit.
if (!kolom_ada($koneksi, 'transactions', 'user_id')) {
    jalankan($koneksi, "ALTER TABLE transactions
        ADD COLUMN user_id INT DEFAULT NULL AFTER no_hp,
        ADD INDEX idx_transactions_user (user_id),
        ADD CONSTRAINT fk_transactions_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE SET NULL");
    baris('[+] transactions.user_id ditambahkan (tautan opsional ke akun pelanggan)');
    $langkah++;
} else {
    baris('[=] transactions.user_id sudah ada');
}

baris('');
baris($langkah > 0
    ? "Selesai — $langkah perubahan diterapkan."
    : 'Selesai — database sudah mutakhir, tidak ada perubahan.');

if (!$isCli) {
    echo '</pre><p><a class="btn btn-primary" href="' . e(url('admin/data_booking.php')) . '">Buka data booking</a></p></main></body></html>';
}