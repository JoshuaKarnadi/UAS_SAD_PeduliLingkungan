<?php

require_once __DIR__ . '/../includes/auth_check.php';
wajib_role('admin');
require_once __DIR__ . '/../helpers/gambar.php';

$admin_id = (int) $_SESSION['user_id'];

function kembali(string $pesan, string $filter = 'all', bool $sukses = false): void
{
    global $laporan_id;

    $_SESSION[$sukses ? 'flash_success' : 'flash_error'] = $pesan;

    if (isset($laporan_id) && is_int($laporan_id)) {
        header('Location: ../admin_detail.php?id=' . $laporan_id);
    } else {
        header('Location: ../admin.php?filter=' . rawurlencode($filter));
    }
    exit;
}

function teks_valid(string $teks): bool
{
    return mb_check_encoding($teks, 'UTF-8') && !str_contains($teks, "\0");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php');
    exit;
}

$filter = $_POST['filter'] ?? 'all';
if (!is_string($filter) || !in_array($filter, ['all', 'reported', 'gotong_royong', 'done'], true)) {
    $filter = 'all';
}


$token_post = $_POST['csrf_token'] ?? '';
$token_sesi = $_SESSION['csrf_token'] ?? '';
if (!is_string($token_post) || $token_sesi === '' || !hash_equals($token_sesi, $token_post)) {
    catat_log($pdo, $admin_id, 'CSRF_FAILED', 'Admin: token CSRF tidak valid');
    kembali('Permintaan tidak valid. Silakan coba lagi.', $filter);
}


$aksi = $_POST['aksi'] ?? '';
if (!is_string($aksi) || !in_array($aksi, ['setujui', 'tolak', 'selesai', 'ubah', 'ubah_volunteer', 'hapus', 'unblock_ip'], true)) {
    kembali('Aksi tidak dikenal.', $filter);
}


if ($aksi === 'unblock_ip') {
    $ip = $_POST['ip'] ?? '';
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        kembali('Alamat IP tidak valid.', $filter);
    }
    $ip = inet_ntop(inet_pton($ip));  

    catat_log($pdo, $admin_id, 'IP_UNBLOCKED', 'Blokir IP dibuka oleh admin', $ip);
    kembali('Blokir IP ' . $ip . ' dibuka.', $filter, true);
}

