<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

check_access([2]);

$db = Database::getInstance();
$action = $_GET['action'] ?? '';
$pengajaran_id = (int)($_GET['pengajaran_id'] ?? 0);

if (!in_array($action, ['export_excel', 'export_pdf'], true) || $pengajaran_id < 1) {
    http_response_code(400);
    exit('Permintaan ekspor tidak valid.');
}

$stmt_guru = $db->prepare('SELECT id FROM guru WHERE user_id = ?');
$stmt_guru->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt_guru->fetchColumn() ?: 0);

$stmt_info = $db->prepare(
    'SELECT m.nama_mapel, k.nama_kelas, g.nama_lengkap AS nama_guru,
            p.tahun_ajaran, p.semester
     FROM pengajaran p
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     JOIN guru g ON g.id = p.guru_id
     WHERE p.id = ? AND p.guru_id = ?'
);
$stmt_info->execute([$pengajaran_id, $guru_id]);
$info = $stmt_info->fetch();

if (!$info) {
    http_response_code(403);
    exit('Pengajaran tidak ditemukan atau bukan milik Anda.');
}

$stmt_rekap = $db->prepare(
    <<<'SQL'
     SELECT s.nisn, s.nama_lengkap,
            COALESCE((SELECT AVG(pt.nilai)
                      FROM pengumpulan_tugas pt
                      JOIN tugas t ON t.id = pt.tugas_id
                      WHERE t.pengajaran_id = p.id AND pt.siswa_id = s.id), 0) AS avg_tugas,
            COALESCE((SELECT AVG(nu.nilai_total)
                      FROM nilai_ujian nu
                      JOIN ujian u ON u.id = nu.ujian_id
                      WHERE u.pengajaran_id = p.id AND nu.siswa_id = s.id), 0) AS avg_ujian,
            COALESCE((SELECT AVG(CASE da.status
                                    WHEN 'Hadir' THEN 100
                                    WHEN 'Terlambat' THEN 100
                                    WHEN 'Sakit' THEN 100
                                    WHEN 'Izin' THEN 100
                                    ELSE 0
                                END)
                      FROM detail_absensi da
                      JOIN sesi_absensi sa ON sa.id = da.sesi_absensi_id
                      WHERE sa.pengajaran_id = p.id
                        AND sa.status = 'Ditutup'
                        AND da.siswa_id = s.id), 0) AS avg_absensi
     FROM siswa s
     JOIN pengajaran p ON p.kelas_id = s.kelas_id
     WHERE p.id = ? AND p.guru_id = ?
     ORDER BY s.nama_lengkap
SQL
);
$stmt_rekap->execute([$pengajaran_id, $guru_id]);
$rekap_data = $stmt_rekap->fetchAll();

foreach ($rekap_data as &$nilai) {
    $nilai['nilai_akhir'] = ((float)$nilai['avg_tugas'] * 0.20)
        + ((float)$nilai['avg_ujian'] * 0.15)
        + ((float)$nilai['avg_absensi'] * 0.40);
}
unset($nilai);

$safe_name = static function (string $value): string {
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value);
    return trim($value, '_') ?: 'Data';
};
$base_filename = 'Rekap_Nilai_' . $safe_name($info['nama_kelas']) . '_' . $safe_name($info['nama_mapel']);

