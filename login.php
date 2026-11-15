<?php
session_start();
include("koneksi.php");

// Sudah login? langsung ke dashboard.
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$email = "";

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // Cek user berdasarkan email
    $check = mysqli_prepare($koneksi, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($check, "s", $email);
    mysqli_stmt_execute($check);
    $result = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        // Cek password
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['login']    = true;
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];

            header("Location: dashboard.php");
            exit();
        }
    }

    // Pesan sama untuk email salah maupun password salah
    $error = "Email atau password tidak cocok. Periksa lagi lalu coba masuk kembali.";
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
    <?php include("partials/auth_aside.php"); ?>

    <section class="auth-main">
        <div class="auth-form-wrap">
            <h1 class="auth-title">Masuk</h1>
            <p class="auth-sub">Gunakan akun yang terdaftar untuk membuka dashboard.</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form class="auth-form" method="POST">
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" autocomplete="email"
                           value="<?php echo htmlspecialchars($email); ?>" required <?php echo $error !== "" ? "" : "autofocus"; ?>>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" autocomplete="current-password" required <?php echo $error !== "" ? "autofocus" : ""; ?>>
                </div>

                <button class="btn btn-primary btn-block" type="submit" name="login">Masuk</button>
            </form>

            <p class="auth-alt">Belum punya akun? <a href="registrasi.php">Daftar di sini</a></p>
        </div>
    </section>
</main>
</body>
</html>