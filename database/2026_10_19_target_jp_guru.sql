-- Tambahan target JP; tidak mengubah realisasi/validasi/pengajaran lama.
-- Terapkan manual setelah migrasi rekap mengajar yang sudah tersedia.
CREATE TABLE IF NOT EXISTS `target_jp_guru` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id` INT UNSIGNED NOT NULL,
  `berlaku_mulai` DATE NOT NULL COMMENT 'Senin awal berlakunya pola; diteruskan sampai pola baru',
  `senin` TINYINT UNSIGNED NOT NULL,
  `selasa` TINYINT UNSIGNED NOT NULL,
  `rabu` TINYINT UNSIGNED NOT NULL,
  `kamis` TINYINT UNSIGNED NOT NULL,
  `jumat` TINYINT UNSIGNED NOT NULL,
  `sabtu` TINYINT UNSIGNED NOT NULL,
  `minggu` TINYINT UNSIGNED NOT NULL,
  `dibuat_oleh` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target_jp_guru_berlaku` (`guru_id`,`berlaku_mulai`,`id`),
  CONSTRAINT `fk_target_jp_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_target_jp_admin` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_target_jp_senin` CHECK (DAYOFWEEK(`berlaku_mulai`)=2),
  CONSTRAINT `chk_target_jp_harian` CHECK (`senin`<=12 AND `selasa`<=12 AND `rabu`<=12 AND `kamis`<=12 AND `jumat`<=12 AND `sabtu`<=12 AND `minggu`<=12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
