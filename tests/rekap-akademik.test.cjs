// Run: node --test tests/rekap-akademik.test.cjs
// Fixtures use connection-local temporary tables and never change school records.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
test('UTS/UAS ownership, missing grades, narratives, monitoring and exports',()=>{
 const php=String.raw`<?php
require 'config/database.php';require 'includes/rekap_akademik.php';
$db=Database::getInstance();
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function reject($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid write accepted');}
$tables=[
'siswa'=>'id INT PRIMARY KEY,kelas_id INT,nisn VARCHAR(30),nama_lengkap VARCHAR(100)',
'pengajaran'=>'id INT PRIMARY KEY,guru_id INT,kelas_id INT,mapel_id INT,kkm DECIMAL(5,2),semester VARCHAR(20),tahun_ajaran VARCHAR(20)',
'guru'=>'id INT PRIMARY KEY,nama_lengkap VARCHAR(100)',
'mapel'=>'id INT PRIMARY KEY,nama_mapel VARCHAR(100)',
'komponen_penilaian'=>'id INT PRIMARY KEY,pengajaran_id INT,nama_komponen VARCHAR(100)',
'nilai_komponen'=>'komponen_id INT,siswa_id INT,nilai DECIMAL(5,2),updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(komponen_id,siswa_id)',
'capaian_penilaian'=>'komponen_id INT,siswa_id INT,deskripsi TEXT,saran TEXT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(komponen_id,siswa_id)',
'riwayat_nilai'=>'pengajaran_id INT,siswa_id INT,komponen_id INT,guru_id INT,nilai_lama DECIMAL(5,2),nilai_baru DECIMAL(5,2)'];
foreach($tables as $t=>$schema)$db->exec("CREATE TEMPORARY TABLE $t ($schema) ENGINE=InnoDB");
$db->exec("INSERT INTO siswa VALUES(1,1,'00123','Andi'),(2,1,'00456','Budi'),(3,2,'00789','Citra')");
$db->exec("INSERT INTO guru VALUES(1,'Guru A'),(2,'Guru B')");
$db->exec("INSERT INTO mapel VALUES(1,'Matematika'),(2,'Bahasa Indonesia')");
$db->exec("INSERT INTO pengajaran VALUES(1,1,1,1,75,'Ganjil','2026/2027'),(2,2,1,2,80,'Ganjil','2026/2027'),(3,1,2,1,75,'Ganjil','2026/2027'),(4,1,1,1,75,'Genap','2026/2027'),(5,1,1,1,75,'Ganjil','2025/2026')");
$db->exec("INSERT INTO komponen_penilaian VALUES(1,1,'UTS'),(2,1,'UAS'),(3,2,'UTS'),(4,3,'UTS'),(5,4,'UTS'),(6,5,'UTS')");
ra_save($db,1,1,1,'UTS','0','Menguasai penjumlahan','Latihan pecahan');
ra_save($db,1,1,1,'UAS','91','Menguasai persamaan','Latihan lanjutan');
ra_save($db,2,2,1,'UTS','85','=SUM(1,2) <teks>','Baca dan rangkum');
ra_save($db,1,1,2,'UTS','','Draf capaian','');
ra_save($db,1,4,1,'UTS','99','Semester lain','Saran lain');
ra_save($db,1,5,1,'UTS','98','Tahun lain','Saran lain');
$rows=ra_rows($db,1,'2026/2027','Ganjil','UTS');check(count($rows)===4,'Class and period isolation');
$indexed=[];foreach($rows as $r)$indexed[$r['siswa_id'].'-'.$r['pengajaran_id']]=$r;
check((float)$indexed['1-1']['nilai']===0.0 && ra_status($indexed['1-1'])==='Perlu Bimbingan','Zero score');
check($indexed['2-1']['nilai']===null && ra_status($indexed['2-1'])==='Belum diisi','Missing score');
check(ra_lengkap($indexed['1-1'])&&!ra_lengkap($indexed['2-1']),'Completeness');
check(count(ra_rows($db,1,'2026/2027','Ganjil','UTS',1))===2,'Per student');
$uas=ra_rows($db,1,'2026/2027','Ganjil','UAS',1);check(count($uas)===2,'Missing component');
foreach($uas as $r)if((int)$r['pengajaran_id']===1)check((float)$r['nilai']===91.0&&$r['deskripsi']==='Menguasai persamaan','UTS/UAS separation');
reject(fn()=>ra_save($db,2,1,1,'UTS','80','x','y'));
reject(fn()=>ra_save($db,1,1,3,'UTS','80','x','y'));
reject(fn()=>ra_save($db,1,1,1,'UTS','101','x','y'));
reject(fn()=>ra_save($db,1,1,1,'UTS','abc','x','y'));
reject(fn()=>ra_save($db,1,1,1,'UTS','','x','y'));
reject(fn()=>ra_save($db,1,1,1,'UTS','80',str_repeat('x',2001),'y'));
reject(fn()=>ra_save($db,2,2,1,'UAS','80','x','y'));
check(!$db->inTransaction(),'Rollback');
$before=$db->query('SELECT COUNT(*) FROM riwayat_nilai')->fetchColumn();
ra_save($db,1,1,1,'UTS','0','Updated','Updated');
check($db->query('SELECT COUNT(*) FROM riwayat_nilai')->fetchColumn()===$before,'Narrative-only audit');
ra_save($db,1,1,1,'UTS','75','Updated','Updated');
check((int)$db->query('SELECT COUNT(*) FROM riwayat_nilai')->fetchColumn()===(int)$before+1,'Grade audit');
$xml=ra_excel('UTS',['No','NISN','Nama','Mapel','Guru','UTS','Deskripsi','Saran','Status','Kelengkapan'],ra_export_rows($rows));
$doc=new DOMDocument();check($doc->loadXML($xml),'Excel XML');
check(str_contains($xml,'ss:Type="String">00123'),'NISN');
check(str_contains($xml,'ss:Type="String">=SUM(1,2) &lt;teks&gt;'),'Formula safety');
$rows[0]['deskripsi']=str_repeat('Narasi panjang. ',300).'AKHIR_DESKRIPSI';
$pdf=ra_pdf('UTS',$rows);check(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'AKHIR_DESKRIPSI'),'PDF complete');
preg_match('/startxref\n(\d+)/',$pdf,$m);check(substr($pdf,(int)$m[1],4)==='xref','PDF xref');
check(str_contains(ra_pdf('Kosong',[]),'Tidak ada data.'),'Empty export');

require 'includes/rekap_akademik_matrix.php';
$db->exec('ALTER TABLE komponen_penilaian ADD bobot DECIMAL(5,2) DEFAULT 0, ADD urutan INT DEFAULT 0');
$db->exec("UPDATE komponen_penilaian SET bobot=20 WHERE id IN(1,2)");
$db->exec("UPDATE komponen_penilaian SET bobot=80 WHERE id=3");
$db->exec("INSERT INTO komponen_penilaian VALUES(7,1,'Ulangan Harian',10,1),(8,1,'Tugas Harian',10,2),(9,1,'Kehadiran',40,3)");
foreach([
'ujian'=>'id INT,pengajaran_id INT,jenis_ujian VARCHAR(20),waktu_selesai DATETIME',
'nilai_ujian'=>'ujian_id INT,siswa_id INT,nilai_total DECIMAL(5,2)',
'tugas'=>'id INT,pengajaran_id INT,deadline DATETIME',
'pengumpulan_tugas'=>'tugas_id INT,siswa_id INT,nilai DECIMAL(5,2)',
'nilai_tugas_manual'=>'pengajaran_id INT,siswa_id INT,nilai DECIMAL(5,2)',
'sesi_absensi'=>'id INT,pengajaran_id INT,status VARCHAR(30)',
'detail_absensi'=>'sesi_absensi_id INT,siswa_id INT,status VARCHAR(30)',
'kelas'=>'id INT,nama_kelas VARCHAR(50)'
] as $table=>$schema)$db->exec("CREATE TEMPORARY TABLE $table ($schema)");
$db->exec("INSERT INTO ujian VALUES(1,1,'Kuis','2020-01-01'),(2,1,'Kuis','2020-01-01'),(3,1,'Kuis','2099-01-01')");
$db->exec('INSERT INTO nilai_ujian VALUES(1,1,100),(3,1,100)');
$db->exec("INSERT INTO tugas VALUES(1,1,'2020-01-01'),(2,1,'2020-01-01'),(3,1,'2099-01-01')");
$db->exec('INSERT INTO pengumpulan_tugas VALUES(1,1,80),(3,1,100)');
$db->exec('INSERT INTO nilai_tugas_manual VALUES(1,1,100)');
$db->exec("INSERT INTO sesi_absensi VALUES(1,1,'Ditutup'),(2,1,'Ditutup'),(3,1,'Dibuka')");
$db->exec("INSERT INTO detail_absensi VALUES(1,1,'Hadir'),(2,1,'Alpa'),(3,1,'Hadir')");
$db->exec("INSERT INTO kelas VALUES(1,'Kelas A'),(2,'Kelas B')");
$matrix=ram_data($db,1,'2026/2027','Ganjil');
check(count($matrix['students'])===2&&count($matrix['subjects'])===2,'Horizontal class matrix dimensions');
$values=$matrix['students'][0]['subjects'][1];
check($values['Ulangan Harian']===50.0&&$values['Tugas Harian']===60.0&&$values['Kehadiran']===50.0,'Automatic components include missing activities and exclude unfinished ones');
check($values['UTS']===75.0&&$values['UAS']===91.0&&abs($values['Nilai Akhir']-64.2)<.001,'Weighted final includes both exams');
check(str_contains($values['Deskripsi Capaian Pembelajaran'],'UTS: Updated')&&str_contains($values['Deskripsi Capaian Pembelajaran'],'UAS: Menguasai persamaan'),'Both narratives retained');
check($matrix['students'][1]['subjects'][1]['Nilai Akhir']==='Belum lengkap','No official final for missing manual scores');
check($matrix['students'][0]['subjects'][2]['Nilai Akhir']==='Bobot belum 100%','Incomplete weights');
check(count(ram_rows($matrix))===2&&count(ram_rows($matrix)[0])===19,'One student per row, eight subcolumns per subject');
check(count(ram_data($db,2,'2026/2027','Ganjil')['students'])===1,'Class separation');
$xml=ram_excel('Rapor Kelas A',$matrix);check($doc->loadXML($xml),'Horizontal workbook XML');
$xp=new DOMXPath($doc);$xp->registerNamespace('ss','urn:schemas-microsoft-com:office:spreadsheet');
check($xp->query('//ss:Worksheet')->length===2,'One worksheet per subject');
foreach($xp->query('//ss:Worksheet') as $sheet){
 check($xp->query('ss:Table/ss:Row',$sheet)->length===5,'Each sheet has three header rows and two students');
 check($xp->query('ss:Table/ss:Row[4]/ss:Cell',$sheet)->length===11,'Only one subject per student row');
}
check($xp->query('//ss:Worksheet[@ss:Name="Matematika"]/ss:Table/ss:Row[4]/ss:Cell[7]/ss:Data')->item(0)->textContent==='75','Math UTS in correct worksheet');
check($xp->query('//ss:Worksheet[@ss:Name="Bahasa Indonesia"]/ss:Table/ss:Row[4]/ss:Cell[7]/ss:Data')->item(0)->textContent==='85','Other subject UTS isolated');
$used=[];$names=[];
foreach(["'Mapel:/\\*?[]'",str_repeat('A',40),str_repeat('a',40),'','Mapel','Mapel'] as $name){
 $safe=ram_sheet_name($name,$used);check(mb_strlen($safe)<=31,'Sheet name length');
 foreach(['\\','/',':','*','?','[',']'] as $bad)check(!str_contains($safe,$bad),'Forbidden sheet character');
 check(!str_starts_with($safe,"'")&&!str_ends_with($safe,"'"),'Sheet apostrophes');$names[]=mb_strtolower($safe);
}
check(count(array_unique($names))===count($names),'Case insensitive unique sheet names');
$empty=ram_excel('Kosong',['subjects'=>[],'students'=>[]]);$emptyDoc=new DOMDocument();check($emptyDoc->loadXML($empty),'Empty workbook valid');
check(str_contains($xml,'ss:Type="String">00123'),'Matrix NISN text');
$matrix['students'][0]['subjects'][1]['Saran Capaian Pembelajaran']=str_repeat('Narasi panjang. ',400).'AKHIR_SARAN';
$pdf=ram_pdf('Rapor Kelas A',$matrix);
check(str_contains($pdf,'AKHIR_SARAN')&&str_contains($pdf,'Matematika')&&str_contains($pdf,'Bahasa Indonesia'),'Wide PDF contains all subjects and long narratives');
preg_match('/MediaBox \[0 0 (\d+) (\d+)\]/',$pdf,$dim);check((int)$dim[1]>(int)$dim[2],'Landscape PDF');
preg_match('/startxref\n(\d+)/',$pdf,$m);check(substr($pdf,(int)$m[1],4)==='xref','Wide PDF offsets');
check(str_contains(ram_pdf('Kosong',['subjects'=>[],'students'=>[]]),'Tidak ada siswa.'),'Empty matrix PDF');
require 'config/auth.php';$_SESSION['user_id']=1;$_SESSION['role_id']=1;
$_SERVER['PHP_SELF']='/admin/rekap_nilai.php';$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF'];
$_GET=['kelas_id'=>1,'tahun_ajaran'=>'2026/2027','semester'=>'Ganjil'];
ob_start();require 'includes/rekap_akademik_admin.php';$html=ob_get_clean();
libxml_use_internal_errors(true);$doc->loadHTML($html);$xp=new DOMXPath($doc);
check($xp->query('//table[@id="raporMatrix"]/tbody/tr')->length===2,'Rendered one student per row');
check($xp->query('//table[@id="raporMatrix"]/thead/tr[1]/th[@colspan="8"]')->length===2,'Rendered subject groups');
check($xp->query('//table[@id="raporMatrix"]/tbody/tr[1]/td')->length===19,'Rendered all subject subcolumns');
check(!str_contains($html,'id="jenisGabungan"'),'Both exams shown without period toggle');
session_destroy();
echo "PASS";
`;
 const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',['-d','session.save_path='+require('node:os').tmpdir()],{input:php,encoding:'utf8',cwd:require('node:path').resolve(__dirname,'..')});
 assert.equal(result.status,0,result.stderr+result.stdout);
 assert.equal(result.stdout,'PASS');
});
