<?php
    session_start();

    //cek apakah sudah login
    if(!isset($_SESSION['login'])||($_SESSION['login']) !== true){
        header("Location: login.php");
        exit();
    }
?>

<h1>
    Selamat datang <?php echo htmlspecialchars($_SESSION['username']); ?> 
</h1>

<p>
    Kamu terlah berhasil Login ke   Dashboard.
</p>

<a href="logout.php">
    Logout
</a>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    
</body>
</html>