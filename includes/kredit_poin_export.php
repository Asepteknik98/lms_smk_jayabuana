<?php
require_once __DIR__.'/kredit_poin.php';
require_once __DIR__.'/excel_workbook.php';

function kps_export_sheets(PDO $db,int $user_id,int $kelas_id,int $siswa_id,string $search=''): array {
    $history=kps_history_page($db,$user_id,$kelas_id,$siswa_id,1,$search,true);
    $details=[['No.','Tanggal','NIS','Siswa','Kelas','Pelanggaran','Poin Standar','Poin Dikurangi','Catatan','Penanganan','Pencatat']];
    $matched=[];
    foreach($history['rows'] as $i=>$r){
        $matched[(int)$r['siswa_id']]=true;
        $details[]=[$i+1,$r['tanggal'],(string)($r['nis']??''),$r['nama_lengkap'],$r['nama_kelas']??'Tanpa kelas',$r['alasan'],$r['poin_standar']===null?'':(int)$r['poin_standar'],abs((int)$r['perubahan']),$r['catatan']??'',$r['penanganan']??'',$r['pencatat']];
    }
    $conditions=[];$params=[];
    if($kelas_id>0){$conditions[]='s.kelas_id=?';$params[]=$kelas_id;}
    if($siswa_id>0){$conditions[]='s.id=?';$params[]=$siswa_id;}
    if(trim($search)!==''){
        if(!$matched)$conditions[]='1=0';
        else{$conditions[]='s.id IN ('.implode(',',array_fill(0,count($matched),'?')).')';array_push($params,...array_keys($matched));}
    }
    $q=$db->prepare('SELECT s.id,s.nis,s.nama_lengkap,k.nama_kelas,COALESCE(p.keluar,0) keluar,COALESCE(p.jumlah,0) jumlah FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN (SELECT siswa_id,SUM(-perubahan) keluar,COUNT(*) jumlah FROM kredit_poin_siswa GROUP BY siswa_id) p ON p.siswa_id=s.id'.($conditions?' WHERE '.implode(' AND ',$conditions):'').' ORDER BY k.nama_kelas,s.nama_lengkap,s.id');
    $q->execute($params);
    $balances=[['No.','NIS','Siswa','Kelas','Saldo Awal','Total Pengurangan','Sisa Poin','Jumlah Pelanggaran']];
    foreach($q->fetchAll() as $i=>$r)$balances[]=[$i+1,(string)($r['nis']??''),$r['nama_lengkap'],$r['nama_kelas']??'Tanpa kelas',KPS_SALDO_AWAL,(int)$r['keluar'],max(0,KPS_SALDO_AWAL-(int)$r['keluar']),(int)$r['jumlah']];
    $info=[['REKAP KREDIT POIN SISWA','Keterangan'],['Waktu Ekspor (WIB)',date('Y-m-d H:i:s')],['Pencarian',trim($search)?:'Semua'],['Jumlah Rincian Pelanggaran',$history['total']],['Jumlah Siswa',count($balances)-1],['Ringkasan Saldo','Saldo dihitung dari seluruh riwayat siswa. Pencarian membatasi rincian dan siswa yang memiliki catatan cocok.'],['Tanpa Pencarian','Semua siswa sesuai filter kelas/siswa disertakan, termasuk yang belum memiliki pelanggaran.']];
    return [
        ['name'=>'Informasi','rows'=>$info,'widths'=>[34,100]],
        ['name'=>'Ringkasan Siswa','rows'=>$balances,'widths'=>[8,20,32,22,15,22,15,22],'numericColumns'=>[0,4,5,6,7],'filter'=>true],
        ['name'=>'Rincian Pelanggaran','rows'=>$details,'widths'=>[8,18,20,32,22,65,17,20,50,50,28],'numericColumns'=>[0,6,7],'filter'=>true]
    ];
}
