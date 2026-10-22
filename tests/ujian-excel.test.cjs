const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Native UH workbooks, XLS/XLSX round trips, PG/essay validation and padded grade headers',()=>{
const script=String.raw`<?php
require 'includes/ujian_excel.php';require 'includes/ujian_nilai_tools.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function reject($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid workbook accepted');}
$tmp=tempnam(sys_get_temp_dir(),'uh_excel_');
try{
 foreach(['pg','esai','campuran'] as $kind){
  $xlsx=ue_template($kind);check(str_starts_with($xlsx,"PK\x03\x04"),'Not a real XLSX');file_put_contents($tmp,$xlsx);
  $book=ni_read_excel($tmp);check($book['sheet']==='Soal','Wrong first sheet');check($book['rows'][0]===UE_HEADERS,'Changed question header');check(count($book['rows'])===101,'Template rows missing');reject(fn()=>ue_parse($book['rows']));
  $examples=ni_read_excel($tmp,2);check(count(ue_parse($examples['rows']))===2,'PG and essay examples missing');
 }
 $xlsx=ue_parse(ni_read_excel('tests/fixtures/ujian-soal-roundtrip.xlsx')['rows']);
 $xls=ue_parse(ni_read_excel('tests/fixtures/ujian-soal-legacy.xls')['rows']);
 check(ue_parse(ni_read_excel('tests/fixtures/ujian-soal-bobot.xlsx')['rows'])===$xlsx,'Blank and comma whole weights changed grading');
 check($xlsx===$xls&&count($xlsx)===2,'Excel roundtrip lost questions');check($xlsx[0][0]==='PG'&&$xlsx[1][0]==='ESAI','Question type mismatch');
 $rows=[UE_HEADERS,[1,'PG','Question','A','B','C','D','','A',1],[2,'ESAI','Essay','','','','','','',3]];
 check(count(ue_parse($rows))===2,'Mixed import rejected');
 foreach(['','1','01','1.00','1,0'," 1\u{00A0}",'100.00'] as $value){$weighted=$rows;$weighted[1][9]=$value;$parsed=ue_parse($weighted);check($parsed[0][8]===($value==='100.00'?100:1),'Excel whole-number format rejected');}
 foreach(['0','-1','101','1.5','abc'] as $value){$weighted=$rows;$weighted[1][9]=$value;reject(fn()=>ue_parse($weighted));}
 $blank=[UE_HEADERS,[1,'PG','','','','','','','',1],[2,'ESAI','','','','','','','',1]];reject(fn()=>ue_parse($blank));
 $bad=$rows;$bad[1][8]='E';reject(fn()=>ue_parse($bad));$bad=$rows;$bad[1][9]='0';reject(fn()=>ue_parse($bad));$bad=$rows;$bad[2][3]='Option';reject(fn()=>ue_parse($bad));$bad=$rows;$bad[2][9]='101';reject(fn()=>ue_parse($bad));$bad=$rows;$bad[0][3]='Changed';reject(fn()=>ue_parse($bad));
 $ctx=['pid'=>1,'selected'=>['id'=>4,'nomor'=>3,'nama_ujian'=>'Ulangan 3','selesai'=>1],'info'=>['nama_kelas'=>'X A','nama_mapel'=>'Algoritma'],'students'=>[['id'=>1,'nis'=>'001','nisn'=>'00001','nama_lengkap'=>'Siswa Satu','nilai'=>null,'pending'=>0,'status'=>null]]];
 file_put_contents($tmp,uhn_template_xlsx($ctx));$book=ni_read_excel($tmp);check($book['sheet']==='Nilai UH','Wrong grade sheet');check($book['rows'][3][1]==='001'&&$book['rows'][3][2]==='00001','Leading zeros lost');
 $gradeRows=$book['rows'];$gradeRows[0]=array_pad($gradeRows[0],5,'');$gradeRows[1]=array_pad($gradeRows[1],5,'');$gradeRows[3][4]='88,5';$plan=uhn_preview($ctx,$gradeRows);check($plan[0]['ready']&&$plan[0]['score']===88.5,'Excel padded metadata rejected');
 $gradeRows[3][4]='101';check(uhn_preview($ctx,$gradeRows)[0]['invalid'],'Invalid score accepted');
 $gradeRows[0][1]='2';reject(fn()=>uhn_preview($ctx,$gradeRows));
 // Text that resembles an Excel formula stays text in the generated workbook.
 file_put_contents($tmp,ew_workbook([['name'=>'Safe','rows'=>[['Header'],['=1+2']]]]));check(ni_read_excel($tmp)['rows'][1][0]==='=1+2','Text was turned into a formula');
 echo 'ok';
}finally{unlink($tmp);}
`;
const r=spawnSync('C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),input:script,encoding:'utf8'});
assert.equal(r.status,0,r.stdout+r.stderr);assert.equal(r.stderr,'');assert.equal(r.stdout,'ok');
});
