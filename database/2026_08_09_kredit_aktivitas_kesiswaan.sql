-- Modul Kredit Aktivitas Kesiswaan (KAK). Jalankan sekali sebelum deploy kode.
CREATE TABLE IF NOT EXISTS `kak_target_tingkat` (
  `tingkat` enum('X','XI','XII') NOT NULL,
  `target_poin` int unsigned NOT NULL DEFAULT 0,
  `diubah_oleh` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tingkat`), KEY `fk_kak_target_guru` (`diubah_oleh`),
  CONSTRAINT `fk_kak_target_guru` FOREIGN KEY (`diubah_oleh`) REFERENCES `guru` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `kak_target_tingkat` (`tingkat`,`target_poin`) VALUES ('X',0),('XI',0),('XII',0);
CREATE TABLE IF NOT EXISTS `kak_aktivitas_siswa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `siswa_id` int unsigned NOT NULL,
  `guru_id` int unsigned NOT NULL,
  `nama_aktivitas` varchar(150) NOT NULL,
  `keterangan` varchar(500) DEFAULT NULL,
  `tanggal_aktivitas` date NOT NULL,
  `poin` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_kak_siswa_tanggal` (`siswa_id`,`tanggal_aktivitas`,`id`), KEY `idx_kak_guru` (`guru_id`),
  CONSTRAINT `fk_kak_aktivitas_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kak_aktivitas_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_kak_poin_positif` CHECK (`poin` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
