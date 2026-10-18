<?php
require_once __DIR__.'/monitoring_kbm_history.php';
require_once __DIR__.'/rekap_akademik.php'; // Writer SpreadsheetML yang sudah digunakan LMS.

const KBM_EXPORT_MAX_ROWS=50000;
const KBM_EXPORT_MAX_BYTES=104857600;
class KbmExportNotice extends RuntimeException {}

function kbm_export_filter_input(array $input): array {
    return array_intersect_key($input,array_flip(['tanggal_mulai','tanggal_selesai','sesi','kelas','guru','status']));
}

function kbm_export_columns(): array {
    return ['Nomor','Hari','Tanggal','Tahun ajaran','Semester','Sesi','Kelas','Guru terjadwal','Mata pelajaran','JP mulai','JP selesai','Jadwal mulai (WIB)','Jadwal selesai (WIB)','Check-in aktual (WIB)','Check-out aktual (WIB)','Durasi tercatat [jam]:menit:detik','Siswa pelapor asli check-in','Siswa pelapor asli check-out','Status','Catatan','Sumber / penanda koreksi admin','Admin koreksi','Waktu koreksi (WIB)','Alasan koreksi terakhir'];
}

function kbm_export_row(array $r,int $number,string $now): array {
    $days=[1=>'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
    $zone=new DateTimeZone('Asia/Jakarta');$date=kbm_date($r['tanggal']);$status=kbm_meeting_status($r,$now);
    $in=$r['checkin_efektif'];$out=$r['checkout_efektif'];$duration=null;$notes=[];
    if($r['rencana_mulai']===null)$notes[]='Pilihan JP tanpa jadwal jam; waktu aktual berasal dari laporan.';
    if($in!==null && $out!==null) $duration=((new DateTimeImmutable($out,$zone))->getTimestamp()-(new DateTimeImmutable($in,$zone))->getTimestamp())/86400;
    elseif($in===null) $notes[]='Check-in belum tercatat; durasi belum tersedia.';
    else $notes[]='Belum check-out; durasi final belum tersedia.';
    if($status['warning']!=='')$notes[]=$status['warning'];
    $absence=$r['guru_tidak_masuk']??null;if(is_string($absence))$absence=json_decode($absence,true);
    if(is_array($absence))$notes[]='Laporan asli guru tidak masuk oleh '.($absence['nama']??'').' pada '.($absence['pada']??'').' WIB.';
    if($r['koreksi_id']!==null)$notes[]='Data efektif hasil koreksi admin; nama pelapor adalah pelapor asli, bukan atribusi koreksi.';
    if(!empty($r['dibatalkan']))$notes[]='Dibatalkan oleh pengguna #'.(int)$r['pembatalan_oleh'].' pada '.$r['pembatalan_pada'].' WIB. Alasan: '.$r['pembatalan_alasan'];
    $stamp=static fn(?string $v)=>$v===null?null:str_replace(' ','T',$v);
    return [$number,$days[(int)$date->format('N')],$r['tanggal'].'T00:00:00',$r['tahun_ajaran'],$r['semester'],$r['nama_sesi'],$r['nama_kelas'],$r['nama_guru'],$r['nama_mapel'],(int)$r['jp_mulai'],(int)$r['jp_selesai'],$stamp($r['rencana_mulai']),$stamp($r['rencana_selesai']),$stamp($in),$stamp($out),$duration,$r['pelapor_masuk'],$r['pelapor_keluar'],$status['label'],implode(' ',$notes),$r['koreksi_id']!==null?'Hasil koreksi admin':($r['checkin_asli']!==null?'Laporan langsung siswa':(!empty($r['guru_tidak_masuk'])?'Laporan guru tidak masuk oleh ketua kelas':'Belum ada laporan siswa')),$r['admin_id']!==null?'Admin #'.(int)$r['admin_id']:null,$stamp($r['koreksi_pada']),$r['alasan_koreksi']];
}

/** File selesai dibangun sebelum header attachment; buffer aplikasi tidak menampung semua hasil. */
function kbm_history_export_file(PDO $db,array $input): array {
    $file=tmpfile();if($file===false)throw new RuntimeException('File sementara ekspor tidak dapat dibuat.');
    $stmt=null;$buffered=$db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    try {
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
        $now=(string)$db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        $f=kbm_history_filters(kbm_export_filter_input($input),substr($now,0,10));
        [$suffix,$params]=kbm_history_condition($f,$now);
        $count=(int)kbm_rows($db,kbm_history_sql(kbm_simple_ready($db)).'SELECT COUNT(*) total '.$suffix,$params)[0]['total'];
        if($count===0)throw new KbmExportNotice('Tidak ada hasil sesuai filter untuk diunduh. Ubah filter riwayat lalu coba lagi.');
        if($count>KBM_EXPORT_MAX_ROWS)throw new KbmExportNotice('Hasil melebihi 50.000 pertemuan. Persempit rentang tanggal, sesi, kelas, atau guru; tidak ada data yang dipotong.');
        $statusNames=[''=>'Semua','guru_tidak_masuk'=>'Guru tidak masuk (laporan ketua kelas)','belum_mulai'=>'Belum waktunya KBM','belum_checkin'=>'Guru belum check-in','checkin'=>'Sudah check-in / belum check-out','selesai'=>'KBM selesai','tanpa_checkin'=>'Tidak tercatat check-in','dikoreksi'=>'Hasil koreksi admin','dibatalkan'=>'Dibatalkan oleh ketua kelas'];
        $options=['sheet'=>'Riwayat KBM','freeze'=>true,
            'metadata'=>['Rentang: '.$f['tanggal_mulai'].' s.d. '.$f['tanggal_selesai'].' | Sesi: '.($f['sesi']?:'Semua').' | Kelas: '.($f['kelas']!==''?'ID '.$f['kelas']:'Semua').' | Guru: '.($f['guru']!==''?'ID '.$f['guru']:'Semua').' | Status: '.$statusNames[$f['status']],
                'Jumlah: '.$count.' pertemuan | Seluruh hasil filter, bukan halaman pagination | Dibuat: '.$now.' WIB',
                'Sel kosong berarti waktu/pelapor belum tercatat. Durasi hanya dihitung bila masuk dan keluar lengkap. Status laporan bukan deteksi kehadiran fisik guru.'],
            'widths'=>[45,75,90,95,75,65,110,165,190,55,55,145,145,145,145,140,160,160,170,300,165,95,145,300],
            'types'=>array_fill(0,24,'String'),'formats'=>[2=>'Date',11=>'DateTime',12=>'DateTime',13=>'DateTime',14=>'DateTime',15=>'Duration',22=>'DateTime']];
        foreach([0,9,10,15] as $col)$options['types'][$col]='Number';
        foreach([2,11,12,13,14,22] as $col)$options['types'][$col]='DateTime';
        $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
        $stmt=$db->prepare(kbm_history_sql(kbm_simple_ready($db)).'SELECT * '.$suffix.' ORDER BY tanggal DESC,rencana_mulai DESC,kelas_id,jadwal_id');$stmt->execute($params);
        $emitted=0;
        $rows=(function()use($stmt,$now,&$emitted){while($r=$stmt->fetch(PDO::FETCH_ASSOC)){if(++$emitted>KBM_EXPORT_MAX_ROWS)throw new RuntimeException('Batas baris ekspor terlampaui.');yield kbm_export_row($r,$emitted,$now);}})();
        foreach(ra_excel_parts('RIWAYAT MONITORING KBM - SMKS JAYA BUANA',kbm_export_columns(),$rows,$options) as $part) {
            $length=strlen($part);if(fwrite($file,$part)!==$length)throw new RuntimeException('File ekspor tidak dapat ditulis lengkap.');
            if(ftell($file)>KBM_EXPORT_MAX_BYTES)throw new KbmExportNotice('Ukuran hasil melebihi 100 MB. Persempit filter; tidak ada file parsial yang diunduh.');
        }
        $stmt->closeCursor();$stmt=null;$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);
        if($emitted!==$count)throw new RuntimeException('Jumlah baris ekspor tidak sesuai hasil filter.');
        $db->commit();rewind($file);
        return ['file'=>$file,'count'=>$count,'filters'=>$f,'now'=>$now,'filename'=>'Riwayat_KBM_'.$f['tanggal_mulai'].'_sd_'.$f['tanggal_selesai'].'.xls','size'=>fstat($file)['size']];
    }catch(Throwable $e){if($stmt)$stmt->closeCursor();$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);if($db->inTransaction())$db->rollBack();fclose($file);throw $e;}
}
