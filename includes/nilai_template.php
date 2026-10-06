<?php
// Small XLSX template writer: ZIP stored entries, no server ZIP extension required.
function ni_template_zip(array $entries): string {
    $body='';$directory='';
    foreach($entries as $name=>$data){
        $size=strlen($data);$crc=crc32($data);$offset=strlen($body);$length=strlen($name);
        $body.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,33,$crc,$size,$size,$length,0).$name.$data;
        $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,33,$crc,$size,$size,$length,0,0,0,0,0,$offset).$name;
    }
    return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,count($entries),count($entries),strlen($directory),strlen($body),0);
}

function ni_template_xlsx(array $context): string {
    if(count($context['students'])>4995)throw new RuntimeException('Jumlah siswa melebihi batas template.');
    $period=in_array($context['component']['nama_komponen'],['UTS','UAS'],true);
    $headers=['Nama Siswa','Kelas','Nilai'];
    if($period)$headers=array_merge($headers,['Deskripsi Capaian Pembelajaran','Saran Capaian Pembelajaran']);
    $rows=[
        ['Template '.$context['component']['nama_komponen'].' - '.$context['nama_mapel']],
        [$context['nama_kelas'].' | '.$context['semester'].' | '.$context['tahun_ajaran']],
        ['Isi Nilai 0–100. Biarkan kosong bila tidak diisi. Nilai lama tidak ditimpa.'],
        [$period?'Deskripsi/saran opsional, maksimal 2000 karakter; hanya teks kosong yang diisi.':'Pilih komponen tujuan yang sesuai saat impor.'],
        $headers
    ];
    foreach($context['students'] as $s)$rows[]=array_merge([$s['nama_lengkap'],$context['nama_kelas']],array_fill(0,count($headers)-2,''));
    $escape=static fn($s)=>htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$s),ENT_XML1|ENT_QUOTES,'UTF-8');
    $xml='';foreach($rows as $i=>$row){
        $xml.='<row r="'.($i+1).'"'.($i===4?' ht="32" customHeight="1"':'').'>';
        foreach($row as $j=>$value)$xml.='<c r="'.chr(65+$j).($i+1).'" t="inlineStr" s="'.($i===4?'1':'0').'"><is><t xml:space="preserve">'.$escape($value).'</t></is></c>';
        $xml.='</row>';
    }
    $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$end=($period?'E':'C').count($rows);
    $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$ns.'"><dimension ref="A1:'.$end.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="35" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="14" customWidth="1"/>'.($period?'<col min="4" max="5" width="55" customWidth="1"/>':'').'</cols><sheetData>'.$xml.'</sheetData><autoFilter ref="A5:'.$end.'"/><mergeCells count="4">';
    for($i=1;$i<=4;$i++)$sheet.='<mergeCell ref="A'.$i.':'.($period?'E':'C').$i.'"/>';
    $sheet.='</mergeCells></worksheet>';
    return ni_template_zip([
        '[Content_Types].xml'=>'<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml'=>'<workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template Nilai" sheetId="1" r:id="r1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="r2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml'=>'<styleSheet xmlns="'.$ns.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        'xl/worksheets/sheet1.xml'=>$sheet
    ]);
}
