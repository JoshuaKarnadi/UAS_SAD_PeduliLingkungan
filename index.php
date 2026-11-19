<?php

require_once __DIR__ . '/config/session.php';
mulai_sesi_aman();

require_once __DIR__ . '/config/database.php';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$filter_boleh = ['all', 'reported', 'gotong_royong'];
$filter = $_GET['filter'] ?? 'all';
if (!is_string($filter) || !in_array($filter, $filter_boleh, true)) {
    $filter = 'all';
}

$label_filter = [
    'all'           => 'Semua',
    'reported'      => 'Laporan Masalah',
    'gotong_royong' => 'Volunteer',
];
$label_status = [
    'reported'      => 'Dilaporkan',
    'gotong_royong' => 'Volunteer',
    'done'          => 'Selesai',
];

$sorotan = [];
if ($filter === 'all') {
    $stmt = $pdo->prepare(
        "SELECT l.id, l.judul, l.lokasi, l.tanggal_aksi, l.kuota,
                (SELECT COUNT(*) FROM laporan_participants p WHERE p.laporan_id = l.id) AS jumlah_peserta
         FROM laporan l
         WHERE l.status = 'gotong_royong' AND l.deleted_at IS NULL AND l.tanggal_aksi >= CURRENT_DATE
         ORDER BY l.tanggal_aksi ASC
         LIMIT 3"
    );
    $stmt->execute();
    $sorotan = $stmt->fetchAll();
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.judul, l.lokasi, l.status, l.permintaan_volunteer, l.tanggal_aksi, l.kuota, l.created_at,
            u.username,
            (SELECT COUNT(*) FROM laporan_participants p WHERE p.laporan_id = l.id) AS jumlah_peserta
     FROM laporan l
     JOIN users u ON u.id = l.user_id
     WHERE l.deleted_at IS NULL AND (CAST(? AS text) = 'all' OR l.status = ?)
     ORDER BY l.created_at DESC
     LIMIT 50"
);
$stmt->execute([$filter, $filter]);
$daftar = $stmt->fetchAll();

$judul_halaman = isset($_SESSION['user_id']) ? 'Beranda' : 'Beranda Tamu';
include_once 'includes/header.php';
?>

<?php if ($flash_success !== ''): ?>
    <div class="alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flash_error !== ''): ?>
    <div class="alert-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card-box">
    <h2><?= htmlspecialchars($judul_halaman, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if ($sudah_login): ?>
        <a href="lapor.php" class="btn-primary">Buat Laporan</a>
    <?php else: ?>
        <p><a href="login.php" class="btn-primary">Masuk</a> untuk membuat laporan atau mendaftar sebagai volunteer.</p>
    <?php endif; ?>
</div>

<?php if (!empty($sorotan)): ?>
<div class="card-box">
    <h3>Kegiatan Volunteer Terdekat</h3>
    <table class="table-data">
        <tr><th>Kegiatan</th><th>Lokasi</th><th>Tanggal</th><th>Sisa Slot</th></tr>
        <?php foreach ($sorotan as $s): ?>
            <?php $sisa = max(0, (int) $s['kuota'] - (int) $s['jumlah_peserta']); ?>
            <tr>
                <td><a href="detail.php?id=<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['judul'], ENT_QUOTES, 'UTF-8') ?></a></td>
                <td><?= htmlspecialchars($s['lokasi'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(date('d-m-Y', strtotime($s['tanggal_aksi'])), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $sisa ?> dari <?= (int) $s['kuota'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="card-box">
    <p class="filter-bar">
        <?php foreach ($label_filter as $kunci => $teks): ?>
            <?php if ($kunci === $filter): ?>
                <a href="index.php?filter=<?= htmlspecialchars($kunci, ENT_QUOTES, 'UTF-8') ?>" class="btn-primary"><?= htmlspecialchars($teks, ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <a href="index.php?filter=<?= htmlspecialchars($kunci, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($teks, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </p>

    <?php if (empty($daftar)): ?>
        <p>Belum ada laporan.</p>
    <?php else: ?>
        <table class="table-data">
            <tr>
                <th>Judul</th><th>Lokasi</th><th>Status</th>
                <th>Tanggal Aksi</th><th>Sisa Slot</th><th>Pelapor</th>
            </tr>
            <?php foreach ($daftar as $r): ?>
                <?php
                $tampil_vol = ($r['status'] === 'gotong_royong')
                || ($r['status'] === 'done' && $r['permintaan_volunteer'] === 'disetujui');
                $sisa = max(0, (int) $r['kuota'] - (int) $r['jumlah_peserta']);
                ?>
                <tr>
                    <td><a href="detail.php?id=<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['judul'], ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><?= htmlspecialchars($r['lokasi'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge-status" data-status="<?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label_status[$r['status']] ?? $r['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <?= ($tampil_vol && $r['tanggal_aksi'] !== null)
                            ? htmlspecialchars(date('d-m-Y', strtotime($r['tanggal_aksi'])), ENT_QUOTES, 'UTF-8')
                            : '-' ?>
                    </td>
                    <td>
                        <?= ($r['status'] === 'gotong_royong' && $r['kuota'] !== null)
                            ? $sisa . ' dari ' . (int) $r['kuota']
                            : '-' ?>
                    </td>
                    <td><?= htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p>Menampilkan maksimal 50 laporan terbaru.</p>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>
