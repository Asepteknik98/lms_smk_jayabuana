<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json');
check_access([3]);

$db = Database::getInstance();

$stmt_s = $db->prepare("SELECT id FROM siswa WHERE user_id = ?");
$stmt_s->execute([$_SESSION['user_id']]);
$siswa = $stmt_s->fetch();
$siswa_id = $siswa['id'] ?? 0;

$action = $_GET['action'] ?? '';

// ------------------- SIMPAN JAWABAN REAL-TIME / AUTO-SAVE -------------------
if ($action === 'save_jawaban') {
    $sesi_id  = intval($_POST['sesi_id'] ?? 0);
    $soal_id  = intval($_POST['soal_id'] ?? 0);
    $tipe     = sanitize($_POST['tipe'] ?? 'PG');
    $jawaban  = sanitize($_POST['jawaban'] ?? '');

    if ($sesi_id <= 0 || $soal_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit();
    }

    // Cek Kunci Jawaban jika PG
    $is_benar = 0;
    if ($tipe === 'PG') {
        $stmt_k = $db->prepare("SELECT kunci_jawaban FROM soal_ujian WHERE id = ?");
        $stmt_k->execute([$soal_id]);
        $kunci = $stmt_k->fetchColumn();
        if ($kunci && strtoupper($jawaban) === strtoupper($kunci)) {
            $is_benar = 1;
        }
    }

    // Upsert Jawaban
    $stmt_c = $db->prepare("SELECT id FROM jawaban_siswa WHERE sesi_ujian_id = ? AND soal_id = ?");
    $stmt_c->execute([$sesi_id, $soal_id]);
    $exist = $stmt_c->fetch();

    if ($exist) {
        if ($tipe === 'PG') {
            $stmt_u = $db->prepare("UPDATE jawaban_siswa SET jawaban_pg = ?, is_benar = ? WHERE id = ?");
            $stmt_u->execute([$jawaban, $is_benar, $exist['id']]);
        } else {
            $stmt_u = $db->prepare("UPDATE jawaban_siswa SET jawaban_esai = ? WHERE id = ?");
            $stmt_u->execute([$jawaban, $exist['id']]);
        }
    } else {
        if ($tipe === 'PG') {
            $stmt_i = $db->prepare("INSERT INTO jawaban_siswa (sesi_ujian_id, soal_id, jawaban_pg, is_benar) VALUES (?, ?, ?, ?)");
            $stmt_i->execute([$sesi_id, $soal_id, $jawaban, $is_benar]);
        } else {
            $stmt_i = $db->prepare("INSERT INTO jawaban_siswa (sesi_ujian_id, soal_id, jawaban_esai) VALUES (?, ?, ?)");
            $stmt_i->execute([$sesi_id, $soal_id, $jawaban]);
        }
    }

    echo json_encode(['status' => 'success']);
    exit();
}

// ------------------- FINISH UJIAN & KALKULASI AUTOMATIS -------------------
if ($action === 'finish_ujian') {
    $sesi_id = intval($_POST['sesi_id'] ?? 0);

    $stmt_sesi = $db->prepare("SELECT * FROM sesi_ujian WHERE id = ? AND siswa_id = ?");
    $stmt_sesi->execute([$sesi_id, $siswa_id]);
    $sesi = $stmt_sesi->fetch();

    if (!$sesi) {
        echo json_encode(['status' => 'error', 'message' => 'Sesi tidak ditemukan']);
        exit();
    }

    $ujian_id = $sesi['ujian_id'];

    // Update status sesi menjadi Selesai
    $stmt_fin = $db->prepare("UPDATE sesi_ujian SET status = 'Selesai', waktu_selesai = NOW() WHERE id = ?");
    $stmt_fin->execute([$sesi_id]);

    // Kalkulasi Otomatis Nilai Pilihan Ganda (PG)
    $stmt_calc = $db->prepare("
        SELECT 
            SUM(su.bobot) as total_bobot,
            SUM(CASE WHEN js.is_benar = 1 THEN su.bobot ELSE 0 END) as bobot_dapat
        FROM soal_ujian su
        LEFT JOIN jawaban_siswa js ON (js.soal_id = su.id AND js.sesi_ujian_id = ?)
        WHERE su.ujian_id = ? AND su.tipe_soal = 'PG'
    ");
    $stmt_calc->execute([$sesi_id, $ujian_id]);
    $res = $stmt_calc->fetch();

    $nilai_pg = 0;
    if ($res && $res['total_bobot'] > 0) {
        $nilai_pg = ($res['bobot_dapat'] / $res['total_bobot']) * 100;
    }

    // Insert / Update Nilai
    $stmt_n = $db->prepare("INSERT INTO nilai_ujian (ujian_id, siswa_id, nilai_pg, nilai_total) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nilai_pg = ?, nilai_total = ?");
    $stmt_n->execute([$ujian_id, $siswa_id, $nilai_pg, $nilai_pg, $nilai_pg, $nilai_pg]);

    catat_log($_SESSION['user_id'], "Menyelesaikan Ujian ID: $ujian_id dengan Nilai PG: $nilai_pg");
    echo json_encode(['status' => 'success', 'message' => 'Ujian telah selesai dikerjakan!']);
    exit();
}