<?php

require_once __DIR__ . '/../config/session.php';
mulai_sesi_aman();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

function kembali_dengan_error(string $pesan, bool $hitung = true): void
{
    global $pdo;

    if ($hitung) {
        catat_log($pdo, null, 'REGISTER_FAILED', 'Registrasi gagal');
    }

    $_SESSION['flash_error'] = $pesan;
    header('Location: ../register.php');
    exit;
}

$token_post = $_POST['csrf_token'] ?? '';
$token_sesi = $_SESSION['csrf_token'] ?? '';
if (!is_string($token_post) || $token_sesi === '' || !hash_equals($token_sesi, $token_post)) {
    catat_log($pdo, null, 'CSRF_FAILED', 'Registrasi: token CSRF tidak valid');
    kembali_dengan_error('Permintaan tidak valid. Silakan coba lagi.', false);
}

const BATAS_DAFTAR = 10;  
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM activity_logs
     WHERE action IN ('REGISTER_FAILED', 'REGISTER_SUCCESS')
       AND ip_address = ?
       AND created_at > NOW() - INTERVAL '10 minutes'
       AND created_at > COALESCE(
             (SELECT MAX(created_at) FROM activity_logs
              WHERE action = 'IP_UNBLOCKED' AND ip_address = ?),
             '-infinity'::timestamp)"
);
$stmt->execute([$ip, $ip]);

if ((int) $stmt->fetchColumn() >= BATAS_DAFTAR) {
    catat_log($pdo, null, 'REGISTER_BLOCKED', 'Registrasi saat IP dibatasi');
    kembali_dengan_error(
        'Terlalu banyak percobaan pendaftaran. Coba lagi beberapa menit lagi.',
        false
    );
}

$username   = $_POST['username'] ?? '';
$email      = $_POST['email'] ?? '';
$password   = $_POST['password'] ?? '';
$konfirmasi = $_POST['konfirmasi'] ?? '';

if (!is_string($username) || !is_string($email)
    || !is_string($password) || !is_string($konfirmasi)) {
    kembali_dengan_error('Input tidak valid.');
}
$username = trim($username);
$email    = strtolower(trim($email));

if (!preg_match('/\A[A-Za-z0-9_]{3,30}\z/', $username)) {
    kembali_dengan_error('Username 3-30 karakter: huruf, angka, atau garis bawah.');
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    kembali_dengan_error('Format email tidak valid.');
}
if (strlen($password) < 8 || strlen($password) > 72) {
    kembali_dengan_error('Password harus 8-72 karakter.');
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    kembali_dengan_error('Password harus mengandung huruf dan angka.');
}
if ($password !== $konfirmasi) {
    kembali_dengan_error('Konfirmasi password tidak sama.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password, role)
         VALUES (?, ?, ?, 'user') RETURNING id"
    );
    $stmt->execute([$username, $email, $hash]);
    $user_id = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        kembali_dengan_error('Email sudah terdaftar.');
    }
    error_log('Registrasi gagal: ' . $e->getMessage());
    kembali_dengan_error('Terjadi kesalahan pada server.');
}

catat_log($pdo, $user_id, 'REGISTER_SUCCESS', 'Akun baru dibuat');

$_SESSION['flash_success'] = 'Registrasi berhasil. Silakan masuk.';
header('Location: ../login.php');
exit;
