const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync,spawn}=require('node:child_process');
const path=require('node:path');

test('Simple monitoring starts without any period, time pattern or holiday configuration',async()=>{
 const code=String.raw`<?php
require 'includes/monitoring_kbm_pilihan.php';require 'includes/monitoring_kbm_history.php';
function ensure($ok,$message){if(!$ok)throw new RuntimeException($message);}
function denied($fn){try{$fn();}catch(InvalidArgumentException|RuntimeException $e){return;}throw new RuntimeException('Forbidden action accepted');}
$db=new PDO('mysql:host=127.0.0.1','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$marker=sys_get_temp_dir().'/lms-akademik-test-path.txt';$actual=$db->query('SELECT @@datadir')->fetchColumn();$expected=trim(file_get_contents($marker));
ensure(strcasecmp(rtrim(str_replace('\\','/',$actual),'/'),rtrim(str_replace('\\','/',$expected),'/'))===0,'Refusing non-isolated database');
$name='lms_kbm_simple_'.bin2hex(random_bytes(5));$db->exec("CREATE DATABASE $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE $name");
foreach([
'users'=>"id INT UNSIGNED PRIMARY KEY,role_id TINYINT UNSIGNED,is_active TINYINT,username VARCHAR(50)",
'kelas'=>'id INT UNSIGNED PRIMARY KEY,nama_kelas VARCHAR(50)',
'guru'=>'id INT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED,nama_lengkap VARCHAR(100)',
'siswa'=>'id INT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED,kelas_id INT UNSIGNED,nama_lengkap VARCHAR(100),nis VARCHAR(20)',
'mapel'=>'id INT UNSIGNED PRIMARY KEY,nama_mapel VARCHAR(100)',
'pengajaran'=>"id INT UNSIGNED PRIMARY KEY,guru_id INT UNSIGNED,mapel_id INT UNSIGNED,kelas_id INT UNSIGNED,tahun_ajaran VARCHAR(10),semester ENUM('Ganjil','Genap')"
] as $table=>$columns)$db->exec("CREATE TABLE $table($columns) ENGINE=InnoDB");
$db->exec("INSERT INTO users VALUES(1,1,1,'admin'),(2,2,1,'guru'),(3,3,1,'ketua'),(4,3,1,'biasa'),(5,5,1,'akademik');INSERT INTO kelas VALUES(1,'X A'),(2,'X B');INSERT INTO guru VALUES(1,2,'Guru A');INSERT INTO siswa VALUES(1,3,1,'Ketua A','001'),(2,4,2,'Ketua B','002')");
foreach(['2026_10_16_monitoring_kbm_data.sql','2026_10_16_monitoring_kbm_admin.sql','2026_10_16_monitoring_kbm_pilihan_guru.sql','2026_10_18_monitoring_kbm_sederhana.sql'] as $file)$db->exec(file_get_contents('database/'.$file));
$db->exec(file_get_contents('database/2026_10_18_monitoring_kbm_sederhana.sql'));
$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute(['2026-10-18 08:00:00']);
foreach(['kbm_periode','kbm_pola_waktu','kbm_slot_waktu','kbm_hari_tanpa_kbm'] as $table)ensure((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn()===0,'Unnecessary configuration created');
kbm_admin_submit($db,1,['action'=>'simple_sesi','kelas_id'=>'1','sesi_kode'=>'pagi']);
kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>'1']);
ensure(count(kbm_student_assignments($db,3))===1,'Chair authorization without period');
ensure(!kbm_student_assignments($db,4),'Ordinary student permission');
$input=['action'=>'pilih_guru','jp'=>'1','jp_selesai'=>'3','guru_id'=>'1'];
kbm_student_post($db,3,$input);$j=(int)$db->query('SELECT MAX(id) FROM kbm_jadwal')->fetchColumn();
ensure(count(kbm_expected($db,'2026-10-18'))===1,'Manual meeting without scheduled hours');
kbm_student_post($db,3,['action'=>'checkin','id'=>(string)$j]);kbm_student_post($db,3,['action'=>'checkin','id'=>(string)$j]);
$p=(int)$db->query('SELECT MAX(id) FROM kbm_pertemuan')->fetchColumn();ensure((int)$db->query('SELECT COUNT(*) FROM kbm_laporan')->fetchColumn()===1,'Double click duplication');
ensure(kbm_monitoring_snapshot($db,1)['classes'][0]['state']['primary']['color']==='hijau','Waka green');
denied(fn()=>kbm_student_post($db,4,['action'=>'checkin','id'=>(string)$j]));
$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute(['2026-10-18 08:01:00']);
kbm_student_post($db,3,['action'=>'checkout','id'=>(string)$p]);
$history=kbm_history_page($db,['tanggal_mulai'=>'2026-10-18','tanggal_selesai'=>'2026-10-18']);ensure($history['total']===1,'History without period');ensure($history['rows'][0]['status_code']==='selesai','Completed history');
ensure($history['rows'][0]['rencana_mulai']===null&&$history['rows'][0]['rencana_selesai']===null,'No fictitious hours');
denied(fn()=>kbm_student_post($db,3,$input));
$pendingInput=['action'=>'pilih_guru','jp'=>'4','jp_selesai'=>'6','guru_id'=>'1'];kbm_student_post($db,3,$pendingInput);$pendingId=(int)$db->query('SELECT MAX(id) FROM kbm_jadwal')->fetchColumn();kbm_materialize($db,1,'2026-10-18');$pendingMeeting=(int)$db->query('SELECT MAX(id) FROM kbm_pertemuan')->fetchColumn();kbm_correct($db,1,$pendingMeeting,null,null,'Koreksi admin mengunci catatan');denied(fn()=>kbm_student_post($db,3,$pendingInput));
$snapshot=kbm_rows($db,'SELECT * FROM kbm_pertemuan WHERE id=?',[$p])[0];
// Changing current setup cannot rewrite the meeting's session or reporter snapshot.
kbm_admin_submit($db,1,['action'=>'simple_sesi','kelas_id'=>'1','sesi_kode'=>'siang']);
ensure($snapshot===kbm_rows($db,'SELECT * FROM kbm_pertemuan WHERE id=?',[$p])[0],'Historical session changed');
$chair=kbm_student_assignments($db,3)[0];kbm_admin_submit($db,1,['action'=>'cabut_ketua','id'=>(string)$chair['id']]);
ensure(!kbm_student_assignments($db,3),'Revocation did not apply immediately');
denied(fn()=>kbm_student_post($db,3,$input));
kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>'1']);
$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute(['2030-07-01 08:00:00']);ensure(count(kbm_student_assignments($db,3))===1,'Unwanted semester expiration');
// Task-only reports are final, attributed to the chair and visible to read-only waka.
require_once 'includes/monitoring_kbm_dashboard.php';require_once 'includes/monitoring_kbm_matrix_export.php';
kbm_student_post($db,3,['action'=>'pilih_guru','jp'=>'1','jp_selesai'=>'2','guru_id'=>'1']);
$taskId=(int)$db->query('SELECT MAX(id) FROM kbm_jadwal')->fetchColumn();
$taskInput=['action'=>'guru_hanya_tugas','id'=>(string)$taskId,'guru_id'=>'1','jp_selesai'=>'2'];
foreach([1,2,4,5] as $actor)denied(fn()=>kbm_student_post($db,$actor,$taskInput));
$changed=$taskInput;$changed['guru_id']='99';denied(fn()=>kbm_student_post($db,3,$changed));
kbm_student_post($db,3,$taskInput);kbm_student_post($db,3,$taskInput);
$taskMeeting=(int)$db->query('SELECT MAX(id) FROM kbm_pertemuan')->fetchColumn();
ensure(!kbm_rows($db,'SELECT * FROM kbm_laporan WHERE pertemuan_id=?',[$taskMeeting]),'Task report invented attendance');
$report=kbm_day_report($db,'2030-07-01');$taskRow=array_values(array_filter($report,fn($r)=>(int)$r['jadwal_id']===$taskId))[0];
ensure(kbm_meeting_status($taskRow,'2030-07-01 08:00:00')['color']==='abu','Student task tone');
$board=kbm_dashboard_payload($db,['status'=>'abu']);ensure($board['summary']['abu']===1&&$board['shown']===1,'Waka task filter/summary');
ensure(str_contains($board['html'],'kbm-color-abu'),'Waka task card color');
$taskHistory=kbm_history_page($db,['tanggal_mulai'=>'2030-07-01','tanggal_selesai'=>'2030-07-01','status'=>'guru_hanya_tugas']);
ensure($taskHistory['total']===1&&$taskHistory['rows'][0]['status_code']==='guru_hanya_tugas','History SQL/PHP task status');
$matrix=kbm_history_matrix($db,['tanggal'=>'2030-07-01']);ensure($matrix['counts']['tugas']===2,'Task JP matrix count');
$file=kbm_matrix_export_file($db,['tanggal'=>'2030-07-01']);$xml=stream_get_contents($file['file']);fclose($file['file']);ensure(str_contains($xml,'Tugas'),'Task missing from Excel');
denied(fn()=>kbm_student_post($db,3,['action'=>'checkin','id'=>(string)$taskId]));
$absence=$taskInput;$absence['action']='guru_tidak_masuk';denied(fn()=>kbm_student_post($db,3,$absence));
denied(fn()=>kbm_student_post($db,3,['action'=>'reset_pilihan','id'=>(string)$taskId]));
denied(fn()=>kbm_student_post($db,3,['action'=>'pilih_guru','jp'=>'1','jp_selesai'=>'2','guru_id'=>'1']));
kbm_student_post($db,3,['action'=>'hapus_pilihan','id'=>(string)$taskId,'alasan'=>'Salah memilih status tugas']);
$cancelHistory=kbm_history_page($db,['tanggal_mulai'=>'2030-07-01','tanggal_selesai'=>'2030-07-01','status'=>'dibatalkan']);ensure($cancelHistory['total']===1,'Cancelled task history');
kbm_student_post($db,3,['action'=>'pilih_guru','jp'=>'1','jp_selesai'=>'2','guru_id'=>'1']);
$newChoice=(int)$db->query('SELECT MAX(id) FROM kbm_jadwal')->fetchColumn();kbm_student_post($db,3,['action'=>'checkin','id'=>(string)$newChoice]);
$blockingTask=$taskInput;$blockingTask['id']=(string)$newChoice;denied(fn()=>kbm_student_post($db,3,$blockingTask));
kbm_student_post($db,3,['action'=>'checkout','id'=>(string)$db->query('SELECT MAX(id) FROM kbm_pertemuan')->fetchColumn()]);
foreach(['periode','pola_waktu','hari_tanpa_kbm','edit_hari_tanpa_kbm','hapus_hari_tanpa_kbm','jadwal'] as $action)denied(fn()=>kbm_admin_submit($db,1,['action'=>$action]));
foreach([2,3,4,5] as $actor)denied(fn()=>kbm_admin_submit($db,$actor,['action'=>'simple_sesi','kelas_id'=>'1','sesi_kode'=>'pagi']));
ensure((int)$db->query('SELECT COUNT(*) FROM kbm_periode')->fetchColumn()===0,'A hidden period was created');
$db->exec("INSERT INTO users VALUES(6,3,1,'kedua'),(7,3,1,'ketiga'),(8,3,1,'keempat'),(9,3,1,'kelima'),(10,3,1,'cadangan');INSERT INTO siswa VALUES(3,6,1,'Pengguna Kedua','003'),(4,7,1,'Pengguna Ketiga','004'),(5,8,1,'Pengguna Keempat','005'),(6,9,1,'Pengguna Kelima','006'),(7,10,1,'Pengguna Cadangan','007')");
foreach([3,4] as $student)kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>(string)$student]);
ensure(count(kbm_simple_chair_slots($db,1))===3,'Additional users replaced the first user');
foreach([3,6,7] as $actor)ensure(count(kbm_student_assignments($db,$actor))===1,'Assigned user denied');
kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>'3']);ensure(count(kbm_simple_chair_slots($db,1))===3,'Duplicate assignment');
denied(fn()=>kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>'2']));
$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute(['2030-07-02 08:00:00']);
kbm_student_post($db,3,['action'=>'pilih_guru','jp'=>'1','jp_selesai'=>'2','guru_id'=>'1']);$jointSchedule=(int)$db->query('SELECT MAX(id) FROM kbm_jadwal')->fetchColumn();kbm_student_post($db,6,['action'=>'checkin','id'=>(string)$jointSchedule]);$jointMeeting=(int)$db->query('SELECT MAX(id) FROM kbm_pertemuan')->fetchColumn();
$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute(['2030-07-02 08:30:00']);kbm_student_post($db,7,['action'=>'checkout','id'=>(string)$jointMeeting]);
$joint=kbm_rows($db,'SELECT * FROM kbm_laporan WHERE pertemuan_id=?',[$jointMeeting])[0];ensure((int)$joint['checkin_siswa_id']===3&&(int)$joint['checkout_siswa_id']===4,'Different reporters not preserved');
$second=kbm_student_assignments($db,6)[0];kbm_admin_submit($db,1,['action'=>'cabut_ketua','id'=>(string)$second['id']]);ensure(!kbm_student_assignments($db,6),'Revoked user still permitted');ensure(count(kbm_student_assignments($db,3))===1&&count(kbm_student_assignments($db,7))===1,'Other users lost permission');ensure($joint===kbm_rows($db,'SELECT * FROM kbm_laporan WHERE pertemuan_id=?',[$jointMeeting])[0],'History changed after revocation');
kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>'3']);
$db->exec('UPDATE users SET is_active=0 WHERE id=7');ensure(!kbm_student_assignments($db,7),'Inactive user allowed');ensure(count(kbm_simple_chair_slots($db,1))===3,'Inactive slot not reserved');$db->exec('UPDATE users SET is_active=1 WHERE id=7');
$db->exec('UPDATE siswa SET kelas_id=2 WHERE id=1');ensure(!kbm_student_assignments($db,3),'Moved user still allowed');$db->exec('UPDATE siswa SET kelas_id=1 WHERE id=1');
echo 'Simple monitoring verified DB='.$name;`;
 const result=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:code,cwd:path.join(__dirname,'..'),encoding:'utf8'});
 assert.equal(result.status,0,result.stdout+result.stderr);assert.equal(result.stderr,'');assert.match(result.stdout,/Simple monitoring verified/);
 const database=result.stdout.match(/DB=(lms_kbm_simple_[a-f0-9]+)/)[1];
 const connect=String.raw`require 'includes/monitoring_kbm_pilihan.php';$db=new PDO('mysql:host=127.0.0.1','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$expected=trim(file_get_contents(sys_get_temp_dir().'/lms-akademik-test-path.txt'));$actual=$db->query('SELECT @@datadir')->fetchColumn();if(strcasecmp(rtrim(str_replace('\\','/',$actual),'/'),rtrim(str_replace('\\','/',$expected),'/'))!==0)throw new RuntimeException('Refusing non-isolated database');$name=getenv('MULTI_DB');if(!preg_match('/^lms_kbm_simple_[a-f0-9]+$/D',$name))throw new RuntimeException('Invalid fixture');$db->exec('USE '.$name);$db->exec("SET time_zone='+07:00'");`;
 const add=student=>new Promise((resolve,reject)=>{
  const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),windowsHide:true,env:{...process.env,MULTI_DB:database,MULTI_STUDENT:String(student)}});let out='',err='';child.stdout.on('data',v=>out+=v);child.stderr.on('data',v=>err+=v);child.on('error',reject);child.on('close',status=>{try{assert.equal(status,0,err+out);assert.equal(err,'');resolve(JSON.parse(out));}catch(e){reject(e);}});child.stdin.end('<?php\n'+connect+`try{kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>getenv('MULTI_STUDENT')]);echo json_encode(['ok'=>true]);}catch(InvalidArgumentException $e){echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}`);
 });
 const race=await Promise.all([add(5),add(6)]);assert.equal(race.filter(r=>r.ok).length,1);assert.match(race.find(r=>!r.ok).message,/Maksimal 4/);
 const probe=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),encoding:'utf8',env:{...process.env,MULTI_DB:database},input:'<?php\n'+connect+String.raw`
$slots=kbm_simple_chair_slots($db,1);if(count($slots)!==4||count(kbm_simple_chairs($db))!==4)throw new RuntimeException('Incorrect four-user limit');$last=end($slots);kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>(string)$last['siswa_id']]);if(count(kbm_simple_chair_slots($db,1))!==4)throw new RuntimeException('Duplicate occupied a fifth slot');$rejected=false;try{kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>'7']);}catch(InvalidArgumentException $e){$rejected=true;}if(!$rejected)throw new RuntimeException('Fifth user accepted');kbm_admin_submit($db,1,['action'=>'cabut_ketua','id'=>(string)$last['id']]);kbm_admin_submit($db,1,['action'=>'simple_ketua','kelas_id'=>'1','siswa_id'=>'7']);if(count(kbm_simple_chair_slots($db,1))!==4)throw new RuntimeException('Slot not reusable');echo 'Four-user limit verified';`});
 assert.equal(probe.status,0,probe.stdout+probe.stderr);assert.equal(probe.stderr,'');assert.match(probe.stdout,/Four-user limit verified/);
});
