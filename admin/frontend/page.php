<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
// FRONTEND: kerangka halaman dashboard admin (hanya menyusun tampilan)
$__fe = ALZZ_ADMIN_DIR . '/frontend';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ALZZ Development</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(ALZZ_ROOT . '/style.css'); ?>">
    <link rel="stylesheet" href="admin/frontend/css/admin.css?v=<?php echo filemtime($__fe . '/css/admin.css'); ?>">
    <link rel="stylesheet" href="admin/frontend/css/pendapatan.css?v=<?php echo filemtime($__fe . '/css/pendapatan.css'); ?>">
</head>
<body>
    <div class="mobile-container">

<?php include $__fe . '/partials/header.php'; ?>

<?php include $__fe . '/partials/tab_pendapatan.php'; ?>
<?php include $__fe . '/partials/tab_situs.php'; ?>
<?php include $__fe . '/partials/tab_pembayaran.php'; ?>
<?php include $__fe . '/partials/tab_produk.php'; ?>
<?php include $__fe . '/partials/tab_users.php'; ?>

    </div>

<?php include $__fe . '/partials/nav.php'; ?>

    <div id="toast-wrap"></div>

    <script>
    // Data dari server untuk script notifikasi
    window.ALZZ_ADMIN = {
        activeTab: <?php echo json_encode($active_tab); ?>,
        maxOrder: <?php echo (int)$page_max_order; ?>,
        maxUser: <?php echo (int)$page_max_user; ?>
    };
    </script>
    <script src="admin/frontend/js/foto_produk.js?v=<?php echo filemtime($__fe . '/js/foto_produk.js'); ?>"></script>
    <script src="admin/frontend/js/notifikasi.js?v=<?php echo filemtime($__fe . '/js/notifikasi.js'); ?>"></script>

</body>
</html>
