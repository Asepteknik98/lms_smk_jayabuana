<?php
require_once __DIR__.'/rapor.php';
require_once __DIR__.'/rapor_ranking.php';

function rapor_print_incomplete(array $report): bool {
    if(!$report['mapel'])return true;
    foreach($report['mapel'] as $m)if($m['nilai_akhir']===null||trim($m['deskripsi']??'')===''||trim($m['saran']??'')==='')return true;
    return false;
}

function rapor_print_batch(PDO $db,int $kelas,string $tahun,string $semester,string $jenis): array {
    if($kelas<1||!preg_match('/^\d{4}\/\d{4}$/',$tahun)||!in_array($semester,['Ganjil','Genap'],true)||!in_array($jenis,['UTS','UAS'],true))throw new InvalidArgumentException('Pilih kelas dan periode rapor yang valid.');
    // Read one snapshot, including details, while teachers may be saving changes.
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT id FROM rapor WHERE kelas_id=? AND tahun_ajaran=? AND semester=? AND jenis=? ORDER BY nomor_absen IS NULL,nomor_absen,nama_siswa,id');
        $q->execute([$kelas,$tahun,$semester,$jenis]);$reports=[];$savedStudents=[];
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$r=rapor_load($db,(int)$id);if($r){$reports[]=$r;$savedStudents[(int)$r['siswa_id']]=true;}}
        $q=$db->prepare('SELECT id,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');$q->execute([$kelas]);$missing=[];
        $roster=$q->fetchAll();foreach($roster as $s)if(!isset($savedStudents[(int)$s['id']]))$missing[]=$s['nama_lengkap'];
        $ranking=rapor_ranking_calculate($reports,$roster);
        foreach($reports as &$report)$report['peringkat']=rapor_ranking_for_report($ranking,(int)$report['id']);unset($report);
        $db->commit();return ['reports'=>$reports,'missing'=>$missing];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function rapor_print_single(PDO $db,int $id): ?array {
    $db->beginTransaction();
    try{
        $report=rapor_load($db,$id);
        if($report){
            $report['peringkat']=(int)$report['kelas_id']>0?rapor_ranking_for_report(rapor_ranking_load($db,(int)$report['kelas_id'],$report['tahun_ajaran'],$report['semester'],$report['jenis']),$id):['peringkat'=>null,'peserta'=>0,'sementara'=>false,'masalah'=>['Kelas arsip tidak tersedia untuk perbandingan.']];
        }
        $db->commit();return $report;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
