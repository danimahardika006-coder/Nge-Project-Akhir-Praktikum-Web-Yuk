<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_admin();

$pesan = '';
$tipe_pesan = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah_layanan') {
        $nama  = trim($_POST['nama_layanan'] ?? '');
        $harga = (int) ($_POST['harga'] ?? 0);
        $ket   = trim($_POST['deskripsi'] ?? '');

        if ($nama === '' || $harga <= 0) {
            $pesan = 'Nama layanan dan harga wajib diisi (harga lebih dari 0).';
            $tipe_pesan = 'danger';
        } else {
            $stmt = mysqli_prepare($koneksi, 'INSERT INTO services (nama_layanan, harga, deskripsi, aktif) VALUES (?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'sis', $nama, $harga, $ket);
            if (mysqli_stmt_execute($stmt)) {
                flash_set('Layanan "' . $nama . '" ditambahkan.');
                header('Location: data_layanan.php');
                exit();
            }
            $pesan = 'Layanan gagal ditambahkan.';
            $tipe_pesan = 'danger';
            mysqli_stmt_close($stmt);
        }
    } elseif ($aksi === 'ubah_layanan') {
        $id    = (int) ($_POST['id'] ?? 0);
        $harga = (int) ($_POST['harga'] ?? 0);
        $nama  = trim($_POST['nama_layanan'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;

        if ($id > 0 && $harga > 0 && $nama !== '') {
            $stmt = mysqli_prepare($koneksi, 'UPDATE services SET nama_layanan=?, harga=?, aktif=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'siii', $nama, $harga, $aktif, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flash_set('Layanan diperbarui.');
            header('Location: data_layanan.php');
            exit();
        }
        $pesan = 'Perubahan layanan tidak valid.';
        $tipe_pesan = 'danger';
    } elseif ($aksi === 'hapus_layanan' || $aksi === 'hapus_paket' || $aksi === 'hapus_tipe') {
        // ---------- Hapus data master ----------
        // Kalau sudah dipakai booking, hapus permanen akan merusak riwayat pelanggan
        // (FK bookings.service_id ON DELETE RESTRICT). Aturan: belum dipakai → hapus,
        // sudah dipakai → otomatis dinonaktifkan supaya riwayat tetap utuh.
        $id = (int) ($_POST['id'] ?? 0);

        $peta = [
            'hapus_layanan' => ['services',    'service_id', 'layanan',     'nama_layanan'],
            'hapus_paket'   => ['pakets',      'paket_id',   'paket',       'nama_paket'],
            'hapus_tipe'    => ['tipe_sepatu', null,         'tipe sepatu', 'nama_tipe'],
        ];
        [$tabel, $kolom_pakai, $label, $kolom_nama] = $peta[$aksi];

        if ($id <= 0) {
            $pesan = 'Data yang mau dihapus tidak valid.';
            $tipe_pesan = 'danger';
        } else {
            $stmt = mysqli_prepare($koneksi, "SELECT $kolom_nama AS nama FROM $tabel WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $baris_ada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$baris_ada) {
                $pesan = ucfirst($label) . ' tidak ditemukan (mungkin sudah dihapus).';
                $tipe_pesan = 'danger';
            } else {
                $nama_hapus = $baris_ada['nama'];

                // Tipe sepatu disimpan sebagai teks di bookings.tipe_sepatu
                if ($kolom_pakai !== null) {
                    $stmt = mysqli_prepare($koneksi, "SELECT COUNT(*) AS n FROM bookings WHERE $kolom_pakai = ?");
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                } else {
                    $stmt = mysqli_prepare($koneksi, 'SELECT COUNT(*) AS n FROM bookings WHERE tipe_sepatu = ?');
                    mysqli_stmt_bind_param($stmt, 's', $nama_hapus);
                }
                mysqli_stmt_execute($stmt);
                $dipakai = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
                mysqli_stmt_close($stmt);

                if ($dipakai > 0) {
                    $stmt = mysqli_prepare($koneksi, "UPDATE $tabel SET aktif = 0 WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    flash_set(ucfirst($label) . ' "' . $nama_hapus . '" dinonaktifkan karena dipakai '
                        . $dipakai . ' booking. Riwayat pelanggan perlu tetap utuh.');
                } else {
                    $stmt = mysqli_prepare($koneksi, "DELETE FROM $tabel WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    flash_set(ucfirst($label) . ' "' . $nama_hapus . '" dihapus permanen.');
                }
                header('Location: data_layanan.php');
                exit();
            }
        }
    } elseif ($aksi === 'tambah_paket') {
        $nama  = trim($_POST['nama_paket'] ?? '');
        $tambah = (int) ($_POST['tambahan_harga'] ?? 0);
        $est   = max(1, (int) ($_POST['estimasi_hari'] ?? 1));

        if ($nama === '') {
            $pesan = 'Nama paket wajib diisi.';
            $tipe_pesan = 'danger';
        } else {
            $stmt = mysqli_prepare($koneksi, 'INSERT INTO pakets (nama_paket, tambahan_harga, estimasi_hari, aktif) VALUES (?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'sii', $nama, $tambah, $est);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flash_set('Paket "' . $nama . '" ditambahkan.');
            header('Location: data_layanan.php');
            exit();
        }
    } elseif ($aksi === 'tambah_tipe') {
        $nama = trim($_POST['nama_tipe'] ?? '');

        if ($nama === '') {
            $pesan = 'Nama tipe sepatu wajib diisi.';
            $tipe_pesan = 'danger';
        } else {
            $stmt = mysqli_prepare($koneksi, 'INSERT INTO tipe_sepatu (nama_tipe, aktif) VALUES (?, 1)');
            mysqli_stmt_bind_param($stmt, 's', $nama);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flash_set('Tipe sepatu "' . $nama . '" ditambahkan.');
            header('Location: data_layanan.php');
            exit();
        }
    } elseif ($aksi === 'ubah_tipe') {
        $id    = (int) ($_POST['id'] ?? 0);
        $nama  = trim($_POST['nama_tipe'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;

        if ($id <= 0 || $nama === '') {
            $pesan = 'Perubahan tipe sepatu tidak valid.';
            $tipe_pesan = 'danger';
        } else {
            $stmt = mysqli_prepare($koneksi, 'UPDATE tipe_sepatu SET nama_tipe=?, aktif=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sii', $nama, $aktif, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flash_set('Tipe sepatu diperbarui.');
            header('Location: data_layanan.php');
            exit();
        }
    }
}

$services = mysqli_query($koneksi, 'SELECT * FROM services ORDER BY harga ASC')->fetch_all(MYSQLI_ASSOC);
$pakets   = mysqli_query($koneksi, 'SELECT * FROM pakets ORDER BY tambahan_harga ASC')->fetch_all(MYSQLI_ASSOC);
$tipes    = mysqli_query($koneksi, 'SELECT * FROM tipe_sepatu ORDER BY id ASC')->fetch_all(MYSQLI_ASSOC);

$judul = 'Layanan & Paket';
$aktif = 'layanan';
require dirname(__DIR__) . '/partials/header_admin.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Layanan &amp; paket</h1>
        <p class="page-date">Harga di sini dipakai saat pelanggan membuat booking.</p>
    </div>
</div>

<?php if ($pesan !== ''): ?>
    <div class="alert alert-<?php echo e($tipe_pesan); ?>" role="alert"><?php echo e($pesan); ?></div>
<?php endif; ?>

<div class="workspace">

    <div>
        <section class="panel" aria-labelledby="judul-svc">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-svc">Daftar layanan</h2>
                <p class="panel-sub"><?php echo count($services); ?> layanan. Nonaktifkan agar tidak muncul di formulir booking.</p>
            </div>
            <div class="table-wrap">
                <table class="orders">
                    <thead>
                        <tr>
                            <th scope="col">Layanan</th>
                            <th scope="col" class="col-money">Harga</th>
                            <th scope="col">Aktif</th>
                            <th scope="col" class="col-action"><span class="sr-only">Simpan</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr>
                            <td colspan="4" style="padding:0">
                                <form class="row-form" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="aksi" value="ubah_layanan">
                                    <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">

                                    <div class="row-cell row-grow">
                                        <label class="sr-only" for="nama-<?php echo (int) $s['id']; ?>">Nama layanan</label>
                                        <input class="input input-sm" id="nama-<?php echo (int) $s['id']; ?>"
                                               type="text" name="nama_layanan" value="<?php echo e($s['nama_layanan']); ?>" required>
                                        <?php if (!empty($s['deskripsi'])): ?>
                                            <span class="cell-sub"><?php echo e($s['deskripsi']); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row-cell">
                                        <label class="sr-only" for="harga-<?php echo (int) $s['id']; ?>">Harga</label>
                                        <input class="input input-sm num" id="harga-<?php echo (int) $s['id']; ?>"
                                               type="number" name="harga" min="1000" step="1000"
                                               value="<?php echo (int) $s['harga']; ?>" required>
                                    </div>

                                    <div class="row-cell">
                                        <label class="check">
                                            <input type="checkbox" name="aktif" value="1" <?php echo (int) $s['aktif'] === 1 ? 'checked' : ''; ?>>
                                            <span>Aktif</span>
                                        </label>
                                    </div>

                                    <div class="row-cell row-right">
                                        <button class="btn btn-outline btn-sm" type="submit">Simpan</button>
                                    </div>
                                </form>
                            </td>
                            <td class="row-hapus">
                                <form method="POST"
                                      onsubmit="return confirm('Hapus layanan &quot;<?php echo e($s['nama_layanan']); ?>&quot;?\n\nKalau layanan ini sudah pernah dipakai booking, data akan dinonaktifkan (bukan dihapus) supaya riwayat pelanggan tetap utuh.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="aksi" value="hapus_layanan">
                                    <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                    <button class="btn btn-outline btn-sm btn-danger-text" type="submit"
                                            title="Hapus layanan" aria-label="Hapus layanan <?php echo e($s['nama_layanan']); ?>">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-labelledby="judul-paket" style="margin-top:var(--s5)">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-paket">Paket pengerjaan</h2>
                <p class="panel-sub">Tambahan harga dijumlahkan dengan harga layanan.</p>
            </div>
            <div class="table-wrap">
                <table class="orders">
                    <thead>
                        <tr>
                            <th scope="col">Paket</th>
                            <th scope="col" class="col-money">Tambahan</th>
                            <th scope="col">Estimasi</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="col-action"><span class="sr-only">Hapus</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pakets as $p): ?>
                        <tr>
                            <td>
                                <div class="cell-main"><?php echo e($p['nama_paket']); ?></div>
                                <?php if (!empty($p['keterangan'])): ?>
                                    <div class="cell-sub"><?php echo e($p['keterangan']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="col-money num" data-label="Tambahan"><?php echo e(rupiah($p['tambahan_harga'])); ?></td>
                            <td data-label="Estimasi"><?php echo (int) $p['estimasi_hari']; ?> hari</td>
                            <td data-label="Status"><?php echo (int) $p['aktif'] === 1 ? 'Aktif' : 'Nonaktif'; ?></td>
                            <td class="row-hapus">
                                <form method="POST"
                                      onsubmit="return confirm('Hapus paket &quot;<?php echo e($p['nama_paket']); ?>&quot;?\n\nKalau paket ini sudah pernah dipakai booking, data akan dinonaktifkan (bukan dihapus) supaya riwayat pelanggan tetap utuh.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="aksi" value="hapus_paket">
                                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                    <button class="btn btn-outline btn-sm btn-danger-text" type="submit"
                                            title="Hapus paket" aria-label="Hapus paket <?php echo e($p['nama_paket']); ?>">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-labelledby="judul-tipe" style="margin-top:var(--s5)">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-tipe">Tipe sepatu</h2>
                <p class="panel-sub">Muncul di formulir booking. Minimal satu tipe harus aktif.</p>
            </div>
            <div class="table-wrap">
                <table class="orders">
                    <thead>
                        <tr>
                            <th scope="col">Tipe</th>
                            <th scope="col">Aktif</th>
                            <th scope="col" class="col-action"><span class="sr-only">Simpan</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tipes as $t): ?>
                        <tr>
                            <td colspan="3" style="padding:0">
                                <form class="row-form" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="aksi" value="ubah_tipe">
                                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">

                                    <div class="row-cell row-grow">
                                        <label class="sr-only" for="tipe-<?php echo (int) $t['id']; ?>">Nama tipe</label>
                                        <input class="input input-sm" id="tipe-<?php echo (int) $t['id']; ?>"
                                               type="text" name="nama_tipe" value="<?php echo e($t['nama_tipe']); ?>" required>
                                    </div>

                                    <div class="row-cell">
                                        <label class="check">
                                            <input type="checkbox" name="aktif" value="1" <?php echo (int) $t['aktif'] === 1 ? 'checked' : ''; ?>>
                                            <span>Aktif</span>
                                        </label>
                                    </div>

                                    <div class="row-cell row-right">
                                        <button class="btn btn-outline btn-sm" type="submit">Simpan</button>
                                    </div>
                                </form>
                            </td>
                            <td class="row-hapus">
                                <form method="POST"
                                      onsubmit="return confirm('Hapus tipe sepatu &quot;<?php echo e($t['nama_tipe']); ?>&quot;?\n\nKalau tipe ini sudah pernah dipakai booking, data akan dinonaktifkan (bukan dihapus) supaya riwayat pelanggan tetap utuh.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="aksi" value="hapus_tipe">
                                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                    <button class="btn btn-outline btn-sm btn-danger-text" type="submit"
                                            title="Hapus tipe" aria-label="Hapus tipe <?php echo e($t['nama_tipe']); ?>">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="sticky">
        <section class="panel" aria-labelledby="judul-add-svc">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-add-svc">Tambah layanan</h2>
            </div>
            <form class="panel-body" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="aksi" value="tambah_layanan">
                <div class="field">
                    <label for="nama_layanan">Nama layanan</label>
                    <input class="input" type="text" id="nama_layanan" name="nama_layanan" required maxlength="100">
                </div>
                <div class="field">
                    <label for="harga">Harga per pasang</label>
                    <input class="input num" type="number" id="harga" name="harga" min="1000" step="1000" required>
                </div>
                <div class="field">
                    <label for="deskripsi">Keterangan <span class="opt">(opsional)</span></label>
                    <input class="input" type="text" id="deskripsi" name="deskripsi" maxlength="200">
                </div>
                <button class="btn btn-primary btn-block" type="submit">Tambah layanan</button>
            </form>
        </section>

        <section class="panel" style="margin-top:var(--s5)" aria-labelledby="judul-add-paket">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-add-paket">Tambah paket</h2>
            </div>
            <form class="panel-body" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="aksi" value="tambah_paket">
                <div class="field">
                    <label for="nama_paket">Nama paket</label>
                    <input class="input" type="text" id="nama_paket" name="nama_paket" required maxlength="60">
                </div>
                <div class="field">
                    <label for="tambahan_harga">Tambahan harga</label>
                    <input class="input num" type="number" id="tambahan_harga" name="tambahan_harga" min="0" step="1000" value="0">
                </div>
                <div class="field">
                    <label for="estimasi_hari">Estimasi (hari)</label>
                    <input class="input num" type="number" id="estimasi_hari" name="estimasi_hari" min="1" max="14" value="2">
                </div>
                <button class="btn btn-outline btn-block" type="submit">Tambah paket</button>
            </form>
        </section>

        <section class="panel" style="margin-top:var(--s5)" aria-labelledby="judul-add-tipe">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-add-tipe">Tambah tipe sepatu</h2>
            </div>
            <form class="panel-body" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="aksi" value="tambah_tipe">
                <div class="field">
                    <label for="nama_tipe">Nama tipe</label>
                    <input class="input" type="text" id="nama_tipe" name="nama_tipe" required maxlength="60"
                           placeholder="Contoh: Sneakers">
                </div>
                <button class="btn btn-outline btn-block" type="submit">Tambah tipe</button>
            </form>
        </section>
    </aside>

</div>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>