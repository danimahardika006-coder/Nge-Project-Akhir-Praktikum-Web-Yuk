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

// ---------- Pesanan kasir (walk-in) yang tertaut ke akun ini ----------
// Dua sumber tautan: kolom transactions.user_id (eksplisit) ATAU nomor HP
// akun yang sama dengan nomor HP pada nota kasir.
$kanon = hp_kanonik((string) ($target['no_hp'] ?? ''));
$kasir = [];
if ($kanon !== '') {
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT t.*, s.nama_layanan FROM transactions t
         JOIN services s ON s.id = t.service_id
         WHERE t.user_id = ? OR ' . sql_hp_kanonik('t.no_hp') . ' = ?
         ORDER BY t.tanggal_masuk DESC, t.id DESC'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $id, $kanon);
        mysqli_stmt_execute($stmt);
        $kasir = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

$kasir_selesai = 0;
$kasir_belanja = 0;
foreach ($kasir as $t) {
    if ($t['status'] === 'Selesai' || $t['status'] === 'Diambil') {
        $kasir_selesai++;
        $kasir_belanja += (int) $t['total_harga'];
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
        <div class="stat-label">Pesanan outlet</div>
        <div class="stat-value num"><?php echo count($kasir); ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Total belanja</div>
        <div class="stat-value num"><?php echo e(rupiah($belanja + $kasir_belanja)); ?></div>
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
        <h2 class="panel-title" id="judul-riwayat">Riwayat booking online</h2>
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

<section class="panel" style="margin-top:var(--s5)" aria-labelledby="judul-kasir">
    <div class="panel-head">
        <h2 class="panel-title" id="judul-kasir">Pesanan langsung di outlet</h2>
        <p class="panel-sub">
            <?php echo count($kasir); ?> pesanan tertaut — dicocokkan dari nomor HP
            <strong class="num"><?php echo e($target['no_hp'] ?: '—'); ?></strong>
            atau tautan manual.
        </p>
    </div>

    <?php if (!$target['no_hp'] && !$kasir): ?>
        <div class="empty">
            <strong>Nomor HP pelanggan belum diisi</strong>
            Isi nomor HP pelanggan agar pesanan kasir miliknya otomatis tertaut ke akun ini.
        </div>
    <?php elseif (!$kasir): ?>
        <div class="empty">
            <strong>Belum ada pesanan outlet</strong>
            Pesanan yang dicatat kasir dengan nomor HP ini akan muncul di sini.
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
                    <th scope="col" class="col-action"><span class="sr-only">Tautan</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($kasir as $t): ?>
                <tr>
                    <td>
                        <span class="tag num"><?php echo e($t['no_pesanan']); ?></span>
                        <div class="date-under num"><?php echo e($t['no_hp']); ?></div>
                    </td>
                    <td data-label="Layanan"><?php echo e($t['nama_layanan']); ?></td>
                    <td data-label="Tanggal masuk"><?php echo e(tanggal_id($t['tanggal_masuk'])); ?></td>
                    <td class="col-money num" data-label="Total"><?php echo e(rupiah($t['total_harga'])); ?></td>
                    <td data-label="Status">
                        <span class="badge" data-warna="<?php echo e(status_kasir_warna($t['status'])); ?>"><?php echo e($t['status']); ?></span>
                    </td>
                    <td class="col-action">
                        <?php if ((int) $t['user_id'] === $id): ?>
                            <span class="chip chip-cust">Tertaut</span>
                        <?php else: ?>
                            <form method="POST" action="data_booking.php?tautkan=<?php echo (int) $t['id']; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="trx_id" value="<?php echo (int) $t['id']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int) $id; ?>">
                                <input type="hidden" name="aksi" value="tautkan_kasir">
                                <button class="btn-text" type="submit" title="Kaitkan pesanan ini ke akun pelanggan">Tautkan</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>