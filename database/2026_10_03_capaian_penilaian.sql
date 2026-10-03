CREATE TABLE IF NOT EXISTS capaian_penilaian (
  komponen_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  deskripsi TEXT NOT NULL,
  saran TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (komponen_id, siswa_id),
  CONSTRAINT fk_capaian_komponen FOREIGN KEY (komponen_id) REFERENCES komponen_penilaian(id) ON DELETE CASCADE,
  CONSTRAINT fk_capaian_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