$laporan_id = filter_var(
    $_POST['laporan_id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
);
if ($laporan_id === false) {
    kembali('Permintaan tidak valid.', $filter);
}

$zona = new DateTimeZone('Asia/Jakarta');


if ($aksi === 'ubah') {
    $judul     = $_POST['judul'] ?? '';
    $lokasi    = $_POST['lokasi'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';

    if (!is_string($judul) || !is_string($lokasi) || !is_string($deskripsi)) {
        kembali('Input tidak valid.', $filter);
    }
    $judul     = trim($judul);
    $lokasi    = trim($lokasi);
    $deskripsi = trim($deskripsi);

    if (!teks_valid($judul) || !teks_valid($lokasi) || !teks_valid($deskripsi)) {
        kembali('Input mengandung karakter yang tidak valid.', $filter);
    }
    $len = mb_strlen($judul, 'UTF-8');
    if ($len < 5 || $len > 150) {
        kembali('Judul harus 5-150 karakter.', $filter);
    }
    $len = mb_strlen($lokasi, 'UTF-8');
    if ($len < 3 || $len > 255) {
        kembali('Lokasi harus 3-255 karakter.', $filter);
    }
    $len = mb_strlen($deskripsi, 'UTF-8');
    if ($len < 10 || $len > 2000) {
        kembali('Deskripsi harus 10-2000 karakter.', $filter);
    }

    $pesan_error = null;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT judul, lokasi, deskripsi, status
             FROM laporan WHERE id = ? AND deleted_at IS NULL FOR UPDATE'
        );
        $stmt->execute([$laporan_id]);
        $lama = $stmt->fetch();

        if ($lama === false) {
            $pesan_error = 'Laporan tidak ditemukan.';
        } elseif ($lama['status'] === 'done') {
            $pesan_error = 'Laporan yang sudah selesai tidak dapat diubah.';
        } else {
            $stmt = $pdo->prepare('UPDATE laporan SET judul = ?, lokasi = ?, deskripsi = ? WHERE id = ?');
            $stmt->execute([$judul, $lokasi, $deskripsi, $laporan_id]);
        }

        if ($pesan_error === null) {
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Edit laporan gagal: ' . $e->getMessage());
        kembali('Terjadi kesalahan pada server.', $filter);
    }

    if ($pesan_error !== null) {
        kembali($pesan_error, $filter);
    }

    $berubah = [];
    if ($judul !== $lama['judul'])         { $berubah[] = 'judul'; }
    if ($lokasi !== $lama['lokasi'])       { $berubah[] = 'lokasi'; }
    if ($deskripsi !== $lama['deskripsi']) { $berubah[] = 'deskripsi'; }

    if (empty($berubah)) {
        kembali('Tidak ada perubahan yang disimpan.', $filter, true);
    }

    catat_log($pdo, $admin_id, 'LAPORAN_EDITED', 'Laporan #' . $laporan_id . ' diubah: ' . implode(', ', $berubah));
    kembali('Perubahan disimpan.', $filter, true);
}


if ($aksi === 'ubah_volunteer') {
    $desk_vol    = $_POST['deskripsi_volunteer'] ?? '';
    $tanggal_raw = $_POST['tanggal_aksi'] ?? '';
    $kuota_raw   = $_POST['kuota'] ?? '';

    if (!is_string($desk_vol) || !is_string($tanggal_raw) || !is_string($kuota_raw)) {
        kembali('Input tidak valid.', $filter);
    }
    $desk_vol    = trim($desk_vol);
    $tanggal_raw = trim($tanggal_raw);
    $kuota_raw   = trim($kuota_raw);

    if (!teks_valid($desk_vol)) {
        kembali('Deskripsi volunteer mengandung karakter yang tidak valid.', $filter);
    }
    $len = mb_strlen($desk_vol, 'UTF-8');
    if ($len < 10 || $len > 1000) {
        kembali('Deskripsi volunteer harus 10-1000 karakter.', $filter);
    }

    $tgl = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal_raw, $zona);
    if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal_raw) {
        kembali('Tanggal aksi harus berupa tanggal yang valid.', $filter);
    }

    $kuota_baru = filter_var(
        $kuota_raw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 500]]
    );
    if ($kuota_baru === false) {
        kembali('Kuota harus berupa angka 1-500.', $filter);
    }

    $pesan_error = null;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT deskripsi_volunteer, tanggal_aksi, kuota, status
             FROM laporan WHERE id = ? AND deleted_at IS NULL FOR UPDATE'
        );
        $stmt->execute([$laporan_id]);
        $lama = $stmt->fetch();

        if ($lama === false) {
            $pesan_error = 'Laporan tidak ditemukan.';
        } elseif ($lama['status'] !== 'gotong_royong') {
            $pesan_error = 'Data kegiatan hanya dapat diubah saat berstatus Gotong Royong.';
        } else {
            
            if ($tanggal_raw !== $lama['tanggal_aksi']) {
                $hari_ini = new DateTimeImmutable('today', $zona);
                if ($tgl < $hari_ini || $tgl > $hari_ini->modify('+365 days')) {
                    $pesan_error = 'Tanggal aksi yang baru harus dari hari ini sampai 1 tahun ke depan.';
                }
            }
            
            if ($pesan_error === null) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM laporan_participants WHERE laporan_id = ?');
                $stmt->execute([$laporan_id]);
                if ($kuota_baru < (int) $stmt->fetchColumn()) {
                    $pesan_error = 'Kuota tidak boleh lebih kecil dari jumlah peserta yang sudah terdaftar.';
                }
            }
        }

        if ($pesan_error === null) {
            $stmt = $pdo->prepare(
                'UPDATE laporan SET deskripsi_volunteer = ?, tanggal_aksi = ?, kuota = ? WHERE id = ?'
            );
            $stmt->execute([$desk_vol, $tanggal_raw, $kuota_baru, $laporan_id]);
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Edit kegiatan gagal: ' . $e->getMessage());
        kembali('Terjadi kesalahan pada server.', $filter);
    }

    if ($pesan_error !== null) {
        kembali($pesan_error, $filter);
    }

    $berubah = [];
    if ($desk_vol !== (string) ($lama['deskripsi_volunteer'] ?? '')) { $berubah[] = 'deskripsi_volunteer'; }
    if ($tanggal_raw !== $lama['tanggal_aksi'])                      { $berubah[] = 'tanggal_aksi'; }
    if ($kuota_baru !== (int) $lama['kuota'])                        { $berubah[] = 'kuota'; }

    if (empty($berubah)) {
        kembali('Tidak ada perubahan yang disimpan.', $filter, true);
    }

    catat_log($pdo, $admin_id, 'VOLUNTEER_EDITED', 'Laporan #' . $laporan_id . ' diubah: ' . implode(', ', $berubah));
    kembali('Data kegiatan disimpan.', $filter, true);
}

