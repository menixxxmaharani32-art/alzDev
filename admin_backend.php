<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
// ============================================================================
// BACKEND DASHBOARD ADMIN (gabungan notif + actions + pendapatan + data)
// Urutan kerja sama seperti sebelumnya: notifikasi (JSON) -> proses simpan/ubah/hapus -> hitung pendapatan -> ambil data tampilan
// ============================================================================

// ---------------------------------------------------------------- [notif]
// BACKEND: jawaban JSON untuk notifikasi (dipanggil otomatis oleh JavaScript dashboard)
// ===== ENDPOINT NOTIFIKASI (dipanggil otomatis oleh JavaScript dashboard) =====
if (isset($_GET['notif'])) {
    header('Content-Type: application/json; charset=utf-8');
    $so = intval($_GET['o'] ?? 0); // id pesanan terakhir yang sudah dilihat
    $su = intval($_GET['u'] ?? 0); // id user terakhir yang sudah dilihat
    $out = ['max_order'=>0,'max_user'=>0,'new_orders'=>0,'new_users'=>0,'pending'=>0,'stok_habis'=>0,
            'last_order'=>null,'last_user'=>null,'habis_nama'=>''];

    $r = db_query($conn, "SELECT MAX(id) AS m, COALESCE(SUM((id > $so)::int), 0) AS n, COALESCE(SUM((status = 'Pending')::int), 0) AS p FROM pesanan");
    if ($r && ($d = db_fetch_assoc($r))) {
        $out['max_order'] = intval($d['m']); $out['new_orders'] = intval($d['n']); $out['pending'] = intval($d['p']);
    }
    $r = db_query($conn, "SELECT p.id, p.username, p.nama, pr.nama_app FROM pesanan p LEFT JOIN produk pr ON p.produk_id = pr.id ORDER BY p.id DESC LIMIT 1");
    if ($r && ($d = db_fetch_assoc($r))) {
        $out['last_order'] = ['id'=>intval($d['id']), 'user'=>($d['username'] ?: ($d['nama'] ?: 'Guest')), 'produk'=>($d['nama_app'] ?? '')];
    }
    $r = db_query($conn, "SELECT MAX(id) AS m, COALESCE(SUM((id > $su)::int), 0) AS n FROM buyers");
    if ($r && ($d = db_fetch_assoc($r))) {
        $out['max_user'] = intval($d['m']); $out['new_users'] = intval($d['n']);
    }
    $r = db_query($conn, "SELECT username FROM buyers ORDER BY id DESC LIMIT 1");
    if ($r && ($d = db_fetch_assoc($r))) { $out['last_user'] = $d['username']; }
    $r = db_query($conn, "SELECT COUNT(*) AS n, STRING_AGG(nama_app, ', ') AS nm FROM produk WHERE stok <= 0");
    if ($r && ($d = db_fetch_assoc($r))) { $out['stok_habis'] = intval($d['n']); $out['habis_nama'] = (string)$d['nm']; }

    echo json_encode($out);
    exit;
}

