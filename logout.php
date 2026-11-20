<?php
require_once __DIR__ . '/partials/init.php';

// Hapus seluruh data session lalu buat session baru (mencegah session fixation).
session_unset();
session_destroy();

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}

alihkan('login.php');