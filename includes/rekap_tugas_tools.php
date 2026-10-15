<?php
require_once __DIR__.'/nilai_excel_reader.php';

function rt_import_template(array $view): string {
    $tasks=$view['visibleTasks'];$data=$view['data'];$info=$data['info'];
    if(!$info || !$tasks)throw new RuntimeException('Belum ada tugas untuk dibuatkan template.');
    if(count($tasks)>96)throw new RuntimeException('Template maksimal 96 tugas. Pilih Per Tugas untuk mengunduh template yang lebih kecil.');
    $x=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
    $rows=[['Pengajaran ID',(string)$view['pid']],['Kelas',$info['nama_kelas'],'Mata Pelajaran',$info['nama_mapel']],['ID Siswa','NIS','NISN','Nama Siswa']];
    foreach($tasks as $task)$rows[2][]='Tugas '.($task['nomor']??1).' [ID:'.$task['id'].']';
    foreach($data['students'] as $s)$rows[]=array_merge([(string)$s['id'],(string)($s['nis']??''),(string)($s['nisn']??''),$s['nama_lengkap']],array_fill(0,count($tasks),''));
    $xml='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="Heading"><Font ss:Bold="1"/><Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/></Style></Styles><Worksheet ss:Name="Nilai Tugas"><Table ss:DefaultColumnWidth="105">';
    foreach($rows as $index=>$row){$xml.='<Row>';foreach($row as $value)$xml.='<Cell'.($index===2?' ss:StyleID="Heading"':'').'><Data ss:Type="String">'.$x($value).'</Data></Cell>';$xml.='</Row>';}
    $xml.='</Table></Worksheet><Worksheet ss:Name="Petunjuk"><Table>';
    $notes=[['Isi nilai 0 sampai 100; sel kosong tidak diubah.'],['Nilai yang sudah terisi dilewati. Perbaikan nilai dapat dilakukan melalui Remedial.'],['Jangan mengubah Pengajaran ID, ID Siswa, atau header ID tugas.'],['Nomor tugas','Judul','ID Tugas']];
    foreach($tasks as $t)$notes[]=['Tugas '.($t['nomor']??1),$t['judul'],(string)$t['id']];
    foreach($notes as $row){$xml.='<Row>';foreach($row as $value)$xml.='<Cell><Data ss:Type="String">'.$x($value).'</Data></Cell>';$xml.='</Row>';}
    return $xml.'</Table></Worksheet></Workbook>';
}

function rt_parse_import(array $data,array $rows,int $pid): array {
    if(trim((string)($rows[0][0]??''))!=='Pengajaran ID' || trim((string)($rows[0][1]??''))!==(string)$pid)throw new RuntimeException('Template bukan untuk kelas dan mata pelajaran yang dipilih. Unduh template yang sesuai.');
    $header=$rows[2]??[];
    if(array_slice($header,0,4)!==['ID Siswa','NIS','NISN','Nama Siswa'])throw new RuntimeException('Header template berubah. Gunakan template resmi.');
    $taskMap=[];foreach($data['tasks'] as $t)$taskMap[(int)$t['id']]=$t;
    $studentMap=[];foreach($data['students'] as $s)$studentMap[(int)$s['id']]=$s;
    $columns=[];$usedTasks=[];
    foreach($header as $col=>$label){
        if($col<4)continue;
        if(!preg_match('/^Tugas [1-9][0-9]* \[ID:([1-9][0-9]*)\]$/D',(string)$label,$match))throw new RuntimeException('Header tugas tidak valid. Gunakan template resmi.');
        $tid=(int)$match[1];
        if(!isset($taskMap[$tid]) || isset($usedTasks[$tid]))throw new RuntimeException('Tugas tidak sesuai pengajaran atau kolom tugas berulang.');
        $columns[$col]=$tid;$usedTasks[$tid]=true;
    }
    if(!$columns)throw new RuntimeException('Tidak ada kolom tugas.');
    $result=[];$usedStudents=[];
    foreach($rows as $index=>$row){
        if($index<3)continue;
        if(!array_filter($row,static fn($v)=>trim((string)$v)!==''))continue;
        $rawId=trim((string)($row[0]??''));$sid=(int)$rawId;
        if(!preg_match('/^[1-9][0-9]*$/D',$rawId) || !isset($studentMap[$sid]))throw new RuntimeException('Baris '.($index+1).': siswa tidak ditemukan pada kelas ini.');
        if(isset($usedStudents[$sid]))throw new RuntimeException('Baris '.($index+1).': ID siswa berulang.');
        $usedStudents[$sid]=true;
        foreach($row as $col=>$value)if($col>=4 && !isset($columns[$col]) && trim((string)$value)!=='')throw new RuntimeException('Baris '.($index+1).': terdapat nilai di luar kolom template.');
        foreach($columns as $col=>$tid){
            $raw=trim((string)($row[$col]??''));if($raw==='')continue;
            $normalized=str_replace(',','.',$raw);$valid=(bool)preg_match('/^(?:\d+)(?:\.\d+)?$/D',$normalized) && is_finite((float)$normalized) && (float)$normalized<=100;
            $old=$studentMap[$sid]['cells'][$tid]['nilai'];
            // An automatic zero for non-submission is not a saved grade.
            $saved=$studentMap[$sid]['cells'][$tid]['pengumpulan_id']>0 && $studentMap[$sid]['cells'][$tid]['status']==='mengerjakan';
            $ready=$valid && !$saved;
            $result[]=['row'=>$index+1,'sid'=>$sid,'tid'=>$tid,'nama'=>$studentMap[$sid]['nama_lengkap'],
                'tugas'=>'Tugas '.($taskMap[$tid]['nomor']??1).' · '.$taskMap[$tid]['judul'],'raw'=>$raw,
                'score'=>$valid?round((float)$normalized,2):null,'old'=>$saved?$old:null,'ready'=>$ready,'invalid'=>!$valid,
                'reason'=>!$valid?'Nilai harus 0–100':($saved?'Dilewati: nilai sudah tersimpan ('.$old.')':'Siap diimpor')];
            if(count($result)>5000)throw new RuntimeException('Maksimal 5.000 nilai per impor. Pisahkan file menjadi beberapa bagian.');
        }
    }
    if(!$result)throw new RuntimeException('Tidak ada nilai yang diisi pada template.');
    return $result;
}

