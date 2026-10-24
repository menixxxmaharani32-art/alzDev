<?php
// ============================================================================
// KONFIGURASI - koneksi ke Supabase (PostgreSQL)
// Tidak ada password di file ini. Semua rahasia dibaca dari Environment Variables
// (diisi di dashboard Vercel): DATABASE_URL, SUPABASE_URL, SUPABASE_SERVICE_KEY
// ============================================================================
require_once __DIR__ . '/db_pg.php';

// Sesi login disimpan di database (server Vercel tidak menyimpan file)
db_register_session_handler();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error dicatat ke log Vercel (tidak ditampilkan ke pengunjung, karena bisa merusak redirect & membocorkan info)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$conn = db_connect();

if (!$conn) {
    http_response_code(500);
    die("Koneksi DB gagal. Periksa DATABASE_URL di pengaturan Vercel.");
}
?>