// ---------------------------------------------------------------- [actions]
// BACKEND: semua proses simpan / ubah / hapus (pesanan, produk, pembayaran, pengaturan, user)
// Stok produk berkurang 1 otomatis saat pesanan pertama kali berstatus Dikonfirmasi / Selesai.
// Kolom pesanan.stok_dikurangi menandai pesanan yang stoknya sudah dikurangi, jadi tidak pernah terpotong dua kali
// (mis. status bolak-balik atau disimpan ulang). Kolom dibuat otomatis bila belum ada (SQL manualnya ada di penjelasan).
function alzz_kurangi_stok_pesanan($conn, $id_pesanan) {
    $id_pesanan = intval($id_pesanan);
    $r = db_query($conn, "SELECT 1 FROM information_schema.columns WHERE table_name = 'pesanan' AND column_name = 'stok_dikurangi' LIMIT 1");
    if (!($r && db_num_rows($r) > 0)) {
        db_query($conn, "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS stok_dikurangi boolean NOT NULL DEFAULT false");
        // Pesanan lama yang sudah dikonfirmasi dianggap stoknya sudah diatur manual (tidak dikurangi lagi)
        db_query($conn, "UPDATE pesanan SET stok_dikurangi = true WHERE status IN ('Dikonfirmasi', 'Selesai') AND id <> $id_pesanan");
    }
    // Satu perintah atomik: tandai pesanan lalu kurangi stok produknya 1 (tidak turun di bawah 0)
    db_query($conn, "WITH f AS (UPDATE pesanan SET stok_dikurangi = true WHERE id = $id_pesanan AND stok_dikurangi = false RETURNING produk_id)
                     UPDATE produk SET stok = GREATEST(stok - 1, 0) WHERE id IN (SELECT produk_id FROM f)");
}

// 1. Proses Update Pesanan
if (isset($_POST['update_pesanan'])) {
    $id_pesanan = intval($_POST['pesanan_id']);
    $status = db_real_escape_string($conn, $_POST['status']);
    $data_pesanan = db_real_escape_string($conn, $_POST['data_pesanan']);
    $cara_login = db_real_escape_string($conn, $_POST['cara_login'] ?? '');

    // Pastikan kolom cara_login ada di tabel pesanan (dibuat otomatis sekali saja)
    db_query($conn, "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS cara_login text");
    
    // Cek apakah admin benar-benar mengubah sesuatu (untuk memunculkan angka notifikasi di riwayat pembeli)
    require_once ALZZ_ROOT . '/notif_buyer.php';
    alzz_ensure_notif_col($conn);
    $old_row = db_fetch_assoc(db_query($conn, "SELECT status, data_pesanan, cara_login FROM pesanan WHERE id = $id_pesanan"));
    $berubah = !$old_row
        || trim((string)$old_row['status']) !== trim($_POST['status'])
        || trim((string)$old_row['data_pesanan']) !== trim($_POST['data_pesanan'])
        || trim((string)($old_row['cara_login'] ?? '')) !== trim($_POST['cara_login'] ?? '');

    $update_query = "UPDATE pesanan SET status = '$status', data_pesanan = '$data_pesanan', cara_login = '$cara_login' WHERE id = $id_pesanan";
    if (!db_query($conn, $update_query)) {
        db_query($conn, "UPDATE pesanan SET status = '$status', data_pesanan = '$data_pesanan' WHERE id = $id_pesanan");
    }
    if ($berubah) {
        db_query($conn, "UPDATE pesanan SET notif_buyer = 1 WHERE id = $id_pesanan");
    }

    // Pesanan dikonfirmasi admin -> stok produk berkurang 1 otomatis (sekali per pesanan)
    if (in_array(trim($_POST['status']), ['Dikonfirmasi', 'Selesai'], true)) {
        alzz_kurangi_stok_pesanan($conn, $id_pesanan);
    }
    
    header("Location: admin_dashboard.php?tab=situs");
    exit;
}

// 2. Proses Hapus Pesanan
if (isset($_GET['hapus_pesanan'])) {
    $id_pesanan = intval($_GET['hapus_pesanan']);
    db_query($conn, "DELETE FROM pesanan WHERE id = $id_pesanan");
    header("Location: admin_dashboard.php?tab=situs");
    exit;
}

// 3. Proses Tambah Produk
if (isset($_POST['tambah_produk'])) {
    $nama_app = db_real_escape_string($conn, $_POST['nama_app']);
    $harga = intval($_POST['harga']);
    $stok = intval($_POST['stok']);
    $deskripsi = db_real_escape_string($conn, $_POST['deskripsi']);
    $durasi = db_real_escape_string($conn, $_POST['durasi']);
    $plan = db_real_escape_string($conn, $_POST['plan']);

    // Foto produk dipilih dari galeri HP (jpg/png/gif/webp, maks 8MB)
    $foto_path = '';
    if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['foto_file']['tmp_name'];
        $info = @getimagesize($tmp);
        $boleh = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', 18 => 'webp']; // 18 = WEBP
        if ($info && isset($boleh[$info[2]]) && $_FILES['foto_file']['size'] <= 8 * 1024 * 1024) {
            require_once ALZZ_ROOT . '/storage_pg.php';
            $nama_obj = 'produk/produk_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $boleh[$info[2]];
            $url_foto = alzz_storage_upload($tmp, $nama_obj, $info['mime']); // simpan ke Supabase Storage
            if ($url_foto !== '') { $foto_path = $url_foto; }
        }
    }
    if ($foto_path === '') {
        header("Location: admin_dashboard.php?tab=produk&err=foto");
        exit;
    }
    $foto_produk = db_real_escape_string($conn, $foto_path);

    db_query($conn, "INSERT INTO produk (nama_app, harga, stok, deskripsi, foto_produk, durasi, plan) VALUES ('$nama_app', '$harga', '$stok', '$deskripsi', '$foto_produk', '$durasi', '$plan')");
    header("Location: admin_dashboard.php?tab=produk");
    exit;
}

