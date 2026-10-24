<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
// BACKEND: mengambil data dari database untuk ditampilkan di dashboard
$admin_data = db_fetch_assoc(db_query($conn, "SELECT * FROM admin ORDER BY id LIMIT 1"));
$produk_result = db_query($conn, "SELECT * FROM produk ORDER BY id");
$metode_result = db_query($conn, "SELECT * FROM metode_pembayaran ORDER BY id");
$pesanan_result = db_query($conn, "SELECT p.*, pr.nama_app, pr.durasi, pr.plan FROM pesanan p JOIN produk pr ON p.produk_id = pr.id ORDER BY p.id DESC");
$buyers_result = db_query($conn, "SELECT * FROM buyers ORDER BY id DESC");
$__mo = db_fetch_assoc(db_query($conn, "SELECT COALESCE(MAX(id),0) AS m FROM pesanan"));
$__mu = db_fetch_assoc(db_query($conn, "SELECT COALESCE(MAX(id),0) AS m FROM buyers"));
$page_max_order = intval($__mo['m'] ?? 0);
$page_max_user  = intval($__mu['m'] ?? 0);

// Data pendapatan (harian, mingguan, bulanan)
require_once __DIR__ . '/pendapatan.php';
$pendapatan = alzz_pendapatan_data($conn);
?>
