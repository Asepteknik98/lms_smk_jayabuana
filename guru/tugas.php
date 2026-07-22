<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);
$db = Database::getInstance();

$stmt = $db->prepare('SELECT id FROM guru WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt->fetchColumn() ?: 0);

if ($guru_id < 1) {
    http_response_code(403);
    exit('Data guru tidak ditemukan.');
}

$pesan_sukses = $_SESSION['tugas_success'] ?? '';
$pesan_error = $_SESSION['tugas_error'] ?? '';
unset($_SESSION['tugas_success'], $_SESSION['tugas_error']);

function kembali_tugas(string $jenis, string $pesan): void
{
    $_SESSION[$jenis === 'success' ? 'tugas_success' : 'tugas_error'] = $pesan;
    header('Location: tugas.php');
    exit;
}

function simpan_lampiran_tugas(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('Lampiran gagal diunggah atau ukurannya melebihi 10 MB.');
    }

    $ekstensi = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $diizinkan = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    if (!isset($diizinkan[$ekstensi]) || !in_array($mime, $diizinkan[$ekstensi], true)) {
        throw new RuntimeException('Format lampiran harus PDF, DOCX, PPTX, atau ZIP.');
    }

    $folder = __DIR__ . '/../assets/upload/tugas/';
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Folder penyimpanan lampiran tidak tersedia.');
    }
    $nama = 'tugas_' . bin2hex(random_bytes(12)) . '.' . $ekstensi;
    if (!move_uploaded_file($file['tmp_name'], $folder . $nama)) {
        throw new RuntimeException('Lampiran gagal disimpan.');
    }
    return $nama;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        kembali_tugas('error', 'Sesi keamanan berakhir. Silakan muat ulang halaman.');
    }

    $aksi = $_POST['action'] ?? '';
    try {
        if ($aksi === 'create' || $aksi === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
            $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 0);
            $judul = trim($_POST['judul'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $deadline_raw = trim($_POST['deadline'] ?? '');
            $deadline_obj = DateTime::createFromFormat('Y-m-d\TH:i', $deadline_raw);

            if ($pengajaran_id < 1 || $pertemuan_ke < 1 || $pertemuan_ke > 20 || $judul === '' || !$deadline_obj) {
                throw new RuntimeException('Kelas, pertemuan 1–20, judul, dan tenggat wajib diisi dengan benar.');
            }
            if (mb_strlen($judul) > 200) {
                throw new RuntimeException('Judul tugas maksimal 200 karakter.');
            }

            $milik = $db->prepare('SELECT id FROM pengajaran WHERE id = ? AND guru_id = ?');
            $milik->execute([$pengajaran_id, $guru_id]);
            if (!$milik->fetchColumn()) {
                throw new RuntimeException('Kelas atau mata pelajaran bukan pengajaran Anda.');
            }

            $lampiran_baru = simpan_lampiran_tugas($_FILES['file_lampiran'] ?? []);
            if ($aksi === 'create') {
                $sql = 'INSERT INTO tugas (pengajaran_id, pertemuan_ke, judul, deskripsi, deadline, file_lampiran) VALUES (?, ?, ?, ?, ?, ?)';
                $db->prepare($sql)->execute([$pengajaran_id, $pertemuan_ke, $judul, $deskripsi ?: null, $deadline_obj->format('Y-m-d H:i:s'), $lampiran_baru]);
                catat_log($_SESSION['user_id'], "Membuat tugas: $judul");
                kembali_tugas('success', 'Tugas berhasil dibuat dan dapat dilihat siswa.');
            }

            $cek = $db->prepare('SELECT t.file_lampiran FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id WHERE t.id=? AND p.guru_id=?');
            $cek->execute([$id, $guru_id]);
            $lama = $cek->fetch();
            if (!$lama) {
                if ($lampiran_baru) @unlink(__DIR__ . '/../assets/upload/tugas/' . $lampiran_baru);
                throw new RuntimeException('Tugas tidak ditemukan atau bukan milik Anda.');
            }
            $lampiran = $lampiran_baru ?: $lama['file_lampiran'];
            $sql = 'UPDATE tugas SET pengajaran_id=?, pertemuan_ke=?, judul=?, deskripsi=?, deadline=?, file_lampiran=? WHERE id=?';
            $db->prepare($sql)->execute([$pengajaran_id, $pertemuan_ke, $judul, $deskripsi ?: null, $deadline_obj->format('Y-m-d H:i:s'), $lampiran, $id]);
            if ($lampiran_baru && $lama['file_lampiran']) @unlink(__DIR__ . '/../assets/upload/tugas/' . basename($lama['file_lampiran']));
            catat_log($_SESSION['user_id'], "Mengubah tugas: $judul");
            kembali_tugas('success', 'Tugas berhasil diperbarui.');
        }

        if ($aksi === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT t.judul,t.file_lampiran FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id WHERE t.id=? AND p.guru_id=?');
            $stmt->execute([$id, $guru_id]);
            $tugas_hapus = $stmt->fetch();
            if (!$tugas_hapus) throw new RuntimeException('Tugas tidak ditemukan atau bukan milik Anda.');

            $jawaban = $db->prepare('SELECT file_tugas FROM pengumpulan_tugas WHERE tugas_id=?');
            $jawaban->execute([$id]);
            $file_jawaban = $jawaban->fetchAll(PDO::FETCH_COLUMN);
            $db->prepare('DELETE FROM tugas WHERE id=?')->execute([$id]);
            if ($tugas_hapus['file_lampiran']) @unlink(__DIR__ . '/../assets/upload/tugas/' . basename($tugas_hapus['file_lampiran']));
            foreach ($file_jawaban as $file) @unlink(__DIR__ . '/../assets/upload/jawaban/' . basename($file));
            catat_log($_SESSION['user_id'], 'Menghapus tugas: ' . $tugas_hapus['judul']);
            kembali_tugas('success', 'Tugas beserta pengumpulannya berhasil dihapus.');
        }
    } catch (Throwable $e) {
        kembali_tugas('error', $e instanceof RuntimeException ? $e->getMessage() : 'Tugas gagal diproses. Silakan coba kembali.');
    }
}

