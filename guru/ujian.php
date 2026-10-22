<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);
require_once __DIR__.'/../includes/ujian_harian.php';
$db = Database::getInstance();
$uhReady=uh_ready($db);

// ID Guru
$stmt_g = $db->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt_g->execute([$_SESSION['user_id']]);
$guru = $stmt_g->fetch();
$guru_id = $guru['id'] ?? 0;

// Daftar Pengajaran
$stmt_p = $db->prepare("
    SELECT p.id, p.kelas_id, p.mapel_id, p.semester, p.tahun_ajaran, m.nama_mapel, k.nama_kelas
    FROM pengajaran p
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ?
");
$stmt_p->execute([$guru_id]);
$pengajaran_list = $stmt_p->fetchAll();
$filter_kelas = max(0, (int)($_GET['kelas_id'] ?? 0));
$filter_mapel = max(0, (int)($_GET['mapel_id'] ?? 0));
$kelas_filter_list = [];
$mapel_filter_list = [];
foreach ($pengajaran_list as $item) {
    $kelas_filter_list[(int)$item['kelas_id']] = $item['nama_kelas'];
    if ($filter_kelas === 0 || (int)$item['kelas_id'] === $filter_kelas) {
        $mapel_filter_list[(int)$item['mapel_id']] = $item['nama_mapel'];
    }
}
if ($filter_mapel > 0 && !isset($mapel_filter_list[$filter_mapel])) {
    $filter_mapel = 0;
}

$q=$db->prepare('SELECT s.id,s.nama_lengkap,s.kelas_id FROM siswa s WHERE EXISTS(SELECT 1 FROM pengajaran p WHERE p.kelas_id=s.kelas_id AND p.guru_id=?) ORDER BY s.nama_lengkap');$q->execute([$guru_id]);$uhStudents=$q->fetchAll();
// Daftar Ulangan Harian Guru
$filter_sql = '';
$filter_params = [$guru_id];
if ($filter_kelas > 0) {
    $filter_sql .= ' AND p.kelas_id = ?';
    $filter_params[] = $filter_kelas;
}
if ($filter_mapel > 0) {
    $filter_sql .= ' AND p.mapel_id = ?';
    $filter_params[] = $filter_mapel;
}
$uhEligible=$uhReady?' AND '.uh_eligible_sql('u.id','s.id'):'';
$stmt_u = $db->prepare("
    SELECT u.*, m.nama_mapel, k.nama_kelas, 
           (SELECT COUNT(*) FROM soal_ujian WHERE ujian_id = u.id) as total_soal,
           (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = p.kelas_id $uhEligible) as total_siswa,
           (SELECT COUNT(*) FROM sesi_ujian WHERE ujian_id = u.id) as total_peserta,
           (SELECT COUNT(*) FROM sesi_ujian WHERE ujian_id = u.id AND status='Selesai') as peserta_selesai
    FROM ujian u
    JOIN pengajaran p ON u.pengajaran_id = p.id
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ? $filter_sql ORDER BY u.id DESC
");
$stmt_u->execute($filter_params);
$ujian_list = $stmt_u->fetchAll();
$soal_per_ujian = [];
if ($ujian_list) {
    $ids_ujian = array_map(static fn($u) => (int)$u['id'], $ujian_list);
    $placeholder = implode(',', array_fill(0, count($ids_ujian), '?'));
    $stmt_soal = $db->prepare("SELECT * FROM soal_ujian WHERE ujian_id IN ($placeholder) ORDER BY ujian_id,id");
    $stmt_soal->execute($ids_ujian);
    foreach ($stmt_soal->fetchAll() as $soal) $soal_per_ujian[(int)$soal['ujian_id']][] = $soal;
}
$questionWorkspace=null;$requestedQuestion=(int)($_GET['soal']??0);
foreach($ujian_list as $workspaceExam)if((int)$workspaceExam['id']===$requestedQuestion&&uh_settings($db,$requestedQuestion,(int)$workspaceExam['pengajaran_id'])['metode']==='cbt')$questionWorkspace=$workspaceExam;
$examFlashSuccess=$_SESSION['flash_success']??'';$examFlashError=$_SESSION['flash_error']??'';unset($_SESSION['flash_success'],$_SESSION['flash_error']);
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<link rel="stylesheet" href="../assets/css/ujian-guru.css?v=<?= (int)filemtime(__DIR__.'/../assets/css/ujian-guru.css') ?>">
<div id="page-content-wrapper" class="uh-page">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-3">
        <div><h1 class="h5 mb-1 fw-bold">Ulangan Harian</h1><p class="uh-subtitle mb-0">Kelola ulangan, pantau pengerjaan, dan lihat nilai siswa.</p></div>
    </nav>

    <main class="container-fluid p-4 uh-content">
        <section class="card border-0 shadow-sm p-3 mb-3 uh-filter">
            <form method="get" id="filterUjianForm" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="filterKelasUjian">Kelas</label>
                    <select name="kelas_id" id="filterKelasUjian" class="form-select">
                        <option value="0">Tampilkan semua kelas</option>
                        <?php foreach ($kelas_filter_list as $kelas_id => $nama_kelas): ?>
                            <option value="<?= (int)$kelas_id ?>" <?= $filter_kelas === (int)$kelas_id ? 'selected' : '' ?>><?= sanitize($nama_kelas) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="filterMapelUjian">Mata pelajaran</label>
                    <select name="mapel_id" id="filterMapelUjian" class="form-select">
                        <option value="0">Semua Mata Pelajaran</option>
                        <?php foreach ($mapel_filter_list as $mapel_id => $nama_mapel): ?>
                            <option value="<?= (int)$mapel_id ?>" <?= $filter_mapel === (int)$mapel_id ? 'selected' : '' ?>><?= sanitize($nama_mapel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript class="col-12"><button class="btn btn-outline-primary">Terapkan filter</button></noscript>
            </form>
        </section>
        <?php if(!$questionWorkspace&&$examFlashSuccess): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($examFlashSuccess) ?><button class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif ?>
        <?php if(!$questionWorkspace&&$examFlashError): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($examFlashError) ?><button class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif ?>
        <p class="uh-flow-note"><span>1. Buat ulangan</span><span>2. Isi soal</span><span>3. Siswa mengerjakan</span><span>4. Periksa hasil</span></p>
        <div class="uh-list-heading">
            <div><h2 class="h6 fw-bold mb-1">Daftar ulangan</h2><p class="uh-subtitle mb-0"><?= count($ujian_list) ?> ulangan sesuai filter</p></div>
            <div class="uh-toolbar"><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUjian" onclick="resetFormUjian()"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Buat ulangan</button></div>
        </div>

        <?php if(!$uhReady): ?><div class="alert alert-warning">Fitur penerima dan berkas memerlukan migrasi 2026_10_22_ujian_harian.sql.</div><?php endif ?>
        <section class="card border-0 shadow-sm uh-list <?= !$ujian_list?'uh-list-empty':'' ?>">
            <div class="table-responsive">
                <table class="table align-middle w-100 uh-exam-table">
                    <caption class="visually-hidden">Daftar ulangan harian dan status pengerjaan siswa</caption>
                    <thead class="table-light">
                        <tr>
                            <th>Mapel / Kelas</th>
                            <th>Nama Ulangan Harian</th>
                            <th>Durasi</th>
                            <th>Waktu Pelaksanaan</th>
                            <th>Soal</th>
                            <th>Siswa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ujian_list as $u): $uh=uh_settings($db,(int)$u['id'],(int)$u['pengajaran_id']);$isRemedial=false;if(uh_remedial_ready($db)){$q=$db->prepare('SELECT ujian_asal_id FROM ujian_harian_remedial WHERE ujian_id=?');$q->execute([$u['id']]);$isRemedial=(bool)$q->fetchColumn();}$recipientIds=[];if($uhReady){$q=$db->prepare('SELECT siswa_id FROM ujian_harian_penerima WHERE ujian_id=?');$q->execute([$u['id']]);$recipientIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));} ?>
                        <tr>
                            <td data-label="Mapel / Kelas">
                                <strong><?= sanitize($u['nama_mapel']) ?></strong><br>
                                <small class="text-muted"><?= sanitize($u['nama_kelas']) ?></small>
                            </td>
                            <td data-label="Ulangan">
                                <span class="badge bg-secondary me-1"><?= $isRemedial?'Remedial':($u['jenis_ujian']==='Kuis'?'UH':sanitize($u['jenis_ujian'])) ?></span>
                                <?= $u['jenis_ujian']==='Kuis'?'UH '.(int)$uh['nomor'].' &middot; ':'' ?><?= sanitize($u['nama_ujian']) ?><small class="d-block text-muted"><?= $uh['metode']==='berkas'?'Unggah berkas':'CBT' ?> &middot; <?= $uh['penerima']==='pilihan'?'Siswa terpilih - '.sanitize($u['nama_kelas']):'Siswa kelas '.sanitize($u['nama_kelas']) ?></small>
                            </td>
                            <td data-label="Durasi"><?= $uh['metode']==='berkas'?'Sesuai tenggat':(int)$u['durasi_menit'].' Menit' ?></td>
                            <td data-label="Jadwal" class="small">
                                <?= date('d/m/y H:i', strtotime($u['waktu_mulai'])) ?> s/d<br>
                                <?= date('d/m/y H:i', strtotime($u['waktu_selesai'])) ?>
                            </td>
                            <td data-label="Soal"><span class="badge bg-info text-dark"><?= $uh['metode']==='berkas'?'Berkas jawaban':((int)$u['total_soal']===0?'Belum ada soal':(int)$u['total_soal'].' soal') ?></span></td>
                            <td data-label="Pengerjaan" class="small text-nowrap"><span class="badge bg-primary-subtle text-primary"><?= (int)$u['total_peserta'] ?>/<?= (int)$u['total_siswa'] ?> mulai</span><br><span class="text-success"><?= (int)$u['peserta_selesai'] ?> selesai</span> &middot; <span class="text-danger"><?= max(0,(int)$u['total_siswa']-(int)$u['total_peserta']) ?> belum</span></td>
                            <td data-label="Aksi"><div class="uh-row-actions">
