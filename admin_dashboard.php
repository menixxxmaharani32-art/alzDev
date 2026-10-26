<?php
// ============================================================================
// ADMIN DASHBOARD - pintu masuk (nama file tetap sama)
// File ini hanya mengatur urutan kerja:
//   admin_backend.php -> logika & database (proses simpan, hapus, hitung pendapatan)
//   admin_page.php    -> tampilan (halaman, tab, navigasi)
//   admin.css / admin.js -> gaya & JavaScript dashboard
// ============================================================================

// Cegah halaman tersimpan di cache (tombol Back setelah logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Aktifkan pelacakan error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth_guard.php';
require_once 'config.php';

// Cek apakah admin sudah login
if (empty($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

// Tanda bahwa admin_backend.php & admin_page.php dibuka lewat halaman ini (bukan langsung dari browser)
define('ALZZ_ADMIN', true);
define('ALZZ_ROOT', __DIR__);

// ---------- BACKEND ----------
$active_tab = $_GET['tab'] ?? 'situs';               // tab aktif berdasarkan URL (?tab=...)
require __DIR__ . '/admin_backend.php';              // notifikasi (JSON, berhenti bila ?notif) -> proses simpan/hapus -> data tampilan

// ---------- FRONTEND ----------
require __DIR__ . '/admin_page.php';
