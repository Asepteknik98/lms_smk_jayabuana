<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([3]);
$db = Database::getInstance();
$materi_id = (int)($_GET['id'] ?? 0);

function youtube_review_id(?string $url): ?string
{
    if (!$url) return null;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    $id = null;
    if (str_ends_with($host, 'youtu.be')) $id = explode('/', $path)[0] ?? null;
    elseif (str_contains($path, 'shorts/')) $id = explode('/', substr($path, strpos($path, 'shorts/') + 7))[0] ?? null;
    elseif (str_contains($path, 'embed/')) $id = explode('/', substr($path, strpos($path, 'embed/') + 6))[0] ?? null;
    else { parse_str((string)parse_url($url, PHP_URL_QUERY), $query); $id = $query['v'] ?? null; }
    return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
}

$stmt_siswa = $db->prepare('SELECT id, kelas_id FROM siswa WHERE user_id = ?');
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

$stmt_materi = $db->prepare(
    'SELECT mat.*, p.id AS pengajaran_id, p.tahun_ajaran, p.semester,
            m.nama_mapel, g.nama_lengkap AS nama_guru
     FROM materi mat
     JOIN pengajaran p ON p.id = mat.pengajaran_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN guru g ON g.id = p.guru_id
     WHERE mat.id = ? AND p.kelas_id = ?'
);
$stmt_materi->execute([$materi_id, (int)($siswa['kelas_id'] ?? 0)]);
$materi = $stmt_materi->fetch();

if (!$materi) {
    http_response_code(404);
}

