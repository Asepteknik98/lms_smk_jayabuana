const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync,spawn}=require('node:child_process');
const fs=require('node:fs');const os=require('node:os');const path=require('node:path');const vm=require('node:vm');
const cwd=path.join(__dirname,'..'),php='C:/xampp/php/php.exe';
const dbName='lms_uh_test_'+require('node:crypto').randomBytes(6).toString('hex');
function sql(code){const r=spawnSync(php,[],{cwd,encoding:'utf8',input:`<?php require 'config/database.php';$db=Database::getInstance();${code}`});assert.equal(r.status,0,r.stdout+r.stderr);assert.equal(r.stderr,'');return r.stdout;}
test('UH: migration, recipients, CBT/essay, file submissions, copying and live grade recap',async()=>{
 let server,dir;const uploaded=[];
 try{
 sql(`$db->exec('CREATE DATABASE ${dbName} CHARACTER SET utf8mb4');foreach(['roles','users','guru','siswa','kelas','mapel','pengajaran','ujian','soal_ujian','sesi_ujian','jawaban_siswa','nilai_ujian','log_aktivitas','tugas','pengumpulan_tugas','rekap_tugas_status','nilai_tugas_manual','sesi_absensi','detail_absensi','komponen_penilaian','nilai_komponen','riwayat_nilai','capaian_penilaian'] as $t)$db->exec('CREATE TABLE ${dbName}.'.$t.' LIKE '.DB_NAME.'.'.$t);$db->exec('USE ${dbName}');$db->exec(file_get_contents('database/2026_10_22_ujian_harian.sql'));$db->exec(file_get_contents('database/2026_10_22_ujian_harian.sql'));$db->exec(file_get_contents('database/2026_10_22_remedial_ujian.sql'));$db->exec(file_get_contents('database/2026_10_22_remedial_ujian.sql'));
 $db->exec("INSERT INTO roles(id,nama_role) VALUES(1,'Admin'),(2,'Guru'),(3,'Siswa');INSERT INTO users(id,username,password,role_id,is_active) VALUES(1,'GuruA','',2,1),(2,'GuruB','',2,1),(3,'SiswaA','',3,1),(4,'SiswaB','',3,1),(5,'SiswaC','',3,1);INSERT INTO guru(id,user_id,nama_lengkap) VALUES(1,1,'Guru A'),(2,2,'Guru B');INSERT INTO kelas(id,nama_kelas,tingkat,jurusan) VALUES(1,'X A','X','TKJ'),(2,'X B','X','TKJ');INSERT INTO mapel(id,kode_mapel,nama_mapel) VALUES(1,'ALG','Algoritma');INSERT INTO pengajaran(id,guru_id,kelas_id,mapel_id,semester,tahun_ajaran) VALUES(1,1,1,1,'Ganjil','2026/2027'),(2,1,2,1,'Ganjil','2026/2027'),(3,2,2,1,'Ganjil','2026/2027');INSERT INTO siswa(id,user_id,kelas_id,nama_lengkap,nisn) VALUES(1,3,1,'Siswa A','001'),(2,4,1,'Siswa B','002'),(3,5,2,'Siswa C','003');INSERT INTO komponen_penilaian(pengajaran_id,nama_komponen,bobot,urutan) VALUES(1,'Ulangan Harian',100,1)");`);
 dir=fs.mkdtempSync(path.join(os.tmpdir(),'lms-uh-test-'));
 const router=path.join(dir,'router.php');
 fs.writeFileSync(router,`<?php chdir(${JSON.stringify(cwd.replaceAll('\\','/'))});require 'config/database.php';$db=Database::getInstance();$db->exec('USE ${dbName}');require 'config/session.php';$actor=(int)($_SERVER['HTTP_X_TEST_ACTOR']??1);$_SESSION=array_merge($_SESSION,['user_id'=>$actor,'role_id'=>$actor<=2?2:3,'csrf_token'=>'uh-test']);$route=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$_SERVER['SCRIPT_NAME']=$route;if(!in_array($route,['/guru/ujian.php','/guru/ujian_excel.php','/guru/ujian_nilai.php','/guru/ujian_action.php','/guru/ujian_hasil.php','/guru/ujian_hasil_export.php','/guru/ujian_berkas.php','/guru/rekap_nilai_guru.php','/siswa/ujian.php','/siswa/ujian_kerjakan.php','/siswa/ujian_action.php'],true)){http_response_code(404);exit;}require getcwd().$route;`);
 const port=18500+Math.floor(Math.random()*1000);let logs='';server=spawn(php,['-d','session.save_path='+dir,'-d','upload_tmp_dir='+dir,'-d','upload_max_filesize=10M','-d','post_max_size=12M','-S',`127.0.0.1:${port}`,router],{cwd,stdio:['ignore','pipe','pipe']});server.stderr.on('data',b=>logs+=b);server.stdout.on('data',b=>logs+=b);
 const base=`http://127.0.0.1:${port}`;
 const cookies=new Map();
 async function request(route,actor=1,fields=null){
  const body=fields===null?null:fields instanceof FormData?fields:new URLSearchParams({csrf_token:'uh-test',...fields});
  const encoded=body===null?null:new Response(body);const payload=encoded?Buffer.from(await encoded.arrayBuffer()):null;
  const headers={'X-Test-Actor':String(actor),Cookie:cookies.get(actor)||'',Connection:'close'};if(payload){headers['Content-Type']=encoded.headers.get('content-type');headers['Content-Length']=String(payload.length);}
  return await new Promise((resolve,reject)=>{const req=require('node:http').request(base+route,{method:payload?'POST':'GET',headers,agent:false},res=>{const chunks=[];res.on('data',b=>chunks.push(b));res.on('end',()=>{try{const bytes=Buffer.concat(chunks),text=bytes.toString('utf8');if(res.headers['set-cookie'])cookies.set(actor,res.headers['set-cookie'][0].split(';')[0]);assert.doesNotMatch(text,/Fatal error:|Warning:|Parse error:/);resolve({status:res.statusCode,text,bytes,headers:new Headers(Object.entries(res.headers).flatMap(([k,v])=>Array.isArray(v)?v.map(x=>[k,x]):[[k,String(v)]])),json:()=>{assert.match(text,/^\s*[{[]/,text);return JSON.parse(text)}});}catch(e){reject(e)}})});req.on('error',e=>reject(new Error(route+' '+logs.slice(-2500),{cause:e})));if(payload)req.write(payload);req.end();});
 }
 for(let i=0;i<50;i++){try{await request('/siswa/ujian.php',3);break;}catch(e){if(i===49)throw Error(logs);await new Promise(r=>setTimeout(r,50));}}
 const now=new Date(),fmt=d=>new Date(d.getTime()+7*3600*1000).toISOString().slice(0,16);
 const common={pengajaran_id:'1',nama_ujian:'UH Algoritma',jenis_ujian:'Kuis',durasi_menit:'60',waktu_mulai:fmt(new Date(now-60000)),waktu_selesai:fmt(new Date(+now+3600000)),metode:'cbt',penerima:'pilihan','siswa_ids[]':'1',instruksi:'Kerjakan dengan teliti'};
 const action=(a,fields,actor=1)=>request('/guru/ujian_action.php?action='+a,actor,fields);
 let denied=await action('create_ujian',{...common,csrf_token:'wrong'});assert.equal(denied.status,403);
 let r=await action('create_ujian',common);assert.equal(r.status,200,r.text);const exam=r.json().ujian_id;
 r=await request('/siswa/ujian.php',3);assert.match(r.text,/UH Algoritma/);r=await request('/siswa/ujian.php',4);assert.doesNotMatch(r.text,/UH Algoritma/);r=await request('/siswa/ujian_kerjakan.php?id='+exam,4);assert.equal(r.status,404);
 for(const kind of ['pg','esai','campuran']){r=await request('/guru/ujian_excel.php?action=template&jenis='+kind);assert.equal(r.status,200);assert.equal(r.bytes.subarray(0,2).toString(),'PK');assert.match(r.headers.get('content-disposition'),/\.xlsx/);}
 const soalForm=(file='ujian-soal-bobot.xlsx')=>{const f=new FormData();f.set('csrf_token','uh-test');f.set('ujian_id',exam);f.set('file_excel',new Blob([fs.readFileSync(path.join(__dirname,'fixtures',file))]),file);return f};
 r=await request('/guru/ujian_excel.php?action=import',1,soalForm());assert.equal(r.status,302,r.text);r=await request('/guru/ujian.php');assert.ok(r.text.includes('2 soal berhasil diimpor'),r.text.match(/Impor dibatalkan[^<]+/)?.[0]||'Pesan sukses impor tidak ditemukan');
 r=await request('/guru/ujian_excel.php?action=import',1,soalForm('ujian-soal-legacy.xls'));assert.equal(r.status,302);r=await request('/guru/ujian.php');assert.match(r.text,/2 soal identik dilewati/);
 r=await request('/guru/ujian_excel.php?action=import',2,soalForm());assert.equal(r.status,302);r=await request('/guru/ujian.php',2);assert.match(r.text,/bukan milik Anda/);
 const ids=JSON.parse(sql(`$db->exec('USE ${dbName}');echo json_encode($db->query('SELECT id FROM soal_ujian ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));`));
 r=await request('/siswa/ujian_kerjakan.php?id='+exam,3);assert.equal(r.status,200,r.text);const session=Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT id FROM sesi_ujian LIMIT 1')->fetchColumn();`));
 r=await request('/guru/ujian_excel.php?action=import',1,soalForm());assert.equal(r.status,302);r=await request('/guru/ujian.php');assert.match(r.text,/sudah memiliki peserta/);
 const studentAction=(a,fields,actor=3)=>request('/siswa/ujian_action.php?action='+a,actor,fields);
 r=await studentAction('save_jawaban',{sesi_id:session,soal_id:ids[0],tipe:'PG',jawaban:'A'},4);assert.equal(r.status,403);
 r=await studentAction('save_jawaban',{sesi_id:session,soal_id:ids[0],tipe:'PG',jawaban:'A'});assert.equal(r.json().status,'success');
 r=await studentAction('save_jawaban',{sesi_id:session,soal_id:ids[1],tipe:'ESAI',jawaban:'Jawaban uraian'});assert.equal(r.json().status,'success');
 r=await studentAction('finish_ujian',{sesi_id:session});assert.equal(r.json().status,'success',r.text);
 r=await studentAction('save_jawaban',{sesi_id:session,soal_id:ids[0],tipe:'PG',jawaban:'B'});assert.equal(r.status,403);
 assert.equal(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT nilai_total IS NULL FROM nilai_ujian LIMIT 1')->fetchColumn();`),'1');
 r=await request('/siswa/ujian.php?status=selesai',3);assert.match(r.text,/Menunggu penilaian guru/);
 r=await action('update_ujian',{...common,ujian_id:exam,penerima:'kelas'});assert.equal(r.status,422,r.text);
 const answer=sql(`$db->exec('USE ${dbName}');echo $db->query("SELECT id FROM jawaban_siswa WHERE soal_id=${ids[1]}")->fetchColumn();`);
 r=await action('nilai_esai',{jawaban_id:answer,nilai_esai:'60'},2);assert.equal(r.status,422);
 r=await action('nilai_esai',{jawaban_id:answer,nilai_esai:'60'});assert.equal(r.status,200,r.text);assert.equal(r.json().nilai_total,70);
 const auto=()=>Number(sql(`$db->exec('USE ${dbName}');require 'includes/penilaian_override.php';echo po_automatic($db,1,1)['Ulangan Harian'];`));assert.equal(auto(),70);
 r=await action('salin_ujian',{ujian_id:exam,'pengajaran_ids[]':'2'});assert.equal(r.status,200,r.text);r=await request('/siswa/ujian.php',5);assert.match(r.text,/UH Algoritma/);
 r=await action('salin_ujian',{ujian_id:exam,'pengajaran_ids[]':'3'});assert.equal(r.status,422);
 r=await action('create_ujian',{...common,nama_ujian:'UH Berkas',metode:'berkas',penerima:'kelas',instruksi:'Tulis jawaban dan unggah PDF'});assert.equal(r.status,200,r.text);const fileExam=r.json().ujian_id;
 r=await request('/siswa/ujian_kerjakan.php?id='+fileExam,3);assert.match(r.text,/Tulis jawaban dan unggah PDF/);
 let form=new FormData();form.set('csrf_token','uh-test');form.set('jawaban',new Blob(['<?php echo 1;?>'],{type:'application/x-php'}),'evil.php');r=await request('/siswa/ujian_kerjakan.php?id='+fileExam,3,form);assert.match(r.text,/Format jawaban harus/);
 form=new FormData();form.set('csrf_token','uh-test');form.set('jawaban',new Blob(['%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF'],{type:'application/pdf'}),'jawaban.pdf');r=await request('/siswa/ujian_kerjakan.php?id='+fileExam,3,form);assert.equal(r.status,302,r.text);
 const stored=sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT nama_file FROM ujian_harian_berkas LIMIT 1')->fetchColumn();`);uploaded.push(path.join(cwd,'storage/ujian',stored));assert.match(stored,/^[a-f0-9]{40}\.pdf$/);
 r=await request('/guru/ujian_berkas.php?ujian_id='+fileExam+'&siswa_id=1',4);assert.equal(r.status,404);r=await request('/guru/ujian_berkas.php?ujian_id='+fileExam+'&siswa_id=1',3);assert.equal(r.status,200);assert.match(r.text,/%PDF/);
 assert.equal(auto(),70);r=await action('nilai_berkas',{ujian_id:fileExam,siswa_id:'1',nilai:'90',catatan:'Baik'});assert.equal(r.status,200,r.text);assert.equal(auto(),80);
 assert.equal(Number(sql(`$db->exec('USE ${dbName}');require 'includes/rekap_akademik_matrix.php';$matrix=ram_data($db,1,'2026/2027','Ganjil');echo $matrix['students'][0]['subjects'][1]['Ulangan Harian'];`)),80);
 r=await request('/guru/ujian.php?rekap_pengajaran=1');assert.match(r.text,/Rekap Ulangan Harian/);assert.match(r.text,/UH 1/);assert.match(r.text,/UH 2/);assert.match(r.text,/Bukan penerima/);
 for(const script of [...r.text.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)])new vm.Script(script[1]);
 r=await request('/guru/ujian_hasil.php?ujian_id='+fileExam);assert.equal(r.status,200,r.text);r=await request('/guru/rekap_nilai_guru.php?pengajaran_id=1');assert.equal(r.status,200,r.text);assert.match(r.text,/80\.00/);
 r=await request('/guru/ujian_hasil_export.php?format=excel&ujian_id='+exam);assert.equal(r.status,200,r.text);assert.match(r.text,/<Workbook/);assert.doesNotMatch(r.text,/Siswa B/);
 r=await request('/guru/ujian_hasil_export.php?format=pdf&ujian_id='+exam);assert.equal(r.status,200);assert.match(r.text,/%PDF/);
 r=await request('/guru/ujian_hasil.php?ujian_id='+exam);for(const script of [...r.text.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)])new vm.Script(script[1]);
 // A graded file cannot be replaced, and an expired UH without a submission counts as zero.
 r=await request('/siswa/ujian_kerjakan.php?id='+fileExam,3,form);assert.match(r.text,/sudah dikirim/);
 sql(`$db->exec('USE ${dbName}');$db->exec("INSERT INTO ujian(pengajaran_id,nama_ujian,jenis_ujian,durasi_menit,waktu_mulai,waktu_selesai,acak_soal) VALUES(1,'Terlewat','Kuis',60,DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_SUB(NOW(),INTERVAL 1 DAY),0)");`);assert.ok(Math.abs(auto()-160/3)<.001);

 // Import targets exactly one UH, previews before saving, and never overwrites grades.
 const missed=Number(sql(`$db->exec('USE ${dbName}');echo $db->query("SELECT id FROM ujian WHERE nama_ujian='Terlewat'")->fetchColumn();`));
 const gradeRoute=(id,panel='impor')=>'/guru/ujian_nilai.php?pengajaran_id=1&ujian_id='+id+'&panel='+panel;
 r=await request(gradeRoute(missed)+'&format=template');assert.equal(r.status,200);assert.equal(r.bytes.subarray(0,2).toString(),'PK');
 const workbook=Buffer.from(sql(`$db->exec('USE ${dbName}');require 'includes/ujian_nilai_tools.php';$ctx=uhn_context($db,1,1,${missed});$rows=[['Pengajaran ID','1'],['UH ID','${missed}'],['ID Siswa','NIS','NISN','Nama Siswa','UH '.$ctx['selected']['nomor'].' [ID:${missed}]']];foreach($ctx['students'] as $i=>$s)$rows[]=[(string)$s['id'],(string)($s['nis']??''),(string)($s['nisn']??''),$s['nama_lengkap'],$i===0?55:65];echo base64_encode(ew_workbook([['name'=>'Nilai UH','rows'=>$rows,'header'=>2]]));`),'base64');
 const importForm=()=>{const f=new FormData();f.set('csrf_token','uh-test');f.set('action','preview');f.set('file_nilai',new Blob([workbook],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}),'nilai.xlsx');return f};
 r=await request(gradeRoute(missed),1,importForm());assert.equal(r.status,200,r.text);assert.match(r.text,/2 nilai siap diimpor/);let token=r.text.match(/name="import_token" value="([^"]+)"/)[1];
 r=await request(gradeRoute(missed),1,{action:'import',import_token:'wrong'});assert.match(r.text,/Pratinjau tidak valid/);
 r=await request(gradeRoute(missed),1,{action:'import',import_token:token});assert.equal(r.status,302,r.text);
 const savedGrade=(id,sid=1)=>Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT nilai_total FROM nilai_ujian WHERE ujian_id=${id} AND siswa_id=${sid}')->fetchColumn();`));assert.equal(savedGrade(missed),55);assert.equal(savedGrade(missed,2),65);
 r=await request(gradeRoute(missed),1,importForm());assert.match(r.text,/0 nilai siap diimpor/);assert.match(r.text,/Dilewati: nilai sudah tersimpan/);
 r=await request(gradeRoute(missed),2);assert.equal(r.status,403);
 sql(`$db->exec('USE ${dbName}');require 'includes/ujian_nilai_tools.php';$ctx=uhn_context($db,1,1,${missed});$rows=[['Pengajaran ID','1'],['UH ID','${missed}'],['ID Siswa','NIS','NISN','Nama Siswa','UH '.$ctx['selected']['nomor'].' [ID:${missed}]'],['1','','','Siswa A','101']];$p=uhn_preview($ctx,$rows);if(!$p[0]['invalid'])throw new RuntimeException('Bad score accepted');$rows[0][1]='2';try{uhn_preview($ctx,$rows);throw new Exception('Foreign template accepted');}catch(RuntimeException $e){}$plan=['guru'=>1,'user'=>1,'pid'=>1,'exam'=>${missed},'expires'=>time()-1,'rows'=>[]];try{uhn_import($db,1,1,1,${missed},$plan);throw new Exception('Expired preview accepted');}catch(RuntimeException $e){}`);
 // Manual remedial preserves the initial score, uses the highest attempt, and supports undo.
 sql(`$db->exec('USE ${dbName}');$db->exec("UPDATE ujian SET waktu_mulai=DATE_SUB(NOW(),INTERVAL 2 DAY),waktu_selesai=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE id=${exam}");`);
 r=await request(gradeRoute(exam,'remedial'),1,{action:'remedial','nilai_remedial[1]':'80','catatan_remedial[1]':'Perbaikan'});assert.equal(r.status,302,r.text);assert.equal(savedGrade(exam),80);
 const firstAttempt=Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT id FROM remedial_ujian ORDER BY id LIMIT 1')->fetchColumn();`));
 r=await request(gradeRoute(exam,'remedial'),1,{action:'remedial','nilai_remedial[1]':'60','catatan_remedial[1]':'Lebih rendah'});assert.equal(r.status,302);assert.equal(savedGrade(exam),80);
 r=await request(gradeRoute(exam,'remedial'),1,{action:'cancel',remedial_id:firstAttempt,alasan:'Salah input'});assert.equal(r.status,302);assert.equal(savedGrade(exam),70);
 r=await request(gradeRoute(exam,'remedial'),1,{action:'cancel',remedial_id:firstAttempt,alasan:'Ulang'});assert.match(r.text,/sudah dibatalkan/);
 r=await request(gradeRoute(exam,'remedial'),1,{action:'remedial','nilai_remedial[2]':'90','catatan_remedial[2]':'Asing'});assert.match(r.text,/bukan penerima/);assert.equal(savedGrade(exam),70);
 // A remedial exam is visible only to selected students and updates its original UH.
 const childFields={action:'create',judul:'Remedial Algoritma',metode:'cbt',instruksi:'Perbaiki jawaban',waktu_mulai:common.waktu_mulai,waktu_selesai:common.waktu_selesai,'siswa_ids[]':'1'};
 r=await request(gradeRoute(exam,'remedial'),1,childFields);assert.equal(r.status,302,r.text);
 const child=Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT ujian_id FROM ujian_harian_remedial ORDER BY ujian_id DESC LIMIT 1')->fetchColumn();`));
 r=await request('/siswa/ujian.php',3);assert.match(r.text,/Remedial Algoritma/);r=await request('/siswa/ujian.php',4);assert.doesNotMatch(r.text,/Remedial Algoritma/);
 r=await request(gradeRoute(child));assert.equal(r.status,403);
 r=await request('/siswa/ujian_kerjakan.php?id='+child,3);assert.equal(r.status,200);
 const childSession=Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT id FROM sesi_ujian WHERE ujian_id=${child}')->fetchColumn();`));const childQuestions=JSON.parse(sql(`$db->exec('USE ${dbName}');echo json_encode($db->query('SELECT id FROM soal_ujian WHERE ujian_id=${child} ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));`));
 await studentAction('save_jawaban',{sesi_id:childSession,soal_id:childQuestions[0],tipe:'PG',jawaban:'A'});await studentAction('save_jawaban',{sesi_id:childSession,soal_id:childQuestions[1],tipe:'ESAI',jawaban:'Perbaikan lengkap'});r=await studentAction('finish_ujian',{sesi_id:childSession});assert.equal(r.json().status,'success');assert.equal(savedGrade(exam),70);
 const childAnswer=Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT id FROM jawaban_siswa WHERE soal_id=${childQuestions[1]}')->fetchColumn();`));r=await action('nilai_esai',{jawaban_id:childAnswer,nilai_esai:'90'});assert.equal(r.status,200,r.text);assert.equal(savedGrade(exam),92.5);assert.ok(Math.abs(auto()-(92.5+90+55)/3)<.001);
 const autoAttempt=Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT id FROM remedial_ujian WHERE sumber_ujian_id=${child}')->fetchColumn();`));r=await request(gradeRoute(exam,'remedial'),1,{action:'cancel',remedial_id:autoAttempt,alasan:'Dibatalkan'});assert.equal(r.status,302,r.text);assert.equal(savedGrade(exam),70);
 r=await action('nilai_esai',{jawaban_id:childAnswer,nilai_esai:'80'});assert.equal(r.status,200,r.text);assert.equal(savedGrade(exam),70);
 r=await action('delete_ujian',{ujian_id:child});assert.equal(r.status,422);
 r=await action('create_ujian',{...common,penerima:'kelas',nama_ujian:'UH berikutnya'});assert.equal(r.status,200);const nextExam=r.json().ujian_id;assert.equal(Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT nomor FROM ujian_harian_pengaturan WHERE ujian_id=${nextExam}')->fetchColumn();`)),4);

 // A stale preview rolls back all new rows instead of partially importing.
 sql(`$db->exec('USE ${dbName}');require 'includes/ujian_nilai_tools.php';$db->exec("UPDATE ujian SET waktu_mulai=DATE_SUB(NOW(),INTERVAL 2 DAY),waktu_selesai=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE id=${nextExam}");uhn_write($db,${nextExam},2,88);$plan=['guru'=>1,'user'=>1,'pid'=>1,'exam'=>${nextExam},'expires'=>time()+900,'rows'=>[['sid'=>1,'score'=>77,'ready'=>true,'invalid'=>false],['sid'=>2,'score'=>66,'ready'=>true,'invalid'=>false]]];try{uhn_import($db,1,1,1,${nextExam},$plan);throw new Exception('Stale preview accepted');}catch(RuntimeException $e){}if($db->query('SELECT COUNT(*) FROM nilai_ujian WHERE ujian_id=${nextExam} AND siswa_id=1')->fetchColumn()!=0)throw new Exception('Partial import');if($db->query('SELECT nilai_total FROM nilai_ujian WHERE ujian_id=${nextExam} AND siswa_id=2')->fetchColumn()!=88)throw new Exception('Existing grade overwritten');`);
 // File-based remedial also updates the original grade while retaining its history.
 r=await request(gradeRoute(missed,'remedial'),1,{...childFields,judul:'Remedial Berkas',metode:'berkas'});assert.equal(r.status,302,r.text);
 const fileChild=Number(sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT ujian_id FROM ujian_harian_remedial ORDER BY ujian_id DESC LIMIT 1')->fetchColumn();`));
 r=await request('/siswa/ujian_kerjakan.php?id='+fileChild,3,form);assert.equal(r.status,302,r.text);const childFile=sql(`$db->exec('USE ${dbName}');echo $db->query('SELECT nama_file FROM ujian_harian_berkas WHERE ujian_id=${fileChild}')->fetchColumn();`);uploaded.push(path.join(cwd,'storage/ujian',childFile));
 r=await action('nilai_berkas',{ujian_id:fileChild,siswa_id:'1',nilai:'85',catatan:'Tuntas'});assert.equal(r.status,200,r.text);assert.equal(savedGrade(missed),85);
 r=await request('/siswa/ujian.php?status=selesai',4);assert.match(r.text,/Dinilai guru/);assert.match(r.text,/65\.00/);
 r=await request('/guru/ujian.php?rekap_pengajaran=1');assert.match(r.text,/Impor nilai/);assert.match(r.text,/Remedial \/ Undo/);
 r=await request(gradeRoute(exam,'remedial'));if(process.env.UH_PREVIEW_HTML)fs.writeFileSync(process.env.UH_PREVIEW_HTML,r.text);assert.match(r.text,/Riwayat \/ Undo/);assert.match(r.text,/Dibatalkan/);
 }finally{if(server&&server.exitCode===null){server.kill();await new Promise(resolve=>server.once('exit',resolve));}for(const file of uploaded)if(fs.existsSync(file))fs.unlinkSync(file);sql(`$db->exec('DROP DATABASE IF EXISTS ${dbName}');`);if(dir)try{fs.rmSync(dir,{recursive:true,force:true,maxRetries:5,retryDelay:200})}catch(e){console.log('Temporary session folder retained:',dir);}}
});

