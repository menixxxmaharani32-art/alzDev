<?php
// ===== WEB PUSH (notifikasi ke layar HP, walau Chrome ditutup) =====
// Tanpa library tambahan. Memakai VAPID + payload terenkripsi (RFC 8291): judul & isi notifikasi ikut dikirim langsung,
// jadi notifikasi muncul sendiri tanpa harus dipancing lewat push test. Jika HP belum punya kunci enkripsi,
// otomatis memakai cara lama (push kosong, isi diambil sw.js dari push_api.php).

function alzz_push_tables($conn) {
    // Tabel push_* sudah dibuat lewat SQL di Supabase
    return;
}

function alzz_b64u($bin) { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }

// Ambil / buat kunci VAPID (disimpan di database, dibuat otomatis sekali)
function alzz_vapid($conn) {
    alzz_push_tables($conn);
    $r = db_query($conn, "SELECT k, v FROM push_config WHERE k IN ('vapid_private','vapid_public')");
    $cfg = [];
    while ($r && ($row = db_fetch_assoc($r))) $cfg[$row['k']] = $row['v'];
    if (!empty($cfg['vapid_private']) && !empty($cfg['vapid_public'])) {
        return ['private' => $cfg['vapid_private'], 'public' => $cfg['vapid_public']];
    }
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) return null;
    openssl_pkey_export($key, $pem);
    $d = openssl_pkey_get_details($key);
    $x = str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $pub = alzz_b64u("\x04" . $x . $y);
    $p1 = db_real_escape_string($conn, $pem);
    $p2 = db_real_escape_string($conn, $pub);
    db_query($conn, "INSERT INTO push_config (k, v) VALUES ('vapid_private', '$p1'), ('vapid_public', '$p2') ON CONFLICT (k) DO UPDATE SET v = EXCLUDED.v");
    return ['private' => $pem, 'public' => $pub];
}

// Tanda tangan DER (ECDSA) -> format mentah R||S 64 byte untuk JWT ES256
function alzz_der_to_raw($der) {
    $o = 2;
    if (ord($der[1]) & 0x80) $o += (ord($der[1]) & 0x7f);
    $rl = ord($der[$o + 1]);
    $r = substr($der, $o + 2, $rl);
    $o2 = $o + 2 + $rl;
    $sl = ord($der[$o2 + 1]);
    $s = substr($der, $o2 + 2, $sl);
    $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    return $r . $s;
}

function alzz_vapid_header($vapid, $endpoint) {
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $head = alzz_b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = alzz_b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:admin@alzzstore.local']));
    $data = $head . '.' . $claims;
    openssl_sign($data, $der, $vapid['private'], OPENSSL_ALGO_SHA256);
    $jwt = $data . '.' . alzz_b64u(alzz_der_to_raw($der));
    return 'vapid t=' . $jwt . ', k=' . $vapid['public'];
}

function alzz_b64u_dec($s) { return base64_decode(strtr($s, '-_', '+/')); }

// Enkripsi payload untuk 1 HP (RFC 8291, aes128gcm). Return isi body push, atau null jika gagal/tidak didukung
function alzz_push_encrypt($payload, $p256dh_b64u, $auth_b64u) {
    try {
        if (!function_exists('openssl_pkey_derive') || !function_exists('openssl_encrypt')) return null;
        $uaPub = alzz_b64u_dec($p256dh_b64u);
        $auth  = alzz_b64u_dec($auth_b64u);
        if (strlen($uaPub) !== 65 || strlen($auth) < 8) return null;

        // Kunci sementara milik server
        $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$eph) return null;
        $d = openssl_pkey_get_details($eph);
        $asPub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);

        // Kunci publik HP -> resource OpenSSL
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $uaPub;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $peer = openssl_pkey_get_public($pem);
        if (!$peer) return null;
        $secret = openssl_pkey_derive($peer, $eph);
        if ($secret === false) return null;
        $secret = str_pad($secret, 32, "\0", STR_PAD_LEFT);

        $ikm   = hash_hmac('sha256', "WebPush: info\0" . $uaPub . $asPub . "\x01", hash_hmac('sha256', $secret, $auth, true), true);
        $salt  = random_bytes(16);
        $prk   = hash_hmac('sha256', $ikm, $salt, true);
        $cek   = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        $tag = '';
        $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ct === false) return null;
        return $salt . pack('N', 4096) . chr(65) . $asPub . $ct . $tag;
    } catch (Throwable $e) {
        return null;
    }
}

// Kirim 1 push ke 1 endpoint. $payload (array) + kunci HP => terenkripsi; tanpa itu => push kosong.
// Return kode HTTP (0 jika gagal koneksi)
function alzz_push_send($vapid, $endpoint, $payload = null, $p256dh = '', $auth = '') {
    $body = '';
    $headers = [
        'Authorization: ' . alzz_vapid_header($vapid, $endpoint),
        'TTL: 86400',
        'Urgency: high',
    ];
    if ($payload !== null && $p256dh !== '' && $auth !== '') {
        $enc = alzz_push_encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE), $p256dh, $auth);
        if ($enc !== null) {
            $body = $enc;
            $headers[] = 'Content-Encoding: aes128gcm';
            $headers[] = 'Content-Type: application/octet-stream';
        }
    }
    $headers[] = 'Content-Length: ' . strlen($body);
    $GLOBALS['alzz_push_mode'] = ($body !== '') ? 'terenkripsi' : 'kosong';

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $GLOBALS['alzz_push_last'] = ['code' => $code, 'error' => curl_error($ch), 'body' => is_string($resp) ? substr($resp, 0, 300) : ''];
        curl_close($ch);
        return $code;
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 6, 'ignore_errors' => true]]);
    @file_get_contents($endpoint, false, $ctx);
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) return (int)$m[1];
    return 0;
}

// Catat kejadian + kirim push ke semua HP admin yang sudah mengaktifkan notifikasi
function alzz_push_event($conn, $type, $title, $body, $url) {
    try {
        alzz_push_tables($conn);
        $t = db_real_escape_string($conn, mb_substr($title, 0, 190));
        $b = db_real_escape_string($conn, mb_substr($body, 0, 480));
        $u = db_real_escape_string($conn, $url);
        $ty = db_real_escape_string($conn, $type);
        db_query($conn, "DELETE FROM push_events WHERE created_at < ((NOW() AT TIME ZONE 'Asia/Jakarta') - INTERVAL '2 days')");
        db_query($conn, "INSERT INTO push_events (type, title, body, url, created_at) VALUES ('$ty', '$t', '$b', '$u', (NOW() AT TIME ZONE 'Asia/Jakarta'))");
        $event_id = db_insert_id($conn);
        $subs = db_query($conn, "SELECT id, endpoint, p256dh, auth FROM push_subs");
        if (!$subs || db_num_rows($subs) === 0) return;
        $vapid = alzz_vapid($conn);
        if (!$vapid) return;
        $payload = ['id' => $event_id, 'type' => $type, 'title' => mb_substr($title, 0, 190), 'body' => mb_substr($body, 0, 300), 'url' => $url];
        while ($s = db_fetch_assoc($subs)) {
            $code = alzz_push_send($vapid, $s['endpoint'], $payload, (string)$s['p256dh'], (string)$s['auth']);
            if ($code === 404 || $code === 410) {
                db_query($conn, "DELETE FROM push_subs WHERE id = " . intval($s['id']));
            }
        }
    } catch (Throwable $e) {
        // Jangan pernah menggagalkan proses pesanan/pendaftaran karena notifikasi
    }
}
