<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json');
check_access([2]); // Hanya Guru

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

// Ambil ID Guru berdasarkan Session User ID
$stmt_g = $db->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt_g->execute([$_SESSION['user_id']]);
$guru = $stmt_g->fetch();

if (!$guru) {
    echo json_encode(['status' => 'error', 'message' => 'Data Guru tidak ditemukan!']);
    exit();
}
$guru_id = $guru['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF Token']);
        exit();
    }

    // ------------------- SIMPAN MATERI BARU -------------------
    if ($action === 'create_materi') {
        $pengajaran_id = intval($_POST['pengajaran_id'] ?? 0);
        $pertemuan_ke  = intval($_POST['pertemuan_ke'] ?? 0);
        $judul         = sanitize($_POST['judul'] ?? '');
        $deskripsi     = sanitize($_POST['deskripsi'] ?? '');
        $file_path     = null;

        if ($pengajaran_id <= 0 || $pertemuan_ke < 1 || $pertemuan_ke > 20 || empty($judul)) {
            echo json_encode(['status' => 'error', 'message' => 'Pengajaran, pertemuan 1-20, dan judul wajib diisi!']);
            exit();
        }

        // Pastikan pengajaran benar-benar milik Guru yang sedang login.
        $stmt_pengajaran = $db->prepare("SELECT id FROM pengajaran WHERE id = ? AND guru_id = ?");
        $stmt_pengajaran->execute([$pengajaran_id, $guru_id]);
        if (!$stmt_pengajaran->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Pengajaran tidak valid atau bukan milik Anda.']);
            exit();
        }

        $stmt_duplikat = $db->prepare("SELECT id FROM materi WHERE pengajaran_id = ? AND pertemuan_ke = ?");
        $stmt_duplikat->execute([$pengajaran_id, $pertemuan_ke]);
        if ($stmt_duplikat->fetch()) {
            echo json_encode(['status' => 'error', 'message' => "Modul pertemuan ke-$pertemuan_ke sudah tersedia."]);
            exit();
        }

        // Upload File Materi (PDF/DOCX/ZIP) jika ada
        if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['file_materi']['tmp_name'];
            $fileName    = $_FILES['file_materi']['name'];
            $fileSize    = $_FILES['file_materi']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedMimeTypes = [
                'pdf' => ['application/pdf'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
                'zip' => ['application/zip', 'application/x-zip-compressed'],
            ];
            $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($fileTmpPath);
            if (!isset($allowedMimeTypes[$fileExtension]) || !in_array($detectedMime, $allowedMimeTypes[$fileExtension], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Format file tidak diizinkan! (Hanya PDF, DOCX, PPTX, ZIP)']);
                exit();
            }

            if ($fileSize > 10 * 1024 * 1024) { // Max 10MB
                echo json_encode(['status' => 'error', 'message' => 'Ukuran file maksimal 10MB!']);
                exit();
            }

            $newFileName = 'materi_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../assets/upload/materi/';

            if(!is_dir($uploadFileDir)){
                mkdir($uploadFileDir, 0755, true);
            }

            $dest_path = $uploadFileDir . $newFileName;
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $file_path = $newFileName;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal mengunggah file materi!']);
                exit();
            }
        }

        try {
            $stmt = $db->prepare("INSERT INTO materi (pengajaran_id, pertemuan_ke, judul, deskripsi, file_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$pengajaran_id, $pertemuan_ke, $judul, $deskripsi, $file_path]);
            catat_log($_SESSION['user_id'], "Membuat modul pertemuan $pertemuan_ke: $judul");
            echo json_encode(['status' => 'success', 'message' => "Modul pertemuan ke-$pertemuan_ke berhasil dipublikasikan!"]);
        } catch (Throwable $e) {
            if ($file_path && is_file($uploadFileDir . $file_path)) {
                unlink($uploadFileDir . $file_path);
            }
            error_log('Gagal menyimpan modul: ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan modul. Silakan coba kembali.']);
        }
        exit();
    }

    // ------------------- SIMPAN TUGAS BARU -------------------
    if ($action === 'create_tugas') {
        $pengajaran_id = intval($_POST['pengajaran_id'] ?? 0);
        $judul         = sanitize($_POST['judul'] ?? '');
        $deskripsi     = sanitize($_POST['deskripsi'] ?? '');
        $deadline      = $_POST['deadline'] ?? '';
        $file_lampiran = null;

        if ($pengajaran_id <= 0 || empty($judul) || empty($deadline)) {
            echo json_encode(['status' => 'error', 'message' => 'Judul, Pengajaran, dan Deadline wajib diisi!']);
            exit();
        }

        if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['file_tugas']['tmp_name'];
            $fileName    = $_FILES['file_tugas']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['pdf', 'docx', 'zip', 'rar'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                echo json_encode(['status' => 'error', 'message' => 'Format file tugas tidak valid!']);
                exit();
            }

            $newFileName = 'tugas_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../assets/upload/tugas/';

            if(!is_dir($uploadFileDir)){
                mkdir($uploadFileDir, 0755, true);
            }

            if(move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                $file_lampiran = $newFileName;
            }
        }

        $stmt = $db->prepare("INSERT INTO tugas (pengajaran_id, judul, deskripsi, deadline, file_lampiran) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$pengajaran_id, $judul, $deskripsi, $deadline, $file_lampiran])) {
            catat_log($_SESSION['user_id'], "Membuat tugas baru: $judul");
            echo json_encode(['status' => 'success', 'message' => 'Tugas berhasil dibuat!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal membuat tugas!']);
        }
        exit();
    }

    // ------------------- PENILAIAN TUGAS SISWA -------------------
    if ($action === 'nilai_tugas') {
        $pengumpulan_id = intval($_POST['pengumpulan_id'] ?? 0);
        $nilai          = floatval($_POST['nilai'] ?? 0);

        if ($pengumpulan_id <= 0 || $nilai < 0 || $nilai > 100) {
            echo json_encode(['status' => 'error', 'message' => 'Nilai harus di antara 0 - 100!']);
            exit();
        }

        $stmt = $db->prepare("UPDATE pengumpulan_tugas SET nilai = ? WHERE id = ?");
        if ($stmt->execute([$nilai, $pengumpulan_id])) {
            catat_log($_SESSION['user_id'], "Memberikan nilai $nilai pada pengumpulan tugas ID: $pengumpulan_id");
            echo json_encode(['status' => 'success', 'message' => 'Nilai berhasil disimpan!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan nilai!']);
        }
        exit();
    }
}
