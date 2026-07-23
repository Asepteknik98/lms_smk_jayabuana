<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json; charset=UTF-8');
check_access([2]);

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

function respons_ujian(string $status, string $message, array $tambahan = []): void
{
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $tambahan), JSON_UNESCAPED_UNICODE);
    exit;
}

function ujian_milik_guru(PDO $db, int $ujian_id, int $user_id): ?array
{
    $stmt = $db->prepare(
        'SELECT u.*,p.guru_id
         FROM ujian u
         JOIN pengajaran p ON p.id=u.pengajaran_id
         JOIN guru g ON g.id=p.guru_id
         WHERE u.id=? AND g.user_id=?'
    );
    $stmt->execute([$ujian_id, $user_id]);
    return $stmt->fetch() ?: null;
}

function ujian_sudah_dikerjakan(PDO $db, int $ujian_id): bool
{
    $stmt = $db->prepare('SELECT EXISTS(SELECT 1 FROM sesi_ujian WHERE ujian_id=?)');
    $stmt->execute([$ujian_id]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respons_ujian('error', 'Metode permintaan tidak diizinkan.');
}
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    respons_ujian('error', 'Sesi keamanan berakhir. Silakan muat ulang halaman.');
}

try {
    if (in_array($action, ['create_ujian', 'update_ujian'], true)) {
        $ujian_id = (int)($_POST['ujian_id'] ?? 0);
        $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
        $nama_ujian = trim($_POST['nama_ujian'] ?? '');
        $jenis_ujian = $_POST['jenis_ujian'] ?? '';
        $durasi_menit = (int)($_POST['durasi_menit'] ?? 0);
        $waktu_mulai = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['waktu_mulai'] ?? '');
        $waktu_selesai = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['waktu_selesai'] ?? '');
        $acak_soal = isset($_POST['acak_soal']) ? 1 : 0;

        $stmt = $db->prepare('SELECT p.id FROM pengajaran p JOIN guru g ON g.id=p.guru_id WHERE p.id=? AND g.user_id=?');
        $stmt->execute([$pengajaran_id, $_SESSION['user_id']]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Kelas atau mata pelajaran bukan pengajaran Anda.');
        }
        if ($nama_ujian === '' || mb_strlen($nama_ujian) > 255 || !in_array($jenis_ujian, ['Kuis', 'UTS', 'UAS'], true)) {
            throw new RuntimeException('Nama atau jenis ujian tidak valid.');
        }
        if ($durasi_menit < 1 || $durasi_menit > 600 || !$waktu_mulai || !$waktu_selesai || $waktu_mulai >= $waktu_selesai) {
            throw new RuntimeException('Durasi harus 1–600 menit dan waktu selesai harus setelah waktu mulai.');
        }
        if ($action === 'create_ujian') {
            $stmt = $db->prepare('INSERT INTO ujian (pengajaran_id,nama_ujian,jenis_ujian,durasi_menit,waktu_mulai,waktu_selesai,acak_soal) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$pengajaran_id, $nama_ujian, $jenis_ujian, $durasi_menit, $waktu_mulai->format('Y-m-d H:i:s'), $waktu_selesai->format('Y-m-d H:i:s'), $acak_soal]);
            $ujian_baru_id = (int)$db->lastInsertId();
            catat_log($_SESSION['user_id'], "Membuat ujian baru: $nama_ujian");
            respons_ujian('success', 'Ujian berhasil dibuat.', ['ujian_id' => $ujian_baru_id, 'nama_ujian' => $nama_ujian]);
        }
        $ujian_lama = ujian_milik_guru($db, $ujian_id, (int)$_SESSION['user_id']);
        if (!$ujian_lama) {
            throw new RuntimeException('Ujian tidak ditemukan atau bukan milik Anda.');
        }
        if (ujian_sudah_dikerjakan($db, $ujian_id) && (int)$ujian_lama['pengajaran_id'] !== $pengajaran_id) {
            throw new RuntimeException('Kelas ujian tidak dapat dipindahkan karena sudah memiliki peserta.');
        }
        $stmt = $db->prepare('UPDATE ujian SET pengajaran_id=?,nama_ujian=?,jenis_ujian=?,durasi_menit=?,waktu_mulai=?,waktu_selesai=?,acak_soal=? WHERE id=?');
        $stmt->execute([$pengajaran_id, $nama_ujian, $jenis_ujian, $durasi_menit, $waktu_mulai->format('Y-m-d H:i:s'), $waktu_selesai->format('Y-m-d H:i:s'), $acak_soal, $ujian_id]);
        catat_log($_SESSION['user_id'], "Mengubah ujian: $nama_ujian");
        respons_ujian('success', 'Ujian berhasil diperbarui.');
    }

    if (in_array($action, ['add_soal', 'update_soal'], true)) {
        $soal_id = (int)($_POST['soal_id'] ?? 0);
        $ujian_id = (int)($_POST['ujian_id'] ?? 0);
        $tipe_soal = $_POST['tipe_soal'] ?? '';
        $pertanyaan = trim($_POST['pertanyaan'] ?? '');
        $bobot = filter_var($_POST['bobot'] ?? null, FILTER_VALIDATE_INT);
        if (!ujian_milik_guru($db, $ujian_id, (int)$_SESSION['user_id'])) {
            throw new RuntimeException('Ujian tidak ditemukan atau bukan milik Anda.');
        }
        if (ujian_sudah_dikerjakan($db, $ujian_id)) {
            throw new RuntimeException('Soal tidak dapat diubah karena ujian sudah memiliki peserta.');
        }
        if (!in_array($tipe_soal, ['PG', 'ESAI'], true) || $pertanyaan === '' || $bobot === false || $bobot < 1 || $bobot > 100) {
            throw new RuntimeException('Tipe, pertanyaan, atau bobot soal tidak valid.');
        }

        $opsi = [null, null, null, null, null];
        $kunci = null;
        if ($tipe_soal === 'PG') {
            $opsi = array_map(static fn($v) => trim((string)$v), [
                $_POST['opsi_a'] ?? '', $_POST['opsi_b'] ?? '', $_POST['opsi_c'] ?? '',
                $_POST['opsi_d'] ?? '', $_POST['opsi_e'] ?? '',
            ]);
            $kunci = strtoupper(trim($_POST['kunci_jawaban'] ?? ''));
            if (in_array('', array_slice($opsi, 0, 4), true) || !in_array($kunci, ['A', 'B', 'C', 'D', 'E'], true) || ($kunci === 'E' && $opsi[4] === '')) {
                throw new RuntimeException('Pilihan A–D dan kunci jawaban wajib diisi dengan benar.');
            }
            $opsi = array_map(static fn($v) => $v === '' ? null : $v, $opsi);
        }

        if ($action === 'add_soal') {
            $stmt = $db->prepare('INSERT INTO soal_ujian (ujian_id,tipe_soal,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_jawaban,bobot) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$ujian_id, $tipe_soal, $pertanyaan, ...$opsi, $kunci, $bobot]);
            catat_log($_SESSION['user_id'], "Menambahkan soal ke ujian ID: $ujian_id");
            respons_ujian('success', 'Soal berhasil ditambahkan.');
        }
        $stmt = $db->prepare('SELECT id FROM soal_ujian WHERE id=? AND ujian_id=?');
        $stmt->execute([$soal_id, $ujian_id]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Soal tidak ditemukan pada ujian ini.');
        }
        $stmt = $db->prepare('UPDATE soal_ujian SET tipe_soal=?,pertanyaan=?,opsi_a=?,opsi_b=?,opsi_c=?,opsi_d=?,opsi_e=?,kunci_jawaban=?,bobot=? WHERE id=? AND ujian_id=?');
        $stmt->execute([$tipe_soal, $pertanyaan, ...$opsi, $kunci, $bobot, $soal_id, $ujian_id]);
        catat_log($_SESSION['user_id'], "Mengubah soal ID: $soal_id");
        respons_ujian('success', 'Soal berhasil diperbarui.');
    }

    if ($action === 'delete_soal') {
        $soal_id = (int)($_POST['soal_id'] ?? 0);
        $stmt = $db->prepare(
            'SELECT so.id,so.ujian_id FROM soal_ujian so
             JOIN ujian u ON u.id=so.ujian_id JOIN pengajaran p ON p.id=u.pengajaran_id
             JOIN guru g ON g.id=p.guru_id WHERE so.id=? AND g.user_id=?'
        );
        $stmt->execute([$soal_id, $_SESSION['user_id']]);
        $soal = $stmt->fetch();
        if (!$soal) throw new RuntimeException('Soal tidak ditemukan atau bukan milik Anda.');
        if (ujian_sudah_dikerjakan($db, (int)$soal['ujian_id'])) {
            throw new RuntimeException('Soal tidak dapat dihapus karena ujian sudah memiliki peserta.');
        }
        $db->prepare('DELETE FROM soal_ujian WHERE id=?')->execute([$soal_id]);
        catat_log($_SESSION['user_id'], "Menghapus soal ID: $soal_id");
        respons_ujian('success', 'Soal berhasil dihapus.');
    }

    if ($action === 'delete_ujian') {
        $ujian_id = (int)($_POST['ujian_id'] ?? 0);
        $ujian = ujian_milik_guru($db, $ujian_id, (int)$_SESSION['user_id']);
        if (!$ujian) throw new RuntimeException('Ujian tidak ditemukan atau bukan milik Anda.');
        $db->prepare('DELETE FROM ujian WHERE id=?')->execute([$ujian_id]);
        catat_log($_SESSION['user_id'], 'Menghapus ujian: ' . $ujian['nama_ujian']);
        respons_ujian('success', 'Ujian beserta soal dan hasilnya berhasil dihapus.');
    }

    if ($action === 'nilai_esai') {
        $jawaban_id = (int)($_POST['jawaban_id'] ?? 0);
        $nilai_esai = filter_var($_POST['nilai_esai'] ?? null, FILTER_VALIDATE_FLOAT);
        $stmt = $db->prepare(
            "SELECT js.id,js.sesi_ujian_id,so.bobot,su.ujian_id,su.siswa_id
             FROM jawaban_siswa js
             JOIN soal_ujian so ON so.id=js.soal_id AND so.tipe_soal='ESAI'
             JOIN sesi_ujian su ON su.id=js.sesi_ujian_id
             JOIN ujian u ON u.id=su.ujian_id JOIN pengajaran p ON p.id=u.pengajaran_id
             JOIN guru g ON g.id=p.guru_id WHERE js.id=? AND g.user_id=?"
        );
        $stmt->execute([$jawaban_id, $_SESSION['user_id']]);
        $jawaban = $stmt->fetch();
        if (!$jawaban || $nilai_esai === false || $nilai_esai < 0 || $nilai_esai > 100) {
            throw new RuntimeException('Jawaban esai atau nilai tidak valid.');
        }

        $db->beginTransaction();
        $db->prepare('UPDATE jawaban_siswa SET nilai_esai=? WHERE id=?')->execute([$nilai_esai, $jawaban_id]);
        $stmt = $db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN so.tipe_soal='PG' THEN so.bobot ELSE 0 END),0) bobot_pg,
                COALESCE(SUM(CASE WHEN so.tipe_soal='PG' AND js.is_benar=1 THEN so.bobot ELSE 0 END),0) dapat_pg,
                COALESCE(SUM(CASE WHEN so.tipe_soal='ESAI' THEN so.bobot ELSE 0 END),0) bobot_esai,
                COALESCE(SUM(CASE WHEN so.tipe_soal='ESAI' THEN COALESCE(js.nilai_esai,0)*so.bobot/100 ELSE 0 END),0) dapat_esai
             FROM soal_ujian so
             LEFT JOIN jawaban_siswa js ON js.soal_id=so.id AND js.sesi_ujian_id=?
             WHERE so.ujian_id=?"
        );
        $stmt->execute([$jawaban['sesi_ujian_id'], $jawaban['ujian_id']]);
        $hasil = $stmt->fetch();
        $nilai_pg = $hasil['bobot_pg'] > 0 ? $hasil['dapat_pg'] / $hasil['bobot_pg'] * 100 : 0;
        $nilai_esai_total = $hasil['bobot_esai'] > 0 ? $hasil['dapat_esai'] / $hasil['bobot_esai'] * 100 : 0;
        $semua_bobot = (float)$hasil['bobot_pg'] + (float)$hasil['bobot_esai'];
        $nilai_total = $semua_bobot > 0 ? ((float)$hasil['dapat_pg'] + (float)$hasil['dapat_esai']) / $semua_bobot * 100 : 0;
        $stmt = $db->prepare(
            'INSERT INTO nilai_ujian (ujian_id,siswa_id,nilai_pg,nilai_esai,nilai_total,selesai_pada)
             VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE nilai_pg=VALUES(nilai_pg),nilai_esai=VALUES(nilai_esai),
                 nilai_total=VALUES(nilai_total),selesai_pada=NOW()'
        );
        $stmt->execute([$jawaban['ujian_id'], $jawaban['siswa_id'], $nilai_pg, $nilai_esai_total, $nilai_total]);
        $db->commit();
        catat_log($_SESSION['user_id'], "Menilai jawaban esai ID: $jawaban_id");
        respons_ujian('success', 'Nilai esai dan nilai total berhasil diperbarui.', ['nilai_total' => round($nilai_total, 2)]);
    }

    http_response_code(400);
    respons_ujian('error', 'Aksi tidak dikenali.');
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    if (!($e instanceof RuntimeException)) error_log('Aksi ujian guru gagal: ' . $e->getMessage());
    http_response_code($e instanceof RuntimeException ? 422 : 500);
    respons_ujian('error', $e instanceof RuntimeException ? $e->getMessage() : 'Permintaan gagal diproses.');
}
