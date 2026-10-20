<?php
// Pakai kesiapan skema dan helper yang sama; tidak membuka fasilitas admin ke siswa.
require_once __DIR__.'/monitoring_kbm_admin.php';
require_once __DIR__.'/monitoring_kbm_status.php';

class KbmStudentForbidden extends RuntimeException {}

function kbm_student_assignments(PDO $db,int $user,bool $lock=false): array {
    if(kbm_simple_ready($db)){
        $meta=kbm_simple_school_year((string)$db->query('SELECT CURRENT_DATE')->fetchColumn());
        return array_map(fn($a)=>array_merge($a,$meta),kbm_simple_chairs($db,$user,$lock));
    }
    return kbm_rows($db,'SELECT a.*,s.user_id,s.nama_lengkap,k.nama_kelas,r.tahun_ajaran,r.semester
        FROM kbm_ketua_kelas a JOIN siswa s ON s.id=a.siswa_id JOIN users u ON u.id=s.user_id
        JOIN kelas k ON k.id=a.kelas_id JOIN kbm_periode r ON r.id=a.periode_id
        WHERE u.id=? AND u.role_id=3 AND u.is_active=1 AND s.kelas_id=a.kelas_id AND a.dicabut_pada IS NULL
        AND CURRENT_DATE BETWEEN a.berlaku_mulai AND a.berlaku_sampai AND CURRENT_DATE BETWEEN r.mulai AND r.selesai
        ORDER BY r.mulai DESC,a.id DESC'.($lock?' FOR UPDATE':''),[$user]);
}

function kbm_student_duration(?string $in,?string $out): string {
    if($in===null || $out===null) return 'Belum check-out';
    $zone=new DateTimeZone('Asia/Jakarta');
    $seconds=max(0,(new DateTimeImmutable($out,$zone))->getTimestamp()-(new DateTimeImmutable($in,$zone))->getTimestamp());
    return intdiv($seconds,3600).' jam '.intdiv($seconds%3600,60).' menit '.($seconds%60).' detik';
}

/** Waktu, kelas, guru, mapel, dan pelapor tidak pernah diambil dari input siswa. */
function kbm_student_record(PDO $db,int $user,string $action,int $id,?int $expectedTeacher=null,?int $expectedEnd=null): string {
    if(!in_array($action,['checkin','checkout'],true) || $id<1) throw new InvalidArgumentException('Tindakan atau pertemuan tidak valid.');
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $db->beginTransaction();
    try {
        $db->exec("SET time_zone='+07:00'");
        $identity=kbm_rows($db,'SELECT s.kelas_id FROM siswa s JOIN users u ON u.id=s.user_id WHERE u.id=? AND u.role_id=3 AND u.is_active=1',[$user]);
        if(!$identity || !$identity[0]['kelas_id']) throw new KbmStudentForbidden('Akun siswa aktif dan kelas diperlukan.');
        $class=(int)$identity[0]['kelas_id'];
        // Mutex InnoDB per kelas, termasuk request dari ketua pengganti/session lain.
        kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]);
        $assignments=kbm_student_assignments($db,$user,true);
        if(!$assignments || (int)$assignments[0]['kelas_id']!==$class) throw new KbmStudentForbidden('Penugasan ketua kelas sudah berakhir, dicabut, atau tidak sesuai kelas Anda.');
        $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn(); $today=substr($now,0,10);
        $simple=kbm_simple_ready($db);
        if($action==='checkin') {
            $selected=null;
            foreach(kbm_expected($db,$today) as $r) if((int)$r['jadwal_id']===$id && (int)$r['kelas_id']===$class && ($simple||in_array((int)$r['periode_id'],array_map('intval',array_column($assignments,'periode_id')),true))) $selected=$r;
            if(!$selected) throw new InvalidArgumentException('Jadwal hari ini tidak tersedia untuk kelas Anda.');
            if($expectedTeacher!==null && (int)$selected['guru_id']!==$expectedTeacher)throw new InvalidArgumentException('Pilihan guru sudah berubah. Muat ulang dan periksa guru sebelum check-in.');
            if($expectedEnd!==null && (int)$selected['jp_selesai']!==$expectedEnd)throw new InvalidArgumentException('Rentang JP sudah berubah. Muat ulang sebelum check-in.');
            if(kbm_meeting_status($selected,$now)['code']!=='belum_checkin') throw new InvalidArgumentException('Check-in hanya dapat dilakukan selama jadwal KBM berlangsung, bukan saat istirahat.');
            $meeting=kbm_store_meeting($db,$user,$selected);
            $saved=kbm_rows($db,'SELECT * FROM kbm_laporan WHERE pertemuan_id=? FOR UPDATE',[$meeting]);
            if($saved) { $db->commit(); return $saved[0]['checkout_at']===null?'Check-in sudah tercatat. Waktu masuk tetap dipertahankan.':'Pertemuan sudah selesai dan tidak dapat dibuka ulang.'; }
            if(kbm_rows($db,'SELECT id FROM kbm_koreksi_admin WHERE pertemuan_id=?',[$meeting])) throw new InvalidArgumentException('Pertemuan ini telah ditangani admin dan tidak dapat dibuka ulang.');
            if(kbm_open_meetings($db,$class)) throw new InvalidArgumentException('Masih ada pertemuan Belum check-out. Tutup pertemuan sebelumnya terlebih dahulu.');
            $chair=$simple?$assignments[0]:null; foreach($assignments as $a) if(!$simple&&(int)$a['periode_id']===(int)$selected['periode_id']) { $chair=$a;break; }
            $db->prepare('INSERT INTO kbm_laporan(pertemuan_id,checkin_at,checkin_siswa_id,checkin_ketua_id,nama_pelapor_masuk) VALUES(?,?,?,?,?)')->execute([$meeting,$now,$chair['siswa_id'],$chair['id'],$chair['nama_lengkap']]);
            $message='Check-in Guru berhasil dicatat pada '.substr($now,11).' WIB.';
        } else {
            $rows=kbm_rows($db,"SELECT p.*,l.checkin_at,l.checkout_at FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id JOIN kbm_jadwal j ON j.id=p.jadwal_id WHERE p.id=? AND p.kelas_id=? AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(j.pengajaran_snapshot,'$.dibatalkan')),'0')<>'1' FOR UPDATE",[$id,$class]);
            if(!$rows) throw new InvalidArgumentException('Pertemuan kelas Anda belum mempunyai check-in yang sah.');
            $r=$rows[0];
            if($r['checkout_at']!==null) { $db->commit();return 'Check-out sudah tercatat. Waktu selesai tetap dipertahankan.'; }
            if(kbm_rows($db,'SELECT id FROM kbm_koreksi_admin WHERE pertemuan_id=?',[$id])) throw new InvalidArgumentException('Catatan ini telah dikoreksi admin. Hubungi admin untuk menutup atau menindaklanjutinya.');
            if($r['checkin_at']>$now) throw new InvalidArgumentException('Waktu check-in tidak valid. Hubungi admin.');
            // Penutupan terlambat lintas tanggal/periode memakai izin ketua saat ini pada kelas yang sama.
            $chair=$assignments[0];
            $db->prepare('UPDATE kbm_laporan SET checkout_at=?,checkout_siswa_id=?,checkout_ketua_id=?,nama_pelapor_keluar=? WHERE pertemuan_id=? AND checkout_at IS NULL')->execute([$now,$chair['siswa_id'],$chair['id'],$chair['nama_lengkap'],$id]);
            $message='Check-out Guru berhasil dicatat pada '.$now.' WIB. Durasi tercatat: '.kbm_student_duration($r['checkin_at'],$now).'.';
        }
        $db->commit();return $message;
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
}

