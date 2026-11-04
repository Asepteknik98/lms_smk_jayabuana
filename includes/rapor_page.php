<?php
require_once __DIR__.'/../config/auth.php';
check_access([1,2]);
require_once __DIR__.'/rapor.php';
require_once __DIR__.'/rapor_ekstrakurikuler.php';
require_once __DIR__.'/rapor_batch.php';
require_once __DIR__.'/rapor_tools.php';
require_once __DIR__.'/rapor_ranking.php';
$db=Database::getInstance();$ready=rapor_ready($db);$error='';$posted=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $posted=$_POST;
    if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token'])){http_response_code(403);$error='Sesi formulir tidak valid. Buka ulang halaman.';}
    elseif(!$ready)$error='Penyimpanan rapor belum tersedia.';
    else{
        try{
            if(isset($_POST['batch_action'])){
                $result=rapor_batch($db,$_POST,(int)$_SESSION['user_id']);
                $_SESSION['rapor_success']=$result['created'].' draf dibuat, '.$result['skipped'].' rapor lama dipertahankan, '.$result['updated'].' rapor diperbarui.';
                $nextSteps=['prepare'=>'attendance','attendance'=>'extras','extras'=>'print'];
                $requestedStep=is_string($_POST['wizard_step']??null)?$_POST['wizard_step']:'';
                $nextStep=in_array($requestedStep,['class','prepare','attendance','extras','print'],true)?$requestedStep:$nextSteps[$_POST['batch_action']];
                header('Location: rapor.php?'.http_build_query(array_merge(array_intersect_key($_POST,array_flip(['kelas_id','tahun_ajaran','semester','jenis'])),['step'=>$nextStep])));exit;
            }
            if(($_POST['form_complete']??'')!=='1')throw new RuntimeException('Data formulir rapor tidak lengkap. Muat ulang halaman sebelum menyimpan.');
            $saved=rapor_save($db,$_POST,(int)$_SESSION['user_id']);$_SESSION['rapor_success']='Rapor berhasil disimpan.';header('Location: rapor.php?id='.$saved);exit;
        }
        catch(RuntimeException $e){$error=$e instanceof PDOException?'Rapor gagal disimpan. Periksa data atau kemungkinan rapor periode ini sudah tersedia.':$e->getMessage();if($e instanceof PDOException)error_log('Simpan rapor: '.$e->getMessage());}
        catch(Throwable $e){error_log('Simpan rapor: '.$e->getMessage());$error='Rapor gagal disimpan. Silakan coba kembali.';}
    }
}
$batchAction=is_string($posted['batch_action']??null)?$posted['batch_action']:'';
$input=$posted??$_GET;
$getInt=static fn($key)=>(isset($input[$key])&&is_scalar($input[$key])&&ctype_digit((string)$input[$key]))?(int)$input[$key]:0;
$id=$getInt('id');$report=$ready&&$id?rapor_load($db,$id):null;
if($id&&!$report&&!$error){http_response_code(404);$error='Rapor tidak ditemukan.';}
$periods=$db->query('SELECT DISTINCT tahun_ajaran,semester FROM pengajaran ORDER BY tahun_ajaran DESC,semester')->fetchAll();
$classes=$db->query('SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();
$kelas=$report?(int)$report['kelas_id']:$getInt('kelas_id');
$tahun=$report['tahun_ajaran']??(is_string($input['tahun_ajaran']??null)?$input['tahun_ajaran']:($periods[0]['tahun_ajaran']??''));
$semester=$report['semester']??(is_string($input['semester']??null)&&in_array($input['semester'],['Ganjil','Genap'],true)?$input['semester']:($periods[0]['semester']??'Ganjil'));
$jenis=$report['jenis']??(($input['jenis']??'')==='UAS'?'UAS':'UTS');
$sid=$report?(int)$report['siswa_id']:$getInt('siswa_id');
$adminPeriod=$kelas?rapor_admin_load($db,$kelas,$tahun,$semester,$jenis):null;$periodLocked=!empty($adminPeriod['terkunci']);
$q=$db->prepare('SELECT id,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');$q->execute([$kelas]);$students=$q->fetchAll();
$q=$db->prepare('SELECT s.nama_lengkap nama_siswa,s.nis,s.nisn,k.nama_kelas,k.jurusan FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.kelas_id=?');$q->execute([$sid,$kelas]);$identity=$report?:$q->fetch();
$sources=[];
if($ready&&$identity){
    try{$sources=rapor_sources($db,$kelas,$sid,$tahun,$semester,$jenis);}
    catch(Throwable $e){error_log('Sumber rapor: '.$e->getMessage());$error=$error?:'Sumber nilai belum dapat dimuat. Periksa kelengkapan tabel rekap nilai.';}
}
$savedList=[];
if($ready&&$kelas){$q=$db->prepare("SELECT r.*, (SELECT COUNT(*) FROM rapor_mapel m WHERE m.rapor_id=r.id) jumlah_mapel, (SELECT COUNT(*) FROM rapor_mapel m WHERE m.rapor_id=r.id AND (m.nilai_akhir IS NULL OR TRIM(COALESCE(m.deskripsi,''))='' OR TRIM(COALESCE(m.saran,''))='')) mapel_belum_lengkap FROM rapor r WHERE kelas_id=? AND tahun_ajaran=? AND semester=? AND jenis=? ORDER BY nomor_absen IS NULL,nomor_absen,nama_siswa,id");$q->execute([$kelas,$tahun,$semester,$jenis]);$savedList=$q->fetchAll();}
$savedByStudent=[];$completeCount=0;
foreach($savedList as $saved){
    $saved['lengkap']=(int)$saved['jumlah_mapel']>0&&(int)$saved['mapel_belum_lengkap']===0;
    foreach(['nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'] as $field)if(trim((string)($saved[$field]??''))==='')$saved['lengkap']=false;
    $savedByStudent[(int)$saved['siswa_id']]=$saved;
}
foreach($students as $student)if(!empty($savedByStudent[(int)$student['id']]['lengkap']))$completeCount++;
// Opening an existing period always uses its saved record instead of creating a duplicate.
if(!$posted&&!$id&&$ready&&$identity){$q=$db->prepare('SELECT id FROM rapor WHERE siswa_id=? AND tahun_ajaran=? AND semester=? AND jenis=?');$q->execute([$sid,$tahun,$semester,$jenis]);if($existing=$q->fetchColumn()){header('Location: rapor.php?id='.(int)$existing);exit;}}
$studentRanking=$report&&$kelas?rapor_ranking_for_report(rapor_ranking_load($db,$kelas,$tahun,$semester,$jenis),(int)$report['id']):null;
$view=$report??[];
if(!$report&&$kelas){$adminDefaults=rapor_admin_load($db,$kelas,$tahun,$semester,$jenis)??[];foreach(['nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'] as $key)if(isset($adminDefaults[$key]))$view[$key]=$adminDefaults[$key];}
if($posted)foreach(['nomor_absen','sakit','izin','alfa','catatan_wali_kelas','nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'] as $key)if(is_string($posted[$key]??null))$view[$key]=$posted[$key];
$rows=$report['mapel']??[];$extras=$report['ekstra']??[];
if($posted&&is_array($posted['mapel']??null)){
    $oldRows=[];foreach($rows as $m)$oldRows[(int)$m['id']]=$m;$rows=[];
    foreach(array_slice($posted['mapel'],0,100) as $m){if(!is_array($m))continue;$mid=is_scalar($m['id']??0)?(int)($m['id']??0):0;$pid=is_scalar($m['pengajaran_id']??0)?(int)($m['pengajaran_id']??0):0;
        $row=isset($oldRows[$mid])&&(int)$oldRows[$mid]['pengajaran_id']===$pid&&empty($m['refresh'])?$oldRows[$mid]:($sources[$pid]??null);
        if($row){$row['id']=$mid;$row['refresh']=!empty($m['refresh']);$rows[]=$row;}
    }
}
if($posted&&is_array($posted['ekstra']??null)){$extras=[];foreach(array_slice($posted['ekstra'],0,100) as $e)if(is_array($e)&&is_string($e['nama_kegiatan']??null)&&is_string($e['grade']??null))$extras[]=$e;}
$extras=rapor_ekstra_template($extras);
$success=$_SESSION['rapor_success']??'';unset($_SESSION['rapor_success']);
require __DIR__.'/header.php';require __DIR__.'/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/rapor-form.css?v=12">
<div id="page-content-wrapper" class="rapor-workspace">
<nav class="navbar top-navbar px-4 py-3"><h5 class="fw-bold mb-0">Rapor Siswa</h5></nav>
<main class="container-fluid p-3 p-md-4 rapor-content">
<div class="rapor-intro"><div><h1><i class="fa-regular fa-file-lines rapor-title-icon" aria-hidden="true"></i> Rapor siswa</h1><p>Siapkan rapor satu kelas, periksa isian, lalu cetak.</p></div><span class="rapor-paper"><i class="fa-solid fa-print" aria-hidden="true"></i> F4 · 2 halaman</span></div>
<?php if($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if($periodLocked): ?><div class="alert alert-info" role="status" id="rapor-locked-notice"><i class="fa-solid fa-lock" aria-hidden="true"></i> Rapor kelas dan periode ini sudah dikunci admin. Anda tetap dapat memeriksa dan mencetak; hubungi admin jika perlu koreksi.</div><?php endif ?>
<?php if(!$ready): ?>
<div class="alert alert-warning">Penyimpanan rapor belum tersedia. Hubungi administrator untuk menyiapkan fitur ini.</div>
<?php else: ?>
<div class="rapor-quick-actions"><a class="btn btn-outline-secondary btn-sm me-2" href="rapor_pengaturan.php?<?= sanitize(http_build_query(['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis])) ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Atur judul rapor</a><a href="rapor.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Ganti kelas / periode</a></div>

<?php if(!$identity): $wizardStep=$posted?(['prepare'=>'prepare','attendance'=>'attendance','extras'=>'extras'][$batchAction]??'class'):(is_string($_GET['step']??null)?$_GET['step']:($kelas?'prepare':'class')); ?>
<div id="rapor-wizard" data-step="<?= sanitize($wizardStep) ?>" data-locked="<?= $periodLocked?'1':'0' ?>" data-has-class="<?= $kelas?'1':'0' ?>" data-has-reports="<?= array_filter($students,static fn($s)=>isset($savedByStudent[(int)$s['id']]))?'1':'0' ?>" data-needs-preparation="<?= count(array_filter($students,static fn($s)=>!isset($savedByStudent[(int)$s['id']])))?'1':'0' ?>" data-has-students="<?= $students?'1':'0' ?>"></div>
<?php endif ?>
<section class="card mb-4 rapor-picker"><div class="card-body"><h6><span class="rapor-step-number"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></span> Pilih kelas dan periode</h6><p class="rapor-help">Mulai dengan memilih periode dan kelas yang akan dibuatkan rapor.</p>
<form method="get" class="row g-3" id="rapor-picker-form">
<div class="col-md-3"><label for="tahun" class="form-label">Tahun ajaran</label><select id="tahun" name="tahun_ajaran" class="form-select" required>
<?php $years=array_unique(array_merge(array_column($periods,'tahun_ajaran'),$tahun?[$tahun]:[]));foreach($years as $year): ?><option <?= $year===$tahun?'selected':'' ?>><?= sanitize($year) ?></option><?php endforeach ?></select></div>
<div class="col-md-2"><label for="semester" class="form-label">Semester</label><select id="semester" name="semester" class="form-select"><?php foreach(['Ganjil','Genap'] as $v): ?><option <?= $semester===$v?'selected':'' ?>><?= $v ?></option><?php endforeach ?></select></div>
<div class="col-md-2"><label for="jenis" class="form-label">Jenis rapor</label><select id="jenis" name="jenis" class="form-select"><?php foreach(['UTS','UAS'] as $v): ?><option <?= $jenis===$v?'selected':'' ?>><?= $v ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label for="kelas" class="form-label">Kelas</label><select id="kelas" name="kelas_id" class="form-select" required><option value="">Pilih kelas</option><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$kelas?'selected':'' ?>><?= sanitize($c['nama_kelas']) ?></option><?php endforeach ?></select></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100"><i class="fa-solid fa-filter" aria-hidden="true"></i> Tampilkan siswa</button></div>
<details class="col-12 rapor-individual-options"><summary><i class="fa-regular fa-user" aria-hidden="true"></i><span>Buka rapor per siswa<small>Untuk memeriksa atau memperbaiki satu rapor</small></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="row g-3"><div class="col-md-10 rapor-student-picker"><label for="siswa" class="form-label">Siswa</label><select id="siswa" name="siswa_id" class="form-select" <?= !$students?'disabled':'' ?>><option value=""><?= !$kelas?'Pilih kelas terlebih dahulu':(!$students?'Belum ada siswa di kelas ini':'Pilih siswa') ?></option><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']===$sid?'selected':'' ?>><?= sanitize($s['nama_lengkap']) ?></option><?php endforeach ?></select></div>
<div class="col-md-2 d-flex align-items-end"><button id="open-rapor" class="btn btn-primary w-100" <?= !$sid||!$identity?'disabled':'' ?>><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Buka rapor</button></div>
</div></details>
</form></div></section>
<?php if($kelas): ?>
<?php require __DIR__.'/rapor_batch_view.php'; ?>
<section class="card mb-4 rapor-progress" id="rapor-progress" data-rapor-filter><div class="card-body"><h6>Ringkasan rapor kelas</h6>
<div class="rapor-stats"><div><strong><?= count($students) ?></strong><span>Jumlah siswa</span></div><div><strong><?= count(array_filter($students,static fn($s)=>isset($savedByStudent[(int)$s['id']]))) ?></strong><span>Rapor tersimpan</span></div><div><strong><?= $completeCount ?></strong><span>Isian lengkap</span></div></div>
<p class="small text-muted">Lengkap berarti ada mata pelajaran, seluruh nilai/deskripsi/saran terisi, serta nama penanda tangan, tempat, dan tanggal penerbitan terisi. Periksa juga kesesuaian mata pelajaran sebelum mencetak. Status ini bukan persetujuan sekolah.</p>
<?php rapor_filter_toolbar('rapor-progress-search','Cari siswa'); ?>
<?php if(!$students): ?><p role="status">Belum ada siswa di kelas ini.</p><?php else: ?>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Siswa</th><th>Status rapor</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($students as $student): $saved=$savedByStudent[(int)$student['id']]??null; ?>
<tr data-filter-row data-filter-search-value="<?= sanitize($student['nama_lengkap']) ?>" data-incomplete="<?= !$saved||!$saved['lengkap']?'1':'0' ?>"><td><?= sanitize($student['nama_lengkap']) ?></td><td><span class="badge <?= !$saved?'bg-secondary':($saved['lengkap']?'bg-success':'bg-warning text-dark') ?>"><?= !$saved?'Belum diisi':($saved['lengkap']?'Lengkap':'Draf belum lengkap') ?></span><?php if($saved&&!$saved['lengkap']): $missing=[];if(!(int)$saved['jumlah_mapel'])$missing[]='Belum ada mapel';if((int)$saved['mapel_belum_lengkap'])$missing[]=$saved['mapel_belum_lengkap'].' mapel belum lengkap';foreach(['nama_wali_kelas'=>'nama wali kelas','nama_kepala_sekolah'=>'nama kepala sekolah','tempat_terbit'=>'tempat penerbitan','tanggal_terbit'=>'tanggal penerbitan'] as $field=>$label)if(trim((string)($saved[$field]??''))==='')$missing[]=$label; ?><small class="d-block text-muted mt-1"><?= sanitize(implode(', ',$missing)) ?></small><?php endif ?></td><td><a class="btn btn-outline-primary btn-sm" href="rapor.php?<?= sanitize(http_build_query($saved?['id'=>(int)$saved['id']]:['kelas_id'=>$kelas,'siswa_id'=>(int)$student['id'],'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis])) ?>"><?= !$saved?'Isi rapor':($saved['lengkap']?'Periksa rapor':'Lanjutkan') ?></a></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?></div></section>
<?php endif ?>
<?php if($identity): ?>
<?php $currentSaved=$savedByStudent[$sid]??null; ?>
<div class="alert <?= !empty($currentSaved['lengkap'])?'alert-success':'alert-info' ?>" role="status"><strong><?= !empty($currentSaved['lengkap'])?'Isian rapor tersimpan lengkap.':($report?'Draf rapor tersimpan.':'Rapor baru belum disimpan.') ?></strong> Periksa nilai dan pelengkap, simpan draf, lalu buka pratinjau sebelum mencetak. Kelengkapan dihitung dari data yang sudah disimpan.</div>
<form method="post" id="rapor-form">
<input type="hidden" name="details_ready" id="details-ready" value="0">
<noscript><div class="alert alert-warning">Aktifkan JavaScript untuk mengisi dan menyimpan rapor.</div></noscript>
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
<?php foreach(['id'=>$report['id']??0,'version'=>$posted&&is_string($posted['version']??null)?$posted['version']:($report?rapor_version($report):''),'kelas_id'=>$kelas,'siswa_id'=>$sid,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis] as $key=>$v): ?><input type="hidden" name="<?= $key ?>" value="<?= sanitize((string)$v) ?>"><?php endforeach ?>
<section class="card mb-4 rapor-profile"><div class="card-body"><span class="rapor-profile-icon"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></span><div><h5><?= sanitize($identity['nama_siswa']) ?></h5><p class="text-muted mb-0"><?= sanitize($identity['nama_kelas'].' · '.$tahun.' · '.$semester.' · '.$jenis) ?><br>NIS <?= sanitize($identity['nis']??'') ?> · NISN <?= sanitize($identity['nisn']??'') ?></p><?php if($studentRanking): ?><p class="rapor-student-ranking"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> <?= $studentRanking['peringkat']!==null?'Peringkat '.(int)$studentRanking['peringkat'].' dari '.(int)$studentRanking['peserta'].' siswa':'Peringkat belum tersedia' ?><?= $studentRanking['sementara']?' ? sementara':'' ?> <a href="rapor_peringkat.php?<?= sanitize(http_build_query(['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis])) ?>">Lihat laporan kelas</a></p><?php endif ?></div></div></section>
<section class="card mb-4"><div class="card-body"><h6><span class="rapor-step-number"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span> Nilai mata pelajaran</h6>
<p class="small text-muted">Pilih mapel dan guru, lalu klik Tambahkan. Hanya pilihan yang disimpan yang tampil di rapor, sesuai urutan penambahan. Nilai, deskripsi, dan saran terisi dari rekap.</p>
<div class="row g-2 mb-3"><div class="col-md-5"><label class="form-label" for="mapel-picker">Mata pelajaran</label><select class="form-select" id="mapel-picker"><option value="">Pilih mata pelajaran</option></select></div><div class="col-md-5"><label class="form-label" for="guru-picker">Guru pengajar</label><select class="form-select" id="guru-picker"><option value="">Pilih guru</option></select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100" type="button" id="add-mapel">Tambahkan</button></div></div>
<p id="mapel-message" class="small text-danger" role="status"></p>
<div class="table-responsive rapor-grades"><table class="table align-middle"><thead><tr><th>Mapel / Guru</th><th>Nilai akhir</th><th>Deskripsi capaian</th><th>Saran</th><th>Tindakan</th></tr></thead><tbody id="mapel-rows"></tbody></table></div><p id="mapel-empty" class="rapor-empty" hidden>Belum ada mata pelajaran. Pilih mata pelajaran dan guru di atas, lalu klik Tambahkan.</p>
<?php if(!$sources): ?><p class="text-warning">Tidak ada sumber pengajaran yang cocok untuk siswa dan periode ini. Data rapor tersimpan tetap dapat dipertahankan.</p><?php endif ?>
</div></section>
<details class="card mb-4 rapor-fold" id="rapor-ekstra-section"><summary><span class="rapor-fold-title"><span class="rapor-step-number"><i class="fa-solid fa-medal" aria-hidden="true"></i></span><span>Ekstrakurikuler<small>23 kegiatan · grade A–D</small></span></span><span class="rapor-badge" id="ekstra-count" aria-live="polite">Pilih grade</span><i class="fa-solid fa-chevron-down rapor-chevron" aria-hidden="true"></i></summary><div class="card-body"><p class="small text-muted">Isi kegiatan yang diikuti. Pilihan “–” dicetak sebagai tanda strip.</p><div id="ekstra-rows" class="rapor-ekstra-grid"></div><button class="btn btn-outline-primary btn-sm mt-3" type="button" id="add-ekstra"><i class="fa-solid fa-plus" aria-hidden="true"></i> Kegiatan lain</button></div></details>
<details class="card mb-4 rapor-fold" id="rapor-attendance-section"><summary><span class="rapor-fold-title"><span class="rapor-step-number"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span><span>Kehadiran & pelengkap<small>Absensi, catatan, dan penanda tangan</small></span></span><i class="fa-solid fa-chevron-down rapor-chevron" aria-hidden="true"></i></summary><div class="card-body"><div class="row g-3">
<?php foreach(['nomor_absen'=>'Nomor absen (opsional)','sakit'=>'Sakit (hari)','izin'=>'Izin (hari)','alfa'=>'Alfa (hari)'] as $key=>$label): ?><div class="col-sm-6 col-lg-3"><label class="form-label" for="<?= $key ?>"><?= $label ?></label><input id="<?= $key ?>" name="<?= $key ?>" class="form-control" type="number" min="0" max="65535" step="1" value="<?= sanitize((string)($view[$key]??($key==='nomor_absen'?'':0))) ?>" <?= $key!=='nomor_absen'?'required':'' ?>></div><?php endforeach ?>
<div class="col-12"><label class="form-label" for="catatan">Catatan wali kelas</label><textarea id="catatan" name="catatan_wali_kelas" class="form-control" rows="3" maxlength="4000"><?= sanitize($view['catatan_wali_kelas']??'') ?></textarea></div>
<?php foreach(['nama_wali_kelas'=>'Nama wali kelas','nama_kepala_sekolah'=>'Nama kepala sekolah','tempat_terbit'=>'Tempat penerbitan'] as $key=>$label): ?><div class="col-md-4"><label class="form-label" for="<?= $key ?>"><?= $label ?></label><input class="form-control" id="<?= $key ?>" name="<?= $key ?>" maxlength="100" value="<?= sanitize($view[$key]??'') ?>"></div><?php endforeach ?>
<div class="col-md-4"><label class="form-label" for="tanggal">Tanggal penerbitan</label><input class="form-control" type="date" id="tanggal" name="tanggal_terbit" value="<?= sanitize($view['tanggal_terbit']??'') ?>"></div>
</div></div></details>
<input type="hidden" name="form_complete" value="1">
<div class="rapor-savebar"><div><strong><i class="fa-regular fa-circle-check" aria-hidden="true"></i> Simpan & cetak</strong><small id="rapor-save-status" role="status">Cetak memakai data tersimpan.</small></div><div class="rapor-save-actions"><button class="btn btn-primary" type="submit"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Simpan draf rapor</button><?php if($report): ?><a class="btn btn-outline-secondary" href="rapor_cetak.php?id=<?= (int)$report['id'] ?>"><i class="fa-solid fa-print" aria-hidden="true"></i> Pratinjau & cetak</a><?php endif ?></div></div>
</form>
<script type="application/json" id="rapor-data"><?= json_encode(['sources'=>array_values($sources),'rows'=>$rows,'extras'=>$extras],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="../assets/js/rapor.js?v=4" defer></script>
<?php endif ?>
<?php if($savedList): ?><details class="card rapor-fold rapor-history"><summary>Riwayat rapor tersimpan <i class="fa-solid fa-chevron-down rapor-chevron" aria-hidden="true"></i></summary><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>Absen</th><th>Siswa</th><th>Terakhir disimpan</th><th></th></tr></thead><tbody><?php foreach($savedList as $r): ?><tr><td><?= sanitize((string)($r['nomor_absen']??'—')) ?></td><td><?= sanitize($r['nama_siswa']) ?></td><td><?= sanitize($r['updated_at']) ?></td><td><a class="btn btn-outline-primary btn-sm" href="rapor.php?id=<?= (int)$r['id'] ?>">Buka / edit</a></td></tr><?php endforeach ?></tbody></table></div></div></details><?php endif ?>
<?php if($kelas||$report): $historyReportId=(int)($report['id']??0);require __DIR__.'/rapor_history_view.php';endif ?>
<?php endif ?>
</main></div>
<script src="../assets/js/rapor-picker.js?v=1" defer></script>
<script src="../assets/js/rapor-extra-matrix.js?v=3" defer></script>
<script src="../assets/js/rapor-batch.js?v=3" defer></script>
<script src="../assets/js/rapor-lock.js?v=2" defer></script>
<script src="../assets/js/rapor-table-tools.js?v=1" defer></script>
<script src="../assets/js/rapor-wizard.js?v=4" defer></script>
<?php require __DIR__.'/footer.php'; ?>
