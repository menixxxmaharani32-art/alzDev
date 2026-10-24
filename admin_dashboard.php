<?php
// ============================================================================
// ADMIN DASHBOARD - pintu masuk (nama file tetap sama)
// File ini sekarang hanya mengatur urutan kerja. Isinya dibagi ke folder admin/:
//   admin/backend/   -> logika & database (proses simpan, hapus, hitung pendapatan)
//   admin/frontend/  -> tampilan (halaman, CSS, JavaScript)
// Penjelasan lengkap: admin/PENJELASAN.md
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

// Tanda bahwa file di folder admin/ dibuka lewat halaman ini (bukan langsung dari browser)
define('ALZZ_ADMIN', true);
define('ALZZ_ROOT', __DIR__);
define('ALZZ_ADMIN_DIR', __DIR__ . '/admin');

// ---------- BACKEND ----------
require ALZZ_ADMIN_DIR . '/backend/notif.php';      // jawaban notifikasi (JSON), berhenti di sini bila ?notif
$active_tab = $_GET['tab'] ?? 'situs';               // tab aktif berdasarkan URL (?tab=...)
require ALZZ_ADMIN_DIR . '/backend/actions.php';     // proses simpan / ubah / hapus
require ALZZ_ADMIN_DIR . '/backend/data.php';        // ambil data untuk tampilan (termasuk pendapatan)

// ---------- FRONTEND ----------
require ALZZ_ADMIN_DIR . '/frontend/page.php';
