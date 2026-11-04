const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const path = require('node:path');

test('Class print isolates periods, orders attendance numbers and reports unsaved students', () => {
    const php = String.raw`<?php
require 'config/database.php'; require 'includes/rapor_print_data.php';
$db=Database::getInstance();
$db->exec('CREATE TEMPORARY TABLE rapor(id INT PRIMARY KEY,siswa_id INT,kelas_id INT,tahun_ajaran VARCHAR(10),semester VARCHAR(10),jenis VARCHAR(3),nomor_absen INT NULL,nama_siswa VARCHAR(100)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE rapor_mapel(id INT,rapor_id INT,urutan INT,nilai_akhir DECIMAL(5,2),deskripsi TEXT,saran TEXT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE rapor_ekstrakurikuler(id INT,rapor_id INT,urutan INT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE siswa(id INT,kelas_id INT,nama_lengkap VARCHAR(100)) ENGINE=InnoDB');
$db->exec("INSERT INTO rapor VALUES(1,1,10,'2026/2027','Ganjil','UTS',2,'Andi'),(2,2,10,'2026/2027','Ganjil','UTS',1,'Budi'),(3,3,10,'2026/2027','Ganjil','UTS',NULL,'Citra'),(4,4,11,'2026/2027','Ganjil','UTS',1,'Other class'),(5,5,10,'2025/2026','Ganjil','UTS',1,'Other year'),(6,6,10,'2026/2027','Genap','UTS',1,'Other semester'),(7,7,10,'2026/2027','Ganjil','UAS',1,'Other exam')");
$db->exec("INSERT INTO siswa VALUES(1,10,'Andi'),(2,10,'Budi'),(3,10,'Citra'),(8,10,'Dedi'),(9,11,'Other class')");
$db->exec("INSERT INTO rapor_mapel VALUES(1,1,1,0,'Description','Advice'),(2,2,1,NULL,'Description','Advice')");
$result=rapor_print_batch($db,10,'2026/2027','Ganjil','UTS');
$flags=array_map('rapor_print_incomplete',$result['reports']);
$rejected=false;try{rapor_print_batch($db,10,'2026/2027','Invalid','UTS');}catch(InvalidArgumentException $e){$rejected=true;}
$empty=rapor_print_batch($db,99,'2026/2027','Ganjil','UTS');
echo json_encode(['ids'=>array_column($result['reports'],'id'),'missing'=>$result['missing'],'flags'=>$flags,'rejected'=>$rejected,'empty'=>$empty['reports'],'transaction'=>$db->inTransaction()]);
?>`;
    const result = spawnSync(process.env.PHP_BINARY || 'C:\\xampp\\php\\php.exe', [], {input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});
    assert.equal(result.status,0,result.stderr+result.stdout);
    const output=JSON.parse(result.stdout);
    assert.deepEqual(output.ids,[2,1,3]);
    assert.deepEqual(output.missing,['Dedi']);
    assert.deepEqual(output.flags,[true,false,true]);
    assert.equal(output.rejected,true);
    assert.deepEqual(output.empty,[]);
    assert.equal(output.transaction,false);
});
