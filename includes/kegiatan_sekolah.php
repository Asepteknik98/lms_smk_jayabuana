<?php
function ks_date($value): DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{3}-\d{2}-\d{2}$/', $value)) {
        throw new InvalidArgumentException('Tanggal tidak valid.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Tanggal tidak valid.');
    return $date;
}

function ks_schedule(int $day): array {
    $special = [
        1 => ['bendera_naik' => 'Upacara Penaikan Bendera'],
        2 => ['senam_pagi' => 'Senam Bersama — Pagi', 'senam_siang' => 'Senam Bersama — Siang'],
        3 => ['ketarunaan' => 'Ketarunaan'],
        4 => ['apel' => 'Apel'],
        5 => ['tadarus' => 'Tadarus', 'bendera_turun' => 'Upacara Penurunan Bendera'],
    ];
    return isset($special[$day]) ? $special[$day] + [
        'menyambut_pagi' => 'Menyambut Siswa — Pagi',
        'menyambut_siang' => 'Menyambut Siswa — Siang',
        'piket_pagi' => 'Guru Piket — Pagi',
        'piket_siang' => 'Guru Piket — Siang',
    ] : [];
}

function ks_people(PDO $db): array {
    $rows = $db->query("SELECT CONCAT('guru:',id) peserta,nama_lengkap,'Guru' jenis FROM guru
        UNION ALL SELECT CONCAT('staf:',id),nama_lengkap,'Staf' FROM kegiatan_staf ORDER BY nama_lengkap, peserta")->fetchAll();
    return array_column($rows, null, 'peserta');
}

function ks_save(PDO $db, int $id, int $version, array $checked, int $admin): void {
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT versi FROM kegiatan_sekolah WHERE id=? FOR UPDATE');
        $lock->execute([$id]);
        $actual = $lock->fetchColumn();
        if ($actual === false) throw new InvalidArgumentException('Kegiatan tidak ditemukan.');
        if ((int)$actual !== $version) throw new InvalidArgumentException('Data sudah diubah admin lain. Muat ulang kegiatan sebelum menyimpan.');
        $people = ks_people($db);
        $old = $db->prepare('SELECT peserta,nama_lengkap,jenis FROM kegiatan_kehadiran WHERE kegiatan_id=?');
        $old->execute([$id]);
        $people += array_column($old->fetchAll(), null, 'peserta');
        foreach ($checked as $key) {
            if (!is_string($key) || !isset($people[$key])) throw new InvalidArgumentException('Peserta tidak valid.');
        }
        $insert = $db->prepare('INSERT INTO kegiatan_kehadiran(kegiatan_id,peserta,nama_lengkap,jenis,hadir) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE nama_lengkap=VALUES(nama_lengkap),jenis=VALUES(jenis),hadir=VALUES(hadir)');
        foreach ($people as $key => $person) $insert->execute([$id,$key,$person['nama_lengkap'],$person['jenis'],in_array($key,$checked,true)?1:0]);
        $db->prepare('UPDATE kegiatan_sekolah SET versi=versi+1,disimpan_pada=NOW(),disimpan_oleh=? WHERE id=?')->execute([$admin,$id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
