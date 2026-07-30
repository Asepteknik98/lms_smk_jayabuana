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

function salin_file_materi(string $nama_sumber): string
{
    $folder = __DIR__ . '/../assets/upload/materi/';
    $ext = strtolower(pathinfo($nama_sumber, PATHINFO_EXTENSION));
    $nama_baru = 'materi_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!copy($folder . basename($nama_sumber), $folder . $nama_baru)) {
        throw new RuntimeException('File materi gagal disalin untuk kelas lainnya.');
    }
    return $nama_baru;
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
    if ($action === 'ubah_status_akses') {
        $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
        $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 0);
        if ($pertemuan_ke < 1 || $pertemuan_ke > 20) {
            throw new RuntimeException('Nomor pertemuan harus berada antara 1–20.');
        }

        $stmt = $db->prepare('SELECT id FROM pengajaran WHERE id=? AND guru_id=?');
        $stmt->execute([$pengajaran_id, $guru_id]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Pengajaran tidak valid atau bukan milik Anda.');
        }

        $status = ($_POST['status'] ?? '') === 'Dibuka' ? 'Dibuka' : 'Dikunci';
        $dibuka_pada = $status === 'Dibuka' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare(
            "INSERT INTO akses_pertemuan (pengajaran_id,pertemuan_ke,status,dibuka_pada)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
                status=VALUES(status),
                dibuka_pada=VALUES(dibuka_pada)"
        );
        $stmt->execute([$pengajaran_id, $pertemuan_ke, $status, $dibuka_pada]);
        catat_log($_SESSION['user_id'], "$status akses pertemuan $pertemuan_ke pada pengajaran ID $pengajaran_id");
        respons_materi('success', "Pertemuan $pertemuan_ke berhasil " . ($status === 'Dibuka' ? 'dibuka untuk siswa.' : 'dikunci dari siswa.'));
    }

    if (in_array($action, ['create_materi', 'update_materi'], true)) {
        $materi_id = (int)($_POST['materi_id'] ?? 0);
        $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
        $pengajaran_ids = $action === 'create_materi' && is_array($_POST['pengajaran_ids'] ?? null)
            ? array_values(array_unique(array_filter(
                array_map('intval', $_POST['pengajaran_ids']),
                static fn(int $id): bool => $id > 0
            )))
            : array_filter([$pengajaran_id]);
        $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $video_url = validasi_video_youtube(trim($_POST['video_url'] ?? ''));
        if ($pertemuan_ke < 1 || $pertemuan_ke > 20 || $judul === '' || mb_strlen($judul) > 200) {
            throw new RuntimeException('Pertemuan 1–20 dan judul maksimal 200 karakter wajib diisi.');
        }
        if (!$pengajaran_ids) throw new RuntimeException('Pilih minimal satu kelas yang akan menerima materi.');
        $placeholder = implode(',', array_fill(0, count($pengajaran_ids), '?'));
        $stmt = $db->prepare("SELECT id,mapel_id,tahun_ajaran,semester FROM pengajaran WHERE id IN ($placeholder) AND guru_id=?");
        $stmt->execute([...$pengajaran_ids, $guru_id]);
        $pengajaran_valid = $stmt->fetchAll();
        if (count($pengajaran_valid) !== count($pengajaran_ids)) {
            throw new RuntimeException('Salah satu pengajaran tidak valid atau bukan milik Anda.');
        }
        $kelompok_valid = array_unique(array_map(
            static fn(array $item): string => $item['mapel_id'] . '|' . $item['tahun_ajaran'] . '|' . $item['semester'],
            $pengajaran_valid
        ));
        if (count($kelompok_valid) !== 1) {
            throw new RuntimeException('Kelas gabungan harus berasal dari mapel, semester, dan tahun ajaran yang sama.');
        }

        $file_baru = simpan_file_materi($_FILES['file_materi'] ?? []);
        if ($action === 'create_materi') {
            if (!$file_baru) {
                throw new RuntimeException('File modul wajib diunggah agar siswa dapat mengunduh materi sebelum membuka tugas.');
            }
            $file_dibuat = [$file_baru];
            try {
                $db->beginTransaction();
                $stmt = $db->prepare('INSERT INTO materi (pengajaran_id,pertemuan_ke,judul,deskripsi,video_url,file_path) VALUES (?,?,?,?,?,?)');
                foreach ($pengajaran_ids as $index => $id_pengajaran) {
                    $file_kelas = $index === 0 ? $file_baru : salin_file_materi($file_baru);
                    if ($index > 0) $file_dibuat[] = $file_kelas;
                    $stmt->execute([$id_pengajaran, $pertemuan_ke, $judul, $deskripsi ?: null, $video_url, $file_kelas]);
                }
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                foreach (array_unique($file_dibuat) as $file) {
                    @unlink(__DIR__ . '/../assets/upload/materi/' . basename($file));
                }
                throw $e;
            }
            $jumlah_kelas = count($pengajaran_ids);
            catat_log($_SESSION['user_id'], "Membuat modul pertemuan $pertemuan_ke untuk $jumlah_kelas kelas: $judul");
            respons_materi('success', "Materi berhasil dipublikasikan ke $jumlah_kelas kelas.");
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
