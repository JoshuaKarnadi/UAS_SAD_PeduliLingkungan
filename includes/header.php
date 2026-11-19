<?php

require_once __DIR__ . '/../config/session.php';
mulai_sesi_aman();

if (sesi_idle_habis()) {
    session_unset();
    session_regenerate_id(true);
} elseif (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$sudah_login   = isset($_SESSION['user_id']);
$adalah_admin  = $sudah_login && ($_SESSION['role'] ?? '') === 'admin';
$judul_halaman = $judul_halaman ?? 'PeduliLingkungan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($judul_halaman, ENT_QUOTES, 'UTF-8') ?> - PeduliLingkungan</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body<?= !empty($halaman_admin) ? ' class="halaman-admin"' : '' ?>>

<nav class="navbar">
    <?php if ($sudah_login): ?>

        <?php if (!$adalah_admin): ?>
            <a href="index.php">Beranda</a>
            <a href="laporan_saya.php">Laporan Saya</a>
            <a href="kegiatan_saya.php">Kegiatan Saya</a>
        <?php endif; ?>

        <?php if ($adalah_admin): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>

        <span><?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        <form action="actions/logout.php" method="post">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-danger">Logout</button>
        </form>
    <?php else: ?>
        <a href="index.php">Beranda</a>
        <a href="login.php">Login</a>
    <?php endif; ?>
</nav>

<?php if (!empty($halaman_admin)): ?>
    <div class="admin-banner">AREA ADMIN: halaman ini hanya untuk administrator</div>
<?php endif; ?>

<main class="container-utama">
