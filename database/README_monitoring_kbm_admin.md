# Pengaturan Monitoring KBM — tahap 4

## Penerapan

Cadangkan database dan uji pada staging. Terapkan migrasi tahap 3 `2026_10_16_monitoring_kbm_data.sql`, kemudian **secara manual** jalankan `2026_10_16_monitoring_kbm_admin.sql` pada database LMS melalui phpMyAdmin. Migrasi tidak dipanggil oleh halaman PHP. Migrasi tahap 4 menambah dua kolom (`nonaktif_mulai`, `diganti_dari`) pada `kbm_jadwal` serta tabel audit `kbm_perubahan_jadwal`. Dapat dijalankan ulang pada struktur yang sama, tidak membuat ulang role atau akun dan tidak menghapus data.

Deploy perubahan helper dan halaman bersamaan dengan migrasi tahap 4. Helper pembaca jadwal kini menggunakan kolom versi baru. Bila migrasi belum lengkap, halaman admin hanya menampilkan petunjuk dan tidak menyediakan formulir perubahan data. DDL MariaDB melakukan implicit commit; periksa struktur jika penerapan terhenti. Tidak ada migrasi database produksi yang dijalankan oleh pengerjaan tahap ini.

## Cara admin menggunakan

Buka **Master Data → Pengaturan Monitoring KBM** (`admin/monitoring_kbm.php`). Pilih periode di bagian atas dan klik **Tampilkan Periode**. Konfigurasi semester lama tetap bisa dilihat dengan pemilih periode.

1. **Periode:** masukkan tahun ajaran (contoh `2026/2027`), semester, tanggal awal dan akhir. Periode unik per tahun/semester.
2. **Sesi Kelas:** pilih kelas dari master LMS, sesi pagi/siang dan rentang tanggal dalam periode. Satu kelas tidak boleh memiliki penempatan yang bertumpang tindih.
3. **Waktu JP:** pilih sesi dan hari; isi jam mulai/selesai JP 1–6 dan, bila ada, sampai tiga waktu istirahat. Waktu berbeda per sesi/hari diperbolehkan. Buat pola untuk setiap hari yang digunakan jadwal. Gunakan WIB. Slot harus berurutan, tidak beririsan; selesai lebih besar dari mulai. Pola yang telah digunakan disimpan sebagai arsip; versi baru menggunakan rentang yang tidak bertumpang tindih. Tahap ini belum menyediakan pemendekan masa berlaku pola/penempatan kelas lama yang sudah terisi penuh.
4. **Ketua Kelas:** pilih kelas, klik **Tampilkan Siswa**, pilih siswa aktif dan masa berlaku. Jika mengganti ketua, klik **Cabut Penugasan** pada penugasan sebelumnya lalu tunjuk pengganti. Pencabutan langsung berlaku, bukan penghapusan siswa/laporan. Akun siswa dan keanggotaan kelas dicek lagi di server; satu ketua belum dicabut per kelas untuk rentang yang sama. Daftar juga memberi keterangan akun nonaktif, siswa pindah, terjadwal, berakhir, atau dicabut.
5. **Jadwal KBM:** pilih kelas/sesi, hari/pola JP, pasangan guru dan mapel dari master Pengajaran, JP mulai/selesai, serta tanggal berlaku. Pilihan guru/mapel dan sesi waktu dibantu penyaringan di browser; semua kecocokan tetap diperiksa server. Bila belum ada Pengajaran pada periode ini, lengkapi melalui menu Pengajaran yang sudah ada. Tidak ada sumber jadwal kedua di luar `kbm_jadwal` tahap 3.
6. **Hari Tanpa KBM:** isi tanggal dan alasan, batasi kelas/sesi bila perlu; pilihan kosong berarti semua. Penambahan hanya mulai besok agar laporan masa lalu dan KBM hari berjalan tidak berubah.

Panel “Konfigurasi yang perlu dilengkapi” memberi petunjuk tentang periode, sesi kelas, pola JP, pengajaran, ketua kelas, atau jadwal yang belum tersedia. Tidak adanya jadwal pada suatu hari berarti tidak ada KBM; admin tidak harus membuat jadwal untuk ketujuh hari.

## Edit dan penonaktifan jadwal

