// ===== MUSIK BERLANJUT ANTAR HALAMAN (beranda, beli, riwayat) =====
// Posisi & status putar disimpan di HP, sehingga musik lanjut dari detik terakhir saat pindah halaman.
(function () {
    var url = window.ALZZ_MUSIC_URL;
    if (!url) return;
    var KEY = 'alzz_music_state';

    function load() { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
    function save(o) { try { localStorage.setItem(KEY, JSON.stringify(o)); } catch (e) {} }

    var audio = document.querySelector('.music-player-card audio');
    var floating = false;
    if (!audio) {
        // Halaman tanpa pemutar (beli/riwayat): buat pemutar tersembunyi + tombol kecil melayang
        floating = true;
        audio = document.createElement('audio');
        audio.src = url;
        audio.loop = true;
        audio.preload = 'auto';
        document.body.appendChild(audio);
    }
    audio.loop = true;

    var st = load();
    if (!st || st.url !== url) { st = { url: url, t: 0, playing: true, ts: Date.now() }; }
    var wantPlay = st.playing !== false;   // default: putar; jika pengguna pernah menjeda, tetap jeda

    // Lanjutkan dari posisi terakhir
    var resumed = false;
    function seekToSaved() {
        if (resumed) return;
        resumed = true;
        var t = st.t || 0;
        if (st.playing !== false) t += Math.max(0, (Date.now() - st.ts) / 1000);
        if (isFinite(audio.duration) && audio.duration > 0) t = t % audio.duration;
        try { audio.currentTime = t; } catch (e) {}
    }
    if (audio.readyState >= 1) seekToSaved(); else audio.addEventListener('loadedmetadata', seekToSaved);

    function persist() {
        save({ url: url, t: audio.currentTime || 0, playing: !audio.paused || (wantPlay && audio.paused && !userPaused), ts: Date.now() });
    }

    var userPaused = !wantPlay;
    audio.addEventListener('pause', function () {
        // pause karena pindah halaman bukan jeda dari pengguna; dibedakan lewat flag leaving
        if (!leaving) { userPaused = true; wantPlay = false; }
        persist();
    });
    audio.addEventListener('play', function () { userPaused = false; wantPlay = true; persist(); updateBtn(); });
    audio.addEventListener('pause', updateBtn);
    setInterval(function () { if (!audio.paused) persist(); }, 1000);

    var leaving = false;
    window.addEventListener('pagehide', function () { leaving = true; persist(); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) persist(); });

    function tryPlay() {
        if (userPaused || !audio.paused) return;
        var p = audio.play();
        if (p && p.catch) p.catch(function () { /* diblokir browser: tunggu sentuhan pertama */ });
    }
    tryPlay();
    // Jika autoplay diblokir, mulai pada sentuhan/klik pertama
    function firstTouch() {
        tryPlay();
        if (!audio.paused) {
            ['touchstart', 'click', 'keydown'].forEach(function (ev) { document.removeEventListener(ev, firstTouch, true); });
        }
    }
    ['touchstart', 'click', 'keydown'].forEach(function (ev) { document.addEventListener(ev, firstTouch, true); });

    // Tombol kecil melayang (hanya di halaman tanpa pemutar)
    var btn = null;
    function updateBtn() { if (btn) btn.textContent = audio.paused ? '🔇' : '🎵'; }
    if (floating) {
        btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Putar / jeda musik');
        btn.style.cssText = 'position:fixed;right:12px;bottom:14px;z-index:9998;width:40px;height:40px;border-radius:50%;' +
            'background:rgba(13,19,38,0.9);border:1px solid #38bdf8;color:#38bdf8;font-size:18px;cursor:pointer;' +
            'box-shadow:0 2px 10px rgba(0,0,0,0.5);';
        btn.onclick = function (e) {
            e.stopPropagation();
            if (audio.paused) { userPaused = false; wantPlay = true; audio.play().catch(function () {}); }
            else { userPaused = true; wantPlay = false; audio.pause(); }
            persist(); updateBtn();
        };
        document.body.appendChild(btn);
        updateBtn();
    }
})();
