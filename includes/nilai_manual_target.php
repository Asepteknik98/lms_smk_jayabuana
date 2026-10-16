<?php
require_once __DIR__.'/rekap_tugas_module.php';
require_once __DIR__.'/nilai_excel_reader.php';

function nm_create_target(PDO $db,int $guru,int $user,int $pid,string $kind,string $title,int $meeting,string $when): int {
    $title=trim($title);$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$when);
    if(!in_array($kind,['tugas','ujian'],true)||$title===''||mb_strlen($title)>200)throw new RuntimeException('Pilih jenis penilaian dan isi judul, maksimal 200 karakter.');
    if(!$date||$date->format('Y-m-d\TH:i')!==$when||$date->getTimestamp()>time())throw new RuntimeException('Tanggal penilaian manual harus sekarang atau sebelumnya.');
    // Zero denotes an offline task independent of any lesson or meeting.
    if($kind==='tugas'&&($meeting<0||$meeting>20))throw new RuntimeException('Pertemuan tugas tidak valid.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT p.id FROM pengajaran p JOIN guru g ON g.id=p.guru_id JOIN users u ON u.id=g.user_id AND u.role_id=2 AND u.is_active=1 WHERE p.id=? AND p.guru_id=? AND g.user_id=? FOR UPDATE');$q->execute([$pid,$guru,$user]);
        if(!$q->fetchColumn())throw new RuntimeException('Pengajaran bukan milik Anda.');
        if($kind==='tugas'){
            $db->prepare("INSERT INTO tugas(pengajaran_id,pertemuan_ke,judul,deskripsi,jenis_tugas,metode_pengumpulan,deadline) VALUES(?,?,?,?,'portofolio','offline',?)")->execute([$pid,$meeting,$title,'Penilaian tugas kelas/buku. Nilai diinput guru melalui Input Nilai Manual.',$date->format('Y-m-d H:i:s')]);
        }else{
            // A completed classroom exam is a grading target, never a new live online session.
            $db->prepare("INSERT INTO ujian(pengajaran_id,nama_ujian,jenis_ujian,durasi_menit,waktu_mulai,waktu_selesai,acak_soal) VALUES(?,?,'Kuis',1,?,?,0)")->execute([$pid,$title,$date->modify('-1 minute')->format('Y-m-d H:i:s'),$date->format('Y-m-d H:i:s')]);
        }
        $id=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)')->execute([$user,'Membuat target nilai manual: '.json_encode(['jenis'=>$kind,'target'=>$id,'pengajaran'=>$pid]),$_SERVER['REMOTE_ADDR']??'CLI',substr($_SERVER['HTTP_USER_AGENT']??'CLI',0,255)]);
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function nm_context(PDO $db,int $guru,int $pid,string $kind): array {
    if(!in_array($kind,['tugas','ujian'],true))throw new RuntimeException('Jenis penilaian tidak valid.');
    $q=$db->prepare('SELECT p.*,m.nama_mapel,k.nama_kelas FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.id=? AND p.guru_id=?');$q->execute([$pid,$guru]);$info=$q->fetch();
    if(!$info)throw new RuntimeException('Pengajaran bukan milik Anda.');
    $q=$db->prepare($kind==='tugas'?'SELECT id,judul,pertemuan_ke,deadline,(deadline<=NOW()) selesai FROM tugas WHERE pengajaran_id=? ORDER BY id':"SELECT id,nama_ujian judul,NULL pertemuan_ke,waktu_selesai deadline,(waktu_selesai<=NOW()) selesai FROM ujian WHERE pengajaran_id=? AND jenis_ujian='Kuis' ORDER BY id");$q->execute([$pid]);$targets=$q->fetchAll();
    foreach($targets as $i=>&$target){$target['nomor']=$i+1;$target['label']=($kind==='tugas'?'Tugas ':'Ujian Harian ').($i+1).' — '.$target['judul'];}unset($target);
    $q=$db->prepare('SELECT id,nis,nisn,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');$q->execute([$info['kelas_id']]);$students=$q->fetchAll();
    $q=$db->prepare($kind==='tugas'?'SELECT pt.siswa_id,pt.tugas_id target_id,pt.nilai,pt.catatan_guru catatan FROM pengumpulan_tugas pt JOIN tugas t ON t.id=pt.tugas_id WHERE t.pengajaran_id=?':"SELECT n.siswa_id,n.ujian_id target_id,n.nilai_total nilai,'' catatan FROM nilai_ujian n JOIN ujian u ON u.id=n.ujian_id WHERE u.pengajaran_id=? AND u.jenis_ujian='Kuis'");$q->execute([$pid]);$scores=[];foreach($q->fetchAll() as $row)$scores[(int)$row['siswa_id']][(int)$row['target_id']]=$row;
    $q=$db->prepare('SELECT pertemuan_ke,judul FROM materi WHERE pengajaran_id=? ORDER BY pertemuan_ke,id');$q->execute([$pid]);$meetings=[];foreach($q->fetchAll() as $row)$meetings[(int)$row['pertemuan_ke']][]=$row['judul'];
    return compact('info','targets','students','scores','meetings','kind','pid');
}

