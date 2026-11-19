<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

mulai_sesi_aman();

function url_dasar(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (basename($dir) === 'actions') {
        $dir = dirname($dir);
    }
    return rtrim($dir, '/');
}

function arahkan_ke_login(string $pesan = ''): void
{
    if ($pesan !== '') {
        $_SESSION['flash_error'] = $pesan;
    }
    header('Location: ' . url_dasar() . '/login.php');
    exit;
}

function wajib_role(string $role): void
{
    global $pdo;

    if (($_SESSION['role'] ?? '') !== $role) {
        catat_log(
            $pdo,
            (int) $_SESSION['user_id'],
            'ACCESS_DENIED',
            'Akses ditolak ke ' . basename($_SERVER['SCRIPT_NAME'] ?? '')
        );
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

if (empty($_SESSION['user_id'])) {
    arahkan_ke_login();
}

if (sesi_idle_habis()) {
    catat_log($pdo, (int) $_SESSION['user_id'], 'SESSION_TIMEOUT', 'Sesi berakhir karena tidak aktif');
    session_unset();
    session_regenerate_id(true);
    arahkan_ke_login('Sesi berakhir karena tidak aktif. Silakan masuk lagi.');
}

$_SESSION['last_activity'] = time();
