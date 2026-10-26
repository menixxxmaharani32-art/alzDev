<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
// FRONTEND: halaman dashboard admin (gabungan kerangka + header + semua tab + navigasi bawah)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ALZZ Development</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(ALZZ_ROOT . '/style.css'); ?>">
    <link rel="stylesheet" href="admin.css?v=<?php echo filemtime(ALZZ_ROOT . '/admin.css'); ?>">
</head>
<body>
    <div class="mobile-container">

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

<?php
// FRONTEND: tab Pendapatan (hanya tampilan; angka dihitung di admin_backend.php)

$rs = $pendapatan['ringkas'];

// Pilihan grafik: harian / mingguan / bulanan (lewat alamat ?tab=pendapatan&r=...)
$range = $_GET['r'] ?? 'harian';
if (!in_array($range, array('harian', 'mingguan', 'bulanan'), true)) { $range = 'harian'; }
$rows  = $pendapatan[$range];
$judul_range = array('harian' => '14 hari terakhir', 'mingguan' => '8 minggu terakhir', 'bulanan' => '12 bulan terakhir');
$satuan_now  = array('harian' => 'Hari ini', 'mingguan' => 'Minggu ini', 'bulanan' => 'Bulan ini');

// Nilai tertinggi, total periode, dan penentu label angka di atas batang
$max = 0; $sum_total = 0; $sum_n = 0; $idx_max = -1;
foreach ($rows as $i => $r) {
    $sum_total += $r['total']; $sum_n += $r['n'];
    if ($r['total'] > $max) { $max = $r['total']; $idx_max = $i; }
}
$tampil_semua = (count($rows) <= 8);
$tinggi_maks  = 84; // piksel
?>
<div id="tab-pendapatan" class="tab-content <?php echo ($active_tab == 'pendapatan') ? 'active' : ''; ?>">

    <!-- Ringkasan utama: bulan ini -->
    <div class="rev-hero">
        <span class="rev-eyebrow">Pendapatan bulan ini</span>
        <div class="rev-hero-value"><span class="rev-rp">Rp</span><?php echo alzz_angka($rs['bulan']['now']['total']); ?></div>
        <div class="rev-sub"><?php echo (int)$rs['bulan']['now']['n']; ?> pesanan &middot; Bulan lalu <?php echo alzz_rupiah($rs['bulan']['prev']['total']); ?></div>
    </div>

    <!-- Hari ini & minggu ini -->
    <div class="rev-grid">
        <div class="rev-card">
            <span class="rev-eyebrow">Hari ini</span>
            <div class="rev-value"><span class="rev-rp">Rp</span><?php echo alzz_angka($rs['hari']['now']['total']); ?></div>
            <div class="rev-sub"><?php echo (int)$rs['hari']['now']['n']; ?> pesanan<br>Kemarin <?php echo alzz_rupiah($rs['hari']['prev']['total']); ?></div>
        </div>
        <div class="rev-card">
            <span class="rev-eyebrow">Minggu ini</span>
            <div class="rev-value"><span class="rev-rp">Rp</span><?php echo alzz_angka($rs['minggu']['now']['total']); ?></div>
            <div class="rev-sub"><?php echo (int)$rs['minggu']['now']['n']; ?> pesanan<br>Minggu lalu <?php echo alzz_rupiah($rs['minggu']['prev']['total']); ?></div>
        </div>
    </div>

    <!-- Total keseluruhan & menunggu konfirmasi -->
    <div class="rev-grid">
        <div class="rev-card">
            <span class="rev-eyebrow">Total keseluruhan</span>
            <div class="rev-value"><span class="rev-rp">Rp</span><?php echo alzz_angka($rs['total']['total']); ?></div>
            <div class="rev-sub"><?php echo (int)$rs['total']['n']; ?> pesanan terkonfirmasi</div>
        </div>
        <div class="rev-card rev-pending">
            <span class="rev-eyebrow">Menunggu konfirmasi</span>
            <div class="rev-value"><span class="rev-rp">Rp</span><?php echo alzz_angka($rs['pending']['total']); ?></div>
            <div class="rev-sub"><?php echo (int)$rs['pending']['n']; ?> pesanan Pending<br>belum dihitung</div>
        </div>
    </div>

    <!-- Grafik & rincian -->
    <div class="admin-card rev-panel">
        <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Rincian Pendapatan</h3>

        <div class="rev-seg">
            <a href="admin_dashboard.php?tab=pendapatan&amp;r=harian"   class="<?php echo $range === 'harian'   ? 'on' : ''; ?>">Harian</a>
            <a href="admin_dashboard.php?tab=pendapatan&amp;r=mingguan" class="<?php echo $range === 'mingguan' ? 'on' : ''; ?>">Mingguan</a>
            <a href="admin_dashboard.php?tab=pendapatan&amp;r=bulanan"  class="<?php echo $range === 'bulanan'  ? 'on' : ''; ?>">Bulanan</a>
        </div>

        <div class="rev-meta">
            <span><?php echo ucfirst($judul_range[$range]); ?></span>
            <strong><?php echo alzz_rupiah($sum_total); ?> &middot; <?php echo (int)$sum_n; ?> pesanan</strong>
        </div>

        <?php if ($sum_total > 0 || $sum_n > 0): ?>
            <div class="rev-chart">
                <?php foreach ($rows as $i => $r):
                    $h = ($r['total'] > 0 && $max > 0) ? max(4, (int)round($r['total'] / $max * $tinggi_maks)) : 2;
                    $label_angka = ($r['total'] > 0 && ($tampil_semua || $i === $idx_max || $r['now']));
                ?>
                    <div class="rev-col <?php echo $r['now'] ? 'now' : ''; ?> <?php echo $r['total'] <= 0 ? 'zero' : ''; ?>" title="<?php echo htmlspecialchars($r['judul']) . ': ' . alzz_rupiah($r['total']); ?>">
                        <div class="rev-track">
                            <span class="rev-amt"><?php echo $label_angka ? alzz_rupiah_ringkas($r['total']) : ''; ?></span>
                            <div class="rev-bar" style="height: <?php echo $h; ?>px;"></div>
                        </div>
                        <span class="rev-lbl"><?php echo htmlspecialchars($r['label']); ?></span>
                        <span class="rev-lbl2"><?php echo htmlspecialchars($r['sub']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="rev-empty">Belum ada pendapatan pada periode ini.</p>
        <?php endif; ?>

        <div class="rev-list">
            <?php foreach (array_reverse($rows) as $r): ?>
                <div class="rev-row <?php echo $r['now'] ? 'now' : ''; ?> <?php echo $r['total'] <= 0 ? 'zero' : ''; ?>">
                    <div>
                        <strong><?php echo htmlspecialchars($r['judul']); ?></strong>
                        <?php if ($r['now']): ?><span class="rev-tag"><?php echo $satuan_now[$range]; ?></span><?php endif; ?>
                        <small><?php echo (int)$r['n']; ?> pesanan</small>
                    </div>
                    <span class="rev-row-val"><?php echo alzz_rupiah($r['total']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="rev-note">Yang dihitung: pesanan berstatus <b>Dikonfirmasi</b> dan <b>Selesai</b>, berdasarkan tanggal pesanan dibuat. Minggu dihitung Senin&ndash;Minggu.</p>
    </div>

</div>
        <!-- TAB 1: SITUS & PESANAN -->
        <div id="tab-situs" class="tab-content <?php echo ($active_tab == 'situs') ? 'active' : ''; ?>">
            
            <!-- Pengaturan Situs & Kontak -->
            <div class="admin-card" style="margin-bottom: 16px; border-color: #38bdf8;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Pengaturan Situs & Kontak</h3>
                <?php if (($_GET['err'] ?? '') === 'musik'): ?>
                    <div style="background: rgba(239,68,68,0.12); border: 1px solid #ef4444; color: #fca5a5; padding: 8px; border-radius: 6px; font-size: 10px; margin-bottom: 8px;">Musik gagal diunggah (pengaturan lain tetap tersimpan).<?php if (!empty($_GET['why'])): ?><br><b>Penyebab:</b> <?php echo htmlspecialchars(substr($_GET['why'], 0, 220)); ?><?php endif; ?></div>
                <?php endif; ?>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 8px;">
                        <label style="color: #cbd5e1; font-size: 10px; display: block; margin-bottom: 2px;">URL Banner / Foto Utama:</label>
                        <input type="text" name="banner_url" value="<?php echo htmlspecialchars($admin_data['banner_url'] ?? ''); ?>" placeholder="https://files.catbox.moe/xxxxx.jpg" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <div style="margin-bottom: 8px;">
                        <label style="color: #cbd5e1; font-size: 10px; display: block; margin-bottom: 2px;">URL Musik MP3 (Opsional):</label>
                        <input type="text" name="music_url" value="<?php echo htmlspecialchars($admin_data['music_url'] ?? ''); ?>" placeholder="https://.../music.mp3" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                        <label style="color: #cbd5e1; font-size: 10px; display: block; margin: 8px 0 2px;">Atau upload file musik dari HP (mp3/m4a/ogg/wav, maks 15MB):</label>
                        <input type="file" name="music_file" accept="audio/*" style="width: 100%; color: #cbd5e1; font-size: 11px;">
                        <?php if (!empty($admin_data['music_url'])): ?>
                            <div style="margin-top: 8px; color: #94a3b8; font-size: 10px;">Musik saat ini (putar untuk tes):</div>
                            <audio controls preload="none" src="<?php echo htmlspecialchars($admin_data['music_url']); ?>" style="width: 100%; margin-top: 4px;"></audio>
                        <?php endif; ?>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="color: #cbd5e1; font-size: 10px; display: block; margin-bottom: 2px;">Nomor WhatsApp Admin (Awali 62):</label>
                        <input type="text" name="no_wa" value="<?php echo htmlspecialchars($admin_data['no_wa'] ?? ''); ?>" placeholder="628123456789" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <button type="submit" name="update_pengaturan" class="btn-buy-mini" style="width: 100%; padding: 8px; font-size: 11px; cursor: pointer;">Simpan Pengaturan Situs</button>
                </form>
            </div>

            <!-- Kelola Pesanan Masuk -->
            <div class="admin-card" style="margin-bottom: 16px;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Kelola Pesanan Masuk</h3>
                <?php if ($pesanan_result && db_num_rows($pesanan_result) > 0): ?>
                    <?php while($ord = db_fetch_assoc($pesanan_result)): ?>
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px; margin-bottom: 12px; font-size: 11px;">
                            
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <strong style="color: #38bdf8; font-size: 12px;">
                                    ID: #<?php echo $ord['id']; ?> - <?php echo htmlspecialchars($ord['nama_app']); ?>
                                    <?php if (!empty($ord['durasi']) || !empty($ord['plan'])): ?>
                                        <span style="color: #facc15; font-size: 11px;">
                                            (<?php echo htmlspecialchars($ord['durasi'] ?? ''); ?> <?php echo !empty($ord['plan']) ? '- '.htmlspecialchars($ord['plan']) : ''; ?>)
                                        </span>
                                    <?php endif; ?>
                                </strong>
                                <a href="admin_dashboard.php?hapus_pesanan=<?php echo $ord['id']; ?>&tab=situs" onclick="return confirm('Hapus pesanan ini dari dashboard?')" style="color: #ef4444; text-decoration: none; font-weight: bold; font-size: 10px; background: rgba(239, 68, 68, 0.1); padding: 2px 6px; border-radius: 4px; border: 1px solid #ef4444;">Hapus</a>
                            </div>
                            
                            <!-- INFORMASI USERNAME DAN DETAIL PEMBELI -->
                            <div style="margin-top: 4px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 4px;">
                                <div>Akun Username: <strong style="color: #38bdf8;"><?php echo htmlspecialchars($ord['username'] ?? 'Guest'); ?></strong></div>
                                <div>Nama Pembeli: <strong style="color: #fff;"><?php echo htmlspecialchars($ord['nama'] ?? '-'); ?></strong></div>
                                <div>Alamat: <strong style="color: #cbd5e1;"><?php echo htmlspecialchars($ord['alamat'] ?? '-'); ?></strong></div>
                                <div>Device: <strong style="color: #38bdf8;"><?php echo htmlspecialchars($ord['device'] ?? '-'); ?></strong></div>
                            </div>

                            <div style="margin-top: 4px;">Tgl: <?php echo $ord['tanggal']; ?></div>
                            <div>Metode: <strong><?php echo htmlspecialchars($ord['metode_pembayaran']); ?></strong></div>
                            
                            <?php if (!empty($ord['bukti_pembayaran'])): ?>
                                <div style="margin-top: 6px;">
                                    <a href="<?php echo htmlspecialchars($ord['bukti_pembayaran']); ?>" target="_blank" style="color: #38bdf8; text-decoration: underline; font-weight: bold;">[ Lihat Foto Bukti Pembayaran ]</a>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST" style="margin-top: 8px;">
                                <input type="hidden" name="pesanan_id" value="<?php echo $ord['id']; ?>">
                                
                                <div style="margin-bottom: 6px;">
                                    <label style="color: #cbd5e1; font-size: 10px; display: block; margin-bottom: 2px;">Status:</label>
                                    <select name="status" style="width: 100%; padding: 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 10px;">
                                        <option value="Pending" <?php echo ($ord['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Dikonfirmasi" <?php echo ($ord['status'] == 'Dikonfirmasi') ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                        <option value="Selesai" <?php echo ($ord['status'] == 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                                    </select>
                                </div>

                                <div style="margin-bottom: 6px;">
                                    <label style="color: #cbd5e1; font-size: 10px; display: block; margin-bottom: 2px;">Data Akun / Pesanan:</label>
                                    <textarea name="data_pesanan" rows="2" style="width: 100%; padding: 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 10px;"><?php echo htmlspecialchars($ord['data_pesanan'] ?? ''); ?></textarea>
                                </div>

                                <div style="margin-bottom: 6px;">
                                    <label style="color: #38bdf8; font-size: 10px; display: block; margin-bottom: 2px;">Cara Login:</label>
                                    <textarea name="cara_login" rows="2" style="width: 100%; padding: 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 10px;"><?php echo htmlspecialchars($ord['cara_login'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" name="update_pesanan" style="background: #38bdf8; color: #000; border: none; padding: 6px 12px; border-radius: 6px; font-weight: bold; font-size: 10px; cursor: pointer;">Simpan Perubahan</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #94a3b8; font-size: 11px; text-align: center;">Belum ada pesanan masuk.</p>
                <?php endif; ?>
            </div>

        </div>
        <!-- TAB 2: METODE PEMBAYARAN -->
        <div id="tab-pembayaran" class="tab-content <?php echo ($active_tab == 'pembayaran') ? 'active' : ''; ?>">
            <div class="admin-card" style="margin-bottom: 16px;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Metode Pembayaran</h3>
                
                <form action="" method="POST" style="margin-bottom: 14px;">
                    <div style="margin-bottom: 8px;">
                        <input type="text" name="nama_metode" placeholder="Nama Metode (Contoh: QRIS / DANA)" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <div style="margin-bottom: 8px;">
                        <input type="text" name="nomor_tujuan" placeholder="Nomor Tujuan atau URL Gambar QRIS" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <div style="margin-bottom: 8px;">
                        <input type="text" name="pemilik" placeholder="Atas Nama (Opsional)" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <button type="submit" name="tambah_metode" class="btn-buy-mini" style="width: 100%; padding: 8px; font-size: 11px; cursor: pointer;">Tambah Metode</button>
                </form>

                <div style="font-size: 11px;">
                    <strong style="color: #cbd5e1; display: block; margin-bottom: 6px;">Daftar Metode:</strong>
                    <?php if ($metode_result && db_num_rows($metode_result) > 0): ?>
                        <?php while($m = db_fetch_assoc($metode_result)): ?>
                            <?php 
                                $nama_m = $m['nama_metode'];
                                $tujuan_m = $m['nomor_tujuan'];
                                $status_m = trim($m['status'] ?? '');
                                $nama_pemilik = (!empty($status_m) && $status_m !== '-') ? ' (a.n ' . $status_m . ')' : ''; 
                                $is_img = (filter_var($tujuan_m, FILTER_VALIDATE_URL) || stripos($nama_m, 'qris') !== false);
                            ?>
                            <div style="background: rgba(255,255,255,0.03); padding: 8px; border-radius: 6px; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.08);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($nama_m); ?></strong><?php echo htmlspecialchars($nama_pemilik); ?>
                                    </div>
                                    <a href="admin_dashboard.php?hapus_metode=<?php echo $m['id']; ?>&tab=pembayaran" onclick="return confirm('Hapus metode ini?')" style="color: #ef4444; text-decoration: none; font-weight: bold; font-size: 10px;">Hapus</a>
                                </div>
                                <?php if ($is_img && filter_var($tujuan_m, FILTER_VALIDATE_URL)): ?>
                                    <div style="margin-top: 4px;">
                                        <img src="<?php echo htmlspecialchars($tujuan_m); ?>" alt="QRIS" style="max-width: 100px; border-radius: 4px; border: 1px solid #fff; display: block; margin-bottom: 4px;">
                                        <a href="<?php echo htmlspecialchars($tujuan_m); ?>" download target="_blank" style="color: #38bdf8; font-size: 10px; text-decoration: underline;">Download Gambar QRIS</a>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #cbd5e1; font-size: 10px;">Tujuan: <strong><?php echo htmlspecialchars($tujuan_m); ?></strong></div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #94a3b8; font-size: 10px;">Belum ada metode pembayaran.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- TAB 3: PRODUK -->
        <div id="tab-produk" class="tab-content <?php echo ($active_tab == 'produk') ? 'active' : ''; ?>">
            
            <!-- Tambah Produk Baru -->
            <div class="admin-card" style="margin-bottom: 16px;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Tambah Produk Baru</h3>
                
                <?php if (($_GET['err'] ?? '') === 'foto'): ?>
                    <div style="background: rgba(239,68,68,0.12); border: 1px solid #ef4444; color: #fca5a5; padding: 8px; border-radius: 6px; font-size: 10px; margin-bottom: 8px;">Foto gagal diunggah. Pilih foto (JPG/PNG/WEBP/GIF, maks 8MB) lalu coba lagi.<?php if (!empty($_GET['why'])): ?><br><b>Penyebab:</b> <?php echo htmlspecialchars(substr($_GET['why'], 0, 220)); ?><?php endif; ?></div>
                <?php endif; ?>
                <form action="" method="POST" enctype="multipart/form-data" id="formTambahProduk">
                    <div style="margin-bottom: 8px;">
                        <input type="text" name="nama_app" placeholder="Nama Aplikasi / Produk" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <div style="margin-bottom: 8px;">
                        <input type="number" name="harga" placeholder="Harga (Contoh: 15000)" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <div style="margin-bottom: 8px;">
                        <input type="number" name="stok" placeholder="Jumlah Stok" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <div style="margin-bottom: 8px;">
                        <label style="color: #cbd5e1; font-size: 10px; display: block; margin-bottom: 4px;">Foto Produk (pilih dari galeri):</label>
                        <input type="file" name="foto_file" id="fotoProdukInput" accept="image/*" required style="width: 100%; color: #cbd5e1; font-size: 11px;">
                        <img id="fotoProdukPreview" alt="" style="display: none; width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); margin-top: 8px;">
                    </div>
                    <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                        <input type="text" name="durasi" placeholder="Durasi (1 Bulan)" style="width: 50%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                        <input type="text" name="plan" placeholder="Plan (Sharing)" style="width: 50%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>
                    <div style="margin-bottom: 8px;">
                        <textarea name="deskripsi" placeholder="Deskripsi singkat produk..." rows="2" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;"></textarea>
                    </div>
                    <button type="submit" name="tambah_produk" class="btn-buy-mini" style="width: 100%; padding: 8px; font-size: 11px; cursor: pointer;">Tambah Produk</button>
                </form>
            </div>

            <!-- Daftar Produk & Edit Stok/Harga -->
            <div class="admin-card" style="margin-bottom: 16px;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Daftar Produk & Edit Stok/Harga</h3>
                
                <div style="font-size: 11px;">
                    <?php if ($produk_result && db_num_rows($produk_result) > 0): ?>
                        <?php while($prod = db_fetch_assoc($produk_result)): ?>
                            <div style="background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.08);">
                                
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px;">
                                    <img src="<?php echo htmlspecialchars($prod['foto_produk']); ?>" alt="" style="width: 45px; height: 45px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2);">
                                    <div style="flex-grow: 1;">
                                        <strong style="color: #fff; font-size: 12px; display: block;"><?php echo htmlspecialchars($prod['nama_app']); ?></strong>
                                        <span style="color: #38bdf8; font-size: 10px;"><?php echo htmlspecialchars($prod['durasi'] ?? ''); ?> <?php echo !empty($prod['plan']) ? '- '.htmlspecialchars($prod['plan']) : ''; ?></span>
                                    </div>
                                    <a href="admin_dashboard.php?hapus_produk=<?php echo $prod['id']; ?>&tab=produk" onclick="return confirm('Hapus produk ini?')" style="color: #ef4444; text-decoration: none; font-weight: bold; font-size: 10px; background: rgba(239, 68, 68, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid #ef4444;">Hapus</a>
                                </div>

                                <form action="" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                    <input type="hidden" name="produk_id" value="<?php echo $prod['id']; ?>">
                                    
                                    <div style="flex: 1;">
                                        <label style="font-size: 9px; color: #94a3b8; display: block;">Harga (Rp):</label>
                                        <input type="number" name="harga" value="<?php echo $prod['harga']; ?>" required style="width: 100%; padding: 4px 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; color: #fff; font-size: 11px;">
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <label style="font-size: 9px; color: #94a3b8; display: block;">Stok:</label>
                                        <input type="number" name="stok" value="<?php echo $prod['stok']; ?>" required style="width: 100%; padding: 4px 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; color: #fff; font-size: 11px;">
                                    </div>
                                    
                                    <div style="padding-top: 14px;">
                                        <button type="submit" name="update_produk" style="background: #34d399; color: #000; border: none; padding: 6px 10px; border-radius: 4px; font-weight: bold; font-size: 10px; cursor: pointer;">Update</button>
                                    </div>
                                </form>

                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #94a3b8; font-size: 10px; text-align: center;">Belum ada produk.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
<?php
require_once ALZZ_ROOT . '/diskon_lib.php';
alzz_diskon_ensure($conn);
$__diskon_result = db_query($conn, "SELECT * FROM produk ORDER BY id");
$__in = "width:100%;padding:6px 8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:11px;";
?>
        <!-- TAB: DISKON -->
        <div id="tab-diskon" class="tab-content <?php echo ($active_tab == 'diskon') ? 'active' : ''; ?>">
            <div class="admin-card" style="margin-bottom: 16px;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 4px;">Atur Diskon Produk</h3>
                <p style="color:#94a3b8;font-size:10px;margin-bottom:10px;">Pilih jenis diskon (persen atau potongan rupiah), isi nilainya, dan (opsional) batas waktunya. Setelah waktu habis, harga otomatis kembali normal.</p>

                <?php if (($_GET['ok'] ?? '') === 'simpan'): ?>
                    <div style="background:rgba(52,211,153,0.12);border:1px solid #34d399;color:#6ee7b7;padding:8px;border-radius:6px;font-size:10px;margin-bottom:8px;">Diskon berhasil disimpan.</div>
                <?php elseif (($_GET['ok'] ?? '') === 'hapus'): ?>
                    <div style="background:rgba(52,211,153,0.12);border:1px solid #34d399;color:#6ee7b7;padding:8px;border-radius:6px;font-size:10px;margin-bottom:8px;">Diskon dihapus. Harga kembali normal.</div>
                <?php elseif (($_GET['err'] ?? '') === 'nilai'): ?>
                    <div style="background:rgba(239,68,68,0.12);border:1px solid #ef4444;color:#fca5a5;padding:8px;border-radius:6px;font-size:10px;margin-bottom:8px;">Nilai diskon tidak valid. Persen: 1-99. Rupiah: harus lebih kecil dari harga produk.</div>
                <?php elseif (($_GET['err'] ?? '') === 'tanggal'): ?>
                    <div style="background:rgba(239,68,68,0.12);border:1px solid #ef4444;color:#fca5a5;padding:8px;border-radius:6px;font-size:10px;margin-bottom:8px;">Batas waktu harus di masa depan.</div>
                <?php endif; ?>

                <?php if ($__diskon_result && db_num_rows($__diskon_result) > 0): ?>
                    <?php while ($dp = db_fetch_assoc($__diskon_result)): ?>
                        <?php
                            $hi = alzz_harga_info($dp);
                            $tipe_now = (($dp['diskon_tipe'] ?? 'persen') === 'nominal') ? 'nominal' : 'persen';
                            $nilai_now = (int)($dp['diskon_nilai'] ?? 0);
                            $sampai_now = !empty($dp['diskon_sampai']) ? date('Y-m-d\TH:i', strtotime($dp['diskon_sampai'])) : '';
                        ?>
                        <div style="background:rgba(255,255,255,0.03);padding:10px;border-radius:8px;margin-bottom:10px;border:1px solid <?php echo $hi['aktif'] ? '#ef4444' : 'rgba(255,255,255,0.08)'; ?>;">
                            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">
                                <img src="<?php echo htmlspecialchars($dp['foto_produk']); ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                                <div style="flex:1;">
                                    <strong style="color:#fff;font-size:12px;display:block;"><?php echo htmlspecialchars($dp['nama_app']); ?></strong>
                                    <?php if ($hi['aktif']): ?>
                                        <span style="font-size:10px;color:#94a3b8;text-decoration:line-through;"><?php echo alzz_rp($hi['harga_asli']); ?></span>
                                        <span style="font-size:11px;color:#34d399;font-weight:bold;"> <?php echo alzz_rp($hi['harga_akhir']); ?></span>
                                        <span style="background:#ef4444;color:#fff;font-size:9px;padding:1px 6px;border-radius:6px;"><?php echo htmlspecialchars($hi['label']); ?></span>
                                        <?php if ($hi['sampai_teks'] !== ''): ?><div style="font-size:9px;color:#facc15;">Berakhir <?php echo htmlspecialchars($hi['sampai_teks']); ?></div><?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size:11px;color:#38bdf8;"><?php echo alzz_rp($hi['harga_asli']); ?></span>
                                        <span style="font-size:9px;color:#94a3b8;"> (tanpa diskon)</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form action="" method="POST">
                                <input type="hidden" name="produk_id" value="<?php echo $dp['id']; ?>">
                                <div style="display:flex;gap:6px;margin-bottom:6px;">
                                    <div style="flex:1;">
                                        <label style="font-size:9px;color:#94a3b8;display:block;">Jenis:</label>
                                        <select name="diskon_tipe" style="<?php echo $__in; ?>">
                                            <option value="persen" <?php echo $tipe_now === 'persen' ? 'selected' : ''; ?>>Persen (%)</option>
                                            <option value="nominal" <?php echo $tipe_now === 'nominal' ? 'selected' : ''; ?>>Potongan (Rp)</option>
                                        </select>
                                    </div>
                                    <div style="flex:1;">
                                        <label style="font-size:9px;color:#94a3b8;display:block;">Nilai:</label>
                                        <input type="number" name="diskon_nilai" min="1" value="<?php echo $nilai_now > 0 ? $nilai_now : ''; ?>" placeholder="20 atau 5000" required style="<?php echo $__in; ?>">
                                    </div>
                                </div>
                                <div style="margin-bottom:8px;">
                                    <label style="font-size:9px;color:#94a3b8;display:block;">Berlaku sampai (kosongkan = tanpa batas waktu):</label>
                                    <input type="datetime-local" name="diskon_sampai" value="<?php echo $sampai_now; ?>" style="<?php echo $__in; ?>">
                                </div>
                                <div style="display:flex;gap:6px;">
                                    <button type="submit" name="simpan_diskon" style="flex:1;background:#34d399;color:#000;border:none;padding:7px;border-radius:6px;font-weight:bold;font-size:11px;cursor:pointer;">Simpan Diskon</button>
                                    <?php if ($hi['aktif']): ?>
                                        <button type="submit" name="hapus_diskon" value="1" formnovalidate onclick="return confirm('Hapus diskon produk ini?')" style="background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid #ef4444;padding:7px 12px;border-radius:6px;font-weight:bold;font-size:11px;cursor:pointer;">Hapus</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#94a3b8;font-size:10px;text-align:center;">Belum ada produk. Tambahkan produk di tab Produk dulu.</p>
                <?php endif; ?>
            </div>
        </div>
        <!-- TAB 4: DAFTAR USER / BUYERS -->
        <div id="tab-users" class="tab-content <?php echo ($active_tab == 'users') ? 'active' : ''; ?>">
            <div class="admin-card" style="margin-bottom: 16px;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Daftar Akun Buyer Terdaftar</h3>
                
                <div style="font-size: 11px;">
                    <?php if ($buyers_result && db_num_rows($buyers_result) > 0): ?>
                        <?php while($user = db_fetch_assoc($buyers_result)): ?>
                            <div style="background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="color: #38bdf8; font-size: 12px; display: block;">👤 <?php echo htmlspecialchars($user['username']); ?></strong>
                                    <span style="color: #94a3b8; font-size: 9px;">Tgl Daftar: <?php echo $user['created_at']; ?></span>
                                </div>
                                <a href="admin_dashboard.php?hapus_user=<?php echo $user['id']; ?>&tab=users" onclick="return confirm('Hapus akun buyer ini?')" style="color: #ef4444; text-decoration: none; font-weight: bold; font-size: 10px; background: rgba(239, 68, 68, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid #ef4444;">Hapus</a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #94a3b8; font-size: 10px; text-align: center;">Belum ada buyer terdaftar.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- TOMBOL NAVIGASI BAWAH (BERBASIS URL PARAM) -->
    <div class="admin-nav-bar">
        <a href="admin_dashboard.php?tab=pendapatan" class="admin-nav-link <?php echo ($active_tab == 'pendapatan') ? 'active-tab' : ''; ?>">Pendapatan</a>
        <a href="admin_dashboard.php?tab=situs" class="admin-nav-link <?php echo ($active_tab == 'situs') ? 'active-tab' : ''; ?>">Situs & Pesanan</a>
        <a href="admin_dashboard.php?tab=pembayaran" class="admin-nav-link <?php echo ($active_tab == 'pembayaran') ? 'active-tab' : ''; ?>">Pembayaran</a>
        <a href="admin_dashboard.php?tab=produk" class="admin-nav-link <?php echo ($active_tab == 'produk') ? 'active-tab' : ''; ?>">Produk</a>
        <a href="admin_dashboard.php?tab=diskon" class="admin-nav-link <?php echo ($active_tab == 'diskon') ? 'active-tab' : ''; ?>">Diskon</a>
        <a href="admin_dashboard.php?tab=users" class="admin-nav-link <?php echo ($active_tab == 'users') ? 'active-tab' : ''; ?>">Daftar User</a>
    </div>

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
    <script src="admin.js?v=<?php echo filemtime(ALZZ_ROOT . '/admin.js'); ?>"
            onerror="alzzJsErr('admin.js gagal dimuat (file tidak ditemukan di server)')"></script>
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
