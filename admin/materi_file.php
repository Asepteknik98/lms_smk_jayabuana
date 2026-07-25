<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

check_access([1]);
$id = (int)($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'download') === 'preview' ? 'preview' : 'download';

$db = Database::getInstance();
$stmt = $db->prepare('SELECT file_path FROM materi WHERE id=?');
$stmt->execute([$id]);
$file = $stmt->fetchColumn();
if (!$file) {
    http_response_code(404);
    exit('File materi tidak ditemukan.');
}

$folder = realpath(__DIR__ . '/../assets/upload/materi');
$path = $folder ? realpath($folder . DIRECTORY_SEPARATOR . basename($file)) : false;
if (!$folder || !$path || !is_file($path) || strpos($path, $folder . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('File materi tidak ditemukan.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$nama = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file));
$inline = $mode === 'preview' && in_array($mime, [
    'application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'text/plain',
], true);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nama . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
