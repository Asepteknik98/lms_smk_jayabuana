const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
function phpTest(code){
    const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',['-d','session.save_path='+require('node:os').tmpdir()],{input:code,encoding:'utf8',cwd:path.resolve(__dirname,'..')});
    assert.equal(result.status,0,result.stderr+result.stdout);assert.equal(result.stdout,'PASS');
}
test('Legacy activity aliases keep their grades and only one column per activity',()=>phpTest(String.raw`<?php
require 'config/database.php';require 'includes/rapor_batch.php';$db=Database::getInstance();
function ck($v,$m){if(!$v)throw new RuntimeException($m);}
$db->exec('CREATE TEMPORARY TABLE siswa(id INT PRIMARY KEY,kelas_id INT,nama_lengkap VARCHAR(100))');$db->exec("INSERT INTO siswa VALUES(1,1,'Uji')");
foreach(['database/2026_10_28_rapor.sql','database/2026_11_03_rapor_administrasi.sql','database/2026_11_03_rapor_riwayat.sql'] as $file){$sql=file_get_contents($file);$sql=preg_replace('/^--.*$/m','',$sql);$sql=preg_replace('/^\s*CONSTRAINT fk_[^\n]+\n/m','',$sql);$sql=preg_replace('/,\s*\)/',"\n)",$sql);foreach(explode(';',str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql)) as $query)if(trim($query))$db->exec($query);}
$db->exec("INSERT INTO rapor(siswa_id,kelas_id,tahun_ajaran,semester,jenis,nama_siswa,nama_kelas) VALUES(1,1,'2026/2027','Ganjil','UTS','Uji','X A')");$id=(int)$db->lastInsertId();
$db->prepare("INSERT INTO rapor_ekstrakurikuler(rapor_id,nama_kegiatan,grade,urutan) VALUES(?,'OSIS','A',1),(?,'Basic-English Club','B',2)")->execute([$id,$id]);
$names=rapor_ekstra_names([['nama_kegiatan'=>'OSIS'],['nama_kegiatan'=>'Basic-English Club']]);ck(count($names)===23,'Aliases must not add duplicate activity columns');
$r=rapor_load($db,$id);$grades=array_fill_keys($names,'');foreach(rapor_ekstra_template($r['ekstra']) as $row)$grades[$row['nama_kegiatan']]=$row['grade']??'';
$data=['batch_action'=>'extras','batch_complete'=>'1','kelas_id'=>1,'tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','extras_payload'=>json_encode([['id'=>$id,'version'=>rapor_version($r),'grades'=>$grades]])];
rapor_batch($db,$data,1);$r=rapor_load($db,$id);$grades=array_column(rapor_ekstra_template($r['ekstra']),'grade','nama_kegiatan');ck($grades['Osis']==='A'&&$grades['Basic English Club']==='B','Saving matrix preserves legacy grades');ck(count($r['ekstra'])===2,'Aliases updated without duplicates');
$data['extras_payload']=json_encode([['id'=>$id,'version'=>rapor_version($r),'grades'=>['Osis'=>'']]]);rapor_batch($db,$data,1);$r=rapor_load($db,$id);$grades=array_column(rapor_ekstra_template($r['ekstra']),'grade','nama_kegiatan');ck($grades['Osis']===null&&$grades['Basic English Club']==='B','Clearing one activity preserves others');ck(!$db->inTransaction(),'Read leaves no open transaction');echo 'PASS';
?>`));
for(const scenario of ['malformed','truncated'])test(`Teacher page rejects ${scenario} POST without a fatal error or write`,()=>phpTest(String.raw`<?php
require 'config/auth.php';$db=Database::getInstance();
$db->exec('CREATE TEMPORARY TABLE roles(id INT,nama_role VARCHAR(50))');$db->exec("INSERT INTO roles VALUES(2,'Guru')");
$db->exec('CREATE TEMPORARY TABLE users(id INT,username VARCHAR(50),role_id INT,is_active INT,password VARCHAR(100))');$db->exec("INSERT INTO users VALUES(777,'uji',2,1,'hash')");
$_SESSION['user_id']=777;$_SESSION['role_id']=2;$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_NAME']='/guru/rapor.php';$_GET=[];
$_POST=['csrf_token'=>$_SESSION['csrf_token'],'details_ready'=>'1','kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS'];${scenario==='malformed'?'$_POST[\'batch_action\']=[];':''}
$before=(int)$db->query('SELECT COUNT(*) FROM rapor')->fetchColumn();
ob_start();require 'guru/rapor.php';$html=ob_get_clean();
if(!str_contains($html,${scenario==='malformed'?"'Tindakan kelas tidak valid.'":"'Data formulir rapor tidak lengkap.'"})||(int)$db->query('SELECT COUNT(*) FROM rapor')->fetchColumn()!==$before)throw new RuntimeException('Invalid request did not fail safely');
session_destroy();echo 'PASS';
?>`));
test('Record IDs support the full database range while attendance keeps its own limit',()=>phpTest(String.raw`<?php
require 'includes/rapor.php';
if(rapor_id('70000','rapor')!==70000||rapor_id('4294967295','rapor')!==4294967295)throw new RuntimeException('Valid ID rejected');
foreach(['4294967296','-1',[],true] as $bad){try{rapor_id($bad,'rapor');throw new LogicException('Invalid ID accepted');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}}
try{rapor_number('70000','sakit');throw new LogicException('Attendance limit changed');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}echo 'PASS';
?>`));
