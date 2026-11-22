<?php
require_once __DIR__ . '/partials/init.php';

// 1. Hanya admin yang boleh membuka panel kasir
$admin = butuh_admin();

// ---------- Helper ----------
// Simpan pesan lalu muat ulang halaman, agar refresh tidak mengirim form dua kali
function kembali_dengan_pesan($teks, $tipe, $sorot = null) {
    $_SESSION['flash'] = ['teks' => $teks, 'tipe' => $tipe, 'sorot' => $sorot];
    header("Location: dashboard.php");
    exit();
}

$pesan      = null;
$pesan_tipe = null;
$pesan_sorot = null;

// Pesan dari aksi sebelumnya
if (isset($_SESSION['flash'])) {
    $pesan       = $_SESSION['flash']['teks'];
    $pesan_tipe  = $_SESSION['flash']['tipe'];
    $pesan_sorot = $_SESSION['flash']['sorot'];
    unset($_SESSION['flash']);
}

// Nilai awal formulir (dipertahankan bila ada kesalahan)
$old = [
    'nama_pelanggan' => '',
    'no_hp'          => '',
    'service_id'     => 0,
    'tanggal_masuk'  => date('Y-m-d'),
];

// 2. Proses tambah transaksi baru
if (isset($_POST['tambahTransaksi'])) {
    wajib_csrf();
    $nama_pelanggan = trim($_POST['nama_pelanggan']);
    $no_hp          = trim($_POST['no_hp']);
    $service_id     = intval($_POST['service_id']);
    $tanggal_masuk  = trim($_POST['tanggal_masuk']);

    $old = [
        'nama_pelanggan' => $nama_pelanggan,
        'no_hp'          => $no_hp,
        'service_id'     => $service_id,
        'tanggal_masuk'  => $tanggal_masuk,
    ];

    if (!empty($nama_pelanggan) && !empty($no_hp) && !empty($service_id) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_masuk)) {
        // Ambil harga dari tabel services berdasarkan service_id
        $stmt_harga = mysqli_prepare($koneksi, "SELECT harga FROM services WHERE id = ?");
        mysqli_stmt_bind_param($stmt_harga, "i", $service_id);
        mysqli_stmt_execute($stmt_harga);
        $result_harga = mysqli_stmt_get_result($stmt_harga);

        if ($row_harga = mysqli_fetch_assoc($result_harga)) {
            $total_harga = $row_harga['harga'];

            // Nomor pesanan unik (contoh: TRX-20261115-412)
            $no_pesanan = "TRX-" . date("Ymd") . "-" . rand(100, 999);

            $stmt_insert = mysqli_prepare(
                $koneksi,
                "INSERT INTO transactions (no_pesanan, nama_pelanggan, no_hp, service_id, tanggal_masuk, status, total_harga)
                 VALUES (?, ?, ?, ?, ?, 'Antrean', ?)"
            );
            mysqli_stmt_bind_param($stmt_insert, "sssisi", $no_pesanan, $nama_pelanggan, $no_hp, $service_id, $tanggal_masuk, $total_harga);

            if (mysqli_stmt_execute($stmt_insert)) {
                kembali_dengan_pesan("Pesanan dicatat dengan nomor", "success", $no_pesanan);
            } else {
                $pesan = "Pesanan belum tersimpan: " . mysqli_error($koneksi);
                $pesan_tipe = "danger";
            }
        } else {
            $pesan = "Layanan yang dipilih tidak valid. Pilih layanan dari daftar.";
            $pesan_tipe = "danger";
        }
    } else {
        $pesan = "Lengkapi nama pelanggan, nomor HP, layanan, dan tanggal masuk.";
        $pesan_tipe = "warning";
    }
}

