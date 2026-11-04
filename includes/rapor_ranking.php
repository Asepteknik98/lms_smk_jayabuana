<?php
require_once __DIR__.'/rapor.php';

// Compare saved academic scores only. Attendance, extracurriculars and notes do not add points.
function rapor_ranking_calculate(array $reports,array $students): array {
    $entries=[];$saved=[];$subjectSets=[];$structuralIssue=false;
    foreach($reports as $report){
        $sid=(int)$report['siswa_id'];$saved[$sid]=true;$subjects=[];$problems=[];$sum=0;$filled=0;$rows=$report['mapel']??[];
        foreach($rows as $row){
            $name=trim((string)($row['nama_mapel']??''));$key=preg_replace('/[^\p{L}\p{N}]/u','',mb_strtolower($name));
            if($key==='')$problems[]='Nama mata pelajaran belum lengkap.';
            elseif(isset($subjects[$key])){$problems[]='Mata pelajaran '.$name.' tercantum lebih dari sekali.';$structuralIssue=true;}
            else $subjects[$key]=$name;
            $score=$row['nilai_akhir']??null;
            if($score!==null&&is_numeric($score)&&is_finite((float)$score)&&(float)$score>=0&&(float)$score<=100){$sum+=(int)round((float)$score*100);$filled++;}
        }
        if(!$rows)$problems[]='Belum ada mata pelajaran tersimpan.';
        elseif($filled!==count($rows))$problems[]='Nilai belum lengkap atau belum valid ('.$filled.'/'.count($rows).' mapel).';
        $keys=array_keys($subjects);sort($keys,SORT_STRING);if($keys)$subjectSets[]=implode('|',$keys);
        $complete=(bool)$rows&&$filled===count($rows);
        $entries[]=['id'=>(int)$report['id'],'siswa_id'=>$sid,'nama_siswa'=>$report['nama_siswa'],'jumlah_mapel'=>count($rows),'nilai_lengkap'=>$filled,'total'=>$complete?$sum/100:null,'rata_rata'=>$complete?round($sum/count($rows),0,PHP_ROUND_HALF_UP)/100:null,'average_key'=>$complete?(int)round($sum/count($rows),0,PHP_ROUND_HALF_UP):null,'peringkat'=>null,'masalah'=>array_values(array_unique($problems))];
    }
    foreach($students as $student)if(!isset($saved[(int)$student['id']]))$entries[]=['id'=>0,'siswa_id'=>(int)$student['id'],'nama_siswa'=>$student['nama_lengkap'],'jumlah_mapel'=>0,'nilai_lengkap'=>0,'total'=>null,'rata_rata'=>null,'average_key'=>null,'peringkat'=>null,'masalah'=>['Rapor siswa belum tersimpan pada kelas dan periode ini.']];
    $comparable=!$structuralIssue&&count(array_unique($subjectSets))<=1;
    if(!$comparable){foreach($entries as &$entry)if($entry['id'])$entry['masalah'][]='Susunan mata pelajaran antar siswa belum seragam; periksa sebelum menentukan peringkat.';unset($entry);}
    usort($entries,static function(array $a,array $b): int {
        $aEligible=!$a['masalah'];$bEligible=!$b['masalah'];if($aEligible!==$bEligible)return $aEligible?-1:1;
        if($aEligible&&$a['average_key']!==$b['average_key'])return $b['average_key']<=>$a['average_key'];
        return strnatcasecmp($a['nama_siswa'],$b['nama_siswa'])?:($a['siswa_id']<=>$b['siswa_id']);
    });
    $previous=null;$position=0;$rank=0;$eligible=0;
    foreach($entries as &$entry){
        if($entry['masalah'])continue;
        if($previous===null||$previous!==$entry['average_key'])$rank=$position+1;
        $entry['peringkat']=$rank;$previous=$entry['average_key'];$position++;$eligible++;
    }unset($entry);
    return ['entries'=>$entries,'total_siswa'=>count($entries),'layak'=>$eligible,'sementara'=>$eligible<count($entries),'mapel_seragam'=>$comparable];
}
function rapor_ranking_load(PDO $db,int $kelas,string $tahun,string $semester,string $jenis): array {
    if($kelas<1||!preg_match('/^\d{4}\/\d{4}$/',$tahun)||!in_array($semester,['Ganjil','Genap'],true)||!in_array($jenis,['UTS','UAS'],true))throw new InvalidArgumentException('Pilih kelas dan periode yang valid untuk laporan peringkat.');
    $ownsTransaction=!$db->inTransaction();if($ownsTransaction)$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id FROM rapor WHERE kelas_id=? AND tahun_ajaran=? AND semester=? AND jenis=? ORDER BY id');$q->execute([$kelas,$tahun,$semester,$jenis]);$reports=[];
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$report=rapor_load($db,(int)$id);if($report)$reports[]=$report;}
        $q=$db->prepare('SELECT id,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');$q->execute([$kelas]);
        $result=rapor_ranking_calculate($reports,$q->fetchAll());$result['nama_kelas']=$reports[0]['nama_kelas']??'';
        if($ownsTransaction)$db->commit();return $result;
    }catch(Throwable $e){if($ownsTransaction&&$db->inTransaction())$db->rollBack();throw $e;}
}
function rapor_ranking_for_report(array $ranking,int $id): array {
    foreach($ranking['entries'] as $entry)if($entry['id']===$id)return ['peringkat'=>$entry['peringkat'],'peserta'=>$ranking['total_siswa'],'sementara'=>$ranking['sementara'],'masalah'=>$entry['masalah']];
    return ['peringkat'=>null,'peserta'=>$ranking['total_siswa'],'sementara'=>true,'masalah'=>['Rapor belum dapat dibandingkan pada kelas ini.']];
}