// 4. Proses Update / Edit Harga dan Stok Produk
if (isset($_POST['update_produk'])) {
    $prod_id = intval($_POST['produk_id']);
    $harga_baru = intval($_POST['harga']);
    $stok_baru = intval($_POST['stok']);
    
    db_query($conn, "UPDATE produk SET harga = $harga_baru, stok = $stok_baru WHERE id = $prod_id");
    header("Location: admin_dashboard.php?tab=produk");
    exit;
}

// 4b. Proses Simpan / Hapus Diskon Produk
if (isset($_POST['simpan_diskon']) || isset($_POST['hapus_diskon'])) {
    require_once ALZZ_ROOT . '/diskon_lib.php';
    alzz_diskon_ensure($conn);
    $prod_id = intval($_POST['produk_id'] ?? 0);

    if (isset($_POST['hapus_diskon'])) {
        db_query($conn, "UPDATE produk SET diskon_nilai = 0, diskon_sampai = NULL WHERE id = $prod_id");
        header("Location: admin_dashboard.php?tab=diskon&ok=hapus");
        exit;
    }

    $tipe  = (($_POST['diskon_tipe'] ?? 'persen') === 'nominal') ? 'nominal' : 'persen';
    $nilai = intval($_POST['diskon_nilai'] ?? 0);
    $row   = db_fetch_assoc(db_query($conn, "SELECT harga FROM produk WHERE id = $prod_id"));
    $harga = intval($row['harga'] ?? 0);

    // Validasi: persen 1-99, nominal 1 sampai (harga - 1)
    $valid = ($tipe === 'persen') ? ($nilai >= 1 && $nilai <= 99) : ($nilai >= 1 && $nilai < $harga);
    if (!$row || !$valid) {
        header("Location: admin_dashboard.php?tab=diskon&err=nilai");
        exit;
    }

    $sampai_sql = 'NULL';
    $sampai_in = trim((string)($_POST['diskon_sampai'] ?? ''));
    if ($sampai_in !== '') {
        $t = strtotime($sampai_in);
        if ($t === false || $t <= time()) {
            header("Location: admin_dashboard.php?tab=diskon&err=tanggal");
            exit;
        }
        $sampai_sql = "'" . date('Y-m-d H:i:s', $t) . "'";
    }

    db_query($conn, "UPDATE produk SET diskon_tipe = '$tipe', diskon_nilai = $nilai, diskon_sampai = $sampai_sql WHERE id = $prod_id");
    header("Location: admin_dashboard.php?tab=diskon&ok=simpan");
    exit;
}

// 5. Proses Hapus Produk
if (isset($_GET['hapus_produk'])) {
    $id = intval($_GET['hapus_produk']);
    db_query($conn, "DELETE FROM produk WHERE id = $id");
    header("Location: admin_dashboard.php?tab=produk");
    exit;
}

