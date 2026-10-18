-- Terapkan manual setelah backup, setelah migrasi pilihan_guru.
-- Tidak mengubah jadwal atau waktu laporan lama; tidak membuat jam fiktif.
ALTER TABLE kbm_jadwal MODIFY pola_id BIGINT UNSIGNED NULL;
ALTER TABLE kbm_jadwal
 DROP CONSTRAINT IF EXISTS ck_kbm_jadwal_pola_manual,
 ADD CONSTRAINT ck_kbm_jadwal_pola_manual CHECK(
   pola_id IS NOT NULL OR
   (COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pengajaran_snapshot,'$.sumber')),'')='ketua_kelas'
    AND berlaku_mulai=berlaku_sampai)
 );
ALTER TABLE kbm_pertemuan
 MODIFY rencana_mulai DATETIME NULL,
 MODIFY rencana_selesai DATETIME NULL;
ALTER TABLE kbm_pertemuan
 DROP CONSTRAINT IF EXISTS ck_kbm_pt_jam,
 ADD CONSTRAINT ck_kbm_pt_jam CHECK(
   (rencana_mulai IS NULL AND rencana_selesai IS NULL) OR
   (rencana_mulai IS NOT NULL AND rencana_selesai IS NOT NULL
    AND rencana_selesai>rencana_mulai AND DATE(rencana_mulai)=tanggal AND DATE(rencana_selesai)=tanggal)
 );
