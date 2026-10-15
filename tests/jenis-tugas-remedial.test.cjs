const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const os = require('node:os');
const {spawn} = require('node:child_process');

test('Six remedial types validate questions, video URLs and submission formats', () => {
    const php = String.raw`<?php
require 'includes/jenis_tugas_remedial.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function reject($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid input accepted');}
check(count(pr_types())===6,'Expected six types');
reject(fn()=>pr_config('unexpected',[]));
foreach(['javascript:alert(1)','https://youtube.com.evil.test/watch?v=x','https://example.com','https://youtube.com@evil.test/x'] as $url)reject(fn()=>pr_config('youtube',['youtube_url'=>$url]));
foreach(['https://youtu.be/example','https://www.youtube.com/watch?v=example','https://m.youtube.com/shorts/example'] as $url)check(pr_config('youtube',['youtube_url'=>$url])['youtube_url']===$url,'Valid YouTube URL rejected');
reject(fn()=>pr_config('pilihan_ganda',['soal'=>[['teks'=>'Q','opsi'=>['a','b','c','d'],'kunci'=>'4']]]));
reject(fn()=>pr_config('pilihan_ganda',['soal'=>[['teks'=>'Q','opsi'=>['a','b','','d'],'kunci'=>'0']]]));
reject(fn()=>pr_config('esai',['soal'=>[['teks'=>'  ']]]));
$config=pr_config('esai',['soal'=>[['teks'=>'Soal satu'],['teks'=>'Soal dua']]]);
$task=['jenis'=>'esai','konfigurasi'=>json_encode($config)];
reject(fn()=>pr_answer($task,['jawaban_soal'=>['Jawab satu']]));
reject(fn()=>pr_answer($task,['jawaban_soal'=>[['nested'],'dua']]));
$answer=pr_answer($task,['jawaban_soal'=>['Satu','Dua']]);
check(str_contains($answer,'1. Soal satu')&&str_contains($answer,'2. Soal dua')&&str_contains($answer,'Jawaban: Dua'),'Essay mapping failed');
reject(fn()=>pr_answer(['jenis'=>'video'],['jawaban'=>'javascript:alert(1)']));
check(pr_answer(['jenis'=>'video'],['jawaban'=>'https://drive.google.com/file/d/example'])==='https://drive.google.com/file/d/example','Video work URL rejected');
check(pr_answer(['jenis'=>'merangkum'],['jawaban'=>' Rangkuman '])==='Rangkuman','Summary text changed');
check(pr_answer(['jenis'=>'portofolio'],['jawaban'=>''])==='','Optional portfolio note became required');
reject(fn()=>pr_upload(['error'=>UPLOAD_ERR_INI_SIZE]));
reject(fn()=>pr_upload(['error'=>UPLOAD_ERR_OK,'size'=>11*1024*1024,'tmp_name'=>'fake','name'=>'test.pdf']));
echo 'PASS types';
`;
    const result = spawnSync('C:/xampp/php/php.exe', [], {cwd:path.join(__dirname,'..'), input:php, encoding:'utf8'});
    assert.equal(result.status, 0, result.stdout + result.stderr);
    assert.match(result.stdout, /PASS types/);
});

test('Portfolio upload accepts a real image and rejects disguised executable content', async () => {
    const folder=fs.mkdtempSync(path.join(os.tmpdir(),'lms-remedial-upload-'));
    const helper=path.resolve(__dirname,'../includes/jenis_tugas_remedial.php').replaceAll('\\','/');
    const storage=path.resolve(__dirname,'../storage/remedial').replaceAll('\\','/');
    fs.writeFileSync(path.join(folder,'index.php'),`<?php
require '${helper}';header('Content-Type: application/json');$file=[];
try{$file=pr_upload($_FILES['file_portofolio']??[]);echo json_encode(['ok'=>true,'name'=>$file['nama_file'],'stored'=>is_file('${storage}/'.$file['file_jawaban'])]);}
catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false]);}
finally{if($file)unlink('${storage}/'.$file['file_jawaban']);}
`);
    const port=23000+Math.floor(Math.random()*10000);
    const server=spawn('C:/xampp/php/php.exe',['-d',`upload_tmp_dir=${folder}`,'-S',`127.0.0.1:${port}`,'-t',folder],{stdio:['ignore','pipe','pipe']});
    try {
        await new Promise((resolve,reject)=>{
            const timer=setTimeout(()=>reject(Error('Test upload server did not start')),5000);
            server.stderr.on('data',chunk=>{if(String(chunk).includes('Development Server')){clearTimeout(timer);resolve();}});
            server.on('error',error=>{clearTimeout(timer);reject(error);});
        });
        const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jU1sAAAAASUVORK5CYII=','base64');
        const form=new FormData();form.append('file_portofolio',new Blob([png],{type:'image/png'}),'portofolio.png');
        const accepted=await fetch(`http://127.0.0.1:${port}`,{method:'POST',body:form});
        const acceptedBody=await accepted.text();assert.equal(accepted.status,200,acceptedBody);assert.doesNotMatch(acceptedBody,/<br/,acceptedBody);assert.deepEqual(JSON.parse(acceptedBody),{ok:true,name:'portofolio.png',stored:true});
        const forged=new FormData();forged.append('file_portofolio',new Blob(['<?php echo "bad"; ?>'],{type:'image/png'}),'jawaban.png');
        const rejected=await fetch(`http://127.0.0.1:${port}`,{method:'POST',body:forged});
        assert.equal(rejected.status,422);assert.deepEqual(await rejected.json(),{ok:false});
    } finally {
        const closed=new Promise(resolve=>server.once('exit',resolve));server.kill();await closed;
        fs.unlinkSync(path.join(folder,'index.php'));fs.rmdirSync(folder);
    }
});
