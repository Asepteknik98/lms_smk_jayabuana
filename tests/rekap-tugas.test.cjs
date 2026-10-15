// Run: node --test tests/rekap-tugas.test.cjs
// Database fixtures are connection-local temporary tables; school records stay untouched.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

test('CSV follows the selected task and preserves zero scores', () => {
    const php=String.raw`<?php
require 'config/helper.php';require 'includes/rekap_tugas_section.php';
$view=['pid'=>1,'visibleTasks'=>[['id'=>2,'nomor'=>2,'pertemuan_ke'=>2,'judul'=>'Tugas Terpilih']],
'data'=>['info'=>['nama_mapel'=>'Matematika','nama_kelas'=>'X-A','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil'],
'students'=>[['nis'=>'001','nisn'=>'00001','nama_lengkap'=>'=FORMULA','cells'=>[2=>['nilai'=>0,'label'=>'Tidak Mengerjakan']],
'rata_rata'=>0,'jumlah_dihitung'=>1,'belum_dinilai'=>0]]]];
rt_inline_export($view);
`;
    const result=spawnSync('C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),input:php,encoding:'utf8'});
    assert.equal(result.status,0,result.stderr);
    assert.match(result.stdout,/sep=;/);assert.match(result.stdout,/Tugas 2 Tugas Terpilih - Nilai/);
    assert.match(result.stdout,/'=FORMULA;0;/);assert.match(result.stdout,/Rata-rata semua tugas selesai/);
});

test('Task recap: averages, authorization, status, answer preservation and rollback', () => {
    const php = String.raw`<?php
require 'config/database.php';require 'includes/rekap_tugas_module.php';require 'includes/rekap_tugas_tools.php';require 'includes/penilaian_override.php';
$db=Database::getInstance();
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function reject($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid mutation accepted');}
$tables=[
 'users'=>'id INT PRIMARY KEY,role_id INT,is_active INT',
 'guru'=>'id INT PRIMARY KEY,user_id INT',
 'kelas'=>'id INT PRIMARY KEY,nama_kelas VARCHAR(30)',
 'mapel'=>'id INT PRIMARY KEY,nama_mapel VARCHAR(30)',
 'pengajaran'=>'id INT PRIMARY KEY,guru_id INT,kelas_id INT,mapel_id INT,tahun_ajaran VARCHAR(20),semester VARCHAR(20)',
 'siswa'=>'id INT PRIMARY KEY,kelas_id INT,nis VARCHAR(30),nisn VARCHAR(30),nama_lengkap VARCHAR(100)',
 'tugas'=>'id INT PRIMARY KEY,pengajaran_id INT,judul VARCHAR(100),pertemuan_ke INT,deadline DATETIME,metode_pengumpulan VARCHAR(20)',
 'pengumpulan_tugas'=>'id INT AUTO_INCREMENT PRIMARY KEY,tugas_id INT,siswa_id INT,nilai DECIMAL(5,2),catatan_guru TEXT,file_tugas VARCHAR(255),catatan TEXT,dikumpulkan_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY(tugas_id,siswa_id)',
 'rekap_tugas_status'=>'tugas_id INT,siswa_id INT,guru_id INT,status VARCHAR(30),catatan VARCHAR(1000),updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(tugas_id,siswa_id)',
 'log_aktivitas'=>'user_id INT,aktivitas VARCHAR(255),ip_address VARCHAR(45),user_agent TEXT',
 'remedial_tugas'=>'id INT AUTO_INCREMENT PRIMARY KEY,tugas_id INT,siswa_id INT,guru_id INT,nilai_awal DECIMAL(5,2),nilai_sebelum DECIMAL(5,2),nilai_remedial DECIMAL(5,2),nilai_akhir DECIMAL(5,2),catatan VARCHAR(1000),dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
 'nilai_tugas_manual'=>'pengajaran_id INT,siswa_id INT,nilai DECIMAL(5,2)',
 'ujian'=>'id INT,pengajaran_id INT,jenis_ujian VARCHAR(20),waktu_selesai DATETIME',
 'nilai_ujian'=>'ujian_id INT,siswa_id INT,nilai_total DECIMAL(5,2)',
 'sesi_absensi'=>'id INT,pengajaran_id INT,status VARCHAR(20)',
 'detail_absensi'=>'sesi_absensi_id INT,siswa_id INT,status VARCHAR(20)'
];
foreach($tables as $table=>$schema)$db->exec("CREATE TEMPORARY TABLE $table ($schema) ENGINE=InnoDB");
$db->exec("INSERT INTO users VALUES(1,2,1),(2,2,1),(3,3,1);INSERT INTO guru VALUES(1,1),(2,2);INSERT INTO kelas VALUES(1,'X-A'),(2,'X-B');INSERT INTO mapel VALUES(1,'Matematika');INSERT INTO pengajaran VALUES(1,1,1,1,'2026/2027','Ganjil'),(2,2,2,1,'2026/2027','Ganjil');INSERT INTO siswa VALUES(1,1,'001','00001','Siswa A'),(2,1,'002','00002','Siswa B'),(3,2,'003','00003','Siswa C');
INSERT INTO tugas VALUES(1,1,'Dinilai',1,DATE_SUB(NOW(),INTERVAL 1 DAY),'online'),(2,1,'Tidak Dikerjakan',2,DATE_SUB(NOW(),INTERVAL 1 DAY),'online'),(3,1,'Belum Dinilai',3,DATE_SUB(NOW(),INTERVAL 1 DAY),'online'),(4,1,'Terbuka',4,DATE_ADD(NOW(),INTERVAL 1 DAY),'online'),(5,1,'Offline',5,DATE_SUB(NOW(),INTERVAL 1 DAY),'offline'),(6,2,'Guru Lain',1,DATE_SUB(NOW(),INTERVAL 1 DAY),'online');
INSERT INTO pengumpulan_tugas(tugas_id,siswa_id,nilai,file_tugas,catatan,dikumpulkan_pada) VALUES(1,1,80,'jawaban.pdf','Jawaban asli','2026-01-01 08:00:00'),(3,1,NULL,NULL,'Esai asli','2026-01-01 08:00:00'),(4,1,100,NULL,NULL,'2026-01-01 08:00:00');");
$student=rt_data($db,1,1,true)['students'][0];
check(abs($student['rata_rata']-26.67)<.01 && $student['jumlah_dihitung']===3,'Expired missing tasks must contribute 0');
check($student['cells'][3]['nilai']===null && $student['belum_dinilai']===1,'Ungraded task must stay pending');
check(!$student['cells'][4]['dihitung'],'Open task included in average');
check(count(rt_data($db,1,1,false)['students'])===2,'Read-only legacy fallback failed');
reject(fn()=>rt_data($db,2,1,true));
$save=fn($sid,$tid,$status,$score='',$note='')=>rt_save($db,1,1,1,$sid,$tid,$status,$score,$note);
reject(fn()=>$save(3,1,'mengerjakan','70'));reject(fn()=>$save(1,6,'mengerjakan','70'));
reject(fn()=>rt_save($db,1,3,1,1,1,'mengerjakan','70',''));
foreach(['','-1','101','NaN','INF','1e999'] as $value)reject(fn()=>$save(1,1,'mengerjakan',$value));
reject(fn()=>$save(1,1,'invalid'));reject(fn()=>$save(1,1,'mengerjakan','80',str_repeat('a',1001)));
reject(fn()=>$save(2,4,'tidak_mengerjakan'));reject(fn()=>$save(1,1,'tidak_mengerjakan'));reject(fn()=>$save(1,1,'belum_dinilai'));
$beforeGrade=po_automatic($db,1,1)['Tugas Harian'];
$save(1,5,'mengerjakan','100','Tugas tertulis');check(rt_data($db,1,1,true)['students'][0]['rata_rata']===60.0,'Offline score not counted');
check(abs($beforeGrade-20)<.01 && abs(po_automatic($db,1,1)['Tugas Harian']-45)<.01,'Saved recap grade did not flow to the existing automatic Tugas Harian component');
// The existing grade formula counts ungraded work as 0, unlike the new provisional history average.
check(po_automatic($db,1,1)['Tugas Harian']!==rt_data($db,1,1,true)['students'][0]['rata_rata'],'Expected formula discrepancy was not detected');
$save(1,2,'belum_dinilai','','Diterima guru');$student=rt_data($db,1,1,true)['students'][0];
check($student['rata_rata']===90.0 && $student['belum_dinilai']===2,'Manual pending not respected');
check((int)$db->query('SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id=2')->fetchColumn()===0,'Status created a fake submission');
$save(1,2,'tidak_mengerjakan');check(rt_data($db,1,1,true)['students'][0]['rata_rata']===60.0,'Missing task did not contribute 0');
$db->exec('INSERT INTO pengumpulan_tugas(tugas_id,siswa_id,nilai) VALUES(2,1,95)');
$student=rt_data($db,1,1,true)['students'][0];check($student['cells'][2]['status']==='mengerjakan' && $student['cells'][2]['nilai']===95.0,'Later submission hidden by manual absence');
$save(1,1,'mengerjakan','0','Nol yang sah');$original=$db->query('SELECT * FROM pengumpulan_tugas WHERE tugas_id=1 AND siswa_id=1')->fetch();
check($original['file_tugas']==='jawaban.pdf' && $original['catatan']==='Jawaban asli' && $original['dikumpulkan_pada']==='2026-01-01 08:00:00','Answer/file/timestamp changed');
check(rt_data($db,1,1,true)['students'][0]['cells'][1]['status']==='mengerjakan','Legitimate zero confused with absence');
$save(1,1,'otomatis');check((float)$db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=1 AND siswa_id=1')->fetchColumn()===0.0,'Reset deleted existing score');
// Inject failure in the connection-local audit table; both mutations must roll back.
$db->exec('UPDATE log_aktivitas SET user_id=-1');
$db->exec('ALTER TABLE log_aktivitas ADD CONSTRAINT fixture_no_audit CHECK (user_id<0)');
reject(fn()=>$save(1,1,'mengerjakan','90'));
check((float)$db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=1 AND siswa_id=1')->fetchColumn()===0.0,'Grade did not roll back');
check((int)$db->query('SELECT COUNT(*) FROM rekap_tugas_status WHERE tugas_id=1 AND siswa_id=1')->fetchColumn()===0,'Status did not roll back');
// Import template round-trip; all writes still use connection-local tables.
$db->exec('ALTER TABLE log_aktivitas DROP CONSTRAINT fixture_no_audit');
$db->exec('UPDATE tugas SET pertemuan_ke=9 WHERE id=1');
$importData=rt_data($db,1,1,true);
check((int)$importData['tasks'][0]['id']===1 && $importData['tasks'][0]['nomor']===1,'Task numbers depend on meeting numbers');
$xml=rt_import_template(['pid'=>1,'data'=>$importData,'visibleTasks'=>$importData['tasks']]);
$file=tempnam(sys_get_temp_dir(),'rt_xls_');file_put_contents($file,$xml);
try{$sheet=ni_read_excel($file);}finally{unlink($file);}
$rows=$sheet['rows'];check($rows[3][1]==='001','Template lost leading zero in NIS');
$rows[4][4]='80';$rows[4][6]='0';$rows[4][8]='55,5';
$parsed=rt_parse_import($importData,$rows,1);
check(count($parsed)===3 && $parsed[1]['score']===0.0,'Import lost zero/blank distinction');
$plan=['guru'=>1,'user'=>1,'pid'=>1,'expires'=>time()+900,'rows'=>$parsed];
check(rt_import_apply($db,1,1,1,$plan)===3,'Class import failed');
check((float)$db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=3 AND siswa_id=2')->fetchColumn()===0.0,'Imported zero missing');
$wrong=$rows;$wrong[0][1]='2';reject(fn()=>rt_parse_import($importData,$wrong,1));
$wrong=$rows;$wrong[2][4]='Tugas 1 [ID:6]';reject(fn()=>rt_parse_import($importData,$wrong,1));
$wrong=$rows;$wrong[]=$wrong[4];reject(fn()=>rt_parse_import($importData,$wrong,1));
$wrong=$rows;$wrong[4][4]='101';$invalid=rt_parse_import($importData,$wrong,1);check($invalid[0]['invalid'],'Bad score accepted by preview');
$badPlan=$plan;$badPlan['rows']=$invalid;reject(fn()=>rt_import_apply($db,1,1,1,$badPlan));
$badPlan=$plan;$badPlan['expires']=time()-1;reject(fn()=>rt_import_apply($db,1,1,1,$badPlan));
reject(fn()=>rt_import_apply($db,2,2,1,$plan));
$again=rt_parse_import(rt_data($db,1,1,true),$rows,1);check(!array_filter($again,fn($r)=>$r['ready']),'Import would overwrite stored grades');

// Multiple remedial attempts keep the first original and the best effective grade.
check(rt_remedial_save($db,1,1,1,2,[2=>'72'],[2=>'Remedial pertama'])===1,'First remedial failed');
rt_remedial_save($db,1,1,1,2,[2=>'65'],[2=>'Lebih rendah']);
rt_remedial_save($db,1,1,1,2,[2=>'85'],[2=>'Perbaikan kedua']);
$cell=rt_data($db,1,1,true)['students'][1]['cells'][2];
check($cell['nilai']===85.0 && (float)$cell['nilai_awal']===0.0 && count($cell['remedial'])===3,'Remedial lost original/history/highest score');
rt_save($db,1,1,1,2,2,'mengerjakan','10','Edit setelah remedial');
check((float)$db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=2 AND siswa_id=2')->fetchColumn()===85.0,'Manual edit lowered remedial effective score');
check(abs(po_automatic($db,1,2)['Tugas Harian']-55.125)<.001,'Remedial not connected to existing automatic task grade');
rt_remedial_save($db,1,1,1,1,[2=>'95'],[2=>'Perbaikan tugas pertama']);
$cell=rt_data($db,1,1,true)['students'][1]['cells'][1];check((float)$cell['nilai_awal']===80.0 && $cell['nilai']===95.0,'Stored initial grade lost');
reject(fn()=>rt_remedial_save($db,1,1,1,3,[1=>'80'],[]));
reject(fn()=>rt_remedial_save($db,1,1,1,4,[2=>'80'],[]));
reject(fn()=>rt_remedial_save($db,2,2,1,2,[2=>'90'],[]));
reject(fn()=>rt_remedial_save($db,1,1,1,2,[2=>'101'],[]));
$beforeCount=(int)$db->query('SELECT COUNT(*) FROM remedial_tugas')->fetchColumn();
reject(fn()=>rt_remedial_save($db,1,1,1,1,[2=>'99',3=>'90'],[]));
check((int)$db->query('SELECT COUNT(*) FROM remedial_tugas')->fetchColumn()===$beforeCount,'Remedial batch partially saved history');
check((float)$db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=1 AND siswa_id=2')->fetchColumn()===95.0,'Remedial batch partially changed score');

// Reject stale preview and roll back any earlier cells in the same class import.
$staleData=rt_data($db,1,1,true);$staleRows=$sheet['rows'];$staleRows[3][6]='70';$staleRows[4][7]='60';
$stalePlan=['guru'=>1,'user'=>1,'pid'=>1,'expires'=>time()+900,'rows'=>rt_parse_import($staleData,$staleRows,1)];
rt_save($db,1,1,1,2,4,'mengerjakan','99','Diisi setelah pratinjau');
reject(fn()=>rt_import_apply($db,1,1,1,$stalePlan));
check($db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=3 AND siswa_id=1')->fetchColumn()===null,'Stale import was partially saved');

// Render the whole task page using only connection-local fixtures.
$db->exec("ALTER TABLE tugas ADD deskripsi TEXT NULL,ADD jenis_tugas VARCHAR(20) DEFAULT 'portofolio',ADD file_lampiran VARCHAR(255) NULL");
$db->exec("ALTER TABLE guru ADD nama_lengkap VARCHAR(100) DEFAULT 'Guru Fixture'");
$db->exec('CREATE TEMPORARY TABLE pertanyaan_tugas(id INT,tugas_id INT,pertanyaan TEXT,urutan INT)');
$db->exec('CREATE TEMPORARY TABLE opsi_tugas(id INT,pertanyaan_id INT,label_opsi VARCHAR(10),teks_opsi TEXT,is_benar INT)');
ini_set('session.save_path',sys_get_temp_dir());require 'config/session.php';
$_SESSION['user_id']=1;$_SESSION['role_id']=2;$_SESSION['username']='Guru Fixture';$_SESSION['milad_announcement_shown']=true;
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/lms/guru/tugas.php';$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];
$_GET=['page'=>2,'rekap_pengajaran'=>1];$_POST=[];
set_error_handler(function($severity,$message,$file,$line){if(error_reporting() & $severity)throw new ErrorException($message,0,$severity,$file,$line);});
ob_start();require 'guru/tugas.php';$html=ob_get_clean();
check(substr_count($html,'id="page-content-wrapper"')===1,'Recap must not create a second page wrapper');
check(substr_count($html,'id="rekap-tugas"')===1,'Recap rendered more than once');
check(strpos($html,'id="rekap-tugas"')>strpos($html,'Menampilkan maksimal'),'Recap must appear below cards AND pagination');
preg_match('~<nav class="navbar task-page-toolbar[\s\S]*?</nav>~',$html,$toolbar);
check(strpos($toolbar[0],'Rekap Tugas')===false,'Separate recap toolbar button remains');
check(strpos($html,'colspan="5">Nilai per Tugas')!==false,'One column per actual task is missing');
check(strpos($html,'<th title="Pertemuan')===false && strpos($html,'>Tugas 1</th>')!==false,'Meeting headings remain in task recap');
check(strpos($html,'id="modalImporTugas"')!==false && strpos($html,'id="modalRemedialTugas"')!==false,'Import/remedial menus are missing');
$options=json_decode(html_entity_decode(rt_inline_options($rekap_tugas_view['data']['students'][0],$rekap_tugas_view['data']['tasks']),ENT_QUOTES,'UTF-8'),true);
check(str_starts_with($options[0]['judul'],'Tugas 1'),'Status modal still uses meeting labels');
check(strpos($html,'name="rekap_pengajaran"')!==false && strpos($html,'name="rekap_mode"')!==false,'Independent recap filters missing');
check(strpos($html,'value="rekap_tugas_save"')!==false,'Recap save action is not isolated');
check(strpos(rt_inline_url(['page'=>2,'rekap_pengajaran'=>1]),'#rekap-tugas')!==false,'Save does not return to embedded recap');
$meetingView=rt_inline_prepare($db,1,['rekap_pengajaran'=>1,'rekap_mode'=>'tugas','rekap_tugas_id'=>2]);
check(count($meetingView['visibleTasks'])===1 && (int)$meetingView['visibleTasks'][0]['id']===2,'Per-meeting filter includes other tasks');
ob_start();rt_inline_render($meetingView);$meetingHtml=ob_get_clean();
check(strpos($meetingHtml,'<th>Catatan</th>')!==false && strpos($meetingHtml,'Ubah Status')!==false,'Per-meeting detail/status table missing');
$savedContext=rt_inline_context(['page'=>2,'pengajaran_id'=>1,'pertemuan'=>3,'rekap_pengajaran'=>1,'untrusted'=>'bad']);
check($savedContext['page']===2 && !isset($savedContext['untrusted']),'List state not preserved or arbitrary query accepted');
$preview=str_replace('<head>','<head><base href="file:///C:/xampp/htdocs/lms/guru/">',$html);
file_put_contents(sys_get_temp_dir().'/lms_rekap_tugas_inline_preview.html',$preview);
restore_error_handler();
// The embedded POST must be intercepted before the task create/edit/delete handler.
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['action'=>'rekap_tugas_save','csrf_token'=>$_SESSION['csrf_token'],'rekap_pengajaran'=>1,'rekap_kelas'=>1,'page'=>2,'siswa_id'=>1,'tugas_id'=>1,'status'=>'mengerjakan','nilai'=>'77','catatan'=>'Melalui rekap di bawah daftar'];
register_shutdown_function(function()use($db){
 check((float)$db->query('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=1 AND siswa_id=1')->fetchColumn()===77.0,'Embedded POST did not save the score');
 check(isset($_SESSION['rekap_tugas_success']),'Embedded POST did not set success feedback');
 session_destroy();echo "PASS task recap integration\n";
});
rt_inline_prepare($db,1,$_GET);
`;
    const result=spawnSync('C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),input:php,encoding:'utf8'});
    assert.equal(result.status,0,result.stdout+result.stderr);
    assert.match(result.stdout,/PASS task recap integration/);
});

test('Embedded recap modal switches tasks and filters without mixing task-list parameters', () => {
    const vm=require('node:vm');
    const callbacks={},elements={};
    const ids=['modalStatusTugas','statusTugas','nilaiStatusTugas','statusSiswaId','statusTugasId','statusNamaSiswa','statusJudulTugas','catatanStatusTugas','lihatJawabanTugas','statusPilihanTugas','formFilterRekapTugas','rekapKelas','rekapPengajaran','rekapMode','rekapTugasFilter'];
    ids.forEach(id=>{elements[id]={value:'',options:[],addEventListener(event,fn){callbacks[id+':'+event]=fn;},replaceChildren(){this.options=[];},appendChild(option){this.options.push(option);},requestSubmit(){this.submitted=true;}};});
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/rekap-tugas.js'),'utf8'),{
        document:{getElementById:id=>elements[id],createElement:()=>({}),querySelectorAll:()=>[]},encodeURIComponent
    });
    const options=[{tugasId:10,judul:'P1 · Esai',status:'mengerjakan',nilai:'85.75',catatan:'Baik',pengumpulan:'7',metode:'online'},
        {tugasId:11,judul:'P1 · Praktik',status:'belum_dinilai',nilai:'',catatan:'Diterima',pengumpulan:'0',metode:'offline'}];
    callbacks['modalStatusTugas:show.bs.modal']({relatedTarget:{dataset:{siswaId:'3',nama:'Siswa C',tugasList:JSON.stringify(options)}}});
    assert.equal(elements.statusPilihanTugas.options.length,2);
    assert.equal(elements.statusTugasId.value,10);assert.equal(elements.nilaiStatusTugas.value,'85.75');
    elements.statusPilihanTugas.value='11';callbacks['statusPilihanTugas:change']();
    assert.equal(elements.statusTugasId.value,11);assert.equal(elements.catatanStatusTugas.value,'Diterima');
    assert.equal(elements.nilaiStatusTugas.required,false);assert.equal(elements.lihatJawabanTugas.hidden,true);
    callbacks['rekapKelas:change']();assert.equal(elements.rekapPengajaran.disabled,true);assert.equal(elements.formFilterRekapTugas.submitted,true);
    elements.rekapMode.value='tugas';callbacks['rekapMode:change']();assert.equal(elements.rekapTugasFilter.disabled,false);
});

test('Modal requires scores only when marked working and searches student history', () => {
    const vm=require('node:vm');
    const callbacks={};
    const elements={};
    for(const id of ['modalStatusTugas','statusTugas','nilaiStatusTugas','statusSiswaId','statusTugasId','statusNamaSiswa','statusJudulTugas','catatanStatusTugas','lihatJawabanTugas','cariSiswaRekap','rekapSearchEmpty']){
        elements[id]={value:'',addEventListener:(event,fn)=>{callbacks[id+':'+event]=fn;}};
    }
    const rows=[{dataset:{search:'andi 001'}},{dataset:{search:'budi 002'}}];
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/rekap-tugas.js'),'utf8'),{
        document:{getElementById:id=>elements[id],querySelectorAll:()=>rows},encodeURIComponent
    });
    callbacks['modalStatusTugas:show.bs.modal']({relatedTarget:{dataset:{siswaId:'1',tugasId:'2',nama:'Andi <b>',judul:'Tugas A',status:'mengerjakan',nilai:'0.00',catatan:'Catatan',pengumpulan:'1',metode:'online'}}});
    assert.equal(elements.nilaiStatusTugas.required,true);assert.equal(elements.nilaiStatusTugas.value,'0.00');
    assert.equal(elements.statusNamaSiswa.textContent,'Andi <b>');assert.equal(elements.lihatJawabanTugas.href,'tugas_penilaian.php?tugas_id=2');
    elements.statusTugas.value='tidak_mengerjakan';callbacks['statusTugas:change']();
    assert.equal(elements.nilaiStatusTugas.required,false);assert.equal(elements.nilaiStatusTugas.readOnly,true);assert.equal(elements.nilaiStatusTugas.value,'0');
    elements.statusTugas.value='belum_dinilai';callbacks['statusTugas:change']();assert.equal(elements.nilaiStatusTugas.value,'');
    elements.cariSiswaRekap.value=' BUDI ';callbacks['cariSiswaRekap:input']();assert.equal(rows[0].hidden,true);assert.equal(rows[1].hidden,false);assert.equal(elements.rekapSearchEmpty.hidden,true);
    elements.cariSiswaRekap.value='tidak ada';callbacks['cariSiswaRekap:input']();assert.equal(elements.rekapSearchEmpty.hidden,false);
});

