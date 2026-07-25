ALTER TABLE tugas
  ADD COLUMN IF NOT EXISTS jenis_tugas ENUM('portofolio','esai','pilihan_ganda','merangkum','video') NOT NULL DEFAULT 'portofolio' AFTER deskripsi;

ALTER TABLE pengumpulan_tugas
  MODIFY COLUMN file_tugas VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS pertanyaan_tugas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tugas_id INT UNSIGNED NOT NULL,
  pertanyaan TEXT NOT NULL,
  urutan SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_pertanyaan_tugas (tugas_id, urutan),
  CONSTRAINT fk_pertanyaan_tugas
    FOREIGN KEY (tugas_id) REFERENCES tugas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opsi_tugas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pertanyaan_id INT UNSIGNED NOT NULL,
  label_opsi CHAR(1) NOT NULL,
  teks_opsi TEXT NOT NULL,
  is_benar TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_opsi_tugas (pertanyaan_id, label_opsi),
  CONSTRAINT fk_opsi_tugas
    FOREIGN KEY (pertanyaan_id) REFERENCES pertanyaan_tugas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jawaban_tugas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengumpulan_id INT UNSIGNED NOT NULL,
  pertanyaan_id INT UNSIGNED NOT NULL,
  opsi_id INT UNSIGNED DEFAULT NULL,
  jawaban_teks LONGTEXT DEFAULT NULL,
  benar TINYINT(1) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jawaban_tugas (pengumpulan_id, pertanyaan_id),
  KEY idx_jawaban_pertanyaan (pertanyaan_id),
  KEY idx_jawaban_opsi (opsi_id),
  CONSTRAINT fk_jawaban_pengumpulan
    FOREIGN KEY (pengumpulan_id) REFERENCES pengumpulan_tugas(id) ON DELETE CASCADE,
  CONSTRAINT fk_jawaban_pertanyaan
    FOREIGN KEY (pertanyaan_id) REFERENCES pertanyaan_tugas(id) ON DELETE CASCADE,
  CONSTRAINT fk_jawaban_opsi
    FOREIGN KEY (opsi_id) REFERENCES opsi_tugas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
