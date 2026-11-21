<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_customer();
$uid  = (int) $user['id'];

// ---------- Pesanan kasir (walk-in) milik pelanggan ini ----------
// Dicocokkan lewat nomor HP pada akun — jadi pelanggan yang langsung datang
// ke outlet (tanpa booking online) tetap melihat cuciannya di sini.
$kasir         = transaksi_pelanggan($koneksi, (string) ($user['no_hp'] ?? ''));
$kasir_ringkas = transaksi_pelanggan_ringkas($koneksi, (string) ($user['no_hp'] ?? ''));

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

// ---------- Filter untuk pesanan kasir (di memori, jumlahnya kecil) ----------
$cari_kasir = mb_strtolower($f_cari);
$kasir_tampil = array_values(array_filter($kasir, function ($t) use ($f_status, $cari_kasir) {
    if ($f_status !== '' && $t['status'] !== $f_status) {
        return false;
    }
    if ($cari_kasir !== '') {
        $teks = mb_strtolower($t['no_pesanan'] . ' ' . $t['nama_layanan']);
        if (mb_strpos($teks, $cari_kasir) === false) {
            return false;
        }
    }
    return true;
}));

// ---------- Ringkasan per status ----------
$ringkas = [];
$res = mysqli_query($koneksi, "SELECT status, COUNT(*) AS n FROM bookings WHERE user_id = $uid GROUP BY status");
while ($r = mysqli_fetch_assoc($res)) {
    $ringkas[$r['status']] = (int) $r['n'];
}
$total = array_sum($ringkas);

// ---------- Belanja, dipisah per sumber ----------
// Total belanja bergantung pada tab yang sedang dilihat:
//   semua   → booking online selesai + pesanan outlet selesai
//   booking → booking online selesai saja
//   kasir   → pesanan outlet selesai saja
// Dihitung di server supaya angkanya konsisten dan tidak bisa diakali dari browser.
$belanja_booking = 0;
$res = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(total_harga), 0) AS n FROM bookings
     WHERE user_id = $uid AND status = 'selesai'"
);
if ($res) {
    $belanja_booking = (int) mysqli_fetch_assoc($res)['n'];
}
$belanja_kasir = (int) $kasir_ringkas['belanja'];
$belanja_semua = $belanja_booking + $belanja_kasir;

$total_semua = $total + $kasir_ringkas['total'];

$judul = 'Riwayat Cucian';
$aktif = 'riwayat';
require dirname(__DIR__) . '/partials/header_customer.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Riwayat cucian</h1>
        <p class="page-date">Semua cucian kamu — booking online maupun pesanan langsung di outlet.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo e(url('customer/booking.php')); ?>">Booking baru</a>
</div>

<div class="stat-grid">
    <div class="stat">
        <div class="stat-label">Total cucian</div>
        <div class="stat-value num"><?php echo (int) $total_semua; ?></div>
        <div class="stat-note">Booking online + pesanan outlet</div>
    </div>
    <div class="stat">
        <div class="stat-label">Booking online</div>
        <div class="stat-value num"><?php echo (int) $total; ?></div>
        <div class="stat-note">Dibuat lewat halaman booking</div>
    </div>
    <div class="stat">
        <div class="stat-label">Pesanan outlet</div>
        <div class="stat-value num"><?php echo (int) $kasir_ringkas['total']; ?></div>
        <div class="stat-note">Dicocokkan dari nomor HP akun</div>
    </div>
    <div class="stat">
        <div class="stat-label">Total belanja</div>
        <div class="stat-value num"><?php echo e(rupiah($belanja_semua)); ?></div>
        <div class="stat-note">Dari semua cucian yang sudah selesai</div>
    </div>
</div>

<section class="panel" id="panel-booking" data-panel="booking" aria-labelledby="judul-booking">
    <div class="panel-head panel-head-row">
        <div>
            <h2 class="panel-title" id="judul-booking">Booking online</h2>
            <p class="panel-sub">Klik Detail untuk melihat progres dan rincian lengkap.</p>
        </div>
        <form class="search-inline" method="GET" action="riwayat.php">
            <?php if ($f_status !== ''): ?>
                <input type="hidden" name="status" value="<?php echo e($f_status); ?>">
            <?php endif; ?>
            <input class="input" type="search" name="cari" placeholder="Cari kode / nomor…"
                   value="<?php echo e($f_cari); ?>" aria-label="Cari riwayat">
            <button class="btn btn-outline" type="submit">Cari</button>
        </form>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty">
            <strong><?php echo $f_cari !== '' || $f_status !== '' ? 'Tidak ada yang cocok' : 'Belum ada booking online'; ?></strong>
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

<section class="panel" id="panel-kasir" data-panel="kasir" style="margin-top:var(--s5)" aria-labelledby="judul-kasir">
    <div class="panel-head">
        <h2 class="panel-title" id="judul-kasir">Pesanan langsung di outlet</h2>
        <p class="panel-sub">
            Pesanan yang dicatat kasir dengan nomor HP <strong class="num"><?php echo e($user['no_hp'] ?: '—'); ?></strong>.
        </p>
    </div>

    <?php if (!$user['no_hp']): ?>
        <div class="empty">
            <strong>Nomor HP belum diisi</strong>
            Lengkapi nomor HP di <a href="<?php echo e(url('customer/profil.php')); ?>">Profil</a> agar pesanan kasir kamu ikut muncul di sini.
        </div>
    <?php elseif (!$kasir): ?>
        <div class="empty">
            <strong>Belum ada pesanan outlet</strong>
            Pesanan yang dicatat kasir memakai nomor HP akun ini akan otomatis muncul di sini.
        </div>
    <?php elseif (!$kasir_tampil): ?>
        <div class="empty">
            <strong>Tidak ada yang cocok</strong>
            Coba kata kunci atau filter lain.
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="orders">
            <thead>
                <tr>
                    <th scope="col">Pesanan</th>
                    <th scope="col">Layanan</th>
                    <th scope="col">Tanggal masuk</th>
                    <th scope="col" class="col-money">Total</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($kasir_tampil as $t): ?>
                <tr>
                    <td><span class="tag num"><?php echo e($t['no_pesanan']); ?></span></td>
                    <td data-label="Layanan">
                        <div class="cell-main"><?php echo e($t['nama_layanan']); ?></div>
                        <div class="cell-sub">Pesanan outlet</div>
                    </td>
                    <td data-label="Tanggal masuk"><?php echo e(tanggal_id($t['tanggal_masuk'])); ?></td>
                    <td class="col-money num" data-label="Total"><?php echo e(rupiah($t['total_harga'])); ?></td>
                    <td data-label="Status">
                        <span class="badge" data-warna="<?php echo e(status_kasir_warna($t['status'])); ?>"><?php echo e($t['status']); ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="panel-note">
        Ingin melacak satu pesanan saja? Masukkan nomornya di
        <a href="<?php echo e(url('tracking.php?no_pesanan=' . urlencode($kasir[0]['no_pesanan'] ?? ''))); ?>">halaman lacak</a>.
    </p>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>

