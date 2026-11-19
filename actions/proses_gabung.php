<?php


require_once __DIR__ . '/../includes/auth_check.php';

function kembali(string $pesan, int $laporan_id = 0, bool $sukses = false): void
{
    $_SESSION[$sukses ? 'flash_success' : 'flash_error'] = $pesan;
    $tujuan = $laporan_id > 0 ? '../detail.php?id=' . $laporan_id : '../index.php';
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$token_post = $_POST['csrf_token'] ?? '';
$token_sesi = $_SESSION['csrf_token'] ?? '';
if (!is_string($token_post) || $token_sesi === '' || !hash_equals($token_sesi, $token_post)) {
    catat_log($pdo, $user_id, 'CSRF_FAILED', 'Gabung: token CSRF tidak valid');
    kembali('Permintaan tidak valid. Silakan coba lagi.');
}

$laporan_id = filter_var(
    $_POST['laporan_id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
);
if ($laporan_id === false) {
    kembali('Permintaan tidak valid.');
}

$hari_ini    = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$pesan_error = null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT status, tanggal_aksi, kuota FROM laporan WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
    $stmt->execute([$laporan_id]);
    $laporan = $stmt->fetch();

    if ($laporan === false) {
        $pesan_error = 'Kegiatan tidak ditemukan.';
    } elseif ($laporan['status'] !== 'gotong_royong'
              || $laporan['tanggal_aksi'] === null
              || $laporan['kuota'] === null) {
        $pesan_error = 'Pendaftaran untuk kegiatan ini tidak dibuka.';
    } elseif ($laporan['tanggal_aksi'] < $hari_ini) {
        $pesan_error = 'Pendaftaran ditutup: tanggal aksi sudah lewat.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM laporan_participants WHERE laporan_id = ?');
        $stmt->execute([$laporan_id]);
        if ((int) $stmt->fetchColumn() >= (int) $laporan['kuota']) {
            $pesan_error = 'Kuota sudah penuh.';
        }
    }

    if ($pesan_error === null) {
        $stmt = $pdo->prepare('INSERT INTO laporan_participants (laporan_id, user_id) VALUES (?, ?)');
        $stmt->execute([$laporan_id, $user_id]);
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23505') {
        kembali('Anda sudah terdaftar di kegiatan ini.', $laporan_id);
    }
    error_log('Gabung gagal: ' . $e->getMessage());
    kembali('Terjadi kesalahan pada server.', $laporan_id);
}

if ($pesan_error !== null) {
    catat_log($pdo, $user_id, 'VOLUNTEER_JOIN_REJECTED', 'Laporan #' . $laporan_id . ': ' . $pesan_error);
    kembali($pesan_error, $laporan_id);
}

catat_log($pdo, $user_id, 'VOLUNTEER_JOINED', 'Mendaftar di laporan #' . $laporan_id);
kembali('Pendaftaran berhasil. Sampai jumpa di lokasi!', $laporan_id, true);