test('Remedial form preserves zero, blocks pending work, shows history and requires an entry', () => {
    const vm=require('node:vm');
    class Element {
        constructor(tag){this.tag=tag;this.children=[];this.style={};this.events={};this.value='';this.dataset={};}
        appendChild(child){this.children.push(child);return child;}
        replaceChildren(){this.children=[];}
        addEventListener(event,callback){this.events[event]=callback;}
        setAttribute(name,value){this[name]=value;}
        setCustomValidity(message){this.validation=message;}
        reportValidity(){this.reported=true;}
        all(){return this.children.flatMap(child=>[child,...child.all()]);}
        querySelectorAll(){return this.all().filter(child=>child.tag==='input' && child.type==='number');}
        querySelector(){return this.querySelectorAll().find(child=>!child.disabled);}
    }
    const ids=['rekapRemedialData','remedialPilihanTugas','remedialDaftarSiswa','simpanRemedialTugas','modalRemedialTugas','formRemedialTugas','modalImporTugas'];
    const elements=Object.fromEntries(ids.map(id=>[id,new Element('div')]));
    elements.rekapRemedialData.textContent=JSON.stringify({ready:true,tasks:[{id:1,selesai:1},{id:2,selesai:0}],students:[
        {id:1,nama_lengkap:'Siswa <A>',cells:{1:{nilai_awal:0,nilai:0,remedial:[{dibuat_pada:'2026-10-15',nilai_remedial:0,nilai_akhir:0,catatan:'<teks>'}]}}},
        {id:2,nama_lengkap:'Siswa B',cells:{1:{nilai_awal:null,nilai:null,remedial:[]}}}
    ]});
    elements.remedialPilihanTugas.value='1';elements.modalImporTugas.dataset.open='1';
    let ready,opened=false;
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/rekap-tugas-tools.js'),'utf8'),{
        document:{getElementById:id=>elements[id],createElement:tag=>new Element(tag),addEventListener:(_,callback)=>{ready=callback;}},
        window:{bootstrap:{Modal:{getOrCreateInstance:()=>({show(){opened=true;}})}}}
    });
    elements.modalRemedialTugas.events['show.bs.modal']();
    assert.equal(elements.remedialDaftarSiswa.children.length,2);
    const first=elements.remedialDaftarSiswa.children[0];
    assert.equal(first.children[0].textContent,'Siswa <A>');assert.equal(first.children[1].textContent,'0');
    assert.equal(first.children[3].children[0].disabled,false);
    assert.equal(elements.remedialDaftarSiswa.children[1].children[3].children[0].disabled,true);
    assert.equal(first.children[4].children[1].children[0].textContent,'1 riwayat remedial');
    let prevented=false;elements.formRemedialTugas.events.submit({preventDefault(){prevented=true;}});assert.equal(prevented,true);
    first.children[3].children[0].value='0';prevented=false;elements.formRemedialTugas.events.submit({preventDefault(){prevented=true;}});assert.equal(prevented,false);
    elements.remedialPilihanTugas.value='2';elements.remedialPilihanTugas.events.change();assert.equal(elements.simpanRemedialTugas.disabled,true);
    ready();assert.equal(opened,true);
});
