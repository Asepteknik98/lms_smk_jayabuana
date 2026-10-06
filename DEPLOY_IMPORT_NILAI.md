# Update impor nilai dan capaian satu kelas

## File production

Unggah file berikut dengan struktur folder yang sama:

- `guru/rekap_nilai.php`
- `guru/rekap_nilai_import.php`
- `includes/nilai_import.php`
- `includes/nilai_excel_reader.php`
- Folder `includes/vendor/simplexlsx/` beserta isinya.
- Folder `includes/vendor/simplexls/` beserta isinya.

Tidak ada migrasi SQL atau perubahan struktur database. Menggunakan tabel nilai,
riwayat, dan capaian yang sudah dipakai fitur rekap sebelumnya. Folder `tests/`
tidak diperlukan di production. Upload dependensi dan file baru terlebih dahulu,
lalu `guru/rekap_nilai.php` agar tombol baru muncul setelah semua file tersedia.

Mengikuti aplikasi saat ini: PHP 8 dengan PDO MySQL, mbstring, DOM, SimpleXML,
dan zlib. Pembaca Excel disertakan; tidak memerlukan Composer atau ekstensi ZIP.
Batas unggah aplikasi 5 MB, maksimal 5000 baris dan 100 kolom per sheet. Batas
`upload_max_filesize`/`post_max_size` hosting juga berlaku.

## Penggunaan

1. Guru memilih kelas/mapel pada Rekap Nilai, lalu **Import Excel**.
2. Pilih komponen tujuan: Tugas Harian, Ulangan Harian, UTS, UAS, atau Ujian
   Praktik. Komponen harus sudah tersedia pada pengajaran tersebut.
3. Unggah `.xlsx` atau `.xls` dan pilih nomor sheet (1 = paling kiri).
4. Format mengikuti contoh: header **Nama Siswa**, **Kelas**, **Asli**, dan
   opsional **Penyesuaian**. Mendukung header bertingkat/gabungan. Jika Penyesuaian
   terisi, angka itu dipakai; jika kosong, Asli dipakai. Nol adalah nilai sah.
5. Periksa pratinjau lalu konfirmasi. Pratinjau berlaku 30 menit.

Pencocokan siswa memakai nama, bukan NISN, dalam kelas yang dipilih.
Kapital dan spasi berlebih diabaikan. Nama ganda, nama tidak cocok, kelas lain,
nilai tidak valid, dan nilai yang sudah tersimpan dilewati dengan alasan.
Pencocokan tidak menebak nama yang mirip. Ejaan nama harus sesuai LMS.

**Nilai lama, termasuk 0, tidak ditimpa.** Penyimpanan memeriksa ulang data saat
konfirmasi. Impor hanya menambahkan nilai yang belum tersimpan. Impor ulang
tidak menimpa hasil impor sebelumnya. Kehadiran tidak dapat diimpor. Untuk
Tugas/Ulangan Harian, nilai impor menjadi manual pengganti; data aktivitas asal
tetap utuh dan fungsi kembali ke otomatis yang sudah ada tetap tersedia.

**Capaian Satu Kelas**: pilih UTS atau UAS, tulis deskripsi/saran sekali,
periksa pratinjau, lalu konfirmasi. Hanya kolom yang kosong pada siswa kelas dan
mapel terpilih yang dilengkapi. Deskripsi dan saran diperiksa terpisah. Teks yang
sudah terisi dipertahankan, nilai siswa tidak berubah, dan pengeditan per siswa
tetap tersedia. Data tetap dapat dipantau admin melalui fitur yang sudah ada.

## Verifikasi lokal

Jalankan `node --test tests/nilai-import.test.cjs tests/rekap-akademik.test.cjs`.
Tes menggunakan tabel sementara per koneksi, bukan mengubah data siswa asli.
Mencakup format Excel, nilai 0, nama/kelas, duplikasi, perlindungan nilai lama,
pemeriksaan ulang setelah pratinjau, rollback, capaian sebagian kosong, akses
pengajaran, CSRF, serta regresi rekap sebelumnya.

File Excel asli pengguna belum tersedia untuk diuji; verifikasi format memakai
fixture yang mengikuti susunan kolom pada gambar. Setelah upload, gunakan
**Periksa Pratinjau** dengan file guru untuk memeriksa kecocokan sebelum menyimpan.
