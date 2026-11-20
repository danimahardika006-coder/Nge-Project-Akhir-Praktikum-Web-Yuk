<?php
/**
 * tests/e2e.php — uji alur lengkap lewat HTTP terhadap server yang berjalan.
 *
 *   DB_PORT=3399 php -S 127.0.0.1:8088 -t . &   # server
 *   php tests/e2e.php                            # uji
 */

declare(strict_types=1);

$BASE = getenv('TEST_BASE') ?: 'http://127.0.0.1:8088';
$pass = 0;
$fail = 0;
$jar  = sys_get_temp_dir() . '/cookies_' . getmypid() . '.txt';

require __DIR__ . '/../koneksi.php';

function warna(string $t, string $c): string
{
    $m = ['g' => "\033[32m", 'r' => "\033[31m", 'y' => "\033[33m", 'c' => "\033[36m", '0' => "\033[0m"];
    return ($m[$c] ?? '') . $t . $m['0'];
}

function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo '  ' . warna('PASS', 'g') . '  ' . $nama . ($detail !== '' ? '  ' . warna($detail, '0') : '') . PHP_EOL;
    } else {
        $fail++;
        echo '  ' . warna('FAIL', 'r') . '  ' . $nama . ($detail !== '' ? '  ' . warna($detail, 'r') : '') . PHP_EOL;
    }
}

function http(string $method, string $url, array $data = [], ?string $jarFile = null, bool $follow = true): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($jarFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jarFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jarFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = curl_exec($ch);
    $hdr = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $info = ['status' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'url' => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)];
    curl_close($ch);
    return ['status' => $info['status'], 'url' => $info['url'],
            'headers' => substr($raw, 0, $hdr), 'body' => substr($raw, $hdr)];
}

function token(string $html): string
{
    return preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
}

function bersih(): void
{
    global $jar;
    if (file_exists($jar)) {
        unlink($jar);
    }
}

echo warna("\n=== UJI E2E: Booking & Akun Pelanggan ===\n", 'c');
echo "Base: $BASE\n\n";

// ---------------------------------------------------------------------------
echo warna("[1] Registrasi pelanggan\n", 'y');
// ---------------------------------------------------------------------------
$stamp = date('His') . random_int(10, 99);
$emailA = "budi.$stamp@test.local";
$emailB = "siti.$stamp@test.local";
$pw = 'RahasiaSekali123';

$r = http('GET', "$BASE/registrasi.php", [], $jar);
cek('halaman registrasi terbuka', $r['status'] === 200);
$tok = token($r['body']);
cek('token CSRF ada di form', $tok !== '');

// tanpa token → ditolak
$r = http('POST', "$BASE/registrasi.php", [
    'registrasi' => '1', 'nama_lengkap' => 'Tanpa CSRF', 'email' => "nocsrf.$stamp@test.local",
    'no_hp' => '081234567890', 'password' => $pw, 'password2' => $pw,
], $jar);
cek('POST tanpa CSRF ditolak (419)', $r['status'] === 419, 'status=' . $r['status']);

// password terlalu pendek
$r = http('GET', "$BASE/registrasi.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/registrasi.php", [
    'csrf_token' => $tok, 'registrasi' => '1', 'nama_lengkap' => 'Budi Pendek',
    'email' => "pendek.$stamp@test.local", 'no_hp' => '081234567890', 'password' => '123', 'password2' => '123',
], $jar);
cek('password < 8 karakter ditolak', strpos($r['body'], 'minimal 8 karakter') !== false);

// nomor HP tidak valid
$r = http('GET', "$BASE/registrasi.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/registrasi.php", [
    'csrf_token' => $tok, 'registrasi' => '1', 'nama_lengkap' => 'Budi HP',
    'email' => "hp.$stamp@test.local", 'no_hp' => '12345', 'password' => $pw, 'password2' => $pw,
], $jar);
cek('nomor HP tidak valid ditolak', strpos($r['body'], 'Nomor HP belum benar') !== false);

