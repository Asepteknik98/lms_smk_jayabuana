const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Report periods use calendar days, Monday weeks and months, capped at server today',()=>{
 const php=String.raw`<?php
require 'includes/monitoring_kbm_report.php';
function check($input,$today,$start,$end,$day){$r=kbm_report_period($input,$today);if($r['tanggal_mulai']!==$start||$r['tanggal_selesai']!==$end||$r['hari']!==$day)throw new RuntimeException(json_encode($r));}
check([],'2026-10-18','2026-10-18','2026-10-18','Minggu');
check(['periode_laporan'=>'mingguan','tanggal'=>'2026-10-14'],'2026-10-18','2026-10-12','2026-10-18','Rabu');
check(['periode_laporan'=>'mingguan','tanggal'=>'2026-10-19'],'2026-10-21','2026-10-19','2026-10-21','Senin');
check(['periode_laporan'=>'mingguan','tanggal'=>'2027-01-01'],'2027-01-31','2026-12-28','2027-01-03','Jumat');
check(['periode_laporan'=>'bulanan','tanggal'=>'2024-02-15'],'2026-10-18','2024-02-01','2024-02-29','Kamis');
check(['periode_laporan'=>'bulanan','tanggal'=>'2026-10-02'],'2026-10-18','2026-10-01','2026-10-18','Jumat');
foreach([['tanggal'=>'2026-10-19'],['tanggal'=>'2026-02-30'],['tanggal'=>[]],['periode_laporan'=>[]],['periode_laporan'=>'tahunan']] as $bad){try{kbm_report_period($bad,'2026-10-18');throw new RuntimeException('Invalid input accepted');}catch(InvalidArgumentException $e){}}
echo 'Calendar periods and invalid inputs passed';`;
 const r=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:php,cwd:path.join(__dirname,'..'),encoding:'utf8'});
 assert.equal(r.status,0,r.stdout+r.stderr);assert.equal(r.stderr,'');assert.match(r.stdout,/Calendar periods/);
});
