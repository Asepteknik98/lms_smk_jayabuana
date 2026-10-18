-- Terapkan manual setelah backup dan migrasi fondasi/admin/pilihan guru.
-- Sudah mencakup perubahan tanpa jam JP tanggal 17; cukup satu file pembaruan ini.
-- Lepaskan periode dari konfigurasi baru; seluruh data dan FK historis tetap utuh.
SET time_zone='+07:00';
ALTER TABLE kbm_jadwal MODIFY pola_id BIGINT UNSIGNED NULL;
ALTER TABLE kbm_jadwal
 DROP CONSTRAINT IF EXISTS ck_kbm_jadwal_pola_manual,
 ADD CONSTRAINT ck_kbm_jadwal_pola_manual CHECK(
   pola_id IS NOT NULL OR
   (COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pengajaran_snapshot,'$.sumber')),'')='ketua_kelas'
    AND berlaku_mulai=berlaku_sampai)
 );
ALTER TABLE kbm_pertemuan MODIFY rencana_mulai DATETIME NULL, MODIFY rencana_selesai DATETIME NULL;
ALTER TABLE kbm_pertemuan
 DROP CONSTRAINT IF EXISTS ck_kbm_pt_jam,
 ADD CONSTRAINT ck_kbm_pt_jam CHECK(
   (rencana_mulai IS NULL AND rencana_selesai IS NULL) OR
   (rencana_mulai IS NOT NULL AND rencana_selesai IS NOT NULL AND rencana_selesai>rencana_mulai
    AND DATE(rencana_mulai)=tanggal AND DATE(rencana_selesai)=tanggal)
 );
CREATE TABLE IF NOT EXISTS kbm_penyederhanaan (
 id TINYINT UNSIGNED PRIMARY KEY,
 mulai DATE NOT NULL,
 CONSTRAINT ck_kbm_simple_id CHECK(id=1)
) ENGINE=InnoDB;
INSERT IGNORE INTO kbm_penyederhanaan(id,mulai) VALUES(1,CURRENT_DATE);
ALTER TABLE kbm_kelas_sesi MODIFY periode_id INT UNSIGNED NULL;
ALTER TABLE kbm_ketua_kelas MODIFY periode_id INT UNSIGNED NULL;
ALTER TABLE kbm_kelas_sesi ADD INDEX IF NOT EXISTS idx_kbm_sesi_terakhir(kelas_id,id);
ALTER TABLE kbm_ketua_kelas ADD INDEX IF NOT EXISTS idx_kbm_ketua_terakhir(kelas_id,dicabut_pada,id);
