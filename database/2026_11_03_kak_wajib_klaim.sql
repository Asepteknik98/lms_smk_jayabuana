-- MariaDB: pemberian poin lama menjadi undangan klaim, bukan saldo otomatis.
ALTER TABLE kak_acara ADD COLUMN IF NOT EXISTS siswa_id int unsigned DEFAULT NULL;
ALTER TABLE kak_acara ADD COLUMN IF NOT EXISTS aktivitas_asal_id bigint unsigned DEFAULT NULL;
ALTER TABLE kak_acara ADD UNIQUE INDEX IF NOT EXISTS uq_kak_acara_asal(aktivitas_asal_id);
ALTER TABLE kak_aktivitas_siswa MODIFY COLUMN guru_id int unsigned DEFAULT NULL;

-- Catatan lama tetap disimpan. Siswa mendapat 30 hari untuk mengunggah bukti.
-- Poin hanya dihitung ketika ada klaim Disetujui yang menunjuk aktivitas tersebut.
INSERT INTO kak_acara(nama,deskripsi,tanggal_acara,klaim_mulai,klaim_sampai,poin,siswa_id,status,dibuat_oleh,aktivitas_asal_id)
SELECT a.nama_aktivitas,a.keterangan,a.tanggal_aktivitas,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),a.poin,a.siswa_id,'Terbit',g.user_id,a.id
FROM kak_aktivitas_siswa a JOIN guru g ON g.id=a.guru_id
WHERE NOT EXISTS(SELECT 1 FROM kak_klaim c WHERE c.aktivitas_id=a.id)
  AND NOT EXISTS(SELECT 1 FROM kak_acara e WHERE e.aktivitas_asal_id=a.id);
