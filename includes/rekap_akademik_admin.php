<?php
check_access([1]);
require_once __DIR__.'/rekap_akademik_matrix.php';
$periods=$db->query('SELECT DISTINCT tahun_ajaran,semester FROM pengajaran ORDER BY tahun_ajaran DESC,semester')->fetchAll();
$tahun=is_string($_GET['tahun_ajaran']??null)?$_GET['tahun_ajaran']:($periods[0]['tahun_ajaran']??'');
$semester=is_string($_GET['semester']??null)?$_GET['semester']:($periods[0]['semester']??'Ganjil');
$stmt=$db->prepare('SELECT DISTINCT k.id,k.nama_kelas FROM kelas k JOIN pengajaran p ON p.kelas_id=k.id WHERE p.tahun_ajaran=? AND p.semester=? ORDER BY k.nama_kelas');
$stmt->execute([$tahun,$semester]);$classes=$stmt->fetchAll();
$kelas=(int)($_GET['kelas_id']??($classes[0]['id']??0));
if(!in_array($kelas,array_map('intval',array_column($classes,'id')),true))$kelas=(int)($classes[0]['id']??0);
$className='';foreach($classes as $c)if((int)$c['id']===$kelas)$className=$c['nama_kelas'];
$stmt=$db->prepare('SELECT id,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap');$stmt->execute([$kelas]);$students=$stmt->fetchAll();
$mode=($_GET['mode']??'kelas')==='siswa'?'siswa':'kelas';
$student=$mode==='siswa'?(int)($_GET['siswa_id']??($students[0]['id']??0)):0;
if($mode==='siswa'&&!in_array($student,array_map('intval',array_column($students,'id')),true))$student=(int)($students[0]['id']??0);
$matrix=ram_data($db,$kelas,$tahun,$semester);
if($mode==='siswa')$matrix['students']=array_values(array_filter($matrix['students'],static fn($s)=>(int)$s['id']===$student));
$allRows=[];
foreach(['UTS','UAS'] as $period)foreach(ra_rows($db,$kelas,$tahun,$semester,$period) as $r){$r['jenis']=$period;$allRows[]=$r;}
$monitor=[];
// Include teachers even when their class has no students yet.
$stmt=$db->prepare('SELECT p.id,m.nama_mapel,g.nama_lengkap FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id WHERE p.kelas_id=? AND p.tahun_ajaran=? AND p.semester=? ORDER BY g.nama_lengkap,m.nama_mapel');
$stmt->execute([$kelas,$tahun,$semester]);
foreach($stmt->fetchAll() as $p)foreach(['UTS','UAS'] as $period)$monitor[$p['id'].'-'.$period]=['guru'=>$p['nama_lengkap'],'mapel'=>$p['nama_mapel'],'jenis'=>$period,'total'=>0,'nilai'=>0,'deskripsi'=>0,'saran'=>0,'lengkap'=>0,'updated'=>''];
foreach($allRows as $r){
 $m=&$monitor[$r['pengajaran_id'].'-'.$r['jenis']];$m['total']++;$m['nilai']+=(int)($r['nilai']!==null);$m['deskripsi']+=(int)(trim($r['deskripsi']??'')!=='');$m['saran']+=(int)(trim($r['saran']??'')!=='');$m['lengkap']+=(int)ra_lengkap($r);
 $m['updated']=max($m['updated'],$r['nilai_updated_at']??'',$r['capaian_updated_at']??'');unset($m);
}
$query=['scope'=>'gabungan','tahun_ajaran'=>$tahun,'semester'=>$semester,'kelas_id'=>$kelas,'mode'=>$mode,'siswa_id'=>$student];
$title='Rekap Nilai Rapor - '.$className.' - '.$semester.' / '.$tahun;
$format=$_GET['format']??'';
if(in_array($format,['excel','pdf'],true)){
 $filename=preg_replace('/[^A-Za-z0-9_-]+/','_',$title).'_'.$mode.($student?'_'.$student:'');
 $content=$format==='excel'?ram_excel($title,$matrix):ram_pdf($title,$matrix);
 header('Content-Type: '.($format==='excel'?'application/vnd.ms-excel; charset=UTF-8':'application/pdf'));
 header('Content-Disposition: attachment; filename="'.$filename.($format==='excel'?'.xls':'.pdf').'"');header('Cache-Control: no-store');echo $content;exit;
}
require_once __DIR__.'/header.php';require_once __DIR__.'/sidebar.php';
?>
<style>
.rapor-scroll{max-height:75vh;position:relative}.rapor-matrix{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0;font-size:.85rem}.rapor-matrix th,.rapor-matrix td{box-sizing:border-box;white-space:pre-wrap;overflow-wrap:anywhere;border-right:1px solid #cbd5e1;border-bottom:1px solid #cbd5e1}.rapor-matrix thead{position:sticky;top:0;z-index:5}.rapor-matrix thead th{background:#dbeafe;color:#17365d}.rapor-matrix .rapor-subject{background:#1769b1;color:#fff}.rapor-matrix .rapor-no,.rapor-matrix .rapor-nisn,.rapor-matrix .rapor-name{position:sticky;z-index:2;background:#fff}.rapor-matrix thead .rapor-no,.rapor-matrix thead .rapor-nisn,.rapor-matrix thead .rapor-name{background:#dbeafe;z-index:3}.rapor-no{left:0;width:45px;min-width:45px;max-width:45px}.rapor-nisn{left:45px;width:105px;min-width:105px;max-width:105px}.rapor-name{left:150px;width:180px;min-width:180px;max-width:180px;box-shadow:3px 0 5px #0001}.rapor-score{width:115px;min-width:115px;max-width:115px;text-align:center}.rapor-narrative{width:300px;min-width:300px;max-width:300px}@media(max-width:575px){.rapor-matrix .rapor-no,.rapor-matrix .rapor-nisn{position:static}.rapor-matrix .rapor-name{left:0;width:120px;min-width:120px;max-width:120px}}
</style>
<link rel="stylesheet" href="../assets/css/rekap-nilai-ui.css?v=20261003-1">
<div id="page-content-wrapper" class="bg-light">
<nav class="navbar top-navbar px-4 py-3"><h5 class="mb-0">Rekap Nilai Rapor per Kelas</h5></nav>
<main class="container-fluid p-3 p-md-4">
<div class="d-flex flex-wrap gap-2 mb-3"><a href="rekap_nilai.php" class="btn btn-outline-primary">Rekap per Guru</a><a href="rekap_nilai.php?scope=gabungan" class="btn btn-primary" aria-current="page">Gabungan Semua Mapel</a></div>
<div class="card mb-3"><div class="card-body">
<h6>Monitoring nilai dan capaian pembelajaran</h6><p class="text-muted">Satu siswa satu baris, seluruh mata pelajaran ke samping. Rincian nilai dan bobot mengikuti pengaturan guru, termasuk UTS, UAS, dan nilai akhir. Deskripsi serta saran UTS/UAS ditampilkan bersama.</p>
<form method="get" class="row g-2 align-items-end" id="gabunganFilter"><input type="hidden" name="scope" value="gabungan">
<div class="col-md-2"><label class="form-label" for="tahunGabungan">Tahun ajaran</label><select id="tahunGabungan" class="form-select" name="tahun_ajaran"><?php foreach(array_unique(array_column($periods,'tahun_ajaran')) as $year): ?><option <?= $year===$tahun?'selected':'' ?> value="<?= sanitize($year) ?>"><?= sanitize($year) ?></option><?php endforeach ?></select></div>
<div class="col-md-2"><label class="form-label" for="semesterGabungan">Semester</label><select id="semesterGabungan" class="form-select" name="semester"><?php foreach(['Ganjil','Genap'] as $sem): ?><option <?= $semester===$sem?'selected':'' ?>><?= $sem ?></option><?php endforeach ?></select></div>
<div class="col-md-2"><label class="form-label" for="kelasGabungan">Kelas</label><select id="kelasGabungan" class="form-select" name="kelas_id"><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $kelas===(int)$c['id']?'selected':'' ?>><?= sanitize($c['nama_kelas']) ?></option><?php endforeach ?></select></div>

<div class="col-md-2"><label class="form-label" for="modeGabungan">Mode rekap</label><select id="modeGabungan" class="form-select" name="mode"><option value="kelas" <?= $mode==='kelas'?'selected':'' ?>>Per Kelas</option><option value="siswa" <?= $mode==='siswa'?'selected':'' ?>>Per Siswa</option></select></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
<?php if($mode==='siswa'): ?><div class="col-md-6"><label class="form-label" for="siswaGabungan">Siswa</label><select id="siswaGabungan" class="form-select" name="siswa_id"><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']===$student?'selected':'' ?>><?= sanitize($s['nama_lengkap']) ?></option><?php endforeach ?></select></div><?php endif ?>
</form></div></div>
<div class="card mb-3 rekap-monitor"><div class="card-body"><h6>Monitoring Guru - <?= sanitize($className) ?></h6><p class="small text-muted">Lengkap berarti nilai, deskripsi capaian, dan saran sudah diisi. Monitoring selalu mencakup seluruh siswa di kelas terpilih.</p>
<div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Guru</th><th>Mapel</th><th>Penilaian</th><th>Nilai</th><th>Deskripsi</th><th>Saran</th><th>Lengkap</th><th>Terakhir diperbarui</th></tr></thead><tbody>
<?php foreach($monitor as $m): ?><tr><td><?= sanitize($m['guru']) ?></td><td><?= sanitize($m['mapel']) ?></td><td><?= $m['jenis'] ?></td><?php foreach(['nilai','deskripsi','saran','lengkap'] as $key): ?><td><?= $m[$key] ?> / <?= $m['total'] ?></td><?php endforeach ?><td><?= sanitize($m['updated']?:'Belum diisi') ?></td></tr><?php endforeach ?>
<?php if(!$monitor): ?><tr><td colspan="8">Tidak ada pengajaran pada periode ini.</td></tr><?php endif ?>
</tbody></table></div></div></div>
<div class="card"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h6><?= sanitize($title) ?></h6><div class="d-flex gap-2"><a class="btn btn-success btn-sm" href="?<?= sanitize(http_build_query($query+['format'=>'excel'])) ?>">Unduh Excel per Mapel</a><a class="btn btn-danger btn-sm" href="?<?= sanitize(http_build_query($query+['format'=>'pdf'])) ?>">Unduh PDF Rapor</a></div></div>
<p class="small text-muted">Geser tabel ke kanan untuk melihat semua mapel. Nama siswa tetap terlihat. PDF memakai halaman landscape lebar agar mapel tetap berjajar horizontal.</p>
<div class="table-responsive rapor-scroll" tabindex="0" role="region" aria-label="Rekap nilai horizontal per kelas"><table class="table table-bordered align-top rapor-matrix" id="raporMatrix">
<thead><tr><th rowspan="2" class="rapor-no" scope="col">No</th><th rowspan="2" class="rapor-nisn" scope="col">NISN</th><th rowspan="2" class="rapor-name" scope="col">Nama Siswa</th>
<?php foreach($matrix['subjects'] as $subject): ?><th colspan="9" scope="colgroup" class="text-center rapor-subject"><?= sanitize($subject['nama_mapel']) ?><small class="d-block fw-normal"><?= sanitize($subject['nama_guru']) ?></small></th><?php endforeach ?></tr>
<tr><?php foreach($matrix['subjects'] as $subject): ?><?php foreach(ram_columns() as $index=>$column): ?><th scope="col" class="<?= $index>=7?'rapor-narrative':'rapor-score' ?>"><?= nl2br(sanitize(ram_label($subject,$column))) ?></th><?php endforeach ?><?php endforeach ?></tr></thead>
<tbody><?php foreach(ram_rows($matrix) as $row): ?><tr><?php foreach($row as $index=>$cell): ?><td class="<?= $index===0?'rapor-no':($index===1?'rapor-nisn':($index===2?'rapor-name':(($index-3)%9>=7?'rapor-narrative':(($index-3)%9===6?'rapor-score fw-bold':'rapor-score')))) ?>"><?= sanitize(is_float($cell)?number_format($cell,2,',','.'):(string)$cell) ?></td><?php endforeach ?></tr><?php endforeach ?>
<?php if(!$matrix['students']): ?><tr><td colspan="<?= 3+9*count($matrix['subjects']) ?>" class="text-center text-muted">Tidak ada siswa sesuai filter.</td></tr><?php endif ?>
</tbody></table></div></div></div></main></div>
<script>
(function(){
 const form=document.getElementById('gabunganFilter');
 ['tahunGabungan','semesterGabungan','kelasGabungan','modeGabungan'].forEach(function(id){document.getElementById(id).addEventListener('change',function(){
  if(['tahunGabungan','semesterGabungan'].includes(id))document.getElementById('kelasGabungan').disabled=true;
  const student=document.getElementById('siswaGabungan');if(student)student.disabled=true;
  form.requestSubmit();
 });});
})();
</script>
<?php require_once __DIR__.'/footer.php'; ?>
