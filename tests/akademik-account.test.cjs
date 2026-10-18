// Run only against an isolated MariaDB started in the recorded temporary directory.
// This test refuses to create fixtures in the application's normal database server.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawn,spawnSync}=require('node:child_process');
const path=require('node:path');
const fs=require('node:fs');
const os=require('node:os');
const crypto=require('node:crypto');
const net=require('node:net');
const root=path.join(__dirname,'..');
const php=process.env.PHP_BINARY||'C:/xampp/php/php.exe';

test('Academic migration, admin management, same login, access isolation and session revocation',async()=>{
 const accountUsername='wakasek.'+crypto.randomBytes(6).toString('hex');
 const renamedUsername='wakasek.baru.'+crypto.randomBytes(6).toString('hex');
 const fixturePassword='Test-'+crypto.randomBytes(12).toString('hex');
 const fixture=String.raw`<?php
 $db=new PDO('mysql:host=127.0.0.1','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $expected=trim(file_get_contents(sys_get_temp_dir().'/lms-akademik-test-path.txt'));
 $actual=$db->query('SELECT @@datadir')->fetchColumn();
 if(strcasecmp(rtrim(str_replace('\\','/',$expected),'/'),rtrim(str_replace('\\','/',$actual),'/'))!==0)throw new RuntimeException('Refusing non-isolated database');
 $db->exec('CREATE DATABASE IF NOT EXISTS lms_smk_jayabuana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$db->exec('USE lms_smk_jayabuana');
 $db->exec('CREATE TABLE IF NOT EXISTS roles(id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,nama_role VARCHAR(50)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE IF NOT EXISTS users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(50) UNIQUE,password VARCHAR(255),email VARCHAR(100) NULL,role_id TINYINT UNSIGNED,is_active TINYINT DEFAULT 1,FOREIGN KEY(role_id) REFERENCES roles(id)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE IF NOT EXISTS log_aktivitas(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,aktivitas VARCHAR(255),ip_address VARCHAR(45),user_agent TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
 $db->exec("INSERT IGNORE INTO roles VALUES(1,'Admin'),(2,'Guru'),(3,'Siswa'),(4,'Reserved')");
 $before=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
 $migration=file_get_contents('database/2026_10_16_akun_akademik.sql');$db->exec($migration);$db->exec($migration);
 if((int)$db->query("SELECT COUNT(*) FROM roles WHERE nama_role='Akademik'")->fetchColumn()!==1)throw new RuntimeException('Migration is not idempotent');
 if((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==$before)throw new RuntimeException('Migration created default accounts');
 $hash=password_hash(getenv('FIXTURE_PASSWORD'),PASSWORD_BCRYPT);
 $q=$db->prepare('INSERT INTO users(username,password,role_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE password=VALUES(password),role_id=VALUES(role_id),is_active=1');foreach(['admin'=>1,'guru'=>2,'siswa'=>3] as $name=>$role)$q->execute([$name,$hash,$role]);
 echo 'READY';`;
 const result=spawnSync(php,[],{cwd:root,input:fixture,encoding:'utf8',env:{...process.env,FIXTURE_PASSWORD:fixturePassword}});
 assert.equal(result.status,0,result.stderr+result.stdout);assert.match(result.stdout,/READY/);
 const socket=net.createServer();await new Promise(resolve=>socket.listen(0,'127.0.0.1',resolve));const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
 const server=spawn(php,['-d','session.save_path='+os.tmpdir(),'-S','127.0.0.1:'+port,'-t',root],{cwd:root,windowsHide:true,stdio:['ignore','pipe','pipe']});
 let logs='';server.stderr.on('data',chunk=>{logs+=chunk;});
 const base='http://127.0.0.1:'+port;
 function client(){return {cookie:'',async request(route,data){
  const response=await fetch(base+route,{method:data?'POST':'GET',redirect:'manual',headers:{Cookie:this.cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});
  const cookie=response.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];
  return {status:response.status,location:response.headers.get('location'),text:await response.text()};
 }};}
 const csrf=html=>{const match=html.match(/name="csrf_token"\s+value="([^"]+)"/);assert.ok(match,'CSRF missing');return match[1];};
 async function login(c,name,password){const view=await c.request('/login.php');return c.request('/login.php',{csrf_token:csrf(view.text),username:name,password});}
 try{
  for(let tries=0;;tries++){try{await fetch(base+'/login.php');break;}catch(e){if(tries>40)throw e;await new Promise(resolve=>setTimeout(resolve,100));}}
  const admin=client();assert.equal((await login(admin,'admin',fixturePassword)).location,'/admin/index.php');
  for(const [name,destination] of [['guru','/guru/index.php'],['siswa','/siswa/index.php']]){const c=client();assert.equal((await login(c,name,fixturePassword)).location,destination);assert.equal((await c.request('/akademik/index.php')).status,403);}
  let view=await admin.request('/admin/akun_akademik.php');assert.equal(view.status,200);let token=csrf(view.text);
  const akademikPassword='Academic-'+crypto.randomBytes(10).toString('hex');
  const form={csrf_token:token,id:'0',nama_lengkap:'Wakasek Akademik',username:accountUsername,password:akademikPassword,is_active:'1'};
  await admin.request('/admin/akun_akademik.php',{...form,csrf_token:'wrong'});view=await admin.request('/admin/akun_akademik.php');assert.match(view.text,/Sesi keamanan berakhir/);assert.doesNotMatch(view.text,new RegExp(accountUsername.replaceAll('.','\\.')));
  assert.equal((await admin.request('/admin/akun_akademik.php',form)).status,302);
  view=await admin.request('/admin/akun_akademik.php');assert.match(view.text,/Wakasek Akademik/);const id=view.text.match(/href="\?edit=(\d+)"/)[1];
  await admin.request('/admin/akun_akademik.php',form);view=await admin.request('/admin/akun_akademik.php');assert.match(view.text,/Username sudah digunakan/);
  const academic=client();assert.equal((await login(academic,accountUsername,akademikPassword)).location,'/akademik/index.php');
  view=await academic.request('/akademik/index.php');assert.equal(view.status,200);assert.match(view.text,/Monitoring KBM belum dikonfigurasi/);
  assert.equal((await academic.request('/index.php')).location,'/akademik/index.php');
  view=await academic.request('/akademik/profil.php?id=1');assert.match(view.text,/Wakasek Akademik/);assert.ok(view.text.includes(accountUsername));
  for(const route of ['/admin/users.php','/admin/dashboard.php','/admin/akun_akademik.php','/guru/rekap_nilai_action.php','/siswa/absensi.php'])assert.equal((await academic.request(route)).status,403,route);
  assert.equal((await academic.request('/admin/akun_akademik.php',form)).status,403);
  assert.equal((await academic.request('/akademik/profil.php',{nama_lengkap:'Changed'})).status,405);
  const edit={...form,id,password:'',nama_lengkap:'Wakasek Diperbarui',username:renamedUsername};
  await admin.request('/admin/akun_akademik.php',edit);view=await academic.request('/akademik/profil.php');assert.match(view.text,/Wakasek Diperbarui/);assert.ok(view.text.includes(renamedUsername));
  const beforeReset=client();await login(beforeReset,renamedUsername,akademikPassword);
  const resetPassword='Reset-'+crypto.randomBytes(10).toString('hex');await admin.request('/admin/akun_akademik.php',{...edit,password:resetPassword});
  assert.equal((await beforeReset.request('/akademik/index.php')).location,'/login.php');
  const oldPassword=client();assert.equal((await login(oldPassword,renamedUsername,akademikPassword)).status,200);
  const active=client();assert.equal((await login(active,renamedUsername,resetPassword)).location,'/akademik/index.php');
  await admin.request('/admin/akun_akademik.php',{...edit,is_active:'0'});assert.equal((await active.request('/akademik/index.php')).location,'/login.php');
  const disabled=client();assert.equal((await login(disabled,renamedUsername,resetPassword)).status,200);
  await admin.request('/admin/akun_akademik.php',{...edit,is_active:'1'});assert.equal((await active.request('/akademik/index.php')).location,'/login.php');
  const logout=client();await login(logout,renamedUsername,resetPassword);assert.equal((await logout.request('/logout.php')).status,302);assert.equal((await logout.request('/akademik/index.php')).location,'/login.php');
  const dbCheck=String.raw`<?php require 'config/database.php';$db=Database::getInstance();$q=$db->prepare('SELECT password FROM users WHERE username=?');$q->execute([getenv('FIXTURE_USERNAME')]);$hash=$q->fetchColumn();if(!password_verify(getenv('FIXTURE_PASSWORD'),$hash)||$hash===getenv('FIXTURE_PASSWORD'))throw new RuntimeException('Password not securely hashed');$roles=$db->query('SELECT nama_role FROM roles WHERE id IN(1,2,3,4) ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);if($roles!==['Admin','Guru','Siswa','Reserved'])throw new RuntimeException('Old roles changed');require 'includes/akun_akademik.php';
function reject_account($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid account operation accepted');}
reject_account(fn()=>akademik_save($db,1,0,'','valid.name','long-enough-password','1'));
reject_account(fn()=>akademik_save($db,1,0,'Name','ADMIN','long-enough-password','1'));
reject_account(fn()=>akademik_save($db,1,0,'Name','valid.name','short','1'));
reject_account(fn()=>akademik_save($db,1,0,'Name','bad username','long-enough-password','1'));
reject_account(fn()=>akademik_save($db,1,0,'Name','valid.name','long-enough-password','2'));
reject_account(fn()=>akademik_save($db,2,0,'Name','valid.name','long-enough-password','1'));
reject_account(fn()=>akademik_save($db,1,2,'Name','valid.name','','1'));
$q=$db->prepare('SELECT id FROM users WHERE username=?');$q->execute([getenv('FIXTURE_USERNAME')]);$id=(int)$q->fetchColumn();
$db->exec('CREATE TEMPORARY TABLE log_aktivitas(user_id INT CHECK(user_id<0),aktivitas TEXT,ip_address VARCHAR(45),user_agent TEXT) ENGINE=InnoDB');
reject_account(fn()=>akademik_save($db,1,$id,'Rollback','rollback.account','','0'));
$q=$db->prepare('SELECT username,is_active FROM users WHERE id=?');$q->execute([$id]);$after=$q->fetch();
if($after['username']!==getenv('FIXTURE_USERNAME')||(int)$after['is_active']!==1||$db->inTransaction())throw new RuntimeException('Audit failure partially changed the account');
echo 'PASS';`;
  const checked=spawnSync(php,[],{cwd:root,input:dbCheck,encoding:'utf8',env:{...process.env,FIXTURE_PASSWORD:resetPassword,FIXTURE_USERNAME:renamedUsername}});assert.equal(checked.status,0,checked.stderr);assert.match(checked.stdout,/PASS/);
  assert.doesNotMatch(logs,/Fatal error|Warning:|Parse error/);
 }finally{server.kill();}
});
