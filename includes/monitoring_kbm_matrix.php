<?php
require_once __DIR__.'/monitoring_kbm_history.php';

/** Ringkasan baca saja; snapshot historis lebih diutamakan daripada nama/sesi saat ini. */
function kbm_history_matrix(PDO $db,array $input): array {
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
    try{
        $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();$today=substr($now,0,10);
        $date=$input['tanggal']??$input['tanggal_mulai']??$today;$session=$input['sesi']??'';
        if(!is_string($date)||!is_string($session))throw new InvalidArgumentException('Tanggal atau sesi tidak valid.');
        kbm_date($date);if($date>$today)throw new InvalidArgumentException('Riwayat hanya tersedia sampai tanggal hari ini.');
        if(!in_array($session,['','pagi','siang'],true))throw new InvalidArgumentException('Pilih sesi pagi, siang, atau semua sesi.');
        // Tautan riwayat lama tetap menerima tanggal tunggal dan menolak rentang terbalik.
        if(isset($input['tanggal_selesai'])){
            if(!is_string($input['tanggal_selesai']))throw new InvalidArgumentException('Tanggal selesai tidak valid.');
            kbm_date($input['tanggal_selesai']);if($input['tanggal_selesai']<$date)throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }
        $records=kbm_rows($db,kbm_history_sql(kbm_simple_ready($db)).'SELECT * FROM history ORDER BY jadwal_id,pertemuan_id',[$date,$date,$now]);
        $classes=kbm_rows($db,'SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas');
        $placements=kbm_rows($db,'SELECT a.* FROM kbm_kelas_sesi a WHERE a.berlaku_mulai<=? AND a.id=(SELECT MAX(b.id) FROM kbm_kelas_sesi b WHERE b.kelas_id=a.kelas_id AND b.berlaku_mulai<=?)',[$date,$date]);
        $byClass=$placementByClass=[];
        foreach($placements as $p)$placementByClass[$p['kelas_id']]=$p['sesi_kode'];
        foreach($records as $r)if(empty($r['dibatalkan'])&&$r['status_code']!=='dibatalkan')$byClass[$r['kelas_id']][]=$r;
        $rows=[];$counts=['hadir'=>0,'tidak_masuk'=>0,'belum_tercatat'=>0];
        foreach($classes as $class){
            $all=$byClass[$class['id']]??[];$sessions=$all?array_values(array_unique(array_column($all,'sesi_kode'))):array_filter([$placementByClass[$class['id']]??null]);
            if($session!==''&&!in_array($session,$sessions,true))continue;
            $matches=$session===''?$all:array_values(array_filter($all,fn($r)=>$r['sesi_kode']===$session));
            $cells=array_fill(1,6,null);$name=$class['nama_kelas'];
            foreach($matches as $r){
                $name=$r['nama_kelas'];$status=kbm_meeting_status($r,$now);
                $kind=$status['code']==='guru_tidak_masuk'?'tidak_masuk':($r['checkin_efektif']!==null&&$r['checkin_efektif']<=$now?'hadir':'belum_tercatat');
                for($jp=(int)$r['jp_mulai'];$jp<=(int)$r['jp_selesai'];$jp++)$cells[$jp]=['kind'=>$kind,'meeting'=>$r,'status'=>$status];
            }
            foreach($cells as $cell)$counts[$cell['kind']??'belum_tercatat']++;
            $rows[]=['id'=>(int)$class['id'],'name'=>$name,'sessions'=>$sessions,'cells'=>$cells];
        }
        usort($rows,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
        $db->commit();return ['date'=>$date,'session'=>$session,'today'=>$today,'now'=>$now,'rows'=>$rows,'counts'=>$counts];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
