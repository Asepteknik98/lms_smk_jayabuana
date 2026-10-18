<?php
require_once __DIR__.'/monitoring_kbm_data.php';

function kbm_admin_ready(PDO $db): bool {
    $tables=kbm_rows($db,"SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('kbm_periode','kbm_sesi_sekolah','kbm_pola_waktu','kbm_slot_waktu','kbm_kelas_sesi','kbm_ketua_kelas','kbm_jadwal','kbm_hari_tanpa_kbm','kbm_pertemuan','kbm_laporan','kbm_koreksi_admin','kbm_perubahan_jadwal')");
    $columns=kbm_rows($db,"SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kbm_jadwal' AND column_name IN ('nonaktif_mulai','diganti_dari')");
    return count($tables)===12 && count($columns)===2;
}

function kbm_input(array $input,string $key,bool $required=true): string {
    $value=$input[$key]??'';
    if(!is_string($value) || ($required && trim($value)==='')) throw new InvalidArgumentException('Isian '.str_replace('_',' ',$key).' wajib dan harus valid.');
    return trim($value);
}
function kbm_int(array $input,string $key): int {
    $value=kbm_input($input,$key);
    if(!preg_match('/^[1-9][0-9]{0,9}$/D',$value)) throw new InvalidArgumentException('Pilihan '.str_replace('_',' ',$key).' tidak valid.');
    return (int)$value;
}