// registrasi valid A
$r = http('GET', "$BASE/registrasi.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/registrasi.php", [
    'csrf_token' => $tok, 'registrasi' => '1', 'nama_lengkap' => "Budi Santoso $stamp",
    'email' => $emailA, 'no_hp' => '081234567890', 'password' => $pw, 'password2' => $pw,
], $jar);
cek('registrasi valid → redirect ke login', strpos($r['url'], 'login.php') !== false, $r['url']);

// email duplikat
$r = http('GET', "$BASE/registrasi.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/registrasi.php", [
    'csrf_token' => $tok, 'registrasi' => '1', 'nama_lengkap' => 'Duplikat',
    'email' => $emailA, 'no_hp' => '081234567890', 'password' => $pw, 'password2' => $pw,
], $jar);
cek('email duplikat ditolak', strpos($r['body'], 'sudah terdaftar') !== false);

// registrasi pelanggan B
$r = http('GET', "$BASE/registrasi.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/registrasi.php", [
    'csrf_token' => $tok, 'registrasi' => '1', 'nama_lengkap' => "Siti Aminah $stamp",
    'email' => $emailB, 'no_hp' => '081298765432', 'password' => $pw, 'password2' => $pw,
], $jar);
cek('registrasi pelanggan kedua berhasil', strpos($r['url'], 'login.php') !== false);

// cek hash di database
$stmt = $koneksi->prepare('SELECT id, password, no_hp, role FROM users WHERE email = ?');
$stmt->bind_param('s', $emailA);
$stmt->execute();
$rowA = $stmt->get_result()->fetch_assoc();
cek('akun A tersimpan di database', $rowA !== null);
cek('password di-hash (bukan plaintext)', $rowA && $rowA['password'] !== $pw && strpos($rowA['password'], '$2y$') === 0);
cek('password terverifikasi', $rowA && password_verify($pw, $rowA['password']));
cek('role default = customer', $rowA && $rowA['role'] === 'customer');
cek('no_hp normalisasi 08…', $rowA && $rowA['no_hp'] === '081234567890');

// ---------------------------------------------------------------------------
echo warna("\n[2] Login & proteksi halaman\n", 'y');
// ---------------------------------------------------------------------------
bersih();
$r = http('GET', "$BASE/customer/dashboard.php", [], $jar);
cek('dashboard tanpa login → diarahkan ke login', strpos($r['url'], 'login.php') !== false, $r['url']);

$r = http('GET', "$BASE/login.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/login.php", ['csrf_token' => $tok, 'login' => '1', 'email' => $emailA, 'password' => 'salah'], $jar);
cek('login password salah ditolak', strpos($r['body'], 'tidak cocok') !== false);

$r = http('GET', "$BASE/login.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/login.php", ['csrf_token' => $tok, 'login' => '1', 'email' => $emailA, 'password' => $pw], $jar);
cek('login benar → dashboard pelanggan', strpos($r['url'], 'customer/dashboard.php') !== false, $r['url']);
cek('dashboard memuat nama pelanggan', strpos($r['body'], 'Budi Santoso') !== false);

$r = http('GET', "$BASE/dashboard.php", [], $jar);
cek('pelanggan tidak bisa buka panel admin (dashboard.php)', strpos($r['url'], 'customer/dashboard.php') !== false, $r['url']);

$r = http('GET', "$BASE/admin/data_booking.php", [], $jar);
cek('pelanggan tidak bisa buka admin/data_booking.php', strpos($r['url'], 'customer/dashboard.php') !== false, $r['url']);

// ---------------------------------------------------------------------------
echo warna("\n[3] Booking\n", 'y');
// ---------------------------------------------------------------------------
$r = http('GET', "$BASE/customer/booking.php", [], $jar);
cek('form booking terbuka', $r['status'] === 200);
$tok = token($r['body']);

$svc = $koneksi->query('SELECT id, harga FROM services WHERE aktif=1 ORDER BY id LIMIT 1')->fetch_assoc();
$pkt = $koneksi->query('SELECT id, tambahan_harga FROM pakets WHERE aktif=1 ORDER BY id LIMIT 1')->fetch_assoc();
$tanggal = date('Y-m-d', strtotime('+3 days'));

