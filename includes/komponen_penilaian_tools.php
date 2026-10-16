<?php

function kp_delete_empty(PDO $db, int $guru, int $pid, int $id): void {
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT k.id FROM komponen_penilaian k JOIN pengajaran p ON p.id=k.pengajaran_id WHERE k.id=? AND p.id=? AND p.guru_id=? FOR UPDATE');
        $q->execute([$id,$pid,$guru]);
        if (!$q->fetchColumn()) throw new RuntimeException('Komponen tidak ditemukan pada pengajaran Anda.');
        foreach (['nilai_komponen','riwayat_nilai','capaian_penilaian'] as $table) {
            $q=$db->prepare("SELECT komponen_id FROM $table WHERE komponen_id=? LIMIT 1 FOR UPDATE");
            $q->execute([$id]);
            if ($q->fetchColumn()!==false) throw new RuntimeException('Komponen sudah memiliki nilai, riwayat, atau capaian siswa sehingga tidak dapat dihapus.');
        }
        $q=$db->prepare('DELETE FROM komponen_penilaian WHERE id=? AND pengajaran_id=?');
        $q->execute([$id,$pid]);
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
