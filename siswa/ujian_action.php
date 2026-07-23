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
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf($_POST['csrf_token']??'')){http_response_code(403);echo json_encode(['status'=>'error','message'=>'Permintaan tidak sah. Muat ulang halaman ujian.']);exit;}

// ------------------- SIMPAN JAWABAN REAL-TIME / AUTO-SAVE -------------------
if ($action === 'save_jawaban') {
    $sesi_id  = intval($_POST['sesi_id'] ?? 0);
    $soal_id  = intval($_POST['soal_id'] ?? 0);
    $tipe     = strtoupper(trim($_POST['tipe'] ?? 'PG'));
    $jawaban  = trim((string)($_POST['jawaban'] ?? ''));

    if ($sesi_id <= 0 || $soal_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit();
    }

    if(!in_array($tipe,['PG','ESAI'],true)||($tipe==='PG'&&!in_array(strtoupper($jawaban),['A','B','C','D','E'],true))){echo json_encode(['status'=>'error','message'=>'Jawaban tidak valid.']);exit;}
    if($tipe==='ESAI'&&mb_strlen($jawaban)>20000){echo json_encode(['status'=>'error','message'=>'Jawaban esai terlalu panjang.']);exit;}
    $stmt_akses=$db->prepare("SELECT su.id,su.ujian_id FROM sesi_ujian su JOIN ujian u ON u.id=su.ujian_id JOIN soal_ujian so ON so.ujian_id=u.id AND so.id=? JOIN pengajaran p ON p.id=u.pengajaran_id JOIN siswa s ON s.id=su.siswa_id AND s.kelas_id=p.kelas_id WHERE su.id=? AND su.siswa_id=? AND su.status='Berlangsung' AND NOW() BETWEEN u.waktu_mulai AND u.waktu_selesai AND so.tipe_soal=?");
    $stmt_akses->execute([$soal_id,$sesi_id,$siswa_id,$tipe]);if(!$stmt_akses->fetch()){http_response_code(403);echo json_encode(['status'=>'error','message'=>'Sesi, soal, atau waktu ujian tidak valid.']);exit;}

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

    $stmt_sesi = $db->prepare("SELECT su.*,u.waktu_mulai jadwal_mulai,u.waktu_selesai jadwal_selesai FROM sesi_ujian su JOIN ujian u ON u.id=su.ujian_id WHERE su.id=? AND su.siswa_id=?");
    $stmt_sesi->execute([$sesi_id, $siswa_id]);
    $sesi = $stmt_sesi->fetch();

    if (!$sesi) {
        echo json_encode(['status' => 'error', 'message' => 'Sesi tidak ditemukan']);
        exit();
    }

    $ujian_id = $sesi['ujian_id'];

    $stmt_belum = $db->prepare(
        "SELECT COUNT(*)
         FROM soal_ujian su
         LEFT JOIN jawaban_siswa js ON js.soal_id = su.id AND js.sesi_ujian_id = ?
         WHERE su.ujian_id = ?
           AND ((su.tipe_soal = 'PG' AND js.jawaban_pg IS NULL)
             OR (su.tipe_soal = 'ESAI' AND (js.jawaban_esai IS NULL OR TRIM(js.jawaban_esai) = '')))"
    );
    $stmt_belum->execute([$sesi_id, $ujian_id]);
    $jumlah_belum = (int)$stmt_belum->fetchColumn();

    $stmt_batas = $db->prepare(
        "SELECT u.durasi_menit,
                TIMESTAMPDIFF(SECOND, su.waktu_mulai, NOW()) AS detik_berjalan,
                (NOW() >= u.waktu_selesai) AS jadwal_berakhir
         FROM sesi_ujian su
         JOIN ujian u ON u.id = su.ujian_id
         WHERE su.id = ?"
    );
    $stmt_batas->execute([$sesi_id]);
    $batas = $stmt_batas->fetch();
    $waktu_benar_habis = $batas && ((int)$batas['jadwal_berakhir']===1 || (int)$batas['detik_berjalan'] >= max(0, ((int)$batas['durasi_menit'] * 60) - 2));

    if ($jumlah_belum > 0 && !$waktu_benar_habis) {
        echo json_encode([
            'status' => 'error',
            'message' => "$jumlah_belum soal belum dijawab. Semua soal wajib dijawab sebelum ujian dikirim."
        ]);
        exit();
    }
    if($sesi['status']==='Selesai'){echo json_encode(['status'=>'success','message'=>'Ujian sudah selesai.']);exit;}
    if(time()<strtotime($sesi['jadwal_mulai'])){echo json_encode(['status'=>'error','message'=>'Ujian belum dimulai.']);exit;}

    // Update status sesi menjadi Selesai
    $db->beginTransaction();
    $stmt_fin = $db->prepare("UPDATE sesi_ujian SET status='Selesai',waktu_selesai=NOW() WHERE id=? AND siswa_id=? AND status='Berlangsung'");
    $stmt_fin->execute([$sesi_id,$siswa_id]);

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
    $db->commit();

    catat_log($_SESSION['user_id'], "Menyelesaikan Ujian ID: $ujian_id dengan Nilai PG: $nilai_pg");
    echo json_encode(['status' => 'success', 'message' => 'Ujian telah selesai dikerjakan!']);
    exit();
}
http_response_code(400);echo json_encode(['status'=>'error','message'=>'Aksi tidak dikenali.']);
