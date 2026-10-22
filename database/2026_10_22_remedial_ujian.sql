CREATE TABLE IF NOT EXISTS ujian_harian_remedial (
 ujian_id INT UNSIGNED PRIMARY KEY,
 ujian_asal_id INT UNSIGNED NOT NULL,
 guru_id INT UNSIGNED NOT NULL,
 dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_uhr_child FOREIGN KEY(ujian_id) REFERENCES ujian(id) ON DELETE CASCADE,
 CONSTRAINT fk_uhr_parent FOREIGN KEY(ujian_asal_id) REFERENCES ujian(id),
 CONSTRAINT fk_uhr_teacher FOREIGN KEY(guru_id) REFERENCES guru(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS remedial_ujian (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ujian_id INT UNSIGNED NOT NULL,
 siswa_id INT UNSIGNED NOT NULL,
 guru_id INT UNSIGNED NOT NULL,
 sumber_ujian_id INT UNSIGNED NULL,
 nilai_awal DECIMAL(5,2) NOT NULL,
 nilai_sebelum DECIMAL(5,2) NOT NULL,
 nilai_remedial DECIMAL(5,2) NOT NULL,
 nilai_akhir DECIMAL(5,2) NOT NULL,
 catatan VARCHAR(1000) NOT NULL DEFAULT '',
 dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_ru_source(sumber_ujian_id,siswa_id),
 KEY idx_ru_pair(ujian_id,siswa_id,id),
 CONSTRAINT fk_ru_exam FOREIGN KEY(ujian_id) REFERENCES ujian(id),
 CONSTRAINT fk_ru_student FOREIGN KEY(siswa_id) REFERENCES siswa(id),
 CONSTRAINT fk_ru_teacher FOREIGN KEY(guru_id) REFERENCES guru(id),
 CONSTRAINT fk_ru_source FOREIGN KEY(sumber_ujian_id) REFERENCES ujian(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS remedial_ujian_batal (
 remedial_id BIGINT UNSIGNED PRIMARY KEY,
 guru_id INT UNSIGNED NOT NULL,
 alasan VARCHAR(1000) NOT NULL DEFAULT '',
 nilai_sebelum DECIMAL(5,2) NOT NULL,
 nilai_sesudah DECIMAL(5,2) NOT NULL,
 dibatalkan_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_rub_history FOREIGN KEY(remedial_id) REFERENCES remedial_ujian(id),
 CONSTRAINT fk_rub_teacher FOREIGN KEY(guru_id) REFERENCES guru(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
