<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([1]);
$db = Database::getInstance();

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
        redirect('pengajaran.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $guru_id = (int)($_POST['guru_id'] ?? 0);
        $mapel_id = (int)($_POST['mapel_id'] ?? 0);
        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '');
        $semester = $_POST['semester'] ?? '';

        $tahun_valid = preg_match('/^(\d{4})\/(\d{4})$/', $tahun_ajaran, $tahun)
            && (int)$tahun[2] === (int)$tahun[1] + 1;

        if ($guru_id < 1 || $mapel_id < 1 || $kelas_id < 1 || !$tahun_valid || !in_array($semester, ['Ganjil', 'Genap'], true)) {
            $_SESSION['flash_error'] = 'Data pengajaran tidak lengkap atau tahun ajaran tidak valid.';
            redirect('pengajaran.php');
        }

        try {
            $stmt_master = $db->prepare(
                'SELECT
                    EXISTS(SELECT 1 FROM guru WHERE id = ?) AS guru_valid,
                    EXISTS(SELECT 1 FROM mapel WHERE id = ?) AS mapel_valid,
                    EXISTS(SELECT 1 FROM kelas WHERE id = ?) AS kelas_valid'
            );
            $stmt_master->execute([$guru_id, $mapel_id, $kelas_id]);
            $master = $stmt_master->fetch();

            if (!$master || !(int)$master['guru_valid'] || !(int)$master['mapel_valid'] || !(int)$master['kelas_valid']) {
                throw new InvalidArgumentException('Data Guru, mata pelajaran, atau kelas tidak ditemukan.');
            }

            $stmt = $db->prepare('INSERT INTO pengajaran (guru_id, mapel_id, kelas_id, tahun_ajaran, semester) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$guru_id, $mapel_id, $kelas_id, $tahun_ajaran, $semester]);
            catat_log($_SESSION['user_id'], "Menambahkan penugasan mengajar ID: {$db->lastInsertId()}");
            $_SESSION['flash_success'] = 'Pengajaran berhasil ditambahkan dan sekarang tersedia pada akun Guru.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (PDOException $e) {
            error_log('Gagal menambah pengajaran: ' . $e->getMessage());
            $_SESSION['flash_error'] = $e->getCode() === '23000'
                ? 'Penugasan yang sama sudah tersedia.'
                : 'Pengajaran gagal disimpan. Silakan coba kembali.';
        }
        redirect('pengajaran.php');
    }

    if ($action === 'hapus') {
        $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
        if ($pengajaran_id < 1) {
            $_SESSION['flash_error'] = 'Data pengajaran tidak valid.';
            redirect('pengajaran.php');
        }

        try {
            $stmt = $db->prepare('DELETE FROM pengajaran WHERE id = ?');
            $stmt->execute([$pengajaran_id]);
            if ($stmt->rowCount() === 1) {
                catat_log($_SESSION['user_id'], "Menghapus penugasan mengajar ID: $pengajaran_id");
                $_SESSION['flash_success'] = 'Pengajaran berhasil dihapus.';
            } else {
                $_SESSION['flash_error'] = 'Pengajaran tidak ditemukan.';
            }
        } catch (PDOException $e) {
            error_log('Gagal menghapus pengajaran: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Pengajaran gagal dihapus karena masih digunakan.';
        }
        redirect('pengajaran.php');
    }

    $_SESSION['flash_error'] = 'Aksi tidak dikenali.';
    redirect('pengajaran.php');
}

$guru_list = $db->query('SELECT id, nip, nama_lengkap FROM guru ORDER BY nama_lengkap')->fetchAll();
$mapel_list = $db->query('SELECT id, kode_mapel, nama_mapel FROM mapel ORDER BY nama_mapel')->fetchAll();
$kelas_list = $db->query('SELECT id, nama_kelas, jurusan FROM kelas ORDER BY nama_kelas')->fetchAll();
$pengajaran_list = $db->query(
    'SELECT p.id, p.tahun_ajaran, p.semester, g.nama_lengkap, g.nip,
            m.kode_mapel, m.nama_mapel, k.nama_kelas
     FROM pengajaran p
     JOIN guru g ON g.id = p.guru_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     ORDER BY p.tahun_ajaran DESC, p.semester, g.nama_lengkap, m.nama_mapel, k.nama_kelas'
)->fetchAll();