try {
    if ($aksi === 'setujui') {
        $desk_raw    = $_POST['deskripsi_volunteer'] ?? '';
        $tanggal_raw = $_POST['tanggal_aksi'] ?? '';
        $kuota_raw   = $_POST['kuota'] ?? '';
        if (!is_string($desk_raw) || !is_string($tanggal_raw) || !is_string($kuota_raw)) {
            kembali('Input tidak valid.', $filter);
        }
        $desk_raw    = trim($desk_raw);
        $tanggal_raw = trim($tanggal_raw);
        $kuota_raw   = trim($kuota_raw);

        if (!teks_valid($desk_raw)) {
            kembali('Deskripsi volunteer mengandung karakter yang tidak valid.', $filter);
        }
        $len = mb_strlen($desk_raw, 'UTF-8');
        if ($len < 10 || $len > 1000) {
            kembali('Deskripsi volunteer harus 10-1000 karakter.', $filter);
        }

        $hari_ini = new DateTimeImmutable('today', $zona);
        $maks     = $hari_ini->modify('+365 days');
        $tgl      = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal_raw, $zona);

        if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal_raw || $tgl < $hari_ini || $tgl > $maks) {
            kembali('Tanggal aksi harus tanggal yang valid, dari hari ini sampai 1 tahun ke depan.', $filter);
        }

        $kuota = filter_var(
            $kuota_raw,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 500]]
        );
        if ($kuota === false) {
            kembali('Kuota harus berupa angka 1-500.', $filter);
        }

        $stmt = $pdo->prepare(
            "UPDATE laporan
             SET status = 'gotong_royong', permintaan_volunteer = 'disetujui',
                 deskripsi_volunteer = ?, tanggal_aksi = ?, kuota = ?
             WHERE id = ? AND status = 'reported' AND deleted_at IS NULL"
        );
        $stmt->execute([$desk_raw, $tgl->format('Y-m-d'), $kuota, $laporan_id]);

        $log_aksi = 'VOLUNTEER_APPROVED';
        $pesan_ok = 'Kegiatan volunteer dibuka.';

    } elseif ($aksi === 'tolak') {
        $stmt = $pdo->prepare(
            "UPDATE laporan SET permintaan_volunteer = 'ditolak'
             WHERE id = ? AND status = 'reported' AND permintaan_volunteer = 'menunggu' AND deleted_at IS NULL"
        );
        $stmt->execute([$laporan_id]);

        $log_aksi = 'VOLUNTEER_REJECTED';
        $pesan_ok = 'Pengajuan volunteer ditolak.';
    
    } elseif ($aksi === 'hapus') {
        if (($_POST['konfirmasi_hapus'] ?? '') !== '1') {
            kembali('Centang konfirmasi untuk menghapus laporan.', $filter);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT gambar FROM laporan WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$laporan_id]);
        $gambar_lama = $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'UPDATE laporan SET deleted_at = NOW(), gambar = NULL
            WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$laporan_id]);
        $pdo->commit();

        $log_aksi = 'LAPORAN_DELETED';
        $pesan_ok = 'Laporan dihapus (soft-delete).';

    } else { 
    
    $stmt = $pdo->prepare(
    "UPDATE laporan
     SET status = 'done',
         permintaan_volunteer = CASE
             WHEN permintaan_volunteer = 'menunggu' THEN 'ditolak'
             ELSE permintaan_volunteer END
     WHERE id = ? AND status IN ('reported', 'gotong_royong') AND deleted_at IS NULL"
);
$stmt->execute([$laporan_id]);

$log_aksi = 'LAPORAN_DONE';
$pesan_ok = 'Laporan ditandai selesai.';
    }
} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
            $pdo->rollBack();
    }
    error_log('Aksi admin gagal: ' . $e->getMessage());
    kembali('Terjadi kesalahan pada server.', $filter);
}

if ($stmt->rowCount() === 0) {
    catat_log($pdo, $admin_id, 'ADMIN_ACTION_REJECTED', 'Aksi ' . $aksi . ' pada laporan #' . $laporan_id . ' tidak sah');
    kembali('Aksi tidak dapat dilakukan: laporan tidak ditemukan atau statusnya sudah berubah.', $filter);
}

catat_log($pdo, $admin_id, $log_aksi, 'Laporan #' . $laporan_id);

if ($aksi === 'hapus' && is_string($gambar_lama)) {
    hapus_berkas_gambar($gambar_lama);
}

kembali($pesan_ok, $filter, true);
