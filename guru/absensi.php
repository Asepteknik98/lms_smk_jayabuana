<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);
$db = Database::getInstance();

$stmt_guru = $db->prepare('SELECT id FROM guru WHERE user_id = ?');
$stmt_guru->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt_guru->fetchColumn() ?: 0);

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
        redirect('absensi.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'buka') {
        $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
        $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 0);
        $tanggal = trim($_POST['tanggal'] ?? '');
        $waktu_buka_input = trim($_POST['waktu_buka'] ?? '');
        $waktu_tutup_input = trim($_POST['waktu_tutup'] ?? '');

        $waktu_buka = DateTime::createFromFormat('Y-m-d\TH:i', $waktu_buka_input);
        $waktu_tutup = DateTime::createFromFormat('Y-m-d\TH:i', $waktu_tutup_input);
        $tanggal_valid = DateTime::createFromFormat('Y-m-d', $tanggal);

        if ($pengajaran_id < 1 || $pertemuan_ke < 1 || $pertemuan_ke > 20 || !$tanggal_valid || !$waktu_buka || !$waktu_tutup) {
            $_SESSION['flash_error'] = 'Data sesi absensi belum lengkap atau tidak valid.';
            redirect('absensi.php');
        }

        if ($waktu_buka >= $waktu_tutup || $tanggal !== $waktu_buka->format('Y-m-d')) {
            $_SESSION['flash_error'] = 'Waktu tutup harus setelah waktu buka pada tanggal yang sama.';
            redirect('absensi.php');
        }

        $stmt_milik = $db->prepare('SELECT id FROM pengajaran WHERE id = ? AND guru_id = ?');
        $stmt_milik->execute([$pengajaran_id, $guru_id]);
        if (!$stmt_milik->fetch()) {
            $_SESSION['flash_error'] = 'Pengajaran tidak valid atau bukan milik Anda.';
            redirect('absensi.php');
        }

        try {
            $stmt = $db->prepare(
                "INSERT INTO sesi_absensi
                    (pengajaran_id, pertemuan_ke, tanggal, waktu_buka, batas_terlambat, waktu_tutup, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'Dibuka')"
            );
            $stmt->execute([
                $pengajaran_id,
                $pertemuan_ke,
                $tanggal,
                $waktu_buka->format('Y-m-d H:i:s'),
                $waktu_tutup->format('Y-m-d H:i:s'),
                $waktu_tutup->format('Y-m-d H:i:s'),
            ]);
            catat_log($_SESSION['user_id'], "Membuka absensi pertemuan $pertemuan_ke");
            $_SESSION['flash_success'] = "Sesi absensi pertemuan ke-$pertemuan_ke berhasil dibuka.";
        } catch (PDOException $e) {
            error_log('Gagal membuka sesi absensi: ' . $e->getMessage());
            $_SESSION['flash_error'] = $e->getCode() === '23000'
                ? 'Absensi untuk pertemuan tersebut sudah tersedia.'
                : 'Sesi absensi gagal dibuat.';
        }
        redirect('absensi.php');
    }

    if ($action === 'tutup') {
        $sesi_id = (int)($_POST['sesi_id'] ?? 0);
        try {
            $db->beginTransaction();
            $stmt_sesi = $db->prepare(
                "SELECT sa.id, sa.pertemuan_ke, p.kelas_id
                 FROM sesi_absensi sa
                 JOIN pengajaran p ON p.id = sa.pengajaran_id
                 WHERE sa.id = ? AND p.guru_id = ? AND sa.status = 'Dibuka'
                 FOR UPDATE"
            );
            $stmt_sesi->execute([$sesi_id, $guru_id]);
            $sesi = $stmt_sesi->fetch();
            if (!$sesi) {
                throw new RuntimeException('Sesi tidak ditemukan, bukan milik Anda, atau sudah ditutup.');
            }

            $stmt_alpa = $db->prepare(
                "INSERT INTO detail_absensi (sesi_absensi_id, siswa_id, status, keterangan)
                 SELECT ?, s.id, 'Alpa', 'Tidak melakukan check-in sampai sesi ditutup'
                 FROM siswa s
                 WHERE s.kelas_id = ?
                 ON DUPLICATE KEY UPDATE sesi_absensi_id = VALUES(sesi_absensi_id)"
            );
            $stmt_alpa->execute([$sesi_id, $sesi['kelas_id']]);

            $stmt_tutup = $db->prepare("UPDATE sesi_absensi SET status = 'Ditutup' WHERE id = ?");
            $stmt_tutup->execute([$sesi_id]);
            $db->commit();

            catat_log($_SESSION['user_id'], "Menutup absensi pertemuan {$sesi['pertemuan_ke']}");
            $_SESSION['flash_success'] = 'Sesi ditutup. Siswa yang belum check-in otomatis tercatat Alpa.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Gagal menutup sesi absensi: ' . $e->getMessage());
            $_SESSION['flash_error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Sesi absensi gagal ditutup.';
        }
        redirect('absensi.php');
    }

    $_SESSION['flash_error'] = 'Aksi tidak dikenali.';
    redirect('absensi.php');
}

$stmt_pengajaran = $db->prepare(
    'SELECT p.id, p.tahun_ajaran, p.semester, m.nama_mapel, k.nama_kelas
     FROM pengajaran p
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     WHERE p.guru_id = ?
     ORDER BY p.tahun_ajaran DESC, p.semester, m.nama_mapel, k.nama_kelas'
);
$stmt_pengajaran->execute([$guru_id]);
$pengajaran_list = $stmt_pengajaran->fetchAll();

