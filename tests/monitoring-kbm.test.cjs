const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
const vm=require('node:vm');
test('KBM migration and data rules on isolated MariaDB',()=>{
 const php=String.raw`<?php
require 'includes/monitoring_kbm_admin.php';
function ok($v,$m){if(!$v)throw new RuntimeException($m);}
function reject($fn){try{$fn();}catch(InvalidArgumentException|PDOException|RuntimeException $e){return;}throw new RuntimeException('Invalid operation accepted');}
$db=new PDO('mysql:host=127.0.0.1','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$marker=sys_get_temp_dir().'/lms-akademik-test-path.txt';
if(!is_file($marker))throw new RuntimeException('Isolated test database marker missing');
$expected=trim(file_get_contents($marker));$actual=$db->query('SELECT @@datadir')->fetchColumn();
ok(strcasecmp(rtrim(str_replace('\\','/',$expected),'/'),rtrim(str_replace('\\','/',$actual),'/'))===0,'Refusing non-isolated database');
$name='lms_kbm_test_'.bin2hex(random_bytes(5));$db->exec("CREATE DATABASE $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE $name");
foreach([
'users'=>'id INT UNSIGNED PRIMARY KEY,role_id TINYINT UNSIGNED,is_active TINYINT',
'kelas'=>'id INT UNSIGNED PRIMARY KEY,nama_kelas VARCHAR(50)',
'guru'=>'id INT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED,nama_lengkap VARCHAR(100)',
'siswa'=>'id INT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED,kelas_id INT UNSIGNED,nama_lengkap VARCHAR(100)',
'mapel'=>'id INT UNSIGNED PRIMARY KEY,nama_mapel VARCHAR(100)',
'pengajaran'=>"id INT UNSIGNED PRIMARY KEY,guru_id INT UNSIGNED,mapel_id INT UNSIGNED,kelas_id INT UNSIGNED,tahun_ajaran VARCHAR(10),semester ENUM('Ganjil','Genap')"
] as $t=>$columns)$db->exec("CREATE TABLE $t($columns) ENGINE=InnoDB");
$db->exec("INSERT INTO users VALUES(1,1,1),(2,2,1),(3,3,1),(4,3,1),(5,5,1),(6,1,0);INSERT INTO kelas VALUES(1,'X A'),(2,'X B');INSERT INTO guru VALUES(1,2,'Guru Awal');INSERT INTO siswa VALUES(1,3,1,'Ketua A'),(2,4,2,'Ketua B');INSERT INTO mapel VALUES(1,'Mapel Awal');INSERT INTO pengajaran VALUES(1,1,1,1,'2026/2027','Ganjil'),(2,1,1,2,'2026/2027','Ganjil'),(3,1,1,1,'2026/2027','Genap')");
$sql=file_get_contents('database/2026_10_16_monitoring_kbm_data.sql');$db->exec($sql);$db->exec($sql);
$adminSql=file_get_contents('database/2026_10_16_monitoring_kbm_admin.sql');$db->exec($adminSql);$db->exec($adminSql);ok(kbm_admin_ready($db),'Admin migration ready');
ok((int)$db->query('SELECT COUNT(*) FROM kbm_sesi_sekolah')->fetchColumn()===2,'Idempotent migration');ok((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===6,'No default account');ok($db->query('SELECT @@session.time_zone')->fetchColumn()==='+07:00','Timezone');
$p=kbm_configure($db,1,'periode',['tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','mulai'=>'2026-07-01','selesai'=>'2026-12-31']);
foreach([2,3,5,6] as $actor)reject(fn()=>kbm_configure($db,$actor,'periode',[]));reject(fn()=>kbm_date('2026-02-30'));
$range=['periode_id'=>$p,'berlaku_mulai'=>'2026-07-01','berlaku_sampai'=>'2026-12-31'];$slots=[];
foreach([1=>['07:00:00','07:40:00'],2=>['07:40:00','08:20:00'],3=>['08:40:00','09:20:00'],4=>['09:20:00','10:00:00'],5=>['10:00:00','10:40:00'],6=>['10:40:00','11:20:00']] as $jp=>$time){if($jp===3)$slots[]=['jenis'=>'ISTIRAHAT','jp'=>null,'mulai'=>'08:20:00','selesai'=>'08:40:00'];$slots[]=['jenis'=>'JP','jp'=>$jp,'mulai'=>$time[0],'selesai'=>$time[1]];}
$patternData=$range+['sesi_kode'=>'pagi','hari'=>5,'slots'=>$slots];$pattern=kbm_configure($db,1,'pola_waktu',$patternData);reject(fn()=>kbm_configure($db,1,'pola_waktu',$patternData));reject(fn()=>kbm_validate_slots(array_slice($slots,1)));
$ks=kbm_configure($db,1,'kelas_sesi',$range+['kelas_id'=>1,'sesi_kode'=>'pagi']);$ks2=kbm_configure($db,1,'kelas_sesi',$range+['kelas_id'=>2,'sesi_kode'=>'pagi']);reject(fn()=>kbm_configure($db,1,'kelas_sesi',$range+['kelas_id'=>1,'sesi_kode'=>'siang']));
$chair=kbm_configure($db,1,'ketua_kelas',$range+['kelas_id'=>1,'siswa_id'=>1]);ok(kbm_chair_permission($db,3,$p,1)!==null,'Chair permission');ok(kbm_chair_permission($db,4,$p,1)===null,'Wrong class permission');reject(fn()=>kbm_configure($db,1,'ketua_kelas',$range+['kelas_id'=>2,'siswa_id'=>1]));
$schedule=['kelas_sesi_id'=>$ks,'pola_id'=>$pattern,'pengajaran_id'=>1,'jp_mulai'=>1,'jp_selesai'=>2,'berlaku_mulai'=>$range['berlaku_mulai'],'berlaku_sampai'=>$range['berlaku_sampai']];
kbm_create_schedule($db,1,$schedule);reject(fn()=>kbm_create_schedule($db,1,array_replace($schedule,['jp_mulai'=>2,'jp_selesai'=>3])));reject(fn()=>kbm_create_schedule($db,1,$schedule));reject(fn()=>kbm_create_schedule($db,1,array_replace($schedule,['kelas_sesi_id'=>$ks2,'pengajaran_id'=>2])));reject(fn()=>kbm_create_schedule($db,1,array_replace($schedule,['pengajaran_id'=>3,'jp_mulai'=>3,'jp_selesai'=>4])));kbm_create_schedule($db,1,array_replace($schedule,['jp_mulai'=>3,'jp_selesai'=>4]));
$day='2026-10-16';ok(count(kbm_day_report($db,$day))===2,'Unreported schedules included');ok((int)$db->query('SELECT COUNT(*) FROM kbm_pertemuan')->fetchColumn()===0,'Read-only report');
kbm_materialize($db,1,$day);kbm_materialize($db,1,$day);ok((int)$db->query('SELECT COUNT(*) FROM kbm_pertemuan')->fetchColumn()===2,'Unique meetings');ok((int)$db->query('SELECT COUNT(*) FROM kbm_laporan')->fetchColumn()===0,'No fake checkin');$meeting=(int)$db->query('SELECT MIN(id) FROM kbm_pertemuan')->fetchColumn();
$db->exec("UPDATE guru SET nama_lengkap='Nama Baru';UPDATE kelas SET nama_kelas='Kelas Baru';UPDATE mapel SET nama_mapel='Mapel Baru'");kbm_materialize($db,1,$day);ok(kbm_day_report($db,$day)[0]['nama_guru']==='Guru Awal','Meeting snapshot');ok(kbm_expected($db,'2026-10-23')[0]['nama_kelas']==='X A','Schedule snapshot');
$db->prepare('INSERT INTO kbm_laporan(pertemuan_id,checkin_at,checkin_siswa_id,checkin_ketua_id,nama_pelapor_masuk) VALUES(?,?,?,?,?)')->execute([$meeting,$day.' 07:02:00',1,$chair,'Ketua A']);reject(fn()=>$db->prepare('UPDATE kbm_laporan SET checkout_at=? WHERE pertemuan_id=?')->execute([$day.' 06:00:00',$meeting]));
kbm_correct($db,1,$meeting,$day.' 07:01:00',$day.' 08:20:00','Verifikasi admin');ok(kbm_day_report($db,$day)[0]['checkout_efektif']===$day.' 08:20:00','Correction applied');kbm_correct($db,1,$meeting,null,null,'Batalkan waktu salah');ok(kbm_day_report($db,$day)[0]['checkin_efektif']===null,'Explicit NULL correction');ok($db->query('SELECT checkin_at FROM kbm_laporan')->fetchColumn()===$day.' 07:02:00','Raw report retained');ok((int)$db->query('SELECT COUNT(*) FROM kbm_koreksi_admin')->fetchColumn()===2,'Correction history');reject(fn()=>kbm_correct($db,5,$meeting,null,null,'Forbidden'));
kbm_configure($db,1,'hari_tanpa_kbm',['periode_id'=>$p,'tanggal'=>'2026-10-23','alasan'=>'Libur sekolah']);ok(count(kbm_expected($db,'2026-10-23'))===0,'Holiday');ok(count(kbm_day_report($db,'2026-10-17'))===0,'No Saturday schedule');kbm_revoke_chair($db,1,$chair);ok(kbm_chair_permission($db,3,$p,1)===null,'Revoke immediately');ok((int)$db->query('SELECT COUNT(*) FROM kbm_laporan')->fetchColumn()===1,'Keep old reports');reject(fn()=>$db->exec('DELETE FROM kbm_jadwal'));
kbm_configure($db,1,'periode',['tahun_ajaran'=>'2026/2027','semester'=>'Genap','mulai'=>'2027-01-01','selesai'=>'2027-06-30']);ok(count(kbm_day_report($db,$day))===2,'Semester history retained');echo 'KBM checks passed';
// Admin settings: validated inputs, atomic revisions, future-only changes, archived meetings.
reject(fn()=>kbm_admin_submit($db,1,['action'=>'kelas_sesi','periode_id'=>['bad']]));
reject(fn()=>kbm_admin_time('25:00'));reject(fn()=>kbm_admin_time(''));
reject(fn()=>kbm_change_schedule($db,1,1,$day,'Backdated',null));
$edit=array_replace($schedule,['jp_mulai'=>5,'jp_selesai'=>6]);
kbm_materialize($db,1,'2026-10-30');
$oldReport=kbm_day_report($db,$day);
$new=kbm_change_schedule($db,1,1,'2026-10-30','Pindah blok JP',$edit);
ok($new!==null,'Replacement schedule');ok(kbm_day_report($db,$day)===$oldReport,'Past records untouched');
$future=kbm_day_report($db,'2026-10-30');ok(count($future)===2,'Unstarted old meeting superseded, no duplicate');
ok(!array_filter($future,fn($r)=>(int)$r['jadwal_id']===1),'Old future block excluded');
ok((int)$db->query('SELECT COUNT(*) FROM kbm_perubahan_jadwal')->fetchColumn()===1,'Revision audited');
reject(fn()=>kbm_change_schedule($db,1,1,'2026-10-30','Duplicate revision',null));
reject(fn()=>kbm_change_schedule($db,1,2,'2026-10-30','Conflict rollback',array_replace($edit,['jp_mulai'=>5,'jp_selesai'=>6])));
ok($db->query('SELECT nonaktif_mulai FROM kbm_jadwal WHERE id=2')->fetchColumn()===null,'Failed revision rollback');
kbm_change_schedule($db,1,$new,'2026-11-06','Nonaktifkan versi baru',null);
ok(count(kbm_day_report($db,'2026-11-06'))===1,'Deactivate future schedule');
reject(fn()=>kbm_change_schedule($db,5,2,'2026-11-06','Academic rejected',null));
reject(fn()=>kbm_admin_submit($db,1,['action'=>'hari_tanpa_kbm','periode_id'=>(string)$p,'tanggal'=>$day,'alasan'=>'Retrospektif']));

// Edit/delete holidays: future only, scoped semester, admin authorization, raw history intact.
$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute(['2026-10-18 09:00:00']);
$beforeHistory=$db->query('SELECT * FROM kbm_pertemuan ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$h=kbm_configure($db,1,'hari_tanpa_kbm',['periode_id'=>$p,'tanggal'=>'2026-10-24','alasan'=>'Libur awal']);
$editHoliday=['action'=>'edit_hari_tanpa_kbm','id'=>(string)$h,'periode_id'=>(string)$p,'tanggal'=>'2026-10-25','kelas_id'=>'1','sesi_kode'=>'siang','alasan'=>'Kegiatan sekolah'];
kbm_admin_submit($db,1,$editHoliday);$changed=kbm_rows($db,'SELECT * FROM kbm_hari_tanpa_kbm WHERE id=?',[$h])[0];ok($changed['tanggal']==='2026-10-25'&&$changed['kelas_id']==1&&$changed['sesi_kode']==='siang'&&$changed['alasan']==='Kegiatan sekolah','Holiday edit');
foreach([2,3,5,6] as $actor)reject(fn()=>kbm_admin_submit($db,$actor,$editHoliday));
foreach([['tanggal'=>'2026-10-18'],['tanggal'=>'2027-01-01'],['tanggal'=>'2026-02-30'],['kelas_id'=>'9999'],['sesi_kode'=>'malam'],['alasan'=>''],['alasan'=>str_repeat('a',256)],['periode_id'=>'2']] as $invalid)reject(fn()=>kbm_admin_submit($db,1,array_replace($editHoliday,$invalid)));
kbm_admin_submit($db,1,array_replace($editHoliday,['kelas_id'=>'','sesi_kode'=>'']));$global=kbm_rows($db,'SELECT * FROM kbm_hari_tanpa_kbm WHERE id=?',[$h])[0];ok($global['kelas_id']===null&&$global['sesi_kode']===null,'Global holiday scope');
$past=kbm_configure($db,1,'hari_tanpa_kbm',['periode_id'=>$p,'tanggal'=>'2026-10-17','alasan'=>'Arsip libur']);
reject(fn()=>kbm_admin_submit($db,1,array_replace($editHoliday,['id'=>(string)$past])));
reject(fn()=>kbm_admin_submit($db,1,['action'=>'hapus_hari_tanpa_kbm','id'=>(string)$past,'periode_id'=>(string)$p]));
foreach([2,3,5,6] as $actor)reject(fn()=>kbm_admin_submit($db,$actor,['action'=>'hapus_hari_tanpa_kbm','id'=>(string)$h,'periode_id'=>(string)$p]));
kbm_admin_submit($db,1,['action'=>'hapus_hari_tanpa_kbm','id'=>(string)$h,'periode_id'=>(string)$p]);ok(!kbm_rows($db,'SELECT id FROM kbm_hari_tanpa_kbm WHERE id=?',[$h]),'Holiday delete');
reject(fn()=>kbm_admin_submit($db,1,['action'=>'hapus_hari_tanpa_kbm','id'=>(string)$h,'periode_id'=>(string)$p]));
ok($beforeHistory===$db->query('SELECT * FROM kbm_pertemuan ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Stored history unchanged');
// Leave one future row for the server-rendered actions/edit form checks.
$h=kbm_configure($db,1,'hari_tanpa_kbm',['periode_id'=>$p,'tanggal'=>'2026-12-25','alasan'=>'Libur untuk edit']);
echo ' Admin checks passed';
$db->exec("ALTER TABLE users ADD username VARCHAR(50) DEFAULT 'fixture',ADD password VARCHAR(255) DEFAULT '';ALTER TABLE siswa ADD nis VARCHAR(20) NULL;CREATE TABLE roles(id TINYINT UNSIGNED PRIMARY KEY,nama_role VARCHAR(50));INSERT INTO roles VALUES(1,'Admin'),(2,'Guru'),(3,'Siswa'),(5,'Akademik')");
$db->exec(file_get_contents('database/2026_10_16_monitoring_kbm_pilihan_guru.sql'));$db->exec(file_get_contents('database/2026_10_17_monitoring_kbm_tanpa_jam.sql'));$simpleSql=file_get_contents('database/2026_10_18_monitoring_kbm_sederhana.sql');$db->exec($simpleSql);$db->exec($simpleSql);
echo ' HOLIDAY_ID='.$h.' TEST_DB='.$name;
`;
 const r=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:php,cwd:path.join(__dirname,'..'),encoding:'utf8'});
 assert.equal(r.status,0,r.stdout+r.stderr);
 assert.match(r.stdout,/KBM checks passed/);
 assert.match(r.stdout,/Admin checks passed/);
 const database=r.stdout.match(/TEST_DB=(lms_kbm_test_[a-f0-9]+)/)[1];
 const page=String.raw`<?php
require 'config/database.php';
$db=new PDO('mysql:host=127.0.0.1;dbname='.getenv('KBM_TEST_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$actual=$db->query('SELECT @@datadir')->fetchColumn();$expected=trim(file_get_contents(sys_get_temp_dir().'/lms-akademik-test-path.txt'));
if(strcasecmp(rtrim(str_replace('\\','/',$actual),'/'),rtrim(str_replace('\\','/',$expected),'/'))!==0)throw new RuntimeException('Refusing non-test server');
$db->exec("SET time_zone='+07:00'");
if(getenv('KBM_REPORT_NOW'))$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute([getenv('KBM_REPORT_NOW')]);
$reflection=new ReflectionClass(Database::class);$instance=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('conn')->setValue($instance,$db);$reflection->getProperty('instance')->setValue(null,$instance);
session_save_path(sys_get_temp_dir());require 'config/session.php';
$_SESSION['user_id']=(int)(getenv('KBM_USER')?:1);$_SESSION['role_id']=[1=>1,2=>2,3=>3,5=>5][$_SESSION['user_id']];
$_GET=['tab'=>getenv('KBM_TAB'),'periode'=>'1','kelas'=>'1'];if(getenv('KBM_REPORT_QUERY'))$_GET=json_decode(getenv('KBM_REPORT_QUERY'),true);if(getenv('KBM_EDIT_HOLIDAY'))$_GET['edit_libur']=getenv('KBM_EDIT_HOLIDAY');$_SERVER['REQUEST_METHOD']=getenv('KBM_METHOD')?:'GET';$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF']='/admin/monitoring_kbm.php';
if($_SERVER['REQUEST_METHOD']==='POST')$_POST=['action'=>getenv('KBM_POST_ACTION')?:'periode','csrf_token'=>'invalid','tahun_ajaran'=>'2030/2031','semester'=>'Ganjil','mulai'=>'2030-07-01','selesai'=>'2030-12-31'];
$before=$db->query('SELECT COUNT(*) FROM kbm_periode')->fetchColumn();
if(getenv('KBM_MISSING'))$db->exec('ALTER TABLE kbm_jadwal CHANGE COLUMN nonaktif_mulai unavailable_column DATE NULL');
ob_start();register_shutdown_function(function()use($db,$before){$out=ob_get_clean();echo json_encode(['status'=>http_response_code()?:200,'html'=>$out,'error'=>$_SESSION['kbm_error']??'','unchanged'=>$before===$db->query('SELECT COUNT(*) FROM kbm_periode')->fetchColumn()]);session_destroy();});
require 'admin/monitoring_kbm.php';`;
 function render(env={}){
  const result=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:page,cwd:path.join(__dirname,'..'),encoding:'utf8',env:{...process.env,KBM_TEST_DB:database,...env}});
  assert.equal(result.status,0,result.stderr+result.stdout);assert.equal(result.stderr,'');return JSON.parse(result.stdout);
 }
 for(const tab of ['kelas','ketua','jadwal']){
  const view=render({KBM_TAB:tab});assert.equal(view.status,200);assert.match(view.html,/Pengaturan Monitoring KBM/);if(tab!=='jadwal')assert.match(view.html,/name="csrf_token"/);assert.doesNotMatch(view.html,/Tampilkan Periode|Tambah Periode|Simpan Pola Waktu|Simpan Hari Tanpa KBM|tab=libur|tab=waktu|tab=periode/);assert.doesNotMatch(view.html,/Fatal error|Warning:/);
  for(const match of view.html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))new vm.Script(match[1]);
 }
 for(const user of ['2','3','5'])assert.equal(render({KBM_USER:user,KBM_TAB:'jadwal'}).status,403);
 const csrf=render({KBM_TAB:'periode',KBM_METHOD:'POST'});assert.equal(csrf.status,302);assert.match(csrf.error,/Sesi keamanan/);assert.equal(csrf.unchanged,true);

 const removed=render({KBM_TAB:'libur'});assert.match(removed.html,/Simpan Sesi Kelas/);assert.doesNotMatch(removed.html,/hapus_hari_tanpa_kbm/);
 for(const [mode,start,end] of [['harian','2026-10-16','2026-10-16'],['mingguan','2026-10-12','2026-10-18'],['bulanan','2026-10-01','2026-10-31']]){
  const report=render({KBM_REPORT_NOW:'2026-11-01 09:00:00',KBM_REPORT_QUERY:JSON.stringify({tab:'jadwal',periode_laporan:mode,tanggal:'2026-10-16'})});
  assert.equal(report.status,200);assert.doesNotMatch(report.html,/Fatal error|Warning:/);assert.match(report.html,/Unduh Excel/);assert.match(report.html,/Jumat/);assert.match(report.html,new RegExp('tanggal_mulai='+start+'&amp;tanggal_selesai='+end));assert.match(report.html,/Check-in \(WIB\)/);
 }
 const workbook=page.slice(0,page.indexOf('$reflection='))+String.raw`
require 'includes/monitoring_kbm_report.php';require 'includes/monitoring_kbm_export.php';require 'includes/nilai_excel_reader.php';
foreach(['harian','mingguan','bulanan'] as $mode){
 $period=kbm_report_period(['periode_laporan'=>$mode,'tanggal'=>'2026-10-16'],'2026-11-01');
 $filters=['tanggal_mulai'=>$period['tanggal_mulai'],'tanggal_selesai'=>$period['tanggal_selesai']];
 $history=kbm_history_page($db,$filters);$book=kbm_history_export_file($db,$filters);$xml=stream_get_contents($book['file']);fclose($book['file']);
 $doc=ni_xml($xml);$xp=new DOMXPath($doc);$xp->registerNamespace('s','urn:schemas-microsoft-com:office:spreadsheet');
 if($book['count']!==$history['total']||$xp->query('//s:Row')->length!==$book['count']+5)throw new RuntimeException('Export row count mismatch');
 foreach($xp->query('//s:Row[position()>5]') as $row){$cells=$xp->query('s:Cell',$row);if($cells->length!==24)throw new RuntimeException('Missing columns');$date=substr($cells->item(2)->textContent,0,10);if($date<$filters['tanggal_mulai']||$date>$filters['tanggal_selesai'])throw new RuntimeException('Filter mismatch');}
 if($xp->query('//@s:Formula')->length)throw new RuntimeException('Formula found');
 $temp=tempnam(sys_get_temp_dir(),'kbm-report-');try{file_put_contents($temp,$xml);$parsed=ni_read_excel($temp);if(count($parsed['rows'])!==$book['count']+5)throw new RuntimeException('Readback mismatch');}finally{unlink($temp);}
}
echo 'Daily weekly monthly workbook counts, dates and readback passed';`;
 const exported=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:workbook,cwd:path.join(__dirname,'..'),encoding:'utf8',env:{...process.env,KBM_TEST_DB:database,KBM_REPORT_NOW:'2026-11-01 09:00:00'}});
 assert.equal(exported.status,0,exported.stdout+exported.stderr);assert.equal(exported.stderr,'');assert.match(exported.stdout,/workbook counts, dates and readback passed/);
 const badReport=render({KBM_REPORT_QUERY:JSON.stringify({tab:'jadwal',tanggal:['bad']})});assert.match(badReport.html,/Pilihan periode laporan tidak valid/);
 const missing=render({KBM_TAB:'jadwal',KBM_MISSING:'1'});assert.match(missing.html,/Pengelola sistem perlu menerapkan pembaruan/);assert.doesNotMatch(missing.html,/name="action"/);
});
