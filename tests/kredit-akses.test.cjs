const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
const code=String.raw`<?php
date_default_timezone_set('Asia/Jakarta');
require 'config/database.php';require 'includes/kredit_akses.php';require 'includes/kredit_poin.php';require 'includes/kak_staff.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function denied($call){try{$call();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected denial');}
$db=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$name='lms_credit_access_test_'.bin2hex(random_bytes(5));$db->exec('CREATE DATABASE '.chr(96).$name.chr(96));
try{
 $db->exec('USE '.chr(96).$name.chr(96));
 $db->exec('CREATE TABLE users(id int unsigned PRIMARY KEY,role_id int,is_active int) ENGINE=InnoDB');
 $db->exec('CREATE TABLE guru(id int unsigned PRIMARY KEY,user_id int unsigned) ENGINE=InnoDB');
 $db->exec('CREATE TABLE kelas(id int unsigned PRIMARY KEY) ENGINE=InnoDB');
 $db->exec('CREATE TABLE siswa(id int unsigned PRIMARY KEY,kelas_id int unsigned) ENGINE=InnoDB');
 $db->exec('CREATE TABLE log_aktivitas(user_id int,aktivitas text,ip_address varchar(255),user_agent text) ENGINE=InnoDB');
 $db->exec('INSERT INTO users VALUES(1,1,1),(2,2,1),(3,2,1),(4,3,1),(5,1,0),(6,2,0)');
 $db->exec('INSERT INTO guru VALUES(10,2),(11,3),(12,6)');$db->exec('INSERT INTO kelas VALUES(1)');$db->exec('INSERT INTO siswa VALUES(1,1)');
 check(!kredit_access_can($db,2,'poin'),'Missing schema must fail closed');
 check(kredit_access_can($db,1,'poin'),'Admin remains authorized before migration');
 $migration=file_get_contents('database/2026_11_03_kredit_akses_guru.sql');$db->exec($migration);$db->exec($migration);
 $db->exec(file_get_contents('database/2026_11_03_kredit_poin_siswa.sql'));
 $db->exec(file_get_contents('database/2026_11_03_master_pelanggaran.sql'));
 check(!kredit_access_can($db,2,'poin')&&!kredit_access_can($db,2,'aktivitas'),'Default deny teachers');
 kredit_access_save($db,1,['poin'=>['10'],'aktivitas'=>['11']]);
 check(kredit_access_can($db,2,'poin')&&!kredit_access_can($db,2,'aktivitas'),'Points assignment independent');
 check(!kredit_access_can($db,3,'poin')&&kredit_access_can($db,3,'aktivitas'),'Activity assignment independent');
 $input=['kelas_id'=>'1','siswa_id'=>'1','pelanggaran_id'=>'1','jenis'=>'kurang','poin'=>'5','tanggal'=>date('Y-m-d'),'request_key'=>bin2hex(random_bytes(32))];
 check(kps_record($db,2,$input),'Assigned points teacher can record');
 denied(fn()=>kps_record($db,3,$input));denied(fn()=>kak_staff_actor($db,2));check((int)kak_staff_actor($db,3)['guru_id']===11,'Assigned activity teacher authorized');
 denied(fn()=>kredit_access_save($db,2,['poin'=>[10,11],'aktivitas'=>[10,11]]));
 denied(fn()=>kredit_access_save($db,5,[]));
 foreach([['poin'=>[999]],['poin'=>[12]],['poin'=>'10'],['aktivitas'=>[[]]]] as $invalid)denied(fn()=>kredit_access_save($db,1,$invalid));
 check(kredit_access_can($db,2,'poin')&&kredit_access_can($db,3,'aktivitas'),'Invalid save must preserve assignments');
 kredit_access_save($db,1,['poin'=>[10,11,10],'aktivitas'=>[10,11]]);
 check(kredit_access_can($db,2,'poin')&&kredit_access_can($db,2,'aktivitas'),'Both modules allowed');
 check((int)$db->query('SELECT COUNT(*) FROM kredit_akses_guru')->fetchColumn()===4,'Multiple teachers and duplicate normalization');
 kredit_access_save($db,1,['poin'=>[11],'aktivitas'=>[10,11]]);
 denied(fn()=>kps_record($db,2,$input));check(kredit_access_can($db,2,'aktivitas'),'Revoking points does not revoke activity');
 $db->exec('UPDATE users SET is_active=0 WHERE id=3');
 check(!kredit_access_can($db,3,'poin')&&!kredit_access_can($db,3,'aktivitas'),'Inactive teacher denied even when assigned');
 check(!kredit_access_can($db,4,'poin')&&!kredit_access_can($db,5,'aktivitas'),'Student and inactive admin denied');
 kredit_access_save($db,1,[]);
 check(!kredit_access_can($db,2,'poin')&&!kredit_access_can($db,2,'aktivitas'),'Empty selections revoke all teacher access immediately');
 check(kredit_access_can($db,1,'poin')&&kredit_access_can($db,1,'aktivitas'),'Admin retains full access');
 check((int)$db->query('SELECT COUNT(*) FROM log_aktivitas')->fetchColumn()===4,'Successful permission changes audited');
 echo "PASS: default deny, independent modules, multiple teachers, both modules, assigned writes, unauthorized saves, validation rollback, immediate revocation, inactive accounts, admin access, audit logs.\n";
}finally{$db->exec('DROP DATABASE '.chr(96).$name.chr(96));}
`;
const result=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{cwd:path.resolve(__dirname,'..'),input:code,encoding:'utf8',timeout:30000});
assert.equal(result.status,0,result.stderr+result.stdout);process.stdout.write(result.stdout);
