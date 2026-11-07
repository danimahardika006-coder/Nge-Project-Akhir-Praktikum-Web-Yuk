<?php
    session_start();

    // 1. cek apakah sudah login
    if(!isset($_SESSION['login'])||($_SESSION['login']) !== true){
        header("Location: login.php");
        exit();
    }
    // 2. Proses tambah transaksi baru
    if(!isset($_POST["tambahTransaksi"])){
        $nama_pelanggan = trim($_POST['nama_pelanggan']); 
        $no_hp = trim($_POST['no_hp']); 
        $service_id = trim($_POST['service_id']); 
        $tanggal_masuk = trim($_POST['tanggal_masuk']);

        // generate NO Pesanan otomatis ex: TRX-20261108-042
        $no_pesanan = "TRX-" . date("Ymd") . "-" . rand(100,999);

        // Ambil harga layanan dari table services
        $stmt_harga = mysqli_prepare($koneksi, "SELECT harga FROM services WHERE id = ?");
        mysqli_stmt_bind_param($stmt_harga, "i", $service_id);
        mysqli_execute($stmt_harga);
        $result_harga = mysqli_stmt_get_result($stmt_harga);

        if($row_harga = mysqli_fetch_assoc($result_harga)){
            $total_harga = $row_harga["harga"];

            //simpan ke table transactions
            $stmt_insert = mysqli_prepare(
                $koneksi,
                "INSERT INTO transactions (no_pesanan, nama_pelanggan, no_hp, 
                service_id, tanggal_masuk, status, total_harga)
                VALUES (?, ?, ?, ?, ?, 'Antrean', ?)"
            );
            mysqli_stmt_bind_param($stmt_insert, "sssisi", $no_pesanan, 
            $nama_pelanggan, $no_hp, $service_id, $tanggal_masuk, $total_harga);

            if(mysqli_execute($stmt_insert)){
                $pesan = "Transaksi berhasil ditambahkan dengan No: $no_pesanan";
            }else{
                $pesan = "Gagal menambahkan transaksi!";
            }
        }
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