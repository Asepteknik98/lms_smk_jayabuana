const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Rapor save/edit, automatic sources, snapshots, validation and rollback',()=>{
const php=String.raw`<?php
require 'config/database.php';require 'includes/rapor.php';$db=Database::getInstance();
$db->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',file_get_contents('database/2026_11_03_rapor_riwayat.sql')));
function ck($ok,$m){if(!$ok)throw new RuntimeException($m);}
function reject($f){try{$f();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid write accepted');}
foreach(['siswa','kelas','guru','mapel','pengajaran','komponen_penilaian','nilai_komponen','capaian_penilaian','ujian','nilai_ujian','sesi_ujian','jawaban_siswa','soal_ujian','tugas','pengumpulan_tugas','nilai_tugas_manual','rekap_tugas_status','sesi_absensi','detail_absensi','ujian_harian_pengaturan','ujian_harian_penerima','ujian_harian_berkas','ujian_harian_remedial','remedial_ujian','remedial_ujian_batal'] as $table){$ddl=$db->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1];$ddl=preg_replace('/^\s*CONSTRAINT .*FOREIGN KEY[^\n]+\n/m','',$ddl);$ddl=preg_replace('/,\s*\)/',"\n)",$ddl);$db->exec(str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl));}
$sql=file_get_contents('database/2026_10_28_rapor.sql');$sql=preg_replace('/^--.*$/m','',$sql);$sql=preg_replace('/^\s*CONSTRAINT fk_[^\n]+\n/m','',$sql);$sql=preg_replace('/,\s*\)/',"\n)",$sql);$sql=str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql);foreach(explode(';',$sql) as $q)if(trim($q))$db->exec($q);
$db->exec("INSERT INTO kelas(id,nama_kelas,tingkat,jurusan) VALUES(1,'X A','X','TKJ'),(2,'X B','X','TKJ')");
$db->exec("INSERT INTO siswa(id,user_id,kelas_id,nis,nisn,nama_lengkap) VALUES(1,1,1,'001','0001','Siswa Uji')");
$db->exec("INSERT INTO guru(id,user_id,nama_lengkap) VALUES(1,3,'Guru Uji')");
$db->exec("INSERT INTO mapel(id,kode_mapel,nama_mapel,kelompok) VALUES(1,'UJI','Mapel Uji','Umum')");
$db->exec("INSERT INTO pengajaran(id,guru_id,mapel_id,kelas_id,tahun_ajaran,semester,kkm) VALUES(1,1,1,1,'2026/2027','Ganjil',75),(2,1,1,2,'2026/2027','Ganjil',75)");
$db->exec("INSERT INTO komponen_penilaian(id,pengajaran_id,nama_komponen,bobot,urutan) VALUES(1,1,'UTS',40,1),(2,1,'UAS',60,2)");
$db->exec("INSERT INTO nilai_komponen(komponen_id,siswa_id,nilai) VALUES(1,1,70),(2,1,90)");
$db->exec("INSERT INTO capaian_penilaian(komponen_id,siswa_id,deskripsi,saran) VALUES(1,1,'Deskripsi UTS','Saran UTS'),(2,1,'Deskripsi UAS','Saran UAS')");
$s=rapor_sources($db,1,1,'2026/2027','Ganjil','UTS');ck((float)$s[1]['nilai_akhir']===82.0&&$s[1]['deskripsi']==='Deskripsi UTS','Weighted score/UTS');ck(rapor_sources($db,1,1,'2026/2027','Ganjil','UAS')[1]['saran']==='Saran UAS','UAS');
$d=['details_ready'=>'1','siswa_id'=>'1','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','sakit'=>'2','mapel'=>[['pengajaran_id'=>'1','nilai_akhir'=>'99','deskripsi'=>'Forged']],'ekstra'=>[['nama_kegiatan'=>'Pramuka','grade'=>'A']]];
$id=rapor_save($db,$d,3);$r=rapor_load($db,$id);ck(count(rapor_history_list($db,1,'2026/2027','Ganjil','UTS',$id))===1&&rapor_history_list($db,1,'2026/2027','Ganjil','UTS',$id)[0]['tindakan']==='create','Creating a draft is recorded');ck((int)$r['sakit']===2&&$r['ekstra'][0]['grade']==='A','Manual fields');ck((float)$r['mapel'][0]['nilai_akhir']===82.0&&$r['mapel'][0]['deskripsi']==='Deskripsi UTS','Reject forged source values');
$e=$d+['id'=>$id,'version'=>rapor_version($r)];$e['mapel']=[['id'=>$r['mapel'][0]['id'],'pengajaran_id'=>1]];$db->exec('UPDATE nilai_komponen SET nilai=100');rapor_save($db,$e,3);$r=rapor_load($db,$id);ck((float)$r['mapel'][0]['nilai_akhir']===82.0,'Snapshot');reject(fn()=>rapor_save($db,$e,3));
$e['version']=rapor_version($r);$e['mapel']=[['id'=>$r['mapel'][0]['id'],'pengajaran_id'=>1,'refresh'=>1]];rapor_save($db,$e,3);$r=rapor_load($db,$id);ck((float)$r['mapel'][0]['nilai_akhir']===100.0,'Refresh');
$e['version']=rapor_version($r);$e['mapel']=[['pengajaran_id'=>2]];reject(fn()=>rapor_save($db,$e,3));ck(rapor_version(rapor_load($db,$id))===rapor_version($r),'Rollback');
foreach(['sakit'=>'-1','tanggal_terbit'=>'2026-02-30','details_ready'=>'0'] as $k=>$v){$b=$d;$b[$k]=$v;reject(fn()=>rapor_save($db,$b,3));}$b=$d;$b['ekstra'][0]['grade']='E';reject(fn()=>rapor_save($db,$b,3));reject(fn()=>rapor_save($db,$d,3));
$b=$e;$b['mapel']=[['pengajaran_id'=>1],['pengajaran_id'=>1]];reject(fn()=>rapor_save($db,$b,3));
$db->exec('DELETE FROM nilai_komponen WHERE komponen_id=2');ck(rapor_sources($db,1,1,'2026/2027','Ganjil','UTS')[1]['nilai_akhir']===null,'Missing grade');$db->exec('UPDATE nilai_komponen SET nilai=0');$db->exec('INSERT INTO nilai_komponen(komponen_id,siswa_id,nilai) VALUES(2,1,0)');ck(rapor_sources($db,1,1,'2026/2027','Ganjil','UTS')[1]['nilai_akhir']===0.0,'Zero grade');
$db->exec('UPDATE komponen_penilaian SET bobot=30 WHERE id=1');ck(rapor_sources($db,1,1,'2026/2027','Ganjil','UTS')[1]['nilai_akhir']===null,'Incomplete weights');
require 'includes/rapor_batch.php';
$db->exec("INSERT INTO siswa(id,user_id,kelas_id,nis,nisn,nama_lengkap) VALUES(2,2,1,'002','0002','Siswa Kedua'),(3,3,1,'003','0003','Siswa Ketiga')");
$batch=['batch_complete'=>'1','batch_action'=>'prepare','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','pengajaran'=>['1'],'nama_wali_kelas'=>'Wali Bersama'];
$before=rapor_version(rapor_load($db,$id));$result=rapor_batch($db,$batch,3);ck($result['created']===2&&$result['skipped']===1,'Batch creates missing only');ck(rapor_version(rapor_load($db,$id))===$before,'Batch preserves saved report');
$result=rapor_batch($db,$batch,3);ck($result['created']===0&&$result['skipped']===3,'Batch retry is idempotent');
$q=$db->query('SELECT id FROM rapor WHERE siswa_id=2');$second=rapor_load($db,(int)$q->fetchColumn());ck($second['nama_wali_kelas']==='Wali Bersama'&&count($second['mapel'])===1,'Shared fields and subjects');
$edit=['batch_complete'=>'1','batch_action'=>'attendance','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','extra_name'=>'Pramuka','extra_grade'=>'B','catatan_wali_kelas'=>'Catatan kelas','students'=>['2'=>['id'=>$second['id'],'version'=>rapor_version($second),'sakit'=>'4','izin'=>'1','alfa'=>'0','catatan_wali_kelas'=>'Catatan kelas','apply_extra'=>'1']]];
rapor_batch($db,$edit,3);$second=rapor_load($db,(int)$second['id']);ck((int)$second['sakit']===4&&$second['catatan_wali_kelas']==='Catatan kelas'&&$second['ekstra'][0]['grade']==='B','Class table and selected extracurricular');ck(count($second['mapel'])===1,'Table preserves grades');reject(fn()=>rapor_batch($db,$edit,3));
$edit['students']['2']['version']=rapor_version($second);$edit['students']['1']=['id'=>$id,'version'=>$before,'sakit'=>'-1','izin'=>'0','alfa'=>'0'];$edit['students']['2']['sakit']='9';reject(fn()=>rapor_batch($db,$edit,3));ck((int)rapor_load($db,(int)$second['id'])['sakit']===4,'Batch table atomic rollback');
$bad=$batch;$bad['pengajaran']=['2'];reject(fn()=>rapor_batch($db,$bad,3));$bad=$batch;unset($bad['batch_complete']);reject(fn()=>rapor_batch($db,$bad,3));

$db->prepare('UPDATE rapor SET nomor_absen=7 WHERE id=?')->execute([$id]);
$shared=['batch_complete'=>'1','batch_action'=>'attendance','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','catatan_wali_kelas'=>'Semangat belajar untuk seluruh kelas','students'=>[]];
foreach([$id,(int)$second['id']] as $rid){$current=rapor_load($db,$rid);$shared['students'][$current['siswa_id']]=['id'=>$rid,'version'=>rapor_version($current),'sakit'=>$current['sakit'],'izin'=>$current['izin'],'alfa'=>$current['alfa']];}
rapor_batch($db,$shared,3);
foreach([$id,(int)$second['id']] as $rid)ck(rapor_load($db,$rid)['catatan_wali_kelas']==='Semangat belajar untuk seluruh kelas','One shared note applies to every submitted student');
ck((int)rapor_load($db,$id)['nomor_absen']===7,'Hidden attendance number remains unchanged');
$first=rapor_load($db,$id);$second=rapor_load($db,(int)$second['id']);
$matrix=['batch_complete'=>'1','batch_action'=>'extras','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','extras_payload'=>json_encode([
 ['id'=>$id,'version'=>rapor_version($first),'grades'=>['Pramuka'=>'A','Futsal'=>'B','Multimedia'=>'D']],
 ['id'=>$second['id'],'version'=>rapor_version($second),'grades'=>['Pramuka'=>'C','Futsal'=>'A']]
])];
ck(rapor_batch($db,$matrix,3)['updated']===2,'Matrix saves several students');
$first=rapor_load($db,$id);$second=rapor_load($db,(int)$second['id']);
$fg=array_column($first['ekstra'],'grade','nama_kegiatan');$sg=array_column($second['ekstra'],'grade','nama_kegiatan');ck($fg['Pramuka']==='A'&&$fg['Futsal']==='B'&&$fg['Multimedia']==='D'&&$sg['Pramuka']==='C'&&$sg['Futsal']==='A','Independent grades per student and activity');
reject(fn()=>rapor_batch($db,$matrix,3));
$matrix['extras_payload']=json_encode([['id'=>$id,'version'=>rapor_version($first),'grades'=>['Pramuka'=>'','Futsal'=>'C']]]);rapor_batch($db,$matrix,3);$first=rapor_load($db,$id);$fg=array_column($first['ekstra'],'grade','nama_kegiatan');ck($fg['Pramuka']===null&&$fg['Futsal']==='C'&&$fg['Multimedia']==='D','Clear a grade and preserve other activities');
$matrix['extras_payload']=json_encode([['id'=>$id,'version'=>rapor_version($first),'grades'=>['Futsal'=>'A']],['id'=>$second['id'],'version'=>rapor_version($second),'grades'=>['Pramuka'=>'E']]]);reject(fn()=>rapor_batch($db,$matrix,3));ck(rapor_version(rapor_load($db,$id))===rapor_version($first),'Invalid grade rolls back entire matrix');
$matrix['kelas_id']='2';reject(fn()=>rapor_batch($db,$matrix,3));
if(!function_exists('sanitize')){function sanitize($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$editableStudents=[['id'=>1,'nama_lengkap'=>'Siswa Uji'],['id'=>2,'nama_lengkap'=>'Siswa Kedua']];$savedByStudent=[1=>$first,2=>$second];$batchContext=[];$posted=null;$batchExtraNames=array_column(rapor_ekstra_template([]),'nama_kegiatan');
$historicalEdit=$e;ob_start();require 'includes/rapor_extra_matrix.php';$html=ob_get_clean();$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);
ck($xpath->query('//thead/tr/th')->length===24,'All 23 activities visible as columns');ck($xpath->query('//tbody/tr')->length===2,'Both students in matrix');ck($xpath->query('//tbody/tr/td')->length===46,'Each student has every activity');ck($xpath->query('//select')->length===0,'No dropdown for activities or grades');ck($xpath->query('//td[@data-extra-name="Futsal" and @data-grade="C"]')->length===1,'Saved individual grade shown');
// Restore version used by the historical-class scenario below.
$e=$historicalEdit;$r=rapor_load($db,$id);$e['version']=rapor_version($r);
$db->exec('UPDATE siswa SET kelas_id=2 WHERE id=1');$e['mapel']=[['id'=>$r['mapel'][0]['id'],'pengajaran_id'=>1]];rapor_save($db,$e,3);ck(rapor_load($db,$id)['nama_kelas']==='X A','Historical class');
$r=rapor_load($db,$id);$e['version']=rapor_version($r);$e['mapel']=[];$e['ekstra']=[];rapor_save($db,$e,3);$r=rapor_load($db,$id);ck(!$r['mapel']&&!$r['ekstra'],'Explicitly remove details');ck(!$db->inTransaction(),'Transaction closed');echo 'PASS';
?>`;
const r=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});
assert.equal(r.status,0,r.stderr+r.stdout);assert.equal(r.stdout,'PASS');
});

