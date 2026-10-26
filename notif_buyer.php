<?php
// ===== NOTIFIKASI PESAN ADMIN UNTUK PEMBELI (angka di tombol Riwayat) =====
// Kolom pesanan.notif_buyer = 1 artinya admin baru memperbarui pesanan & pembeli belum melihatnya.
function alzz_ensure_notif_col($conn) {
    // Kolom notif_buyer sudah dibuat lewat SQL di Supabase
    return;
}

function alzz_buyer_notif_count($conn, $username) {
    alzz_ensure_notif_col($conn);
    $u = db_real_escape_string($conn, $username);
    $r = db_query($conn, "SELECT COUNT(*) AS n FROM pesanan WHERE username = '$u' AND notif_buyer = 1");
    if ($r && ($d = db_fetch_assoc($r))) return intval($d['n']);
    return 0;
}

function alzz_notif_badge($n) {
    $show = $n > 0 ? 'inline-block' : 'none';
    $txt = $n > 99 ? '99+' : $n;
    return '<span class="notif-count" style="display:' . $show . ';background:#ef4444;color:#fff;font-size:9px;font-weight:bold;min-width:15px;height:15px;line-height:15px;padding:0 4px;box-sizing:border-box;border-radius:8px;text-align:center;margin-left:4px;vertical-align:middle;">' . $txt . '</span>';
}
