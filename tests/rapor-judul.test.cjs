const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Title settings isolate class/period, support defaults and preserve safe print text',()=>{
const php=String.raw`<?php
require 'config/database.php';require 'includes/rapor_judul.php';require 'includes/rapor_print_view.php';$db=Database::getInstance();
$db->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',file_get_contents('database/2026_11_03_rapor_riwayat.sql')));
$db->exec('CREATE TEMPORARY TABLE kelas(id INT PRIMARY KEY)');$db->exec('INSERT INTO kelas VALUES(1),(2)');
$sql=file_get_contents('database/2026_10_28_rapor_judul.sql');$sql=preg_replace('/^--.*$/m','',$sql);$sql=preg_replace('/^\s*CONSTRAINT fk_[^\n]+\n/m','',$sql);$sql=preg_replace('/,\s*\)/',"\n)",$sql);$db->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql));
function ck($v){if(!$v)throw new RuntimeException('Title setting mismatch');}
$data=['kelas_id'=>1,'tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','judul'=>'LAPORAN KHUSUS','penilaian'=>'PENILAIAN SEKOLAH','konsentrasi'=>'Desain Teknik Furniture'];
rapor_judul_save($db,$data,1);$s=rapor_judul_load($db,1,'2026/2027','Ganjil','UTS');ck($s['judul']==='LAPORAN KHUSUS');ck(!rapor_judul_load($db,2,'2026/2027','Ganjil','UTS'));ck(!rapor_judul_load($db,1,'2026/2027','Ganjil','UAS'));ck(!rapor_judul_load($db,1,'2026/2027','Genap','UTS'));ck(!rapor_judul_load($db,1,'2025/2026','Ganjil','UTS'));
$r=['nama_siswa'=>'Siswa','nis'=>'1','nisn'=>'1','nama_kelas'=>'X A','jurusan'=>'TKJ','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','sakit'=>0,'izin'=>0,'alfa'=>0,'catatan_wali_kelas'=>'','nama_kepala_sekolah'=>'','nama_wali_kelas'=>'','tempat_terbit'=>'','tanggal_terbit'=>null,'mapel'=>[],'ekstra'=>[],'judul_cetak'=>$s];
ob_start();rapor_print_render($r);$html=ob_get_clean();ck(substr_count($html,'LAPORAN KHUSUS')===2);ck(substr_count($html,'Desain Teknik Furniture')===2);
$data['judul']='';$data['penilaian']='';$data['konsentrasi']='';rapor_judul_save($db,$data,1);$r['judul_cetak']=rapor_judul_load($db,1,'2026/2027','Ganjil','UTS');ob_start();rapor_print_render($r);$html=ob_get_clean();ck(substr_count($html,'LAPORAN PENCAPAIAN KOMPETENSI PESERTA DIDIK')===2);ck(substr_count($html,'KONSENTRASI KEAHLIAN: TKJ')===2);
$data['judul']='<script>alert(1)</script>';rapor_judul_save($db,$data,1);$r['judul_cetak']=rapor_judul_load($db,1,'2026/2027','Ganjil','UTS');ob_start();rapor_print_render($r);$html=ob_get_clean();ck(!str_contains($html,'<script>'));ck(str_contains($html,'&lt;script&gt;'));
$data['judul']=str_repeat('a',161);$rejected=false;try{rapor_judul_save($db,$data,1);}catch(RuntimeException $e){$rejected=true;}ck($rejected);ck((int)$db->query("SELECT COUNT(*) FROM rapor_riwayat WHERE tindakan='titles'")->fetchColumn()===3);echo 'PASS';
?>`;
const r=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});assert.equal(r.status,0,r.stderr+r.stdout);assert.equal(r.stdout,'PASS');
});
