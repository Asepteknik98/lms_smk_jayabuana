CREATE TABLE IF NOT EXISTS komponen_penilaian (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengajaran_id INT UNSIGNED NOT NULL,
  nama_komponen VARCHAR(100) NOT NULL,
  bobot DECIMAL(5,2) UNSIGNED NOT NULL,
  urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_komponen_pengajaran (pengajaran_id),
  CONSTRAINT fk_komponen_pengajaran FOREIGN KEY (pengajaran_id) REFERENCES pengajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nilai_komponen (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  komponen_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  nilai DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_nilai_komponen_siswa (komponen_id, siswa_id),
  KEY idx_nilai_komponen_siswa (siswa_id),
  CONSTRAINT fk_nilai_komponen FOREIGN KEY (komponen_id) REFERENCES komponen_penilaian(id) ON DELETE CASCADE,
  CONSTRAINT fk_nilai_komponen_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
