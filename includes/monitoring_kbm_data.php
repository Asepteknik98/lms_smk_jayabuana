<?php
require_once __DIR__.'/monitoring_kbm_simple.php';
// Fondasi data saja: tidak menyediakan endpoint, tidak mengubah role atau session.
function kbm_rows(PDO $db, string $sql, array $params = []): array {
    $q = $db->prepare($sql); $q->execute($params); return $q->fetchAll(PDO::FETCH_ASSOC);
}

function kbm_date(string $value): DateTimeImmutable {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
    if (!$d || $d->format('Y-m-d') !== $value) throw new InvalidArgumentException('Tanggal KBM tidak valid.');
    return $d;
}

function kbm_range(string $start, string $end, array $period): void {
    kbm_date($start); kbm_date($end);
    if ($end < $start || $start < $period['mulai'] || $end > $period['selesai']) {
        throw new InvalidArgumentException('Tanggal berlaku harus berada antara '.$period['mulai'].' sampai '.$period['selesai'].', dan tanggal selesai tidak boleh sebelum tanggal mulai. Untuk jadwal, rentang juga harus sesuai sesi kelas dan pola waktu JP.');
    }
}

/** Setiap operasi konfigurasi diserialisasi; tidak boleh dipanggil dalam transaksi lain. */
function kbm_admin_write(PDO $db, int $admin, callable $operation): mixed {
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $db->beginTransaction();
    try {
        // Mengunci semua admin aktif juga mencegah bentrok lintas periode yang beririsan.
        $admins = kbm_rows($db, 'SELECT id FROM users WHERE role_id=1 AND is_active=1 ORDER BY id FOR UPDATE');
        if (!in_array($admin, array_map('intval', array_column($admins, 'id')), true)) {
            throw new RuntimeException('Hanya admin aktif yang dapat mengubah konfigurasi KBM.');
        }
        $db->exec("SET time_zone = '+07:00'");
        $result = $operation(); $db->commit(); return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack(); throw $e;
    }
}

function kbm_period(PDO $db, int $id): array {
    $rows = kbm_rows($db, 'SELECT * FROM kbm_periode WHERE id=?', [$id]);
    if (!$rows) throw new InvalidArgumentException('Periode KBM tidak ditemukan.');
    return $rows[0];
}

function kbm_revoke_chair(PDO $db,int $admin,int $assignment): void {
    kbm_admin_write($db,$admin,function () use($db,$admin,$assignment) {
        $q=$db->prepare('UPDATE kbm_ketua_kelas SET dicabut_pada=CURRENT_TIMESTAMP,dicabut_oleh=? WHERE id=? AND dicabut_pada IS NULL');
        $q->execute([$admin,$assignment]);
        if(!$q->rowCount()) throw new InvalidArgumentException('Penugasan tidak ditemukan atau sudah dicabut.');
    });
}

