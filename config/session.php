<?php

function kirim_header_keamanan(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';");
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

function mulai_sesi_aman(): void
{
    kirim_header_keamanan();

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

const BATAS_IDLE_DETIK = 900; 

function sesi_idle_habis(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $terakhir = $_SESSION['last_activity'] ?? 0;
    return (time() - $terakhir) > BATAS_IDLE_DETIK;
}