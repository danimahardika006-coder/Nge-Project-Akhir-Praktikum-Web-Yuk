<?php
require_once __DIR__ . '/partials/init.php';

// Sudah login? Tidak perlu daftar lagi.
if (sudah_login()) {
    $u = user_aktif($koneksi);
    if ($u !== null) {
        alihkan($u['role'] === 'admin' ? 'dashboard.php' : 'customer/dashboard.php');
    }
}

$error = '';
$nama  = '';
$email = '';
$no_hp = '';

if (isset($_POST['registrasi']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();

    $nama    = trim($_POST['nama_lengkap'] ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $no_hp   = trim($_POST['no_hp'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $ulangi   = (string) ($_POST['password2'] ?? '');

    // ---------- Validasi server-side ----------
    if ($nama === '' || $email === '' || $no_hp === '' || $password === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $error = 'Nama lengkap harus 3 sampai 100 karakter.';
    } elseif (!valid_email($email)) {
        $error = 'Format email belum benar. Contoh: nama@email.com';
    } elseif (!valid_hp($no_hp)) {
        $error = 'Nomor HP belum benar. Gunakan format 08xxxxxxxxxx.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $ulangi) {
        $error = 'Konfirmasi password belum sama.';
    } elseif ($email === '' || strpos($email, ' ') !== false) {
        $error = 'Email tidak boleh mengandung spasi.';
    } else {
        // Nomor HP dinormalisasi ke format 08...
        $no_hp = normalisasi_hp($no_hp);

        // ---------- Cek email terdaftar ----------
        $cek = mysqli_prepare($koneksi, 'SELECT id FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($cek, 's', $email);
        mysqli_stmt_execute($cek);
        $sudah = mysqli_stmt_get_result($cek);
        if (mysqli_num_rows($sudah) > 0) {
            mysqli_stmt_close($cek);
            $error = 'Email ini sudah terdaftar. Masuk dengan email tersebut, atau daftar dengan email lain.';
        } else {
            mysqli_stmt_close($cek);

            // ---------- Username otomatis dari email, dijaga unik ----------
            $dasar = preg_replace('/[^a-z0-9_]/', '', strtolower(strstr($email, '@', true)) ?: 'pelanggan');
            $dasar = substr($dasar !== '' ? $dasar : 'pelanggan', 0, 40);
            $username = $dasar;
            $n = 1;
            while (true) {
                $cek = mysqli_prepare($koneksi, 'SELECT id FROM users WHERE username = ? LIMIT 1');
                mysqli_stmt_bind_param($cek, 's', $username);
                mysqli_stmt_execute($cek);
                $res = mysqli_stmt_get_result($cek);
                $dipakai = mysqli_num_rows($res) > 0;
                mysqli_stmt_close($cek);
                if (!$dipakai) {
                    break;
                }
                $n++;
                $username = $dasar . $n;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $query = mysqli_prepare(
                $koneksi,
                "INSERT INTO users (username, nama_lengkap, email, password, no_hp, role, status_akun, dibuat_pada, diperbarui_pada)
                 VALUES (?, ?, ?, ?, ?, 'customer', 'aktif', NOW(), NOW())"
            );
            mysqli_stmt_bind_param($query, 'sssss', $username, $nama, $email, $hash, $no_hp);

            if (mysqli_stmt_execute($query)) {
                mysqli_stmt_close($query);
                flash_set('Akun berhasil dibuat. Silakan masuk dengan email dan password kamu.', 'success');
                alihkan('login.php?daftar=1');
            } else {
                mysqli_stmt_close($query);
                $error = 'Pendaftaran gagal disimpan. Coba lagi beberapa saat.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar · Cuci Sepatu</title>
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
            <h1 class="auth-title">Buat akun</h1>
            <p class="auth-sub">Daftar sekali untuk memesan cuci sepatu dan memantau riwayat cucian.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form class="auth-form" action="registrasi.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <label for="nama_lengkap">Nama lengkap</label>
                    <input class="input" type="text" id="nama_lengkap" name="nama_lengkap" autocomplete="name"
                           value="<?php echo e($nama); ?>" required autofocus>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" autocomplete="email"
                           value="<?php echo e($email); ?>" required>
                </div>

                <div class="field">
                    <label for="no_hp">Nomor HP</label>
                    <input class="input" type="tel" id="no_hp" name="no_hp" inputmode="tel" autocomplete="tel"
                           placeholder="08xxxxxxxxxx" value="<?php echo e($no_hp); ?>" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" autocomplete="new-password"
                           minlength="8" required>
                    <p class="field-hint">Minimal 8 karakter.</p>
                </div>

                <div class="field">
                    <label for="password2">Ulangi password</label>
                    <input class="input" type="password" id="password2" name="password2" autocomplete="new-password"
                           minlength="8" required>
                </div>

                <button class="btn btn-primary btn-block" type="submit" name="registrasi">Daftar</button>
            </form>

            <p class="auth-alt">Sudah punya akun? <a href="login.php">Masuk</a></p>
        </div>
    </section>
</main>
</body>
</html>