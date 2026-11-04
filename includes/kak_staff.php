<?php
require_once __DIR__.'/kak_klaim.php';
require_once __DIR__.'/kredit_akses.php';
require_once __DIR__.'/kak_master.php';

function kak_staff_actor(PDO $db,int $user_id): array {
    if(!kredit_access_can($db,$user_id,'aktivitas'))throw new DomainException('Anda belum ditunjuk admin untuk mengelola Kredit Aktivitas Siswa.');
    $q=$db->prepare('SELECT u.id,u.role_id,g.id guru_id FROM users u LEFT JOIN guru g ON g.user_id=u.id WHERE u.id=? AND u.is_active=1');
    $q->execute([$user_id]);$actor=$q->fetch();
    if(!$actor||!in_array((int)$actor['role_id'],[1,2],true)||((int)$actor['role_id']===2&&!$actor['guru_id'])) throw new DomainException('Anda tidak memiliki akses pengelolaan aktivitas.');
    return $actor;
}

function kak_staff_list(PDO $db,int $user_id,string $kind,array $input,bool $all=false): array {
    $actor=kak_staff_actor($db,$user_id);
    if(!in_array($kind,['claims','events'],true))throw new InvalidArgumentException('Daftar tidak valid.');
    $search=is_string($input['q']??null)?mb_substr(trim($input['q']),0,100):'';
    $status=is_string($input['status']??null)&&in_array($input['status'],['Menunggu','Disetujui','Ditolak'],true)?$input['status']:'';
    $page=filter_var($input['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
    $conditions=[];$params=[];
    if((int)$actor['role_id']!==1){$conditions[]='a.dibuat_oleh=?';$params[]=$user_id;}
    if($search!==''){
        $search_fields="a.nama LIKE ? ESCAPE '!' OR s.nama_lengkap LIKE ? ESCAPE '!' OR k.nama_kelas LIKE ? ESCAPE '!'";
        if($kind==='events')$search_fields.=" OR (CASE a.target_tingkat WHEN 'X' THEN 'Semua kelas 10 (X)' WHEN 'XI' THEN 'Semua kelas 11 (XI)' WHEN 'XII' THEN 'Semua kelas 12 (XII)' ELSE '' END) LIKE ? ESCAPE '!'";
        $conditions[]='('.$search_fields.')';
        $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
        array_push($params,$pattern,$pattern,$pattern);
        if($kind==='events')$params[]=$pattern;
    }
    if($kind==='claims'&&$status!==''){$conditions[]='c.status=?';$params[]=$status;}
    $from=$kind==='claims'
        ?' FROM kak_klaim c JOIN kak_acara a ON a.id=c.acara_id JOIN siswa s ON s.id=c.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id'
        :' FROM kak_acara a LEFT JOIN kelas k ON k.id=a.kelas_id LEFT JOIN siswa s ON s.id=a.siswa_id';
    $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
    $q=$db->prepare('SELECT COUNT(*)'.$from.$where);$q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/10));$page=min($page,$pages);
    $select=$kind==='claims'?'c.*,a.nama,a.poin,s.nama_lengkap,k.nama_kelas':'a.*,k.nama_kelas,s.nama_lengkap';
    $order=$kind==='claims'?"(c.status='Menunggu') DESC,c.id DESC":'a.id DESC';
    $q=$db->prepare('SELECT '.$select.$from.$where.' ORDER BY '.$order.($all?'':' LIMIT 10 OFFSET '.(($page-1)*10)));$q->execute($params);
    return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'search'=>$search,'status'=>$status];
}

