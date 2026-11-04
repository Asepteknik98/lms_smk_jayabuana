<?php
// Peringatan hanya membaca status absensi yang telah dicatat.
function student_attendance_period(?DateTimeImmutable $now=null): array {
    $now=($now??new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $year=(int)$now->format('Y');$odd=(int)$now->format('n')>=7;
    return ['semester'=>$odd?'Ganjil':'Genap','tahun_ajaran'=>($odd?$year:$year-1).'/'.($odd?$year+1:$year),
        'start'=>($odd?$year.'-07-01':$year.'-01-01'),'end'=>($odd?($year+1).'-01-01':$year.'-07-01')];
}
function student_attendance_warnings(PDO $db,int $studentId,?DateTimeImmutable $now=null): array {
    if($studentId<1)return [];
    $period=student_attendance_period($now);
    $q=$db->prepare("SELECT m.id mapel_id,m.nama_mapel,MIN(p.id) pengajaran_id,COUNT(DISTINCT sa.id) jumlah_alpa
        FROM detail_absensi da JOIN sesi_absensi sa ON sa.id=da.sesi_absensi_id
        JOIN pengajaran p ON p.id=sa.pengajaran_id JOIN mapel m ON m.id=p.mapel_id
        WHERE da.siswa_id=? AND da.status='Alpa' AND p.semester=? AND p.tahun_ajaran=?
        AND sa.tanggal>=? AND sa.tanggal<?
        GROUP BY m.id,m.nama_mapel HAVING COUNT(DISTINCT sa.id)>=3 ORDER BY jumlah_alpa DESC,m.nama_mapel");
    $q->execute([$studentId,$period['semester'],$period['tahun_ajaran'],$period['start'],$period['end']]);
    return $q->fetchAll();
}
