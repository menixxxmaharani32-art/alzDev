<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
?>
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
