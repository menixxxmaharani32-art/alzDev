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
            analyser.fftSize = 1024;                   // ±43 Hz per bin: kick terbaca jelas
            analyser.smoothingTimeConstant = 0.2;
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

    // ---------- pembantu ----------
    function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
    function kf(dt, tau) { return 1 - Math.exp(-dt / tau); }          // koefisien penghalus berbasis waktu (mulus di semua FPS)

    // Tiga pita dibaca dengan SPECTRAL FLUX (lonjakan energi antar-frame) + ambang adaptif (rata-rata + simpangan),
    // jadi tiap beat tetap terbaca walau bass menyala terus, volume berubah, atau lagu masuk bagian ramai.
    function mkBand(lo, hi, gap, atk, rel, k, minFlux) {
        return { lo: lo, hi: hi, gap: gap, atk: atk, rel: rel, k: k, minFlux: minFlux,
                 peak: 0.3, floor: 0, avg: 0, last: 0, punch: 0, env: 0, n: 0, fm: 0, fv: 0, fpk: 0.02, str: 0.7 };
    }
    var kick = mkBand(1, 6, 130, 0.020, 0.16, 1.1, 0.006),
        snare = mkBand(8, 34, 110, 0.020, 0.12, 1.1, 0.008),
        hat = mkBand(48, 170, 60, 0.012, 0.07, 1.3, 0.010);
    var kickHit = false, snareHit = false, prevF = null;

    function bandEnergy(b) {
        var s = 0, c = 0;
        for (var k = b.lo; k <= b.hi && k < freq.length; k++) { s += freq[k]; c++; }
        return c ? s / c / 255 : 0;
    }
    function bandFlux(b) {
        var s = 0, c = 0, d;
        for (var k = b.lo; k <= b.hi && k < freq.length; k++) { d = freq[k] - prevF[k]; if (d > 0) s += d; c++; }
        return c ? s / c / 255 : 0;
    }
    function feed(b, e, fl, dt, now) {
        // level halus (tidak pernah "mati" saat bass terus menyala)
        b.peak = Math.max(e, b.peak * Math.pow(0.996, dt * 60), 0.12);
        b.floor += (Math.min(e, b.floor + 0.08) - b.floor) * kf(dt, 6);
        b.floor = Math.min(b.floor, b.peak * 0.55);
        b.n = Math.pow(clamp((e - b.floor) / Math.max(b.peak - b.floor, 0.1), 0, 1), 1.2);

        // deteksi beat: lonjakan flux di atas ambang adaptif
        b.fm += (fl - b.fm) * kf(dt, 0.6);
        var dv = fl - b.fm;
        b.fv += (dv * dv - b.fv) * kf(dt, 0.6);
        b.fpk = Math.max(fl, b.fpk * Math.pow(0.995, dt * 60), 0.02);
        var thr = b.fm + b.k * Math.sqrt(b.fv) + b.minFlux;
        var starve = (b === kick) && now - b.last > 1300 && b.n > 0.3;     // jaring pengaman: jangan diam terlalu lama
        var hit = false;
        if (((fl > thr && fl > b.fpk * 0.3 && e > 0.06) || starve) && now - b.last > b.gap) {
            b.last = now;
            b.str = starve ? 0.5 : clamp(0.45 + 0.55 * fl / b.fpk, 0.45, 1);
            b.punch = b.str; hit = true;
        }
        b.punch *= Math.exp(-dt / 0.13);
        var target = Math.max(b.n * 0.7, b.punch);
        b.env += (target - b.env) * kf(dt, target > b.env ? b.atk : b.rel);
        return hit;
    }

    // spektrum log untuk bentuk pinggiran
    var BINS = 40, vals = new Array(BINS), peakAll = 0.4, logLo = Math.log(1), logHi = Math.log(190);
    function readSpectrum(dt, now) {
        var k, a, b, m, v;
        if (analyser && !simulated) {
            analyser.getByteFrequencyData(freq);
            if (!prevF) prevF = new Uint8Array(freq.length);
            var mx = 0;
            for (k = 0; k < BINS; k++) {
                a = Math.floor(Math.exp(logLo + (logHi - logLo) * k / BINS));
                b = Math.max(a, Math.floor(Math.exp(logLo + (logHi - logLo) * (k + 1) / BINS)) - 1);
                m = 0; for (var q = a; q <= b; q++) if (freq[q] > m) m = freq[q];
                vals[k] = m / 255; if (vals[k] > mx) mx = vals[k];
            }
            peakAll = Math.max(mx, peakAll * Math.pow(0.996, dt * 60), 0.3);
            for (k = 0; k < BINS; k++) vals[k] = clamp(vals[k] / peakAll, 0, 1);
            kickHit = feed(kick, bandEnergy(kick), bandFlux(kick), dt, now);
            snareHit = feed(snare, bandEnergy(snare), bandFlux(snare), dt, now);
            feed(hat, bandEnergy(hat), bandFlux(hat), dt, now);
            prevF.set(freq);
        } else {
            // beat tiruan (dipakai hanya jika file musik tidak bisa dianalisis lintas domain): 4/4 ±126 BPM
            simT += dt;
            var beatLen = 0.476, ph = simT / beatLen, bi = Math.floor(ph), fr = ph - bi;
            kickHit = false; snareHit = false;
            if (bi !== simBi) {
                simBi = bi; simVel = 0.6 + Math.random() * 0.4; simFill = Math.random() < 0.12;
                if (bi % 4 === 0 || bi % 4 === 2 || simFill) { kickHit = true; kick.last = now; kick.str = simVel; }
                else if (bi % 2 === 1) { snareHit = true; snare.last = now; snare.str = simVel; }
            }
            var kE = (bi % 4 === 0 || bi % 4 === 2 || simFill) ? Math.exp(-fr * 7) * simVel : 0;
            var sE = (bi % 2 === 1) ? Math.exp(-fr * 9) * (0.7 + 0.3 * simVel) : 0;
            var h8 = (ph * 2) % 1, hE = Math.exp(-h8 * 14) * (0.35 + 0.35 * Math.abs(Math.sin(simT * 2.3)));
            for (k = 0; k < BINS; k++) {
                var t = k / BINS;
                vals[k] = clamp(kE * (1 - t * 1.4) + sE * Math.exp(-Math.pow((t - 0.5) * 3, 2)) + hE * t * 0.9, 0, 1) * (0.7 + 0.3 * Math.abs(Math.sin(simT * 4 + k)));
            }
            kick.n = kE; kick.punch = Math.max(kick.punch * Math.exp(-dt / 0.13), kickHit ? simVel : 0);
            kick.env += (Math.max(kE * 0.8, kick.punch) - kick.env) * kf(dt, kE > kick.env ? 0.018 : 0.16);
            snare.env += (sE - snare.env) * kf(dt, sE > snare.env ? 0.018 : 0.12);
            hat.env += (hE - hat.env) * kf(dt, hE > hat.env ? 0.012 : 0.07);
        }
    }
    var simT = 0, simBi = -1, simVel = 1, simFill = false;

    // ---- bentuk pinggiran ----
    // Tiap beat melahirkan "denyut": tonjolan tebal bergelombang di satu sisi (mula-mula bawah),
    // naik sangat cepat, lalu MENYEBAR ke samping & atas sambil meredup mulus -> kembali jadi cincin tipis tenang.
    var N = 128, amp = new Array(N), tgt = new Array(N), i0;
    for (i0 = 0; i0 < N; i0++) { amp[i0] = 0; tgt[i0] = 0; }
    var scaleEnv = 0, pulses = [], lastC = Math.PI / 2, drift = 0, lastKickAt = -9999;

    function spawn(c, s, life, spread) {
        pulses.push({ c: c, s: s, age: 0, life: life, spread: spread });
        if (pulses.length > 5) pulses.shift();
    }

    function shape(dt, now, t) {
        drift += dt * (0.10 + snare.env * 0.35);
        if (kickHit) {
            if (now - lastKickAt < 380) {
                lastC += (Math.random() - 0.5) * 0.7;              // beat rapat: bergeser sedikit -> gerak menyambung, tidak melompat
            } else {
                var r = Math.random();
                lastC = Math.PI / 2 + (r < 0.55 ? 0 : (r < 0.8 ? Math.PI : (Math.random() < 0.5 ? -1 : 1) * (0.9 + Math.random() * 0.8))) + (Math.random() - 0.5) * 0.7;
            }
            lastKickAt = now;
            spawn(lastC, 0.6 + 0.4 * kick.str, 0.20, 1);
        }
        if (snareHit) {
            var side = (Math.random() < 0.5 ? -1 : 1) * (1.2 + Math.random() * 0.5);
            spawn(lastC + side, 0.35 + 0.35 * snare.str, 0.13, 0.7);
        }
        for (var q = pulses.length - 1; q >= 0; q--) {
            pulses[q].age += dt;
            if (pulses[q].age > pulses[q].life * 6) pulses.splice(q, 1);
        }

        for (var j = 0; j < N; j++) {
            var th = (j / N) * Math.PI * 2;
            var v = 0;
            for (var p = 0; p < pulses.length; p++) {
                var P = pulses[p], age = P.age;
                var dd = Math.abs(Math.atan2(Math.sin(th - P.c), Math.cos(th - P.c))) / Math.PI;   // 0..1 dari pusat denyut
                var env = (1 - Math.exp(-age / 0.022)) * Math.exp(-age / P.life);                  // naik cepat, turun mengalir
                var width = (0.14 + 0.5 * (1 - Math.exp(-age / 0.11))) * P.spread;                // makin lama makin melebar
                var lobe = Math.exp(-Math.pow(dd / width, 2));
                var ripple = 0.6 + 0.4 * Math.cos(dd * Math.PI * 6 - age * 16);                    // gelombang merambat keluar
                v += P.s * env * lobe * ripple;
            }
            // dasar tenang: spektrum halus + riak tipis dari hat
            var idx = Math.min(BINS - 1, Math.floor((j / N) * BINS));
            var base = Math.pow(vals[idx], 2) * 0.10;
            var shimmer = (0.5 + 0.5 * Math.sin(th * 7 + drift * 3 + t * 0.002)) * hat.env * 0.06;
            tgt[j] = 1 - Math.exp(-1.8 * Math.max(0, v + base + shimmer));   // saturasi lembut, tanpa potongan keras
        }
    }

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // ---- gambar ----
    var raf = 0, lastTime = '', lastT = 0;
    function frame(t) {
        raf = 0;
        var dt = lastT ? clamp((t - lastT) / 1000, 0.004, 0.05) : 0.016; lastT = t;
        var playing = !audio.paused && !audio.ended, i;
        if (playing) { readSpectrum(dt, t); shape(dt, t, t); }
        else {
            kickHit = false; snareHit = false; pulses.length = 0;
            kick.env *= Math.exp(-dt / 0.15); snare.env *= Math.exp(-dt / 0.15); hat.env *= Math.exp(-dt / 0.15);
            for (i = 0; i < N; i++) tgt[i] = 0;
        }
        // gerak halus: naik cepat tapi tidak patah, turun mengalir
        var ka = kf(dt, 0.016), kr = kf(dt, 0.085);
        for (i = 0; i < N; i++) amp[i] += (tgt[i] - amp[i]) * (tgt[i] > amp[i] ? ka : kr);
        var sm = new Array(N), sm2 = new Array(N);
        for (i = 0; i < N; i++) sm[i] = (amp[(i + N - 1) % N] + amp[i] * 2 + amp[(i + 1) % N]) / 4;
        for (i = 0; i < N; i++) sm2[i] = (sm[(i + N - 1) % N] + sm[i] * 2 + sm[(i + 1) % N]) / 4;

        scaleEnv += ((kick.env * 0.8) - scaleEnv) * kf(dt, 0.07);
        ctx.clearRect(0, 0, S, S);
        var cx = S / 2, cy = S / 2, R = S * 0.30 * (1 + scaleEnv * 0.09);

        var ext = S * 0.21, base = R * 1.08, k, th, rr, outer = [], inner = [];
        for (k = 0; k < N; k++) {
            th = (k / N) * 6.2832;
            rr = base + sm2[k] * ext;            outer.push([cx + Math.cos(th) * rr, cy + Math.sin(th) * rr]);
            rr = base + sm2[k] * ext * 0.7 + 1;  inner.push([cx + Math.cos(th) * rr, cy + Math.sin(th) * rr]);
        }
        function path(pts) {
            ctx.moveTo((pts[0][0] + pts[N - 1][0]) / 2, (pts[0][1] + pts[N - 1][1]) / 2);
            for (var p = 0; p < N; p++) { var a = pts[p], c = pts[(p + 1) % N]; ctx.quadraticCurveTo(a[0], a[1], (a[0] + c[0]) / 2, (a[1] + c[1]) / 2); }
            ctx.closePath();
        }
        ctx.save();
        ctx.shadowColor = 'rgba(255,255,255,' + (0.3 + kick.env * 0.6) + ')'; ctx.shadowBlur = 3 + kick.env * 8;
        ctx.beginPath(); path(outer); path(inner); ctx.fillStyle = 'rgba(255,255,255,0.95)'; ctx.fill('evenodd');
        ctx.beginPath(); path(outer); ctx.lineWidth = 1.2; ctx.strokeStyle = 'rgba(255,255,255,0.95)'; ctx.stroke();
        ctx.restore();

        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832); ctx.fillStyle = '#030303'; ctx.fill();
        var gr = ctx.createLinearGradient(cx - R, cy - R, cx + R, cy + R);
        gr.addColorStop(0, '#ffffff'); gr.addColorStop(0.25, '#9ca3af'); gr.addColorStop(0.5, '#f9fafb'); gr.addColorStop(0.75, '#6b7280'); gr.addColorStop(1, '#ffffff');
        ctx.save(); ctx.shadowColor = 'rgba(255,255,255,0.6)'; ctx.shadowBlur = 3 + kick.env * 8;
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832); ctx.lineWidth = Math.max(2.2, R * 0.09); ctx.strokeStyle = gr; ctx.stroke();
        ctx.restore();

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
        if (playing || !still) raf = requestAnimationFrame(frame); else lastT = 0;
    }
    function start() { if (!raf) raf = requestAnimationFrame(frame); }
    audio.addEventListener('pause', start);
    audio.addEventListener('seeked', function () { lastTime = ''; start(); });
    document.addEventListener('visibilitychange', function () { lastT = 0; if (!document.hidden) start(); });
    start();
})();
