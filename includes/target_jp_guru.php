<?php
const RM_TARGET_DAYS=['senin'=>'Senin','selasa'=>'Selasa','rabu'=>'Rabu','kamis'=>'Kamis','jumat'=>'Jumat'];

function rm_target_number(float $value): string {
    return rtrim(rtrim(number_format($value,2,',','.'),'0'),',');
}
function rm_target_ready(PDO $db): bool {
    return (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='target_jp_guru' AND column_name IN ('id','guru_id','berlaku_mulai','senin','selasa','rabu','kamis','jumat','sabtu','minggu','dibuat_oleh','created_at')")->fetchColumn()===12;
}
function rm_target_week(string $date): array {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Jakarta'));
    if(!$d||$d->format('Y-m-d')!==$date||$date<'2000-01-01'||$date>'2099-12-31')throw new InvalidArgumentException('Tanggal minggu harus valid, antara tahun 2000 dan 2099.');
    $start=$d->modify('-'.((int)$d->format('N')-1).' days');
    return ['mulai'=>$start->format('Y-m-d'),'selesai'=>$start->modify('+4 days')->format('Y-m-d')];
}
function rm_target_latest(PDO $db,int $guru,string $monday,bool $lock=false): ?array {
    $q=$db->prepare('SELECT * FROM target_jp_guru WHERE guru_id=? AND berlaku_mulai<=? ORDER BY berlaku_mulai DESC,id DESC LIMIT 1'.($lock?' FOR UPDATE':''));$q->execute([$guru,$monday]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function rm_target_save(PDO $db,int $actor,array $input): string {
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT role_id,is_active FROM users WHERE id=? LOCK IN SHARE MODE');$q->execute([$actor]);$admin=$q->fetch(PDO::FETCH_ASSOC);
        if(!$admin||(int)$admin['role_id']!==1||(int)$admin['is_active']!==1)throw new InvalidArgumentException('Target JP hanya dapat diatur admin aktif.');
        if(!rm_target_ready($db))throw new InvalidArgumentException('Migrasi target JP belum diterapkan.');
        $guru=$input['target_guru_id']??null;$date=$input['berlaku_mulai']??null;
        if(!is_string($guru)||!preg_match('/^[1-9][0-9]{0,9}$/D',$guru)||!is_string($date))throw new InvalidArgumentException('Guru dan tanggal berlaku wajib diisi dengan benar.');
        $week=rm_target_week($date);if($week['mulai']!==$date)throw new InvalidArgumentException('Tanggal mulai berlaku harus hari Senin.');
        $today=(string)$db->query('SELECT CURRENT_DATE')->fetchColumn();$current=rm_target_week($today);
        if($date<$current['mulai'])throw new InvalidArgumentException('Target tidak boleh diberlakukan pada minggu yang sudah berlalu.');
        $q=$db->prepare('SELECT id FROM guru WHERE id=? FOR UPDATE');$q->execute([$guru]);if(!$q->fetchColumn())throw new InvalidArgumentException('Guru tidak ditemukan.');
        $values=[];foreach(RM_TARGET_DAYS as $key=>$label){
            if(array_key_exists($key.'_pagi',$input)||array_key_exists($key.'_siang',$input)){
                $total=0;foreach(['pagi','siang'] as $session){$part=$input[$key.'_'.$session]??null;if(!is_string($part)||!preg_match('/^[0-6]$/D',$part))throw new InvalidArgumentException('Target '.$label.' sesi '.$session.' harus bilangan bulat 0?6 JP.');$total+=(int)$part;}
                $input[$key]=(string)$total;
            }
            $v=$input[$key]??null;if(!is_string($v)||!preg_match('/^(?:[0-9]|1[0-2])$/D',$v))throw new InvalidArgumentException('Target '.$label.' harus bilangan bulat 0–12 JP.');$values[$key]=(int)$v;}
        foreach(['sabtu','minggu'] as $key){if(isset($input[$key])&&$input[$key]!=='0')throw new InvalidArgumentException('Target JP hanya berlaku Senin sampai Jumat.');$values[$key]=0;}
        $existing=rm_target_latest($db,(int)$guru,$date,true);
        if($existing&&array_map('intval',array_intersect_key($existing,$values))===$values){$db->commit();return 'Target sama dengan pengaturan yang sudah berlaku; tidak dibuat catatan ganda.';}
        if($date===$current['mulai']&&$existing)throw new InvalidArgumentException('Target minggu berjalan sudah ditetapkan. Perubahan berlaku mulai Senin minggu berikutnya agar riwayat tetap utuh.');
        $q=$db->prepare('INSERT INTO target_jp_guru(guru_id,berlaku_mulai,senin,selasa,rabu,kamis,jumat,sabtu,minggu,dibuat_oleh) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([(int)$guru,$date,...array_values($values),$actor]);
        $id=(int)$db->lastInsertId();$q=$db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)');
        $q->execute([$actor,'Target JP #'.$id.' guru #'.$guru.' mulai '.$date.'; harian='.implode(',',array_values($values)).'; mingguan='.array_sum($values).'; versi sebelumnya #'.($existing['id']??0),$_SERVER['REMOTE_ADDR']??'UNKNOWN',$_SERVER['HTTP_USER_AGENT']??'UNKNOWN']);
        $db->commit();return 'Target harian dan '.array_sum($values).' JP per minggu disimpan, berlaku mulai '.$date.'.';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function rm_target_compare(?int $target,float $actual): array {
    return ['target'=>$target,'aktual'=>$actual,'kurang'=>$target===null?null:max(0,$target-$actual),'lebih'=>$target===null?null:max(0,$actual-$target),
        'label'=>$target===null?'Belum diatur':($target===0?'Tanpa target':($actual>=$target?'Target tercapai':'Belum tercapai'))];
}
/** Acuan formulir admin; tidak membuat realisasi atau mengubah target. */
function rm_target_input_summary(PDO $db,int $actor,array $input): array {
    $q=$db->prepare('SELECT role_id,is_active FROM users WHERE id=?');$q->execute([$actor]);$admin=$q->fetch(PDO::FETCH_ASSOC);
    if(!$admin||(int)$admin['role_id']!==1||(int)$admin['is_active']!==1)throw new InvalidArgumentException('Acuan input hanya tersedia bagi admin aktif.');
    $guru=$input['guru_id']??null;$date=$input['tanggal']??null;
    if(!is_string($guru)||!preg_match('/^[1-9][0-9]{0,9}$/D',$guru)||!is_string($date))throw new InvalidArgumentException('Pilih guru dan tanggal yang valid.');
    $week=rm_target_week($date);$q=$db->prepare('SELECT id FROM guru WHERE id=?');$q->execute([$guru]);if(!$q->fetchColumn())throw new InvalidArgumentException('Guru tidak ditemukan.');
    $base=['guru_id'=>(int)$guru,'tanggal'=>$date,'week'=>$week];
    if(!rm_target_ready($db))return $base+['ready'=>false,'configured'=>false,'message'=>'Target JP belum diaktifkan. Input manual tetap tersedia.'];
    $report=rm_target_report($db,$date,(int)$guru);$row=$report['rows'][0];
    $day=(int)(new DateTimeImmutable($date))->format('N')-1;
    if($day>4){
        $q=$db->prepare('SELECT COALESCE(SUM(r.jumlah_jp),0) FROM rekap_mengajar r JOIN pengajaran p ON p.id=r.pengajaran_id WHERE p.guru_id=? AND r.tanggal=?');$q->execute([(int)$guru,$date]);
        return $base+['ready'=>true,'configured'=>$row['versi']!==null,'working_day'=>false,'hari'=>$day===5?'Sabtu':'Minggu','harian'=>rm_target_compare(0,(float)$q->fetchColumn())+['tanggal'=>$date],'mingguan'=>$row['mingguan'],'berlaku_mulai'=>$row['versi']['berlaku_mulai']??null];
    }
    $key=array_keys(RM_TARGET_DAYS)[$day];
    return $base+['ready'=>true,'configured'=>$row['versi']!==null,'hari'=>RM_TARGET_DAYS[$key],'harian'=>$row['harian'][$key],'mingguan'=>$row['mingguan'],'berlaku_mulai'=>$row['versi']['berlaku_mulai']??null];
}
function rm_target_report(PDO $db,string $date,int $guru=0): array {
    $week=rm_target_week($date);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT id,nama_lengkap FROM guru'.($guru?' WHERE id=?':'').' ORDER BY nama_lengkap');$q->execute($guru?[$guru]:[]);$teachers=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT t.* FROM target_jp_guru t WHERE t.berlaku_mulai<=?'.($guru?' AND t.guru_id=?':'').' AND NOT EXISTS(SELECT 1 FROM target_jp_guru n WHERE n.guru_id=t.guru_id AND n.berlaku_mulai<=? AND (n.berlaku_mulai>t.berlaku_mulai OR (n.berlaku_mulai=t.berlaku_mulai AND n.id>t.id)))');$q->execute($guru?[$week['mulai'],$guru,$week['mulai']]:[$week['mulai'],$week['mulai']]);$targets=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$targets[(int)$r['guru_id']]=$r;
        $q=$db->prepare('SELECT p.guru_id,r.tanggal,SUM(r.jumlah_jp) jp FROM rekap_mengajar r JOIN pengajaran p ON p.id=r.pengajaran_id WHERE r.tanggal BETWEEN ? AND ?'.($guru?' AND p.guru_id=?':'').' GROUP BY p.guru_id,r.tanggal');$q->execute($guru?[$week['mulai'],$week['selesai'],$guru]:[$week['mulai'],$week['selesai']]);$actual=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$actual[(int)$r['guru_id']][$r['tanggal']]=(float)$r['jp'];
        $rows=[];foreach($teachers as $t){$id=(int)$t['id'];$target=$targets[$id]??null;$days=[];$total=0;$goal=$target?0:null;$offset=0;foreach(RM_TARGET_DAYS as $key=>$label){$day=(new DateTimeImmutable($week['mulai']))->modify('+'.$offset++.' days')->format('Y-m-d');$value=$actual[$id][$day]??0;$n=$target?(int)$target[$key]:null;$days[$key]=rm_target_compare($n,$value)+['tanggal'=>$day];$total+=$value;if($target)$goal+=$n;}$rows[]=['guru_id'=>$id,'nama'=>$t['nama_lengkap'],'versi'=>$target,'harian'=>$days,'mingguan'=>rm_target_compare($goal,$total)];}
        $db->commit();return ['week'=>$week,'rows'=>$rows];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
