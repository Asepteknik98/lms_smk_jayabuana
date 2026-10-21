<?php
// Konfigurasi kelompok saja; identitas siswa dan laporan tetap memakai kelas asal.
function kbm_pkl_path(): string {
    return __DIR__.'/../storage/monitoring-pkl/groups.php';
}
function kbm_pkl_group(string $id): array {
    foreach(kbm_pkl_read() as $group)if($group['id']===$id)return $group;
    throw new InvalidArgumentException('Kelompok PKL tidak ditemukan. Pilih ulang kelas.');
}
function kbm_pkl_slots(array $slots,array $members): array {
    return array_values(array_filter($slots,fn($slot)=>in_array((int)$slot['kelas_id'],$members,true)));
}
function kbm_pkl_add_users(PDO $db,int $admin,array $input): string {
    $id=kbm_input($input,'pkl_id');
    $students=$input['siswa_ids']??null;
    if(!is_array($students)||!$students||count($students)>4)throw new InvalidArgumentException('Pilih 1 sampai 4 siswa.');
    $ids=[];foreach($students as $student){
        if(!is_string($student)||!ctype_digit($student)||(int)$student<1)throw new InvalidArgumentException('Pilihan siswa tidak valid.');
        $ids[]=(int)$student;
    }
    $ids=array_values(array_unique($ids));
    return kbm_admin_write($db,$admin,function()use($db,$admin,$id,$ids){
        $group=kbm_pkl_group($id);$members=$group['kelas_ids'];sort($members,SORT_NUMERIC);
        foreach($members as $class)if(!kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]))throw new InvalidArgumentException('Kelas asal sudah dihapus. Perbarui kelompok PKL.');
        $slots=kbm_pkl_slots(kbm_simple_chair_slots($db),$members);
        $assigned=array_map('intval',array_column($slots,'siswa_id'));
        $new=array_values(array_diff($ids,$assigned));
        if(count($slots)+count($new)>4)throw new InvalidArgumentException('Maksimal 4 pengguna untuk seluruh kelompok PKL. Cabut pengguna sebelum menambah pengganti.');
        foreach($new as $student){
            $rows=kbm_rows($db,'SELECT s.kelas_id FROM siswa s JOIN users u ON u.id=s.user_id WHERE s.id=? AND u.role_id=3 AND u.is_active=1',[$student]);
            if(!$rows||!in_array((int)$rows[0]['kelas_id'],$members,true))throw new InvalidArgumentException('Pilih siswa aktif dari kelas anggota kelompok PKL.');
            $db->prepare("INSERT INTO kbm_ketua_kelas(periode_id,kelas_id,siswa_id,berlaku_mulai,berlaku_sampai,ditunjuk_oleh) VALUES(NULL,?,?,CURRENT_DATE,'9999-12-31',?)")->execute([(int)$rows[0]['kelas_id'],$student,$admin]);
        }
        return $new?'Pengguna kelompok PKL berhasil ditambahkan.':'Siswa yang dipilih sudah ditunjuk sebagai pengguna.';
    });
}
function kbm_pkl_read(): array {
    $path=kbm_pkl_path();
    if(!is_file($path))return [];
    $raw=file_get_contents($path);
    $prefix="<?php exit; ?>\n";
    if($raw===false||!str_starts_with($raw,$prefix))throw new RuntimeException('File kelompok PKL tidak dapat dibaca.');
    try{$groups=json_decode(substr($raw,strlen($prefix)),true,512,JSON_THROW_ON_ERROR);}
    catch(JsonException $e){throw new RuntimeException('File kelompok PKL tidak valid. Hubungi pengelola sistem.');}
    if(!is_array($groups)||!array_is_list($groups))throw new RuntimeException('Format kelompok PKL tidak valid.');
    $seenIds=[];$seenMembers=[];
    foreach($groups as $g){
        if(!is_array($g)||!is_string($g['id']??null)||!preg_match('/^[a-f0-9]{24}$/D',$g['id'])||!is_string($g['nama']??null)||trim($g['nama'])===''||!is_array($g['kelas_ids']??null)||!array_is_list($g['kelas_ids'])||count($g['kelas_ids'])<2)throw new RuntimeException('Data kelompok PKL tidak valid.');
        if(in_array($g['id'],$seenIds,true))throw new RuntimeException('Kelompok PKL terduplikasi.');
        $seenIds[]=$g['id'];
        foreach($g['kelas_ids'] as $id)if(!is_int($id)||$id<1)throw new RuntimeException('Anggota kelompok PKL tidak valid.');
        if(count(array_unique($g['kelas_ids']))!==count($g['kelas_ids'])||array_intersect($seenMembers,$g['kelas_ids']))throw new RuntimeException('Anggota kelompok PKL terduplikasi.');
        $seenMembers=array_merge($seenMembers,$g['kelas_ids']);
    }
    return $groups;
}
function kbm_pkl_save(array $input,array $classes): string {
    $action=$input['action']??'';
    if(!in_array($action,['pkl_save','pkl_delete'],true))throw new InvalidArgumentException('Aksi kelompok PKL tidak valid.');
    $id=$input['pkl_id']??'';
    if(!is_string($id)||($id!==''&&!preg_match('/^[a-f0-9]{24}$/D',$id)))throw new InvalidArgumentException('Kelompok PKL tidak valid.');
    $name=$input['pkl_nama']??'';$members=$input['pkl_kelas']??[];
    $selected=[];
    if($action==='pkl_save'){
        if(!is_string($name)||trim($name)===''||mb_strlen(trim($name))>80)throw new InvalidArgumentException('Nama kelompok PKL wajib diisi, maksimal 80 karakter.');
        if(!is_array($members))throw new InvalidArgumentException('Pilih kelas asal yang valid.');
        $valid=array_map('intval',array_column($classes,'id'));
        foreach($members as $member){
            if(!is_string($member)||!ctype_digit($member)||!in_array((int)$member,$valid,true))throw new InvalidArgumentException('Kelas asal tidak ditemukan.');
            $selected[]=(int)$member;
        }
        $selected=array_values(array_unique($selected));
        if(count($selected)<2)throw new InvalidArgumentException('Pilih minimal dua kelas asal untuk kelompok gabungan.');
    }
    $dir=dirname(kbm_pkl_path());
    if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Folder kelompok PKL belum dapat dibuat.');
    $lock=fopen($dir.'/groups.lock','c');
    if(!$lock)throw new RuntimeException('Penyimpanan kelompok PKL tidak tersedia.');
    $tmp=null;
    try{
        if(!flock($lock,LOCK_EX))throw new RuntimeException('Kelompok PKL sedang diproses. Coba lagi.');
        $groups=kbm_pkl_read();$found=false;
        foreach($groups as $g){
            if($g['id']===$id)$found=true;
            elseif($action==='pkl_save'){
                if(mb_strtolower($g['nama'])===mb_strtolower(trim($name)))throw new InvalidArgumentException('Nama kelompok PKL sudah digunakan.');
                if(array_intersect($selected,$g['kelas_ids']))throw new InvalidArgumentException('Satu kelas asal hanya boleh berada dalam satu kelompok PKL.');
            }
        }
        if(($id!==''||$action==='pkl_delete')&&!$found)throw new InvalidArgumentException('Kelompok PKL tidak ditemukan. Muat ulang halaman.');
        $groups=array_values(array_filter($groups,fn($g)=>$g['id']!==$id));
        if($action==='pkl_save')$groups[]=['id'=>$id?:bin2hex(random_bytes(12)),'nama'=>trim($name),'kelas_ids'=>$selected];
        $data="<?php exit; ?>\n".json_encode($groups,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $tmp=tempnam($dir,'pkl');
        if($tmp===false||file_put_contents($tmp,$data)!==strlen($data)||!rename($tmp,kbm_pkl_path()))throw new RuntimeException('Kelompok PKL gagal disimpan. Pengaturan sebelumnya tetap dipertahankan.');
        $tmp=null;
        return $action==='pkl_delete'?'Kelompok PKL dihapus. Kelas asal dan riwayat tetap tersimpan.':'Kelompok PKL berhasil disimpan.';
    }finally{
        if($tmp&&is_file($tmp))unlink($tmp);
        flock($lock,LOCK_UN);fclose($lock);
    }
}
function kbm_pkl_session(PDO $db,int $admin,array $input): string {
    $id=$input['pkl_id']??null;$session=$input['sesi_kode']??null;
    if(!is_string($id)||!in_array($session,['pagi','siang'],true))throw new InvalidArgumentException('Pilih kelompok PKL dan sesi yang valid.');
    $group=null;foreach(kbm_pkl_read() as $g)if($g['id']===$id)$group=$g;
    if(!$group)throw new InvalidArgumentException('Kelompok PKL tidak ditemukan.');
    return kbm_admin_write($db,$admin,function()use($db,$admin,$group,$session){
        $ids=$group['kelas_ids'];sort($ids,SORT_NUMERIC);
        foreach($ids as $class){
            if(!kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]))throw new InvalidArgumentException('Ada kelas asal yang sudah dihapus. Perbarui anggota kelompok PKL terlebih dahulu.');
            $current=kbm_simple_sessions($db,$class);
            if(!$current||$current[0]['sesi_kode']!==$session)$db->prepare("INSERT INTO kbm_kelas_sesi(periode_id,kelas_id,sesi_kode,berlaku_mulai,berlaku_sampai,dibuat_oleh) VALUES(NULL,?,?,CURRENT_DATE,'9999-12-31',?)")->execute([$class,$session,$admin]);
        }
        return 'Sesi seluruh kelas asal dalam kelompok PKL berhasil disimpan.';
    });
}
