<?php

require_once __DIR__ . '/includes/auth_check.php';
wajib_role('admin');
require_once __DIR__ . '/helpers/gambar.php';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$id = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
);

$laporan = false;
$peserta = [];

if ($id !== false) {
    $stmt = $pdo->prepare(
        "SELECT l.id, l.judul, l.lokasi, l.deskripsi, l.gambar, l.status, l.permintaan_volunteer,
            l.deskripsi_volunteer, l.tanggal_aksi, l.kuota, l.created_at, l.deleted_at,
            u.username, u.created_at AS pelapor_sejak
         FROM laporan l
         JOIN users u ON u.id = l.user_id
         WHERE l.id = ?"
    );
    $stmt->execute([$id]);
    $laporan = $stmt->fetch();

    if ($laporan !== false) {
        $stmt = $pdo->prepare(
            "SELECT u.username, p.joined_at
             FROM laporan_participants p
             JOIN users u ON u.id = p.user_id
             WHERE p.laporan_id = ?
             ORDER BY p.joined_at ASC"
        );
        $stmt->execute([$id]);
        $peserta = $stmt->fetchAll();
    }
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
    'tidak'     => 'Tidak mengajukan',
    'menunggu'  => 'Menunggu persetujuan',
    'disetujui' => 'Disetujui',
    'ditolak'   => 'Ditolak',
];

$hari_ini = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');

$halaman_admin = true;
$judul_halaman = ($laporan !== false) ? 'Kelola: ' . $laporan['judul'] : 'Tidak ditemukan';
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
        <p class="btn-back"><a href="admin.php">Kembali ke daftar admin</a></p>
    </div>

