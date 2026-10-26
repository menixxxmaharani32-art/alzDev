<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
?>
    <!-- TOMBOL NAVIGASI BAWAH (BERBASIS URL PARAM) -->
    <div class="admin-nav-bar">
        <a href="admin_dashboard.php?tab=pendapatan" class="admin-nav-link <?php echo ($active_tab == 'pendapatan') ? 'active-tab' : ''; ?>">Pendapatan</a>
        <a href="admin_dashboard.php?tab=situs" class="admin-nav-link <?php echo ($active_tab == 'situs') ? 'active-tab' : ''; ?>">Situs & Pesanan</a>
        <a href="admin_dashboard.php?tab=pembayaran" class="admin-nav-link <?php echo ($active_tab == 'pembayaran') ? 'active-tab' : ''; ?>">Pembayaran</a>
        <a href="admin_dashboard.php?tab=produk" class="admin-nav-link <?php echo ($active_tab == 'produk') ? 'active-tab' : ''; ?>">Produk</a>
        <a href="admin_dashboard.php?tab=diskon" class="admin-nav-link <?php echo ($active_tab == 'diskon') ? 'active-tab' : ''; ?>">Diskon</a>
        <a href="admin_dashboard.php?tab=users" class="admin-nav-link <?php echo ($active_tab == 'users') ? 'active-tab' : ''; ?>">Daftar User</a>
    </div>
