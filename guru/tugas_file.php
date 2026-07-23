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
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'zip' => 'application/zip',
    'rar' => 'application/vnd.rar',
];

if (!isset($mime_types[$ekstensi])) {
    http_response_code(415);
    exit('Format file tidak didukung.');
}

if ($mode === 'preview' && !in_array($ekstensi, ['pdf', 'png', 'jpg', 'jpeg'], true)) {
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
