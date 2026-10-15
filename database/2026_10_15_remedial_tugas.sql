CREATE TABLE IF NOT EXISTS remedial_tugas (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tugas_id INT UNSIGNED NOT NULL,
 siswa_id INT UNSIGNED NOT NULL,
 guru_id INT UNSIGNED NOT NULL,
 nilai_awal DECIMAL(5,2) NOT NULL,
 nilai_sebelum DECIMAL(5,2) NOT NULL,
 nilai_remedial DECIMAL(5,2) NOT NULL,
 nilai_akhir DECIMAL(5,2) NOT NULL,
 catatan VARCHAR(1000) NOT NULL DEFAULT '',
 dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_remedial_tugas_siswa (tugas_id,siswa_id,id),
 CONSTRAINT fk_remedial_tugas_t FOREIGN KEY(tugas_id) REFERENCES tugas(id) ON DELETE CASCADE,
 CONSTRAINT fk_remedial_tugas_s FOREIGN KEY(siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
 CONSTRAINT fk_remedial_tugas_g FOREIGN KEY(guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
