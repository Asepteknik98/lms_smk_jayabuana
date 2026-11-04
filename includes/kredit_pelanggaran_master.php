<?php
const KPS_KATEGORI = ['A'=>'Keterlambatan','B'=>'Kehadiran','C'=>'Pakaian Seragam','D'=>'Kepribadian','E'=>'Ketertiban','F'=>'Merokok','G'=>'Pornografi','H'=>'Senjata Tajam','I'=>'Narkoba dan Minuman Keras','J'=>'Berkelahi / Tawuran','K'=>'Intimasi / Ancaman dengan Kekerasan'];

function kps_catalog_ready(PDO $db): bool {
    return (bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='kredit_master_pelanggaran'")->fetchColumn();
}

function kps_catalog_delete(PDO $db,int $user_id,int $id): string {
    $q=$db->prepare('SELECT id FROM users WHERE id=? AND role_id=1 AND is_active=1');$q->execute([$user_id]);
    if(!$q->fetchColumn())throw new DomainException('Hanya admin yang dapat menghapus Master Pelanggaran.');
    if($id<1)throw new InvalidArgumentException('Pelanggaran tidak valid.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id FROM kredit_master_pelanggaran WHERE id=? FOR UPDATE');$q->execute([$id]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Pelanggaran tidak ditemukan.');
        $q=$db->prepare('SELECT COUNT(*) FROM kredit_poin_siswa WHERE pelanggaran_id=?');$q->execute([$id]);
        $used=(int)$q->fetchColumn()>0;
        if($used){$q=$db->prepare('UPDATE kredit_master_pelanggaran SET aktif=0,diubah_oleh=? WHERE id=?');$q->execute([$user_id,$id]);}
        else{$q=$db->prepare('DELETE FROM kredit_master_pelanggaran WHERE id=?');$q->execute([$id]);}
        $q=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
        $q->execute([$user_id,($used?'Menonaktifkan':'Menghapus').' Master Pelanggaran #'.$id,$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        $db->commit();return $used?'deactivated':'deleted';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function kps_catalog_save(PDO $db,int $user_id,array $input): int {
    $q=$db->prepare('SELECT id FROM users WHERE id=? AND role_id=1 AND is_active=1');$q->execute([$user_id]);
    if(!$q->fetchColumn())throw new DomainException('Hanya admin yang dapat mengubah Master Pelanggaran.');
    $id=filter_var($input['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    $kategori=$input['kategori']??'';
    $nama=is_string($input['nama']??null)?trim($input['nama']):'';
    $poin=filter_var($input['poin']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
    $penanganan=is_string($input['penanganan']??null)?trim($input['penanganan']):'';
    if($id===false||!is_string($kategori)||!isset(KPS_KATEGORI[$kategori])||$nama===''||mb_strlen($nama)>500||!$poin||mb_strlen($penanganan)>500)throw new InvalidArgumentException('Lengkapi kategori, jenis pelanggaran, poin 1–100.000, dan penanganan maksimal 500 karakter.');
    $aktif=($input['aktif']??'')==='1'?1:0;
    $db->beginTransaction();
    try{
        if($id){
            $q=$db->prepare('SELECT id FROM kredit_master_pelanggaran WHERE id=? FOR UPDATE');$q->execute([$id]);
            if(!$q->fetchColumn())throw new InvalidArgumentException('Pelanggaran tidak ditemukan.');
            $q=$db->prepare('UPDATE kredit_master_pelanggaran SET kategori=?,nama=?,poin=?,penanganan=?,aktif=?,diubah_oleh=? WHERE id=?');
            $q->execute([$kategori,$nama,$poin,$penanganan?:null,$aktif,$user_id,$id]);
        }else{
            $q=$db->prepare('INSERT INTO kredit_master_pelanggaran(kode,kategori,nama,poin,penanganan,aktif,urutan,diubah_oleh) VALUES(?,?,?,?,?,?,999,?)');
            $q->execute(['T.'.bin2hex(random_bytes(6)),$kategori,$nama,$poin,$penanganan?:null,$aktif,$user_id]);$id=(int)$db->lastInsertId();
        }
        $q=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
        $q->execute([$user_id,'Memperbarui Master Pelanggaran #'.$id,$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
