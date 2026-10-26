<?php
// Prevent caching agar browser tidak menyimpan sesi lama
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'auth_guard.php';
require_once 'config.php';
require_once 'diskon_lib.php';

// 1. CEK APAKAH AKUN BUYER SUDAH DIHAPUS DARI DATABASE
if (isset($_SESSION['buyer_logged']) && isset($_SESSION['buyer_user'])) {
    $current_buyer_user = db_real_escape_string($conn, $_SESSION['buyer_user']);
    $check_buyer_query = db_query($conn, "SELECT * FROM buyers WHERE username = '$current_buyer_user'");
    
    if (db_num_rows($check_buyer_query) === 0) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// 2. WAJIB LOGIN: Jika belum login sebagai Admin maupun Buyer, paksa ke login.php
if (!isset($_SESSION['admin_logged']) && !isset($_SESSION['buyer_logged'])) {
    header("Location: login.php");
    exit;
}

// Notifikasi pesan admin untuk pembeli (angka di tombol Riwayat)
require_once 'notif_buyer.php';
$notif_count = 0;
if (isset($_SESSION['buyer_logged']) && isset($_SESSION['buyer_user'])) {
    $notif_count = alzz_buyer_notif_count($conn, $_SESSION['buyer_user']);
    if (isset($_GET['notif_count'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['count' => $notif_count]);
        exit;
    }
}

// Error dicatat ke log Vercel, tidak ditampilkan ke pengunjung (diatur di config.php)

// Ambil data pengaturan situs dari tabel admin
$admin_query = db_query($conn, "SELECT * FROM admin ORDER BY id LIMIT 1");
$admin_data = ($admin_query && db_num_rows($admin_query) > 0) ? db_fetch_assoc($admin_query) : [];

$banner_url = !empty($admin_data['banner_url']) ? $admin_data['banner_url'] : '';
$music_url  = $admin_data['music_url'] ?? '';

// Ambil daftar produk dari database
$produk_result = db_query($conn, "SELECT * FROM produk ORDER BY id DESC");

// Produk yang sedang diskon otomatis tampil paling atas; urutan lainnya tetap (terbaru dulu)
$produk_list = [];
if ($produk_result) {
    while ($__row = db_fetch_assoc($produk_result)) {
        $__row['_urut'] = count($produk_list);
        $produk_list[] = $__row;
    }
}
usort($produk_list, function ($x, $y) {
    $dx = alzz_harga_info($x)['aktif'] ? 0 : 1;
    $dy = alzz_harga_info($y)['aktif'] ? 0 : 1;
    return ($dx <=> $dy) ?: ($x['_urut'] <=> $y['_urut']);
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALZZ STORE - Beranda</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body>

    <div class="mobile-container">
        
        <!-- HEADER MENU & STATUS LOGIN -->
        <div class="section-header" style="margin-bottom: 14px;">
            <h3 class="catalog-title" style="font-size: 13px; margin: 0;">
                <span class="catalog-text">ALZZ STORE</span>
            </h3>
            
            <div style="display: flex; gap: 6px; align-items: center;">
                <?php if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true): ?>
                    <a href="admin_dashboard.php" class="admin-link">Dashboard</a>
                    <a href="logout.php" class="admin-link" style="background: rgba(239, 68, 68, 0.15); border-color: #ef4444; color: #ef4444;">Logout</a>
                <?php elseif (isset($_SESSION['buyer_logged']) && $_SESSION['buyer_logged'] === true): ?>
                    <span style="color: #34d399; font-size: 10px; font-weight: bold;"> <?php echo htmlspecialchars($_SESSION['buyer_user']); ?></span>
                    <a href="beli.php?id=0" class="admin-link" style="background: rgba(52, 211, 153, 0.15); border-color: #34d399; color: #34d399; font-weight: bold;">Riwayat<?php echo alzz_notif_badge($notif_count); ?></a>
                    <a href="logout.php" class="admin-link" style="background: rgba(239, 68, 68, 0.15); border-color: #ef4444; color: #ef4444;">Logout</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- BANNER UTAMA / HERO BANNER -->
        <?php if (!empty($banner_url)): ?>
            <div class="hero-banner" id="mainBanner">
                <div class="hero-photo-wrapper">
                    <img src="<?php echo htmlspecialchars($banner_url); ?>" alt="Banner Utama">
                </div>
                <div class="hero-content">
                    <h1>ALZZ DEVELOPMENT</h1>
                    <p>Pusat Layanan & Aplikasi Premium Terlengkap</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- MUSIK BACKGROUND (JIKA ADA) -->
        <?php if (!empty($music_url)): ?>
            <div class="music-player-card">
                <div class="music-info">
                    <span>🎵 Backsound Musik</span>
                </div>
                <audio autoplay loop controls>
                    <source src="<?php echo htmlspecialchars($music_url); ?>" type="audio/mpeg">
                </audio>
            </div>
        <?php endif; ?>

        <!-- HEADER KATALOG -->
        <div class="section-header">
            <h2 class="catalog-title">
                <span class="catalog-text">ALL APP PREMIUM</span>
            </h2>
            <a href="beli.php?id=0" class="admin-link">Cek Pesanan<?php echo alzz_notif_badge($notif_count); ?></a>
        </div>

        <!-- KATALOG PRODUK ELEGANT (GRID 3 KOTAK KE SAMPING) -->
        <div class="product-grid-compact">
            <?php if (!empty($produk_list)): ?>
                <?php foreach ($produk_list as $p): ?>
                    <div class="app-card-mini">
                        <div>
                            <div class="product-photo-box">
                                <img src="<?php echo htmlspecialchars($p['foto_produk']); ?>" alt="<?php echo htmlspecialchars($p['nama_app']); ?>">
                                <span class="stock-badge-overlay">Stok: <?php echo $p['stok']; ?></span>
                                <?php $hb = alzz_harga_info($p); if ($hb['aktif']): ?>
                                    <span style="position:absolute;top:4px;left:4px;background:#ef4444;color:#fff;font-size:9px;font-weight:bold;padding:2px 6px;border-radius:6px;z-index:2;"><?php echo htmlspecialchars($hb['label']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <h3><?php echo htmlspecialchars($p['nama_app']); ?></h3>
                            
                            <?php if (!empty($p['durasi']) || !empty($p['plan'])): ?>
                                <div class="product-duration">
                                    <?php echo htmlspecialchars($p['durasi'] ?? ''); ?> <?php echo !empty($p['plan']) ? '- ' . htmlspecialchars($p['plan']) : ''; ?>
                                </div>
                            <?php endif; ?>
                            
                            <p><?php echo htmlspecialchars($p['deskripsi'] ?? ''); ?></p>
                        </div>

                        <div>
                            <div class="card-footer">
                                <?php $hi = alzz_harga_info($p); ?>
                                <?php if ($hi['aktif']): ?>
                                    <div style="line-height:1.25;">
                                        <div style="color:#94a3b8;font-size:9px;text-decoration:line-through;"><?php echo alzz_rp($hi['harga_asli']); ?></div>
                                        <span class="price-tag"><?php echo alzz_rp($hi['harga_akhir']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="price-tag"><?php echo alzz_rp($hi['harga_asli']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($p['stok'] > 0): ?>
                                <a href="beli.php?id=<?php echo $p['id']; ?>" class="btn-buy-mini">Beli Sekarang</a>
                            <?php else: ?>
                                <button disabled class="btn-buy-mini" style="background: #334155; color: #94a3b8; cursor: not-allowed;">Habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: span 3; text-align: center; color: var(--text-muted); padding: 20px; font-size: 11px;">
                    Belum ada produk yang tersedia saat ini.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="script.js"></script>
    <script>window.ALZZ_MUSIC_URL = <?php echo json_encode($music_url); ?>;</script>
    <script src="music.js?v=<?php echo filemtime(__DIR__ . '/music.js'); ?>"></script>
    <?php if (isset($_SESSION['buyer_logged'])): ?>
    <script>
    // Perbarui angka notifikasi pesan admin secara otomatis
    (function () {
        function upd() {
            fetch('index.php?notif_count=1&_=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    document.querySelectorAll('.notif-count').forEach(function (el) {
                        if (d.count > 0) { el.textContent = d.count > 99 ? '99+' : d.count; el.style.display = 'inline-block'; }
                        else { el.style.display = 'none'; }
                    });
                }).catch(function () {});
        }
        setInterval(upd, 15000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) upd(); });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
