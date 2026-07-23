<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

check_access([1]);
$db = Database::getInstance();
$jenis = $_GET['jenis'] ?? '';
$format = $_GET['format'] ?? '';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$scope = $_GET['scope'] ?? 'siswa';

if (!in_array($jenis, ['guru', 'siswa', 'absensi'], true) || !in_array($format, ['pdf', 'excel'], true)) {
    http_response_code(400); exit('Permintaan ekspor tidak valid.');
}

$judul = ''; $headers = []; $rows = [];
$namaFile = 'rekap-' . $jenis . '-' . date('Ymd-His');

if ($jenis === 'guru') {
    if ($id > 0) {
        $stmt = $db->prepare("SELECT g.nama_lengkap,g.nip,g.email,u.username,IF(u.is_active=1,'Aktif','Nonaktif') status_akun,COALESCE(m.nama_mapel,'-') nama_mapel,COALESCE(k.nama_kelas,'-') nama_kelas,COALESCE(p.semester,'-') semester,COALESCE(p.tahun_ajaran,'-') tahun_ajaran FROM guru g JOIN users u ON u.id=g.user_id LEFT JOIN pengajaran p ON p.guru_id=g.id LEFT JOIN mapel m ON m.id=p.mapel_id LEFT JOIN kelas k ON k.id=p.kelas_id WHERE g.id=? ORDER BY p.tahun_ajaran DESC,p.semester,m.nama_mapel");
        $stmt->execute([$id]); $data = $stmt->fetchAll();
        if (!$data) { http_response_code(404); exit('Data guru tidak ditemukan.'); }
        $judul = 'Rekap Guru - ' . $data[0]['nama_lengkap'];
        $headers = ['Nama','NIP','Email','Username','Status','Mata Pelajaran','Kelas','Semester','Tahun Ajaran'];
        foreach ($data as $r) $rows[] = [$r['nama_lengkap'],$r['nip'] ?: '-',$r['email'] ?: '-',$r['username'],$r['status_akun'],$r['nama_mapel'],$r['nama_kelas'],$r['semester'],$r['tahun_ajaran']];
    } else {
        $data = $db->query("SELECT g.nip,g.nama_lengkap,u.username,IF(u.is_active=1,'Aktif','Nonaktif') status_akun,(SELECT COUNT(*) FROM pengajaran p WHERE p.guru_id=g.id) total_pengajaran,(SELECT COUNT(*) FROM materi x JOIN pengajaran p ON p.id=x.pengajaran_id WHERE p.guru_id=g.id) total_materi,(SELECT COUNT(*) FROM tugas x JOIN pengajaran p ON p.id=x.pengajaran_id WHERE p.guru_id=g.id) total_tugas,(SELECT COUNT(*) FROM ujian x JOIN pengajaran p ON p.id=x.pengajaran_id WHERE p.guru_id=g.id) total_ujian,(SELECT COUNT(*) FROM sesi_absensi x JOIN pengajaran p ON p.id=x.pengajaran_id WHERE p.guru_id=g.id) total_absensi FROM guru g JOIN users u ON u.id=g.user_id ORDER BY g.nama_lengkap")->fetchAll();
        $judul = 'Rekap Keseluruhan Guru';
        $headers = ['NIP','Nama Guru','Username','Status','Pengajaran','Materi','Tugas','Ujian','Sesi Absensi'];
        foreach ($data as $r) $rows[] = [$r['nip'] ?: '-',$r['nama_lengkap'],$r['username'],$r['status_akun'],$r['total_pengajaran'],$r['total_materi'],$r['total_tugas'],$r['total_ujian'],$r['total_absensi']];
    }
} elseif ($jenis === 'siswa') {
    $where = $id > 0 ? 'WHERE s.id=?' : '';
    $stmt = $db->prepare("SELECT s.nisn,s.nama_lengkap,COALESCE(k.nama_kelas,'-') nama_kelas,u.username,IF(u.is_active=1,'Aktif','Nonaktif') status_akun,(SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.siswa_id=s.id) tugas,(SELECT COUNT(*) FROM nilai_ujian nu WHERE nu.siswa_id=s.id) ujian,(SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id=s.id AND da.status='Hadir') hadir,(SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id=s.id AND da.status='Terlambat') terlambat,(SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id=s.id AND da.status='Sakit') sakit,(SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id=s.id AND da.status='Izin') izin,(SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id=s.id AND da.status='Alpa') alpa FROM siswa s JOIN users u ON u.id=s.user_id LEFT JOIN kelas k ON k.id=s.kelas_id $where ORDER BY k.nama_kelas,s.nama_lengkap");
    $stmt->execute($id > 0 ? [$id] : []); $data = $stmt->fetchAll();
    if ($id > 0 && !$data) { http_response_code(404); exit('Data siswa tidak ditemukan.'); }
    $judul = $id > 0 ? 'Rekap Siswa - ' . $data[0]['nama_lengkap'] : 'Rekap Keseluruhan Siswa';
    $headers = ['NISN','Nama Siswa','Kelas','Username','Status','Tugas','Ujian','Hadir','Terlambat','Sakit','Izin','Alpa'];
    foreach ($data as $r) $rows[] = [$r['nisn'],$r['nama_lengkap'],$r['nama_kelas'],$r['username'],$r['status_akun'],$r['tugas'],$r['ujian'],$r['hadir'],$r['terlambat'],$r['sakit'],$r['izin'],$r['alpa']];
} else {
    $where = '';
    if ($id > 0) $where = $scope === 'guru' ? 'WHERE p.guru_id=?' : 'WHERE da.siswa_id=?';
    $stmt = $db->prepare("SELECT sa.tanggal,sa.pertemuan_ke,m.nama_mapel,k.nama_kelas,g.nama_lengkap nama_guru,s.nisn,s.nama_lengkap nama_siswa,da.status,da.waktu_checkin FROM detail_absensi da JOIN sesi_absensi sa ON sa.id=da.sesi_absensi_id JOIN pengajaran p ON p.id=sa.pengajaran_id JOIN guru g ON g.id=p.guru_id JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id JOIN siswa s ON s.id=da.siswa_id $where ORDER BY sa.tanggal DESC,k.nama_kelas,s.nama_lengkap");
    $stmt->execute($id > 0 ? [$id] : []); $data = $stmt->fetchAll();
    if ($id > 0 && $scope === 'guru') {
        $judul = 'Rekap Absensi Guru' . ($data ? ' - '.$data[0]['nama_guru'] : '');
    } else {
        $judul = $id > 0 ? 'Rekap Absensi Siswa' . ($data ? ' - '.$data[0]['nama_siswa'] : '') : 'Rekap Keseluruhan Absensi';
    }
    $headers = ['Tanggal','Pertemuan','Mata Pelajaran','Kelas','Guru','NISN','Siswa','Status','Check-in'];
    foreach ($data as $r) $rows[] = [date('d/m/Y',strtotime($r['tanggal'])),$r['pertemuan_ke'],$r['nama_mapel'],$r['nama_kelas'],$r['nama_guru'],$r['nisn'],$r['nama_siswa'],$r['status'],$r['waktu_checkin'] ? date('H:i:s',strtotime($r['waktu_checkin'])) : '-'];
}

