-- Pengaturan judul saja; tidak mengubah data atau nilai rapor.
CREATE TABLE IF NOT EXISTS rapor_pengaturan_judul (
 kelas_id INT UNSIGNED NOT NULL,
 tahun_ajaran VARCHAR(10) NOT NULL,
 semester ENUM('Ganjil','Genap') NOT NULL,
 jenis ENUM('UTS','UAS') NOT NULL,
 judul VARCHAR(160) NOT NULL DEFAULT '',
 penilaian VARCHAR(160) NOT NULL DEFAULT '',
 konsentrasi VARCHAR(120) NOT NULL DEFAULT '',
 updated_by INT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(kelas_id,tahun_ajaran,semester,jenis),
 CONSTRAINT fk_rapor_judul_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
 CONSTRAINT fk_rapor_judul_pengubah FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
