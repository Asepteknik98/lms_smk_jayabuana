# Update impor nilai dan capaian satu kelas

## Update terbaru: template dan edit capaian

Jika versi impor sebelumnya sudah terpasang, cukup unggah **tiga file**, dengan
urutan ini:

1. `includes/nilai_template.php` — file baru.
2. `includes/nilai_import.php` — replace file lama.
3. `guru/rekap_nilai_import.php` — replace paling akhir.

Tidak perlu mengunggah ulang vendor, mengubah konfigurasi, atau menjalankan SQL.

Guru memilih komponen tujuan lalu **Unduh Template Excel**. Template `.xlsx`
memuat nama siswa dan kelas otomatis, serta kolom Nilai kosong. Khusus UTS/UAS,
tersedia Deskripsi Capaian Pembelajaran dan Saran Capaian Pembelajaran per siswa.
Impor teks hanya mengisi bagian kosong, termasuk ketika nilai siswa sudah ada.
Nilai lama termasuk 0 tetap tidak ditimpa. Template komponen lain tidak memiliki
kolom capaian; kolom capaian tambahan yang diunggah untuk komponen lain diabaikan.

Untuk memperbaiki teks yang sudah tersimpan, pada **Capaian Satu Kelas** pilih
**Perbarui satu kelas**. Pilihan ini mengganti teks lama, termasuk teks khusus
per siswa, hanya pada pengajaran dan periode terpilih. Pratinjau menampilkan
teks lama dan baru. Input deskripsi/saran yang dikosongkan tidak menghapus teks
lama. **Isi yang kosong** tetap menjadi pilihan default. Kedua mode tidak
mengubah angka nilai. Jika teks target berubah setelah pratinjau, mode pembaruan
membatalkan seluruh penyimpanan dan meminta pratinjau ulang.

## File production

Unggah file berikut dengan struktur folder yang sama:

- `guru/rekap_nilai.php`
- `guru/rekap_nilai_import.php`
- `includes/nilai_import.php`
- `includes/nilai_template.php`
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
4. Template baru menggunakan **Nama Siswa**, **Kelas**, **Nilai**. Format lama
   tetap diterima: header **Nama Siswa**, **Kelas**, **Asli**, dan
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

**Capaian Satu Kelas / Isi yang kosong**: pilih UTS atau UAS, tulis deskripsi/saran sekali,
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
