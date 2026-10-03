<?php
// Shared data source for teacher input and the administrator's UTS/UAS report.
function ra_jenis($value): string {
    return in_array($value, ['UTS', 'UAS'], true) ? $value : 'UTS';
}

function ra_status(array $row): string {
    if ($row['nilai'] === null) return 'Belum diisi';
    return (float)$row['nilai'] >= (float)$row['kkm'] ? 'Tuntas' : 'Perlu Bimbingan';
}

function ra_lengkap(array $row): bool {
    return $row['nilai'] !== null && trim($row['deskripsi'] ?? '') !== '' && trim($row['saran'] ?? '') !== '';
}

function ra_rows(PDO $db, int $kelas, string $tahun, string $semester, string $jenis, int $siswa = 0): array {
    $sql = 'SELECT s.id siswa_id,s.nisn,s.nama_lengkap,p.id pengajaran_id,p.kkm,
                   m.nama_mapel,g.nama_lengkap nama_guru,kp.id komponen_id,nk.nilai,
                   cp.deskripsi,cp.saran,nk.updated_at nilai_updated_at,cp.updated_at capaian_updated_at
            FROM siswa s JOIN pengajaran p ON p.kelas_id=s.kelas_id
            JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id
            LEFT JOIN komponen_penilaian kp ON kp.pengajaran_id=p.id AND kp.nama_komponen=?
            LEFT JOIN nilai_komponen nk ON nk.komponen_id=kp.id AND nk.siswa_id=s.id
            LEFT JOIN capaian_penilaian cp ON cp.komponen_id=kp.id AND cp.siswa_id=s.id
            WHERE p.kelas_id=? AND p.tahun_ajaran=? AND p.semester=?';
    $args = [$jenis,$kelas,$tahun,$semester];
    if ($siswa) { $sql .= ' AND s.id=?'; $args[] = $siswa; }
    $sql .= ' ORDER BY s.nama_lengkap,s.id,m.nama_mapel,g.nama_lengkap,p.id,kp.id';
    $stmt = $db->prepare($sql); $stmt->execute($args);
    return $stmt->fetchAll();
}

function ra_save(PDO $db, int $guru, int $pengajaran, int $siswa, string $jenis, $score, $deskripsi, $saran): void {
    if (!in_array($jenis,['UTS','UAS'],true)) throw new RuntimeException('Jenis penilaian tidak valid.');
    if (!is_string($score) || !is_string($deskripsi) || !is_string($saran)) throw new RuntimeException('Isian tidak valid.');
    $score=trim($score); $deskripsi=trim($deskripsi); $saran=trim($saran);
    if ($score!=='' && (!is_numeric($score) || !is_finite((float)$score) || (float)$score<0 || (float)$score>100)) throw new RuntimeException('Nilai harus berupa angka 0 sampai 100.');
    if (mb_strlen($deskripsi)>2000 || mb_strlen($saran)>2000) throw new RuntimeException('Deskripsi dan saran masing-masing maksimal 2000 karakter.');
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT p.id FROM pengajaran p JOIN siswa s ON s.kelas_id=p.kelas_id WHERE p.id=? AND p.guru_id=? AND s.id=? FOR UPDATE');
        $stmt->execute([$pengajaran,$guru,$siswa]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Siswa atau pengajaran bukan milik Anda.');
        $stmt=$db->prepare('SELECT id FROM komponen_penilaian WHERE pengajaran_id=? AND nama_komponen=? FOR UPDATE');
        $stmt->execute([$pengajaran,$jenis]); $ids=$stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids)!==1) throw new RuntimeException('Pastikan tepat satu komponen '.$jenis.' sudah ditambahkan pada pengajaran ini.');
        $komponen=(int)$ids[0];
        $stmt=$db->prepare('SELECT nilai FROM nilai_komponen WHERE komponen_id=? AND siswa_id=? FOR UPDATE');
        $stmt->execute([$komponen,$siswa]); $old=$stmt->fetchColumn();
        if ($score==='' && $old!==false) throw new RuntimeException('Nilai yang sudah tersimpan tidak dapat dikosongkan. Masukkan nilai penggantinya.');
        if ($score!=='' && ($old===false || abs((float)$old-round((float)$score,2))>.001)) {
            $db->prepare('INSERT INTO nilai_komponen(komponen_id,siswa_id,nilai) VALUES(?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)')->execute([$komponen,$siswa,round((float)$score,2)]);
            $db->prepare('INSERT INTO riwayat_nilai(pengajaran_id,siswa_id,komponen_id,guru_id,nilai_lama,nilai_baru) VALUES(?,?,?,?,?,?)')->execute([$pengajaran,$siswa,$komponen,$guru,$old===false?null:$old,round((float)$score,2)]);
        }
        $db->prepare('INSERT INTO capaian_penilaian(komponen_id,siswa_id,deskripsi,saran) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE deskripsi=VALUES(deskripsi),saran=VALUES(saran)')->execute([$komponen,$siswa,$deskripsi,$saran]);
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}

