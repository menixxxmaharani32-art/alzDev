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


// ===== VISUALIZER LINGKARAN (bergerak mengikuti beat musik) — hanya di beranda, tanpa logo =====
(function () {
    var audio = document.querySelector('.music-player-card audio');
    if (!audio || !window.ALZZ_MUSIC_URL) return;
    var card = audio.parentNode;

    // ---- gaya & elemen ----
    var css = document.createElement('style');
    css.textContent =
        '.alzz-viz{display:flex;flex-direction:column;align-items:center;margin:2px 0 10px;position:relative}' +
        '.alzz-viz canvas{width:100%;max-width:270px;aspect-ratio:1/1;display:block}' +
        '.alzz-viz-time{margin-top:-6px;font:800 22px/1 system-ui,-apple-system,"Segoe UI",sans-serif;letter-spacing:2px;' +
        'color:#f3f4f6;text-shadow:0 0 8px rgba(255,255,255,.35),0 2px 3px rgba(0,0,0,.8)}';
    document.head.appendChild(css);

    var wrap = document.createElement('div');
    wrap.className = 'alzz-viz';
    var cv = document.createElement('canvas');
    var timeEl = document.createElement('div');
    timeEl.className = 'alzz-viz-time';
    timeEl.textContent = '0:00';
    wrap.appendChild(cv); wrap.appendChild(timeEl);
    card.insertBefore(wrap, audio);
    var ctx = cv.getContext('2d');

    var S = 270, dpr = Math.min(window.devicePixelRatio || 1, 2);
    function resize() {
        S = Math.round(cv.getBoundingClientRect().width) || 270;
        cv.width = S * dpr; cv.height = S * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize);

    // ---- analisis audio (Web Audio). Jika gagal/CORS ditolak -> animasi beat tiruan ----
    var analyser = null, freq = null, simulated = false, actx = null, wired = false;
    function wire() {
        if (wired || simulated) return;
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) { simulated = true; return; }
            actx = actx || new AC();
            var src = actx.createMediaElementSource(audio);
            analyser = actx.createAnalyser();
            analyser.fftSize = 256;
            analyser.smoothingTimeConstant = 0.72;
            src.connect(analyser);
            analyser.connect(actx.destination);
            freq = new Uint8Array(analyser.frequencyBinCount);
            wired = true;
        } catch (e) { simulated = true; analyser = null; }
    }
    function resumeCtx() { if (actx && actx.state === 'suspended') actx.resume().catch(function () {}); }
    audio.addEventListener('play', function () { wire(); resumeCtx(); start(); });
    document.addEventListener('touchstart', resumeCtx, true);
    document.addEventListener('click', resumeCtx, true);

    // Jika file tidak boleh dibaca lintas domain (CORS), putar biasa tanpa analisis
    var srcEl = audio.querySelector('source');
    var retried = false;
    function noCors() {
        if (retried || wired) return;
        retried = true; simulated = true;
        audio.removeAttribute('crossorigin');
        var wasPlaying = !audio.paused;
        audio.load();
        if (wasPlaying || audio.autoplay) { var p = audio.play(); if (p && p.catch) p.catch(function () {}); }
    }
    if (srcEl) srcEl.addEventListener('error', noCors);
    audio.addEventListener('error', noCors);

    // ---- data pembantu ----
    var N = 120;                       // jumlah titik di pinggiran
    var amp = new Array(N), tgt = new Array(N);
    for (var i = 0; i < N; i++) { amp[i] = 0; tgt[i] = 0; }
    var bass = 0, bassSm = 0, simT = 0;

    var bubbles = [];
    for (var b = 0; b < 16; b++) bubbles.push(newBubble(true));
    function newBubble(init) {
        var r = Math.random();
        return {
            x: Math.random(), y: init ? Math.random() : 1.05,
            r: 1.5 + Math.random() * 4.5, vy: 0.0006 + Math.random() * 0.0016,
            dx: (Math.random() - 0.5) * 0.0006, ph: Math.random() * 6.28,
            col: r < 0.12 ? '120,225,170' : (r < 0.2 ? '255,150,170' : '235,235,240')
        };
    }

    function readSpectrum(t) {
        // mengisi tgt[] (0..1) per titik, bass (0..1)
        var bins = 56, vals = new Array(bins), k;
        if (analyser && !simulated) {
            analyser.getByteFrequencyData(freq);
            for (k = 0; k < bins; k++) vals[k] = freq[k] / 255;
            var s = 0; for (k = 0; k < 6; k++) s += freq[k]; bass = Math.min(1, (s / 6) / 255 * 1.25);
        } else {
            // beat tiruan ~ 128 BPM
            simT += 0.016;
            var beat = Math.pow(Math.max(0, Math.sin(simT * 13.4)), 6);
            var off = Math.pow(Math.max(0, Math.sin(simT * 6.7 + 1)), 8) * 0.5;
            bass = Math.min(1, beat * 0.9 + off * 0.3);
            for (k = 0; k < bins; k++) {
                var fall = 1 - k / bins;
                vals[k] = Math.min(1, (beat * 0.85 + off * 0.6) * fall * (0.55 + 0.45 * Math.abs(Math.sin(simT * 3 + k * 1.7))));
            }
        }
        // titik di pinggiran: bass di bawah & samping, treble ke atas (simetris kiri-kanan)
        for (var j = 0; j < N; j++) {
            var th = (j / N) * Math.PI * 2;                       // 0 = kanan, PI/2 = bawah
            var d = Math.abs(Math.atan2(Math.sin(th - Math.PI / 2), Math.cos(th - Math.PI / 2))) / Math.PI; // 0 bawah .. 1 atas
            var idx = Math.min(bins - 1, Math.floor(Math.pow(d, 1.15) * bins));
            var v = vals[idx];
            tgt[j] = Math.min(1, Math.pow(v, 1.5) * 1.2);
        }
    }

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // ---- gambar ----
    var raf = 0, running = false, lastTime = '';
    function frame(t) {
        raf = 0;
        var playing = !audio.paused && !audio.ended;
        if (playing) readSpectrum(t); else { for (var q = 0; q < N; q++) tgt[q] = 0; bass = 0; }
        bassSm += (bass - bassSm) * 0.35;

        // naik cepat, turun pelan
        for (var i = 0; i < N; i++) {
            amp[i] += (tgt[i] - amp[i]) * (tgt[i] > amp[i] ? 0.55 : 0.16);
        }
        // haluskan antar titik
        var sm = new Array(N);
        for (i = 0; i < N; i++) sm[i] = (amp[(i + N - 1) % N] + amp[i] * 2 + amp[(i + 1) % N]) / 4;

        ctx.clearRect(0, 0, S, S);
        var cx = S / 2, cy = S / 2, R = S * 0.30 * (1 + bassSm * 0.045);

        // gelembung
        for (var b = 0; b < bubbles.length; b++) {
            var bb = bubbles[b];
            bb.y -= bb.vy * (1 + bassSm * 2.2); bb.x += bb.dx + Math.sin(t / 900 + bb.ph) * 0.0004;
            if (bb.y < -0.05) bubbles[b] = bb = newBubble(false);
            var br = bb.r * (1 + bassSm * 0.5);
            ctx.beginPath(); ctx.arc(bb.x * S, bb.y * S, br, 0, 6.2832);
            ctx.lineWidth = 1.3; ctx.strokeStyle = 'rgba(' + bb.col + ',' + (0.35 + bassSm * 0.35) + ')'; ctx.stroke();
        }

        // pinggiran yang bergerak: pita putih tipis (kepingan) yang melebar mengikuti beat
        var ext = R * 0.5, base = R * 1.07, k, th, rr, outer = [], inner = [];
        for (k = 0; k < N; k++) {
            th = (k / N) * Math.PI * 2;
            rr = base + sm[k] * ext;
            outer.push([cx + Math.cos(th) * rr, cy + Math.sin(th) * rr]);
            rr = base + sm[k] * ext * 0.7 + 1.5;
            inner.push([cx + Math.cos(th) * rr, cy + Math.sin(th) * rr]);
        }
        function smoothPath(pts) {
            ctx.moveTo((pts[0][0] + pts[N - 1][0]) / 2, (pts[0][1] + pts[N - 1][1]) / 2);
            for (var p = 0; p < N; p++) {
                var a = pts[p], c = pts[(p + 1) % N];
                ctx.quadraticCurveTo(a[0], a[1], (a[0] + c[0]) / 2, (a[1] + c[1]) / 2);
            }
            ctx.closePath();
        }
        ctx.save();
        ctx.shadowColor = 'rgba(255,255,255,' + (0.3 + bassSm * 0.5) + ')';
        ctx.shadowBlur = 5 + bassSm * 12;
        // pita putih antara garis dalam & luar
        ctx.beginPath(); smoothPath(outer); smoothPath(inner);
        ctx.fillStyle = 'rgba(255,255,255,0.95)'; ctx.fill('evenodd');
        // garis luar tipis
        ctx.beginPath(); smoothPath(outer);
        ctx.lineWidth = 1.6; ctx.strokeStyle = 'rgba(255,255,255,0.95)'; ctx.stroke();
        ctx.restore();

        // isi hitam tengah (kosong, tanpa logo)
        var cg = ctx.createRadialGradient(cx, cy, R * 0.1, cx, cy, R);
        cg.addColorStop(0, '#050505'); cg.addColorStop(1, '#000');
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832); ctx.fillStyle = cg; ctx.fill();

        // cincin perak utama
        var gr = ctx.createLinearGradient(cx - R, cy - R, cx + R, cy + R);
        gr.addColorStop(0, '#ffffff'); gr.addColorStop(0.25, '#9ca3af'); gr.addColorStop(0.5, '#f9fafb');
        gr.addColorStop(0.75, '#6b7280'); gr.addColorStop(1, '#ffffff');
        ctx.save();
        ctx.shadowColor = 'rgba(255,255,255,0.6)'; ctx.shadowBlur = 4 + bassSm * 12;
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832);
        ctx.lineWidth = Math.max(3, R * 0.055); ctx.strokeStyle = gr; ctx.stroke();
        ctx.restore();
        ctx.beginPath(); ctx.arc(cx, cy, R * 0.925, 0, 6.2832);
        ctx.lineWidth = 1.2; ctx.strokeStyle = 'rgba(255,255,255,0.55)'; ctx.stroke();

        // waktu
        var tx = fmt(audio.currentTime);
        if (tx !== lastTime) { timeEl.textContent = tx; lastTime = tx; }

        // lanjut selama diputar, atau sampai gerakan reda setelah dijeda
        var still = true;
        for (i = 0; i < N; i++) { if (amp[i] > 0.004) { still = false; break; } }
        if (playing || !still) { raf = requestAnimationFrame(frame); } else { running = false; }
    }
    function start() { if (!running || !raf) { running = true; if (!raf) raf = requestAnimationFrame(frame); } }

    audio.addEventListener('pause', start);       // biar gerakan mereda halus
    audio.addEventListener('seeked', function () { lastTime = ''; start(); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) start(); });
    start();
})();
