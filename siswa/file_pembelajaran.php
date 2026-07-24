<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
check_access([3]);

$jenis = $_GET['jenis'] ?? '';
$mode = ($_GET['mode']??'download')==='preview'?'preview':'download';
$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance();
$stmt = $db->prepare('SELECT id,kelas_id FROM siswa WHERE user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$siswa = $stmt->fetch();
if (!$siswa) { http_response_code(403); exit('Data siswa tidak ditemukan.'); }

if ($jenis === 'materi') {
    $stmt = $db->prepare("SELECT mat.file_path FROM materi mat JOIN pengajaran p ON p.id=mat.pengajaran_id JOIN akses_pertemuan ap ON ap.pengajaran_id=mat.pengajaran_id AND ap.pertemuan_ke=mat.pertemuan_ke AND ap.status='Dibuka' WHERE mat.id=? AND p.kelas_id=?");
    $stmt->execute([$id, $siswa['kelas_id']]);
    $folder_nama = 'materi';
} elseif ($jenis === 'tugas') {
    $stmt = $db->prepare("SELECT t.file_lampiran FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN akses_pertemuan ap ON ap.pengajaran_id=t.pengajaran_id AND ap.pertemuan_ke=t.pertemuan_ke AND ap.status='Dibuka' WHERE t.id=? AND p.kelas_id=? AND (NOT EXISTS(SELECT 1 FROM materi mat WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke) OR (EXISTS(SELECT 1 FROM materi mat JOIN materi_siswa_dibaca msb ON msb.materi_id=mat.id AND msb.siswa_id=? WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke) AND (NOT EXISTS(SELECT 1 FROM materi mat WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke AND mat.file_path IS NOT NULL) OR EXISTS(SELECT 1 FROM materi mat JOIN materi_siswa_diunduh msu ON msu.materi_id=mat.id AND msu.siswa_id=? WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke))))");
    $stmt->execute([$id, $siswa['kelas_id'], $siswa['id'], $siswa['id']]);
    $folder_nama = 'tugas';
} elseif ($jenis === 'jawaban') {
    $stmt = $db->prepare('SELECT file_tugas FROM pengumpulan_tugas WHERE id=? AND siswa_id=?');
    $stmt->execute([$id, $siswa['id']]);
    $folder_nama = 'jawaban';
} else {
    http_response_code(400); exit('Jenis file tidak valid.');
}
$file = $stmt->fetchColumn();
if (!$file) { http_response_code(404); exit('File tidak ditemukan.'); }

if ($jenis === 'materi') {
    if ($mode === 'download') {
        $db->prepare(
            'INSERT INTO materi_siswa_diunduh (materi_id,siswa_id,diunduh_pada)
             VALUES (?,?,NOW())
             ON DUPLICATE KEY UPDATE diunduh_pada=VALUES(diunduh_pada)'
        )->execute([$id, (int)$siswa['id']]);
    }
}

$folder = realpath(__DIR__ . '/../assets/upload/' . $folder_nama);
$path = $folder ? realpath($folder . DIRECTORY_SEPARATOR . basename($file)) : false;
if (!$folder || !$path || !is_file($path) || strpos($path, $folder . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404); exit('File tidak ditemukan.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$nama = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file));
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
$inline=in_array($mime,['application/pdf','image/png','image/jpeg','image/webp'],true)&&$mode==='preview';
header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="' . $nama . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
