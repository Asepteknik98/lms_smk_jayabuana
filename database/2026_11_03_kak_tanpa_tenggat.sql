-- Kolom jadwal lama dipertahankan untuk arsip, tidak digunakan sebagai syarat klaim.
-- Acara baru tidak lagi perlu menyimpan tanggal buka/tutup klaim.
ALTER TABLE kak_acara MODIFY COLUMN klaim_mulai datetime DEFAULT NULL,
                      MODIFY COLUMN klaim_sampai datetime DEFAULT NULL;
