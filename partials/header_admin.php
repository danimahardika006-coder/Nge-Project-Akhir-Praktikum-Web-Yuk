<?php
/**
 * header_admin.php — topbar + navigasi panel admin.
 * Variabel: $user (admin), $judul, $aktif
 */
if (!isset($user)) {
    return;
}
$judul = $judul ?? 'Admin';
$aktif = $aktif ?? '';
$flash = flash_get();

function nav_admin_cls(string $slug, string $aktif): string
{
    return 'nav-link' . ($slug === $aktif ? ' nav-link-aktif' : '');
}

// Hitung booking menunggu konfirmasi untuk tanda di nav
$n_konfirmasi = 0;
if (isset($koneksi)) {
    $res = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM bookings WHERE status = 'menunggu_konfirmasi'");
    if ($res) {
        $n_konfirmasi = (int) mysqli_fetch_assoc($res)['n'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($judul); ?> · Admin Cuci Sepatu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(url('assets/style.css')); ?>">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?php echo e(url('dashboard.php')); ?>">
            <span class="brand-mark" aria-hidden="true"></span>
            Cuci Sepatu <span class="brand-sub">Admin</span>
        </a>

        <nav class="mainnav" aria-label="Navigasi admin">
            <a class="<?php echo nav_admin_cls('kasir', $aktif); ?>" href="<?php echo e(url('dashboard.php')); ?>">Kasir</a>
            <a class="<?php echo nav_admin_cls('booking', $aktif); ?>" href="<?php echo e(url('admin/data_booking.php')); ?>">
                Booking<?php echo $n_konfirmasi > 0 ? ' <span class="nav-dot num">' . (int) $n_konfirmasi . '</span>' : ''; ?>
            </a>
            <a class="<?php echo nav_admin_cls('pelanggan', $aktif); ?>" href="<?php echo e(url('admin/data_pelanggan.php')); ?>">Pelanggan</a>
            <a class="<?php echo nav_admin_cls('layanan', $aktif); ?>" href="<?php echo e(url('admin/data_layanan.php')); ?>">Layanan</a>
        </nav>

        <div class="userbar">
            <span class="who">Login Sebagai <strong style="text-transform: uppercase;"><?php echo e($user['role']); ?></strong></span>
            <a href="<?php echo e(url('logout.php')); ?>">Keluar</a>
        </div>
    </div>
</header>

<main class="page">
<?php if ($flash): ?>
    <div class="alert alert-<?php echo e($flash['tipe']); ?>" role="<?php echo $flash['tipe'] === 'danger' ? 'alert' : 'status'; ?>">
        <?php echo e($flash['teks']); ?>
    </div>
<?php endif; ?>