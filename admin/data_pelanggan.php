<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_admin();

// ---------- Nonaktifkan / aktifkan akun ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    $id   = (int) ($_POST['user_id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    $stmt = mysqli_prepare($koneksi, 'SELECT id, username, role, status_akun FROM users WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$target) {
        flash_set('Akun tidak ditemukan.', 'danger');
    } elseif ($target['role'] !== 'customer') {
        flash_set('Hanya akun pelanggan yang bisa dikelola di sini.', 'warning');
    } elseif ($aksi === 'nonaktif') {
        $upd = mysqli_prepare($koneksi, "UPDATE users SET status_akun='nonaktif', diperbarui_pada=NOW() WHERE id=?");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        flash_set('Akun ' . $target['username'] . ' dinonaktifkan. Pelanggan tidak bisa masuk sampai diaktifkan lagi.');
    } elseif ($aksi === 'aktifkan') {
        $upd = mysqli_prepare($koneksi, "UPDATE users SET status_akun='aktif', diperbarui_pada=NOW() WHERE id=?");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        flash_set('Akun ' . $target['username'] . ' diaktifkan kembali.');
    }

    header('Location: data_pelanggan.php');
    exit();
}

$f_cari = trim($_GET['cari'] ?? '');
$f_role = trim($_GET['role'] ?? '');

$q    = "SELECT u.*,
            (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS total_booking,
            (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status IN
                ('menunggu_konfirmasi','dikonfirmasi','sepatu_diterima','dicuci','quality_control','siap_diambil')) AS booking_aktif,
            (SELECT COALESCE(SUM(b.total_harga),0) FROM bookings b WHERE b.user_id = u.id AND b.status = 'selesai') AS total_belanja
         FROM users u WHERE 1=1";
$tipe = '';
$args = [];

if ($f_role === 'customer' || $f_role === 'admin') {
    $q .= ' AND u.role = ?';
    $tipe .= 's';
    $args[] = $f_role;
}
if ($f_cari !== '') {
    $q .= ' AND (u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.no_hp LIKE ? OR u.username LIKE ?)';
    $tipe .= 'ssss';
    $like = '%' . $f_cari . '%';
    array_push($args, $like, $like, $like, $like);
}
$q .= ' ORDER BY u.role ASC, u.id ASC';

$stmt = mysqli_prepare($koneksi, $q);
if ($tipe !== '') {
    mysqli_stmt_bind_param($stmt, $tipe, ...$args);
}
mysqli_stmt_execute($stmt);
$daftar = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$n_customer = 0;
$res = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM users WHERE role='customer'");
if ($res) {
    $n_customer = (int) mysqli_fetch_assoc($res)['n'];
}

$judul = 'Data Pelanggan';
$aktif = 'pelanggan';
require dirname(__DIR__) . '/partials/header_admin.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Akun pelanggan</h1>
        <p class="page-date"><?php echo (int) $n_customer; ?> pelanggan terdaftar, plus akun admin.</p>
    </div>
</div>

<section class="panel">
    <div class="panel-head panel-head-row">
        <div>
            <h2 class="panel-title">Daftar akun</h2>
            <p class="panel-sub">Klik nama untuk membuka riwayat cucian pelanggan.</p>
        </div>
        <form class="search-inline" method="GET" action="data_pelanggan.php">
            <select class="select select-sm" name="role" aria-label="Filter peran">
                <option value="">Semua peran</option>
                <option value="customer" <?php echo $f_role === 'customer' ? 'selected' : ''; ?>>Pelanggan</option>
                <option value="admin" <?php echo $f_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
            <input class="input input-sm" type="search" name="cari" placeholder="Cari nama / email / HP…"
                   value="<?php echo e($f_cari); ?>">
            <button class="btn btn-outline btn-sm" type="submit">Cari</button>
        </form>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty"><strong>Tidak ada akun</strong> Coba kata kunci lain.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="orders">
            <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Kontak</th>
                    <th scope="col">Peran</th>
                    <th scope="col" class="num">Booking</th>
                    <th scope="col" class="col-money">Belanja</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="col-action"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $u): ?>
                <tr>
                    <td>
                        <div class="cell-main"><a<?php echo (int) $u['id']; ?>><?php echo e($u['nama_lengkap']); ?></a></div>
                        <div class="cell-sub">@<?php echo e($u['username']); ?></div>
                    </td>
                    <td data-label="Kontak">
                        <div class="cell-main"><?php echo e($u['email']); ?></div>
                        <div class="cell-sub num"><?php echo e($u['no_hp'] ?: '—'); ?></div>
                    </td>
                    <td data-label="Peran">
                        <span class="chip <?php echo $u['role'] === 'admin' ? 'chip-admin' : 'chip-cust'; ?>"><?php echo e(ucfirst($u['role'])); ?></span>
                    </td>
                    <td class="num" data-label="Booking">
                        <?php echo (int) $u['total_booking']; ?>
                        <?php if ((int) $u['booking_aktif'] > 0): ?>
                            <div class="cell-sub"><?php echo (int) $u['booking_aktif']; ?> berjalan</div>
                        <?php endif; ?>
                    </td>
                    <td class="col-money num" data-label="Belanja"><?php echo e(rupiah($u['total_belanja'])); ?></td>
                    <td data-label="Status">
                        <span class="badge" data-warna="<?php echo $u['status_akun'] === 'aktif' ? 'selesai' : 'batal'; ?>">
                            <?php echo $u['status_akun'] === 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </td>
                    <td class="col-action">
                        <?php if ($u['role'] === 'customer'): ?>
                            <?php if ($u['status_akun'] === 'aktif'): ?>
                            <form method="POST" onsubmit="return confirm('Nonaktifkan akun ini? Pelanggan tidak bisa masuk sampai diaktifkan lagi.');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <input type="hidden" name="aksi" value="nonaktif">
                                <button class="btn-text" type="submit">Nonaktifkan</button>
                            </form>
                            <?php else: ?>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <input type="hidden" name="aksi" value="aktifkan">
                                <button class="btn-text" type="submit">Aktifkan</button>
                            </form>
                            <?php endif; ?>
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