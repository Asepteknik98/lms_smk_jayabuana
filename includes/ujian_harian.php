<?php
/** Shared recipient rules and final grades for UH. Legacy exams target the whole class. */
function uh_remedial_ready(PDO $db): bool {
    try{$db->query('SELECT ujian_id,ujian_asal_id FROM ujian_harian_remedial LIMIT 0');$db->query('SELECT id,sumber_ujian_id FROM remedial_ujian LIMIT 0');$db->query('SELECT remedial_id FROM remedial_ujian_batal LIMIT 0');return true;}
    catch(PDOException $e){if($e->getCode()==='42S02')return false;throw $e;}
}
function uh_regular_sql(PDO $db,string $exam='u.id'): string {
    return uh_remedial_ready($db)?"NOT EXISTS(SELECT 1 FROM ujian_harian_remedial uh_child WHERE uh_child.ujian_id=$exam)":'1=1';
}
function uh_ready(PDO $db): bool {
    static $ready;
    $ready ??= new WeakMap();
    if(isset($ready[$db]))return $ready[$db];
    try {
        $db->query('SELECT ujian_id,nomor,metode,penerima,instruksi FROM ujian_harian_pengaturan LIMIT 0');
        $db->query('SELECT ujian_id,siswa_id FROM ujian_harian_penerima LIMIT 0');
        $db->query('SELECT ujian_id,siswa_id,nama_file FROM ujian_harian_berkas LIMIT 0');
        return $ready[$db]=true;
    } catch(PDOException $e) { if($e->getCode()==='42S02')return $ready[$db]=false;throw $e; }
}
function uh_eligible_sql(string $exam='u.id',string $student='s.id'): string {
    return "(NOT EXISTS(SELECT 1 FROM ujian_harian_pengaturan uh_m WHERE uh_m.ujian_id=$exam AND uh_m.penerima='pilihan') OR EXISTS(SELECT 1 FROM ujian_harian_penerima uh_r WHERE uh_r.ujian_id=$exam AND uh_r.siswa_id=$student))";
}
function uh_pending_sql(string $exam='u.id',string $student='s.id'): string {
    return "EXISTS(SELECT 1 FROM sesi_ujian uh_s JOIN jawaban_siswa uh_j ON uh_j.sesi_ujian_id=uh_s.id JOIN soal_ujian uh_q ON uh_q.id=uh_j.soal_id WHERE uh_s.ujian_id=$exam AND uh_s.siswa_id=$student AND uh_s.status='Selesai' AND uh_q.tipe_soal='ESAI' AND TRIM(COALESCE(uh_j.jawaban_esai,''))<>'' AND uh_j.nilai_esai IS NULL)";
}
function uh_average_sql(PDO $db,string $pid,string $sid): string {
    $regular=uh_regular_sql($db);
    $eligible=uh_ready($db)?uh_eligible_sql('u.id','uh_student.id'):'1=1';
    $pending=uh_pending_sql('u.id','uh_student.id');
    return "COALESCE((SELECT AVG(COALESCE(nu.nilai_total,0)) FROM ujian u CROSS JOIN siswa uh_student LEFT JOIN nilai_ujian nu ON nu.ujian_id=u.id AND nu.siswa_id=uh_student.id WHERE uh_student.id=$sid AND u.pengajaran_id=$pid AND u.jenis_ujian='Kuis' AND $regular AND $eligible AND (u.waktu_selesai<=NOW() OR EXISTS(SELECT 1 FROM sesi_ujian uh_finished WHERE uh_finished.ujian_id=u.id AND uh_finished.siswa_id=uh_student.id AND uh_finished.status='Selesai')) AND NOT ($pending) AND (nu.nilai_total IS NOT NULL OR (u.waktu_selesai<=NOW() AND NOT EXISTS(SELECT 1 FROM sesi_ujian uh_done WHERE uh_done.ujian_id=u.id AND uh_done.siswa_id=uh_student.id AND uh_done.status='Selesai')))),0)";
}
function uh_number(PDO $db,int $exam,int $pid): int {
    $regular=uh_regular_sql($db);$q=$db->prepare("SELECT COUNT(*) FROM ujian u WHERE pengajaran_id=? AND jenis_ujian='Kuis' AND id<=? AND $regular");$q->execute([$pid,$exam]);return max(1,(int)$q->fetchColumn());
}
function uh_settings(PDO $db,int $exam,int $pid): array {
    if(uh_ready($db)){$q=$db->prepare('SELECT * FROM ujian_harian_pengaturan WHERE ujian_id=?');$q->execute([$exam]);if($r=$q->fetch(PDO::FETCH_ASSOC))return $r;}
    return ['nomor'=>uh_number($db,$exam,$pid),'metode'=>'cbt','penerima'=>'kelas','instruksi'=>''];
}
function uh_save_settings(PDO $db,int $exam,int $pid,array $input): void {
    if(!uh_ready($db))throw new RuntimeException('Aktifkan migrasi 2026_10_22_ujian_harian.sql terlebih dahulu.');
    $method=$input['metode']??'cbt';$recipient=$input['penerima']??'kelas';$instructions=$input['instruksi']??'';
    if(!in_array($method,['cbt','berkas'],true)||!in_array($recipient,['kelas','pilihan'],true)||!is_string($instructions)||mb_strlen($instructions)>20000)throw new RuntimeException('Metode, penerima atau instruksi tidak valid.');
    $old=uh_settings($db,$exam,$pid);
    $q=$db->prepare('SELECT COUNT(*) FROM sesi_ujian WHERE ujian_id=?');$q->execute([$exam]);
    if((int)$q->fetchColumn()>0&&($old['metode']!==$method||$old['penerima']!==$recipient))throw new RuntimeException('Metode dan penerima dikunci setelah siswa mulai mengerjakan.');
    $ids=$input['siswa_ids']??[];
    if(!is_array($ids))throw new RuntimeException('Daftar siswa tidak valid.');
    $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
    if($recipient==='pilihan'){
        if(!$ids)throw new RuntimeException('Pilih minimal satu siswa penerima.');
        $q=$db->prepare('SELECT s.id FROM siswa s JOIN pengajaran p ON p.kelas_id=s.kelas_id WHERE p.id=?');$q->execute([$pid]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(array_diff($ids,$allowed))throw new RuntimeException('Siswa penerima harus berasal dari kelas terpilih.');
        $q=$db->prepare('SELECT siswa_id FROM ujian_harian_penerima WHERE ujian_id=? ORDER BY siswa_id');$q->execute([$exam]);$before=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $q=$db->prepare('SELECT COUNT(*) FROM sesi_ujian WHERE ujian_id=?');$q->execute([$exam]);if((int)$q->fetchColumn()>0&&$ids!==$before)throw new RuntimeException('Daftar penerima dikunci setelah ada peserta.');
    }
    $q=$db->prepare('SELECT nomor FROM ujian_harian_pengaturan WHERE ujian_id=?');$q->execute([$exam]);$number=$q->fetchColumn();
    if($number===false){$regular=uh_regular_sql($db);$q=$db->prepare("SELECT COALESCE(MAX(h.nomor),0)+1 FROM ujian_harian_pengaturan h JOIN ujian u ON u.id=h.ujian_id WHERE u.pengajaran_id=? AND u.jenis_ujian='Kuis' AND $regular");$q->execute([$pid]);$number=max($old['nomor'],(int)$q->fetchColumn());}
    $q=$db->prepare('INSERT INTO ujian_harian_pengaturan(ujian_id,nomor,metode,penerima,instruksi) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE metode=VALUES(metode),penerima=VALUES(penerima),instruksi=VALUES(instruksi)');$q->execute([$exam,$number,$method,$recipient,trim($instructions)]);
    $db->prepare('DELETE FROM ujian_harian_penerima WHERE ujian_id=?')->execute([$exam]);
    if($recipient==='pilihan'){$q=$db->prepare('INSERT INTO ujian_harian_penerima(ujian_id,siswa_id) VALUES(?,?)');foreach($ids as $id)$q->execute([$exam,$id]);}
}
function uh_calculate(PDO $db,int $exam,int $sid,int $session): void {
    $q=$db->prepare("SELECT COALESCE(SUM(so.bobot),0) total,COALESCE(SUM(CASE WHEN so.tipe_soal='PG' AND js.is_benar=1 THEN so.bobot WHEN so.tipe_soal='ESAI' THEN COALESCE(js.nilai_esai,0)*so.bobot/100 ELSE 0 END),0) earned,COALESCE(SUM(CASE WHEN so.tipe_soal='PG' THEN so.bobot ELSE 0 END),0) pg_weight,COALESCE(SUM(CASE WHEN so.tipe_soal='PG' AND js.is_benar=1 THEN so.bobot ELSE 0 END),0) pg,COALESCE(SUM(CASE WHEN so.tipe_soal='ESAI' THEN so.bobot ELSE 0 END),0) essay_weight,COALESCE(SUM(CASE WHEN so.tipe_soal='ESAI' THEN COALESCE(js.nilai_esai,0)*so.bobot/100 ELSE 0 END),0) essay,SUM(CASE WHEN so.tipe_soal='ESAI' AND TRIM(COALESCE(js.jawaban_esai,''))<>'' AND js.nilai_esai IS NULL THEN 1 ELSE 0 END) pending FROM soal_ujian so LEFT JOIN jawaban_siswa js ON js.soal_id=so.id AND js.sesi_ujian_id=? WHERE so.ujian_id=?");
    $q->execute([$session,$exam]);$r=$q->fetch();$pg=$r['pg_weight']>0?$r['pg']/$r['pg_weight']*100:0;$essay=$r['essay_weight']>0?$r['essay']/$r['essay_weight']*100:0;$total=$r['pending']?null:($r['total']>0?$r['earned']/$r['total']*100:0);
    $db->prepare('INSERT INTO nilai_ujian(ujian_id,siswa_id,nilai_pg,nilai_esai,nilai_total,selesai_pada) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE nilai_pg=VALUES(nilai_pg),nilai_esai=VALUES(nilai_esai),nilai_total=VALUES(nilai_total),selesai_pada=NOW()')->execute([$exam,$sid,$pg,$essay,$total]);
    if($total!==null&&uh_remedial_ready($db)){require_once __DIR__.'/ujian_nilai_tools.php';uhn_sync($db,$exam,$sid,(float)$total);}
}
