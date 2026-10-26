<?php
// Cegah halaman tersimpan di cache (tombol Back setelah logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once 'auth_guard.php';
require_once 'config.php';
require_once 'diskon_lib.php';

// 1. CEK APAKAH AKUN BUYER SUDAH DIHAPUS DARI DATABASE
if (isset($_SESSION['buyer_logged']) && isset($_SESSION['buyer_user'])) {
    $current_buyer_user = db_real_escape_string($conn, $_SESSION['buyer_user']);
    $check_buyer_query = db_query($conn, "SELECT * FROM buyers WHERE username = '$current_buyer_user'");
    
    if (db_num_rows($check_buyer_query) === 0) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Pastikan buyer sudah login
if (!isset($_SESSION['buyer_logged']) && !isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit;
}

$id_produk = intval($_GET['id'] ?? 0);
$produk = null;

if ($id_produk > 0) {
    $produk = db_fetch_assoc(db_query($conn, "SELECT * FROM produk WHERE id = $id_produk"));
    if (!$produk) {
        echo "<script>alert('Produk tidak ditemukan!'); window.location='index.php';</script>";
        exit;
    }
}

// Harga akhir (sudah termasuk diskon bila ada) - dihitung di server
$hinfo = $produk ? alzz_harga_info($produk) : null;

// Ambil metode pembayaran dari database
$metode_result = db_query($conn, "SELECT * FROM metode_pembayaran ORDER BY id");

// Ambil data admin untuk nomor WhatsApp
$admin_data = db_fetch_assoc(db_query($conn, "SELECT * FROM admin ORDER BY id LIMIT 1"));
$wa_admin = $admin_data['no_wa'] ?? $admin_data['whatsapp'] ?? '6281234567890';

$success_order = null;
$my_orders = null;
// IP pembeli sengaja TIDAK dicatat (privasi)
$current_buyer = $_SESSION['buyer_user'] ?? $_SESSION['admin_user'] ?? 'Guest';

// Proses form checkout dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_produk > 0) {
    $nama = db_real_escape_string($conn, $_POST['nama'] ?? '');
    $alamat = db_real_escape_string($conn, $_POST['alamat'] ?? '');
    $device = db_real_escape_string($conn, $_POST['device'] ?? '');
    $metode_pembayaran = db_real_escape_string($conn, $_POST['metode_pembayaran'] ?? '');
    $tanggal = date('Y-m-d H:i:s');
    $status = 'Pending';
    
    // Upload bukti pembayaran foto
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
        $file_name = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['bukti_pembayaran']['name']));
        require_once 'storage_pg.php';
        // Bukti pembayaran disimpan di Supabase Storage (bukan di folder server)
        $bukti_pembayaran = alzz_storage_upload($file_tmp, 'bukti/' . $file_name);
    }
    
    if (empty($nama) || empty($alamat) || empty($device)) {
        echo "<script>alert('Harap isi Nama, Alamat, dan Merk Device dengan lengkap!');</script>";
    } elseif (empty($metode_pembayaran)) {
        echo "<script>alert('Harap pilih metode pembayaran terlebih dahulu!');</script>";
    } elseif (empty($bukti_pembayaran)) {
        echo "<script>alert('Harap unggah bukti pembayaran foto dengan benar!');</script>";
    } else {
        // Menyimpan pesanan lengkap dengan username buyer yang sedang login
        // harga_beli = harga akhir saat dibeli (setelah diskon), dipakai untuk menghitung pendapatan
        $harga_final = intval($hinfo['harga_akhir']);
        $query = "INSERT INTO pesanan (produk_id, username, nama, alamat, device, metode_pembayaran, bukti_pembayaran, ip_address, status, tanggal, harga_beli) VALUES ('$id_produk', '$current_buyer', '$nama', '$alamat', '$device', '$metode_pembayaran', '$bukti_pembayaran', '-', '$status', '$tanggal', $harga_final)";
        $simpan_ok = db_query($conn, $query);
        if (!$simpan_ok) {
            // cadangan bila kolom harga_beli belum ada
            $query = "INSERT INTO pesanan (produk_id, username, nama, alamat, device, metode_pembayaran, bukti_pembayaran, ip_address, status, tanggal) VALUES ('$id_produk', '$current_buyer', '$nama', '$alamat', '$device', '$metode_pembayaran', '$bukti_pembayaran', '-', '$status', '$tanggal')";
            $simpan_ok = db_query($conn, $query);
        }
        if ($simpan_ok) {
            $order_id = db_insert_id($conn);
            // Kirim notifikasi push ke HP admin
            require_once 'push_lib.php';
            $__nama_app = '';
            $__rp = db_query($conn, "SELECT nama_app FROM produk WHERE id = " . intval($id_produk));
            if ($__rp && ($__rw = db_fetch_assoc($__rp))) $__nama_app = $__rw['nama_app'];
            alzz_push_event($conn, 'order', '🛒 Pesanan baru', $current_buyer . ' memesan ' . $__nama_app, 'admin_dashboard.php?tab=situs');
            $success_order = db_fetch_assoc(db_query($conn, "SELECT p.*, pr.nama_app FROM pesanan p JOIN produk pr ON p.produk_id = pr.id WHERE p.id = $order_id"));
        } else {
            echo "<script>alert('Gagal memproses pesanan. Coba lagi.');</script>";
        }
    }
}

