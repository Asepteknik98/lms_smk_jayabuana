-- Pelaporan per JP dengan guru pilihan ketua kelas. Terapkan manual setelah backup.
-- Tidak menghapus jadwal atau mengubah laporan lama. MariaDB 10.4.
ALTER TABLE kbm_jadwal
 MODIFY pengajaran_id INT UNSIGNED NULL,
 MODIFY mapel_id INT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS tanggal_pilihan DATE NULL,
 ADD COLUMN IF NOT EXISTS dipilih_siswa_id INT UNSIGNED NULL,
 ADD UNIQUE INDEX IF NOT EXISTS uk_kbm_pilihan_jp(kelas_sesi_id,tanggal_pilihan,jp_mulai);
ALTER TABLE kbm_pertemuan MODIFY mapel_id INT UNSIGNED NULL;
-- Guru tanpa pengajaran pada kelas tersebut tidak dibuatkan pengajaran/mapel fiktif.
-- tanggal_pilihan NULL untuk jadwal admin lama; tanggal terisi untuk pilihan harian.
