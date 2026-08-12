-- Jalankan satu kali sebelum mengunggah kode fitur tugas offline ke production.
ALTER TABLE tugas
  ADD COLUMN IF NOT EXISTS metode_pengumpulan ENUM('online','offline') NOT NULL DEFAULT 'online' AFTER jenis_tugas;
