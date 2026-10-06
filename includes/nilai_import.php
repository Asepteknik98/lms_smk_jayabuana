<?php
function ni_name(string $value): string {
    return mb_strtolower(trim(preg_replace('/[\s\p{Z}]+/u',' ',$value)),'UTF-8');
}
function ni_class(string $value): string {
    $v=mb_strtoupper(trim(preg_replace('/[\s\p{Z}_-]+/u',' ',$value)),'UTF-8');
    $parts=explode(' ',$v);$roman=['X'=>'10','XI'=>'11','XII'=>'12'];
    if(isset($roman[$parts[0]])){$grade=$roman[$parts[0]];array_shift($parts);if(($parts[0]??'')===$grade)array_shift($parts);array_unshift($parts,$grade);}
    return implode('',$parts);
}
function ni_allowed(): array { return ['Tugas Harian','Ulangan Harian','UTS','UAS','Ujian Praktik']; }

function ni_context(PDO $db,int $guru,int $pid,int $component,bool $lock=false): array {
    $stmt=$db->prepare('SELECT p.id,p.kelas_id,p.semester,p.tahun_ajaran,k.nama_kelas,m.nama_mapel FROM pengajaran p JOIN kelas k ON k.id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id WHERE p.id=? AND p.guru_id=?'.($lock?' FOR UPDATE':''));
    $stmt->execute([$pid,$guru]);$info=$stmt->fetch();
    if(!$info)throw new RuntimeException('Pengajaran tidak dapat diakses.');
    $stmt=$db->prepare('SELECT id,nama_komponen FROM komponen_penilaian WHERE id=? AND pengajaran_id=?'.($lock?' FOR UPDATE':''));$stmt->execute([$component,$pid]);$c=$stmt->fetch();
    if(!$c||!in_array($c['nama_komponen'],ni_allowed(),true))throw new RuntimeException('Komponen tidak valid. Kehadiran tidak dapat diimpor.');
    $info['component']=$c;
    $stmt=$db->prepare('SELECT id,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id'.($lock?' FOR UPDATE':''));$stmt->execute([$info['kelas_id']]);$info['students']=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT siswa_id,nilai FROM nilai_komponen WHERE komponen_id=?'.($lock?' FOR UPDATE':''));$stmt->execute([$component]);$info['scores']=[];
    foreach($stmt->fetchAll() as $r)$info['scores'][(int)$r['siswa_id']]=$r['nilai'];
    return $info;
}

function ni_excel_records(array $rows): array {
    $map=[];$start=null;
    foreach($rows as $r=>$row){
        if($r>30)break;
        foreach($row as $col=>$value){
            $label=ni_name((string)$value);$key=match($label){'nama siswa','nama'=>'name','kelas'=>'class','asli'=>'original','penyesuaian'=>'adjusted',default=>null};
            if($key!==null){if(isset($map[$key])&&$map[$key]!==$col)throw new RuntimeException('Kolom '.$label.' ganda. Gunakan satu tabel pada sheet.');$map[$key]=$col;}
        }
        if(isset($map['name'],$map['class'],$map['original'])){
            $start=$r;
            // A two-level header may place Penyesuaian next to Asli on this row.
            break;
        }
    }
    if($start===null)throw new RuntimeException('Header Nama Siswa, Kelas, dan Asli tidak ditemukan dalam 30 baris pertama.');
    $records=[];
    foreach($rows as $r=>$row){
        if($r<=$start)continue;
        $name=trim((string)($row[$map['name']]??''));$class=trim((string)($row[$map['class']]??''));
        $original=trim((string)($row[$map['original']]??''));$adjusted=isset($map['adjusted'])?trim((string)($row[$map['adjusted']]??'')):'';
        if($name===''&&$class===''&&$original===''&&$adjusted==='')continue;
        $records[]=['row'=>$r+1,'name'=>$name,'class'=>$class,'raw'=>$adjusted!==''?$adjusted:$original,'source'=>$adjusted!==''?'Penyesuaian':'Asli'];
    }
    if(!$records)throw new RuntimeException('Tidak ada baris siswa pada sheet ini.');
    return $records;
}

function ni_preview(array $context,array $records): array {
    $names=[];$counts=[];
    foreach($context['students'] as $s)$names[ni_name($s['nama_lengkap'])][]=(int)$s['id'];
    $class=ni_class($context['nama_kelas']);
    foreach($records as $r)if(ni_class($r['class'])===$class){$key=ni_name($r['name']);$counts[$key]=($counts[$key]??0)+1;}
    $result=[];
    foreach($records as $r){
        $key=ni_name($r['name']);$r['student_id']=null;$r['value']=null;$r['ready']=false;
        if(ni_class($r['class'])!==$class)$reason='Dilewati: kelas berbeda atau kosong';
        elseif($key===''||!isset($names[$key]))$reason='Dilewati: nama tidak ditemukan';
        elseif(count($names[$key])!==1)$reason='Dilewati: nama siswa ganda di LMS';
        elseif(($counts[$key]??0)>1)$reason='Dilewati: nama berulang di Excel';
        else{
            $r['student_id']=$names[$key][0];
            if(array_key_exists($r['student_id'],$context['scores']))$reason='Dilewati: nilai sudah tersimpan ('.$context['scores'][$r['student_id']].')';
            elseif($r['raw']==='')$reason='Dilewati: nilai kosong';
            else{
                $number=str_replace(',','.',$r['raw']);
                if(!preg_match('/^\d+(?:\.\d+)?$/D',$number)||!is_finite((float)$number)||(float)$number>100)$reason='Dilewati: nilai harus angka 0 sampai 100';
                else{$r['value']=round((float)$number,2);$r['ready']=true;$reason='Siap diimpor';}
            }
        }
        $r['status']=$reason;$result[]=$r;
    }
    return $result;
}

