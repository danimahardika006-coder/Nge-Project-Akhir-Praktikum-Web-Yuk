<?php   
    include("koneksi.php");
    if(isset($_POST['registrasi'])){
        $username = $_POST ['username'];
        $nama_lengkap = $_POST ['nama_lengkap'];
        $email = $_POST ['email'];
        $password = $_POST ['password'];

        //mengubah pw menjadi hash
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        //mengecek
        $check = mysqli_prepare(
            $koneksi,
            "SELECT id FROM USERS WHERE email = ?"
        );
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);

        $result = mysqli_stmt_get_result($check);

        if(mysqli_num_rows($result) > 0 ){
            echo "Email sudah terdaftar";
        }else{
            $query = mysqli_prepare(
                $koneksi,
                "INSERT INTO users (username, nama_lengkap, email, password) VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param($query, "ssss", $username, $nama_lengkap, $email, $hashedPassword);
            
            if(mysqli_stmt_execute($query)){
                header("Location: login.php");
                exit();
            }else{
                echo "Registrasi gagal!";
            }
        }
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>
        Form Register
    </h2>
    <form action="registrasi.php" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <br>
        <br>
        
        <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" required>
        <br>
        <br>

        <input type="email" name="email" placeholder="Email" required>
        <br>
        <br>
        
        <input type="password" name="password" placeholder="Password" required>
        <br>
        <br>

        <button type="submit" name="registrasi">
            Register
        </button>

    </form>

    <p>
        Kalau sudah punya akun, coba login dulu <a href="login.php"> Login </a>
    </p>
    
</body>
</html>