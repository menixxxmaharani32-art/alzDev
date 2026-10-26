<?php
// API kecil untuk notifikasi push (hanya admin yang sudah login)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once 'auth_guard.php';
require_once 'config.php';
require_once 'push_lib.php';

if (empty($_SESSION['admin_logged'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
alzz_push_tables($conn);
$action = $_GET['action'] ?? '';

if ($action === 'key') {
    $v = alzz_vapid($conn);
    echo json_encode(['key' => $v ? $v['public'] : null]);
    exit;
}

if ($action === 'subscribe' || $action === 'unsubscribe') {
    $in = json_decode(file_get_contents('php://input'), true);
    $endpoint = $in['endpoint'] ?? '';
    if (!is_string($endpoint) || strpos($endpoint, 'https://') !== 0) {
        http_response_code(400);
        echo json_encode(['error' => 'endpoint tidak valid']);
        exit;
    }
    $hash = hash('sha256', $endpoint);
    $ep = db_real_escape_string($conn, $endpoint);
    if ($action === 'subscribe') {
        // Kunci enkripsi HP (agar isi notifikasi bisa dikirim langsung)
        $k = isset($in['keys']) && is_array($in['keys']) ? $in['keys'] : [];
        $p256 = (isset($k['p256dh']) && is_string($k['p256dh']) && preg_match('/^[A-Za-z0-9_-]{80,100}$/', $k['p256dh'])) ? $k['p256dh'] : '';
        $au   = (isset($k['auth']) && is_string($k['auth']) && preg_match('/^[A-Za-z0-9_-]{8,40}$/', $k['auth'])) ? $k['auth'] : '';
        db_query($conn, "INSERT INTO push_subs (endpoint_hash, endpoint, p256dh, auth, created_at) VALUES ('$hash', '$ep', '$p256', '$au', (NOW() AT TIME ZONE 'Asia/Jakarta')) ON CONFLICT (endpoint_hash) DO UPDATE SET endpoint = EXCLUDED.endpoint, p256dh = EXCLUDED.p256dh, auth = EXCLUDED.auth, created_at = EXCLUDED.created_at");
    } else {
        db_query($conn, "DELETE FROM push_subs WHERE endpoint_hash = '$hash'");
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'events') {
    $since = intval($_GET['since'] ?? 0);
    $where = $since > 0 ? "id > $since" : "created_at > ((NOW() AT TIME ZONE 'Asia/Jakarta') - INTERVAL '10 minutes')";
    $r = db_query($conn, "SELECT id, type, title, body, url FROM push_events WHERE $where ORDER BY id ASC LIMIT 10");
    $out = [];
    while ($r && ($row = db_fetch_assoc($r))) { $row['id'] = intval($row['id']); $out[] = $row; }
    echo json_encode(['events' => $out]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'action tidak dikenal']);
