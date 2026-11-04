-- Langkah 3: penyimpanan rapor. Jalankan secara manual setelah disetujui.
-- Tidak mengubah tabel atau nilai sumber yang sudah ada.
CREATE TABLE IF NOT EXISTS rapor (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 siswa_id INT UNSIGNED NOT NULL,
 kelas_id INT UNSIGNED NULL,
 tahun_ajaran VARCHAR(10) NOT NULL,
 semester ENUM('Ganjil','Genap') NOT NULL,
 jenis ENUM('UTS','UAS') NOT NULL,
 nama_siswa VARCHAR(100) NOT NULL,
 nis VARCHAR(20) NOT NULL DEFAULT '',
 nisn VARCHAR(20) NOT NULL DEFAULT '',
 nama_kelas VARCHAR(50) NOT NULL,
 jurusan VARCHAR(100) NOT NULL DEFAULT '',
 nomor_absen SMALLINT UNSIGNED NULL,
 sakit SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 izin SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 alfa SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 catatan_wali_kelas TEXT NULL,
 nama_wali_kelas VARCHAR(100) NOT NULL DEFAULT '',
 nama_kepala_sekolah VARCHAR(100) NOT NULL DEFAULT '',
 tempat_terbit VARCHAR(100) NOT NULL DEFAULT '',
 tanggal_terbit DATE NULL,
 created_by INT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_rapor_siswa_periode (siswa_id,tahun_ajaran,semester,jenis),
 KEY idx_rapor_cetak_kelas (kelas_id,tahun_ajaran,semester,jenis),
 CONSTRAINT fk_rapor_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rapor_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL,
 CONSTRAINT fk_rapor_pembuat FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_rapor_pengubah FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rapor_mapel (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 rapor_id INT UNSIGNED NOT NULL,
 pengajaran_id INT UNSIGNED NULL,
 nama_mapel VARCHAR(100) NOT NULL,
 nama_guru VARCHAR(100) NOT NULL,
 kelompok VARCHAR(50) NOT NULL DEFAULT '',
 urutan SMALLINT UNSIGNED NOT NULL,
 nilai_akhir DECIMAL(5,2) UNSIGNED NULL,
 deskripsi TEXT NULL,
 saran TEXT NULL,
 sumber_diambil_pada DATETIME NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_rapor_mapel_pengajaran (rapor_id,pengajaran_id),
 KEY idx_rapor_mapel_urutan (rapor_id,urutan,id),
 CONSTRAINT fk_rapor_mapel_induk FOREIGN KEY (rapor_id) REFERENCES rapor(id) ON DELETE CASCADE,
 CONSTRAINT fk_rapor_mapel_sumber FOREIGN KEY (pengajaran_id) REFERENCES pengajaran(id) ON DELETE SET NULL,
 CONSTRAINT ck_rapor_mapel_nilai CHECK (nilai_akhir IS NULL OR nilai_akhir <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rapor_ekstrakurikuler (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 rapor_id INT UNSIGNED NOT NULL,
 nama_kegiatan VARCHAR(150) NOT NULL,
 grade ENUM('A','B','C','D') NULL,
 urutan SMALLINT UNSIGNED NOT NULL,
 PRIMARY KEY (id),
 KEY idx_rapor_ekstra_urutan (rapor_id,urutan,id),
 CONSTRAINT fk_rapor_ekstra_induk FOREIGN KEY (rapor_id) REFERENCES rapor(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
