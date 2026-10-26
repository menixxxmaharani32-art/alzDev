<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
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
