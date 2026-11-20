<?php
/**
 * header_customer.php — topbar + navigasi untuk halaman pelanggan.
 *
 * Variabel yang dipakai:
 *   $user   — baris users milik pelanggan (hasil butuh_customer())
 *   $judul  — judul halaman (opsional)
 *   $aktif  — slug halaman aktif untuk tanda nav (opsional)
 */
if (!isset($user)) {
    return;
}
$judul = $judul ?? 'Cuci Sepatu';
$aktif = $aktif ?? '';
$flash = flash_get();

function nav_cls(string $slug, string $aktif): string
{
    return 'nav-link' . ($slug === $aktif ? ' nav-link-aktif' : '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($judul); ?> · Cuci Sepatu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(url('assets/style.css')); ?>">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?php echo e(url('customer/dashboard.php')); ?>">
            <span class="brand-mark" aria-hidden="true"></span>
            Cuci Sepatu
        </a>

        <nav class="mainnav" aria-label="Navigasi utama">
            <a class="<?php echo nav_cls('dashboard', $aktif); ?>" href="<?php echo e(url('customer/dashboard.php')); ?>">Dashboard</a>
            <a class="<?php echo nav_cls('booking', $aktif); ?>" href="<?php echo e(url('customer/booking.php')); ?>">Booking</a>
            <a class="<?php echo nav_cls('riwayat', $aktif); ?>" href="<?php echo e(url('customer/riwayat.php')); ?>">Riwayat</a>
            <a class="<?php echo nav_cls('profil', $aktif); ?>" href="<?php echo e(url('customer/profil.php')); ?>">Profil</a>
        </nav>

        <div class="userbar">
            <span class="who">Halo, <strong><?php echo e($user['nama_lengkap']); ?></strong></span>
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