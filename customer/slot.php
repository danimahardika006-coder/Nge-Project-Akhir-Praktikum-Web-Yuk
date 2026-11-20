<?php
/**
 * slot.php — sumber slot terpakai per tanggal (dipakai JS halaman booking).
 * Output: { "09:00": 2, "10:00": 0, ... }
 */
require_once dirname(__DIR__) . '/partials/init.php';

if (!is_customer()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

$tanggal = $_GET['tanggal'] ?? '';
header('Content-Type: application/json; charset=utf-8');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    echo json_encode(new stdClass());
    exit();
}

// Tanggal masa lalu → semua slot dianggap kosong (form menolaknya saat submit).
if (strtotime($tanggal) < strtotime(date('Y-m-d'))) {
    echo json_encode(new stdClass());
    exit();
}

$out = new stdClass();
foreach (slot_terpakai($koneksi, $tanggal) as $jam => $n) {
    $out->{$jam} = $n;
}
echo json_encode($out);