if ($action === 'export_excel') {
    $xml = static function ($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $base_filename . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
  <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>
  <Style ss:ID="Number"><NumberFormat ss:Format="0.00"/></Style>
 </Styles>
 <Worksheet ss:Name="Rekap Nilai"><Table>
  <Column ss:Width="35"/><Column ss:Width="90"/><Column ss:Width="170"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="90"/><Column ss:Width="105"/>
  <Row><Cell ss:MergeAcross="6" ss:StyleID="Title"><Data ss:Type="String">REKAPITULASI NILAI SISWA - SMK JAYA BUANA</Data></Cell></Row>
  <Row><Cell ss:MergeAcross="6"><Data ss:Type="String">Mata Pelajaran: <?= $xml($info['nama_mapel']) ?></Data></Cell></Row>
  <Row><Cell ss:MergeAcross="6"><Data ss:Type="String">Kelas: <?= $xml($info['nama_kelas']) ?> | Guru: <?= $xml($info['nama_guru']) ?></Data></Cell></Row>
  <Row><Cell ss:MergeAcross="6"><Data ss:Type="String">Semester <?= $xml($info['semester']) ?> | Tahun Ajaran <?= $xml($info['tahun_ajaran']) ?></Data></Cell></Row>
  <Row/>
  <Row><?php foreach (['No','NISN','Nama Siswa','Rata-rata Tugas (20%)','Rata-rata Ujian (15%)','Absensi (40%)','Nilai LMS (Maks. 75)'] as $header): ?><Cell ss:StyleID="Header"><Data ss:Type="String"><?= $xml($header) ?></Data></Cell><?php endforeach; ?></Row>
  <?php foreach ($rekap_data as $index => $row): ?>
  <Row>
   <Cell><Data ss:Type="Number"><?= $index + 1 ?></Data></Cell>
   <Cell><Data ss:Type="String"><?= $xml($row['nisn']) ?></Data></Cell>
   <Cell><Data ss:Type="String"><?= $xml($row['nama_lengkap']) ?></Data></Cell>
   <Cell ss:StyleID="Number"><Data ss:Type="Number"><?= number_format((float)$row['avg_tugas'], 2, '.', '') ?></Data></Cell>
   <Cell ss:StyleID="Number"><Data ss:Type="Number"><?= number_format((float)$row['avg_ujian'], 2, '.', '') ?></Data></Cell>
   <Cell ss:StyleID="Number"><Data ss:Type="Number"><?= number_format((float)$row['avg_absensi'], 2, '.', '') ?></Data></Cell>
   <Cell ss:StyleID="Number"><Data ss:Type="Number"><?= number_format((float)$row['nilai_akhir'], 2, '.', '') ?></Data></Cell>
  </Row>
  <?php endforeach; ?>
 </Table></Worksheet>
</Workbook><?php
    exit;
}

function pdf_safe_text(string $text): string
{
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function build_simple_pdf(array $pages): string
{
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $kids = [];
    $object_id = 4;

    foreach ($pages as $lines) {
        $page_id = $object_id++;
        $content_id = $object_id++;
        $kids[] = "$page_id 0 R";
        $commands = "BT\n/F1 9 Tf\n";
        foreach ($lines as $line) {
            $commands .= sprintf("1 0 0 1 %d %d Tm (%s) Tj\n", $line[0], $line[1], pdf_safe_text($line[2]));
        }
        $commands .= "ET";
        $objects[$page_id] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $content_id 0 R >>";
        $objects[$content_id] = "<< /Length " . strlen($commands) . " >>\nstream\n$commands\nendstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$object\nendobj\n";
    }
    $xref = strlen($pdf);
    $max_id = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max_id + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $max_id; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= "trailer\n<< /Size " . ($max_id + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}

$pages = [];
$lines = [];
$new_page = static function () use (&$pages, &$lines, $info): void {
    if ($lines) {
        $pages[] = $lines;
    }
    $lines = [
        [40, 560, 'REKAPITULASI NILAI SISWA - SMK JAYA BUANA'],
        [40, 542, 'Mata Pelajaran: ' . $info['nama_mapel'] . ' | Kelas: ' . $info['nama_kelas']],
        [40, 526, 'Guru: ' . $info['nama_guru'] . ' | Semester ' . $info['semester'] . ' | ' . $info['tahun_ajaran']],
        [40, 500, sprintf('%-4s %-16s %-28s %10s %10s %10s %12s', 'No', 'NISN', 'Nama Siswa', 'Tugas 20%', 'Ujian 15%', 'Absen 40%', 'LMS /75')],
        [40, 488, str_repeat('-', 105)],
    ];
};
$new_page();
$y = 470;
foreach ($rekap_data as $index => $row) {
    if ($y < 45) {
        $new_page();
        $y = 470;
    }
    $nama = function_exists('mb_strimwidth')
        ? mb_strimwidth($row['nama_lengkap'], 0, 30, '..', 'UTF-8')
        : substr($row['nama_lengkap'], 0, 30);
    $lines[] = [40, $y, sprintf('%-4d %-16s %-28s %10.2f %10.2f %10.2f %12.2f', $index + 1, $row['nisn'], $nama, $row['avg_tugas'], $row['avg_ujian'], $row['avg_absensi'], $row['nilai_akhir'])];
    $y -= 16;
}
if (!$rekap_data) {
    $lines[] = [40, 470, 'Belum ada data siswa pada pengajaran ini.'];
}
$pages[] = $lines;
$pdf = build_simple_pdf($pages);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $base_filename . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $pdf;
exit;
