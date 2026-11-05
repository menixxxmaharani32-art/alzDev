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
    var fastAn = null, tbuf = null, prevR = 0;      // jalur cepat khusus kick (domain waktu, jendela ±11 ms)
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
            try {   // jalur cepat kick: lowpass 150 Hz -> RMS jendela pendek (latensi jauh lebih kecil daripada FFT 1024)
                var lpf = actx.createBiquadFilter(); lpf.type = 'lowpass'; lpf.frequency.value = 150; lpf.Q.value = 0.7;
                fastAn = actx.createAnalyser(); fastAn.fftSize = 256; fastAn.smoothingTimeConstant = 0;
                var mute = actx.createGain(); mute.gain.value = 0;
                src.connect(lpf); lpf.connect(fastAn); fastAn.connect(mute); mute.connect(actx.destination);
                tbuf = new Float32Array(fastAn.fftSize);
            } catch (e2) { fastAn = null; }
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
    var kick = mkBand(1, 6, 130, 0.008, 0.16, 1.1, 0.006),
        snare = mkBand(8, 34, 110, 0.020, 0.12, 1.1, 0.008),
        hat = mkBand(48, 170, 60, 0.012, 0.07, 1.3, 0.010);
    var kickHit = false, snareHit = false, kickPend = false, snarePend = false, prevF = null, lastPump = 0;

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
            var norm = 0.016 / dt;
            if (!fastAn && feed(kick, bandEnergy(kick), bandFlux(kick) * norm, dt, now)) kickPend = true;
            if (feed(snare, bandEnergy(snare), bandFlux(snare) * norm, dt, now)) snarePend = true;
            feed(hat, bandEnergy(hat), bandFlux(hat) * norm, dt, now);
            prevF.set(freq);
        } else {
            // beat tiruan (dipakai hanya jika file musik tidak bisa dianalisis lintas domain): 4/4 ±126 BPM
            simT += dt;
            var beatLen = 0.476, ph = simT / beatLen, bi = Math.floor(ph), fr = ph - bi;
            var hk = false;
            if (bi !== simBi) {
                simBi = bi; simVel = 0.6 + Math.random() * 0.4; simFill = Math.random() < 0.12;
                if (bi % 4 === 0 || bi % 4 === 2 || simFill) { hk = true; kickPend = true; kick.last = now; kick.str = simVel; }
                else if (bi % 2 === 1) { snarePend = true; snare.last = now; snare.str = simVel; }
            }
            var kE = (bi % 4 === 0 || bi % 4 === 2 || simFill) ? Math.exp(-fr * 7) * simVel : 0;
            var sE = (bi % 2 === 1) ? Math.exp(-fr * 9) * (0.7 + 0.3 * simVel) : 0;
            var h8 = (ph * 2) % 1, hE = Math.exp(-h8 * 14) * (0.35 + 0.35 * Math.abs(Math.sin(simT * 2.3)));
            for (k = 0; k < BINS; k++) {
                var t = k / BINS;
                vals[k] = clamp(kE * (1 - t * 1.4) + sE * Math.exp(-Math.pow((t - 0.5) * 3, 2)) + hE * t * 0.9, 0, 1) * (0.7 + 0.3 * Math.abs(Math.sin(simT * 4 + k)));
            }
            kick.n = kE; kick.punch = Math.max(kick.punch * Math.exp(-dt / 0.13), hk ? simVel : 0);
            kick.env += (Math.max(kE * 0.8, kick.punch) - kick.env) * kf(dt, kE > kick.env ? 0.018 : 0.16);
            snare.env += (sE - snare.env) * kf(dt, sE > snare.env ? 0.018 : 0.12);
            hat.env += (hE - hat.env) * kf(dt, hE > hat.env ? 0.012 : 0.07);
        }
    }
    var simT = 0, simBi = -1, simVel = 1, simFill = false;

    // ---- bentuk pinggiran ----
    // Tiap beat melahirkan "denyut": tonjolan tebal bergelombang di satu sisi (mula-mula bawah),
    // naik sangat cepat, lalu MENYEBAR ke samping & atas sambil meredup mulus -> kembali jadi cincin tipis tenang.
    var N = 96, i0;
    var amp = new Float32Array(N), tgt = new Float32Array(N), sm = new Float32Array(N), sm2 = new Float32Array(N);
    var ox = new Float32Array(N), oy = new Float32Array(N), ix = new Float32Array(N), iy = new Float32Array(N);
    var COS = new Float32Array(N), SIN = new Float32Array(N), C7 = new Float32Array(N), S7 = new Float32Array(N), IDX = new Uint8Array(N);
    var pc = new Float32Array(8), psn = new Float32Array(8), pe = new Float32Array(8), pw = new Float32Array(8), pa = new Float32Array(8);
    for (i0 = 0; i0 < N; i0++) {
        var a0 = i0 / N * Math.PI * 2;
        COS[i0] = Math.cos(a0); SIN[i0] = Math.sin(a0); C7[i0] = Math.cos(a0 * 7); S7[i0] = Math.sin(a0 * 7);
        IDX[i0] = Math.min(BINS - 1, Math.floor(i0 / N * BINS));
    }
    var RING_GR = ctx.createLinearGradient(S / 2 - S * 0.30, S / 2 - S * 0.30, S / 2 + S * 0.30, S / 2 + S * 0.30);   // dibuat sekali
    RING_GR.addColorStop(0, '#ffffff'); RING_GR.addColorStop(0.25, '#9ca3af'); RING_GR.addColorStop(0.5, '#f9fafb'); RING_GR.addColorStop(0.75, '#6b7280'); RING_GR.addColorStop(1, '#ffffff');
    var scaleEnv = 0, pulses = [], lastC = Math.PI / 2, drift = 0, lastKickAt = -9999;

    function spawn(c, s, life, spread, age0) {
        pulses.push({ c: c, s: s, age: age0 || 0, life: life, spread: spread });
        if (pulses.length > 5) pulses.shift();
    }

    function shape(dt, now, t) {
        drift += dt * (0.10 + snare.env * 0.35);
        if (kickPend) {
            kickPend = false;
            if (now - lastKickAt < 380) {
                lastC += (Math.random() - 0.5) * 0.7;              // beat rapat: bergeser sedikit -> gerak menyambung, tidak melompat
            } else {
                var r = Math.random();
                lastC = Math.PI / 2 + (r < 0.55 ? 0 : (r < 0.8 ? Math.PI : (Math.random() < 0.5 ? -1 : 1) * (0.9 + Math.random() * 0.8))) + (Math.random() - 0.5) * 0.7;
            }
            lastKickAt = now;
            spawn(lastC, 0.6 + 0.4 * kick.str, 0.20, 1, clamp((now - kick.last) / 1000, 0, 0.03));
        }
        if (snarePend) {
            snarePend = false;
            var side = (Math.random() < 0.5 ? -1 : 1) * (1.2 + Math.random() * 0.5);
            spawn(lastC + side, 0.35 + 0.35 * snare.str, 0.13, 0.7, clamp((now - snare.last) / 1000, 0, 0.03));
        }
        for (var q = pulses.length - 1; q >= 0; q--) {
            pulses[q].age += dt;
            if (pulses[q].age > pulses[q].life * 6) pulses.splice(q, 1);
        }

        var np = 0, p;
        for (p = 0; p < pulses.length && np < 8; p++) {
            var P = pulses[p], age = P.age;
            var pen = (1 - Math.exp(-age / 0.006)) * Math.exp(-age / P.life) * P.s;
            if (pen < 0.004) continue;                                     // denyut yang sudah hampir hilang dilewati
            pc[np] = Math.cos(P.c); psn[np] = Math.sin(P.c); pe[np] = pen;
            pw[np] = 1 / ((0.14 + 0.5 * (1 - Math.exp(-age / 0.11))) * P.spread);   // makin lama makin melebar
            pa[np] = age * 16; np++;
        }
        var hs = hat.env * 0.06, hph = drift * 3 + t * 0.002, cph = Math.cos(hph), sph = Math.sin(hph);
        for (var j = 0; j < N; j++) {
            var v = 0;
            for (p = 0; p < np; p++) {
                var cd = COS[j] * pc[p] + SIN[j] * psn[p];
                var dd = Math.acos(cd > 1 ? 1 : (cd < -1 ? -1 : cd)) / Math.PI;       // 0..1 dari pusat denyut
                var z = dd * pw[p];
                v += pe[p] * Math.exp(-z * z) * (0.6 + 0.4 * Math.cos(dd * 18.8496 - pa[p]));   // riak merambat keluar
            }
            var vv = vals[IDX[j]];
            var shimmer = hs > 0.0005 ? (0.5 + 0.5 * (S7[j] * cph + C7[j] * sph)) * hs : 0;
            tgt[j] = 1 - Math.exp(-1.8 * (v + vv * vv * 0.10 + shimmer));         // saturasi lembut
        }
    }

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // Jalur cepat KICK: hanya membaca 256 sampel bass (sangat ringan), ±80x/detik, hanya saat musik main & tab terlihat.
    // Bagian berat (spektrum, snare, hat) cukup sekali per frame gambar.
    var lastFast = 0, fastTimer = 0;
    function fastKick() {
        if (!fastAn || audio.paused || audio.ended) { lastFast = 0; return; }
        var now = performance.now();
        var dt = lastFast ? clamp((now - lastFast) / 1000, 0.004, 0.05) : 0.012;
        lastFast = now;
        fastAn.getFloatTimeDomainData(tbuf);
        var s2 = 0; for (var z = 0; z < tbuf.length; z++) s2 += tbuf[z] * tbuf[z];
        var rr = Math.sqrt(s2 / tbuf.length);
        var fl = Math.max(0, rr - prevR) * (0.016 / dt); prevR = rr;
        if (feed(kick, clamp(rr * 2.5, 0, 1), fl, dt, now)) kickPend = true;
    }
    function fastStart() { if (!fastTimer && fastAn && !audio.paused && !document.hidden) fastTimer = setInterval(fastKick, 12); }
    function fastStop() { if (fastTimer) { clearInterval(fastTimer); fastTimer = 0; } lastFast = 0; }
    audio.addEventListener('play', fastStart);
    audio.addEventListener('pause', fastStop);
    audio.addEventListener('ended', fastStop);
    document.addEventListener('visibilitychange', function () { if (document.hidden) fastStop(); else fastStart(); });

    function path(xs, ys) {
        ctx.moveTo((xs[0] + xs[N - 1]) / 2, (ys[0] + ys[N - 1]) / 2);
        for (var p = 0; p < N; p++) { var q = p + 1 === N ? 0 : p + 1; ctx.quadraticCurveTo(xs[p], ys[p], (xs[p] + xs[q]) / 2, (ys[p] + ys[q]) / 2); }
        ctx.closePath();
    }

    // ---- gambar ----
    var raf = 0, lastTime = '', lastT = 0;
    function frame(t) {
        raf = 0;
        var dt = lastT ? clamp((t - lastT) / 1000, 0.004, 0.05) : 0.016; lastT = t;
        var playing = !audio.paused && !audio.ended, i;
        if (playing) { var pn = performance.now(); readSpectrum(dt, pn); shape(dt, pn, t); }
        else {
            kickPend = false; snarePend = false; pulses.length = 0;
            kick.env *= Math.exp(-dt / 0.15); snare.env *= Math.exp(-dt / 0.15); hat.env *= Math.exp(-dt / 0.15);
            tgt.fill(0);
        }
        // gerak halus: naik cepat tapi tidak patah, turun mengalir
        var ka = kf(dt, 0.005), kr = kf(dt, 0.085);
        for (i = 0; i < N; i++) amp[i] += (tgt[i] - amp[i]) * (tgt[i] > amp[i] ? ka : kr);
        for (i = 0; i < N; i++) sm[i] = (amp[i === 0 ? N - 1 : i - 1] + amp[i] * 2 + amp[i === N - 1 ? 0 : i + 1]) / 4;
        for (i = 0; i < N; i++) sm2[i] = (sm[i === 0 ? N - 1 : i - 1] + sm[i] * 2 + sm[i === N - 1 ? 0 : i + 1]) / 4;

        scaleEnv += ((kick.env * 0.8) - scaleEnv) * kf(dt, kick.env * 0.8 > scaleEnv ? 0.02 : 0.09);
        ctx.clearRect(0, 0, S, S);
        var cx = S / 2, cy = S / 2, R = S * 0.30 * (1 + scaleEnv * 0.09);

        var ext = S * 0.21, base = R * 1.08, k, rr;
        for (k = 0; k < N; k++) {
            rr = base + sm2[k] * ext;            ox[k] = cx + COS[k] * rr; oy[k] = cy + SIN[k] * rr;
            rr = base + sm2[k] * ext * 0.7 + 1;  ix[k] = cx + COS[k] * rr; iy[k] = cy + SIN[k] * rr;
        }
        ctx.beginPath(); path(ox, oy); path(ix, iy); ctx.fillStyle = 'rgba(255,255,255,0.95)'; ctx.fill('evenodd');
        ctx.beginPath(); path(ox, oy);
        if (kick.env > 0.12) { ctx.lineWidth = 3.5; ctx.strokeStyle = 'rgba(255,255,255,' + (kick.env * 0.2).toFixed(3) + ')'; ctx.stroke(); }   // glow murah pengganti shadowBlur
        ctx.lineWidth = 1.2; ctx.strokeStyle = 'rgba(255,255,255,0.95)'; ctx.stroke();

        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832); ctx.fillStyle = '#030303'; ctx.fill();
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832); ctx.lineWidth = Math.max(2.2, R * 0.09); ctx.strokeStyle = RING_GR; ctx.stroke();

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
