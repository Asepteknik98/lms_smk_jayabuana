<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Proteksi Khusus Guru (Role ID 2)
check_access([2]);

$db = Database::getInstance();

// Dapatkan ID Guru
$stmt_g = $db->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt_g->execute([$_SESSION['user_id']]);
$guru = $stmt_g->fetch();
$guru_id = $guru['id'] ?? 0;

// Ambil daftar kelas dan mata pelajaran yang diampu oleh Guru ini
$stmt_p = $db->prepare("
    SELECT p.id as pengajaran_id, m.nama_mapel, k.nama_kelas, k.tingkat,
           p.tahun_ajaran, p.semester
    FROM pengajaran p
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ?
    ORDER BY p.tahun_ajaran DESC, p.semester, m.nama_mapel, k.nama_kelas
");
$stmt_p->execute([$guru_id]);
$pengajaran_list = $stmt_p->fetchAll();

// Ambil Daftar Materi yang sudah dibuat
$stmt_m = $db->prepare("
    SELECT mat.*, m.nama_mapel, k.nama_kelas, p.tahun_ajaran, p.semester
    FROM materi mat
    JOIN pengajaran p ON mat.pengajaran_id = p.id
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ? ORDER BY p.tahun_ajaran DESC, p.semester, mat.pertemuan_ke ASC
");
$stmt_m->execute([$guru_id]);
$materi_list = $stmt_m->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<style>
    .teacher-material-page { background:#f5f7fb; min-width:0; }
    .material-content { max-width:1200px; margin:0 auto; }
    .material-panel { border:0; border-radius:18px; box-shadow:0 6px 22px rgba(15,23,42,.06); }
    .material-panel .form-label { font-size:.84rem; margin-bottom:.4rem; }
    .material-panel .form-control,.material-panel .form-select { min-height:44px; border-radius:10px; border-color:#dfe5ee; }
    .material-panel textarea.form-control { min-height:94px; }
    .publish-button { min-height:46px; border-radius:11px; font-weight:700; }
    .material-mobile-item { border:1px solid #e9edf4; border-radius:14px; padding:14px; background:#fff; }
    .material-mobile-item + .material-mobile-item { margin-top:10px; }
    .material-title { font-size:.92rem; line-height:1.4; overflow-wrap:anywhere; }
    .material-meta { font-size:.73rem; line-height:1.5; }
    #tableMateri td { vertical-align:middle; }
    @media (max-width:767.98px) {
        .material-content { padding:13px!important; }
        .material-panel { border-radius:15px; }
        .material-panel .card-body { padding:16px!important; }
        .material-panel .form-control,.material-panel .form-select { font-size:16px; }
    }
</style>

<div id="page-content-wrapper" class="teacher-material-page">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-3 px-md-4 py-3">
        <div><h5 class="mb-0 fw-bold"><i class="fa-solid fa-book-open text-primary me-2"></i>Materi Pembelajaran</h5><small class="text-muted">Bagikan modul berdasarkan kelas dan pertemuan</small></div>
    </nav>

    <div class="container-fluid material-content p-3 p-md-4">
        <div class="row g-3 align-items-start">
            <!-- Form Tambah Materi -->
            <div class="col-lg-5">
                <section class="card material-panel"><div class="card-body p-4">
                    <div class="mb-3"><h6 class="fw-bold mb-1"><i class="fa-solid fa-file-circle-plus me-2 text-primary"></i>Upload Materi Baru</h6><small class="text-muted">Lengkapi data sebelum dipublikasikan.</small></div>
                    <form id="formMateri" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Kelas / Mata Pelajaran</label>
                            <select name="pengajaran_id" class="form-select" required>
                                <option value="">-- Pilih Mapel & Kelas --</option>
                                <?php foreach($pengajaran_list as $p): ?>
                                    <option value="<?= $p['pengajaran_id'] ?>">
                                        <?= sanitize($p['nama_mapel']) ?> — <?= sanitize($p['nama_kelas']) ?>
                                        (<?= sanitize($p['semester']) ?>, <?= sanitize($p['tahun_ajaran']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pertemuan Ke</label>
                            <select name="pertemuan_ke" class="form-select" required>
                                <option value="">-- Pilih Pertemuan --</option>
                                <?php for ($pertemuan = 1; $pertemuan <= 20; $pertemuan++): ?>
                                    <option value="<?= $pertemuan ?>">Pertemuan <?= $pertemuan ?></option>
                                <?php endfor; ?>
                            </select>
                            <small class="text-muted">Maksimal 20 pertemuan setiap semester.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Judul Materi</label>
                            <input type="text" name="judul" class="form-control" placeholder="Contoh: Pengenalan Topologi Jaringan" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Deskripsi / Tautan Video</label>
                            <textarea name="deskripsi" class="form-control" rows="3" placeholder="Tambahkan ringkasan materi atau tautan video..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">File Modul</label>
                            <input type="file" name="file_materi" class="form-control" accept=".pdf,.docx,.pptx,.zip">
                            <small class="text-muted">PDF, DOCX, PPTX, atau ZIP · maksimal 10 MB.</small>
                        </div>

                        <button type="submit" class="btn btn-primary publish-button w-100"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Publikasikan Materi</button>
                    </form>
                </div></section>
            </div>

            <!-- Daftar Materi yang telah diunggah -->
            <div class="col-lg-7">
                <section class="card material-panel"><div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="fw-bold mb-1"><i class="fa-solid fa-folder-open me-2 text-primary"></i>Materi Terunggah</h6><small class="text-muted"><?= count($materi_list) ?> materi tersedia</small></div></div>
                    <?php if (!$materi_list): ?>
                        <div class="text-center py-5"><i class="fa-solid fa-folder-open fa-2x text-muted opacity-50 mb-2"></i><h6 class="fw-bold">Belum ada materi</h6><p class="small text-muted mb-0">Materi yang dipublikasikan akan muncul di sini.</p></div>
                    <?php else: ?>
                    <div class="table-responsive d-none d-md-block">
                        <table id="tableMateri" class="table table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Mapel & Kelas</th>
                                    <th>Semester/Pertemuan</th>
                                    <th>Judul Materi</th>
                                    <th>File</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($materi_list as $m): ?>
                                <tr>
                                    <td>
                                        <strong><?= sanitize($m['nama_mapel']) ?></strong><br>
                                        <small class="text-muted"><?= sanitize($m['nama_kelas']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= sanitize($m['semester']) ?></span><br>
                                        <small><?= sanitize($m['tahun_ajaran']) ?> · Pertemuan <?= (int)$m['pertemuan_ke'] ?></small>
                                    </td>
                                    <td><?= sanitize($m['judul']) ?></td>
                                    <td>
                                        <?php if($m['file_path']): ?>
                                            <a href="../assets/upload/materi/<?= rawurlencode(basename($m['file_path'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-download me-1"></i>Unduh
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Tanpa File</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-md-none">
                        <?php foreach($materi_list as $m): ?>
                            <article class="material-mobile-item">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><span class="badge bg-primary">Pertemuan <?= (int)$m['pertemuan_ke'] ?></span><small class="text-muted"><?= date('d/m/Y',strtotime($m['created_at'])) ?></small></div>
                                <h2 class="material-title fw-bold mb-1"><?= sanitize($m['judul']) ?></h2>
                                <p class="material-meta text-muted mb-3"><?= sanitize($m['nama_mapel']) ?> &middot; <?= sanitize($m['nama_kelas']) ?><br><?= sanitize($m['semester']) ?> <?= sanitize($m['tahun_ajaran']) ?></p>
                                <?php if($m['file_path']): ?><a href="../assets/upload/materi/<?= rawurlencode(basename($m['file_path'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary w-100"><i class="fa-solid fa-download me-1"></i>Buka / Unduh Modul</a><?php else: ?><span class="btn btn-sm btn-light disabled w-100">Materi tanpa file</span><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div></section>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#tableMateri').DataTable();

    $('#formMateri').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);

        $.ajax({
            url: 'materi_action.php?action=create_materi',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            }
        });
    });
});
</script>
