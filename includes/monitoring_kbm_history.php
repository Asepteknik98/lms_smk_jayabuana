<?php
require_once __DIR__.'/monitoring_kbm_siswa.php';

function kbm_history_filters(array $input,string $today): array {
    $f=[];
    foreach(['tanggal_mulai'=>substr($today,0,7).'-01','tanggal_selesai'=>$today,'sesi'=>'','kelas'=>'','guru'=>'','status'=>'','halaman'=>'1','per_halaman'=>'25'] as $key=>$default) {
        $v=$input[$key]??$default;
        if(!is_string($v)) throw new InvalidArgumentException('Filter '.$key.' tidak valid.');
        $f[$key]=trim($v);
    }
    $start=kbm_date($f['tanggal_mulai']);$end=kbm_date($f['tanggal_selesai']);
    if($end<$start || $f['tanggal_selesai']>$today || $start->diff($end)->days>365) throw new InvalidArgumentException('Rentang tanggal harus berurutan, maksimal 366 hari, dan tidak melewati hari ini.');
    if(!in_array($f['sesi'],['','pagi','siang'],true)) throw new InvalidArgumentException('Sesi tidak valid.');
    foreach(['kelas','guru'] as $key) if($f[$key]!=='' && !preg_match('/^[1-9][0-9]{0,9}$/D',$f[$key])) throw new InvalidArgumentException('Filter '.$key.' tidak valid.');
    if(!in_array($f['status'],['','belum_mulai','belum_checkin','checkin','selesai','tanpa_checkin','dikoreksi','dibatalkan','guru_tidak_masuk'],true)) throw new InvalidArgumentException('Status tidak valid.');
    if(!preg_match('/^[1-9][0-9]{0,6}$/D',$f['halaman']) || !in_array($f['per_halaman'],['10','25','50'],true)) throw new InvalidArgumentException('Pilihan halaman tidak valid.');
    return $f;
}