// 6. Proses Tambah Metode Pembayaran
if (isset($_POST['tambah_metode'])) {
    $nama_metode  = db_real_escape_string($conn, $_POST['nama_metode']);
    $nomor_raw    = trim($_POST['nomor_tujuan'] ?? '');
    $pemilik      = !empty($_POST['pemilik']) ? db_real_escape_string($conn, $_POST['pemilik']) : '-';

    // Gambar QRIS boleh dipilih dari galeri HP (jpg/png/gif/webp, maks 8MB) -> disimpan di Supabase Storage,
    // alamat (URL) gambarnya dipakai sebagai "nomor_tujuan" (kolom yang sama seperti sebelumnya)
    if (isset($_FILES['qris_file']) && $_FILES['qris_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $url_qris = '';
        $why = '';
        if ($_FILES['qris_file']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['qris_file']['tmp_name'];
            $info = @getimagesize($tmp);
            $boleh = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', 18 => 'webp']; // 18 = WEBP
            if ($info && isset($boleh[$info[2]]) && $_FILES['qris_file']['size'] <= 8 * 1024 * 1024) {
                require_once ALZZ_ROOT . '/storage_pg.php';
                $nama_obj = 'qris/qris_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $boleh[$info[2]];
                $url_qris = alzz_storage_upload($tmp, $nama_obj, $info['mime']); // simpan ke Supabase Storage
                if ($url_qris === '') { $why = $GLOBALS['__storage_last'] ?? 'Gagal menyimpan ke Supabase Storage'; }
            } else {
                $why = 'File bukan gambar yang didukung atau lebih dari 8MB';
            }
        } else {
            $why = 'Upload ditolak server (kode ' . intval($_FILES['qris_file']['error']) . ')';
        }
        if ($url_qris === '') {
            header("Location: admin_dashboard.php?tab=pembayaran&err=qris&why=" . urlencode($why));
            exit;
        }
        $nomor_raw = $url_qris;
    }
    if ($nomor_raw === '') {
        header("Location: admin_dashboard.php?tab=pembayaran&err=qris&why=" . urlencode('Nomor tujuan dan gambar QRIS sama-sama kosong'));
        exit;
    }
    $nomor_tujuan = db_real_escape_string($conn, $nomor_raw);

    $query_metode = "INSERT INTO metode_pembayaran (nama_metode, nomor_tujuan, status) VALUES ('$nama_metode', '$nomor_tujuan', '$pemilik')";
    db_query($conn, $query_metode);
    
    header("Location: admin_dashboard.php?tab=pembayaran");
    exit;
}

// 7. Proses Hapus Metode Pembayaran
if (isset($_GET['hapus_metode'])) {
    $id = intval($_GET['hapus_metode']);
    db_query($conn, "DELETE FROM metode_pembayaran WHERE id = $id");
    header("Location: admin_dashboard.php?tab=pembayaran");
    exit;
}

// 8. Proses Update Pengaturan Situs & Nomor WhatsApp Admin
// Banner dipilih dari galeri HP (jpg/png/gif/webp, maks 8MB) dan musik dipilih dari file HP (maks 15MB),
// keduanya disimpan di Supabase Storage. Kalau tidak memilih file baru, nilai lama tetap dipakai.
if (isset($_POST['update_pengaturan'])) {
    $cek_admin  = db_query($conn, "SELECT * FROM admin ORDER BY id LIMIT 1");
    $ada_admin  = ($cek_admin && db_num_rows($cek_admin) > 0);
    $admin_lama = $ada_admin ? (db_fetch_assoc($cek_admin) ?: []) : [];
    $banner_raw = (string)($admin_lama['banner_url'] ?? '');
    $music_raw  = (string)($admin_lama['music_url'] ?? '');
    $err_upload = [];

    // ---- Banner dari galeri ----
    if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $why_b = '';
        if ($_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['banner_file']['tmp_name'];
            $info = @getimagesize($tmp);
            $boleh = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', 18 => 'webp']; // 18 = WEBP
            if ($info && isset($boleh[$info[2]]) && $_FILES['banner_file']['size'] <= 8 * 1024 * 1024) {
                require_once ALZZ_ROOT . '/storage_pg.php';
                $nama_obj = 'banner/banner_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $boleh[$info[2]];
                $url_banner = alzz_storage_upload($tmp, $nama_obj, $info['mime']); // simpan ke Supabase Storage
                if ($url_banner !== '') { $banner_raw = $url_banner; }
                else { $why_b = $GLOBALS['__storage_last'] ?? 'Gagal menyimpan ke Supabase Storage'; }
            } else {
                $why_b = 'File bukan gambar yang didukung atau lebih dari 8MB';
            }
        } else {
            $why_b = 'Upload ditolak server (kode ' . intval($_FILES['banner_file']['error']) . ')';
        }
        if ($why_b !== '') { $err_upload[] = 'Banner: ' . $why_b; }
    }

    // ---- Musik dari file HP ----
    if (isset($_FILES['music_file']) && $_FILES['music_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $why_m = '';
        if ($_FILES['music_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['music_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3', 'm4a', 'ogg', 'wav', 'aac'], true) && $_FILES['music_file']['size'] <= 15 * 1024 * 1024) {
                require_once ALZZ_ROOT . '/storage_pg.php';
                $mimes = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'aac' => 'audio/aac'];
                $url_musik = alzz_storage_upload($_FILES['music_file']['tmp_name'], 'music/music_' . time() . '.' . $ext, $mimes[$ext]); // simpan ke Supabase Storage
                if ($url_musik !== '') { $music_raw = $url_musik; }
                else { $why_m = $GLOBALS['__storage_last'] ?? 'Gagal menyimpan ke Supabase Storage'; }
            } else {
                $why_m = 'Format musik tidak didukung (mp3/m4a/ogg/wav/aac) atau lebih dari 15MB';
            }
        } else {
            $why_m = 'Upload ditolak server (kode ' . intval($_FILES['music_file']['error']) . ')';
        }
        if ($why_m !== '') { $err_upload[] = 'Musik: ' . $why_m; }
    }

    $banner_url = db_real_escape_string($conn, $banner_raw);
    $music_url  = db_real_escape_string($conn, $music_raw);
    $no_wa = db_real_escape_string($conn, $_POST['no_wa']);

    if ($ada_admin) {
        db_query($conn, "UPDATE admin SET banner_url = '$banner_url', music_url = '$music_url', no_wa = '$no_wa' WHERE id = (SELECT id FROM admin ORDER BY id LIMIT 1)");
    } else {
        db_query($conn, "INSERT INTO admin (banner_url, music_url, no_wa) VALUES ('$banner_url', '$music_url', '$no_wa')");
    }

    $balik = "admin_dashboard.php?tab=situs";
    if (!empty($err_upload)) {
        $balik .= "&err=upload&why=" . urlencode(implode(' | ', $err_upload));
    }
    header("Location: " . $balik);
    exit;
}

