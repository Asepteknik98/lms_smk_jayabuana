<?php
function nm_component_name(string $kind): ?string {
    return ['uts'=>'UTS','uas'=>'UAS','praktik'=>'Ujian Praktik'][$kind]??null;
}

function nmc_context(PDO $db,int $guru,int $pid,string $kind): array {
    $name=nm_component_name($kind);
    if($name===null)throw new RuntimeException('Jenis komponen tidak valid.');
    $q=$db->prepare('SELECT p.*,m.nama_mapel,k.nama_kelas FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.id=? AND p.guru_id=?');$q->execute([$pid,$guru]);$info=$q->fetch();
    if(!$info)throw new RuntimeException('Pengajaran bukan milik Anda.');
    $q=$db->prepare('SELECT id FROM komponen_penilaian WHERE pengajaran_id=? AND nama_komponen=? ORDER BY id');$q->execute([$pid,$name]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
    if(count($ids)>1)throw new RuntimeException('Komponen penilaian ganda. Periksa Rekap Nilai.');
    $q=$db->prepare('SELECT SUM(bobot) FROM komponen_penilaian WHERE pengajaran_id=?');$q->execute([$pid]);$ready=abs((float)$q->fetchColumn()-100)<.001;
    $targets=$ids?[['id'=>(int)$ids[0],'nomor'=>1,'judul'=>$name,'label'=>$name,'pertemuan_ke'=>null,'selesai'=>$ready]]:[];
    $q=$db->prepare('SELECT id,nis,nisn,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');$q->execute([$info['kelas_id']]);$students=$q->fetchAll();
    $scores=[];
    if($ids){$q=$db->prepare("SELECT siswa_id,nilai,'' catatan FROM nilai_komponen WHERE komponen_id=?");$q->execute([$ids[0]]);foreach($q->fetchAll() as $row)$scores[(int)$row['siswa_id']][(int)$ids[0]]=$row;}
    $meetings=[];return compact('info','targets','students','scores','meetings','kind','pid');
}

function nmc_save(PDO $db,int $guru,int $user,int $pid,string $kind,int $id,array $values,array $expected=[]): array {
    $name=nm_component_name($kind);
    if($name===null||count($values)>5000)throw new RuntimeException('Format penilaian tidak valid.');
    if($expected&&!in_array($kind,['uts','uas'],true))throw new RuntimeException('Edit nilai hanya tersedia untuk PSTS dan PSAS.');
    $entries=[];
    foreach($values as $sid=>$raw){
        if(!preg_match('/^[1-9][0-9]*$/D',(string)$sid))throw new RuntimeException('ID siswa tidak valid.');
        $score=nm_score($raw);if($score!==null)$entries[]=[(int)$sid,$score];
    }
    if(!$entries)throw new RuntimeException('Isi minimal satu nilai baru.');
    $db->beginTransaction();$saved=0;$skipped=0;
    try{
        $q=$db->prepare('SELECT p.kelas_id FROM pengajaran p JOIN guru g ON g.id=p.guru_id JOIN users u ON u.id=g.user_id AND u.role_id=2 AND u.is_active=1 WHERE p.id=? AND p.guru_id=? AND g.user_id=? FOR UPDATE');$q->execute([$pid,$guru,$user]);$kelas=$q->fetchColumn();
        if($kelas===false)throw new RuntimeException('Pengajaran bukan milik Anda.');
        $q=$db->prepare('SELECT id,nama_komponen,bobot FROM komponen_penilaian WHERE pengajaran_id=? FOR UPDATE');$q->execute([$pid]);$components=$q->fetchAll();
        $matches=array_filter($components,static fn($c)=>$c['nama_komponen']===$name);
        if(count($matches)!==1||(int)reset($matches)['id']!==$id)throw new RuntimeException('Komponen tidak sesuai kelas atau jenis penilaian.');
        if(abs(array_sum(array_column($components,'bobot'))-100)>.001)throw new RuntimeException('Total bobot harus 100% sebelum menyimpan nilai.');
        foreach($entries as [$sid,$score]){
            $q=$db->prepare('SELECT id FROM siswa WHERE id=? AND kelas_id=? FOR UPDATE');$q->execute([$sid,$kelas]);
            if(!$q->fetchColumn())throw new RuntimeException('Siswa bukan anggota kelas ini.');
            $q=$db->prepare('SELECT nilai FROM nilai_komponen WHERE komponen_id=? AND siswa_id=? FOR UPDATE');$q->execute([$id,$sid]);$old=$q->fetch();
            if($expected){
                $previous=nm_score($expected[$sid]??'');
                if(!$old||$old['nilai']===null||$previous===null||abs((float)$old['nilai']-$previous)>.001)throw new RuntimeException('Nilai sudah berubah. Muat ulang halaman sebelum mengedit kembali.');
                if(abs((float)$old['nilai']-$score)<.001){$skipped++;continue;}
            }elseif($old&&$old['nilai']!==null){$skipped++;continue;}
            $db->prepare('INSERT INTO nilai_komponen(komponen_id,siswa_id,nilai) VALUES(?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)')->execute([$id,$sid,$score]);
            $db->prepare('INSERT INTO riwayat_nilai(pengajaran_id,siswa_id,komponen_id,guru_id,nilai_lama,nilai_baru) VALUES(?,?,?,?,?,?)')->execute([$pid,$sid,$id,$guru,$old['nilai']??null,$score]);
            $saved++;
        }
        $db->commit();return compact('saved','skipped');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function nmc_update(PDO $db,int $guru,int $user,int $pid,string $kind,int $id,int $sid,mixed $value,mixed $previous): array {
    if(!in_array($kind,['uts','uas'],true)||nm_score($value)===null||nm_score($previous)===null)throw new RuntimeException('Isi nilai PSTS atau PSAS antara 0 sampai 100.');
    return nmc_save($db,$guru,$user,$pid,$kind,$id,[$sid=>$value],[$sid=>$previous]);
}

function nmc_status(float $score): string { return $score>=75?'Tuntas':'Remedial'; }
