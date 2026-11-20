<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_customer();

// Hanya layanan aktif yang bisa dipesan.
$services = [];
$res = mysqli_query($koneksi, 'SELECT id, nama_layanan, harga, deskripsi FROM services WHERE aktif = 1 ORDER BY harga ASC');
while ($row = mysqli_fetch_assoc($res)) {
    $services[] = $row;
}
$pakets = [];
$res = mysqli_query($koneksi, 'SELECT id, nama_paket, tambahan_harga, estimasi_hari, keterangan FROM pakets WHERE aktif = 1 ORDER BY tambahan_harga ASC');
while ($row = mysqli_fetch_assoc($res)) {
    $pakets[] = $row;
}
$tipes = [];
$res = mysqli_query($koneksi, 'SELECT nama_tipe FROM tipe_sepatu WHERE aktif = 1 ORDER BY id ASC');
while ($row = mysqli_fetch_assoc($res)) {
    $tipes[] = $row['nama_tipe'];
}

$hari_ini = date('Y-m-d');
$old = [
    'service_id'     => 0,
    'paket_id'       => 0,
    'tipe_sepatu'    => '',
    'jumlah_sepatu'  => 1,
    'catatan'        => '',
    'tanggal_booking'=> $hari_ini,
    'jam_booking'    => '',
];

$error = '';

// Slot yang sudah terisi untuk tanggal terpilih (untuk JS + validasi).
$tanggal_dilihat = $_GET['tanggal'] ?? '';
$slot_info = [];
if ($tanggal_dilihat !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_dilihat)) {
    $slot_info = slot_terpakai($koneksi, $tanggal_dilihat);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();

    $old['service_id']      = (int) ($_POST['service_id'] ?? 0);
    $old['paket_id']        = (int) ($_POST['paket_id'] ?? 0);
    $old['tipe_sepatu']     = trim($_POST['tipe_sepatu'] ?? '');
    $old['jumlah_sepatu']   = (int) ($_POST['jumlah_sepatu'] ?? 1);
    $old['catatan']         = trim($_POST['catatan'] ?? '');
    $old['tanggal_booking'] = trim($_POST['tanggal_booking'] ?? '');
    $old['jam_booking']     = trim($_POST['jam_booking'] ?? '');

    // ---------- Validasi server-side ----------
    if ($old['service_id'] <= 0 || $old['paket_id'] <= 0 || $old['tipe_sepatu'] === ''
        || $old['tanggal_booking'] === '' || $old['jam_booking'] === '') {
        $error = 'Lengkapi layanan, paket, tipe sepatu, tanggal, dan jam.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['tanggal_booking'])
              || strtotime($old['tanggal_booking']) === false) {
        $error = 'Tanggal booking tidak valid.';
    } elseif (strtotime($old['tanggal_booking']) < strtotime($hari_ini)) {
        $error = 'Tanggal booking tidak boleh di masa lalu.';
    } elseif (!in_array($old['jam_booking'], slot_jam(), true)) {
        $error = 'Jam booking tidak tersedia. Pilih dari daftar jam operasional.';
    } elseif ($old['jumlah_sepatu'] < 1 || $old['jumlah_sepatu'] > 20) {
        $error = 'Jumlah sepatu harus antara 1 sampai 20 pasang.';
    } elseif (mb_strlen($old['catatan']) > 500) {
        $error = 'Catatan maksimal 500 karakter.';
    } else {
        // ---------- Ambil data harga dari database (bukan dari form) ----------
        $stmt = mysqli_prepare($koneksi, 'SELECT nama_layanan, harga FROM services WHERE id = ? AND aktif = 1');
        mysqli_stmt_bind_param($stmt, 'i', $old['service_id']);
        mysqli_stmt_execute($stmt);
        $svc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($koneksi, 'SELECT nama_paket, tambahan_harga FROM pakets WHERE id = ? AND aktif = 1');
        mysqli_stmt_bind_param($stmt, 'i', $old['paket_id']);
        mysqli_stmt_execute($stmt);
        $pkt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$svc) {
            $error = 'Layanan tidak tersedia. Pilih dari daftar.';
        } elseif (!$pkt) {
            $error = 'Paket tidak tersedia. Pilih dari daftar.';
        } else {
            // ---------- Cek slot ----------
            $terpakai = slot_terpakai($koneksi, $old['tanggal_booking']);
            if (($terpakai[$old['jam_booking']] ?? 0) >= KAPASITAS_SLOT) {
                $error = 'Slot jam ' . $old['jam_booking'] . ' pada tanggal itu sudah penuh. Pilih jam lain.';
            } else {
                $total = hitung_total((int) $svc['harga'], (int) $pkt['tambahan_harga'], $old['jumlah_sepatu']);
                $kode  = buat_kode_booking($koneksi);
                $now   = date('Y-m-d H:i:s');

                mysqli_begin_transaction($koneksi);
                $ok = true;

                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO bookings
                        (kode_booking, user_id, service_id, paket_id, tipe_sepatu, jumlah_sepatu,
                         catatan, tanggal_booking, jam_booking, harga_layanan, harga_paket,
                         total_harga, status, dibuat_pada, diperbarui_pada)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_konfirmasi', ?, ?)"
                );
                $paket_id = $old['paket_id'] > 0 ? $old['paket_id'] : null;
                // 14 parameter: kode(s) user(i) service(i) paket(i) tipe(s) jumlah(i)
                // catatan(s) tanggal(s) jam(s) harga(i) tambahan(i) total(i) now(s) now(s)
                $tipe_bind = 'siii' . 's' . 'i' . 'sss' . 'iii' . 'ss';
                mysqli_stmt_bind_param(
                    $stmt,
                    $tipe_bind,
                    $kode,
                    $user['id'],
                    $old['service_id'],
                    $paket_id,
                    $old['tipe_sepatu'],
                    $old['jumlah_sepatu'],
                    $old['catatan'],
                    $old['tanggal_booking'],
                    $old['jam_booking'],
                    $svc['harga'],
                    $pkt['tambahan_harga'],
                    $total,
                    $now,
                    $now
                );

                if (!mysqli_stmt_execute($stmt)) {
                    $ok = false;
                    $error = 'Booking gagal disimpan: ' . mysqli_error($koneksi);
                }
                $booking_id = $ok ? mysqli_insert_id($koneksi) : 0;
                mysqli_stmt_close($stmt);

                if ($ok) {
                    catat_status($koneksi, $booking_id, 'menunggu_konfirmasi', $user['id'], 'Booking dibuat pelanggan');
                }

                if ($ok) {
                    mysqli_commit($koneksi);
                    flash_set('Booking berhasil dibuat dengan nomor ' . $kode . '.');
                    header('Location: detail_booking.php?id=' . (int) $booking_id);
                    exit();
                }
                mysqli_rollback($koneksi);
            }
        }
    }
}

