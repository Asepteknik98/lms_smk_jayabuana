<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json');
check_access([2]); // Khusus Guru

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF Token Invalid']);
        exit();
    }

    // ------------------- BUAT UJIAN BARU -------------------
    if ($action === 'create_ujian') {
        $pengajaran_id = intval($_POST['pengajaran_id'] ?? 0);
        $nama_ujian    = sanitize($_POST['nama_ujian'] ?? '');
        $jenis_ujian   = sanitize($_POST['jenis_ujian'] ?? 'Kuis');
        $durasi_menit  = intval($_POST['durasi_menit'] ?? 60);
        $waktu_mulai   = $_POST['waktu_mulai'] ?? '';
        $waktu_selesai = $_POST['waktu_selesai'] ?? '';
        $acak_soal     = isset($_POST['acak_soal']) ? 1 : 0;

        if ($pengajaran_id <= 0 || empty($nama_ujian) || empty($waktu_mulai) || empty($waktu_selesai)) {
            echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi!']);
            exit();
        }

        $stmt = $db->prepare("INSERT INTO ujian (pengajaran_id, nama_ujian, jenis_ujian, durasi_menit, waktu_mulai, waktu_selesai, acak_soal) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$pengajaran_id, $nama_ujian, $jenis_ujian, $durasi_menit, $waktu_mulai, $waktu_selesai, $acak_soal])) {
            catat_log($_SESSION['user_id'], "Membuat ujian baru: $nama_ujian");
            echo json_encode(['status' => 'success', 'message' => 'Ujian berhasil dibuat!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal membuat ujian']);
        }
        exit();
    }

    // ------------------- TAMBAH SOAL (PG / ESAI) -------------------
    if ($action === 'add_soal') {
        $ujian_id      = intval($_POST['ujian_id'] ?? 0);
        $tipe_soal     = sanitize($_POST['tipe_soal'] ?? 'PG');
        $pertanyaan    = sanitize($_POST['pertanyaan'] ?? '');
        $bobot         = intval($_POST['bobot'] ?? 1);

        if ($ujian_id <= 0 || empty($pertanyaan)) {
            echo json_encode(['status' => 'error', 'message' => 'Ujian ID dan Pertanyaan wajib diisi!']);
            exit();
        }

        if ($tipe_soal === 'PG') {
            $opsi_a = sanitize($_POST['opsi_a'] ?? '');
            $opsi_b = sanitize($_POST['opsi_b'] ?? '');
            $opsi_c = sanitize($_POST['opsi_c'] ?? '');
            $opsi_d = sanitize($_POST['opsi_d'] ?? '');
            $opsi_e = sanitize($_POST['opsi_e'] ?? '');
            $kunci  = sanitize($_POST['kunci_jawaban'] ?? '');

            $stmt = $db->prepare("INSERT INTO soal_ujian (ujian_id, tipe_soal, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, bobot) VALUES (?, 'PG', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ujian_id, $pertanyaan, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $opsi_e, $kunci, $bobot]);
        } else {
            $stmt = $db->prepare("INSERT INTO soal_ujian (ujian_id, tipe_soal, pertanyaan, bobot) VALUES (?, 'ESAI', ?, ?)");
            $stmt->execute([$ujian_id, $pertanyaan, $bobot]);
        }

        catat_log($_SESSION['user_id'], "Menambahkan soal ke ujian ID: $ujian_id");
        echo json_encode(['status' => 'success', 'message' => 'Soal berhasil ditambahkan!']);
        exit();
    }

    // ------------------- PENILAIAN ESAI SISWA -------------------
    if ($action === 'nilai_esai') {
        $jawaban_id = intval($_POST['jawaban_id'] ?? 0);
        $nilai_esai = floatval($_POST['nilai_esai'] ?? 0);

        $stmt = $db->prepare("UPDATE jawaban_siswa SET nilai_esai = ? WHERE id = ?");
        $stmt->execute([$nilai_esai, $jawaban_id]);

        echo json_encode(['status' => 'success', 'message' => 'Nilai esai diperbarui']);
        exit();
    }
}