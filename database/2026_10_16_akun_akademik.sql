-- Tahap 2: role dan profil Akademik. Tidak membuat akun/password bawaan.
-- Jalankan secara manual pada database tujuan setelah backup.
-- ID role menggunakan AUTO_INCREMENT proyek, tidak mengubah ID role lama.
INSERT INTO roles (nama_role)
SELECT 'Akademik'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nama_role='Akademik');

CREATE TABLE IF NOT EXISTS akademik_profil (
  user_id INT UNSIGNED NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_akademik_profil_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
