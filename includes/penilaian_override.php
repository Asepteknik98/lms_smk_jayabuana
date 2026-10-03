<?php
function po_auto_names(): array { return ['Ulangan Harian','Tugas Harian','Kehadiran']; }

function po_automatic(PDO $db,int $pid,int $sid): array {
    $stmt=$db->prepare("SELECT
      COALESCE((SELECT AVG(COALESCE(nu.nilai_total,0)) FROM ujian u LEFT JOIN nilai_ujian nu ON nu.ujian_id=u.id AND nu.siswa_id=? WHERE u.pengajaran_id=? AND u.jenis_ujian='Kuis' AND u.waktu_selesai<=NOW()),0) ulangan,
      COALESCE((SELECT (SUM(COALESCE(pt.nilai,0))+COALESCE((SELECT SUM(nm.nilai) FROM nilai_tugas_manual nm WHERE nm.pengajaran_id=? AND nm.siswa_id=?),0))/NULLIF(COUNT(*)+(SELECT COUNT(*) FROM nilai_tugas_manual nm WHERE nm.pengajaran_id=? AND nm.siswa_id=?),0) FROM tugas t LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.siswa_id=? WHERE t.pengajaran_id=? AND t.deadline<=NOW()),0) tugas,
      COALESCE((SELECT AVG(CASE WHEN da.status IN ('Hadir','Sakit','Izin') THEN 100 ELSE 0 END) FROM sesi_absensi sa LEFT JOIN detail_absensi da ON da.sesi_absensi_id=sa.id AND da.siswa_id=? WHERE sa.pengajaran_id=? AND sa.status='Ditutup'),0) kehadiran");
    $stmt->execute([$sid,$pid,$pid,$sid,$pid,$sid,$sid,$pid,$sid,$pid]);$r=$stmt->fetch();
    return ['Ulangan Harian'=>(float)$r['ulangan'],'Tugas Harian'=>(float)$r['tugas'],'Kehadiran'=>(float)$r['kehadiran']];
}

// Saved automatic-component rows are explicit overrides. Deleting one restores live data.
function po_save(PDO $db,int $guru,int $pid,$grades,$modes): void {
    if(!is_array($grades)||!is_array($modes))throw new RuntimeException('Isian nilai tidak valid.');
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT kelas_id FROM pengajaran WHERE id=? AND guru_id=? FOR UPDATE');$stmt->execute([$pid,$guru]);$kelas=$stmt->fetchColumn();
        if($kelas===false)throw new RuntimeException('Pengajaran bukan milik Anda.');
        $stmt=$db->prepare('SELECT id,nama_komponen,bobot FROM komponen_penilaian WHERE pengajaran_id=? FOR UPDATE');$stmt->execute([$pid]);$components=[];$weight=0;
        foreach($stmt->fetchAll() as $c){$components[(int)$c['id']]=$c;$weight+=(float)$c['bobot'];}
        if(abs($weight-100)>.001)throw new RuntimeException('Total bobot wajib tepat 100% sebelum nilai disimpan.');
        $stmt=$db->prepare('SELECT id FROM siswa WHERE kelas_id=?');$stmt->execute([$kelas]);$students=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        $old=$db->prepare('SELECT nilai FROM nilai_komponen WHERE komponen_id=? AND siswa_id=? FOR UPDATE');
        $up=$db->prepare('INSERT INTO nilai_komponen(komponen_id,siswa_id,nilai) VALUES(?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)');
        $delete=$db->prepare('DELETE FROM nilai_komponen WHERE komponen_id=? AND siswa_id=?');
        $history=$db->prepare('INSERT INTO riwayat_nilai(pengajaran_id,siswa_id,komponen_id,guru_id,nilai_lama,nilai_baru) VALUES(?,?,?,?,?,?)');
        foreach(array_unique(array_merge(array_keys($grades),array_keys($modes))) as $student){
            $sid=(int)$student;
            if(!in_array($sid,$students,true))throw new RuntimeException('Siswa bukan anggota kelas ini.');
            $values=$grades[$student]??[];$sources=$modes[$student]??[];
            if(!is_array($values)||!is_array($sources))throw new RuntimeException('Format nilai tidak valid.');
            $auto=null;
            foreach(array_unique(array_merge(array_keys($values),array_keys($sources))) as $component){
                $cid=(int)$component;$c=$components[$cid]??null;
                if(!$c)throw new RuntimeException('Komponen bukan milik pengajaran ini.');
                $isAuto=in_array($c['nama_komponen'],po_auto_names(),true);
                $mode=$sources[$component]??($isAuto?'auto':'manual');
                if(!in_array($mode,['auto','manual'],true)||(!$isAuto&&$mode==='auto'))throw new RuntimeException('Sumber nilai tidak valid.');
                $old->execute([$cid,$sid]);$previous=$old->fetchColumn();
                if($mode==='auto'){
                    if($previous!==false){
                        $auto??=po_automatic($db,$pid,$sid);
                        $delete->execute([$cid,$sid]);
                        $history->execute([$pid,$sid,$cid,$guru,$previous,$auto[$c['nama_komponen']]]);
                    }
                    continue;
                }
                $raw=$values[$component]??'';
                if(!is_string($raw))throw new RuntimeException('Nilai harus berupa angka.');
                if(trim($raw)===''){
                    if($isAuto)throw new RuntimeException('Isi nilai pengganti atau pilih Kembali ke Otomatis.');
                    continue;
                }
                if(!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<0||(float)$raw>100)throw new RuntimeException('Nilai harus berupa angka 0 sampai 100.');
                $value=round((float)$raw,2);
                if($previous===false||abs((float)$previous-$value)>.001){
                    $up->execute([$cid,$sid,$value]);
                    $history->execute([$pid,$sid,$cid,$guru,$previous===false?null:$previous,$value]);
                }
            }
        }
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
