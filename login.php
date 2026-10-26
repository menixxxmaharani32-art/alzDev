<?php
require_once 'auth_guard.php';
require_once 'config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = db_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // 1. Cek ke tabel ADMIN terlebih dahulu
    $result_admin = db_query($conn, "SELECT * FROM admin WHERE username='$username'");
    if (db_num_rows($result_admin) === 1) {
        $row = db_fetch_assoc($result_admin);
        if ($password === $row['password']) {
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $row['username'];
            alzz_admin_remember($conn, $row['username']);
            session_write_close(); // pastikan sesi sudah tersimpan di DB sebelum redirect
            header("Location: admin_dashboard.php");
            exit;
        }
    }

    // 2. Jika bukan admin, cek ke tabel BUYERS (Pembeli)
    $result_buyer = db_query($conn, "SELECT * FROM buyers WHERE username='$username'");
    if (db_num_rows($result_buyer) === 1) {
        $row = db_fetch_assoc($result_buyer);
        if ($password === $row['password']) {
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            $_SESSION['buyer_logged'] = true;
            $_SESSION['buyer_user'] = $row['username'];
            session_write_close(); // pastikan sesi sudah tersimpan di DB sebelum redirect
            header("Location: index.php");
            exit;
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - ALZZ STORE</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <link rel="stylesheet" href="auth.css?v=<?php echo filemtime(__DIR__ . '/auth.css'); ?>">
</head>
<body>
    <div class="mobile-container auth-wrap">
        <div class="auth-card">

            <div class="auth-brand">ALZZ STORE</div>
            <div class="auth-title">Masuk ke akun Anda</div>

            <?php if($error): ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="login-input" required placeholder="Masukkan username" autocomplete="username">
                </div>
                <div class="form-group last">
                    <label>Password</label>
                    <input type="password" name="password" class="login-input" required placeholder="••••••••" autocomplete="current-password">
                </div>
                <button type="submit" class="auth-btn">Login</button>
            </form>

            <div class="auth-links">
                <a href="buyer_register.php" class="auth-link">Belum punya akun? Daftar</a>
            </div>

        </div>
    </div>
</body>
</html>
