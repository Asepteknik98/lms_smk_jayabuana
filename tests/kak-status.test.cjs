const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {spawnSync,spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..');
const php=process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe';
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'lms-kak-claim-'));
const database='lms_kak_claim_test_'+require('node:crypto').randomBytes(5).toString('hex');
function run(code){const r=spawnSync(php,[],{cwd:root,input:'<?php '+code,encoding:'utf8',env:{...process.env,KAK_TEST_DB:database},timeout:15000});assert.equal(r.status,0,r.stderr+r.stdout);return r.stdout;}
const connect=`require 'config/database.php';$db=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);`;
let server;
(async()=>{
try{
    run(connect+`$name=getenv('KAK_TEST_DB');$db->exec('CREATE DATABASE '.chr(96).$name.chr(96));$db->exec('USE '.chr(96).$name.chr(96));
    $db->exec('CREATE TABLE users(id int unsigned PRIMARY KEY,role_id int,is_active int) ENGINE=InnoDB');
    $db->exec("CREATE TABLE kelas(id int unsigned PRIMARY KEY,tingkat enum('X','XI','XII'),nama_kelas varchar(100) DEFAULT 'Test Class') ENGINE=InnoDB");
    $db->exec("CREATE TABLE siswa(id int unsigned PRIMARY KEY,user_id int unsigned,kelas_id int unsigned,nama_lengkap varchar(100) DEFAULT 'Test Student') ENGINE=InnoDB");
    $db->exec('CREATE TABLE guru(id int unsigned PRIMARY KEY,user_id int unsigned) ENGINE=InnoDB');
    $db->exec(file_get_contents('database/2026_08_09_kredit_aktivitas_kesiswaan.sql'));
    $db->exec('INSERT INTO users VALUES(1,1,1),(2,2,1),(3,3,1),(4,3,1),(5,3,0)');
    $db->exec("INSERT INTO kelas(id,tingkat) VALUES(1,'X'),(2,'XI'),(3,'X'),(4,'XII')");$db->exec('INSERT INTO siswa(id,user_id,kelas_id) VALUES(1,3,1),(2,4,2),(3,5,1)');
    $db->exec('INSERT INTO users VALUES(7,3,1),(8,3,1)');$db->exec('INSERT INTO siswa(id,user_id,kelas_id) VALUES(4,7,3),(5,8,4)');
    $db->exec('INSERT INTO users VALUES(6,2,1)');$db->exec('INSERT INTO guru VALUES(1,2),(2,6)');
    $db->exec(file_get_contents('database/2026_11_03_kredit_akses_guru.sql'));
    $db->exec("INSERT INTO kredit_akses_guru(guru_id,modul,diberikan_oleh) VALUES(1,'aktivitas',1),(2,'aktivitas',1)");
    $db->exec("INSERT INTO kak_aktivitas_siswa(siswa_id,guru_id,nama_aktivitas,tanggal_aktivitas,poin) VALUES(1,1,'Legacy activity',CURRENT_DATE,20)");
    require 'includes/kak_klaim.php';if(kak_claim_ready($db))throw new RuntimeException('Unexpected ready schema');
    $db->exec(file_get_contents('database/2026_11_03_kak_klaim.sql'));$db->exec(file_get_contents('database/2026_11_03_kak_klaim.sql'));
    $db->exec(file_get_contents('database/2026_11_03_kak_wajib_klaim.sql'));$db->exec(file_get_contents('database/2026_11_03_kak_wajib_klaim.sql'));
    $db->exec(file_get_contents('database/2026_11_03_kak_tanpa_tenggat.sql'));$db->exec(file_get_contents('database/2026_11_03_kak_tanpa_tenggat.sql'));
    $db->exec(file_get_contents('database/2026_11_03_kak_target_angkatan.sql'));$db->exec(file_get_contents('database/2026_11_03_kak_target_angkatan.sql'));
    $db->exec('CREATE TABLE log_aktivitas(id int unsigned AUTO_INCREMENT PRIMARY KEY,user_id int unsigned,aktivitas text,ip_address varchar(100),user_agent text)');
    $db->exec(file_get_contents('database/2026_11_03_master_aktivitas.sql'));$db->exec(file_get_contents('database/2026_11_03_master_aktivitas.sql'));
    $db->exec(file_get_contents('database/2026_11_03_kak_poin_per_tingkat.sql'));$db->exec(file_get_contents('database/2026_11_03_kak_poin_per_tingkat.sql'));
    if((int)$db->query('SELECT COUNT(*) FROM kak_master_aktivitas')->fetchColumn()!==33)throw new RuntimeException('Expected 33 activities');
    if((int)$db->query('SELECT SUM(target_poin) FROM kak_target_tingkat')->fetchColumn()!==300)throw new RuntimeException('Expected cumulative target 300');
    foreach(['X'=>120,'XI'=>131,'XII'=>151] as $grade=>$sum){$q=$db->prepare('SELECT SUM(poin) FROM kak_master_aktivitas WHERE tingkat=?');$q->execute([$grade]);if((int)$q->fetchColumn()!==$sum)throw new RuntimeException('Invalid activity points');}
    if(kak_claim_total($db,1)!==0)throw new RuntimeException('Legacy points must require a claim');
    $db->exec('UPDATE kak_acara SET id=100 WHERE aktivitas_asal_id IS NOT NULL');
    $db->exec("INSERT INTO kak_acara(id,nama,tanggal_acara,klaim_mulai,klaim_sampai,poin,kelas_id,status,dibuat_oleh) VALUES
    (1,'Acara sekolah',CURRENT_DATE,NOW()-INTERVAL 1 DAY,NOW()+INTERVAL 1 DAY,25,NULL,'Terbit',1),
    (2,'Draft',CURRENT_DATE,NOW()-INTERVAL 1 DAY,NOW()+INTERVAL 1 DAY,25,NULL,'Draft',1),
    (3,'Selesai',CURRENT_DATE,NOW()-INTERVAL 2 DAY,NOW()-INTERVAL 1 DAY,25,NULL,'Terbit',1),
    (4,'Kelas lain',CURRENT_DATE,NOW()-INTERVAL 1 DAY,NOW()+INTERVAL 1 DAY,25,2,'Terbit',1),
    (5,'Belum mulai',CURRENT_DATE,NOW()+INTERVAL 1 DAY,NOW()+INTERVAL 2 DAY,25,NULL,'Terbit',1)");`);
    const router=path.join(temp,'router.php');
    const source=`<?php
    chdir(${JSON.stringify(root.replaceAll('\\','/'))});
    ${connect}
    $db->exec('USE '.chr(96).getenv('KAK_TEST_DB').chr(96));require 'includes/kak_klaim.php';header('Content-Type: application/json');
    try{$user=(int)($_SERVER['HTTP_X_TEST_USER']??3);
      if($_SERVER['REQUEST_METHOD']==='GET')echo json_encode(kak_claim_events($db,$user));
      else echo json_encode(['id'=>kak_claim_submit($db,$user,$_POST,kak_claim_upload($_FILES),getenv('KAK_TEST_STORAGE'))]);
    }catch(Throwable $e){http_response_code($e instanceof DomainException?403:422);echo json_encode(['error'=>$e->getMessage()]);}`;
    fs.writeFileSync(router,source);
    const port=24000+Math.floor(Math.random()*8000),base='http://127.0.0.1:'+port;
    server=spawn(php,['-d','upload_max_filesize=6M','-d','post_max_size=8M','-d',`upload_tmp_dir=${temp}`,'-S',`127.0.0.1:${port}`,router],{cwd:root,env:{...process.env,KAK_TEST_DB:database,KAK_TEST_STORAGE:path.join(temp,'proofs')},stdio:'ignore'});
    let started=false;
    for(let i=0;i<50;i++){try{await fetch(base);started=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
    assert.ok(started,'PHP test server starts');
    const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j5Z0AAAAASUVORK5CYII=','base64');
    async function upload(id=1,user=3,data=png,type='image/png',field='bukti'){
        const form=new FormData();form.set('acara_id',String(id));form.set('catatan','Foto sertifikat');form.set('siswa_id','2');form.set('poin','99999');
        if(data)form.set(field,new Blob([data],{type}),'sertifikat.png');
        const result=await fetch(base,{method:'POST',headers:{'X-Test-User':String(user)},body:form});return {status:result.status,data:await result.json()};
    }
    assert.equal((await upload(1,3,null)).status,422,'Evidence required');
    assert.equal((await upload(1,3,Buffer.from('<?php echo 1; ?>'),'image/png')).status,422,'Fake image rejected');
    assert.equal((await upload(1,3,Buffer.alloc(5*1024*1024+1),'image/png')).status,422,'Oversized image rejected');
    for(const id of [2,4,999])assert.equal((await upload(id)).status,422,'Draft/other class/missing denied');
    for(const user of [1,2,5,999])assert.equal((await upload(1,user)).status,403,'Nonstudent/inactive denied');
    const claim=await upload();assert.equal(claim.status,200,JSON.stringify(claim.data));
    assert.equal((await upload()).status,422,'Duplicate denied');
    const listing=await (await fetch(base)).json();
    assert.equal(listing.find(x=>x.id===1).status_klaim,'Menunggu');
    assert.ok(!listing.some(x=>x.id===2||x.id===4),'Draft and wrong class hidden');
    const other=await (await fetch(base,{headers:{'X-Test-User':'4'}})).json();
    assert.equal(other.find(x=>x.id===1).klaim_id,null,'Other student cannot see claim');
    assert.ok(!other.some(x=>x.id===100),'Legacy individual invitation hidden from other students');
    const stats=JSON.parse(run(connect+`$db->exec('USE '.chr(96).getenv('KAK_TEST_DB').chr(96));echo json_encode($db->query('SELECT siswa_id,status,aktivitas_id,bukti_file FROM kak_klaim')->fetchAll());`));
    assert.equal(stats.length,1);assert.equal(stats[0].siswa_id,1,'Student identity comes from account');assert.equal(stats[0].aktivitas_id,null,'Pending claim never credits points');
    assert.equal(fs.readdirSync(path.join(temp,'proofs')).length,1,'Rejected uploads leave no files');
    assert.match(stats[0].bukti_file,/^[a-f0-9]{48}\.png$/,'Stored filename randomized');
    assert.equal((await upload(3)).status,200,'Old expired schedule no longer blocks claims');
    assert.equal((await upload(5)).status,200,'Old future opening date no longer blocks claims');
    const reviewCode=connect+`$db->exec('USE '.chr(96).getenv('KAK_TEST_DB').chr(96));require 'includes/kak_staff.php';`;
    const proofFolder=path.join(temp,'proofs').replaceAll('\\','/');
    const rejectAttempt=run(reviewCode+`try{kak_staff_review($db,6,['klaim_id'=>${claim.data.id},'keputusan'=>'Disetujui'],'${proofFolder}');echo 'WRONG';}catch(DomainException $e){echo 'FORBIDDEN';}`);
    assert.equal(rejectAttempt,'FORBIDDEN','Other teachers cannot approve another event');
    assert.equal(run(reviewCode+`kak_staff_review($db,1,['klaim_id'=>${claim.data.id},'disetujui'=>'1'],'${proofFolder}');echo $db->query('SELECT status FROM kak_klaim WHERE id=${claim.data.id}')->fetchColumn().':'.kak_claim_total($db,1);`),'Disetujui:0','Checkbox approves without awarding points');
    assert.equal(run(reviewCode+`echo kak_staff_review($db,1,['klaim_id'=>${claim.data.id},'disetujui'=>'1'],'${proofFolder}')?'CHANGED':'UNCHANGED';`),'UNCHANGED','Repeated approval is idempotent');
    const rejected=await upload(100);assert.equal(rejected.status,200);
    assert.equal(run(reviewCode+`try{kak_staff_review($db,1,['klaim_id'=>${rejected.data.id}],'${proofFolder}');echo 'WRONG';}catch(InvalidArgumentException $e){echo 'REQUIRED';}`),'REQUIRED','Unchecked approval requires correction note');
    run(reviewCode+`kak_staff_review($db,1,['klaim_id'=>${rejected.data.id},'catatan_verifikasi'=>'Foto kurang jelas'],'${proofFolder}');`);
    const resubmitted=await upload(100);assert.equal(resubmitted.status,200);assert.equal(resubmitted.data.id,rejected.data.id,'Resubmission reuses claim');
    assert.equal(run(reviewCode+`$c=$db->query('SELECT status,catatan_verifikasi FROM kak_klaim WHERE id=${rejected.data.id}')->fetch();echo $c['status'].':'.($c['catatan_verifikasi']===null?'CLEAR':'STALE');`),'Menunggu:CLEAR');
    assert.equal(run(reviewCode+`$id=kak_staff_create($db,2,['aktivitas_id'=>1,'tanggal_acara'=>date('Y-m-d'),'kelas_id'=>'1']);echo $db->query('SELECT poin FROM kak_acara WHERE id='.$id)->fetchColumn();`),'1','Event creation needs no point input');
    run(reviewCode+`require 'includes/kak_export.php';foreach(['claims','events'] as $kind){$sheets=kak_export_sheets($db,1,$kind,[]);foreach($sheets as $sheet)foreach($sheet['rows'] as $row)foreach($row as $cell)if(is_string($cell)&&stripos($cell,'poin')!==false)throw new RuntimeException('Points in export');}`);
    assert.equal(run(reviewCode+`$id=kak_master_save($db,1,['tingkat'=>'X','nama'=>'Kegiatan tanpa poin','aktif'=>'1']);$q=$db->prepare('SELECT nama,aktif FROM kak_master_aktivitas WHERE id=?');$q->execute([$id]);$r=$q->fetch();if($r['nama']!=='Kegiatan tanpa poin'||!(int)$r['aktif'])throw new RuntimeException('Create failed');kak_master_save($db,1,['id'=>$id,'tingkat'=>'XI','nama'=>'Kegiatan diperbarui']);$q->execute([$id]);$r=$q->fetch();echo $r['nama'].':'.$r['aktif'];`),'Kegiatan diperbarui:0','Master create and edit require no points and support deactivation');
    assert.equal(run(reviewCode+`try{kak_master_save($db,2,['tingkat'=>'X','nama'=>'Tidak diizinkan']);echo 'WRONG';}catch(DomainException $e){echo 'FORBIDDEN';}`),'FORBIDDEN','Master remains admin only');
    assert.equal(run(reviewCode+`$id=kak_staff_create($db,2,['aktivitas_id'=>1,'tanggal_acara'=>'2026-11-04','kelas_id'=>'1']);$before=$db->query('SELECT * FROM kak_klaim ORDER BY id')->fetchAll();kak_staff_update_date($db,2,['acara_id'=>$id,'tanggal_acara'=>'2026-11-25']);if($db->query('SELECT tanggal_acara FROM kak_acara WHERE id='.$id)->fetchColumn()!=='2026-11-25')throw new RuntimeException('Owner update failed');try{kak_staff_update_date($db,6,['acara_id'=>$id,'tanggal_acara'=>'2026-11-26']);throw new RuntimeException('Other teacher changed date');}catch(DomainException $e){}kak_staff_update_date($db,1,['acara_id'=>$id,'tanggal_acara'=>'2026-12-01']);if($db->query('SELECT tanggal_acara FROM kak_acara WHERE id='.$id)->fetchColumn()!=='2026-12-01')throw new RuntimeException('Admin update failed');foreach(['2026-02-30','2026-13-01','invalid'] as $date){try{kak_staff_update_date($db,1,['acara_id'=>$id,'tanggal_acara'=>$date]);throw new RuntimeException('Invalid date accepted');}catch(InvalidArgumentException $e){}}try{kak_staff_update_date($db,1,['acara_id'=>999999,'tanggal_acara'=>'2026-12-01']);throw new RuntimeException('Missing event accepted');}catch(DomainException $e){}kak_staff_update_date($db,1,['acara_id'=>1,'tanggal_acara'=>'2026-12-02']);if($before!==$db->query('SELECT * FROM kak_klaim ORDER BY id')->fetchAll())throw new RuntimeException('Claims changed');echo 'DATE_PASS';`),'DATE_PASS','Date editing: owner/admin permissions, valid dates and preserved claims');
    console.log('PASS: uploads, access control, checkbox approval without points, duplicate protection, correction and resubmission, event creation, exports.');
}finally{
    if(server){server.kill();await new Promise(resolve=>server.once('exit',resolve));}
    run(connect+`$db->exec('DROP DATABASE IF EXISTS '.chr(96).getenv('KAK_TEST_DB').chr(96));`);
    const resolved=path.resolve(temp);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('lms-kak-claim-'));
    fs.rmSync(resolved,{recursive:true,force:true});
}
})().catch(e=>{console.error(e);process.exitCode=1;});
