const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('History filters validate dates, limits, identifiers and structured malicious input',()=>{
 const php=String.raw`<?php
require 'includes/monitoring_kbm_history.php';
$today='2026-11-27';$default=kbm_history_filters([],$today);
if($default['tanggal_mulai']!=='2026-11-01'||$default['tanggal_selesai']!==$today)throw new RuntimeException('Wrong server date defaults');
foreach([
 ['tanggal_mulai'=>'2026-11-28','tanggal_selesai'=>'2026-11-27'],
 ['tanggal_mulai'=>'2026-02-30'],['tanggal_selesai'=>'2026-11-28'],
 ['tanggal_mulai'=>'2024-01-01'],['tanggal_mulai'=>['2026-11-01']],
 ['sesi'=>'malicious'],['kelas'=>'1 OR 1=1'],['guru'=>'-1'],['status'=>'hapus'],
 ['per_halaman'=>'100000'],['halaman'=>'0'],['halaman'=>['1']],
] as $input){$rejected=false;try{kbm_history_filters($input,$today);}catch(InvalidArgumentException $e){$rejected=true;}if(!$rejected)throw new RuntimeException('Invalid filter accepted');}
$f=kbm_history_filters(['tanggal_mulai'=>'2024-02-29','tanggal_selesai'=>'2025-02-28','halaman'=>'2','per_halaman'=>'10','status'=>'tanpa_checkin'],$today);
if($f['tanggal_mulai']!=='2024-02-29'||$f['halaman']!=='2')throw new RuntimeException('Valid leap-year range rejected');
echo 'History filter checks passed';`;
 const r=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{input:php,cwd:path.join(__dirname,'..'),encoding:'utf8'});
 assert.equal(r.status,0,r.stdout+r.stderr);assert.equal(r.stderr,'');assert.match(r.stdout,/History filter checks passed/);
});
