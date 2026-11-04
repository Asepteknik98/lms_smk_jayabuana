<?php
require_once __DIR__.'/kak_staff.php';
require_once __DIR__.'/excel_workbook.php';

function kak_export_sheets(PDO $db,int $user_id,string $kind,array $input): array {
    $data=kak_staff_list($db,$user_id,$kind,$input,true);
    $claims=$kind==='claims';
    $rows=[$claims?['No.','Tanggal Klaim (WIB)','Aktivitas','Siswa','Kelas','Status Klaim','Catatan Siswa','Catatan Verifikasi','Waktu Verifikasi (WIB)']:['No.','Tanggal Acara','Nama Aktivitas','Sasaran','Tingkat Aktivitas','Status Acara','Keterangan','Periode Klaim']];
    $approved=0;$waiting=0;$rejected=0;$credited=0;$points=0;
    foreach($data['rows'] as $i=>$r){
        $points+=(int)$r['poin'];
        if($claims){
            $earned=$r['status']==='Disetujui'?(int)$r['poin']:0;
            $credited+=$earned;$approved+=(int)($r['status']==='Disetujui');$waiting+=(int)($r['status']==='Menunggu');$rejected+=(int)($r['status']==='Ditolak');
            $rows[]=[$i+1,$r['created_at'],$r['nama'],$r['nama_lengkap'],$r['nama_kelas']??'Tanpa kelas',$r['status']==='Ditolak'?'Perlu perbaikan':$r['status'],$r['catatan']??'',$r['catatan_verifikasi']??'',$r['diverifikasi_pada']??''];
        }else{
            $target=$r['target_tingkat']?'Semua kelas '.(['X'=>'10','XI'=>'11','XII'=>'12'][$r['target_tingkat']]).' ('.$r['target_tingkat'].')':($r['nama_lengkap']??$r['nama_kelas']??'Semua siswa');
            $rows[]=[$i+1,$r['tanggal_acara'],$r['nama'],$target,$r['tingkat_aktivitas']??'',$r['status']==='Terbit'?'Terbit':'Diarsipkan',$r['deskripsi']??'','Tanpa batas waktu'];
        }
    }
    $summary=[['REKAP KREDIT AKTIVITAS SISWA','Keterangan'],['Jenis Rekap',$claims?'Klaim Siswa':'Acara Diterbitkan'],['Waktu Ekspor (WIB)',date('Y-m-d H:i:s')],['Pencarian',$data['search']?:'Semua'],['Filter Status',$claims?($data['status']?:'Semua status'):'Semua status'],['Jumlah Data',$data['total']]];
    if($claims){$summary[]=['Menunggu',$waiting];$summary[]=['Disetujui',$approved];$summary[]=['Ditolak',$rejected];}

    return [
        ['name'=>'Ringkasan','rows'=>$summary,'widths'=>[34,65],'freeze'=>1],
        ['name'=>$claims?'Klaim Siswa':'Acara Diterbitkan','rows'=>$rows,'widths'=>$claims?[8,23,45,30,20,14,18,18,50,50,23]:[8,18,45,35,20,12,18,50,24],'numericColumns'=>[0],'filter'=>true,'freeze'=>1]
    ];
}
