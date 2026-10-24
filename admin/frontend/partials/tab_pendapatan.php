<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
// FRONTEND: tab Pendapatan (hanya tampilan; angka dihitung di backend/pendapatan.php)

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
