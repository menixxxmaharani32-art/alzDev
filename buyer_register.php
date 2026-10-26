<?php
require_once 'config.php'; // config.php sudah memulai sesi
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = db_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // Cek apakah username sudah dipakai di tabel buyers
        $cek = db_query($conn, "SELECT * FROM buyers WHERE username='$username'");
        if (db_num_rows($cek) > 0) {
            $error = "Username sudah digunakan, silakan pilih yang lain!";
        } else {
            // Simpan data pembeli baru ke database
            $query = "INSERT INTO buyers (username, password) VALUES ('$username', '$password')";
            if (db_query($conn, $query)) {
                $success = "Pendaftaran berhasil! Silakan login.";
                // Kirim notifikasi push ke HP admin
                require_once 'push_lib.php';
                alzz_push_event($conn, 'user', '👤 User baru', $username . ' baru saja mendaftar.', 'admin_dashboard.php?tab=users');
            } else {
                $error = "Terjadi kesalahan pada server, coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Buyer - ALZZ Store</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <link rel="stylesheet" href="auth.css?v=<?php echo filemtime(__DIR__ . '/auth.css'); ?>">
</head>
<body>
    <div class="mobile-container auth-wrap">
        <div class="auth-card">

            <div class="auth-brand">ALZZ STORE</div>
            <div class="auth-title">Buat akun baru</div>

            <?php if($error): ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="auth-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="login-input" required placeholder="Buat username" autocomplete="username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="login-input" required placeholder="Buat password" autocomplete="new-password">
                </div>
                <div class="form-group last">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="confirm_password" class="login-input" required placeholder="Ulangi password" autocomplete="new-password">
                </div>
                <button type="submit" class="auth-btn">Daftar</button>
            </form>

            <div class="auth-links">
                <a href="login.php" class="auth-link">Sudah punya akun? Login</a>
            </div>

        </div>
    </div>
</body>
</html>
