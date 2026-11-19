<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if (
    preg_match('#(^|/)\.#', $uri) ||
    preg_match('#^/(config|includes)(/|$)#i', $uri)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403 Forbidden';
    exit;
}

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

$filePath = __DIR__ . $uri;

if ($uri !== '/' && is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
    $mimeTypes = [
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript; charset=UTF-8',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ];
    $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

if ($uri !== '/' && is_file($filePath)) {
    return false;
}

require_once __DIR__ . '/index.php';
