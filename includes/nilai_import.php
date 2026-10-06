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
            $label=ni_name((string)$value);$key=match($label){'nama siswa','nama'=>'name','kelas'=>'class','asli'=>'original','nilai'=>'grade','penyesuaian'=>'adjusted','deskripsi capaian pembelajaran'=>'description','saran capaian pembelajaran'=>'advice',default=>null};
            if($key!==null){if(isset($map[$key])&&$map[$key]!==$col)throw new RuntimeException('Kolom '.$label.' ganda. Gunakan satu tabel pada sheet.');$map[$key]=$col;}
        }
        // The legacy workbook has a merged "Nilai" heading above Asli/Penyesuaian.
        $nextLabels=array_map(static fn($v)=>ni_name((string)$v),$rows[$r+1]??[]);
        if(isset($map['name'],$map['class'])&&(isset($map['original'])||(isset($map['grade'])&&!in_array('asli',$nextLabels,true)))){
            $sourceLabel=isset($map['original'])?'Asli':'Nilai';
            if(!isset($map['original']))$map['original']=$map['grade'];
            $start=$r;
            // A two-level header may place Penyesuaian next to Asli on this row.
            break;
        }
    }
    if($start===null)throw new RuntimeException('Header Nama Siswa, Kelas, dan Nilai (atau Asli) tidak ditemukan dalam 30 baris pertama.');
    $records=[];
    foreach($rows as $r=>$row){
        if($r<=$start)continue;
        $name=trim((string)($row[$map['name']]??''));$class=trim((string)($row[$map['class']]??''));
        $original=trim((string)($row[$map['original']]??''));$adjusted=isset($map['adjusted'])?trim((string)($row[$map['adjusted']]??'')):'';
        if($name===''&&$class===''&&$original===''&&$adjusted==='')continue;
        $records[]=['row'=>$r+1,'name'=>$name,'class'=>$class,'raw'=>$adjusted!==''?$adjusted:$original,'source'=>$adjusted!==''?'Penyesuaian':$sourceLabel,
            'description_text'=>isset($map['description'])?trim((string)($row[$map['description']]??'')):'',
            'advice_text'=>isset($map['advice'])?trim((string)($row[$map['advice']]??'')):''];
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

function ni_import_preview(PDO $db,array $context,array $records): array {
    $rows=ni_preview($context,$records);$old=[];
    $period=in_array($context['component']['nama_komponen'],['UTS','UAS'],true);
    if($period){
        $stmt=$db->prepare('SELECT siswa_id,deskripsi,saran FROM capaian_penilaian WHERE komponen_id=?');
        $stmt->execute([$context['component']['id']]);foreach($stmt->fetchAll() as $r)$old[(int)$r['siswa_id']]=$r;
    }
    foreach($rows as &$r){
        $r['score_ready']=$r['ready'];$r['description']=false;$r['advice']=false;
        if(!$period){
            if(($r['description_text']??'')!==''||($r['advice_text']??'')!=='')$r['status'].='; teks capaian diabaikan (khusus UTS/UAS)';
            continue;
        }
        if($r['student_id']===null)continue;
        try{
            foreach(['description'=>'deskripsi','advice'=>'saran'] as $key=>$column){
                $r[$key.'_text']=ni_narrative_text($r[$key.'_text']??'');
                $r[$key]=$r[$key.'_text']!==''&&trim($old[$r['student_id']][$column]??'')==='';
            }
        }catch(RuntimeException $e){$r['ready']=$r['score_ready']=$r['description']=$r['advice']=false;$r['status']='Dilewati: '.$e->getMessage();continue;}
        $r['ready']=$r['score_ready']||$r['description']||$r['advice'];
        if($r['description']||$r['advice'])$r['status'].='; capaian kosong akan diisi';
    }
    unset($r);return $rows;
}

// Called inside the owner's transaction; never updates numeric grades.
function ni_write_narrative(PDO $db,int $component,int $sid,string $description,string $advice,array $candidate,bool $replace=false): int {
    $stmt=$db->prepare('SELECT deskripsi,saran FROM capaian_penilaian WHERE komponen_id=? AND siswa_id=? FOR UPDATE');
    $stmt->execute([$component,$sid]);$old=$stmt->fetch();$sets=[];$args=[];
    foreach(['description'=>['deskripsi',$description],'advice'=>['saran',$advice]] as $key=>[$column,$text]){
        if(empty($candidate[$key])||$text==='')continue;
        $current=(string)($old[$column]??'');
        if($replace){
            if(!array_key_exists('old_'.$key,$candidate)||$current!==$candidate['old_'.$key])throw new RuntimeException('Capaian berubah sejak pratinjau. Tidak ada perubahan disimpan; buat pratinjau ulang.');
        }elseif(trim($current)!=='')continue;
        if($current===$text)continue;
        $sets[]=$column.'=?';$args[]=$text;
    }
    if(!$sets)return 0;
    if($old){$args[]=$component;$args[]=$sid;$db->prepare('UPDATE capaian_penilaian SET '.implode(',',$sets).' WHERE komponen_id=? AND siswa_id=?')->execute($args);}
    else $db->prepare('INSERT INTO capaian_penilaian(komponen_id,siswa_id,deskripsi,saran) VALUES(?,?,?,?)')->execute([$component,$sid,in_array('deskripsi=?',$sets,true)?$description:'',in_array('saran=?',$sets,true)?$advice:'']);
    return count($sets);
}
function ni_commit(PDO $db,int $guru,int $pid,int $component,array $candidates,array $expected=[]): array {
    $db->beginTransaction();$inserted=0;$skipped=0;$fields=0;
    try{
        $context=ni_context($db,$guru,$pid,$component,true);
        ni_snapshot($context,$expected);
        $students=[];foreach($context['students'] as $s)$students[(int)$s['id']]=ni_name($s['nama_lengkap']);
        $names=array_count_values(array_values($students));
        $insert=$db->prepare('INSERT INTO nilai_komponen(komponen_id,siswa_id,nilai) VALUES(?,?,?)');
        $history=$db->prepare('INSERT INTO riwayat_nilai(pengajaran_id,siswa_id,komponen_id,guru_id,nilai_lama,nilai_baru) VALUES(?,?,?,?,NULL,?)');
        foreach($candidates as $r){
            $sid=(int)$r['student_id'];$name=ni_name($r['name']);
            if(!isset($students[$sid])||$students[$sid]!==$name||($names[$name]??0)!==1||ni_class($r['class'])!==ni_class($context['nama_kelas'])){$skipped++;continue;}
            $changed=false;
            if(($r['score_ready']??true)&&!array_key_exists($sid,$context['scores'])){
                if(!is_numeric($r['value'])||!is_finite((float)$r['value'])||$r['value']<0||$r['value']>100)throw new RuntimeException('Nilai pratinjau tidak valid.');
                $didInsert=false;
                try{$insert->execute([$component,$sid,$r['value']]);$didInsert=true;}
                catch(PDOException $e){if(($e->errorInfo[1]??0)!==1062)throw $e;}
                if($didInsert){$history->execute([$pid,$sid,$component,$guru,$r['value']]);$context['scores'][$sid]=$r['value'];$inserted++;$changed=true;}
            }
            if(in_array($context['component']['nama_komponen'],['UTS','UAS'],true)&&(!empty($r['description'])||!empty($r['advice']))){
                $n=ni_write_narrative($db,$component,$sid,ni_narrative_text($r['description_text']??''),ni_narrative_text($r['advice_text']??''),$r);
                $fields+=$n;$changed=$changed||$n>0;
            }
            if(!$changed)$skipped++;
        }
        $db->commit();return ['inserted'=>$inserted,'skipped'=>$skipped,'fields'=>$fields];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function ni_narrative_text($text): string {
    if(!is_string($text))throw new RuntimeException('Deskripsi/saran tidak valid.');
    $text=trim($text);if(mb_strlen($text)>2000)throw new RuntimeException('Deskripsi/saran maksimal 2000 karakter.');return $text;
}
function ni_narrative_preview(PDO $db,array $context,string $description,string $advice,bool $replace=false): array {
    if(!in_array($context['component']['nama_komponen'],['UTS','UAS'],true))throw new RuntimeException('Capaian kelas hanya untuk UTS atau UAS.');
    $stmt=$db->prepare('SELECT siswa_id,deskripsi,saran FROM capaian_penilaian WHERE komponen_id=?');$stmt->execute([$context['component']['id']]);$old=[];
    foreach($stmt->fetchAll() as $row)$old[(int)$row['siswa_id']]=$row;
    $rows=[];foreach($context['students'] as $student){
        $sid=(int)$student['id'];$d=(string)($old[$sid]['deskripsi']??'');$a=(string)($old[$sid]['saran']??'');
        $rows[]=['student_id'=>$sid,'name'=>$student['nama_lengkap'],'old_description'=>$d,'old_advice'=>$a,
            'description'=>$description!==''&&($replace?$d!==$description:trim($d)===''),
            'advice'=>$advice!==''&&($replace?$a!==$advice:trim($a)==='')];
    }
    return $rows;
}
function ni_narrative_commit(PDO $db,int $guru,int $pid,int $component,string $description,string $advice,array $candidates,array $expected=[],bool $replace=false): array {
    $description=ni_narrative_text($description);$advice=ni_narrative_text($advice);
    if($description===''&&$advice==='')throw new RuntimeException('Isi deskripsi atau saran terlebih dahulu.');
    $db->beginTransaction();$changed=0;$fields=0;
    try{
        $context=ni_context($db,$guru,$pid,$component,true);
        ni_snapshot($context,$expected);
        if(!in_array($context['component']['nama_komponen'],['UTS','UAS'],true))throw new RuntimeException('Jenis capaian tidak valid.');
        $students=[];foreach($context['students'] as $s)$students[(int)$s['id']]=ni_name($s['nama_lengkap']);
        foreach($candidates as $r){
            $sid=(int)$r['student_id'];if(!isset($students[$sid])||$students[$sid]!==ni_name($r['name']))continue;
            $n=ni_write_narrative($db,$component,$sid,$description,$advice,$r,$replace);
            if($n){$changed++;$fields+=$n;}
        }
        $db->commit();return ['students'=>$changed,'fields'=>$fields];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