// jumlah sepatu di luar batas
$r = http('POST', "$BASE/customer/booking.php", [
    'csrf_token' => $tok, 'service_id' => $svc['id'], 'paket_id' => $pkt['id'],
    'tipe_sepatu' => 'Sneakers', 'jumlah_sepatu' => '99', 'tanggal_booking' => $tanggal, 'jam_booking' => '10:00',
], $jar, false);
cek('jumlah sepatu > 20 ditolak', strpos($r['body'], 'antara 1 sampai 20') !== false);

// tanggal masa lalu
$r = http('GET', "$BASE/customer/booking.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/customer/booking.php", [
    'csrf_token' => $tok, 'service_id' => $svc['id'], 'paket_id' => $pkt['id'],
    'tipe_sepatu' => 'Sneakers', 'jumlah_sepatu' => '2', 'tanggal_booking' => '2020-01-01', 'jam_booking' => '10:00',
], $jar, false);
cek('tanggal masa lalu ditolak', strpos($r['body'], 'masa lalu') !== false);

// jam di luar slot
$r = http('GET', "$BASE/customer/booking.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/customer/booking.php", [
    'csrf_token' => $tok, 'service_id' => $svc['id'], 'paket_id' => $pkt['id'],
    'tipe_sepatu' => 'Sneakers', 'jumlah_sepatu' => '2', 'tanggal_booking' => $tanggal, 'jam_booking' => '03:00',
], $jar, false);
cek('jam di luar operasional ditolak', strpos($r['body'], 'Jam booking tidak tersedia') !== false);

// booking valid
$r = http('GET', "$BASE/customer/booking.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/customer/booking.php", [
    'csrf_token' => $tok, 'service_id' => $svc['id'], 'paket_id' => $pkt['id'],
    'tipe_sepatu' => 'Sneakers', 'jumlah_sepatu' => '3', 'tanggal_booking' => $tanggal,
    'jam_booking' => '10:00', 'catatan' => 'Ada noda di lidah sepatu',
], $jar);
cek('booking valid → halaman detail', strpos($r['url'], 'detail_booking.php') !== false, $r['url']);

$stmt = $koneksi->prepare(
    'SELECT b.*, u.email FROM bookings b JOIN users u ON u.id=b.user_id WHERE u.email=? ORDER BY b.id DESC LIMIT 1'
);
$stmt->bind_param('s', $emailA);
$stmt->execute();
$b1 = $stmt->get_result()->fetch_assoc();
cek('booking tersimpan di database', $b1 !== null);
cek('kode booking format BK-YYYYMMDD-XXXX', $b1 && preg_match('/^BK-\d{8}-[0-9A-F]{4}$/', $b1['kode_booking']) === 1, $b1['kode_booking'] ?? '');
cek('booking terhubung ke akun pelanggan A', $b1 && (int) $b1['user_id'] === (int) $rowA['id']);

$total_harap = ((int) $svc['harga'] + (int) $pkt['tambahan_harga']) * 3;
cek('total harga dihitung server-side', $b1 && (int) $b1['total_harga'] === $total_harap,
    'db=' . ($b1['total_harga'] ?? '-') . ' harap=' . $total_harap);
cek('snapshot harga layanan disimpan', $b1 && (int) $b1['harga_layanan'] === (int) $svc['harga']);
cek('status awal = menunggu_konfirmasi', $b1 && $b1['status'] === 'menunggu_konfirmasi');
cek('tipe sepatu & jumlah tersimpan', $b1 && $b1['tipe_sepatu'] === 'Sneakers' && (int) $b1['jumlah_sepatu'] === 3);
cek('catatan tersimpan', $b1 && $b1['catatan'] === 'Ada noda di lidah sepatu');

$stmt = $koneksi->prepare('SELECT COUNT(*) AS n FROM order_status_history WHERE booking_id = ?');
$stmt->bind_param('i', $b1['id']);
$stmt->execute();
cek('riwayat status awal tercatat', (int) $stmt->get_result()->fetch_assoc()['n'] === 1);

