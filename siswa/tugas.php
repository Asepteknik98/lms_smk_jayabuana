<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Proteksi Khusus Siswa (Role ID 3)
check_access([3]);

$db = Database::getInstance();

// Dapatkan Data Siswa & Kelasnya
$stmt_s = $db->prepare("SELECT id, kelas_id FROM siswa WHERE user_id = ?");
$stmt_s->execute([$_SESSION['user_id']]);
$siswa = $stmt_s->fetch();
$siswa_id = $siswa['id'] ?? 0;
$kelas_id = $siswa['kelas_id'] ?? 0;

// Ambil Daftar Tugas Berdasarkan Kelas Siswa
$stmt_t = $db->prepare("
    SELECT t.*, m.nama_mapel, g.nama_lengkap as nama_guru, pt.nilai,
           pt.catatan_guru, pt.file_tugas as jawaban_file
    FROM tugas t
    JOIN pengajaran p ON t.pengajaran_id = p.id
    JOIN mapel m ON p.mapel_id = m.id
    JOIN guru g ON p.guru_id = g.id
    LEFT JOIN pengumpulan_tugas pt ON (pt.tugas_id = t.id AND pt.siswa_id = ?)
    WHERE p.kelas_id = ?
    ORDER BY (t.deadline < NOW()) ASC, t.deadline ASC
");
$stmt_t->execute([$siswa_id, $kelas_id]);
$tugas_list = $stmt_t->fetchAll();

// Batasi jumlah kartu agar halaman tetap ringkas meskipun tugas sangat banyak.
$tugas_per_halaman = 3;
$total_tugas = count($tugas_list);
$total_halaman = max(1, (int)ceil($total_tugas / $tugas_per_halaman));
$halaman = max(1, min((int)($_GET['page'] ?? 1), $total_halaman));
$tugas_list = array_slice($tugas_list, ($halaman - 1) * $tugas_per_halaman, $tugas_per_halaman);
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<style>
    .student-task-grid { --task-radius: 15px; }
    .student-task-card { border-radius: var(--task-radius); transition: transform .18s ease, box-shadow .18s ease; }
    .student-task-card .task-title { font-size: 1rem; line-height: 1.35; overflow-wrap: anywhere; }
    .student-task-card .badge { font-size: .68rem; font-weight: 650; }
    .student-task-card .task-meta { font-size: .76rem; line-height: 1.45; }
    .task-instruction { background:#f8fafc; border:1px solid #edf1f6; border-radius:11px; }
    .task-instruction summary { cursor:pointer; list-style:none; font-size:.78rem; color:#475569; }
    .task-instruction summary::-webkit-details-marker { display:none; }
    .task-instruction summary::after { content:"+"; float:right; font-weight:700; }
    .task-instruction[open] summary::after { content:"−"; }
    .task-instruction .instruction-copy { max-height:130px; overflow:auto; padding-top:8px; font-size:.78rem; line-height:1.5; }
    .task-deadline { font-size:.75rem; line-height:1.4; }
    .student-task-card .btn-sm { min-height:36px; font-size:.78rem; }
    @media (hover:hover) and (min-width:992px) { .student-task-card:hover { transform:translateY(-2px); box-shadow:0 .5rem 1.25rem rgba(15,23,42,.1)!important; } }
    @media (max-width:575.98px) {
        .student-task-grid { --bs-gutter-y:.7rem; }
        .student-task-card { border-radius:13px; }
        .student-task-card .card-body { padding:14px!important; }
    }
</style>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-3 px-md-4 py-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-list-check text-primary me-2"></i>Daftar Tugas Saya</h5>
            <small class="text-muted">Baca instruksi, unduh soal, lalu kirim jawaban</small>
        </div>
    </nav>

    <div class="container-fluid p-3 p-md-4">
        <?php if (!$tugas_list): ?>
            <div class="card border-0 shadow-sm text-center p-5">
                <i class="fa-solid fa-clipboard-check fa-3x text-primary opacity-50 mb-3"></i>
                <h6 class="fw-bold">Belum ada tugas</h6>
                <p class="text-muted mb-0">Guru belum memberikan tugas untuk kelas Anda.</p>
            </div>
        <?php endif; ?>
        <div class="row g-3 student-task-grid">
            <?php foreach($tugas_list as $t): 
                $is_submitted = !empty($t['jawaban_file']);
                $is_expired   = strtotime(date('Y-m-d H:i:s')) > strtotime($t['deadline']);
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card student-task-card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-info text-dark"><?= sanitize($t['nama_mapel']) ?></span>
                        <?php if($is_submitted): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i> Sudah Dikirim</span>
                        <?php elseif($is_expired): ?>
                            <span class="badge bg-danger">Waktu Habis</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum Dikumpul</span>
                        <?php endif; ?>
                    </div>

                    <h2 class="task-title fw-bold mb-1"><?= sanitize($t['judul']) ?></h2>
                    <p class="task-meta text-muted mb-2">Guru: <?= sanitize($t['nama_guru']) ?> &middot; Pertemuan <?= (int)$t['pertemuan_ke'] ?></p>
                    <?php if ($t['deskripsi']): ?>
                        <details class="task-instruction p-2 mb-2">
                            <summary class="fw-semibold"><i class="fa-solid fa-align-left me-1"></i> Lihat instruksi tugas</summary>
                            <div class="instruction-copy text-secondary"><?= nl2br(sanitize($t['deskripsi'])) ?></div>
                        </details>
                    <?php endif; ?>

                    <?php if ($t['file_lampiran']): ?>
                        <a href="../assets/upload/tugas/<?= rawurlencode(basename($t['file_lampiran'])) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm w-100 mb-2">
                            <i class="fa-solid fa-file-arrow-down me-1"></i> Buka / Unduh Soal Tugas
                        </a>
                    <?php else: ?>
                        <div class="alert alert-light border small py-2 px-2 mb-2">
                            <i class="fa-solid fa-circle-info text-muted me-1"></i> Tugas ini tidak memiliki lampiran. Ikuti instruksi yang tertulis.
                        </div>
                    <?php endif; ?>

                    <div class="border-top pt-2 mt-auto">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-2">
                            <small class="task-deadline text-danger fw-semibold">
                                <i class="fa-regular fa-clock me-1"></i><?= date('d M Y, H:i', strtotime($t['deadline'])) ?>
                            </small>
                            <?php if($t['nilai'] !== null): ?>
                                <span class="badge bg-primary">Nilai: <?= $t['nilai'] ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if($is_submitted): ?>
                            <button class="btn btn-success btn-sm w-100" disabled>
                                <i class="fa-solid fa-circle-check me-1"></i> Tugas Sudah Dikumpulkan
                            </button>
                        <?php elseif(!$is_expired): ?>
                            <button class="btn btn-outline-primary btn-sm w-100" onclick='openSubmitModal(<?= (int)$t['id'] ?>, <?= json_encode($t['judul'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>
                                <i class="fa-solid fa-paper-plane me-1"></i> Kirim Jawaban
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm w-100" disabled>Pengumpulan Ditutup</button>
                        <?php endif; ?>

                        <?php if ($is_submitted): ?>
                            <a href="../assets/upload/jawaban/<?= rawurlencode(basename($t['jawaban_file'])) ?>" target="_blank" rel="noopener" class="btn btn-link btn-sm w-100 mt-1">
                                <i class="fa-solid fa-eye me-1"></i> Lihat jawaban yang sudah dikirim
                            </a>
                        <?php endif; ?>

                        <?php if ($t['catatan_guru']): ?>
                            <div class="alert alert-primary small mt-3 mb-0">
                                <strong><i class="fa-solid fa-comment-dots me-1"></i> Catatan Guru</strong><br>
                                <?= nl2br(sanitize($t['catatan_guru'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_tugas > 0): ?>
            <nav class="mt-4" aria-label="Navigasi halaman tugas">
                <div class="d-flex d-md-none justify-content-between align-items-center gap-2">
                    <a class="btn btn-sm btn-outline-primary <?= $halaman <= 1 ? 'disabled' : '' ?>" href="?page=<?= max(1, $halaman - 1) ?>"><i class="fa-solid fa-chevron-left me-1"></i>Sebelumnya</a>
                    <small class="text-muted">Halaman <?= $halaman ?> / <?= $total_halaman ?></small>
                    <a class="btn btn-sm btn-outline-primary <?= $halaman >= $total_halaman ? 'disabled' : '' ?>" href="?page=<?= min($total_halaman, $halaman + 1) ?>">Berikutnya<i class="fa-solid fa-chevron-right ms-1"></i></a>
                </div>
                <ul class="pagination pagination-sm justify-content-center d-none d-md-flex mb-0">
                    <li class="page-item <?= $halaman <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= max(1, $halaman - 1) ?>" aria-label="Sebelumnya">&laquo;</a></li>
                    <?php for ($nomor = 1; $nomor <= $total_halaman; $nomor++): ?>
                        <li class="page-item <?= $nomor === $halaman ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $nomor ?>"><?= $nomor ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $halaman >= $total_halaman ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= min($total_halaman, $halaman + 1) ?>" aria-label="Berikutnya">&raquo;</a></li>
                </ul>
                <p class="text-center text-muted small mt-2 mb-0">Menampilkan maksimal <?= $tugas_per_halaman ?> dari <?= $total_tugas ?> tugas</p>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Pengumpulan Tugas -->
<div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTugasTitle">Kirim Jawaban Tugas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formKirimTugas" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tugas_id" id="modalTugasId">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Unggah File Jawaban (PDF/ZIP/DOCX/Gambar)</label>
                        <input type="file" name="file_jawaban" class="form-control" required>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Kirim Tugas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let submitModal;

$(document).ready(function() {
    submitModal = new bootstrap.Modal(document.getElementById('submitModal'));

    $('#formKirimTugas').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);

        $.ajax({
            url: 'tugas_action.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                if(res.status === 'success') {
                    submitModal.hide();
                    Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            }
        });
    });
});

function openSubmitModal(tugasId, judul) {
    $('#modalTugasId').val(tugasId);
    $('#modalTugasTitle').text('Kirim Jawaban: ' + judul);
    submitModal.show();
}
</script>
