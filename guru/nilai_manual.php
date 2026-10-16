<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/nilai_manual_target.php';
check_access([2]);$db=Database::getInstance();
$q=$db->prepare('SELECT id FROM guru WHERE user_id=?');$q->execute([$_SESSION['user_id']]);$guru=(int)$q->fetchColumn();
if(!$guru){http_response_code(403);exit('Data guru tidak ditemukan.');}
$q=$db->prepare('SELECT p.id,p.semester,p.tahun_ajaran,k.nama_kelas,m.nama_mapel FROM pengajaran p JOIN kelas k ON k.id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id WHERE p.guru_id=? ORDER BY p.tahun_ajaran DESC,k.nama_kelas,m.nama_mapel');$q->execute([$guru]);$assignments=$q->fetchAll();
$input=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET;
$pid=(int)($input['pengajaran_id']??($assignments[0]['id']??0));$kind=is_string($input['jenis']??null)&&in_array($input['jenis'],['tugas','ujian','uts','uas','praktik'],true)?$input['jenis']:'tugas';$componentName=nm_component_name($kind);$id=(int)($input['target_id']??0);
$context=null;$target=null;$error='';$preview=null;
try{
 if($pid)$context=nm_context($db,$guru,$pid,$kind);
 if($id&&$context)$target=nm_target($context,$id);
}catch(Throwable $e){http_response_code(403);exit('Kelas atau target penilaian tidak dapat diakses.');}
if($context&&!$target&&$_SERVER['REQUEST_METHOD']==='GET'){
 $candidates=$context['targets'];
 foreach($candidates as $candidate){
  $target=$candidate;
  if(array_filter($context['students'],static fn($student)=>!isset($context['scores'][$student['id']][$candidate['id']]['nilai'])))break;
 }
 if($target)$id=(int)$target['id'];
}
if(!isset($_SESSION['manual_create_token']))$_SESSION['manual_create_token']=bin2hex(random_bytes(24));
$url='nilai_manual.php?'.http_build_query(['pengajaran_id'=>$pid,'jenis'=>$kind,'target_id'=>$id]);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
  if(!$context)throw new RuntimeException('Pilih kelas terlebih dahulu.');
  $action=$_POST['action']??'';
  if($action==='create_target'){
   foreach(['judul_manual','tanggal_manual','create_token'] as $key)if(!is_string($_POST[$key]??null))throw new RuntimeException('Format target manual tidak valid.');
   if(!hash_equals($_SESSION['manual_create_token'],$_POST['create_token']))throw new RuntimeException('Target sudah diproses. Muat ulang halaman.');
   $newMeeting=0; // Tugas manual mandiri, tidak terikat materi.
   $created=nm_create_target($db,$guru,(int)$_SESSION['user_id'],$pid,$kind,$_POST['judul_manual'],$newMeeting,$_POST['tanggal_manual']);
   $_SESSION['manual_create_token']=bin2hex(random_bytes(24));
   $_SESSION['manual_target_success']='Target manual dibuat. Silakan isi atau impor nilai siswa.';
   header('Location: nilai_manual.php?'.http_build_query(['pengajaran_id'=>$pid,'jenis'=>$kind,'target_id'=>$created]));exit;
  }
  if(!$target)throw new RuntimeException('Pilih atau buat target penilaian terlebih dahulu.');
  if($action==='template'){
   $className=trim(preg_replace('/[^A-Za-z0-9_-]+/','_',(string)$context['info']['nama_kelas']),'_');
   if($className==='')$className=(string)$pid;
   $filename='Template_Manual_'.$kind.'_'.$target['nomor'].'_Kelas_'.$className.'_ID_'.$pid.'.xls';
   $xml=nm_template($context,$id);header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Cache-Control: no-store');echo $xml;exit;
  }elseif($action==='preview'){
   unset($_SESSION['manual_target_import']);$file=$_FILES['excel']??[];
   if(($file['error']??null)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??0)>5*1024*1024||!in_array(strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION)),['xlsx','xls'],true))throw new RuntimeException('Unggah file Excel .xlsx atau .xls, maksimal 5 MB.');
   $book=ni_read_excel($file['tmp_name']);$rows=nm_preview($context,$id,$book['rows']);
   $_SESSION['manual_target_import']=['user'=>(int)$_SESSION['user_id'],'guru'=>$guru,'pid'=>$pid,'kind'=>$kind,'id'=>$id,'expires'=>time()+900,'token'=>bin2hex(random_bytes(24)),'rows'=>$rows];
   header('Location: '.$url.'#impor-manual');exit;
  }elseif($action==='confirm'){
   $plan=$_SESSION['manual_target_import']??[];
   if(($plan['user']??0)!==(int)$_SESSION['user_id']||($plan['guru']??0)!==$guru||($plan['pid']??0)!==$pid||($plan['kind']??'')!==$kind||($plan['id']??0)!==$id||($plan['expires']??0)<time()||!is_string($_POST['import_token']??null)||!hash_equals($plan['token']??'',$_POST['import_token']))throw new RuntimeException('Pratinjau tidak sesuai target atau sudah kedaluwarsa. Unggah ulang file.');
   $values=[];foreach($plan['rows'] as $row)if($row['ready'])$values[$row['sid']]=(string)$row['score'];
   $result=nm_save($db,$guru,(int)$_SESSION['user_id'],$pid,$kind,$id,$values);unset($_SESSION['manual_target_import']);
   $_SESSION['manual_target_success']=$result['saved'].' nilai baru diimpor. Nilai yang sudah terisi atau berubah tetap dipertahankan.';header('Location: '.$url);exit;
  }elseif($action==='save'){
   if(!is_array($_POST['nilai']??null)||!is_array($_POST['catatan']??[]))throw new RuntimeException('Format nilai tidak valid.');
   $result=nm_save($db,$guru,(int)$_SESSION['user_id'],$pid,$kind,$id,$_POST['nilai'],$_POST['catatan']??[]);
   $_SESSION['manual_target_success']=$result['saved'].' nilai baru disimpan; '.$result['skipped'].' nilai lama dilewati.';header('Location: '.$url);exit;
  }else throw new RuntimeException('Aksi tidak valid.');
 }catch(Throwable $e){error_log('Nilai manual target: '.$e->getMessage());$error=$e instanceof PDOException?'Nilai gagal disimpan. Tidak ada perubahan yang diterapkan.':$e->getMessage();}
}
$plan=$_SESSION['manual_target_import']??null;
if($plan&&($plan['user']===(int)$_SESSION['user_id']&&$plan['guru']===$guru&&$plan['pid']===$pid&&$plan['kind']===$kind&&$plan['id']===$id&&$plan['expires']>=time()))$preview=$plan;
$success=$_SESSION['manual_target_success']??'';unset($_SESSION['manual_target_success']);
$hidden=static function()use($pid,$kind,$id):void{
 foreach(['csrf_token'=>$_SESSION['csrf_token'],'pengajaran_id'=>$pid,'jenis'=>$kind,'target_id'=>$id] as $name=>$value)echo '<input type="hidden" name="'.sanitize($name).'" value="'.sanitize((string)$value).'">';
};
$targets=$context['targets']??[];$filtered=$targets;
$legacy=[];if($context){$q=$db->prepare('SELECT n.*,s.nama_lengkap FROM nilai_tugas_manual n JOIN siswa s ON s.id=n.siswa_id AND s.kelas_id=? WHERE n.pengajaran_id=? ORDER BY n.pertemuan_ke,s.nama_lengkap');$q->execute([$context['info']['kelas_id'],$pid]);$legacy=$q->fetchAll();}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/nilai-manual.css?v=2">
<link rel="stylesheet" href="../assets/css/nilai-manual-guru.css?v=1">
<div id="page-content-wrapper" class="nm-page manual-grade-page"><nav class="navbar top-navbar px-4 py-3"><div><h1 class="h5 fw-bold mb-1"><span class="manual-title-icon"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></span>Input Nilai Manual</h1><small class="text-muted">Nilai per tugas dan ujian harian, terhubung ke rekap kelas.</small></div><a class="btn btn-outline-primary btn-sm" href="rekap_nilai_guru.php?pengajaran_id=<?= $pid ?>"><i class="fa-solid fa-chart-column me-2" aria-hidden="true"></i>Rekap Nilai</a></nav>
<main class="nm-content p-3 p-md-4">
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<section class="nm-card manual-filter p-4 mb-4"><div class="manual-section-heading"><span class="manual-section-icon tone-purple"><i class="fa-solid fa-filter" aria-hidden="true"></i></span><div><h2>Pilih penilaian</h2><p>Tentukan kelas dan tugas atau ujian yang akan dinilai.</p></div></div><form method="get" class="row g-3">
<div class="col-md-8"><label for="nmKelas" class="form-label fw-semibold"><i class="fa-solid fa-book-open me-2" aria-hidden="true"></i>Mata pelajaran dan kelas</label><select name="pengajaran_id" id="nmKelas" class="form-select" onchange="this.form.target_id.value='';this.form.submit()"><option value="">Pilih kelas</option><?php foreach($assignments as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id']===$pid?'selected':'' ?>><?= sanitize($a['nama_mapel'].' - '.$a['nama_kelas'].' ('.$a['semester'].', '.$a['tahun_ajaran'].')') ?></option><?php endforeach ?></select></div>
<div class="col-md-4"><label for="nmJenis" class="form-label fw-semibold"><i class="fa-solid fa-layer-group me-2" aria-hidden="true"></i>Jenis penilaian</label><select name="jenis" id="nmJenis" class="form-select" onchange="this.form.target_id.value='';this.form.submit()"><option value="tugas" <?= $kind==='tugas'?'selected':'' ?>>Nilai Tugas</option><option value="ujian" <?= $kind==='ujian'?'selected':'' ?>>Ujian Harian</option><option value="uts" <?= $kind==='uts'?'selected':'' ?>>UTS</option><option value="uas" <?= $kind==='uas'?'selected':'' ?>>UAS</option><option value="praktik" <?= $kind==='praktik'?'selected':'' ?>>Ujian Praktik</option></select></div>
<div class="col-12"><label for="nmTarget" class="form-label fw-semibold"><i class="fa-solid fa-list-check me-2" aria-hidden="true"></i><?= sanitize($componentName??($kind==='tugas'?'Tugas':'Ujian Harian')) ?></label><select id="nmTarget" name="target_id" class="form-select" onchange="this.form.submit()"><option value="">Pilih <?= sanitize($componentName??($kind==='tugas'?'tugas':'ujian harian')) ?></option><?php foreach($filtered as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$t['id']===$id?'selected':'' ?>><?= sanitize($t['label']) ?></option><?php endforeach ?></select></div></form>
<?php if(!$filtered): ?><p class="manual-empty-hint mt-3 mb-0"><i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i><?php if($componentName!==null): ?>Komponen <?= sanitize($componentName) ?> belum tersedia. Tambahkan komponen dan atur bobotnya melalui <a href="rekap_nilai_guru.php?pengajaran_id=<?= $pid ?>">Rekap Nilai</a>.<?php else: ?>Belum ada target pada pilihan ini. Buat target manual melalui form di bawah untuk mulai menilai siswa.<?php endif ?></p><?php endif ?>
<?php if($target): ?><div class="alert alert-primary manual-target mt-3 mb-0"><i class="fa-solid fa-bullseye me-2" aria-hidden="true"></i><strong><?= sanitize($target['label']) ?></strong><?php if($kind==='tugas'&&(int)$target['pertemuan_ke']>0): ?><div class="small mt-1">Pertemuan <?= (int)$target['pertemuan_ke'] ?> &mdash; <?= sanitize(implode('; ',$context['meetings'][(int)$target['pertemuan_ke']]??['Belum ada materi'])) ?></div><?php endif ?><div class="small mt-1">Nilai tersimpan diteruskan ke <?= sanitize($componentName??($kind==='tugas'?'Rekap Tugas dan rata-rata Tugas Harian':'rata-rata Ulangan Harian')) ?>. Nilai kosong dilewati; angka 0 tetap disimpan.</div></div><?php endif ?></section>
<?php if($context&&$componentName===null): ?>
<details class="nm-card manual-create p-4 mb-4" id="buat-target-manual" <?= !$filtered?'open':'' ?>><summary class="fw-semibold text-primary"><span class="manual-section-icon tone-blue"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i></span>Buat <?= $kind==='tugas'?'Tugas':'Ujian Harian' ?> Manual Baru</summary>
<p class="small text-muted mt-3">Untuk tugas buku atau penilaian yang sudah dilaksanakan di kelas. Nomor mengikuti urutan <?= $kind==='tugas'?'tugas':'ujian harian' ?> kelas ini. Tugas tidak harus mengikuti materi. Setelah dibuat, Anda bisa langsung mengisi atau mengimpor nilai.</p>
<form method="post" class="row g-3"><?php $hidden(); ?><input type="hidden" name="action" value="create_target"><input type="hidden" name="create_token" value="<?= sanitize($_SESSION['manual_create_token']) ?>">
<div class="col-md-7"><label for="judulManual" class="form-label fw-semibold"><i class="fa-solid fa-pen me-2" aria-hidden="true"></i>Judul <?= $kind==='tugas'?'tugas':'ujian harian' ?></label><input id="judulManual" class="form-control" name="judul_manual" maxlength="200" placeholder="Contoh: <?= $kind==='tugas'?'Latihan buku halaman 20':'Ulangan harian bab 1' ?>" required></div>
<div class="col-md-5"><label for="tanggalManual" class="form-label fw-semibold"><i class="fa-solid fa-calendar-check me-2" aria-hidden="true"></i>Tanggal dan waktu penilaian</label><input id="tanggalManual" class="form-control" type="datetime-local" name="tanggal_manual" value="<?= date('Y-m-d\TH:i') ?>" max="<?= date('Y-m-d\TH:i') ?>" required><small class="text-muted">Pilih waktu kegiatan yang sudah dilaksanakan.</small></div>

<div class="col-12"><button class="btn btn-primary"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Buat Target dan Mulai Menilai</button></div></form></details>
<?php endif ?>
<?php if($context): $graded=$target?count(array_filter($context['students'],static fn($s)=>isset($context['scores'][$s['id']][$id]['nilai']))):0;$enabled=$target&&($kind==='tugas'||$target['selesai']); ?>
<section class="nm-card manual-students mb-4"><div class="manual-student-heading p-4 border-bottom d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h6 fw-bold mb-1"><span class="manual-section-icon tone-teal"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></span>Daftar nilai siswa</h2><small class="text-muted"><?= $graded ?> dari <?= count($context['students']) ?> siswa sudah dinilai. Nilai lama dikunci.</small></div><?php if($kind==='tugas'&&$target): ?><a class="btn btn-sm btn-outline-primary" href="tugas.php?rekap_pengajaran=<?= $pid ?>&amp;rekap_mode=tugas&amp;rekap_tugas_id=<?= $id ?>#rekap-tugas"><i class="fa-solid fa-list-check me-2" aria-hidden="true"></i>Lihat Rekap Tugas / Remedial</a><?php endif ?></div>
<?php if(!$target): ?><div class="alert alert-info m-3"><?php if($componentName!==null): ?>Tambahkan komponen <?= sanitize($componentName) ?> melalui Rekap Nilai untuk mulai mengisi nilai siswa.<?php else: ?>Daftar siswa sudah tersedia. Buat target manual di atas agar kolom nilai dapat diisi dan hasilnya masuk ke rekap yang tepat.<?php endif ?></div><?php elseif(!$enabled): ?><div class="alert alert-warning m-3"><?= $componentName!==null?'Total bobot harus 100% sebelum pengisian atau impor nilai. Atur bobot melalui Rekap Nilai.':'Pengisian dan impor nilai tersedia setelah jadwal ujian berakhir.' ?></div><?php endif ?>
<form method="post"><?php $hidden(); ?><input type="hidden" name="action" value="save"><div class="table-responsive manual-score-scroll" tabindex="0" role="region" aria-label="Daftar nilai siswa, geser untuk melihat seluruh kolom"><table class="table align-middle mb-0"><caption class="visually-hidden">Input nilai siswa untuk target penilaian yang dipilih</caption><thead class="table-light"><tr><th>No</th><th><i class="fa-solid fa-users me-2" aria-hidden="true"></i>Siswa</th><th><i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>Status</th><th><i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Nilai</th><?php if($kind==='tugas'): ?><th><i class="fa-solid fa-comment-dots me-2" aria-hidden="true"></i>Catatan</th><?php endif ?></tr></thead><tbody><?php foreach($context['students'] as $i=>$s):$cell=$context['scores'][$s['id']][$id]??null;$has=$cell&&$cell['nilai']!==null; ?><tr><td><?= $i+1 ?></td><td><strong><?= sanitize($s['nama_lengkap']) ?></strong><small class="d-block text-muted">NIS <?= sanitize($s['nis']??'') ?></small></td><td><span class="badge <?= $has?'bg-success':'bg-secondary' ?>"><?= !$target?'Belum ada target':($has?'Sudah dinilai':'Belum dinilai') ?></span></td><td><?php if($has): ?><strong><?= sanitize((string)$cell['nilai']) ?></strong><?php else: ?><input type="number" name="nilai[<?= (int)$s['id'] ?>]" min="0" max="100" step="0.01" placeholder="0-100" aria-label="Nilai <?= sanitize($s['nama_lengkap']) ?>" class="form-control nm-score" <?= !$enabled?'disabled':'' ?>><?php endif ?></td><?php if($kind==='tugas'): ?><td><?php if($has): ?><?= sanitize($cell['catatan']??'') ?><?php else: ?><input name="catatan[<?= (int)$s['id'] ?>]" maxlength="1000" class="form-control nm-note" <?= !$enabled?'disabled':'' ?> aria-label="Catatan <?= sanitize($s['nama_lengkap']) ?>" placeholder="Opsional"><?php endif ?></td><?php endif ?></tr><?php endforeach ?></tbody></table></div><div class="manual-save p-3 border-top"><span><i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i>Isi nilai yang masih kosong, lalu simpan.</span><button class="btn btn-primary" <?= !$enabled||$graded===count($context['students'])?'disabled':'' ?>><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Simpan Nilai Baru</button></div></form></section>
<?php if($target): ?><section class="nm-card manual-import p-4 mb-4" id="impor-manual"><h2 class="h6 fw-bold"><span class="manual-section-icon tone-green"><i class="fa-solid fa-file-excel" aria-hidden="true"></i></span>Impor nilai satu kelas</h2><p class="small text-muted">Target: <?= sanitize($target['label']) ?>. Gunakan template khusus target ini.</p>
<form method="post" class="mb-3"><?php $hidden(); ?><button name="action" value="template" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-file-arrow-down me-2" aria-hidden="true"></i>Unduh Template Excel</button></form>
<form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 mb-3"><?php $hidden(); ?><input type="hidden" name="action" value="preview"><input type="file" name="excel" accept=".xlsx,.xls" class="form-control nm-upload" aria-label="File nilai Excel" required <?= !$enabled?'disabled':'' ?>><button class="btn btn-primary" <?= !$enabled?'disabled':'' ?>><i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Periksa dan Pratinjau</button></form>
<?php if($preview):$readyCount=count(array_filter($preview['rows'],static fn($r)=>$r['ready'])); ?><div class="alert alert-info"><?= $readyCount ?> nilai baru siap disimpan untuk <strong><?= sanitize($target['label']) ?></strong>.</div><div class="table-responsive"><table class="table"><thead><tr><th>Siswa</th><th>Nilai</th><th>Hasil pemeriksaan</th></tr></thead><tbody><?php foreach($preview['rows'] as $r): ?><tr><td><?= sanitize($r['nama']) ?></td><td><?= sanitize((string)$r['score']) ?></td><td><?= sanitize($r['reason']) ?></td></tr><?php endforeach ?></tbody></table></div><form method="post"><?php $hidden(); ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="import_token" value="<?= sanitize($preview['token']) ?>"><button class="btn btn-success" <?= !$readyCount||!$enabled?'disabled':'' ?>><i class="fa-solid fa-check me-2" aria-hidden="true"></i>Konfirmasi Impor <?= $readyCount ?> Nilai</button></form><?php endif ?></section>
<?php endif ?>
<?php endif ?>
<?php if($context&&$targets&&$kind==='ujian'): ?><section class="nm-card manual-history mb-4"><div class="p-4 border-bottom"><h2 class="h6 fw-bold mb-1"><span class="manual-section-icon tone-purple"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>Riwayat penilaian <?= $kind==='tugas'?'tugas':'ujian harian' ?></h2><p class="small text-muted mb-0">Semua target kelas ini. Klik judul kolom untuk melihat rincian dan mengisi nilai.</p></div><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Siswa</th><?php foreach($targets as $t): ?><th><a href="?<?= sanitize(http_build_query(['pengajaran_id'=>$pid,'jenis'=>$kind,'target_id'=>$t['id']])) ?>" title="<?= sanitize($t['judul']) ?>"><?= $kind==='tugas'?'Tugas':'Ujian' ?> <?= (int)$t['nomor'] ?></a><small class="d-block fw-normal text-muted"><?= sanitize($t['judul']) ?></small></th><?php endforeach ?></tr></thead><tbody><?php foreach($context['students'] as $s): ?><tr><td class="text-nowrap"><?= sanitize($s['nama_lengkap']) ?></td><?php foreach($targets as $t): $v=$context['scores'][$s['id']][$t['id']]['nilai']??null; ?><td><?php if($v!==null): ?><strong class="text-success"><?= sanitize((string)$v) ?></strong><small class="d-block text-muted">Sudah dinilai</small><?php else: ?><span class="text-muted small">Belum dinilai</span><?php endif ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></div></section><?php endif ?>
<?php if($legacy): ?><details class="nm-card p-4 mb-4"><summary class="fw-semibold"><span class="manual-section-icon tone-slate"><i class="fa-solid fa-box-archive" aria-hidden="true"></i></span>Arsip nilai manual lama (<?= count($legacy) ?>)</summary><p class="small text-muted mt-2">Nilai lama tetap tersimpan dan mengikuti perhitungan sebelumnya. Tidak otomatis dipindahkan ke tugas tertentu agar tidak terhitung dua kali.</p><div class="table-responsive"><table class="table"><thead><tr><th>Siswa</th><th>Pertemuan</th><th>Nilai lama</th><th>Catatan</th></tr></thead><tbody><?php foreach($legacy as $r): ?><tr><td><?= sanitize($r['nama_lengkap']) ?></td><td><?= (int)$r['pertemuan_ke'] ?></td><td><?= sanitize((string)$r['nilai']) ?></td><td><?= sanitize($r['catatan']??'') ?></td></tr><?php endforeach ?></tbody></table></div></details><?php endif ?>
</main></div><?php require_once __DIR__.'/../includes/footer.php'; ?>
