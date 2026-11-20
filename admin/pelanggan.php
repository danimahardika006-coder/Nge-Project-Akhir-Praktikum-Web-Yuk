<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare($koneksi, 'SELECT * FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$target) {
    flash_set('Akun tidak ditemukan.', 'danger');
    header('Location: data_pelanggan.php');
    exit();
}

// Riwayat seluruh booking pelanggan
$stmt = mysqli_prepare(
    $koneksi,
    'SELECT b.*, s.nama_layanan, p.nama_paket
     FROM bookings b
     JOIN services s ON s.id = b.service_id
     LEFT JOIN pakets p ON p.id = b.paket_id
     WHERE b.user_id = ?
     ORDER BY b.id DESC'
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$bookings = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$selesai = 0; $berjalan = 0; $belanja = 0;
foreach ($bookings as $b) {
    if ($b['status'] === 'selesai') {
        $selesai++;
        $belanja += (int) $b['total_harga'];
    } elseif (in_array($b['status'], status_berjalan(), true)) {
        $berjalan++;
    }
}

$judul = 'Pelanggan ' . $target['nama_lengkap'];
$aktif = 'pelanggan';
require dirname(__DIR__) . '/partials/header_admin.php';
?>

<div class="page-head">
    <div>
        <p class="crumb"><a href="data_pelanggan.php">Pelanggan</a> / <?php echo e($target['nama_lengkap']); ?></p>
        <h1 class="page-title"><?php echo e($target['nama_lengkap']); ?></h1>
        <p class="page-date">Terdaftar <?php echo e(tanggal_panjang($target['dibuat_pada'])); ?></p>
    </div>
    <span class="badge" data-warna="<?php echo $target['status_akun'] === 'aktif' ? 'selesai' : 'batal'; ?>">
        <?php echo $target['status_akun'] === 'aktif' ? 'Akun aktif' : 'Akun nonaktif'; ?>
    </span>
</div>

<div class="stat-grid">
    <div class="stat">
        <div class="stat-label">Total booking</div>
        <div class="stat-value num"><?php echo count($bookings); ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Sedang berjalan</div>
        <div class="stat-value num"><?php echo (int) $berjalan; ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Selesai</div>
        <div class="stat-value num"><?php echo (int) $selesai; ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Total belanja</div>
        <div class="stat-value num"><?php echo e(rupiah($belanja)); ?></div>
    </div>
</div>

<section class="panel" aria-labelledby="judul-profil">
    <div class="panel-head">
        <h2 class="panel-title" id="judul-profil">Data akun</h2>
    </div>
    <dl class="details">
        <div><dt>Nama lengkap</dt><dd><?php echo e($target['nama_lengkap']); ?></dd></div>
        <div><dt>Username</dt><dd>@<?php echo e($target['username']); ?></dd></div>
        <div><dt>Email</dt><dd><?php echo e($target['email']); ?></dd></div>
        <div><dt>Nomor HP</dt><dd class="num"><?php echo e($target['no_hp'] ?: '—'); ?></dd></div>
        <div><dt>Peran</dt><dd><?php echo e(ucfirst($target['role'])); ?></dd></div>
        <div><dt>Terakhir diperbarui</dt><dd><?php echo e(tanggal_waktu_id($target['diperbarui_pada'])); ?></dd></div>
    </dl>
</section>

<section class="panel" aria-labelledby="judul-riwayat" style="margin-top:var(--s5)">
    <div class="panel-head">
        <h2 class="panel-title" id="judul-riwayat">Riwayat cucian</h2>
        <p class="panel-sub"><?php echo count($bookings); ?> booking dari pelanggan ini.</p>
    </div>

    <?php if (!$bookings): ?>
        <div class="empty"><strong>Belum ada booking</strong> Pelanggan ini belum memesan.</div>
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
            <?php foreach ($bookings as $b): ?>
                <tr>
                    <td>
                        <span class="tag num"><?php echo e($b['kode_booking']); ?></span>
                        <div class="date-under">Dibuat <?php echo e(tanggal_id($b['dibuat_pada'])); ?></div>
                    </td>
                    <td data-label="Layanan">
                        <div class="cell-main"><?php echo e($b['nama_layanan']); ?></div>
                        <div class="cell-sub"><?php echo e($b['tipe_sepatu']); ?> · <?php echo (int) $b['jumlah_sepatu']; ?> pasang</div>
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
                        <a class="btn-text" href="detail_booking.php?id=<?php echo (int) $b['id']; ?>">Kelola</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>