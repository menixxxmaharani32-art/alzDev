<?php
// Halaman diagnosa notifikasi push (hanya admin). Buka: push_test.php
require_once 'auth_guard.php';
require_once 'config.php';
require_once 'push_lib.php';
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['admin_logged'])) { echo 'Login admin dulu.'; exit; }

function ok($b) { return $b ? '✅' : '❌'; }
alzz_push_tables($conn);
$vapid = alzz_vapid($conn);
$subs = db_query($conn, "SELECT id, endpoint, p256dh, auth, created_at FROM push_subs");
$n = $subs ? db_num_rows($subs) : 0;
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tes Push</title>
<style>body{font-family:sans-serif;background:#0d1117;color:#fff;padding:14px;font-size:14px}code{color:#38bdf8;word-break:break-all}.b{background:#38bdf8;color:#000;padding:10px 14px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;margin:6px 0}</style></head><body>
<h3>Diagnosa Notifikasi Push</h3>
<p><?= ok(!empty($_SERVER['HTTPS'])) ?> HTTPS aktif</p>
<p><?= ok(function_exists('openssl_sign')) ?> OpenSSL tersedia</p>
<p><?= ok(function_exists('curl_init')) ?> cURL tersedia</p>
<p><?= ok($vapid) ?> Kunci VAPID dibuat <?= $vapid ? '<br><code>' . htmlspecialchars($vapid['public']) . '</code>' : '' ?></p>
<p><?= ok($n > 0) ?> HP terdaftar (push_subs): <b><?= $n ?></b></p>
<?php if ($n === 0): ?>
<p>Belum ada HP terdaftar. Buka <b>admin_dashboard.php</b> di Chrome HP, ketuk 🔔, pilih <b>Izinkan</b>, lalu kembali ke sini.</p>
<?php endif; ?>

<?php if ($n > 0 && $vapid): ?>
<p><a class="b" href="push_test.php?kirim=1">Kirim notifikasi tes</a></p>
<?php endif; ?>

<?php
if (isset($_GET['kirim']) && $n > 0 && $vapid) {
    $t = db_real_escape_string($conn, '🔔 Tes notifikasi');
    db_query($conn, "INSERT INTO push_events (type, title, body, url, created_at) VALUES ('tes', '$t', 'Kalau ini muncul di layar HP, push berhasil.', 'admin_dashboard.php', (NOW() AT TIME ZONE 'Asia/Jakarta'))");
    $tes_payload = ['id' => db_insert_id($conn), 'type' => 'tes', 'title' => '🔔 Tes notifikasi', 'body' => 'Kalau ini muncul di layar HP, push berhasil.', 'url' => 'admin_dashboard.php'];
    db_data_seek($subs, 0);
    echo '<h4>Hasil kirim:</h4>';
    while ($s = db_fetch_assoc($subs)) {
        $host = parse_url($s['endpoint'], PHP_URL_HOST);
        $code = alzz_push_send($vapid, $s['endpoint'], $tes_payload, (string)$s['p256dh'], (string)$s['auth']);
        $last = $GLOBALS['alzz_push_last'] ?? [];
        if ($code === 404 || $code === 410) {
            db_query($conn, "DELETE FROM push_subs WHERE id = " . intval($s['id']));
            echo '<p>⚠️ Langganan HP ini sudah mati dan dihapus. Buka admin_dashboard.php, ketuk 🔔 untuk mendaftar ulang.</p>';
        }
        echo '<p>Tujuan: <code>' . htmlspecialchars($host) . '</code><br>';
        echo 'Mode: <b>' . ($GLOBALS['alzz_push_mode'] ?? '-') . '</b><br>';
        echo 'Kode HTTP: <b>' . $code . '</b> ' . ok($code >= 200 && $code < 300) . '<br>';
        if (!empty($last['error'])) echo 'Error cURL: <code>' . htmlspecialchars($last['error']) . '</code><br>';
        if (!empty($last['body'])) echo 'Balasan: <code>' . htmlspecialchars($last['body']) . '</code><br>';
        echo '</p>';
    }
    echo '<p><small>201 = diterima Google (notifikasi seharusnya muncul). 0 = server hosting memblokir koneksi keluar. 400/401/403 = kunci/tanda tangan ditolak. 404/410 = langganan HP kedaluwarsa (ketuk 🔔 lagi).</small></p>';
}
?>
</body></html>
