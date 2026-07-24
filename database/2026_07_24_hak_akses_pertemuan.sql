CREATE TABLE IF NOT EXISTS akses_pertemuan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengajaran_id INT UNSIGNED NOT NULL,
  pertemuan_ke TINYINT UNSIGNED NOT NULL,
  status ENUM('Dibuka','Dikunci') NOT NULL DEFAULT 'Dikunci',
  dibuka_pada DATETIME DEFAULT NULL,
  diperbarui_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_akses_pertemuan (pengajaran_id, pertemuan_ke),
  CONSTRAINT fk_akses_pertemuan_pengajaran FOREIGN KEY (pengajaran_id) REFERENCES pengajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO akses_pertemuan (pengajaran_id, pertemuan_ke, status, dibuka_pada)
SELECT sumber.pengajaran_id, sumber.pertemuan_ke, 'Dibuka', NOW()
FROM (
  SELECT pengajaran_id, pertemuan_ke FROM materi
  UNION
  SELECT pengajaran_id, pertemuan_ke FROM tugas
) AS sumber
ON DUPLICATE KEY UPDATE pengajaran_id = VALUES(pengajaran_id);