// 9. Proses Hapus User Pembeli
if (isset($_GET['hapus_user'])) {
    $id_user = intval($_GET['hapus_user']);
    db_query($conn, "DELETE FROM buyers WHERE id = $id_user");
    header("Location: admin_dashboard.php?tab=users");
    exit;
}

// ---------------------------------------------------------------- [pendapatan]
// ============================================================================
// BACKEND PENDAPATAN
// Menghitung pendapatan harian, mingguan, dan bulanan dari tabel pesanan.
//
// Aturan hitung:
//  - Yang dihitung sebagai pendapatan: pesanan berstatus "Dikonfirmasi" atau "Selesai".
//  - Pesanan "Pending" TIDAK dihitung (ditampilkan terpisah sebagai "menunggu konfirmasi").
//  - Tanggal yang dipakai: tanggal pesanan dibuat (kolom pesanan.tanggal).
//  - Minggu dihitung Senin - Minggu.
//  - Harga dicatat di kolom pesanan.harga_beli, supaya kalau nanti harga produk diubah,
//    pendapatan lama tidak ikut berubah.
// ============================================================================

// ---------- Format tampilan ----------
function alzz_angka($n) {
    return number_format((int)$n, 0, ',', '.');
}

function alzz_rupiah($n) {
    return 'Rp ' . alzz_angka($n);
}

// Angka ringkas untuk label grafik: 250 rb, 1,2 jt
function alzz_rupiah_ringkas($n) {
    $n = (int)$n;
    if ($n >= 1000000) {
        $teks = number_format($n / 1000000, 1, ',', '.');
        $teks = rtrim(rtrim($teks, '0'), ',');
        return $teks . ' jt';
    }
    if ($n >= 1000) {
        return round($n / 1000) . ' rb';
    }
    return (string)$n;
}

function alzz_bulan_singkat($m) {
    $a = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
    return $a[(int)$m];
}

function alzz_bulan_penuh($m) {
    $a = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
    return $a[(int)$m];
}

function alzz_hari_singkat($w) { // 0 = Minggu
    $a = array('Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab');
    return $a[(int)$w];
}

