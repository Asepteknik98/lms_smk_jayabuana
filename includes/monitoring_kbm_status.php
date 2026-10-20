<?php
require_once __DIR__.'/monitoring_kbm_admin.php';

/** Satu definisi status untuk seluruh role. Tidak menyimpan warna ke database. */
function kbm_status_value(string $code,string $color,string $label,string $warning=''): array {
    return ['code'=>$code,'color'=>$color,'label'=>$label,'warning'=>$warning,
        'bootstrap'=>['kuning'=>'warning','merah'=>'danger','hijau'=>'success','abu'=>'secondary'][$color],
        'icon'=>['kuning'=>'clock','merah'=>'circle-exclamation','hijau'=>'circle-check','abu'=>'clipboard-list'][$color]];
}

function kbm_meeting_status(array $meeting,string $now): array {
    if(!empty($meeting['dibatalkan']))return kbm_status_value('dibatalkan','kuning','Dibatalkan','Catatan asli tetap tersimpan di riwayat.');
    $task=$meeting['guru_hanya_tugas']??null;if(is_string($task))$task=json_decode($task,true);
    if(is_array($task)&&empty($meeting['koreksi_id']))return kbm_status_value('guru_hanya_tugas','abu','Guru hanya memberikan tugas','Laporan ketua kelas: '.($task['nama']??'').' ? '.($task['pada']??'').' WIB.');
    $absence=$meeting['guru_tidak_masuk']??null;if(is_string($absence))$absence=json_decode($absence,true);
    if(is_array($absence)&&empty($meeting['koreksi_id']))return kbm_status_value('guru_tidak_masuk','merah','Guru tidak masuk','Laporan ketua kelas: '.($absence['nama']??'').' · '.($absence['pada']??'').' WIB.');
    // array_key_exists penting: NULL pada koreksi berarti pembatalan waktu asli.
    $in=array_key_exists('checkin_efektif',$meeting)?$meeting['checkin_efektif']:($meeting['checkin_at']??null);
    $out=array_key_exists('checkout_efektif',$meeting)?$meeting['checkout_efektif']:($meeting['checkout_at']??null);
    if($out!==null && $out<=$now) return kbm_status_value('selesai','kuning','KBM selesai');
    $manual=($meeting['sumber']??'')==='ketua_kelas';
    if($in!==null && $in<=$now) return kbm_status_value('checkin','hijau','Guru sudah check-in',$manual?($meeting['tanggal']<substr($now,0,10)?'Catatan hari sebelumnya belum check-out':''):($now>=$meeting['rencana_selesai']?'Jadwal berakhir, belum check-out':''));
    if($manual)return $meeting['tanggal']===substr($now,0,10)?kbm_status_value('belum_checkin','merah','Guru belum check-in'):kbm_status_value('tanpa_checkin','kuning','Tidak tercatat check-in');
    if($now<$meeting['rencana_mulai']) return kbm_status_value('belum_mulai','kuning','Belum waktunya KBM');
    if($now<$meeting['rencana_selesai']) return kbm_status_value('belum_checkin','merah','Guru belum check-in');
    return kbm_status_value('tanpa_checkin','kuning','Tidak tercatat check-in');
}

/** Pendamping SQL untuk filter/pagination; label/warna tetap dari kbm_meeting_status. */
function kbm_meeting_status_sql(): string {
    return "CASE WHEN r.dibatalkan='1' THEN 'dibatalkan'
        WHEN r.guru_hanya_tugas IS NOT NULL AND r.guru_hanya_tugas<>'null' AND r.koreksi_id IS NULL THEN 'guru_hanya_tugas'
        WHEN r.guru_tidak_masuk IS NOT NULL AND r.guru_tidak_masuk<>'null' AND r.koreksi_id IS NULL THEN 'guru_tidak_masuk'
        WHEN r.checkout_efektif IS NOT NULL AND r.checkout_efektif<=clock.now_server THEN 'selesai'
        WHEN r.checkin_efektif IS NOT NULL AND r.checkin_efektif<=clock.now_server THEN 'checkin'
        WHEN r.sumber='ketua_kelas' THEN CASE WHEN r.tanggal=DATE(clock.now_server) THEN 'belum_checkin' ELSE 'tanpa_checkin' END
        WHEN clock.now_server<r.rencana_mulai THEN 'belum_mulai'
        WHEN clock.now_server<r.rencana_selesai THEN 'belum_checkin' ELSE 'tanpa_checkin' END";
}