function ni_snapshot(array $context,array $expected): void {
    if(!$expected)return;
    if($context['component']['nama_komponen']!==$expected['component_name']||$context['kelas_id']!=$expected['kelas_id']||$context['semester']!==$expected['semester']||$context['tahun_ajaran']!==$expected['tahun_ajaran'])throw new RuntimeException('Pengajaran berubah sejak pratinjau. Buat pratinjau ulang.');
}
function ni_commit(PDO $db,int $guru,int $pid,int $component,array $candidates,array $expected=[]): array {
    $db->beginTransaction();$inserted=0;$skipped=0;
    try{
        $context=ni_context($db,$guru,$pid,$component,true);
        ni_snapshot($context,$expected);
        $students=[];foreach($context['students'] as $s)$students[(int)$s['id']]=ni_name($s['nama_lengkap']);
        $names=array_count_values(array_values($students));
        $insert=$db->prepare('INSERT INTO nilai_komponen(komponen_id,siswa_id,nilai) VALUES(?,?,?)');
        $history=$db->prepare('INSERT INTO riwayat_nilai(pengajaran_id,siswa_id,komponen_id,guru_id,nilai_lama,nilai_baru) VALUES(?,?,?,?,NULL,?)');
        foreach($candidates as $r){
            $sid=(int)$r['student_id'];$name=ni_name($r['name']);
            if(!isset($students[$sid])||$students[$sid]!==$name||($names[$name]??0)!==1||ni_class($r['class'])!==ni_class($context['nama_kelas'])||array_key_exists($sid,$context['scores'])){$skipped++;continue;}
            if(!is_numeric($r['value'])||!is_finite((float)$r['value'])||$r['value']<0||$r['value']>100)throw new RuntimeException('Nilai pratinjau tidak valid.');
            try{$insert->execute([$component,$sid,$r['value']]);}
            catch(PDOException $e){if(($e->errorInfo[1]??0)===1062){$skipped++;continue;}throw $e;}
            $history->execute([$pid,$sid,$component,$guru,$r['value']]);$context['scores'][$sid]=$r['value'];$inserted++;
        }
        $db->commit();return ['inserted'=>$inserted,'skipped'=>$skipped];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function ni_narrative_text($text): string {
    if(!is_string($text))throw new RuntimeException('Deskripsi/saran tidak valid.');
    $text=trim($text);if(mb_strlen($text)>2000)throw new RuntimeException('Deskripsi/saran maksimal 2000 karakter.');return $text;
}
function ni_narrative_preview(PDO $db,array $context,string $description,string $advice): array {
    if(!in_array($context['component']['nama_komponen'],['UTS','UAS'],true))throw new RuntimeException('Capaian kelas hanya untuk UTS atau UAS.');
    $stmt=$db->prepare('SELECT siswa_id,deskripsi,saran FROM capaian_penilaian WHERE komponen_id=?');$stmt->execute([$context['component']['id']]);$old=[];
    foreach($stmt->fetchAll() as $row)$old[(int)$row['siswa_id']]=$row;
    $rows=[];foreach($context['students'] as $student){$sid=(int)$student['id'];$rows[]=['student_id'=>$sid,'name'=>$student['nama_lengkap'],'description'=>$description!==''&&trim($old[$sid]['deskripsi']??'')==='','advice'=>$advice!==''&&trim($old[$sid]['saran']??'')===''];}
    return $rows;
}
function ni_narrative_commit(PDO $db,int $guru,int $pid,int $component,string $description,string $advice,array $candidates,array $expected=[]): array {
    $description=ni_narrative_text($description);$advice=ni_narrative_text($advice);
    if($description===''&&$advice==='')throw new RuntimeException('Isi deskripsi atau saran terlebih dahulu.');
    $db->beginTransaction();$changed=0;$fields=0;
    try{
        $context=ni_context($db,$guru,$pid,$component,true);
        ni_snapshot($context,$expected);
        if(!in_array($context['component']['nama_komponen'],['UTS','UAS'],true))throw new RuntimeException('Jenis capaian tidak valid.');
        $students=[];foreach($context['students'] as $s)$students[(int)$s['id']]=ni_name($s['nama_lengkap']);
        $old=$db->prepare('SELECT deskripsi,saran FROM capaian_penilaian WHERE komponen_id=? AND siswa_id=? FOR UPDATE');
        foreach($candidates as $r){
            $sid=(int)$r['student_id'];if(!isset($students[$sid])||$students[$sid]!==ni_name($r['name']))continue;
            $old->execute([$component,$sid]);$existing=$old->fetch();
            $fillDescription=!empty($r['description'])&&$description!==''&&trim($existing['deskripsi']??'')==='';
            $fillAdvice=!empty($r['advice'])&&$advice!==''&&trim($existing['saran']??'')==='';
            if(!$fillDescription&&!$fillAdvice)continue;
            if($existing){
                $sets=[];$args=[];
                if($fillDescription){$sets[]='deskripsi=?';$args[]=$description;}
                if($fillAdvice){$sets[]='saran=?';$args[]=$advice;}
                $args[]=$component;$args[]=$sid;
                $db->prepare('UPDATE capaian_penilaian SET '.implode(',',$sets).' WHERE komponen_id=? AND siswa_id=?')->execute($args);
            }else{
                $db->prepare('INSERT INTO capaian_penilaian(komponen_id,siswa_id,deskripsi,saran) VALUES(?,?,?,?)')->execute([$component,$sid,$fillDescription?$description:'',$fillAdvice?$advice:'']);
            }
            $changed++;$fields+=(int)$fillDescription+(int)$fillAdvice;
        }
        $db->commit();return ['students'=>$changed,'fields'=>$fields];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
