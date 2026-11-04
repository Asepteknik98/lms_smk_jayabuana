<?php
require_once __DIR__.'/rapor.php';
function rapor_judul_ready(PDO $db): bool {
    return (bool)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rapor_pengaturan_judul'")->fetchColumn();
}
function rapor_judul_load(PDO $db,int $kelas,string $tahun,string $semester,string $jenis): array {
    $q=$db->prepare('SELECT judul,penilaian,konsentrasi FROM rapor_pengaturan_judul WHERE kelas_id=? AND tahun_ajaran=? AND semester=? AND jenis=?');$q->execute([$kelas,$tahun,$semester,$jenis]);return $q->fetch()?:[];
}
function rapor_judul_save(PDO $db,array $data,int $user): void {
    $kelas=filter_var($data['kelas_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $tahun=rapor_text($data,'tahun_ajaran',10);$semester=rapor_text($data,'semester',10);$jenis=rapor_text($data,'jenis',3);
    if(!$kelas||!preg_match('/^\d{4}\/\d{4}$/',$tahun)||!in_array($semester,['Ganjil','Genap'],true)||!in_array($jenis,['UTS','UAS'],true))throw new RuntimeException('Kelas atau periode tidak valid.');
    $q=$db->prepare('SELECT id FROM kelas WHERE id=?');$q->execute([$kelas]);if(!$q->fetchColumn())throw new RuntimeException('Kelas tidak ditemukan.');
    $ownsTransaction=!$db->inTransaction();if($ownsTransaction)$db->beginTransaction();
    try{
    rapor_admin_assert_open($db,$kelas,$tahun,$semester,$jenis);
    $previous=rapor_judul_load($db,$kelas,$tahun,$semester,$jenis);
    $values=[];foreach(['judul'=>160,'penilaian'=>160,'konsentrasi'=>120] as $key=>$max){$v=rapor_text($data,$key,$max);if(preg_match('/[\x00-\x1F\x7F]/u',$v))throw new RuntimeException('Judul harus berupa teks satu baris.');$values[]=$v;}
    $db->prepare('INSERT INTO rapor_pengaturan_judul(kelas_id,tahun_ajaran,semester,jenis,judul,penilaian,konsentrasi,updated_by) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE judul=VALUES(judul),penilaian=VALUES(penilaian),konsentrasi=VALUES(konsentrasi),updated_by=VALUES(updated_by)')->execute([$kelas,$tahun,$semester,$jenis,...$values,$user]);
    rapor_history_record($db,['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis],$user,'titles','Mengubah judul cetak rapor',$previous,rapor_judul_load($db,$kelas,$tahun,$semester,$jenis));
    if($ownsTransaction)$db->commit();
    }catch(Throwable $e){if($ownsTransaction&&$db->inTransaction())$db->rollBack();throw $e;}
}
