<?php
    session_start();

    // 1. cek apakah sudah login
    if(!isset($_SESSION['login'])||($_SESSION['login']) !== true){
        header("Location: login.php");
        exit();
    }
    // 2. Proses tambah transaksi baru
    if (isset($_POST['tambahTransaksi'])) {
        $nama_pelanggan = trim($_POST['nama_pelanggan']);
        $no_hp          = trim($_POST['no_hp']);
        $service_id     = intval($_POST['service_id']);
        $tanggal_masuk  = trim($_POST['tanggal_masuk']);

        if (!empty($nama_pelanggan) && !empty($no_hp) && !empty($service_id) && !empty($tanggal_masuk)) {
            // Ambil harga dari tabel services berdasarkan service_id
            $stmt_harga = mysqli_prepare($koneksi, "SELECT harga FROM services WHERE id = ?");
            mysqli_stmt_bind_param($stmt_harga, "i", $service_id);
            mysqli_stmt_execute($stmt_harga);
            $result_harga = mysqli_stmt_get_result($stmt_harga);
            if ($row_harga = mysqli_fetch_assoc($result_harga)) {
                $total_harga = $row_harga['harga'];

                // Generate nomor pesanan unik (contoh: TRX-20261108-412)
                $no_pesanan = "TRX-" . date("Ymd") . "-" . rand(100, 999);
                $stmt_insert = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO transactions (no_pesanan, nama_pelanggan, no_hp, service_id, tanggal_masuk, status, total_harga) 
                     VALUES (?, ?, ?, ?, ?, 'Antrean', ?)"
                );
                mysqli_stmt_bind_param($stmt_insert, "sssisi", $no_pesanan, $nama_pelanggan, $no_hp, $service_id, $tanggal_masuk, $total_harga);
                if (mysqli_stmt_execute($stmt_insert)) {
                    $pesan = "Transaksi berhasil dibuat! No. Pesanan: <strong>$no_pesanan</strong>";
                    $pesan_tipe = "success";
                } else {
                    $pesan = "Gagal membuat transaksi: " . mysqli_error($koneksi);
                    $pesan_tipe = "danger";
                }
            } else {
                $pesan = "Layanan yang dipilih tidak valid!";
                $pesan_tipe = "danger";
            }
        } else {
            $pesan = "Mohon lengkapi semua data formulir!";
            $pesan_tipe = "warning";
        }

        // 3. Update status transaksi
        if(isset($_POST["updateStatus"])) {
            $trx_id = intval($_POST["trx_id"]);
            $status_baru = trim($_POST["status"]);
            $valid_status = ['Antrean', 'Dicuci', 'Selesai', 'Diambil'];

            if(in_array($status_baru, $valid_status)){
                $stmt_update = mysqli_prepare($koneksi,'UPDATE transactions SET status = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt_update,'si', $status_baru, $trx_id);
            } if(mysqli_stmt_execute($stmt_update)) {
                $pesan = "Status transaksi #$trx_id berhasil diubah menjadi <strong>$status_baru</strong>.";
                $pesan_tipe = "success";
            }else {
                $pesan = "Gagal memperbarui status transaksi!";
                $pesan_tipe = "danger";        
            }
        }

        // 4. Hapus Transaksi
        if(isset($_POST["hapus"])) {
            $id_hapus = intval($_GET['hapus']);
            $stmt_del = mysqli_prepare($koneksi, "DELETE FROM transactions WHERE id = ?");
            mysqli_stmt_bind_param($stmt_del, "i", $id_hapus);
            
            if (mysqli_stmt_execute($stmt_del)) {
            $pesan = "Transaksi berhasil dihapus.";
            $pesan_tipe = "success";
            } else {
            $pesan = "Gagal menghapus transaksi!";
            $pesan_tipe = "danger";
            }
        }

        // 5. Query Data Layanan (services)
        $services_query = mysqli_query($koneksi, "SELECT * FROM services ORDER BY harga ASC");
        $services_list  = [];
        while ($s = mysqli_fetch_assoc($services_query)) {
        $services_list[] = $s;
        }
        
        // 6. Query Ringkasan Statistik
        $total_trx = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM transactions"));
        $antrean   = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM transactions WHERE status = 'Antrean'"));
        $dicuci    = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM transactions WHERE status = 'Dicuci'"));
        $selesai   = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM transactions WHERE status = 'Selesai'"));
        
        // Total pendapatan dari pesanan yang sudah selesai/diambil
        $pendapatan_query = mysqli_query($koneksi, "SELECT SUM(total_harga) AS total FROM transactions WHERE status IN ('Selesai', 'Diambil')");
        $pendapatan_row   = mysqli_fetch_assoc($pendapatan_query);
        $total_pendapatan = $pendapatan_row['total'] ?? 0;

        // 7. Query Transaksi (JOIN dengan tabel services)
        $trx_query = "SELECT t.*, s.nama_layanan 
                    FROM transactions t 
                    JOIN services s ON t.service_id = s.id 
                    ORDER BY t.id DESC";
        $trx_result = mysqli_query($koneksi, $trx_query);
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