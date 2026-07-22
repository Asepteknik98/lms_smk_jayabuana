<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json');
check_access([3]); // Khusus Siswa

$db = Database::getInstance();

// Dapatkan ID Siswa
$stmt_s = $db->prepare("SELECT id, kelas_id FROM siswa WHERE user_id = ?");
$stmt_s->execute([$_SESSION['user_id']]);
$siswa = $stmt_s->fetch();

if (!$siswa) {
    echo json_encode(['status' => 'error', 'message' => 'Data Siswa tidak terdaftar!']);
    exit();
}
$siswa_id = $siswa['id'];
$kelas_id = (int)$siswa['kelas_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF Token Invalid']);
        exit();
    }

    $tugas_id = intval($_POST['tugas_id'] ?? 0);

    // Cek apakah deadline tugas sudah lewat
    $stmt_t = $db->prepare("SELECT t.deadline FROM tugas t JOIN pengajaran p ON p.id = t.pengajaran_id WHERE t.id = ? AND p.kelas_id = ?");
    $stmt_t->execute([$tugas_id, $kelas_id]);
    $tugas = $stmt_t->fetch();

    if (!$tugas) {
        echo json_encode(['status' => 'error', 'message' => 'Tugas tidak ditemukan!']);
        exit();
    }

    if (strtotime(date('Y-m-d H:i:s')) > strtotime($tugas['deadline'])) {
        echo json_encode(['status' => 'error', 'message' => 'Batas waktu (deadline) pengumpulan tugas telah lewat!']);
        exit();
    }

    // Satu siswa hanya boleh mengumpulkan satu kali untuk setiap tugas.
    $stmt_sudah_kumpul = $db->prepare('SELECT id FROM pengumpulan_tugas WHERE tugas_id = ? AND siswa_id = ?');
    $stmt_sudah_kumpul->execute([$tugas_id, $siswa_id]);
    if ($stmt_sudah_kumpul->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Tugas ini sudah pernah dikumpulkan. Jawaban tidak dapat dikirim ulang.']);
        exit();
    }

    // Upload Berkas Tugas
    if (!isset($_FILES['file_jawaban']) || $_FILES['file_jawaban']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Wajib melampirkan file jawaban!']);
        exit();
    }

    $fileTmpPath = $_FILES['file_jawaban']['tmp_name'];
    $fileName    = $_FILES['file_jawaban']['name'];
    $fileSize    = $_FILES['file_jawaban']['size'];
    $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExts = ['pdf', 'docx', 'zip', 'rar', 'png', 'jpg'];
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['status' => 'error', 'message' => 'Format file tidak diizinkan! (PDF/DOCX/ZIP/Gambar)']);
        exit();
    }
    if ($fileSize > 10 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Ukuran file jawaban maksimal 10 MB!']);
        exit();
    }

    $newFileName = 'jawaban_' . $tugas_id . '_' . $siswa_id . '_' . time() . '.' . $fileExt;
    $uploadDir   = __DIR__ . '/../assets/upload/jawaban/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
        try {
            $stmt_ins = $db->prepare("INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, file_tugas, catatan) VALUES (?, ?, ?, NULL)");
            $stmt_ins->execute([$tugas_id, $siswa_id, $newFileName]);
            catat_log($_SESSION['user_id'], "Mengirimkan jawaban tugas ID: $tugas_id");
            echo json_encode(['status' => 'success', 'message' => 'Tugas berhasil dikumpulkan. Jawaban tidak dapat diubah atau dikirim ulang.']);
        } catch (PDOException $e) {
            @unlink($uploadDir . $newFileName);
            error_log('Pengumpulan tugas gagal: ' . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => $e->getCode() === '23000'
                    ? 'Tugas ini sudah pernah dikumpulkan. Jawaban tidak dapat dikirim ulang.'
                    : 'Jawaban gagal disimpan. Silakan coba kembali.'
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengunggah file jawaban!']);
    }
    exit();
}
