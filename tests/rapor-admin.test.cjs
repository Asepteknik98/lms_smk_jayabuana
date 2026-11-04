const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Rapor administration isolates periods, prepares headers and enforces locks on every write path',()=>{
const php=String.raw`<?php
require 'config/database.php';require 'includes/rapor.php';require 'includes/rapor_batch.php';require 'includes/rapor_judul.php';$db=Database::getInstance();
function ck($v,$message){if(!$v)throw new RuntimeException($message);}
function reject($f){try{$f();}catch(RuntimeException $e){return;}throw new RuntimeException('Unexpected accepted action');}
$db->exec('CREATE TEMPORARY TABLE kelas(id INT PRIMARY KEY,nama_kelas VARCHAR(100))');$db->exec("INSERT INTO kelas VALUES(1,'X A'),(2,'X B')");
$db->exec('CREATE TEMPORARY TABLE siswa(id INT PRIMARY KEY,kelas_id INT,nama_lengkap VARCHAR(100))');$db->exec("INSERT INTO siswa VALUES(1,1,'Satu'),(2,1,'Dua'),(3,2,'Tiga')");
$db->exec('CREATE TEMPORARY TABLE guru(id INT PRIMARY KEY,nama_lengkap VARCHAR(100))');$db->exec("INSERT INTO guru VALUES(1,'Wali Uji')");
foreach(['database/2026_10_28_rapor.sql','database/2026_11_03_rapor_administrasi.sql','database/2026_11_03_rapor_riwayat.sql'] as $file){$sql=file_get_contents($file);$sql=preg_replace('/^--.*$/m','',$sql);$sql=preg_replace('/^\s*CONSTRAINT fk_[^\n]+\n/m','',$sql);$sql=preg_replace('/,\s*\)/',"\n)",$sql);$sql=str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql);foreach(explode(';',$sql) as $statement)if(trim($statement))$db->exec($statement);}
$data=['admin_action'=>'settings','kelas_id'=>1,'tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','wali_guru_id'=>'1','nama_kepala_sekolah'=>'Kepala','tempat_terbit'=>'Tangerang','tanggal_terbit'=>'2026-11-03','settings_version'=>rapor_admin_version(null)];
rapor_admin_save($db,$data,1);$a=rapor_admin_load($db,1,'2026/2027','Ganjil','UTS');ck($a['nama_wali_kelas']==='Wali Uji','Assigned teacher name');ck(!rapor_admin_load($db,2,'2026/2027','Ganjil','UTS'),'Class isolation');ck(!rapor_admin_load($db,1,'2026/2027','Ganjil','UAS'),'Period isolation');reject(fn()=>rapor_admin_save($db,$data,1));
$data['settings_version']=rapor_admin_version($a);$bad=$data;$bad['tanggal_terbit']='2026-02-30';reject(fn()=>rapor_admin_save($db,$bad,1));$bad=$data;$bad['wali_guru_id']='99';reject(fn()=>rapor_admin_save($db,$bad,1));
$lock=$data;$lock['admin_action']='lock';reject(fn()=>rapor_admin_save($db,$lock,1));
$db->exec("INSERT INTO rapor(siswa_id,kelas_id,tahun_ajaran,semester,jenis,nama_siswa,nama_kelas,nama_wali_kelas,nama_kepala_sekolah,tempat_terbit,tanggal_terbit) VALUES(1,1,'2026/2027','Ganjil','UTS','Satu','X A','Wali Lama','Kepala Lama','Tempat Lama','2026-10-01'),(2,1,'2026/2027','Ganjil','UTS','Dua','X A','Wali Lama','Kepala Lama','Tempat Lama','2026-10-01'),(3,2,'2026/2027','Ganjil','UTS','Tiga','X B','Lain','Lain','Lain','2026-10-01')");
$ids=$db->query('SELECT id FROM rapor WHERE kelas_id=1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
foreach($ids as $id)$db->prepare("INSERT INTO rapor_mapel(rapor_id,nama_mapel,nama_guru,kelompok,urutan,nilai_akhir,deskripsi,saran,sumber_diambil_pada) VALUES(?,'Mapel','Guru','Umum',1,80,'Capaian','Saran',NOW())")->execute([$id]);
$db->prepare('UPDATE rapor SET sakit=3,catatan_wali_kelas=? WHERE id=?')->execute(['Catatan tetap',$ids[0]]);
$data['apply_existing']='1';rapor_admin_save($db,$data,1);$r=rapor_load($db,(int)$ids[0]);ck($r['nama_wali_kelas']==='Wali Uji'&&$r['nama_kepala_sekolah']==='Kepala','Apply common headers');ck((int)$r['sakit']===3&&$r['catatan_wali_kelas']==='Catatan tetap'&&(float)$r['mapel'][0]['nilai_akhir']===80.0,'Preserve report values');
$other=$db->query('SELECT nama_wali_kelas FROM rapor WHERE kelas_id=2')->fetchColumn();ck($other==='Lain','Header update isolates classes');
$reports=rapor_admin_reports($db,'2026/2027','Ganjil','UTS',1);ck(count($reports)===2&&$reports[0]['lengkap']&&$reports[1]['lengkap'],'Completeness matches headers and grades');
$lock['settings_version']=rapor_admin_version(rapor_admin_load($db,1,'2026/2027','Ganjil','UTS'));rapor_admin_save($db,$lock,1);ck((int)rapor_admin_load($db,1,'2026/2027','Ganjil','UTS')['terkunci']===1,'Lock complete class');
reject(fn()=>rapor_admin_assert_open($db,1,'2026/2027','Ganjil','UTS'));rapor_admin_assert_open($db,1,'2026/2027','Ganjil','UAS');
$r=rapor_load($db,(int)$ids[0]);reject(fn()=>rapor_save($db,['details_ready'=>'1','id'=>$r['id'],'version'=>rapor_version($r)],1));
$batch=['batch_complete'=>'1','kelas_id'=>1,'tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS'];foreach(['prepare','attendance','extras'] as $action)reject(fn()=>rapor_batch($db,$batch+['batch_action'=>$action],1));
reject(fn()=>rapor_judul_save($db,['kelas_id'=>1,'tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS'],1));
$data['settings_version']=rapor_admin_version(rapor_admin_load($db,1,'2026/2027','Ganjil','UTS'));reject(fn()=>rapor_admin_save($db,$data,1));
$unlock=$data;$unlock['admin_action']='unlock';rapor_admin_save($db,$unlock,1);rapor_admin_assert_open($db,1,'2026/2027','Ganjil','UTS');ck(!$db->inTransaction(),'Transactions closed');
$activities=array_count_values(array_column(rapor_history_list($db,1,'2026/2027','Ganjil','UTS'),'tindakan'));ck(($activities['lock']??0)===1&&($activities['unlock']??0)===1&&($activities['headers']??0)===2,'Admin locks, unlocks and header updates recorded');
require 'config/auth.php';
$db->exec('CREATE TEMPORARY TABLE roles(id INT,nama_role VARCHAR(50))');$db->exec("INSERT INTO roles VALUES(1,'Admin')");
$db->exec('CREATE TEMPORARY TABLE users(id INT,username VARCHAR(50),role_id INT,is_active INT,password VARCHAR(100))');$db->exec("INSERT INTO users VALUES(777,'uji',1,1,'hash')");
$_SESSION['user_id']=777;$_SESSION['role_id']=1;$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/admin/rapor.php';$_GET=['kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS'];
ob_start();require 'admin/rapor.php';$html=ob_get_clean();ck(str_contains($html,'id="admin-class-detail"')&&str_contains($html,'name="admin_action" value="settings"')&&str_contains($html,'Progres per kelas'),'Admin class page renders management and monitoring');ck(!str_contains($html,'id="rapor-wizard"'),'Admin landing uses monitoring rather than teacher wizard');
$db->exec('UPDATE siswa SET kelas_id=2 WHERE id=2');$_GET['kelas_id']='2';ob_start();require 'admin/rapor.php';$html=ob_get_clean();ck($summary[2]['belum_dibuat']===1&&str_contains($html,'Periksa arsip kelas sebelumnya'),'Transferred students retain old reports without inflating new class completion');
session_destroy();echo 'PASS';
?>`;
const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',['-d','session.save_path='+require('node:os').tmpdir()],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});
assert.equal(result.status,0,result.stderr+result.stdout);assert.equal(result.stdout,'PASS');
});
