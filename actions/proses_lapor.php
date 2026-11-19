<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../helpers/gambar.php';

const BATAS_LAPOR_PER_JAM = 10;

function kembali_dengan_error(string $pesan, array $input = []): void
{
    $_SESSION['flash_error'] = $pesan;
    $_SESSION['input_lama']  = $input;
    header('Location: ../lapor.php');
    exit;
}

function teks_valid(string $teks): bool
{
    return mb_check_encoding($teks, 'UTF-8') && !str_contains($teks, "\0");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../lapor.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
    kembali_dengan_error('Ukuran data yang dikirim terlalu besar. Gambar maksimal 2 MB.');
}


$token_post = $_POST['csrf_token'] ?? '';
$token_sesi = $_SESSION['csrf_token'] ?? '';
if (!is_string($token_post) || $token_sesi === '' || !hash_equals($token_sesi, $token_post)) {
    catat_log($pdo, $user_id, 'CSRF_FAILED', 'Lapor: token CSRF tidak valid');
    kembali_dengan_error('Permintaan tidak valid. Silakan coba lagi.');
}


$judul     = $_POST['judul'] ?? '';
$lokasi    = $_POST['lokasi'] ?? '';
$deskripsi = $_POST['deskripsi'] ?? '';

if (!is_string($judul) || !is_string($lokasi) || !is_string($deskripsi)) {
    kembali_dengan_error('Input tidak valid.');
}

$judul     = trim($judul);
$lokasi    = trim($lokasi);
$deskripsi = trim($deskripsi);
$ajukan    = (($_POST['ajukan_gotong_royong'] ?? $_POST['ajukan_volunteer'] ?? '') === '1');

$tanggal_raw  = $_POST['tanggal_aksi'] ?? '';
$kuota_raw    = $_POST['kuota'] ?? '';
$desk_vol_raw = $_POST['deskripsi_volunteer'] ?? '';

if (!is_string($tanggal_raw) || !is_string($kuota_raw) || !is_string($desk_vol_raw)) {
    kembali_dengan_error('Input tidak valid.');
}

$tanggal_raw  = trim($tanggal_raw);
$kuota_raw    = trim($kuota_raw);
$desk_vol_raw = trim($desk_vol_raw);

$input = [
    'judul'                => $judul,
    'lokasi'               => $lokasi,
    'deskripsi'            => $deskripsi,
    'ajukan_gotong_royong' => $ajukan,
    'ajukan_volunteer'     => $ajukan,
    'deskripsi_volunteer'  => $desk_vol_raw,
    'tanggal_aksi'         => $tanggal_raw,
    'kuota'                => $kuota_raw,
];


if (!teks_valid($judul) || !teks_valid($lokasi) || !teks_valid($deskripsi)) {
    kembali_dengan_error('Input mengandung karakter yang tidak valid.');
}

$len_judul = mb_strlen($judul, 'UTF-8');
if ($len_judul < 5 || $len_judul > 150) {
    kembali_dengan_error('Judul harus 5-150 karakter.', $input);
}

$len_lokasi = mb_strlen($lokasi, 'UTF-8');
if ($len_lokasi < 3 || $len_lokasi > 255) {
    kembali_dengan_error('Lokasi harus 3-255 karakter.', $input);
}

$len_deskripsi = mb_strlen($deskripsi, 'UTF-8');
if ($len_deskripsi < 10 || $len_deskripsi > 2000) {
    kembali_dengan_error('Deskripsi harus 10-2000 karakter.', $input);
}


$deskripsi_volunteer = null;
$tanggal_aksi        = null;
$kuota               = null;

if ($ajukan) {
    if ($desk_vol_raw !== '') {
        if (!teks_valid($desk_vol_raw)) {
            kembali_dengan_error('Deskripsi volunteer mengandung karakter yang tidak valid.', $input);
        }
        $len_vol = mb_strlen($desk_vol_raw, 'UTF-8');
        if ($len_vol < 10 || $len_vol > 1000) {
            kembali_dengan_error('Deskripsi volunteer harus 10-1000 karakter.', $input);
        }
        $deskripsi_volunteer = $desk_vol_raw;
    }

    $zona  = new DateTimeZone('Asia/Jakarta');
    $besok = new DateTimeImmutable('tomorrow', $zona);
    $maks  = $besok->modify('+365 days');
    $tgl   = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal_raw, $zona);

    if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal_raw || $tgl < $besok || $tgl > $maks) {
        kembali_dengan_error('Tanggal aksi harus tanggal yang valid, mulai besok sampai 1 tahun ke depan.', $input);
    }

    $kuota_valid = filter_var(
        $kuota_raw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 500]]
    );
    if ($kuota_valid === false) {
        kembali_dengan_error('Kuota relawan harus berupa angka 1-500.', $input);
    }

    $tanggal_aksi = $tgl->format('Y-m-d');
    $kuota        = $kuota_valid;
}


$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM laporan
     WHERE user_id = ? AND created_at > NOW() - INTERVAL '1 hour'"
);
$stmt->execute([$user_id]);

if ((int) $stmt->fetchColumn() >= BATAS_LAPOR_PER_JAM) {
    catat_log($pdo, $user_id, 'LAPORAN_BLOCKED', 'Batas laporan per jam tercapai');
    kembali_dengan_error('Anda sudah membuat banyak laporan. Coba lagi dalam satu jam.', $input);
}


[$pesan_gambar, $gambar_mime, $gambar_data] = proses_gambar($_FILES['gambar'] ?? null);
if ($pesan_gambar !== null) {
    kembali_dengan_error($pesan_gambar . ' Silakan pilih ulang gambar Anda.', $input);
}

$permintaan  = $ajukan ? 'menunggu' : 'tidak';
$gambar_nama = null;
if ($gambar_data !== null) {
    $gambar_nama = simpan_berkas_gambar($gambar_data, $gambar_mime);
    if ($gambar_nama === null) {
        error_log('Gagal menulis gambar ke folder upload');
        kembali_dengan_error('Gambar gagal disimpan. Silakan pilih ulang gambar Anda.', $input);
    }
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO laporan
            (user_id, judul, lokasi, deskripsi, permintaan_volunteer,
             deskripsi_volunteer, tanggal_aksi, kuota, gambar)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id'
    );
    $stmt->execute([
        $user_id, $judul, $lokasi, $deskripsi,
        $permintaan, $deskripsi_volunteer, $tanggal_aksi, $kuota, $gambar_nama,
    ]);
    $laporan_id = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    hapus_berkas_gambar($gambar_nama);   
    error_log('Simpan laporan gagal: ' . $e->getMessage());
    kembali_dengan_error('Terjadi kesalahan pada server.', $input);
}

catat_log(
    $pdo,
    $user_id,
    'LAPORAN_CREATED',
    'Laporan #' . $laporan_id . ' dibuat' . ($gambar_nama !== null ? ' (dengan gambar)' : '')
);

if ($ajukan) {
    catat_log($pdo, $user_id, 'GOTONG_ROYONG_REQUESTED', 'Laporan #' . $laporan_id . ' mengajukan gotong royong');
}

$_SESSION['flash_success'] = $ajukan
    ? 'Laporan terkirim dan sudah tampil. Pengajuan volunteer menunggu persetujuan admin.'
    : 'Laporan berhasil dikirim.';

header('Location: ../index.php');
exit;
