<?php
// ===== PENJAGA SESI & LOGIN (dipakai semua halaman) =====
// Buyer: sesi hidup selama browser terbuka & aktif; lama tidak aktif => harus login ulang.
// Admin: login diingat (cookie token 90 hari) supaya HP tidak mudah logout dari dashboard.
if (!defined('SESSION_IDLE_LIMIT')) define('SESSION_IDLE_LIMIT', 1800); // 30 menit (khusus buyer)
if (!defined('ADMIN_REMEMBER_DAYS')) define('ADMIN_REMEMBER_DAYS', 90);
if (!defined('ADMIN_REMEMBER_COOKIE')) define('ADMIN_REMEMBER_COOKIE', 'alzz_admin_token');

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/db_pg.php'; // dimuat lebih dulu agar $_SERVER['HTTPS'] terdeteksi di belakang proxy Vercel
    // Server boleh menyimpan data sesi lebih lama (agar tidak cepat dibersihkan server)
    @ini_set('session.gc_maxlifetime', (string)(ADMIN_REMEMBER_DAYS * 86400));
    session_set_cookie_params([
        'lifetime' => ADMIN_REMEMBER_DAYS * 86400, // cookie sesi bertahan lama, tidak hilang saat browser ditutup
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    db_register_session_handler(); // sesi login disimpan di database
    session_start();
}

// ---------- Fungsi token "ingat saya" untuk admin ----------
function alzz_cookie_opts($expires) {
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function alzz_token_table($conn) {
    // Tabel admin_tokens sudah dibuat lewat SQL di Supabase
    return;
}

// Dipanggil dari login.php setelah admin berhasil login
function alzz_admin_remember($conn, $username) {
    alzz_token_table($conn);
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $uname = db_real_escape_string($conn, $username);
    $exp   = time() + ADMIN_REMEMBER_DAYS * 86400;
    db_query($conn, "DELETE FROM admin_tokens WHERE expires_at < (NOW() AT TIME ZONE 'Asia/Jakarta')");
    db_query($conn, "INSERT INTO admin_tokens (username, token_hash, expires_at) VALUES ('$uname', '$hash', '" . date('Y-m-d H:i:s', $exp) . "')");
    setcookie(ADMIN_REMEMBER_COOKIE, $token, alzz_cookie_opts($exp));
}

// Dipanggil dari logout.php
function alzz_admin_forget($conn) {
    if (!empty($_COOKIE[ADMIN_REMEMBER_COOKIE])) {
        alzz_token_table($conn);
        $hash = hash('sha256', $_COOKIE[ADMIN_REMEMBER_COOKIE]);
        db_query($conn, "DELETE FROM admin_tokens WHERE token_hash = '$hash'");
    }
    setcookie(ADMIN_REMEMBER_COOKIE, '', alzz_cookie_opts(time() - 42000));
}

// ---------- Pulihkan login admin dari cookie token jika sesi hilang ----------
if (empty($_SESSION['admin_logged']) && !empty($_COOKIE[ADMIN_REMEMBER_COOKIE])
    && basename($_SERVER['SCRIPT_NAME']) !== 'logout.php') {
    require_once __DIR__ . '/config.php';
    alzz_token_table($conn);
    $hash = hash('sha256', $_COOKIE[ADMIN_REMEMBER_COOKIE]);
    $res  = db_query($conn, "SELECT username FROM admin_tokens WHERE token_hash = '$hash' AND expires_at > (NOW() AT TIME ZONE 'Asia/Jakarta') LIMIT 1");
    if ($res && ($tk = db_fetch_assoc($res))) {
        $u = db_real_escape_string($conn, $tk['username']);
        $cek = db_query($conn, "SELECT username FROM admin WHERE username = '$u' LIMIT 1");
        if ($cek && db_num_rows($cek) === 1) {
            session_regenerate_id(true);
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user']   = $tk['username'];
            $_SESSION['last_activity'] = time();
        }
    }
}

$__sudah_login = !empty($_SESSION['admin_logged']) || !empty($_SESSION['buyer_logged']);
if ($__sudah_login) {
    // Batas tidak aktif 30 menit HANYA untuk buyer; admin tidak dibatasi
    $__is_admin = !empty($_SESSION['admin_logged']);
    if (!$__is_admin && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_IDLE_LIMIT) {
        $_SESSION = [];
        session_destroy();
    } else {
        $_SESSION['last_activity'] = time();
    }
}
?>
