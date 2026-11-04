-- Pengaturan administrasi khusus rapor; tidak mengubah tabel nilai atau kelas.
CREATE TABLE IF NOT EXISTS rapor_administrasi (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 kelas_id INT UNSIGNED NOT NULL,
 tahun_ajaran VARCHAR(10) NOT NULL,
 semester ENUM('Ganjil','Genap') NOT NULL,
 jenis ENUM('UTS','UAS') NOT NULL,
 wali_guru_id INT UNSIGNED NULL,
 nama_wali_kelas VARCHAR(100) NOT NULL DEFAULT '',
 nama_kepala_sekolah VARCHAR(100) NOT NULL DEFAULT '',
 tempat_terbit VARCHAR(100) NOT NULL DEFAULT '',
 tanggal_terbit DATE NULL,
 terkunci TINYINT(1) NOT NULL DEFAULT 0,
 updated_by INT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_rapor_administrasi (kelas_id,tahun_ajaran,semester,jenis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
