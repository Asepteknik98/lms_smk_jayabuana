ALTER TABLE pengajaran ADD COLUMN IF NOT EXISTS kkm DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 75.00 AFTER semester;

CREATE TABLE IF NOT EXISTS catatan_siswa_pengajaran (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengajaran_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  catatan TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_catatan_pengajaran_siswa (pengajaran_id,siswa_id),
  CONSTRAINT fk_catatan_pengajaran FOREIGN KEY (pengajaran_id) REFERENCES pengajaran(id) ON DELETE CASCADE,
  CONSTRAINT fk_catatan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS riwayat_nilai (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengajaran_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  komponen_id INT UNSIGNED NOT NULL,
  guru_id INT UNSIGNED NOT NULL,
  nilai_lama DECIMAL(5,2) UNSIGNED DEFAULT NULL,
  nilai_baru DECIMAL(5,2) UNSIGNED NOT NULL,
  diubah_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_riwayat_pengajaran_siswa (pengajaran_id,siswa_id,diubah_pada),
  CONSTRAINT fk_riwayat_pengajaran FOREIGN KEY (pengajaran_id) REFERENCES pengajaran(id) ON DELETE CASCADE,
  CONSTRAINT fk_riwayat_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
  CONSTRAINT fk_riwayat_komponen FOREIGN KEY (komponen_id) REFERENCES komponen_penilaian(id) ON DELETE CASCADE,
  CONSTRAINT fk_riwayat_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