function exportEscape($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$namaFile.'.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate'); echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"><style>table{border-collapse:collapse}th,td{border:1px solid #777;padding:6px}th{background:#dbeafe}</style></head><body><h2>'.exportEscape($judul).'</h2><p>Dicetak: '.date('d/m/Y H:i').'</p><table><thead><tr>';
    foreach ($headers as $h) echo '<th>'.exportEscape($h).'</th>'; echo '</tr></thead><tbody>';
    foreach ($rows as $row) { echo '<tr>'; foreach ($row as $cell) echo '<td>'.exportEscape($cell).'</td>'; echo '</tr>'; }
    if (!$rows) echo '<tr><td colspan="'.count($headers).'">Tidak ada data.</td></tr>';
    echo '</tbody></table></body></html>'; exit;
}

function pdfEscape($text) {
    $text = iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$text);
    return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$text);
}
$lines = [$judul,'Dicetak: '.date('d/m/Y H:i'),str_repeat('-',118),implode(' | ',$headers),str_repeat('-',118)];
foreach ($rows as $row) foreach (str_split(implode(' | ',array_map('strval',$row)),118) as $part) $lines[] = $part;
if (!$rows) $lines[] = 'Tidak ada data.';
$pages = array_chunk($lines,48); $objects = [1=>'<< /Type /Catalog /Pages 2 0 R >>']; $kids=[]; $next=4;
foreach ($pages as $pageLines) {
    $pageObj=$next++; $streamObj=$next++; $kids[]=$pageObj.' 0 R'; $commands="BT /F1 8 Tf 35 560 Td 11 TL\n";
    foreach ($pageLines as $line) $commands .= '('.pdfEscape($line).") Tj T*\n"; $commands.='ET';
    $objects[$pageObj]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamObj 0 R >>";
    $objects[$streamObj]="<< /Length ".strlen($commands)." >>\nstream\n$commands\nendstream";
}
$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>'; $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>'; ksort($objects);
$pdf="%PDF-1.4\n"; $offsets=[0]; foreach ($objects as $num=>$body) { $offsets[$num]=strlen($pdf); $pdf.="$num 0 obj\n$body\nendobj\n"; }
$xref=strlen($pdf); $max=max(array_keys($objects)); $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
for ($i=1;$i<=$max;$i++) $pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="'.$namaFile.'.pdf"'); header('Content-Length: '.strlen($pdf)); header('Cache-Control: no-store, no-cache, must-revalidate'); echo $pdf;
