<?php
require_once __DIR__.'/excel_workbook.php';
require_once __DIR__.'/nilai_excel_reader.php';
const UE_HEADERS=['No','Tipe Soal','Pertanyaan','Pilihan A','Pilihan B','Pilihan C','Pilihan D','Pilihan E','Kunci Jawaban','Bobot'];
function ue_weight($value): int {
    $raw=preg_replace('/[\s\x{00A0}\x{202F}]+/u','',(string)$value);
    if($raw==='')return 1;
    // Excel may save a whole number as text such as 01, 1.00 or 1,0.
    if(!preg_match('/^\+?\d+(?:[.,]0+)?$/D',$raw))throw new RuntimeException('bobot harus angka bulat 1-100 (contoh: 1 atau 2); kosong berarti 1.');
    $number=(float)str_replace(',','.',$raw);
    if(!is_finite($number)||$number<1||$number>100)throw new RuntimeException('bobot harus antara 1 dan 100; kosong berarti 1.');
    return (int)$number;
}
function ue_template(string $kind='campuran'): string {
    if(!in_array($kind,['campuran','pg','esai'],true))throw new RuntimeException('Jenis template tidak valid.');
    $rows=[UE_HEADERS];for($i=1;$i<=100;$i++)$rows[]=[$i,$kind==='esai'?'ESAI':'PG','','','','','','','',1];
    $examples=[UE_HEADERS,[1,'PG','Hasil 2 + 3 adalah ...','2','3','4','5','','D',1],[2,'ESAI','Jelaskan langkah menyelesaikan persamaan x + 2 = 5.','','','','','','',3]];
    return ew_workbook([
        ['name'=>'Soal','rows'=>$rows,'widths'=>[7,15,65,25,25,25,25,25,18,10],'numericColumns'=>[0,9],'filter'=>true,'validation'=>[
            ['type'=>'list','range'=>'B2:B501','first'=>'"PG,ESAI"','error'=>'Pilih PG atau ESAI.'],
            ['type'=>'list','range'=>'I2:I501','first'=>'"A,B,C,D,E"','error'=>'Kunci PG harus A-E; kosongkan untuk esai.'],
            ['type'=>'whole','range'=>'J2:J501','first'=>'1','second'=>'100','error'=>'Bobot harus bilangan bulat 1-100.']]],
        ['name'=>'Petunjuk','rows'=>[['PETUNJUK TEMPLATE SOAL UH'],['1. Isi soal pada sheet Soal. Tersedia 100 baris; boleh menambah sampai 500 soal.'],['2. Pilih Tipe Soal PG atau ESAI. PG dan esai boleh digabung dalam satu file.'],['3. PG: isi pertanyaan, Pilihan A-D, kunci A-E dan bobot 1-100 (boleh dikosongkan, otomatis 1). Pilihan E boleh kosong kecuali kuncinya E.'],['4. ESAI: isi pertanyaan; bobot boleh kosong (otomatis 1). Kosongkan semua pilihan dan kunci. Esai dinilai guru setelah siswa selesai.'],['5. Baris kosong atau yang hanya berisi No/Tipe diabaikan. Contoh ada pada sheet Contoh dan tidak ikut diimpor.'],['6. Jangan mengubah header/urutan kolom atau memindahkan sheet Soal. Simpan sebagai .xlsx; XLS lama juga diterima.'],['7. Unggah melalui Impor soal pada UH CBT yang belum memiliki peserta. Batas file 5 MB.'],['8. Impor menambahkan soal. Soal yang identik dilewati agar tidak ganda. Bobot adalah perbandingan antarsoal, tidak harus berjumlah 100.']],'widths'=>[115]],
        ['name'=>'Contoh','rows'=>$examples,'widths'=>[7,15,65,25,25,25,25,25,18,10]]
    ]);
}
function ue_parse(array $rows): array {
    $header=null;$questions=[];
    foreach($rows as $i=>$row){
        $row=array_map(static fn($v)=>trim((string)$v),$row);
        if($header===null){if(array_map('strtolower',array_values(array_slice($row,0,10)))===array_map('strtolower',UE_HEADERS))$header=$i;elseif($i>30)break;continue;}
        $cells=[];for($col=0;$col<10;$col++)$cells[$col]=$row[$col]??'';
        // Pre-numbered blank template rows are intentionally not questions.
        if(!array_filter(array_slice($cells,2,7),static fn($v)=>$v!==''))continue;
        foreach($row as $col=>$value)if($col>=10&&$value!=='')throw new RuntimeException('Baris '.($i+1).': ada isian di luar kolom template.');
        [$no,$type,$question]=$cells;$type=strtoupper($type);if(in_array($type,['ESAI','ESSAI','URAIAN'],true))$type='ESAI';if($type==='PILIHAN GANDA')$type='PG';
        if(str_starts_with(strtoupper($question),'HAPUS CONTOH INI'))continue;
        $error='Baris '.($i+1).': ';
        if(!in_array($type,['PG','ESAI'],true)||$question==='')throw new RuntimeException($error.'isi tipe PG/ESAI dan pertanyaan.');
        try{$weight=ue_weight($cells[9]);}catch(RuntimeException $e){throw new RuntimeException($error.$e->getMessage());}
        $options=array_slice($cells,3,5);$key=strtoupper($cells[8]);
        if($type==='PG'){
            if(in_array('',array_slice($options,0,4),true)||!in_array($key,['A','B','C','D','E'],true)||($key==='E'&&$options[4]===''))throw new RuntimeException($error.'PG memerlukan Pilihan A-D dan kunci A-E yang sesuai pilihan.');
        }elseif(array_filter($options,static fn($v)=>$v!=='')||$key!=='')throw new RuntimeException($error.'kosongkan pilihan dan kunci untuk soal esai.');
        $questions[]=[$type,$question,...array_map(static fn($v)=>$v===''?null:$v,$options),$type==='PG'?$key:null,$weight];
        if(count($questions)>500)throw new RuntimeException('Maksimal 500 soal per impor.');
    }
    if($header===null)throw new RuntimeException('Header soal tidak ditemukan atau berubah. Gunakan sheet pertama dari template resmi.');
    if(!$questions)throw new RuntimeException('Belum ada soal yang diisi. Isi sheet Soal, bukan sheet Contoh.');return $questions;
}
