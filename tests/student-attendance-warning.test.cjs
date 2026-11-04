const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{cwd:require('node:path').resolve(__dirname,'..'),encoding:'utf8',input:`<?php
require 'config/database.php';require 'includes/student_attendance_warning.php';
$db=Database::getInstance();
// Temporary tables are connection-local and leave application data untouched.
$db->exec('CREATE TEMPORARY TABLE mapel(id int PRIMARY KEY,nama_mapel varchar(100))');
$db->exec('CREATE TEMPORARY TABLE pengajaran(id int PRIMARY KEY,mapel_id int,semester varchar(10),tahun_ajaran varchar(20))');
$db->exec('CREATE TEMPORARY TABLE sesi_absensi(id int PRIMARY KEY,pengajaran_id int,tanggal date)');
$db->exec('CREATE TEMPORARY TABLE detail_absensi(sesi_absensi_id int,siswa_id int,status varchar(20))');
$db->exec("INSERT INTO mapel VALUES(1,'Mikrotik'),(2,'Bahasa')");
$db->exec("INSERT INTO pengajaran VALUES(1,1,'Ganjil','2026/2027'),(2,2,'Ganjil','2026/2027'),(3,1,'Genap','2025/2026'),(4,1,'Ganjil','2026/2027')");
for($i=1;$i<=12;$i++)$db->exec("INSERT INTO sesi_absensi VALUES($i,".($i<=4?1:($i<=6?2:($i<=9?3:4))).",'".($i>=7&&$i<=9?'2026-03-01':'2026-10-01')."')");
$db->exec("INSERT INTO detail_absensi VALUES(1,1,'Alpa'),(2,1,'Alpa'),(3,1,'Hadir'),(4,1,'Izin'),(5,1,'Alpa'),(6,1,'Alpa'),(7,1,'Alpa'),(8,1,'Alpa'),(9,1,'Alpa'),(1,2,'Alpa'),(2,2,'Alpa'),(3,2,'Alpa')");
$now=new DateTimeImmutable('2026-11-04',new DateTimeZone('Asia/Jakarta'));
if(student_attendance_warnings($db,1,$now)!==[])throw new RuntimeException('Two absences must not warn');
$db->exec("INSERT INTO detail_absensi VALUES(10,1,'Alpa')");
$rows=student_attendance_warnings($db,1,$now);
if(count($rows)!==1||(int)$rows[0]['jumlah_alpa']!==3||$rows[0]['nama_mapel']!=='Mikrotik')throw new RuntimeException('Threshold or subject aggregation failed');
$db->exec("INSERT INTO detail_absensi VALUES(11,1,'Alpa')");
if((int)student_attendance_warnings($db,1,$now)[0]['jumlah_alpa']!==4)throw new RuntimeException('Above threshold failed');
if(student_attendance_warnings($db,999,$now)!==[])throw new RuntimeException('Student isolation failed');
$db->exec("UPDATE detail_absensi SET status='Sakit' WHERE siswa_id=1 AND sesi_absensi_id IN(10,11)");
if(student_attendance_warnings($db,1,$now)!==[])throw new RuntimeException('Correction must clear warning');
foreach(['2026-01-01'=>['Genap','2025/2026'],'2026-06-30'=>['Genap','2025/2026'],'2026-07-01'=>['Ganjil','2026/2027']] as $date=>$expected){$p=student_attendance_period(new DateTimeImmutable($date));if([$p['semester'],$p['tahun_ajaran']]!==$expected)throw new RuntimeException('Semester boundary failed');}
echo 'PASS: threshold 3+, subject aggregation, semester/year, student isolation, corrections and semester boundaries.';
`});
assert.equal(result.status,0,result.stderr+result.stdout);console.log(result.stdout);
