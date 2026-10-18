# Tampilan pengguna pelapor di HP

Perubahan tampilan pada `includes/monitoring_kbm_pilihan_page.php` dan CSS
terbatas `.kbm-student-page` di `assets/css/monitoring-kbm-student.css`.

- Identitas kelas, tombol muat ulang dan jarak antarpanel lebih ringkas.
- JP menggunakan tiga kolom pada layar hingga 767px; warna dan penguncian
  tetap berasal dari state JP yang sudah ada. Desktop tetap enam kolom.
- Panduan pemilihan JP dan pembatalan dapat dibuka/tutup.
- Dropdown guru native tetap 16px agar nyaman digunakan di ponsel.
- Tabel desktop tetap tersedia. Pada HP, laporan menjadi kartu per rentang
  JP. Baris lanjutan rentang disembunyikan dan rentang lengkap ditampilkan
  pada kartu pertama. Form check-in/out dan pembatalan hanya satu per rentang,
  memakai elemen serta payload server yang sama. Tidak menduplikasi form/ID.

Tidak mengubah query, CSRF, validasi, status, pencatatan, audit, atau hak akses.
Tidak ada migrasi.

Pemeriksaan yang benar-benar dijalankan:

- PHP lint: lulus.
- Integrasi `tests/monitoring-kbm-siswa.test.cjs` pada salinan TEMP dengan
  MariaDB terisolasi port 3307: lulus (74 detik), termasuk pencatatan,
  penolakan akses, klik ganda, request bersamaan, pembatalan dan riwayat.
- Fixture tata letak memakai CSS halaman dengan Chrome headless/CDP pada
  viewport mobile 360, 390, 430px: lebar konten sama dengan viewport, JP tiga
  kolom, tinggi tombol 59px, select 16px, baris lanjutan rentang tersembunyi.
  Ini pemeriksaan fixture CSS, bukan pemeriksaan visual aplikasi pada HP asli.
- Database utama tidak dimigrasi. Belum diuji langsung pada Safari iPhone
  maupun perangkat Android.
