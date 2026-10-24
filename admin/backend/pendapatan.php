<?php
if (!defined('ALZZ_ADMIN')) { http_response_code(403); exit('Akses ditolak'); }
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
?>