<?php else: ?>
    
    <?php if ($laporan['deleted_at'] !== null): ?>
    <div class="alert-error">
        Laporan ini sudah dihapus pada
        <?= htmlspecialchars(date('d-m-Y H:i', strtotime($laporan['deleted_at'])), ENT_QUOTES, 'UTF-8') ?>.
        Halaman ini hanya untuk dilihat (read-only).
    </div>
    <?php endif; ?>

    <div class="card-box">
        <h2><?= htmlspecialchars($laporan['judul'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p>
            <span class="badge-status" data-status="<?= htmlspecialchars($laporan['status'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($label_status[$laporan['status']] ?? $laporan['status'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </p>
        <p>Pelapor: <strong><?= htmlspecialchars($laporan['username'], ENT_QUOTES, 'UTF-8') ?></strong>
           (anggota sejak <?= htmlspecialchars(date('d-m-Y', strtotime($laporan['pelapor_sejak'])), ENT_QUOTES, 'UTF-8') ?>),
           melapor pada <?= htmlspecialchars(date('d-m-Y H:i', strtotime($laporan['created_at'])), ENT_QUOTES, 'UTF-8') ?></p>
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

    <div class="card-box">
        <h3>Volunteer</h3>
        <p><strong>Pengajuan:</strong>
            <?= htmlspecialchars($label_permintaan[$laporan['permintaan_volunteer']] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
        <p><?php $dv = (string) ($laporan['deskripsi_volunteer'] ?? ''); ?>
           <?= $dv !== '' ? nl2br(htmlspecialchars($dv, ENT_QUOTES, 'UTF-8')) : '(Belum ada deskripsi volunteer.)' ?></p>
        <p><strong>Tanggal aksi:</strong>
            <?= $laporan['tanggal_aksi'] !== null
                ? htmlspecialchars(date('d-m-Y', strtotime($laporan['tanggal_aksi'])), ENT_QUOTES, 'UTF-8')
                : '-' ?>
           | <strong>Kuota:</strong> <?= $laporan['kuota'] !== null ? (int) $laporan['kuota'] : '-' ?>
           | <strong>Terdaftar:</strong> <?= count($peserta) ?></p>
    </div>


    <?php if ($laporan['deleted_at'] === null): ?>

    <div class="card-box">
        <h3>Tindakan Admin</h3>

        <?php if ($laporan['status'] === 'reported'): ?>
            <form action="actions/proses_acc.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="laporan_id" value="<?= (int) $laporan['id'] ?>">

                <div class="form-group">
                    <label for="deskripsi_volunteer">Deskripsi kegiatan volunteer (apa yang dikerjakan, titik kumpul, yang perlu dibawa)</label>
                    <textarea class="form-input" id="deskripsi_volunteer" name="deskripsi_volunteer"
                            rows="4" maxlength="1000"><?= htmlspecialchars((string) ($laporan['deskripsi_volunteer'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="tanggal_aksi">Tanggal aksi</label>
                    <input class="form-input" type="date" id="tanggal_aksi" name="tanggal_aksi"
                        min="<?= htmlspecialchars($hari_ini, ENT_QUOTES, 'UTF-8') ?>"
                        value="<?= htmlspecialchars((string) ($laporan['tanggal_aksi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="kuota">Kuota relawan (1-500)</label>
                    <input class="form-input" type="number" id="kuota" name="kuota" min="1" max="500"
                        value="<?= htmlspecialchars((string) ($laporan['kuota'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <button type="submit" name="aksi" value="setujui" class="btn-primary">Adakan Volunteer</button>
                <?php if ($laporan['permintaan_volunteer'] === 'menunggu'): ?>
                    <button type="submit" name="aksi" value="tolak" class="btn-danger">Tolak Pengajuan</button>
                <?php endif; ?>
            </form>

            <hr>
            <form action="actions/proses_acc.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="laporan_id" value="<?= (int) $laporan['id'] ?>">
                <p>Masalah sudah ditangani tanpa kegiatan volunteer?</p>
                <button type="submit" name="aksi" value="selesai" class="btn-primary">Selesaikan Laporan</button>
            </form>

        <?php elseif ($laporan['status'] === 'gotong_royong'): ?>
            <form action="actions/proses_acc.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="laporan_id" value="<?= (int) $laporan['id'] ?>">

                <div class="form-group">
                    <label for="deskripsi_volunteer">Deskripsi kegiatan volunteer</label>
                    <textarea class="form-input" id="deskripsi_volunteer" name="deskripsi_volunteer"
                            rows="4" maxlength="1000" required><?= htmlspecialchars((string) ($laporan['deskripsi_volunteer'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="tanggal_aksi">Tanggal aksi</label>
                    <input class="form-input" type="date" id="tanggal_aksi" name="tanggal_aksi" required
                        value="<?= htmlspecialchars((string) ($laporan['tanggal_aksi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="kuota">Kuota relawan (1-500)</label>
                    <input class="form-input" type="number" id="kuota" name="kuota" min="1" max="500" required
                        value="<?= htmlspecialchars((string) ($laporan['kuota'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <button type="submit" name="aksi" value="ubah_volunteer" class="btn-primary">Simpan Data Kegiatan</button>
            </form>

            <hr>
            <form action="actions/proses_acc.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="laporan_id" value="<?= (int) $laporan['id'] ?>">
                <button type="submit" name="aksi" value="selesai" class="btn-primary">Tandai Kegiatan Selesai</button>
            </form>

        <?php else: ?>
            <p>Laporan ini sudah selesai. Tidak ada tindakan yang tersedia.</p>
        <?php endif; ?>
    </div>

       <?php if ($laporan['status'] !== 'done'): ?>
    <div class="card-box">
        <h3>Edit Laporan</h3>
        <form action="actions/proses_acc.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="laporan_id" value="<?= (int) $laporan['id'] ?>">

            <div class="form-group">
                <label for="edit_judul">Judul</label>
                <input class="form-input" type="text" id="edit_judul" name="judul" maxlength="150" required
                    value="<?= htmlspecialchars($laporan['judul'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="edit_lokasi">Lokasi</label>
                <input class="form-input" type="text" id="edit_lokasi" name="lokasi" maxlength="255" required
                    value="<?= htmlspecialchars($laporan['lokasi'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="edit_deskripsi">Deskripsi laporan</label>
                <textarea class="form-input" id="edit_deskripsi" name="deskripsi"
                        rows="6" maxlength="2000" required><?= htmlspecialchars($laporan['deskripsi'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <button type="submit" name="aksi" value="ubah" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card-box">
        <h3>Hapus Laporan</h3>
        <p>Laporan akan disembunyikan dari semua halaman (soft-delete). Datanya tetap tersimpan sebagai arsip audit.</p>
        <form action="actions/proses_acc.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="laporan_id" value="<?= (int) $laporan['id'] ?>">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="konfirmasi_hapus" value="1">
                    Saya yakin ingin menghapus laporan ini
                </label>
            </div>
            <button type="submit" name="aksi" value="hapus" class="btn-danger">Hapus Laporan</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card-box">
        <h3>Peserta Volunteer</h3>
        <?php if (empty($peserta)): ?>
            <p>Belum ada peserta.</p>
        <?php else: ?>
            <table class="table-data">
                <tr><th>Username</th><th>Terdaftar Pada</th></tr>
                <?php foreach ($peserta as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['username'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($p['joined_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <p class="btn-back"><a href="admin.php">Kembali ke daftar admin</a></p>
<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>
