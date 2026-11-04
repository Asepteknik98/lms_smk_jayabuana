<?php
function rapor_history_ready(PDO $db): bool {
    try{$db->query('SELECT 1 FROM rapor_riwayat LIMIT 0');return true;}
    catch(PDOException $e){if($e->getCode()==='42S02')return false;throw $e;}
}
function rapor_history_snapshot(?array $report): array {
    if(!$report)return [];
    $snapshot=[];
    foreach(['nomor_absen','sakit','izin','alfa','catatan_wali_kelas','nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'] as $key)$snapshot[$key]=$report[$key]??null;
    $snapshot['mapel']=array_map(static fn($row)=>array_intersect_key($row,array_flip(['pengajaran_id','nama_mapel','nama_guru','kelompok','nilai_akhir','deskripsi','saran','sumber_diambil_pada'])),$report['mapel']??[]);
    $snapshot['ekstra']=array_map(static fn($row)=>['nama_kegiatan'=>$row['nama_kegiatan'],'grade'=>$row['grade']],$report['ekstra']??[]);
    return $snapshot;
}
function rapor_history_record(PDO $db,array $context,int $user,string $action,string $summary,array $before,array $after): void {
    if(!rapor_history_ready($db))return;
    $changes=[];
    foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $key)if(($before[$key]??null)!==($after[$key]??null))$changes[$key]=['sebelum'=>$before[$key]??null,'sesudah'=>$after[$key]??null];
    if(!$changes)return;
    if(!$db->inTransaction())throw new RuntimeException('Riwayat rapor harus disimpan bersama perubahan datanya.');
    $q=$db->prepare('SELECT username FROM users WHERE id=?');$q->execute([$user]);$actor=$q->fetchColumn();
    $db->prepare('INSERT INTO rapor_riwayat(rapor_id,kelas_id,tahun_ajaran,semester,jenis,nama_siswa,user_id,nama_pengguna,tindakan,ringkasan,perubahan) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$context['id']??null,(int)$context['kelas_id'],$context['tahun_ajaran'],$context['semester'],$context['jenis'],$context['nama_siswa']??'',$user,$actor===false?'Pengguna #'.$user:mb_substr($actor,0,100),$action,$summary,json_encode($changes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}
function rapor_history_report(PDO $db,?array $before,array $after,int $user,string $action,string $summary): void {
    rapor_history_record($db,$after,$user,$action,$summary,rapor_history_snapshot($before),rapor_history_snapshot($after));
}
function rapor_history_list(PDO $db,int $kelas,string $tahun,string $semester,string $jenis,int $reportId=0): array {
    if(!rapor_history_ready($db))return [];
    $sql='SELECT * FROM rapor_riwayat WHERE kelas_id=? AND tahun_ajaran=? AND semester=? AND jenis=?';$args=[$kelas,$tahun,$semester,$jenis];
    if($reportId){$sql.=' AND (rapor_id=? OR rapor_id IS NULL)';$args[]=$reportId;}
    $q=$db->prepare($sql.' ORDER BY id DESC LIMIT 100');$q->execute($args);return $q->fetchAll();
}