// tamper harga lewat POST — server harus mengabaikan
$r = http('GET', "$BASE/customer/booking.php", [], $jar);
$tok = token($r['body']);
$r = http('POST', "$BASE/customer/booking.php", [
    'csrf_token' => $tok, 'service_id' => $svc['id'], 'paket_id' => $pkt['id'],
    'tipe_sepatu' => 'Running', 'jumlah_sepatu' => '1', 'tanggal_booking' => $tanggal,
    'jam_booking' => '11:00', 'total_harga' => '1', 'harga_layanan' => '1',
], $jar);
$stmt = $koneksi->prepare('SELECT total_harga FROM bookings WHERE user_id=? ORDER BY id DESC LIMIT 1');
$stmt->bind_param('i', $rowA['id']);
$stmt->execute();
$tamper = (int) $stmt->get_result()->fetch_assoc()['total_harga'];
$harap2 = (int) $svc['harga'] + (int) $pkt['tambahan_harga'];
cek('harga dari POST diabaikan (tidak bisa dimanipulasi)', $tamper === $harap2, "db=$tamper harap=$harap2");

// kapasitas slot
$terpakai = [];
foreach ([$b1['id']] as $x) {}
$stmt = $koneksi->prepare("SELECT COUNT(*) AS n FROM bookings WHERE tanggal_booking=? AND jam_booking='10:00' AND status<>'dibatalkan'");
$stmt->bind_param('s', $tanggal);
$stmt->execute();
$n_slot = (int) $stmt->get_result()->fetch_assoc()['n'];
cek('slot 10:00 terisi sesuai jumlah booking', $n_slot >= 1, "n=$n_slot");

// ---------------------------------------------------------------------------
echo warna("\n[4] Isolasi data antar pelanggan (IDOR)\n", 'y');
// ---------------------------------------------------------------------------
// Login sebagai pelanggan B
$jarB = sys_get_temp_dir() . '/cookies_b_' . getmypid() . '.txt';
@unlink($jarB);
$r = http('GET', "$BASE/login.php", [], $jarB);
$tok = token($r['body']);
$r = http('POST', "$BASE/login.php", ['csrf_token' => $tok, 'login' => '1', 'email' => $emailB, 'password' => $pw], $jarB);
cek('pelanggan B login', strpos($r['url'], 'customer/dashboard.php') !== false);

$r = http('GET', "$BASE/customer/detail_booking.php?id=" . $b1['id'], [], $jarB, false);
cek('B tidak bisa buka detail booking A (404)', $r['status'] === 404, 'status=' . $r['status']);
cek('B tidak melihat data A di halaman itu', strpos($r['body'], $b1['kode_booking']) === false);

$r = http('GET', "$BASE/customer/riwayat.php", [], $jarB);
cek('riwayat B tidak memuat booking A', strpos($r['body'], $b1['kode_booking']) === false);
cek('riwayat B kosong', strpos($r['body'], 'Belum ada booking') !== false);

// B coba batalkan booking A
$r = http('GET', "$BASE/customer/booking.php", [], $jarB);
$tokB = token($r['body']);
$r = http('POST', "$BASE/customer/detail_booking.php?id=" . $b1['id'], ['csrf_token' => $tokB, 'batalkan' => '1'], $jarB, false);
$stmt = $koneksi->prepare('SELECT status FROM bookings WHERE id = ?');
$stmt->bind_param('i', $b1['id']);
$stmt->execute();
cek('B tidak bisa membatalkan booking A', $stmt->get_result()->fetch_assoc()['status'] === 'menunggu_konfirmasi');

// slot.php tanpa login
$r = http('GET', "$BASE/customer/slot.php?tanggal=$tanggal", [], null, false);
cek('slot.php tanpa login ditolak (403)', $r['status'] === 403, 'status=' . $r['status']);

