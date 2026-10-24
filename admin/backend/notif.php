<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
// BACKEND: jawaban JSON untuk notifikasi (dipanggil otomatis oleh JavaScript dashboard)
// ===== ENDPOINT NOTIFIKASI (dipanggil otomatis oleh JavaScript dashboard) =====
if (isset($_GET['notif'])) {
    header('Content-Type: application/json; charset=utf-8');
    $so = intval($_GET['o'] ?? 0); // id pesanan terakhir yang sudah dilihat
    $su = intval($_GET['u'] ?? 0); // id user terakhir yang sudah dilihat
    $out = ['max_order'=>0,'max_user'=>0,'new_orders'=>0,'new_users'=>0,'pending'=>0,'stok_habis'=>0,
            'last_order'=>null,'last_user'=>null,'habis_nama'=>''];

    $r = db_query($conn, "SELECT MAX(id) AS m, COALESCE(SUM((id > $so)::int), 0) AS n, COALESCE(SUM((status = 'Pending')::int), 0) AS p FROM pesanan");
    if ($r && ($d = db_fetch_assoc($r))) {
        $out['max_order'] = intval($d['m']); $out['new_orders'] = intval($d['n']); $out['pending'] = intval($d['p']);
    }
    $r = db_query($conn, "SELECT p.id, p.username, p.nama, pr.nama_app FROM pesanan p LEFT JOIN produk pr ON p.produk_id = pr.id ORDER BY p.id DESC LIMIT 1");
    if ($r && ($d = db_fetch_assoc($r))) {
        $out['last_order'] = ['id'=>intval($d['id']), 'user'=>($d['username'] ?: ($d['nama'] ?: 'Guest')), 'produk'=>($d['nama_app'] ?? '')];
    }
    $r = db_query($conn, "SELECT MAX(id) AS m, COALESCE(SUM((id > $su)::int), 0) AS n FROM buyers");
    if ($r && ($d = db_fetch_assoc($r))) {
        $out['max_user'] = intval($d['m']); $out['new_users'] = intval($d['n']);
    }
    $r = db_query($conn, "SELECT username FROM buyers ORDER BY id DESC LIMIT 1");
    if ($r && ($d = db_fetch_assoc($r))) { $out['last_user'] = $d['username']; }
    $r = db_query($conn, "SELECT COUNT(*) AS n, STRING_AGG(nama_app, ', ') AS nm FROM produk WHERE stok <= 0");
    if ($r && ($d = db_fetch_assoc($r))) { $out['stok_habis'] = intval($d['n']); $out['habis_nama'] = (string)$d['nm']; }

    echo json_encode($out);
    exit;
}
?>
