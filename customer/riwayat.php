<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_customer();
$uid  = (int) $user['id'];

// ---------- Filter ----------
$f_status = trim($_GET['status'] ?? '');
$f_cari   = trim($_GET['cari'] ?? '');
$q        = "SELECT b.*, s.nama_layanan, p.nama_paket
             FROM bookings b
             JOIN services s ON s.id = b.service_id
             LEFT JOIN pakets p ON p.id = b.paket_id
             WHERE b.user_id = ?";
$tipe     = 'i';
$args     = [$uid];

if ($f_status !== '' && status_valid($f_status)) {
    $q .= ' AND b.status = ?';
    $tipe .= 's';
    $args[] = $f_status;
}
if ($f_cari !== '') {
    $q .= ' AND b.kode_booking LIKE ?';
    $tipe .= 's';
    $args[] = '%' . $f_cari . '%';
}
$q .= ' ORDER BY b.id DESC';

$stmt = mysqli_prepare($koneksi, $q);
mysqli_stmt_bind_param($stmt, $tipe, ...$args);
mysqli_stmt_execute($stmt);
$daftar = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Ringkasan per status
$ringkas = [];
$res = mysqli_query($koneksi, "SELECT status, COUNT(*) AS n FROM bookings WHERE user_id = $uid GROUP BY status");
while ($r = mysqli_fetch_assoc($res)) {
    $ringkas[$r['status']] = (int) $r['n'];
}
$total = array_sum($ringkas);

$judul = 'Riwayat Cucian';
$aktif = 'riwayat';
require dirname(__DIR__) . '/partials/header_customer.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Riwayat cucian</h1>
        <p class="page-date">Semua booking kamu, dari yang terbaru.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo e(url('customer/booking.php')); ?>">Booking baru</a>
</div>

<div class="stages" role="group" aria-label="Filter berdasarkan status">
    <a class="stage" href="riwayat.php" aria-pressed="<?php echo $f_status === '' ? 'true' : 'false'; ?>">
        <span class="stage-count num"><?php echo (int) $total; ?></span>
        <span class="stage-name">Semua</span>
    </a>
    <?php foreach (status_booking_semua() as $kode => $info): ?>
        <?php if (empty($ringkas[$kode]) && $f_status !== $kode) continue; ?>
        <a class="stage" href="riwayat.php?status=<?php echo e($kode); ?>"
           aria-pressed="<?php echo $f_status === $kode ? 'true' : 'false'; ?>">
            <span class="stage-count num"><?php echo (int) ($ringkas[$kode] ?? 0); ?></span>
            <span class="stage-name"><span class="stage-dot" data-warna="<?php echo e(status_warna($kode)); ?>" aria-hidden="true"></span><?php echo e($info['label']); ?></span>
        </a>
    <?php endforeach; ?>
</div>

<section class="panel" aria-labelledby="judul-daftar">
    <div class="panel-head panel-head-row">
        <div>
            <h2 class="panel-title" id="judul-daftar">Daftar booking</h2>
            <p class="panel-sub">Klik Detail untuk melihat progres dan rincian lengkap.</p>
        </div>
        <form class="search-inline" method="GET" action="riwayat.php">
            <?php if ($f_status !== ''): ?>
                <input type="hidden" name="status" value="<?php echo e($f_status); ?>">
            <?php endif; ?>
            <input class="input" type="search" name="cari" placeholder="Cari nomor booking…"
                   value="<?php echo e($f_cari); ?>" aria-label="Cari nomor booking">
            <button class="btn btn-outline" type="submit">Cari</button>
        </form>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty">
            <strong><?php echo $f_cari !== '' || $f_status !== '' ? 'Tidak ada yang cocok' : 'Belum ada booking'; ?></strong>
            <?php if ($f_cari === '' && $f_status === ''): ?>
                Buat booking pertama lewat halaman Booking.
            <?php else: ?>
                Coba kata kunci atau filter lain.
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="orders">
            <thead>
                <tr>
                    <th scope="col">Booking</th>
                    <th scope="col">Layanan</th>
                    <th scope="col">Jadwal</th>
                    <th scope="col" class="col-money">Total</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="col-action"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $b): ?>
                <tr>
                    <td>
                        <span class="tag num"><?php echo e($b['kode_booking']); ?></span>
                        <div class="date-under">Dibuat <?php echo e(tanggal_id($b['dibuat_pada'])); ?></div>
                    </td>
                    <td data-label="Layanan">
                        <div class="cell-main"><?php echo e($b['nama_layanan']); ?></div>
                        <div class="cell-sub"><?php echo e($b['tipe_sepatu']); ?> · <?php echo (int) $b['jumlah_sepatu']; ?> pasang · <?php echo e($b['nama_paket'] ?? '-'); ?></div>
                    </td>
                    <td data-label="Jadwal">
                        <div class="cell-main"><?php echo e(tanggal_id($b['tanggal_booking'])); ?></div>
                        <div class="cell-sub num"><?php echo e(jam_singkat($b['jam_booking'])); ?> WIB</div>
                    </td>
                    <td class="col-money num" data-label="Total"><?php echo e(rupiah($b['total_harga'])); ?></td>
                    <td data-label="Status">
                        <span class="badge" data-warna="<?php echo e(status_warna($b['status'])); ?>"><?php echo e(status_label($b['status'])); ?></span>
                    </td>
                    <td class="col-action">
                        <a class="btn-text" href="<?php echo e(url('customer/detail_booking.php?id=' . (int) $b['id'])); ?>">Detail</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>