<?php

$env_path = dirname(__DIR__, 2) . '/.env';

if (!file_exists($env_path)) {
    error_log('File konfigurasi .env tidak ditemukan di: ' . $env_path);
    http_response_code(500);
    exit('Terjadi kesalahan pada server.');
}

$env = parse_ini_file($env_path);

$db_host = $env['DB_HOST'] ?? '';
$db_port = $env['DB_PORT'] ?? '';
$db_name = $env['DB_NAME'] ?? '';
$db_user = $env['DB_USER'] ?? '';
$db_pass = $env['DB_PASS'] ?? '';

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";

    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('Koneksi database gagal: ' . $e->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan pada server.');
}
