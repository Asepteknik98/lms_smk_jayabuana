// Run: node --test tests/rekap-period.test.cjs
// PHP uses connection-local temporary tables; existing school data is untouched.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const vm=require('node:vm');
const fs=require('node:fs');
const php=String.raw`<?php
require 'config/database.php';
$db=Database::getInstance();
$schemas=[
 'users'=>'id INT,role_id INT',
 'guru'=>'id INT,user_id INT,nama_lengkap VARCHAR(80),nip VARCHAR(30)',
 'kelas'=>'id INT,nama_kelas VARCHAR(80)',
 'mapel'=>'id INT,nama_mapel VARCHAR(80)',
 'pengajaran'=>'id INT,guru_id INT,kelas_id INT,mapel_id INT,semester VARCHAR(20),tahun_ajaran VARCHAR(20)',
 'rekap_mengajar'=>'id INT,pengajaran_id INT,tanggal DATE,jenis_sesi VARCHAR(20),jam_ke_mulai INT,jam_ke_selesai INT,jumlah_jp INT,jam_mulai TIME',
 'validasi_rekap_mengajar'=>'guru_id INT,periode_bulan DATE,total_jp INT,divalidasi_oleh INT,divalidasi_pada DATETIME'
];
foreach($schemas as $table=>$columns)$db->exec("CREATE TEMPORARY TABLE $table ($columns)");
$db->exec("INSERT INTO users VALUES(1,1)");
$db->exec("INSERT INTO guru VALUES(1,101,'Guru Sendiri','1'),(2,102,'Guru Lain','2')");
$db->exec("INSERT INTO kelas VALUES(1,'Kelas A'),(2,'Kelas B')");
$db->exec("INSERT INTO mapel VALUES(1,'Mapel A'),(2,'Mapel B')");
$db->exec("INSERT INTO pengajaran VALUES(1,1,1,1,'Ganjil','2026/2027'),(2,1,2,2,'Ganjil','2026/2027'),(3,2,1,1,'Ganjil','2026/2027')");
$db->exec("INSERT INTO rekap_mengajar VALUES
 (1,1,'2026-08-24','Pagi',1,1,1,'07:00'),
 (2,1,'2026-08-25','Pagi',1,2,2,'07:00'),
 (3,1,'2026-09-10','Pagi',1,3,3,'07:00'),
 (4,1,'2026-09-11','Pagi',1,4,4,'07:00'),
 (5,3,'2026-09-01','Pagi',1,6,90,'07:00'),
 (6,2,'2026-09-01','Pagi',1,5,5,'07:00'),
 (7,1,'2024-02-29','Pagi',1,6,6,'07:00'),
 (8,1,'2024-03-01','Pagi',1,6,7,'07:00')");
$db->exec("INSERT INTO validasi_rekap_mengajar VALUES(1,'2026-09-01',12,1,NOW())");
session_save_path(sys_get_temp_dir());require 'config/session.php';
$_GET=json_decode(getenv('LMS_REKAP_TEST_QUERY'),true);
$rm_is_admin=!empty($_GET['test_admin']);
if(!empty($_GET['test_validated']))$db->exec("INSERT INTO validasi_rekap_mengajar VALUES(1,'2026-08-01',3,1,NOW())");
$_SESSION['user_id']=101;$_SESSION['role_id']=$rm_is_admin?1:2;
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']=$rm_is_admin?'/admin/rekap_guru_mengajar.php':'/guru/rekap_mengajar.php';
ob_start();register_shutdown_function(function(){
 $output=ob_get_clean();
 echo json_encode(['http'=>http_response_code()?:200,'output'=>$output,'total'=>$GLOBALS['totalMonthly']??null,'detailTotal'=>$GLOBALS['totalActual']??null,'ids'=>array_column($GLOBALS['details']??[],'id'),'validated'=>isset($GLOBALS['validationByTeacher'][1]),'state'=>$GLOBALS['state']??[]]);
 session_destroy();
});
require 'includes/rekap_mengajar_module.php';
`;
function run(query){
 const result=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:php,encoding:'utf8',env:{...process.env,LMS_REKAP_TEST_QUERY:JSON.stringify(query)}});
 assert.equal(result.status,0,result.stderr);
 assert.equal(result.stderr,'');
 return JSON.parse(result.stdout);
}
const range={periode:'rentang',tanggal_mulai:'2026-08-25',tanggal_selesai:'2026-09-10',format:'excel'};
test('range includes both boundary dates and excludes other teachers',()=>{
 const r=run({...range,guru_id:2});
 assert.equal(r.total,10);assert.equal(r.detailTotal,10);assert.deepEqual(r.ids.sort(),[2,3,6]);
 assert.equal(r.validated,false);assert.match(r.output,/25\/08\/2026 - 10\/09\/2026/);
 assert.equal((r.output.match(/Divalidasi Admin/g)||[]).length,2);
 assert.equal((r.output.match(/Belum Divalidasi/g)||[]).length,1);
});
test('monthly view retains full-month totals',()=>{const r=run({bulan:'2026-09',format:'excel'});assert.equal(r.total,12);assert.equal(r.validated,true);});
test('class and subject filters affect details while overall period total stays explicit',()=>{
 const r=run({...range,kelas_id:1,mapel_id:1});assert.equal(r.total,10);assert.equal(r.detailTotal,5);
});
test('all recorded months must be validated for a validated range',()=>{assert.equal(run({...range,test_validated:1}).validated,true);});
test('PDF export uses selected range and totals',()=>{const r=run({...range,format:'pdf'});assert.match(r.output,/^%PDF-1.4/);assert.match(r.output,/25\/08\/2026 - 10\/09\/2026/);assert.match(r.output,/TOTAL 10 JP/);});
test('single leap day is a valid inclusive range',()=>{assert.equal(run({...range,tanggal_mulai:'2024-02-29',tanggal_selesai:'2024-02-29'}).total,6);});
for(const [name,dates] of Object.entries({reversed:{tanggal_mulai:'2026-09-11'},invalid:{tanggal_mulai:'2026-02-30'},missing:{tanggal_mulai:''},array:{tanggal_mulai:[]}})){
 test('invalid range rejected: '+name,()=>{assert.equal(run({...range,...dates}).http,400);});
}
test('admin remains in monthly mode even with range query parameters',()=>{const r=run({...range,bulan:'2026-09',test_admin:1,guru_id:1});assert.equal(r.total,12);assert.equal(r.state.periode,undefined);});
test('teacher HTML preserves date filter in export links',()=>{const r=run({...range,format:''});assert.match(r.output,/id="rmPeriodMode"/);assert.match(r.output,/tanggal_mulai=2026-08-25/);assert.match(r.output,/tanggal_selesai=2026-09-10/);});
test('invalid HTML range shows error and disables export links',()=>{const r=run({...range,format:'',tanggal_mulai:'2026-09-20'});assert.equal(r.total,0);assert.match(r.output,/Tanggal mulai tidak boleh/);assert.doesNotMatch(r.output,/format=excel/);});
test('inline scripts compile',()=>{
 const source=fs.readFileSync('includes/rekap_mengajar_module.php','utf8').replace(/<\?[\s\S]*?\?>/g,'null');
 for(const match of source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(match[1]);
});
