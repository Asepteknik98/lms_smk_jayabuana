// Connection-local temporary tables; school grades are never modified.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('UTS, UAS and practical grades share recap storage and safe Excel imports',()=>{
const php=String.raw`<?php
require 'config/database.php';require 'config/helper.php';require 'includes/nilai_manual_target.php';
$db=Database::getInstance();
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
function reject($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid operation accepted');}
$tables=[
'users'=>'id INT PRIMARY KEY,role_id INT,is_active INT',
'guru'=>'id INT PRIMARY KEY,user_id INT,nama_lengkap VARCHAR(100)',
'kelas'=>'id INT PRIMARY KEY,nama_kelas VARCHAR(30)',
'mapel'=>'id INT PRIMARY KEY,nama_mapel VARCHAR(100)',
'pengajaran'=>'id INT PRIMARY KEY,guru_id INT,kelas_id INT,mapel_id INT,semester VARCHAR(20),tahun_ajaran VARCHAR(20)',
'siswa'=>'id INT PRIMARY KEY,kelas_id INT,nis VARCHAR(30),nisn VARCHAR(30),nama_lengkap VARCHAR(100)',
'komponen_penilaian'=>'id INT PRIMARY KEY,pengajaran_id INT,nama_komponen VARCHAR(100),bobot DECIMAL(5,2)',
'nilai_komponen'=>'komponen_id INT,siswa_id INT,nilai DECIMAL(5,2),PRIMARY KEY(komponen_id,siswa_id)',
'riwayat_nilai'=>'pengajaran_id INT,siswa_id INT,komponen_id INT,guru_id INT,nilai_lama DECIMAL(5,2),nilai_baru DECIMAL(5,2)',
'nilai_tugas_manual'=>'pengajaran_id INT,siswa_id INT,pertemuan_ke INT,nilai DECIMAL(5,2),catatan TEXT'
];
foreach($tables as $name=>$cols)$db->exec("CREATE TEMPORARY TABLE $name ($cols) ENGINE=InnoDB");
$db->exec("INSERT INTO users VALUES(1,2,1),(2,2,1),(3,3,1);INSERT INTO guru VALUES(1,1,'Guru A'),(2,2,'Guru B');INSERT INTO kelas VALUES(1,'10 DPB 2'),(2,'10 DPB 3');INSERT INTO mapel VALUES(1,'Koding');INSERT INTO pengajaran VALUES(1,1,1,1,'Ganjil','2026/2027'),(2,2,2,1,'Ganjil','2026/2027');INSERT INTO siswa VALUES(1,1,'001','001','Andi'),(2,1,'002','002','Budi'),(3,2,'003','003','Citra');INSERT INTO komponen_penilaian VALUES(1,1,'UTS',30),(2,1,'UAS',30),(3,1,'Ujian Praktik',40),(4,2,'UTS',100)");
foreach(['uts'=>1,'uas'=>2,'praktik'=>3] as $kind=>$id){
 $c=nm_context($db,1,1,$kind);check(count($c['targets'])===1&&(int)$c['targets'][0]['id']===$id,'Wrong component selected');
 $r=nm_save($db,1,1,1,$kind,$id,[1=>'0',2=>'']);check($r===['saved'=>1,'skipped'=>0],'Zero or blank handling failed');
 $r=nm_save($db,1,1,1,$kind,$id,[1=>'99']);check($r['skipped']===1,'Existing grade overwritten');
 $c=nm_context($db,1,1,$kind);$file=tempnam(sys_get_temp_dir(),'component_excel_');file_put_contents($file,nm_template($c,$id));
 try{$rows=ni_read_excel($file)['rows'];}finally{unlink($file);}
 $rows[4][4]='50';$rows[5][4]='80';$preview=nm_preview($c,$id,$rows);
 check(!$preview[0]['ready']&&$preview[1]['ready'],'Excel readiness ignored existing grade');
 $wrong=$rows;$wrong[1][1]='tugas';reject(fn()=>nm_preview($c,$id,$wrong));
 nm_save($db,1,1,1,$kind,$id,[2=>'80']);
 check((float)$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id='.$id.' AND siswa_id=2')->fetchColumn()===80.0,'Recap storage not updated');
}
reject(fn()=>nm_context($db,2,1,'uts'));
reject(fn()=>nm_save($db,1,1,1,'uts',2,[2=>'90']));
reject(fn()=>nm_save($db,1,3,1,'uts',1,[2=>'90']));
reject(fn()=>nm_save($db,1,1,1,'uts',1,[3=>'90']));
reject(fn()=>nm_save($db,1,1,1,'uts',1,[1=>'101']));
$db->exec('DELETE FROM nilai_komponen WHERE komponen_id=1 AND siswa_id=2');
reject(fn()=>nm_save($db,1,1,1,'uts',1,[2=>'90',3=>'80']));
check(!$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=1 AND siswa_id=2')->fetch(),'Invalid batch partially saved');
$db->exec('UPDATE komponen_penilaian SET bobot=20 WHERE id=1');
check(!nm_context($db,1,1,'uts')['targets'][0]['selesai'],'Incomplete weights enabled inputs');
reject(fn()=>nm_save($db,1,1,1,'uts',1,[2=>'90']));
$db->exec('UPDATE komponen_penilaian SET bobot=30 WHERE id=1');
$db->exec('ALTER TABLE riwayat_nilai ADD CONSTRAINT reject_new_grade CHECK(nilai_baru<90)');
reject(fn()=>nm_save($db,1,1,1,'uts',1,[2=>'95']));
check(!$db->inTransaction()&&!$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=1 AND siswa_id=2')->fetch(),'History failure did not roll back');
ini_set('session.save_path',sys_get_temp_dir());require 'config/session.php';$_SESSION['user_id']=1;$_SESSION['role_id']=2;$_SESSION['username']='Guru A';$_SESSION['milad_announcement_shown']=true;
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/lms/guru/nilai_manual.php';$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];
set_error_handler(function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);});
foreach(['uts','uas','praktik'] as $kind){
 $_GET=['pengajaran_id'=>1,'jenis'=>$kind];ob_start();require 'guru/nilai_manual.php';$html=ob_get_clean();
 check(str_contains($html,'value="'.$kind.'" selected')&&str_contains($html,'Daftar nilai siswa'),'Component view not rendered');
 check(!str_contains($html,'id="buat-target-manual"'),'Component view offers task creation');
}
$db->exec('DELETE FROM nilai_komponen WHERE komponen_id=3;DELETE FROM komponen_penilaian WHERE id=3');
$_GET=['pengajaran_id'=>1,'jenis'=>'praktik'];ob_start();require 'guru/nilai_manual.php';$html=ob_get_clean();check(str_contains($html,'belum tersedia'),'Missing component guidance absent');
restore_error_handler();session_destroy();echo 'PASS component grades';
`;
const r=spawnSync('C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),input:php,encoding:'utf8'});
assert.equal(r.status,0,r.stderr+r.stdout);assert.match(r.stdout,/PASS component grades/);
});