/** Izin tambahan; selalu periksa user aktif dan keanggotaan kelas terkini. */
function kbm_chair_permission(PDO $db,int $user,int $period,int $class): ?array {
    if(kbm_simple_ready($db)){foreach(kbm_simple_chairs($db,$user) as $a)if((int)$a['kelas_id']===$class)return $a;return null;}
    $rows=kbm_rows($db,'SELECT a.* FROM kbm_ketua_kelas a JOIN siswa s ON s.id=a.siswa_id JOIN users u ON u.id=s.user_id
        WHERE u.id=? AND u.role_id=3 AND u.is_active=1 AND s.kelas_id=a.kelas_id AND a.periode_id=? AND a.kelas_id=?
        AND a.dicabut_pada IS NULL AND CURRENT_DATE BETWEEN a.berlaku_mulai AND a.berlaku_sampai',[$user,$period,$class]);
    return $rows[0]??null;
}

/** JP harus lengkap 1–6, berurutan, tanpa tumpang tindih; istirahat boleh lebih dari satu. */
function kbm_validate_slots(array $slots): array {
    $last = '00:00:00'; $jp = 0; $result = [];
    foreach ($slots as $slot) {
        $kind = $slot['jenis'] ?? '';
        $start = $slot['mulai'] ?? ''; $end = $slot['selesai'] ?? '';
        foreach ([$start, $end] as $time) {
            if (!is_string($time) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D', $time)) {
                throw new InvalidArgumentException('Jam harus HH:MM:SS.');
            }
        }
        if ($start < $last || $end <= $start) throw new InvalidArgumentException('Slot waktu bertumpang tindih atau terbalik.');
        if ($kind === 'JP') {
            if ((int)($slot['jp'] ?? 0) !== ++$jp || $jp > 6) throw new InvalidArgumentException('JP harus berurutan 1–6.');
        } elseif ($kind !== 'ISTIRAHAT' || ($slot['jp'] ?? null) !== null) {
            throw new InvalidArgumentException('Jenis slot tidak valid.');
        }
        $result[] = ['jenis'=>$kind, 'jp'=>$kind === 'JP' ? $jp : null, 'mulai'=>$start, 'selesai'=>$end];
        $last = $end;
    }
    if ($jp !== 6 || count($result) > 255) throw new InvalidArgumentException('Wajib menyediakan JP 1–6.');
    return $result;
}

function kbm_block(array $slots, int $first, int $last): array {
    if ($first < 1 || $last > 6 || $last < $first) throw new InvalidArgumentException('Rentang JP tidak valid.');
    $selected = array_values(array_filter($slots, fn($s) => $s['jenis'] === 'JP' && $s['jp'] >= $first && $s['jp'] <= $last));
    if (count($selected) !== $last - $first + 1) throw new InvalidArgumentException('JP belum dikonfigurasi.');
    $start = $selected[0]['mulai']; $end = $selected[count($selected)-1]['selesai'];
    foreach ($slots as $s) {
        if ($s['jenis'] === 'ISTIRAHAT' && $s['mulai'] < $end && $s['selesai'] > $start) {
            throw new InvalidArgumentException('Pertemuan melintasi istirahat. Pecah menjadi blok jadwal terpisah.');
        }
    }
    return ['mulai'=>$start, 'selesai'=>$end, 'slots'=>$selected];
}

/** Tambah versi konfigurasi; tidak menyediakan overwrite atau hapus data historis. */
function kbm_configure(PDO $db, int $admin, string $type, array $data): int {
    return kbm_admin_write($db, $admin, function () use ($db, $admin, $type, $data) {
        if ($type === 'periode') {
            if (!preg_match('/^(\d{4})\/(\d{4})$/D', $data['tahun_ajaran'] ?? '', $m) || (int)$m[2] !== (int)$m[1]+1 || !in_array($data['semester'] ?? '', ['Ganjil','Genap'], true)) {
                throw new InvalidArgumentException('Tahun ajaran atau semester tidak valid.');
            }
            kbm_date($data['mulai']); kbm_date($data['selesai']);
            if ($data['selesai'] < $data['mulai']) throw new InvalidArgumentException('Rentang periode terbalik.');
            $db->prepare('INSERT INTO kbm_periode(tahun_ajaran,semester,mulai,selesai,dibuat_oleh) VALUES(?,?,?,?,?)')
                ->execute([$data['tahun_ajaran'],$data['semester'],$data['mulai'],$data['selesai'],$admin]);
            return (int)$db->lastInsertId();
        }
        $period = kbm_period($db, (int)$data['periode_id']);
        if ($type === 'hari_tanpa_kbm') {
            kbm_range($data['tanggal'], $data['tanggal'], $period);
            $reason = trim($data['alasan'] ?? '');
            if ($reason === '' || mb_strlen($reason) > 255) throw new InvalidArgumentException('Alasan wajib, maksimal 255 karakter.');
            $db->prepare('INSERT INTO kbm_hari_tanpa_kbm(periode_id,tanggal,kelas_id,sesi_kode,alasan,dibuat_oleh) VALUES(?,?,?,?,?,?)')
                ->execute([$period['id'],$data['tanggal'],$data['kelas_id'] ?? null,$data['sesi_kode'] ?? null,$reason,$admin]);
            return (int)$db->lastInsertId();
        }
        kbm_range($data['berlaku_mulai'], $data['berlaku_sampai'], $period);
        $start = $data['berlaku_mulai']; $end = $data['berlaku_sampai'];
        if ($type === 'pola_waktu') {
            $slots = kbm_validate_slots($data['slots']); $day = (int)$data['hari'];
            if ($day < 1 || $day > 7) throw new InvalidArgumentException('Hari harus 1–7.');
            if (kbm_rows($db, 'SELECT id FROM kbm_pola_waktu WHERE periode_id=? AND sesi_kode=? AND hari=? AND berlaku_mulai<=? AND berlaku_sampai>=?', [$period['id'],$data['sesi_kode'],$day,$end,$start])) {
                throw new InvalidArgumentException('Versi pola waktu bertumpang tindih.');
            }
            $db->prepare('INSERT INTO kbm_pola_waktu(periode_id,sesi_kode,hari,berlaku_mulai,berlaku_sampai,dibuat_oleh) VALUES(?,?,?,?,?,?)')->execute([$period['id'],$data['sesi_kode'],$day,$start,$end,$admin]);
            $id = (int)$db->lastInsertId();
            $q = $db->prepare('INSERT INTO kbm_slot_waktu(pola_id,urutan,jenis,jp,mulai,selesai) VALUES(?,?,?,?,?,?)');
            foreach ($slots as $i=>$s) $q->execute([$id,$i+1,$s['jenis'],$s['jp'],$s['mulai'],$s['selesai']]);
            return $id;
        }
        if ($type === 'kelas_sesi' || $type === 'ketua_kelas') {
            $chair = $type === 'ketua_kelas';
            $table = $chair ? 'kbm_ketua_kelas' : 'kbm_kelas_sesi';
            $extra = $chair ? ' AND dicabut_pada IS NULL' : '';
            if (kbm_rows($db, "SELECT id FROM $table WHERE periode_id=? AND kelas_id=? AND berlaku_mulai<=? AND berlaku_sampai>=?$extra", [$period['id'],$data['kelas_id'],$end,$start])) {
                throw new InvalidArgumentException('Penugasan kelas pada rentang tersebut sudah ada.');
            }
            if ($chair) {
                if (!kbm_rows($db, 'SELECT s.id FROM siswa s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.kelas_id=? AND u.role_id=3 AND u.is_active=1', [$data['siswa_id'],$data['kelas_id']])) {
                    throw new InvalidArgumentException('Ketua harus siswa aktif dari kelas yang dituju.');
                }
                $db->prepare('INSERT INTO kbm_ketua_kelas(periode_id,kelas_id,siswa_id,berlaku_mulai,berlaku_sampai,ditunjuk_oleh) VALUES(?,?,?,?,?,?)')->execute([$period['id'],$data['kelas_id'],$data['siswa_id'],$start,$end,$admin]);
            } else {
                $db->prepare('INSERT INTO kbm_kelas_sesi(periode_id,kelas_id,sesi_kode,berlaku_mulai,berlaku_sampai,dibuat_oleh) VALUES(?,?,?,?,?,?)')->execute([$period['id'],$data['kelas_id'],$data['sesi_kode'],$start,$end,$admin]);
            }
            return (int)$db->lastInsertId();
        }
        throw new InvalidArgumentException('Jenis konfigurasi tidak dikenal.');
    });
}

function kbm_create_schedule(PDO $db, int $admin, array $data): int {
    return kbm_admin_write($db, $admin, fn()=>kbm_insert_schedule($db,$admin,$data));
}

/** Internal: hanya dalam transaksi kbm_admin_write. */
function kbm_insert_schedule(PDO $db, int $admin, array $data): int {
        if (!$db->inTransaction()) throw new LogicException('Transaksi admin KBM diperlukan.');
        $rows = kbm_rows($db, 'SELECT ks.*, p.hari,p.berlaku_mulai pola_mulai,p.berlaku_sampai pola_sampai FROM kbm_kelas_sesi ks JOIN kbm_pola_waktu p ON p.periode_id=ks.periode_id AND p.sesi_kode=ks.sesi_kode WHERE ks.id=? AND p.id=?', [$data['kelas_sesi_id'],$data['pola_id']]);
        if (!$rows) throw new InvalidArgumentException('Pola waktu dan sesi kelas tidak sesuai.');
        $ks = $rows[0]; $period = kbm_period($db, (int)$ks['periode_id']);
        $teaching = kbm_rows($db, 'SELECT g.*,k.nama_kelas,t.nama_lengkap nama_guru,m.nama_mapel FROM pengajaran g JOIN kelas k ON k.id=g.kelas_id JOIN guru t ON t.id=g.guru_id JOIN mapel m ON m.id=g.mapel_id WHERE g.id=? AND g.kelas_id=? AND g.tahun_ajaran=? AND g.semester=?', [$data['pengajaran_id'],$ks['kelas_id'],$period['tahun_ajaran'],$period['semester']]);
        if (!$teaching) throw new InvalidArgumentException('Pengajaran harus sesuai kelas, tahun ajaran, dan semester.');
        $start = $data['berlaku_mulai']; $end = $data['berlaku_sampai'];
        kbm_range($start,$end,$period);
        kbm_range($start,$end,['mulai'=>max($ks['berlaku_mulai'],$ks['pola_mulai']), 'selesai'=>min($ks['berlaku_sampai'],$ks['pola_sampai'])]);
        $block = kbm_block(kbm_rows($db, 'SELECT * FROM kbm_slot_waktu WHERE pola_id=? ORDER BY urutan', [$data['pola_id']]), (int)$data['jp_mulai'], (int)$data['jp_selesai']);
        $others = kbm_rows($db, 'SELECT j.*,p.hari FROM kbm_jadwal j JOIN kbm_kelas_sesi ks ON ks.id=j.kelas_sesi_id JOIN kbm_pola_waktu p ON p.id=j.pola_id WHERE p.hari=? AND j.berlaku_mulai<=? AND j.berlaku_sampai>=? AND (j.nonaktif_mulai IS NULL OR j.nonaktif_mulai>?) AND (ks.kelas_id=? OR j.guru_id=?)', [$ks['hari'],$end,$start,$start,$ks['kelas_id'],$teaching[0]['guru_id']]);
        foreach ($others as $other) {
            $b = kbm_block(kbm_rows($db, 'SELECT * FROM kbm_slot_waktu WHERE pola_id=? ORDER BY urutan', [$other['pola_id']]), (int)$other['jp_mulai'], (int)$other['jp_selesai']);
            if ($b['mulai'] < $block['selesai'] && $b['selesai'] > $block['mulai']) throw new InvalidArgumentException('Jadwal kelas atau guru bertabrakan.');
        }
        $snapshot=array_intersect_key($teaching[0],array_flip(['kelas_id','guru_id','mapel_id','nama_kelas','nama_guru','nama_mapel','tahun_ajaran','semester']));
        $db->prepare('INSERT INTO kbm_jadwal(kelas_sesi_id,pola_id,pengajaran_id,guru_id,mapel_id,pengajaran_snapshot,jp_mulai,jp_selesai,berlaku_mulai,berlaku_sampai,dibuat_oleh) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$data['kelas_sesi_id'],$data['pola_id'],$data['pengajaran_id'],$teaching[0]['guru_id'],$teaching[0]['mapel_id'],json_encode($snapshot,JSON_THROW_ON_ERROR),$data['jp_mulai'],$data['jp_selesai'],$start,$end,$admin]);
        return (int)$db->lastInsertId();
}

/** Read-only: jadwal tetap muncul walau belum ada pertemuan maupun laporan siswa. */
/** Nomor JP saja: tidak menyatakan jam mulai/selesai yang tidak dikonfigurasi. */
function kbm_pilihan_tanpa_jam_ready(PDO $db): bool {
    return (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND is_nullable='YES' AND ((table_name='kbm_jadwal' AND column_name='pola_id') OR (table_name='kbm_pertemuan' AND column_name IN ('rencana_mulai','rencana_selesai')))")->fetchColumn()===3;
}
function kbm_numbered_slots(): array {
    return array_map(fn($n)=>['jenis'=>'JP','jp'=>$n,'urutan'=>$n,'mulai'=>null,'selesai'=>null],range(1,6));
}
function kbm_pilihan_block(array $slots,int $start,int $end): array {
    if($start<1||$end>6||$end<$start)throw new InvalidArgumentException('Rentang JP harus 1 sampai 6 dan berurutan.');
    $chosen=array_values(array_filter($slots,fn($s)=>$s['jenis']==='JP'&&(int)$s['jp']>=$start&&(int)$s['jp']<=$end));
    usort($chosen,fn($a,$b)=>(int)$a['jp']<=>(int)$b['jp']);
    if(count($chosen)!==$end-$start+1)throw new InvalidArgumentException('Nomor JP belum lengkap pada pengaturan kelas.');
    return ['mulai'=>$chosen[0]['mulai'],'selesai'=>$chosen[count($chosen)-1]['selesai'],'slots'=>$chosen];
}
function kbm_expected(PDO $db, string $date): array {
    $day = (int)kbm_date($date)->format('N');
    $simple=kbm_simple_ready($db);
    $manual="JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.sumber'))='ketua_kelas'";
    $modeWhere=$simple?" AND ($manual OR ?<(SELECT mulai FROM kbm_penyederhanaan WHERE id=1))":'';
    $rows = kbm_rows($db, 'SELECT j.*,ks.periode_id,ks.kelas_id,ks.sesi_kode,s.nama nama_sesi,r.tahun_ajaran,r.semester,k.nama_kelas,t.nama_lengkap nama_guru,m.nama_mapel
        FROM kbm_jadwal j JOIN kbm_kelas_sesi ks ON ks.id=j.kelas_sesi_id LEFT JOIN kbm_pola_waktu p ON p.id=j.pola_id
        LEFT JOIN kbm_periode r ON r.id=ks.periode_id JOIN kbm_sesi_sekolah s ON s.kode=ks.sesi_kode
        JOIN kelas k ON k.id=ks.kelas_id JOIN guru t ON t.id=j.guru_id LEFT JOIN mapel m ON m.id=j.mapel_id
        WHERE (p.hari=? OR (j.pola_id IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,\'$.sumber\'))=\'ketua_kelas\' AND j.berlaku_mulai=j.berlaku_sampai)) AND ? BETWEEN j.berlaku_mulai AND j.berlaku_sampai AND (j.nonaktif_mulai IS NULL OR j.nonaktif_mulai>?)
        AND (? BETWEEN ks.berlaku_mulai AND ks.berlaku_sampai OR (j.pola_id IS NULL AND j.berlaku_mulai=j.berlaku_sampai)) AND (j.pola_id IS NULL OR ? BETWEEN p.berlaku_mulai AND p.berlaku_sampai)
        AND ((j.pola_id IS NULL AND j.berlaku_mulai=j.berlaku_sampai) OR (? BETWEEN r.mulai AND r.selesai AND NOT EXISTS(SELECT 1 FROM kbm_hari_tanpa_kbm h WHERE h.periode_id=r.id AND h.tanggal=? AND (h.kelas_id IS NULL OR h.kelas_id=ks.kelas_id) AND (h.sesi_kode IS NULL OR h.sesi_kode=ks.sesi_kode))))'. $modeWhere.' ORDER BY ks.kelas_id,j.jp_mulai,j.id', [$day,$date,$date,$date,$date,$date,$date,...($simple?[$date]:[])]);
    $patternIds=array_values(array_unique(array_column($rows,'pola_id')));$slotsByPattern=[];
    if($patternIds) foreach(kbm_rows($db,'SELECT * FROM kbm_slot_waktu WHERE pola_id IN ('.implode(',',array_fill(0,count($patternIds),'?')).') ORDER BY pola_id,urutan',$patternIds) as $slot) $slotsByPattern[$slot['pola_id']][]=$slot;
    foreach ($rows as $i=>&$r) {
        $r=array_merge($r,json_decode($r['pengajaran_snapshot'],true,512,JSON_THROW_ON_ERROR));
        try { $b = ($r['sumber']??'')==='ketua_kelas'?kbm_pilihan_block($r['pola_id']===null?kbm_numbered_slots():($slotsByPattern[$r['pola_id']]??[]),(int)$r['jp_mulai'],(int)$r['jp_selesai']):kbm_block($slotsByPattern[$r['pola_id']]??[], (int)$r['jp_mulai'],(int)$r['jp_selesai']); }
        catch(InvalidArgumentException $e) {unset($rows[$i]);continue;}
        $r['tanggal']=$date; $r['jadwal_id']=$r['id']; $r['rencana_mulai']=$b['mulai']===null?null:$date.' '.$b['mulai']; $r['rencana_selesai']=$b['selesai']===null?null:$date.' '.$b['selesai'];
        $r['jp_snapshot']=json_encode($b['slots'],JSON_THROW_ON_ERROR); $r['zona_waktu']='Asia/Jakarta';
    }
    unset($r);
    $manual=array_filter($rows,fn($r)=>($r['sumber']??'')==='ketua_kelas');
    foreach($rows as $i=>$r)if(($r['sumber']??'')!=='ketua_kelas')foreach($manual as $choice)if((int)$r['kelas_id']===(int)$choice['kelas_id']&&$r['jp_mulai']<=$choice['jp_selesai']&&$r['jp_selesai']>=$choice['jp_mulai']){unset($rows[$i]);break;}
    return array_values($rows);
}

/** Pilihan harian menggantikan rencana admin yang bertumpang tindih, bukan laporan aslinya. */
function kbm_choice_shadow_sql(string $alias,string $date): string {
    return "(COALESCE(JSON_UNQUOTE(JSON_EXTRACT($alias.pengajaran_snapshot,'$.sumber')),'')<>'ketua_kelas' AND EXISTS(SELECT 1 FROM kbm_jadwal choice WHERE choice.kelas_sesi_id=$alias.kelas_sesi_id AND choice.berlaku_mulai=$date AND choice.berlaku_sampai=$date AND (choice.nonaktif_mulai IS NULL OR $date<choice.nonaktif_mulai) AND JSON_UNQUOTE(JSON_EXTRACT(choice.pengajaran_snapshot,'$.sumber'))='ketua_kelas' AND choice.jp_mulai<=$alias.jp_selesai AND choice.jp_selesai>=$alias.jp_mulai))";
}

/** Dipanggil job/admin secara eksplisit, bukan GET dashboard Akademik. */
function kbm_materialize(PDO $db, int $admin, string $date): int {
    return kbm_admin_write($db,$admin,function () use ($db,$admin,$date) {
        $count=0;
        foreach(kbm_expected($db,$date) as $r) { kbm_store_meeting($db,$admin,$r); $count++; }
        return $count;
    });
}

/** Internal: actor adalah user pembuat (admin/job atau ketua yang tervalidasi). */
function kbm_store_meeting(PDO $db,int $actor,array $row): int {
    if(!$db->inTransaction()) throw new LogicException('Transaksi KBM diperlukan.');
    $columns=['jadwal_id','tanggal','kelas_id','guru_id','mapel_id','tahun_ajaran','semester','nama_kelas','nama_guru','nama_mapel','sesi_kode','nama_sesi','jp_mulai','jp_selesai','rencana_mulai','rencana_selesai','jp_snapshot','zona_waktu'];
    $sql='INSERT INTO kbm_pertemuan('.implode(',',$columns).',dibuat_oleh) VALUES('.implode(',',array_fill(0,count($columns)+1,'?')).') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)';
    $db->prepare($sql)->execute([...array_map(fn($c)=>$row[$c],$columns),$actor]);
    return (int)$db->lastInsertId();
}

/** Seluruh tanggal/periode: laporan terlambat tetap menghalangi pertemuan baru. */
function kbm_open_meetings(PDO $db,?int $class=null): array {
    return kbm_rows($db,'SELECT p.*,p.id pertemuan_id,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,\'$.sumber\')) sumber,l.checkin_at,l.checkout_at,c.id koreksi_id,
        CASE WHEN c.id IS NULL THEN l.checkin_at ELSE c.checkin_baru END checkin_efektif,
        CASE WHEN c.id IS NULL THEN l.checkout_at ELSE c.checkout_baru END checkout_efektif
        FROM (SELECT pertemuan_id FROM kbm_laporan WHERE checkout_at IS NULL
              UNION SELECT pertemuan_id FROM kbm_koreksi_admin WHERE checkout_baru IS NULL AND checkin_baru IS NOT NULL) candidate
        JOIN kbm_pertemuan p ON p.id=candidate.pertemuan_id JOIN kbm_jadwal j ON j.id=p.jadwal_id LEFT JOIN kbm_laporan l ON l.pertemuan_id=p.id
        LEFT JOIN kbm_koreksi_admin c ON c.id=(SELECT MAX(x.id) FROM kbm_koreksi_admin x WHERE x.pertemuan_id=p.id)
        WHERE '.($class!==null?'p.kelas_id=? AND ':'').'(CASE WHEN c.id IS NULL THEN l.checkin_at ELSE c.checkin_baru END) IS NOT NULL
        AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,\'$.dibatalkan\')),\'0\')<>\'1\'
        AND (CASE WHEN c.id IS NULL THEN l.checkout_at ELSE c.checkout_baru END) IS NULL ORDER BY p.tanggal,p.id',$class!==null?[$class]:[]);
}

function kbm_day_report(PDO $db, string $date): array {
    kbm_date($date);
    $saved=kbm_rows($db,'SELECT p.*,p.id pertemuan_id,JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,\'$.sumber\')) sumber,JSON_EXTRACT(j.pengajaran_snapshot,\'$.guru_tidak_masuk\') guru_tidak_masuk,l.checkin_at,l.checkout_at,l.checkin_siswa_id,l.checkout_siswa_id,l.ditutup_sistem_pada,
        c.id koreksi_id,CASE WHEN c.id IS NULL THEN l.checkin_at ELSE c.checkin_baru END checkin_efektif,
        CASE WHEN c.id IS NULL THEN l.checkout_at ELSE c.checkout_baru END checkout_efektif
        FROM kbm_pertemuan p JOIN kbm_jadwal j ON j.id=p.jadwal_id LEFT JOIN kbm_laporan l ON l.pertemuan_id=p.id
        LEFT JOIN kbm_koreksi_admin c ON c.id=(SELECT MAX(c2.id) FROM kbm_koreksi_admin c2 WHERE c2.pertemuan_id=p.id)
        WHERE p.tanggal=? AND (j.nonaktif_mulai IS NULL OR p.tanggal<j.nonaktif_mulai)
        AND (l.pertemuan_id IS NOT NULL OR c.id IS NOT NULL OR NOT '.kbm_choice_shadow_sql('j','p.tanggal').') ORDER BY p.kelas_id,p.rencana_mulai,p.id',[$date]);
    $result=[]; foreach($saved as $r) $result[$r['jadwal_id']]=$r;
    foreach(kbm_expected($db,$date) as $r) if(!isset($result[$r['jadwal_id']])) $result[$r['jadwal_id']]=array_merge($r,['pertemuan_id'=>null,'checkin_efektif'=>null,'checkout_efektif'=>null]);
    $result=array_values($result);
    usort($result,fn($a,$b)=>[$a['kelas_id'],$a['rencana_mulai'],$a['jadwal_id']]<=>[$b['kelas_id'],$b['rencana_mulai'],$b['jadwal_id']]);
    return $result;
}

/** Koreksi tidak menulis ulang laporan asli; NULL dapat digunakan untuk membatalkan waktu salah. */
function kbm_correct(PDO $db,int $admin,int $meeting,?string $in,?string $out,string $reason): int {
    return kbm_admin_write($db,$admin,fn()=>kbm_insert_correction($db,$admin,$meeting,$in,$out,$reason));
}

/** Internal: digunakan halaman koreksi dalam transaksi admin yang sama. */
function kbm_insert_correction(PDO $db,int $admin,int $meeting,?string $in,?string $out,string $reason): int {
        if(!$db->inTransaction()) throw new LogicException('Transaksi admin KBM diperlukan.');
        $reason=trim($reason);
        if($reason==='' || mb_strlen($reason)>1000) throw new InvalidArgumentException('Alasan koreksi wajib, maksimal 1000 karakter.');
        $identity=kbm_rows($db,'SELECT kelas_id FROM kbm_pertemuan WHERE id=?',[$meeting]);
        if(!$identity) throw new InvalidArgumentException('Pertemuan tidak ditemukan.');
        kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$identity[0]['kelas_id']]);
        $pt=kbm_rows($db,'SELECT * FROM kbm_pertemuan WHERE id=? FOR UPDATE',[$meeting]);
        if(!$pt) throw new InvalidArgumentException('Pertemuan tidak ditemukan.');
        foreach([$in,$out] as $time) if($time!==null) {
            $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$time,new DateTimeZone('Asia/Jakarta'));
            if(!$d || $d->format('Y-m-d H:i:s')!==$time) throw new InvalidArgumentException('Format waktu koreksi tidak valid.');
            $serverNow=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
            if($time>$serverNow) throw new InvalidArgumentException('Waktu koreksi tidak boleh berada di masa depan.');
        }
        if($in!==null && substr($in,0,10)!==$pt[0]['tanggal']) throw new InvalidArgumentException('Check-in koreksi harus pada tanggal pertemuan.');
        if($out!==null && ($in===null || $out<$in)) throw new InvalidArgumentException('Check-out harus setelah check-in.');
        if($in!==null && $out===null) foreach(kbm_open_meetings($db,(int)$pt[0]['kelas_id']) as $active) {
            if((int)$active['pertemuan_id']!==$meeting) throw new InvalidArgumentException('Kelas masih mempunyai pertemuan aktif lain.');
        }
        $old=kbm_rows($db,'SELECT checkin_baru,checkout_baru FROM kbm_koreksi_admin WHERE pertemuan_id=? ORDER BY id DESC LIMIT 1',[$meeting]);
        if($old) { $beforeIn=$old[0]['checkin_baru']; $beforeOut=$old[0]['checkout_baru']; }
        else { $raw=kbm_rows($db,'SELECT checkin_at,checkout_at FROM kbm_laporan WHERE pertemuan_id=?',[$meeting]); $beforeIn=$raw[0]['checkin_at']??null; $beforeOut=$raw[0]['checkout_at']??null; }
        $db->prepare('INSERT INTO kbm_koreksi_admin(pertemuan_id,admin_id,alasan,checkin_lama,checkout_lama,checkin_baru,checkout_baru) VALUES(?,?,?,?,?,?,?)')->execute([$meeting,$admin,$reason,$beforeIn,$beforeOut,$in,$out]);
        return (int)$db->lastInsertId();
}
