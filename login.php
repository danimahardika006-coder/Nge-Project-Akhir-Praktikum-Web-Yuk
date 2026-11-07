<?php
session_start();
include("koneksi.php");
if (isset($_POST['login'])) {

    $email = $_POST['email'];
    $password = $_POST['password'];

    //mengecek user berdasarkan email
    $check = mysqli_prepare(
        $koneksi,
        "SELECT * FROM USERS WHERE email = ?"
    );

    mysqli_stmt_bind_param($check, "s", $email);
    mysqli_stmt_execute($check);

    $result = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        //mengecek password
        if (password_verify($password, $user['password'])) {
            $_SESSION['login'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];

            header("Location: dashboard.php");
            exit();
        } else {
            echo "Password salah!";
        }
    } else {
        echo "Email tidak ditemukan!";
    }

}
?>

<h2>
    Form Login
</h2>
<form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <br>
    <br>

    <input type="password" name="password" placeholder="Password" required>
    <br>
    <br>

    <button type="submit" name="login">
        Login
    </button>
</form>

<p>
    Kalau belum punya akun, coba regis dulu <a href="register.php"> Register </a>
</p>