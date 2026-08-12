-- Jalankan satu kali sebelum mengunggah fitur Nilai Manual ke production.
CREATE TABLE IF NOT EXISTS nilai_tugas_manual (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengajaran_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  pertemuan_ke TINYINT UNSIGNED NOT NULL DEFAULT 1,
  nilai DECIMAL(5,2) UNSIGNED NOT NULL,
  catatan VARCHAR(1000) DEFAULT NULL,
  diubah_oleh INT UNSIGNED NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_nilai_tugas_manual (pengajaran_id,siswa_id,pertemuan_ke),
  KEY idx_nilai_manual_siswa (siswa_id),
  KEY idx_nilai_manual_guru (diubah_oleh),
  CONSTRAINT fk_nilai_manual_pengajaran FOREIGN KEY (pengajaran_id) REFERENCES pengajaran(id) ON DELETE CASCADE,
  CONSTRAINT fk_nilai_manual_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
  CONSTRAINT fk_nilai_manual_guru FOREIGN KEY (diubah_oleh) REFERENCES guru(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
