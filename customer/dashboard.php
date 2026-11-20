<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_customer();
$uid  = (int) $user['id'];

// ---------- Ringkasan ----------
$stmt = mysqli_prepare($koneksi, 'SELECT COUNT(*) AS total FROM bookings WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$total_booking = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
mysqli_stmt_close($stmt);

$tmp = implode(',', array_map(fn($s) => "'" . $s . "'", status_berjalan()));
$stmt = mysqli_prepare($koneksi, "SELECT COUNT(*) AS n FROM bookings WHERE user_id = ? AND status IN ($tmp)");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$berjalan = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($koneksi, "SELECT COUNT(*) AS n, COALESCE(SUM(total_harga),0) AS belanja
                                  FROM bookings WHERE user_id = ? AND status = 'selesai'");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$selesai_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$selesai = (int) $selesai_row['n'];
$belanja = (int) $selesai_row['belanja'];

// ---------- Booking terbaru ----------
$stmt = mysqli_prepare(
    $koneksi,
    'SELECT b.*, s.nama_layanan FROM bookings b
     JOIN services s ON s.id = b.service_id
     WHERE b.user_id = ? ORDER BY b.id DESC LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$terbaru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
mysqli_stmt_close($stmt);

// ---------- Daftar terakhir ----------
$stmt = mysqli_prepare(
    $koneksi,
    'SELECT b.*, s.nama_layanan FROM bookings b
     JOIN services s ON s.id = b.service_id
     WHERE b.user_id = ? ORDER BY b.id DESC LIMIT 5'
);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$daftar = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$judul = 'Dashboard';
$aktif = 'dashboard';
require dirname(__DIR__) . '/partials/header_customer.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Halo, <?php echo e($user['nama_lengkap']); ?></h1>
        <p class="page-date">Ringkasan cucian sepatu kamu.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo e(url('customer/booking.php')); ?>">Buat booking baru</a>
</div>

<div class="stat-grid">
    <div class="stat">
        <div class="stat-label">Total booking</div>
        <div class="stat-value num"><?php echo (int) $total_booking; ?></div>
        <div class="stat-note">Semua booking yang pernah dibuat</div>
    </div>
    <div class="stat">
        <div class="stat-label">Sedang berjalan</div>
        <div class="stat-value num"><?php echo (int) $berjalan; ?></div>
        <div class="stat-note">Belum selesai / belum diambil</div>
    </div>
    <div class="stat">
        <div class="stat-label">Selesai</div>
        <div class="stat-value num"><?php echo (int) $selesai; ?></div>
        <div class="stat-note">Pesanan yang sudah tuntas</div>
    </div>
    <div class="stat">
        <div class="stat-label">Total belanja</div>
        <div class="stat-value num"><?php echo e(rupiah($belanja)); ?></div>
        <div class="stat-note">Dari booking berstatus selesai</div>
    </div>
</div>

<div class="workspace">

    <section class="panel" aria-labelledby="judul-riwayat">
        <div class="panel-head panel-head-row">
            <div>
                <h2 class="panel-title" id="judul-riwayat">Riwayat terakhir</h2>
                <p class="panel-sub">5 booking terbaru.</p>
            </div>
            <a class="link-more" href="<?php echo e(url('customer/riwayat.php')); ?>">Lihat semua</a>
        </div>

        <?php if (!$daftar): ?>
            <div class="empty">
                <strong>Belum ada booking</strong>
                Buat booking pertama, lalu pantau progresnya di sini.
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="orders">
                <thead>
                    <tr>
                        <th scope="col">Booking</th>
                        <th scope="col">Layanan</th>
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
                            <div class="date-under"><?php echo e(tanggal_id($b['tanggal_booking'])); ?> · <?php echo e(jam_singkat($b['jam_booking'])); ?></div>
                        </td>
                        <td data-label="Layanan">
                            <div class="cell-main"><?php echo e($b['nama_layanan']); ?></div>
                            <div class="cell-sub"><?php echo e($b['tipe_sepatu']); ?> · <?php echo (int) $b['jumlah_sepatu']; ?> pasang</div>
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

    <aside class="panel sticky" aria-labelledby="judul-terbaru">
        <div class="panel-head">
            <h2 class="panel-title" id="judul-terbaru">Booking terbaru</h2>
            <p class="panel-sub">Status diperbarui admin secara langsung.</p>
        </div>

        <?php if ($terbaru): ?>
        <div class="panel-body">
            <div class="latest-top">
                <span class="tag num"><?php echo e($terbaru['kode_booking']); ?></span>
                <span class="badge" data-warna="<?php echo e(status_warna($terbaru['status'])); ?>"><?php echo e(status_label($terbaru['status'])); ?></span>
            </div>

            <p class="latest-pesan"><?php echo e(status_pesan($terbaru['status'])); ?></p>

            <dl class="mini-details">
                <div><dt>Layanan</dt><dd><?php echo e($terbaru['nama_layanan']); ?></dd></div>
                <div><dt>Tipe sepatu</dt><dd><?php echo e($terbaru['tipe_sepatu']); ?></dd></div>
                <div><dt>Jumlah</dt><dd class="num"><?php echo (int) $terbaru['jumlah_sepatu']; ?> pasang</dd></div>
                <div><dt>Jadwal</dt><dd><?php echo e(tanggal_panjang($terbaru['tanggal_booking'])); ?>, <?php echo e(jam_singkat($terbaru['jam_booking'])); ?></dd></div>
                <div><dt>Total</dt><dd class="num strong"><?php echo e(rupiah($terbaru['total_harga'])); ?></dd></div>
            </dl>

            <a class="btn btn-primary btn-block" href="<?php echo e(url('customer/detail_booking.php?id=' . (int) $terbaru['id'])); ?>">Lacak booking ini</a>
        </div>
        <?php else: ?>
        <div class="empty">
            <strong>Belum ada booking</strong>
            <a class="btn btn-primary" href="<?php echo e(url('customer/booking.php')); ?>">Mulai booking</a>
        </div>
        <?php endif; ?>
    </aside>

</div>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>