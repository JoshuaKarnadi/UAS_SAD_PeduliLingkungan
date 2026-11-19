<?php

require_once __DIR__ . '/includes/auth_check.php';
wajib_role('admin');

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$filter_boleh = ['all', 'reported', 'gotong_royong', 'done'];
$filter = $_GET['filter'] ?? 'all';
if (!is_string($filter) || !in_array($filter, $filter_boleh, true)) {
    $filter = 'all';
}

$label_filter = [
    'all'           => 'Semua',
    'reported'      => 'Dilaporkan',
    'gotong_royong' => 'Gotong Royong',
    'done'          => 'Selesai',
];
$label_status = [
    'reported'      => 'Dilaporkan',
    'gotong_royong' => 'Gotong Royong',
    'done'          => 'Selesai',
];
$label_permintaan = [
    'tidak'     => '-',
    'menunggu'  => 'Menunggu persetujuan',
    'disetujui' => 'Disetujui',
    'ditolak'   => 'Ditolak',
];


$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM laporan
     WHERE status = 'reported' AND permintaan_volunteer = 'menunggu' AND deleted_at IS NULL"
);
$stmt->execute();
$jumlah_menunggu = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT l.id, l.judul, l.status, l.permintaan_volunteer, l.tanggal_aksi, l.kuota,
            l.created_at, u.username,
            (SELECT COUNT(*) FROM laporan_participants p WHERE p.laporan_id = l.id) AS jumlah_peserta
     FROM laporan l
     JOIN users u ON u.id = l.user_id
     WHERE l.deleted_at IS NULL AND (CAST(? AS text) = 'all' OR l.status = ?)
     ORDER BY (l.status = 'reported' AND l.permintaan_volunteer = 'menunggu') DESC,
              l.created_at DESC
     LIMIT 100"
);
$stmt->execute([$filter, $filter]);
$daftar = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT a.created_at, a.action, a.description, a.ip_address, u.username
     FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC
     LIMIT 50"
);
$stmt->execute();
$logs = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "WITH unblock AS (
         SELECT ip_address, MAX(created_at) AS terakhir
         FROM activity_logs
         WHERE action = 'IP_UNBLOCKED'
         GROUP BY ip_address
     ),
     login_gagal AS (
         SELECT a.ip_address, COUNT(*) AS jumlah, MAX(a.created_at) AS terakhir
         FROM activity_logs a
         LEFT JOIN unblock u ON u.ip_address = a.ip_address
         WHERE a.action = 'LOGIN_FAILED'
           AND a.created_at > NOW() - INTERVAL '5 minutes'
           AND (u.terakhir IS NULL OR a.created_at > u.terakhir)
         GROUP BY a.ip_address
         HAVING COUNT(*) >= 5
     ),
     daftar_gagal AS (
         SELECT a.ip_address, COUNT(*) AS jumlah, MAX(a.created_at) AS terakhir
         FROM activity_logs a
         LEFT JOIN unblock u ON u.ip_address = a.ip_address
         WHERE a.action IN ('REGISTER_FAILED', 'REGISTER_SUCCESS')
           AND a.created_at > NOW() - INTERVAL '10 minutes'
           AND (u.terakhir IS NULL OR a.created_at > u.terakhir)
         GROUP BY a.ip_address
         HAVING COUNT(*) >= 10
     )
     SELECT 'login' AS jenis, ip_address, jumlah, terakhir FROM login_gagal
     UNION ALL
     SELECT 'registrasi' AS jenis, ip_address, jumlah, terakhir FROM daftar_gagal
     ORDER BY terakhir DESC"
);
$stmt->execute();
$ip_terblokir = $stmt->fetchAll();

$label_jenis = ['login' => 'Login', 'registrasi' => 'Registrasi'];

$judul_halaman = 'Admin';
$halaman_admin = true;

include_once 'includes/header.php';
?>

<?php if ($flash_success !== ''): ?>
    <div class="alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flash_error !== ''): ?>
    <div class="alert-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card-box">
    <h2>Admin: Kelola Laporan</h2>
    <p>Pengajuan volunteer yang menunggu: <strong><?= $jumlah_menunggu ?></strong></p>
    <p class="filter-bar">
        <?php foreach ($label_filter as $kunci => $teks): ?>
            <?php if ($kunci === $filter): ?>
                <a href="admin.php?filter=<?= htmlspecialchars($kunci, ENT_QUOTES, 'UTF-8') ?>" class="btn-primary"><?= htmlspecialchars($teks, ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <a href="admin.php?filter=<?= htmlspecialchars($kunci, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($teks, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </p>

    <?php if (empty($daftar)): ?>
        <p>Tidak ada laporan.</p>
    <?php else: ?>
        <table class="table-data">
            <tr>
                <th>Laporan</th><th>Status</th><th>Pengajuan</th>
                <th>Tanggal / Kuota</th><th>Aksi</th>
            </tr>
            <?php foreach ($daftar as $r): ?>
                <tr>
                    <td>
                        <a href="admin_detail.php?id=<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['judul'], ENT_QUOTES, 'UTF-8') ?></a><br>
                        oleh <?= htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <span class="badge-status" data-status="<?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label_status[$r['status']] ?? $r['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($label_permintaan[$r['permintaan_volunteer']] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?= $r['tanggal_aksi'] !== null
                            ? htmlspecialchars(date('d-m-Y', strtotime($r['tanggal_aksi'])), ENT_QUOTES, 'UTF-8')
                            : '-' ?>
                        /
                        <?= $r['kuota'] !== null ? (int) $r['kuota'] : '-' ?>
                        <?php if ($r['status'] !== 'reported'): ?>
                            (terdaftar <?= (int) $r['jumlah_peserta'] ?>)
                        <?php endif; ?>
                    </td>

<td><a href="admin_detail.php?id=<?= (int) $r['id'] ?>" class="btn-primary">Kelola</a></td>

                </tr>
            <?php endforeach; ?>
        </table>
        <p>Menampilkan maksimal 100 laporan.</p>
    <?php endif; ?>
</div>

<div class="card-box">
    <h3>IP yang Sedang Diblokir</h3>
    <p>Blokir berakhir otomatis setelah jendela waktunya lewat (login 5 menit, registrasi 10 menit). Tombol di bawah membukanya lebih cepat.</p>

    <?php if (empty($ip_terblokir)): ?>
        <p>Tidak ada IP yang sedang diblokir.</p>
    <?php else: ?>
        <table class="table-data">
            <tr><th>IP</th><th>Jenis</th><th>Percobaan</th><th>Terakhir</th><th>Aksi</th></tr>
            <?php foreach ($ip_terblokir as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($label_jenis[$b['jenis']] ?? $b['jenis'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $b['jumlah'] ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($b['terakhir'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form action="actions/proses_acc.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="ip" value="<?= htmlspecialchars($b['ip_address'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" name="aksi" value="unblock_ip" class="btn-primary">Buka Blokir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card-box">
    <h3>Audit Log (50 terbaru)</h3>
    <?php if (empty($logs)): ?>
        <p>Belum ada catatan.</p>
    <?php else: ?>
        <table class="table-data">
            <tr><th>Waktu</th><th>Aksi</th><th>Pengguna</th><th>IP</th><th>Keterangan</th></tr>
            <?php foreach ($logs as $g): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($g['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($g['action'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($g['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($g['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $g['description'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>
