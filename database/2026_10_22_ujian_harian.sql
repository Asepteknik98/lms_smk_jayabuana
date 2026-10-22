-- Tambahan saja; data ujian dan nilai lama tetap dipertahankan.
CREATE TABLE IF NOT EXISTS ujian_harian_pengaturan (
 ujian_id INT UNSIGNED PRIMARY KEY,
 nomor INT UNSIGNED NOT NULL,
 metode ENUM('cbt','berkas') NOT NULL DEFAULT 'cbt',
 penerima ENUM('kelas','pilihan') NOT NULL DEFAULT 'kelas',
 instruksi TEXT NULL,
 CONSTRAINT fk_uh_pengaturan FOREIGN KEY (ujian_id) REFERENCES ujian(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nomor historis berdasarkan urutan pembuatan, hanya untuk UH (Kuis).
INSERT INTO ujian_harian_pengaturan(ujian_id,nomor,metode,penerima)
SELECT u.id,(SELECT COUNT(*) FROM ujian n WHERE n.pengajaran_id=u.pengajaran_id AND n.jenis_ujian='Kuis' AND n.id<=u.id),'cbt','kelas'
FROM ujian u WHERE u.jenis_ujian='Kuis'
AND NOT EXISTS(SELECT 1 FROM ujian_harian_pengaturan h WHERE h.ujian_id=u.id);
CREATE TABLE IF NOT EXISTS ujian_harian_penerima (
 ujian_id INT UNSIGNED NOT NULL,
 siswa_id INT UNSIGNED NOT NULL,
 PRIMARY KEY(ujian_id,siswa_id),
 CONSTRAINT fk_uh_penerima_ujian FOREIGN KEY (ujian_id) REFERENCES ujian(id) ON DELETE CASCADE,
 CONSTRAINT fk_uh_penerima_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ujian_harian_berkas (
 ujian_id INT UNSIGNED NOT NULL,
 siswa_id INT UNSIGNED NOT NULL,
 nama_file VARCHAR(100) NOT NULL,
 dikumpulkan_pada DATETIME NOT NULL,
 catatan_guru TEXT NULL,
 PRIMARY KEY(ujian_id,siswa_id),
 CONSTRAINT fk_uh_berkas_ujian FOREIGN KEY (ujian_id) REFERENCES ujian(id) ON DELETE CASCADE,
 CONSTRAINT fk_uh_berkas_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
