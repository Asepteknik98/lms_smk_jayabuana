<?php

function monitoringActivityDate($value): DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
        throw new InvalidArgumentException('Tanggal rekap tidak valid.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01' || $value > '9999-12-30') {
        throw new InvalidArgumentException('Tanggal rekap tidak valid.');
    }
    return $date;
}

function monitoringDailyActivities(PDO $db, DateTimeImmutable $date): array
{
    $queries = [];
    $params = [];
    foreach ([['materi', 'judul', 'Materi'], ['tugas', 'judul', 'Tugas'], ['ujian', 'nama_ujian', 'Ulangan Harian']] as [$table, $title, $type]) {
        $queries[] = "SELECT '$type' AS jenis, x.$title AS aktivitas, x.created_at AS waktu,
                            g.nama_lengkap AS pelaku, m.nama_mapel, k.nama_kelas
                     FROM $table x
                     JOIN pengajaran p ON p.id = x.pengajaran_id
                     JOIN guru g ON g.id = p.guru_id
                     JOIN mapel m ON m.id = p.mapel_id
                     JOIN kelas k ON k.id = p.kelas_id
                     WHERE x.created_at >= ? AND x.created_at < ?";
        $params[] = $date->format('Y-m-d 00:00:00');
        $params[] = $date->modify('+1 day')->format('Y-m-d 00:00:00');
    }
    $stmt = $db->prepare(implode(' UNION ALL ', $queries) . ' ORDER BY waktu DESC, jenis, pelaku, nama_kelas, aktivitas');
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
