const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');

test('Manual target grades, Excel and history feed task and exam averages without overwriting records',()=>{
const php=String.raw`<?php
require 'config/database.php';require 'config/helper.php';require 'includes/nilai_manual_target.php';require 'includes/penilaian_override.php';
$db=Database::getInstance();
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
function reject($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Unsafe input accepted');}
$tables=[
'users'=>'id INT PRIMARY KEY,role_id INT,is_active INT',
'guru'=>'id INT PRIMARY KEY,user_id INT,nama_lengkap VARCHAR(100)',
'kelas'=>'id INT PRIMARY KEY,nama_kelas VARCHAR(30)',
'mapel'=>'id INT PRIMARY KEY,nama_mapel VARCHAR(100)',
'pengajaran'=>'id INT PRIMARY KEY,guru_id INT,kelas_id INT,mapel_id INT,tahun_ajaran VARCHAR(20),semester VARCHAR(20),kkm DECIMAL(5,2)',
'siswa'=>'id INT PRIMARY KEY,kelas_id INT,nis VARCHAR(30),nisn VARCHAR(30),nama_lengkap VARCHAR(100)',
'materi'=>'id INT PRIMARY KEY,pengajaran_id INT,pertemuan_ke INT,judul VARCHAR(200)',
'tugas'=>'id INT PRIMARY KEY,pengajaran_id INT,judul VARCHAR(100),pertemuan_ke INT,deadline DATETIME',
'pengumpulan_tugas'=>'id INT AUTO_INCREMENT PRIMARY KEY,tugas_id INT,siswa_id INT,nilai DECIMAL(5,2),catatan_guru TEXT,file_tugas VARCHAR(255),catatan TEXT,dikumpulkan_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY(tugas_id,siswa_id)',
'rekap_tugas_status'=>'tugas_id INT,siswa_id INT,guru_id INT,status VARCHAR(30),catatan VARCHAR(1000),updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(tugas_id,siswa_id)',
'remedial_tugas'=>'id INT PRIMARY KEY,tugas_id INT,siswa_id INT,guru_id INT,nilai_awal DECIMAL(5,2),nilai_remedial DECIMAL(5,2),catatan TEXT',
'remedial_tugas_batal'=>'remedial_id INT PRIMARY KEY,dibatalkan_pada TIMESTAMP,alasan TEXT',
'nilai_tugas_manual'=>'id INT PRIMARY KEY,pengajaran_id INT,siswa_id INT,pertemuan_ke INT,nilai DECIMAL(5,2),catatan TEXT',
'ujian'=>'id INT PRIMARY KEY,pengajaran_id INT,nama_ujian VARCHAR(100),jenis_ujian VARCHAR(20),waktu_selesai DATETIME',
'nilai_ujian'=>'id INT AUTO_INCREMENT PRIMARY KEY,ujian_id INT,siswa_id INT,nilai_pg DECIMAL(5,2) DEFAULT 0,nilai_esai DECIMAL(5,2) DEFAULT 0,nilai_total DECIMAL(5,2),selesai_pada DATETIME,UNIQUE KEY(ujian_id,siswa_id)',
'sesi_ujian'=>'id INT PRIMARY KEY,ujian_id INT,siswa_id INT,status VARCHAR(30)',
'sesi_absensi'=>'id INT,pengajaran_id INT,status VARCHAR(20)',
'detail_absensi'=>'sesi_absensi_id INT,siswa_id INT,status VARCHAR(20)',
'komponen_penilaian'=>'id INT PRIMARY KEY,pengajaran_id INT,nama_komponen VARCHAR(100),bobot DECIMAL(5,2),urutan INT',
'nilai_komponen'=>'siswa_id INT,komponen_id INT,nilai DECIMAL(5,2)',
'riwayat_nilai'=>'komponen_id INT',
'capaian_penilaian'=>'komponen_id INT',
'log_aktivitas'=>'user_id INT,aktivitas VARCHAR(255),ip_address VARCHAR(45),user_agent TEXT'
];
foreach($tables as $name=>$schema)$db->exec("CREATE TEMPORARY TABLE $name ($schema) ENGINE=InnoDB");
$db->exec("INSERT INTO users VALUES(1,2,1),(2,2,1),(3,3,1);INSERT INTO guru VALUES(1,1,'Guru A'),(2,2,'Guru B');INSERT INTO kelas VALUES(1,'X A'),(2,'X B');INSERT INTO mapel VALUES(1,'Algoritma');INSERT INTO pengajaran VALUES(1,1,1,1,'2026/2027','Ganjil',75),(2,2,2,1,'2026/2027','Ganjil',75);INSERT INTO siswa VALUES(1,1,'001','001','Siswa A'),(2,1,'002','002','Siswa B'),(3,2,'003','003','Siswa C');INSERT INTO materi VALUES(1,1,1,'Pengenalan Algoritma'),(2,1,2,'Percabangan');INSERT INTO tugas VALUES(1,1,'Praktik',1,DATE_SUB(NOW(),INTERVAL 1 DAY)),(2,1,'Essai',2,DATE_SUB(NOW(),INTERVAL 1 DAY)),(3,2,'Asing',1,DATE_SUB(NOW(),INTERVAL 1 DAY));INSERT INTO pengumpulan_tugas(tugas_id,siswa_id,nilai,file_tugas,catatan) VALUES(1,1,80,'asli.pdf','Jawaban asli'),(2,1,NULL,'essai.pdf','Essai asli');INSERT INTO ujian VALUES(1,1,'Ujian Algoritma','Kuis',DATE_SUB(NOW(),INTERVAL 1 DAY)),(2,1,'Ujian Berikutnya','Kuis',DATE_ADD(NOW(),INTERVAL 1 DAY)),(3,1,'UTS','UTS',DATE_SUB(NOW(),INTERVAL 1 DAY));INSERT INTO komponen_penilaian VALUES(1,1,'Tugas Harian',50,1),(2,1,'Ulangan Harian',50,2);");
$c=nm_context($db,1,1,'tugas');check($c['targets'][1]['label']==='Tugas 2 — Essai','Task numbering mismatch');check($c['meetings'][1][0]==='Pengenalan Algoritma','Meeting topic missing');
reject(fn()=>nm_context($db,2,1,'tugas'));reject(fn()=>nm_target($c,3));
check(po_automatic($db,1,1)['Tugas Harian']===80.0,'Pending task treated as zero');
$r=nm_save($db,1,1,1,'tugas',1,[1=>'99',2=>'0']);check($r===['saved'=>1,'skipped'=>1],'Saved grade overwritten or zero skipped');
check($db->query('SELECT COUNT(*) FROM nilai_tugas_manual')->fetchColumn()===0,'Target grade duplicated into legacy table');
reject(fn()=>nm_save($db,1,1,1,'tugas',2,[1=>'90',3=>'50']));
check($db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=2 AND siswa_id=1')->fetchColumn()===null,'Invalid batch partially applied');
reject(fn()=>nm_save($db,1,3,1,'tugas',2,[1=>'90']));
nm_save($db,1,1,1,'tugas',2,[1=>'100']);
$row=$db->query('SELECT * FROM pengumpulan_tugas WHERE tugas_id=2 AND siswa_id=1')->fetch();check($row['file_tugas']==='essai.pdf'&&$row['catatan']==='Essai asli','Answer lost during manual grade');
check(po_automatic($db,1,1)['Tugas Harian']===90.0,'Task average incorrect');
$c=nm_context($db,1,1,'ujian');check(count($c['targets'])===2,'UTS included in daily exam targets');
reject(fn()=>nm_save($db,1,1,1,'ujian',2,[1=>'90']));reject(fn()=>nm_save($db,1,1,1,'ujian',3,[1=>'90']));reject(fn()=>nm_save($db,1,1,1,'ujian',1,[3=>'90']));
nm_save($db,1,1,1,'ujian',1,[1=>'80']);check(po_automatic($db,1,1)['Ulangan Harian']===80.0,'Exam average not updated');
$db->exec("INSERT INTO sesi_ujian VALUES(1,1,2,'Berlangsung')");reject(fn()=>nm_save($db,1,1,1,'ujian',1,[2=>'70']));$db->exec('DELETE FROM sesi_ujian');
$r=nm_save($db,1,1,1,'ujian',1,[1=>'99']);check($r['skipped']===1,'Exam grade overwritten');
$xml=nm_template($c,1);$tmp=tempnam(sys_get_temp_dir(),'nm_excel_');file_put_contents($tmp,$xml);try{$rows=ni_read_excel($tmp)['rows'];}finally{unlink($tmp);}
check(count($rows[2])===5&&$rows[4][1]==='001','Target template or leading zero lost');
$rows[4][4]='90';$rows[5][4]='0';$preview=nm_preview(nm_context($db,1,1,'ujian'),1,$rows);check(!$preview[0]['ready']&&$preview[1]['ready']&&$preview[1]['score']===0.0,'Import readiness incorrect');
reject(fn()=>nm_preview($c,2,$rows));$wrong=$rows;$wrong[1][1]='tugas';reject(fn()=>nm_preview($c,1,$wrong));$wrong=$rows;$wrong[5][4]='101';reject(fn()=>nm_preview($c,1,$wrong));
$values=[];foreach($preview as $r)if($r['ready'])$values[$r['sid']]=(string)$r['score'];
nm_save($db,1,1,1,'ujian',1,[2=>'70']);$r=nm_save($db,1,1,1,'ujian',1,$values);check($r['skipped']===1,'Changed score overwritten after preview');
$db->exec('UPDATE log_aktivitas SET user_id=-1');$db->exec('ALTER TABLE log_aktivitas ADD CONSTRAINT nm_fail_log CHECK(user_id<0)');reject(fn()=>nm_save($db,1,1,1,'tugas',2,[2=>'55']));check(!$db->inTransaction(),'Transaction left open');check(!$db->query('SELECT id FROM pengumpulan_tugas WHERE tugas_id=2 AND siswa_id=2')->fetchColumn(),'Audit failure partially saved grade');$db->exec('ALTER TABLE log_aktivitas DROP CONSTRAINT nm_fail_log');
$db->exec("INSERT INTO nilai_tugas_manual VALUES(1,1,1,3,60,'Arsip');");
check(abs(po_automatic($db,1,1)['Tugas Harian']-80)<.01,'Legacy grade no longer counted');
$db->exec("ALTER TABLE tugas MODIFY id INT AUTO_INCREMENT,ADD deskripsi TEXT,ADD jenis_tugas VARCHAR(30) DEFAULT 'portofolio',ADD metode_pengumpulan VARCHAR(20) DEFAULT 'online';ALTER TABLE ujian MODIFY id INT AUTO_INCREMENT,ADD durasi_menit INT DEFAULT 60,ADD waktu_mulai DATETIME,ADD acak_soal INT DEFAULT 1;");
$when=date('Y-m-d\TH:i',time()-3600);
reject(fn()=>nm_create_target($db,2,2,1,'tugas','Asing',1,$when));
reject(fn()=>nm_create_target($db,1,3,1,'tugas','Aktor siswa',1,$when));
reject(fn()=>nm_create_target($db,1,1,1,'tugas','',1,$when));
reject(fn()=>nm_create_target($db,1,1,1,'tugas','Invalid meeting',-1,$when));
reject(fn()=>nm_create_target($db,1,1,1,'ujian','Future',1,date('Y-m-d\TH:i',time()+86400)));
$createdTask=nm_create_target($db,1,1,1,'tugas','Latihan buku',0,$when);
$createdExam=nm_create_target($db,1,1,1,'ujian','Ujian kelas',1,$when);
$record=$db->query('SELECT * FROM tugas WHERE id='.$createdTask)->fetch();check($record['metode_pengumpulan']==='offline'&&$record['pertemuan_ke']===0,'Manual target became an online assignment');
$record=$db->query('SELECT * FROM ujian WHERE id='.$createdExam)->fetch();check(strtotime($record['waktu_selesai'])<=time()&&strtotime($record['waktu_mulai'])<strtotime($record['waktu_selesai']),'Manual exam became a live session');
$createdContext=nm_context($db,1,1,'tugas');$created=nm_target($createdContext,$createdTask);check($created['nomor']===3,'New classroom task did not continue class task numbering');
nm_save($db,1,1,1,'tugas',$createdTask,[1=>'90']);nm_save($db,1,1,1,'ujian',$createdExam,[1=>'75']);
check(nm_context($db,1,1,'tugas')['scores'][1][$createdTask]['nilai']==='90.00','New manual task grade missing');
$db->exec('DELETE FROM pengumpulan_tugas WHERE tugas_id='.$createdTask.';DELETE FROM rekap_tugas_status WHERE tugas_id='.$createdTask.';DELETE FROM tugas WHERE id='.$createdTask.';DELETE FROM nilai_ujian WHERE ujian_id='.$createdExam.';DELETE FROM ujian WHERE id='.$createdExam);
ini_set('session.save_path',sys_get_temp_dir());require 'config/session.php';$_SESSION['user_id']=1;$_SESSION['role_id']=2;$_SESSION['username']='Guru A';$_SESSION['milad_announcement_shown']=true;$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/lms/guru/nilai_manual.php';$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];$_GET=['pengajaran_id'=>1,'jenis'=>'tugas','target_id'=>2];
set_error_handler(function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);});
ob_start();require 'guru/nilai_manual.php';$html=ob_get_clean();
check(!str_contains($html,'Riwayat penilaian tugas')&&str_contains($html,'Percabangan')&&str_contains($html,'Arsip nilai manual lama'),'Task history must be hidden while existing topic and archive remain');
preg_match('~<select id="nmTarget"[^>]*>(.*?)</select>~s',$html,$taskSelect);
check(!str_contains($html,'id="pertemuanManual"'),'Manual task creation still requires lesson selection');
check(!str_contains($html,'id="nmPertemuan"')&&substr_count($html,'name="target_id" class="form-select"')===1,'Duplicate meeting/task selectors remain');
check(str_contains($taskSelect[1],'Tugas 1')&&str_contains($taskSelect[1],'Praktik')&&str_contains($taskSelect[1],'Tugas 2')&&str_contains($taskSelect[1],'Essai'),'Task numbers or titles missing');
$_GET=['pengajaran_id'=>1,'jenis'=>'tugas','target_id'=>2,'pertemuan'=>1];ob_start();require 'guru/nilai_manual.php';$oldLinkHtml=ob_get_clean();
preg_match('~<select id="nmTarget"[^>]*>(.*?)</select>~s',$oldLinkHtml,$oldLinkSelect);
check(str_contains($oldLinkSelect[1],'value="2" selected')&&str_contains($oldLinkSelect[1],'Tugas 1'),'Old meeting parameter hides tasks or changes explicit selection');
check(str_contains($html,'Unduh Template Excel')&&str_contains($html,'Sudah dinilai')&&str_contains($html,'Belum dinilai'),'Input or import statuses missing');
file_put_contents(sys_get_temp_dir().'/lms_manual_target_preview.html',str_replace('<head>','<head><base href="file:///C:/xampp/htdocs/lms/guru/">',$html));
$_GET=['pengajaran_id'=>1];ob_start();require 'guru/nilai_manual.php';$autoHtml=ob_get_clean();check(str_contains($autoHtml,'Daftar nilai siswa')&&str_contains($autoHtml,'Buat Target dan Mulai Menilai')&&$id>0,'Student list or auto-selected target is missing');
$db->exec("INSERT INTO pengajaran VALUES(3,1,2,1,'2026/2027','Ganjil',75)");
$_GET=['pengajaran_id'=>3];ob_start();require 'guru/nilai_manual.php';$emptyHtml=ob_get_clean();check(str_contains($emptyHtml,'Daftar nilai siswa')&&str_contains($emptyHtml,'Siswa C')&&str_contains($emptyHtml,'Belum ada target'),'Student list disappears when class has no tasks');
file_put_contents(sys_get_temp_dir().'/lms_manual_empty_class_preview.html',str_replace('<head>','<head><base href="file:///C:/xampp/htdocs/lms/guru/">',substr($html,0,strpos($html,'<link rel="stylesheet" href="../assets/css/nilai-manual.css')).$emptyHtml.'</div></body></html>'));
$_GET=['pengajaran_id'=>1];ob_start();require 'guru/rekap_nilai_guru.php';$recap=ob_get_clean();check(str_contains($recap,'80.00')&&str_contains($recap,'Nilai Akhir'),'Final grade recap did not render');
restore_error_handler();
$_SERVER['REQUEST_METHOD']='POST';$_POST=['pengajaran_id'=>3,'jenis'=>'tugas','target_id'=>0,'action'=>'create_target','csrf_token'=>$_SESSION['csrf_token'],'create_token'=>$_SESSION['manual_create_token'],'judul_manual'=>'Dibuat dari halaman manual','tanggal_manual'=>$when,'pertemuan_manual'=>11];
register_shutdown_function(function()use($db){$row=$db->query('SELECT metode_pengumpulan,pertemuan_ke FROM tugas WHERE pengajaran_id=3')->fetch();check($row&&$row['metode_pengumpulan']==='offline'&&(int)$row['pertemuan_ke']===0,'Create-target POST failed when no target existed');check(isset($_SESSION['manual_target_success']),'Create-target POST did not show success');session_destroy();echo 'PASS manual targets';});
require 'guru/nilai_manual.php';
`;
const result=spawnSync('C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),input:php,encoding:'utf8'});
assert.equal(result.status,0,result.stdout+result.stderr);assert.match(result.stdout,/PASS manual targets/);
});
