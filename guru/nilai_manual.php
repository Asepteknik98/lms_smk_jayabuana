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
$pid=(int)($input['pengajaran_id']??($assignments[0]['id']??0));$kind=($input['jenis']??'tugas')==='ujian'?'ujian':'tugas';$id=(int)($input['target_id']??0);$meeting=max(0,(int)($input['pertemuan']??0));
$context=null;$target=null;$error='';$preview=null;
try{
 if($pid)$context=nm_context($db,$guru,$pid,$kind);
 if($id&&$context)$target=nm_target($context,$id);
}catch(Throwable $e){http_response_code(403);exit('Kelas atau target penilaian tidak dapat diakses.');}
$url='nilai_manual.php?'.http_build_query(['pengajaran_id'=>$pid,'jenis'=>$kind,'target_id'=>$id,'pertemuan'=>$meeting]);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
  if(!$context||!$target)throw new RuntimeException('Pilih target penilaian terlebih dahulu.');
  $action=$_POST['action']??'';
  if($action==='template'){
   $xml=nm_template($context,$id);header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="Template_Manual_'.$kind.'_'.$target['nomor'].'_Kelas_'.$pid.'.xls"');header('Cache-Control: no-store');echo $xml;exit;
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
$hidden=static function()use($pid,$kind,$id,$meeting):void{
 foreach(['csrf_token'=>$_SESSION['csrf_token'],'pengajaran_id'=>$pid,'jenis'=>$kind,'target_id'=>$id,'pertemuan'=>$meeting] as $name=>$value)echo '<input type="hidden" name="'.sanitize($name).'" value="'.sanitize((string)$value).'">';
};
$targets=$context['targets']??[];$filtered=$kind==='tugas'&&$meeting?array_values(array_filter($targets,static fn($t)=>(int)$t['pertemuan_ke']===$meeting)):$targets;
$legacy=[];if($context){$q=$db->prepare('SELECT n.*,s.nama_lengkap FROM nilai_tugas_manual n JOIN siswa s ON s.id=n.siswa_id AND s.kelas_id=? WHERE n.pengajaran_id=? ORDER BY n.pertemuan_ke,s.nama_lengkap');$q->execute([$context['info']['kelas_id'],$pid]);$legacy=$q->fetchAll();}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/nilai-manual.css?v=1">
<div id="page-content-wrapper" class="nm-page"><nav class="navbar top-navbar px-4 py-3"><div><h1 class="h5 fw-bold mb-1"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Input Nilai Manual</h1><small class="text-muted">Nilai per tugas dan ujian harian, terhubung ke rekap kelas.</small></div><a class="btn btn-outline-primary btn-sm" href="rekap_nilai_guru.php?pengajaran_id=<?= $pid ?>">Rekap Nilai</a></nav>
<main class="nm-content p-3 p-md-4">
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<section class="nm-card p-4 mb-4"><form method="get" class="row g-3">
<div class="col-md-8"><label for="nmKelas" class="form-label fw-semibold">Mata pelajaran dan kelas</label><select name="pengajaran_id" id="nmKelas" class="form-select" onchange="this.form.target_id.value='';this.form.submit()"><option value="">Pilih kelas</option><?php foreach($assignments as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id']===$pid?'selected':'' ?>><?= sanitize($a['nama_mapel'].' - '.$a['nama_kelas'].' ('.$a['semester'].', '.$a['tahun_ajaran'].')') ?></option><?php endforeach ?></select></div>
<div class="col-md-4"><label for="nmJenis" class="form-label fw-semibold">Jenis penilaian</label><select name="jenis" id="nmJenis" class="form-select" onchange="this.form.target_id.value='';this.form.submit()"><option value="tugas" <?= $kind==='tugas'?'selected':'' ?>>Nilai Tugas</option><option value="ujian" <?= $kind==='ujian'?'selected':'' ?>>Ujian Harian</option></select></div>
<?php if($kind==='tugas'): ?><div class="col-md-5"><label for="nmPertemuan" class="form-label fw-semibold">Pertemuan dan materi</label><select id="nmPertemuan" name="pertemuan" class="form-select" onchange="this.form.target_id.value='';this.form.submit()"><option value="0">Semua pertemuan</option><?php $maxMeeting=max(array_merge([20],array_map(static fn($t)=>(int)$t['pertemuan_ke'],$targets)));for($n=1;$n<=$maxMeeting;$n++): ?><option value="<?= $n ?>" <?= $meeting===$n?'selected':'' ?>>Pertemuan <?= $n ?> &mdash; <?= sanitize(implode('; ',$context['meetings'][$n]??['Belum ada materi'])) ?></option><?php endfor ?></select></div><?php endif ?>
<div class="<?= $kind==='tugas'?'col-md-7':'col-12' ?>"><label for="nmTarget" class="form-label fw-semibold">Target <?= $kind==='tugas'?'tugas':'ujian harian' ?></label><select id="nmTarget" name="target_id" class="form-select" onchange="this.form.submit()"><option value="">Pilih target penilaian</option><?php foreach($filtered as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$t['id']===$id?'selected':'' ?>><?= sanitize($t['label']) ?></option><?php endforeach ?></select></div></form>
<?php if(!$filtered): ?><p class="text-muted mt-3 mb-0">Belum ada target pada pilihan ini. Buat <?= $kind==='tugas'?'tugas':'ujian harian' ?> terlebih dahulu melalui menu yang sesuai.</p><?php endif ?>
<?php if($target): ?><div class="alert alert-primary mt-3 mb-0"><strong><?= sanitize($target['label']) ?></strong><?php if($kind==='tugas'): ?><div class="small mt-1">Pertemuan <?= (int)$target['pertemuan_ke'] ?> &mdash; <?= sanitize(implode('; ',$context['meetings'][(int)$target['pertemuan_ke']]??['Belum ada materi'])) ?></div><?php endif ?><div class="small mt-1">Nilai tersimpan diteruskan ke <?= $kind==='tugas'?'Rekap Tugas dan rata-rata Tugas Harian':'rata-rata Ulangan Harian' ?>. Nilai kosong dilewati; angka 0 tetap disimpan.</div></div><?php endif ?></section>
<?php if($context&&$target): $graded=count(array_filter($context['students'],static fn($s)=>isset($context['scores'][$s['id']][$id]['nilai'])));$enabled=$kind==='tugas'||$target['selesai']; ?>
<section class="nm-card mb-4"><div class="p-4 border-bottom d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h6 fw-bold mb-1">Daftar nilai siswa</h2><small class="text-muted"><?= $graded ?> dari <?= count($context['students']) ?> siswa sudah dinilai. Nilai lama dikunci.</small></div><?php if($kind==='tugas'): ?><a class="btn btn-sm btn-outline-primary" href="tugas.php?rekap_pengajaran=<?= $pid ?>&amp;rekap_mode=tugas&amp;rekap_tugas_id=<?= $id ?>#rekap-tugas">Lihat Rekap Tugas / Remedial</a><?php endif ?></div>
<?php if(!$enabled): ?><div class="alert alert-warning m-3">Pengisian dan impor nilai tersedia setelah jadwal ujian berakhir.</div><?php endif ?>
<form method="post"><?php $hidden(); ?><input type="hidden" name="action" value="save"><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>No</th><th>Siswa</th><th>Status</th><th>Nilai</th><?php if($kind==='tugas'): ?><th>Catatan</th><?php endif ?></tr></thead><tbody><?php foreach($context['students'] as $i=>$s):$cell=$context['scores'][$s['id']][$id]??null;$has=$cell&&$cell['nilai']!==null; ?><tr><td><?= $i+1 ?></td><td><strong><?= sanitize($s['nama_lengkap']) ?></strong><small class="d-block text-muted">NIS <?= sanitize($s['nis']??'') ?></small></td><td><span class="badge <?= $has?'bg-success':'bg-secondary' ?>"><?= $has?'Sudah dinilai':'Belum dinilai' ?></span></td><td><?php if($has): ?><strong><?= sanitize((string)$cell['nilai']) ?></strong><?php else: ?><input type="number" name="nilai[<?= (int)$s['id'] ?>]" min="0" max="100" step="0.01" placeholder="0-100" aria-label="Nilai <?= sanitize($s['nama_lengkap']) ?>" class="form-control nm-score" <?= !$enabled?'disabled':'' ?>><?php endif ?></td><?php if($kind==='tugas'): ?><td><?php if($has): ?><?= sanitize($cell['catatan']??'') ?><?php else: ?><input name="catatan[<?= (int)$s['id'] ?>]" maxlength="1000" class="form-control nm-note" aria-label="Catatan <?= sanitize($s['nama_lengkap']) ?>" placeholder="Opsional"><?php endif ?></td><?php endif ?></tr><?php endforeach ?></tbody></table></div><div class="p-3 border-top text-end"><button class="btn btn-primary" <?= !$enabled||$graded===count($context['students'])?'disabled':'' ?>>Simpan Nilai Baru</button></div></form></section>
<section class="nm-card p-4 mb-4" id="impor-manual"><h2 class="h6 fw-bold">Impor nilai satu kelas</h2><p class="small text-muted">Target: <?= sanitize($target['label']) ?>. Gunakan template khusus target ini.</p>
<form method="post" class="mb-3"><?php $hidden(); ?><button name="action" value="template" class="btn btn-outline-success btn-sm">Unduh Template Excel</button></form>
<form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 mb-3"><?php $hidden(); ?><input type="hidden" name="action" value="preview"><input type="file" name="excel" accept=".xlsx,.xls" class="form-control nm-upload" aria-label="File nilai Excel" required <?= !$enabled?'disabled':'' ?>><button class="btn btn-primary" <?= !$enabled?'disabled':'' ?>>Periksa dan Pratinjau</button></form>
<?php if($preview):$readyCount=count(array_filter($preview['rows'],static fn($r)=>$r['ready'])); ?><div class="alert alert-info"><?= $readyCount ?> nilai baru siap disimpan untuk <strong><?= sanitize($target['label']) ?></strong>.</div><div class="table-responsive"><table class="table"><thead><tr><th>Siswa</th><th>Nilai</th><th>Hasil pemeriksaan</th></tr></thead><tbody><?php foreach($preview['rows'] as $r): ?><tr><td><?= sanitize($r['nama']) ?></td><td><?= sanitize((string)$r['score']) ?></td><td><?= sanitize($r['reason']) ?></td></tr><?php endforeach ?></tbody></table></div><form method="post"><?php $hidden(); ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="import_token" value="<?= sanitize($preview['token']) ?>"><button class="btn btn-success" <?= !$readyCount||!$enabled?'disabled':'' ?>>Konfirmasi Impor <?= $readyCount ?> Nilai</button></form><?php endif ?></section>
<?php endif ?>
<?php if($context&&$targets&&$kind==='ujian'): ?><section class="nm-card mb-4"><div class="p-4 border-bottom"><h2 class="h6 fw-bold mb-1">Riwayat penilaian <?= $kind==='tugas'?'tugas':'ujian harian' ?></h2><p class="small text-muted mb-0">Semua target kelas ini. Klik judul kolom untuk melihat rincian dan mengisi nilai.</p></div><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Siswa</th><?php foreach($targets as $t): ?><th><a href="?<?= sanitize(http_build_query(['pengajaran_id'=>$pid,'jenis'=>$kind,'target_id'=>$t['id']])) ?>" title="<?= sanitize($t['judul']) ?>"><?= $kind==='tugas'?'Tugas':'Ujian' ?> <?= (int)$t['nomor'] ?></a><small class="d-block fw-normal text-muted"><?= sanitize($t['judul']) ?></small></th><?php endforeach ?></tr></thead><tbody><?php foreach($context['students'] as $s): ?><tr><td class="text-nowrap"><?= sanitize($s['nama_lengkap']) ?></td><?php foreach($targets as $t): $v=$context['scores'][$s['id']][$t['id']]['nilai']??null; ?><td><?php if($v!==null): ?><strong class="text-success"><?= sanitize((string)$v) ?></strong><small class="d-block text-muted">Sudah dinilai</small><?php else: ?><span class="text-muted small">Belum dinilai</span><?php endif ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></div></section><?php endif ?>
<?php if($legacy): ?><details class="nm-card p-4 mb-4"><summary class="fw-semibold">Arsip nilai manual lama (<?= count($legacy) ?>)</summary><p class="small text-muted mt-2">Nilai lama tetap tersimpan dan mengikuti perhitungan sebelumnya. Tidak otomatis dipindahkan ke tugas tertentu agar tidak terhitung dua kali.</p><div class="table-responsive"><table class="table"><thead><tr><th>Siswa</th><th>Pertemuan</th><th>Nilai lama</th><th>Catatan</th></tr></thead><tbody><?php foreach($legacy as $r): ?><tr><td><?= sanitize($r['nama_lengkap']) ?></td><td><?= (int)$r['pertemuan_ke'] ?></td><td><?= sanitize((string)$r['nilai']) ?></td><td><?= sanitize($r['catatan']??'') ?></td></tr><?php endforeach ?></tbody></table></div></details><?php endif ?>
</main></div><?php require_once __DIR__.'/../includes/footer.php'; ?>
