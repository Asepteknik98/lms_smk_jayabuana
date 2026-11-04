ALTER TABLE kak_klaim ADD COLUMN IF NOT EXISTS tingkat_aktivitas enum('X','XI','XII') DEFAULT NULL;
ALTER TABLE kak_klaim ADD INDEX IF NOT EXISTS idx_kak_klaim_tingkat(siswa_id,tingkat_aktivitas,status);
-- Gunakan tingkat acara tersimpan; data lama tanpa tingkat memakai kelas terkait.
-- Hanya mengisi kolom kosong agar kenaikan kelas tidak memindahkan poin lama.
UPDATE kak_klaim c
JOIN kak_acara e ON e.id=c.acara_id
JOIN siswa s ON s.id=c.siswa_id
LEFT JOIN kelas ke ON ke.id=e.kelas_id
LEFT JOIN kelas ks ON ks.id=s.kelas_id
SET c.tingkat_aktivitas=COALESCE(e.tingkat_aktivitas,e.target_tingkat,ke.tingkat,ks.tingkat)
WHERE c.tingkat_aktivitas IS NULL;