/** Perubahan efektif besok atau sesudahnya; hari berjalan dipertahankan seluruhnya. */
function kbm_change_schedule(PDO $db,int $admin,int $id,string $effective,string $reason,?array $replacement): ?int {
    kbm_date($effective); $reason=trim($reason);
    if($effective <= (new DateTimeImmutable('today',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d')) throw new InvalidArgumentException('Perubahan jadwal berlaku paling cepat besok agar KBM hari ini dan riwayat tetap utuh.');
    if($reason==='' || mb_strlen($reason)>1000) throw new InvalidArgumentException('Alasan perubahan wajib, maksimal 1000 karakter.');
    return kbm_admin_write($db,$admin,function () use($db,$admin,$id,$effective,$reason,$replacement) {
        $rows=kbm_rows($db,'SELECT j.*,ks.periode_id,ks.kelas_id FROM kbm_jadwal j JOIN kbm_kelas_sesi ks ON ks.id=j.kelas_sesi_id WHERE j.id=? FOR UPDATE',[$id]);
        if(!$rows || $rows[0]['nonaktif_mulai']!==null) throw new InvalidArgumentException('Jadwal tidak ditemukan atau sudah dijadwalkan nonaktif.');
        $old=$rows[0];
        if($effective>$old['berlaku_sampai']) throw new InvalidArgumentException('Tanggal perubahan melewati masa berlaku jadwal.');
        if(kbm_rows($db,'SELECT p.id FROM kbm_pertemuan p WHERE p.jadwal_id=? AND p.tanggal>=? AND (p.rencana_mulai<=CURRENT_TIMESTAMP OR EXISTS(SELECT 1 FROM kbm_laporan l WHERE l.pertemuan_id=p.id) OR EXISTS(SELECT 1 FROM kbm_koreksi_admin c WHERE c.pertemuan_id=p.id))',[$id,$effective])) throw new InvalidArgumentException('Rentang perubahan memiliki pertemuan yang sudah berlangsung atau dilaporkan.');
        $db->prepare('UPDATE kbm_jadwal SET nonaktif_mulai=? WHERE id=?')->execute([$effective,$id]);
        $new=null;
        if($replacement!==null) {
            $ks=kbm_rows($db,'SELECT * FROM kbm_kelas_sesi WHERE id=? AND periode_id=? AND kelas_id=?',[$replacement['kelas_sesi_id'],$old['periode_id'],$old['kelas_id']]);
            if(!$ks) throw new InvalidArgumentException('Versi edit harus berada pada kelas dan periode yang sama. Untuk kelas lain, nonaktifkan lalu tambah jadwal.');
            $replacement['berlaku_mulai']=max($effective,$old['berlaku_mulai']);
            $new=kbm_insert_schedule($db,$admin,$replacement);
            $db->prepare('UPDATE kbm_jadwal SET diganti_dari=? WHERE id=?')->execute([$id,$new]);
        }
        $db->prepare('INSERT INTO kbm_perubahan_jadwal(jadwal_id,jadwal_baru_id,berlaku_mulai,admin_id,alasan) VALUES(?,?,?,?,?)')->execute([$id,$new,$effective,$admin,$reason]);
        return $new;
    });
}

/** Hari berjalan/lampau dikunci agar laporan dan jadwal historis tidak berubah. */
function kbm_change_holiday(PDO $db,int $admin,array $input,bool $delete): void {
    $id=kbm_int($input,'id');$periodId=kbm_int($input,'periode_id');
    kbm_admin_write($db,$admin,function() use($db,$input,$id,$periodId,$delete) {
        $rows=kbm_rows($db,'SELECT * FROM kbm_hari_tanpa_kbm WHERE id=? AND periode_id=? FOR UPDATE',[$id,$periodId]);
        if(!$rows)throw new InvalidArgumentException('Hari tanpa KBM pada semester ini tidak ditemukan.');
        $today=(string)$db->query('SELECT CURRENT_DATE')->fetchColumn();
        if($rows[0]['tanggal']<=$today)throw new InvalidArgumentException('Hari tanpa KBM hari ini atau yang sudah berlalu tidak dapat diubah atau dihapus agar riwayat tetap utuh.');
        if($delete){$db->prepare('DELETE FROM kbm_hari_tanpa_kbm WHERE id=? AND periode_id=?')->execute([$id,$periodId]);return;}
        $date=kbm_input($input,'tanggal');$reason=kbm_input($input,'alasan');
        $class=kbm_input($input,'kelas_id',false)===''?null:kbm_int($input,'kelas_id');
        $session=kbm_input($input,'sesi_kode',false)?:null;
        if($date<=$today)throw new InvalidArgumentException('Tanggal tanpa KBM harus mulai besok.');
        kbm_range($date,$date,kbm_period($db,$periodId));
        if(mb_strlen($reason)>255)throw new InvalidArgumentException('Alasan maksimal 255 karakter.');
        if($session!==null&&!in_array($session,['pagi','siang'],true))throw new InvalidArgumentException('Sesi sekolah tidak valid.');
        if($class!==null&&!kbm_rows($db,'SELECT id FROM kelas WHERE id=?',[$class]))throw new InvalidArgumentException('Kelas tidak ditemukan.');
        $db->prepare('UPDATE kbm_hari_tanpa_kbm SET tanggal=?,kelas_id=?,sesi_kode=?,alasan=? WHERE id=? AND periode_id=?')->execute([$date,$class,$session,$reason,$id,$periodId]);
    });
}
function kbm_admin_submit(PDO $db,int $admin,array $input): string {
    $action=kbm_input($input,'action');
    if(kbm_simple_ready($db))return kbm_simple_submit($db,$admin,$input);
    if(in_array($action,['edit_hari_tanpa_kbm','hapus_hari_tanpa_kbm'],true)){
        kbm_change_holiday($db,$admin,$input,$action==='hapus_hari_tanpa_kbm');
        return $action==='hapus_hari_tanpa_kbm'?'Hari tanpa KBM berhasil dihapus.':'Hari tanpa KBM berhasil diperbarui.';
    }
    // Form semester penuh: tanggal ditentukan server, bukan tanggal tersembunyi dari browser.
    if(($input['semester_penuh']??'')==='1' && in_array($action,['kelas_sesi','pola_waktu','ketua_kelas','jadwal','edit_jadwal'],true)) {
        $semester=kbm_period($db,kbm_int($input,'periode_id'));
        $input['berlaku_mulai']=$semester['mulai'];$input['berlaku_sampai']=$semester['selesai'];
        if($action==='ketua_kelas') $input['berlaku_mulai']=max($semester['mulai'],(string)$db->query('SELECT CURRENT_DATE')->fetchColumn());
        if($action==='jadwal') $input['berlaku_mulai']=max($semester['mulai'],(string)$db->query('SELECT CURRENT_DATE + INTERVAL 1 DAY')->fetchColumn());
        if($action==='edit_jadwal') $input['berlaku_mulai']=kbm_input($input,'efektif');
    }
    if($action==='cabut_ketua') { kbm_revoke_chair($db,$admin,kbm_int($input,'id')); return 'Penugasan ketua kelas dicabut. Riwayat pelapor tetap tersimpan.'; }
    if($action==='nonaktif_jadwal') {
        kbm_change_schedule($db,$admin,kbm_int($input,'id'),kbm_input($input,'efektif'),kbm_input($input,'alasan'),null);
        return 'Penonaktifan jadwal berhasil dijadwalkan. Riwayat lama tetap tersimpan.';
    }
    if($action==='periode') {
        kbm_configure($db,$admin,'periode',['tahun_ajaran'=>kbm_input($input,'tahun_ajaran'),'semester'=>kbm_input($input,'semester'),'mulai'=>kbm_input($input,'mulai'),'selesai'=>kbm_input($input,'selesai')]);
        return 'Periode KBM berhasil ditambahkan.';
    }
    if(in_array($action,['jadwal','edit_jadwal'],true)) {
        $data=[];
        foreach(['kelas_sesi_id','pola_id','pengajaran_id','jp_mulai','jp_selesai'] as $key) $data[$key]=kbm_int($input,$key);
        $data['berlaku_mulai']=kbm_input($input,'berlaku_mulai'); $data['berlaku_sampai']=kbm_input($input,'berlaku_sampai');
        if($action==='edit_jadwal') kbm_change_schedule($db,$admin,kbm_int($input,'id'),$data['berlaku_mulai'],kbm_input($input,'alasan'),$data);
        else {
            $today=(new DateTimeImmutable('today',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
            if($data['berlaku_mulai']<$today) throw new InvalidArgumentException('Jadwal baru tidak boleh dimulai pada tanggal yang sudah berlalu.');
            kbm_admin_write($db,$admin,function () use($db,$admin,$data,$today) {
                $id=kbm_insert_schedule($db,$admin,$data);
                // Jika memilih hari ini, hanya blok yang belum mulai yang dapat ditambahkan.
                foreach(kbm_expected($db,$today) as $r) if((int)$r['jadwal_id']===$id && $r['rencana_mulai']<=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s')) throw new InvalidArgumentException('Jam KBM hari ini sudah dimulai. Pilih berlaku mulai besok atau tanggal berikutnya.');
            });
        }
        return 'Jadwal KBM berhasil disimpan. Versi dan riwayat sebelumnya dipertahankan.';
    }
    $data=['periode_id'=>kbm_int($input,'periode_id')];
    if($action==='hari_tanpa_kbm') {
        $data+=['tanggal'=>kbm_input($input,'tanggal'),'alasan'=>kbm_input($input,'alasan'),'kelas_id'=>kbm_input($input,'kelas_id',false)===''?null:kbm_int($input,'kelas_id'),'sesi_kode'=>kbm_input($input,'sesi_kode',false)?:null];
        // Jangan menambah libur retrospektif yang akan menyembunyikan jadwal tanpa laporan.
        if($data['tanggal']<=(new DateTimeImmutable('today',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d')) throw new InvalidArgumentException('Hari tanpa KBM hanya dapat ditambahkan mulai besok.');
    } else {
        $data+=['berlaku_mulai'=>kbm_input($input,'berlaku_mulai'),'berlaku_sampai'=>kbm_input($input,'berlaku_sampai')];
        if($action==='kelas_sesi') $data+=['kelas_id'=>kbm_int($input,'kelas_id'),'sesi_kode'=>kbm_input($input,'sesi_kode')];
        elseif($action==='ketua_kelas') $data+=['kelas_id'=>kbm_int($input,'kelas_id'),'siswa_id'=>kbm_int($input,'siswa_id')];
        elseif($action==='pola_waktu') {
            $slots=[];
            for($jp=1;$jp<=6;$jp++) $slots[]=['jenis'=>'JP','jp'=>$jp,'mulai'=>kbm_admin_time(kbm_input($input,'jp_'.$jp.'_mulai')),'selesai'=>kbm_admin_time(kbm_input($input,'jp_'.$jp.'_selesai'))];
            for($i=1;$i<=3;$i++) {
                $start=kbm_input($input,'istirahat_'.$i.'_mulai',false); $end=kbm_input($input,'istirahat_'.$i.'_selesai',false);
                if($start!=='' || $end!=='') $slots[]=['jenis'=>'ISTIRAHAT','jp'=>null,'mulai'=>kbm_admin_time($start),'selesai'=>kbm_admin_time($end)];
            }
            usort($slots,fn($a,$b)=>strcmp($a['mulai'],$b['mulai']));
            $data+=['hari'=>kbm_int($input,'hari'),'sesi_kode'=>kbm_input($input,'sesi_kode'),'slots'=>$slots];
        } else throw new InvalidArgumentException('Aksi pengaturan tidak dikenali.');
    }
    kbm_configure($db,$admin,$action,$data);
    return 'Pengaturan berhasil disimpan.';
}

function kbm_admin_time(string $time): string {
    if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time)) throw new InvalidArgumentException('Jam mulai dan selesai harus diisi dengan benar.');
    return $time.':00';
}
