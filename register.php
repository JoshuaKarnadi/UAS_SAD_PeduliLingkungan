<?php

require_once __DIR__ . '/config/session.php';
mulai_sesi_aman();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$judul_halaman = 'Daftar';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

include_once 'includes/header.php';
?>
<div class="card-box">
    <h2>Daftar Akun</h2>

    <?php if ($flash_error !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form action="actions/proses_register.php" method="post">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input class="form-input" type="text" id="username" name="username"
                   maxlength="30" autocomplete="username" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input class="form-input" type="email" id="email" name="email"
                maxlength="254" autocomplete="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input class="form-input" type="password" id="password" name="password"
                   maxlength="72" autocomplete="new-password" required>
            <p>Minimal 8 karakter, kombinasi huruf dan angka.</p>
        </div>

        <div class="form-group">
            <label for="konfirmasi">Ulangi Password</label>
            <input class="form-input" type="password" id="konfirmasi" name="konfirmasi"
                   maxlength="72" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn-primary">Daftar</button>
    </form>

    <p>Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>
<?php include_once 'includes/footer.php'; ?>
