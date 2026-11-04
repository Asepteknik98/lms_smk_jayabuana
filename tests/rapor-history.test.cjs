const {test}=require('node:test');const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');const path=require('node:path');
test('History records the actor and changes, isolates periods, skips no-ops and rolls back with failed class saves',()=>{
const php=String.raw`<?php
require 'config/database.php';require 'includes/rapor.php';require 'includes/rapor_batch.php';$db=Database::getInstance();
function ck($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach(['database/2026_10_28_rapor.sql','database/2026_11_03_rapor_administrasi.sql','database/2026_11_03_rapor_riwayat.sql'] as $file){$sql=preg_replace('/^--.*$/m','',file_get_contents($file));$sql=preg_replace('/^\s*CONSTRAINT fk_[^\n]+\n/m','',$sql);$sql=preg_replace('/,\s*\)/',"\n)",$sql);foreach(explode(';',str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql)) as $query)if(trim($query))$db->exec($query);}
$db->exec('CREATE TEMPORARY TABLE users(id INT PRIMARY KEY,username VARCHAR(100))');$db->exec("INSERT INTO users VALUES(7,'wali_uji')");
$db->exec('CREATE TEMPORARY TABLE kelas(id INT PRIMARY KEY)');$db->exec('INSERT INTO kelas VALUES(1),(2)');
$db->exec('CREATE TEMPORARY TABLE siswa(id INT PRIMARY KEY,kelas_id INT,nama_lengkap VARCHAR(100))');$db->exec("INSERT INTO siswa VALUES(1,1,'Satu'),(2,1,'Dua')");
$db->exec("INSERT INTO rapor(siswa_id,kelas_id,tahun_ajaran,semester,jenis,nama_siswa,nama_kelas) VALUES(1,1,'2026/2027','Ganjil','UTS','Satu','X A'),(2,1,'2026/2027','Ganjil','UTS','Dua','X A')");
$ids=$db->query('SELECT id FROM rapor ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);$first=rapor_load($db,(int)$ids[0]);$second=rapor_load($db,(int)$ids[1]);
$batch=['batch_action'=>'attendance','batch_complete'=>'1','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','catatan_wali_kelas'=>'Semangat','students'=>[]];
$batch['students'][1]=['id'=>$first['id'],'version'=>rapor_version($first),'sakit'=>'2','izin'=>'0','alfa'=>'0'];
$batch['students'][2]=['id'=>$second['id'],'version'=>'stale','sakit'=>'1','izin'=>'0','alfa'=>'0'];
try{rapor_batch($db,$batch,7);throw new LogicException('Invalid batch accepted');}catch(RuntimeException $e){}
ck((int)$db->query('SELECT COUNT(*) FROM rapor_riwayat')->fetchColumn()===0,'Rolled-back changes leave no history');ck((int)rapor_load($db,(int)$first['id'])['sakit']===0,'Data also rolled back');
$batch['students'][2]['version']=rapor_version($second);rapor_batch($db,$batch,7);$rows=rapor_history_list($db,1,'2026/2027','Ganjil','UTS');ck(count($rows)===2,'One history entry per changed student');ck($rows[0]['nama_pengguna']==='wali_uji'&&(int)$rows[0]['user_id']===7,'Actor derived from server user');
$changes=json_decode($rows[0]['perubahan'],true);ck($changes['catatan_wali_kelas']['sesudah']==='Semangat'&&isset($changes['sakit']),'Changed fields retained');
ck(!rapor_history_list($db,2,'2026/2027','Ganjil','UTS')&&!rapor_history_list($db,1,'2026/2027','Ganjil','UAS'),'Class and period isolation');
foreach($ids as $index=>$id)$batch['students'][$index+1]['version']=rapor_version(rapor_load($db,(int)$id));rapor_batch($db,$batch,7);ck(count(rapor_history_list($db,1,'2026/2027','Ganjil','UTS'))===2,'No-op saves do not create extra activities');
$r=rapor_load($db,(int)$first['id']);$single=['id'=>$r['id'],'version'=>rapor_version($r),'details_ready'=>'1','sakit'=>'2','izin'=>'0','alfa'=>'0','catatan_wali_kelas'=>'Catatan baru'];rapor_save($db,$single,7);$one=rapor_history_list($db,1,'2026/2027','Ganjil','UTS',(int)$r['id']);ck(count($one)===2&&$one[0]['tindakan']==='edit','Individual save also logged and student history isolated');
$r=rapor_load($db,(int)$first['id']);$extras=['batch_action'=>'extras','batch_complete'=>'1','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','extras_payload'=>json_encode([['id'=>$r['id'],'version'=>rapor_version($r),'grades'=>['Pramuka'=>'A','Futsal'=>'B']]])];rapor_batch($db,$extras,7);$one=rapor_history_list($db,1,'2026/2027','Ganjil','UTS',(int)$r['id']);ck($one[0]['tindakan']==='extras'&&isset(json_decode($one[0]['perubahan'],true)['ekstra']),'Extracurricular changes logged');
ck(!$db->inTransaction(),'Transactions closed');echo 'PASS';
?>`;
const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});assert.equal(result.status,0,result.stderr+result.stdout);assert.equal(result.stdout,'PASS');
});