- Pilih **Edit Jadwal** pada baris jadwal. Tetapkan tanggal efektif (paling cepat besok), pilih pengaturan pengganti dan isi alasan. Edit dibatasi pada kelas/periode yang sama. Untuk memindahkan ke kelas lain, nonaktifkan jadwal lama dan buat jadwal baru.
- Jadwal lama ditandai `nonaktif_mulai`; jadwal pengganti memiliki ID baru dan `diganti_dari`. Pengaturan lama tidak ditimpa, serta audit menyimpan admin, alasan, tanggal efektif dan ID kedua versi. Masa berlaku versi pengganti dimulai pada maksimum tanggal efektif dan awal jadwal lama.
- Klik **Nonaktifkan Jadwal**, isi tanggal efektif dan alasan, lalu konfirmasi. Tidak ada tombol hapus permanen.
- Perubahan/penonaktifan berlaku paling cepat besok (WIB), bukan di tengah KBM hari berjalan. Tanggal efektif tidak boleh melewati akhir masa berlaku jadwal. Pertemuan pada rentang perubahan yang telah dimulai, memiliki laporan atau koreksi admin menyebabkan perubahan ditolak.
- Pertemuan masa lalu tetap disimpan dan ditampilkan. Pertemuan masa depan yang sudah dibuat tetapi belum berlangsung tidak dihapus; pembaca laporan mengabaikan versi jadwal lama mulai tanggal nonaktif dan menampilkan versi penggantinya. Jika nonaktif tanpa pengganti, tidak ada blok aktif sesudah tanggal efektif. Baris pertemuan lama masih tersedia sebagai arsip internal.
- Edit kedua dilakukan pada versi pengganti. Satu versi lama hanya dapat ditutup sekali. Bila versi baru bentrok/tidak valid, transaksi dibatalkan termasuk tanda nonaktif jadwal lama.
- Jadwal baru tidak boleh dimulai sebelum hari ini; jika memilih hari ini, blok KBM yang sudah mulai tidak dapat ditambahkan. Pola dan penempatan kelas dapat diawali pada awal semester untuk konfigurasi awal, tetapi penambahan jadwal tetap dibatasi waktu di atas.

Bentrok diperiksa untuk kelas yang sama **atau guru yang sama**, pada hari dan masa berlaku yang beririsan, berdasarkan jam sebenarnya (termasuk lintas sesi/periode). JP pada blok harus sesuai pola sesi kelas. Blok yang melintasi istirahat ditolak; buat jadwal terpisah di kedua sisi istirahat. Hak tulis selalu memerlukan admin aktif; query menggunakan parameter, semua form POST memakai CSRF, notifikasi dan redirect setelah POST. Form gagal mempertahankan nilai input di session untuk diperbaiki. Tidak ada endpoint pelaporan siswa atau monitoring Akademik baru pada tahap ini.

## File dan pemeriksaan

- `admin/monitoring_kbm.php`: halaman pengaturan enam bagian dan pesan kesiapan migrasi.
- `includes/monitoring_kbm_admin.php`: validasi input admin, kesiapan skema, dispatcher perubahan, revisi/penonaktifan atomik.
- `includes/monitoring_kbm_data.php`: insert jadwal internal untuk transaksi revisi; pembaca jadwal/laporan mengikuti masa aktif versi.
- `includes/sidebar.php`: tautan khusus area admin.
- `database/2026_10_16_monitoring_kbm_admin.sql`: migrasi tambahan.
- `tests/monitoring-kbm.test.cjs`: migrasi dua kali di MariaDB sementara; validasi fondasi serta revisi, rollback konflik, larangan perubahan retroaktif, penonaktifan dan arsip. Render keenam tab memakai guard proyek, memeriksa larangan akses guru/siswa/Akademik, CSRF salah tidak menulis, pesan migrasi belum lengkap dan kompilasi JavaScript inline.

Jalankan `node --test tests/monitoring-kbm.test.cjs` pada MariaDB sementara yang datadir-nya sesuai marker `%TEMP%/lms-akademik-test-path.txt`. Test memverifikasi datadir sebelum membuat database acak terpisah dan tidak menjalankan migrasi pada database produksi. Jalankan `node --test tests/*.test.cjs` untuk regresi modul lama. Pemeriksaan tampilan tahap ini mencakup render HTML dan sintaks skrip, belum berupa uji browser visual otomatis.
## Edit dan hapus Hari Tanpa KBM

Pada tab Hari Tanpa KBM, daftar mempunyai tombol Edit (pensil) dan Hapus (tempat sampah). Edit mengisi formulir dengan tanggal, kelas, sesi, serta alasan yang tersimpan; tersedia Simpan Perubahan dan Batal Edit. Hapus meminta konfirmasi dan hanya menghapus pengecualian hari libur yang dipilih.

Perubahan hanya diizinkan untuk tanggal mulai besok. Baris hari ini/lampau tetap terlihat dengan tombol nonaktif dan keterangan arsip, agar laporan serta jadwal historis tidak berubah. Server memeriksa admin aktif, semester pemilik data, tanggal dalam semester, kelas, sesi, dan alasan. Semua tindakan mutasi memakai POST serta CSRF halaman admin yang sudah ada. Tidak memerlukan migrasi tambahan.

Pemeriksaan aktual: sintaks kedua berkas PHP lulus. Uji integrasi `tests/monitoring-kbm.test.cjs` lulus pada MariaDB terisolasi: edit cakupan kelas/sesi maupun seluruh kelas, hapus, penolakan tanggal/kelas/sesi/alasan/periode yang tidak valid, penolakan guru/siswa/akademik/admin nonaktif, CSRF, formulir edit, konfirmasi hapus, dan kesamaan catatan pertemuan sebelum/sesudah perubahan. Database utama tidak diubah oleh pengujian.
## Pengaturan terbaru: tanpa periode, jam JP, dan hari libur

Alur pengaturan lama di dokumen ini telah digantikan oleh [Monitoring KBM sederhana](README_monitoring_kbm_sederhana.md). Gunakan migrasi `2026_10_18_monitoring_kbm_sederhana.sql` dan panduan tersebut. Form/aksi Hari Tanpa KBM tidak lagi tersedia dalam alur baru.
