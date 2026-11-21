<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_admin();
$admin_id = (int) $user['id'];

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare(
    $koneksi,
    'SELECT b.*, s.nama_layanan, p.nama_paket, p.estimasi_hari,
            u.nama_lengkap, u.email, u.no_hp, u.username, u.id AS uid
     FROM bookings b
     JOIN services s ON s.id = b.service_id
     LEFT JOIN pakets p ON p.id = b.paket_id
     JOIN users u ON u.id = b.user_id
     WHERE b.id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$b = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$b) {
    flash_set('Booking tidak ditemukan.', 'danger');
    header('Location: data_booking.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'status') {
        $baru    = trim($_POST['status'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        if (!status_valid($baru)) {
            flash_set('Status tidak dikenal.', 'danger');
        } elseif ($baru === $b['status']) {
            flash_set('Status tidak berubah.', 'warning');
        } else {
            $upd = mysqli_prepare($koneksi, 'UPDATE bookings SET status=?, diperbarui_pada=NOW() WHERE id=?');
            mysqli_stmt_bind_param($upd, 'si', $baru, $id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            catat_status($koneksi, $id, $baru, $admin_id, $catatan !== '' ? $catatan : null);
            flash_set('Status diubah ke ' . status_label($baru) . '.');
        }
    } elseif ($aksi === 'catatan_admin') {
        $catatan = trim($_POST['catatan_admin'] ?? '');
        $upd = mysqli_prepare($koneksi, 'UPDATE bookings SET catatan_admin=?, diperbarui_pada=NOW() WHERE id=?');
        mysqli_stmt_bind_param($upd, 'si', $catatan, $id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        flash_set('Catatan untuk pelanggan disimpan.');
    }
    header('Location: detail_booking.php?id=' . $id);
    exit();
}

$riwayat = riwayat_status($koneksi, $id);
$tahap   = status_tahap();
$posisi  = array_search($b['status'], $tahap, true);
$batal   = $b['status'] === 'dibatalkan';
$persen  = $batal ? 0 : (int) round((((int) $posisi) + 1) / count($tahap) * 100);

$judul = 'Booking ' . $b['kode_booking'];
$aktif = 'booking';
require dirname(__DIR__) . '/partials/header_admin.php';
?>

<div class="page-head">
    <div>
        <p class="crumb"><a href="data_booking.php">Data booking</a> / <?php echo e($b['kode_booking']); ?></p>
        <h1 class="page-title"><?php echo e($b['kode_booking']); ?></h1>
        <p class="page-date">Dipesan <?php echo e(tanggal_waktu_id($b['dibuat_pada'])); ?></p>
    </div>
    <span class="badge badge-lg" data-warna="<?php echo e(status_warna($b['status'])); ?>"><?php echo e(status_label($b['status'])); ?></span>
</div>

<div class="workspace">

    <div>
        <section class="panel" aria-labelledby="judul-info">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-info">Rincian booking</h2>
            </div>
            <dl class="details">
                <div><dt>Pelanggan</dt><dd><?php echo e($b['nama_lengkap']); ?></dd></div>
                <div><dt>Username</dt><dd><?php echo e($b['username']); ?></dd></div>
                <div><dt>Email</dt><dd><?php echo e($b['email']); ?></dd></div>
                <div><dt>Nomor HP</dt><dd class="num"><?php echo e($b['no_hp']); ?></dd></div>
                <div><dt>Layanan</dt><dd><?php echo e($b['nama_layanan']); ?></dd></div>
                <div><dt>Paket</dt><dd><?php echo e($b['nama_paket'] ?? '-'); ?></dd></div>
                <div><dt>Tipe sepatu</dt><dd><?php echo e($b['tipe_sepatu']); ?></dd></div>
                <div><dt>Jumlah</dt><dd class="num"><?php echo (int) $b['jumlah_sepatu']; ?> pasang</dd></div>
                <div><dt>Tanggal booking</dt><dd><?php echo e(tanggal_panjang($b['tanggal_booking'])); ?></dd></div>
                <div><dt>Jam</dt><dd class="num"><?php echo e(jam_singkat($b['jam_booking'])); ?> WIB</dd></div>
                <?php if (!empty($b['catatan'])): ?>
                <div class="span-2"><dt>Catatan pelanggan</dt><dd class="plain"><?php echo nl2br(e($b['catatan'])); ?></dd></div>
                <?php endif; ?>
                <div class="details-total">
                    <dt>Total harga</dt>
                    <dd class="num"><?php echo e(rupiah($b['total_harga'])); ?></dd>
                </div>
            </dl>
        </section>

        <section class="panel" aria-labelledby="judul-progres" style="margin-top:var(--s5)">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-progres">Progres</h2>
                <p class="panel-sub">Tampilan yang dilihat pelanggan.</p>
            </div>
            <div class="panel-body">
                <div class="progressbar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $persen; ?>">
                    <span class="progressbar-fill" style="width:<?php echo $persen; ?>%"></span>
                </div>
                <ol class="track track-wide" aria-label="Tahap pesanan">
                    <?php foreach ($tahap as $i => $kode): ?>
                        <?php
                            if ($batal) {
                                $kelas = 'pending';
                            } elseif ($posisi !== false && $i < $posisi) {
                                $kelas = 'done';
                            } elseif ($posisi !== false && $i === $posisi) {
                                $kelas = 'current';
                            } else {
                                $kelas = 'pending';
                            }
                        ?>
                        <li class="<?php echo $kelas; ?>"<?php echo $kelas === 'current' ? ' aria-current="step"' : ''; ?>>
                            <span class="track-dot" aria-hidden="true"></span>
                            <span class="track-label"><?php echo e(status_label($kode)); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>
    </div>

    <aside class="sticky">
        <section class="panel" aria-labelledby="judul-ubah">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-ubah">Perbarui status</h2>
                <p class="panel-sub">Pelanggan langsung melihat perubahan ini.</p>
            </div>
            <form class="panel-body" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="aksi" value="status">

                <div class="field">
                    <label for="status">Status baru</label>
                    <select class="select" id="status" name="status" required
                            data-status-sekarang="<?php echo e($b['status']); ?>">
                        <?php foreach (status_booking_semua() as $kode => $info): ?>
                            <option value="<?php echo e($kode); ?>"
                                    data-mundur="<?php echo status_mundur($b['status'], $kode) ? '1' : '0'; ?>"
                                    <?php echo $kode === $b['status'] ? 'selected' : ''; ?>>
                                <?php echo e($info['label']); ?><?php echo status_mundur($b['status'], $kode) ? ' ↩' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">Status boleh dimundurkan bila salah klik. Setiap perubahan tetap tercatat di riwayat.</p>
                </div>

                <div class="field">
                    <label for="catatan">Catatan perubahan (opsional)</label>
                    <input class="input" type="text" id="catatan" name="catatan" maxlength="255"
                           placeholder="Contoh: sepatu sudah selesai QC">
                </div>

                <button class="btn btn-primary btn-block" type="submit">Simpan status</button>
            </form>
        </section>

        <section class="panel" style="margin-top:var(--s5)" aria-labelledby="judul-cat">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-cat">Catatan untuk pelanggan</h2>
            </div>
            <form class="panel-body" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="aksi" value="catatan_admin">
                <div class="field">
                    <textarea class="input textarea" name="catatan_admin" rows="3" maxlength="1000"><?php echo e($b['catatan_admin']); ?></textarea>
                </div>
                <button class="btn btn-outline btn-block" type="submit">Simpan catatan</button>
            </form>
        </section>

        <section class="panel" style="margin-top:var(--s5)" aria-labelledby="judul-hist">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-hist">Riwayat status</h2>
            </div>
            <?php if (!$riwayat): ?>
                <div class="empty">Belum ada riwayat.</div>
            <?php else: ?>
            <ul class="history">
                <?php foreach (array_reverse($riwayat) as $r): ?>
                <li>
                    <span class="history-dot" data-warna="<?php echo e(status_warna($r['status'])); ?>" aria-hidden="true"></span>
                    <div>
                        <strong><?php echo e(status_label($r['status'])); ?></strong>
                        <span class="history-time num"><?php echo e(tanggal_waktu_id($r['dibuat_pada'])); ?></span>
                        <?php if (!empty($r['catatan'])): ?>
                            <span class="history-note"><?php echo e($r['catatan']); ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </aside>

</div>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>

<script>
// Konfirmasi saat status dimundurkan. Ini hanya pengaman salah klik —
// server tetap menerima perubahan, dan riwayatnya tetap tercatat.
(function () {
    var sel = document.getElementById('status');
    if (!sel) return;
    sel.form.addEventListener('submit', function (ev) {
        var opt = sel.options[sel.selectedIndex];
        if (opt && opt.getAttribute('data-mundur') === '1') {
            if (!confirm('Mundurkan status ke "' + opt.text.replace(' ↩', '') + '"?\n\nPelanggan akan melihat status ini.')) {
                ev.preventDefault();
            }
        }
    });
})();
</script>
