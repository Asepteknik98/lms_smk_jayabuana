<?php
const KAK_TINGKAT = ['X'=>'Kelas 10','XI'=>'Kelas 11','XII'=>'Kelas 12'];
function kak_master_delete(PDO $db,int $user_id,int $id): string {
    $q=$db->prepare('SELECT id FROM users WHERE id=? AND role_id=1 AND is_active=1');$q->execute([$user_id]);
    if(!$q->fetchColumn())throw new DomainException('Hanya admin yang dapat menghapus Master Aktivitas.');
    if($id<1)throw new InvalidArgumentException('Aktivitas tidak valid.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id FROM kak_master_aktivitas WHERE id=? FOR UPDATE');$q->execute([$id]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Aktivitas tidak ditemukan.');
        $q=$db->prepare('SELECT id FROM kak_acara WHERE master_aktivitas_id=? LIMIT 1');$q->execute([$id]);$used=(bool)$q->fetchColumn();
        $q=$db->prepare($used?'UPDATE kak_master_aktivitas SET aktif=0,diubah_oleh=? WHERE id=?':'DELETE FROM kak_master_aktivitas WHERE id=?');
        $q->execute($used?[$user_id,$id]:[$id]);
        $q=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
        $q->execute([$user_id,($used?'Menonaktifkan':'Menghapus').' Master Aktivitas #'.$id,$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        $db->commit();return $used?'deactivated':'deleted';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function kak_master_ready(PDO $db): bool {
    return (bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='kak_master_aktivitas'")->fetchColumn();
}
function kak_master_save(PDO $db,int $user_id,array $input): int {
    $q=$db->prepare('SELECT id FROM users WHERE id=? AND role_id=1 AND is_active=1');$q->execute([$user_id]);
    if(!$q->fetchColumn())throw new DomainException('Hanya admin yang dapat mengubah Master Aktivitas.');
    $id=filter_var($input['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    $tingkat=$input['tingkat']??'';$nama=is_string($input['nama']??null)?trim($input['nama']):'';
    $poin=1; // Kompatibilitas kolom wajib skema lama; tidak digunakan sebagai penilaian.
    if($id===false||!is_string($tingkat)||!isset(KAK_TINGKAT[$tingkat])||$nama===''||mb_strlen($nama)>150)throw new InvalidArgumentException('Lengkapi tingkat dan nama aktivitas maksimal 150 karakter.000.');
    $aktif=($input['aktif']??'')==='1'?1:0;
    $db->beginTransaction();
    try{
        if($id){
            $q=$db->prepare('SELECT id FROM kak_master_aktivitas WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Aktivitas tidak ditemukan.');
            $q=$db->prepare('UPDATE kak_master_aktivitas SET tingkat=?,nama=?,aktif=?,diubah_oleh=? WHERE id=?');$q->execute([$tingkat,$nama,$aktif,$user_id,$id]);
        }else{
            $q=$db->prepare('INSERT INTO kak_master_aktivitas(kode,tingkat,nama,poin,aktif,urutan,diubah_oleh) VALUES(?,?,?,?,?,999,?)');
            $q->execute(['T.'.bin2hex(random_bytes(6)),$tingkat,$nama,$poin,$aktif,$user_id]);$id=(int)$db->lastInsertId();
        }
        $q=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
        $q->execute([$user_id,'Memperbarui Master Aktivitas #'.$id,$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