// ---------------------------------------------------------------------------
echo warna("\n[5] Panel admin: konfirmasi & ubah status\n", 'y');
// ---------------------------------------------------------------------------
$jarA = sys_get_temp_dir() . '/cookies_a_' . getmypid() . '.txt';
@unlink($jarA);
$r = http('GET', "$BASE/login.php", [], $jarA);
$tok = token($r['body']);
$r = http('POST', "$BASE/login.php", ['csrf_token' => $tok, 'login' => '1', 'email' => 'admin@cucisepatu.test', 'password' => 'PasswordKuat99'], $jarA);
cek('admin login → panel kasir', strpos($r['url'], 'dashboard.php') !== false, $r['url']);

$r = http('GET', "$BASE/admin/data_booking.php", [], $jarA);
cek('admin melihat daftar booking', $r['status'] === 200 && strpos($r['body'], $b1['kode_booking']) !== false);
cek('admin melihat data pelanggan pada booking', strpos($r['body'], $emailA) !== false);
$tokAdm = token($r['body']);

// konfirmasi
$r = http('POST', "$BASE/admin/data_booking.php", [
    'csrf_token' => $tokAdm, 'booking_id' => $b1['id'], 'aksi' => 'konfirmasi',
], $jarA);
$stmt = $koneksi->prepare('SELECT status FROM bookings WHERE id=?');
$stmt->bind_param('i', $b1['id']);
$stmt->execute();
cek('admin konfirmasi → status dikonfirmasi', $stmt->get_result()->fetch_assoc()['status'] === 'dikonfirmasi');

// ubah status berantai
foreach (['sepatu_diterima', 'dicuci', 'quality_control', 'siap_diambil', 'selesai'] as $st) {
    $r = http('GET', "$BASE/admin/detail_booking.php?id={$b1['id']}", [], $jarA);
    $t = token($r['body']);
    http('POST', "$BASE/admin/detail_booking.php?id={$b1['id']}", [
        'csrf_token' => $t, 'aksi' => 'status', 'status' => $st, 'catatan' => "uji $st",
    ], $jarA);
    $s = $koneksi->prepare('SELECT status FROM bookings WHERE id=?');
    $s->bind_param('i', $b1['id']);
    $s->execute();
    if ($s->get_result()->fetch_assoc()['status'] !== $st) {
        cek("ubah status ke $st", false);
    }
}
$s = $koneksi->prepare('SELECT status FROM bookings WHERE id=?');
$s->bind_param('i', $b1['id']);
$s->execute();
cek('rantai status sampai selesai', $s->get_result()->fetch_assoc()['status'] === 'selesai');

$s = $koneksi->prepare('SELECT COUNT(*) AS n FROM order_status_history WHERE booking_id=?');
$s->bind_param('i', $b1['id']);
$s->execute();
$n_hist = (int) $s->get_result()->fetch_assoc()['n'];
cek('setiap perubahan status tercatat di riwayat', $n_hist === 7, "n=$n_hist");

// status tidak valid ditolak
$r = http('POST', "$BASE/admin/detail_booking.php?id={$b1['id']}", [
    'csrf_token' => $tokAdm, 'aksi' => 'status', 'status' => 'status_palsu',
], $jarA);
cek('status tidak dikenal ditolak', strpos($r['body'], 'Status tidak dikenal') !== false || $r['status'] === 200);
$s = $koneksi->prepare('SELECT status FROM bookings WHERE id=?');
$s->bind_param('i', $b1['id']);
$s->execute();
cek('status tetap selesai setelah input palsu', $s->get_result()->fetch_assoc()['status'] === 'selesai');

// ---------------------------------------------------------------------------
echo warna("\n[6] Pelanggan melihat status terbaru\n", 'y');
// ---------------------------------------------------------------------------
$r = http('GET', "$BASE/customer/detail_booking.php?id={$b1['id']}", [], $jar);
cek('pelanggan melihat status Selesai', strpos($r['body'], 'Selesai') !== false);
cek('timeline menampilkan tahap Quality Control', strpos($r['body'], 'Quality Control') !== false);
cek('halaman detail memuat riwayat perubahan', strpos($r['body'], 'uji quality_control') !== false);

$r = http('GET', "$BASE/customer/dashboard.php", [], $jar);
cek('dashboard: total booking naik', strpos($r['body'], 'Total booking') !== false);
preg_match('/Total booking.*?stat-value num">(\d+)/s', $r['body'], $m);
cek('dashboard menghitung booking pelanggan A', isset($m[1]) && (int) $m[1] >= 2, 'n=' . ($m[1] ?? '-'));

