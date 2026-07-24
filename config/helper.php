<?php
require_once __DIR__ . '/database.php';

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function catat_log($user_id, $aktivitas) {
    $db = Database::getInstance();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    
    $stmt = $db->prepare("INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $aktivitas, $ip, $agent]);
}

/**
 * Menutup satu sesi dan mencatat siswa yang belum check-in sebagai Alpa.
 * Fungsi ini aman dipanggil dari proses manual maupun otomatis.
 */
function tutup_sesi_absensi(PDO $db, int $sesi_id, ?int $guru_id = null): bool {
    if ($sesi_id < 1) return false;
    $memulai_transaksi = !$db->inTransaction();
    if ($memulai_transaksi) $db->beginTransaction();

    try {
        $sql = "SELECT sa.id,p.kelas_id
                FROM sesi_absensi sa
                JOIN pengajaran p ON p.id=sa.pengajaran_id
                WHERE sa.id=? AND sa.status='Dibuka'";
        $parameter = [$sesi_id];
        if ($guru_id !== null) {
            $sql .= ' AND p.guru_id=?';
            $parameter[] = $guru_id;
        }
        $sql .= ' FOR UPDATE';
        $stmt = $db->prepare($sql);
        $stmt->execute($parameter);
        $sesi = $stmt->fetch();
        if (!$sesi) {
            if ($memulai_transaksi) $db->commit();
            return false;
        }

        $stmt = $db->prepare(
            "INSERT INTO detail_absensi (sesi_absensi_id,siswa_id,status,keterangan)
             SELECT ?,s.id,'Alpa','Tidak melakukan check-in sampai sesi ditutup'
             FROM siswa s
             WHERE s.kelas_id=?
             ON DUPLICATE KEY UPDATE sesi_absensi_id=VALUES(sesi_absensi_id)"
        );
        $stmt->execute([$sesi_id,(int)$sesi['kelas_id']]);
        $db->prepare("UPDATE sesi_absensi SET status='Ditutup' WHERE id=? AND status='Dibuka'")->execute([$sesi_id]);

        if ($memulai_transaksi) $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($memulai_transaksi && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/**
 * Menutup sesi yang melewati waktu_tutup. Jika $tutup_semua dipakai bersama
 * pengajaran_id, seluruh sesi lama pembelajaran itu ditutup sebelum sesi baru.
 */
function proses_penutupan_absensi_otomatis(PDO $db, ?int $pengajaran_id = null, bool $tutup_semua = false): int {
    $sql = "SELECT id FROM sesi_absensi WHERE status='Dibuka'";
    $parameter = [];
    if ($pengajaran_id !== null) {
        $sql .= ' AND pengajaran_id=?';
        $parameter[] = $pengajaran_id;
    }
    if (!$tutup_semua) $sql .= ' AND waktu_tutup<=NOW()';
    $stmt = $db->prepare($sql);
    $stmt->execute($parameter);

    $jumlah = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sesi_id) {
        if (tutup_sesi_absensi($db, (int)$sesi_id)) $jumlah++;
    }
    return $jumlah;
}
