<?php
// ============================================================================
// storage_pg.php - simpan file upload (foto produk, bukti bayar, musik) ke Supabase Storage
// Server Vercel tidak menyimpan file permanen, jadi file dikirim ke Supabase.
// Butuh Environment Variables: SUPABASE_URL, SUPABASE_SERVICE_KEY (opsional: SUPABASE_BUCKET)
// ============================================================================
function alzz_mime_file($path) {
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if ($m) return $m;
    }
    return 'application/octet-stream';
}

// Mengembalikan alamat (URL) publik file, atau '' kalau gagal
function alzz_storage_upload($tmpPath, $objectName, $mime = '') {
    $base   = rtrim((string)getenv('SUPABASE_URL'), '/');
    $key    = (string)getenv('SUPABASE_SERVICE_KEY');
    $bucket = getenv('SUPABASE_BUCKET') ?: 'uploads';
    if ($base === '' || $key === '' || !is_file($tmpPath) || !function_exists('curl_init')) {
        error_log('[storage] konfigurasi belum lengkap');
        $GLOBALS['__storage_last'] = 'Konfigurasi belum lengkap (SUPABASE_URL / SUPABASE_SERVICE_KEY / cURL)';
        return '';
    }
    if ($mime === '') { $mime = alzz_mime_file($tmpPath); }
    $enc = implode('/', array_map('rawurlencode', explode('/', $objectName)));
    $ch = curl_init($base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . $enc);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => file_get_contents($tmpPath),
        CURLOPT_HTTPHEADER     => array('Authorization: Bearer ' . $key, 'apikey: ' . $key, 'Content-Type: ' . $mime, 'x-upsert: false'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ));
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        return $base . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . $enc;
    }
    $GLOBALS['__storage_last'] = 'HTTP ' . $code . ': ' . substr((string)$resp, 0, 300);
    error_log('[storage] upload gagal (' . $code . '): ' . substr((string)$resp, 0, 200));
    return '';
}

// Minta alamat upload sementara (signed URL) ke Supabase supaya browser bisa mengunggah file besar LANGSUNG
// ke Supabase Storage, tanpa lewat server Vercel (server Vercel membatasi ukuran request sekitar 4,5MB).
// Mengembalikan ['upload_url' => ..., 'public_url' => ...] atau null kalau gagal.
function alzz_storage_signed_upload($objectName) {
    $base   = rtrim((string)getenv('SUPABASE_URL'), '/');
    $key    = (string)getenv('SUPABASE_SERVICE_KEY');
    $bucket = getenv('SUPABASE_BUCKET') ?: 'uploads';
    if ($base === '' || $key === '' || !function_exists('curl_init')) {
        $GLOBALS['__storage_last'] = 'Konfigurasi belum lengkap (SUPABASE_URL / SUPABASE_SERVICE_KEY / cURL)';
        return null;
    }
    $enc = implode('/', array_map('rawurlencode', explode('/', $objectName)));
    $ch = curl_init($base . '/storage/v1/object/upload/sign/' . rawurlencode($bucket) . '/' . $enc);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '{}',
        CURLOPT_HTTPHEADER     => array('Authorization: Bearer ' . $key, 'apikey: ' . $key, 'Content-Type: application/json'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ));
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string)$resp, true);
    if ($code >= 200 && $code < 300 && !empty($j['url'])) {
        $u = (string)$j['url'];
        if (strpos($u, 'http') !== 0) {
            if (strpos($u, '/storage/v1') !== 0) { $u = '/storage/v1' . (($u[0] ?? '') === '/' ? '' : '/') . $u; }
            $u = $base . $u;
        }
        return array(
            'upload_url' => $u,
            'public_url' => $base . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . $enc,
        );
    }
    $GLOBALS['__storage_last'] = 'HTTP ' . $code . ': ' . substr((string)$resp, 0, 300);
    error_log('[storage] signed url gagal (' . $code . '): ' . substr((string)$resp, 0, 200));
    return null;
}