$stmt = $db->prepare('SELECT p.id,m.nama_mapel,k.nama_kelas,p.semester,p.tahun_ajaran FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.guru_id=? ORDER BY p.tahun_ajaran DESC,p.semester,m.nama_mapel,k.nama_kelas');
$stmt->execute([$guru_id]);
$pengajaran = $stmt->fetchAll();

$stmt = $db->prepare("SELECT t.*,m.nama_mapel,k.nama_kelas,p.semester,p.tahun_ajaran,
    (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id=p.kelas_id) total_siswa,
    (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id) sudah_mengumpulkan,
    (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id AND pt.nilai IS NULL) belum_dinilai
    FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id
    WHERE p.guru_id=? ORDER BY t.deadline DESC");
$stmt->execute([$guru_id]);
$daftar_tugas = $stmt->fetchAll();

// Batasi kartu tugas per halaman agar daftar tetap ringkas.
$tugas_per_halaman = 3;
$total_tugas_guru = count($daftar_tugas);
$total_halaman_tugas = max(1, (int)ceil($total_tugas_guru / $tugas_per_halaman));
$halaman_tugas = max(1, min((int)($_GET['page'] ?? 1), $total_halaman_tugas));
$daftar_tugas = array_slice($daftar_tugas, ($halaman_tugas - 1) * $tugas_per_halaman, $tugas_per_halaman);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .teacher-task-page { background:#f5f7fb; min-width:0; }
    .teacher-task-content { max-width:1200px; margin:0 auto; }
    .task-page-toolbar { position:sticky; top:0; z-index:25; background:rgba(255,255,255,.96); backdrop-filter:blur(10px); border-bottom:1px solid #e8edf4; box-shadow:0 3px 12px rgba(15,23,42,.04); }
    .teacher-task-card { border:0; border-radius:17px; box-shadow:0 6px 22px rgba(15,23,42,.06); transition:transform .18s ease,box-shadow .18s ease; overflow:hidden; }
    .teacher-task-card .task-heading { font-size:1rem; line-height:1.4; overflow-wrap:anywhere; }
    .teacher-task-card .task-badge { max-width:100%; white-space:normal; text-align:left; line-height:1.35; }
    .task-meta-box { background:#f8fafc; border:1px solid #edf1f5; border-radius:11px; padding:10px; font-size:.76rem; line-height:1.5; }
    .task-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:7px; }
    .task-stats > div { background:#f8fafc; border-radius:10px; padding:9px 4px; text-align:center; }
    .task-stats strong { display:block; font-size:1.05rem; line-height:1.1; }
    .task-stats small { color:#64748b; font-size:.66rem; }
    .task-action-main { min-height:38px; border-radius:9px; }
    .task-action-icon { width:38px; height:38px; padding:0; display:grid; place-items:center; border-radius:9px; flex:0 0 38px; }
    #modalTugas .modal-content { border-radius:18px; overflow:hidden; }
    #modalTugas #formTugas { display:flex; flex-direction:column; min-height:0; max-height:calc(100vh - 3.5rem); }
    #modalTugas .modal-header,#modalTugas .modal-footer { flex-shrink:0; background:#fff; }
    #modalTugas .modal-body { flex:1 1 auto; min-height:0; overflow-y:auto; }
    #modalTugas .modal-footer { position:sticky; bottom:0; z-index:2; border-top:1px solid #e9edf3; }
    #modalTugas .form-label { font-size:.84rem; margin-bottom:.4rem; }
    #modalTugas .form-control,#modalTugas .form-select { min-height:44px; border-radius:10px; border-color:#dfe5ee; }
    #modalTugas .modal-body .row { --bs-gutter-y:.8rem; }
    @media (hover:hover) { .teacher-task-card:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(15,23,42,.1); } }
    @media (max-width:575.98px) {
        .teacher-task-content { padding:13px!important; }
        .teacher-task-card { border-radius:14px; }
        .teacher-task-card .card-body { padding:15px!important; }
        .task-page-toolbar .btn { min-height:40px; white-space:nowrap; }
        .task-page-toolbar h5 { font-size:.92rem; }
        .task-page-toolbar small { font-size:.64rem; }
        .task-page-toolbar .create-task-button { width:auto; height:36px; min-height:36px; padding:0 9px; display:flex; align-items:center; justify-content:center; border-radius:9px; font-size:.7rem; }
        .task-page-toolbar .create-task-button i { margin-right:4px!important; }
        #modalTugas .modal-dialog { margin:.65rem auto; width:calc(100% - 1.3rem); max-height:calc(100dvh - 1.3rem); }
        #modalTugas .modal-content { border-radius:14px; max-height:calc(100dvh - 1.3rem); }
        #modalTugas #formTugas { max-height:calc(100dvh - 1.3rem); }
        #modalTugas .modal-header { padding:12px 14px; }
        #modalTugas .modal-header h5 { font-size:.98rem; }
        #modalTugas .modal-header small { font-size:.68rem; }
        #modalTugas .modal-body { padding:12px 14px; overflow-y:auto; }
        #modalTugas .modal-body .row { --bs-gutter-x:.6rem; --bs-gutter-y:.6rem; }
        #modalTugas .form-label { font-size:.76rem; margin-bottom:.25rem; }
        #modalTugas .form-control,#modalTugas .form-select { min-height:39px; padding-top:.4rem; padding-bottom:.4rem; font-size:16px; border-radius:8px; }
        #modalTugas textarea.form-control { min-height:68px; }
        #modalTugas .form-text,#modalTugas small.text-muted { font-size:.65rem; }
        #modalTugas .modal-footer { padding:10px 14px; }
        #modalTugas .modal-footer .btn { flex:1; min-height:38px; padding:.4rem .65rem; font-size:.78rem; }
    }
</style>
<div id="page-content-wrapper" class="teacher-task-page">
    <nav class="navbar task-page-toolbar px-2 px-md-4 py-2 py-md-3">
        <div class="d-flex justify-content-between align-items-center gap-2 w-100">
            <div class="min-w-0"><h5 class="mb-0 fw-bold text-truncate"><i class="fa-solid fa-list-check text-primary me-2"></i>Daftar Tugas</h5><small class="text-muted d-block text-truncate"><?= $total_tugas_guru ?> tugas pada seluruh kelas yang Anda ampu</small></div>
            <button class="btn btn-primary btn-sm create-task-button flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalTugas" onclick="tugasBaru()" aria-label="Tambah tugas baru" title="Tambah Tugas"><i class="fa-solid fa-plus me-1"></i><span>Tambah Tugas</span></button>
        </div>
    </nav>
    <div class="container-fluid teacher-task-content p-3 p-md-4">
        <?php if ($pesan_sukses): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($pesan_sukses) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($pesan_error): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($pesan_error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <?php if (!$pengajaran): ?>
            <div class="alert alert-info">Belum ada kelas dan mata pelajaran yang ditugaskan admin kepada Anda.</div>
        <?php elseif (!$daftar_tugas): ?>
            <div class="card border-0 shadow-sm text-center p-5"><i class="fa-solid fa-clipboard-list fa-3x text-primary opacity-50 mb-3"></i><h6 class="fw-bold">Belum ada tugas</h6><p class="text-muted mb-0">Gunakan tombol Buat Tugas untuk memulai.</p></div>
        <?php else: ?>
            <div class="row g-3">
            <?php foreach ($daftar_tugas as $t): $lewat = strtotime($t['deadline']) < time(); ?>
                <div class="col-12 col-md-6 col-xl-4"><article class="card teacher-task-card h-100"><div class="card-body p-3 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><span class="badge task-badge bg-primary-subtle text-primary"><?= sanitize($t['nama_mapel']) ?> &middot; <?= sanitize($t['nama_kelas']) ?></span><span class="badge <?= $lewat?'bg-secondary':'bg-success' ?> flex-shrink-0"><?= $lewat?'Ditutup':'Aktif' ?></span></div>
                    <h2 class="task-heading fw-bold mb-2"><?= sanitize($t['judul']) ?></h2>
                    <div class="task-meta-box text-muted mb-2"><div><i class="fa-solid fa-calendar-day me-1"></i>Pertemuan <?= (int)$t['pertemuan_ke'] ?> &middot; <?= sanitize($t['semester']) ?></div><div><i class="fa-regular fa-clock me-1"></i><?= date('d M Y, H:i', strtotime($t['deadline'])) ?></div></div>
                    <?php if ($t['file_lampiran']): ?><a href="../assets/upload/tugas/<?= rawurlencode(basename($t['file_lampiran'])) ?>" target="_blank" rel="noopener" class="small text-decoration-none d-inline-block mb-3"><i class="fa-solid fa-paperclip me-1"></i>Lihat lampiran soal</a><?php else: ?><small class="text-muted d-block mb-3"><i class="fa-solid fa-file-circle-xmark me-1"></i>Tanpa lampiran soal</small><?php endif; ?>
                    <div class="task-stats mb-3"><div><strong><?= (int)$t['total_siswa'] ?></strong><small>Siswa</small></div><div><strong class="text-success"><?= (int)$t['sudah_mengumpulkan'] ?></strong><small>Terkumpul</small></div><div><strong class="text-warning"><?= (int)$t['belum_dinilai'] ?></strong><small>Belum Nilai</small></div></div>
                    <div class="d-flex gap-2 mt-auto"><a href="tugas_penilaian.php?tugas_id=<?= (int)$t['id'] ?>" class="btn btn-primary btn-sm task-action-main flex-grow-1"><i class="fa-solid fa-users-viewfinder me-1"></i>Lihat & Nilai</a><button class="btn btn-outline-secondary btn-sm task-action-icon" data-bs-toggle="modal" data-bs-target="#modalTugas" aria-label="Edit tugas" onclick='editTugas(<?= json_encode(["id"=>(int)$t["id"],"pengajaran_id"=>(int)$t["pengajaran_id"],"pertemuan_ke"=>(int)$t["pertemuan_ke"],"judul"=>$t["judul"],"deskripsi"=>$t["deskripsi"],"deadline"=>date("Y-m-d\\TH:i",strtotime($t["deadline"]))], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-pen"></i></button><form method="post" class="d-flex" onsubmit="return confirm('Hapus tugas beserta seluruh jawaban siswa?')"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-outline-danger btn-sm task-action-icon" aria-label="Hapus tugas"><i class="fa-solid fa-trash"></i></button></form></div>
                </div></article></div>
            <?php endforeach; ?>
            </div>

            <?php if ($total_tugas_guru > 0): ?>
                <nav class="mt-4" aria-label="Navigasi halaman tugas guru">
                    <div class="d-flex d-md-none justify-content-between align-items-center gap-2">
                        <a class="btn btn-sm btn-outline-primary <?= $halaman_tugas <= 1 ? 'disabled' : '' ?>" href="?page=<?= max(1, $halaman_tugas - 1) ?>"><i class="fa-solid fa-chevron-left me-1"></i>Sebelumnya</a>
                        <small class="text-muted"><?= $halaman_tugas ?> / <?= $total_halaman_tugas ?></small>
                        <a class="btn btn-sm btn-outline-primary <?= $halaman_tugas >= $total_halaman_tugas ? 'disabled' : '' ?>" href="?page=<?= min($total_halaman_tugas, $halaman_tugas + 1) ?>">Berikutnya<i class="fa-solid fa-chevron-right ms-1"></i></a>
                    </div>
                    <ul class="pagination pagination-sm justify-content-center d-none d-md-flex mb-0">
                        <li class="page-item <?= $halaman_tugas <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= max(1, $halaman_tugas - 1) ?>" aria-label="Sebelumnya">&laquo;</a></li>
                        <?php for ($nomor_halaman = 1; $nomor_halaman <= $total_halaman_tugas; $nomor_halaman++): ?>
                            <li class="page-item <?= $nomor_halaman === $halaman_tugas ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $nomor_halaman ?>"><?= $nomor_halaman ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $halaman_tugas >= $total_halaman_tugas ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= min($total_halaman_tugas, $halaman_tugas + 1) ?>" aria-label="Berikutnya">&raquo;</a></li>
                    </ul>
                    <p class="text-center text-muted small mt-2 mb-0">Menampilkan maksimal <?= $tugas_per_halaman ?> dari <?= $total_tugas_guru ?> tugas</p>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalTugas" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0"><form method="post" enctype="multipart/form-data" id="formTugas"><div class="modal-header"><div><h5 class="modal-title fw-bold" id="judulModal">Buat Tugas Baru</h5><small class="text-muted">Lengkapi informasi tugas untuk siswa.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" id="aksiTugas" value="create"><input type="hidden" name="id" id="idTugas">
    <div class="row g-3"><div class="col-md-8"><label class="form-label fw-semibold">Mata Pelajaran & Kelas</label><select class="form-select" name="pengajaran_id" id="pengajaranTugas" required><option value="">Pilih pengajaran</option><?php foreach ($pengajaran as $p): ?><option value="<?= (int)$p['id'] ?>"><?= sanitize($p['nama_mapel']) ?> — <?= sanitize($p['nama_kelas']) ?> (<?= sanitize($p['semester']) ?>)</option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label fw-semibold">Pertemuan</label><select class="form-select" name="pertemuan_ke" id="pertemuanTugas" required><?php for($i=1;$i<=20;$i++): ?><option value="<?= $i ?>">Pertemuan <?= $i ?></option><?php endfor; ?></select></div>
    <div class="col-12"><label class="form-label fw-semibold">Judul Tugas</label><input class="form-control" name="judul" id="namaTugas" maxlength="200" required></div><div class="col-12"><label class="form-label fw-semibold">Deskripsi / Instruksi</label><textarea class="form-control" name="deskripsi" id="deskripsiTugas" rows="4"></textarea></div><div class="col-md-6"><label class="form-label fw-semibold">Tenggat Waktu</label><input type="datetime-local" class="form-control" name="deadline" id="deadlineTugas" required></div><div class="col-md-6"><label class="form-label fw-semibold">Lampiran</label><input type="file" class="form-control" name="file_lampiran" accept=".pdf,.docx,.pptx,.zip"><small class="text-muted">PDF, DOCX, PPTX, ZIP · maks. 10 MB</small></div></div>
    <div id="infoEdit" class="alert alert-info small mt-3 mb-0 d-none">Kosongkan lampiran jika tidak ingin mengganti file lama.</div>
    </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up me-1"></i> Publikasikan Tugas</button></div></form></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
function tugasBaru(){document.getElementById('formTugas').reset();document.getElementById('aksiTugas').value='create';document.getElementById('idTugas').value='';document.getElementById('judulModal').textContent='Buat Tugas Baru';document.getElementById('infoEdit').classList.add('d-none')}
function editTugas(d){document.getElementById('formTugas').reset();document.getElementById('aksiTugas').value='update';document.getElementById('idTugas').value=d.id;document.getElementById('pengajaranTugas').value=d.pengajaran_id;document.getElementById('pertemuanTugas').value=d.pertemuan_ke;document.getElementById('namaTugas').value=d.judul;document.getElementById('deskripsiTugas').value=d.deskripsi||'';document.getElementById('deadlineTugas').value=d.deadline;document.getElementById('judulModal').textContent='Edit Tugas';document.getElementById('infoEdit').classList.remove('d-none')}
</script>
