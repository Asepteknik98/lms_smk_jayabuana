<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);

$pengumpulan_id = (int)($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'preview') === 'download' ? 'download' : 'preview';

$db = Database::getInstance();
$stmt = $db->prepare(
    'SELECT pt.file_tugas
     FROM pengumpulan_tugas pt
     JOIN tugas t ON t.id = pt.tugas_id
     JOIN pengajaran p ON p.id = t.pengajaran_id
     JOIN guru g ON g.id = p.guru_id
     WHERE pt.id = ? AND g.user_id = ?'
);
$stmt->execute([$pengumpulan_id, $_SESSION['user_id']]);
$nama_file = $stmt->fetchColumn();

if (!$nama_file) {
    http_response_code(404);
    exit('File jawaban tidak ditemukan.');
}

$folder_upload = realpath(__DIR__ . '/../assets/upload/jawaban');
$path_file = $folder_upload ? realpath($folder_upload . DIRECTORY_SEPARATOR . basename($nama_file)) : false;

if (!$folder_upload || !$path_file || !is_file($path_file) || strpos($path_file, $folder_upload . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('File jawaban tidak ditemukan.');
}

$ekstensi = strtolower(pathinfo($path_file, PATHINFO_EXTENSION));
$mime_types = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'zip' => 'application/zip',
    'rar' => 'application/vnd.rar',
];

if (!isset($mime_types[$ekstensi])) {
    http_response_code(415);
    exit('Format file tidak didukung.');
}

if ($mode === 'preview' && $ekstensi === 'docx') {
    /**
     * Mengambil satu berkas dari kontainer ZIP/DOCX tanpa bergantung pada
     * ekstensi ZipArchive, yang tidak selalu aktif pada instalasi XAMPP.
     */
    $ambil_entri_zip = static function (string $zip_path, string $nama_entri): ?string {
        $data = file_get_contents($zip_path);
        if ($data === false) return null;

        $pos_eocd = strrpos($data, "PK\x05\x06");
        if ($pos_eocd === false || strlen($data) < $pos_eocd + 22) return null;
        $eocd = unpack('vjumlah_disk/vjumlah_total/Vukuran_direktori/Voffset_direktori', substr($data, $pos_eocd + 8, 12));
        $pos = (int)$eocd['offset_direktori'];

        for ($i = 0; $i < (int)$eocd['jumlah_total']; $i++) {
            if (substr($data, $pos, 4) !== "PK\x01\x02") return null;
            $header = unpack('vmetode/x8/Vukuran_kompresi/Vukuran_asli/vpanjang_nama/vpanjang_extra/vpanjang_komentar/x8/Voffset_lokal', substr($data, $pos + 10, 36));
            $nama = substr($data, $pos + 46, (int)$header['panjang_nama']);

            if ($nama === $nama_entri) {
                if ((int)$header['ukuran_asli'] > 10 * 1024 * 1024) return null;
                $lokal = (int)$header['offset_lokal'];
                if (substr($data, $lokal, 4) !== "PK\x03\x04") return null;
                $panjang = unpack('vnama/vextra', substr($data, $lokal + 26, 4));
                $awal = $lokal + 30 + (int)$panjang['nama'] + (int)$panjang['extra'];
                $isi = substr($data, $awal, (int)$header['ukuran_kompresi']);
                if ((int)$header['metode'] === 0) return $isi;
                if ((int)$header['metode'] === 8) {
                    $hasil = @gzinflate($isi);
                    return $hasil === false ? null : $hasil;
                }
                return null;
            }
            $pos += 46 + (int)$header['panjang_nama'] + (int)$header['panjang_extra'] + (int)$header['panjang_komentar'];
        }
        return null;
    };

    $xml_dokumen = $ambil_entri_zip($path_file, 'word/document.xml');
    if ($xml_dokumen === null) {
        http_response_code(422);
        exit('Dokumen Word tidak dapat dibaca.');
    }

    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml_dokumen, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        http_response_code(422);
        exit('Isi dokumen Word tidak valid.');
    }
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $body = $xpath->query('//w:body')->item(0);

    $render_teks = static function (DOMNode $node) use ($xpath): string {
        $bagian = [];
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $node) as $item) {
            if ($item->localName === 'tab') $bagian[] = "\t";
            elseif ($item->localName === 'br') $bagian[] = "\n";
            else $bagian[] = $item->textContent;
        }
        return htmlspecialchars(implode('', $bagian), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $html = '';
    if ($body) {
        foreach ($body->childNodes as $node) {
            if ($node->localName === 'p') {
                $teks = $render_teks($node);
                $html .= $teks === '' ? '<p>&nbsp;</p>' : '<p>' . nl2br($teks) . '</p>';
            } elseif ($node->localName === 'tbl') {
                $html .= '<table>';
                foreach ($xpath->query('./w:tr', $node) as $baris) {
                    $html .= '<tr>';
                    foreach ($xpath->query('./w:tc', $baris) as $sel) $html .= '<td>' . nl2br($render_teks($sel)) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
        }
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    ?><!doctype html>
    <html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
    *{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#202124;font:16px/1.6 Arial,sans-serif}
    .notice{max-width:900px;margin:16px auto 0;padding:10px 14px;background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;color:#664d03;font-size:13px}
    .document{width:min(900px,calc(100% - 24px));min-height:calc(100vh - 80px);margin:12px auto 24px;padding:56px 68px;background:#fff;box-shadow:0 2px 12px #0002}
    p{margin:0 0 12px;white-space:pre-wrap;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse;margin:16px 0}td{border:1px solid #777;padding:7px;vertical-align:top}
    @media(max-width:600px){.document{padding:28px 22px}.notice{margin:10px 12px 0}}
    </style></head><body><div class="notice">Pratinjau Word menampilkan teks dan tabel untuk pemeriksaan cepat. Tata letak atau gambar tertentu mungkin disederhanakan.</div><main class="document"><?= $html ?: '<p>Dokumen tidak memiliki teks yang dapat ditampilkan.</p>' ?></main></body></html><?php
    exit;
}

if ($mode === 'preview' && !in_array($ekstensi, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)) {
    http_response_code(415);
    exit('Format file ini hanya dapat diunduh.');
}

$disposition = $mode === 'download' ? 'attachment' : 'inline';
$nama_aman = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($nama_file));

header('Content-Type: ' . $mime_types[$ekstensi]);
header('Content-Length: ' . filesize($path_file));
header('Content-Disposition: ' . $disposition . '; filename="' . $nama_aman . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($path_file);
exit;