$awal = new DateTime();
$akhir = (clone $awal)->modify('+30 minutes');
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .teacher-attendance-page { background:#f5f7fb; min-width:0; }
    .attendance-form-content { max-width:980px; margin:0 auto; }
    .attendance-intro { border:0; border-radius:18px; color:#fff; background:linear-gradient(135deg,#0f766e,#0d9488); box-shadow:0 10px 25px rgba(13,148,136,.16); overflow:hidden; position:relative; }
    .attendance-intro::after { content:""; position:absolute; width:150px; height:150px; border-radius:50%; right:-45px; bottom:-85px; background:rgba(255,255,255,.1); }
    .attendance-form-card { border:0; border-radius:18px; box-shadow:0 7px 24px rgba(15,23,42,.07); }
    .attendance-form-card .form-label { font-size:.83rem; margin-bottom:.38rem; }
    .attendance-form-card .form-control,.attendance-form-card .form-select { min-height:44px; border-radius:10px; border-color:#dfe5ee; }
    .time-field { background:#f8fafc; border:1px solid #edf1f5; border-radius:13px; padding:12px; height:100%; }
    .open-attendance-button { min-height:48px; border-radius:11px; font-weight:700; }
    .attendance-tip { border-radius:12px; background:#eff6ff; color:#475569; font-size:.76rem; line-height:1.5; }
    @media(max-width:575.98px){
        .attendance-form-content { padding:13px!important; }
        .attendance-intro { border-radius:15px; padding:17px!important; }
        .attendance-intro h1 { font-size:1.05rem; }
        .attendance-intro p { font-size:.73rem; line-height:1.45; }
        .attendance-form-card { border-radius:15px; }
        .attendance-form-card .card-body { padding:16px!important; }
        .attendance-form-card .form-control,.attendance-form-card .form-select { min-height:42px; font-size:16px; }
        .time-field { padding:10px; }
    }
</style>
<div id="page-content-wrapper" class="teacher-attendance-page">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-3 px-md-4 py-3">
        <div class="d-flex justify-content-between align-items-center gap-2 w-100"><div><h5 class="mb-0 fw-bold"><i class="fa-solid fa-clipboard-user text-primary me-2"></i>Absensi Online</h5><small class="text-muted">Buka sesi check-in untuk siswa</small></div><a href="riwayat_absensi.php" class="btn btn-sm btn-outline-primary flex-shrink-0"><i class="fa-solid fa-clock-rotate-left me-1"></i><span class="d-none d-sm-inline">Riwayat</span></a></div>
    </nav>
    <div class="container-fluid attendance-form-content p-3 p-md-4">
        <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($success) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <section class="attendance-intro p-4 mb-3"><div class="position-relative"><small class="opacity-75">Kelas online</small><h1 class="h5 fw-bold mb-2">Siapkan Absensi Siswa</h1><p class="mb-0 opacity-75">Pilih kelas dan atur waktu check-in. Siswa akan melihat sesi setelah berhasil dibuka.</p></div></section>

        <section class="card attendance-form-card"><div class="card-body p-4">
                    <div class="mb-3"><h6 class="fw-bold mb-1">Buka Sesi Baru</h6><small class="text-muted">Pastikan kelas, pertemuan, dan waktu sudah sesuai.</small></div>
                    <?php if (!$pengajaran_list): ?>
                        <div class="alert alert-warning mb-0">Admin belum memberikan pengajaran kepada Anda.</div>
                    <?php else: ?>
                    <form method="post" action="absensi.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="buka">
                        <div class="row g-3"><div class="col-12">
                            <label class="form-label fw-semibold">Mapel dan Kelas</label>
                            <select name="pengajaran_id" class="form-select" required>
                                <option value="">-- Pilih Pengajaran --</option>
                                <?php foreach ($pengajaran_list as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>"><?= sanitize($item['nama_mapel']) ?> — <?= sanitize($item['nama_kelas']) ?> (<?= sanitize($item['semester']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-7 col-md-6">
                            <label class="form-label fw-semibold">Pertemuan</label>
                            <select name="pertemuan_ke" class="form-select" required>
                                <option value="">-- Pilih Pertemuan --</option>
                                <?php for ($i = 1; $i <= 20; $i++): ?><option value="<?= $i ?>">Pertemuan <?= $i ?></option><?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-5 col-md-6"><label class="form-label fw-semibold">Tanggal</label><input type="date" name="tanggal" value="<?= $awal->format('Y-m-d') ?>" class="form-control" required></div>
                        <div class="col-12 col-md-6"><div class="time-field"><label class="form-label fw-semibold">Waktu Buka</label><input type="datetime-local" name="waktu_buka" value="<?= $awal->format('Y-m-d\TH:i') ?>" class="form-control" required></div></div>
                        <div class="col-12 col-md-6"><div class="time-field"><label class="form-label fw-semibold">Waktu Tutup</label><input type="datetime-local" name="waktu_tutup" value="<?= $akhir->format('Y-m-d\TH:i') ?>" class="form-control" required></div></div>
                        <div class="col-12"><div class="attendance-tip p-3"><i class="fa-solid fa-circle-info text-primary me-1"></i>Siswa yang check-in selama sesi dibuka akan tercatat Hadir. Siswa yang belum check-in saat sesi ditutup akan tercatat Alpa.</div></div>
                        <div class="col-12"><button class="btn btn-primary open-attendance-button w-100" type="submit"><i class="fa-solid fa-door-open me-2"></i>Buka Absensi Sekarang</button></div></div>
                    </form>
                    <?php endif; ?>
        </div></section>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
