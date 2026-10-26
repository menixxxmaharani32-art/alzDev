<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
?>
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