function nm_target(array $context,int $id): array {
    foreach($context['targets'] as $target)if((int)$target['id']===$id)return $target;
    throw new RuntimeException('Pilih target tugas atau ujian yang tersedia pada kelas ini.');
}

function nm_score(mixed $raw): ?float {
    if(!is_string($raw))throw new RuntimeException('Format nilai tidak valid.');
    $raw=str_replace(',','.',trim($raw));if($raw==='')return null;
    if(!preg_match('/^\d+(?:\.\d+)?$/D',$raw)||!is_finite((float)$raw)||(float)$raw>100)throw new RuntimeException('Nilai harus angka 0 sampai 100.');
    return round((float)$raw,2);
}

function nm_save(PDO $db,int $guru,int $user,int $pid,string $kind,int $id,array $values,array $notes=[]): array {
    if(!in_array($kind,['tugas','ujian'],true)||count($values)>5000)throw new RuntimeException('Format penilaian tidak valid.');
    $entries=[];
    foreach($values as $sid=>$raw){
        if(!preg_match('/^[1-9][0-9]*$/D',(string)$sid))throw new RuntimeException('ID siswa tidak valid.');
        $score=nm_score($raw);if($score===null)continue;
        $note=$notes[$sid]??'';if(!is_string($note)||mb_strlen($note)>1000)throw new RuntimeException('Catatan maksimal 1.000 karakter.');
        $entries[]=[(int)$sid,$score,trim($note)];
    }
    if(!$entries)throw new RuntimeException('Isi minimal satu nilai baru.');
    $db->beginTransaction();$saved=0;$skipped=0;
    try{
        foreach($entries as [$sid,$score,$note]){
            if($kind==='tugas'){
                rt_lock_pair($db,$guru,$user,$pid,$sid,$id);
                $q=$db->prepare('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=? AND siswa_id=? FOR UPDATE');$q->execute([$id,$sid]);$old=$q->fetch();
                if($old&&$old['nilai']!==null){$skipped++;continue;}
                rt_save($db,$guru,$user,$pid,$sid,$id,'mengerjakan',(string)$score,$note);
            }else{
                $q=$db->prepare("SELECT u.id,(u.waktu_selesai<=NOW()) selesai FROM ujian u JOIN pengajaran p ON p.id=u.pengajaran_id JOIN guru g ON g.id=p.guru_id JOIN users a ON a.id=g.user_id AND a.role_id=2 AND a.is_active=1 JOIN siswa s ON s.kelas_id=p.kelas_id WHERE u.id=? AND p.id=? AND p.guru_id=? AND g.user_id=? AND s.id=? AND u.jenis_ujian='Kuis' FOR UPDATE");$q->execute([$id,$pid,$guru,$user,$sid]);$exam=$q->fetch();
                if(!$exam)throw new RuntimeException('Ujian atau siswa bukan bagian kelas yang Anda ampu.');
                if(!$exam['selesai'])throw new RuntimeException('Nilai ujian manual dapat diisi setelah jadwal ujian berakhir.');
                $q=$db->prepare('SELECT nilai_total FROM nilai_ujian WHERE ujian_id=? AND siswa_id=? FOR UPDATE');$q->execute([$id,$sid]);$old=$q->fetch();
                if($old&&$old['nilai_total']!==null){$skipped++;continue;}
                $q=$db->prepare('SELECT status FROM sesi_ujian WHERE ujian_id=? AND siswa_id=? FOR UPDATE');$q->execute([$id,$sid]);
                if($q->fetchColumn()==='Berlangsung')throw new RuntimeException('Masih ada sesi ujian siswa yang berlangsung. Selesaikan pemeriksaan sesi sebelum mengisi nilai manual.');
                $db->prepare('INSERT INTO nilai_ujian(ujian_id,siswa_id,nilai_total,selesai_pada) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE nilai_total=VALUES(nilai_total),selesai_pada=NOW()')->execute([$id,$sid,$score]);
                $db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)')->execute([$user,'Input nilai ujian manual: '.json_encode(['ujian'=>$id,'siswa'=>$sid,'nilai'=>$score]),$_SERVER['REMOTE_ADDR']??'CLI',substr($_SERVER['HTTP_USER_AGENT']??'CLI',0,255)]);
            }
            $saved++;
        }
        $db->commit();return compact('saved','skipped');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function nm_template(array $context,int $id): string {
    $target=nm_target($context,$id);$x=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
    $rows=[['Pengajaran ID',(string)$context['pid']],['Jenis',$context['kind'],'Target ID',(string)$id],['ID Siswa','NIS','NISN','Nama Siswa',$target['label']],['PETUNJUK','Isi nilai 0–100; kosong dilewati. Nilai lama tidak ditimpa.']];
    foreach($context['students'] as $s)$rows[]=[(string)$s['id'],(string)($s['nis']??''),(string)($s['nisn']??''),$s['nama_lengkap'],''];
    $xml='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Nilai"><Table ss:DefaultColumnWidth="130">';
    foreach($rows as $row){$xml.='<Row>';foreach($row as $v)$xml.='<Cell><Data ss:Type="String">'.$x($v).'</Data></Cell>';$xml.='</Row>';}
    return $xml.'</Table></Worksheet></Workbook>';
}

function nm_preview(array $context,int $id,array $rows): array {
    $target=nm_target($context,$id);
    if(($rows[0][0]??'')!=='Pengajaran ID'||(string)($rows[0][1]??'')!==(string)$context['pid']||($rows[1][0]??'')!=='Jenis'||($rows[1][1]??'')!==$context['kind']||($rows[1][2]??'')!=='Target ID'||(string)($rows[1][3]??'')!==(string)$id)throw new RuntimeException('Template tidak sesuai kelas, jenis nilai, atau target terpilih.');
    if(($rows[2]??[])!==['ID Siswa','NIS','NISN','Nama Siswa',$target['label']])throw new RuntimeException('Kolom template berubah. Unduh template untuk target terpilih.');
    if(($rows[3][0]??'')!=='PETUNJUK')throw new RuntimeException('Baris petunjuk template berubah. Gunakan template resmi tanpa menghapus baris.');
    $students=[];foreach($context['students'] as $s)$students[(int)$s['id']]=$s;
    $result=[];$used=[];
    foreach($rows as $i=>$row){
        if($i<4||!array_filter($row,static fn($v)=>trim((string)$v)!==''))continue;
        $sid=(string)($row[0]??'');if(!preg_match('/^[1-9][0-9]*$/D',$sid)||!isset($students[(int)$sid])||isset($used[$sid]))throw new RuntimeException('ID siswa tidak sesuai atau berulang pada baris '.($i+1).'.');$used[$sid]=true;
        foreach($row as $col=>$value)if($col>4&&trim((string)$value)!=='')throw new RuntimeException('Ada nilai di luar kolom target.');
        $score=nm_score((string)($row[4]??''));if($score===null)continue;
        $old=$context['scores'][(int)$sid][$id]['nilai']??null;
        $result[]=['sid'=>(int)$sid,'nama'=>$students[(int)$sid]['nama_lengkap'],'score'=>$score,'ready'=>$old===null,'reason'=>$old===null?'Siap disimpan':'Dilewati: sudah dinilai'];
        if(count($result)>5000)throw new RuntimeException('Maksimal 5.000 nilai per impor.');
    }
    if(!$result)throw new RuntimeException('Tidak ada nilai yang diisi.');return $result;
}
