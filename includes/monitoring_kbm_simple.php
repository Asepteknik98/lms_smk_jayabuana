<?php
// Helper internal; dimuat oleh monitoring_kbm_data.php.

function kbm_simple_ready(PDO $db): bool {
    return (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name='periode_id' AND is_nullable='YES' AND table_name IN ('kbm_kelas_sesi','kbm_ketua_kelas')")->fetchColumn()===2;
}
/** Metadata laporan baru, bukan syarat izin/masa berlaku monitoring. */
function kbm_simple_school_year(string $date): array {
    $year=(int)substr($date,0,4);$month=(int)substr($date,5,2);$start=$month>=7?$year:$year-1;
    return ['tahun_ajaran'=>$start.'/'.($start+1),'semester'=>$month>=7?'Ganjil':'Genap'];
}
function kbm_simple_sessions(PDO $db,?int $class=null): array {
    return kbm_rows($db,'SELECT a.*,s.nama nama_sesi FROM kbm_kelas_sesi a JOIN kbm_sesi_sekolah s ON s.kode=a.sesi_kode WHERE a.id=(SELECT MAX(b.id) FROM kbm_kelas_sesi b WHERE b.kelas_id=a.kelas_id)'.($class!==null?' AND a.kelas_id=?':''),$class!==null?[$class]:[]);
}
function kbm_simple_chairs(PDO $db,?int $user=null,bool $lock=false): array {
    return kbm_rows($db,'SELECT a.*,s.user_id,s.nama_lengkap,k.nama_kelas FROM kbm_ketua_kelas a JOIN siswa s ON s.id=a.siswa_id AND s.kelas_id=a.kelas_id JOIN kelas k ON k.id=a.kelas_id JOIN users u ON u.id=s.user_id WHERE u.role_id=3 AND u.is_active=1 AND a.dicabut_pada IS NULL AND a.id=(SELECT MAX(b.id) FROM kbm_ketua_kelas b WHERE b.kelas_id=a.kelas_id AND b.siswa_id=a.siswa_id AND b.dicabut_pada IS NULL) AND (SELECT COUNT(DISTINCT b.siswa_id) FROM kbm_ketua_kelas b WHERE b.kelas_id=a.kelas_id AND b.dicabut_pada IS NULL AND b.id>a.id AND b.siswa_id<>a.siswa_id)<4'.($user!==null?' AND u.id=?':'').' ORDER BY a.kelas_id,a.id'.($lock?' FOR UPDATE':''),$user!==null?[$user]:[]);
}
/** Slot izin tetap dicadangkan sampai dicabut, termasuk ketika akun nonaktif/pindah kelas. */
function kbm_simple_chair_slots(PDO $db,?int $class=null): array {
    return kbm_rows($db,'SELECT a.*,s.nama_lengkap,s.kelas_id kelas_sekarang,u.is_active,u.role_id FROM kbm_ketua_kelas a JOIN siswa s ON s.id=a.siswa_id JOIN users u ON u.id=s.user_id WHERE a.dicabut_pada IS NULL AND a.id=(SELECT MAX(b.id) FROM kbm_ketua_kelas b WHERE b.kelas_id=a.kelas_id AND b.siswa_id=a.siswa_id AND b.dicabut_pada IS NULL)'.($class!==null?' AND a.kelas_id=?':'').' ORDER BY a.kelas_id,a.id',$class!==null?[$class]:[]);
}
function kbm_simple_submit(PDO $db,int $admin,array $input): string {
    $action=kbm_input($input,'action');
    if(!in_array($action,['simple_sesi','simple_ketua','cabut_ketua'],true))throw new InvalidArgumentException('Pengaturan ini sudah tidak digunakan. Cukup atur sesi kelas dan ketua kelas.');
    return kbm_admin_write($db,$admin,function() use($db,$admin,$input,$action) {
        if($action==='cabut_ketua'){
            $id=kbm_int($input,'id');$rows=kbm_rows($db,'SELECT kelas_id,siswa_id FROM kbm_ketua_kelas WHERE id=? AND dicabut_pada IS NULL',[$id]);
            if(!$rows)throw new InvalidArgumentException('Penugasan tidak ditemukan atau sudah dicabut.');
            kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$rows[0]['kelas_id']]);
            $q=$db->prepare('UPDATE kbm_ketua_kelas SET dicabut_pada=CURRENT_TIMESTAMP,dicabut_oleh=? WHERE kelas_id=? AND siswa_id=? AND dicabut_pada IS NULL');$q->execute([$admin,$rows[0]['kelas_id'],$rows[0]['siswa_id']]);
            if(!$q->rowCount())throw new InvalidArgumentException('Penugasan tidak ditemukan atau sudah dicabut.');
            return 'Izin pengguna ini dicabut. Pengguna lain dan riwayat tetap tersimpan.';
        }
        $class=kbm_int($input,'kelas_id');
        if(!kbm_rows($db,'SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]))throw new InvalidArgumentException('Kelas tidak ditemukan.');
        if($action==='simple_sesi'){
            $session=kbm_input($input,'sesi_kode');if(!in_array($session,['pagi','siang'],true))throw new InvalidArgumentException('Pilih sesi pagi atau siang.');
            $current=kbm_simple_sessions($db,$class);
            if(!$current||$current[0]['sesi_kode']!==$session)$db->prepare("INSERT INTO kbm_kelas_sesi(periode_id,kelas_id,sesi_kode,berlaku_mulai,berlaku_sampai,dibuat_oleh) VALUES(NULL,?,?,CURRENT_DATE,'9999-12-31',?)")->execute([$class,$session,$admin]);
            return 'Sesi kelas disimpan, berlaku sampai diubah kembali.';
        }
        $student=kbm_int($input,'siswa_id');
        if(!kbm_rows($db,'SELECT s.id FROM siswa s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.kelas_id=? AND u.role_id=3 AND u.is_active=1',[$student,$class]))throw new InvalidArgumentException('Pilih siswa aktif dari kelas tersebut.');
        $current=kbm_simple_chair_slots($db,$class);
        foreach($current as $chair)if((int)$chair['siswa_id']===$student)return 'Siswa ini sudah ditunjuk sebagai pengguna.';
        if(count($current)>=4)throw new InvalidArgumentException('Maksimal 4 pengguna per kelas. Cabut salah satu penugasan sebelum menambahkan pengguna baru.');
        $db->prepare("INSERT INTO kbm_ketua_kelas(periode_id,kelas_id,siswa_id,berlaku_mulai,berlaku_sampai,ditunjuk_oleh) VALUES(NULL,?,?,CURRENT_DATE,'9999-12-31',?)")->execute([$class,$student,$admin]);
        return 'Pengguna ditambahkan. Pengguna sebelumnya tetap memiliki izin; berlaku sampai dicabut.';
    });
}
