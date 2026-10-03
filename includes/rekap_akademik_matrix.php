<?php
require_once __DIR__.'/rekap_akademik.php';

function ram_columns(): array {
    return ['Ulangan Harian','Tugas Harian','Kehadiran','UTS','UAS','Ujian Praktik','Nilai Akhir','Deskripsi Capaian Pembelajaran','Saran Capaian Pembelajaran'];
}

function ram_data(PDO $db, int $kelas, string $tahun, string $semester): array {
    $stmt=$db->prepare('SELECT p.id,p.kkm,m.nama_mapel,g.nama_lengkap nama_guru FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id WHERE p.kelas_id=? AND p.tahun_ajaran=? AND p.semester=? ORDER BY m.nama_mapel,g.nama_lengkap,p.id');
    $stmt->execute([$kelas,$tahun,$semester]);$subjects=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT id,nisn,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');$stmt->execute([$kelas]);$students=[];
    foreach($stmt->fetchAll() as $s){$s['subjects']=[];$students[$s['id']]=$s;}
    foreach($subjects as &$subject){
        $pid=(int)$subject['id'];
        $stmt=$db->prepare('SELECT id,nama_komponen,bobot FROM komponen_penilaian WHERE pengajaran_id=? ORDER BY urutan,id');$stmt->execute([$pid]);$components=$stmt->fetchAll();
        $subject['weights']=[];
        foreach($components as $c)$subject['weights'][$c['nama_komponen']]=(float)$c['bobot'];
        $stmt=$db->prepare('SELECT nk.siswa_id,nk.komponen_id,nk.nilai FROM nilai_komponen nk JOIN komponen_penilaian kp ON kp.id=nk.komponen_id WHERE kp.pengajaran_id=?');$stmt->execute([$pid]);$scores=[];
        foreach($stmt->fetchAll() as $r)$scores[$r['siswa_id']][$r['komponen_id']]=(float)$r['nilai'];
        $stmt=$db->prepare('SELECT cp.siswa_id,kp.nama_komponen,cp.deskripsi,cp.saran FROM capaian_penilaian cp JOIN komponen_penilaian kp ON kp.id=cp.komponen_id WHERE kp.pengajaran_id=?');$stmt->execute([$pid]);$notes=[];
        foreach($stmt->fetchAll() as $r)$notes[$r['siswa_id']][$r['nama_komponen']]=$r;
        // The same automatic averages as the teacher's grade recap, including manual tasks.
        $stmt=$db->prepare("SELECT s.id,
          COALESCE((SELECT AVG(COALESCE(nu.nilai_total,0)) FROM ujian u LEFT JOIN nilai_ujian nu ON nu.ujian_id=u.id AND nu.siswa_id=s.id WHERE u.pengajaran_id=? AND u.jenis_ujian='Kuis' AND u.waktu_selesai<=NOW()),0) ulangan,
          COALESCE((SELECT (SUM(COALESCE(pt.nilai,0))+COALESCE((SELECT SUM(nm.nilai) FROM nilai_tugas_manual nm WHERE nm.pengajaran_id=? AND nm.siswa_id=s.id),0))/NULLIF(COUNT(*)+(SELECT COUNT(*) FROM nilai_tugas_manual nm WHERE nm.pengajaran_id=? AND nm.siswa_id=s.id),0) FROM tugas t LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.siswa_id=s.id WHERE t.pengajaran_id=? AND t.deadline<=NOW()),0) tugas,
          COALESCE((SELECT AVG(CASE WHEN da.status IN ('Hadir','Sakit','Izin') THEN 100 ELSE 0 END) FROM sesi_absensi sa LEFT JOIN detail_absensi da ON da.sesi_absensi_id=sa.id AND da.siswa_id=s.id WHERE sa.pengajaran_id=? AND sa.status='Ditutup'),0) kehadiran
          FROM siswa s WHERE s.kelas_id=?");
        $stmt->execute([$pid,$pid,$pid,$pid,$pid,$kelas]);
        foreach($stmt->fetchAll() as $auto){
            $sid=$auto['id'];$values=array_fill_keys(array_slice(ram_columns(),0,6),null);
            $automatic=['Ulangan Harian'=>(float)$auto['ulangan'],'Tugas Harian'=>(float)$auto['tugas'],'Kehadiran'=>(float)$auto['kehadiran']];
            $total=0;$missing=false;
            foreach($components as $c){
                $value=$scores[$sid][$c['id']]??($automatic[$c['nama_komponen']]??null);
                $values[$c['nama_komponen']]=$value;
                if($value===null)$missing=true;else $total+=$value*(float)$c['bobot']/100;
            }
            $ready=abs(array_sum(array_column($components,'bobot'))-100)<.001;
            $values['Nilai Akhir']=!$ready?'Bobot belum 100%':($missing?'Belum lengkap':round($total,2));
            foreach(['Deskripsi Capaian Pembelajaran'=>'deskripsi','Saran Capaian Pembelajaran'=>'saran'] as $label=>$field){
                $parts=[];
                foreach(['UTS','UAS'] as $period)$parts[]=$period.': '.(trim($notes[$sid][$period][$field]??'')?:'Belum diisi');
                $values[$label]=implode("\n",$parts);
            }
            $students[$sid]['subjects'][$pid]=$values;
        }
    }
    unset($subject);
    return ['subjects'=>$subjects,'students'=>array_values($students)];
}

function ram_label(array $subject, string $column): string {
    if(!in_array($column,array_slice(ram_columns(),0,6),true))return $column;
    $weight=isset($subject['weights'][$column])?number_format($subject['weights'][$column],2,',','.').'%':'Belum diatur';
    return $column."\n".$weight."\n".(in_array($column,['UTS','UAS','Ujian Praktik'],true)?'Manual':'Otomatis / Manual');
}

function ram_rows(array $data): array {
    $rows=[];
    foreach($data['students'] as $i=>$s){
        $row=[$i+1,$s['nisn']??'',$s['nama_lengkap']];
        foreach($data['subjects'] as $subject)foreach(ram_columns() as $column)$row[]=$s['subjects'][$subject['id']][$column]??'Belum diisi';
        $rows[]=$row;
    }
    return $rows;
}

function ram_sheet_name(string $name, array &$used): string {
    $name=str_replace(['\\','/',':','*','?','[',']'], ' ', $name);
    $name=preg_replace('/[\x00-\x1F\x7F]/u','',$name);
    $base=trim(mb_substr(trim($name," '\t\r\n"),0,31)," '");
    if($base==='')$base='Mapel';
    $candidate=$base;$number=1;
    while(isset($used[mb_strtolower($candidate)])){
        $suffix=' ('.(++$number).')';
        $candidate=rtrim(mb_substr($base,0,31-mb_strlen($suffix))," '").$suffix;
    }
    $used[mb_strtolower($candidate)]=true;
    return $candidate;
}

function ram_excel(string $title,array $data): string {
    $x=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
    $cell=static function($value,$style='Wrap',$merge=0)use($x){return '<Cell ss:StyleID="'.$style.'"'.($merge?' ss:MergeAcross="'.$merge.'"':'').'><Data ss:Type="'.(is_int($value)||is_float($value)?'Number':'String').'">'.$x($value).'</Data></Cell>';};
    $xml='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="Wrap"><Alignment ss:Vertical="Top" ss:WrapText="1"/></Style><Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style></Styles>';
    $used=[];
    foreach($data['subjects'] as $subject){
        $name=ram_sheet_name($subject['nama_mapel'],$used);
        $xml.='<Worksheet ss:Name="'.$x($name).'"><Table>';
        foreach([35,95,160,90,90,90,70,70,90,100,280,280] as $width)$xml.='<Column ss:Width="'.$width.'"/>';
        $xml.='<Row>'.$cell($title,'Header',11).'</Row><Row>'.$cell($subject['nama_mapel']."\nGuru: ".$subject['nama_guru'],'Header',11).'</Row><Row ss:Height="65">';
        foreach(['No','NISN','Nama Siswa'] as $label)$xml.=$cell($label,'Header');
        foreach(ram_columns() as $column)$xml.=$cell(ram_label($subject,$column),'Header');
        $xml.='</Row>';
        foreach(ram_rows(['subjects'=>[$subject],'students'=>$data['students']]) as $row){$xml.='<Row>';foreach($row as $value)$xml.=$cell($value);$xml.='</Row>';}
        if(!$data['students'])$xml.='<Row>'.$cell('Tidak ada siswa sesuai filter.','Wrap',11).'</Row>';
        $xml.='</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>3</SplitHorizontal><TopRowBottomPane>3</TopRowBottomPane><SplitVertical>3</SplitVertical><LeftColumnRightPane>3</LeftColumnRightPane></WorksheetOptions></Worksheet>';
    }
    if(!$data['subjects'])$xml.='<Worksheet ss:Name="Rekap Kelas"><Table><Row>'.$cell($title,'Header').'</Row><Row>'.$cell('Tidak ada mata pelajaran sesuai filter.').'</Row></Table></Worksheet>';
    return $xml.'</Workbook>';
}

function ram_pdf(string $title,array $data): string {
    // A wide landscape sheet keeps every subject beside the student's identity.
    // Extreme widths are tiled at the PDF 200-inch page limit, repeating identities.
    $encode=static fn($v)=>(string)iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$v);
    $escape=static fn($v)=>str_replace(['\\','(',')',"\r"],['\\\\','\\(','\\)',''],$v);
    $wrap=static function($text,$width)use($encode){return explode("\n",wordwrap(str_replace("\t",' ',$encode($text)),max(1,(int)(($width-8)/4.8)),"\n",true));};
    $pages=[];$groups=array_chunk($data['subjects'],17);if(!$groups)$groups=[[]];
    foreach($groups as $groupIndex=>$subjects){
        $slice=['subjects'=>$subjects,'students'=>$data['students']];
        $widths=[32,85,140];foreach($subjects as $subject)$widths=array_merge($widths,[65,65,65,45,45,65,85,180,180]);
        $pageWidth=max(842,40+array_sum($widths));
        $draw=static function($cells,$y,$height,$header=false)use($escape){
            $out='';$x=20;
            foreach($cells as $cell){[$width,$lines]=$cell;
                if($header)$out.="0.86 0.92 1 rg $x ".($y-$height)." $width $height re f\n";
                $out.="0.65 G 0.4 w $x ".($y-$height)." $width $height re S\n0 g\nBT /F1 8 Tf ".($x+4).' '.($y-12)." Td 11 TL\n";
                foreach($lines as $line)$out.='('.$escape($line).") Tj T*\n";
                $out.="ET\n";$x+=$width;
            }return $out;
        };
        $start=static function()use($title,$groupIndex,$groups,$subjects,$widths,$wrap,$draw){
            $caption=$title.(count($groups)>1?' - Bagian mapel '.($groupIndex+1).'/'.count($groups):'');
            $cmd=$draw([[array_sum($widths),$wrap($caption,array_sum($widths))]],815,32,true);
            $top=[[257,['Identitas Siswa']]];
            foreach($subjects as $subject)$top[]=[795,$wrap($subject['nama_mapel'].' / '.$subject['nama_guru'],795)];
            $cmd.=$draw($top,783,38,true);
            $labels=['No','NISN','Nama Siswa'];foreach($subjects as $subject)foreach(ram_columns() as $column)$labels[]=ram_label($subject,$column);
            $cells=[];$count=0;foreach($labels as $i=>$label){$lines=$wrap($label,$widths[$i]);$count=max($count,count($lines));$cells[]=[$widths[$i],$lines];}
            $height=$count*11+10;$cmd.=$draw($cells,745,$height,true);
            return [$cmd,745-$height];
        };
        [$cmd,$y]=$start();
        $rows=ram_rows($slice);if(!$rows)$rows=[[1,'','Tidak ada siswa.']];
        foreach($rows as $row){
            $wrapped=[];$lineCount=0;
            foreach($widths as $i=>$width){$lines=$wrap((string)($row[$i]??''),$width);$wrapped[]=$lines;$lineCount=max($lineCount,count($lines));}
            $offset=0;
            while($offset<$lineCount){
                $capacity=(int)(($y-40-10)/11);
                if($capacity<1 || ($offset===0&&$lineCount<45&&$capacity<$lineCount)){$pages[]=[$pageWidth,$cmd];[$cmd,$y]=$start();$capacity=(int)(($y-40-10)/11);}
                $take=min($capacity,$lineCount-$offset);$cells=[];
                foreach($widths as $i=>$width)$cells[]=[$width,($i<3&&$offset>0)?$wrapped[$i]:array_slice($wrapped[$i],$offset,$take)];
                $visibleLines=$take;if($offset>0)foreach(array_slice($wrapped,0,3) as $identity)$visibleLines=max($visibleLines,count($identity));
                $height=$visibleLines*11+10;$cmd.=$draw($cells,$y,$height);$y-=$height;$offset+=$take;
            }
        }
        $pages[]=[$pageWidth,$cmd];
    }
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>'];$kids=[];$next=4;
    foreach($pages as $i=>[$width,$cmd]){
        $p=$next++;$c=$next++;$kids[]="$p 0 R";$cmd.='BT /F1 8 Tf 20 20 Td (Halaman '.($i+1).' / '.count($pages).") Tj ET\n";
        $objects[$p]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $width 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $c 0 R >>";
        $objects[$c]='<< /Length '.strlen($cmd)." >>\nstream\n$cmd\nendstream";
    }
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[];
    foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}
    $xref=strlen($pdf);$size=count($objects)+1;$pdf.="xref\n0 $size\n0000000000 65535 f \n";
    for($i=1;$i<$size;$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
    return $pdf."trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
