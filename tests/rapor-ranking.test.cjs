const {test}=require('node:test');const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');const path=require('node:path');
function calculate(reports,students=[]){
    const payload=Buffer.from(JSON.stringify({reports,students})).toString('base64');
    const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{input:`<?php require 'includes/rapor_ranking.php';$data=json_decode(base64_decode('${payload}'),true);echo json_encode(rapor_ranking_calculate($data['reports'],$data['students']));`,encoding:'utf8',cwd:path.resolve(__dirname,'..')});
    assert.equal(result.status,0,result.stderr+result.stdout);return JSON.parse(result.stdout);
}
const report=(id,scores,names=['Matematika','Bahasa Indonesia'])=>({id,siswa_id:id,nama_siswa:`Siswa ${id}`,mapel:scores.map((score,i)=>({nama_mapel:names[i],nilai_akhir:score}))});
test('Ranking orders averages descending and exposes ranks 1 through 10',()=>{
    const result=calculate(Array.from({length:12},(_,i)=>report(i+1,[60+i,70+i])));
    assert.deepEqual(result.entries.slice(0,10).map(row=>row.peringkat),[1,2,3,4,5,6,7,8,9,10]);
    assert.equal(result.entries[0].siswa_id,12);assert.equal(result.entries[0].total,152);assert.equal(result.entries[0].rata_rata,76);assert.equal(result.sementara,false);
});
test('Equal averages share a rank and do not get broken by the student name',()=>{
    const result=calculate([report(1,[95,95]),report(2,[90,90]),report(3,[90,90]),report(4,[80,80])]);
    assert.deepEqual(result.entries.map(row=>row.peringkat),[1,2,2,4]);
});
test('Zero is valid, while incomplete and unsaved reports never become zero scores',()=>{
    const result=calculate([report(1,[0,0]),report(2,[10,10]),report(3,[100,null])],[{id:4,nama_lengkap:'Belum tersimpan'}]);
    assert.equal(result.layak,2);assert.equal(result.sementara,true);assert.equal(result.entries.find(row=>row.id===1).peringkat,2);
    assert.equal(result.entries.find(row=>row.id===3).rata_rata,null);assert.equal(result.entries.find(row=>row.siswa_id===4).peringkat,null);
});
test('Different subject lists and duplicate subjects block unfair rankings',()=>{
    for(const reports of [[report(1,[90,90]),report(2,[99,99],['Matematika','Fisika'])],[report(1,[90,90]),report(2,[99,99],['Matematika','Matematika'])]]){
        const result=calculate(reports);assert.equal(result.mapel_seragam,false);assert.equal(result.layak,0);assert.ok(result.entries.every(row=>row.peringkat===null));
    }
});
test('Rounding matches the displayed two decimals and reordered subjects remain comparable',()=>{
    const result=calculate([report(1,[90,90.01]),report(2,[90.01,90.01],[' BAHASA Indonesia ','matematika'])]);
    assert.equal(result.mapel_seragam,true);assert.deepEqual(result.entries.map(row=>row.rata_rata),[90.01,90.01]);assert.deepEqual(result.entries.map(row=>row.peringkat),[1,1]);
});
test('A tie at rank ten includes every student with that rank',()=>{
    const reports=Array.from({length:9},(_,i)=>report(i+1,[100-i,100-i]));reports.push(report(10,[80,80]),report(11,[80,80]),report(12,[79,79]));
    const result=calculate(reports);assert.equal(result.entries.filter(row=>row.peringkat<=10).length,11);assert.equal(result.entries.at(-1).peringkat,12);
});
test('Database ranking and single/class printing agree, isolate periods and do not change stored reports',()=>{
const php=String.raw`<?php
require 'config/database.php';require 'includes/rapor_print_data.php';$db=Database::getInstance();
function ck($v,$m){if(!$v)throw new RuntimeException($m);}
foreach(explode(';',preg_replace('/,\s*\)/',"\n)",preg_replace('/^\s*CONSTRAINT fk_[^\n]+\n/m','',preg_replace('/^--.*$/m','',str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',file_get_contents('database/2026_10_28_rapor.sql')))))) as $q)if(trim($q))$db->exec($q);
$db->exec('CREATE TEMPORARY TABLE kelas(id INT PRIMARY KEY,nama_kelas VARCHAR(100))');$db->exec("INSERT INTO kelas VALUES(1,'X A'),(2,'X B')");
$db->exec('CREATE TEMPORARY TABLE siswa(id INT PRIMARY KEY,kelas_id INT,nama_lengkap VARCHAR(100))');$db->exec("INSERT INTO siswa VALUES(1,1,'Satu'),(2,1,'Dua'),(3,1,'Tiga'),(4,2,'Empat')");
$db->exec('CREATE TEMPORARY TABLE pengajaran(id INT,tahun_ajaran VARCHAR(10))');$db->exec("INSERT INTO pengajaran VALUES(1,'2026/2027')");
$db->exec("INSERT INTO rapor(id,siswa_id,kelas_id,tahun_ajaran,semester,jenis,nama_siswa,nama_kelas) VALUES(1,1,1,'2026/2027','Ganjil','UTS','Satu','X A'),(2,2,1,'2026/2027','Ganjil','UTS','Dua','X A'),(3,4,2,'2026/2027','Ganjil','UTS','Empat','X B'),(4,1,1,'2026/2027','Ganjil','UAS','Satu','X A')");
foreach([1=>90,2=>80,3=>100,4=>50] as $id=>$grade)foreach(['Matematika','Bahasa Indonesia'] as $i=>$subject)$db->prepare('INSERT INTO rapor_mapel(rapor_id,nama_mapel,nama_guru,kelompok,urutan,nilai_akhir,deskripsi,saran,sumber_diambil_pada) VALUES(?,?,?,?,?,?,?,?,NOW())')->execute([$id,$subject,'Guru','Umum',$i+1,$grade,'Capaian','Saran']);
$before=rapor_version(rapor_load($db,1));$ranking=rapor_ranking_load($db,1,'2026/2027','Ganjil','UTS');ck($ranking['total_siswa']===3&&$ranking['layak']===2&&$ranking['sementara'],'Partial cohort flagged');ck(rapor_ranking_for_report($ranking,1)['peringkat']===1&&rapor_ranking_for_report($ranking,2)['peringkat']===2,'Correct class order');
ck(rapor_ranking_for_report(rapor_ranking_load($db,2,'2026/2027','Ganjil','UTS'),3)['peringkat']===1,'Separate class');ck(rapor_ranking_load($db,1,'2026/2027','Ganjil','UAS')['entries'][0]['rata_rata']===50.0,'Separate exam');
$batch=rapor_print_batch($db,1,'2026/2027','Ganjil','UTS');$single=rapor_print_single($db,2);$match=array_values(array_filter($batch['reports'],static fn($r)=>(int)$r['id']===2))[0];ck($single['peringkat']===$match['peringkat'],'Single print compares whole class, not just one student');ck($single['peringkat']['peringkat']===2,'Second student is not wrongly first');
ck(rapor_print_single($db,99999)===null&&!$db->inTransaction(),'Missing record leaves no transaction');ck(rapor_version(rapor_load($db,1))===$before,'Ranking is read only');
require 'config/auth.php';$db->exec('CREATE TEMPORARY TABLE roles(id INT,nama_role VARCHAR(50))');$db->exec("INSERT INTO roles VALUES(1,'Admin'),(2,'Guru')");$db->exec('CREATE TEMPORARY TABLE users(id INT,username VARCHAR(50),role_id INT,is_active INT,password VARCHAR(100))');$db->exec("INSERT INTO users VALUES(777,'uji',1,1,'hash')");
$_SESSION['user_id']=777;$_SESSION['role_id']=1;$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/admin/rapor_peringkat.php';$_GET=['kelas_id'=>'1','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS'];ob_start();require 'admin/rapor_peringkat.php';$html=ob_get_clean();ck(str_contains($html,'Peringkat sementara.')&&str_contains($html,'rapor-ranking-print')&&str_contains($html,'Satu'),'Admin report page renders');
$db->exec('UPDATE users SET role_id=2 WHERE id=777');$_SESSION['role_id']=2;$_SERVER['SCRIPT_NAME']='/guru/rapor_peringkat.php';$_GET['tampilan']='semua';ob_start();require 'guru/rapor_peringkat.php';$html=ob_get_clean();ck(str_contains($html,'Seluruh siswa')&&str_contains($html,'Tiga'),'Teacher full list includes unranked students');session_destroy();echo 'PASS';
?>`;
const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',['-d','session.save_path='+require('node:os').tmpdir()],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});assert.equal(result.status,0,result.stderr+result.stdout);assert.equal(result.stdout,'PASS');
});