$tugas_terkait = [];
$materi_sebelumnya = $materi_berikutnya = null;
if ($materi && $siswa) {
    $db->prepare(
        'INSERT INTO materi_siswa_dibaca (materi_id, siswa_id, dibaca_pada)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE dibaca_pada = VALUES(dibaca_pada)'
    )->execute([$materi_id, (int)$siswa['id']]);

    $stmt_tugas = $db->prepare(
        'SELECT t.id, t.judul, t.deskripsi, t.deadline, t.file_lampiran,
                pt.id AS pengumpulan_id, pt.nilai
         FROM tugas t
         LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
         WHERE t.pengajaran_id = ? AND t.pertemuan_ke = ?
         ORDER BY t.deadline ASC'
    );
    $stmt_tugas->execute([(int)$siswa['id'], (int)$materi['pengajaran_id'], (int)$materi['pertemuan_ke']]);
    $tugas_terkait = $stmt_tugas->fetchAll();

    $stmt_sebelumnya = $db->prepare(
        'SELECT id, judul FROM materi
         WHERE pengajaran_id = ? AND (pertemuan_ke < ? OR (pertemuan_ke = ? AND id < ?))
         ORDER BY pertemuan_ke DESC, id DESC LIMIT 1'
    );
    $stmt_sebelumnya->execute([$materi['pengajaran_id'], $materi['pertemuan_ke'], $materi['pertemuan_ke'], $materi_id]);
    $materi_sebelumnya = $stmt_sebelumnya->fetch();

    $stmt_berikutnya = $db->prepare(
        'SELECT id, judul FROM materi
         WHERE pengajaran_id = ? AND (pertemuan_ke > ? OR (pertemuan_ke = ? AND id > ?))
         ORDER BY pertemuan_ke ASC, id ASC LIMIT 1'
    );
    $stmt_berikutnya->execute([$materi['pengajaran_id'], $materi['pertemuan_ke'], $materi['pertemuan_ke'], $materi_id]);
    $materi_berikutnya = $stmt_berikutnya->fetch();
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.review-shell{max-width:1100px;margin:0 auto}.review-card{border:0;border-radius:18px;box-shadow:0 6px 22px rgba(15,23,42,.06)}.review-copy{line-height:1.75;white-space:normal;overflow-wrap:anywhere}.review-file{background:linear-gradient(135deg,#eff6ff,#f8fbff);border:1px solid #dbeafe;border-radius:15px}.review-video{border-radius:15px;overflow:hidden;background:#000}.review-video iframe{display:block;width:100%;aspect-ratio:16/9;border:0}.related-task{border:1px solid #e8edf4;border-radius:14px}.review-nav a{min-width:0}.review-nav .nav-copy{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}@media(max-width:575.98px){.review-card{border-radius:15px}.review-card .card-body{padding:17px!important}.review-nav a{font-size:.78rem}}
</style>
<div id="page-content-wrapper">
    <nav class="navbar navbar-light top-navbar px-3 px-md-4 py-3">
        <div class="d-flex align-items-center gap-2">
            <a href="materi.php<?= $materi ? '?pengajaran_id='.(int)$materi['pengajaran_id'] : '' ?>" class="btn btn-sm btn-light border" aria-label="Kembali ke daftar materi"><i class="fa-solid fa-arrow-left"></i></a>
            <div><h5 class="mb-0 fw-bold">Review Materi</h5><small class="text-muted">Pelajari materi dan lanjutkan ke tugas terkait</small></div>
        </div>
    </nav>
    <main class="container-fluid p-3 p-md-4">
        <div class="review-shell">
        <?php if (!$materi): ?>
            <div class="alert alert-danger">Materi tidak ditemukan atau tidak tersedia untuk kelas Anda.</div>
        <?php else: ?>
            <article class="card review-card mb-3">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-primary"><?= sanitize($materi['nama_mapel']) ?></span>
                        <span class="badge bg-light text-dark border">Pertemuan <?= (int)$materi['pertemuan_ke'] ?></span>
                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Sudah dibuka</span>
                    </div>
                    <h1 class="h4 fw-bold mb-2"><?= sanitize($materi['judul']) ?></h1>
                    <p class="small text-muted mb-4">Guru <?= sanitize($materi['nama_guru']) ?> &middot; <?= sanitize($materi['semester']) ?> <?= sanitize($materi['tahun_ajaran']) ?> &middot; <?= date('d M Y, H:i', strtotime($materi['created_at'])) ?></p>
                    <?php if ($materi['deskripsi']): ?>
                        <div class="review-copy text-secondary mb-4"><?= nl2br(sanitize($materi['deskripsi'])) ?></div>
                    <?php else: ?>
                        <p class="text-muted fst-italic">Tidak ada ringkasan tertulis untuk materi ini.</p>
                    <?php endif; ?>
                    <?php $reviewYoutubeId=youtube_review_id($materi['video_url']??null); if($reviewYoutubeId): ?>
                        <div class="review-video mb-4"><iframe src="https://www.youtube-nocookie.com/embed/<?= sanitize($reviewYoutubeId) ?>" title="Video: <?= sanitize($materi['judul']) ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>
                        <a href="<?= sanitize($materi['video_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger mb-4"><i class="fa-brands fa-youtube me-1"></i>Buka di YouTube</a>
                    <?php endif; ?>
                    <?php if ($materi['file_path']): ?>
                        <div class="review-file p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div><strong class="d-block"><i class="fa-solid fa-file-lines text-primary me-2"></i>Lampiran materi</strong><small class="text-muted">Buka untuk membaca atau mengunduh berkas dari guru.</small></div>
                            <a href="file_pembelajaran.php?jenis=materi&amp;id=<?= (int)$materi['id'] ?>&amp;mode=preview" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa-solid fa-up-right-from-square me-1"></i>Buka Lampiran</a>
                        </div>
                    <?php endif; ?>
                </div>
            </article>

            <section class="card review-card mb-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-bold mb-0"><i class="fa-solid fa-list-check text-warning me-2"></i>Tugas Pertemuan Ini</h2><span class="badge bg-light text-dark border"><?= count($tugas_terkait) ?> tugas</span></div>
                    <?php if (!$tugas_terkait): ?>
                        <div class="text-center py-3 text-muted"><i class="fa-solid fa-circle-check text-success fa-2x mb-2"></i><p class="small mb-0">Belum ada tugas untuk pertemuan ini.</p></div>
                    <?php else: foreach ($tugas_terkait as $tugas):
                        $sudah = !empty($tugas['pengumpulan_id']);
                        $lewat = strtotime($tugas['deadline']) < time();
                    ?>
                        <div class="related-task p-3 mb-2">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-1"><strong><?= sanitize($tugas['judul']) ?></strong><span class="badge <?= $sudah?'bg-success':($lewat?'bg-danger':'bg-warning text-dark') ?>"><?= $sudah?'Sudah dikumpulkan':($lewat?'Terlambat':'Belum dikerjakan') ?></span></div>
                            <small class="text-danger d-block mb-2"><i class="fa-regular fa-clock me-1"></i>Deadline <?= date('d M Y, H:i', strtotime($tugas['deadline'])) ?></small>
                            <a href="tugas.php?pengajaran_id=<?= (int)$materi['pengajaran_id'] ?>&amp;pertemuan=<?= (int)$materi['pertemuan_ke'] ?>#tugas-<?= (int)$tugas['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-right me-1"></i><?= $sudah?'Review Tugas':'Buka Tugas' ?></a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </section>

            <nav class="review-nav d-flex justify-content-between gap-2" aria-label="Navigasi materi">
                <?php if ($materi_sebelumnya): ?><a class="btn btn-outline-secondary text-start flex-grow-1" href="materi_review.php?id=<?= (int)$materi_sebelumnya['id'] ?>"><small class="d-block text-muted">Sebelumnya</small><span class="nav-copy d-block"><?= sanitize($materi_sebelumnya['judul']) ?></span></a><?php else: ?><span></span><?php endif; ?>
                <?php if ($materi_berikutnya): ?><a class="btn btn-outline-primary text-end flex-grow-1" href="materi_review.php?id=<?= (int)$materi_berikutnya['id'] ?>"><small class="d-block text-muted">Berikutnya</small><span class="nav-copy d-block"><?= sanitize($materi_berikutnya['judul']) ?></span></a><?php endif; ?>
            </nav>
        <?php endif; ?>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
