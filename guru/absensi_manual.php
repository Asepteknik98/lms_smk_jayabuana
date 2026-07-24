<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

check_access([2]);
$db = Database::getInstance();
$stmt = $db->prepare('SELECT id FROM guru WHERE user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt->fetchColumn() ?: 0);
proses_penutupan_absensi_otomatis($db);

$success = $_SESSION['absensi_manual_success'] ?? '';
$error = $_SESSION['absensi_manual_error'] ?? '';
unset($_SESSION['absensi_manual_success'], $_SESSION['absensi_manual_error']);

$stmt = $db->prepare(
    'SELECT p.id,p.kelas_id,m.nama_mapel,k.nama_kelas,p.semester,p.tahun_ajaran
     FROM pengajaran p
     JOIN mapel m ON m.id=p.mapel_id
     JOIN kelas k ON k.id=p.kelas_id
     WHERE p.guru_id=?
     ORDER BY p.tahun_ajaran DESC,p.semester,m.nama_mapel,k.nama_kelas'
);
$stmt->execute([$guru_id]);
$pengajaran_list = $stmt->fetchAll();
$pengajaran_id = (int)($_GET['pengajaran_id'] ?? $_POST['pengajaran_id'] ?? 0);
$info = null;
foreach ($pengajaran_list as $item) {
    if ((int)$item['id'] === $pengajaran_id) { $info = $item; break; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 0);
    $tanggal = trim($_POST['tanggal'] ?? '');
    $statuses = $_POST['status'] ?? [];
    $keterangan = $_POST['keterangan'] ?? [];
    $redirect = 'absensi_manual.php' . ($pengajaran_id ? '?pengajaran_id='.$pengajaran_id : '');

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['absensi_manual_error'] = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
        redirect($redirect);
    }
    $tanggal_obj = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$info || $pertemuan_ke < 1 || $pertemuan_ke > 20 || !$tanggal_obj || $tanggal_obj->format('Y-m-d') !== $tanggal) {
        $_SESSION['absensi_manual_error'] = 'Pengajaran, pertemuan, atau tanggal tidak valid.';
        redirect($redirect);
    }

    $stmt = $db->prepare('SELECT id,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap');
    $stmt->execute([(int)$info['kelas_id']]);
    $siswa_kelas = $stmt->fetchAll();
    $status_valid = ['Hadir','Sakit','Izin','Alpa'];
    foreach ($siswa_kelas as $siswa) {
        if (!in_array($statuses[$siswa['id']] ?? '', $status_valid, true)) {
            $_SESSION['absensi_manual_error'] = 'Status seluruh siswa wajib dipilih.';
            redirect($redirect);
        }
    }

    try {
        $db->beginTransaction();
        $waktu = $tanggal . ' ' . date('H:i:s');
        $stmt = $db->prepare(
            "INSERT INTO sesi_absensi
             (pengajaran_id,pertemuan_ke,tanggal,waktu_buka,waktu_tutup,status)
             VALUES (?,?,?,?,?,'Ditutup')"
        );
        $stmt->execute([$pengajaran_id,$pertemuan_ke,$tanggal,$waktu,$waktu]);
        $sesi_id = (int)$db->lastInsertId();
        $stmt_detail = $db->prepare(
            'INSERT INTO detail_absensi
             (sesi_absensi_id,siswa_id,status,waktu_checkin,keterangan)
             VALUES (?,?,?,?,?)'
        );
        foreach ($siswa_kelas as $siswa) {
            $sid = (int)$siswa['id'];
            $status = $statuses[$sid];
            $catatan = mb_substr(trim((string)($keterangan[$sid] ?? '')), 0, 255);
            $stmt_detail->execute([
                $sesi_id, $sid, $status,
                $status === 'Hadir' ? $waktu : null,
                $catatan ?: ($status === 'Hadir' ? 'Dicatat manual oleh guru' : null),
            ]);
        }
        $db->commit();
        catat_log($_SESSION['user_id'], "Menyimpan absensi manual pertemuan $pertemuan_ke untuk {$info['nama_mapel']} - {$info['nama_kelas']}");
        $_SESSION['absensi_manual_success'] = "Absensi manual pertemuan ke-$pertemuan_ke berhasil disimpan.";
        redirect('absensi_manual.php?pengajaran_id='.$pengajaran_id);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Absensi manual kelas gagal: '.$e->getMessage());
        $_SESSION['absensi_manual_error'] = $e->getCode() === '23000'
            ? 'Absensi untuk pertemuan tersebut sudah tersedia. Pilih pertemuan lain.'
            : 'Absensi manual gagal disimpan.';
        redirect($redirect);
    }
}

