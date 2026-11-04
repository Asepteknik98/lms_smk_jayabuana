-- Koreksi nama sekolah pada master J.2. Riwayat pelanggaran tidak diubah.
UPDATE kredit_master_pelanggaran
SET nama=REPLACE(nama,'SMK Taruna Bhakti','SMK JAYA BUANA')
WHERE kode='J.2' AND nama LIKE '%SMK Taruna Bhakti%';
