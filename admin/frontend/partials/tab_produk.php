<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
?>
        <!-- TAB 3: PRODUK -->
        <div id="tab-produk" class="tab-content <?php echo ($active_tab == 'produk') ? 'active' : ''; ?>">
            
            <!-- Tambah Produk Baru -->
            <div class="admin-card" style="margin-bottom: 16px;">
                <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px;">Tambah Produk Baru</h3>
                
                <?php if (($_GET['err'] ?? '') === 'foto'): ?>
                    <div style="background: rgba(239,68,68,0.12); border: 1px solid #ef4444; color: #fca5a5; padding: 8px; border-radius: 6px; font-size: 10px; margin-bottom: 8px;">Foto gagal diunggah. Pilih foto (JPG/PNG/WEBP/GIF, maks 8MB) lalu coba lagi.</div>
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
