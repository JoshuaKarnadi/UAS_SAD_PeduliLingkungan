<?php

require_once __DIR__ . '/config/session.php';
mulai_sesi_aman();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$judul_halaman = 'Masuk';
$flash_error   = $_SESSION['flash_error'] ?? '';
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

include_once 'includes/header.php';
?>
<div class="card-box">
    <h2>Masuk</h2>

    <?php if ($flash_error !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flash_success !== ''): ?>
        <div class="alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form action="actions/proses_login.php" method="post">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="email">Email</label>
            <input class="form-input" type="email" id="email" name="email"
                   maxlength="254" autocomplete="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input class="form-input" type="password" id="password" name="password"
                   maxlength="72" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn-primary">Masuk</button>
    </form>

    <p>Belum punya akun? <a href="register.php">Daftar</a></p>
</div>
<?php include_once 'includes/footer.php'; ?>
