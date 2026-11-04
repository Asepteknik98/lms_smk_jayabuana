-- Riwayat khusus rapor. Tidak membuat aktivitas lama secara otomatis.
CREATE TABLE IF NOT EXISTS rapor_riwayat (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 rapor_id INT UNSIGNED NULL,
 kelas_id INT UNSIGNED NOT NULL,
 tahun_ajaran VARCHAR(10) NOT NULL,
 semester VARCHAR(10) NOT NULL,
 jenis VARCHAR(3) NOT NULL,
 nama_siswa VARCHAR(100) NOT NULL DEFAULT '',
 user_id INT UNSIGNED NOT NULL,
 nama_pengguna VARCHAR(100) NOT NULL,
 tindakan VARCHAR(40) NOT NULL,
 ringkasan VARCHAR(255) NOT NULL,
 perubahan LONGTEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_rapor_riwayat_periode (kelas_id,tahun_ajaran,semester,jenis,id),
 KEY idx_rapor_riwayat_siswa (rapor_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
