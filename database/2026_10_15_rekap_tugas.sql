-- Tambahan khusus status rekap tugas; tabel dan nilai lama tidak diubah.
CREATE TABLE IF NOT EXISTS rekap_tugas_status (
 tugas_id INT UNSIGNED NOT NULL,
 siswa_id INT UNSIGNED NOT NULL,
 guru_id INT UNSIGNED NOT NULL,
 status ENUM('mengerjakan','tidak_mengerjakan','belum_dinilai') NOT NULL,
 catatan VARCHAR(1000) NOT NULL DEFAULT '',
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (tugas_id,siswa_id),
 KEY idx_rekap_tugas_siswa (siswa_id),
 CONSTRAINT fk_rekap_tugas_tugas FOREIGN KEY (tugas_id) REFERENCES tugas(id) ON DELETE CASCADE,
 CONSTRAINT fk_rekap_tugas_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
 CONSTRAINT fk_rekap_tugas_guru_cascade FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
