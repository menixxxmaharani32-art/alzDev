<?php
// Halaman diagnosa upload foto ke Supabase Storage (hanya admin). Buka: /storage_test.php
// HAPUS file ini dari GitHub setelah masalah selesai.
require_once 'auth_guard.php';
require_once 'config.php';
require_once 'storage_pg.php';
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['admin_logged'])) { echo 'Login admin dulu.'; exit; }

function ok($b) { return $b ? '✅' : '❌'; }
$url    = rtrim((string)getenv('SUPABASE_URL'), '/');
$key    = (string)getenv('SUPABASE_SERVICE_KEY');
$bucket = getenv('SUPABASE_BUCKET') ?: 'uploads';

// Jenis kunci (tanpa menampilkan kunci itu sendiri)
$jenis = 'tidak diisi';
$kunci_benar = false;
if ($key !== '') {
    if (strpos($key, 'sb_secret_') === 0) { $jenis = 'secret (benar)'; $kunci_benar = true; }
    elseif (strpos($key, 'sb_publishable_') === 0) { $jenis = 'publishable (SALAH, pakai yang secret)'; }
    elseif (substr_count($key, '.') === 2) {
        $pl = json_decode(base64_decode(strtr(explode('.', $key)[1], '-_', '+/')), true);
        $role = $pl['role'] ?? '?';
        $jenis = 'JWT role=' . $role . ($role === 'service_role' ? ' (benar)' : ' (SALAH, harus service_role)');
        $kunci_benar = ($role === 'service_role');
    } else { $jenis = 'format tidak dikenal'; }
}

// Cek bucket
$bucket_info = '-'; $bucket_ok = false;
if ($url !== '' && $key !== '') {
    $ch = curl_init($url . '/storage/v1/bucket/' . rawurlencode($bucket));
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $key, 'apikey: ' . $key)));
    $r = curl_exec($ch); $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode((string)$r, true);
    $bucket_ok = ($c === 200);
    $bucket_info = 'HTTP ' . $c . ($bucket_ok ? (' - public: ' . (!empty($j['public']) ? 'ya' : 'TIDAK')) : (' - ' . substr((string)$r, 0, 200)));
}

// Coba upload file kecil
$tmp = tempnam(sys_get_temp_dir(), 'tes');
file_put_contents($tmp, 'tes upload ' . date('c'));
$hasil = alzz_storage_upload($tmp, 'tes/tes_' . time() . '.txt', 'text/plain');
@unlink($tmp);
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tes Storage</title>
<style>body{font-family:sans-serif;background:#0d1117;color:#fff;padding:14px;font-size:14px;line-height:1.6}code{color:#38bdf8;word-break:break-all}</style></head><body>
<h3>Diagnosa Upload ke Supabase Storage</h3>
<p><?= ok($url !== '') ?> SUPABASE_URL: <code><?= htmlspecialchars($url ?: 'belum diisi') ?></code></p>
<p><?= ok($key !== '') ?> SUPABASE_SERVICE_KEY terisi</p>
<p><?= ok($kunci_benar) ?> Jenis kunci: <code><?= htmlspecialchars($jenis) ?></code></p>
<p><?= ok(function_exists('curl_init')) ?> cURL tersedia</p>
<p><?= ok($bucket_ok) ?> Bucket <code><?= htmlspecialchars($bucket) ?></code>: <code><?= htmlspecialchars($bucket_info) ?></code></p>
<p><?= ok($hasil !== '') ?> Tes upload file kecil
<?php if ($hasil !== ''): ?>
    berhasil<br><code><?= htmlspecialchars($hasil) ?></code>
<?php else: ?>
    GAGAL<br><code><?= htmlspecialchars($GLOBALS['__storage_last'] ?? 'tidak ada keterangan') ?></code>
<?php endif; ?></p>
<p style="color:#94a3b8;font-size:12px">Batas ukuran upload PHP: <?= htmlspecialchars(ini_get('upload_max_filesize')) ?> / post <?= htmlspecialchars(ini_get('post_max_size')) ?></p>
</body></html>
