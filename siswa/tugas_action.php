<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json');
check_access([3]); // Khusus Siswa

$db = Database::getInstance();

function is_duplicate_submission(Throwable $error): bool {
    return $error instanceof PDOException
        && isset($error->errorInfo[1])
        && (int)$error->errorInfo[1] === 1062;
}

function catat_log_pengumpulan_aman(int $user_id, int $tugas_id): void {
    try {
        catat_log($user_id, "Mengirimkan jawaban tugas ID: $tugas_id");
    } catch (Throwable $error) {
        // Kegagalan audit log tidak boleh mengubah pengumpulan yang sudah berhasil menjadi gagal.
        error_log('Log pengumpulan tugas gagal: ' . $error->getMessage());
    }
}

function kirim_error_pengumpulan(Throwable $error, string $konteks): void {
    error_log($konteks . ': ' . $error->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => is_duplicate_submission($error)
            ? 'Tugas ini sudah pernah dikumpulkan. Jawaban tidak dapat dikirim ulang.'
            : 'Jawaban gagal disimpan. Silakan coba kembali atau hubungi admin.'
    ]);
}

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
    $stmt_t = $db->prepare("SELECT t.deadline,t.jenis_tugas FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN akses_pertemuan ap ON ap.pengajaran_id=t.pengajaran_id AND ap.pertemuan_ke=t.pertemuan_ke AND ap.status='Dibuka' WHERE t.id=? AND p.kelas_id=? AND (NOT EXISTS(SELECT 1 FROM materi mat WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke) OR (EXISTS(SELECT 1 FROM materi mat JOIN materi_siswa_dibaca msb ON msb.materi_id=mat.id AND msb.siswa_id=? WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke) AND (NOT EXISTS(SELECT 1 FROM materi mat WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke AND mat.file_path IS NOT NULL) OR EXISTS(SELECT 1 FROM materi mat JOIN materi_siswa_diunduh msu ON msu.materi_id=mat.id AND msu.siswa_id=? WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke))))");
    $stmt_t->execute([$tugas_id, $kelas_id, $siswa_id, $siswa_id]);
    $tugas = $stmt_t->fetch();

    if (!$tugas) {
        echo json_encode(['status' => 'error', 'message' => 'Tugas tidak ditemukan!']);
        exit();
    }

    $stmt_metode = $db->prepare('SELECT metode_pengumpulan FROM tugas WHERE id=?');
    $stmt_metode->execute([$tugas_id]);
    if (($stmt_metode->fetchColumn() ?: 'online') !== 'online') {
        echo json_encode(['status' => 'error', 'message' => 'Tugas offline dinilai langsung oleh guru dan tidak dapat dikirim melalui LMS.']);
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

    $jenis = $tugas['jenis_tugas'] ?? 'portofolio';
    if (in_array($jenis, ['esai','pilihan_ganda'], true)) {
        $stmt_q = $db->prepare('SELECT pt.id,ot.id opsi_id,ot.is_benar FROM pertanyaan_tugas pt LEFT JOIN opsi_tugas ot ON ot.pertanyaan_id=pt.id WHERE pt.tugas_id=? ORDER BY pt.urutan,ot.label_opsi');
        $stmt_q->execute([$tugas_id]);
        $soal = [];
        foreach ($stmt_q->fetchAll() as $row) {
            $pid=(int)$row['id'];
            if (!isset($soal[$pid])) $soal[$pid]=[];
            if ($row['opsi_id']) $soal[$pid][(int)$row['opsi_id']] = (int)$row['is_benar'];
        }
        if (!$soal) { echo json_encode(['status'=>'error','message'=>'Pertanyaan tugas belum tersedia.']); exit; }
        $jawaban = $_POST['jawaban'] ?? [];
        foreach (array_keys($soal) as $pid) {
            if (!isset($jawaban[$pid]) || trim((string)$jawaban[$pid])==='') {
                echo json_encode(['status'=>'error','message'=>'Semua pertanyaan wajib dijawab.']); exit;
            }
        }
        try {
            $db->beginTransaction();
            $nilai_otomatis = null;
            if ($jenis === 'pilihan_ganda') {
                $benar=0;
                foreach ($soal as $pid=>$opsi) {
                    $dipilih=(int)$jawaban[$pid];
                    if (!array_key_exists($dipilih,$opsi)) throw new RuntimeException('Pilihan jawaban tidak valid.');
                    if ($opsi[$dipilih]===1) $benar++;
                }
                $nilai_otomatis=round($benar/count($soal)*100,2);
            }
            $db->prepare('INSERT INTO pengumpulan_tugas (tugas_id,siswa_id,file_tugas,catatan,nilai) VALUES (?,?,NULL,NULL,?)')->execute([$tugas_id,$siswa_id,$nilai_otomatis]);
            $pengumpulan_id=(int)$db->lastInsertId();
            $simpan=$db->prepare('INSERT INTO jawaban_tugas (pengumpulan_id,pertanyaan_id,opsi_id,jawaban_teks,benar) VALUES (?,?,?,?,?)');
            foreach ($soal as $pid=>$opsi) {
                if ($jenis==='pilihan_ganda') {
                    $oid=(int)$jawaban[$pid]; $simpan->execute([$pengumpulan_id,$pid,$oid,null,$opsi[$oid]]);
                } else {
                    $teks=trim((string)$jawaban[$pid]);
                    if (mb_strlen($teks)>20000) throw new RuntimeException('Jawaban esai terlalu panjang.');
                    $simpan->execute([$pengumpulan_id,$pid,null,$teks,null]);
                }
            }
            $db->commit();
            catat_log_pengumpulan_aman((int)$_SESSION['user_id'], $tugas_id);
            echo json_encode(['status'=>'success','message'=>$jenis==='pilihan_ganda'?'Jawaban berhasil dikirim dan dinilai otomatis.':'Jawaban esai berhasil dikirim.']);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            kirim_error_pengumpulan($e, 'Pengumpulan tugas teks gagal');
        }
        exit;
    }

    if ($jenis === 'merangkum' || $jenis === 'video') {
        $catatan = trim((string)($_POST[$jenis === 'video' ? 'tautan_video' : 'jawaban_ringkasan'] ?? ''));
        $skema_video = strtolower((string)parse_url($catatan, PHP_URL_SCHEME));
        if ($jenis === 'video' && (!filter_var($catatan, FILTER_VALIDATE_URL) || !in_array($skema_video, ['http','https'], true))) {
            echo json_encode(['status'=>'error','message'=>'Masukkan tautan video yang valid.']); exit;
        }
        if ($jenis === 'merangkum' && (mb_strlen($catatan)<10 || mb_strlen($catatan)>30000)) {
            echo json_encode(['status'=>'error','message'=>'Ringkasan wajib diisi (10-30.000 karakter).']); exit;
        }
        try {
            $db->prepare('INSERT INTO pengumpulan_tugas (tugas_id,siswa_id,file_tugas,catatan) VALUES (?,?,NULL,?)')->execute([$tugas_id,$siswa_id,$catatan]);
            catat_log_pengumpulan_aman((int)$_SESSION['user_id'], $tugas_id);
            echo json_encode(['status'=>'success','message'=>'Tugas berhasil dikumpulkan.']);
        } catch (Throwable $e) {
            kirim_error_pengumpulan($e, 'Pengumpulan tugas tautan/ringkasan gagal');
        }
        exit;
    }

    // Portofolio menggunakan unggahan berkas.
    $uploadKey=(isset($_FILES['foto_kamera'])&&$_FILES['foto_kamera']['error']===UPLOAD_ERR_OK)?'foto_kamera':'file_jawaban';
    if (!isset($_FILES[$uploadKey]) || $_FILES[$uploadKey]['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Wajib melampirkan file jawaban!']);
        exit();
    }

    $fileTmpPath = $_FILES[$uploadKey]['tmp_name'];
    $fileName    = $_FILES[$uploadKey]['name'];
    $fileSize    = $_FILES[$uploadKey]['size'];
    $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExts = ['pdf', 'docx', 'zip', 'rar', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'heif'];
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['status' => 'error', 'message' => 'Format file tidak diizinkan! (PDF/DOCX/ZIP/Gambar)']);
        exit();
    }
    if ($fileSize > 10 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Ukuran file jawaban maksimal 10 MB!']);
        exit();
    }
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($fileTmpPath)?:'';
    $allowedMimes=['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/x-zip-compressed','application/vnd.rar','application/x-rar-compressed','image/png','image/jpeg','image/webp','image/heic','image/heif','application/octet-stream'];
    if(!in_array($mime,$allowedMimes,true)){echo json_encode(['status'=>'error','message'=>'Isi file tidak sesuai dengan format yang diizinkan.']);exit;}

    $newFileName = 'jawaban_' . $tugas_id . '_' . $siswa_id . '_' . time() . '.' . $fileExt;
    $uploadDir   = __DIR__ . '/../assets/upload/jawaban/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
        try {
            $namaAsli=mb_substr(basename($fileName),0,255);
            $stmt_ins = $db->prepare("INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, file_tugas, nama_file_asli, ukuran_file, catatan) VALUES (?, ?, ?, ?, ?, NULL)");
            $stmt_ins->execute([$tugas_id, $siswa_id, $newFileName, $namaAsli, $fileSize]);
            catat_log_pengumpulan_aman((int)$_SESSION['user_id'], $tugas_id);
            echo json_encode(['status' => 'success', 'message' => 'Tugas berhasil dikumpulkan. Jawaban tidak dapat diubah atau dikirim ulang.']);
        } catch (Throwable $e) {
            @unlink($uploadDir . $newFileName);
            kirim_error_pengumpulan($e, 'Pengumpulan tugas berkas gagal');
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengunggah file jawaban!']);
    }
    exit();
}
