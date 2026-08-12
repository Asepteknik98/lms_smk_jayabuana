-- Jalankan satu kali jika tabel nilai_tugas_manual sudah dibuat di production.
-- Nilai manual lama dipertahankan sebagai nilai Pertemuan 1.
ALTER TABLE nilai_tugas_manual
  ADD COLUMN IF NOT EXISTS pertemuan_ke TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER siswa_id;

ALTER TABLE nilai_tugas_manual
  DROP INDEX uk_nilai_tugas_manual,
  ADD UNIQUE KEY uk_nilai_tugas_manual (pengajaran_id,siswa_id,pertemuan_ke);
