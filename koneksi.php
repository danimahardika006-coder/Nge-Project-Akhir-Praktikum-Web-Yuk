<?php
/**
 * Koneksi database.
 *
 * Nilai default mengikuti XAMPP (user root tanpa password). Bila host diisi
 * "localhost", koneksi memakai TCP ke 127.0.0.1:PORT — lebih stabil daripada
 * socket path XAMPP yang bisa berubah, dan tetap kompatibel dengan XAMPP.
 *
 * Port bisa diatur lewat environment variable DB_PORT (berguna untuk setup
 * non-standar). Default 3306.
 */

$host     = "localhost";
$user     = "root";
$password = "";
$database = "db_cuci_sepatu";

$port = (int) (getenv("DB_PORT") ?: 3306);

// "localhost" pada mysqli memilih socket; paksa TCP agar port dipakai.
$tcp_host = ($host === "localhost") ? "127.0.0.1" : $host;

try {
    $koneksi = new mysqli($tcp_host, $user, $password, $database, $port);
} catch (mysqli_sql_exception $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}
$koneksi->set_charset("utf8mb4");