// 3. Update status transaksi
if (isset($_POST['updateStatus'])) {
    wajib_csrf();
    $trx_id       = intval($_POST['trx_id']);
    $status_baru  = trim($_POST['status']);
    $valid_status = ['Antrean', 'Dicuci', 'Selesai', 'Diambil'];

    if (in_array($status_baru, $valid_status)) {
        $stmt_update = mysqli_prepare($koneksi, 'UPDATE transactions SET status = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt_update, 'si', $status_baru, $trx_id);

        if (mysqli_stmt_execute($stmt_update)) {
            kembali_dengan_pesan("Status pesanan #$trx_id diubah menjadi", "success", $status_baru);
        }
    }
    kembali_dengan_pesan("Status pesanan belum berubah. Muat ulang halaman lalu coba lagi.", "danger");
}

// 4. Hapus transaksi
if (isset($_POST['hapus'])) {
    wajib_csrf();
    $id_hapus = intval($_POST['trx_id']);
    $stmt_del = mysqli_prepare($koneksi, "DELETE FROM transactions WHERE id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $id_hapus);

    if (mysqli_stmt_execute($stmt_del)) {
        kembali_dengan_pesan("Pesanan dihapus.", "success");
    }
    kembali_dengan_pesan("Pesanan belum terhapus. Coba lagi.", "danger");
}

// 5. Query data layanan (services)
$services_query = mysqli_query($koneksi, "SELECT * FROM services ORDER BY harga ASC");
$services_list  = [];
while ($s = mysqli_fetch_assoc($services_query)) {
    $services_list[] = $s;
}

// 6. Query ringkasan statistik
$jumlah = ['Antrean' => 0, 'Dicuci' => 0, 'Selesai' => 0, 'Diambil' => 0];
$stat_query = mysqli_query($koneksi, "SELECT status, COUNT(*) AS jml FROM transactions GROUP BY status");
while ($r = mysqli_fetch_assoc($stat_query)) {
    if (isset($jumlah[$r['status']])) {
        $jumlah[$r['status']] = (int) $r['jml'];
    }
}
$total_trx = array_sum($jumlah);

// Total pendapatan dari pesanan yang sudah selesai/diambil
$pendapatan_query = mysqli_query($koneksi, "SELECT SUM(total_harga) AS total FROM transactions WHERE status IN ('Selesai', 'Diambil')");
$pendapatan_row   = mysqli_fetch_assoc($pendapatan_query);
$total_pendapatan = $pendapatan_row['total'] ?? 0;

// 7. Query transaksi (JOIN dengan tabel services)
$trx_query = "SELECT t.*, s.nama_layanan
              FROM transactions t
              JOIN services s ON t.service_id = s.id
              ORDER BY t.id DESC";
$trx_result = mysqli_query($koneksi, $trx_query);

$hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hari_ini = $hari[(int) date('w')] . ', ' . tanggal_id(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan · Cuci Sepatu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark" aria-hidden="true"></span>
            Cuci Sepatu <span class="brand-sub">Admin</span>
        </a>

        <nav class="mainnav" aria-label="Navigasi admin">
            <a class="nav-link nav-link-aktif" href="dashboard.php">Kasir</a>
            <a class="nav-link" href="admin/data_booking.php">
                Booking<?php
                    $n_konfirmasi = 0;
                    $q = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM bookings WHERE status = 'menunggu_konfirmasi'");
                    if ($q) { $n_konfirmasi = (int) mysqli_fetch_assoc($q)['n']; }
                    echo $n_konfirmasi > 0 ? ' <span class="nav-dot num">' . $n_konfirmasi . '</span>' : '';
                ?>
            </a>
            <a class="nav-link" href="admin/data_pelanggan.php">Pelanggan</a>
            <a class="nav-link" href="admin/data_layanan.php">Layanan</a>
        </nav>

        <div class="userbar">
            <span class="who">Login Sebagai <strong style="text-transform: uppercase"><?php echo e($_SESSION['role']); ?></strong></span>
            <a href="logout.php">Keluar</a>
        </div>
    </div>
</header>

<main class="page">

    <div class="page-head">
        <div>
            <h1 class="page-title">Pesanan</h1>
            <p class="page-date"><?php echo e($hari_ini); ?></p>
            <a class="btn btn-primary jump" href="#form-pesanan">Catat pesanan baru</a>
        </div>
        <div class="revenue-group">
            <div class="revenue">
                <div class="revenue-label">Pendapatan kasir</div>
                <div class="revenue-value num"><?php echo e(rupiah($total_pendapatan)); ?></div>
                <div class="revenue-note">Pesanan walk-in berstatus Selesai dan Diambil</div>
            </div>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="alert alert-<?php echo e($pesan_tipe); ?>" role="<?php echo $pesan_tipe === 'danger' ? 'alert' : 'status'; ?>">
            <?php echo e($pesan); ?>
            <?php if ($pesan_sorot): ?> <strong><?php echo e($pesan_sorot); ?></strong>.<?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="stages" role="group" aria-label="Filter berdasarkan status">
        <button type="button" class="stage" data-filter="all" aria-pressed="true">
            <span class="stage-count num"><?php echo $total_trx; ?></span>
            <span class="stage-name">Semua</span>
        </button>
        <?php foreach ($jumlah as $nama_status => $jml): ?>
            <button type="button" class="stage" data-filter="<?php echo e($nama_status); ?>" aria-pressed="false">
                <span class="stage-count num"><?php echo $jml; ?></span>
                <span class="stage-name"><span class="stage-dot" aria-hidden="true"></span><?php echo e($nama_status); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="workspace">

        <section class="panel" aria-labelledby="judul-daftar">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-daftar">Daftar pesanan</h2>
                <p class="panel-sub">Ubah status langsung dari kolom Status.</p>
            </div>

            <?php if ($total_trx === 0): ?>
                <div class="empty">
                    <strong>Belum ada pesanan</strong>
                    Catat sepatu pertama lewat formulir pesanan baru.
                </div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="orders">
                    <thead>
                        <tr>
                            <th scope="col">Pesanan</th>
                            <th scope="col">Pelanggan</th>
                            <th scope="col">Layanan</th>
                            <th scope="col" class="col-money">Total</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="col-action"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($trx = mysqli_fetch_assoc($trx_result)): ?>
                        <tr data-status="<?php echo e($trx['status']); ?>">
                            <td>
                                <span class="tag num"><?php echo e($trx['no_pesanan']); ?></span>
                                <div class="date-under">Masuk <?php echo e(tanggal_id($trx['tanggal_masuk'])); ?></div>
                            </td>
                            <td>
                                <div class="cell-main"><?php echo e($trx['nama_pelanggan']); ?></div>
                                <div class="cell-sub num"><?php echo e($trx['no_hp']); ?></div>
                            </td>
                            <td data-label="Layanan"><?php echo e($trx['nama_layanan']); ?></td>
                            <td class="col-money num" data-label="Total"><?php echo e(rupiah($trx['total_harga'])); ?></td>
                            <td data-label="Status">
                                <form method="POST" class="status" data-status="<?php echo e($trx['status']); ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="trx_id" value="<?php echo (int) $trx['id']; ?>">
                                    <input type="hidden" name="updateStatus" value="1">
                                    <label class="sr-only" for="status-<?php echo (int) $trx['id']; ?>">Status pesanan <?php echo e($trx['no_pesanan']); ?></label>
                                    <select class="select" id="status-<?php echo (int) $trx['id']; ?>" name="status">
                                        <?php foreach (array_keys($jumlah) as $opt): ?>
                                            <option value="<?php echo e($opt); ?>" <?php echo $opt === $trx['status'] ? 'selected' : ''; ?>><?php echo e($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button type="submit" class="btn btn-outline">Simpan</button></noscript>
                                </form>
                            </td>
                            <td class="col-action">
                                <button type="button" class="btn-text"
                                        data-hapus="<?php echo (int) $trx['id']; ?>"
                                        data-nomor="<?php echo e($trx['no_pesanan']); ?>"
                                        aria-label="Hapus pesanan <?php echo e($trx['no_pesanan']); ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="empty" id="filter-kosong" hidden>
                <strong>Tidak ada pesanan di tahap ini</strong>
                Pilih tahap lain di atas, atau tampilkan Semua.
            </div>
            <?php endif; ?>
        </section>

        <aside class="panel sticky" id="form-pesanan" aria-labelledby="judul-form">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-form">Pesanan baru</h2>
                <p class="panel-sub">Status awal otomatis Antrean.</p>
            </div>
            <form class="panel-body" method="POST">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <label for="nama_pelanggan">Nama pelanggan</label>
                    <input class="input" type="text" id="nama_pelanggan" name="nama_pelanggan" autocomplete="off"
                           value="<?php echo e($old['nama_pelanggan']); ?>" required>
                </div>

                <div class="field">
                    <label for="no_hp">Nomor HP</label>
                    <input class="input" type="tel" id="no_hp" name="no_hp" inputmode="tel" autocomplete="off"
                           placeholder="08xxxxxxxxxx" value="<?php echo e($old['no_hp']); ?>" required>
                </div>

                <div class="field">
                    <label for="tanggal_masuk">Tanggal masuk</label>
                    <input class="input" type="date" id="tanggal_masuk" name="tanggal_masuk"
                           value="<?php echo e($old['tanggal_masuk']); ?>" required>
                </div>

                <button class="btn btn-primary btn-block" type="submit" name="tambahTransaksi">Simpan pesanan</button>
            </form>
        </aside>

    </div>
</main>

<dialog class="dialog" id="dialog-hapus" aria-labelledby="dialog-judul">
    <form method="POST">
        <?php echo csrf_field(); ?>
        <h2 id="dialog-judul">Hapus pesanan?</h2>
        <p>Pesanan <strong id="dialog-nomor"></strong> akan dihapus permanen dan tidak bisa dikembalikan.</p>
        <input type="hidden" name="trx_id" id="dialog-id" value="">
        <input type="hidden" name="hapus" value="1">
        <div class="dialog-actions">
            <button type="button" class="btn btn-outline" id="dialog-batal">Batal</button>
            <button type="submit" class="btn btn-danger">Hapus pesanan</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    // Ubah status: kirim form begitu pilihan diganti
    document.querySelectorAll('form.status select').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // Filter daftar berdasarkan tahap
    var tombol = document.querySelectorAll('.stage');
    var baris  = document.querySelectorAll('.orders tbody tr');
    var kosong = document.getElementById('filter-kosong');
    var tabel  = document.querySelector('.table-wrap');

    tombol.forEach(function (b) {
        b.addEventListener('click', function () {
            var f = b.getAttribute('data-filter');
            var tampil = 0;
            tombol.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
            baris.forEach(function (r) {
                var cocok = f === 'all' || r.getAttribute('data-status') === f;
                r.hidden = !cocok;
                if (cocok) tampil++;
            });
            if (kosong && tabel) {
                kosong.hidden = tampil !== 0;
                tabel.hidden  = tampil === 0;
            }
        });
    });

    // Dialog konfirmasi hapus
    var dlg = document.getElementById('dialog-hapus');
    document.querySelectorAll('[data-hapus]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('dialog-id').value = b.getAttribute('data-hapus');
            document.getElementById('dialog-nomor').textContent = b.getAttribute('data-nomor');
            dlg.showModal();
        });
    });
    document.getElementById('dialog-batal').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (ev) { if (ev.target === dlg) dlg.close(); });

    // Tampilkan harga layanan yang dipilih
    var svc  = document.getElementById('service_id');
    var info = document.getElementById('harga-info');
    function tampilHarga() {
        var opt = svc.options[svc.selectedIndex];
        info.innerHTML = '';
        if (opt && opt.getAttribute('data-harga')) {
            var s = document.createElement('strong');
            s.textContent = opt.getAttribute('data-harga');
            info.appendChild(document.createTextNode('Total pesanan: '));
            info.appendChild(s);
        }
    }
    svc.addEventListener('change', tampilHarga);
    tampilHarga();
})();
</script>
</body>
</html>