$judul = 'Booking Baru';
$aktif = 'booking';
require dirname(__DIR__) . '/partials/header_customer.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Buat booking baru</h1>
        <p class="page-date">Pilih layanan, paket pengerjaan, dan jadwal. Harga total muncul otomatis sebelum dikirim.</p>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="workspace">

    <section class="panel" aria-labelledby="judul-form">
        <div class="panel-head">
            <h2 class="panel-title" id="judul-form">Detail booking</h2>
            <p class="panel-sub">Status awal booking: Menunggu Konfirmasi admin.</p>
        </div>

        <form class="panel-body" method="POST" id="form-booking" novalidate>
            <?php echo csrf_field(); ?>

            <div class="field">
                <label for="service_id">Layanan cuci</label>
                <select class="select" id="service_id" name="service_id" required>
                    <option value="">Pilih layanan</option>
                    <?php foreach ($services as $svc): ?>
                        <option value="<?php echo (int) $svc['id']; ?>"
                                data-harga="<?php echo (int) $svc['harga']; ?>"
                                <?php echo $old['service_id'] === (int) $svc['id'] ? 'selected' : ''; ?>>
                            <?php echo e($svc['nama_layanan']); ?> — <?php echo e(rupiah($svc['harga'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint" id="hint-layanan"></p>
            </div>

            <div class="field">
                <label for="paket_id">Paket pengerjaan</label>
                <select class="select" id="paket_id" name="paket_id" required>
                    <option value="">Pilih paket</option>
                    <?php foreach ($pakets as $p): ?>
                        <option value="<?php echo (int) $p['id']; ?>"
                                data-tambahan="<?php echo (int) $p['tambahan_harga']; ?>"
                                data-estimasi="<?php echo (int) $p['estimasi_hari']; ?>"
                                <?php echo $old['paket_id'] === (int) $p['id'] ? 'selected' : ''; ?>>
                            <?php echo e($p['nama_paket']); ?>
                            (<?php echo (int) $p['tambahan_harga'] > 0 ? '+' . e(rupiah($p['tambahan_harga'])) : 'tanpa tambahan'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint" id="hint-paket"></p>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="tipe_sepatu">Tipe sepatu</label>
                    <select class="select" id="tipe_sepatu" name="tipe_sepatu" required>
                        <option value="">Pilih tipe</option>
                        <?php foreach ($tipes as $t): ?>
                            <option value="<?php echo e($t); ?>" <?php echo $old['tipe_sepatu'] === $t ? 'selected' : ''; ?>><?php echo e($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="jumlah_sepatu">Jumlah sepatu</label>
                    <input class="input" type="number" id="jumlah_sepatu" name="jumlah_sepatu" min="1" max="20"
                           value="<?php echo (int) $old['jumlah_sepatu']; ?>" inputmode="numeric" required>
                </div>
            </div>

            <div class="field">
                <label for="tanggal_booking">Tanggal booking</label>
                <input class="input" type="date" id="tanggal_booking" name="tanggal_booking"
                       min="<?php echo e($hari_ini); ?>" value="<?php echo e($old['tanggal_booking']); ?>" required>
            </div>

            <div class="field">
                <label for="jam_booking">Jam kedatangan</label>
                <select class="select" id="jam_booking" name="jam_booking" required>
                    <option value="">Pilih jam</option>
                    <?php foreach (slot_jam() as $jam): ?>
                        <option value="<?php echo e($jam); ?>" <?php echo $old['jam_booking'] === $jam ? 'selected' : ''; ?>><?php echo e($jam); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint" id="hint-slot">Slot yang penuh akan ditandai otomatis.</p>
            </div>

            <div class="field">
                <label for="catatan">Catatan tambahan <span class="opt">(opsional)</span></label>
                <textarea class="input textarea" id="catatan" name="catatan" rows="3" maxlength="500"
                          placeholder="Contoh: sol kusam, ada noda di bagian lidah..."><?php echo e($old['catatan']); ?></textarea>
            </div>

            <div class="ringkas" id="ringkas" aria-live="polite">
                <div class="ringkas-row"><span>Layanan</span><strong id="ringkas-layanan">—</strong></div>
                <div class="ringkas-row"><span>Paket</span><strong id="ringkas-paket">—</strong></div>
                <div class="ringkas-row"><span>Jumlah</span><strong id="ringkas-jumlah">1 pasang</strong></div>
                <div class="ringkas-row ringkas-total"><span>Estimasi total</span><strong class="num" id="ringkas-total">—</strong></div>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Kirim booking</button>
            <p class="field-hint" style="text-align:center">Booking kamu akan ditinjau admin sebelum diproses.</p>
        </form>
    </section>

    <aside class="panel sticky" aria-labelledby="judul-info">
        <div class="panel-head">
            <h2 class="panel-title" id="judul-info">Cara kerja booking</h2>
        </div>
        <div class="panel-body">
            <ol class="steps">
                <li><strong>Kirim booking</strong> — kamu menerima nomor booking unik.</li>
                <li><strong>Menunggu konfirmasi</strong> — admin memeriksa slot dan harga.</li>
                <li><strong>Dikonfirmasi</strong> — datang sesuai jadwal yang kamu pilih.</li>
                <li><strong>Pantau progres</strong> — status berubah sampai selesai.</li>
            </ol>
            <p class="steps-note">Maksimal <?php echo (int) KAPASITAS_SLOT; ?> booking per jam kedatangan supaya pengerjaan tetap fokus.</p>
        </div>
    </aside>

</div>

<script>
(function () {
    var svc    = document.getElementById('service_id');
    var pkt    = document.getElementById('paket_id');
    var tipe   = document.getElementById('tipe_sepatu');
    var jumlah = document.getElementById('jumlah_sepatu');
    var tgl    = document.getElementById('tanggal_booking');
    var jam    = document.getElementById('jam_booking');

    var rp = function (n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); };
    var fmt = function (jam) { return jam.replace(':', '.'); };

    var info = {
        <?php foreach ($services as $svc): ?>
        '<?php echo (int) $svc['id']; ?>': { nama: <?php echo json_encode($svc['nama_layanan']); ?>, harga: <?php echo (int) $svc['harga']; ?>, ket: <?php echo json_encode((string) ($svc['deskripsi'] ?? '')); ?> },
        <?php endforeach; ?>
    };
    var infoPaket = {
        <?php foreach ($pakets as $p): ?>
        '<?php echo (int) $p['id']; ?>': { nama: <?php echo json_encode($p['nama_paket']); ?>, tambahan: <?php echo (int) $p['tambahan_harga']; ?>, estimasi: <?php echo (int) $p['estimasi_hari']; ?>, ket: <?php echo json_encode((string) $p['keterangan']); ?> },
        <?php endforeach; ?>
    };
    var slot = <?php echo json_encode($slot_info ?: new stdClass()); ?>;
    var kap  = <?php echo (int) KAPASITAS_SLOT; ?>;
    var hariIniStr = <?php echo json_encode($hari_ini); ?>;

    function perbaruiSlot() {
        var hariIni = new Date();
        var y = tgl.value;
        var hintSlot = document.getElementById('hint-slot');
        var teks = [];
        Array.prototype.forEach.call(jam.options, function (opt, i) {
            if (i === 0) return; // placeholder
            var jam = opt.value;
            var terpakai = slot[jam] || 0;
            var penuh = terpakai >= kap;
            opt.disabled = penuh;
            if (penuh) {
                opt.textContent = fmt(jam) + ' — penuh';
                teks.push(fmt(jam));
            } else if (terpakai > 0) {
                opt.textContent = fmt(jam) + ' — sisa ' + (kap - terpakai);
            } else {
                opt.textContent = fmt(jam);
            }
            if (y === hariIniStr) {
                var h = new Date();
                var jamNum = parseInt(jam.split(':')[0], 10);
                if (jamNum <= h.getHours()) opt.disabled = true;
            }
        });
        if (teks.length) {
            hintSlot.textContent = 'Slot penuh: ' + teks.join(', ') + '. Pilih jam lain.';
        } else if (tgl.value) {
            hintSlot.textContent = 'Semua slot masih tersedia untuk tanggal ini.';
        } else {
            hintSlot.textContent = 'Pilih tanggal untuk melihat ketersediaan slot.';
        }
        if (jam.selectedOptions[0] && jam.selectedOptions[0].disabled) {
            jam.value = '';
        }
    }

    function perbaruiRingkasan() {
        var s = info[svc.value], p = infoPaket[pkt.value];
        var n = Math.min(Math.max(parseInt(jumlah.value, 10) || 0, 1), 20);

        document.getElementById('ringkas-layanan').textContent = s ? s.nama + ' — ' + rp(s.harga) : '—';
        document.getElementById('ringkas-paket').textContent   = p ? p.nama + (p.tambahan ? ' +' + rp(p.tambahan) : '') : '—';
        document.getElementById('ringkas-jumlah').textContent  = n + ' pasang';

        var hintL = document.getElementById('hint-layanan');
        var hintP = document.getElementById('hint-paket');
        hintL.textContent = s && s.ket ? s.ket : '';
        hintP.textContent = p && p.ket ? p.ket + ' · estimasi ' + p.estimasi + ' hari' : '';

        var total = document.getElementById('ringkas-total');
        if (s && p && n >= 1) {
            total.textContent = rp((s.harga + p.tambahan) * n);
        } else {
            total.textContent = '—';
        }
    }

    tgl.addEventListener('change', function () {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'slot.php?tanggal=' + encodeURIComponent(tgl.value), true);
        xhr.onload = function () {
            try {
                slot = JSON.parse(xhr.responseText);
            } catch (err) { slot = {}; }
            perbaruiSlot();
        };
        xhr.send();
    });

    [svc, pkt, jumlah].forEach(function (el) {
        el.addEventListener('change', perbaruiRingkasan);
        el.addEventListener('input', perbaruiRingkasan);
    });
    tgl.addEventListener('change', perbaruiRingkasan);

    perbaruiSlot();
    perbaruiRingkasan();
})();
</script>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>