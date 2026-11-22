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

    // Kembali ke daftar dengan filter & urutan yang sama (jangan reset tampilan admin)
    $balik = array_filter([
        'role' => $_POST['balik_role'] ?? '',
        'cari' => $_POST['balik_cari'] ?? '',
        'sort' => $_POST['balik_sort'] ?? '',
        'arah' => $_POST['balik_arah'] ?? '',
    ], fn ($v) => $v !== '');
    header('Location: data_pelanggan.php' . ($balik ? '?' . http_build_query($balik) : ''));
    exit();
}

$f_cari = trim($_GET['cari'] ?? '');
$f_role = trim($_GET['role'] ?? '');

// ---------- Sorting (whitelist, anti SQL injection) ----------
$kolom_sort = [
    'nama'     => 'u.nama_lengkap',
    'email'    => 'u.email',
    'role'     => 'u.role',
    'booking'  => 'total_booking',
    'belanja'  => 'total_belanja',
    'status'   => 'u.status_akun',
];
$f_sort  = $_GET['sort'] ?? 'role';
$f_arah  = strtolower($_GET['arah'] ?? 'asc');
if (!isset($kolom_sort[$f_sort])) {
    $f_sort = 'role';
}
$f_arah = $f_arah === 'desc' ? 'desc' : 'asc';
$arah_lawan = $f_arah === 'asc' ? 'desc' : 'asc';

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
$q .= ' ORDER BY ' . $kolom_sort[$f_sort] . ' ' . strtoupper($f_arah) . ', u.role ASC, u.id ASC';

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

// Helper: URL untuk link sort di header tabel
function sort_url(string $kolom, string $f_sort, string $f_arah, string $f_role, string $f_cari): string
{
    $qs = http_build_query(array_filter([
        'sort' => $kolom,
        'arah' => ($f_sort === $kolom && $f_arah === 'asc') ? 'desc' : 'asc',
        'role' => $f_role,
        'cari' => $f_cari,
    ], fn ($v) => $v !== ''));
    return 'data_pelanggan.php' . ($qs !== '' ? '?' . $qs : '');
}

// Helper: ikon penanda kolom yang sedang diurutkan
function sort_ikon(string $kolom, string $f_sort, string $f_arah): string
{
    if ($f_sort !== $kolom) {
        return '<span class="sort-ikon" aria-hidden="true">↕</span>';
    }
    return '<span class="sort-ikon sort-aktif" aria-hidden="true">' . ($f_arah === 'asc' ? '▲' : '▼') . '</span>';
}

// Helper: hidden field agar filter & urutan tetap sama setelah aksi POST
function balik_filter_field(string $f_role, string $f_cari, string $f_sort, string $f_arah): string
{
    $out = '';
    foreach (['balik_role' => $f_role, 'balik_cari' => $f_cari, 'balik_sort' => $f_sort, 'balik_arah' => $f_arah] as $nama => $nilai) {
        if ($nilai !== '') {
            $out .= '<input type="hidden" name="' . $nama . '" value="' . e($nilai) . '">';
        }
    }
    return $out;
}
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
            <select class="select select-sm" name="role" aria-label="Filter peran" data-auto-submit>
                <option value="">Semua peran</option>
                <option value="customer" <?php echo $f_role === 'customer' ? 'selected' : ''; ?>>Pelanggan</option>
                <option value="admin" <?php echo $f_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
            <input class="input input-sm" type="search" name="cari" placeholder="Cari nama / email / HP…"
                   value="<?php echo e($f_cari); ?>">
            <input type="hidden" name="sort" value="<?php echo e($f_sort); ?>">
            <input type="hidden" name="arah" value="<?php echo e($f_arah); ?>">
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
                    <th scope="col"><a class="sort-link" href="<?php echo e(sort_url('nama', $f_sort, $f_arah, $f_role, $f_cari)); ?>">Nama <?php echo sort_ikon('nama', $f_sort, $f_arah); ?></a></th>
                    <th scope="col"><a class="sort-link" href="<?php echo e(sort_url('email', $f_sort, $f_arah, $f_role, $f_cari)); ?>">Kontak <?php echo sort_ikon('email', $f_sort, $f_arah); ?></a></th>
                    <th scope="col"><a class="sort-link" href="<?php echo e(sort_url('role', $f_sort, $f_arah, $f_role, $f_cari)); ?>">Peran <?php echo sort_ikon('role', $f_sort, $f_arah); ?></a></th>
                    <th scope="col" class="num"><a class="sort-link" href="<?php echo e(sort_url('booking', $f_sort, $f_arah, $f_role, $f_cari)); ?>">Booking <?php echo sort_ikon('booking', $f_sort, $f_arah); ?></a></th>
                    <th scope="col" class="col-money"><a class="sort-link" href="<?php echo e(sort_url('belanja', $f_sort, $f_arah, $f_role, $f_cari)); ?>">Belanja <?php echo sort_ikon('belanja', $f_sort, $f_arah); ?></a></th>
                    <th scope="col"><a class="sort-link" href="<?php echo e(sort_url('status', $f_sort, $f_arah, $f_role, $f_cari)); ?>">Status <?php echo sort_ikon('status', $f_sort, $f_arah); ?></a></th>
                    <th scope="col" class="col-action"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $u): ?>
                <tr>
                    <td>
                        <div class="cell-main"><a href="pelanggan.php?id=<?php echo (int) $u['id']; ?>"><?php echo e($u['nama_lengkap']); ?></a></div>
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
                                <?php echo balik_filter_field($f_role, $f_cari, $f_sort, $f_arah); ?>
                                <button class="btn-text" type="submit">Nonaktifkan</button>
                            </form>
                            <?php else: ?>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <input type="hidden" name="aksi" value="aktifkan">
                                <?php echo balik_filter_field($f_role, $f_cari, $f_sort, $f_arah); ?>
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

<script>
(function () {
    // Filter peran langsung diterapkan begitu dropdown diganti
    document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });
})();
</script>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>