CREATE TABLE IF NOT EXISTS kak_master_aktivitas (
 id int unsigned NOT NULL AUTO_INCREMENT,
 kode varchar(20) NOT NULL,
 tingkat enum('X','XI','XII') NOT NULL,
 nama varchar(150) NOT NULL,
 poin int unsigned NOT NULL,
 aktif tinyint(1) NOT NULL DEFAULT 1,
 urutan int unsigned NOT NULL DEFAULT 0,
 diubah_oleh int unsigned DEFAULT NULL,
 updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 PRIMARY KEY(id), UNIQUE KEY uq_kak_master_kode(kode), KEY idx_kak_master_tingkat(aktif,tingkat,urutan),
 CONSTRAINT fk_kak_master_admin FOREIGN KEY(diubah_oleh) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_kak_master_poin CHECK(poin BETWEEN 1 AND 100000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE kak_acara ADD COLUMN IF NOT EXISTS master_aktivitas_id int unsigned DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS tingkat_aktivitas enum('X','XI','XII') DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS poin_standar int unsigned DEFAULT NULL;
ALTER TABLE kak_acara ADD INDEX IF NOT EXISTS idx_kak_master_aktivitas(master_aktivitas_id);

START TRANSACTION;
SET @kak_inisialisasi_target = (SELECT COUNT(*)=0 FROM kak_master_aktivitas);
INSERT INTO kak_master_aktivitas(kode,tingkat,nama,poin,urutan) VALUES
('X.01','X','MPLS',50,1),
('X.02','X','Kegiatan Sekolah (PHBI, Hari Besar)',5,2),
('X.03','X','UTS SMT 1',15,3),
('X.04','X','Prestasi Akademik',5,4),
('X.05','X','Prestasi Non Akademik',5,5),
('X.06','X','Mengikuti Ekstrakurikuler',5,6),
('X.07','X','Mengikuti Latihan Dasar Himpunan Siswa Jurusan',5,7),
('X.08','X','Mengikuti Organisasi Siswa Intra Sekolah',10,8),
('X.09','X','Selesai UAS SMT 1',10,9),
('X.10','X','Selesai UTS SMT 2',10,10),
('XI.01','XI','Pembekalan Prakerin',30,1),
('XI.02','XI','Kegiatan Sekolah (PHBI, Hari Besar)',2,2),
('XI.03','XI','UTS SMT 1',15,3),
('XI.04','XI','Prestasi Lomba Akademik',5,4),
('XI.05','XI','Prestasi Lomba Non Akademik',5,5),
('XI.06','XI','Pengurus Ekstrakurikuler',4,6),
('XI.07','XI','Pengurus OSIS',5,7),
('XI.08','XI','Ketuntasan Prakerin / Kunjungan Industri',20,8),
('XI.09','XI','Mengikuti Sidang Prakerin',25,9),
('XI.10','XI','Selesai UTS SMT 1',10,10),
('XI.11','XI','Selesai UAS SMT 1',10,11),
('XII.01','XII','BDP',30,1),
('XII.02','XII','Kegiatan Sekolah (PHBI, Hari Besar)',2,2),
('XII.03','XII','UTS SMT 1',15,3),
('XII.04','XII','Prestasi Lomba Akademik',5,4),
('XII.05','XII','Prestasi Lomba Non Akademik',5,5),
('XII.06','XII','Pengurus Ekstrakurikuler',4,6),
('XII.07','XII','Pengurus MPK / OSIS',5,7),
('XII.08','XII','Mengikuti Training UKK',20,8),
('XII.09','XII','Selesai Mengikuti UKK',25,9),
('XII.10','XII','Selesai Kelas Industri',20,10),
('XII.11','XII','Selesai UAS SMT 1',10,11),
('XII.12','XII','Selesai UTS SMT 1',10,12)
ON DUPLICATE KEY UPDATE kode=VALUES(kode);
-- Sekali saat katalog pertama diisi: target 100 per tingkat, total kumulatif 300.
-- Pengulangan migrasi tidak menimpa target yang kemudian disesuaikan admin/guru.
INSERT INTO kak_target_tingkat(tingkat,target_poin)
SELECT tingkat,100 FROM (SELECT 'X' tingkat UNION ALL SELECT 'XI' UNION ALL SELECT 'XII') t
WHERE @kak_inisialisasi_target=1
ON DUPLICATE KEY UPDATE target_poin=VALUES(target_poin);
COMMIT;
