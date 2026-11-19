<?php

require_once __DIR__ . '/../config/session.php';
mulai_sesi_aman();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Cek CSRF
$token_post = $_POST['csrf_token'] ?? '';
$token_sesi = $_SESSION['csrf_token'] ?? '';
if (!is_string($token_post) || $token_sesi === '' || !hash_equals($token_sesi, $token_post)) {
    catat_log($pdo, (int) $_SESSION['user_id'], 'CSRF_FAILED', 'Logout: token CSRF tidak valid');
    header('Location: ../index.php');
    exit;
}

catat_log($pdo, (int) $_SESSION['user_id'], 'LOGOUT', 'Logout');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

mulai_sesi_aman();
$_SESSION['flash_success'] = 'Anda telah keluar.';

header('Location: ../login.php');
exit;
