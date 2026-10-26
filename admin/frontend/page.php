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
<?php include $__fe . '/partials/tab_diskon.php'; ?>
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
    <script>
    // ===== Tombol lonceng (mandiri, tidak tergantung file JS lain) =====
    function alzzB64(b) {
        var pad = '='.repeat((4 - b.length % 4) % 4);
        var raw = atob((b + pad).replace(/-/g, '+').replace(/_/g, '/'));
        var a = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) a[i] = raw.charCodeAt(i);
        return a;
    }
    function alzzJson(r, nama) {
        if (!r.ok) throw new Error(nama + ' membalas kode ' + r.status + (r.status === 401 ? ' (login admin dulu)' : ''));
        return r.json();
    }
    window.alzzBell = function (btn) {
        try {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                alert('Browser ini belum mendukung notifikasi layar HP. Gunakan Chrome terbaru di Android.');
                return false;
            }
            if (Notification.permission === 'denied') {
                alert('Notifikasi diblokir. Ketuk ikon gembok di address bar > Izin/Notifikasi > Izinkan, lalu ketuk lonceng lagi.');
                return false;
            }
            Notification.requestPermission().then(function (p) {
                if (p !== 'granted') { alert('Izin notifikasi tidak diberikan.'); return; }
                return navigator.serviceWorker.register('sw.js')
                    .then(function () { return navigator.serviceWorker.ready; })
                    .then(function (reg) {
                        return fetch('push_api.php?action=key', { credentials: 'same-origin', cache: 'no-store' })
                            .then(function (r) { return alzzJson(r, 'push_api.php (key)'); })
                            .then(function (k) {
                                if (!k.key) throw new Error('Kunci VAPID gagal dibuat di server (cek openssl).');
                                return reg.pushManager.getSubscription().then(function (sub) {
                                    return sub ? sub.unsubscribe().catch(function () {}) : null;
                                }).then(function () {
                                    return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: alzzB64(k.key) });
                                });
                            });
                    })
                    .then(function (sub) {
                        return fetch('push_api.php?action=subscribe', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(sub.toJSON())
                        }).then(function (r) { return alzzJson(r, 'push_api.php (subscribe)'); });
                    })
                    .then(function (res) {
                        if (!res || !res.ok) throw new Error('Server menolak pendaftaran: ' + JSON.stringify(res));
                        window.alzzPushOn = true;
                        btn.textContent = '🔔 On'; btn.className = 'on';
                        alert('Notifikasi aktif. HP ini sekarang akan menerima notifikasi pesanan & user baru.');
                    });
            }).catch(function (e) {
                alert('Gagal mengaktifkan: ' + (e && e.message ? e.message : e));
            });
        } catch (e) {
            alert('Error: ' + e.message);
        }
        return false;
    };
    </script>
    <script src="admin/frontend/js/foto_produk.js?v=<?php echo filemtime($__fe . '/js/foto_produk.js'); ?>"></script>
    <script src="admin/frontend/js/notifikasi.js?v=<?php echo filemtime($__fe . '/js/notifikasi.js'); ?>"
            onerror="alzzJsErr('notifikasi.js gagal dimuat (file tidak ditemukan di server)')"></script>
    <script>
    // Tampilkan error JavaScript di layar (bantuan diagnosa)
    function alzzJsErr(m) {
        var d = document.getElementById('js-err');
        if (!d) {
            d = document.createElement('div'); d.id = 'js-err';
            d.style.cssText = 'position:fixed;left:8px;right:8px;bottom:70px;z-index:9999;background:#7f1d1d;color:#fff;font-size:11px;padding:8px;border-radius:8px';
            document.body.appendChild(d);
        }
        d.textContent = 'JS error: ' + m;
    }
    window.addEventListener('error', function (e) { alzzJsErr((e.message || 'tidak diketahui') + ' (' + (e.filename || '').split('/').pop() + ':' + (e.lineno || '?') + ')'); });
    </script>

</body>
</html>