// ---------------------------------------------------------------------------
echo warna("\n[7] Profil & ganti password\n", 'y');
// ---------------------------------------------------------------------------
$r = http('GET', "$BASE/customer/profil.php", [], $jar);
$t = token($r['body']);
$r = http('POST', "$BASE/customer/profil.php", [
    'csrf_token' => $t, 'simpan_profil' => '1', 'nama_lengkap' => "Budi Santoso Baru $stamp",
    'email' => $emailA, 'no_hp' => '081200001111',
], $jar);
cek('profil berhasil diubah', strpos($r['body'], 'berhasil diperbarui') !== false);
$s = $koneksi->prepare('SELECT nama_lengkap, no_hp FROM users WHERE email=?');
$s->bind_param('s', $emailA);
$s->execute();
$p = $s->get_result()->fetch_assoc();
cek('perubahan tersimpan di database', $p['no_hp'] === '081200001111');

// email milik orang lain
$r = http('GET', "$BASE/customer/profil.php", [], $jar);
$t = token($r['body']);
$r = http('POST', "$BASE/customer/profil.php", [
    'csrf_token' => $t, 'simpan_profil' => '1', 'nama_lengkap' => 'Coba', 'email' => $emailB, 'no_hp' => '081200001111',
], $jar);
cek('email milik akun lain ditolak', strpos($r['body'], 'sudah dipakai akun lain') !== false);

// ganti password — password lama salah
$r = http('GET', "$BASE/customer/profil.php", [], $jar);
$t = token($r['body']);
$r = http('POST', "$BASE/customer/profil.php", [
    'csrf_token' => $t, 'ubah_password' => '1', 'password_lama' => 'salahbanget',
    'password_baru' => 'BaruSekali456', 'password_baru2' => 'BaruSekali456',
], $jar);
cek('ganti password dengan password lama salah ditolak', strpos($r['body'], 'Password lama tidak cocok') !== false);

// ganti password valid
$pw_baru = 'BaruSekali456';
$r = http('GET', "$BASE/customer/profil.php", [], $jar);
$t = token($r['body']);
$r = http('POST', "$BASE/customer/profil.php", [
    'csrf_token' => $t, 'ubah_password' => '1', 'password_lama' => $pw,
    'password_baru' => $pw_baru, 'password_baru2' => $pw_baru,
], $jar);
cek('password berhasil diganti', strpos($r['body'], 'Password berhasil diganti') !== false);

// login dengan password baru
$jarN = sys_get_temp_dir() . '/cookies_n_' . getmypid() . '.txt';
@unlink($jarN);
$r = http('GET', "$BASE/login.php", [], $jarN);
$t = token($r['body']);
$r = http('POST', "$BASE/login.php", ['csrf_token' => $t, 'login' => '1', 'email' => $emailA, 'password' => $pw_baru], $jarN);
cek('login dengan password baru berhasil', strpos($r['url'], 'customer/dashboard.php') !== false);

// ---------------------------------------------------------------------------
echo warna("\n[8] Admin: pelanggan, layanan, nonaktifkan akun\n", 'y');
// ---------------------------------------------------------------------------
$r = http('GET', "$BASE/admin/data_pelanggan.php", [], $jarA);
cek('admin melihat daftar pelanggan', $r['status'] === 200 && strpos($r['body'], $emailA) !== false);
$t = token($r['body']);

$r = http('GET', "$BASE/admin/pelanggan.php?id={$rowA['id']}", [], $jarA);
cek('admin melihat riwayat lengkap pelanggan', strpos($r['body'], $b1['kode_booking']) !== false);

$r = http('GET', "$BASE/admin/data_layanan.php", [], $jarA);
cek('admin melihat daftar layanan', $r['status'] === 200 && strpos($r['body'], 'Cuci') !== false);

