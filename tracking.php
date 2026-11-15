<?php
session_start();
include("koneksi.php");
// ---------- Fungsi bantu (dibuat mandiri agar halaman ini tidak bergantung file lain) ----------
if (!function_exists('e')) {
    function e($teks) {
        return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka) {
        return "Rp " . number_format((float) $angka, 0, ',', '.');
    }
}

if (!function_exists('tanggal_id')) {
    function tanggal_id($tanggal) {
        $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $t = strtotime($tanggal);
        return $t ? date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t) : $tanggal;
    }
}

// Samarkan nomor HP untuk halaman publik: 0812••••678
if (!function_exists('samarkan_hp')) {
    function samarkan_hp($hp) {
        $hp = trim((string) $hp);
        $n  = strlen($hp);
        if ($n <= 7) {
            return $hp;
        }
        return substr($hp, 0, 4) . str_repeat('•', $n - 7) . substr($hp, -3);
    }
}


// Tahap pesanan berurutan beserta pesan untuk pelanggan
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

if ($input !== null) {
    $kode = strtoupper($input);

    if ($kode === "") {
        $error = "Masukkan nomor pesanan terlebih dahulu.";
    } elseif (!preg_match('/^TRX-\d{8}-\d{3}$/', $kode)) {
        $error = "Format nomor pesanan belum sesuai. Contoh yang benar: TRX-20261115-412.";
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
    </div>
</header>

<main class="page page-narrow">

    <div class="track-intro">
        <h1 class="page-title">Lacak pesanan</h1>
        <p class="page-date">Masukkan nomor pesanan yang tertera di nota untuk melihat sudah sampai tahap mana sepatu kamu.</p>
    </div>

    <form class="search" method="GET" action="tracking.php" novalidate>
        <div class="field">
            <label for="no_pesanan">Nomor pesanan</label>
            <input class="input input-code" type="text" id="no_pesanan" name="no_pesanan"
                   placeholder="TRX-20261115-412" maxlength="20"
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