/** Status utama selalu mengikuti blok berjalan, bukan laporan terbuka sebelumnya. */
function kbm_class_status(array $meetings,array $context,array $open,string $now): array {
    $date=substr($now,0,10);$running=[];$today=[];
    foreach($meetings as $m) if($m['tanggal']===$date) {
        $today[]=$m;
        if($m['rencana_mulai']<=$now && $now<$m['rencana_selesai']) $running[]=$m;
    }
    usort($today,fn($a,$b)=>[$a['rencana_mulai'],$a['jadwal_id']]<=>[$b['rencana_mulai'],$b['jadwal_id']]);
    usort($running,fn($a,$b)=>[$a['rencana_mulai'],$a['jadwal_id']]<=>[$b['rencana_mulai'],$b['jadwal_id']]);
    $primary=null;$selected=null;
    $manualToday=array_values(array_filter($today,fn($m)=>($m['sumber']??'')==='ketua_kelas'));
    if($manualToday){
        usort($manualToday,fn($a,$b)=>(int)$b['jadwal_id']<=>(int)$a['jadwal_id']);
        $selected=$manualToday[0];foreach($manualToday as $m)if(kbm_meeting_status($m,$now)['code']==='checkin'){$selected=$m;break;}
        $primary=kbm_meeting_status($selected,$now);
    }
    if(!$primary&&!empty($context['holidays'])) $primary=kbm_status_value('hari_tanpa_kbm','kuning','Hari tanpa KBM: '.implode('; ',$context['holidays']));
    if(!$primary) foreach($context['breaks']??[] as $b) if($b['mulai']<=$now && $now<$b['selesai']) {$primary=kbm_status_value('istirahat','kuning','Istirahat');break;}
    if(!$primary && $running) {$selected=$running[0];$primary=kbm_meeting_status($selected,$now);}
    if(!$primary) {
        foreach($today as $m) if($now<$m['rencana_mulai']) {$selected=$m;$primary=kbm_status_value('belum_mulai','kuning','Belum waktunya KBM');break;}
    }
    if(!$primary && $today) $primary=kbm_status_value('selesai','kuning','KBM selesai');
    if(!$primary) $primary=kbm_status_value(!empty($context['incomplete'])?'belum_dikonfigurasi':'tanpa_jadwal','kuning',!empty($context['incomplete'])?'Konfigurasi KBM belum lengkap':'Tidak ada jadwal KBM');
    if(!empty($context['simple']) && $primary['code']==='tanpa_jadwal')$primary=kbm_status_value('tanpa_jadwal','kuning','Belum ada laporan ketua kelas');
    $warnings=[];
    foreach($open as $m) {
        if($selected && !empty($selected['pertemuan_id']) && (int)$selected['pertemuan_id']===(int)$m['pertemuan_id'] && ($running||$manualToday) && $primary['code']==='checkin') continue;
        $status=kbm_meeting_status($m,$now);
        $warnings[]=['meeting'=>$m,'status'=>$status,'label'=>$status['warning']?:'Belum check-out'];
    }
    return ['primary'=>$primary,'meeting'=>$selected,'warnings'=>$warnings];
}