function kak_staff_create(PDO $db,int $user_id,array $input): int {
    kak_staff_actor($db,$user_id);
    $deskripsi=is_string($input['deskripsi']??null)?trim($input['deskripsi']):'';
    $tanggal=is_string($input['tanggal_acara']??null)?$input['tanggal_acara']:'';
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$tanggal);
    $target=$input['kelas_id']??'';
    $tingkat=null;
    $kelas=null;
    if(in_array($target,['tingkat:X','tingkat:XI','tingkat:XII'],true))$tingkat=substr($target,8);
    else $kelas=filter_var($target,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $siswa=null;
    if(!$kelas&&!$tingkat)throw new InvalidArgumentException('Pilih target kelas atau satu angkatan terlebih dahulu.');
    if($kelas){
        $q=$db->prepare('SELECT tingkat FROM kelas WHERE id=?');$q->execute([$kelas]);$tingkat_kelas=$q->fetchColumn();
        if(!$tingkat_kelas)throw new InvalidArgumentException('Target kelas tidak ditemukan atau tingkatnya belum ditentukan.');
    }
    $tingkat_aktivitas=$tingkat?:$tingkat_kelas;
    $master_id=filter_var($input['aktivitas_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$master_id||!kak_master_ready($db))throw new InvalidArgumentException('Pilih aktivitas dari Master Aktivitas sesuai tingkat kelas.');
    $db->beginTransaction();
    try{
    $q=$db->prepare('SELECT * FROM kak_master_aktivitas WHERE id=? AND aktif=1 AND tingkat=? FOR UPDATE');$q->execute([$master_id,$tingkat_aktivitas]);$master=$q->fetch();
    if(!$master)throw new InvalidArgumentException('Aktivitas tidak tersedia untuk tingkat kelas yang dipilih.');
    $nama=$master['nama'];
    $poin=1; // Nilai kompatibilitas skema lama; persetujuan menggunakan status.
    if(($input['siswa_id']??'')!==''){
        if($tingkat)throw new InvalidArgumentException('Target satu angkatan berlaku untuk seluruh siswanya, bukan siswa tertentu.');
        $siswa=filter_var($input['siswa_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$siswa)throw new InvalidArgumentException('Siswa sasaran tidak valid.');
        $q=$db->prepare('SELECT id FROM siswa WHERE id=? AND kelas_id=?');$q->execute([$siswa,$kelas]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Siswa tidak terdaftar di kelas yang dipilih.');
    }
    if($nama===''||mb_strlen($nama)>150||mb_strlen($deskripsi)>1000||!$date||$date->format('Y-m-d')!==$tanggal) throw new InvalidArgumentException('Lengkapi nama dan tanggal acara yang valid.');
    $q=$db->prepare("INSERT INTO kak_acara(nama,deskripsi,tanggal_acara,poin,kelas_id,siswa_id,target_tingkat,master_aktivitas_id,tingkat_aktivitas,poin_standar,status,dibuat_oleh) VALUES(?,?,?,?,?,?,?,?,?,?,'Terbit',?)");
    $q->execute([$nama,$deskripsi?:null,$tanggal,$poin,$kelas,$siswa,$tingkat,$master_id,$tingkat_aktivitas,1,$user_id]);
    $id=(int)$db->lastInsertId();$db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function kak_staff_review(PDO $db,int $user_id,array $input,?string $storage=null): bool {
    $actor=kak_staff_actor($db,$user_id);
    $id=filter_var($input['klaim_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $keputusan=isset($input['disetujui']) ? ($input['disetujui']==='1'?'Disetujui':'') : ($input['keputusan']??'Ditolak');
    $catatan=is_string($input['catatan_verifikasi']??null)?trim($input['catatan_verifikasi']):'';
    if(!$id||!in_array($keputusan,['Disetujui','Ditolak'],true)||mb_strlen($catatan)>500||($keputusan==='Ditolak'&&$catatan===''))throw new InvalidArgumentException('Pilih keputusan dan isi alasan jika klaim ditolak.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT c.*,a.nama,a.poin,a.tanggal_acara,a.aktivitas_asal_id,a.dibuat_oleh FROM kak_klaim c JOIN kak_acara a ON a.id=c.acara_id WHERE c.id=? FOR UPDATE');
        $q->execute([$id]);$claim=$q->fetch();
        if(!$claim||((int)$actor['role_id']!==1&&(int)$claim['dibuat_oleh']!==$user_id))throw new DomainException('Klaim tidak ditemukan atau bukan acara yang Anda buat.');
        if($claim['status']!=='Menunggu'){ $db->commit();return false; }
        $activity=null;
        if($keputusan==='Disetujui'){
            $folder=$storage??__DIR__.'/../storage/kak-klaim';
            if(!preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp)\z/',$claim['bukti_file'])||!is_file($folder.'/'.$claim['bukti_file']))throw new InvalidArgumentException('Foto bukti tidak tersedia. Klaim belum dapat disetujui.');

        }
        $q=$db->prepare('UPDATE kak_klaim SET status=?,catatan_verifikasi=?,diverifikasi_oleh=?,diverifikasi_pada=NOW(),aktivitas_id=? WHERE id=?');
        $q->execute([$keputusan,$catatan?:null,$user_id,$activity,$id]);
        $db->commit();return true;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function kak_staff_update_date(PDO $db,int $user_id,array $input): void {
    $actor=kak_staff_actor($db,$user_id);
    $id=filter_var($input['acara_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $tanggal=is_string($input['tanggal_acara']??null)?$input['tanggal_acara']:'';
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$tanggal);
    if(!$id||!$date||$date->format('Y-m-d')!==$tanggal||$tanggal<'1000-01-01'||$tanggal>'9999-12-31')throw new InvalidArgumentException('Pilih acara dan tanggal yang valid.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT tanggal_acara,dibuat_oleh FROM kak_acara WHERE id=? FOR UPDATE');$q->execute([$id]);$event=$q->fetch();
        if(!$event||((int)$actor['role_id']!==1&&(int)$event['dibuat_oleh']!==$user_id))throw new DomainException('Acara tidak ditemukan atau bukan acara yang Anda buat.');
        if($event['tanggal_acara']!==$tanggal){
            $q=$db->prepare('UPDATE kak_acara SET tanggal_acara=? WHERE id=?');$q->execute([$tanggal,$id]);
            $q=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
            $q->execute([$user_id,'Mengubah tanggal acara #'.$id.' dari '.$event['tanggal_acara'].' menjadi '.$tanggal,$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        }
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

// Status Draft menghentikan klaim baru; klaim yang ada tetap menjadi riwayat.
function kak_staff_delete_event(PDO $db,int $user_id,int $id): string {
    $actor=kak_staff_actor($db,$user_id);
    if($id<1)throw new InvalidArgumentException('Acara tidak valid.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id,dibuat_oleh FROM kak_acara WHERE id=? FOR UPDATE');$q->execute([$id]);$event=$q->fetch();
        if(!$event||((int)$actor['role_id']!==1&&(int)$event['dibuat_oleh']!==$user_id))throw new DomainException('Acara tidak ditemukan atau bukan acara yang Anda buat.');
        $q=$db->prepare('SELECT id FROM kak_klaim WHERE acara_id=? LIMIT 1 FOR UPDATE');$q->execute([$id]);$used=(bool)$q->fetchColumn();
        $q=$db->prepare($used?"UPDATE kak_acara SET status='Draft' WHERE id=?":'DELETE FROM kak_acara WHERE id=?');$q->execute([$id]);
        $q=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
        $q->execute([$user_id,($used?'Mengarsipkan':'Menghapus').' acara aktivitas #'.$id,$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        $db->commit();return $used?'archived':'deleted';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
