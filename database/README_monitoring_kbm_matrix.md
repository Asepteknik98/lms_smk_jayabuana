# Riwayat Waka dalam tabel kelas dan JP

Halaman `akademik/riwayat_kbm.php` menampilkan filter satu tanggal dan sesi (semua/pagi/siang), kemudian tabel **Kelas | JP 1 | JP 2 | JP 3 | JP 4 | JP 5 | JP 6**. Semua kelas sesuai sesi ditampilkan tanpa pagination. Kepala tabel dan kolom kelas melekat saat digeser.

- Ceklis hijau: check-in efektif tercatat. Bisa masih mengajar atau sudah check-out; detail membedakannya.
- Lingkaran merah dengan garis horizontal: laporan eksplisit **Guru Tidak Masuk** dari ketua kelas.
- Strip abu-abu: belum ada laporan masuk. Tidak dianggap bukti guru tidak hadir.
- Rentang JP 1–2 mengisi kedua kolom. Catatan dibatalkan tidak dihitung. Koreksi admin memakai nilai efektif dengan arsip aslinya tetap tersedia.
- Klik ikon membuka detail guru, waktu, pelapor, pembatalan, dan audit. Detail akademik tetap hanya baca, tanpa formulir koreksi. Filter panjang dan tabel pertemuan lama tidak ditampilkan pada detail Waka. Admin tetap memakai halaman riwayat/koreksi/Excel yang sudah ada.
- Filter sesi memakai snapshot laporan pada tanggal tersebut. Untuk kelas tanpa laporan, sesi memakai konfigurasi terakhir yang sudah mulai berlaku pada tanggal terpilih. Nama kelas historis pada laporan dipertahankan.

Tidak memerlukan migrasi atau perubahan data. Berkas baru: `includes/monitoring_kbm_matrix.php` dan `includes/monitoring_kbm_matrix_page.php`. Controller akademik dan tampilan detail bersama disesuaikan; endpoint tetap memeriksa role/status akun di server.

Pemeriksaan aktual: sintaks empat berkas PHP lulus. Uji integrasi `tests/monitoring-kbm-siswa.test.cjs` diperluas dan lulus (69,8 detik) pada server terisolasi: matriks 84 kelas tanpa pagination, enam JP, check-in hijau, laporan tidak masuk merah, sel belum terisi, filter sesi historis, catatan dibatalkan diabaikan, validasi tanggal/sesi, penolakan role lain, dan detail/audit lama. Pemeriksaan browser/perangkat nyata belum dilakukan. Database utama tidak diubah.

## Unduh Excel untuk Waka

Tombol **Unduh Excel** tersedia di bagian Rekap JP per Kelas. Pilih tanggal/sesi, tekan Tampilkan, lalu Unduh Excel. Hasil memakai filter yang sudah diterapkan, seluruh kelas, kolom No/Kelas/Sesi/JP 1–6, metadata tanggal/sesi, dan legenda. Simbol ✓ berwarna hijau, ⊖ merah, dan — abu-abu. Nama file memuat tanggal dan sesi.

Endpoint `akademik/rekap_kbm_excel.php` hanya menerima GET dari akun akademik aktif. Unduhan ini bersifat baca saja; akses akademik ke ekspor riwayat lengkap milik admin tetap ditolak. Format memakai writer SpreadsheetML `.xls` yang sudah tersedia, dengan pewarnaan opsional `status_colors`/`status_columns`; pemanggil lama tidak berubah. Semua teks bertipe String sehingga tidak ditafsirkan sebagai formula. Tidak mengekspor data akun, password, atau token.

File selesai disiapkan sebelum dikirim. Hasil kosong/invalid menampilkan pemberitahuan di halaman rekap. Batas 5.000 kelas/25 MB ditolak dengan pesan, tidak dipotong diam-diam. Tidak membutuhkan migrasi tambahan.

Pemeriksaan aktual pembaruan unduhan: **44 pengujian lulus, 0 gagal** pada suite lengkap (82,7 detik). Meliputi round-trip pembacaan workbook, validitas XML, jumlah kelas/baris, isi enam JP sesuai matriks, sesi pagi, warna simbol, nama file, tipe teks tanpa formula, request unduhan nyata melalui controller, penolakan guru/siswa/admin/akun nonaktif/tanpa login/POST, hasil kosong, serta fitur LMS lain. Sintaks empat berkas PHP berubah lulus. Belum diuji dengan membuka aplikasi Microsoft Excel desktop secara manual. Database utama tidak diubah.
