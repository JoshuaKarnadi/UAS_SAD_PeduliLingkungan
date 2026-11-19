<?php

require_once __DIR__ . '/../config/session.php';
mulai_sesi_aman();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

const BATAS_GAGAL = 5;   

function kembali_dengan_error(string $pesan): void
{
    $_SESSION['flash_error'] = $pesan;
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$token_post = $_POST['csrf_token'] ?? '';
$token_sesi = $_SESSION['csrf_token'] ?? '';
if (!is_string($token_post) || $token_sesi === '' || !hash_equals($token_sesi, $token_post)) {
    catat_log($pdo, null, 'CSRF_FAILED', 'Login: token CSRF tidak valid');
    kembali_dengan_error('Permintaan tidak valid. Silakan coba lagi.');
}

$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
if (!is_string($email) || !is_string($password)) {
    kembali_dengan_error('Input tidak valid.');
}
$email = strtolower(trim($email));

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM activity_logs
     WHERE action = 'LOGIN_FAILED'
       AND ip_address = ?
       AND created_at > NOW() - INTERVAL '5 minutes'
       AND created_at > COALESCE(
             (SELECT MAX(created_at) FROM activity_logs
              WHERE action = 'IP_UNBLOCKED' AND ip_address = ?),
             '-infinity'::timestamp)"
);
$stmt->execute([$ip, $ip]);

if ((int) $stmt->fetchColumn() >= BATAS_GAGAL) {
    catat_log($pdo, null, 'LOGIN_BLOCKED', 'Percobaan login saat IP diblokir');
    kembali_dengan_error('Terlalu banyak percobaan gagal. Coba lagi beberapa menit lagi.');
}

$stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

$berhasil = false;
if ($user !== false) {
    $berhasil = strlen($password) <= 72 && password_verify($password, $user['password']);
} else {
    password_hash($password, PASSWORD_DEFAULT);
}

if (!$berhasil) {
    $id_user = ($user !== false) ? (int) $user['id'] : null;
    catat_log($pdo, $id_user, 'LOGIN_FAILED', 'Login gagal');
    kembali_dengan_error('Email atau password salah.');
}

session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['id'];
$_SESSION['username']      = $user['username'];
$_SESSION['role']          = $user['role'];
$_SESSION['last_activity'] = time();
$_SESSION['csrf_token']    = bin2hex(random_bytes(32));

catat_log($pdo, (int) $user['id'], 'LOGIN_SUCCESS', 'Login berhasil');
if ($user['role'] === 'admin') {
    header('Location: ../admin.php');
} else {
    header('Location: ../index.php');
}

exit;
