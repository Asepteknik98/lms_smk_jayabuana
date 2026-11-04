<?php
function kredit_access_ready(PDO $db): bool {
    return (bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='kredit_akses_guru'")->fetchColumn();
}

function kredit_access_modules(PDO $db,int $user_id): array {
    if(!kredit_access_ready($db))return [];
    $q=$db->prepare('SELECT a.modul FROM kredit_akses_guru a JOIN guru g ON g.id=a.guru_id JOIN users u ON u.id=g.user_id WHERE u.id=? AND u.role_id=2 AND u.is_active=1');
    $q->execute([$user_id]);return $q->fetchAll(PDO::FETCH_COLUMN);
}

function kredit_access_can(PDO $db,int $user_id,string $modul): bool {
    if(!in_array($modul,['poin','aktivitas'],true))return false;
    $q=$db->prepare('SELECT role_id FROM users WHERE id=? AND is_active=1');$q->execute([$user_id]);$role=(int)$q->fetchColumn();
    if($role===1)return true;
    return $role===2&&in_array($modul,kredit_access_modules($db,$user_id),true);
}

function kredit_access_require(PDO $db,int $user_id,string $modul): void {
    if(!kredit_access_can($db,$user_id,$modul)){
        http_response_code(403);exit('Akses ditolak. Fitur ini hanya tersedia untuk guru yang ditunjuk admin.');
    }
}

function kredit_access_save(PDO $db,int $admin_id,array $input): void {
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id FROM users WHERE id=? AND role_id=1 AND is_active=1 FOR UPDATE');$q->execute([$admin_id]);
        if(!$q->fetchColumn())throw new DomainException('Hanya admin yang dapat mengatur akses kredit siswa.');
        if(!kredit_access_ready($db))throw new InvalidArgumentException('Pengaturan akses belum diaktifkan.');
        $allowed=array_map('intval',$db->query('SELECT g.id FROM guru g JOIN users u ON u.id=g.user_id WHERE u.role_id=2 AND u.is_active=1')->fetchAll(PDO::FETCH_COLUMN));
        $assignments=[];
        foreach(['poin','aktivitas'] as $modul){
            $values=$input[$modul]??[];
            if(!is_array($values))throw new InvalidArgumentException('Pilihan guru tidak valid.');
            $assignments[$modul]=[];
            foreach($values as $value){
                $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if(!$id||!in_array($id,$allowed,true))throw new InvalidArgumentException('Pilih guru dengan akun aktif yang tersedia.');
                $assignments[$modul][$id]=$id;
            }
        }
        $db->exec('DELETE FROM kredit_akses_guru');
        $q=$db->prepare('INSERT INTO kredit_akses_guru(guru_id,modul,diberikan_oleh) VALUES(?,?,?)');
        foreach($assignments as $modul=>$ids)foreach($ids as $id)$q->execute([$id,$modul,$admin_id]);
        $log=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
        $log->execute([$admin_id,'Mengatur akses kredit siswa: poin guru #'.implode(',',$assignments['poin']).'; aktivitas guru #'.implode(',',$assignments['aktivitas']),$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