<?php if($uh['metode']==='cbt'): ?><button class="btn btn-sm btn-outline-primary" onclick='openAddSoalModal(<?= (int)$u["id"] ?>,<?= json_encode($u["nama_ujian"],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>,<?= json_encode($soal_per_ujian[(int)$u["id"]]??[],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>,<?= (int)$u['total_peserta']>0?'true':'false' ?>)'><i class="fa-regular fa-file-lines me-1" aria-hidden="true"></i><?= (int)$u['total_peserta']>0?'Lihat soal':'Soal' ?></button><?php endif ?>
<a class="btn btn-sm btn-outline-secondary" href="ujian_hasil.php?ujian_id=<?= (int)$u['id'] ?>"><i class="fa-solid fa-chart-column me-1" aria-hidden="true"></i>Hasil</a>
<details class="uh-actions-more"><summary class="btn btn-sm btn-light">Lainnya</summary><div class="d-flex flex-wrap gap-2 mt-2"><button aria-label="Edit ulangan" title="Edit ulangan" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalCreateUjian" onclick='editUjian(<?= json_encode(["id"=>(int)$u["id"],"pengajaran_id"=>(int)$u["pengajaran_id"],"nama_ujian"=>$u["nama_ujian"],"jenis_ujian"=>$u["jenis_ujian"],"durasi_menit"=>(int)$u["durasi_menit"],"waktu_mulai"=>date("Y-m-d\\TH:i",strtotime($u["waktu_mulai"])),"waktu_selesai"=>date("Y-m-d\\TH:i",strtotime($u["waktu_selesai"])),"acak_soal"=>(int)$u["acak_soal"],"metode"=>$uh["metode"],"penerima"=>$uh["penerima"],"instruksi"=>$uh["instruksi"],"siswa_ids"=>$recipientIds],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>'><i class="fa-solid fa-pen me-1" aria-hidden="true"></i>Edit jadwal</button><button aria-label="Hapus ulangan" title="Hapus ulangan" class="btn btn-sm btn-outline-danger" onclick='hapusUjian(<?= (int)$u["id"] ?>,<?= json_encode($u["nama_ujian"],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Hapus</button></div></details>
</div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$ujian_list): ?>
                        <tr class="uh-empty-row"><td colspan="7"><div class="uh-empty"><span class="uh-empty-icon"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></span><h3 class="h6">Belum ada ulangan</h3><p>Tidak ada ulangan harian pada filter yang dipilih.</p><small>Klik Buat ulangan untuk menyiapkan jadwal dan soal.</small></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php require __DIR__.'/../includes/rekap_ujian_section.php'; ?>
    </main>
