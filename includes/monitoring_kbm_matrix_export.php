<?php
require_once __DIR__.'/monitoring_kbm_matrix.php';
require_once __DIR__.'/monitoring_kbm_export.php';

function kbm_matrix_export_file(PDO $db,array $input): array {
    $matrix=kbm_history_matrix($db,array_intersect_key($input,array_flip(['tanggal','sesi'])));
    if(!$matrix['rows'])throw new KbmExportNotice('Tidak ada kelas pada tanggal dan sesi yang dipilih.');
    if(count($matrix['rows'])>5000)throw new KbmExportNotice('Rekap melebihi 5.000 kelas. Pilih satu sesi agar hasil lebih kecil.');
    $session=[''=>'Semua Sesi','pagi'=>'Pagi','siang'=>'Siang'][$matrix['session']];
    $rows=(function()use($matrix){foreach($matrix['rows'] as $i=>$class){
        $row=[$i+1,$class['name'],$class['sessions']?implode(' / ',array_map('ucfirst',$class['sessions'])):'Belum diatur'];
        foreach($class['cells'] as $cell)$row[]=match($cell['kind']??'belum_tercatat'){'tugas'=>'Tugas','hadir'=>'✓','tidak_masuk'=>'⊖',default=>'—'};
        yield $row;
    }})();
    $options=['sheet'=>'Rekap JP','freeze'=>true,'widths'=>[40,155,100,65,65,65,65,65,65],
        'types'=>['Number','String','String','String','String','String','String','String','String'],
        'status_columns'=>range(3,8),'status_colors'=>['✓'=>'#16874B','⊖'=>'#D62F43','—'=>'#7A8798','Tugas'=>'#737B85'],
        'metadata'=>['Tanggal: '.kbm_date($matrix['date'])->format('d/m/Y').' | Sesi: '.$session,
            'Tugas: Guru hanya memberikan tugas | Jumlah: '.count($matrix['rows']).' kelas | Dibuat dari server: '.$matrix['now'].' WIB',
            '✓ Check-in tercatat | ⊖ Dilaporkan tidak masuk | — Belum ada laporan masuk',
            'Ceklis tidak berarti sudah check-out. Catatan dibatalkan tidak dihitung. Data berasal dari laporan ketua kelas atau koreksi admin.']];
    $file=tmpfile();if($file===false)throw new RuntimeException('File rekap belum dapat disiapkan.');
    try{
        foreach(ra_excel_parts('REKAP MONITORING KBM - SMKS JAYA BUANA',['No','Kelas','Sesi','JP 1','JP 2','JP 3','JP 4','JP 5','JP 6'],$rows,$options) as $part){
            if(fwrite($file,$part)!==strlen($part))throw new RuntimeException('File rekap belum dapat ditulis lengkap.');
            if(ftell($file)>26214400)throw new KbmExportNotice('File melebihi 25 MB. Persempit filter sesi.');
        }
        rewind($file);return ['file'=>$file,'size'=>fstat($file)['size'],'count'=>count($matrix['rows']),
            'filename'=>'Rekap_KBM_'.$matrix['date'].'_'.str_replace(' ','_',$session).'.xls'];
    }catch(Throwable $e){fclose($file);throw $e;}
}
