// Run: node --test tests/nilai-import.test.cjs
// All database writes use connection-local temporary tables.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {spawnSync}=require('node:child_process');
test('Import page renders both modes and rejects invalid CSRF, expired previews and foreign assignments',()=>{
 const setup=String.raw`<?php
require 'config/session.php';require 'config/database.php';
$_SESSION['user_id']=101;$_SESSION['role_id']=2;
$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF']='/guru/rekap_nilai_import.php';
$_SERVER['REQUEST_METHOD']='GET';$_GET=['pengajaran_id'=>1];$_POST=[];
$db=Database::getInstance();
foreach([
'guru'=>'id INT,user_id INT,nama_lengkap VARCHAR(100)',
'kelas'=>'id INT,nama_kelas VARCHAR(100)',
'mapel'=>'id INT,nama_mapel VARCHAR(100)',
'pengajaran'=>'id INT,guru_id INT,kelas_id INT,mapel_id INT,semester VARCHAR(30),tahun_ajaran VARCHAR(30)',
'komponen_penilaian'=>'id INT,pengajaran_id INT,nama_komponen VARCHAR(100),urutan INT',
'siswa'=>'id INT,kelas_id INT,nama_lengkap VARCHAR(100)',
'nilai_komponen'=>'komponen_id INT,siswa_id INT,nilai DECIMAL(5,2)',
'capaian_penilaian'=>'komponen_id INT,siswa_id INT,deskripsi TEXT,saran TEXT'
] as $table=>$schema)$db->exec("CREATE TEMPORARY TABLE $table ($schema)");
$db->exec("INSERT INTO guru VALUES(1,101,'Guru Uji')");
$db->exec("INSERT INTO kelas VALUES(1,'10 TP 3')");$db->exec("INSERT INTO mapel VALUES(1,'KKA')");
$db->exec("INSERT INTO pengajaran VALUES(1,1,1,1,'Ganjil','2026/2027'),(2,2,1,1,'Ganjil','2026/2027')");
$db->exec("INSERT INTO komponen_penilaian VALUES(1,1,'UTS',1),(2,1,'Kehadiran',2)");
$db->exec("INSERT INTO siswa VALUES(1,1,'Siswa Uji')");
$db->exec("INSERT INTO capaian_penilaian VALUES(1,1,'Teks lama','Saran lama')");
register_shutdown_function(static function(){session_destroy();});
`;
 const run=code=>{
  const r=spawnSync('C:/xampp/php/php.exe',['-d','display_errors=1','-d','session.save_path='+os.tmpdir()],{input:setup+code+"\nrequire 'guru/rekap_nilai_import.php';",encoding:'utf8',timeout:15000});
  assert.equal(r.status,0,r.stderr+r.stdout);assert.equal(r.stderr,'');assert.doesNotMatch(r.stdout,/Warning:|Fatal error:|Notice:/);return r.stdout;
 };
 const html=run('');assert.match(html,/File Excel/);assert.match(html,/>UTS<\/option>/);assert.doesNotMatch(html,/>Kehadiran<\/option>/);
 assert.match(run("$_GET['mode']='capaian';"),/Deskripsi Capaian Pembelajaran/);
 assert.match(run("$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>'wrong'];"),/Permintaan tidak sah/);
 assert.match(run("$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>$_SESSION['csrf_token'],'action'=>'confirm','preview_token'=>'expired'];"),/Pratinjau sudah kedaluwarsa/);
 assert.equal(run("$_GET['pengajaran_id']=2;"),'Pengajaran tidak dapat diakses.');
 const template=run("$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>$_SESSION['csrf_token'],'action'=>'template','komponen_id'=>1];");
 assert.ok(template.startsWith('PK'));assert.match(template,/Siswa Uji/);assert.match(template,/Deskripsi Capaian Pembelajaran/);
 assert.match(run("$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>$_SESSION['csrf_token'],'action'=>'template','komponen_id'=>2];"),/Kehadiran tidak dapat diimpor/);
 const preview=run("$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>$_SESSION['csrf_token'],'action'=>'preview','mode'=>'capaian','narrative_mode'=>'replace','komponen_id'=>1,'deskripsi'=>'Teks baru','saran'=>''];");
 assert.match(preview,/Konfirmasi Pembaruan Capaian/);assert.match(preview,/Teks lama/);assert.match(preview,/→ Teks baru/);assert.match(preview,/Saran lama/);
 const confirmed=run(String.raw`
require 'includes/nilai_import.php';
$context=ni_context($db,1,1,1);
$_SESSION['nilai_import_previews']['test']=['user'=>101,'guru'=>1,'pid'=>1,'mode'=>'capaian','component'=>1,'component_name'=>'UTS','kelas_id'=>1,'semester'=>'Ganjil','tahun_ajaran'=>'2026/2027','expires'=>time()+60,'replace'=>true,'description'=>'Teks baru','advice'=>'','candidates'=>ni_narrative_preview($db,$context,'Teks baru','',true)];
$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>$_SESSION['csrf_token'],'action'=>'confirm','mode'=>'capaian','preview_token'=>'test','deskripsi'=>'POST diabaikan','narrative_mode'=>'fill'];
register_shutdown_function(static function()use($db){echo json_encode($db->query('SELECT deskripsi,saran FROM capaian_penilaian WHERE komponen_id=1 AND siswa_id=1')->fetch(PDO::FETCH_ASSOC));});
`);
 assert.deepEqual(JSON.parse(confirmed),{deskripsi:'Teks baru',saran:'Saran lama'});
});
function zip(entries){
 const locals=[],central=[];let offset=0;
 const crc32=b=>{let crc=0xffffffff;for(const n of b){crc^=n;for(let i=0;i<8;i++)crc=(crc>>>1)^((crc&1)?0xedb88320:0);}return (crc^0xffffffff)>>>0;};
 for(const [name,body] of Object.entries(entries)){
  const n=Buffer.from(name),b=Buffer.from(body),z=require('node:zlib').deflateRawSync(b),crc=crc32(b);
  const h=Buffer.alloc(30);h.writeUInt32LE(0x04034b50);h.writeUInt16LE(20,4);h.writeUInt16LE(8,8);h.writeUInt32LE(crc,14);h.writeUInt32LE(z.length,18);h.writeUInt32LE(b.length,22);h.writeUInt16LE(n.length,26);
  const c=Buffer.alloc(46);c.writeUInt32LE(0x02014b50);c.writeUInt16LE(20,4);c.writeUInt16LE(20,6);c.writeUInt16LE(8,10);c.writeUInt32LE(crc,16);c.writeUInt32LE(z.length,20);c.writeUInt32LE(b.length,24);c.writeUInt16LE(n.length,28);c.writeUInt32LE(offset,42);
  locals.push(h,n,z);central.push(c,n);offset+=h.length+n.length+z.length;
 }
 const directory=Buffer.concat(central),end=Buffer.alloc(22);end.writeUInt32LE(0x06054b50);end.writeUInt16LE(Object.keys(entries).length,8);end.writeUInt16LE(Object.keys(entries).length,10);end.writeUInt32LE(directory.length,12);end.writeUInt32LE(offset,16);
 return Buffer.concat([...locals,directory,end]);
}
test('Excel formats, name/class matching, insert-only grades and fill-only narratives',()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'lms-import-test-'));
 const rows=[{A:'Rekap Penilaian Per Siswa'}, {}, {A:'No',B:'Nama Siswa',C:'NISN',D:'Kelas',N:'Nilai'}, {N:'Asli',O:'Penyesuaian'},
 {A:'1',B:' aEnY ',C:'different-nisn',D:'X-10 TP 3',N:'99',O:'100'},
 {A:'2',B:' Budi   SANTOSO ',D:'X-10 TP 3',N:'80',O:'0'},
 {A:'3',B:'Citra',D:'10 TP 3',N:'75.5'},
 {A:'4',B:'Doni',D:'10 TP 3',N:'70'}, {A:'5',B:' DONI ',D:'10 TP 3',N:'90'},
 {A:'6',B:'Eka',D:'10 TP 3',N:'80'}, {A:'7',B:'Fajar',D:'10 TP 3',N:'80',O:'invalid'},
 {A:'8',B:'Gita',D:'10 TP 3',N:'80'}, {A:'9',B:'Hana',D:'10 TP 3'},
 {A:'10',B:'Ika',D:'10 TP 3',N:'90'}, {A:'11',B:'Citra',D:'10 TP 2',N:'100'},
 {A:'12',B:'Nama Lain',D:'10 TP 3',N:'100'}];
 const esc=s=>s.replaceAll('&','&amp;').replaceAll('<','&lt;');
 const sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:O17"/><sheetData>'+rows.map((row,i)=>'<row r="'+(i+1)+'">'+Object.entries(row).map(([c,v])=>'<c r="'+c+(i+1)+'" t="inlineStr"><is><t>'+esc(v)+'</t></is></c>').join('')+'</row>').join('')+'</sheetData></worksheet>';
 const entries={
 '[Content_Types].xml':'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>',
 '_rels/.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
 'xl/workbook.xml':'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Nilai" sheetId="1" r:id="r1"/></sheets></workbook>',
 'xl/_rels/workbook.xml.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
 'xl/worksheets/sheet1.xml':sheet};
 fs.writeFileSync(path.join(dir,'grades.xlsx'),zip(entries));
 const cyclic=Buffer.from(fs.readFileSync('tests/fixtures/simplexls-books.xls'));
 const fat=cyclic.readUInt32LE(76),root=cyclic.readUInt32LE(48);
 cyclic.writeUInt32LE(root,(fat+1)*512+root*4);
 fs.writeFileSync(path.join(dir,'cyclic.xls'),cyclic);
 fs.writeFileSync(path.join(dir,'truncated.xls'),cyclic.subarray(0,100));
 fs.writeFileSync(path.join(dir,'bomb.xlsx'),zip({...entries,'xl/bomb.xml':'x'.repeat(9*1024*1024)}));
 fs.writeFileSync(path.join(dir,'grades.xls'),'<!DOCTYPE html><html><table><tr><th rowspan="2">Nama Siswa</th><th rowspan="2">Kelas</th><th colspan="2">Nilai</th></tr><tr><th>Asli</th><th>Penyesuaian</th></tr><tr><td>Budi Santoso</td><td>10 TP 3</td><td>80</td><td>0</td></tr></table></html>');
 fs.writeFileSync(path.join(dir,'xml.xls'),'<?xml version="1.0"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Nilai"><Table><Row><Cell><Data ss:Type="String">Nama Siswa</Data></Cell><Cell><Data ss:Type="String">Kelas</Data></Cell><Cell><Data ss:Type="String">Asli</Data></Cell></Row><Row><Cell><Data ss:Type="String">Citra</Data></Cell><Cell><Data ss:Type="String">10 TP 3</Data></Cell><Cell><Data ss:Type="Number">75.5</Data></Cell></Row></Table></Worksheet></Workbook>');
 const php=String.raw`<?php
require 'config/database.php';require 'includes/nilai_import.php';require 'includes/nilai_excel_reader.php';
function ok($condition,$message){if(!$condition)throw new RuntimeException($message);}
function reject($fn){try{$fn();}catch(Throwable $e){return;}throw new RuntimeException('Expected rejection');}
$db=Database::getInstance();$dir=getenv('IMPORT_FIXTURES');
$book=ni_read_excel($dir.'/grades.xlsx');$records=ni_excel_records($book['rows']);ok($book['sheet']==='Nilai','Sheet name');
ok(ni_excel_records(ni_read_excel($dir.'/grades.xls')['rows'])[0]['raw']==='0','HTML rowspan/colspan');
ok(ni_excel_records(ni_read_excel($dir.'/xml.xls')['rows'])[0]['raw']==='75.5','Spreadsheet XML');
ok(count(ni_read_excel('tests/fixtures/simplexls-books.xls')['rows'])>0,'Binary XLS');
reject(fn()=>ni_read_excel($dir.'/cyclic.xls'));reject(fn()=>ni_read_excel($dir.'/truncated.xls'));
reject(fn()=>ni_read_excel($dir.'/bomb.xlsx'));reject(fn()=>ni_read_excel($dir.'/grades.xlsx',3));
reject(fn()=>ni_xml('<!DOCTYPE x [<!ENTITY t SYSTEM "file:///secret">]><x>&t;</x>'));
reject(fn()=>ni_excel_records([['Nama Siswa','Kelas','Skor']]));
$schemas=[
'kelas'=>'id INT PRIMARY KEY,nama_kelas VARCHAR(100)',
'mapel'=>'id INT PRIMARY KEY,nama_mapel VARCHAR(100)',
'pengajaran'=>'id INT PRIMARY KEY,guru_id INT,kelas_id INT,mapel_id INT,semester VARCHAR(30),tahun_ajaran VARCHAR(30)',
'siswa'=>'id INT PRIMARY KEY,kelas_id INT,nama_lengkap VARCHAR(100)',
'komponen_penilaian'=>'id INT PRIMARY KEY,pengajaran_id INT,nama_komponen VARCHAR(100)',
'nilai_komponen'=>'komponen_id INT,siswa_id INT,nilai DECIMAL(5,2),PRIMARY KEY(komponen_id,siswa_id)',
'riwayat_nilai'=>'pengajaran_id INT,siswa_id INT,komponen_id INT,guru_id INT,nilai_lama DECIMAL(5,2),nilai_baru DECIMAL(5,2)',
'capaian_penilaian'=>'komponen_id INT,siswa_id INT,deskripsi TEXT,saran TEXT,PRIMARY KEY(komponen_id,siswa_id)'];
foreach($schemas as $t=>$schema)$db->exec("CREATE TEMPORARY TABLE $t ($schema) ENGINE=InnoDB");
$db->exec("INSERT INTO kelas VALUES(1,'10 TP 3'),(2,'10 TP 2')");$db->exec("INSERT INTO mapel VALUES(1,'KKA')");
$db->exec("INSERT INTO pengajaran VALUES(1,1,1,1,'Ganjil','2026/2027'),(2,2,1,1,'Ganjil','2026/2027')");
$db->exec("INSERT INTO siswa VALUES(1,1,'Aeny'),(2,1,'Budi Santoso'),(3,1,'Citra'),(4,1,'Doni'),(5,1,'Eka'),(6,1,'EKA'),(7,1,'Fajar'),(8,1,'Gita'),(9,1,'Hana'),(10,1,'Ika'),(11,2,'Citra')");
$db->exec("INSERT INTO komponen_penilaian VALUES(1,1,'UTS'),(2,1,'UAS'),(3,1,'Tugas Harian'),(4,1,'Kehadiran'),(5,1,'Ujian Praktik'),(6,2,'UTS'),(7,1,'Ulangan Harian')");
$db->exec('INSERT INTO nilai_komponen VALUES(1,1,0),(1,8,60),(6,3,40),(4,2,88)');
$ctx=ni_context($db,1,1,1);$preview=ni_preview($ctx,$records);$ready=array_values(array_filter($preview,fn($r)=>$r['ready']));
ok(count($ready)===3,'Only three unambiguous missing scores');
ok($ready[0]['student_id']===2&&$ready[0]['value']===0.0&&$ready[0]['source']==='Penyesuaian','Name matching and adjusted zero');
ok($ready[1]['student_id']===3&&$ready[1]['value']===75.5,'Original fallback');
ok(str_contains($preview[0]['status'],'sudah tersimpan'),'Existing zero skipped');
ok(str_contains($preview[3]['status'],'berulang')&&str_contains($preview[5]['status'],'ganda'),'Duplicate Excel and LMS names');
ok(str_contains($preview[6]['status'],'angka'),'Invalid adjusted value must not fall back');
ok(str_contains($preview[10]['status'],'kelas berbeda'),'Other class skipped');
reject(fn()=>ni_context($db,2,1,1));reject(fn()=>ni_context($db,1,1,6));reject(fn()=>ni_context($db,1,1,4));
$bad=$ready;$bad[1]['value']=999;reject(fn()=>ni_commit($db,1,1,1,$bad));
ok((int)$db->query('SELECT COUNT(*) FROM nilai_komponen WHERE komponen_id=1 AND siswa_id=2')->fetchColumn()===0,'Rollback all candidates on failure');
// A teacher saves a value after preview: confirm must preserve it.
$db->exec('INSERT INTO nilai_komponen VALUES(1,10,33)');
$result=ni_commit($db,1,1,1,$ready);ok($result===['inserted'=>2,'skipped'=>1,'fields'=>0],'Recheck current values during commit');
ok((float)$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=1 AND siswa_id=1')->fetchColumn()===0.0,'Saved zero preserved');
ok((float)$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=1 AND siswa_id=10')->fetchColumn()===33.0,'Concurrent value preserved');
ok((float)$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=6')->fetchColumn()===40.0,'Other teacher unchanged');
ok((float)$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=4')->fetchColumn()===88.0,'Attendance untouched');
ok(ni_commit($db,1,1,1,$ready)['inserted']===0,'Repeat import does not overwrite');
ok((int)$db->query('SELECT COUNT(*) FROM riwayat_nilai')->fetchColumn()===2,'History only for new grades');
foreach([2,3,5,7] as $component){$r=ni_commit($db,1,1,$component,[$ready[1]]);ok($r['inserted']===1,'Each allowed component supports insert');}
$snapshot=['component_name'=>'UTS','kelas_id'=>1,'semester'=>'Ganjil','tahun_ajaran'=>'2026/2027'];
$db->exec("UPDATE komponen_penilaian SET nama_komponen='UAS' WHERE id=1");reject(fn()=>ni_commit($db,1,1,1,$ready,$snapshot));$db->exec("UPDATE komponen_penilaian SET nama_komponen='UTS' WHERE id=1");
$db->exec("INSERT INTO capaian_penilaian VALUES(1,1,'Deskripsi lama',''),(1,2,'','Saran lama'),(1,3,'Lengkap lama','Saran lengkap'),(2,1,'UAS lama','UAS saran')");
$beforeScores=$db->query('SELECT * FROM nilai_komponen ORDER BY komponen_id,siswa_id')->fetchAll();
$p=ni_narrative_preview($db,ni_context($db,1,1,1),'Deskripsi kelas','Saran kelas');
ok(!$p[0]['description']&&$p[0]['advice'],'Fill only missing advice');
ok($p[1]['description']&&!$p[1]['advice'],'Fill only missing description');
$candidates=array_values(array_filter($p,fn($r)=>$r['description']||$r['advice']));
$db->exec("UPDATE capaian_penilaian SET saran='Saran baru guru' WHERE komponen_id=1 AND siswa_id=1");
ni_narrative_commit($db,1,1,1,'Deskripsi kelas','Saran kelas',$candidates);
$saved=$db->query('SELECT * FROM capaian_penilaian WHERE komponen_id=1 ORDER BY siswa_id')->fetchAll();
ok($saved[0]['deskripsi']==='Deskripsi lama'&&$saved[0]['saran']==='Saran baru guru','Narratives saved after preview retained');
ok($saved[1]['deskripsi']==='Deskripsi kelas'&&$saved[1]['saran']==='Saran lama','Fill individual blank field only');
ok($saved[2]['deskripsi']==='Lengkap lama','Complete text retained');
ok(ni_narrative_commit($db,1,1,1,'Different','Different',$candidates)['fields']===0,'Repeated class fill cannot overwrite');
ok($db->query('SELECT * FROM nilai_komponen ORDER BY komponen_id,siswa_id')->fetchAll()===$beforeScores,'Class narratives never change grades');
reject(fn()=>ni_narrative_commit($db,2,1,1,'x','y',$candidates));reject(fn()=>ni_narrative_commit($db,1,1,3,'x','y',$candidates));reject(fn()=>ni_narrative_text(str_repeat('x',2001)));
// Templates must round-trip through the actual reader, with the selected roster.
require 'includes/nilai_template.php';
foreach([1,2,3,5,7] as $cid){
    $context=ni_context($db,1,1,$cid);$file=$dir.'/template-'.$cid.'.xlsx';
    file_put_contents($file,ni_template_xlsx($context));$template=ni_read_excel($file)['rows'];
    $period=in_array($cid,[1,2],true);
    ok($template[4]===($period?['Nama Siswa','Kelas','Nilai','Deskripsi Capaian Pembelajaran','Saran Capaian Pembelajaran']:['Nama Siswa','Kelas','Nilai']),'Headers follow component');
    $records=ni_excel_records($template);ok(count($records)===10,'Class roster populated');
    ok($records[0]['name']==='Aeny'&&$records[0]['class']==='10 TP 3'&&$records[0]['raw']==='','Names and blank score');
    ok($records[0]['source']==='Nilai','New header accepted');
}
// Import narratives independently of numeric scores, only into blank UTS/UAS fields.
$rows=[['Nama Siswa','Kelas','Nilai','Deskripsi Capaian Pembelajaran','Saran Capaian Pembelajaran'],
['Aeny','10 TP 3','99','Replacement blocked','Replacement blocked'],
['Budi Santoso','10 TP 3','88','Capaian Excel','Saran Excel'],
['Hana','10 TP 3','0','Hana menguasai materi','Latihan Hana'],
['Eka','10 TP 3','90','Ambiguous','Ambiguous'],
['Citra','10 TP 2','90','Wrong class','Wrong class']];
$db->exec('INSERT INTO nilai_komponen VALUES(2,2,0)');
$records=ni_excel_records($rows);$p=ni_import_preview($db,ni_context($db,1,1,2),$records);
ok(!$p[0]['description']&&!$p[0]['advice'],'Existing narrative not overwritten');
ok($p[1]['ready']&&!$p[1]['score_ready']&&$p[1]['description'],'Text can import despite existing numeric score');
ok($p[2]['score_ready']&&$p[2]['description'],'Zero and text can import together');
ok(!$p[3]['ready']&&!$p[4]['ready'],'Ambiguous or other class cannot import text');
$batch=array_values(array_filter($p,fn($r)=>$r['ready']));
$r=ni_commit($db,1,1,2,$batch);ok($r['fields']===4,'Four blank narrative fields filled');
ok((float)$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=2 AND siswa_id=9')->fetchColumn()===0.0,'New zero saved');
ok((float)$db->query('SELECT nilai FROM nilai_komponen WHERE komponen_id=2 AND siswa_id=2')->fetchColumn()===0.0,'Existing zero preserved while importing text');
ok($db->query('SELECT deskripsi FROM capaian_penilaian WHERE komponen_id=2 AND siswa_id=1')->fetchColumn()==='UAS lama','Old text preserved');
ok(ni_commit($db,1,1,2,$batch)['fields']===0,'Repeat Excel cannot overwrite text');
$p=ni_import_preview($db,ni_context($db,1,1,3),$records);foreach($p as $r)ok(!$r['description']&&!$r['advice'],'Non-period components ignore narratives');
// Replacing class text fixes editing, preserves blank input fields and all grades.
$beforeScores=$db->query('SELECT * FROM nilai_komponen ORDER BY komponen_id,siswa_id')->fetchAll();
$p=ni_narrative_preview($db,ni_context($db,1,1,1),'Revisi kelas','',true);
ok($p[0]['description']&&!$p[0]['advice']&&$p[0]['old_description']==='Deskripsi lama','Replacement preview includes previous text');
$r=ni_narrative_commit($db,1,1,1,'Revisi kelas','',$p,[],true);
ok($r['fields']===10,'Replace includes all class students, including individual text');
ok($db->query('SELECT deskripsi FROM capaian_penilaian WHERE komponen_id=1 AND siswa_id=3')->fetchColumn()==='Revisi kelas','Individual text replaced');
ok($db->query('SELECT saran FROM capaian_penilaian WHERE komponen_id=1 AND siswa_id=1')->fetchColumn()==='Saran baru guru','Blank input does not erase advice');
ok($db->query('SELECT * FROM nilai_komponen ORDER BY komponen_id,siswa_id')->fetchAll()===$beforeScores,'Replacement never changes scores');
$p=ni_narrative_preview($db,ni_context($db,1,1,1),'Revisi berikutnya','Saran revisi',true);
$db->exec("UPDATE capaian_penilaian SET deskripsi='Edit setelah pratinjau' WHERE komponen_id=1 AND siswa_id=10");
$before=$db->query('SELECT * FROM capaian_penilaian ORDER BY komponen_id,siswa_id')->fetchAll();
reject(fn()=>ni_narrative_commit($db,1,1,1,'Revisi berikutnya','Saran revisi',$p,[],true));
ok($db->query('SELECT * FROM capaian_penilaian ORDER BY komponen_id,siswa_id')->fetchAll()===$before,'Concurrent edit causes full rollback');
$p=ni_narrative_preview($db,ni_context($db,1,1,2),'UAS revisi','UAS saran revisi',true);
ni_narrative_commit($db,1,1,2,'UAS revisi','UAS saran revisi',$p,[],true);
ok($db->query('SELECT deskripsi FROM capaian_penilaian WHERE komponen_id=1 AND siswa_id=10')->fetchColumn()==='Edit setelah pratinjau','Replacing UAS leaves UTS unchanged');
echo 'PASS';
`;
 try{
  const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..'),env:{...process.env,IMPORT_FIXTURES:dir},timeout:30000});
  assert.equal(result.status,0,result.stderr+result.stdout);assert.equal(result.stdout,'PASS');
 }finally{for(const file of fs.readdirSync(dir))fs.unlinkSync(path.join(dir,file));fs.rmdirSync(dir);}
});