// Hanya mengambil riwayat pesanan MILIK USER YANG SEDANG LOGIN SAJA
require_once 'notif_buyer.php';
alzz_ensure_notif_col($conn);
if ($id_produk === 0 && !$success_order) {
    $my_orders = db_query($conn, "SELECT p.*, pr.nama_app, pr.durasi, pr.plan FROM pesanan p JOIN produk pr ON p.produk_id = pr.id WHERE p.username = '$current_buyer' ORDER BY p.id DESC");
    // Pembeli membuka riwayat: tandai pesan admin sudah dilihat (data di atas sudah tersimpan, jadi label "PESAN BARU" tetap tampil sekali ini)
    if (isset($_SESSION['buyer_logged'])) {
        db_query($conn, "UPDATE pesanan SET notif_buyer = 0 WHERE username = '" . db_real_escape_string($conn, $current_buyer) . "' AND notif_buyer = 1");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id_produk > 0 ? 'Checkout - ' . htmlspecialchars($produk['nama_app']) : 'Bantuan & Riwayat Pesanan'; ?></title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <style>
        #qrModal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.85);
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 20px;
            box-sizing: border-box;
        }
        #qrModal img {
            max-width: 90%;
            max-height: 80vh;
            border-radius: 8px;
            border: 2px solid #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            object-fit: contain;
        }
        #qrModal span {
            color: #fff;
            margin-top: 15px;
            font-size: 13px;
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="mobile-container">
        
        <div class="section-header">
            <h2 class="catalog-title">
                <span class="catalog-text"><?php echo $id_produk > 0 ? 'CHECKOUT PRODUK' : 'BANTUAN & RIWAYAT PESANAN'; ?></span>
            </h2>
            <a href="index.php" class="admin-link">Beranda</a>
        </div>

        <?php if ($success_order): ?>
            <!-- TAMPILAN SETELAH CHECKOUT BERHASIL -->
            <div class="admin-card" style="border-color: #34d399; margin-bottom: 16px;">
                <h3 style="color: #34d399; font-size: 14px; margin-bottom: 10px; text-align: center;">Pesanan Berhasil Dibuat!</h3>
                
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; font-size: 11px; position: relative;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <strong>Produk: <?php echo htmlspecialchars($success_order['nama_app']); ?></strong>
                        <span style="color: #94a3b8; font-size: 9px;"><?php echo $success_order['tanggal']; ?></span>
                    </div>
                    <div>Akun Username: <strong style="color: #38bdf8;"><?php echo htmlspecialchars($success_order['username']); ?></strong></div>
                    <div>Nama Pembeli: <strong><?php echo htmlspecialchars($success_order['nama']); ?></strong></div>
                    <div>Alamat: <strong><?php echo htmlspecialchars($success_order['alamat']); ?></strong></div>
                    <div>Merk Device: <strong><?php echo htmlspecialchars($success_order['device']); ?></strong></div>
                    <div style="margin-top: 4px;">Metode Pembayaran: <strong><?php echo htmlspecialchars($success_order['metode_pembayaran']); ?></strong></div>
                    <?php if (($success_order['harga_beli'] ?? null) !== null): ?>
                        <div>Total Bayar: <strong style="color: #34d399;"><?php echo alzz_rp($success_order['harga_beli'] ?? 0); ?></strong></div>
                    <?php endif; ?>
                    <div>Status Pesanan: <span style="font-weight: bold; color: #facc15;"><?php echo htmlspecialchars($success_order['status']); ?></span></div>
                    
                    <?php if (!empty($success_order['bukti_pembayaran'])): ?>
                        <div style="margin-top: 6px;">
                            <a href="<?php echo htmlspecialchars($success_order['bukti_pembayaran']); ?>" target="_blank" style="color: #38bdf8; text-decoration: underline;">Lihat Bukti Transfer yang Diunggah</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_order['data_pesanan'])): ?>
                        <div style="margin-top: 14px; background: rgba(52, 211, 153, 0.1); border: 1px solid #34d399; padding: 12px; border-radius: 6px;">
                            <strong style="color: #34d399; display: block; margin-bottom: 6px;">Data Akun / Pesanan Anda:</strong>
                            <div style="color: #fff; word-break: break-all; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($success_order['data_pesanan'])); ?></div>
                            
                            <div style="margin-top: 25px;"></div>
                            
                            <strong style="color: #38bdf8; display: block; margin-bottom: 6px;">Cara Login / Menggunakan Akun:</strong>
                            <div style="color: #cbd5e1; word-break: break-all; line-height: 1.6;">
                                <?php 
                                    if (!empty($success_order['cara_login'])) {
                                        echo nl2br(htmlspecialchars($success_order['cara_login']));
                                    } else {
                                        echo "1. Salin data username/email dan password di atas dengan benar.<br>2. Login ke aplikasi atau platform resmi sesuai produk yang dibeli.<br>3. Jangan mengubah data akun agar garansi tetap aktif.";
                                    }
                                ?>
                            </div>
                            <div style="margin-top: 45px;"></div>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 10px; padding: 8px; background: rgba(250, 204, 21, 0.1); border: 1px solid #facc15; border-radius: 6px; color: #facc15; font-style: italic; font-weight: bold;">
                            Pesanan anda akan segera di proses oleh admin.
                        </div>
                    <?php endif; ?>

                    <div style="text-align: right; margin-top: 12px;">
                        <a href="https://wa.me/<?php echo htmlspecialchars($wa_admin); ?>?text=Halo%20Admin,%20saya%20(User:%20<?php echo urlencode($current_buyer); ?>)%20ingin%20konfirmasi%20pesanan%20produk%20<?php echo urlencode($success_order['nama_app']); ?>%20(ID:%20<?php echo $success_order['id']; ?>)" target="_blank" style="background: #25d366; color: #fff; padding: 5px 12px; border-radius: 6px; font-size: 10px; text-decoration: none; font-weight: bold; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                            WhatsApp Admin
                        </a>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 15px;">
                    <a href="index.php" class="btn-buy-mini" style="display: inline-block; text-decoration: none; padding: 10px 20px;">Kembali ke Beranda</a>
                </div>
            </div>

        <?php elseif ($id_produk === 0): ?>
            
            <div class="admin-card" style="border-color: #25d366; margin-bottom: 16px; text-align: center; padding: 16px; background: rgba(37, 211, 102, 0.05);">
                <h3 style="color: #25d366; font-size: 14px; margin-bottom: 6px;">Butuh Bantuan / Kendala Pesanan?</h3>
                <p style="color: #cbd5e1; font-size: 11px; margin-bottom: 12px;">Punya pertanyaan atau ingin konfirmasi pembayaran? Hubungi admin langsung melalui WhatsApp.</p>
                <a href="https://wa.me/<?php echo htmlspecialchars($wa_admin); ?>?text=Halo%20Admin,%20saya%20User%20<?php echo urlencode($current_buyer); ?>%20butuh%20bantuan." target="_blank" style="background: #25d366; color: #fff; padding: 10px 20px; border-radius: 8px; font-size: 12px; text-decoration: none; font-weight: bold; display: inline-block; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);">
                    Chat WhatsApp Admin Sekarang
                </a>
            </div>

            <h3 style="color: #38bdf8; font-size: 13px; margin-bottom: 10px; margin-top: 16px;">Riwayat Pesanan Akun: <span style="color:#34d399;"><?php echo htmlspecialchars($current_buyer); ?></span></h3>
            
            <?php if ($my_orders && db_num_rows($my_orders) > 0): ?>
                <?php while($ord = db_fetch_assoc($my_orders)): ?>
                    <div class="admin-card" style="border-color: #38bdf8; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <strong style="color: #38bdf8; font-size: 12px;">
                                Produk: <?php echo htmlspecialchars($ord['nama_app']); ?>
                                <?php if (!empty($ord['durasi']) || !empty($ord['plan'])): ?>
                                    <span style="color: #facc15; font-size: 11px;">
                                        (<?php echo htmlspecialchars($ord['durasi'] ?? ''); ?> <?php echo !empty($ord['plan']) ? '- '.htmlspecialchars($ord['plan']) : ''; ?>)
                                    </span>
                                <?php endif; ?>
                            </strong>
                            <span style="color: #94a3b8; font-size: 9px;">
                                <?php if (!empty($ord['notif_buyer'])): ?><span style="background:#ef4444;color:#fff;font-weight:bold;padding:1px 6px;border-radius:8px;margin-right:4px;">PESAN BARU</span><?php endif; ?>
                                <?php echo $ord['tanggal']; ?>
                            </span>
                        </div>
                        <div style="font-size: 11px;">Nama: <strong><?php echo htmlspecialchars($ord['nama']); ?></strong></div>
                        <div style="font-size: 11px;">Alamat: <strong><?php echo htmlspecialchars($ord['alamat']); ?></strong></div>
                        <div style="font-size: 11px;">Device: <strong><?php echo htmlspecialchars($ord['device']); ?></strong></div>
                        <div style="font-size: 11px; margin-top: 4px;">Metode Pembayaran: <strong><?php echo htmlspecialchars($ord['metode_pembayaran']); ?></strong></div>
                        <?php if (($ord['harga_beli'] ?? null) !== null): ?>
                            <div style="font-size: 11px;">Total Bayar: <strong style="color: #34d399;"><?php echo alzz_rp($ord['harga_beli']); ?></strong></div>
                        <?php endif; ?>
                        <div style="font-size: 11px;">Status Pesanan: 
                            <span style="font-weight: bold; color: <?php echo ($ord['status'] == 'Selesai' || $ord['status'] == 'Dikonfirmasi') ? '#34d399' : '#facc15'; ?>">
                                <?php echo htmlspecialchars($ord['status']); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($ord['bukti_pembayaran'])): ?>
                            <div style="margin-top: 6px; font-size: 11px;">
                                <a href="<?php echo htmlspecialchars($ord['bukti_pembayaran']); ?>" target="_blank" style="color: #38bdf8; text-decoration: underline;">Lihat Bukti Transfer yang Diunggah</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($ord['data_pesanan'])): ?>
                            <div style="margin-top: 14px; background: rgba(52, 211, 153, 0.1); border: 1px solid #34d399; padding: 12px; border-radius: 6px; font-size: 11px;">
                                <strong style="color: #34d399; display: block; margin-bottom: 6px;">Data Akun / Pesanan Anda:</strong>
                                <div style="color: #fff; word-break: break-all; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($ord['data_pesanan'])); ?></div>
                                
                                <div style="margin-top: 25px;"></div>
                                
                                <strong style="color: #38bdf8; display: block; margin-bottom: 6px;">Cara Login / Menggunakan Akun:</strong>
                                <div style="color: #cbd5e1; word-break: break-all; line-height: 1.6;">
                                    <?php 
                                        if (!empty($ord['cara_login'])) {
                                            echo nl2br(htmlspecialchars($ord['cara_login']));
                                        } else {
                                            echo "1. Salin data username/email dan password di atas dengan benar.<br>2. Login ke aplikasi atau platform resmi sesuai produk yang dibeli.<br>3. Jangan mengubah data akun agar garansi tetap aktif.";
                                        }
                                    ?>
                                </div>
                                <div style="margin-top: 45px;"></div>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 10px; padding: 8px; background: rgba(250, 204, 21, 0.1); border: 1px solid #facc15; border-radius: 6px; color: #facc15; font-style: italic; font-weight: bold; font-size: 11px;">
                                Pesanan anda akan segera di proses oleh admin.
                            </div>
                        <?php endif; ?>

                        <div style="text-align: right; margin-top: 12px;">
                            <a href="https://wa.me/<?php echo htmlspecialchars($wa_admin); ?>?text=Halo%20Admin,%20saya%20User%20<?php echo urlencode($current_buyer); ?>%20ingin%20konfirmasi%20pesanan%20produk%20<?php echo urlencode($ord['nama_app']); ?>%20(ID:%20<?php echo $ord['id']; ?>)" target="_blank" style="background: #25d366; color: #fff; padding: 5px 12px; border-radius: 6px; font-size: 10px; text-decoration: none; font-weight: bold; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                WhatsApp Admin
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="index.php" class="btn-buy-mini" style="display: inline-block; text-decoration: none; padding: 10px 20px;">Kembali ke Beranda</a>
                </div>
            <?php else: ?>
                <div class="admin-card" style="text-align: center; padding: 20px;">
                    <p style="color: #94a3b8; font-size: 12px; margin-bottom: 15px;">Belum ada pesanan untuk akun ini.</p>
                    <a href="index.php" class="btn-buy-mini" style="display: inline-block; text-decoration: none; padding: 10px 20px;">Mulai Belanja Sekarang</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- FORM PEMBELIAN -->
            <div class="admin-card" style="margin-bottom: 16px;">
                <div style="display: flex; gap: 12px; margin-bottom: 16px; align-items: center;">
                    <img src="<?php echo htmlspecialchars($produk['foto_produk']); ?>" alt="" style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px;">
                    <div>
                        <h3 style="color: #fff; font-size: 14px; margin-bottom: 4px;"><?php echo htmlspecialchars($produk['nama_app']); ?></h3>
                        <?php if ($hinfo['aktif']): ?>
                            <div style="color: #94a3b8; font-size: 11px; text-decoration: line-through;"><?php echo alzz_rp($hinfo['harga_asli']); ?></div>
                            <div style="color: #38bdf8; font-weight: bold; font-size: 13px;"><?php echo alzz_rp($hinfo['harga_akhir']); ?>
                                <span style="background:#ef4444;color:#fff;font-size:9px;padding:2px 6px;border-radius:6px;margin-left:4px;"><?php echo htmlspecialchars($hinfo['label']); ?></span>
                            </div>
                            <?php if ($hinfo['sampai_teks'] !== ''): ?>
                                <div style="color:#facc15;font-size:10px;margin-top:2px;">Diskon sampai <?php echo htmlspecialchars($hinfo['sampai_teks']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="color: #38bdf8; font-weight: bold; font-size: 13px;"><?php echo alzz_rp($hinfo['harga_asli']); ?></div>
                        <?php endif; ?>
                        <div style="color: #94a3b8; font-size: 11px; margin-top: 2px;">Stok: <?php echo $produk['stok']; ?></div>
                    </div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px; font-weight: bold;">Nama:</label>
                        <input type="text" name="nama" placeholder="Masukkan nama Anda" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px; font-weight: bold;">Alamat:</label>
                        <textarea name="alamat" placeholder="Masukkan nama kota saja" rows="2" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;"></textarea>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px; font-weight: bold;">Merk Device / Perangkat:</label>
                        <input type="text" name="device" placeholder="Contoh: Samsung A52 / iPhone 11 / Laptop ASUS" required style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 11px;">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 8px; font-weight: bold;">Pilih Metode Pembayaran:</label>
                        
                        <?php if ($metode_result && db_num_rows($metode_result) > 0): ?>
                            <?php while($m = db_fetch_assoc($metode_result)): ?>
                                <?php 
                                    $nama_m = $m['nama_metode'];
                                    $tujuan_m = $m['nomor_tujuan'];
                                    $status_db = trim($m['status'] ?? '');
                                    
                                    $pemilik_m = (!empty($status_db) && $status_db !== '-') ? ' - a.n ' . $status_db : '';
                                    // Untuk QRIS (tujuan berupa alamat gambar) yang disimpan di pesanan cukup nama metode,
                                    // bukan alamat gambarnya yang panjang (bisa membuat penyimpanan pesanan gagal)
                                    if (filter_var($tujuan_m, FILTER_VALIDATE_URL)) {
                                        $val_str = $nama_m . ($pemilik_m !== '' ? ' (' . ltrim($pemilik_m, ' -') . ')' : '');
                                    } else {
                                        $val_str = $nama_m . ' (' . $tujuan_m . $pemilik_m . ')';
                                    }
                                    
                                    $is_img = (filter_var($tujuan_m, FILTER_VALIDATE_URL) || stripos($nama_m, 'qris') !== false);
                                ?>
                                <label style="display: block; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 14px; margin-bottom: 10px; cursor: pointer;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="radio" name="metode_pembayaran" value="<?php echo htmlspecialchars($val_str); ?>" required style="width: 16px; height: 16px; accent-color: #38bdf8;">
                                        <strong style="color: #38bdf8; font-size: 13px;"><?php echo htmlspecialchars($nama_m); ?></strong>
                                    </div>
                                    
                                    <?php if ($is_img && filter_var($tujuan_m, FILTER_VALIDATE_URL)): ?>
                                        <div style="margin-top: 12px; text-align: center;">
                                            <img src="<?php echo htmlspecialchars($tujuan_m); ?>" alt="QRIS" onclick="openQrModal('<?php echo htmlspecialchars($tujuan_m); ?>')" style="max-width: 180px; width: 100%; border-radius: 8px; border: 2px solid rgba(255,255,255,0.3); display: block; margin: 0 auto 8px auto; cursor: pointer;" title="Ketuk untuk memperbesar">
                                            <div style="font-size: 10px; color: #94a3b8; margin-bottom: 6px;">Ketuk gambar untuk memperbesar</div>
                                            <button type="button" onclick="downloadFile('<?php echo htmlspecialchars($tujuan_m); ?>', 'QRIS_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $nama_m); ?>.jpg', event)" style="background: #38bdf8; color: #000; border: none; padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: bold; cursor: pointer;">Unduh QR Code</button>
                                        </div>
                                    <?php else: ?>
                                        <div style="color: #e2e8f0; font-size: 12px; margin-top: 8px; padding-left: 26px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                                            <div>
                                                Tujuan: <strong id="num_<?php echo $m['id']; ?>" style="color: #fff;"><?php echo htmlspecialchars($tujuan_m); ?></strong>
                                                <?php if (!empty($status_db) && $status_db !== '-'): ?>
                                                    <span style="color: #cbd5e1; font-size: 11px;"> (a.n <?php echo htmlspecialchars($status_db); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" onclick="copyText('num_<?php echo $m['id']; ?>', event)" style="background: rgba(56, 189, 248, 0.15); border: 1px solid #38bdf8; color: #38bdf8; padding: 4px 10px; border-radius: 4px; font-size: 10px; cursor: pointer; font-weight: bold;">Salin Nomor</button>
                                        </div>
                                    <?php endif; ?>
                                </label>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #94a3b8; font-size: 12px;">Belum ada metode pembayaran yang tersedia.</p>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 6px; font-weight: bold;">Upload Bukti Pembayaran (Foto):</label>
                        <input type="file" name="bukti_pembayaran" accept="image/*" required style="width: 100%; font-size: 11px; color: #fff; padding: 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px;">
                    </div>

                    <div style="background: rgba(56,189,248,0.08); border: 1px solid #38bdf8; border-radius: 8px; padding: 10px; margin-bottom: 12px; text-align: center;">
                        <div style="font-size: 10px; color: #94a3b8;">Total yang harus dibayar</div>
                        <div style="font-size: 16px; font-weight: bold; color: #38bdf8;"><?php echo alzz_rp($hinfo['harga_akhir']); ?></div>
                    </div>
                    <button type="submit" class="btn-buy-mini" style="width: 100%; padding: 12px; font-size: 13px; cursor: pointer; font-weight: bold;">Kirim Pesanan & Bukti Pembayaran</button>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <!-- MODAL ZOOM QRIS -->
    <div id="qrModal" onclick="closeQrModal()">
        <img id="modalImg" src="" alt="QRIS Zoom">
        <span>Tutup [X]</span>
    </div>

    <script>
        function openQrModal(imgUrl) {
            const modal = document.getElementById('qrModal');
            const modalImg = document.getElementById('modalImg');
            modalImg.src = imgUrl;
            modal.style.display = 'flex';
        }

        function closeQrModal() {
            const modal = document.getElementById('qrModal');
            modal.style.display = 'none';
        }

        function copyText(elementId, event) {
            event.preventDefault();
            event.stopPropagation();
            const text = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(text).then(function() {
                alert('Nomor berhasil disalin!');
            }, function(err) {
                alert('Gagal menyalin nomor.');
            });
        }

        function downloadFile(url, filename, event) {
            event.preventDefault();
            event.stopPropagation();
            
            fetch(url)
                .then(response => response.blob())
                .then(blob => {
                    const blobUrl = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = blobUrl;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(blobUrl);
                    document.body.removeChild(a);
                })
                .catch(err => {
                    window.open(url, '_blank');
                });
        }
    </script>
    <script>window.ALZZ_MUSIC_URL = <?php echo json_encode($admin_data['music_url'] ?? ''); ?>;</script>
    <script src="music.js?v=<?php echo filemtime(__DIR__ . '/music.js'); ?>"></script>
</body>
</html>
