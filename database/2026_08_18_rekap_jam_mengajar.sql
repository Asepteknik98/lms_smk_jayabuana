-- Rekap Jam Mengajar Guru
-- Jalankan satu kali pada database production sebelum mengunggah halaman PHP.

ALTER TABLE `pengajaran`
  ADD COLUMN IF NOT EXISTS `alokasi_jp_mingguan` DECIMAL(6,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `semester`;

CREATE TABLE IF NOT EXISTS `rekap_mengajar` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pengajaran_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `sesi` VARCHAR(100) NOT NULL,
  `jumlah_jp` DECIMAL(6,2) UNSIGNED NOT NULL,
  `dibuat_oleh` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rekap_mengajar_tanggal` (`tanggal`),
  KEY `idx_rekap_mengajar_pengajaran_tanggal` (`pengajaran_id`,`tanggal`),
  KEY `idx_rekap_mengajar_admin` (`dibuat_oleh`),
  CONSTRAINT `fk_rekap_mengajar_pengajaran`
    FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_rekap_mengajar_admin`
    FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_rekap_mengajar_jp` CHECK (`jumlah_jp` > 0),
  CONSTRAINT `chk_rekap_mengajar_jam` CHECK (`jam_selesai` > `jam_mulai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
