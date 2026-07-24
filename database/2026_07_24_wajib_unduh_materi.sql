ALTER TABLE akses_pertemuan
  ADD COLUMN wajib_unduh_materi TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

CREATE TABLE IF NOT EXISTS materi_siswa_diunduh (
  materi_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  diunduh_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (materi_id,siswa_id),
  KEY idx_materi_diunduh_siswa (siswa_id),
  CONSTRAINT fk_materi_diunduh_materi FOREIGN KEY (materi_id) REFERENCES materi(id) ON DELETE CASCADE,
  CONSTRAINT fk_materi_diunduh_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
