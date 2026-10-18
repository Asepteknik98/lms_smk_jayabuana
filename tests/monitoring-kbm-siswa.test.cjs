const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawn,spawnSync}=require('node:child_process');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'..');
const php=process.env.PHP_BINARY||'C:/xampp/php/php.exe';
const connect=String.raw`
$db=new PDO('mysql:host=127.0.0.1','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$marker=sys_get_temp_dir().'/lms-akademik-test-path.txt';if(!is_file($marker))throw new RuntimeException('Isolated server marker missing');
$expected=trim(file_get_contents($marker));$actual=$db->query('SELECT @@datadir')->fetchColumn();
if(strcasecmp(rtrim(str_replace('\\','/',$expected),'/'),rtrim(str_replace('\\','/',$actual),'/'))!==0)throw new RuntimeException('Refusing non-isolated server');
$db->exec("SET time_zone='+07:00'");
if(getenv('KBM_TEST_DB')){if(!preg_match('/^lms_kbm_student_[a-f0-9]+$/D',getenv('KBM_TEST_DB')))throw new RuntimeException('Invalid test database');$db->exec('USE '.getenv('KBM_TEST_DB'));}
if(getenv('KBM_TEST_NOW'))$db->prepare('SET timestamp=UNIX_TIMESTAMP(?)')->execute([getenv('KBM_TEST_NOW')]);
`;
function run(code,env={}){
 const r=spawnSync(php,[],{input:'<?php\n'+connect+code,cwd:root,encoding:'utf8',env:{...process.env,...env}});
 assert.equal(r.status,0,r.stderr+r.stdout);assert.equal(r.stderr,'');return r.stdout;
}
test('Chair workflow, server rejection, double clicks and concurrent class writes',async()=>{
 const database=run(String.raw`
require 'includes/monitoring_kbm_siswa.php';
$name='lms_kbm_student_'.bin2hex(random_bytes(5));$db->exec("CREATE DATABASE $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE $name");
foreach([
'roles'=>'id TINYINT UNSIGNED PRIMARY KEY,nama_role VARCHAR(50)',
'akademik_profil'=>'user_id INT UNSIGNED PRIMARY KEY,nama_lengkap VARCHAR(100)',
'users'=>"id INT UNSIGNED PRIMARY KEY,role_id TINYINT UNSIGNED,is_active TINYINT,username VARCHAR(50) DEFAULT 'fixture',password VARCHAR(255) DEFAULT ''",
'kelas'=>'id INT UNSIGNED PRIMARY KEY,nama_kelas VARCHAR(50)',
'guru'=>'id INT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED,nama_lengkap VARCHAR(100)',
'siswa'=>'id INT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED,kelas_id INT UNSIGNED,nama_lengkap VARCHAR(100),nis VARCHAR(20) NULL',
'mapel'=>'id INT UNSIGNED PRIMARY KEY,nama_mapel VARCHAR(100)',
'pengajaran'=>"id INT UNSIGNED PRIMARY KEY,guru_id INT UNSIGNED,mapel_id INT UNSIGNED,kelas_id INT UNSIGNED,tahun_ajaran VARCHAR(10),semester ENUM('Ganjil','Genap')"
] as $t=>$c)$db->exec("CREATE TABLE $t($c) ENGINE=InnoDB");
$db->exec("INSERT INTO roles VALUES(1,'Admin'),(2,'Guru'),(3,'Siswa'),(5,'Akademik');INSERT INTO users(id,role_id,is_active) VALUES(1,1,1),(2,2,1),(3,3,1),(4,3,1),(5,5,1),(6,3,1),(7,3,1);INSERT INTO kelas VALUES(1,'X A'),(2,'X B');INSERT INTO guru VALUES(1,2,'Guru A');INSERT INTO siswa VALUES(1,3,1,'Ketua A','001'),(2,4,2,'Ketua B','002'),(3,6,1,'Siswa Biasa','003'),(4,7,1,'Pengganti','004');INSERT INTO mapel VALUES(1,'Mapel A');INSERT INTO pengajaran VALUES(1,1,1,1,'2026/2027','Ganjil'),(2,1,1,2,'2026/2027','Ganjil')");
$db->exec(file_get_contents('database/2026_10_16_monitoring_kbm_data.sql'));$db->exec(file_get_contents('database/2026_10_16_monitoring_kbm_admin.sql'));
$p=kbm_configure($db,1,'periode',['tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','mulai'=>'2026-07-01','selesai'=>'2026-12-31']);$range=['periode_id'=>$p,'berlaku_mulai'=>'2026-07-01','berlaku_sampai'=>'2026-12-31'];$slots=[];
foreach([1=>['07:00:00','07:40:00'],2=>['07:40:00','08:20:00'],3=>['08:40:00','09:20:00'],4=>['09:20:00','10:00:00'],5=>['10:00:00','10:40:00'],6=>['10:40:00','11:20:00']] as $jp=>$t){if($jp===3)$slots[]=['jenis'=>'ISTIRAHAT','jp'=>null,'mulai'=>'08:20:00','selesai'=>'08:40:00'];$slots[]=['jenis'=>'JP','jp'=>$jp,'mulai'=>$t[0],'selesai'=>$t[1]];}
$pattern=kbm_configure($db,1,'pola_waktu',$range+['sesi_kode'=>'pagi','hari'=>5,'slots'=>$slots]);$ks=kbm_configure($db,1,'kelas_sesi',$range+['kelas_id'=>1,'sesi_kode'=>'pagi']);kbm_configure($db,1,'kelas_sesi',$range+['kelas_id'=>2,'sesi_kode'=>'pagi']);kbm_configure($db,1,'ketua_kelas',$range+['kelas_id'=>1,'siswa_id'=>1]);kbm_configure($db,1,'ketua_kelas',$range+['kelas_id'=>2,'siswa_id'=>2]);
$j=['kelas_sesi_id'=>$ks,'pola_id'=>$pattern,'pengajaran_id'=>1,'jp_mulai'=>1,'jp_selesai'=>2,'berlaku_mulai'=>'2026-07-01','berlaku_sampai'=>'2026-12-31'];kbm_create_schedule($db,1,$j);kbm_create_schedule($db,1,array_replace($j,['jp_mulai'=>3,'jp_selesai'=>4]));kbm_create_schedule($db,1,array_replace($j,['jp_mulai'=>5,'jp_selesai'=>6]));echo $name;
`);
 const env={KBM_TEST_DB:database,KBM_TEST_NOW:'2026-10-16 07:10:00'};
 const recordCode=String.raw`
require 'includes/monitoring_kbm_siswa.php';
try{$msg=kbm_student_post($db,(int)getenv('KBM_USER'),json_decode(getenv('KBM_INPUT'),true));echo json_encode(['ok'=>true,'message'=>$msg]);}
catch(KbmStudentForbidden $e){echo json_encode(['ok'=>false,'status'=>403,'message'=>$e->getMessage()]);}
catch(InvalidArgumentException $e){echo json_encode(['ok'=>false,'status'=>422,'message'=>$e->getMessage()]);}
`;
 const record=(action,id,user='3',now=env.KBM_TEST_NOW,extra={})=>JSON.parse(run(recordCode,{...env,KBM_USER:user,KBM_TEST_NOW:now,KBM_INPUT:JSON.stringify({action,id:String(id),...extra})}));
 const query=(sql,now=env.KBM_TEST_NOW)=>JSON.parse(run('$q=$db->query(getenv("KBM_SQL"));echo json_encode($q->columnCount()?$q->fetchAll():[]);',{...env,KBM_SQL:sql,KBM_TEST_NOW:now}));
 for(const user of ['1','2','5','6','7'])assert.equal(record('checkin',1,user).status,403);
 assert.equal(record('checkin',1,'4').ok,false);
 assert.equal(record('checkin',1,'3','2026-10-16 06:59:59').ok,false);
 assert.equal(record('checkin',2,'3','2026-10-16 08:30:00').ok,false);
 assert.equal(record('checkin',1,'3','2026-10-16 08:20:00').ok,false);
 assert.equal(record('checkin',1,'3','2026-10-17 07:10:00').ok,false);
 assert.equal(record('checkin',1,'3',undefined,{tanggal:'2026-10-15',checkin_at:'2026-10-15 07:00:00'}).ok,false);
 assert.equal(record('checkout',99).ok,false);
 assert.equal(record('checkin',1).ok,true);
 const first=query('SELECT * FROM kbm_laporan')[0];assert.equal(first.checkin_at,'2026-10-16 07:10:00');
 assert.equal(record('checkin',1,'3','2026-10-16 07:11:00').ok,true);assert.equal(query('SELECT COUNT(*) n FROM kbm_laporan')[0].n,1);
 assert.equal(record('checkout',first.pertemuan_id,'4').ok,false);
 assert.equal(record('checkout',first.pertemuan_id,'3','2026-10-16 07:20:00').ok,true);
 assert.equal(record('checkout',first.pertemuan_id,'3','2026-10-16 07:21:00').ok,true);
 assert.equal(query('SELECT checkout_at FROM kbm_laporan')[0].checkout_at,'2026-10-16 07:20:00');
 assert.equal(record('checkin',1,'3','2026-10-16 07:22:00').ok,true);assert.equal(query('SELECT checkout_at FROM kbm_laporan')[0].checkout_at,'2026-10-16 07:20:00');
 function concurrent(id,now,user='3',extra={}){return new Promise((resolve,reject)=>{
  const child=spawn(php,[],{cwd:root,windowsHide:true,env:{...process.env,...env,KBM_TEST_NOW:now,KBM_USER:user,KBM_INPUT:JSON.stringify({action:'checkin',id:String(id),...extra})},stdio:['pipe','pipe','pipe']});let out='',err='';child.stdout.on('data',c=>out+=c);child.stderr.on('data',c=>err+=c);child.on('error',reject);child.on('close',status=>{try{assert.equal(status,0,err+out);assert.equal(err,'');resolve(JSON.parse(out));}catch(e){reject(e);}});child.stdin.end('<?php\n'+connect+recordCode);
 });}
 const doubles=await Promise.all(Array.from({length:6},()=>concurrent(1,'2026-10-23 07:10:00')));
 assert.ok(doubles.every(r=>r.ok));assert.equal(query("SELECT COUNT(*) n FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='2026-10-23'")[0].n,1);
 let open=query("SELECT p.id FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='2026-10-23'")[0].id;
 assert.equal(record('checkin',2,'3','2026-10-23 08:50:00').ok,false);
 assert.equal(query('SELECT checkout_at FROM kbm_laporan WHERE pertemuan_id='+open)[0].checkout_at,null);
 assert.equal(record('checkout',open,'3','2026-10-24 09:00:00').ok,true);assert.equal(query('SELECT checkout_at FROM kbm_laporan WHERE pertemuan_id='+open)[0].checkout_at,'2026-10-24 09:00:00');
 // Intentionally malformed overlapping fixture to prove class mutex independently of timetable validation.
 query('INSERT INTO kbm_jadwal(kelas_sesi_id,pola_id,pengajaran_id,guru_id,mapel_id,pengajaran_snapshot,jp_mulai,jp_selesai,berlaku_mulai,berlaku_sampai,dibuat_oleh) SELECT kelas_sesi_id,pola_id,pengajaran_id,guru_id,mapel_id,pengajaran_snapshot,jp_mulai,jp_selesai,berlaku_mulai,berlaku_sampai,dibuat_oleh FROM kbm_jadwal WHERE id=1');
 const duplicateId=query('SELECT MAX(id) id FROM kbm_jadwal')[0].id;
 const race=await Promise.all([concurrent(1,'2026-10-30 07:10:00'),concurrent(duplicateId,'2026-10-30 07:10:00')]);assert.equal(race.filter(r=>r.ok).length,1);
 assert.equal(query("SELECT COUNT(*) n FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='2026-10-30' AND l.checkout_at IS NULL")[0].n,1);
 open=query("SELECT p.id FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='2026-10-30'")[0].id;
 query('UPDATE users SET is_active=0 WHERE id=3');assert.equal(record('checkout',open,'3','2026-10-30 07:20:00').status,403);query('UPDATE users SET is_active=1 WHERE id=3');
 query('UPDATE siswa SET kelas_id=2 WHERE user_id=3');assert.equal(record('checkout',open,'3','2026-10-30 07:20:00').status,403);query('UPDATE siswa SET kelas_id=1 WHERE user_id=3');
 query('UPDATE kbm_ketua_kelas SET dicabut_pada=CURRENT_TIMESTAMP,dicabut_oleh=1 WHERE siswa_id=1');assert.equal(record('checkout',open,'3','2026-10-30 07:20:00').status,403);
 run("require 'includes/monitoring_kbm_siswa.php';kbm_configure($db,1,'ketua_kelas',['periode_id'=>1,'kelas_id'=>1,'siswa_id'=>4,'berlaku_mulai'=>'2026-10-30','berlaku_sampai'=>'2026-12-31']);",{...env,KBM_TEST_NOW:'2026-10-30 07:20:00'});
 assert.equal(record('checkout',open,'7','2026-10-31 09:00:00').ok,true);
 const replacement=query('SELECT checkin_siswa_id,checkout_siswa_id FROM kbm_laporan WHERE pertemuan_id='+open)[0];assert.equal(replacement.checkin_siswa_id,1);assert.equal(replacement.checkout_siswa_id,4);
 const htmlCode=String.raw`
require 'config/database.php';require 'includes/monitoring_kbm_siswa.php';
$r=new ReflectionClass(Database::class);$i=$r->newInstanceWithoutConstructor();$r->getProperty('conn')->setValue($i,$db);$r->getProperty('instance')->setValue(null,$i);
session_save_path(sys_get_temp_dir());require 'config/session.php';$_SESSION['user_id']=(int)getenv('KBM_USER');$_SESSION['role_id']=[1=>1,2=>2,3=>3,4=>3,5=>5,6=>3,7=>3][$_SESSION['user_id']];
$route=getenv('KBM_TEST_ROUTE')?:'siswa/monitoring_kbm.php';$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF']='/'.$route;$_SERVER['REQUEST_METHOD']=getenv('KBM_METHOD')?:'GET';$_GET=['jp'=>getenv('KBM_JP')?:'1'];if(getenv('KBM_TEST_DATE'))$_GET['tanggal']=getenv('KBM_TEST_DATE');
if(getenv('KBM_TEST_QUERY'))$_GET=json_decode(getenv('KBM_TEST_QUERY'),true);
if(getenv('KBM_ANONYMOUS'))$_SESSION=[];
if(getenv('KBM_CLOSED_SEED'))$_SESSION['kbm_siswa_closed']=(int)getenv('KBM_CLOSED_SEED');
if($_SERVER['REQUEST_METHOD']==='POST')$_POST=['action'=>getenv('KBM_POST_ACTION')?:'checkin','id'=>getenv('KBM_POST_ID')?:'1','csrf_token'=>getenv('KBM_POST_VALID')?$_SESSION['csrf_token']:'bad'];
if(getenv('KBM_POST_DATA')){$_POST=json_decode(getenv('KBM_POST_DATA'),true);$_POST['csrf_token']=getenv('KBM_POST_VALID')?$_SESSION['csrf_token']:'bad';}
ob_start();register_shutdown_function(function(){echo json_encode(['status'=>http_response_code()?:200,'html'=>ob_get_clean(),'error'=>$_SESSION['kbm_siswa_error']??$_SESSION['kbm_history_error']??$_SESSION['kbm_error']??$_SESSION['kbm_matrix_error']??'','closed'=>$_SESSION['kbm_siswa_closed']??null]);if(session_status()===PHP_SESSION_ACTIVE)session_destroy();});require $route;
`;
 const render=(user='7',extra={})=>JSON.parse(run(htmlCode,{...env,KBM_USER:user,KBM_TEST_NOW:'2026-10-30 07:30:00',...extra}));
 let view=render();assert.equal(view.status,200);assert.match(view.html,/JP 1–2/);assert.match(view.html,/0 jam 10 menit 0 detik|jam/);assert.match(view.html,/Monitoring KBM/);
 for(const match of view.html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))new vm.Script(match[1]);
 for(const user of ['1','2','3','5','6'])assert.equal(render(user).status,403);
 const csrf=render('7',{KBM_METHOD:'POST'});assert.equal(csrf.status,302);assert.match(csrf.error,/Sesi keamanan/);
 const noSchedule=render('7',{KBM_TEST_NOW:'2026-10-31 09:01:00'});assert.match(noSchedule.html,/Belum ada jadwal KBM hari ini/);
 assert.equal(query('SELECT COUNT(*) n FROM kbm_laporan')[0].n,3);
 const controllerIn=render('7',{KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_TEST_NOW:'2026-11-06 07:10:00'});assert.equal(controllerIn.status,302);assert.equal(controllerIn.error,'');
 const controllerMeeting=query("SELECT p.id FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='2026-11-06'")[0].id;
 const controllerOut=render('7',{KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_POST_ACTION:'checkout',KBM_POST_ID:String(controllerMeeting),KBM_TEST_NOW:'2026-11-07 09:00:00'});assert.equal(controllerOut.status,302);assert.equal(controllerOut.closed,controllerMeeting);
 const lateResult=render('7',{KBM_CLOSED_SEED:String(controllerMeeting),KBM_TEST_NOW:'2026-11-07 09:01:00'});assert.match(lateResult.html,/Pertemuan selesai dicatat/);assert.match(lateResult.html,/2026-11-06 07:10:00 WIB/);assert.match(lateResult.html,/2026-11-07 09:00:00 WIB/);assert.match(lateResult.html,/25 jam 50 menit 0 detik/);
 // Sidebar must hide Monitoring KBM for a normal student, keeping existing links.
 const sidebarCode=htmlCode.slice(0,htmlCode.indexOf('ob_start();'))+String.raw`require 'config/auth.php';ob_start();require 'includes/sidebar.php';$out=ob_get_clean();echo json_encode(['html'=>$out]);session_destroy();`;
 const regular=JSON.parse(run(sidebarCode,{...env,KBM_USER:'6'}));assert.doesNotMatch(regular.html,/href="monitoring_kbm.php"/);assert.match(regular.html,/Absensi Saya/);assert.match(regular.html,/Tugas/);
 // The same central result is rendered on all three roles, without writes on GET.
 assert.equal(record('checkin',1,'7','2026-11-13 07:10:00').ok,true);
 const before=query('SELECT COUNT(*) n FROM kbm_pertemuan')[0].n;
 for(const [user,route] of [['1','admin/monitoring_kbm_status.php'],['5','akademik/index.php']]){
  const board=render(user,{KBM_TEST_ROUTE:route,KBM_TEST_NOW:'2026-11-13 08:50:00'});
  assert.equal(board.status,200);assert.match(board.html,/data-class-id="1" data-status="belum_checkin"/);assert.match(board.html,/Guru belum check-in/);assert.match(board.html,/Jadwal berakhir, belum check-out/);assert.match(board.html,/kbm-color-merah/);
  const history=render(user,{KBM_TEST_ROUTE:route,KBM_TEST_DATE:'2026-10-09',KBM_TEST_NOW:'2026-11-13 08:50:00'});assert.match(history.html,/Tidak tercatat check-in/);
  assert.equal(render(user,{KBM_TEST_ROUTE:route,KBM_METHOD:'POST',KBM_POST_VALID:'1'}).status,405);
  assert.equal(render('7',{KBM_TEST_ROUTE:route}).status,403);
  const nextDay=render(user,{KBM_TEST_ROUTE:route,KBM_TEST_NOW:'2026-11-14 07:10:00'});assert.match(nextDay.html,/data-class-id="1" data-status="tanpa_jadwal"/);assert.match(nextDay.html,/2026-11-13/);
  const invalid=render(user,{KBM_TEST_ROUTE:route,KBM_TEST_DATE:'2026-02-30'});assert.equal(invalid.status,400);
 }
 const studentStatus=render('7',{KBM_TEST_NOW:'2026-11-13 08:50:00',KBM_JP:'3'});assert.match(studentStatus.html,/Guru belum check-in/);assert.match(studentStatus.html,/Jadwal berakhir, belum check-out/);
 assert.equal(query('SELECT COUNT(*) n FROM kbm_pertemuan')[0].n,before);
 query("INSERT INTO kelas VALUES(3,'Kelas Belum Dibagi')");query("INSERT INTO kbm_hari_tanpa_kbm(periode_id,tanggal,alasan,dibuat_oleh) VALUES(1,'2026-11-20','Libur global',1)");
 const holidayBoard=render('1',{KBM_TEST_ROUTE:'admin/monitoring_kbm_status.php',KBM_TEST_NOW:'2026-11-20 07:10:00'});assert.match(holidayBoard.html,/data-class-id="3" data-status="hari_tanpa_kbm"/);assert.match(holidayBoard.html,/Hari tanpa KBM: Libur global/);
 // Stage 7: safe indexes, grouped filters, read-only feed and changing server data.
 run("$sql=file_get_contents('database/2026_10_16_monitoring_kbm_dashboard.sql');$db->exec($sql);$db->exec($sql);",env);
 query("INSERT INTO kelas VALUES(4,'Siang Belum Lengkap')");
 run("require 'includes/monitoring_kbm_siswa.php';kbm_configure($db,1,'kelas_sesi',['periode_id'=>1,'kelas_id'=>4,'sesi_kode'=>'siang','berlaku_mulai'=>'2026-07-01','berlaku_sampai'=>'2026-12-31']);",env);
 const previous=query("SELECT p.id FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='2026-11-13'")[0].id;
 assert.equal(record('checkout',previous,'7','2026-11-27 06:50:00').ok,true);
 const feed=(user,route,params={},extra={})=>{const r=render(user,{KBM_TEST_ROUTE:route,KBM_TEST_NOW:'2026-11-27 07:10:00',KBM_TEST_QUERY:JSON.stringify(params),...extra});return {...r,data:JSON.parse(r.html)};};
 for(const [user,route] of [['1','admin/monitoring_kbm_data.php'],['5','akademik/monitoring_kbm_data.php']]){
  let f=feed(user,route);assert.equal(f.status,200);assert.equal(f.data.summary.merah,1);assert.equal(f.data.summary.kuning,3);assert.equal(f.data.summary.incomplete,3);assert.match(f.data.html,/data-session="pagi"/);assert.match(f.data.html,/data-session="siang"/);assert.match(f.data.html,/Sesi belum diatur/);assert.match(f.data.html,/Konfigurasi belum lengkap/);assert.equal(f.data.server_time,'2026-11-27 07:10:00');
  f=feed(user,route,{sesi:'siang'});assert.equal(f.data.shown,1);assert.equal(f.data.summary.incomplete,1);assert.doesNotMatch(f.data.html,/data-session="pagi"/);
  f=feed(user,route,{kelas:'1',status:'merah'});assert.equal(f.data.shown,1);assert.equal(f.data.summary.merah,1);assert.doesNotMatch(f.data.html,/data-class-id="2"/);
  assert.equal(feed(user,route,{status:'hijau'}).data.shown,0);
  assert.equal(feed(user,route,{status:'belum_dikonfigurasi'}).data.shown,3);
  assert.equal(feed(user,route,{sesi:['pagi']}).status,400);assert.equal(feed(user,route,{status:'injected'}).status,400);assert.equal(feed(user,route,{kelas:'999'}).status,400);
  assert.equal(feed(user,route,{}, {KBM_METHOD:'POST'}).status,405);
  assert.equal(feed(user,route,{}, {KBM_ANONYMOUS:'1'}).status,401);
  for(const other of ['2','6','7'])assert.equal(feed(other,route).status,403);
 }
 assert.equal(feed('5','admin/monitoring_kbm_data.php').status,403);
 assert.equal(feed('1','akademik/monitoring_kbm_data.php').status,403);
 assert.equal(record('checkin',1,'7','2026-11-27 07:10:00').ok,true);
 assert.equal(feed('5','akademik/monitoring_kbm_data.php').data.summary.hijau,1);
 const checked=query("SELECT p.id FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='2026-11-27'")[0].id;
 assert.equal(record('checkout',checked,'7','2026-11-27 07:20:00').ok,true);
 const closedFeed=feed('5','akademik/monitoring_kbm_data.php',{}, {KBM_TEST_NOW:'2026-11-27 07:21:00'});assert.equal(closedFeed.data.summary.kuning,4);assert.match(closedFeed.data.html,/2026-11-27 07:20:00/);
 query('UPDATE users SET is_active=0 WHERE id=5');assert.equal(feed('5','akademik/monitoring_kbm_data.php').status,401);query('UPDATE users SET is_active=1 WHERE id=5');
 // Query count stays fixed when school size grows; the performance fixture is isolated.
 const perf=JSON.parse(run(String.raw`
require 'includes/monitoring_kbm_dashboard.php';
class KbmCountDb extends PDO {
 public int $queries=0;
 public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {$this->queries++;return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);}
 public function prepare(string $query,array $options=[]): PDOStatement|false {$this->queries++;return parent::prepare($query,$options);}
}
$counted=new KbmCountDb('mysql:host=127.0.0.1;dbname='.getenv('KBM_TEST_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$counted->exec("SET time_zone='+07:00'");$counted->exec("SET timestamp=UNIX_TIMESTAMP('2026-11-27 07:21:00')");
$a=kbm_monitoring_snapshot($counted);$first=$counted->queries;
for($id=100;$id<180;$id++)$db->exec("INSERT INTO kelas VALUES($id,'Synthetic $id')");
$counted->queries=0;$b=kbm_monitoring_snapshot($counted);echo json_encode(['small'=>$first,'large'=>$counted->queries,'total'=>count($b['classes'])]);
`,env));assert.equal(perf.small,perf.large);assert.ok(perf.large<=17,JSON.stringify(perf));assert.equal(perf.total,84);
 // Stage 8: paginated history from both schedules and reports, archived labels and audit.
 const historyEnv={...env,KBM_TEST_NOW:'2026-11-27 12:00:00'};
 const history=(filters={})=>JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,json_decode(getenv('KBM_FILTERS'),true)));",{...historyEnv,KBM_FILTERS:JSON.stringify({tanggal_mulai:'2026-10-01',tanggal_selesai:'2026-11-27',...filters})}));
 const firstPage=history({per_halaman:'10'}),secondPage=history({per_halaman:'10',halaman:'2'});assert.equal(firstPage.rows.length,10);assert.ok(firstPage.pages>1);assert.notEqual(firstPage.rows[0].tanggal+firstPage.rows[0].jadwal_id,secondPage.rows[0].tanggal+secondPage.rows[0].jadwal_id);
 const onlyDay=history({tanggal_mulai:'2026-11-06',tanggal_selesai:'2026-11-06'});assert.ok(onlyDay.rows.length>0);assert.ok(onlyDay.rows.every(r=>r.tanggal==='2026-11-06'));assert.ok(onlyDay.rows.every(r=>r.status_code===r.status.code));
 const missingRow=onlyDay.rows.find(r=>Number(r.jadwal_id)===2);assert.equal(missingRow.pertemuan_id,null);assert.equal(missingRow.status.label,'Tidak tercatat check-in');assert.equal(missingRow.checkin_efektif,null);
 assert.equal(history({sesi:'siang'}).total,0);assert.equal(history({guru:'99'}).total,0);assert.equal(history({kelas:'99'}).total,0);assert.ok(history({kelas:'1',guru:'1',sesi:'pagi',status:'tanpa_checkin'}).rows.every(r=>r.status.code==='tanpa_checkin'));
 const beforeRead=query('SELECT COUNT(*) n FROM kbm_pertemuan')[0].n;history();assert.equal(query('SELECT COUNT(*) n FROM kbm_pertemuan')[0].n,beforeRead);
 const correction=String.raw`require 'includes/monitoring_kbm_history.php';$row=kbm_history_row($db,2,'2026-11-06');$input=['jadwal_id'=>'2','tanggal'=>'2026-11-06','revision'=>kbm_history_revision($row),'checkin'=>'2026-11-06T08:45:00','checkout'=>'','alasan'=>'Verifikasi guru dan ketua'];$id=kbm_history_correct($db,1,$input);echo json_encode(['id'=>$id,'row'=>kbm_history_row($db,2,'2026-11-06'),'stale'=>$input]);`;
 const corrected=JSON.parse(run(correction,historyEnv));assert.equal(corrected.row.checkin_asli,null);assert.equal(corrected.row.checkin_efektif,'2026-11-06 08:45:00');assert.equal(corrected.row.status_code,'checkin');assert.equal(corrected.row.pelapor_masuk,null);assert.equal(query('SELECT COUNT(*) n FROM kbm_laporan')[0].n,6);
 const auditBefore=query('SELECT COUNT(*) n FROM kbm_koreksi_admin')[0].n;
 const attempt=(input,actor=1)=>JSON.parse(run(String.raw`require 'includes/monitoring_kbm_history.php';try{kbm_history_correct($db,(int)getenv('KBM_ACTOR'),json_decode(getenv('KBM_CORRECTION'),true));echo json_encode(['ok'=>true]);}catch(InvalidArgumentException|RuntimeException $e){echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}`,{...historyEnv,KBM_ACTOR:String(actor),KBM_CORRECTION:JSON.stringify(input)}));
 assert.equal(attempt(corrected.stale).ok,false);
 const revision=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_revision(kbm_history_row($db,2,'2026-11-06')));",historyEnv));
 const fix={...corrected.stale,revision,checkout:'2026-11-07T09:00:00'};
 assert.equal(attempt({...fix,alasan:''}).ok,false);assert.equal(attempt({...fix,checkout:'2026-11-06T08:00:00'}).ok,false);assert.equal(attempt({...fix,checkout:'2026-11-28T09:00:00'}).ok,false);assert.equal(attempt(fix,5).ok,false);assert.equal(query('SELECT COUNT(*) n FROM kbm_koreksi_admin')[0].n,auditBefore);
 assert.equal(attempt(fix).ok,true);
 const afterFix=history({status:'dikoreksi'}).rows.find(r=>Number(r.jadwal_id)===2&&r.tanggal==='2026-11-06');assert.equal(afterFix.duration,'24 jam 15 menit 0 detik');assert.equal(afterFix.status.code,'selesai');assert.equal(afterFix.checkin_asli,null);
 const audit=query('SELECT * FROM kbm_koreksi_admin ORDER BY id DESC LIMIT 1')[0];assert.equal(audit.checkin_lama,'2026-11-06 08:45:00');assert.equal(audit.checkout_lama,null);assert.equal(audit.checkout_baru,'2026-11-07 09:00:00');assert.equal(audit.admin_id,1);
 const oldRow=history({tanggal_mulai:'2026-10-16',tanggal_selesai:'2026-10-16'}).rows.find(r=>Number(r.jadwal_id)===1);
 query("UPDATE guru SET nama_lengkap='Guru Diganti'");query("UPDATE kelas SET nama_kelas='Kelas Diganti' WHERE id=1");
 const archived=history({tanggal_mulai:'2026-10-16',tanggal_selesai:'2026-10-16'}).rows.find(r=>Number(r.jadwal_id)===1);assert.equal(archived.nama_guru,oldRow.nama_guru);assert.equal(archived.nama_kelas,oldRow.nama_kelas);
 run(String.raw`require 'includes/monitoring_kbm_history.php';$slots=kbm_rows($db,'SELECT jenis,jp,mulai,selesai FROM kbm_slot_waktu WHERE pola_id=1 ORDER BY urutan');$p=kbm_configure($db,1,'pola_waktu',['periode_id'=>1,'sesi_kode'=>'pagi','hari'=>1,'berlaku_mulai'=>'2026-07-01','berlaku_sampai'=>'2026-12-31','slots'=>$slots]);kbm_change_schedule($db,1,1,'2026-11-30','Pindah hari', ['kelas_sesi_id'=>1,'pola_id'=>$p,'pengajaran_id'=>1,'jp_mulai'=>1,'jp_selesai'=>2,'berlaku_sampai'=>'2026-12-31']);`,historyEnv);
 assert.deepEqual(history({tanggal_mulai:'2026-10-16',tanggal_selesai:'2026-10-16'}).rows.find(r=>Number(r.jadwal_id)===1),archived);
 const detailQuery={tanggal_mulai:'2026-11-06',tanggal_selesai:'2026-11-06',detail_jadwal:'2',detail_tanggal:'2026-11-06'};
 for(const [user,route]of [['1','admin/riwayat_kbm.php'],['5','akademik/riwayat_kbm.php']]){
  const page=render(user,{KBM_TEST_ROUTE:route,KBM_TEST_NOW:historyEnv.KBM_TEST_NOW,KBM_TEST_QUERY:JSON.stringify(detailQuery)});assert.equal(page.status,200);assert.match(page.html,/Jumat, 6 November 2026/);assert.match(page.html,/Laporan asli siswa/);assert.match(page.html,/Sebelum/);assert.match(page.html,/Sesudah/);assert.match(page.html,/Verifikasi guru dan ketua/);
  if(user==='1')assert.match(page.html,/name="revision"/);else assert.doesNotMatch(page.html,/name="action"/);
  assert.equal(render('7',{KBM_TEST_ROUTE:route}).status,403);assert.equal(render('2',{KBM_TEST_ROUTE:route}).status,403);
  assert.equal(render(user,{KBM_TEST_ROUTE:route,KBM_TEST_QUERY:JSON.stringify({tanggal_mulai:'2026-11-10',tanggal_selesai:'2026-11-01'})}).status,400);
 }
 const invalidCsrf=render('1',{KBM_TEST_ROUTE:'admin/riwayat_kbm.php',KBM_METHOD:'POST',KBM_POST_DATA:JSON.stringify({action:'koreksi',...fix}),KBM_TEST_NOW:historyEnv.KBM_TEST_NOW});assert.equal(invalidCsrf.status,302);assert.match(invalidCsrf.error,/Sesi keamanan/);
 assert.equal(render('5',{KBM_TEST_ROUTE:'akademik/riwayat_kbm.php',KBM_METHOD:'POST'}).status,403);
 const latestRevision=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_revision(kbm_history_row($db,2,'2026-11-06')));",historyEnv));
 const validPagePost=render('1',{KBM_TEST_ROUTE:'admin/riwayat_kbm.php',KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_POST_DATA:JSON.stringify({action:'koreksi',...fix,revision:latestRevision,alasan:'Validasi halaman admin'}),KBM_TEST_NOW:historyEnv.KBM_TEST_NOW,KBM_TEST_QUERY:JSON.stringify(detailQuery)});assert.equal(validPagePost.status,302);assert.equal(validPagePost.error,'');assert.equal(query('SELECT alasan FROM kbm_koreksi_admin ORDER BY id DESC LIMIT 1')[0].alasan,'Validasi halaman admin');
 // Stage 9: actual generated workbook round-trip, all pages and matching filters.
 const exportFilters={tanggal_mulai:'2026-10-01',tanggal_selesai:'2026-11-27'};
 const workbook=(filters={})=>JSON.parse(run(String.raw`
require 'includes/monitoring_kbm_export.php';require 'includes/nilai_excel_reader.php';
try{
 $e=kbm_history_export_file($db,json_decode(getenv('KBM_FILTERS'),true));$xml=stream_get_contents($e['file']);fclose($e['file']);
 $doc=ni_xml($xml);$xp=new DOMXPath($doc);$xp->registerNamespace('s','urn:schemas-microsoft-com:office:spreadsheet');
 $path=tempnam(sys_get_temp_dir(),'kbm-export-');try{file_put_contents($path,$xml);$parsed=ni_read_excel($path);}finally{unlink($path);}
 echo json_encode(['count'=>$e['count'],'rows'=>$parsed['rows'],'formulas'=>$xp->query('//@s:Formula')->length,'columns'=>$xp->query('//s:Column')->length,'filename'=>$e['filename']]);
}catch(KbmExportNotice $ex){echo json_encode(['notice'=>$ex->getMessage()]);}
`,{...historyEnv,KBM_FILTERS:JSON.stringify({...exportFilters,...filters})}));
 const allExport=workbook({per_halaman:'10',halaman:'2'}),allHistory=history({per_halaman:'50'});
 assert.equal(allExport.count,allHistory.total);assert.ok(allExport.count>10);assert.equal(allExport.rows.length,allExport.count+5);assert.equal(allExport.formulas,0);assert.equal(allExport.columns,24);
 for(let i=0;i<allHistory.rows.length;i++){
  const row=allExport.rows[i+5],source=allHistory.rows[i];assert.equal(String(row[0]),String(i+1));assert.equal(row[6],source.nama_kelas);assert.equal(row[7],source.nama_guru);assert.equal(row[8],source.nama_mapel);assert.equal(row[18],source.status.label);
  assert.equal(row[13]||'',(source.checkin_efektif||'').replace(' ','T'));assert.equal(row[14]||'',(source.checkout_efektif||'').replace(' ','T'));
  if(source.checkin_efektif===null){assert.equal(row[15]||'','');assert.match(row[19],/belum tercatat/);}
 }
 for(const filters of [{kelas:'1',guru:'1',sesi:'pagi',status:'tanpa_checkin'},{status:'dikoreksi'},{tanggal_mulai:'2026-11-06',tanggal_selesai:'2026-11-06'}])assert.equal(workbook(filters).count,history(filters).total);
 const correctedExport=workbook({status:'dikoreksi'}).rows[5];assert.equal(correctedExport[20],'Hasil koreksi admin');assert.equal(correctedExport[21],'Admin #1');assert.equal(correctedExport[23],'Validasi halaman admin');assert.ok(Math.abs(Number(correctedExport[15])-24.25/24)<0.0000001);
 assert.match(workbook({guru:'99'}).notice,/Tidak ada hasil/);
 const exportRoute={KBM_TEST_ROUTE:'admin/riwayat_kbm_export.php',KBM_TEST_NOW:historyEnv.KBM_TEST_NOW,KBM_TEST_QUERY:JSON.stringify(exportFilters)};
 const download=render('1',exportRoute);assert.equal(download.status,200);assert.match(download.html,/^<\?xml/);assert.match(download.html,/RIWAYAT MONITORING KBM/);
 for(const user of ['2','5','6','7'])assert.equal(render(user,exportRoute).status,403);
 assert.equal(render('1',{...exportRoute,KBM_METHOD:'POST'}).status,405);assert.equal(render('1',{...exportRoute,KBM_ANONYMOUS:'1'}).status,302);
 const empty=render('1',{...exportRoute,KBM_TEST_QUERY:JSON.stringify({...exportFilters,guru:'99'})});assert.equal(empty.status,302);assert.match(empty.error,/Tidak ada hasil/);assert.doesNotMatch(empty.html,/<Workbook/);
 const adminHistory=render('1',{KBM_TEST_ROUTE:'admin/riwayat_kbm.php',KBM_TEST_NOW:historyEnv.KBM_TEST_NOW,KBM_TEST_QUERY:JSON.stringify({...exportFilters,halaman:'2',per_halaman:'10'})});assert.match(adminHistory.html,/Unduh Excel Semua Hasil/);assert.doesNotMatch(adminHistory.html.match(/href="riwayat_kbm_export.php\?[^"]+/)[0],/halaman/);
 assert.doesNotMatch(render('5',{KBM_TEST_ROUTE:'akademik/riwayat_kbm.php',KBM_TEST_NOW:historyEnv.KBM_TEST_NOW}).html,/riwayat_kbm_export/);
 // Stage 10: actual afternoon schedules across a semester and school-year boundary.
 const nextPeriods=JSON.parse(run(String.raw`
require 'includes/monitoring_kbm_siswa.php';$result=[];
foreach([
 ['2026/2027','Genap','2027-01-01','2027-06-30',3],
 ['2027/2028','Ganjil','2027-07-01','2027-12-31',4]
] as [$year,$semester,$start,$end,$teaching]){
 $db->prepare('INSERT INTO pengajaran VALUES(?,1,1,1,?,?)')->execute([$teaching,$year,$semester]);
 $period=kbm_configure($db,1,'periode',['tahun_ajaran'=>$year,'semester'=>$semester,'mulai'=>$start,'selesai'=>$end]);
 $range=['periode_id'=>$period,'berlaku_mulai'=>$start,'berlaku_sampai'=>$end];
 $slots=kbm_rows($db,'SELECT jenis,jp,mulai,selesai FROM kbm_slot_waktu WHERE pola_id=1 ORDER BY urutan');
 foreach($slots as &$slot)foreach(['mulai','selesai'] as $key)$slot[$key]=(new DateTimeImmutable('2026-10-16 '.$slot[$key],new DateTimeZone('Asia/Jakarta')))->modify('+6 hours')->format('H:i:s');unset($slot);
 $pattern=kbm_configure($db,1,'pola_waktu',$range+['sesi_kode'=>'siang','hari'=>5,'slots'=>$slots]);
 $ks=kbm_configure($db,1,'kelas_sesi',$range+['kelas_id'=>1,'sesi_kode'=>'siang']);
 kbm_configure($db,1,'ketua_kelas',$range+['kelas_id'=>1,'siswa_id'=>4]);
 $result[]=kbm_create_schedule($db,1,['kelas_sesi_id'=>$ks,'pola_id'=>$pattern,'pengajaran_id'=>$teaching,'jp_mulai'=>1,'jp_selesai'=>2,'berlaku_mulai'=>$start,'berlaku_sampai'=>$end]);
}echo json_encode($result);`,historyEnv));
 for(const [i,date]of ['2027-01-08','2027-07-02'].entries()){
  const id=nextPeriods[i];assert.equal(record('checkin',1,'7',date+' 13:30:00').ok,false);
  assert.equal(record('checkin',id,'7',date+' 12:59:59').ok,false);
  assert.equal(record('checkin',id,'7',date+' 13:30:00').ok,true);
  const current=query(`SELECT p.id,p.tahun_ajaran,p.semester,p.sesi_kode,l.checkin_at FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.tanggal='${date}'`)[0];
  assert.equal(current.sesi_kode,'siang');assert.equal(current.semester,i===0?'Genap':'Ganjil');assert.equal(current.tahun_ajaran,i===0?'2026/2027':'2027/2028');assert.equal(current.checkin_at,date+' 13:30:00');
  const live=feed('5','akademik/monitoring_kbm_data.php',{kelas:'1'},{KBM_TEST_NOW:date+' 13:31:00'});assert.equal(live.status,200);assert.equal(live.data.summary.hijau,1);
  assert.equal(record('checkout',current.id,'7',date+' 14:00:00').ok,true);
 }
 assert.equal(history({tanggal_mulai:'2026-10-16',tanggal_selesai:'2026-10-16'}).rows.find(r=>Number(r.jadwal_id)===1).tahun_ajaran,'2026/2027');
 // Semester form derives dates server-side even if browser supplies different dates.
 const semesterForm=JSON.parse(run(String.raw`
require 'includes/monitoring_kbm_admin.php';
kbm_admin_submit($db,1,['action'=>'kelas_sesi','periode_id'=>'1','kelas_id'=>'179','sesi_kode'=>'pagi','semester_penuh'=>'1','berlaku_mulai'=>'1900-01-01','berlaku_sampai'=>'2099-01-01']);
$ks=kbm_rows($db,'SELECT * FROM kbm_kelas_sesi WHERE kelas_id=179')[0];
$slots=kbm_rows($db,'SELECT jenis,jp,mulai,selesai FROM kbm_slot_waktu WHERE pola_id=1 ORDER BY urutan');
$p=kbm_configure($db,1,'pola_waktu',['periode_id'=>1,'sesi_kode'=>'pagi','hari'=>2,'berlaku_mulai'=>'2026-07-01','berlaku_sampai'=>'2026-12-31','slots'=>$slots]);
$db->exec("INSERT INTO pengajaran VALUES(8,1,1,179,'2026/2027','Ganjil')");
kbm_admin_submit($db,1,['action'=>'jadwal','periode_id'=>'1','semester_penuh'=>'1','kelas_sesi_id'=>(string)$ks['id'],'pola_id'=>(string)$p,'pengajaran_id'=>'8','jp_mulai'=>'1','jp_selesai'=>'2']);
echo json_encode(['session'=>$ks,'schedule'=>kbm_rows($db,'SELECT * FROM kbm_jadwal WHERE pengajaran_id=8')[0]]);`,historyEnv));
 assert.equal(semesterForm.session.berlaku_mulai,'2026-07-01');assert.equal(semesterForm.session.berlaku_sampai,'2026-12-31');assert.equal(semesterForm.schedule.berlaku_mulai,'2026-11-28');assert.equal(semesterForm.schedule.berlaku_sampai,'2026-12-31');
 // Simple JP workflow: all active school teachers, dated selection, original reporting infrastructure.
 run(String.raw`require 'includes/monitoring_kbm_pilihan.php';$sql=file_get_contents('database/2026_10_16_monitoring_kbm_pilihan_guru.sql');$db->exec($sql);$db->exec($sql);if(!kbm_pilihan_ready($db))throw new RuntimeException('Selection schema not ready');$db->exec("INSERT INTO users(id,role_id,is_active) VALUES(8,2,1);INSERT INTO guru VALUES(2,8,'Guru Lintas Kelas');UPDATE siswa SET kelas_id=179 WHERE id=4");kbm_configure($db,1,'ketua_kelas',['periode_id'=>1,'kelas_id'=>179,'siswa_id'=>4,'berlaku_mulai'=>'2026-07-01','berlaku_sampai'=>'2026-12-31']);`,historyEnv);
 const pick=(jp,guru,user='7',time='2026-11-27 07:10:00',extra={})=>JSON.parse(run(recordCode,{...env,KBM_USER:user,KBM_TEST_NOW:time,KBM_INPUT:JSON.stringify({action:'pilih_guru',jp:String(jp),guru_id:String(guru),...extra})}));
 assert.equal(pick(1,2,'6').status,403);assert.equal(pick(1,2,'7',undefined,{tanggal:'2026-10-01'}).ok,false);assert.equal(pick(7,2).ok,false);assert.equal(pick(1,99).ok,false);
 assert.equal(pick(1,2).ok,true);assert.equal(pick(1,2).ok,true);assert.equal(pick(1,1).ok,true);assert.equal(pick(1,2).ok,true);
 const selectedChoice=query("SELECT id,mapel_id,pengajaran_id FROM kbm_jadwal WHERE tanggal_pilihan='2026-11-27' AND dipilih_siswa_id=4 AND jp_mulai=1");assert.equal(selectedChoice.length,1);assert.equal(selectedChoice[0].mapel_id,null);assert.equal(selectedChoice[0].pengajaran_id,null);
 assert.equal(record('checkin',selectedChoice[0].id,'7','2026-11-27 07:11:00',{guru_id:'1'}).ok,false);
 let chosenFeed=feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-11-27 07:11:00'});assert.equal(chosenFeed.data.summary.merah,1);assert.match(chosenFeed.data.html,/Guru Lintas Kelas/);
 const simplePage=render('7',{KBM_TEST_NOW:'2026-11-27 07:11:00'});assert.equal(simplePage.status,200);assert.match(simplePage.html,/<select id="kbmTeacherSelect" name="guru_id"/);assert.match(simplePage.html,/<option value="2" selected>Guru Lintas Kelas<\/option>/);assert.doesNotMatch(simplePage.html,/teacher-grid|teacher-pick|kbmTeacherSearch/);assert.ok(simplePage.html.indexOf('kbmTeacherSelect')<simplePage.html.indexOf('id="kbmTodayTable"')); assert.match(simplePage.html,/Guru Lintas Kelas/);assert.match(simplePage.html,/Check-in Guru/);assert.match(simplePage.html,/Jadwal dan laporan hari ini/);
 const simultaneousChoice=await Promise.all(Array.from({length:6},()=>concurrent(selectedChoice[0].id,'2026-11-27 07:12:00','7')));assert.ok(simultaneousChoice.every(r=>r.ok));
 const selectedMeeting=query(`SELECT p.id,l.checkin_at FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.jadwal_id=${selectedChoice[0].id}`);assert.equal(selectedMeeting.length,1);assert.equal(selectedMeeting[0].checkin_at,'2026-11-27 07:12:00');
 assert.equal(pick(1,1).ok,false);
 const lockedTeacher=render('7',{KBM_TEST_NOW:'2026-11-27 07:13:00'});assert.match(lockedTeacher.html,/<select id="kbmTeacherSelect"[^>]*disabled/);assert.match(lockedTeacher.html,/Check-out Guru/);
 assert.equal(feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-11-27 07:13:00'}).data.summary.hijau,1);assert.equal(pick(2,1).ok,false);
 query('UPDATE kbm_ketua_kelas SET dicabut_pada=CURRENT_TIMESTAMP,dicabut_oleh=1 WHERE kelas_id=179');assert.equal(record('checkout',selectedMeeting[0].id,'7','2026-11-27 07:20:00').status,403);query('UPDATE kbm_ketua_kelas SET dicabut_pada=NULL,dicabut_oleh=NULL WHERE kelas_id=179');
 assert.equal(record('checkout',selectedMeeting[0].id,'7','2026-11-27 07:20:00').ok,true);assert.equal(record('checkout',selectedMeeting[0].id,'7','2026-11-27 07:21:00').ok,true);assert.equal(feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-11-27 07:21:00'}).data.summary.kuning,1);
 assert.equal(pick(2,1,'7','2026-11-27 07:45:00').ok,true);const jp2=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-11-27' AND dipilih_siswa_id=4 AND jp_mulai=2")[0].id;assert.equal(record('checkin',jp2,'7','2026-11-27 07:46:00').ok,true);const meeting2=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+jp2)[0].id;assert.equal(record('checkout',meeting2,'7','2026-11-27 08:00:00').ok,true);
 assert.equal(workbook({kelas:'179',tanggal_mulai:'2026-11-27',tanggal_selesai:'2026-11-27'}).count,2);assert.equal(history({kelas:'179',tanggal_mulai:'2026-11-27',tanggal_selesai:'2026-11-27'}).total,2);
 // Controller CSRF and a pre-existing weekly plan being replaced without duplicate history.
 query(`INSERT INTO kbm_jadwal(kelas_sesi_id,pola_id,pengajaran_id,guru_id,mapel_id,pengajaran_snapshot,jp_mulai,jp_selesai,berlaku_mulai,berlaku_sampai,dibuat_oleh) SELECT kelas_sesi_id,1,pengajaran_id,guru_id,mapel_id,pengajaran_snapshot,3,4,'2026-11-27','2026-11-27',dibuat_oleh FROM kbm_jadwal WHERE pengajaran_id=8 AND tanggal_pilihan IS NULL`);
 const choosePost={KBM_METHOD:'POST',KBM_TEST_NOW:'2026-11-27 08:41:00',KBM_POST_DATA:JSON.stringify({action:'pilih_guru',jp:'3',guru_id:'2'})};
 assert.match(render('7',choosePost).error,/Sesi keamanan/);const chosenPost=render('7',{...choosePost,KBM_POST_VALID:'1'});assert.equal(chosenPost.status,302);assert.equal(chosenPost.error,'');
 const thirdChoice=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-11-27' AND dipilih_siswa_id=4 AND jp_mulai=3")[0].id;
 assert.equal(record('checkin',thirdChoice,'7','2026-11-27 08:39:59').ok,true);
 const inPost=render('7',{KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_TEST_NOW:'2026-11-27 08:42:00',KBM_POST_DATA:JSON.stringify({action:'checkin',id:String(thirdChoice),guru_id:'2'})});assert.equal(inPost.status,302);assert.equal(inPost.error,'');
 const thirdMeeting=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+thirdChoice)[0].id;
 const outPost=render('7',{KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_TEST_NOW:'2026-11-27 08:50:00',KBM_POST_DATA:JSON.stringify({action:'checkout',id:String(thirdMeeting)})});assert.equal(outPost.status,302);assert.equal(outPost.error,'');
 const finalChoiceHistory=history({kelas:'179',tanggal_mulai:'2026-11-27',tanggal_selesai:'2026-11-27'});assert.equal(finalChoiceHistory.total,3);assert.equal(workbook({kelas:'179',tanggal_mulai:'2026-11-27',tanggal_selesai:'2026-11-27'}).count,3);
 assert.equal(render('6',{KBM_TEST_NOW:'2026-11-27 08:51:00'}).status,403);assert.equal(render('5',{KBM_TEST_NOW:'2026-11-27 08:51:00'}).status,403);
 // A contiguous JP range is one report, with overlap, break and stale-form guards.
 assert.equal(pick(1,2,'7','2026-12-04 07:10:00',{jp_selesai:'7'}).ok,false);
 assert.equal(pick(3,2,'7','2026-12-04 07:10:00',{jp_selesai:'2'}).ok,false);
 assert.equal(pick(1,2,'7','2026-12-04 07:10:00',{jp_selesai:'3'}).ok,true); // manual range ignores clock/break gates
 assert.equal(pick(1,2,'7','2026-12-04 07:10:00',{jp_selesai:'2'}).ok,true);
 assert.equal(pick(2,1,'7','2026-12-04 07:10:00').ok,false);
 const rangeChoice=query("SELECT id,jp_mulai,jp_selesai FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-04' AND dipilih_siswa_id=4");assert.equal(rangeChoice.length,1);assert.equal(rangeChoice[0].jp_mulai,1);assert.equal(rangeChoice[0].jp_selesai,2);
 const rangePage=render('7',{KBM_TEST_NOW:'2026-12-04 07:11:00',KBM_TEST_QUERY:JSON.stringify({jp:'1',akhir:'2'})});assert.equal(rangePage.status,200);assert.match(rangePage.html,/name="jp_selesai" value="2"/);assert.match(rangePage.html,/Guru untuk JP 1–2/);assert.equal((rangePage.html.match(/class="jp-pending" aria-current="true"/g)||[]).length,2);
 assert.match(rangePage.html,/href="\?jp=3&amp;pilih_awal=1"/);
 const startRangePage=render('7',{KBM_TEST_NOW:'2026-12-04 07:11:00',KBM_TEST_QUERY:JSON.stringify({jp:'3',pilih_awal:'1'})});assert.match(startRangePage.html,/href="\?jp=3&amp;akhir=5"/);
 assert.equal(record('checkin',rangeChoice[0].id,'7','2026-12-04 07:12:00',{guru_id:'2',jp_selesai:'1'}).ok,false);
 assert.equal(record('checkin',rangeChoice[0].id,'7','2026-12-04 07:12:00',{guru_id:'2',jp_selesai:'2'}).ok,true);
 const rangeMeeting=query('SELECT id,jp_mulai,jp_selesai,rencana_mulai,rencana_selesai FROM kbm_pertemuan WHERE jadwal_id='+rangeChoice[0].id)[0];assert.equal(rangeMeeting.jp_selesai,2);assert.equal(rangeMeeting.rencana_selesai,'2026-12-04 08:20:00');
 assert.equal(feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-04 07:50:00'}).data.summary.hijau,1);
 assert.equal(record('checkout',rangeMeeting.id,'7','2026-12-04 08:15:00').ok,true);
 const rangeHistory=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,['tanggal_mulai'=>'2026-12-04','tanggal_selesai'=>'2026-12-04','kelas'=>'179']));",{...historyEnv,KBM_TEST_NOW:'2026-12-04 12:00:00'}));assert.equal(rangeHistory.total,1);assert.equal(rangeHistory.rows[0].jp_selesai,2);
 assert.equal(pick(3,1,'7','2026-12-04 08:41:00',{jp_selesai:'5'}).ok,true);
 const triple=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-04' AND dipilih_siswa_id=4 AND jp_mulai=3")[0].id;
 assert.equal(pick(5,2,'7','2026-12-04 08:41:00').ok,false);assert.equal(record('checkin',triple,'7','2026-12-04 08:42:00',{jp_selesai:'5'}).ok,true);
 assert.equal(feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-04 09:40:00'}).data.summary.hijau,1);
 const tripleMeeting=query('SELECT id,jp_selesai FROM kbm_pertemuan WHERE jadwal_id='+triple)[0];assert.equal(tripleMeeting.jp_selesai,5);assert.equal(record('checkout',tripleMeeting.id,'7','2026-12-04 10:30:00').ok,true);
 // Manual choices are usable after planned hours and before planned start; old dated reports stay separate.
 assert.equal(pick(1,2,'7','2026-12-11 23:00:00',{jp_selesai:'3'}).ok,true);
 const lateChoice=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-11' AND dipilih_siswa_id=4")[0].id;
 const latePage=render('7',{KBM_TEST_NOW:'2026-12-11 23:00:30',KBM_TEST_QUERY:JSON.stringify({jp:'1',akhir:'3'})});assert.equal(latePage.status,200);assert.doesNotMatch(latePage.html,/disabled aria-describedby="kbmTeacherBlocked"/);assert.match(latePage.html,/Check-in Guru/);
 assert.equal(record('checkin',lateChoice,'7','2026-12-11 23:01:00').ok,true);
 const lateFeed=feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-11 23:02:00'});assert.equal(lateFeed.data.summary.hijau,1);assert.doesNotMatch(lateFeed.data.html,/Jadwal berakhir, belum check-out/);
 const lateHistory=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,['tanggal_mulai'=>'2026-12-11','tanggal_selesai'=>'2026-12-11','kelas'=>'179','status'=>'checkin']));",{...historyEnv,KBM_TEST_NOW:'2026-12-11 23:02:00'}));assert.equal(lateHistory.total,1);assert.equal(lateHistory.rows[0].status.code,'checkin');
 const lateMeeting=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+lateChoice)[0].id;assert.equal(record('checkout',lateMeeting,'7','2026-12-11 23:05:00').ok,true);assert.equal(feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-11 23:06:00'}).data.summary.kuning,1);
 assert.equal(pick(4,1,'7','2026-12-18 05:00:00',{jp_selesai:'6'}).ok,true);const earlyChoice=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-18' AND dipilih_siswa_id=4")[0].id;assert.equal(record('checkin',earlyChoice,'7','2026-12-18 05:01:00').ok,true);assert.equal(feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-18 05:02:00'}).data.summary.hijau,1);
 const nextDateFeed=feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-19 09:00:00'});assert.equal(nextDateFeed.data.summary.hijau,0);assert.match(nextDateFeed.data.html,/belum check-out/);
 const earlyMeeting=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+earlyChoice)[0].id;assert.equal(record('checkout',earlyMeeting,'7','2026-12-19 09:01:00').ok,true);
 // Reset only cancels an unreported choice; class ownership, CSRF and old reports stay protected.
 assert.equal(pick(1,2,'7','2026-12-25 23:00:00',{jp_selesai:'3'}).ok,true);const resetId=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-25' AND dipilih_siswa_id=4")[0].id;
 for(const user of ['4','5','6'])assert.equal(record('reset_pilihan',resetId,user,'2026-12-25 23:00:30').status,403);
 const resetPost={KBM_METHOD:'POST',KBM_TEST_NOW:'2026-12-25 23:00:30',KBM_POST_DATA:JSON.stringify({action:'reset_pilihan',id:String(resetId)})};assert.match(render('7',resetPost).error,/Sesi keamanan/);
 const beforeResetReports=query('SELECT COUNT(*) n FROM kbm_laporan')[0].n;const resetPage=render('7',{...resetPost,KBM_POST_VALID:'1'});assert.equal(resetPage.status,302);assert.equal(resetPage.error,'');
 const archivedChoice=query('SELECT nonaktif_mulai,tanggal_pilihan,pengajaran_snapshot FROM kbm_jadwal WHERE id='+resetId)[0];assert.equal(archivedChoice.nonaktif_mulai,'2026-12-25');assert.equal(archivedChoice.tanggal_pilihan,null);assert.equal(JSON.parse(archivedChoice.pengajaran_snapshot).reset_oleh,'7');assert.equal(query('SELECT COUNT(*) n FROM kbm_laporan')[0].n,beforeResetReports);
 assert.equal(record('reset_pilihan',resetId,'7','2026-12-25 23:00:40').ok,true);
 const clearedPage=render('7',{KBM_TEST_NOW:'2026-12-25 23:00:45',KBM_TEST_QUERY:JSON.stringify({reset:'1'})});assert.equal(clearedPage.status,200);assert.doesNotMatch(clearedPage.html,/class="active" aria-current="true"/);assert.match(clearedPage.html,/Pilih JP awal dan JP akhir terlebih dahulu/);
 assert.equal(pick(1,1,'7','2026-12-25 23:01:00',{jp_selesai:'3'}).ok,true);const replacementId=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-25' AND dipilih_siswa_id=4")[0].id;assert.notEqual(replacementId,resetId);assert.equal(record('checkin',replacementId,'7','2026-12-25 23:02:00').ok,true);assert.equal(record('reset_pilihan',replacementId,'7','2026-12-25 23:03:00').ok,false);const replacementMeeting=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+replacementId)[0].id;assert.equal(record('checkout',replacementMeeting,'7','2026-12-25 23:04:00').ok,true);assert.equal(record('reset_pilihan',replacementId,'7','2026-12-25 23:05:00').ok,false);
 const resetHistory=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,['tanggal_mulai'=>'2026-12-25','tanggal_selesai'=>'2026-12-25','kelas'=>'179']));",{...historyEnv,KBM_TEST_NOW:'2026-12-25 23:05:00'}));assert.equal(resetHistory.total,1);assert.equal(resetHistory.rows[0].checkin_efektif,'2026-12-25 23:02:00');
 // Table actions and audited cancellation before/after check-in and after completion.
 const cancel=(id,reason='Salah memilih guru',user='7',time='2026-12-25 23:06:00')=>JSON.parse(run(recordCode,{...env,KBM_USER:user,KBM_TEST_NOW:time,KBM_INPUT:JSON.stringify({action:'hapus_pilihan',id:String(id),alasan:reason})}));
 const actionPage=render('7',{KBM_TEST_NOW:'2026-12-25 23:05:00'});assert.equal(actionPage.status,200);assert.match(actionPage.html,/<th>Check-in \/ Check-out<\/th><th>Pembatalan<\/th>/);assert.doesNotMatch(actionPage.html,/Reset Pilihan/);assert.match(actionPage.html,/name="action" value="hapus_pilihan"/);
 assert.equal(cancel(replacementId,'').ok,false);for(const other of ['4','5','6'])assert.equal(cancel(replacementId,'Tidak berhak',other).status,403);
 const badCancel=render('7',{KBM_METHOD:'POST',KBM_TEST_NOW:'2026-12-25 23:06:00',KBM_POST_DATA:JSON.stringify({action:'hapus_pilihan',id:String(replacementId),alasan:'Salah memilih guru'})});assert.match(badCancel.error,/Sesi keamanan/);
 const originalLog=query('SELECT * FROM kbm_laporan WHERE pertemuan_id='+replacementMeeting)[0];const cancelPost=render('7',{KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_TEST_NOW:'2026-12-25 23:06:00',KBM_POST_DATA:JSON.stringify({action:'hapus_pilihan',id:String(replacementId),alasan:'Salah memilih guru'})});assert.equal(cancelPost.status,302);assert.equal(cancelPost.error,'');assert.deepEqual(query('SELECT * FROM kbm_laporan WHERE pertemuan_id='+replacementMeeting)[0],originalLog);
 assert.equal(cancel(replacementId,'Klik ulang').ok,true);
 let canceledHistory=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,['tanggal_mulai'=>'2026-12-25','tanggal_selesai'=>'2026-12-25','kelas'=>'179','status'=>'dibatalkan']));",{...historyEnv,KBM_TEST_NOW:'2026-12-25 23:06:30'}));assert.equal(canceledHistory.total,1);assert.equal(canceledHistory.rows[0].pembatalan_alasan,'Salah memilih guru');assert.equal(Number(canceledHistory.rows[0].pembatalan_oleh),7);assert.equal(canceledHistory.rows[0].status.code,'dibatalkan');assert.equal(canceledHistory.rows[0].status_code,'dibatalkan');
 assert.equal(pick(1,2,'7','2026-12-25 23:07:00',{jp_selesai:'3'}).ok,true);let cancelChoice=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-25' AND dipilih_siswa_id=4")[0].id;assert.equal(record('checkin',cancelChoice,'7','2026-12-25 23:07:10').ok,true);const cancelOpen=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+cancelChoice)[0].id;assert.equal(cancel(cancelChoice,'Guru yang dipilih keliru','7','2026-12-25 23:08:00').ok,true);assert.equal(query('SELECT checkout_at FROM kbm_laporan WHERE pertemuan_id='+cancelOpen)[0].checkout_at,null);assert.equal(record('checkout',cancelOpen,'7','2026-12-25 23:08:10').ok,false);
 const clearedFeed=feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-25 23:08:20'});assert.equal(clearedFeed.data.summary.hijau,0);assert.doesNotMatch(clearedFeed.data.html,/Belum check-out/);
 assert.equal(pick(1,1,'7','2026-12-25 23:09:00',{jp_selesai:'3'}).ok,true);cancelChoice=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-25' AND dipilih_siswa_id=4")[0].id;assert.equal(cancel(cancelChoice,'Batal mengisi pilihan','7','2026-12-25 23:09:10').ok,true);
 const cancellationBook=JSON.parse(run(String.raw`require 'includes/monitoring_kbm_export.php';require 'includes/nilai_excel_reader.php';$e=kbm_history_export_file($db,['tanggal_mulai'=>'2026-12-25','tanggal_selesai'=>'2026-12-25','kelas'=>'179','status'=>'dibatalkan']);$xml=stream_get_contents($e['file']);fclose($e['file']);ni_xml($xml);echo json_encode(['count'=>$e['count'],'xml'=>$xml]);`,{...historyEnv,KBM_TEST_NOW:'2026-12-25 23:10:00'}));assert.equal(cancellationBook.count,3);assert.match(cancellationBook.xml,/Salah memilih guru/);assert.match(cancellationBook.xml,/Dibatalkan/);
 const cancelDetail=render('5',{KBM_TEST_ROUTE:'akademik/riwayat_kbm.php',KBM_TEST_NOW:'2026-12-25 23:10:00',KBM_TEST_QUERY:JSON.stringify({tanggal_mulai:'2026-12-25',tanggal_selesai:'2026-12-25',detail_jadwal:String(replacementId),detail_tanggal:'2026-12-25'})});assert.equal(cancelDetail.status,200);assert.match(cancelDetail.html,/Dibatalkan oleh pengguna #7/);assert.match(cancelDetail.html,/Salah memilih guru/);assert.match(cancelDetail.html,/2026-12-25 23:02:00/);

 // Free numbered JP: no pattern for Saturday, no generated/fictitious school times.
 const noClock={...historyEnv,KBM_TEST_NOW:'2026-12-26 14:03:00'};
 run("require 'includes/monitoring_kbm_pilihan.php';$sql=file_get_contents('database/2026_10_17_monitoring_kbm_tanpa_jam.sql');$db->exec($sql);$db->exec($sql);if(!kbm_pilihan_tanpa_jam_ready($db))throw new RuntimeException('Free JP schema missing');",noClock);
 assert.equal(query('SELECT COUNT(*) n FROM kbm_pola_waktu WHERE hari=6')[0].n,0);
 const freePage=render('7',{KBM_TEST_NOW:noClock.KBM_TEST_NOW,KBM_TEST_QUERY:JSON.stringify({jp:'1',akhir:'3'})});assert.equal(freePage.status,200);assert.doesNotMatch(freePage.html,/Admin perlu melengkapi waktu JP|Jam JP belum tersedia|disabled aria-describedby="kbmTeacherBlocked"/);assert.match(freePage.html,/Guru Lintas Kelas/);
 assert.equal(pick(1,2,'7',noClock.KBM_TEST_NOW,{jp_selesai:'3'}).ok,true);
 const freeChoice=query("SELECT id,pola_id FROM kbm_jadwal WHERE tanggal_pilihan='2026-12-26' AND dipilih_siswa_id=4")[0];assert.equal(freeChoice.pola_id,null);
 const freeHistory=()=>JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,['tanggal_mulai'=>'2026-12-26','tanggal_selesai'=>'2026-12-26','kelas'=>'179']));",noClock));
 assert.equal(freeHistory().total,1);assert.equal(freeHistory().rows[0].status_code,'belum_checkin');assert.equal(freeHistory().rows[0].rencana_mulai,null);
 assert.equal(record('checkin',freeChoice.id,'4',noClock.KBM_TEST_NOW).ok,false);
 assert.equal(record('checkin',freeChoice.id,'7','2026-12-26 14:03:10').ok,true);assert.equal(record('checkin',freeChoice.id,'7','2026-12-26 14:03:11').ok,true);
 const freeMeeting=query('SELECT id,rencana_mulai,rencana_selesai FROM kbm_pertemuan WHERE jadwal_id='+freeChoice.id)[0];assert.equal(freeMeeting.rencana_mulai,null);assert.equal(freeMeeting.rencana_selesai,null);
 const freeFeed=feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2026-12-26 14:03:30'});assert.equal(freeFeed.data.summary.hijau,1);assert.doesNotMatch(freeFeed.data.html,/Perlu konfigurasi/);assert.match(freeFeed.data.html,/Tanpa jadwal jam/);
 assert.equal(record('checkout',freeMeeting.id,'7','2026-12-26 14:04:10').ok,true);
 noClock.KBM_TEST_NOW='2026-12-26 14:05:00';const finalFree=freeHistory();assert.equal(finalFree.rows[0].checkout_efektif,'2026-12-26 14:04:10');assert.equal(finalFree.rows[0].status_code,'selesai');
 const freeBook=JSON.parse(run(String.raw`require 'includes/monitoring_kbm_export.php';require 'includes/nilai_excel_reader.php';$e=kbm_history_export_file($db,['tanggal_mulai'=>'2026-12-26','tanggal_selesai'=>'2026-12-26','kelas'=>'179']);$xml=stream_get_contents($e['file']);fclose($e['file']);ni_xml($xml);echo json_encode(['count'=>$e['count'],'xml'=>$xml]);`,{...noClock,KBM_TEST_NOW:'2026-12-26 14:05:00'}));assert.equal(freeBook.count,1);assert.match(freeBook.xml,/Pilihan JP tanpa jadwal jam/);
 assert.equal(cancel(freeChoice.id,'Salah memilih JP','7','2026-12-26 14:06:00').ok,true);
 // Deliberately duplicated schedules only in the disposable fixture exercise the real count guard.
 query(`INSERT INTO kbm_jadwal(kelas_sesi_id,pola_id,pengajaran_id,guru_id,mapel_id,pengajaran_snapshot,jp_mulai,jp_selesai,berlaku_mulai,berlaku_sampai,dibuat_oleh)
 SELECT j.kelas_sesi_id,j.pola_id,j.pengajaran_id,j.guru_id,j.mapel_id,j.pengajaran_snapshot,j.jp_mulai,j.jp_selesai,j.berlaku_mulai,j.berlaku_sampai,j.dibuat_oleh FROM kbm_jadwal j CROSS JOIN kelas a CROSS JOIN kelas b CROSS JOIN kelas c WHERE j.id=2 LIMIT 8000`);
 assert.match(workbook().notice,/50.000/);
 { // Simplified setup: no active period, no JP time, and an old holiday must not block reporting.
 const simpleEnv={...historyEnv,KBM_TEST_NOW:'2028-01-02 09:00:00'};
 run("require 'includes/monitoring_kbm_pilihan.php';$sql=file_get_contents('database/2026_10_18_monitoring_kbm_sederhana.sql');$db->exec($sql);$db->exec($sql);if(!kbm_simple_ready($db))throw new RuntimeException('Simple schema missing');",simpleEnv);
 assert.equal(query("SELECT COUNT(*) n FROM kbm_periode WHERE '2028-01-02' BETWEEN mulai AND selesai")[0].n,0);
 const simpleSubmit=(input,user=1)=>JSON.parse(run("require 'includes/monitoring_kbm_admin.php';try{echo json_encode(['ok'=>true,'message'=>kbm_admin_submit($db,(int)getenv('KBM_ACTOR'),json_decode(getenv('KBM_DATA'),true))]);}catch(InvalidArgumentException|RuntimeException $e){echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}",{...simpleEnv,KBM_ACTOR:String(user),KBM_DATA:JSON.stringify(input)}));
 for(const action of ['periode','pola_waktu','hari_tanpa_kbm','edit_hari_tanpa_kbm','hapus_hari_tanpa_kbm','jadwal'])assert.equal(simpleSubmit({action}).ok,false);
 assert.equal(simpleSubmit({action:'simple_sesi',kelas_id:'179',sesi_kode:'siang'}).ok,true);assert.equal(simpleSubmit({action:'simple_sesi',kelas_id:'179',sesi_kode:'malam'}).ok,false);
 assert.equal(query('SELECT periode_id FROM kbm_kelas_sesi WHERE kelas_id=179 ORDER BY id DESC LIMIT 1')[0].periode_id,null);
 for(const actor of [2,4,5,6,7])assert.equal(simpleSubmit({action:'simple_sesi',kelas_id:'179',sesi_kode:'pagi'},actor).ok,false);
 // Existing active chair remains authorized after its old semester/date limits expire.
 const simplePage=render('7',{KBM_TEST_NOW:simpleEnv.KBM_TEST_NOW});assert.equal(simplePage.status,200);assert.match(simplePage.html,/Guru Lintas Kelas/);assert.doesNotMatch(simplePage.html,/Admin perlu melengkapi waktu JP|hari tanpa KBM/);
 assert.equal(pick(1,2,'7',simpleEnv.KBM_TEST_NOW,{jp_selesai:'2'}).ok,true);
 const simpleChoice=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2028-01-02' AND dipilih_siswa_id=4")[0].id;
 assert.equal(record('checkin',simpleChoice,'7','2028-01-02 09:01:00').ok,true);
 const simpleMeeting=query('SELECT id,tahun_ajaran,semester FROM kbm_pertemuan WHERE jadwal_id='+simpleChoice)[0];assert.equal(simpleMeeting.tahun_ajaran,'2027/2028');assert.equal(simpleMeeting.semester,'Genap');
 const simpleFeed=feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2028-01-02 09:02:00'});assert.equal(simpleFeed.data.summary.hijau,1);assert.doesNotMatch(simpleFeed.data.html,/Pola waktu|Periode tahun ajaran/);
 assert.equal(record('checkout',simpleMeeting.id,'7','2028-01-02 09:05:00').ok,true);
 const simpleHistory=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,['tanggal_mulai'=>'2028-01-02','tanggal_selesai'=>'2028-01-02','kelas'=>'179']));",{...simpleEnv,KBM_TEST_NOW:'2028-01-02 09:06:00'}));assert.equal(simpleHistory.total,1);assert.equal(simpleHistory.rows[0].status_code,'selesai');
 // Revoke/reassign without a period, reject an outsider, preserve the original reporter.
 const simpleChair=query('SELECT id FROM kbm_ketua_kelas WHERE siswa_id=4 AND dicabut_pada IS NULL ORDER BY id DESC LIMIT 1')[0].id;
 assert.equal(simpleSubmit({action:'cabut_ketua',id:String(simpleChair)}).ok,true);assert.equal(render('7',{KBM_TEST_NOW:simpleEnv.KBM_TEST_NOW}).status,403);
 assert.equal(simpleSubmit({action:'simple_ketua',kelas_id:'179',siswa_id:'2'}).ok,false);
 assert.equal(simpleSubmit({action:'simple_ketua',kelas_id:'179',siswa_id:'4'}).ok,true);assert.equal(query('SELECT periode_id FROM kbm_ketua_kelas WHERE siswa_id=4 ORDER BY id DESC LIMIT 1')[0].periode_id,null);
 const simpleAdmin=render('1',{KBM_TEST_ROUTE:'admin/monitoring_kbm.php',KBM_TEST_NOW:simpleEnv.KBM_TEST_NOW});assert.equal(simpleAdmin.status,200);assert.match(simpleAdmin.html,/Simpan Sesi Kelas/);assert.doesNotMatch(simpleAdmin.html,/Tampilkan Periode|tab=periode|tab=waktu|tab=libur/);
 const simpleBadPost=render('1',{KBM_TEST_ROUTE:'admin/monitoring_kbm.php',KBM_METHOD:'POST',KBM_TEST_NOW:simpleEnv.KBM_TEST_NOW,KBM_POST_DATA:JSON.stringify({action:'simple_sesi',kelas_id:'179',sesi_kode:'pagi'})});assert.match(simpleBadPost.error,/Sesi keamanan/);
 const simplePost=render('1',{KBM_TEST_ROUTE:'admin/monitoring_kbm.php',KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_TEST_NOW:simpleEnv.KBM_TEST_NOW,KBM_POST_DATA:JSON.stringify({action:'simple_sesi',kelas_id:'179',sesi_kode:'pagi'})});assert.equal(simplePost.status,302);assert.equal(simplePost.error,'');
 const simpleBook=JSON.parse(run(String.raw`require 'includes/monitoring_kbm_export.php';require 'includes/nilai_excel_reader.php';$e=kbm_history_export_file($db,['tanggal_mulai'=>'2028-01-02','tanggal_selesai'=>'2028-01-02','kelas'=>'179']);$xml=stream_get_contents($e['file']);fclose($e['file']);ni_xml($xml);echo json_encode(['count'=>$e['count'],'xml'=>$xml]);`,{...simpleEnv,KBM_TEST_NOW:'2028-01-02 09:06:00'}));assert.equal(simpleBook.count,1);assert.match(simpleBook.xml,/2028-01-02T09:01:00/);

 // JP progress is terminal, absence is a report rather than a fabricated check-in.
 const donePage=render('7',{KBM_TEST_NOW:'2028-01-02 09:07:00'});const doneNav=donePage.html.match(/<nav class="jp-nav"[\s\S]*?<\/nav>/)[0];assert.equal((doneNav.match(/class="jp-done"/g)||[]).length,2);assert.doesNotMatch(doneNav,/href="\?jp=[12](?:&|"|$)/);
 assert.equal(pick(1,2,'7','2028-01-02 09:07:00',{jp_selesai:'2'}).ok,false);
 assert.equal(pick(3,2,'7','2028-01-02 09:08:00',{jp_selesai:'4'}).ok,true);
 const absentId=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2028-01-02' AND jp_mulai=3 AND dipilih_siswa_id=4")[0].id;
 const absentInput={action:'guru_tidak_masuk',id:String(absentId),guru_id:'2',jp_selesai:'4'};
 const absence=(input=absentInput,user='7',time='2028-01-02 09:09:00')=>JSON.parse(run(recordCode,{...simpleEnv,KBM_USER:user,KBM_TEST_NOW:time,KBM_INPUT:JSON.stringify(input)}));
 for(const other of ['4','5','6'])assert.equal(absence(absentInput,other).status,403);
 assert.equal(absence({...absentInput,guru_id:'1'}).ok,false);assert.equal(absence({...absentInput,jp_selesai:'6'}).ok,false);assert.equal(absence({...absentInput,checkin_at:'2028-01-01'}).ok,false);
 const noCsrf=render('7',{KBM_METHOD:'POST',KBM_TEST_NOW:'2028-01-02 09:09:00',KBM_POST_DATA:JSON.stringify(absentInput)});assert.match(noCsrf.error,/Sesi keamanan/);
 const pendingAbsence=render('7',{KBM_TEST_NOW:'2028-01-02 09:09:00',KBM_TEST_QUERY:JSON.stringify({jp:'3',akhir:'4'})});assert.match(pendingAbsence.html,/Guru Tidak Masuk/);
 const absenceClicks=await Promise.all(Array.from({length:6},()=>concurrent(absentId,'2028-01-02 09:09:00','7',{action:'guru_tidak_masuk',guru_id:'2',jp_selesai:'4'})));assert.ok(absenceClicks.every(r=>r.ok));
 const absenceMeeting=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+absentId)[0].id;assert.equal(query('SELECT COUNT(*) n FROM kbm_laporan WHERE pertemuan_id='+absenceMeeting)[0].n,0);
 assert.equal(record('checkin',absentId,'7','2028-01-02 09:10:00').ok,false);assert.equal(record('checkout',absenceMeeting,'7','2028-01-02 09:10:00').ok,false);assert.equal(pick(3,2,'7','2028-01-02 09:10:00',{jp_selesai:'4'}).ok,false);
 const absentPage=render('7',{KBM_TEST_NOW:'2028-01-02 09:10:00'});assert.equal((absentPage.html.match(/class="jp-absent"/g)||[]).length,2);assert.match(absentPage.html,/Guru tidak masuk/);
 const absenceFeed=feed('5','akademik/monitoring_kbm_data.php',{kelas:'179'},{KBM_TEST_NOW:'2028-01-02 09:10:00'});assert.equal(absenceFeed.data.summary.merah,1);assert.match(absenceFeed.data.html,/Laporan ketua kelas/);
 const absenceHistory=JSON.parse(run("require 'includes/monitoring_kbm_history.php';echo json_encode(kbm_history_page($db,['tanggal_mulai'=>'2028-01-02','tanggal_selesai'=>'2028-01-02','kelas'=>'179','status'=>'guru_tidak_masuk']));",{...simpleEnv,KBM_TEST_NOW:'2028-01-02 09:10:00'}));assert.equal(absenceHistory.total,1);assert.equal(absenceHistory.rows[0].checkin_efektif,null);assert.equal(absenceHistory.rows[0].checkout_efektif,null);assert.equal(absenceHistory.rows[0].status.code,'guru_tidak_masuk');
 const absenceBook=JSON.parse(run(String.raw`require 'includes/monitoring_kbm_export.php';require 'includes/nilai_excel_reader.php';$e=kbm_history_export_file($db,['tanggal_mulai'=>'2028-01-02','tanggal_selesai'=>'2028-01-02','kelas'=>'179','status'=>'guru_tidak_masuk']);$xml=stream_get_contents($e['file']);fclose($e['file']);ni_xml($xml);echo json_encode(['count'=>$e['count'],'xml'=>$xml]);`,{...simpleEnv,KBM_TEST_NOW:'2028-01-02 09:10:00'}));assert.equal(absenceBook.count,1);assert.match(absenceBook.xml,/Guru tidak masuk/);assert.match(absenceBook.xml,/Laporan asli guru tidak masuk/);

 // Waka matrix: every class and JP, historical sessions, absence and no-report are distinct.
 const matrix=(input,time='2028-01-02 09:10:00')=>JSON.parse(run("require 'includes/monitoring_kbm_matrix.php';echo json_encode(kbm_history_matrix($db,json_decode(getenv('MATRIX_FILTER'),true)));",{...simpleEnv,KBM_TEST_NOW:time,MATRIX_FILTER:JSON.stringify(input)}));
 const matrixAll=matrix({tanggal:'2028-01-02'});assert.equal(matrixAll.rows.length,query('SELECT COUNT(*) n FROM kelas')[0].n);
 const matrixClass=matrixAll.rows.find(r=>r.id===179);assert.equal(matrixClass.cells[1].kind,'hadir');assert.equal(matrixClass.cells[2].kind,'hadir');assert.equal(matrixClass.cells[3].kind,'tidak_masuk');assert.equal(matrixClass.cells[4].kind,'tidak_masuk');assert.equal(matrixClass.cells[5],null);assert.equal(matrixClass.cells[6],null);
 const matrixMorning=matrix({tanggal:'2028-01-02',sesi:'pagi'}).rows.find(r=>r.id===179);assert.equal(matrixMorning.cells[1],null);assert.equal(matrixMorning.cells[3].kind,'tidak_masuk');
 const matrixAfternoon=matrix({tanggal:'2028-01-02',sesi:'siang'}).rows.find(r=>r.id===179);assert.equal(matrixAfternoon.cells[1].kind,'hadir');assert.equal(matrixAfternoon.cells[3],null);
 const wakaMatrix=render('5',{KBM_TEST_ROUTE:'akademik/riwayat_kbm.php',KBM_TEST_NOW:'2028-01-02 09:10:00',KBM_TEST_QUERY:JSON.stringify({tanggal:'2028-01-02'})});assert.equal(wakaMatrix.status,200);assert.match(wakaMatrix.html,/Rekap JP per Kelas/);assert.match(wakaMatrix.html,/fa-circle-check/);assert.match(wakaMatrix.html,/fa-circle-minus/);assert.doesNotMatch(wakaMatrix.html,/name="guru"|name="kelas"|name="status"|name="per_halaman"|name="tanggal_mulai"|name="action"/);assert.equal((wakaMatrix.html.match(/data-matrix-class=/g)||[]).length,matrixAll.rows.length);
 const matrixExport=(filter)=>JSON.parse(run(String.raw`require 'includes/monitoring_kbm_matrix_export.php';require 'includes/nilai_excel_reader.php';$e=kbm_matrix_export_file($db,json_decode(getenv('EXCEL_MATRIX_FILTER'),true));$xml=stream_get_contents($e['file']);fclose($e['file']);$doc=ni_xml($xml);$xp=new DOMXPath($doc);$xp->registerNamespace('s','urn:schemas-microsoft-com:office:spreadsheet');$file=tempnam(sys_get_temp_dir(),'matrix-book-');try{file_put_contents($file,$xml);$book=ni_read_excel($file);}finally{unlink($file);}echo json_encode(['count'=>$e['count'],'filename'=>$e['filename'],'xml'=>$xml,'rows'=>$book['rows'],'formulas'=>$xp->query('//@s:Formula')->length,'green'=>$xp->query('//s:Style[@s:ID="Status0"]/s:Font/@s:Color')->item(0)->value,'red'=>$xp->query('//s:Style[@s:ID="Status1"]/s:Font/@s:Color')->item(0)->value]);`,{...simpleEnv,KBM_TEST_NOW:'2028-01-02 09:10:00',EXCEL_MATRIX_FILTER:JSON.stringify(filter)}));
 const allMatrixBook=matrixExport({tanggal:'2028-01-02',password:'not-exported',halaman:'2'});assert.equal(allMatrixBook.count,matrixAll.rows.length);assert.equal(allMatrixBook.rows.length,matrixAll.rows.length+6);assert.equal(allMatrixBook.formulas,0);assert.equal(allMatrixBook.green,'#16874B');assert.equal(allMatrixBook.red,'#D62F43');assert.match(allMatrixBook.filename,/2028-01-02_Semua_Sesi/);assert.doesNotMatch(allMatrixBook.xml,/not-exported/);
 const exportedClass=allMatrixBook.rows.find(r=>r[1]===matrixClass.name);assert.deepEqual(exportedClass.slice(3,9),['✓','✓','⊖','⊖','—','—']);
 const pagiBook=matrixExport({tanggal:'2028-01-02',sesi:'pagi'});assert.equal(pagiBook.count,matrix({tanggal:'2028-01-02',sesi:'pagi'}).rows.length);assert.match(pagiBook.filename,/Pagi/);const exportedMorning=pagiBook.rows.find(r=>r[1]===matrixClass.name);assert.deepEqual(exportedMorning.slice(3,7),['—','—','⊖','⊖']);
 assert.match(wakaMatrix.html,/rekap_kbm_excel\.php\?tanggal=2028-01-02&amp;sesi=/);
 const wakaDownload={KBM_TEST_ROUTE:'akademik/rekap_kbm_excel.php',KBM_TEST_NOW:'2028-01-02 09:10:00',KBM_TEST_QUERY:JSON.stringify({tanggal:'2028-01-02',sesi:'pagi'})};const wakaFile=render('5',wakaDownload);assert.equal(wakaFile.status,200);assert.match(wakaFile.html,/^<\?xml/);assert.match(wakaFile.html,/REKAP MONITORING KBM/);
 for(const user of ['1','2','6','7'])assert.equal(render(user,wakaDownload).status,403);
 assert.equal(render('5',{...wakaDownload,KBM_METHOD:'POST'}).status,405);assert.equal(render('5',{...wakaDownload,KBM_ANONYMOUS:'1'}).status,302);
 const emptyMatrixBook=render('5',{...wakaDownload,KBM_TEST_QUERY:JSON.stringify({tanggal:'1900-01-01',sesi:'pagi'})});assert.equal(emptyMatrixBook.status,302);assert.match(emptyMatrixBook.error,/Tidak ada kelas/);assert.doesNotMatch(emptyMatrixBook.html,/<Workbook/);
 const invalidMatrixBook=render('5',{...wakaDownload,KBM_TEST_QUERY:JSON.stringify({tanggal:'2028-01-03'})});assert.equal(invalidMatrixBook.status,302);assert.doesNotMatch(invalidMatrixBook.html,/<Workbook/);
 query('UPDATE users SET is_active=0 WHERE id=5');const blockedDownload=render('5',wakaDownload);assert.ok([302,403].includes(blockedDownload.status));assert.doesNotMatch(blockedDownload.html,/<Workbook/);query('UPDATE users SET is_active=1 WHERE id=5');
 for(const user of ['2','6','7'])assert.equal(render(user,{KBM_TEST_ROUTE:'akademik/riwayat_kbm.php'}).status,403);
 for(const invalid of [{tanggal:'2028-01-03'},{tanggal:['2028-01-02']},{tanggal:'2028-02-30'},{sesi:'malam'}])assert.equal(render('5',{KBM_TEST_ROUTE:'akademik/riwayat_kbm.php',KBM_TEST_NOW:'2028-01-02 09:10:00',KBM_TEST_QUERY:JSON.stringify(invalid)}).status,400);
 // Check-in versus absence is serialized: exactly one can win, with no conflicting reports.
 assert.equal(pick(5,2,'7','2028-01-02 09:11:00',{jp_selesai:'6'}).ok,true);const raceId=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2028-01-02' AND jp_mulai=5 AND dipilih_siswa_id=4")[0].id;
 const race=await Promise.all([concurrent(raceId,'2028-01-02 09:12:00','7'),concurrent(raceId,'2028-01-02 09:12:00','7',{action:'guru_tidak_masuk',guru_id:'2',jp_selesai:'6'})]);assert.equal(race.filter(r=>r.ok).length,1);
 const raceMeeting=query('SELECT id FROM kbm_pertemuan WHERE jadwal_id='+raceId)[0].id;
 if(race[0].ok)assert.equal(record('checkout',raceMeeting,'7','2028-01-02 09:13:00').ok,true);
 const allDone=render('7',{KBM_TEST_NOW:'2028-01-02 09:14:00'});assert.match(allDone.html,/Semua JP hari ini sudah dilaporkan/);assert.equal((allDone.html.match(/type="button" disabled aria-label="JP/g)||[]).length,6);
 assert.equal(cancel(absentId,'Salah memilih guru','7','2028-01-02 09:15:00').ok,true);assert.equal(matrix({tanggal:'2028-01-02'},'2028-01-02 09:15:30').rows.find(r=>r.id===179).cells[3],null);assert.equal(pick(3,1,'7','2028-01-02 09:16:00',{jp_selesai:'4'}).ok,true);

 // Valid controller POST, revoked chair, and admin correction preserve the original absence report.
 assert.equal(pick(1,2,'7','2028-01-03 09:00:00',{jp_selesai:'2'}).ok,true);
 const correctionChoice=query("SELECT id FROM kbm_jadwal WHERE tanggal_pilihan='2028-01-03' AND jp_mulai=1 AND dipilih_siswa_id=4")[0].id;
 run("require 'includes/monitoring_kbm_history.php';kbm_materialize($db,1,'2028-01-03');",{...simpleEnv,KBM_TEST_NOW:'2028-01-03 09:00:00'});
 const beforeAbsenceRevision=run("require 'includes/monitoring_kbm_history.php';echo kbm_history_revision(kbm_history_row($db,"+correctionChoice+",'2028-01-03'));",{...simpleEnv,KBM_TEST_NOW:'2028-01-03 09:00:00'});
 const httpAbsent={action:'guru_tidak_masuk',id:String(correctionChoice),guru_id:'2',jp_selesai:'2'};
 query('UPDATE kbm_ketua_kelas SET dicabut_pada=CURRENT_TIMESTAMP,dicabut_oleh=1 WHERE siswa_id=4 AND dicabut_pada IS NULL');
 assert.equal(render('7',{KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_TEST_NOW:'2028-01-03 09:01:00',KBM_POST_DATA:JSON.stringify(httpAbsent)}).status,403);
 query('UPDATE kbm_ketua_kelas SET dicabut_pada=NULL,dicabut_oleh=NULL WHERE id=(SELECT n FROM (SELECT MAX(id) n FROM kbm_ketua_kelas WHERE siswa_id=4) latest)');
 const absencePost=render('7',{KBM_METHOD:'POST',KBM_POST_VALID:'1',KBM_TEST_NOW:'2028-01-03 09:01:00',KBM_POST_DATA:JSON.stringify(httpAbsent)});assert.equal(absencePost.status,302);assert.equal(absencePost.error,'');
 const correctionResult=JSON.parse(run(String.raw`require 'includes/monitoring_kbm_history.php';$id=(int)getenv('REPORT_ID');$r=kbm_history_row($db,$id,'2028-01-03');$before=kbm_history_revision($r);$staleRejected=false;try{kbm_history_correct($db,1,['jadwal_id'=>(string)$id,'tanggal'=>'2028-01-03','revision'=>getenv('OLD_REVISION'),'alasan'=>'Uji formulir lama','checkin'=>'2028-01-03T09:00','checkout'=>'2028-01-03T09:30']);}catch(InvalidArgumentException $e){$staleRejected=true;}kbm_history_correct($db,1,['jadwal_id'=>(string)$id,'tanggal'=>'2028-01-03','revision'=>$before,'alasan'=>'Verifikasi admin: laporan tidak masuk keliru','checkin'=>'2028-01-03T09:00','checkout'=>'2028-01-03T09:30']);$r=kbm_history_row($db,$id,'2028-01-03');echo json_encode(['stale'=>$staleRejected,'status'=>kbm_meeting_status($r,'2028-01-03 10:00:00'),'raw'=>$r['checkin_asli'],'absence'=>$r['guru_tidak_masuk'],'corrected'=>$r['checkin_efektif']]);`,{...simpleEnv,KBM_TEST_NOW:'2028-01-03 10:00:00',REPORT_ID:String(correctionChoice),OLD_REVISION:beforeAbsenceRevision}));assert.equal(correctionResult.stale,true);assert.equal(correctionResult.status.code,'selesai');assert.equal(correctionResult.raw,null);assert.match(correctionResult.absence,/2028-01-03 09:01:00/);assert.equal(correctionResult.corrected,'2028-01-03 09:00:00');

 // Admin shows all assigned users, enforces four and revokes an individual only.
 query("INSERT INTO users(id,role_id,is_active) VALUES(9,3,1),(10,3,1),(11,3,1)");query("INSERT INTO siswa(id,user_id,kelas_id,nama_lengkap,nis) VALUES(5,9,179,'Pengguna Kedua','005'),(6,10,179,'Pengguna Ketiga','006'),(7,11,179,'Pengguna Keempat','007')");
 for(const student of ['5','6','7'])assert.equal(simpleSubmit({action:'simple_ketua',kelas_id:'179',siswa_id:student}).ok,true);
 const usersPage=render('1',{KBM_TEST_ROUTE:'admin/monitoring_kbm.php',KBM_TEST_NOW:'2028-01-04 09:00:00',KBM_TEST_QUERY:JSON.stringify({tab:'ketua',kelas:'179'})});assert.equal(usersPage.status,200);assert.match(usersPage.html,/4\/4/);for(const name of ['Pengganti','Pengguna Kedua','Pengguna Ketiga','Pengguna Keempat'])assert.match(usersPage.html,new RegExp(name));assert.match(usersPage.html,/Batas sudah tercapai/);assert.doesNotMatch(usersPage.html,/name="action" value="simple_ketua"/);
 assert.equal(render('7',{KBM_TEST_NOW:'2028-01-04 09:00:00'}).status,200);
 const secondPermission=query('SELECT id FROM kbm_ketua_kelas WHERE siswa_id=5 AND dicabut_pada IS NULL ORDER BY id DESC LIMIT 1')[0].id;assert.equal(simpleSubmit({action:'cabut_ketua',id:String(secondPermission)}).ok,true);
 const fewerUsers=render('1',{KBM_TEST_ROUTE:'admin/monitoring_kbm.php',KBM_TEST_NOW:'2028-01-04 09:01:00',KBM_TEST_QUERY:JSON.stringify({tab:'ketua',kelas:'179'})});assert.match(fewerUsers.html,/3\/4/);assert.match(fewerUsers.html,/Tambah Pengguna/);assert.equal(render('7',{KBM_TEST_NOW:'2028-01-04 09:01:00'}).status,200);

 }
});
