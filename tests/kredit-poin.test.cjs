const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const path = require('node:path');
const code = String.raw`<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'includes/kredit_poin.php';
function expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function rejected(callable $call, string $message): void {
    try { $call(); } catch (InvalidArgumentException | DomainException $e) { return; }
    throw new RuntimeException($message);
}
$db = new PDO('mysql:host='.DB_HOST.';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$name = 'lms_kps_test_'.bin2hex(random_bytes(5));
$db->exec("CREATE DATABASE ".chr(96).$name.chr(96)." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $db->exec('USE '.chr(96).$name.chr(96));
    $db->exec('CREATE TABLE users(id int unsigned PRIMARY KEY,role_id int NOT NULL,is_active int NOT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE kelas(id int unsigned PRIMARY KEY) ENGINE=InnoDB');
    $db->exec('CREATE TABLE siswa(id int unsigned PRIMARY KEY,kelas_id int unsigned) ENGINE=InnoDB');
    $db->exec('CREATE TABLE guru(id int unsigned PRIMARY KEY,user_id int unsigned) ENGINE=InnoDB');
    $db->exec('CREATE TABLE log_aktivitas(user_id int,aktivitas text,ip_address varchar(255),user_agent text) ENGINE=InnoDB');
    $db->exec('INSERT INTO users VALUES (1,1,1),(2,2,1),(3,3,1),(4,2,0),(5,4,1)');
    $db->exec('INSERT INTO kelas VALUES(1),(2)');
    $db->exec('INSERT INTO siswa VALUES (1,1),(2,2)');
    $db->exec('INSERT INTO guru VALUES(1,2)');
    $db->exec(file_get_contents('database/2026_11_03_kredit_akses_guru.sql'));
    $db->exec("INSERT INTO kredit_akses_guru(guru_id,modul,diberikan_oleh) VALUES(1,'poin',1)");
    expect(!kps_ready($db), 'Must detect missing migration');
    $migration = file_get_contents('database/2026_11_03_kredit_poin_siswa.sql');
    $db->exec($migration);
    $db->exec($migration);
    $master=file_get_contents('database/2026_11_03_master_pelanggaran.sql');$db->exec($master);$db->exec($master);
    expect((int)$db->query('SELECT COUNT(*) FROM kredit_master_pelanggaran')->fetchColumn()===53,'Exactly 53 seeded violations');
    $expected=['A'=>[1,2],'B'=>[2,10,10],'C'=>[10,15,5,10,10],'D'=>[5,5,5,75,15,10,10,10,50,50,75,50,50,100,75,50,75,100,20,75],'E'=>[50,10,25,10,5,15,20,10],'F'=>[25],'G'=>[50,50,50],'H'=>[100,100,100],'I'=>[100,100],'J'=>[100,100,100],'K'=>[100,100,100]];
    foreach($expected as $category=>$points){$q=$db->prepare('SELECT poin FROM kredit_master_pelanggaran WHERE kategori=? ORDER BY urutan');$q->execute([$category]);expect(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))===$points,'Seed points for '.$category);}
    expect(kps_ready($db), 'Migration must be repeatable');
    $db->exec('ALTER TABLE kredit_poin_siswa DROP CONSTRAINT chk_kps_hanya_pengurangan');
    $upgrade = file_get_contents('database/2026_11_03_kredit_poin_pelanggaran.sql');
    $db->exec($upgrade);
    $db->exec($upgrade);
    expect(kps_summary($db,1) === ['keluar'=>0,'jumlah'=>0,'awal'=>100,'saldo'=>100,'persentase'=>100], 'Initial balance must be 100');
    $input = ['kelas_id'=>'1','siswa_id'=>'1','pelanggaran_id'=>'1','jenis'=>'kurang','poin'=>'25','tanggal'=>date('Y-m-d'),'alasan'=>'Tampered title','catatan'=>'Catatan kejadian','request_key'=>bin2hex(random_bytes(32))];
    expect(kps_record($db,2,$input), 'Teacher can record a violation');
    $saved=$db->query('SELECT * FROM kredit_poin_siswa ORDER BY id LIMIT 1')->fetch();
    expect($saved['alasan']==='Terlambat masuk 07.15 | 13.15'&&(int)$saved['poin_standar']===1&&(int)$saved['perubahan']===-25,'Catalog title preserved and edited points accepted');
    expect(!kps_record($db,2,$input), 'Repeated form must not subtract points twice');
    $minus = array_replace($input,['jenis'=>'kurang','poin'=>'40','alasan'=>'Pelanggaran','request_key'=>bin2hex(random_bytes(32))]);
    expect(kps_record($db,1,$minus), 'Admin can subtract points');
    expect(kps_summary($db,1) === ['keluar'=>65,'jumlah'=>2,'awal'=>100,'saldo'=>35,'persentase'=>35], 'Violations must only reduce balance');
    expect(kps_summary($db,2)['saldo'] === 100, 'Other student unaffected');
    foreach ([3,4,5,999] as $actor) rejected(fn()=>kps_record($db,$actor,$input), 'Student/inactive/other roles must be denied');
    foreach ([
        ['kelas_id'=>''],['kelas_id'=>'999'],['kelas_id'=>[]],['kelas_id'=>'2'],['siswa_id'=>'2'],['siswa_id'=>'999'],['poin'=>'0'],['poin'=>'-1'],['poin'=>'1.5'],['poin'=>'100001'],
        ['jenis'=>'hapus'],['jenis'=>'tambah'],['jenis'=>[]],['catatan'=>str_repeat('a',501)],['pelanggaran_id'=>''],['pelanggaran_id'=>'999'],['pelanggaran_id'=>[]],
        ['tanggal'=>'2026-02-30'],['tanggal'=>date('Y-m-d',strtotime('+1 day'))],
        ['request_key'=>'bad'],['poin'=>[]],['catatan'=>[]],['siswa_id'=>[]]
    ] as $invalid) rejected(fn()=>kps_record($db,2,array_replace($input,$invalid)), 'Invalid input must be denied: '.json_encode($invalid));
    expect(kps_summary($db,1)['jumlah'] === 2, 'Rejected entries must not alter history');
    $db->beginTransaction();
    expect(kps_record($db,2,array_replace($input,['request_key'=>bin2hex(random_bytes(32))])), 'Transaction accepts record');
    $db->rollBack();
    expect(kps_summary($db,1)['saldo'] === 35, 'Rollback must undo points');
    expect(kps_record($db,1,array_replace($input,['poin'=>'50','request_key'=>bin2hex(random_bytes(32))])), 'Further violation can be recorded');
    expect(kps_summary($db,1)['saldo'] === 0 && kps_summary($db,1)['keluar'] === 115, 'Balance floors at zero without losing violation totals');
    $blocked = false;
    try {
        $db->exec("INSERT INTO kredit_poin_siswa(siswa_id,pencatat_id,tanggal,perubahan,alasan,request_key) VALUES(1,1,CURRENT_DATE,5,'Forbidden positive','".bin2hex(random_bytes(32))."')");
    } catch (PDOException $e) { $blocked = true; }
    expect($blocked, 'Database must also reject additions');
    $weapon=(int)$db->query("SELECT id FROM kredit_master_pelanggaran WHERE kode='H.1'")->fetchColumn();
    expect(kps_record($db,1,array_replace($input,['siswa_id'=>'2','kelas_id'=>'2','pelanggaran_id'=>(string)$weapon,'poin'=>'','request_key'=>bin2hex(random_bytes(32))])),'Default points from master without JS');
    $snapshot=$db->query('SELECT * FROM kredit_poin_siswa WHERE siswa_id=2')->fetch();
    expect((int)$snapshot['perubahan']===-100&&$snapshot['penanganan']==='Dikembalikan kepada orang tua','Standard points and handling from catalog');
    rejected(fn()=>kps_catalog_save($db,2,['id'=>'1']),'Teacher cannot edit master');
    $id=kps_catalog_save($db,1,['id'=>'1','kategori'=>'A','nama'=>'Nama diperbarui','poin'=>'3','penanganan'=>'Pembinaan','aktif'=>'1']);
    $db->exec($master);expect($db->query('SELECT nama FROM kredit_master_pelanggaran WHERE id=1')->fetchColumn()==='Nama diperbarui','Repeated migration preserves admin edits');
    expect($db->query('SELECT alasan FROM kredit_poin_siswa ORDER BY id LIMIT 1')->fetchColumn()===$saved['alasan'],'Editing master does not change past records');
    kps_catalog_save($db,1,['id'=>'1','kategori'=>'A','nama'=>'Nama diperbarui','poin'=>'3']);
    rejected(fn()=>kps_record($db,2,array_replace($input,['request_key'=>bin2hex(random_bytes(32))])),'Inactive violation cannot be recorded');
    $new=kps_catalog_save($db,1,['kategori'=>'E','nama'=>'Pelanggaran baru','poin'=>'4','aktif'=>'1']);expect($new>53,'Admin can add violations');
    rejected(fn()=>kps_catalog_delete($db,2,$new),'Teacher cannot delete master');
    expect(kps_catalog_delete($db,1,$new)==='deleted','Unused violation deleted');
    expect((int)$db->query('SELECT COUNT(*) FROM kredit_master_pelanggaran WHERE id='.$new)->fetchColumn()===0,'Unused row removed');
    rejected(fn()=>kps_catalog_delete($db,1,$new),'Repeated deletion handled without mutation');
    $historyCount=(int)$db->query('SELECT COUNT(*) FROM kredit_poin_siswa')->fetchColumn();
    expect(kps_catalog_delete($db,1,1)==='deactivated','Used violation deactivated instead of deleted');
    expect((int)$db->query('SELECT aktif FROM kredit_master_pelanggaran WHERE id=1')->fetchColumn()===0,'Used violation unavailable for new records');
    expect((int)$db->query('SELECT COUNT(*) FROM kredit_poin_siswa')->fetchColumn()===$historyCount,'Deleting used master preserves history');
    $db->exec("ALTER TABLE users ADD username varchar(50) DEFAULT 'Test User'");
    $db->exec("ALTER TABLE guru ADD nama_lengkap varchar(100) DEFAULT 'Test Teacher'");
    $db->exec("ALTER TABLE siswa ADD nama_lengkap varchar(100) DEFAULT 'Test Student', ADD nis varchar(20) DEFAULT '1'");
    $db->exec("ALTER TABLE kelas ADD nama_kelas varchar(100) DEFAULT 'Test Class'");
    $baseline=(int)$db->query('SELECT COUNT(*) FROM kredit_poin_siswa')->fetchColumn();
    $ins=$db->prepare("INSERT INTO kredit_poin_siswa(siswa_id,pencatat_id,tanggal,perubahan,alasan,request_key) VALUES(?,1,CURRENT_DATE,-1,'Riwayat pengujian',?)");
    $db->beginTransaction();for($i=0;$i<1205;$i++)$ins->execute([$i%2===0?1:2,bin2hex(random_bytes(32))]);$db->commit();
    $first=kps_history_page($db,1,0,0,1);$second=kps_history_page($db,1,0,0,2);$last=kps_history_page($db,1,0,0,999999);
    expect($first['total']===$baseline+1205&&count($first['rows'])===10&&count($second['rows'])===10,'History loads only ten of 1205+ records');
    expect(!array_intersect(array_column($first['rows'],'id'),array_column($second['rows'],'id')),'History pages do not overlap');
    expect($last['page']===$last['pages']&&count($last['rows'])===($last['total']%10?:10),'Last history page remains reachable');
    $class=kps_history_page($db,2,1,0,2);expect(count($class['rows'])===10&&array_unique(array_column($class['rows'],'siswa_id'))===[1],'Class filter retained across pages');
    $student=kps_history_page($db,1,2,2,1);expect(array_unique(array_column($student['rows'],'siswa_id'))===[2],'Student filter scopes history');
    rejected(fn()=>kps_history_page($db,1,1,2,1),'Cannot mix student and class');
    rejected(fn()=>kps_history_page($db,3,0,0,1),'Students cannot access staff history');
    require 'includes/kredit_poin_export.php';
    $searched=kps_history_page($db,1,0,0,1,'Riwayat pengujian');expect($searched['total']===1205,'Search matches all history pages');
    expect(kps_history_page($db,1,0,0,1,'%_!')['total']===0,'Search treats wildcards literally');
    $sheets=kps_export_sheets($db,1,0,0,'Riwayat pengujian');expect(count($sheets[2]['rows'])===1206,'Excel exports all 1205 matching rows');
    expect(count($sheets[1]['rows'])===3,'Excel contains both matching students');
    foreach(array_slice($sheets[1]['rows'],1) as $r){expect($r[4]===100&&$r[6]===0,'Summary saldo floors at zero');}
    $one=kps_export_sheets($db,2,1,1,'Riwayat pengujian');expect(count($one[1]['rows'])===2&&count($one[2]['rows'])===604,'Excel class/student filters');
    $empty=kps_export_sheets($db,1,0,0,'no-match-123');expect(count($empty[1]['rows'])===1&&count($empty[2]['rows'])===1,'Empty export is valid');
    rejected(fn()=>kps_export_sheets($db,3,0,0),'Student cannot export staff rekap');
    expect(substr(ew_workbook($sheets),0,2)==='PK','Excel is real XLSX workbook');
    $db->exec("INSERT INTO siswa(id,kelas_id,nama_lengkap,nis) VALUES(3,1,'Tanpa Pelanggaran','00123')");
    $clean=kps_export_sheets($db,1,1,3);
    expect(count($clean[1]['rows'])===2&&$clean[1]['rows'][1][1]==='00123'&&$clean[1]['rows'][1][5]===0&&$clean[1]['rows'][1][6]===100,'No-violation students keep 100 points and leading-zero NIS');
    expect($sheets[1]['rows'][1][5]===kps_summary($db,1)['keluar'],'Filtered export summary uses full lifetime deductions');
    echo "PASS: migrations, deductions, saldo floor, permissions, duplicate protection, pagination, search and full XLSX export.\n";
} finally {
    $db->exec('DROP DATABASE '.chr(96).$name.chr(96));
}`;
const result = spawnSync(process.env.PHP_BINARY || 'C:\\xampp\\php\\php.exe', [], {
    cwd:path.resolve(__dirname,'..'),input:code,encoding:'utf8',timeout:30000
});
assert.equal(result.status,0,result.stderr+result.stdout);
process.stdout.write(result.stdout);
