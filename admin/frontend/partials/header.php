<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
?>
        <!-- HEADER DASHBOARD -->
        <div class="section-header">
            <h2 class="catalog-title">
                <span class="catalog-text">ADMIN DASHBOARD</span>
            </h2>
            <div style="display: flex; gap: 8px;">
                <a href="index.php" class="admin-link">Beranda</a>
                <a href="logout.php" class="admin-link" style="background: #ef4444; border-color: #ef4444;">Logout</a>
            </div>
        </div>
