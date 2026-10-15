-- Pembatalan disimpan terpisah agar riwayat nilai remedial asli tetap utuh.
CREATE TABLE IF NOT EXISTS remedial_tugas_batal (
 remedial_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 guru_id INT UNSIGNED NOT NULL,
 alasan VARCHAR(1000) NOT NULL DEFAULT '',
 nilai_sebelum DECIMAL(5,2) NOT NULL,
 nilai_sesudah DECIMAL(5,2) NOT NULL,
 dibatalkan_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_batal_remedial_r FOREIGN KEY(remedial_id) REFERENCES remedial_tugas(id) ON DELETE CASCADE,
 CONSTRAINT fk_batal_remedial_g FOREIGN KEY(guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
