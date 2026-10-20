<?php
require_once __DIR__.'/monitoring_kbm_siswa.php';
// Izin ketua, laporan, koreksi dan status tetap memakai fasilitas KBM yang sama.
function kbm_pilihan_ready(PDO $db): bool {
    $r=kbm_rows($db,"SELECT table_name,column_name,is_nullable FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='kbm_jadwal' AND column_name IN ('tanggal_pilihan','dipilih_siswa_id','pengajaran_id','mapel_id')) OR (table_name='kbm_pertemuan' AND column_name='mapel_id'))");
    return count($r)===5 && count(array_filter($r,fn($c)=>in_array($c['column_name'],['pengajaran_id','mapel_id'],true)&&$c['is_nullable']==='YES'))===3;
}
function kbm_pilihan_teachers(PDO $db): array {
    return kbm_rows($db,'SELECT g.id,g.nama_lengkap FROM guru g JOIN users u ON u.id=g.user_id WHERE u.role_id=2 AND u.is_active=1 ORDER BY g.nama_lengkap,g.id');
}
function kbm_pilihan_tidak_masuk(PDO $db,int $user,array $input): string {
    $task = ($input['action'] ?? '') === 'guru_hanya_tugas';
    $reportKey = $task ? 'guru_hanya_tugas' : 'guru_tidak_masuk';
    if(array_diff(array_keys($input),['action','id','guru_id','jp_selesai','csrf_token']))throw new InvalidArgumentException('Data laporan tidak valid.');
    $id=kbm_int($input,'id');$teacher=kbm_int($input,'guru_id');$end=kbm_int($input,'jp_selesai');
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$db->beginTransaction();
    try{
        $identity=kbm_rows($db,'SELECT s.kelas_id FROM siswa s JOIN users u ON u.id=s.user_id WHERE u.id=? AND u.role_id=3 AND u.is_active=1',[$user]);
        if(!$identity)throw new KbmStudentForbidden('Akun siswa aktif diperlukan.');$class=(int)$identity[0]['kelas_id'];
        kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]);$assignments=kbm_student_assignments($db,$user,true);
        if(count($assignments)!==1)throw new KbmStudentForbidden('Penugasan ketua kelas aktif diperlukan.');
        $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();$today=substr($now,0,10);
        $choices=kbm_rows($db,'SELECT j.* FROM kbm_jadwal j JOIN kbm_kelas_sesi a ON a.id=j.kelas_sesi_id WHERE j.id=? AND a.kelas_id=? AND j.berlaku_mulai=? AND j.berlaku_sampai=? FOR UPDATE',[$id,$class,$today,$today]);
        $choice=$choices[0]??null;$snap=$choice?json_decode($choice['pengajaran_snapshot'],true):[];
        if(!$choice||($snap['sumber']??'')!=='ketua_kelas'||!empty($snap['dibatalkan'])||($choice['nonaktif_mulai']!==null&&$choice['nonaktif_mulai']<=$today))throw new KbmStudentForbidden('Hanya pilihan guru kelas Anda untuk hari ini yang dapat dilaporkan.');
        if((int)$choice['guru_id']!==$teacher||(int)$choice['jp_selesai']!==$end)throw new InvalidArgumentException('Pilihan guru atau JP berubah. Muat ulang halaman.');
        if(!empty($snap[$reportKey])){$db->commit();return 'Laporan sudah tercatat.';}
        if(!empty($snap['guru_tidak_masuk']) || !empty($snap['guru_hanya_tugas']))throw new InvalidArgumentException('JP sudah dilaporkan. Batalkan catatan jika keliru.');
        if(kbm_rows($db,'SELECT p.id FROM kbm_pertemuan p WHERE p.jadwal_id=? AND (EXISTS(SELECT 1 FROM kbm_laporan l WHERE l.pertemuan_id=p.id) OR EXISTS(SELECT 1 FROM kbm_koreksi_admin c WHERE c.pertemuan_id=p.id))',[$id]))throw new InvalidArgumentException('Catatan sudah mempunyai check-in atau koreksi. Gunakan pembatalan jika keliru.');
        if(kbm_open_meetings($db,$class))throw new InvalidArgumentException('Check-out catatan aktif terlebih dahulu.');
        $planned=null;foreach(kbm_expected($db,$today) as $r)if((int)$r['jadwal_id']===$id){$planned=$r;break;}
        if(!$planned)throw new InvalidArgumentException('Pilihan JP hari ini tidak tersedia.');
        kbm_store_meeting($db,$user,$planned);
        $snap[$reportKey]=['pada'=>$now,'user_id'=>$user,'siswa_id'=>(int)$assignments[0]['siswa_id'],'nama'=>$assignments[0]['nama_lengkap']];
        $db->prepare('UPDATE kbm_jadwal SET pengajaran_snapshot=? WHERE id=?')->execute([json_encode($snap,JSON_THROW_ON_ERROR),$id]);
        $db->commit();return ($task ? 'Guru hanya memberikan tugas' : 'Guru tidak masuk').' dicatat berdasarkan laporan ketua kelas. Silakan pilih JP berikutnya.';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function kbm_pilihan_hapus(PDO $db,int $user,array $input): string {
    if(!kbm_pilihan_ready($db))throw new RuntimeException('Pelaporan pilihan guru belum diaktifkan.');
    if(array_diff(array_keys($input),['action','id','alasan','csrf_token']))throw new InvalidArgumentException('Data pembatalan tidak valid.');
    $id=kbm_int($input,'id');$reason=kbm_input($input,'alasan');if(mb_strlen($reason)>500)throw new InvalidArgumentException('Alasan pembatalan maksimal 500 karakter.');
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$db->beginTransaction();
    try{
        $identity=kbm_rows($db,'SELECT s.kelas_id FROM siswa s JOIN users u ON u.id=s.user_id WHERE u.id=? AND u.role_id=3 AND u.is_active=1',[$user]);
        if(!$identity)throw new KbmStudentForbidden('Akun siswa aktif diperlukan.');$class=(int)$identity[0]['kelas_id'];
        kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]);$assignments=kbm_student_assignments($db,$user,true);
        if(count($assignments)!==1)throw new KbmStudentForbidden('Penugasan ketua kelas aktif diperlukan.');$today=(string)$db->query('SELECT CURRENT_DATE')->fetchColumn();
        $choices=kbm_rows($db,'SELECT j.*,a.periode_id FROM kbm_jadwal j JOIN kbm_kelas_sesi a ON a.id=j.kelas_sesi_id WHERE j.id=? AND a.kelas_id=? AND j.berlaku_mulai=? AND j.berlaku_sampai=? FOR UPDATE',[$id,$class,$today,$today]);
        $choice=$choices[0]??null;$snap=$choice?json_decode($choice['pengajaran_snapshot'],true):[];
        if(!$choice||(!kbm_simple_ready($db)&&(int)$choice['periode_id']!==(int)$assignments[0]['periode_id'])||($snap['sumber']??'')!=='ketua_kelas')throw new KbmStudentForbidden('Hanya catatan pilihan guru hari ini untuk kelas Anda yang dapat dibatalkan.');
        if(!empty($snap['dibatalkan'])){$db->commit();return 'Catatan sudah dibatalkan. Riwayat aslinya tetap tersimpan.';}
        if($choice['nonaktif_mulai']!==null&&$choice['nonaktif_mulai']<=$today)throw new InvalidArgumentException('Pilihan ini sudah tidak aktif.');
        $meeting=kbm_rows($db,'SELECT id FROM kbm_pertemuan WHERE jadwal_id=? AND tanggal=?',[$id,$today]);
        if(!$meeting){$planned=null;foreach(kbm_expected($db,$today) as $r)if((int)$r['jadwal_id']===$id){$planned=$r;break;}if(!$planned)throw new InvalidArgumentException('Catatan hari ini tidak tersedia.');kbm_store_meeting($db,$user,$planned);}
        $db->prepare("UPDATE kbm_jadwal SET nonaktif_mulai=?,tanggal_pilihan=NULL,pengajaran_snapshot=JSON_SET(pengajaran_snapshot,'$.dibatalkan',1,'$.pembatalan_oleh',?,'$.pembatalan_pada',CAST(CURRENT_TIMESTAMP AS CHAR),'$.pembatalan_alasan',?) WHERE id=?")->execute([$today,$user,$reason,$id]);
        $db->commit();return 'Catatan dibatalkan dari daftar hari ini. Waktu asli dan alasan pembatalan tetap tersimpan di riwayat.';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function kbm_pilihan_reset(PDO $db,int $user,array $input): string {
    if(!kbm_pilihan_ready($db))throw new RuntimeException('Pelaporan pilihan guru belum diaktifkan.');
    if(array_diff(array_keys($input),['action','id','csrf_token']))throw new InvalidArgumentException('Data reset tidak valid.');$id=kbm_int($input,'id');
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$db->beginTransaction();
    try{
        $identity=kbm_rows($db,'SELECT s.kelas_id FROM siswa s JOIN users u ON u.id=s.user_id WHERE u.id=? AND u.role_id=3 AND u.is_active=1',[$user]);
        if(!$identity)throw new KbmStudentForbidden('Akun siswa aktif diperlukan.');$class=(int)$identity[0]['kelas_id'];
        kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]);$assignments=kbm_student_assignments($db,$user,true);
        if(count($assignments)!==1)throw new KbmStudentForbidden('Penugasan ketua kelas aktif diperlukan.');
        $today=(string)$db->query('SELECT CURRENT_DATE')->fetchColumn();
        $choices=kbm_rows($db,'SELECT j.*,a.periode_id FROM kbm_jadwal j JOIN kbm_kelas_sesi a ON a.id=j.kelas_sesi_id WHERE j.id=? AND a.kelas_id=? AND j.berlaku_mulai=? AND j.berlaku_sampai=? FOR UPDATE',[$id,$class,$today,$today]);
        if(!$choices||(!kbm_simple_ready($db)&&(int)$choices[0]['periode_id']!==(int)$assignments[0]['periode_id'])||(json_decode($choices[0]['pengajaran_snapshot'],true)['sumber']??'')!=='ketua_kelas')throw new KbmStudentForbidden('Hanya pilihan guru hari ini untuk kelas Anda yang dapat direset.');
        $snapshot = json_decode($choices[0]['pengajaran_snapshot'], true);
        if(!empty($snapshot['guru_hanya_tugas']) || !empty($snapshot['guru_tidak_masuk']))throw new InvalidArgumentException('JP sudah dilaporkan. Gunakan pembatalan jika keliru.');
        if(kbm_rows($db,'SELECT p.id FROM kbm_pertemuan p WHERE p.jadwal_id=? AND (EXISTS(SELECT 1 FROM kbm_laporan l WHERE l.pertemuan_id=p.id) OR EXISTS(SELECT 1 FROM kbm_koreksi_admin c WHERE c.pertemuan_id=p.id))',[$id]))throw new InvalidArgumentException('Pilihan sudah mempunyai check-in atau koreksi dan tidak dapat direset. Gunakan check-out atau hubungi admin.');
        if($choices[0]['nonaktif_mulai']!==null&&$choices[0]['nonaktif_mulai']<=$today){$db->commit();return 'Pilihan sudah direset. Silakan pilih JP dan guru kembali.';}
        $db->prepare("UPDATE kbm_jadwal SET nonaktif_mulai=?,tanggal_pilihan=NULL,pengajaran_snapshot=JSON_SET(pengajaran_snapshot,'$.reset_oleh',?,'$.reset_pada',CAST(CURRENT_TIMESTAMP AS CHAR)) WHERE id=?")->execute([$today,$user,$id]);
        $db->commit();return 'Pilihan JP dan guru berhasil direset. Silakan pilih kembali; belum ada check-in yang dihapus.';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function kbm_pilihan_slots(PDO $db,array $assignment,string $today): array {
    if(kbm_simple_ready($db)){
        if(!kbm_pilihan_tanpa_jam_ready($db))throw new RuntimeException('Migrasi tanpa jam JP perlu diterapkan oleh pengelola sistem.');
        $sessions=kbm_simple_sessions($db,(int)$assignment['kelas_id']);
        if(!$sessions)throw new InvalidArgumentException('Admin perlu memilih sesi pagi atau siang untuk kelas Anda.');
        return ['session'=>$sessions[0],'pattern'=>['id'=>null],'slots'=>kbm_numbered_slots()];
    }
    $sessions=kbm_rows($db,'SELECT a.*,s.nama nama_sesi FROM kbm_kelas_sesi a JOIN kbm_sesi_sekolah s ON s.kode=a.sesi_kode WHERE a.periode_id=? AND a.kelas_id=? AND ? BETWEEN a.berlaku_mulai AND a.berlaku_sampai',[$assignment['periode_id'],$assignment['kelas_id'],$today]);
    if(count($sessions)!==1)throw new InvalidArgumentException('Admin perlu melengkapi satu sesi pagi/siang untuk kelas Anda.');
    $session=$sessions[0];
    if(kbm_rows($db,'SELECT id FROM kbm_hari_tanpa_kbm WHERE periode_id=? AND tanggal=? AND (kelas_id IS NULL OR kelas_id=?) AND (sesi_kode IS NULL OR sesi_kode=?)',[$assignment['periode_id'],$today,$assignment['kelas_id'],$session['sesi_kode']]))throw new InvalidArgumentException('Hari ini ditetapkan sebagai hari tanpa KBM.');
    if(kbm_pilihan_tanpa_jam_ready($db))return ['session'=>$session,'pattern'=>['id'=>null],'slots'=>kbm_numbered_slots()];
    $patterns=kbm_rows($db,'SELECT * FROM kbm_pola_waktu WHERE periode_id=? AND sesi_kode=? AND hari=? AND ? BETWEEN berlaku_mulai AND berlaku_sampai',[$assignment['periode_id'],$session['sesi_kode'],(int)kbm_date($today)->format('N'),$today]);
    if(count($patterns)!==1)throw new InvalidArgumentException('Pengelola sistem perlu menerapkan migrasi 2026_10_17_monitoring_kbm_tanpa_jam.sql satu kali agar JP dapat dipilih tanpa pengaturan jam.');
    $pattern=$patterns[0];$slots=kbm_rows($db,'SELECT * FROM kbm_slot_waktu WHERE pola_id=? ORDER BY urutan',[$pattern['id']]);kbm_validate_slots($slots);
    return ['session'=>$session,'pattern'=>$pattern,'slots'=>$slots];
}
function kbm_pilihan_record(PDO $db,int $user,array $input): string {
    if(!kbm_pilihan_ready($db))throw new RuntimeException('Pembaruan pelaporan JP belum diterapkan oleh pengelola sistem.');
    if(array_diff(array_keys($input),['action','jp','jp_selesai','guru_id','csrf_token']))throw new InvalidArgumentException('Data pilihan tidak valid. Waktu dan kelas ditentukan server.');
    $jp=kbm_int($input,'jp');$end=isset($input['jp_selesai'])?kbm_int($input,'jp_selesai'):$jp;$teacher=kbm_int($input,'guru_id');if($jp>6||$end>6||$end<$jp)throw new InvalidArgumentException('Pilih rentang JP 1 sampai 6 dengan JP selesai tidak sebelum JP mulai.');
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$db->beginTransaction();
    try {
        $identity=kbm_rows($db,'SELECT s.kelas_id FROM siswa s JOIN users u ON u.id=s.user_id WHERE u.id=? AND u.role_id=3 AND u.is_active=1',[$user]);
        if(!$identity)throw new KbmStudentForbidden('Akun siswa aktif diperlukan.');$class=(int)$identity[0]['kelas_id'];
        kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]);$assignments=kbm_student_assignments($db,$user,true);
        if(count($assignments)!==1)throw new KbmStudentForbidden('Penugasan ketua kelas aktif harus tersedia dan tidak bertumpang tindih.');$a=$assignments[0];
        $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();$today=substr($now,0,10);$plan=kbm_pilihan_slots($db,$a,$today);
        $block=kbm_pilihan_block($plan['slots'],$jp,$end);
        $g=kbm_rows($db,'SELECT g.id,g.nama_lengkap FROM guru g JOIN users u ON u.id=g.user_id WHERE g.id=? AND u.role_id=2 AND u.is_active=1',[$teacher]);
        if(!$g)throw new InvalidArgumentException('Guru tidak ditemukan atau akunnya tidak aktif.');
        $existing=kbm_rows($db,'SELECT * FROM kbm_jadwal WHERE kelas_sesi_id=? AND tanggal_pilihan=? AND jp_mulai=? FOR UPDATE',[$plan['session']['id'],$today,$jp]);
        if($existing){
            $oldSnap=json_decode($existing[0]['pengajaran_snapshot'],true);
            if(!empty($oldSnap['guru_hanya_tugas'])||!empty($oldSnap['guru_tidak_masuk'])||kbm_rows($db,'SELECT p.id FROM kbm_pertemuan p LEFT JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.jadwal_id=? AND (l.checkout_at IS NOT NULL OR EXISTS(SELECT 1 FROM kbm_koreksi_admin c WHERE c.pertemuan_id=p.id))',[$existing[0]['id']]))throw new InvalidArgumentException('JP ini sudah selesai dilaporkan dan terkunci. Pilih JP lain atau batalkan catatan jika keliru.');
            if((int)$existing[0]['guru_id']===$teacher && (int)$existing[0]['jp_selesai']===$end){$db->commit();return 'Pilihan guru sudah tersimpan. Silakan check-in ketika guru masuk.';}
            if(kbm_rows($db,'SELECT id FROM kbm_pertemuan WHERE jadwal_id=?',[$existing[0]['id']]))throw new InvalidArgumentException('JP ini sudah tercatat. Guru tidak dapat diganti; hubungi admin jika ada kekeliruan.');
        }
        if(kbm_open_meetings($db,$class))throw new InvalidArgumentException('Check-out pertemuan sebelumnya terlebih dahulu sebelum memilih guru berikutnya.');
        if(kbm_rows($db,'SELECT j.id FROM kbm_jadwal j JOIN kbm_kelas_sesi ks ON ks.id=j.kelas_sesi_id WHERE ks.kelas_id=? AND j.tanggal_pilihan=? AND j.jp_mulai<=? AND j.jp_selesai>=? AND j.id<>?',[$class,$today,$end,$jp,$existing[0]['id']??0]))throw new InvalidArgumentException('Rentang JP bertumpang tindih dengan pilihan guru yang sudah tersimpan.');
        if(kbm_rows($db,"SELECT p.id FROM kbm_pertemuan p JOIN kbm_jadwal j ON j.id=p.jadwal_id WHERE p.kelas_id=? AND p.tanggal=? AND p.jp_mulai<=? AND p.jp_selesai>=? AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.dibatalkan')),'0')<>'1' AND (EXISTS(SELECT 1 FROM kbm_laporan l WHERE l.pertemuan_id=p.id) OR EXISTS(SELECT 1 FROM kbm_koreksi_admin c WHERE c.pertemuan_id=p.id))",[$class,$today,$end,$jp]))throw new InvalidArgumentException('JP ini sudah mempunyai laporan atau koreksi. Catatan yang sudah ada tetap dipertahankan.');
        // Mata pelajaran hanya diketahui bila pengajaran kelas/periode guru tersebut tepat satu.
        $teaching=kbm_rows($db,'SELECT p.id,p.mapel_id,m.nama_mapel FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id WHERE p.guru_id=? AND p.kelas_id=? AND p.tahun_ajaran=? AND p.semester=?',[$teacher,$class,$a['tahun_ajaran'],$a['semester']]);
        $t=count($teaching)===1?$teaching[0]:null;
        $snapshot=['kelas_id'=>$class,'guru_id'=>$teacher,'mapel_id'=>$t['mapel_id']??null,'nama_kelas'=>$a['nama_kelas'],'nama_guru'=>$g[0]['nama_lengkap'],'nama_mapel'=>$t['nama_mapel']??'Guru pilihan ketua kelas (mapel tidak ditentukan)','tahun_ajaran'=>$a['tahun_ajaran'],'semester'=>$a['semester'],'sumber'=>'ketua_kelas'];
        if($existing)$db->prepare('UPDATE kbm_jadwal SET pengajaran_id=?,guru_id=?,mapel_id=?,pengajaran_snapshot=?,dipilih_siswa_id=?,jp_selesai=? WHERE id=?')->execute([$t['id']??null,$teacher,$t['mapel_id']??null,json_encode($snapshot,JSON_THROW_ON_ERROR),$a['siswa_id'],$end,$existing[0]['id']]);
        else $db->prepare('INSERT INTO kbm_jadwal(kelas_sesi_id,pola_id,pengajaran_id,guru_id,mapel_id,pengajaran_snapshot,jp_mulai,jp_selesai,berlaku_mulai,berlaku_sampai,dibuat_oleh,tanggal_pilihan,dipilih_siswa_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$plan['session']['id'],$plan['pattern']['id'],$t['id']??null,$teacher,$t['mapel_id']??null,json_encode($snapshot,JSON_THROW_ON_ERROR),$jp,$end,$today,$today,$user,$today,$a['siswa_id']]);
        $db->commit();return 'Guru dipilih untuk JP '.$jp.($end!==$jp?'–'.$end:'').'. Silakan Check-in Guru ketika guru masuk.';
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