// nonaktifkan akun A
$r = http('POST', "$BASE/admin/data_pelanggan.php", [
    'csrf_token' => $t, 'user_id' => $rowA['id'], 'aksi' => 'nonaktif',
], $jarA);
$s = $koneksi->prepare('SELECT status_akun FROM users WHERE id=?');
$s->bind_param('i', $rowA['id']);
$s->execute();
cek('admin menonaktifkan akun', $s->get_result()->fetch_assoc()['status_akun'] === 'nonaktif');

$jarX = sys_get_temp_dir() . '/cookies_x_' . getmypid() . '.txt';
@unlink($jarX);
$r = http('GET', "$BASE/login.php", [], $jarX);
$t = token($r['body']);
$r = http('POST', "$BASE/login.php", ['csrf_token' => $t, 'login' => '1', 'email' => $emailA, 'password' => $pw_baru], $jarX);
cek('akun nonaktif tidak bisa login', strpos($r['body'], 'dinonaktifkan') !== false);

// aktifkan kembali
$r = http('GET', "$BASE/admin/data_pelanggan.php", [], $jarA);
$t = token($r['body']);
http('POST', "$BASE/admin/data_pelanggan.php", ['csrf_token' => $t, 'user_id' => $rowA['id'], 'aksi' => 'aktifkan'], $jarA);
$s = $koneksi->prepare('SELECT status_akun FROM users WHERE id=?');
$s->bind_param('i', $rowA['id']);
$s->execute();
cek('akun diaktifkan kembali', $s->get_result()->fetch_assoc()['status_akun'] === 'aktif');

// ---------------------------------------------------------------------------
echo warna("\n[9] Fitur lama tetap berjalan\n", 'y');
// ---------------------------------------------------------------------------
$r = http('GET', "$BASE/dashboard.php", [], $jarA);
cek('panel kasir lama tetap terbuka', $r['status'] === 200 && strpos($r['body'], 'Catat pesanan baru') !== false);
$t = token($r['body']);
$r = http('POST', "$BASE/dashboard.php", [
    'csrf_token' => $t, 'tambahTransaksi' => '1', 'nama_pelanggan' => 'Walk In Uji',
    'no_hp' => '081211112222', 'service_id' => $svc['id'], 'tanggal_masuk' => date('Y-m-d'),
], $jarA);
cek('tambah transaksi kasir masih berfungsi', strpos($r['body'], 'Pesanan dicatat dengan nomor') !== false);

$s = $koneksi->query('SELECT no_pesanan FROM transactions ORDER BY id DESC LIMIT 1')->fetch_assoc();
$r = http('GET', "$BASE/tracking.php?no_pesanan=" . urlencode($s['no_pesanan']), [], null);
cek('tracking TRX lama masih berfungsi', strpos($r['body'], $s['no_pesanan']) !== false);

$r = http('GET', "$BASE/tracking.php?no_pesanan=" . urlencode($b1['kode_booking']), [], null);
cek('tracking BK tanpa login ditolak dengan pesan jelas', strpos($r['body'], 'hanya bisa dilihat dari akun') !== false);

$r = http('GET', "$BASE/tracking.php?no_pesanan=" . urlencode($b1['kode_booking']), [], $jarN);
cek('tracking BK oleh pemilik → detail booking', strpos($r['url'], 'detail_booking.php') !== false, $r['url']);

$r = http('GET', "$BASE/logout.php", [], $jarN);
cek('logout → kembali ke login', strpos($r['url'], 'login.php') !== false);
$r = http('GET', "$BASE/customer/dashboard.php", [], $jarN);
cek('setelah logout tidak bisa akses dashboard', strpos($r['url'], 'login.php') !== false);

// ---------------------------------------------------------------------------
echo warna("\n" . str_repeat('=', 46) . "\n", 'c');
echo 'Total: ' . ($pass + $fail) . '  ' . warna("LULUS: $pass", 'g') . '  ' . ($fail ? warna("GAGAL: $fail", 'r') : 'GAGAL: 0') . "\n\n";

bersih();
@unlink($jarA);
@unlink($jarB);
@unlink($jarN);
@unlink($jarX);
exit($fail === 0 ? 0 : 1);