/** Pembacaan saja. Satu waktu server dipakai untuk semua kelas pada satu respons. */
function kbm_monitoring_snapshot(PDO $db,?int $class=null,?string $date=null): array {
    if($db->inTransaction()) return kbm_monitoring_read($db,$class,$date);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    try {
        $result=kbm_monitoring_read($db,$class,$date);$db->commit();return $result;
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Internal: seluruh pembacaan satu respons memakai snapshot transaksi yang sama. */
function kbm_monitoring_read(PDO $db,?int $class,?string $date): array {
    $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();$today=substr($now,0,10);
    $date=$date??$today;kbm_date($date);
    if($date>$today) throw new InvalidArgumentException('Riwayat hanya tersedia sampai tanggal server hari ini.');
    $classes=kbm_rows($db,'SELECT id,nama_kelas FROM kelas'.($class!==null?' WHERE id=?':'').' ORDER BY nama_kelas',$class!==null?[$class]:[]);
    $reports=kbm_day_report($db,$date);$cards=[];
    // Muat per jenis data, bukan query berulang untuk setiap kartu kelas.
    $simple=kbm_simple_ready($db);
    if($simple){
        $periods=[true];$assignmentsAll=kbm_simple_sessions($db);$chairs=kbm_simple_chairs($db);
        $patterns=$slots=$holidaysAll=$weekly=$configured=[];
    }else{
    $periods=kbm_rows($db,'SELECT * FROM kbm_periode WHERE ? BETWEEN mulai AND selesai',[$date]);
    $assignmentsAll=kbm_rows($db,'SELECT a.* FROM kbm_kelas_sesi a JOIN kbm_periode p ON p.id=a.periode_id WHERE ? BETWEEN a.berlaku_mulai AND a.berlaku_sampai AND ? BETWEEN p.mulai AND p.selesai',[$date,$date]);
    $patterns=kbm_rows($db,'SELECT w.* FROM kbm_pola_waktu w JOIN kbm_periode p ON p.id=w.periode_id WHERE ? BETWEEN w.berlaku_mulai AND w.berlaku_sampai AND ? BETWEEN p.mulai AND p.selesai',[$date,$date]);
    $patternIds=array_column($patterns,'id');$slots=[];
    if($patternIds) foreach(kbm_rows($db,'SELECT * FROM kbm_slot_waktu WHERE pola_id IN ('.implode(',',array_fill(0,count($patternIds),'?')).') ORDER BY pola_id,urutan',$patternIds) as $s) $slots[$s['pola_id']][]=$s;
    $holidaysAll=kbm_rows($db,'SELECT h.* FROM kbm_hari_tanpa_kbm h JOIN kbm_periode p ON p.id=h.periode_id WHERE h.tanggal=? AND ? BETWEEN p.mulai AND p.selesai',[$date,$date]);
    $weekly=kbm_rows($db,'SELECT j.*,a.kelas_id,p.hari FROM kbm_jadwal j JOIN kbm_kelas_sesi a ON a.id=j.kelas_sesi_id JOIN kbm_pola_waktu p ON p.id=j.pola_id WHERE ? BETWEEN j.berlaku_mulai AND j.berlaku_sampai AND (j.nonaktif_mulai IS NULL OR j.nonaktif_mulai>?)',[$date,$date]);
    $configured=array_flip(array_column(kbm_rows($db,'SELECT DISTINCT kelas_sesi_id FROM kbm_jadwal'),'kelas_sesi_id'));
    $chairs=kbm_rows($db,'SELECT a.kelas_id,a.periode_id FROM kbm_ketua_kelas a JOIN siswa s ON s.id=a.siswa_id AND s.kelas_id=a.kelas_id JOIN users u ON u.id=s.user_id WHERE u.role_id=3 AND u.is_active=1 AND a.dicabut_pada IS NULL AND ? BETWEEN a.berlaku_mulai AND a.berlaku_sampai',[$date]);
    }
    $chairKeys=[];foreach($chairs as $a)$chairKeys[($simple?'':$a['periode_id']).':'.$a['kelas_id']]=true;
    $openAll=kbm_open_meetings($db,$class);
    $reportsByClass=$assignmentsByClass=$weeklyByClass=$openByClass=$patternsBySession=[];
    foreach($reports as $r) $reportsByClass[$r['kelas_id']][]=$r;
    foreach($assignmentsAll as $a) $assignmentsByClass[$a['kelas_id']][]=$a;
    foreach($weekly as $j) $weeklyByClass[$j['kelas_id']][]=$j;
    foreach($openAll as $m) if($m['tanggal']<=$date) $openByClass[$m['kelas_id']][]=$m;
    foreach($patterns as $p) $patternsBySession[$p['periode_id'].':'.$p['sesi_kode']][]=$p;
    $freeJp=$simple||kbm_pilihan_tanpa_jam_ready($db);
    foreach($classes as $c) {
        $id=(int)$c['id'];
        $rows=$reportsByClass[$id]??[];
        foreach($rows as &$r) $r['status']=kbm_meeting_status($r,$now);unset($r);
        $assignments=$assignmentsByClass[$id]??[];$issues=[];
        if(!$periods) $issues[]='Periode tahun ajaran dan semester pada tanggal ini belum diatur.';
        if(!$assignments) $issues[]='Pembagian sesi kelas belum diatur.';
        if(count($assignments)>1) $issues[]='Terdapat pembagian sesi/periode yang bertumpang tindih.';
        $context=['simple'=>$simple,'sessions'=>array_values(array_unique(array_column($assignments,'sesi_kode'))),'breaks'=>[],'holidays'=>[],'incomplete'=>!$assignments];
        foreach($assignments as $a) {
            if(!isset($chairKeys[($simple?'':$a['periode_id']).':'.$id])) $issues[]='Ketua kelas aktif belum ditunjuk atau izinnya tidak lagi berlaku.';
            // Pelaporan bebas JP tidak membutuhkan pola waktu/jadwal guru admin.
            // Tetap periksa periode, pembagian sesi dan izin ketua kelas di atas.
            if($freeJp) continue;
            $sessionPatterns=$patternsBySession[$a['periode_id'].':'.$a['sesi_kode']]??[];
            if(!$sessionPatterns) $issues[]='Pola waktu JP sesi '.ucfirst($a['sesi_kode']).' belum diatur.';
            $classWeekly=array_values(array_filter($weeklyByClass[$id]??[],fn($j)=>(int)$j['kelas_sesi_id']===(int)$a['id']));
            if(!isset($configured[$a['id']])) $issues[]='Jadwal KBM kelas belum dikonfigurasi untuk masa berlaku ini.';
            foreach($classWeekly as $j) {
                if(!in_array($j['pola_id'],array_column($sessionPatterns,'id'))) $issues[]='Masa berlaku pola JP tidak sesuai jadwal.';
            }
            foreach($sessionPatterns as $p) {
                if((int)$p['hari']!==(int)kbm_date($date)->format('N')) continue;
                try { kbm_validate_slots($slots[$p['id']]??[]); }
                catch(InvalidArgumentException $e) {$issues[]='Pola JP hari ini belum lengkap atau jamnya tidak valid.';}
                foreach($slots[$p['id']]??[] as $b) if($b['jenis']==='ISTIRAHAT') $context['breaks'][]=['mulai'=>$date.' '.$b['mulai'],'selesai'=>$date.' '.$b['selesai']];
            }
        }
        foreach($holidaysAll as $h) if(($h['kelas_id']===null || (int)$h['kelas_id']===$id) && ($h['sesi_kode']===null || array_filter($assignments,fn($a)=>(int)$a['periode_id']===(int)$h['periode_id'] && $a['sesi_kode']===$h['sesi_kode']))) $context['holidays'][]=$h['alasan'];
        $context['holidays']=array_values(array_unique($context['holidays']));
        $context['issues']=array_values(array_unique($issues));$context['incomplete']=(bool)$issues;
        // Halaman riwayat memakai kondisi akhir tanggal terpilih untuk status kelas;
        // waktu kejadian tetap berasal dari server, tidak ada jam yang ditulis ulang.
        $classNow=$date===$today?$now:$date.' 23:59:59';
        $historicalOpen=$openByClass[$id]??[];
        $state=kbm_class_status($rows,$context,$historicalOpen,$classNow);
        $cards[]=['class'=>$c,'context'=>$context,'state'=>$state,'meetings'=>$rows];
    }
    return ['now'=>$now,'date'=>$date,'classes'=>$cards];
}
