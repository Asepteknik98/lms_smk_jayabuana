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
    const approved=run(reviewCode+`if(kak_claim_total($db,1)!==0)throw new RuntimeException('Pending points counted');kak_staff_review($db,1,['klaim_id'=>${claim.data.id},'keputusan'=>'Disetujui'],'${proofFolder}');echo kak_claim_total($db,1);`);
    assert.equal(approved,'25','Admin approval credits exact event points');
    assert.equal(run(reviewCode+`echo kak_staff_review($db,1,['klaim_id'=>${claim.data.id},'keputusan'=>'Disetujui'],'${proofFolder}')?'CHANGED':'UNCHANGED';echo ':'.kak_claim_total($db,1);`),'UNCHANGED:25','Repeated approval does not double-credit');
    const legacy=await upload(100,3,png,'image/png','bukti_kamera');assert.equal(legacy.status,200,JSON.stringify(legacy.data));
    assert.equal(run(reviewCode+`kak_staff_review($db,2,['klaim_id'=>${legacy.data.id},'keputusan'=>'Disetujui'],'${proofFolder}');echo kak_claim_total($db,1).':'.$db->query('SELECT COUNT(*) FROM kak_aktivitas_siswa')->fetchColumn();`),'45:2','Legacy claim reuses preserved row without duplicate credit');
    assert.equal(run(reviewCode+`
        if(kak_claim_total($db,1,'X')!==45||kak_claim_total($db,1,'XI')!==0||kak_claim_total($db,1,'XII')!==0)throw new RuntimeException('Grade totals incorrect');
        $db->exec('UPDATE siswa SET kelas_id=2 WHERE id=1');
        $db->exec(file_get_contents('database/2026_11_03_kak_poin_per_tingkat.sql'));
        if(kak_claim_total($db,1,'XI')!==0||kak_claim_total($db,1,'X')!==45||kak_claim_total($db,1)!==45)throw new RuntimeException('Promotion moved old points');
        $db->exec('UPDATE siswa SET kelas_id=1 WHERE id=1');echo 'GRADE_PASS';`),'GRADE_PASS','Grade totals and promotion preserve old history without carrying points');
    const created=JSON.parse(run(reviewCode+`$id=kak_staff_create($db,2,['aktivitas_id'=>1,'nama'=>'Teacher event','tanggal_acara'=>date('Y-m-d'),'poin'=>'10','kelas_id'=>'1','siswa_id'=>'1']);echo json_encode(['id'=>$id,'total'=>kak_claim_total($db,1)]);`));
    for(const target of [{},{kelas_id:'999'},{kelas_id:'1',siswa_id:'2'},{kelas_id:'1',siswa_id:'bad'}]){
        assert.equal(run(reviewCode+`try{kak_staff_create($db,2,array_merge(['nama'=>'Invalid target','tanggal_acara'=>date('Y-m-d'),'poin'=>'10'],json_decode('${JSON.stringify(target)}',true)));echo 'WRONG';}catch(InvalidArgumentException $e){echo 'BLOCKED';}`),'BLOCKED','Class required and student must belong to selected class');
    }
    assert.equal(run(reviewCode+`$id=kak_staff_create($db,2,['aktivitas_id'=>1,'nama'=>'Whole class','tanggal_acara'=>date('Y-m-d'),'poin'=>'10','kelas_id'=>'1','siswa_id'=>'']);$q=$db->query('SELECT kelas_id,siswa_id FROM kak_acara WHERE id='.$id)->fetch();echo $q['kelas_id'].':'.($q['siswa_id']===null?'ALL':'WRONG');`),'1:ALL','Whole class target supported');
    assert.equal(created.total,45,'Publishing event never credits points');
    for(const [grade,allowedUsers,blockedUsers] of [['X',[3,7],[4,8]],['XI',[4],[3,7,8]],['XII',[8],[3,4,7]]]){
        const gradeId=Number(run(reviewCode+`$master=$db->query("SELECT id FROM kak_master_aktivitas WHERE kode='${grade}.01'")->fetchColumn();echo kak_staff_create($db,2,['aktivitas_id'=>$master,'tanggal_acara'=>date('Y-m-d'),'poin'=>'10','kelas_id'=>'tingkat:${grade}']);`));
        for(const user of allowedUsers){
            const events=await (await fetch(base,{headers:{'X-Test-User':String(user)}})).json();assert.ok(events.some(event=>event.id===gradeId),'Whole-grade event visible in every matching class');
            assert.equal((await upload(gradeId,user)).status,200,'Matching grade may submit proof');
            assert.equal((await upload(gradeId,user)).status,422,'Whole-grade claims still unique per student');
        }
        for(const user of blockedUsers){
            const events=await (await fetch(base,{headers:{'X-Test-User':String(user)}})).json();assert.ok(!events.some(event=>event.id===gradeId),'Other grades cannot see event');
            assert.equal((await upload(gradeId,user)).status,422,'Other grades cannot bypass scope via POST');
        }
    }
    for(const target of [{kelas_id:'tingkat:INVALID'},{kelas_id:'tingkat:X',siswa_id:'1'}]){
        assert.equal(run(reviewCode+`try{kak_staff_create($db,2,array_merge(['nama'=>'Invalid grade','tanggal_acara'=>date('Y-m-d'),'poin'=>'10'],json_decode('${JSON.stringify(target)}',true)));echo 'WRONG';}catch(InvalidArgumentException $e){echo 'BLOCKED';}`),'BLOCKED','Grade target rejects invalid grade and individual override');
    }
    const denied=await upload(created.id);assert.equal(denied.status,200);
    assert.equal(run(reviewCode+`kak_staff_review($db,2,['klaim_id'=>${denied.data.id},'keputusan'=>'Ditolak','catatan_verifikasi'=>'Bukti tidak sesuai'],'${proofFolder}');echo kak_claim_total($db,1);`),'45','Rejected claim does not credit points');
    const noProof=await upload(1,4);assert.equal(noProof.status,200);const otherProof=JSON.parse(run(connect+`$db->exec('USE '.chr(96).getenv('KAK_TEST_DB').chr(96));echo json_encode($db->query('SELECT bukti_file FROM kak_klaim WHERE id=${noProof.data.id}')->fetch());`));fs.unlinkSync(path.join(temp,'proofs',otherProof.bukti_file));
    assert.equal(run(reviewCode+`try{kak_staff_review($db,1,['klaim_id'=>${noProof.data.id},'keputusan'=>'Disetujui'],'${proofFolder}');echo 'WRONG';}catch(InvalidArgumentException $e){echo 'BLOCKED:'.kak_claim_total($db,2);}`),'BLOCKED:0','Missing physical evidence cannot be approved');
    const both=new FormData();both.set('acara_id','1');both.set('bukti',new Blob([png],{type:'image/png'}),'gallery.png');both.set('bukti_kamera',new Blob([png],{type:'image/png'}),'camera.png');
    assert.equal((await fetch(base,{method:'POST',body:both})).status,422,'Two simultaneous photo sources rejected');
    assert.equal(run(reviewCode+`
    $event=$db->prepare("INSERT INTO kak_acara(nama,tanggal_acara,poin,kelas_id,status,dibuat_oleh) VALUES(?,CURRENT_DATE,10,1,'Terbit',?)");
    $claim=$db->prepare("INSERT INTO kak_klaim(acara_id,siswa_id,bukti_file,bukti_mime,status) VALUES(?,1,?,'image/png',?)");
    $db->beginTransaction();for($i=0;$i<1205;$i++){
        $event->execute(['Mass event '.sprintf('%04d',$i),$i%2===0?2:6]);$id=(int)$db->lastInsertId();
        $claim->execute([$id,str_repeat('a',48).'.png',['Menunggu','Disetujui','Ditolak'][$i%3]]);
    }$db->commit();
    $first=kak_staff_list($db,1,'claims',['q'=>'Mass event']);$second=kak_staff_list($db,1,'claims',['q'=>'Mass event','page'=>2]);
    if($first['total']!==1205||count($first['rows'])!==10||count($second['rows'])!==10)throw new RuntimeException('Page size or count mismatch');
    if(array_intersect(array_column($first['rows'],'id'),array_column($second['rows'],'id')))throw new RuntimeException('Pages overlap');
    $last=kak_staff_list($db,1,'claims',['q'=>'Mass event','page'=>999999]);if($last['page']!==121||count($last['rows'])!==5)throw new RuntimeException('Last page inaccessible');
    $waiting=kak_staff_list($db,1,'claims',['q'=>'Mass event','status'=>'Menunggu']);if($waiting['total']!==402)throw new RuntimeException('Status filter failed');
    foreach(['claims','events'] as $kind){
        $own=kak_staff_list($db,2,$kind,['q'=>'Mass event']);if($own['total']!==603)throw new RuntimeException('Teacher scope failed');
        $literal=kak_staff_list($db,1,$kind,['q'=>'%_!']);if($literal['total']!==0)throw new RuntimeException('Search wildcards must be literal');
        $deep=kak_staff_list($db,1,$kind,['q'=>'Mass event 1204']);if($deep['total']!==1)throw new RuntimeException('Search misses older records');
    }
    if(kak_staff_list($db,1,'claims',['q'=>'Test Student'])['total']<1205)throw new RuntimeException('Student search failed');
    if(kak_staff_list($db,1,'events',['q'=>'Test Class'])['total']<1205)throw new RuntimeException('Class search failed');
    if(kak_staff_list($db,1,'events',['q'=>'Semua kelas 10'])['total']!==1)throw new RuntimeException('Grade search failed');
    echo 'PAGING_PASS';`),'PAGING_PASS','1205 rows: bounded pages, all records reachable, search, status and teacher scope');
    assert.equal(run(reviewCode+`
    require 'includes/kak_export.php';
    $all=kak_export_sheets($db,1,'claims',['q'=>'Mass event']);
    if(count($all[1]['rows'])!==1206)throw new RuntimeException('Export omitted records beyond first page');
    $own=kak_export_sheets($db,2,'events',['q'=>'Mass event']);if(count($own[1]['rows'])!==604)throw new RuntimeException('Export teacher scope failed');
    $filtered=kak_export_sheets($db,1,'claims',['q'=>'Mass event','status'=>'Menunggu']);
    if(count($filtered[1]['rows'])!==403)throw new RuntimeException('Export filter failed');
    foreach(array_slice($filtered[1]['rows'],1) as $row)if($row[7]!==0)throw new RuntimeException('Pending claim counted as approved points');
    $empty=kak_export_sheets($db,1,'events',['q'=>'absent12345']);if(count($empty[1]['rows'])!==1)throw new RuntimeException('Empty export failed');
    $xlsx=ew_workbook($all);if(substr($xlsx,0,2)!=='PK'||strpos($xlsx,'autoFilter')===false||strpos($xlsx,'state="frozen"')===false)throw new RuntimeException('Invalid workbook');
    $safe=ew_workbook([['name'=>'Safe','rows'=>[['Name'],['=HYPERLINK("bad")']]]]);if(strpos($safe,'<f>')!==false||strpos($safe,'t="inlineStr"')===false)throw new RuntimeException('Formula not exported as text');
    try{kak_export_sheets($db,3,'claims',[]);throw new RuntimeException('Student export accepted');}catch(DomainException $e){}
    echo 'EXPORT_PASS';`),'EXPORT_PASS','XLSX full dataset, teacher scope, status filters, pending points, empty data and safe text');
    assert.equal(run(reviewCode+`
    $old=$db->query('SELECT nama,poin,poin_standar,tingkat_aktivitas FROM kak_acara WHERE id=${created.id}')->fetch();
    if($old['nama']!=='MPLS'||(int)$old['poin']!==10||(int)$old['poin_standar']!==50||$old['tingkat_aktivitas']!=='X')throw new RuntimeException('Invalid snapshot');
    foreach([[],['aktivitas_id'=>11],['aktivitas_id'=>99999]] as $input){
        try{kak_staff_create($db,2,array_merge(['kelas_id'=>'1','tanggal_acara'=>date('Y-m-d')],$input));throw new RuntimeException('Invalid master accepted');}catch(InvalidArgumentException $e){}
    }
    $id=kak_staff_create($db,2,['kelas_id'=>'1','aktivitas_id'=>1,'tanggal_acara'=>date('Y-m-d'),'poin'=>'']);
    if((int)$db->query('SELECT poin FROM kak_acara WHERE id='.$id)->fetchColumn()!==50)throw new RuntimeException('Standard fallback failed');
    try{kak_master_save($db,2,['id'=>1,'tingkat'=>'X','nama'=>'Changed','poin'=>60,'aktif'=>'1']);throw new RuntimeException('Teacher changed master');}catch(DomainException $e){}
    kak_master_save($db,1,['id'=>1,'tingkat'=>'X','nama'=>'Changed MPLS','poin'=>60,'aktif'=>'1']);
    $after=$db->query('SELECT nama,poin,poin_standar,tingkat_aktivitas FROM kak_acara WHERE id=${created.id}')->fetch();if($old!==$after)throw new RuntimeException('Master edit changed published event');
    kak_master_save($db,1,['id'=>1,'tingkat'=>'X','nama'=>'Changed MPLS','poin'=>60]);
    try{kak_staff_create($db,2,['kelas_id'=>'1','aktivitas_id'=>1,'tanggal_acara'=>date('Y-m-d')]);throw new RuntimeException('Inactive master accepted');}catch(InvalidArgumentException $e){}
    $db->exec("UPDATE kak_target_tingkat SET target_poin=123 WHERE tingkat='X'");$db->exec(file_get_contents('database/2026_11_03_master_aktivitas.sql'));
    if((int)$db->query("SELECT target_poin FROM kak_target_tingkat WHERE tingkat='X'")->fetchColumn()!==123||(int)$db->query('SELECT poin FROM kak_master_aktivitas WHERE id=1')->fetchColumn()!==60)throw new RuntimeException('Migration reset admin changes');
    if((int)$db->query("SELECT COUNT(*) FROM kak_master_aktivitas WHERE tingkat='XI' AND nama IN ('UTS SMT 1','Selesai UTS SMT 1')")->fetchColumn()!==2)throw new RuntimeException('Separate UTS activities missing');
    $new=kak_master_save($db,1,['tingkat'=>'X','nama'=>'Temporary','poin'=>5,'aktif'=>'1']);
    try{kak_master_delete($db,2,$new);throw new RuntimeException('Teacher deleted master');}catch(DomainException $e){}
    if(kak_master_delete($db,1,$new)!=='deleted')throw new RuntimeException('Unused master not deleted');
    if((int)$db->query('SELECT COUNT(*) FROM kak_master_aktivitas WHERE id='.$new)->fetchColumn()!==0)throw new RuntimeException('Unused row remains');
    $eventsBefore=(int)$db->query('SELECT COUNT(*) FROM kak_acara')->fetchColumn();$claimsBefore=(int)$db->query('SELECT COUNT(*) FROM kak_klaim')->fetchColumn();
    if(kak_master_delete($db,1,1)!=='deactivated')throw new RuntimeException('Used master not protected');
    if((int)$db->query('SELECT COUNT(*) FROM kak_acara')->fetchColumn()!==$eventsBefore||(int)$db->query('SELECT COUNT(*) FROM kak_klaim')->fetchColumn()!==$claimsBefore)throw new RuntimeException('Deletion changed history');
    echo 'MASTER_PASS';`),'MASTER_PASS','Grade-scoped master, editable defaults, protected delete, snapshots, admin-only edits and safe rerun');
    console.log('PASS: 33 master activities, editable defaults, snapshots, grade scope, migrations, claim evidence, approval exactly once and pagination.');
}finally{
    if(server){server.kill();await new Promise(resolve=>server.once('exit',resolve));}
    run(connect+`$db->exec('DROP DATABASE IF EXISTS '.chr(96).getenv('KAK_TEST_DB').chr(96));`);
    const resolved=path.resolve(temp);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('lms-kak-claim-'));
    fs.rmSync(resolved,{recursive:true,force:true});
}
})().catch(e=>{console.error(e);process.exitCode=1;});
