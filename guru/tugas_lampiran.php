<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
check_access([2]);

$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance();
$stmt = $db->prepare(
    'SELECT t.file_lampiran FROM tugas t
     JOIN pengajaran p ON p.id=t.pengajaran_id
     JOIN guru g ON g.id=p.guru_id
     WHERE t.id=? AND g.user_id=?'
);
$stmt->execute([$id, $_SESSION['user_id']]);
$file = $stmt->fetchColumn();
if (!$file) { http_response_code(404); exit('Lampiran tugas tidak ditemukan.'); }

$folder = realpath(__DIR__ . '/../assets/upload/tugas');
$path = $folder ? realpath($folder . DIRECTORY_SEPARATOR . basename($file)) : false;
if (!$folder || !$path || !is_file($path) || strpos($path, $folder . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404); exit('Lampiran tugas tidak ditemukan.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$nama = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file));
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $nama . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
