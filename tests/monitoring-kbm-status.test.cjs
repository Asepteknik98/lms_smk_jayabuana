const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Central KBM status: exact boundaries, blocks, breaks, calendar and unfinished reports',()=>{
 const code=String.raw`<?php
require 'includes/monitoring_kbm_status.php';
function meeting($id,$start,$end,$in=null,$out=null,$date='2026-10-16') {return ['jadwal_id'=>$id,'pertemuan_id'=>$id,'tanggal'=>$date,'rencana_mulai'=>$date.' '.$start,'rencana_selesai'=>$date.' '.$end,'checkin_efektif'=>$in===null?null:$date.' '.$in,'checkout_efektif'=>$out===null?null:$date.' '.$out];}
$a=meeting(1,'07:00:00','08:20:00');$b=meeting(2,'08:40:00','10:00:00');$c=meeting(3,'10:00:00','11:20:00');$open=meeting(1,'07:00:00','08:20:00','07:01:00');
$context=['breaks'=>[['mulai'=>'2026-10-16 08:20:00','selesai'=>'2026-10-16 08:40:00']]];
$result=[];
foreach(['06:59:59','07:00:00','07:39:59','07:40:00','08:19:59','08:20:00'] as $t)$result['meeting_'.$t]=kbm_meeting_status($a,'2026-10-16 '.$t);
$result['open_before_end']=kbm_meeting_status($open,'2026-10-16 08:19:59');
$result['open_at_end']=kbm_meeting_status($open,'2026-10-16 08:20:00');
$result['checkout']=kbm_meeting_status(meeting(1,'07:00:00','08:20:00','07:01:00','07:50:00'),'2026-10-16 07:50:00');
foreach(['06:59:59','07:00:00','08:19:59','08:20:00','08:39:59','08:40:00','09:59:59','10:00:00','11:20:00'] as $t)$result['class_'.$t]=kbm_class_status([$open,$b,$c],$context,[$open],'2026-10-16 '.$t);
$result['during_current']=kbm_class_status([$open,$b,$c],$context,[$open],'2026-10-16 07:30:00');
$result['next_day']=kbm_class_status([],[],[$open],'2026-10-17 07:10:00');
$tomorrow=meeting(4,'07:00:00','08:20:00',null,null,'2026-10-17');
$result['next_day_running']=kbm_class_status([$tomorrow],[],[$open],'2026-10-17 07:10:00');
$result['empty']=kbm_class_status([],[],[],'2026-10-16 07:10:00');
$result['holiday']=kbm_class_status([],['holidays'=>['Libur sekolah']],[$open],'2026-10-16 07:10:00');
$result['incomplete']=kbm_class_status([],['incomplete'=>true],[],'2026-10-16 07:10:00');
$result['same_block_checkout']=kbm_class_status([meeting(1,'07:00:00','08:20:00','07:01:00','07:50:00')],[],[],'2026-10-16 07:55:00');
$gap=meeting(5,'09:00:00','10:00:00');$result['gap']=kbm_class_status([$a,$gap],[],[],'2026-10-16 08:30:00');
$cancelled=$a+['checkin_at'=>'2026-10-16 07:01:00'];$result['cancelled']=kbm_meeting_status($cancelled,'2026-10-16 07:30:00');
$afternoon=meeting(8,'13:00:00','14:20:00');$result['afternoon']=kbm_class_status([$afternoon],[],[],'2026-10-16 13:00:00');
$result['next_day_before']=kbm_class_status([$tomorrow],[],[$open],'2026-10-17 06:59:59');
echo json_encode($result);`;
 const r=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),input:code,encoding:'utf8'});
 assert.equal(r.status,0,r.stderr+r.stdout);assert.equal(r.stderr,'');const s=JSON.parse(r.stdout);
 assert.equal(s['meeting_06:59:59'].label,'Belum waktunya KBM');
 for(const t of ['07:00:00','07:39:59','07:40:00','08:19:59'])assert.equal(s['meeting_'+t].color,'merah');
 assert.equal(s['meeting_08:20:00'].label,'Tidak tercatat check-in');
 assert.equal(s.open_before_end.color,'hijau');assert.equal(s.open_before_end.warning,'');
 assert.equal(s.open_at_end.color,'hijau');assert.equal(s.open_at_end.warning,'Jadwal berakhir, belum check-out');
 assert.equal(s.checkout.color,'kuning');assert.equal(s.checkout.label,'KBM selesai');
 for(const t of ['08:20:00','08:39:59']){assert.equal(s['class_'+t].primary.label,'Istirahat');assert.equal(s['class_'+t].primary.color,'kuning');assert.equal(s['class_'+t].warnings.length,1);}
 assert.equal(s['class_08:19:59'].primary.color,'hijau');
 for(const t of ['08:40:00','09:59:59','10:00:00']){assert.equal(s['class_'+t].primary.color,'merah');assert.equal(s['class_'+t].warnings.length,1);}
 assert.equal(s['class_08:40:00'].meeting.jadwal_id,2);assert.equal(s['class_10:00:00'].meeting.jadwal_id,3);
 assert.equal(s['class_11:20:00'].primary.label,'KBM selesai');assert.equal(s['class_11:20:00'].primary.color,'kuning');assert.equal(s['class_11:20:00'].warnings.length,1);
 assert.equal(s.during_current.primary.color,'hijau');assert.equal(s.during_current.warnings.length,0);
 assert.equal(s.next_day.primary.color,'kuning');assert.equal(s.next_day.warnings.length,1);
 assert.equal(s.next_day_running.primary.color,'merah');assert.equal(s.next_day_running.warnings.length,1);
 assert.equal(s.empty.primary.label,'Tidak ada jadwal KBM');assert.match(s.holiday.primary.label,/Hari tanpa KBM: Libur sekolah/);
 assert.equal(s.incomplete.primary.color,'kuning');assert.equal(s.same_block_checkout.primary.color,'kuning');
 assert.equal(s.gap.primary.label,'Belum waktunya KBM');assert.equal(s.cancelled.color,'merah');assert.equal(s.afternoon.primary.color,'merah');
 assert.equal(s.next_day_before.primary.color,'kuning');
});
