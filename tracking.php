<?php
require_once __DIR__ . '/partials/init.php';

// e(), rupiah(), tanggal_id(), samarkan_hp() tersedia dari partials/init.php

// ---------- Lacak pesanan kasir (tabel transactions, format TRX-…) ----------
$tahap_pesan = [
    'Antrean' => 'Sepatu sudah kami terima dan sedang menunggu giliran dicuci.',
    'Dicuci'  => 'Sepatu kamu sedang dalam proses pencucian.',
    'Selesai' => 'Sepatu sudah bersih dan siap diambil.',
    'Diambil' => 'Sepatu sudah diambil. Terima kasih sudah mencuci di sini.',
];
$tahap = array_keys($tahap_pesan);

$input = isset($_GET['no_pesanan']) ? trim($_GET['no_pesanan']) : null;
$error = "";
$error_tipe = "danger";
$order = null;
$booking_saya = null;
$reverse = [];

// ---------- Lacak booking online (tabel bookings, format BK-…) ----------
if ($input !== null) {
    $kode = strtoupper($input);

    if ($kode === "") {
        $error = "Masukkan nomor pesanan terlebih dahulu.";
    } elseif (preg_match('/^BK-[0-9A-Z-]+$/', $kode)) {
        if (!is_customer()) {
            $error = "Booking online hanya bisa dilihat dari akun pemiliknya. Silakan masuk lebih dulu.";
            $error_tipe = "warning";
        } else {
            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT b.*, s.nama_layanan, p.nama_paket
                 FROM bookings b
                 JOIN services s ON s.id = b.service_id
                 LEFT JOIN pakets p ON p.id = b.paket_id
                 WHERE b.kode_booking = ? AND b.user_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, 'si', $kode, $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $booking_saya = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
            mysqli_stmt_close($stmt);

            if (!$booking_saya) {
                $error = "Booking $kode tidak ditemukan di akun kamu.";
                $error_tipe = "warning";
            } else {
                header('Location: customer/detail_booking.php?id=' . (int) $booking_saya['id']);
                exit();
            }
        }
    } elseif (!preg_match('/^TRX-\d{8}-\d{3}$/', $kode)) {
        $error = "Format nomor belum sesuai. Contoh: TRX-20261115-412 atau BK-20261115-A1B2.";
    } else {
        // Batasi pencarian: maksimal 10 kali per menit per pengunjung
        $sekarang  = time();
        $riwayat   = array_filter($_SESSION['lacak'] ?? [], function ($t) use ($sekarang) {
            return $sekarang - $t < 60;
        });

        if (count($riwayat) >= 10) {
            $error = "Terlalu banyak pencarian dalam waktu singkat. Tunggu sebentar lalu coba lagi.";
        } else {
            $riwayat[] = $sekarang;

            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT t.no_pesanan, t.nama_pelanggan, t.no_hp, t.tanggal_masuk, t.status, t.total_harga, s.nama_layanan
                 FROM transactions t
                 JOIN services s ON t.service_id = s.id
                 WHERE t.no_pesanan = ?
                 LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, "s", $kode);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $order  = mysqli_fetch_assoc($result);

            if (!$order) {
                $error = "Pesanan dengan nomor $kode tidak ditemukan. Periksa kembali nomor di nota kamu.";
                $error_tipe = "warning";
            }
        }
        $_SESSION['lacak'] = array_values($riwayat);
    }
}

// Posisi tahap saat ini (-1 bila status tidak dikenal)
$idx = -1;
if ($order) {
    $found = array_search($order['status'], $tahap, true);
    $idx   = ($found === false) ? -1 : $found;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Pesanan · Cuci Sepatu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="tracking.php">
            <span class="brand-mark" aria-hidden="true"></span>
            Cuci Sepatu
        </a>
        <nav class="mainnav" aria-label="Navigasi">
            <?php if (is_customer()): ?>
                <a class="nav-link" href="customer/dashboard.php">Dashboard</a>
                <a class="nav-link" href="customer/booking.php">Booking</a>
                <a class="nav-link" href="customer/riwayat.php">Riwayat</a>
                <a class="nav-link" href="logout.php">Keluar</a>
            <?php elseif (is_admin()): ?>
                <a class="nav-link" href="dashboard.php">Panel admin</a>
            <?php else: ?>
                <a class="nav-link" href="login.php">Masuk</a>
                <a class="nav-link nav-link-aktif" href="registrasi.php">Daftar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="page page-narrow">

    <div class="track-intro">
        <h1 class="page-title">Lacak pesanan</h1>
        <p class="page-date">Masukkan nomor pesanan dari nota, atau nomor booking <strong>BK-</strong> untuk pesanan online yang kamu buat sendiri.</p>
    </div>

    <form class="search" method="GET" action="tracking.php" novalidate>
        <div class="field">
            <label for="no_pesanan">Nomor pesanan atau booking</label>
            <input class="input input-code" type="text" id="no_pesanan" name="no_pesanan"
                   placeholder="TRX-20261115-412 / BK-20261115-A1B2" maxlength="24"
                   autocomplete="off" autocapitalize="characters" spellcheck="false"
                   value="<?php echo e($input ?? ''); ?>"
                   <?php echo $error !== "" ? 'aria-invalid="true" aria-describedby="pesan-error"' : ''; ?>>
        </div>
        <button class="btn btn-primary" type="submit">Lacak pesanan</button>
    </form>

    <?php if ($error !== ""): ?>
        <div class="alert alert-<?php echo e($error_tipe); ?>" id="pesan-error" role="alert">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($order): ?>
    <section class="panel result" aria-labelledby="hasil-judul">

        <div class="result-head">
            <span class="tag num"><?php echo e($order['no_pesanan']); ?></span>
            <span class="badge" data-status="<?php echo e($order['status']); ?>"><?php echo e($order['status']); ?></span>
        </div>

        <div class="result-body">
            <h2 class="result-status" id="hasil-judul">
                <?php echo e($tahap_pesan[$order['status']] ?? 'Status pesanan: ' . $order['status']); ?>
            </h2>

            <ol class="track" aria-label="Progres pesanan">
                <?php foreach ($tahap as $i => $nama): ?>
                    <?php
                        if ($i < $idx)       { $kelas = 'done';    $ket = 'sudah dilewati'; }
                        elseif ($i === $idx) { $kelas = 'current'; $ket = 'tahap saat ini'; }
                        else                 { $kelas = 'pending'; $ket = 'belum dimulai'; }
                    ?>
                    <li class="<?php echo $kelas; ?>"<?php echo $kelas === 'current' ? ' aria-current="step"' : ''; ?>>
                        <span class="track-dot" aria-hidden="true"></span>
                        <span class="track-label"><?php echo e($nama); ?><span class="sr-only">, <?php echo $ket; ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <dl class="details">
            <div>
                <dt>Nama pelanggan</dt>
                <dd><?php echo e($order['nama_pelanggan']); ?></dd>
            </div>
            <div>
                <dt>Nomor HP</dt>
                <dd class="num"><?php echo e(samarkan_hp($order['no_hp'])); ?></dd>
            </div>
            <div>
                <dt>Layanan</dt>
                <dd><?php echo e($order['nama_layanan']); ?></dd>
            </div>
            <div>
                <dt>Tanggal masuk</dt>
                <dd><?php echo e(tanggal_id($order['tanggal_masuk'])); ?></dd>
            </div>
            <div class="details-total">
                <dt>Total harga</dt>
                <dd class="num"><?php echo e(rupiah($order['total_harga'])); ?></dd>
            </div>
        </dl>

    </section>
    <?php endif; ?>

</main>

</body>
</html>