function alzz_hari_penuh($w) {
    $a = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    return $a[(int)$w];
}

// ---------- Persiapan database ----------
// Menambah kolom harga_beli (sekali saja) dan mengisi pesanan yang belum punya harga.
function alzz_pendapatan_siapkan($conn) {
    static $done = false;
    if ($done) return;
    // Kolom harga_beli sudah dibuat lewat SQL di Supabase.
    // Pesanan yang belum punya harga -> isi dengan harga produk saat ini
    db_query($conn, "UPDATE pesanan AS p SET harga_beli = pr.harga FROM produk pr WHERE p.produk_id = pr.id AND p.harga_beli IS NULL");
    $done = true;
}

// Rumus harga per pesanan
function alzz_ekspresi_harga($conn) {
    return "COALESCE(p.harga_beli, pr.harga, 0)";
}

// Mengelompokkan pendapatan. $kunci = rumus SQL pengelompokan (selalu dari kode ini, bukan dari input pengguna)
function alzz_pendapatan_kelompok($conn, $kunci, $dari) {
    $harga = alzz_ekspresi_harga($conn);
    $dari  = db_real_escape_string($conn, $dari);
    $hasil = array();
    $q = db_query($conn,
        "SELECT $kunci AS k, COUNT(*) AS n, COALESCE(SUM($harga), 0) AS total
         FROM pesanan p LEFT JOIN produk pr ON p.produk_id = pr.id
         WHERE p.status IN ('Dikonfirmasi', 'Selesai') AND DATE(p.tanggal) >= '$dari'
         GROUP BY k");
    if ($q) {
        while ($row = db_fetch_assoc($q)) {
            $hasil[(string)$row['k']] = array('n' => (int)$row['n'], 'total' => (int)$row['total']);
        }
    }
    return $hasil;
}

// ---------- Data utama ----------
function alzz_pendapatan_data($conn) {
    alzz_pendapatan_siapkan($conn);

    $hariIni = strtotime(date('Y-m-d'));
    $dow     = (int)date('N', $hariIni);                       // 1 = Senin ... 7 = Minggu
    $senin   = strtotime('-' . ($dow - 1) . ' day', $hariIni); // Senin minggu ini
    $tgl1    = strtotime(date('Y-m-01', $hariIni));            // tanggal 1 bulan ini

    $JML_HARI = 14; $JML_MINGGU = 8; $JML_BULAN = 12;

    $dataHari   = alzz_pendapatan_kelompok($conn, "DATE(p.tanggal)", date('Y-m-d', strtotime('-' . ($JML_HARI - 1) . ' day', $hariIni)));
    $dataMinggu = alzz_pendapatan_kelompok($conn, "(DATE(p.tanggal) - (EXTRACT(ISODOW FROM p.tanggal)::int - 1))", date('Y-m-d', strtotime('-' . ($JML_MINGGU - 1) . ' week', $senin)));
    $dataBulan  = alzz_pendapatan_kelompok($conn, "TO_CHAR(p.tanggal, 'YYYY-MM')", date('Y-m-d', strtotime('-' . ($JML_BULAN - 1) . ' month', $tgl1)));

    $harian = array();
    for ($i = $JML_HARI - 1; $i >= 0; $i--) {
        $ts = strtotime('-' . $i . ' day', $hariIni);
        $k  = date('Y-m-d', $ts);
        $w  = (int)date('w', $ts);
        $harian[] = array(
            'label' => date('j', $ts),
            'sub'   => alzz_hari_singkat($w),
            'judul' => alzz_hari_penuh($w) . ', ' . date('j', $ts) . ' ' . alzz_bulan_singkat(date('n', $ts)),
            'n'     => isset($dataHari[$k]) ? $dataHari[$k]['n'] : 0,
            'total' => isset($dataHari[$k]) ? $dataHari[$k]['total'] : 0,
            'now'   => ($i === 0),
        );
    }

    $mingguan = array();
    for ($i = $JML_MINGGU - 1; $i >= 0; $i--) {
        $mulai   = strtotime('-' . $i . ' week', $senin);
        $selesai = strtotime('+6 day', $mulai);
        $k = date('Y-m-d', $mulai);
        $mingguan[] = array(
            'label' => date('j', $mulai),
            'sub'   => alzz_bulan_singkat(date('n', $mulai)),
            'judul' => date('j', $mulai) . ' ' . alzz_bulan_singkat(date('n', $mulai)) . ' - ' . date('j', $selesai) . ' ' . alzz_bulan_singkat(date('n', $selesai)),
            'n'     => isset($dataMinggu[$k]) ? $dataMinggu[$k]['n'] : 0,
            'total' => isset($dataMinggu[$k]) ? $dataMinggu[$k]['total'] : 0,
            'now'   => ($i === 0),
        );
    }

    $bulanan = array();
    for ($i = $JML_BULAN - 1; $i >= 0; $i--) {
        $ts = strtotime('-' . $i . ' month', $tgl1);
        $k  = date('Y-m', $ts);
        $bulanan[] = array(
            'label' => alzz_bulan_singkat(date('n', $ts)),
            'sub'   => date('y', $ts),
            'judul' => alzz_bulan_penuh(date('n', $ts)) . ' ' . date('Y', $ts),
            'n'     => isset($dataBulan[$k]) ? $dataBulan[$k]['n'] : 0,
            'total' => isset($dataBulan[$k]) ? $dataBulan[$k]['total'] : 0,
            'now'   => ($i === 0),
        );
    }

    // Total sepanjang waktu & pesanan yang masih menunggu konfirmasi
    $harga = alzz_ekspresi_harga($conn);
    $total = array('n' => 0, 'total' => 0);
    $q = db_query($conn, "SELECT COUNT(*) AS n, COALESCE(SUM($harga), 0) AS total FROM pesanan p LEFT JOIN produk pr ON p.produk_id = pr.id WHERE p.status IN ('Dikonfirmasi', 'Selesai')");
    if ($q && ($d = db_fetch_assoc($q))) { $total = array('n' => (int)$d['n'], 'total' => (int)$d['total']); }

    $pending = array('n' => 0, 'total' => 0);
    $q = db_query($conn, "SELECT COUNT(*) AS n, COALESCE(SUM($harga), 0) AS total FROM pesanan p LEFT JOIN produk pr ON p.produk_id = pr.id WHERE p.status = 'Pending'");
    if ($q && ($d = db_fetch_assoc($q))) { $pending = array('n' => (int)$d['n'], 'total' => (int)$d['total']); }

    return array(
        'harian'   => $harian,
        'mingguan' => $mingguan,
        'bulanan'  => $bulanan,
        'ringkas'  => array(
            'hari'    => array('now' => $harian[count($harian) - 1],     'prev' => $harian[count($harian) - 2]),
            'minggu'  => array('now' => $mingguan[count($mingguan) - 1], 'prev' => $mingguan[count($mingguan) - 2]),
            'bulan'   => array('now' => $bulanan[count($bulanan) - 1],   'prev' => $bulanan[count($bulanan) - 2]),
            'total'   => $total,
            'pending' => $pending,
        ),
    );
}

// ---------------------------------------------------------------- [data]
// BACKEND: mengambil data dari database untuk ditampilkan di dashboard
$admin_data = db_fetch_assoc(db_query($conn, "SELECT * FROM admin ORDER BY id LIMIT 1"));
$produk_result = db_query($conn, "SELECT * FROM produk ORDER BY id");
$metode_result = db_query($conn, "SELECT * FROM metode_pembayaran ORDER BY id");
$pesanan_result = db_query($conn, "SELECT p.*, pr.nama_app, pr.durasi, pr.plan FROM pesanan p JOIN produk pr ON p.produk_id = pr.id ORDER BY p.id DESC");
$buyers_result = db_query($conn, "SELECT * FROM buyers ORDER BY id DESC");
$__mo = db_fetch_assoc(db_query($conn, "SELECT COALESCE(MAX(id),0) AS m FROM pesanan"));
$__mu = db_fetch_assoc(db_query($conn, "SELECT COALESCE(MAX(id),0) AS m FROM buyers"));
$page_max_order = intval($__mo['m'] ?? 0);
$page_max_user  = intval($__mu['m'] ?? 0);

// Data pendapatan (harian, mingguan, bulanan)
$pendapatan = alzz_pendapatan_data($conn);
