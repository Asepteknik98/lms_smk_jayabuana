const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Excel streams large row sets, preserves literal hostile text and blank timestamps',()=>{
 const php=String.raw`<?php
require 'includes/monitoring_kbm_export.php';require 'includes/nilai_excel_reader.php';
$texts=['=HYPERLINK("https://example.invalid","x")','+123','-123','@SUM(A1)','Nama & <kelas>'];
$rows=(function()use($texts){for($i=0;$i<2000;$i++)yield [$i+1,$texts[$i%5],null,'2026-10-16T07:00:00',1.0104166666667];})();
$xml=implode('',iterator_to_array(ra_excel_parts('Riwayat',['No','Teks','Kosong','Waktu','Durasi'],$rows,['types'=>['Number','String','DateTime','DateTime','Number'],'formats'=>[2=>'DateTime',3=>'DateTime',4=>'Duration'],'widths'=>[45,190,145,145,120],'freeze'=>true]),false));
$doc=ni_xml($xml);$xp=new DOMXPath($doc);$xp->registerNamespace('s','urn:schemas-microsoft-com:office:spreadsheet');
if($xp->query('//s:Row')->length!==2002||$xp->query('//@s:Formula')->length!==0)throw new RuntimeException('Rows/formula unsafe');
foreach($texts as $i=>$text){$cells=$xp->query('//s:Row['.($i+3).']/s:Cell');if($cells[1]->textContent!==$text||$cells[1]->firstChild->getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet','Type')!=='String')throw new RuntimeException('Text interpreted');if($cells[2]->hasChildNodes())throw new RuntimeException('Missing time not blank');}
$file=tempnam(sys_get_temp_dir(),'kbm-book-');try{file_put_contents($file,$xml);$book=ni_read_excel($file);if(count($book['rows'])!==2002)throw new RuntimeException('Workbook cannot roundtrip');}finally{unlink($file);}
if(kbm_export_filter_input(['kelas'=>'1','halaman'=>'2','password'=>'secret'])!==['kelas'=>'1'])throw new RuntimeException('Extra query fields exported');
echo 'Excel safety and 2000-row roundtrip passed';`;
 const r=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:php,cwd:path.join(__dirname,'..'),encoding:'utf8'});
 assert.equal(r.status,0,r.stdout+r.stderr);assert.equal(r.stderr,'');assert.match(r.stdout,/2000-row roundtrip passed/);
});
