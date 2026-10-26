<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
?>
        <!-- HEADER DASHBOARD -->
        <div class="section-header">
            <h2 class="catalog-title">
                <span class="catalog-text">ADMIN DASHBOARD</span>
            </h2>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button id="notif-bell" type="button" onclick="return window.alzzBell ? alzzBell(this) : alert('Script notifikasi belum termuat, muat ulang halaman.')">🔔</button>
                <a href="index.php" class="admin-link">Beranda</a>
                <a href="logout.php" class="admin-link" style="background: #ef4444; border-color: #ef4444;">Logout</a>
            </div>
        </div>

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

    <script>
    // ===== Saat halaman dibuka: cek langganan, pulihkan status "🔔 On" & sinkronkan ke server =====
    (function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        function paintOn() {
            var b = document.getElementById('notif-bell');
            if (b) { b.textContent = '🔔 On'; b.className = 'on'; }
            window.alzzPushOn = true;
        }
        navigator.serviceWorker.register('sw.js')
            .then(function () { return navigator.serviceWorker.ready; })
            .then(function (reg) {
                return reg.pushManager.getSubscription().then(function (sub) {
                    if (sub) return sub;
                    // Langganan hilang (misal data browser dibersihkan): buat ulang diam-diam
                    return fetch('push_api.php?action=key', { credentials: 'same-origin', cache: 'no-store' })
                        .then(function (r) { return alzzJson(r, 'push_api.php (key)'); })
                        .then(function (k) {
                            if (!k.key) throw new Error('no key');
                            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: alzzB64(k.key) });
                        });
                });
            })
            .then(function (sub) {
                return fetch('push_api.php?action=subscribe', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(sub.toJSON())
                }).then(function (r) { return r.ok ? r.json() : null; });
            })
            .then(function (res) { if (res && res.ok) paintOn(); })
            .catch(function () { /* diam: tombol tetap 🔔 biasa, bisa diketuk manual */ });
    })();
    </script>
