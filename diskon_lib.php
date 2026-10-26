<?php
// ============================================================================
// DISKON PRODUK
// Setiap produk bisa punya diskon: persen (mis. 20%) atau potongan rupiah (mis. Rp 5.000),
// dengan batas waktu opsional. Harga akhir SELALU dihitung di server.
// Kolom di tabel produk: diskon_tipe ('persen'/'nominal'), diskon_nilai, diskon_sampai
// ============================================================================

// Tambahkan kolom diskon ke tabel produk bila belum ada (aman dijalankan berulang)
function alzz_diskon_ensure($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $r = db_query($conn, "SELECT 1 FROM information_schema.columns WHERE table_name = 'produk' AND column_name = 'diskon_nilai' LIMIT 1");
    if ($r && db_num_rows($r) > 0) return;
    db_query($conn, "ALTER TABLE produk
        ADD COLUMN IF NOT EXISTS diskon_tipe varchar(10) NOT NULL DEFAULT 'persen',
        ADD COLUMN IF NOT EXISTS diskon_nilai integer NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS diskon_sampai timestamp NULL");
}

function alzz_rp($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}

// Hitung harga akhir dari satu baris produk
function alzz_harga_info(array $p) {
    $harga = max(0, (int)($p['harga'] ?? 0));
    $tipe  = (($p['diskon_tipe'] ?? 'persen') === 'nominal') ? 'nominal' : 'persen';
    $nilai = (int)($p['diskon_nilai'] ?? 0);
    $sampai = trim((string)($p['diskon_sampai'] ?? ''));

    $aktif = ($nilai > 0 && $harga > 0);
    if ($aktif && $sampai !== '') {
        $t = strtotime($sampai);
        if ($t !== false && $t <= time()) $aktif = false; // diskon sudah berakhir
    }

    $akhir = $harga;
    if ($aktif) {
        $akhir = ($tipe === 'persen')
            ? (int)round($harga * (100 - min($nilai, 100)) / 100)
            : max(0, $harga - $nilai);
        if ($akhir >= $harga) { $aktif = false; $akhir = $harga; }
    }

    $persen = ($aktif && $harga > 0) ? (int)round(($harga - $akhir) / $harga * 100) : 0;
    return [
        'aktif'       => $aktif,
        'harga_asli'  => $harga,
        'harga_akhir' => $akhir,
        'hemat'       => $harga - $akhir,
        'persen'      => $persen,
        'label'       => $aktif ? ($tipe === 'persen' ? '-' . $nilai . '%' : 'HEMAT ' . alzz_rp($harga - $akhir)) : '',
        'sampai_teks' => ($aktif && $sampai !== '' && strtotime($sampai) !== false) ? date('d M Y H:i', strtotime($sampai)) : '',
    ];
}
