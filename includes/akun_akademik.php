<?php
function akademik_role_id(PDO $db): int {
    $q=$db->query("SELECT id FROM roles WHERE nama_role='Akademik'");
    $ids=$q->fetchAll(PDO::FETCH_COLUMN);
    return count($ids)===1&&(int)$ids[0]>3?(int)$ids[0]:0;
}

function akademik_save(PDO $db,int $actor,int $id,mixed $name,mixed $username,mixed $password,mixed $active): int {
    if(!is_string($name)||!is_string($username)||!is_string($password)||!in_array($active,['0','1'],true))throw new RuntimeException('Format data akun tidak valid.');
    $name=trim($name);$username=trim($username);
    if($name===''||mb_strlen($name)>100||preg_match('/[\x00-\x1F\x7F]/u',$name))throw new RuntimeException('Nama wajib diisi, maksimal 100 karakter.');
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/D',$username))throw new RuntimeException('Username 3–50 karakter: huruf, angka, titik, garis bawah, atau tanda minus.');
    if(($id===0||$password!=='')&&(strlen($password)<8||strlen($password)>72||str_contains($password,"\0")))throw new RuntimeException('Password harus 8–72 byte.');
    if($id<0)throw new RuntimeException('Akun tidak valid.');
    $role=akademik_role_id($db);
    if(!$role)throw new RuntimeException('Role Akademik belum tersedia. Hubungi pengelola sistem.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id FROM users WHERE id=? AND role_id=1 AND is_active=1 FOR UPDATE');$q->execute([$actor]);
        if(!$q->fetchColumn())throw new RuntimeException('Hanya admin aktif yang dapat mengelola akun Akademik.');
        if($id){$q=$db->prepare('SELECT id FROM users WHERE id=? AND role_id=? FOR UPDATE');$q->execute([$id,$role]);if(!$q->fetchColumn())throw new RuntimeException('Akun Akademik tidak ditemukan.');}
        $q=$db->prepare('SELECT id FROM users WHERE username=? AND id<>?');$q->execute([$username,$id]);
        if($q->fetchColumn())throw new RuntimeException('Username sudah digunakan.');
        if(!$id){
            $hash=password_hash($password,PASSWORD_BCRYPT);
            $db->prepare('INSERT INTO users(username,password,role_id,is_active) VALUES(?,?,?,?)')->execute([$username,$hash,$role,(int)$active]);
            $id=(int)$db->lastInsertId();
        }else{
            $db->prepare('UPDATE users SET username=?,is_active=? WHERE id=? AND role_id=?')->execute([$username,(int)$active,$id,$role]);
            if($password!=='')$db->prepare('UPDATE users SET password=? WHERE id=? AND role_id=?')->execute([password_hash($password,PASSWORD_BCRYPT),$id,$role]);
        }
        $db->prepare('INSERT INTO akademik_profil(user_id,nama_lengkap) VALUES(?,?) ON DUPLICATE KEY UPDATE nama_lengkap=VALUES(nama_lengkap)')->execute([$id,$name]);
        $db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)')->execute([$actor,'Mengelola akun Akademik ID '.$id.'; aktif='.$active.($password!==''?'; password diperbarui':''),$_SERVER['REMOTE_ADDR']??'CLI',substr($_SERVER['HTTP_USER_AGENT']??'CLI',0,255)]);
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
