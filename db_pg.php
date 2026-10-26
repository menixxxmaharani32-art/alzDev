<?php
// ============================================================================
// db_pg.php - penghubung ke database PostgreSQL (Supabase)
// Kode lama memakai gaya db_*. Fungsi di sini bernama db_* dan bekerja
// dengan cara yang sama, tetapi bicara ke PostgreSQL. Isinya:
//   - koneksi (dibaca dari Environment Variable DATABASE_URL)
//   - db_query, db_fetch_assoc, db_num_rows, db_insert_id, db_real_escape_string
//   - penyimpanan sesi login ke database (karena server Vercel tidak menyimpan file)
// ============================================================================
date_default_timezone_set('Asia/Jakarta');

// Di belakang proxy Vercel, koneksi dari luar HTTPS tetapi ke server HTTP biasa
if (empty($_SERVER['HTTPS']) && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    $_SERVER['HTTPS'] = 'on';
}

class DbResult {
    public $rows = array();
    public $pos = 0;
    public $num_rows = 0;
}

function db_pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $url = getenv('DATABASE_URL');
    if (!$url) { $url = $_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? ''); }
    if (!$url) { $GLOBALS['__db_error'] = 'DATABASE_URL belum diisi'; return null; }
    $p = parse_url($url);
    if (!$p || empty($p['host']) || empty($p['user'])) { $GLOBALS['__db_error'] = 'DATABASE_URL tidak valid'; return null; }
    $dsn = 'pgsql:host=' . $p['host'] . ';port=' . (isset($p['port']) ? (int)$p['port'] : 5432)
         . ';dbname=' . ltrim($p['path'] ?? '/postgres', '/') . ';sslmode=require';
    try {
        $pdo = new PDO($dsn, urldecode($p['user']), urldecode($p['pass'] ?? ''), array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
            PDO::ATTR_EMULATE_PREPARES   => true,   // wajib untuk Supabase pooler (tanpa prepared statement di server)
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 10,
            PDO::ATTR_PERSISTENT         => true,   // pakai ulang koneksi (jauh lebih cepat)
        ));
    } catch (Throwable $e) {
        error_log('[db] koneksi gagal: ' . $e->getMessage());
        $GLOBALS['__db_error'] = 'tidak bisa terhubung ke database';
        $pdo = null;
    }
    return $pdo;
}

function db_connect() { return db_pdo(); }
function db_connect_error() { return $GLOBALS['__db_error'] ?? ''; }

function db_real_escape_string($conn, $s) {
    return str_replace("'", "''", str_replace("\0", '', (string)$s));
}

function db_query($conn, $sql) {
    $pdo = db_pdo();
    if (!$pdo) return false;
    // INSERT ke tabel ber-id: minta id baru kembali (pengganti db_insert_id)
    $auto = false;
    if (preg_match('/^\s*INSERT\s+INTO\s+(admin|buyers|produk|metode_pembayaran|pesanan|admin_tokens|push_subs|push_events)\b/i', $sql)
        && stripos($sql, 'RETURNING') === false) {
        $sql = rtrim(rtrim($sql), ';') . ' RETURNING id';
        $auto = true;
    }
    $st = $pdo->query($sql);
    if ($st === false) {
        $e = $pdo->errorInfo();
        error_log('[db] ' . ($e[2] ?? '') . ' | ' . substr($sql, 0, 300));
        $GLOBALS['__db_error'] = $e[2] ?? 'query gagal';
        return false;
    }
    if ($auto) {
        $row = $st->fetch();
        $GLOBALS['__db_last_id'] = $row ? (int)$row['id'] : 0;
        return true;
    }
    if ($st->columnCount() > 0) {
        $r = new DbResult();
        $r->rows = $st->fetchAll();
        $r->num_rows = count($r->rows);
        return $r;
    }
    return true;
}

function db_fetch_assoc($res) {
    if (!($res instanceof DbResult) || $res->pos >= $res->num_rows) return null;
    return $res->rows[$res->pos++];
}
function db_num_rows($res) { return ($res instanceof DbResult) ? $res->num_rows : 0; }
function db_data_seek($res, $n) { if ($res instanceof DbResult) { $res->pos = max(0, (int)$n); } return true; }
function db_insert_id($conn = null) { return (int)($GLOBALS['__db_last_id'] ?? 0); }

// ---------- Sesi login disimpan di tabel php_sessions ----------
class AlzzDbSessionHandler implements SessionHandlerInterface {
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false {
        $pdo = db_pdo(); if (!$pdo) return '';
        $st = $pdo->query("SELECT data FROM php_sessions WHERE id = " . $pdo->quote($id));
        $row = $st ? $st->fetch() : false;
        if (!$row) return '';
        $d = base64_decode($row['data'], true);
        return $d === false ? '' : $d;
    }
    public function write(string $id, string $data): bool {
        $pdo = db_pdo(); if (!$pdo) return false;
        $q = $pdo->quote($id); $d = $pdo->quote(base64_encode($data));
        $sql = "INSERT INTO php_sessions (id, data, updated_at) VALUES ($q, $d, (NOW() AT TIME ZONE 'Asia/Jakarta'))
                ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = EXCLUDED.updated_at";
        $r = $pdo->exec($sql);
        if ($r === false) {
            // Kemungkinan tabel php_sessions belum dibuat -> buat otomatis lalu coba lagi
            $pdo->exec("CREATE TABLE IF NOT EXISTS php_sessions (
                id varchar(128) primary key,
                data text not null default '',
                updated_at timestamp not null default (now() at time zone 'Asia/Jakarta'))");
            $r = $pdo->exec($sql);
            if ($r === false) {
                $e = $pdo->errorInfo();
                error_log('[session] gagal menyimpan sesi: ' . ($e[2] ?? ''));
            }
        }
        return $r !== false;
    }
    public function destroy(string $id): bool {
        $pdo = db_pdo(); if (!$pdo) return false;
        $pdo->exec("DELETE FROM php_sessions WHERE id = " . $pdo->quote($id));
        return true;
    }
    public function gc(int $max_lifetime): int|false {
        $pdo = db_pdo(); if (!$pdo) return 0;
        $n = $pdo->exec("DELETE FROM php_sessions WHERE updated_at < ((NOW() AT TIME ZONE 'Asia/Jakarta') - INTERVAL '" . (int)$max_lifetime . " seconds')");
        return $n === false ? 0 : $n;
    }
}

function db_register_session_handler() {
    static $done = false;
    if ($done) return;
    $done = true;
    if (session_status() === PHP_SESSION_NONE && db_pdo()) {
        session_set_save_handler(new AlzzDbSessionHandler(), true);
    }
}
