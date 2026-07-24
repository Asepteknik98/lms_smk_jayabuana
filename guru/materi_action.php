<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json; charset=UTF-8');
check_access([2]);

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

function respons_materi(string $status, string $message): void
{
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function simpan_file_materi(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('File gagal diunggah atau melebihi 10 MB.');
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
        throw new RuntimeException('Format file harus PDF, DOCX, PPTX, atau ZIP.');
    }
    $folder = __DIR__ . '/../assets/upload/materi/';
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Folder penyimpanan materi tidak tersedia.');
    }
    $nama = 'materi_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $folder . $nama)) {
        throw new RuntimeException('File materi gagal disimpan.');
    }
    return $nama;
}

function validasi_video_youtube(string $url): ?string
{
    if ($url === '') return null;
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Link video YouTube tidak valid.');
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host);
    if (!in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
        throw new RuntimeException('Link video harus berasal dari YouTube.');
    }
    return mb_substr($url, 0, 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respons_materi('error', 'Metode permintaan tidak diizinkan.');
}
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    respons_materi('error', 'Sesi keamanan berakhir. Silakan muat ulang halaman.');
}

$stmt = $db->prepare('SELECT id FROM guru WHERE user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt->fetchColumn() ?: 0);

try {
    if (in_array($action, ['create_materi', 'update_materi'], true)) {
        $materi_id = (int)($_POST['materi_id'] ?? 0);
        $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
        $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $video_url = validasi_video_youtube(trim($_POST['video_url'] ?? ''));
        if ($pertemuan_ke < 1 || $pertemuan_ke > 20 || $judul === '' || mb_strlen($judul) > 200) {
            throw new RuntimeException('Pertemuan 1–20 dan judul maksimal 200 karakter wajib diisi.');
        }
        $stmt = $db->prepare('SELECT id FROM pengajaran WHERE id=? AND guru_id=?');
        $stmt->execute([$pengajaran_id, $guru_id]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Pengajaran tidak valid atau bukan milik Anda.');

        $file_baru = simpan_file_materi($_FILES['file_materi'] ?? []);
        if ($action === 'create_materi') {
            try {
                $stmt = $db->prepare('INSERT INTO materi (pengajaran_id,pertemuan_ke,judul,deskripsi,video_url,file_path) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$pengajaran_id, $pertemuan_ke, $judul, $deskripsi ?: null, $video_url, $file_baru]);
            } catch (Throwable $e) {
                if ($file_baru) @unlink(__DIR__ . '/../assets/upload/materi/' . $file_baru);
                throw $e;
            }
            catat_log($_SESSION['user_id'], "Membuat modul pertemuan $pertemuan_ke: $judul");
            respons_materi('success', 'Materi berhasil dipublikasikan.');
        }

        $stmt = $db->prepare('SELECT mat.file_path FROM materi mat JOIN pengajaran p ON p.id=mat.pengajaran_id WHERE mat.id=? AND p.guru_id=?');
        $stmt->execute([$materi_id, $guru_id]);
        $lama = $stmt->fetch();
        if (!$lama) {
            if ($file_baru) @unlink(__DIR__ . '/../assets/upload/materi/' . $file_baru);
            throw new RuntimeException('Materi tidak ditemukan atau bukan milik Anda.');
        }
        try {
            $file_simpan = $file_baru ?: $lama['file_path'];
            $stmt = $db->prepare('UPDATE materi SET pengajaran_id=?,pertemuan_ke=?,judul=?,deskripsi=?,video_url=?,file_path=? WHERE id=?');
            $stmt->execute([$pengajaran_id, $pertemuan_ke, $judul, $deskripsi ?: null, $video_url, $file_simpan, $materi_id]);
        } catch (Throwable $e) {
            if ($file_baru) @unlink(__DIR__ . '/../assets/upload/materi/' . $file_baru);
            throw $e;
        }
        if ($file_baru && $lama['file_path']) @unlink(__DIR__ . '/../assets/upload/materi/' . basename($lama['file_path']));
        catat_log($_SESSION['user_id'], "Mengubah materi: $judul");
        respons_materi('success', 'Materi berhasil diperbarui.');
    }

    if ($action === 'delete_materi') {
        $materi_id = (int)($_POST['materi_id'] ?? 0);
        $stmt = $db->prepare('SELECT mat.judul,mat.file_path FROM materi mat JOIN pengajaran p ON p.id=mat.pengajaran_id WHERE mat.id=? AND p.guru_id=?');
        $stmt->execute([$materi_id, $guru_id]);
        $materi = $stmt->fetch();
        if (!$materi) throw new RuntimeException('Materi tidak ditemukan atau bukan milik Anda.');
        $db->prepare('DELETE FROM materi WHERE id=?')->execute([$materi_id]);
        if ($materi['file_path']) @unlink(__DIR__ . '/../assets/upload/materi/' . basename($materi['file_path']));
        catat_log($_SESSION['user_id'], 'Menghapus materi: ' . $materi['judul']);
        respons_materi('success', 'Materi berhasil dihapus.');
    }

    http_response_code(400);
    respons_materi('error', 'Aksi tidak dikenali.');
} catch (Throwable $e) {
    if (!($e instanceof RuntimeException) && $e->getCode() !== '23000') error_log('Aksi materi gagal: ' . $e->getMessage());
    http_response_code(422);
    respons_materi('error', $e->getCode() === '23000'
        ? 'Materi untuk pertemuan tersebut sudah tersedia.'
        : ($e instanceof RuntimeException ? $e->getMessage() : 'Materi gagal diproses.'));
}