function kbm_student_post(PDO $db,int $user,array $input): string {
    if(in_array($input['action']??'', ['guru_tidak_masuk','guru_hanya_tugas'], true)){
        require_once __DIR__.'/monitoring_kbm_pilihan.php';return kbm_pilihan_tidak_masuk($db,$user,$input);
    }
    if(($input['action']??'')==='hapus_pilihan'){
        require_once __DIR__.'/monitoring_kbm_pilihan.php';return kbm_pilihan_hapus($db,$user,$input);
    }
    if(($input['action']??'')==='reset_pilihan'){
        require_once __DIR__.'/monitoring_kbm_pilihan.php';return kbm_pilihan_reset($db,$user,$input);
    }
    if(($input['action']??'')==='pilih_guru') {
        require_once __DIR__.'/monitoring_kbm_pilihan.php';return kbm_pilihan_record($db,$user,$input);
    }
    // Form hanya menerima aksi/ID dan CSRF yang diperiksa controller.
    $allowed=['action','id','csrf_token'];if(($input['action']??'')==='checkin'){$allowed[]='guru_id';$allowed[]='jp_selesai';}
    if(array_diff(array_keys($input),$allowed)) throw new InvalidArgumentException('Tanggal, waktu, dan data jadwal tidak dapat diubah oleh siswa.');
    return kbm_student_record($db,$user,kbm_input($input,'action'),kbm_int($input,'id'),isset($input['guru_id'])?kbm_int($input,'guru_id'):null,isset($input['jp_selesai'])?kbm_int($input,'jp_selesai'):null);
}