/** Arsip tersimpan + jadwal bertanggal tanpa laporan. Tidak menulis saat GET. */
function kbm_history_sql(bool $simple=false): string {
    $modeWhere=$simple?" AND (JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.sumber'))='ketua_kelas' OR d.tanggal<(SELECT mulai FROM kbm_penyederhanaan WHERE id=1))":"";
    $json=fn($field)=>"CAST(JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.".$field."')) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci";
    return "WITH RECURSIVE kbm_dates AS (
        SELECT CAST(? AS DATE) tanggal UNION ALL SELECT tanggal+INTERVAL 1 DAY FROM kbm_dates WHERE tanggal<CAST(? AS DATE)
    ), records AS (
        SELECT p.jadwal_id,p.tanggal,p.id pertemuan_id,p.kelas_id,p.guru_id,p.mapel_id,p.tahun_ajaran,p.semester,
        p.nama_kelas,p.nama_guru,p.nama_mapel,p.sesi_kode,p.nama_sesi,p.jp_mulai,p.jp_selesai,p.rencana_mulai,p.rencana_selesai,
        l.checkin_at checkin_asli,l.checkout_at checkout_asli,l.nama_pelapor_masuk pelapor_masuk,l.nama_pelapor_keluar pelapor_keluar,
        l.checkin_siswa_id siswa_masuk_id,l.checkout_siswa_id siswa_keluar_id,
        CASE WHEN c.id IS NULL THEN l.checkin_at ELSE c.checkin_baru END checkin_efektif,
        CASE WHEN c.id IS NULL THEN l.checkout_at ELSE c.checkout_baru END checkout_efektif,
        c.id koreksi_id,c.alasan alasan_koreksi,c.admin_id,u.username admin_username,c.created_at koreksi_pada,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.sumber')) sumber,
        JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.dibatalkan')) dibatalkan,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.pembatalan_alasan')) pembatalan_alasan,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.pembatalan_oleh')) pembatalan_oleh,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.pembatalan_pada')) pembatalan_pada,JSON_EXTRACT(j.pengajaran_snapshot,'$.guru_tidak_masuk') guru_tidak_masuk
        FROM kbm_dates d JOIN kbm_pertemuan p ON p.tanggal=d.tanggal JOIN kbm_jadwal j ON j.id=p.jadwal_id
        LEFT JOIN kbm_laporan l ON l.pertemuan_id=p.id
        LEFT JOIN kbm_koreksi_admin c ON c.id=(SELECT MAX(x.id) FROM kbm_koreksi_admin x WHERE x.pertemuan_id=p.id)
        LEFT JOIN users u ON u.id=c.admin_id
        WHERE (j.nonaktif_mulai IS NULL OR p.tanggal<j.nonaktif_mulai OR JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.dibatalkan'))='1')
        AND (l.pertemuan_id IS NOT NULL OR c.id IS NOT NULL OR NOT ".kbm_choice_shadow_sql('j','p.tanggal').")
        UNION ALL
        SELECT j.id,d.tanggal,NULL,ks.kelas_id,j.guru_id,j.mapel_id,".$json('tahun_ajaran').",".$json('semester').",
        ".$json('nama_kelas').",".$json('nama_guru').",".$json('nama_mapel').",ks.sesi_kode,ss.nama,j.jp_mulai,j.jp_selesai,
        TIMESTAMP(d.tanggal,s1.mulai),TIMESTAMP(d.tanggal,s2.selesai),
        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.sumber')),NULL,NULL,NULL,NULL,JSON_EXTRACT(j.pengajaran_snapshot,'$.guru_tidak_masuk')
        FROM kbm_dates d JOIN kbm_jadwal j ON d.tanggal BETWEEN j.berlaku_mulai AND j.berlaku_sampai
        JOIN kbm_kelas_sesi ks ON ks.id=j.kelas_sesi_id AND (d.tanggal BETWEEN ks.berlaku_mulai AND ks.berlaku_sampai OR j.pola_id IS NULL)
        LEFT JOIN kbm_periode r ON r.id=ks.periode_id AND d.tanggal BETWEEN r.mulai AND r.selesai
        JOIN kbm_sesi_sekolah ss ON ss.kode=ks.sesi_kode
        LEFT JOIN kbm_pola_waktu p ON p.id=j.pola_id AND p.hari=DAYOFWEEK(d.tanggal+INTERVAL 6 DAY) AND d.tanggal BETWEEN p.berlaku_mulai AND p.berlaku_sampai
        LEFT JOIN kbm_slot_waktu s1 ON s1.pola_id=p.id AND s1.jp=j.jp_mulai AND s1.jenis='JP'
        LEFT JOIN kbm_slot_waktu s2 ON s2.pola_id=p.id AND s2.jp=j.jp_selesai AND s2.jenis='JP'
        WHERE (j.nonaktif_mulai IS NULL OR d.tanggal<j.nonaktif_mulai)
        AND NOT ".kbm_choice_shadow_sql('j','d.tanggal')."
        AND NOT EXISTS(SELECT 1 FROM kbm_pertemuan saved WHERE saved.jadwal_id=j.id AND saved.tanggal=d.tanggal)
        AND (j.pola_id IS NULL OR (r.id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM kbm_hari_tanpa_kbm h WHERE h.periode_id=r.id AND h.tanggal=d.tanggal AND (h.kelas_id IS NULL OR h.kelas_id=ks.kelas_id) AND (h.sesi_kode IS NULL OR h.sesi_kode=ks.sesi_kode))))
        AND (JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.sumber'))='ketua_kelas' OR NOT EXISTS(SELECT 1 FROM kbm_slot_waktu b WHERE b.pola_id=p.id AND b.jenis='ISTIRAHAT' AND b.mulai<s2.selesai AND b.selesai>s1.mulai))
        AND ((j.pola_id IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.sumber'))='ketua_kelas' AND j.berlaku_mulai=j.berlaku_sampai) OR (p.id IS NOT NULL AND s1.id IS NOT NULL AND s2.id IS NOT NULL AND (SELECT COUNT(*) FROM kbm_slot_waktu b WHERE b.pola_id=p.id AND b.jenis='JP' AND b.jp BETWEEN j.jp_mulai AND j.jp_selesai)=j.jp_selesai-j.jp_mulai+1))
        ".$modeWhere."
    ), history AS (SELECT r.*,".kbm_meeting_status_sql()." status_code FROM records r CROSS JOIN (SELECT CAST(? AS DATETIME) now_server) clock) ";
}

function kbm_history_page(PDO $db,array $input): array {
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
    try {
        $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();$f=kbm_history_filters($input,substr($now,0,10));
        [$suffix,$params]=kbm_history_condition($f,$now);
        $total=(int)kbm_rows($db,kbm_history_sql(kbm_simple_ready($db)).'SELECT COUNT(*) total '.$suffix,$params)[0]['total'];
        $pages=max(1,(int)ceil($total/(int)$f['per_halaman']));$page=min((int)$f['halaman'],$pages);$f['halaman']=(string)$page;
        $offset=($page-1)*(int)$f['per_halaman'];
        $rows=kbm_rows($db,kbm_history_sql(kbm_simple_ready($db)).'SELECT * '.$suffix.' ORDER BY tanggal DESC,rencana_mulai DESC,kelas_id,jadwal_id LIMIT '.(int)$f['per_halaman'].' OFFSET '.$offset,$params);
        foreach($rows as &$r) {$r['status']=kbm_meeting_status($r,$now);$r['duration']=kbm_student_duration($r['checkin_efektif'],$r['checkout_efektif']);}unset($r);
        // Nama pilihan berasal dari arsip, sehingga master yang diganti tidak menghilangkan filter lama.
        $classes=kbm_rows($db,"SELECT kelas_id id,MAX(nama_kelas) nama FROM (SELECT kelas_id,nama_kelas FROM kbm_pertemuan UNION ALL SELECT ks.kelas_id,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.nama_kelas')) FROM kbm_jadwal j JOIN kbm_kelas_sesi ks ON ks.id=j.kelas_sesi_id) choices GROUP BY kelas_id ORDER BY nama");
        $teachers=kbm_rows($db,"SELECT guru_id id,MAX(nama_guru) nama FROM (SELECT guru_id,nama_guru FROM kbm_pertemuan UNION ALL SELECT guru_id,JSON_UNQUOTE(JSON_EXTRACT(pengajaran_snapshot,'$.nama_guru')) FROM kbm_jadwal) choices GROUP BY guru_id ORDER BY nama");
        $db->commit();return ['rows'=>$rows,'filters'=>$f,'total'=>$total,'pages'=>$pages,'now'=>$now,'classes'=>$classes,'teachers'=>$teachers];
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Satu kondisi filter untuk tabel dan ekspor, tanpa memasukkan LIMIT pagination. */
function kbm_history_condition(array $f,string $now): array {
    $where=[];$params=[$f['tanggal_mulai'],$f['tanggal_selesai'],$now];
    foreach(['sesi'=>'sesi_kode','kelas'=>'kelas_id','guru'=>'guru_id'] as $key=>$column) if($f[$key]!=='') {$where[]=$column.'=?';$params[]=$f[$key];}
    if($f['status']==='dikoreksi') $where[]='koreksi_id IS NOT NULL';
    elseif($f['status']!=='') {$where[]='status_code=?';$params[]=$f['status'];}
    return ['FROM history'.($where?' WHERE '.implode(' AND ',$where):''),$params];
}

function kbm_history_row(PDO $db,int $schedule,string $date): ?array {
    kbm_date($date);$now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
    if($date>substr($now,0,10)) throw new InvalidArgumentException('Tanggal pertemuan belum berlangsung.');
    return kbm_rows($db,kbm_history_sql(kbm_simple_ready($db)).'SELECT * FROM history WHERE jadwal_id=? AND tanggal=?',[$date,$date,$now,$schedule,$date])[0]??null;
}

function kbm_history_revision(array $r): string {
    return hash('sha256',json_encode([$r['pertemuan_id'],$r['checkin_asli'],$r['checkout_asli'],$r['koreksi_id'],$r['checkin_efektif'],$r['checkout_efektif'],$r['guru_tidak_masuk']??null],JSON_THROW_ON_ERROR));
}

function kbm_history_correct(PDO $db,int $admin,array $input): int {
    $schedule=kbm_int($input,'jadwal_id');$date=kbm_input($input,'tanggal');kbm_date($date);
    $revision=kbm_input($input,'revision');$reason=kbm_input($input,'alasan');
    $values=[];
    foreach(['checkin','checkout'] as $key) {
        $value=kbm_input($input,$key,false);
        if($value==='') {$values[$key]=null;continue;}
        if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$value)) $value.=':00';
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s',$value,new DateTimeZone('Asia/Jakarta'));
        if(!$d || $d->format('Y-m-d\TH:i:s')!==$value) throw new InvalidArgumentException('Waktu koreksi harus memuat tanggal, jam, menit, dan detik yang valid.');
        $values[$key]=$d->format('Y-m-d H:i:s');
    }
    return kbm_admin_write($db,$admin,function()use($db,$admin,$schedule,$date,$revision,$reason,$values) {
        $scope=kbm_rows($db,'SELECT a.kelas_id FROM kbm_jadwal j JOIN kbm_kelas_sesi a ON a.id=j.kelas_sesi_id WHERE j.id=?',[$schedule]);
        if(!$scope) throw new InvalidArgumentException('Jadwal tidak ditemukan.');
        kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$scope[0]['kelas_id']]);
        $row=kbm_history_row($db,$schedule,$date);
        if(!$row) throw new InvalidArgumentException('Pertemuan historis tidak ditemukan.');
        if(!hash_equals(kbm_history_revision($row),$revision)) throw new InvalidArgumentException('Catatan telah berubah sejak dibuka. Muat ulang dan periksa nilai terbaru sebelum mengoreksi.');
        $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        if(($row['sumber']??'')!=='ketua_kelas' && $row['rencana_mulai']>$now) throw new InvalidArgumentException('Pertemuan belum dimulai dan tidak dapat dikoreksi.');
        if($row['pertemuan_id']===null) {
            $planned=null;foreach(kbm_expected($db,$date) as $r) if((int)$r['jadwal_id']===$schedule){$planned=$r;break;}
            if(!$planned) throw new InvalidArgumentException('Jadwal bertanggal tidak tersedia.');
            $meeting=kbm_store_meeting($db,$admin,$planned);
        } else $meeting=(int)$row['pertemuan_id'];
        return kbm_insert_correction($db,$admin,$meeting,$values['checkin'],$values['checkout'],$reason);
    });
}

function kbm_history_audit(PDO $db,int $meeting): array {
    return kbm_rows($db,'SELECT c.*,u.username FROM kbm_koreksi_admin c JOIN users u ON u.id=c.admin_id WHERE c.pertemuan_id=? ORDER BY c.id DESC',[$meeting]);
}