$siswa_list = [];
$pertemuan_terpakai = [];
if ($info) {
    $stmt = $db->prepare('SELECT id,nis,nisn,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap');
    $stmt->execute([(int)$info['kelas_id']]);
    $siswa_list = $stmt->fetchAll();
    $stmt = $db->prepare('SELECT pertemuan_ke FROM sesi_absensi WHERE pengajaran_id=?');
    $stmt->execute([$pengajaran_id]);
    $pertemuan_terpakai = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.manual-page{background:#f5f7fb;min-width:0;overflow-x:hidden}.manual-content{max-width:1200px;margin:auto;padding-bottom:calc(28px + env(safe-area-inset-bottom))!important}.manual-card{border:0;border-radius:18px;box-shadow:0 6px 22px rgba(15,23,42,.06)}.manual-card .form-control,.manual-card .form-select{min-height:44px;border-radius:10px}.student-attendance-row{border:1px solid #e8edf4;border-radius:14px;padding:13px;background:#fff;transition:border-color .15s ease,box-shadow .15s ease}.student-attendance-row:focus-within{border-color:#bfdbfe;box-shadow:0 0 0 3px rgba(37,99,235,.07)}.student-attendance-row+.student-attendance-row{margin-top:9px}.student-name{min-width:0}.student-name strong{overflow-wrap:anywhere;line-height:1.35}.student-name small{display:block;line-height:1.45}.status-options{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}.status-option{position:relative;min-width:0}.status-option input{position:absolute;opacity:0;pointer-events:none}.status-option label,.reset-student{display:flex;align-items:center;justify-content:center;gap:5px;width:100%;min-height:42px;padding:7px 6px;border:1px solid #dbe2ea;border-radius:10px;background:#fff;color:#64748b;font-size:.76rem;font-weight:700;cursor:pointer;transition:.15s ease;white-space:nowrap}.status-option label::before{content:"";width:12px;height:12px;border:2px solid currentColor;border-radius:50%;flex:0 0 12px}.status-option input:focus-visible+label,.reset-student:focus-visible{outline:3px solid rgba(37,99,235,.25);outline-offset:2px}.status-option input:checked+label{box-shadow:0 0 0 2px rgba(15,23,42,.05);color:#fff}.status-option input:checked+label::before{border:3px solid #fff;background:currentColor;box-shadow:inset 0 0 0 2px currentColor}.status-hadir input:checked+label{background:#198754;border-color:#198754}.status-sakit input:checked+label{background:#6c757d;border-color:#6c757d}.status-izin input:checked+label{background:#0d6efd;border-color:#0d6efd}.status-alpa input:checked+label{background:#dc3545;border-color:#dc3545}.reset-student:hover{background:#f1f5f9;color:#334155}.manual-summary{position:sticky;bottom:calc(12px + env(safe-area-inset-bottom));z-index:10;border:1px solid rgba(226,232,240,.9);border-radius:15px;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);box-shadow:0 8px 28px rgba(15,23,42,.16)}.manual-summary .badge{font-size:.72rem;padding:7px 8px}.manual-summary .btn{min-height:44px}.offline-note{font-size:.76rem;color:#64748b}
.student-list-toolbar{position:sticky;top:0;z-index:5;padding:10px;margin:0 -4px 12px;border:1px solid #e8edf4;border-radius:13px;background:rgba(248,250,252,.96);backdrop-filter:blur(8px)}.student-search-wrap{position:relative}.student-search-wrap>i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none}.student-search-wrap input{padding-left:38px}.student-pagination{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px}.student-pagination .btn{min-height:40px;min-width:42px}.student-page-info{min-width:125px;text-align:center;font-size:.78rem;color:#64748b}.student-empty-filter{border:1px dashed #cbd5e1;border-radius:13px;padding:28px;text-align:center;color:#64748b}
@media(min-width:992px) and (max-width:1199.98px){.student-attendance-row .student-name{font-size:.9rem}.status-option label,.reset-student{font-size:.7rem;padding-inline:4px}.status-option label::before{display:none}}
@media(max-width:991.98px){.student-attendance-row{padding:14px}.status-options{grid-template-columns:repeat(5,minmax(72px,1fr))}.student-attendance-row .row{--bs-gutter-y:.65rem}.manual-summary{bottom:calc(74px + env(safe-area-inset-bottom))}}
@media(max-width:767.98px){.status-options{grid-template-columns:repeat(3,minmax(0,1fr))}.manual-summary{position:relative;bottom:auto;margin-top:12px!important}.manual-summary .btn{width:100%}}
@media(max-width:575.98px){.manual-content{padding:12px!important;padding-bottom:calc(20px + env(safe-area-inset-bottom))!important}.manual-card{border-radius:15px}.manual-card .card-body{padding:14px!important}.manual-card .form-control,.manual-card .form-select{min-height:46px;font-size:16px}.student-attendance-row{padding:12px;border-radius:13px}.student-name strong{font-size:.92rem}.student-name small{font-size:.71rem}.status-options{gap:7px}.status-option label,.reset-student{min-height:46px;font-size:.78rem;border-radius:11px}.manual-summary{padding:12px!important;border-radius:14px}.manual-summary>div{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;width:100%}.manual-summary .badge{margin:0!important;text-align:center;padding:7px 4px}.top-navbar h5{font-size:1rem}.top-navbar small{font-size:.7rem}}
@media(max-width:374.98px){.status-options{grid-template-columns:repeat(2,minmax(0,1fr))}.manual-summary>div{grid-template-columns:repeat(2,1fr)}}
</style>
<div id="page-content-wrapper" class="manual-page">
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div class="d-flex justify-content-between align-items-center gap-2 w-100"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-user-check text-success me-2"></i>Absensi Manual</h5><small class="text-muted">Catat kehadiran pembelajaran offline</small></div><a href="riwayat_absensi.php" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-clock-rotate-left me-1"></i><span class="d-none d-sm-inline">Riwayat</span></a></div></nav>
<main class="container-fluid manual-content p-3 p-md-4">
<?php if($success): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($success) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<section class="card manual-card mb-3"><div class="card-body p-4">
<form method="get" class="row g-2 align-items-end" id="formPilihPengajaran"><div class="col-md-9"><label class="form-label fw-semibold">Mata Pelajaran dan Kelas</label><select name="pengajaran_id" id="pilihPengajaranManual" class="form-select" required><option value="">-- Pilih Pengajaran --</option><?php foreach($pengajaran_list as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $pengajaran_id===(int)$p['id']?'selected':'' ?>><?= sanitize($p['nama_mapel'].' — '.$p['nama_kelas'].' ('.$p['semester'].' / '.$p['tahun_ajaran'].')') ?></option><?php endforeach; ?></select><small class="text-muted" id="statusMuatSiswa">Daftar siswa tampil otomatis setelah pengajaran dipilih.</small></div><div class="col-md-3 d-grid"><button class="btn btn-primary" id="tombolTampilkanSiswa"><i class="fa-solid fa-users-viewfinder me-1"></i>Tampilkan Siswa</button></div></form>
</div></section>

<?php if($info): ?>
<form method="post" id="formAbsensiManualKelas">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="pengajaran_id" value="<?= $pengajaran_id ?>">
<section class="card manual-card mb-3"><div class="card-body p-4">
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h6 class="fw-bold mb-1"><?= sanitize($info['nama_mapel'].' — '.$info['nama_kelas']) ?></h6><small class="text-muted"><?= count($siswa_list) ?> siswa</small></div><div class="d-flex flex-wrap gap-1"><button type="button" class="btn btn-sm btn-outline-success set-all" data-status="Hadir">Semua Hadir</button><button type="button" class="btn btn-sm btn-outline-danger set-all" data-status="Alpa">Semua Alpa</button><button type="button" class="btn btn-sm btn-outline-secondary" id="resetAbsensi"><i class="fa-solid fa-rotate-left me-1"></i>Reset</button></div></div>
<div class="row g-2 mb-3"><div class="col-md-6"><label class="form-label fw-semibold">Pertemuan</label><select name="pertemuan_ke" class="form-select" required><option value="">-- Pilih Pertemuan --</option><?php for($i=1;$i<=20;$i++): ?><option value="<?= $i ?>" <?= in_array($i,$pertemuan_terpakai,true)?'disabled':'' ?>>Pertemuan <?= $i ?><?= in_array($i,$pertemuan_terpakai,true)?' — sudah ada':'' ?></option><?php endfor; ?></select></div><div class="col-md-6"><label class="form-label fw-semibold">Tanggal Pembelajaran</label><input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required></div></div>
<?php if(!$siswa_list): ?><div class="alert alert-warning mb-0">Belum ada siswa pada kelas ini.</div><?php else: ?>
<div class="student-list-toolbar"><div class="row g-2 align-items-center"><div class="col-12 col-md"><div class="student-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control" id="cariSiswa" placeholder="Cari nama, NIS, atau NISN..." autocomplete="off"></div></div><div class="col-7 col-md-auto"><select class="form-select" id="jumlahPerHalaman" aria-label="Jumlah siswa per halaman"><option value="10">10 siswa / halaman</option><option value="15" selected>15 siswa / halaman</option><option value="25">25 siswa / halaman</option><option value="50">50 siswa / halaman</option></select></div><div class="col-5 col-md-auto"><span class="badge bg-primary-subtle text-primary w-100 py-2" id="hasilPencarian"><?= count($siswa_list) ?> siswa</span></div></div></div>
<div id="daftarSiswaManual"><?php foreach($siswa_list as $no=>$siswa): ?>
<div class="student-attendance-row" data-search="<?= sanitize(mb_strtolower($siswa['nama_lengkap'].' '.$siswa['nis'].' '.$siswa['nisn'])) ?>"><div class="row g-2 align-items-center"><div class="col-12 col-lg-4 student-name"><strong class="d-block"><span class="student-number"><?= $no+1 ?></span>. <?= sanitize($siswa['nama_lengkap']) ?></strong><small class="text-muted">NIS <?= sanitize($siswa['nis']?:'-') ?> · NISN <?= sanitize($siswa['nisn']?:'-') ?></small></div><div class="col-12 col-lg-5"><div class="status-options" role="radiogroup" aria-label="Status <?= sanitize($siswa['nama_lengkap']) ?>"><?php foreach(['Hadir'=>'hadir','Sakit'=>'sakit','Izin'=>'izin','Alpa'=>'alpa'] as $status=>$kelasStatus): $radioId='status'.$siswa['id'].$status; ?><div class="status-option status-<?= $kelasStatus ?>"><input type="radio" name="status[<?= (int)$siswa['id'] ?>]" id="<?= $radioId ?>" value="<?= $status ?>" class="attendance-status" <?= $status==='Hadir'?'checked':'' ?> required><label for="<?= $radioId ?>"><?= $status ?></label></div><?php endforeach; ?><button type="button" class="reset-student" title="Reset status siswa" aria-label="Reset status <?= sanitize($siswa['nama_lengkap']) ?>"><i class="fa-solid fa-rotate-left"></i>Reset</button></div></div><div class="col-12 col-lg-3"><input type="text" name="keterangan[<?= (int)$siswa['id'] ?>]" class="form-control student-note" maxlength="255" placeholder="Keterangan (opsional)"></div></div></div>
<?php endforeach; ?></div>
<div class="student-empty-filter d-none" id="siswaTidakDitemukan"><i class="fa-solid fa-user-slash fa-2x mb-2"></i><p class="mb-0">Siswa tidak ditemukan.</p></div>
<nav class="student-pagination" aria-label="Halaman daftar siswa"><button type="button" class="btn btn-outline-primary" id="halamanSebelumnya" aria-label="Halaman sebelumnya"><i class="fa-solid fa-chevron-left"></i></button><span class="student-page-info" id="infoHalaman">Halaman 1 / 1</span><button type="button" class="btn btn-outline-primary" id="halamanBerikutnya" aria-label="Halaman berikutnya"><i class="fa-solid fa-chevron-right"></i></button></nav>
<?php endif; ?>
</div></section>
<?php if($siswa_list): ?><div class="manual-summary p-3 d-flex flex-wrap justify-content-between align-items-center gap-2"><div class="small"><span class="badge bg-success me-1">Hadir <b id="countHadir">0</b></span><span class="badge bg-secondary me-1">Sakit <b id="countSakit">0</b></span><span class="badge bg-primary me-1">Izin <b id="countIzin">0</b></span><span class="badge bg-danger">Alpa <b id="countAlpa">0</b></span></div><button class="btn btn-success fw-semibold" id="simpanAbsensi"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Absensi Kelas</button></div><?php endif; ?>
</form>
<?php elseif(!$pengajaran_list): ?><div class="alert alert-warning">Admin belum memberikan pengajaran kepada Anda.</div><?php endif; ?>
</main></div>
<script>
document.getElementById('pilihPengajaranManual')?.addEventListener('change',function(){
    if(!this.value)return;
    const button=document.getElementById('tombolTampilkanSiswa');
    button.disabled=true;
    button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Memuat...';
    document.getElementById('statusMuatSiswa').textContent='Memuat daftar siswa kelas...';
    document.getElementById('formPilihPengajaran').submit();
});
const studentRows=Array.from(document.querySelectorAll('.student-attendance-row'));
let studentPage=1;
function renderStudentPage(){
    const search=(document.getElementById('cariSiswa')?.value||'').trim().toLocaleLowerCase('id');
    const perPage=parseInt(document.getElementById('jumlahPerHalaman')?.value||'15',10);
    const filtered=studentRows.filter(row=>(row.dataset.search||'').includes(search));
    const totalPages=Math.max(1,Math.ceil(filtered.length/perPage));
    studentPage=Math.min(Math.max(1,studentPage),totalPages);
    const start=(studentPage-1)*perPage;
    const visible=new Set(filtered.slice(start,start+perPage));
    studentRows.forEach(row=>row.classList.toggle('d-none',!visible.has(row)));
    filtered.forEach((row,index)=>{const number=row.querySelector('.student-number');if(number)number.textContent=index+1});
    const result=document.getElementById('hasilPencarian');
    const info=document.getElementById('infoHalaman');
    const empty=document.getElementById('siswaTidakDitemukan');
    const pagination=document.querySelector('.student-pagination');
    if(result)result.textContent=filtered.length+' siswa';
    if(info)info.textContent='Halaman '+studentPage+' / '+totalPages;
    if(empty)empty.classList.toggle('d-none',filtered.length!==0);
    if(pagination)pagination.classList.toggle('d-none',filtered.length===0);
    const previous=document.getElementById('halamanSebelumnya');
    const next=document.getElementById('halamanBerikutnya');
    if(previous)previous.disabled=studentPage<=1;
    if(next)next.disabled=studentPage>=totalPages;
}
document.getElementById('cariSiswa')?.addEventListener('input',()=>{studentPage=1;renderStudentPage()});
document.getElementById('jumlahPerHalaman')?.addEventListener('change',()=>{studentPage=1;renderStudentPage()});
document.getElementById('halamanSebelumnya')?.addEventListener('click',()=>{studentPage--;renderStudentPage();document.getElementById('daftarSiswaManual')?.scrollIntoView({behavior:'smooth',block:'start'})});
document.getElementById('halamanBerikutnya')?.addEventListener('click',()=>{studentPage++;renderStudentPage();document.getElementById('daftarSiswaManual')?.scrollIntoView({behavior:'smooth',block:'start'})});
function hitungStatus(){const counts={Hadir:0,Sakit:0,Izin:0,Alpa:0};document.querySelectorAll('.attendance-status:checked').forEach(el=>counts[el.value]++);Object.keys(counts).forEach(status=>{const el=document.getElementById('count'+status);if(el)el.textContent=counts[status]})}
document.querySelectorAll('.attendance-status').forEach(el=>el.addEventListener('change',hitungStatus));
document.querySelectorAll('.set-all').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('.attendance-status[value="'+button.dataset.status+'"]').forEach(el=>el.checked=true);hitungStatus()}));
document.getElementById('resetAbsensi')?.addEventListener('click',function(){
    if(!confirm('Reset seluruh pilihan status dan keterangan?'))return;
    document.querySelectorAll('.attendance-status[value="Hadir"]').forEach(el=>el.checked=true);
    document.querySelectorAll('input[name^="keterangan["]').forEach(el=>el.value='');
    hitungStatus();
});
document.querySelectorAll('.reset-student').forEach(button=>button.addEventListener('click',function(){
    const row=this.closest('.student-attendance-row');
    const hadir=row?.querySelector('.attendance-status[value="Hadir"]');
    const note=row?.querySelector('.student-note');
    if(hadir)hadir.checked=true;
    if(note)note.value='';
    hitungStatus();
}));
document.getElementById('formAbsensiManualKelas')?.addEventListener('submit',function(event){if(!confirm('Simpan absensi seluruh kelas? Data akan langsung masuk ke rekap.'))event.preventDefault();else document.getElementById('simpanAbsensi').disabled=true});
hitungStatus();
renderStudentPage();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
