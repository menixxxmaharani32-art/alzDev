<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
?>
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
