<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_admin();
$admin_id = (int) $user['id'];

// ---------- Aksi admin ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    $id     = (int) ($_POST['booking_id'] ?? 0);
    $aksi   = $_POST['aksi'] ?? '';

    // ---------- Tautkan pesanan kasir ke akun pelanggan ----------
    // Dipakai dari halaman detail pelanggan. Pesanan walk-in tetap tanpa akun
    // secara default; aksi ini hanya memperkuat tautan yang sudah ada.
    if ($aksi === 'tautkan_kasir') {
        $trx_id = (int) ($_POST['trx_id'] ?? 0);
        $uid    = (int) ($_POST['user_id'] ?? 0);

        $stmt = mysqli_prepare($koneksi, "SELECT id, no_pesanan, user_id FROM transactions WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $trx_id);
        mysqli_stmt_execute($stmt);
        $trx = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($koneksi, "SELECT id, nama_lengkap FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $cust = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$trx) {
            flash_set('Pesanan kasir tidak ditemukan.', 'danger');
        } elseif (!$cust) {
            flash_set('Akun pelanggan tidak ditemukan.', 'danger');
        } elseif ((int) $trx['user_id'] === $uid) {
            flash_set('Pesanan ' . $trx['no_pesanan'] . ' sudah tertaut ke akun ini.', 'info');
        } else {
            $upd = mysqli_prepare($koneksi, 'UPDATE transactions SET user_id = ? WHERE id = ?');
            mysqli_stmt_bind_param($upd, 'ii', $uid, $trx_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            flash_set('Pesanan ' . $trx['no_pesanan'] . ' ditautkan ke akun ' . $cust['nama_lengkap'] . '.');
        }

        header('Location: pelanggan.php?id=' . $uid);
        exit();
    }

    // Pastikan booking ada
    $stmt = mysqli_prepare($koneksi, 'SELECT id, kode_booking, status FROM bookings WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $bk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$bk) {
        flash_set('Booking tidak ditemukan.', 'danger');
    } elseif ($aksi === 'konfirmasi' && $bk['status'] === 'menunggu_konfirmasi') {
        $upd = mysqli_prepare($koneksi, "UPDATE bookings SET status='dikonfirmasi', diperbarui_pada=NOW() WHERE id=? AND status='menunggu_konfirmasi'");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        if (mysqli_stmt_affected_rows($upd) > 0) {
            catat_status($koneksi, $id, 'dikonfirmasi', $admin_id, trim($_POST['catatan'] ?? '') ?: 'Dikonfirmasi admin');
            flash_set('Booking ' . $bk['kode_booking'] . ' dikonfirmasi.');
        }
        mysqli_stmt_close($upd);
    } elseif ($aksi === 'tolak' && $bk['status'] === 'menunggu_konfirmasi') {
        $alasan = trim($_POST['catatan'] ?? '') ?: 'Ditolak admin';
        $upd = mysqli_prepare($koneksi, "UPDATE bookings SET status='dibatalkan', catatan_admin=?, diperbarui_pada=NOW() WHERE id=? AND status='menunggu_konfirmasi'");
        mysqli_stmt_bind_param($upd, 'si', $alasan, $id);
        mysqli_stmt_execute($upd);
        if (mysqli_stmt_affected_rows($upd) > 0) {
            catat_status($koneksi, $id, 'dibatalkan', $admin_id, $alasan);
            flash_set('Booking ' . $bk['kode_booking'] . ' ditolak.');
        }
        mysqli_stmt_close($upd);
    } elseif ($aksi === 'status') {
        $status_baru = trim($_POST['status'] ?? '');
        $catatan     = trim($_POST['catatan'] ?? '');
        if (!status_valid($status_baru)) {
            flash_set('Status tidak dikenal.', 'danger');
        } elseif ($status_baru === $bk['status']) {
            flash_set('Status tidak berubah.', 'warning');
        } else {
            $upd = mysqli_prepare($koneksi, 'UPDATE bookings SET status=?, diperbarui_pada=NOW() WHERE id=?');
            mysqli_stmt_bind_param($upd, 'si', $status_baru, $id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            catat_status($koneksi, $id, $status_baru, $admin_id, $catatan !== '' ? $catatan : null);
            flash_set('Status ' . $bk['kode_booking'] . ' diubah ke ' . status_label($status_baru) . '.');
        }
    } elseif ($aksi === 'catatan_admin') {
        $catatan = trim($_POST['catatan_admin'] ?? '');
        $upd = mysqli_prepare($koneksi, 'UPDATE bookings SET catatan_admin=?, diperbarui_pada=NOW() WHERE id=?');
        mysqli_stmt_bind_param($upd, 'si', $catatan, $id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        flash_set('Catatan untuk pelanggan disimpan.');
    }

    header('Location: data_booking.php' . (isset($_POST['balik_filter']) ? '?' . $_POST['balik_filter'] : ''));
    exit();
}

// ---------- Filter ----------
$f_status = trim($_GET['status'] ?? '');
$f_cari   = trim($_GET['cari'] ?? '');

$q    = "SELECT b.*, s.nama_layanan, p.nama_paket, u.nama_lengkap, u.no_hp, u.email
         FROM bookings b
         JOIN services s ON s.id = b.service_id
         LEFT JOIN pakets p ON p.id = b.paket_id
         JOIN users u ON u.id = b.user_id
         WHERE 1=1";
$tipe = '';
$args = [];

if ($f_status !== '' && status_valid($f_status)) {
    $q .= ' AND b.status = ?';
    $tipe .= 's';
    $args[] = $f_status;
}
if ($f_cari !== '') {
    $q .= ' AND (b.kode_booking LIKE ? OR u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.no_hp LIKE ?)';
    $tipe .= 'ssss';
    $like = '%' . $f_cari . '%';
    array_push($args, $like, $like, $like, $like);
}
$q .= ' ORDER BY (b.status = "menunggu_konfirmasi") DESC, b.id DESC';

$stmt = mysqli_prepare($koneksi, $q);
if ($tipe !== '') {
    mysqli_stmt_bind_param($stmt, $tipe, ...$args);
}
mysqli_stmt_execute($stmt);
$daftar = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Statistik
$ringkas = [];
$res = mysqli_query($koneksi, 'SELECT status, COUNT(*) AS n FROM bookings GROUP BY status');
while ($r = mysqli_fetch_assoc($res)) {
    $ringkas[$r['status']] = (int) $r['n'];
}
$total_booking = array_sum($ringkas);

// Pendapatan booking: total booking yang sudah selesai
$pendapatan_booking = 0;
$q = mysqli_query($koneksi, "SELECT COALESCE(SUM(total_harga),0) AS total FROM bookings WHERE status = 'selesai'");
if ($q) {
    $pendapatan_booking = (int) mysqli_fetch_assoc($q)['total'];
}

$judul = 'Data Booking';
$aktif = 'booking';
require dirname(__DIR__) . '/partials/header_admin.php';

$filter_querystring = http_build_query(array_filter(['status' => $f_status, 'cari' => $f_cari]));
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Data booking</h1>
        <p class="page-date">Semua booking pelanggan. Konfirmasi, tolak, atau perbarui status pengerjaan.</p>
    </div>
</div>

<div class="stat-grid stat-grid-5">
    <div class="stat">
        <div class="stat-label">Total booking</div>
        <div class="stat-value num"><?php echo (int) $total_booking; ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Menunggu konfirmasi</div>
        <div class="stat-value num"><?php echo (int) ($ringkas['menunggu_konfirmasi'] ?? 0); ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Sedang dikerjakan</div>
        <div class="stat-value num"><?php
            echo (int) (($ringkas['sepatu_diterima'] ?? 0) + ($ringkas['dicuci'] ?? 0) + ($ringkas['quality_control'] ?? 0));
        ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Siap diambil</div>
        <div class="stat-value num"><?php echo (int) ($ringkas['siap_diambil'] ?? 0); ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Pendapatan booking</div>
        <div class="stat-value num"><?php echo e(rupiah($pendapatan_booking)); ?></div>
        <div class="stat-note">Dari booking berstatus Selesai</div>
    </div>
</div>

<div class="stages" role="group" aria-label="Filter status">
    <a class="stage" href="data_booking.php" aria-pressed="<?php echo $f_status === '' ? 'true' : 'false'; ?>">
        <span class="stage-count num"><?php echo (int) $total_booking; ?></span>
        <span class="stage-name">Semua</span>
    </a>
    <?php foreach (status_booking_semua() as $kode => $info): ?>
        <a class="stage" href="data_booking.php?status=<?php echo e($kode); ?>"
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
            <p class="panel-sub">Booking baru tampil paling atas.</p>
        </div>
        <form class="search-inline" method="GET" action="data_booking.php">
            <?php if ($f_status !== ''): ?>
                <input type="hidden" name="status" value="<?php echo e($f_status); ?>">
            <?php endif; ?>
            <input class="input" type="search" name="cari" placeholder="Cari kode / nama / email / HP…"
                   value="<?php echo e($f_cari); ?>" aria-label="Cari booking">
            <button class="btn btn-outline" type="submit">Cari</button>
        </form>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty">
            <strong>Tidak ada booking</strong>
            <?php echo $f_cari !== '' || $f_status !== '' ? 'Coba filter lain.' : 'Belum ada pelanggan yang melakukan booking.'; ?>
        </div>
    <?php else: ?>

    <div class="booking-cards">
        <?php foreach ($daftar as $b): ?>
        <?php $id = (int) $b['id']; ?>
        <article class="booking-card" data-status="<?php echo e($b['status']); ?>">
            <header class="bc-head">
                <div class="bc-id">
                    <span class="tag num"><?php echo e($b['kode_booking']); ?></span>
                    <span class="bc-waktu">Dipesan <?php echo e(tanggal_waktu_id($b['dibuat_pada'])); ?></span>
                </div>
                <span class="badge" data-warna="<?php echo e(status_warna($b['status'])); ?>"><?php echo e(status_label($b['status'])); ?></span>
            </header>

            <div class="bc-grid">
                <div class="bc-col">
                    <h3 class="bc-label">Pelanggan</h3>
                    <p class="bc-value"><?php echo e($b['nama_lengkap']); ?></p>
                    <p class="bc-sub num"><?php echo e($b['no_hp']); ?></p>
                    <p class="bc-sub"><?php echo e($b['email']); ?></p>
                    <a class="bc-link" href="<?php echo e(url('admin/pelanggan.php?id=' . (int) $b['user_id'])); ?>">Lihat profil &amp; riwayat</a>
                </div>

                <div class="bc-col">
                    <h3 class="bc-label">Pesanan</h3>
                    <p class="bc-value"><?php echo e($b['nama_layanan']); ?></p>
                    <p class="bc-sub">Paket <?php echo e($b['nama_paket'] ?? '-'); ?></p>
                    <p class="bc-sub"><?php echo e($b['tipe_sepatu']); ?> · <?php echo (int) $b['jumlah_sepatu']; ?> pasang</p>
                </div>

                <div class="bc-col">
                    <h3 class="bc-label">Jadwal</h3>
                    <p class="bc-value"><?php echo e(tanggal_panjang($b['tanggal_booking'])); ?></p>
                    <p class="bc-sub num">Jam <?php echo e(jam_singkat($b['jam_booking'])); ?> WIB</p>
                </div>

                <div class="bc-col bc-total">
                    <h3 class="bc-label">Total</h3>
                    <p class="bc-value num"><?php echo e(rupiah($b['total_harga'])); ?></p>
                    <p class="bc-sub num">(<?php echo e(rupiah($b['harga_layanan'])); ?> + <?php echo e(rupiah($b['harga_paket'])); ?>) &times; <?php echo (int) $b['jumlah_sepatu']; ?></p>
                </div>
            </div>

            <?php if (!empty($b['catatan'])): ?>
                <p class="bc-catatan"><strong>Catatan pelanggan:</strong> <?php echo nl2br(e($b['catatan'])); ?></p>
            <?php endif; ?>

            <div class="bc-actions">
                <?php if ($b['status'] === 'menunggu_konfirmasi'): ?>
                    <form method="POST" class="inline-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="balik_filter" value="<?php echo e($filter_querystring); ?>">
                        <input type="hidden" name="aksi" value="konfirmasi">
                        <button class="btn btn-primary btn-sm" type="submit">Konfirmasi booking</button>
                    </form>

                    <form method="POST" class="inline-form" onsubmit="return confirm('Tolak booking ini?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="balik_filter" value="<?php echo e($filter_querystring); ?>">
                        <input type="hidden" name="aksi" value="tolak">
                        <input class="input input-sm" type="text" name="catatan" placeholder="Alasan penolakan (opsional)">
                        <button class="btn btn-outline btn-sm btn-danger-text" type="submit">Tolak</button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="inline-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="balik_filter" value="<?php echo e($filter_querystring); ?>">
                        <input type="hidden" name="aksi" value="status">
                        <label class="sr-only" for="st-<?php echo $id; ?>">Ubah status</label>
                        <select class="select select-sm" id="st-<?php echo $id; ?>" name="status">
                            <?php foreach (status_booking_semua() as $kode => $info): ?>
                                <option value="<?php echo e($kode); ?>"
                                        data-mundur="<?php echo status_mundur($b['status'], $kode) ? '1' : '0'; ?>"
                                        <?php echo $kode === $b['status'] ? 'selected' : ''; ?>><?php echo e($info['label']); ?><?php echo status_mundur($b['status'], $kode) ? ' ↩' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="input input-sm" type="text" name="catatan" placeholder="Catatan status (opsional)" maxlength="255">
                        <button class="btn btn-outline btn-sm" type="submit">Simpan status</button>
                    </form>
                <?php endif; ?>

                <a class="btn btn-outline btn-sm" href="<?php echo e(url('admin/detail_booking.php?id=' . $id)); ?>">Detail &amp; riwayat</a>
            </div>

            <details class="bc-detail">
                <summary>Catatan untuk pelanggan</summary>
                <form method="POST" class="bc-catatan-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="balik_filter" value="<?php echo e($filter_querystring); ?>">
                    <input type="hidden" name="aksi" value="catatan_admin">
                    <textarea class="input textarea" name="catatan_admin" rows="2" maxlength="1000"
                              placeholder="Contoh: sol sudah dikeringkan, siap diambil besok."><?php echo e($b['catatan_admin']); ?></textarea>
                    <button class="btn btn-outline btn-sm" type="submit">Simpan catatan</button>
                </form>
            </details>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>

<script>
// Konfirmasi hanya muncul kalau status dipilih mundur (ditandai ↩ di daftar).
document.querySelectorAll('select[name="status"]').forEach(function (sel) {
    sel.form.addEventListener('submit', function (ev) {
        var opt = sel.options[sel.selectedIndex];
        if (opt && opt.getAttribute('data-mundur') === '1') {
            if (!confirm('Mundurkan status ke "' + opt.text.replace(' ↩', '') + '"?')) {
                ev.preventDefault();
            }
        }
    });
});
</script>
