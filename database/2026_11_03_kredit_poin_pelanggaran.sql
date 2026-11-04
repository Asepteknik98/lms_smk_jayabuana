-- Jalankan sesudah migrasi kredit_poin_siswa untuk instalasi versi sebelumnya.
-- Tidak menghapus riwayat. Jika ada transaksi positif lama, tinjau data tersebut
-- terlebih dahulu; constraint akan menolak migrasi sampai data ditangani.
SET @kps_constraint_exists = (
    SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE() AND table_name='kredit_poin_siswa'
      AND constraint_name='chk_kps_hanya_pengurangan' AND constraint_type='CHECK'
);
SET @kps_constraint_sql = IF(@kps_constraint_exists > 0, 'SELECT 1',
    'ALTER TABLE kredit_poin_siswa ADD CONSTRAINT chk_kps_hanya_pengurangan CHECK (perubahan BETWEEN -100000 AND -1)');
PREPARE kps_constraint_statement FROM @kps_constraint_sql;
EXECUTE kps_constraint_statement;
DEALLOCATE PREPARE kps_constraint_statement;
