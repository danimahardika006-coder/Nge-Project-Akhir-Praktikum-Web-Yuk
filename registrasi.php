<?php
include("koneksi.php");

$error        = "";
$username     = "";
$nama_lengkap = "";
$email        = "";

if (isset($_POST['registrasi'])) {
    $username     = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email        = trim($_POST['email']);
    $password     = $_POST['password'];

    // Ubah password menjadi hash
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Cek apakah email sudah terdaftar
    $check = mysqli_prepare($koneksi, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($check, "s", $email);
    mysqli_stmt_execute($check);
    $result = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($result) > 0) {
        $error = "Email ini sudah terdaftar. Masuk dengan email tersebut, atau daftar dengan email lain.";
    } else {
        $query = mysqli_prepare(
            $koneksi,
            "INSERT INTO users (username, nama_lengkap, email, password) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($query, "ssss", $username, $nama_lengkap, $email, $hashedPassword);

        if (mysqli_stmt_execute($query)) {
            header("Location: login.php");
            exit();
        } else {
            $error = "Pendaftaran gagal disimpan. Coba lagi beberapa saat.";
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
    <?php include("partials/auth_aside.php"); ?>

    <section class="auth-main">
        <div class="auth-form-wrap">
            <h1 class="auth-title">Buat akun</h1>
            <p class="auth-sub">Isi data berikut untuk mulai mencatat pesanan.</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form class="auth-form" action="registrasi.php" method="POST">
                <div class="field">
                    <label for="username">Username</label>
                    <input class="input" type="text" id="username" name="username" autocomplete="username"
                           value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                </div>

                <div class="field">
                    <label for="nama_lengkap">Nama lengkap</label>
                    <input class="input" type="text" id="nama_lengkap" name="nama_lengkap" autocomplete="name"
                           value="<?php echo htmlspecialchars($nama_lengkap); ?>" required>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" autocomplete="email"
                           value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" autocomplete="new-password" required>
                </div>

                <button class="btn btn-primary btn-block" type="submit" name="registrasi">Daftar</button>
            </form>

            <p class="auth-alt">Sudah punya akun? <a href="login.php">Masuk</a></p>
        </div>
    </section>
</main>
</body>
</html>