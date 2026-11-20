<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_customer();

$booking_id = (int) ($_GET['id'] ?? 0);
$booking    = $booking_id > 0 ? ambil_booking_customer($koneksi, $booking_id, (int) $user['id']) : null;

// Booking milik orang lain / tidak ada → jangan bocorkan apa pun.
if (!$booking) {
    http_response_code(404);
    $judul = 'Booking tidak ditemukan';
    $aktif = 'riwayat';
    require dirname(__DIR__) . '/partials/header_customer.php';
    echo '<div class="page-head"><div><h1 class="page-title">Booking tidak ditemukan</h1>'
       . '<p class="page-date">Booking ini tidak ada atau bukan milik akun kamu.</p></div></div>'
       . '<a class="btn btn-primary" href="' . e(url('customer/riwayat.php')) . '">Kembali ke riwayat</a>';
    require dirname(__DIR__) . '/partials/footer_customer.php';
    exit();
}

// ---------- Batalkan (hanya selama belum dikonfirmasi admin) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batalkan'])) {
    wajib_csrf();
    if ($booking['status'] !== 'menunggu_konfirmasi') {
        flash_set('Booking sudah diproses admin, jadi tidak bisa dibatalkan sendiri.', 'warning');
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE bookings SET status = 'dibatalkan', diperbarui_pada = NOW()
             WHERE id = ? AND user_id = ? AND status = 'menunggu_konfirmasi'"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user['id']);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            catat_status($koneksi, $booking_id, 'dibatalkan', (int) $user['id'], 'Dibatalkan oleh pelanggan');
            flash_set('Booking ' . $booking['kode_booking'] . ' dibatalkan.');
        } else {
            flash_set('Booking tidak bisa dibatalkan.', 'warning');
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: detail_booking.php?id=' . $booking_id);
    exit();
}

$riwayat  = riwayat_status($koneksi, $booking_id);
$tahap    = status_tahap();
$posisi   = array_search($booking['status'], $tahap, true);
$batal    = $booking['status'] === 'dibatalkan';
$progres  = $batal ? 0 : (($posisi === false ? 0 : (int) $posisi) + 1);
$persen   = (int) round($progres / count($tahap) * 100);

// Waktu per status (untuk timeline)
$waktu_status = [];
foreach ($riwayat as $r) {
    $waktu_status[$r['status']] = $r['dibuat_pada'];
}

$judul = 'Detail ' . $booking['kode_booking'];
$aktif = 'riwayat';
require dirname(__DIR__) . '/partials/header_customer.php';
?>

<div class="page-head">
    <div>
        <p class="crumb"><a href="<?php echo e(url('customer/riwayat.php')); ?>">Riwayat cucian</a> / <?php echo e($booking['kode_booking']); ?></p>
        <h1 class="page-title"><?php echo e($booking['kode_booking']); ?></h1>
        <p class="page-date">Dipesan <?php echo e(tanggal_waktu_id($booking['dibuat_pada'])); ?></p>
    </div>
    <span class="badge badge-lg" data-warna="<?php echo e(status_warna($booking['status'])); ?>"><?php echo e(status_label($booking['status'])); ?></span>
</div>

<?php if ($batal): ?>
    <div class="alert alert-danger" role="alert">
        Booking ini dibatalkan<?php echo $booking['catatan_admin'] ? ': ' . e($booking['catatan_admin']) : '.'; ?>
    </div>
<?php else: ?>
    <div class="alert alert-success" role="status"><?php echo e(status_pesan($booking['status'])); ?></div>
<?php endif; ?>

