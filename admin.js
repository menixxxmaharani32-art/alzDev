// ===== Dashboard admin: pilih gambar dari galeri (foto produk & QRIS) =====
    // Pratinjau & perkecil gambar dari galeri sebelum diunggah (supaya cepat & tidak gagal karena ukuran)
    function alzzPilihGambar(inputId, previewId, max, mutu, nama) {
        var inp = document.getElementById(inputId);
        var prev = document.getElementById(previewId);
        if (!inp) return;
        inp.addEventListener('change', function () {
            var f = inp.files && inp.files[0];
            if (!f) { prev.style.display = 'none'; return; }
            var url = URL.createObjectURL(f);
            prev.src = url; prev.style.display = 'block';
            if (!/^image\/(jpeg|png|webp)$/.test(f.type) || !window.DataTransfer) return;
            var img = new Image();
            img.onload = function () {
                try {
                    var w = img.naturalWidth, h = img.naturalHeight;
                    if (w <= max && h <= max && f.size < 1.5 * 1024 * 1024) return;
                    var r = Math.min(1, max / Math.max(w, h));
                    var c = document.createElement('canvas');
                    c.width = Math.round(w * r); c.height = Math.round(h * r);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    c.toBlob(function (b) {
                        if (!b) return;
                        var nf = new File([b], nama, { type: 'image/jpeg' });
                        var dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
                    }, 'image/jpeg', mutu);
                } catch (e) {}
            };
            img.src = url;
        });
    }
    alzzPilihGambar('fotoProdukInput', 'fotoProdukPreview', 1200, 0.85, 'produk.jpg');
    alzzPilihGambar('bannerInput', 'bannerPreview', 1600, 0.88, 'banner.jpg');
    alzzPilihGambar('qrisInput', 'qrisPreview', 1600, 0.92, 'qris.jpg'); // QRIS dibuat lebih tajam agar tetap terbaca scanner

