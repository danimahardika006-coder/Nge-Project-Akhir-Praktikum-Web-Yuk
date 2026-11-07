<?php
    session_start();

    //cek apakah sudah login
    if(!isset($_SESSION['login'])||($_SESSION['login']) !== true){
        header("Location: login.php");
        exit();
    }
?>

<h1>
    Selamat datang <?php echo htmlspecialchars($_SESSION['name']); ?> 
</h1>

<p>
    Kamu terlah berhasil Login ke Dashboard.
</p>

<a href="logout.php">
    Logout
</a>