function rt_import_apply(PDO $db,int $guru,int $user,int $pid,array $plan): int {
    if(($plan['guru']??0)!==$guru || ($plan['user']??0)!==$user || ($plan['pid']??0)!==$pid || ($plan['expires']??0)<time())throw new RuntimeException('Pratinjau impor kedaluwarsa atau tidak sesuai. Unggah ulang file.');
    if(array_filter($plan['rows'],static fn($row)=>$row['invalid']))throw new RuntimeException('Perbaiki nilai yang tidak valid dan unggah ulang file.');
    $ready=array_values(array_filter($plan['rows'],static fn($row)=>$row['ready']));
    if(!$ready)throw new RuntimeException('Tidak ada nilai baru yang dapat diimpor.');
    $db->beginTransaction();
    try{
        foreach($ready as $row){
            rt_lock_pair($db,$guru,$user,$pid,$row['sid'],$row['tid']);
            $q=$db->prepare('SELECT nilai,catatan_guru FROM pengumpulan_tugas WHERE tugas_id=? AND siswa_id=? FOR UPDATE');$q->execute([$row['tid'],$row['sid']]);$old=$q->fetch();
            if($old && $old['nilai']!==null)throw new RuntimeException('Nilai berubah setelah pratinjau. Tidak ada nilai yang diimpor; unggah ulang file.');
            rt_save($db,$guru,$user,$pid,$row['sid'],$row['tid'],'mengerjakan',(string)$row['score'],(string)($old['catatan_guru']??'Diimpor dari template kelas'));
        }
        $db->commit();return count($ready);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function rt_remedial_save(PDO $db,int $guru,int $user,int $pid,int $tid,array $scores,array $notes): int {
    if(!rt_remedial_ready($db))throw new RuntimeException('Remedial belum diaktifkan. Hubungi admin.');
    $entries=[];
    foreach($scores as $sid=>$raw){
        if(!is_string($raw) || !preg_match('/^[1-9][0-9]*$/D',(string)$sid))throw new RuntimeException('Format nilai remedial tidak valid.');
        $raw=trim($raw);if($raw==='')continue;
        $raw=str_replace(',','.',$raw);
        if(!preg_match('/^\d+(?:\.\d+)?$/D',$raw) || !is_finite((float)$raw) || (float)$raw>100)throw new RuntimeException('Nilai remedial harus angka 0 sampai 100.');
        $note=$notes[$sid]??'';
        if(!is_string($note) || mb_strlen(trim($note))>1000)throw new RuntimeException('Catatan remedial maksimal 1.000 karakter.');
        $entries[]=[(int)$sid,round((float)$raw,2),trim($note)];
    }
    if(!$entries || count($entries)>5000)throw new RuntimeException('Isi minimal satu nilai remedial, maksimal 5.000 siswa.');
    $db->beginTransaction();
    try{
        foreach($entries as [$sid,$grade,$note]){
            $task=rt_lock_pair($db,$guru,$user,$pid,$sid,$tid);
            if(!$task['selesai'])throw new RuntimeException('Remedial tersedia setelah tenggat tugas berakhir.');
            $q=$db->prepare('SELECT nilai,catatan_guru FROM pengumpulan_tugas WHERE tugas_id=? AND siswa_id=? FOR UPDATE');$q->execute([$tid,$sid]);$old=$q->fetch();
            if($old && $old['nilai']===null)throw new RuntimeException('Masih ada tugas yang belum dinilai. Isi nilai awal siswa terlebih dahulu.');
            $before=$old?(float)$old['nilai']:0.0;$initial=$before;$final=max($before,$grade);
            $q=$db->prepare('SELECT nilai_awal,nilai_remedial FROM remedial_tugas WHERE tugas_id=? AND siswa_id=? ORDER BY id FOR UPDATE');$q->execute([$tid,$sid]);$history=$q->fetchAll();
            if($history)$initial=(float)$history[0]['nilai_awal'];
            foreach($history as $attempt)$final=max($final,(float)$attempt['nilai_awal'],(float)$attempt['nilai_remedial']);
            $db->prepare('INSERT INTO remedial_tugas(tugas_id,siswa_id,guru_id,nilai_awal,nilai_sebelum,nilai_remedial,nilai_akhir,catatan) VALUES(?,?,?,?,?,?,?,?)')->execute([$tid,$sid,$guru,$initial,$before,$grade,$final,$note]);
            rt_save($db,$guru,$user,$pid,$sid,$tid,'mengerjakan',(string)$final,$note!==''?$note:(string)($old['catatan_guru']??''));
        }
        $db->commit();return count($entries);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
