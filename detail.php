<?php

require_once __DIR__ . '/config/session.php';
mulai_sesi_aman();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/gambar.php';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$uid      = (isset($_SESSION['user_id']) && !sesi_idle_habis()) ? (int) $_SESSION['user_id'] : null;
$is_admin = ($uid !== null && ($_SESSION['role'] ?? '') === 'admin');

$id = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
);

$laporan = false;
if ($id !== false) {
    $stmt = $pdo->prepare(
        "SELECT l.id, l.user_id, l.judul, l.lokasi, l.deskripsi, l.status,
                l.gambar,
                l.permintaan_volunteer, l.deskripsi_volunteer, l.tanggal_aksi, l.kuota,
                l.created_at, u.username,
                (SELECT COUNT(*) FROM laporan_participants lp WHERE lp.laporan_id = l.id) AS jumlah_peserta
         FROM laporan l
         JOIN users u ON u.id = l.user_id
         WHERE l.id = ? AND l.deleted_at IS NULL"
    );
    $stmt->execute([$id]);
    $laporan = $stmt->fetch();
}

if ($laporan === false) {
    http_response_code(404);
}

$label_status = [
    'reported'      => 'Dilaporkan',
    'gotong_royong' => 'Gotong Royong',
    'done'          => 'Selesai',
];
$label_permintaan = [
    'menunggu'  => 'Menunggu persetujuan admin',
    'ditolak'   => 'Pengajuan ditolak',
    'disetujui' => 'Disetujui',
];

$hari_ini = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');

$sudah_daftar       = false;
$tampil_volunteer   = false;
$pemilik_atau_admin = false;
$sisa               = 0;

if ($laporan !== false) {
    $tampil_volunteer   = ($laporan['status'] === 'gotong_royong')
        || ($laporan['status'] === 'done' && $laporan['permintaan_volunteer'] === 'disetujui');
    $pemilik_atau_admin = ($uid !== null && ($uid === (int) $laporan['user_id'] || $is_admin));
    $sisa               = max(0, (int) $laporan['kuota'] - (int) $laporan['jumlah_peserta']);

    if ($uid !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM laporan_participants WHERE laporan_id = ? AND user_id = ?');
        $stmt->execute([$laporan['id'], $uid]);
        $sudah_daftar = ($stmt->fetchColumn() !== false);
    }
}

$judul_halaman = ($laporan !== false) ? $laporan['judul'] : 'Tidak ditemukan';
include_once 'includes/header.php';
?>

<?php if ($flash_success !== ''): ?>
    <div class="alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flash_error !== ''): ?>
    <div class="alert-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($laporan === false): ?>
    <div class="card-box">
        <h2>Laporan tidak ditemukan</h2>
        <p><a href="index.php">Kembali ke daftar</a></p>
    </div>

<?php else: ?>
    <div class="card-box">
        <h2><?= htmlspecialchars($laporan['judul'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p>
            <span class="badge-status" data-status="<?= htmlspecialchars($laporan['status'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($label_status[$laporan['status']] ?? $laporan['status'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </p>
        <p>Dilaporkan oleh <?= htmlspecialchars($laporan['username'], ENT_QUOTES, 'UTF-8') ?>
           pada <?= htmlspecialchars(date('d-m-Y', strtotime($laporan['created_at'])), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Lokasi:</strong> <?= htmlspecialchars($laporan['lokasi'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= nl2br(htmlspecialchars($laporan['deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>

        <?php $url_gbr = url_gambar($laporan['gambar'] ?? null); ?>
        <?php if ($url_gbr !== null): ?>
            <p>
                <img src="<?= htmlspecialchars($url_gbr, ENT_QUOTES, 'UTF-8') ?>"
                     alt="Foto laporan: <?= htmlspecialchars($laporan['judul'], ENT_QUOTES, 'UTF-8') ?>">
            </p>
        <?php endif; ?>
    </div>

    <?php if ($tampil_volunteer): ?>
        <div class="card-box">
            <h3>Kegiatan Volunteer</h3>
            <p>
                <?php $dv = (string) ($laporan['deskripsi_volunteer'] ?? ''); ?>
                <?= $dv !== '' ? nl2br(htmlspecialchars($dv, ENT_QUOTES, 'UTF-8')) : '(Belum ada deskripsi kegiatan.)' ?>
            </p>
            <p><strong>Tanggal aksi:</strong>
                <?= $laporan['tanggal_aksi'] !== null
                    ? htmlspecialchars(date('d-m-Y', strtotime($laporan['tanggal_aksi'])), ENT_QUOTES, 'UTF-8')
                    : '-' ?></p>
            <p><strong>Kuota:</strong> <?= (int) $laporan['kuota'] ?>,
               terdaftar <?= (int) $laporan['jumlah_peserta'] ?>,
               sisa <?= $sisa ?></p>
        </div>
    <?php elseif ($pemilik_atau_admin && $laporan['permintaan_volunteer'] !== 'tidak'): ?>
        <div class="card-box">
            <h3>Pengajuan Volunteer (hanya terlihat oleh pelapor dan admin)</h3>
            <p><strong>Status:</strong>
                <?= htmlspecialchars($label_permintaan[$laporan['permintaan_volunteer']] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= nl2br(htmlspecialchars((string) $laporan['deskripsi_volunteer'], ENT_QUOTES, 'UTF-8')) ?></p>
            <p><strong>Usulan tanggal:</strong>
                <?= $laporan['tanggal_aksi'] !== null
                    ? htmlspecialchars(date('d-m-Y', strtotime($laporan['tanggal_aksi'])), ENT_QUOTES, 'UTF-8')
                    : '-' ?>
               | <strong>Usulan kuota:</strong>
                <?= $laporan['kuota'] !== null ? (int) $laporan['kuota'] : '-' ?></p>
        </div>
    <?php endif; ?>

    <?php if ($laporan['status'] === 'gotong_royong'): ?>
        <div class="card-box">
            <?php if ($laporan['tanggal_aksi'] !== null && $laporan['tanggal_aksi'] < $hari_ini): ?>
                <p>Pendaftaran ditutup: tanggal aksi sudah lewat.</p>
            <?php elseif ($sudah_daftar): ?>
                <p>Anda sudah terdaftar di kegiatan ini.</p>
            <?php elseif ($sisa <= 0): ?>
                <p>Kuota sudah penuh.</p>
            <?php elseif ($uid === null): ?>
                <p><a href="login.php">Masuk</a> untuk mendaftar sebagai volunteer.</p>
            <?php else: ?>
                <form action="actions/proses_gabung.php" method="post">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="laporan_id" value="<?= (int) $laporan['id'] ?>">
                    <button type="submit" class="btn-primary">Daftar Volunteer</button>
                </form>
            <?php endif; ?>
        </div>
    <?php elseif ($laporan['status'] === 'done'): ?>
        <div class="card-box"><p>Kegiatan ini sudah selesai.</p></div>
    <?php endif; ?>

    <p><a href="index.php">Kembali ke daftar</a></p>
<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>
