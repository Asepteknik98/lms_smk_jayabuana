<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([3]);
$db = Database::getInstance();

$stmt_siswa = $db->prepare(
    'SELECT s.id, s.nama_lengkap, s.kelas_id, k.nama_kelas
     FROM siswa s
     LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE s.user_id = ?'
);
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

$kelas_id = (int)($siswa['kelas_id'] ?? 0);
$pengajaran_id = (int)($_GET['pengajaran_id'] ?? 0);

$stmt_pengajaran = $db->prepare(
    'SELECT p.id, p.tahun_ajaran, p.semester, m.nama_mapel, g.nama_lengkap AS nama_guru
     FROM pengajaran p
     JOIN mapel m ON m.id = p.mapel_id
     JOIN guru g ON g.id = p.guru_id
     WHERE p.kelas_id = ?
     ORDER BY p.tahun_ajaran DESC, p.semester, m.nama_mapel'
);
$stmt_pengajaran->execute([$kelas_id]);
$pengajaran_list = $stmt_pengajaran->fetchAll();

$parameter = [$kelas_id];
$filter_pengajaran = '';
if ($pengajaran_id > 0) {
    $filter_pengajaran = ' AND p.id = ?';
    $parameter[] = $pengajaran_id;
}

$stmt_materi = $db->prepare(
    'SELECT mat.id, mat.pertemuan_ke, mat.judul, mat.deskripsi, mat.file_path, mat.created_at,
            p.id AS pengajaran_id, p.tahun_ajaran, p.semester,
            m.nama_mapel, g.nama_lengkap AS nama_guru
     FROM materi mat
     JOIN pengajaran p ON p.id = mat.pengajaran_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN guru g ON g.id = p.guru_id
     WHERE p.kelas_id = ?' . $filter_pengajaran . '
     ORDER BY p.tahun_ajaran DESC, p.semester, m.nama_mapel, mat.pertemuan_ke ASC'
);
$stmt_materi->execute($parameter);
$materi_list = $stmt_materi->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-book-open-reader text-primary me-2"></i> Materi Pembelajaran</h5>
            <span class="badge bg-primary px-3 py-2">Kelas: <?= sanitize($siswa['nama_kelas'] ?? 'Belum ditentukan') ?></span>
        </div>
    </nav>

    <div class="container-fluid p-4">
        <?php if (!$siswa): ?>
            <div class="alert alert-danger">Profil Siswa tidak ditemukan. Hubungi Administrator.</div>
        <?php elseif (!$kelas_id): ?>
            <div class="alert alert-warning">Anda belum ditempatkan pada kelas. Hubungi Administrator.</div>
        <?php else: ?>
            <div class="card border-0 shadow-sm p-3 mb-4">
                <form method="get" action="materi.php" class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label fw-semibold">Filter Mata Pelajaran</label>
                        <select name="pengajaran_id" class="form-select">
                            <option value="0">Semua Mata Pelajaran</option>
                            <?php foreach ($pengajaran_list as $pengajaran): ?>
                                <option value="<?= (int)$pengajaran['id'] ?>" <?= $pengajaran_id === (int)$pengajaran['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($pengajaran['nama_mapel']) ?> — <?= sanitize($pengajaran['nama_guru']) ?>
                                    (<?= sanitize($pengajaran['semester']) ?> <?= sanitize($pengajaran['tahun_ajaran']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Tampilkan</button>
                    </div>
                </form>
            </div>

            <?php if (!$pengajaran_list): ?>
                <div class="alert alert-info">Belum ada mata pelajaran yang ditugaskan untuk kelas Anda.</div>
            <?php elseif (!$materi_list): ?>
                <div class="card border-0 shadow-sm p-5 text-center">
                    <i class="fa-solid fa-folder-open fa-3x text-secondary mb-3"></i>
                    <h6 class="fw-bold">Belum ada materi</h6>
                    <p class="text-muted mb-0">Guru belum mengunggah materi untuk pilihan ini.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($materi_list as $materi): ?>
                        <div class="col-md-6 col-xl-4">
                            <article class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <span class="badge bg-primary">Pertemuan <?= (int)$materi['pertemuan_ke'] ?></span>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($materi['created_at'])) ?></small>
                                    </div>
                                    <h5 class="fw-bold mb-2"><?= sanitize($materi['judul']) ?></h5>
                                    <p class="text-primary fw-semibold small mb-1"><?= sanitize($materi['nama_mapel']) ?></p>
                                    <p class="text-muted small mb-3">
                                        <i class="fa-solid fa-user-tie me-1"></i><?= sanitize($materi['nama_guru']) ?><br>
                                        <?= sanitize($materi['semester']) ?> · <?= sanitize($materi['tahun_ajaran']) ?>
                                    </p>
                                    <?php if ($materi['deskripsi']): ?>
                                        <p class="text-secondary small flex-grow-1"><?= nl2br(sanitize($materi['deskripsi'])) ?></p>
                                    <?php else: ?>
                                        <div class="flex-grow-1"></div>
                                    <?php endif; ?>

                                    <?php if ($materi['file_path']): ?>
                                        <a href="file_pembelajaran.php?jenis=materi&amp;id=<?= (int)$materi['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline-primary w-100">
                                            <i class="fa-solid fa-download me-1"></i> Buka / Unduh Modul
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-light disabled w-100">Materi tanpa lampiran</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