</div>

<!-- Modal Buat Ulangan Harian -->
<div class="modal fade uh-modal" id="modalCreateUjian" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="judulModalUjian">Buat Ulangan Harian Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCreateUjian" class="uh-modal-form">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="ujian_id" id="editUjianId">
                    <input type="hidden" id="aksiUjian" value="create_ujian">
                    <div class="mb-3">
                        <label for="editPengajaranUjian" class="form-label fw-semibold">Target kelas dan mata pelajaran</label>
                        <select name="pengajaran_id" id="editPengajaranUjian" class="form-select" required aria-describedby="uhTargetSummary"><option value="">Pilih kelas dan mata pelajaran tujuan</option>
                            <?php foreach($pengajaran_list as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= sanitize($p['nama_kelas'].' - '.$p['nama_mapel'].' ('.$p['semester'].', '.$p['tahun_ajaran'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p id="uhTargetSummary" class="uh-target-summary" role="status" aria-live="polite">Pilih kelas dan mata pelajaran penerima ulangan.</p>
                    <div class="mb-3">
                        <label for="editNamaUjian" class="form-label fw-semibold">Nama Ulangan Harian</label>
                        <input type="text" name="nama_ujian" id="editNamaUjian" class="form-control" maxlength="255" placeholder="Contoh: Ulangan Harian Topologi" required>
                    </div>
                    <div class="row">
                        <input type="hidden" name="jenis_ujian" id="editJenisUjian" value="Kuis">
                        <div class="col-md-6 mb-3">
                            <label for="editDurasiUjian" class="form-label fw-semibold">Durasi (Menit)</label>
                            <input type="number" name="durasi_menit" id="editDurasiUjian" class="form-control" min="1" max="600" value="60" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editMulaiUjian" class="form-label fw-semibold">Waktu Mulai</label>
                        <input type="datetime-local" name="waktu_mulai" id="editMulaiUjian" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="editSelesaiUjian" class="form-label fw-semibold">Waktu Selesai</label>
                        <input type="datetime-local" name="waktu_selesai" id="editSelesaiUjian" class="form-control" required>
                    </div>
                    <details id="uhExtraOptions" class="uh-extra-options"><summary>Opsi tambahan</summary><div class="pt-3">
                    <div class="row g-2 mb-3"><div class="col-sm-6"><label for="uhMetode" class="form-label">Pengerjaan</label><select id="uhMetode" name="metode" class="form-select" onchange="updateExamMethod()"><option value="cbt">CBT: PG / Esai</option><option value="berkas">Unggah berkas jawaban</option></select></div><div class="col-sm-6"><label for="uhPenerima" class="form-label">Penerima</label><select id="uhPenerima" name="penerima" class="form-select" onchange="updateUhStudents()"><option value="kelas">Semua siswa di kelas terpilih</option><option value="pilihan">Siswa tertentu</option></select></div></div>
                    <div class="mb-3" id="uhSiswaWrap" hidden><label for="uhSiswa" class="form-label">Pilih siswa (bisa lebih dari satu)</label><select id="uhSiswa" name="siswa_ids[]" multiple size="6" class="form-select"></select></div>
                    <div class="mb-3"><label for="uhInstruksi" class="form-label">Instruksi / soal untuk pengumpulan berkas</label><textarea id="uhInstruksi" name="instruksi" class="form-control" rows="4" maxlength="20000"></textarea><small class="text-muted">Ulangan tampil di siswa setelah disimpan. Pengerjaan mengikuti jadwal. Nomor UH otomatis per pengajaran (kelas, mapel, semester dan tahun ajaran).</small></div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="acak_soal" id="acakSoal" value="1" checked>
                        <label class="form-check-label fw-semibold" for="acakSoal">Acak Urutan Soal Siswa</label>
                    </div>
                    </div></details>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="tombolSimpanUjian">Simpan &amp; lanjut ke soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Satu tempat untuk menyiapkan dan meninjau soal. -->
<div class="modal fade uh-modal" id="modalAddSoal" tabindex="-1" aria-labelledby="titleSoalModal"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h2 class="modal-title fw-bold" id="titleSoalModal">Soal ulangan</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
<div class="modal-body">
<?php if($questionWorkspace&&$examFlashSuccess): ?><div class="alert alert-success" role="status"><?= sanitize($examFlashSuccess) ?></div><?php endif ?>
<?php if($questionWorkspace&&$examFlashError): ?><div class="alert alert-danger" role="alert"><?= sanitize($examFlashError) ?></div><?php endif ?>
<div id="uhSoalLocked" class="alert alert-light border small" hidden>Soal sudah digunakan siswa dan dikunci. Anda tetap dapat melihat isi soal.</div>
<div id="uhSoalTools" class="uh-question-tools mb-3"><button type="button" class="btn btn-primary btn-sm" onclick="showQuestionImport()"><i class="fa-solid fa-file-import me-1" aria-hidden="true"></i>Impor Excel</button><button type="button" class="btn btn-outline-secondary btn-sm" onclick="showQuestionForm()">Tambah manual</button></div>
<form id="formImportSoal" method="post" action="ujian_excel.php?action=import" enctype="multipart/form-data" class="uh-question-panel mb-3" hidden>
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="ujian_id" id="importUjianId">
<p class="small mb-2">1. Unduh template, isi soal dan kunci jawaban, lalu simpan sebagai Excel.</p><a href="ujian_excel.php?action=template" class="btn btn-outline-primary btn-sm mb-3">Unduh template soal</a>
<p class="small text-muted">Template mendukung PG dan esai. Bobot boleh dikosongkan (otomatis 1). Semua bobot tidak perlu berjumlah 100. Contoh ada di sheet terpisah.</p>
<label for="importSoalFile" class="form-label">2. Unggah file soal (.xlsx / .xls, maksimal 5 MB)</label><input id="importSoalFile" class="form-control mb-3" type="file" name="file_excel" accept=".xlsx,.xls" required>
<button type="submit" class="btn btn-primary btn-sm">Impor soal</button></form>
<form id="formAddSoal" class="uh-question-panel mb-3" hidden><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="ujian_id" id="modalUjianId"><input type="hidden" name="soal_id" id="modalSoalId"><input type="hidden" id="aksiSoal" value="add_soal">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tipeSoalSelect" class="form-label fw-semibold">Tipe soal</label>
                            <select name="tipe_soal" id="tipeSoalSelect" class="form-select" onchange="toggleTipeSoal(this.value)">
                                <option value="PG">Pilihan Ganda (PG)</option>
                                <option value="ESAI">Esai / Uraian</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="bobotSoal" class="form-label fw-semibold">Bobot soal</label>
                            <input type="number" name="bobot" id="bobotSoal" class="form-control" min="1" max="100" value="1"><small class="text-muted">Kosong = 1. Nilai akhir dihitung otomatis dari total bobot.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="pertanyaanSoal" class="form-label fw-semibold">Pertanyaan</label>
                        <textarea name="pertanyaan" id="pertanyaanSoal" class="form-control" rows="3" required></textarea>
                    </div>

                    <div id="pgContainer">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6"><input type="text" name="opsi_a" id="opsiA" class="form-control" placeholder="Pilihan A"></div>
                            <div class="col-md-6"><input type="text" name="opsi_b" id="opsiB" class="form-control" placeholder="Pilihan B"></div>
                            <div class="col-md-6"><input type="text" name="opsi_c" id="opsiC" class="form-control" placeholder="Pilihan C"></div>
                            <div class="col-md-6"><input type="text" name="opsi_d" id="opsiD" class="form-control" placeholder="Pilihan D"></div>
                            <div class="col-md-12"><input type="text" name="opsi_e" id="opsiE" class="form-control" placeholder="Pilihan E"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-success">Kunci Jawaban Benar</label>
                            <select name="kunci_jawaban" id="kunciSoal" class="form-select">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="E">E</option>
                            </select>
                        </div>
                    </div>

<div class="d-flex gap-2 mt-3"><button type="submit" class="btn btn-primary btn-sm" id="tombolSimpanSoal">Tambahkan Soal</button><button type="button" class="btn btn-light btn-sm d-none" id="batalEditSoal" onclick="resetFormSoal()">Batal edit</button></div>
</form>
<div id="daftarSoalModal"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Selesai</button></div>
</div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let modalSoal;
let soalTerkunci=false;

document.getElementById('filterKelasUjian')?.addEventListener('change', function () {
    document.getElementById('filterMapelUjian').disabled = true;
    document.getElementById('filterUjianForm').submit();
});
document.getElementById('filterMapelUjian')?.addEventListener('change', function () {
    document.getElementById('filterUjianForm').submit();
});

$(document).ready(function() {
    modalSoal = new bootstrap.Modal(document.getElementById('modalAddSoal'));
    <?php if($questionWorkspace): ?>openAddSoalModal(<?= (int)$questionWorkspace['id'] ?>,<?= json_encode($questionWorkspace['nama_ujian'],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,<?= json_encode($soal_per_ujian[(int)$questionWorkspace['id']]??[],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,<?= (int)$questionWorkspace['total_peserta']>0?'true':'false' ?>);<?php endif ?>

    $('#formCreateUjian').on('submit', function(e) {
        e.preventDefault();
        $.post('ujian_action.php?action=' + document.getElementById('aksiUjian').value, $(this).serialize(), function(res) {
            if(res.status === 'success') {
                if(document.getElementById('aksiUjian').value==='create_ujian'&&document.getElementById('uhMetode').value==='cbt'){
                    location.href='ujian.php?soal='+encodeURIComponent(res.ujian_id);
                }else{
                    Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                }
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        }, 'json').fail(function(xhr){Swal.fire('Gagal!',xhr.responseJSON?.message||'Ulangan Harian gagal diproses.','error');});
    });

    $('#formAddSoal').on('submit', function(e) {
        e.preventDefault();
        $.post('ujian_action.php?action=' + document.getElementById('aksiSoal').value, $(this).serialize(), function(res) {
            if(res.status === 'success') {
                Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        }, 'json').fail(function(xhr){Swal.fire('Gagal!',xhr.responseJSON?.message||'Soal gagal diproses.','error');});
    });
});

let soalAktif=[];
function openAddSoalModal(id, nama, daftar, locked=false) {
    document.getElementById('modalUjianId').value=id;
    document.getElementById('importUjianId').value=id;
    document.getElementById('titleSoalModal').textContent='Soal: '+nama;
    soalTerkunci=locked;
    document.getElementById('uhSoalTools').hidden=locked;
    document.getElementById('uhSoalLocked').hidden=!locked;
    document.getElementById('formImportSoal').hidden=true;
    document.getElementById('formAddSoal').hidden=true;
    document.getElementById('importSoalFile').value='';
    soalAktif=daftar||[];renderDaftarSoal();resetFormSoal();
    const url=new URL(location.href);url.searchParams.set('soal',String(id));history.replaceState(null,'',url);
    modalSoal.show();
}
function showQuestionImport(){
    if(soalTerkunci)return;
    document.getElementById('formImportSoal').hidden=false;
    document.getElementById('formAddSoal').hidden=true;
}
function showQuestionForm(reset=true){
    if(soalTerkunci)return;
    if(reset)resetFormSoal();
    document.getElementById('formImportSoal').hidden=true;
    document.getElementById('formAddSoal').hidden=false;
}

const uhStudents=<?= json_encode($uhStudents,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const uhAssignments=<?= json_encode($pengajaran_list,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
function updateUhStudents(selected=[]){
    const pid=document.getElementById('editPengajaranUjian').value;
    const assignment=uhAssignments.find(p=>String(p.id)===pid);
    const select=document.getElementById('uhSiswa');select.replaceChildren();
    uhStudents.filter(s=>String(s.kelas_id)===String(assignment?.kelas_id)).forEach(s=>{const o=new Option(s.nama_lengkap,s.id);o.selected=selected.map(Number).includes(Number(s.id));select.add(o);});
    const chosen=document.getElementById('uhPenerima').value==='pilihan';document.getElementById('uhSiswaWrap').hidden=!chosen;select.required=chosen;
    updateUhTargetSummary();
}
function updateUhTargetSummary(){
    const assignment=uhAssignments.find(p=>String(p.id)===document.getElementById('editPengajaranUjian').value);
    const select=document.getElementById('uhSiswa'),chosen=document.getElementById('uhPenerima').value==='pilihan';
    document.getElementById('uhTargetSummary').textContent=assignment?('Penerima: '+(chosen?select.selectedOptions.length+' siswa terpilih di kelas ':'siswa kelas ')+assignment.nama_kelas+' | '+assignment.nama_mapel+' | '+assignment.semester+' '+assignment.tahun_ajaran):'Pilih kelas dan mata pelajaran penerima ulangan.';
}
document.getElementById('uhSiswa').addEventListener('change',updateUhTargetSummary);
document.getElementById('editPengajaranUjian').addEventListener('change',()=>updateUhStudents());
function updateExamMethod(){
    const editing=document.getElementById('aksiUjian').value==='update_ujian';
    document.getElementById('tombolSimpanUjian').textContent=editing?'Simpan perubahan':(document.getElementById('uhMetode').value==='berkas'?'Simpan ulangan':'Simpan & lanjut ke soal');
}
function resetFormUjian(){
    document.getElementById('formCreateUjian').reset();
    document.getElementById('editUjianId').value='';
    document.getElementById('aksiUjian').value='create_ujian';
    document.getElementById('judulModalUjian').textContent='Buat Ulangan Harian Baru';
    document.getElementById('tombolSimpanUjian').textContent='Simpan & lanjut ke soal';document.getElementById('uhExtraOptions').open=false;updateUhStudents();
}
function editUjian(data){
    document.getElementById('formCreateUjian').reset();
    document.getElementById('editUjianId').value=data.id;
    document.getElementById('aksiUjian').value='update_ujian';
    document.getElementById('editPengajaranUjian').value=data.pengajaran_id;
    document.getElementById('editNamaUjian').value=data.nama_ujian;
    document.getElementById('editJenisUjian').value=data.jenis_ujian;
    document.getElementById('editDurasiUjian').value=data.durasi_menit;
    document.getElementById('editMulaiUjian').value=data.waktu_mulai;
    document.getElementById('editSelesaiUjian').value=data.waktu_selesai;
    document.getElementById('acakSoal').checked=Number(data.acak_soal)===1;
    document.getElementById('judulModalUjian').textContent='Edit Ulangan Harian';
    document.getElementById('tombolSimpanUjian').textContent='Simpan Perubahan';
    document.getElementById('uhExtraOptions').open=data.metode==='berkas'||data.penerima==='pilihan';document.getElementById('uhMetode').value=data.metode;document.getElementById('uhPenerima').value=data.penerima;document.getElementById('uhInstruksi').value=data.instruksi||'';updateUhStudents(data.siswa_ids||[]);
}
async function kirimAksiUjian(action,fields){
    const data=new FormData();data.append('csrf_token',<?= json_encode($_SESSION['csrf_token']) ?>);
    Object.entries(fields).forEach(([key,value])=>data.append(key,value));
    const response=await fetch('ujian_action.php?action='+action,{method:'POST',body:data});
    const body=await response.json();
    if(!response.ok||body.status!=='success')throw new Error(body.message||'Permintaan gagal.');
    return body;
}
function hapusUjian(id,nama){
    Swal.fire({title:'Hapus ulangan harian?',text:nama+' beserta soal dan seluruh hasil siswa akan dihapus.',icon:'warning',showCancelButton:true,confirmButtonText:'Hapus',cancelButtonText:'Batal',confirmButtonColor:'#dc3545'}).then(async function(result){
        if(!result.isConfirmed)return;
        try{const body=await kirimAksiUjian('delete_ujian',{ujian_id:id});Swal.fire('Berhasil!',body.message,'success').then(()=>location.reload());}
        catch(error){Swal.fire('Gagal!',error.message,'error');}
    });
}
function renderDaftarSoal(){
    const container=document.getElementById('daftarSoalModal');container.replaceChildren();
    const title=document.createElement('div');title.className='d-flex justify-content-between align-items-center mb-2';
    const strong=document.createElement('strong');strong.textContent='Soal Tersimpan';
    const badge=document.createElement('span');badge.className='badge bg-primary';badge.textContent=soalAktif.length+' soal';
    title.append(strong,badge);container.appendChild(title);
    if(!soalAktif.length){const empty=document.createElement('div');empty.className='alert alert-light small';empty.textContent='Belum ada soal. Pilih Impor Excel atau Tambah manual di atas.';container.appendChild(empty);return;}
    const list=document.createElement('div');list.className='list-group mb-3 uh-question-list';
    soalAktif.forEach(function(soal,index){
        const item=document.createElement('div');item.className='list-group-item d-flex justify-content-between align-items-start gap-2';
        const copy=document.createElement('div');copy.className='small';const label=document.createElement('strong');label.textContent=(index+1)+'. ['+soal.tipe_soal+'] ';
        const question=document.createTextNode(soal.pertanyaan);copy.append(label,question);
        const actions=document.createElement('div');actions.className='btn-group btn-group-sm';
        const edit=document.createElement('button');edit.type='button';edit.className='btn btn-outline-secondary';edit.textContent='Edit';edit.onclick=()=>editSoal(soal);
        const del=document.createElement('button');del.type='button';del.className='btn btn-outline-danger';del.textContent='Hapus';del.onclick=()=>hapusSoal(soal.id);
        edit.setAttribute('aria-label','Edit soal '+(index+1));del.setAttribute('aria-label','Hapus soal '+(index+1));if(!soalTerkunci)actions.append(edit,del);const weight=document.createElement('small');weight.className='d-block text-muted';weight.textContent='Bobot: '+soal.bobot;copy.appendChild(weight);item.append(copy,actions);list.appendChild(item);
    });container.appendChild(list);
}
function resetFormSoal(){
    document.getElementById('modalSoalId').value='';
    document.getElementById('aksiSoal').value='add_soal';
    document.getElementById('tipeSoalSelect').value='PG';
    document.getElementById('bobotSoal').value=1;
    document.getElementById('pertanyaanSoal').value='';
    ['opsiA','opsiB','opsiC','opsiD','opsiE'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('kunciSoal').value='A';toggleTipeSoal('PG');
    document.getElementById('tombolSimpanSoal').textContent='Tambahkan Soal';
    document.getElementById('batalEditSoal').classList.add('d-none');
}
function editSoal(soal){
    if(soalTerkunci)return;showQuestionForm(false);
    document.getElementById('modalSoalId').value=soal.id;
    document.getElementById('aksiSoal').value='update_soal';
    document.getElementById('tipeSoalSelect').value=soal.tipe_soal;
    document.getElementById('bobotSoal').value=soal.bobot;
    document.getElementById('pertanyaanSoal').value=soal.pertanyaan;
    document.getElementById('opsiA').value=soal.opsi_a||'';document.getElementById('opsiB').value=soal.opsi_b||'';
    document.getElementById('opsiC').value=soal.opsi_c||'';document.getElementById('opsiD').value=soal.opsi_d||'';
    document.getElementById('opsiE').value=soal.opsi_e||'';document.getElementById('kunciSoal').value=soal.kunci_jawaban||'A';
    toggleTipeSoal(soal.tipe_soal);document.getElementById('tombolSimpanSoal').textContent='Simpan Perubahan';
    document.getElementById('batalEditSoal').classList.remove('d-none');
}
function hapusSoal(id){
    Swal.fire({title:'Hapus soal?',text:'Jawaban siswa untuk soal ini juga akan terhapus.',icon:'warning',showCancelButton:true,confirmButtonText:'Hapus',cancelButtonText:'Batal',confirmButtonColor:'#dc3545'}).then(async function(result){
        if(!result.isConfirmed)return;
        try{const body=await kirimAksiUjian('delete_soal',{soal_id:id});Swal.fire('Berhasil!',body.message,'success').then(()=>location.reload());}
        catch(error){Swal.fire('Gagal!',error.message,'error');}
    });
}
function toggleTipeSoal(val) {
    if(val === 'ESAI') {
        $('#pgContainer').hide();
    } else {
        $('#pgContainer').show();
    }
}
</script>
