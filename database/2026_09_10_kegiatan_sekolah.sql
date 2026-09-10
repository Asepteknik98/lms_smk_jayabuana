CREATE TABLE IF NOT EXISTS kegiatan_staf (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama_lengkap VARCHAR(100) NOT NULL,
 nip VARCHAR(30) NOT NULL DEFAULT '',
 UNIQUE KEY identitas (nama_lengkap, nip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kegiatan_sekolah (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tanggal DATE NOT NULL,
 kode VARCHAR(50) NOT NULL,
 nama VARCHAR(160) NOT NULL,
 disimpan_pada DATETIME NULL,
 disimpan_oleh INT UNSIGNED NULL,
 versi INT UNSIGNED NOT NULL DEFAULT 0,
 UNIQUE KEY jadwal (tanggal, kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kegiatan_kehadiran (
 kegiatan_id INT UNSIGNED NOT NULL,
 peserta VARCHAR(40) NOT NULL,
 nama_lengkap VARCHAR(100) NOT NULL,
 jenis VARCHAR(10) NOT NULL,
 hadir TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY (kegiatan_id, peserta),
 CONSTRAINT fk_kegiatan_kehadiran FOREIGN KEY (kegiatan_id) REFERENCES kegiatan_sekolah(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