for(const [role,name] of [[1,'admin'],[2,'guru'],[3,'siswa'],[4,'Akademik']]){
 for(const folder of ['admin','guru'])for(const file of ['rapor.php','rapor_cetak.php','rapor_pengaturan.php','rapor_peringkat.php']){
  test(`Rapor access ${name} -> ${folder}/${file}`,()=>{
   const php=String.raw`<?php
require 'config/auth.php';$db=Database::getInstance();
$db->exec('CREATE TEMPORARY TABLE roles(id INT,nama_role VARCHAR(50))');
$db->exec('CREATE TEMPORARY TABLE users(id INT,username VARCHAR(50),role_id INT,is_active INT,password VARCHAR(100))');
$db->exec("INSERT INTO roles VALUES(1,'Admin'),(2,'Guru'),(3,'Siswa'),(4,'Akademik')");
$db->exec("INSERT INTO users VALUES(777,'uji',${role},1,'hash')");
$_SESSION['user_id']=777;$_SESSION['role_id']=${role};$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/${folder}/${file}';$_GET=[];
register_shutdown_function(function(){echo "\nSTATUS=".(http_response_code()?:200);session_destroy();});
require '${folder}/${file}';
?>`;
   const r=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',['-d','session.save_path='+require('node:os').tmpdir()],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});
   assert.equal(r.status,0,r.stderr+r.stdout);
   const allowed=role===(folder==='admin'?1:2);
   assert.match(r.stdout,allowed?/STATUS=(200|400)$/:/STATUS=403$/);
   if(!allowed)assert.ok(!r.stdout.includes('id="rapor-form"'));
  });
 }
}
for(const scenario of ['anonymous','disabled','csrf']){
 test(`Rapor rejects ${scenario} request`,()=>{
  const php=String.raw`<?php
require 'config/auth.php';$db=Database::getInstance();
$db->exec('CREATE TEMPORARY TABLE roles(id INT,nama_role VARCHAR(50))');
$db->exec('CREATE TEMPORARY TABLE users(id INT,username VARCHAR(50),role_id INT,is_active INT,password VARCHAR(100))');
$db->exec("INSERT INTO roles VALUES(1,'Admin')");
$db->exec("INSERT INTO users VALUES(777,'uji',1,${scenario==='disabled'?0:1},'hash')");
${scenario==='anonymous'?'':"$_SESSION['user_id']=777;$_SESSION['role_id']=1;"}
$_SERVER['REQUEST_METHOD']='${scenario==='csrf'?'POST':'GET'}';$_SERVER['SCRIPT_NAME']='/admin/rapor.php';$_GET=[];
$_POST=['csrf_token'=>'invalid'];
register_shutdown_function(function(){echo "\nSTATUS=".(http_response_code()?:200);session_destroy();});
require 'admin/rapor.php';
?>`;
  const r=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',['-d','session.save_path='+require('node:os').tmpdir()],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});
  assert.equal(r.status,0,r.stderr+r.stdout);
  assert.match(r.stdout,scenario==='csrf'?/STATUS=403$/:/STATUS=302$/);
  if(scenario==='csrf')assert.ok(r.stdout.includes('Sesi formulir tidak valid'));
 });
}