function ra_export_rows(array $rows): array {
    $result=[];
    foreach ($rows as $i=>$r) $result[]=[$i+1,$r['nisn'],$r['nama_lengkap'],$r['nama_mapel'],$r['nama_guru'],$r['nilai']===null?'Belum diisi':number_format((float)$r['nilai'],2,'.',''),$r['deskripsi']?:'Belum diisi',$r['saran']?:'Belum diisi',ra_status($r),ra_lengkap($r)?'Lengkap':'Belum lengkap'];
    return $result;
}

function ra_excel(string $title, array $headers, array $rows): string {
    $x=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
    $out='<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="Wrap"><Alignment ss:Vertical="Top" ss:WrapText="1"/></Style><Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/><Alignment ss:WrapText="1"/></Style></Styles><Worksheet ss:Name="Rekap"><Table ss:DefaultColumnWidth="110">';
    foreach ([35,95,150,160,150,80,280,280,110,110] as $width) $out.='<Column ss:Width="'.$width.'"/>';
    $out.='<Row><Cell ss:MergeAcross="9" ss:StyleID="Header"><Data ss:Type="String">'.$x($title).'</Data></Cell></Row>';
    foreach (array_merge([$headers],$rows) as $index=>$row) {
        $out.='<Row>';
        foreach ($row as $col=>$value) {
            // Explicit string cells preserve NISN and prevent formula interpretation.
            $type=$index>0 && ($col===0 || ($col===5 && is_numeric($value)))?'Number':'String';
            $out.='<Cell ss:StyleID="'.($index===0?'Header':'Wrap').'"><Data ss:Type="'.$type.'">'.$x($value).'</Data></Cell>';
        }
        $out.='</Row>';
    }
    return $out.'</Table></Worksheet></Workbook>';
}

function ra_pdf(string $title, array $rows): string {
    // Wrapped student/subject blocks keep long teacher narratives readable over page breaks.
    $encode=static fn($v)=>(string)iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$v);
    $escape=static fn($v)=>str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$v);
    $pages=[]; $lines=[]; $student=null;
    foreach ($rows as $r) {
        if ($student!==null && $student!==$r['siswa_id'] && $lines) { $pages[]=$lines; $lines=[]; }
        $student=$r['siswa_id'];
        $block=['Siswa: '.$r['nama_lengkap'].' | NISN: '.$r['nisn'],
                'Mapel: '.$r['nama_mapel'].' | Guru: '.$r['nama_guru'],
                'Nilai: '.($r['nilai']===null?'Belum diisi':number_format((float)$r['nilai'],2)).' | '.ra_status($r).' | '.(ra_lengkap($r)?'Lengkap':'Belum lengkap'),
                'Deskripsi capaian: '.($r['deskripsi']?:'Belum diisi'),
                'Saran capaian: '.($r['saran']?:'Belum diisi'),str_repeat('-',100)];
        foreach ($block as $text) foreach (explode("\n",wordwrap(str_replace(["\r","\t"],['',' '],$encode($text)),100,"\n",true)) as $line) {
            if (count($lines)>=50) { $pages[]=$lines; $lines=['Lanjutan: '.$encode($r['nama_lengkap'])]; }
            $lines[]=$line;
        }
    }
    if ($lines) $pages[]=$lines;
    if (!$pages) $pages=[['Tidak ada data.']];
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>']; $kids=[]; $next=4;
    foreach ($pages as $i=>$page) {
        $p=$next++; $c=$next++; $kids[]="$p 0 R";
        $cmd="BT /F1 8 Tf 30 800 Td 13 TL\n";
        foreach (explode("\n",wordwrap($encode($title),100,"\n",true)) as $line) $cmd.='('.$escape($line).") Tj T*\n";
        $cmd.="() Tj T*\n";
        foreach ($page as $line) $cmd.='('.$escape($line).") Tj T*\n";
        $cmd.="ET\nBT /F1 8 Tf 30 25 Td (Halaman ".($i+1).' / '.count($pages).") Tj ET";
        $objects[$p]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $c 0 R >>";
        $objects[$c]='<< /Length '.strlen($cmd)." >>\nstream\n$cmd\nendstream";
    }
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>'; ksort($objects);
    $pdf="%PDF-1.4\n"; $offset=[];
    foreach ($objects as $n=>$body) { $offset[$n]=strlen($pdf); $pdf.="$n 0 obj\n$body\nendobj\n"; }
    $xref=strlen($pdf); $count=count($objects)+1; $pdf.="xref\n0 $count\n0000000000 65535 f \n";
    for ($n=1;$n<$count;$n++) $pdf.=sprintf('%010d 00000 n ',$offset[$n])."\n";
    return $pdf."trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}

