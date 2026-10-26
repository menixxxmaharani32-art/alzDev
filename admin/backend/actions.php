<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
// BACKEND: semua proses simpan / ubah / hapus (pesanan, produk, pembayaran, pengaturan, user)
// 1. Proses Update Pesanan
if (isset($_POST['update_pesanan'])) {
    $id_pesanan = intval($_POST['pesanan_id']);
    $status = db_real_escape_string($conn, $_POST['status']);
    $data_pesanan = db_real_escape_string($conn, $_POST['data_pesanan']);
    $cara_login = db_real_escape_string($conn, $_POST['cara_login']);
    
    // Cek apakah admin benar-benar mengubah sesuatu (untuk memunculkan angka notifikasi di riwayat pembeli)
    require_once ALZZ_ROOT . '/notif_buyer.php';
    alzz_ensure_notif_col($conn);
    $old_row = db_fetch_assoc(db_query($conn, "SELECT status, data_pesanan, cara_login FROM pesanan WHERE id = $id_pesanan"));
    $berubah = !$old_row
        || trim((string)$old_row['status']) !== trim($_POST['status'])
        || trim((string)$old_row['data_pesanan']) !== trim($_POST['data_pesanan'])
        || trim((string)($old_row['cara_login'] ?? '')) !== trim($_POST['cara_login']);

    $update_query = "UPDATE pesanan SET status = '$status', data_pesanan = '$data_pesanan', cara_login = '$cara_login' WHERE id = $id_pesanan";
    if (!db_query($conn, $update_query)) {
        db_query($conn, "UPDATE pesanan SET status = '$status', data_pesanan = '$data_pesanan' WHERE id = $id_pesanan");
    }
    if ($berubah) {
        db_query($conn, "UPDATE pesanan SET notif_buyer = 1 WHERE id = $id_pesanan");
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
    $nomor_tujuan = db_real_escape_string($conn, $_POST['nomor_tujuan']);
    $pemilik      = !empty($_POST['pemilik']) ? db_real_escape_string($conn, $_POST['pemilik']) : '-';
    
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
if (isset($_POST['update_pengaturan'])) {
    $banner_url = db_real_escape_string($conn, $_POST['banner_url']);
    $music_raw = trim($_POST['music_url'] ?? '');
    // Kolom URL dikosongkan = musik dimatikan
    // Upload file musik langsung dari HP (mp3/m4a/ogg/wav, maks 15MB)
    if (isset($_FILES['music_file']) && $_FILES['music_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['music_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp3', 'm4a', 'ogg', 'wav', 'aac'], true) && $_FILES['music_file']['size'] <= 15 * 1024 * 1024) {
            require_once ALZZ_ROOT . '/storage_pg.php';
            $mimes = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'aac' => 'audio/aac'];
            $url_musik = alzz_storage_upload($_FILES['music_file']['tmp_name'], 'music/music_' . time() . '.' . $ext, $mimes[$ext]); // simpan ke Supabase Storage
            if ($url_musik !== '') {
                $music_raw = $url_musik;
            }
        }
    }
    $music_url = db_real_escape_string($conn, $music_raw);
    $no_wa = db_real_escape_string($conn, $_POST['no_wa']);
    
    $cek_admin = db_query($conn, "SELECT * FROM admin ORDER BY id LIMIT 1");
    if ($cek_admin && db_num_rows($cek_admin) > 0) {
        db_query($conn, "UPDATE admin SET banner_url = '$banner_url', music_url = '$music_url', no_wa = '$no_wa' WHERE id = (SELECT id FROM admin ORDER BY id LIMIT 1)");
    } else {
        db_query($conn, "INSERT INTO admin (banner_url, music_url, no_wa) VALUES ('$banner_url', '$music_url', '$no_wa')");
    }

    header("Location: admin_dashboard.php?tab=situs");
    exit;
}

// 9. Proses Hapus User Pembeli
if (isset($_GET['hapus_user'])) {
    $id_user = intval($_GET['hapus_user']);
    db_query($conn, "DELETE FROM buyers WHERE id = $id_user");
    header("Location: admin_dashboard.php?tab=users");
    exit;
}
?>
