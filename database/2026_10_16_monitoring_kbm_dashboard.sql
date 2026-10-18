-- Tahap 7: indeks pembacaan monitoring. Terapkan manual setelah backup/staging.
-- MariaDB 10.4+, idempoten, tidak mengubah isi laporan atau role.
ALTER TABLE kbm_laporan ADD INDEX IF NOT EXISTS idx_kbm_laporan_terbuka(checkout_at,pertemuan_id);
ALTER TABLE kbm_koreksi_admin ADD INDEX IF NOT EXISTS idx_kbm_koreksi_terbuka(checkout_baru,checkin_baru,pertemuan_id,id);