<section class="panel" aria-labelledby="judul-progres">
    <div class="panel-head panel-head-row">
        <div>
            <h2 class="panel-title" id="judul-progres">Progres pengerjaan</h2>
            <p class="panel-sub">Diperbarui otomatis setiap kali admin mengubah status.</p>
        </div>
        <div class="progress-num num"><?php echo $persen; ?>%</div>
    </div>

    <div class="panel-body">
        <div class="progressbar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="<?php echo $persen; ?>" aria-label="Progres pesanan">
            <span class="progressbar-fill" style="width:<?php echo $persen; ?>%"></span>
        </div>

        <ol class="track track-wide" aria-label="Tahap pesanan">
            <?php foreach ($tahap as $i => $kode): ?>
                <?php
                    if ($batal) {
                        $kelas = 'pending';
                    } elseif ($i < $posisi) {
                        $kelas = 'done';
                    } elseif ($i === $posisi) {
                        $kelas = 'current';
                    } else {
                        $kelas = 'pending';
                    }
                ?>
                <li class="<?php echo $kelas; ?>"<?php echo $kelas === 'current' ? ' aria-current="step"' : ''; ?>>
                    <span class="track-dot" aria-hidden="true"></span>
                    <span class="track-label"><?php echo e(status_label($kode)); ?></span>
                    <span class="track-time num"><?php echo !empty($waktu_status[$kode]) ? e(tanggal_id(date('Y-m-d', strtotime($waktu_status[$kode])))) : '—'; ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<div class="workspace">

    <section class="panel" aria-labelledby="judul-detail">
        <div class="panel-head">
            <h2 class="panel-title" id="judul-detail">Rincian pesanan</h2>
        </div>

        <dl class="details">
            <div>
                <dt>Layanan</dt>
                <dd><?php echo e($booking['nama_layanan']); ?></dd>
            </div>
            <div>
                <dt>Paket pengerjaan</dt>
                <dd><?php echo e($booking['nama_paket'] ?? 'Tidak dipilih'); ?></dd>
            </div>
            <div>
                <dt>Tipe sepatu</dt>
                <dd><?php echo e($booking['tipe_sepatu']); ?></dd>
            </div>
            <div>
                <dt>Jumlah sepatu</dt>
                <dd class="num"><?php echo (int) $booking['jumlah_sepatu']; ?> pasang</dd>
            </div>
            <div>
                <dt>Tanggal booking</dt>
                <dd><?php echo e(tanggal_panjang($booking['tanggal_booking'])); ?></dd>
            </div>
            <div>
                <dt>Jam kedatangan</dt>
                <dd class="num"><?php echo e(jam_singkat($booking['jam_booking'])); ?> WIB</dd>
            </div>
            <div>
                <dt>Estimasi selesai</dt>
                <dd>
                    <?php
                        $estimasi = !empty($booking['estimasi_hari']) ? (int) $booking['estimasi_hari'] : 2;
                        echo e(tanggal_panjang(date('Y-m-d', strtotime($booking['tanggal_booking'] . ' +' . $estimasi . ' days'))));
                    ?>
                </dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><span class="badge" data-warna="<?php echo e(status_warna($booking['status'])); ?>"><?php echo e(status_label($booking['status'])); ?></span></dd>
            </div>
            <?php if (!empty($booking['catatan'])): ?>
            <div class="span-2">
                <dt>Catatan kamu</dt>
                <dd class="plain"><?php echo nl2br(e($booking['catatan'])); ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($booking['catatan_admin'])): ?>
            <div class="span-2">
                <dt>Catatan dari admin</dt>
                <dd class="plain"><?php echo nl2br(e($booking['catatan_admin'])); ?></dd>
            </div>
            <?php endif; ?>

            <div class="details-total">
                <dt>Total harga</dt>
                <dd class="num"><?php echo e(rupiah($booking['total_harga'])); ?></dd>
                <div class="calc-note">
                    (<?php echo e(rupiah($booking['harga_layanan'])); ?> + <?php echo e(rupiah($booking['harga_paket'])); ?>)
                    &times; <?php echo (int) $booking['jumlah_sepatu']; ?> pasang
                </div>
            </div>
        </dl>
    </section>

    <aside class="panel sticky" aria-labelledby="judul-riwayat">
        <div class="panel-head">
            <h2 class="panel-title" id="judul-riwayat">Riwayat status</h2>
            <p class="panel-sub">Setiap perubahan dicatat.</p>
        </div>

        <?php if (!$riwayat): ?>
            <div class="empty">Belum ada riwayat.</div>
        <?php else: ?>
        <ul class="history">
            <?php foreach (array_reverse($riwayat) as $r): ?>
            <li>
                <span class="history-dot" data-warna="<?php echo e(status_warna($r['status'])); ?>" aria-hidden="true"></span>
                <div>
                    <strong><?php echo e(status_label($r['status'])); ?></strong>
                    <span class="history-time num"><?php echo e(tanggal_waktu_id($r['dibuat_pada'])); ?></span>
                    <?php if (!empty($r['catatan'])): ?>
                        <span class="history-note"><?php echo e($r['catatan']); ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if ($booking['status'] === 'menunggu_konfirmasi'): ?>
        <div class="panel-body">
            <form method="POST" onsubmit="return confirm('Batalkan booking ini? Tindakan tidak bisa dibatalkan.');">
                <?php echo csrf_field(); ?>
                <button class="btn btn-outline btn-block" type="submit" name="batalkan" value="1">Batalkan booking</button>
            </form>
            <p class="field-hint" style="text-align:center">Pembatalan hanya bisa selama belum dikonfirmasi admin.</p>
        </div>
        <?php endif; ?>
    </aside>

</div>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>