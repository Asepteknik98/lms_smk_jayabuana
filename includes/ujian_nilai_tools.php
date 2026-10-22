<?php
require_once __DIR__.'/ujian_harian.php';
require_once __DIR__.'/nilai_excel_reader.php';
require_once __DIR__.'/excel_workbook.php';

function uhn_score($raw): ?float {
    if(!is_string($raw))throw new RuntimeException('Format nilai tidak valid.');
    $raw=str_replace(',','.',trim($raw));if($raw==='')return null;
    if(!preg_match('/^\d+(?:\.\d+)?$/D',$raw)||!is_finite((float)$raw)||(float)$raw>100)throw new RuntimeException('Nilai harus angka 0 sampai 100.');
    return round((float)$raw,2);
}
function uhn_context(PDO $db,int $guru,int $pid,int $exam): array {
    $q=$db->prepare('SELECT p.*,m.nama_mapel,k.nama_kelas FROM pengajaran p JOIN guru g ON g.id=p.guru_id JOIN users a ON a.id=g.user_id AND a.role_id=2 AND a.is_active=1 JOIN kelas k ON k.id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id WHERE p.id=? AND p.guru_id=?');$q->execute([$pid,$guru]);$info=$q->fetch();
    if(!$info)throw new RuntimeException('Pengajaran tidak tersedia untuk Anda.');
    $regular=uh_regular_sql($db);$q=$db->prepare("SELECT u.*,(u.waktu_selesai<=NOW()) selesai FROM ujian u WHERE u.pengajaran_id=? AND u.jenis_ujian='Kuis' AND $regular ORDER BY u.id");$q->execute([$pid]);$exams=$q->fetchAll();$selected=null;
    foreach($exams as &$row){$row['nomor']=uh_settings($db,(int)$row['id'],$pid)['nomor'];if((int)$row['id']===$exam)$selected=$row;}unset($row);
    if(!$selected)throw new RuntimeException('Pilih UH asal yang sesuai kelas dan mata pelajaran.');
    $eligible=uh_ready($db)?uh_eligible_sql((string)$exam,'s.id'):'1=1';$pending=uh_pending_sql((string)$exam,'s.id');
    $q=$db->prepare("SELECT s.id,s.nis,s.nisn,s.nama_lengkap,n.nilai_total nilai,se.status,$pending pending FROM siswa s LEFT JOIN nilai_ujian n ON n.siswa_id=s.id AND n.ujian_id=? LEFT JOIN sesi_ujian se ON se.siswa_id=s.id AND se.ujian_id=? WHERE s.kelas_id=? AND $eligible ORDER BY s.nama_lengkap,s.id");$q->execute([$exam,$exam,$info['kelas_id']]);$students=$q->fetchAll();
    return compact('pid','exam','guru','info','exams','selected','students');
}
function uhn_template(array $context): string {
    $e=$context['selected'];$rows=[['Pengajaran ID',(string)$context['pid']],['UH ID',(string)$e['id']],['ID Siswa','NIS','NISN','Nama Siswa','UH '.$e['nomor'].' [ID:'.$e['id'].']']];
    foreach($context['students'] as $s)$rows[]=[(string)$s['id'],(string)($s['nis']??''),(string)($s['nisn']??''),$s['nama_lengkap'],''];
    $x=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');$xml='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Nilai UH"><Table>';
    foreach($rows as $row){$xml.='<Row>';foreach($row as $v)$xml.='<Cell><Data ss:Type="String">'.$x($v).'</Data></Cell>';$xml.='</Row>';}
    return $xml.'</Table></Worksheet><Worksheet ss:Name="Petunjuk"><Table><Row><Cell><Data ss:Type="String">Isi nilai 0-100. Sel kosong dilewati. Nilai yang sudah ada tidak ditimpa; gunakan Remedial. Jangan mengubah ID atau header. Impor tersedia setelah jadwal UH berakhir dan sesi siswa telah selesai.</Data></Cell></Row></Table></Worksheet></Workbook>';
}
function uhn_template_xlsx(array $context): string {
    $e=$context['selected'];$rows=[['Pengajaran ID',(string)$context['pid']],['UH ID',(string)$e['id']],['ID Siswa','NIS','NISN','Nama Siswa','UH '.$e['nomor'].' [ID:'.$e['id'].']']];
    foreach($context['students'] as $s)$rows[]=[(string)$s['id'],(string)($s['nis']??''),(string)($s['nisn']??''),$s['nama_lengkap'],''];
    return ew_workbook([
        ['name'=>'Nilai UH','rows'=>$rows,'widths'=>[18,20,22,40,24],'header'=>2,'freeze'=>3,'validation'=>[
            ['type'=>'decimal','range'=>'E4:E5000','first'=>'0','second'=>'100','error'=>'Isi angka 0-100; kosongkan jika tidak dinilai.']]],
        ['name'=>'Petunjuk','rows'=>[['PETUNJUK IMPOR NILAI UH'],['Kelas: '.$context['info']['nama_kelas'].' - '.$context['info']['nama_mapel']],['UH '.$e['nomor'].' - '.$e['nama_ujian']],['Isi nilai 0-100 pada kolom E di sheet Nilai UH. Sel kosong dilewati.'],['Jangan mengubah Pengajaran ID, UH ID, ID Siswa atau nama kolom. NIS/NISN dipertahankan sebagai teks.'],['Nilai lama tidak ditimpa. Gunakan Remedial untuk perbaikan nilai.'],['Impor setelah jadwal UH berakhir. Jawaban yang menunggu pemeriksaan dan sesi aktif dilewati.'],['Simpan sebagai .xlsx, unggah, periksa pratinjau lalu konfirmasi. Maksimal 5 MB.']],'widths'=>[115]]
    ]);
}
function uhn_preview(array $context,array $rows): array {
    $exam=$context['selected'];
    $head=static fn($row,$count)=>array_map(static fn($v)=>trim((string)$v),array_slice($row,0,$count));
    if($head($rows[0]??[],2)!==['Pengajaran ID',(string)$context['pid']]||$head($rows[1]??[],2)!==['UH ID',(string)$exam['id']]||$head($rows[2]??[],5)!==['ID Siswa','NIS','NISN','Nama Siswa','UH '.$exam['nomor'].' [ID:'.$exam['id'].']'])throw new RuntimeException('Template tidak sesuai UH, kelas atau mata pelajaran. Unduh template untuk UH terpilih.');
    if(!$exam['selesai'])throw new RuntimeException('Impor nilai tersedia setelah jadwal UH berakhir.');
    $students=[];foreach($context['students'] as $s)$students[(int)$s['id']]=$s;
    $used=[];$plan=[];
    foreach($rows as $i=>$row){
        if($i<3||!array_filter($row,static fn($v)=>trim((string)$v)!==''))continue;
        $sid=(string)($row[0]??'');if(!preg_match('/^[1-9][0-9]*$/D',$sid)||!isset($students[(int)$sid])||isset($used[$sid]))throw new RuntimeException('Baris '.($i+1).': ID siswa tidak sesuai penerima atau berulang.');$used[$sid]=true;
        foreach($row as $col=>$v)if($col>4&&trim((string)$v)!=='')throw new RuntimeException('Ada nilai di luar kolom template.');
        $raw=trim((string)($row[4]??''));if($raw==='')continue;$invalid=false;
        try{$score=uhn_score($raw);}catch(RuntimeException $e){$score=null;$invalid=true;}
        $s=$students[(int)$sid];$blocked=$s['pending']||$s['status']==='Berlangsung';$saved=$s['nilai']!==null;
        $ready=!$invalid&&!$blocked&&!$saved;
        $plan[]=['sid'=>(int)$sid,'nama'=>$s['nama_lengkap'],'raw'=>$raw,'score'=>$score,'invalid'=>$invalid,'ready'=>$ready,'reason'=>$invalid?'Nilai harus 0-100':($blocked?'Dilewati: sesi aktif atau menunggu pemeriksaan':($saved?'Dilewati: nilai sudah tersimpan':'Siap diimpor'))];
        if(count($plan)>5000)throw new RuntimeException('Maksimal 5.000 nilai per impor.');
    }
    if(!$plan)throw new RuntimeException('Tidak ada nilai yang diisi.');return $plan;
}
/** Caller owns the transaction. Locks exam first, then session and grade. */
function uhn_lock(PDO $db,int $guru,int $user,int $pid,int $exam,int $sid): array {
    $regular=uh_regular_sql($db);$eligible=uh_ready($db)?uh_eligible_sql('u.id','s.id'):'1=1';
    $q=$db->prepare("SELECT u.id,(u.waktu_selesai<=NOW()) selesai FROM ujian u JOIN pengajaran p ON p.id=u.pengajaran_id JOIN guru g ON g.id=p.guru_id JOIN users a ON a.id=g.user_id AND a.role_id=2 AND a.is_active=1 JOIN siswa s ON s.kelas_id=p.kelas_id WHERE u.id=? AND p.id=? AND g.id=? AND a.id=? AND s.id=? AND u.jenis_ujian='Kuis' AND $eligible AND $regular FOR UPDATE");$q->execute([$exam,$pid,$guru,$user,$sid]);$row=$q->fetch();
    if(!$row)throw new RuntimeException('UH atau siswa bukan penerima pada pengajaran Anda.');
    if(!$row['selesai'])throw new RuntimeException('Impor dan remedial tersedia setelah jadwal UH berakhir.');
    $q=$db->prepare('SELECT status FROM sesi_ujian WHERE ujian_id=? AND siswa_id=? FOR UPDATE');$q->execute([$exam,$sid]);$status=$q->fetchColumn();
    $pending=uh_pending_sql('u.id',(string)$sid);$q=$db->prepare("SELECT $pending FROM ujian u WHERE u.id=?");$q->execute([$exam]);
    if($status==='Berlangsung'||$q->fetchColumn())throw new RuntimeException('Selesaikan sesi atau penilaian awal siswa terlebih dahulu.');
    $q=$db->prepare('SELECT nilai_total FROM nilai_ujian WHERE ujian_id=? AND siswa_id=? FOR UPDATE');$q->execute([$exam,$sid]);$grade=$q->fetch();
    if($status==='Selesai'&&(!$grade||$grade['nilai_total']===null))throw new RuntimeException('Jawaban siswa masih menunggu penilaian awal.');
    return ['nilai'=>$grade&&$grade['nilai_total']!==null?(float)$grade['nilai_total']:null];
}
function uhn_write(PDO $db,int $exam,int $sid,float $grade): void {
    $db->prepare('INSERT INTO nilai_ujian(ujian_id,siswa_id,nilai_total,selesai_pada) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE nilai_total=VALUES(nilai_total),selesai_pada=NOW()')->execute([$exam,$sid,$grade]);
}
function uhn_log(PDO $db,int $user,string $text): void {
    $db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)')->execute([$user,$text,$_SERVER['REMOTE_ADDR']??'CLI',substr($_SERVER['HTTP_USER_AGENT']??'CLI',0,255)]);
}
function uhn_import(PDO $db,int $guru,int $user,int $pid,int $exam,array $plan): int {
    if(($plan['guru']??0)!==$guru||($plan['user']??0)!==$user||($plan['pid']??0)!==$pid||($plan['exam']??0)!==$exam||($plan['expires']??0)<time())throw new RuntimeException('Pratinjau tidak sesuai atau kedaluwarsa. Unggah ulang file.');
    $rows=$plan['rows']??[];if(!$rows||count($rows)>5000||array_filter($rows,static fn($r)=>$r['invalid']))throw new RuntimeException('Perbaiki file sebelum menyimpan.');
    $rows=array_values(array_filter($rows,static fn($r)=>$r['ready']));if(!$rows)throw new RuntimeException('Tidak ada nilai baru untuk diimpor.');
    $db->beginTransaction();try{foreach($rows as $r){$old=uhn_lock($db,$guru,$user,$pid,$exam,$r['sid']);if($old['nilai']!==null)throw new RuntimeException('Nilai berubah setelah pratinjau. Unggah ulang; tidak ada nilai yang ditimpa.');$score=uhn_score((string)$r['score']);if($score===null)throw new RuntimeException('Nilai tidak valid.');uhn_write($db,$exam,$r['sid'],$score);}
        uhn_log($db,$user,'Impor '.count($rows).' nilai UH #'.$exam);$db->commit();return count($rows);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function uhn_history(PDO $db,int $exam,int $sid=0,bool $lock=false): array {
    $q=$db->prepare('SELECT r.*,b.dibatalkan_pada,b.alasan FROM remedial_ujian r LEFT JOIN remedial_ujian_batal b ON b.remedial_id=r.id WHERE r.ujian_id=?'.($sid?' AND r.siswa_id=?':'').' ORDER BY r.id'.($lock?' FOR UPDATE':''));$q->execute($sid?[$exam,$sid]:[$exam]);return $q->fetchAll();
}
/** Record one attempt, keeping the original grade and the highest active score. */
function uhn_attempt(PDO $db,int $guru,int $user,int $exam,int $sid,float $grade,string $note,?int $source=null): void {
    $q=$db->prepare('SELECT nilai_total FROM nilai_ujian WHERE ujian_id=? AND siswa_id=? FOR UPDATE');$q->execute([$exam,$sid]);$saved=$q->fetchColumn();$before=$saved===false||$saved===null?0:(float)$saved;
    $history=uhn_history($db,$exam,$sid,true);$initial=$history?(float)$history[0]['nilai_awal']:$before;$expected=$initial;$existing=null;
    foreach($history as $r){if(!$r['dibatalkan_pada'])$expected=max($expected,(float)$r['nilai_remedial']);if($source!==null&&(int)$r['sumber_ujian_id']===$source)$existing=$r;}
    if($existing&&$existing['dibatalkan_pada'])return;
    if($history&&abs($before-$expected)>.001)throw new RuntimeException('Nilai berubah di luar remedial. Periksa riwayat sebelum melanjutkan.');
    if(!$history&&$before>=75&&$source===null)throw new RuntimeException('Remedial hanya untuk siswa dengan nilai awal di bawah 75.');
    $final=$initial;foreach($history as $r)if(!$r['dibatalkan_pada']&&(!$existing||(int)$r['id']!==(int)$existing['id']))$final=max($final,(float)$r['nilai_remedial']);$final=max($final,$grade);
    if($existing)$db->prepare('UPDATE remedial_ujian SET nilai_remedial=?,nilai_akhir=?,catatan=? WHERE id=?')->execute([$grade,$final,$note,$existing['id']]);
    else $db->prepare('INSERT INTO remedial_ujian(ujian_id,siswa_id,guru_id,sumber_ujian_id,nilai_awal,nilai_sebelum,nilai_remedial,nilai_akhir,catatan) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$exam,$sid,$guru,$source,$initial,$before,$grade,$final,$note]);
    uhn_write($db,$exam,$sid,$final);uhn_log($db,$user,'Remedial UH #'.$exam.' siswa #'.$sid.' nilai '.$before.' -> '.$final);
}
function uhn_remedial(PDO $db,int $guru,int $user,int $pid,int $exam,array $values,array $notes): int {
    if(!uh_remedial_ready($db))throw new RuntimeException('Migrasi remedial UH belum aktif.');
    if(count($values)>5000)throw new RuntimeException('Maksimal 5.000 nilai.');$entries=[];
    foreach($values as $sid=>$raw){if(!preg_match('/^[1-9][0-9]*$/D',(string)$sid))throw new RuntimeException('ID siswa tidak valid.');$grade=uhn_score($raw);if($grade===null)continue;$note=$notes[$sid]??'';if(!is_string($note)||mb_strlen($note)>1000)throw new RuntimeException('Catatan maksimal 1.000 karakter.');$entries[]=[(int)$sid,$grade,trim($note)];}
    if(!$entries)throw new RuntimeException('Isi minimal satu nilai remedial.');
    $db->beginTransaction();try{foreach($entries as [$sid,$grade,$note]){uhn_lock($db,$guru,$user,$pid,$exam,$sid);uhn_attempt($db,$guru,$user,$exam,$sid,$grade,$note);}$db->commit();return count($entries);}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function uhn_cancel(PDO $db,int $guru,int $user,int $pid,int $exam,int $attempt,string $reason): void {
    if(mb_strlen($reason)>1000)throw new RuntimeException('Alasan maksimal 1.000 karakter.');
    $db->beginTransaction();try{
        $q=$db->prepare('SELECT siswa_id FROM remedial_ujian WHERE id=? AND ujian_id=?');$q->execute([$attempt,$exam]);$sid=(int)$q->fetchColumn();if(!$sid)throw new RuntimeException('Riwayat remedial tidak ditemukan.');
        $current=uhn_lock($db,$guru,$user,$pid,$exam,$sid);$history=uhn_history($db,$exam,$sid,true);$before=(float)$history[0]['nilai_awal'];$after=$before;$target=null;
        foreach($history as $r){if((int)$r['id']===$attempt)$target=$r;if($r['dibatalkan_pada'])continue;$before=max($before,(float)$r['nilai_remedial']);if((int)$r['id']!==$attempt)$after=max($after,(float)$r['nilai_remedial']);}
        if(!$target||$target['dibatalkan_pada'])throw new RuntimeException('Remedial sudah dibatalkan.');
        if($current['nilai']===null||abs($current['nilai']-$before)>.001)throw new RuntimeException('Nilai berubah di luar remedial; pembatalan ditolak agar nilai lain tidak tertimpa.');
        $db->prepare('INSERT INTO remedial_ujian_batal(remedial_id,guru_id,alasan,nilai_sebelum,nilai_sesudah) VALUES(?,?,?,?,?)')->execute([$attempt,$guru,trim($reason),$before,$after]);uhn_write($db,$exam,$sid,$after);uhn_log($db,$user,'Undo remedial UH #'.$exam.' percobaan #'.$attempt.' nilai '.$before.' -> '.$after);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function uhn_create(PDO $db,int $guru,int $user,int $pid,int $exam,array $input): int {
    if(!uh_remedial_ready($db))throw new RuntimeException('Migrasi remedial UH belum aktif.');
    $ids=$input['siswa_ids']??[];$title=$input['judul']??'';$instructions=$input['instruksi']??'';$method=$input['metode']??'';
    if(!is_array($ids)||!$ids||count($ids)>5000||!is_string($title)||trim($title)===''||mb_strlen($title)>200||!is_string($instructions)||mb_strlen($instructions)>20000||!in_array($method,['cbt','berkas'],true))throw new RuntimeException('Isi judul, metode dan pilih siswa remedial.');
    $start=$input['waktu_mulai']??'';$end=$input['waktu_selesai']??'';
    foreach([$start,$end] as $date)if(!is_string($date)||!($d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date))||$d->format('Y-m-d\TH:i')!==$date)throw new RuntimeException('Jadwal remedial tidak valid.');
    if($start>=$end||strtotime($end)<=time())throw new RuntimeException('Tenggat remedial harus setelah waktu mulai dan belum berlalu.');
    $db->beginTransaction();try{
        $context=uhn_context($db,$guru,$pid,$exam);$source=$context['selected'];$ids=array_values(array_unique(array_map('intval',$ids)));
        foreach($ids as $sid){$old=uhn_lock($db,$guru,$user,$pid,$exam,$sid);if(($old['nilai']??0)>=75)throw new RuntimeException('Peserta remedial harus memiliki nilai di bawah 75.');}
        if($method==='cbt'){$q=$db->prepare('SELECT COUNT(*) FROM soal_ujian WHERE ujian_id=?');$q->execute([$exam]);if(!(int)$q->fetchColumn())throw new RuntimeException('UH asal tidak memiliki soal CBT. Pilih unggah berkas atau tambahkan soal pada UH asal.');}
        $db->prepare("INSERT INTO ujian(pengajaran_id,nama_ujian,jenis_ujian,durasi_menit,waktu_mulai,waktu_selesai,acak_soal) VALUES(?,?,'Kuis',?,?,?,?)")->execute([$pid,trim($title),$source['durasi_menit'],str_replace('T',' ',$start).':00',str_replace('T',' ',$end).':00',$source['acak_soal']]);$child=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO ujian_harian_remedial(ujian_id,ujian_asal_id,guru_id) VALUES(?,?,?)')->execute([$child,$exam,$guru]);
        uh_save_settings($db,$child,$pid,['metode'=>$method,'penerima'=>'pilihan','siswa_ids'=>array_map('strval',$ids),'instruksi'=>$instructions]);$db->prepare('UPDATE ujian_harian_pengaturan SET nomor=? WHERE ujian_id=?')->execute([$source['nomor'],$child]);
        if($method==='cbt')$db->prepare('INSERT INTO soal_ujian(ujian_id,tipe_soal,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_jawaban,bobot) SELECT ?,tipe_soal,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_jawaban,bobot FROM soal_ujian WHERE ujian_id=?')->execute([$child,$exam]);
        uhn_log($db,$user,'Membuat remedial UH #'.$exam.' sebagai ujian #'.$child);$db->commit();return $child;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function uhn_sync(PDO $db,int $child,int $sid,float $grade): void {
    $q=$db->prepare('SELECT r.ujian_asal_id,r.guru_id,g.user_id FROM ujian_harian_remedial r JOIN guru g ON g.id=r.guru_id WHERE r.ujian_id=?');$q->execute([$child]);$link=$q->fetch();if(!$link)return;
    $db->prepare('SELECT id FROM ujian WHERE id=? FOR UPDATE')->execute([$link['ujian_asal_id']]);
    uhn_attempt($db,(int)$link['guru_id'],(int)$link['user_id'],(int)$link['ujian_asal_id'],$sid,$grade,'Hasil ujian remedial #'.$child,$child);
}
