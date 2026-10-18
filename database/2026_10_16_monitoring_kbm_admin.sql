-- Tahap 4. Terapkan manual sesudah migrasi fondasi KBM. Tidak mengubah role/data lama.
ALTER TABLE kbm_jadwal ADD COLUMN IF NOT EXISTS nonaktif_mulai DATE NULL;
ALTER TABLE kbm_jadwal ADD COLUMN IF NOT EXISTS diganti_dari BIGINT UNSIGNED NULL;
CREATE TABLE IF NOT EXISTS kbm_perubahan_jadwal (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 jadwal_id BIGINT UNSIGNED NOT NULL,
 jadwal_baru_id BIGINT UNSIGNED NULL,
 berlaku_mulai DATE NOT NULL,
 admin_id INT UNSIGNED NOT NULL,
 alasan VARCHAR(1000) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_kbm_perubahan_jadwal(jadwal_id),
 CONSTRAINT fk_kbm_ubah_lama FOREIGN KEY(jadwal_id) REFERENCES kbm_jadwal(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ubah_baru FOREIGN KEY(jadwal_baru_id) REFERENCES kbm_jadwal(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ubah_admin FOREIGN KEY(admin_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_ubah_alasan CHECK(CHAR_LENGTH(TRIM(alasan))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