$tahun_sekarang = (int)date('Y');
$tahun_ajaran_default = $tahun_sekarang . '/' . ($tahun_sekarang + 1);
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper" class="teaching-page">
    <style>
        .teaching-page{background:#f7f9fc;min-height:100vh}.teaching-hero{background:linear-gradient(135deg,#172554,#1d4ed8);color:#fff;border-radius:22px;padding:28px;box-shadow:0 18px 44px rgba(29,78,216,.17)}
        .teaching-card{border:1px solid #e8edf5!important;border-radius:18px!important;box-shadow:0 8px 28px rgba(15,23,42,.045)!important}.summary-box{background:#fff;border:1px solid #e8edf5;border-radius:16px;padding:16px;height:100%}.summary-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:13px;flex:0 0 44px}
        .form-step{display:flex;align-items:center;gap:10px;margin-bottom:18px}.step-number{width:30px;height:30px;display:grid;place-items:center;border-radius:50%;background:#dbeafe;color:#1d4ed8;font-size:.8rem;font-weight:700}.teaching-form .form-label{color:#334155;font-size:.85rem}.teaching-form .form-select,.teaching-form .form-control{border-color:#dfe6ef;min-height:44px;border-radius:11px}.teaching-form .form-select:focus,.teaching-form .form-control:focus{border-color:#60a5fa;box-shadow:0 0 0 .2rem rgba(59,130,246,.12)}
        .teaching-table thead th{background:#f8fafc;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}.teaching-table>:not(caption)>*>*{padding:1rem .8rem;border-bottom-color:#edf1f6}.teacher-avatar{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:#e0e7ff;color:#4338ca;font-weight:700;flex:0 0 38px}.period-badge{display:inline-flex;align-items:center;padding:6px 9px;border-radius:9px;font-size:.75rem;font-weight:600}
        @media(max-width:767.98px){.teaching-content{padding:18px!important}.teaching-hero{padding:22px;border-radius:18px}.teaching-card{border-radius:15px!important}}
    </style>
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-3">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-person-chalkboard text-primary me-2"></i> Manajemen Pengajaran</h5>
    </nav>

    <div class="container-fluid p-4 teaching-content">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= sanitize($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= sanitize($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section class="teaching-hero mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div><small class="text-uppercase fw-semibold opacity-75">Pengaturan Akademik</small><h3 class="fw-bold mb-1 mt-1">Atur Guru, Mapel, dan Kelas</h3><p class="mb-0 opacity-75">Setiap penugasan menentukan materi, tugas, ujian, serta absensi yang dapat dikelola Guru.</p></div>
                <div class="bg-white bg-opacity-10 rounded-4 px-4 py-3 text-center"><div class="fs-3 fw-bold"><?= count($pengajaran_list) ?></div><small class="opacity-75">Pengajaran Aktif</small></div>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="summary-box d-flex align-items-center gap-3"><span class="summary-icon bg-primary-subtle text-primary"><i class="fa-solid fa-chalkboard-user"></i></span><div><small class="text-muted">Guru</small><h5 class="fw-bold mb-0"><?= count($guru_list) ?></h5></div></div></div>
            <div class="col-6 col-lg-3"><div class="summary-box d-flex align-items-center gap-3"><span class="summary-icon bg-success-subtle text-success"><i class="fa-solid fa-book"></i></span><div><small class="text-muted">Mapel</small><h5 class="fw-bold mb-0"><?= count($mapel_list) ?></h5></div></div></div>
            <div class="col-6 col-lg-3"><div class="summary-box d-flex align-items-center gap-3"><span class="summary-icon bg-warning-subtle text-warning"><i class="fa-solid fa-school"></i></span><div><small class="text-muted">Kelas</small><h5 class="fw-bold mb-0"><?= count($kelas_list) ?></h5></div></div></div>
            <div class="col-6 col-lg-3"><div class="summary-box d-flex align-items-center gap-3"><span class="summary-icon bg-info-subtle text-info"><i class="fa-solid fa-link"></i></span><div><small class="text-muted">Relasi</small><h5 class="fw-bold mb-0"><?= count($pengajaran_list) ?></h5></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card teaching-card p-4 sticky-xl-top" style="top:20px;z-index:1">
                    <div class="mb-4"><h5 class="fw-bold mb-1">Tambah Pengajaran</h5><p class="text-muted small mb-0">Lengkapi pilihan secara berurutan.</p></div>
                    <?php if (!$guru_list || !$mapel_list || !$kelas_list): ?>
                        <div class="alert alert-warning mb-0">Lengkapi data Guru, mata pelajaran, dan kelas terlebih dahulu.</div>
                    <?php else: ?>
                    <form method="post" action="pengajaran.php" class="teaching-form">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="tambah">

                        <div class="form-step"><span class="step-number">1</span><strong class="small">Pilih tenaga pengajar</strong></div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Guru</label>
                            <select name="guru_id" class="form-select" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($guru_list as $guru): ?>
                                    <option value="<?= (int)$guru['id'] ?>"><?= sanitize($guru['nama_lengkap']) ?><?= $guru['nip'] ? ' — ' . sanitize($guru['nip']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-step"><span class="step-number">2</span><strong class="small">Tentukan pembelajaran</strong></div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mata Pelajaran</label>
                            <select name="mapel_id" class="form-select" required>
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php foreach ($mapel_list as $mapel): ?>
                                    <option value="<?= (int)$mapel['id'] ?>"><?= sanitize($mapel['kode_mapel']) ?> — <?= sanitize($mapel['nama_mapel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kelas</label>
                            <select name="kelas_id" class="form-select" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                    <option value="<?= (int)$kelas['id'] ?>"><?= sanitize($kelas['nama_kelas']) ?> — <?= sanitize($kelas['jurusan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-step mt-4"><span class="step-number">3</span><strong class="small">Tentukan periode akademik</strong></div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Tahun Ajaran</label>
                                <input type="text" name="tahun_ajaran" class="form-control" value="<?= sanitize($tahun_ajaran_default) ?>" pattern="\d{4}/\d{4}" placeholder="2026/2027" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Semester</label>
                                <select name="semester" class="form-select" required>
                                    <option value="Ganjil">Ganjil</option>
                                    <option value="Genap">Genap</option>
                                </select>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 py-2 rounded-3 fw-semibold" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan Pengajaran</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card teaching-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Daftar Pengajaran</h5><p class="text-muted small mb-0">Seluruh penugasan Guru pada periode akademik.</p></div><span class="badge bg-primary-subtle text-primary px-3 py-2"><?= count($pengajaran_list) ?> data</span></div>
                    <div class="table-responsive">
                        <table id="tablePengajaran" class="table teaching-table table-hover align-middle w-100">
                            <thead class="table-light"><tr><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Periode</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php foreach ($pengajaran_list as $item): ?>
                                <tr>
                                    <td><div class="d-flex align-items-center gap-2"><span class="teacher-avatar"><?= sanitize(strtoupper(substr($item['nama_lengkap'],0,1))) ?></span><div><strong><?= sanitize($item['nama_lengkap']) ?></strong><br><small class="text-muted"><?= sanitize($item['nip'] ?: 'NIP belum diisi') ?></small></div></div></td>
                                    <td><span class="badge bg-light text-dark"><?= sanitize($item['kode_mapel']) ?></span><br><?= sanitize($item['nama_mapel']) ?></td>
                                    <td><?= sanitize($item['nama_kelas']) ?></td>
                                    <td><span class="period-badge <?= $item['semester']==='Ganjil'?'bg-warning-subtle text-warning-emphasis':'bg-info-subtle text-info-emphasis' ?>"><?= sanitize($item['semester']) ?></span><br><small class="text-muted"><?= sanitize($item['tahun_ajaran']) ?></small></td>
                                    <td>
                                        <form method="post" action="pengajaran.php" onsubmit="return confirm('Hapus pengajaran ini? Modul, tugas, dan ujian terkait juga dapat terhapus.');">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="pengajaran_id" value="<?= (int)$item['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!$pengajaran_list): ?><p class="text-center text-muted mb-0">Belum ada pengajaran yang ditentukan.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(function () {
    if ($('#tablePengajaran tbody tr').length) {
        $('#tablePengajaran').DataTable({ language: { emptyTable: 'Belum ada data pengajaran' } });
    }
});
</script>