// ===== Dashboard admin: notifikasi =====
    (function () {
        var CFG = window.ALZZ_ADMIN || {};
        var ACTIVE_TAB = CFG.activeTab || 'situs';
        var PAGE_MAX_ORDER = parseInt(CFG.maxOrder, 10) || 0;
        var PAGE_MAX_USER  = parseInt(CFG.maxUser, 10) || 0;
        var POLL_MS = 8000;

        function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
        function lsSet(k, v) { try { localStorage.setItem(k, String(v)); } catch (e) {} }

        // Penanda "terakhir dilihat" (disimpan di HP)
        var seenOrder = lsGet('alzz_seen_order'); var seenUser = lsGet('alzz_seen_user');
        seenOrder = (seenOrder === null) ? PAGE_MAX_ORDER : parseInt(seenOrder, 10);
        seenUser  = (seenUser  === null) ? PAGE_MAX_USER  : parseInt(seenUser, 10);
        if (ACTIVE_TAB === 'situs') seenOrder = PAGE_MAX_ORDER;   // sedang melihat daftar pesanan
        if (ACTIVE_TAB === 'users') seenUser  = PAGE_MAX_USER;    // sedang melihat daftar user
        lsSet('alzz_seen_order', seenOrder); lsSet('alzz_seen_user', seenUser);

        var notifiedOrder = null, notifiedUser = null, lastHabis = parseInt(lsGet('alzz_habis') || '0', 10);
        var firstPoll = true;
        var pushActive = false; // true jika HP ini sudah berlangganan push (notif layar HP)

        // ----- Badge di menu bawah -----
        function badgeFor(hrefPart) {
            var a = document.querySelector('.admin-nav-link[href*="' + hrefPart + '"]');
            if (!a) return null;
            var b = a.querySelector('.nav-badge');
            if (!b) { b = document.createElement('span'); b.className = 'nav-badge'; a.appendChild(b); }
            return b;
        }
        function setBadge(b, n) {
            if (!b) return;
            if (n > 0) { b.textContent = n > 99 ? '99+' : n; b.style.display = 'block'; }
            else { b.style.display = 'none'; }
        }

        // ----- Suara & getar -----
        var audioCtx = null;
        function unlockAudio() {
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (AC && !audioCtx) audioCtx = new AC();
                if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
            } catch (e) {}
        }
        document.addEventListener('touchstart', unlockAudio, { passive: true });
        document.addEventListener('click', unlockAudio);
        function beep() {
            try {
                if (!audioCtx) return;
                [880, 1175].forEach(function (f, i) {
                    var o = audioCtx.createOscillator(), g = audioCtx.createGain();
                    o.type = 'sine'; o.frequency.value = f;
                    o.connect(g); g.connect(audioCtx.destination);
                    var t = audioCtx.currentTime + i * 0.18;
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.16);
                    o.start(t); o.stop(t + 0.18);
                });
            } catch (e) {}
            try { if (navigator.vibrate) navigator.vibrate([200, 100, 200]); } catch (e) {}
        }

        // ----- Toast di dalam dashboard -----
        function toast(title, body, targetUrl, warn) {
            var wrap = document.getElementById('toast-wrap');
            var d = document.createElement('div');
            d.className = 'notif-toast' + (warn ? ' warn' : '');
            var st = document.createElement('strong'); st.textContent = title;
            var tx = document.createElement('div'); tx.textContent = body;
            var sm = document.createElement('small'); sm.textContent = 'Ketuk untuk membuka';
            d.appendChild(st); d.appendChild(tx); d.appendChild(sm);
            d.onclick = function () { window.location.href = targetUrl; };
            wrap.appendChild(d);
            setTimeout(function () { if (d.parentNode) d.parentNode.removeChild(d); }, 12000);
            beep();
            // Notifikasi sistem HP (jika sudah diizinkan)
            try {
                if (!pushActive && !window.alzzPushOn && 'Notification' in window && Notification.permission === 'granted') {
                    var n = new Notification(title, { body: body, tag: 'alzz-' + title });
                    n.onclick = function () { window.focus(); window.location.href = targetUrl; };
                }
            } catch (e) {}
        }

        // ----- Tombol lonceng: aktifkan notifikasi di layar HP (Web Push) -----
        function b64uToUint8(b64u) {
            var pad = '='.repeat((4 - b64u.length % 4) % 4);
            var raw = atob((b64u + pad).replace(/-/g, '+').replace(/_/g, '/'));
            var arr = new Uint8Array(raw.length);
            for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
            return arr;
        }
        var pushSupported = ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);

        function sendSub(sub, action) {
            return fetch('push_api.php?action=' + action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sub.toJSON ? sub.toJSON() : sub)
            }).then(function (r) { return r.json(); });
        }

        function sameKey(sub, keyBytes) {
            try {
                var cur = sub.options && sub.options.applicationServerKey;
                if (!cur) return false;
                var a = new Uint8Array(cur);
                if (a.length !== keyBytes.length) return false;
                for (var i = 0; i < a.length; i++) if (a[i] !== keyBytes[i]) return false;
                return true;
            } catch (e) { return false; }
        }

        // force = true: buang langganan lama lalu buat yang baru (mengatasi langganan kedaluwarsa)
        function enablePush(force) {
            return navigator.serviceWorker.register('sw.js').then(function () {
                return navigator.serviceWorker.ready;
            }).then(function (reg) {
                return fetch('push_api.php?action=key', { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (k) {
                        if (!k.key) throw new Error('Kunci VAPID gagal dibuat (cek openssl di hosting).');
                        var keyBytes = b64uToUint8(k.key);
                        function baru() { return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes }); }
                        return reg.pushManager.getSubscription().then(function (sub) {
                            if (!sub) return baru();
                            if (force || !sameKey(sub, keyBytes)) {
                                return sendSub(sub, 'unsubscribe').catch(function () {}).then(function () {
                                    return sub.unsubscribe();
                                }).then(baru);
                            }
                            return sub;
                        });
                    });
            }).then(function (sub) {
                return sendSub(sub, 'subscribe');
            });
        }

        (function addBell() {
            var holder = document.querySelector('.section-header > div');
            var btn = document.getElementById('notif-bell');
            var tombolBaru = false;
            if (!btn) {
                if (!holder) return;
                btn = document.createElement('button');
                btn.id = 'notif-bell'; btn.type = 'button';
                tombolBaru = true;
            }
            function paint() {
                var ok = pushActive;
                btn.className = ok ? 'on' : ''; btn.textContent = ok ? '🔔 On' : '🔔';
            }
            // Cek status langganan saat halaman dibuka
            if (pushSupported && Notification.permission === 'granted') {
                enablePush().then(function () { pushActive = true; paint(); }).catch(function () { paint(); });
            }
            btn.onclick = function () {
                if (window.alzzBell) { unlockAudio(); window.alzzBell(btn); return; }
                unlockAudio(); beep();
                if (!pushSupported) {
                    toast('Belum didukung', 'Browser ini tidak mendukung notifikasi layar HP. Gunakan Chrome terbaru (Android).', location.href, true);
                    return;
                }
                if (Notification.permission === 'denied') {
                    toast('Notifikasi diblokir', 'Buka pengaturan situs di Chrome > Notifikasi > Izinkan, lalu ketuk 🔔 lagi.', location.href, true);
                    return;
                }
                Notification.requestPermission().then(function (p) {
                    if (p !== 'granted') { paint(); return; }
                    return enablePush(true).then(function () {
                        pushActive = true; paint();
                        toast('Notifikasi layar HP aktif', 'Pesanan & user baru akan muncul di layar HP walau Chrome ditutup.', location.href);
                    });
                }).catch(function (err) {
                    paint();
                    toast('Gagal mengaktifkan', String(err && err.message ? err.message : err), location.href, true);
                });
            };
            paint();
            if (tombolBaru) holder.insertBefore(btn, holder.firstChild);
        })();

        // ----- Polling -----
        function poll() {
            var url = 'admin_dashboard.php?notif=1&o=' + seenOrder + '&u=' + seenUser + '&_=' + Date.now();
            fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var orderBadge = badgeFor('tab=situs'), userBadge = badgeFor('tab=users'), prodBadge = badgeFor('tab=produk');
                    setBadge(orderBadge, ACTIVE_TAB === 'situs' ? Math.max(0, d.max_order > PAGE_MAX_ORDER ? d.max_order - PAGE_MAX_ORDER : 0) : d.new_orders);
                    setBadge(userBadge,  ACTIVE_TAB === 'users' ? Math.max(0, d.max_user  > PAGE_MAX_USER  ? d.max_user  - PAGE_MAX_USER  : 0) : d.new_users);
                    setBadge(prodBadge, d.stok_habis);

                    var newOrderCount = (ACTIVE_TAB === 'situs') ? Math.max(0, d.max_order - PAGE_MAX_ORDER) : d.new_orders;
                    var newUserCount  = (ACTIVE_TAB === 'users') ? Math.max(0, d.max_user  - PAGE_MAX_USER)  : d.new_users;

                    if (notifiedOrder === null) { notifiedOrder = PAGE_MAX_ORDER; notifiedUser = PAGE_MAX_USER; }

                    // Pesanan baru
                    if (d.max_order > notifiedOrder) {
                        var lo = d.last_order || {};
                        var jml = d.max_order - notifiedOrder;
                        toast('🛒 Pesanan baru' + (jml > 1 ? ' (' + jml + ')' : ''),
                              (lo.user || 'Pembeli') + (lo.produk ? ' memesan ' + lo.produk : ' membuat pesanan') + '. Total pending: ' + d.pending,
                              'admin_dashboard.php?tab=situs');
                        notifiedOrder = d.max_order;
                    }
                    // User baru
                    if (d.max_user > notifiedUser) {
                        var ju = d.max_user - notifiedUser;
                        toast('👤 User baru' + (ju > 1 ? ' (' + ju + ')' : ''),
                              (d.last_user || 'Pembeli baru') + ' baru saja mendaftar.',
                              'admin_dashboard.php?tab=users');
                        notifiedUser = d.max_user;
                    }
                    // Stok habis (hanya kalau bertambah)
                    if (d.stok_habis > lastHabis) {
                        toast('⚠️ Stok habis', d.habis_nama || 'Ada produk yang stoknya 0.', 'admin_dashboard.php?tab=produk', true);
                    }
                    if (d.stok_habis !== lastHabis) { lastHabis = d.stok_habis; lsSet('alzz_habis', lastHabis); }

                    // Saat dashboard baru dibuka: beritahu jika ada yang belum dilihat
                    if (firstPoll) {
                        firstPoll = false;
                        if (ACTIVE_TAB !== 'situs' && d.new_orders > 0)
                            toast('🛒 ' + d.new_orders + ' pesanan belum dilihat', 'Sejak terakhir Anda membuka daftar pesanan.', 'admin_dashboard.php?tab=situs');
                        if (ACTIVE_TAB !== 'users' && d.new_users > 0)
                            toast('👤 ' + d.new_users + ' user baru belum dilihat', 'Sejak terakhir Anda membuka daftar user.', 'admin_dashboard.php?tab=users');
                    }
                })
                .catch(function () { /* abaikan error jaringan sementara */ });
        }

        poll();
        setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    })();
