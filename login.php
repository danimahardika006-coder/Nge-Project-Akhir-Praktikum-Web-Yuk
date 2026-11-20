<?php
require_once __DIR__ . '/partials/init.php';

// Sudah login? langsung ke halaman sesuai peran.
if (sudah_login()) {
    $u = user_aktif($koneksi);
    if ($u !== null) {
        alihkan($u['role'] === 'admin' ? 'dashboard.php' : 'customer/dashboard.php');
    }
}

$error  = '';
$info   = '';
$email  = '';
$next   = $_GET['next'] ?? '';
$daftar_baru = isset($_GET['daftar']);

if (isset($_GET['expired'])) {
    $info = 'Sesi kamu berakhir karena tidak ada aktivitas. Silakan masuk kembali.';
}

if (isset($_POST['login'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id, username, nama_lengkap, email, password, role, status_akun
             FROM users WHERE email = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Pesan seragam untuk email salah maupun password salah.
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status_akun'] !== 'aktif') {
                $error = 'Akun ini sedang dinonaktifkan. Hubungi admin untuk informasi lebih lanjut.';
            } else {
                // Perbaiki hash bila algoritma default berubah.
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd  = mysqli_prepare($koneksi, 'UPDATE users SET password = ? WHERE id = ?');
                    mysqli_stmt_bind_param($upd, 'si', $hash, $user['id']);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }

                $upd = mysqli_prepare($koneksi, 'UPDATE users SET diperbarui_pada = NOW() WHERE id = ?');
                mysqli_stmt_bind_param($upd, 'i', $user['id']);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);

                set_session_user($user);

                if ($user['role'] === 'admin') {
                    alihkan('dashboard.php');
                }
                alihkan('customer/dashboard.php');
            }
        } else {
            $error = 'Email atau password tidak cocok. Periksa lagi lalu coba masuk kembali.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · Cuci Sepatu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth-layout">
    <?php include __DIR__ . '/partials/auth_aside.php'; ?>

    <section class="auth-main">
        <div class="auth-form-wrap">
            <h1 class="auth-title">Masuk</h1>
            <p class="auth-sub">
                <?php echo $next === 'customer'
                    ? 'Masuk untuk melihat booking dan riwayat cucian kamu.'
                    : 'Gunakan akun yang terdaftar untuk membuka dashboard.'; ?>
            </p>

            <?php if ($info !== ''): ?>
                <div class="alert alert-warning" role="status"><?php echo e($info); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form class="auth-form" method="POST">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" autocomplete="email"
                           value="<?php echo e($email); ?>" required <?php echo $error !== '' ? '' : 'autofocus'; ?>>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" autocomplete="current-password" required <?php echo $error !== '' ? 'autofocus' : ''; ?>>
                </div>

                <button class="btn btn-primary btn-block" type="submit" name="login">Masuk</button>
            </form>

            <p class="auth-alt">Belum punya akun? <a href="registrasi.php">Daftar di sini</a></p>
            <p class="auth-alt" style="border-top:0;padding-top:var(--s2);margin-top:var(--s2)">
                Hanya ingin mengecek status cucian? <a href="tracking.php">Lacak dengan nomor pesanan</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>