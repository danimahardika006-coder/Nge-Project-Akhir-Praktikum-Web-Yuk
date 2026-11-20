<?php
require_once dirname(__DIR__) . '/partials/init.php';
$user = butuh_customer();
$uid  = (int) $user['id'];

$error_profil = '';
$error_pw     = '';
$tab          = 'profil';

// ---------- Ubah profil ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_profil'])) {
    wajib_csrf();
    $nama  = trim($_POST['nama_lengkap'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $no_hp = trim($_POST['no_hp'] ?? '');

    if ($nama === '' || $email === '' || $no_hp === '') {
        $error_profil = 'Semua kolom wajib diisi.';
    } elseif (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $error_profil = 'Nama lengkap harus 3 sampai 100 karakter.';
    } elseif (!valid_email($email)) {
        $error_profil = 'Format email belum benar.';
    } elseif (!valid_hp($no_hp)) {
        $error_profil = 'Nomor HP belum benar. Gunakan format 08xxxxxxxxxx.';
    } else {
        $no_hp = normalisasi_hp($no_hp);

        // Email tidak boleh dipakai akun lain
        $cek = mysqli_prepare($koneksi, 'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        mysqli_stmt_bind_param($cek, 'si', $email, $uid);
        mysqli_stmt_execute($cek);
        $dipakai = mysqli_stmt_get_result($cek)->num_rows > 0;
        mysqli_stmt_close($cek);

        if ($dipakai) {
            $error_profil = 'Email itu sudah dipakai akun lain.';
        } else {
            $upd = mysqli_prepare(
                $koneksi,
                'UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, diperbarui_pada = NOW() WHERE id = ?'
            );
            mysqli_stmt_bind_param($upd, 'sssi', $nama, $email, $no_hp, $uid);
            if (mysqli_stmt_execute($upd)) {
                mysqli_stmt_close($upd);
                $_SESSION['nama'] = $nama;
                flash_set('Profil berhasil diperbarui.');
                header('Location: profil.php');
                exit();
            }
            mysqli_stmt_close($upd);
            $error_profil = 'Profil gagal disimpan. Coba lagi.';
        }
    }
    $tab = 'profil';
}

// ---------- Ubah password ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_password'])) {
    wajib_csrf();
    $tab    = 'password';
    $lama   = (string) ($_POST['password_lama'] ?? '');
    $baru   = (string) ($_POST['password_baru'] ?? '');
    $ulangi = (string) ($_POST['password_baru2'] ?? '');

    if ($lama === '' || $baru === '' || $ulangi === '') {
        $error_pw = 'Lengkapi semua kolom password.';
    } else {
        $stmt = mysqli_prepare($koneksi, 'SELECT password FROM users WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $hash = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['password'] ?? '';
        mysqli_stmt_close($stmt);

        if (!password_verify($lama, $hash)) {
            $error_pw = 'Password lama tidak cocok.';
        } elseif (strlen($baru) < 8) {
            $error_pw = 'Password baru minimal 8 karakter.';
        } elseif ($baru !== $ulangi) {
            $error_pw = 'Konfirmasi password baru belum sama.';
        } elseif ($baru === $lama) {
            $error_pw = 'Password baru harus berbeda dari password lama.';
        } else {
            $hash_baru = password_hash($baru, PASSWORD_DEFAULT);
            $upd = mysqli_prepare($koneksi, 'UPDATE users SET password = ?, diperbarui_pada = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($upd, 'si', $hash_baru, $uid);
            if (mysqli_stmt_execute($upd)) {
                mysqli_stmt_close($upd);
                // Sesi baru supaya session lama tidak ikut aktif
                session_regenerate_id(true);
                flash_set('Password berhasil diganti. Gunakan password baru saat masuk berikutnya.');
                header('Location: profil.php');
                exit();
            }
            mysqli_stmt_close($upd);
            $error_pw = 'Password gagal disimpan. Coba lagi.';
        }
    }
}

// Segarkan data profil setelah perubahan
$user = user_aktif($koneksi) ?? $user;

// ---------- Statistik kecil ----------
$stmt = mysqli_prepare($koneksi, 'SELECT COUNT(*) AS n FROM bookings WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$jml_booking = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
mysqli_stmt_close($stmt);

$judul = 'Profil';
$aktif = 'profil';
require dirname(__DIR__) . '/partials/header_customer.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Profil saya</h1>
        <p class="page-date">Kelola data akun dan keamanan password.</p>
    </div>
</div>

<div class="workspace">

    <section class="panel" aria-labelledby="judul-profil">
        <div class="panel-head">
            <h2 class="panel-title" id="judul-profil">Data pribadi</h2>
            <p class="panel-sub">Dipakai untuk keperluan konfirmasi booking.</p>
        </div>

        <form class="panel-body" method="POST" action="profil.php">
            <?php echo csrf_field(); ?>
            <?php if ($error_profil !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo e($error_profil); ?></div>
            <?php endif; ?>

            <div class="field">
                <label for="nama_lengkap">Nama lengkap</label>
                <input class="input" type="text" id="nama_lengkap" name="nama_lengkap"
                       value="<?php echo e($user['nama_lengkap']); ?>" required>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email"
                       value="<?php echo e($user['email']); ?>" required>
                <p class="field-hint">Email dipakai untuk masuk, jadi harus unik.</p>
            </div>

            <div class="field">
                <label for="no_hp">Nomor HP</label>
                <input class="input" type="tel" id="no_hp" name="no_hp" inputmode="tel"
                       value="<?php echo e($user['no_hp']); ?>" placeholder="08xxxxxxxxxx" required>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input class="input" type="text" id="username" value="<?php echo e($user['username']); ?>" disabled>
                <p class="field-hint">Username dibuat otomatis dan tidak dapat diubah.</p>
            </div>

            <button class="btn btn-primary" type="submit" name="simpan_profil" value="1">Simpan perubahan</button>
        </form>
    </section>

    <aside class="sticky">
        <section class="panel" aria-labelledby="judul-pw">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-pw">Ganti password</h2>
                <p class="panel-sub">Minimal 8 karakter.</p>
            </div>
            <form class="panel-body" method="POST" action="profil.php">
                <?php echo csrf_field(); ?>
                <?php if ($error_pw !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo e($error_pw); ?></div>
                <?php endif; ?>

                <div class="field">
                    <label for="password_lama">Password lama</label>
                    <input class="input" type="password" id="password_lama" name="password_lama"
                           autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="password_baru">Password baru</label>
                    <input class="input" type="password" id="password_baru" name="password_baru"
                           autocomplete="new-password" minlength="8" required>
                </div>
                <div class="field">
                    <label for="password_baru2">Ulangi password baru</label>
                    <input class="input" type="password" id="password_baru2" name="password_baru2"
                           autocomplete="new-password" minlength="8" required>
                </div>

                <button class="btn btn-outline btn-block" type="submit" name="ubah_password" value="1">Ganti password</button>
            </form>
        </section>

        <section class="panel" aria-labelledby="judul-akun" style="margin-top:var(--s5)">
            <div class="panel-head">
                <h2 class="panel-title" id="judul-akun">Ringkasan akun</h2>
            </div>
            <dl class="mini-details" style="padding:var(--s5)">
                <div><dt>Total booking</dt><dd class="num"><?php echo (int) $jml_booking; ?></dd></div>
                <div><dt>Bergabung</dt><dd><?php echo e(tanggal_panjang($user['dibuat_pada'])); ?></dd></div>
                <div><dt>Status akun</dt><dd><?php echo e(ucfirst($user['status_akun'])); ?></dd></div>
            </dl>
        </section>
    </aside>

</div>

<?php require dirname(__DIR__) . '/partials/footer_customer.php'; ?>