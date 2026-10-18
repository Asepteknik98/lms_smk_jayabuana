-- Tahap 3. Jalankan manual setelah backup; tidak membuat ulang role/akun.
-- MariaDB 10.4+, InnoDB. Semua DATETIME KBM memakai Asia/Jakarta (UTC+7).
SET SESSION time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS kbm_periode (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tahun_ajaran VARCHAR(10) NOT NULL,
 semester ENUM('Ganjil','Genap') NOT NULL,
 mulai DATE NOT NULL, selesai DATE NOT NULL,
 dibuat_oleh INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_kbm_periode (tahun_ajaran,semester),
 CONSTRAINT fk_kbm_periode_admin FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_periode_tanggal CHECK(selesai>=mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_sesi_sekolah (
 kode VARCHAR(5) PRIMARY KEY, nama VARCHAR(20) NOT NULL,
 CONSTRAINT ck_kbm_sesi_kode CHECK(kode IN ('pagi','siang'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO kbm_sesi_sekolah(kode,nama) SELECT 'pagi','Pagi' WHERE NOT EXISTS(SELECT 1 FROM kbm_sesi_sekolah WHERE kode='pagi');
INSERT INTO kbm_sesi_sekolah(kode,nama) SELECT 'siang','Siang' WHERE NOT EXISTS(SELECT 1 FROM kbm_sesi_sekolah WHERE kode='siang');

CREATE TABLE IF NOT EXISTS kbm_pola_waktu (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 periode_id INT UNSIGNED NOT NULL, sesi_kode VARCHAR(5) NOT NULL,
 hari TINYINT UNSIGNED NOT NULL COMMENT 'ISO: Senin=1, Minggu=7',
 berlaku_mulai DATE NOT NULL, berlaku_sampai DATE NOT NULL,
 dibuat_oleh INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_kbm_pola_versi(periode_id,sesi_kode,hari,berlaku_mulai),
 KEY idx_kbm_pola_tanggal(periode_id,sesi_kode,hari,berlaku_mulai,berlaku_sampai),
 CONSTRAINT fk_kbm_pola_periode FOREIGN KEY(periode_id) REFERENCES kbm_periode(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_pola_sesi FOREIGN KEY(sesi_kode) REFERENCES kbm_sesi_sekolah(kode) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_pola_admin FOREIGN KEY(dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_pola_hari CHECK(hari BETWEEN 1 AND 7),
 CONSTRAINT ck_kbm_pola_tanggal CHECK(berlaku_sampai>=berlaku_mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_slot_waktu (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pola_id BIGINT UNSIGNED NOT NULL,
 urutan TINYINT UNSIGNED NOT NULL,
 jenis ENUM('JP','ISTIRAHAT') NOT NULL,
 jp TINYINT UNSIGNED NULL,
 mulai TIME NOT NULL, selesai TIME NOT NULL,
 UNIQUE KEY uk_kbm_slot_urutan(pola_id,urutan),
 UNIQUE KEY uk_kbm_slot_jp(pola_id,jp),
 CONSTRAINT fk_kbm_slot_pola FOREIGN KEY(pola_id) REFERENCES kbm_pola_waktu(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_slot_jp CHECK((jenis='JP' AND jp IS NOT NULL AND jp BETWEEN 1 AND 6) OR (jenis='ISTIRAHAT' AND jp IS NULL)),
 CONSTRAINT ck_kbm_slot_jam CHECK(mulai>='00:00:00' AND selesai<='23:59:59' AND selesai>mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_kelas_sesi (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 periode_id INT UNSIGNED NOT NULL, kelas_id INT UNSIGNED NOT NULL,
 sesi_kode VARCHAR(5) NOT NULL,
 berlaku_mulai DATE NOT NULL, berlaku_sampai DATE NOT NULL,
 dibuat_oleh INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_kbm_kelas_sesi_versi(periode_id,kelas_id,berlaku_mulai),
 KEY idx_kbm_kelas_sesi_tanggal(periode_id,kelas_id,berlaku_mulai,berlaku_sampai),
 CONSTRAINT fk_kbm_ks_periode FOREIGN KEY(periode_id) REFERENCES kbm_periode(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ks_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ks_sesi FOREIGN KEY(sesi_kode) REFERENCES kbm_sesi_sekolah(kode) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ks_admin FOREIGN KEY(dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_ks_tanggal CHECK(berlaku_sampai>=berlaku_mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_ketua_kelas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 periode_id INT UNSIGNED NOT NULL, kelas_id INT UNSIGNED NOT NULL, siswa_id INT UNSIGNED NOT NULL,
 berlaku_mulai DATE NOT NULL, berlaku_sampai DATE NOT NULL,
 dicabut_pada DATETIME NULL,
 dicabut_oleh INT UNSIGNED NULL,
 ditunjuk_oleh INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_kbm_ketua_kelas(periode_id,kelas_id,berlaku_mulai,berlaku_sampai),
 KEY idx_kbm_ketua_siswa(siswa_id,periode_id,berlaku_mulai,berlaku_sampai),
 CONSTRAINT fk_kbm_ketua_periode FOREIGN KEY(periode_id) REFERENCES kbm_periode(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ketua_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ketua_siswa FOREIGN KEY(siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ketua_admin FOREIGN KEY(ditunjuk_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_ketua_cabut FOREIGN KEY(dicabut_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_ketua_cabut CHECK((dicabut_pada IS NULL AND dicabut_oleh IS NULL) OR (dicabut_pada IS NOT NULL AND dicabut_oleh IS NOT NULL)),
 CONSTRAINT ck_kbm_ketua_tanggal CHECK(berlaku_sampai>=berlaku_mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_jadwal (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kelas_sesi_id BIGINT UNSIGNED NOT NULL, pola_id BIGINT UNSIGNED NOT NULL,
 pengajaran_id INT UNSIGNED NOT NULL,
 guru_id INT UNSIGNED NOT NULL, mapel_id INT UNSIGNED NOT NULL COMMENT 'Identitas historis dari pengajaran',
 pengajaran_snapshot JSON NOT NULL COMMENT 'Identitas dan nama saat jadwal dibuat; bukan master pengajaran baru',
 jp_mulai TINYINT UNSIGNED NOT NULL, jp_selesai TINYINT UNSIGNED NOT NULL,
 berlaku_mulai DATE NOT NULL, berlaku_sampai DATE NOT NULL,
 dibuat_oleh INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_kbm_jadwal_tanggal(kelas_sesi_id,berlaku_mulai,berlaku_sampai),
 KEY idx_kbm_jadwal_pengajaran(pengajaran_id,berlaku_mulai,berlaku_sampai),
 KEY idx_kbm_jadwal_guru(guru_id,berlaku_mulai,berlaku_sampai),
 CONSTRAINT fk_kbm_jadwal_ks FOREIGN KEY(kelas_sesi_id) REFERENCES kbm_kelas_sesi(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_jadwal_pola FOREIGN KEY(pola_id) REFERENCES kbm_pola_waktu(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_jadwal_pengajaran FOREIGN KEY(pengajaran_id) REFERENCES pengajaran(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_jadwal_guru FOREIGN KEY(guru_id) REFERENCES guru(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_jadwal_mapel FOREIGN KEY(mapel_id) REFERENCES mapel(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_jadwal_admin FOREIGN KEY(dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_jadwal_jp CHECK(jp_mulai BETWEEN 1 AND 6 AND jp_selesai BETWEEN jp_mulai AND 6),
 CONSTRAINT ck_kbm_jadwal_tanggal CHECK(berlaku_sampai>=berlaku_mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_hari_tanpa_kbm (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 periode_id INT UNSIGNED NOT NULL, tanggal DATE NOT NULL,
 kelas_id INT UNSIGNED NULL COMMENT 'NULL: semua kelas',
 sesi_kode VARCHAR(5) NULL COMMENT 'NULL: semua sesi',
 alasan VARCHAR(255) NOT NULL, dibuat_oleh INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_kbm_hari_tanggal(periode_id,tanggal,kelas_id,sesi_kode),
 CONSTRAINT fk_kbm_hari_periode FOREIGN KEY(periode_id) REFERENCES kbm_periode(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_hari_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_hari_sesi FOREIGN KEY(sesi_kode) REFERENCES kbm_sesi_sekolah(kode) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_hari_admin FOREIGN KEY(dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_hari_alasan CHECK(CHAR_LENGTH(TRIM(alasan))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_pertemuan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 jadwal_id BIGINT UNSIGNED NOT NULL, tanggal DATE NOT NULL,
 kelas_id INT UNSIGNED NOT NULL, guru_id INT UNSIGNED NOT NULL, mapel_id INT UNSIGNED NOT NULL,
 tahun_ajaran VARCHAR(10) NOT NULL, semester ENUM('Ganjil','Genap') NOT NULL,
 nama_kelas VARCHAR(50) NOT NULL, nama_guru VARCHAR(100) NOT NULL, nama_mapel VARCHAR(100) NOT NULL,
 sesi_kode VARCHAR(5) NOT NULL, nama_sesi VARCHAR(20) NOT NULL,
 jp_mulai TINYINT UNSIGNED NOT NULL, jp_selesai TINYINT UNSIGNED NOT NULL,
 rencana_mulai DATETIME NOT NULL, rencana_selesai DATETIME NOT NULL,
 jp_snapshot JSON NOT NULL,
 zona_waktu VARCHAR(32) NOT NULL DEFAULT 'Asia/Jakarta',
 dibuat_oleh INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_kbm_pertemuan_jadwal_tanggal(jadwal_id,tanggal),
 KEY idx_kbm_pertemuan_kelas(tanggal,kelas_id,rencana_mulai),
 KEY idx_kbm_pertemuan_guru(guru_id,tanggal),
 CONSTRAINT fk_kbm_pt_jadwal FOREIGN KEY(jadwal_id) REFERENCES kbm_jadwal(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_pt_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_pt_guru FOREIGN KEY(guru_id) REFERENCES guru(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_pt_mapel FOREIGN KEY(mapel_id) REFERENCES mapel(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_pt_admin FOREIGN KEY(dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_pt_jp CHECK(jp_mulai BETWEEN 1 AND 6 AND jp_selesai BETWEEN jp_mulai AND 6),
 CONSTRAINT ck_kbm_pt_jam CHECK(rencana_selesai>rencana_mulai AND DATE(rencana_mulai)=tanggal AND DATE(rencana_selesai)=tanggal),
 CONSTRAINT ck_kbm_pt_zona CHECK(zona_waktu='Asia/Jakarta')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_laporan (
 pertemuan_id BIGINT UNSIGNED PRIMARY KEY,
 checkin_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 checkin_siswa_id INT UNSIGNED NOT NULL, checkin_ketua_id BIGINT UNSIGNED NOT NULL,
 nama_pelapor_masuk VARCHAR(100) NOT NULL,
 checkout_at DATETIME NULL,
 checkout_siswa_id INT UNSIGNED NULL, checkout_ketua_id BIGINT UNSIGNED NULL,
 nama_pelapor_keluar VARCHAR(100) NULL,
 ditutup_sistem_pada DATETIME NULL COMMENT 'Bukan waktu checkout guru',
 CONSTRAINT fk_kbm_lp_pt FOREIGN KEY(pertemuan_id) REFERENCES kbm_pertemuan(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_lp_masuk FOREIGN KEY(checkin_siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_lp_keluar FOREIGN KEY(checkout_siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_lp_ketua_masuk FOREIGN KEY(checkin_ketua_id) REFERENCES kbm_ketua_kelas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_lp_ketua_keluar FOREIGN KEY(checkout_ketua_id) REFERENCES kbm_ketua_kelas(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_lp_checkout CHECK(
  (checkout_at IS NULL AND checkout_siswa_id IS NULL AND checkout_ketua_id IS NULL AND nama_pelapor_keluar IS NULL)
  OR (checkout_at IS NOT NULL AND checkout_at>=checkin_at AND checkout_siswa_id IS NOT NULL AND checkout_ketua_id IS NOT NULL AND nama_pelapor_keluar IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kbm_koreksi_admin (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pertemuan_id BIGINT UNSIGNED NOT NULL, admin_id INT UNSIGNED NOT NULL,
 alasan VARCHAR(1000) NOT NULL,
 checkin_lama DATETIME NULL, checkout_lama DATETIME NULL,
 checkin_baru DATETIME NULL, checkout_baru DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_kbm_koreksi_riwayat(pertemuan_id,id),
 CONSTRAINT fk_kbm_kr_pt FOREIGN KEY(pertemuan_id) REFERENCES kbm_pertemuan(id) ON DELETE RESTRICT,
 CONSTRAINT fk_kbm_kr_admin FOREIGN KEY(admin_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT ck_kbm_kr_alasan CHECK(CHAR_LENGTH(TRIM(alasan))>0),
 CONSTRAINT ck_kbm_kr_jam CHECK(checkout_baru IS NULL OR (checkin_baru IS NOT NULL AND checkout_baru>=checkin_baru))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
