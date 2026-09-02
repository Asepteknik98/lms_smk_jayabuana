-- Revisi rekap JP: sesi/jam pelajaran dan validasi bulanan guru.
-- Jalankan setelah database/2026_08_18_rekap_jam_mengajar.sql.

ALTER TABLE `rekap_mengajar`
  ADD COLUMN IF NOT EXISTS `jenis_sesi` ENUM('Pagi','Siang') NULL AFTER `tanggal`,
  ADD COLUMN IF NOT EXISTS `jam_ke_mulai` TINYINT UNSIGNED NULL AFTER `jenis_sesi`,
  ADD COLUMN IF NOT EXISTS `jam_ke_selesai` TINYINT UNSIGNED NULL AFTER `jam_ke_mulai`;

CREATE TABLE IF NOT EXISTS `validasi_rekap_mengajar` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id` INT UNSIGNED NOT NULL,
  `periode_bulan` DATE NOT NULL COMMENT 'Tanggal pertama bulan terkait',
  `total_jp` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `divalidasi_oleh` INT UNSIGNED NOT NULL,
  `divalidasi_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_validasi_guru_bulan` (`guru_id`,`periode_bulan`),
  KEY `idx_validasi_periode` (`periode_bulan`),
  CONSTRAINT `fk_validasi_rekap_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_validasi_rekap_user` FOREIGN KEY (`divalidasi_oleh`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
