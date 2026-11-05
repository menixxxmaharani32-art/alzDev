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


// ===== VISUALIZER LINGKARAN KECIL = TOMBOL PLAY/PAUSE (bergerak mengikuti beat) — hanya di beranda, tanpa logo =====
(function () {
    var audio = document.querySelector('.music-player-card audio');
    if (!audio || !window.ALZZ_MUSIC_URL) return;
    var card = audio.parentNode;
    audio.removeAttribute('controls');                 // tombol bawaan diganti lingkaran

    var css = document.createElement('style');
    css.textContent =
        '.music-player-card{padding:8px 12px !important}' +
        '.music-player-card .music-info{display:none}' +
        '.music-player-card audio{display:none}' +
        '.alzz-viz{display:flex;align-items:center;gap:12px}' +
        '.alzz-viz canvas{width:84px;height:84px;flex:none;cursor:pointer;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '.alzz-viz-txt{display:flex;flex-direction:column;gap:4px;min-width:0}' +
        '.alzz-viz-title{font-size:11px;font-weight:700;color:#38bdf8}' +
        '.alzz-viz-time{font:800 18px/1 system-ui,-apple-system,"Segoe UI",sans-serif;letter-spacing:1.5px;color:#f3f4f6;' +
        'text-shadow:0 0 6px rgba(255,255,255,.3)}';
    document.head.appendChild(css);

    var wrap = document.createElement('div'); wrap.className = 'alzz-viz';
    var cv = document.createElement('canvas');
    cv.setAttribute('role', 'button'); cv.setAttribute('aria-label', 'Putar / jeda musik');
    var txt = document.createElement('div'); txt.className = 'alzz-viz-txt';
    var ttl = document.createElement('div'); ttl.className = 'alzz-viz-title'; ttl.textContent = '\uD83C\uDFB5 Backsound Musik';
    var timeEl = document.createElement('div'); timeEl.className = 'alzz-viz-time'; timeEl.textContent = '0:00';
    txt.appendChild(ttl); txt.appendChild(timeEl);
    wrap.appendChild(cv); wrap.appendChild(txt);
    card.insertBefore(wrap, audio);
    var ctx = cv.getContext('2d');

    var S = 84, dpr = Math.min(window.devicePixelRatio || 1, 3);
    cv.width = S * dpr; cv.height = S * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    // ---- tombol play/pause ----
    var playedAt = 0;
    audio.addEventListener('play', function () { playedAt = Date.now(); });
    cv.addEventListener('click', function () {
        if (Date.now() - playedAt < 600) return;      // sentuhan pertama sudah memulai musik
        if (audio.paused) { var p = audio.play(); if (p && p.catch) p.catch(function () {}); }
        else audio.pause();
    });

    // ---- analisis audio ----
    var analyser = null, freq = null, simulated = false, actx = null, wired = false;
    function wire() {
        if (wired || simulated) return;
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) { simulated = true; return; }
            actx = actx || new AC();
            var src = actx.createMediaElementSource(audio);
            analyser = actx.createAnalyser();
            analyser.fftSize = 512;                    // ±86 Hz per bin
            analyser.smoothingTimeConstant = 0.35;     // responsif terhadap hentakan
            src.connect(analyser); analyser.connect(actx.destination);
            freq = new Uint8Array(analyser.frequencyBinCount);
            wired = true;
        } catch (e) { simulated = true; analyser = null; }
    }
    function resumeCtx() { if (actx && actx.state === 'suspended') actx.resume().catch(function () {}); }
    audio.addEventListener('play', function () { wire(); resumeCtx(); start(); });
    document.addEventListener('touchstart', resumeCtx, true);
    document.addEventListener('click', resumeCtx, true);

    var srcEl = audio.querySelector('source'), retried = false;
    function noCors() {          // file tidak boleh dibaca lintas domain -> putar biasa, beat tiruan
        if (retried || wired) return;
        retried = true; simulated = true;
        audio.removeAttribute('crossorigin');
        var was = !audio.paused;
        audio.load();
        if (was || audio.autoplay) { var p = audio.play(); if (p && p.catch) p.catch(function () {}); }
    }
    if (srcEl) srcEl.addEventListener('error', noCors);
    audio.addEventListener('error', noCors);

    // ---- deteksi beat ----
    var N = 96, amp = [], tgt = [], i0;
    for (i0 = 0; i0 < N; i0++) { amp.push(0); tgt.push(0); }
    var BINS = 64, vals = new Array(BINS);
    var peakAll = 0.5;                 // penguat otomatis (lagu pelan tetap bergerak)
    var avgE = 0, prevE = 0, lastBeat = 0, beat = 0, bassN = 0, bassPeak = 0.3, bassFloor = 0, simT = 0;

    function readSpectrum(now) {
        var k, e;
        if (analyser && !simulated) {
            analyser.getByteFrequencyData(freq);
            var mx = 0;
            for (k = 0; k < BINS; k++) { vals[k] = freq[k] / 255; if (vals[k] > mx) mx = vals[k]; }
            peakAll = Math.max(mx, peakAll * 0.995, 0.35);
            for (k = 0; k < BINS; k++) vals[k] = Math.min(1, vals[k] / peakAll);
            // energi bass (kick): ± 90–430 Hz
            e = (freq[1] + freq[2] + freq[3] + freq[4]) / 4 / 255;
        } else {
            simT += 0.016;
            var kk = Math.pow(Math.max(0, Math.sin(simT * 13.4)), 8), of = Math.pow(Math.max(0, Math.sin(simT * 6.7 + 1)), 10) * 0.5;
            for (k = 0; k < BINS; k++) {
                var fall = 1 - k / BINS;
                vals[k] = Math.min(1, (kk * 0.9 + of * 0.6) * fall * (0.55 + 0.45 * Math.abs(Math.sin(simT * 3 + k * 1.7))));
            }
            e = kk * 0.9 + of * 0.3;
        }
        // bass ternormalisasi terhadap puncak & dasar yang menyesuaikan diri
        bassPeak = Math.max(e, bassPeak * 0.996, 0.25);
        bassFloor += (Math.min(e, bassFloor + 0.2) - bassFloor) * 0.01;
        bassN = Math.max(0, Math.min(1, (e - bassFloor) / (bassPeak - bassFloor + 0.05)));
        // hentakan: naik tiba-tiba di atas rata-rata
        avgE += (e - avgE) * 0.05;
        var flux = e - prevE; prevE = e;
        if (flux > 0.025 && e > avgE * 1.08 && e > 0.2 && now - lastBeat > 120) { beat = 1; lastBeat = now; }
        beat *= 0.84;

        // petakan ke titik di pinggiran: bass bawah & samping, treble ke atas (simetris)
        for (var j = 0; j < N; j++) {
            var th = (j / N) * 6.2832;
            var d = Math.abs(Math.atan2(Math.sin(th - 1.5708), Math.cos(th - 1.5708))) / 3.1416;   // 0 bawah .. 1 atas
            var idx = Math.min(BINS - 1, Math.floor(Math.pow(d, 1.1) * BINS));
            var v = Math.max(vals[idx], vals[Math.min(BINS - 1, idx + 1)] * 0.8);
            var w = 1 - d * 0.7;                                                                  // hentakan paling terasa di bawah
            tgt[j] = Math.min(1, Math.pow(v, 1.3) * 0.85 + beat * 0.55 * w + bassN * 0.18 * w);
        }
    }

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // ---- gambar ----
    var raf = 0, lastTime = '';
    function frame(t) {
        raf = 0;
        var playing = !audio.paused && !audio.ended, i;
        if (playing) readSpectrum(t);
        else { for (i = 0; i < N; i++) tgt[i] = 0; beat = 0; bassN = 0; }
        for (i = 0; i < N; i++) amp[i] += (tgt[i] - amp[i]) * (tgt[i] > amp[i] ? 0.85 : 0.22);   // naik hampir seketika, turun halus
        var sm = new Array(N);
        for (i = 0; i < N; i++) sm[i] = (amp[(i + N - 1) % N] + amp[i] * 2 + amp[(i + 1) % N]) / 4;

        ctx.clearRect(0, 0, S, S);
        var cx = S / 2, cy = S / 2, R = S * 0.30 * (1 + (beat * 0.07 + bassN * 0.04));

        // pita putih tipis di pinggiran
        var ext = S * 0.17, base = R * 1.08, k, th, rr, outer = [], inner = [];
        for (k = 0; k < N; k++) {
            th = (k / N) * 6.2832;
            rr = base + sm[k] * ext;       outer.push([cx + Math.cos(th) * rr, cy + Math.sin(th) * rr]);
            rr = base + sm[k] * ext * 0.7 + 1; inner.push([cx + Math.cos(th) * rr, cy + Math.sin(th) * rr]);
        }
        function path(pts) {
            ctx.moveTo((pts[0][0] + pts[N - 1][0]) / 2, (pts[0][1] + pts[N - 1][1]) / 2);
            for (var p = 0; p < N; p++) { var a = pts[p], c = pts[(p + 1) % N]; ctx.quadraticCurveTo(a[0], a[1], (a[0] + c[0]) / 2, (a[1] + c[1]) / 2); }
            ctx.closePath();
        }
        ctx.save();
        ctx.shadowColor = 'rgba(255,255,255,' + (0.3 + beat * 0.6) + ')'; ctx.shadowBlur = 3 + beat * 8;
        ctx.beginPath(); path(outer); path(inner); ctx.fillStyle = 'rgba(255,255,255,0.95)'; ctx.fill('evenodd');
        ctx.beginPath(); path(outer); ctx.lineWidth = 1.2; ctx.strokeStyle = 'rgba(255,255,255,0.95)'; ctx.stroke();
        ctx.restore();

        // isi hitam + cincin perak
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832); ctx.fillStyle = '#030303'; ctx.fill();
        var gr = ctx.createLinearGradient(cx - R, cy - R, cx + R, cy + R);
        gr.addColorStop(0, '#ffffff'); gr.addColorStop(0.25, '#9ca3af'); gr.addColorStop(0.5, '#f9fafb'); gr.addColorStop(0.75, '#6b7280'); gr.addColorStop(1, '#ffffff');
        ctx.save(); ctx.shadowColor = 'rgba(255,255,255,0.6)'; ctx.shadowBlur = 3 + beat * 8;
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832); ctx.lineWidth = Math.max(2.2, R * 0.09); ctx.strokeStyle = gr; ctx.stroke();
        ctx.restore();

        // ikon play / pause di tengah
        ctx.fillStyle = 'rgba(255,255,255,0.92)';
        var u = R * 0.38;
        if (playing) {
            ctx.fillRect(cx - u * 0.85, cy - u, u * 0.62, u * 2);
            ctx.fillRect(cx + u * 0.23, cy - u, u * 0.62, u * 2);
        } else {
            ctx.beginPath(); ctx.moveTo(cx - u * 0.65, cy - u * 1.05); ctx.lineTo(cx + u * 1.05, cy); ctx.lineTo(cx - u * 0.65, cy + u * 1.05); ctx.closePath(); ctx.fill();
        }

        var tx = fmt(audio.currentTime);
        if (tx !== lastTime) { timeEl.textContent = tx; lastTime = tx; }

        var still = true;
        for (i = 0; i < N; i++) { if (amp[i] > 0.004) { still = false; break; } }
        if (playing || !still) raf = requestAnimationFrame(frame);
    }
    function start() { if (!raf) raf = requestAnimationFrame(frame); }
    audio.addEventListener('pause', start);
    audio.addEventListener('seeked', function () { lastTime = ''; start(); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) start(); });
    start();
})();
