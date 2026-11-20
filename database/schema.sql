-- ==========================================================================
-- Cuci Sepatu — skema lengkap (referensi)
--
-- File ini adalah dokumentasi struktur akhir database db_cuci_sepatu.
-- Untuk menerapkan ke database yang sudah ada, JANGAN import file ini
-- (akan bertabrakan dengan tabel lama). Jalankan:
--
--     php database/migrate.php
--
-- Skrip migrasi bersifat idempotent: hanya menambah yang belum ada dan
-- tidak menghapus data lama.
-- ==========================================================================

SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- users : admin panel + akun pelanggan (satu tabel, dibedakan kolom role)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    nama_lengkap  VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL,
    password      VARCHAR(255) NOT NULL,           -- password_hash()
    no_hp         VARCHAR(20)  DEFAULT NULL,
    role          ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    status_akun   ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    dibuat_pada   DATETIME     DEFAULT NULL,
    diperbarui_pada DATETIME   DEFAULT NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- services : daftar layanan cuci (sudah ada, kolom aktif ditambahkan)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nama_layanan VARCHAR(100) NOT NULL,
    harga        INT NOT NULL,
    deskripsi    VARCHAR(255) DEFAULT NULL,
    aktif        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- pakets : paket pengerjaan (reguler / express / premium) — menambah harga
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pakets (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nama_paket     VARCHAR(60) NOT NULL,
    tambahan_harga INT NOT NULL DEFAULT 0,
    estimasi_hari  TINYINT UNSIGNED NOT NULL DEFAULT 2,
    keterangan     VARCHAR(255) DEFAULT NULL,
    aktif          TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- tipe_sepatu : pilihan jenis sepatu pada formulir booking
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipe_sepatu (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nama_tipe VARCHAR(60) NOT NULL,
    aktif     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- bookings : pesanan online milik pelanggan terdaftar
--   harga_layanan / harga_paket / total_harga disimpan sebagai snapshot
--   agar riwayat tidak berubah saat admin menaikkan harga layanan.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    kode_booking   VARCHAR(20) NOT NULL,
    user_id        INT NOT NULL,
    service_id     INT NOT NULL,
    paket_id       INT DEFAULT NULL,
    tipe_sepatu    VARCHAR(40) NOT NULL,
    jumlah_sepatu  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    catatan        TEXT DEFAULT NULL,
    tanggal_booking DATE NOT NULL,
    jam_booking    TIME NOT NULL,
    harga_layanan  INT NOT NULL,
    harga_paket    INT NOT NULL DEFAULT 0,
    total_harga    INT NOT NULL,
    status         VARCHAR(30) NOT NULL DEFAULT 'menunggu_konfirmasi',
    catatan_admin  TEXT DEFAULT NULL,
    dibuat_pada    DATETIME NOT NULL,
    diperbarui_pada DATETIME NOT NULL,
    UNIQUE KEY uq_bookings_kode (kode_booking),
    KEY idx_bookings_user (user_id),
    KEY idx_bookings_status (status),
    KEY idx_bookings_slot (tanggal_booking, jam_booking),
    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_service
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_paket
        FOREIGN KEY (paket_id) REFERENCES pakets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- order_status_history : jejak setiap perubahan status booking
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_status_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT NOT NULL,
    status      VARCHAR(30) NOT NULL,
    catatan     VARCHAR(255) DEFAULT NULL,
    diubah_oleh INT DEFAULT NULL,
    dibuat_pada DATETIME NOT NULL,
    KEY idx_history_booking (booking_id, dibuat_pada),
    CONSTRAINT fk_history_booking
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user
        FOREIGN KEY (diubah_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- transactions : pesanan walk-in dari panel kasir (STRUKTUR LAMA, tidak diubah)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    no_pesanan     VARCHAR(30) NOT NULL,
    nama_pelanggan VARCHAR(100) NOT NULL,
    no_hp          VARCHAR(20) NOT NULL,
    service_id     INT NOT NULL,
    tanggal_masuk  DATE NOT NULL,
    status         VARCHAR(30) NOT NULL DEFAULT 'Antrean',
    total_harga    INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;