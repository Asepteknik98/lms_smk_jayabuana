<div align="center">

  <img src="assets/img/jb-mobile.png" alt="Logo SMKS Jaya Buana" width="120">

# LMS SMKS Jaya Buana

  <img src="https://readme-typing-svg.demolab.com?font=Poppins&weight=600&size=20&duration=3000&pause=1000&color=7952B3&center=true&vCenter=true&width=700&lines=Platform+Pembelajaran+Digital+SMKS+Jaya+Buana;Kelola+Materi%2C+Tugas%2C+Absensi%2C+dan+Nilai;Mendukung+Admin%2C+Guru%2C+dan+Siswa;WebApp+Version+1.0.0" alt="Animasi LMS SMKS Jaya Buana">

**Platform pembelajaran digital untuk mengelola kegiatan akademik sekolah dalam satu sistem.**

  <br>

[![WebApp Version](https://img.shields.io/badge/WebApp-V1.0.0-0d6efd?style=for-the-badge\&logo=googlechrome\&logoColor=white)](#versi-aplikasi)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge\&logo=php\&logoColor=white)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.4-003545?style=for-the-badge\&logo=mariadb\&logoColor=white)](https://mariadb.org/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge\&logo=bootstrap\&logoColor=white)](https://getbootstrap.com/)
![Status](https://img.shields.io/badge/Status-Active-success?style=for-the-badge)

<br><br>

<strong>Administrator</strong> · <strong>Guru</strong> · <strong>Siswa</strong>

</div>

---

## Tentang Aplikasi

**LMS SMKS Jaya Buana** adalah aplikasi berbasis web atau **WebApp** yang dirancang untuk mendukung rangkaian kegiatan belajar mengajar di SMKS Jaya Buana dalam satu sistem terintegrasi.

Aplikasi ini membantu sekolah dalam mengelola data akademik, distribusi materi pembelajaran, pengumpulan tugas, absensi, ulangan harian berbasis komputer, penilaian, hingga rekap perkembangan siswa.

LMS dibangun menggunakan PHP Native, MariaDB/MySQL, Bootstrap 5, dan JavaScript. Antarmuka aplikasi dirancang responsif agar nyaman digunakan melalui komputer, laptop, tablet, maupun telepon seluler.

## Versi Aplikasi

### WebApp V1.0.0

Versi aplikasi yang sedang dikembangkan dan digunakan saat ini adalah:

```text
LMS SMKS Jaya Buana
WebApp Version 1.0.0
Status: Active Development
```

**WebApp V1.0.0** merupakan versi rilis awal LMS SMKS Jaya Buana yang telah menyediakan fondasi utama sistem pembelajaran digital untuk tiga jenis pengguna, yaitu Administrator, Guru, dan Siswa.

Status penggunaan saat ini:

| Lingkungan | Status |
| --- | --- |
| Demo dan presentasi | Siap |
| Pengujian internal sekolah | Siap dengan akun serta data uji |
| Produksi publik | Memerlukan audit keamanan, backup, HTTPS, dan uji beban |

Fitur utama yang tersedia pada versi ini meliputi:

* Sistem autentikasi berdasarkan peran pengguna.
* Dashboard Administrator, Guru, dan Siswa.
* Manajemen data guru, siswa, kelas, jurusan, dan mata pelajaran.
* Pengelolaan materi pembelajaran.
* Dukungan materi berupa file, lampiran, dan video YouTube.
* Sistem tugas multi-format: portofolio, esai, pilihan ganda, merangkum, dan video.
* Penilaian otomatis untuk tugas pilihan ganda serta penilaian manual oleh guru.
* Preview dan unduh jawaban tugas.
* Absensi pembelajaran.
* Ulangan harian berbasis komputer.
* Rekap nilai siswa.
* Pengaturan KKM dan bobot penilaian.
* Ekspor laporan ke Excel dan PDF.
* Progressive Web App atau PWA.
* Antarmuka responsif untuk perangkat desktop dan mobile.
* Identitas visual sekolah menggunakan logo dan motif `assets/img/batikjb.webp`.
* Sistem keamanan dasar menggunakan CSRF, prepared statement, password hashing, dan pembatasan hak akses.

Penomoran versi aplikasi menggunakan format:

```text
MAJOR.MINOR.PATCH
```

Keterangan:

| Bagian  | Penjelasan                                                                   |
| ------- | ---------------------------------------------------------------------------- |
| `MAJOR` | Bertambah ketika terdapat perubahan besar pada sistem atau struktur aplikasi |
| `MINOR` | Bertambah ketika terdapat penambahan fitur baru yang tetap kompatibel        |
| `PATCH` | Bertambah ketika terdapat perbaikan bug atau perubahan kecil                 |

Contoh perkembangan versi:

```text
V1.0.0 → Rilis awal aplikasi
V1.1.0 → Penambahan fitur baru
V1.1.1 → Perbaikan bug pada fitur yang tersedia
V2.0.0 → Perubahan besar pada sistem atau arsitektur aplikasi
```

## Fitur Utama

### Administrator

* Dashboard dan ringkasan aktivitas LMS.
* Manajemen akun administrator, guru, dan siswa.
* Pengelolaan data guru dan siswa.
* Pengelolaan kelas, jurusan, serta mata pelajaran.
* Pengaturan pengajaran berdasarkan guru, kelas, semester, dan tahun ajaran.
* Rekap absensi berdasarkan guru, kelas, mata pelajaran, semester, dan pertemuan.
* Monitoring materi berdasarkan guru, kelas, status akses, file, dan pertemuan.
* Monitoring tugas, pengumpulan siswa, keterlambatan, jenis tugas, dan status penilaian.
* Monitoring ulangan, soal, peserta, siswa yang sudah atau belum mengerjakan, dan nilai.
* Rekap nilai akademik gabungan tugas, ulangan, serta absensi.
* Detail aktivitas dan produktivitas guru.
* Kontrol operasional sesi absensi dan akses pembelajaran.
* Pusat laporan berdasarkan kelas, guru, siswa, semester, dan tahun ajaran.
* Ekspor laporan Excel dan PDF.
* Pencatatan log aktivitas pengguna.
* Sidebar berkelompok, mode ringkas pada desktop, dan off-canvas pada perangkat mobile.

### Guru

* Dashboard kegiatan pembelajaran.
* Membuat, memperbarui, dan membagikan materi.
* Menambahkan video YouTube dan lampiran pada materi.
* Membuka atau mengunci akses pembelajaran per pertemuan.
* Mewajibkan siswa mengunduh materi sebelum tugas dapat diakses.
* Membuat tugas beserta lampiran, jenis tugas, pertanyaan, dan tenggat waktu.
* Mendukung tugas Portofolio, Esai, Pilihan Ganda, Merangkum, dan Pembuatan Video.
* Menambahkan beberapa pertanyaan esai atau pilihan ganda dengan opsi A-D dan kunci jawaban.
* Mengunci struktur pertanyaan setelah terdapat pengumpulan siswa untuk menjaga konsistensi data.
* Melihat, mengunduh, dan melakukan preview jawaban siswa.
* Melihat jawaban formulir siswa, memberikan nilai, serta menambahkan catatan.
* Membuka sesi absensi dan mengatur status Hadir, Sakit, Izin, atau Alpa.
* Rekap absensi per kelas dengan ekspor Excel dan PDF.
* Membuat ulangan harian dan mengelola soal.
* Impor soal dari template Excel.
* Memeriksa jawaban pilihan ganda dan esai.
* Memantau siswa yang sudah, sedang, atau belum mengerjakan ulangan harian.
* Rekap nilai otomatis dari kehadiran, tugas, dan ulangan harian.
* Pengaturan bobot komponen dan KKM per pengajaran.
* Penanda ketuntasan dan siswa yang memerlukan remedial.
* Catatan perkembangan untuk setiap siswa.
* Riwayat perubahan nilai manual.
* Cetak laporan nilai individual siswa.
* Ekspor rekap nilai ke Excel dan PDF.

### Siswa

* Dashboard pembelajaran dan pengingat kegiatan.
* Mengakses serta mengunduh materi.
* Menandai materi yang sudah dibaca melalui proses review.
* Melihat indikator hijau pada pertemuan yang seluruh materinya sudah dibaca.
* Melihat peringatan prasyarat tugas pada sub-dropdown pertemuan.
* Menonton video pembelajaran YouTube langsung dari halaman materi.
* Melihat status pertemuan yang terbuka atau masih terkunci.
* Mengerjakan tugas portofolio, esai, pilihan ganda, rangkuman, dan pembuatan video.
* Mengunggah portofolio, menulis jawaban, memilih opsi, atau mengirim tautan video.
* Mengakses tugas setelah membaca, mengunduh lampiran, dan menyelesaikan review materi.
* Melihat nilai otomatis pilihan ganda serta nilai dan catatan dari guru.
* Melakukan check-in pada sesi absensi yang aktif.
* Mengikuti ulangan harian berbasis komputer.
* Mendapatkan peringatan tugas atau kuis yang belum dikerjakan.
* Memasang LMS sebagai aplikasi web atau PWA pada perangkat yang mendukung.

## Alur Rekap Nilai

Nilai siswa dihitung berdasarkan komponen dan bobot yang ditentukan oleh guru.

| Komponen       | Sumber                            | Perhitungan             |
| -------------- | --------------------------------- | ----------------------- |
| Kehadiran      | Sesi absensi yang telah ditutup   | Rata-rata seluruh sesi  |
| Tugas Harian   | Tugas yang telah melewati tenggat | Rata-rata seluruh tugas |
| Ulangan Harian | Kuis yang telah selesai           | Rata-rata seluruh kuis  |
| UTS/UAS        | Input guru                        | Nilai manual            |

Kegiatan yang belum selesai belum dimasukkan ke dalam perhitungan rata-rata.

Siswa yang tidak mengikuti kegiatan yang telah selesai akan memperoleh nilai `0` untuk kegiatan tersebut. Nilai akhir kemudian dibandingkan dengan KKM untuk menentukan status **Tuntas** atau **Remedial**.

## Teknologi

* PHP 8.x
* MariaDB/MySQL
* HTML5
* CSS3
* JavaScript
* Bootstrap 5
* Font Awesome
* PDO dengan prepared statements
* Apache melalui XAMPP
* Progressive Web App atau PWA

Proyek ini tidak memerlukan Composer atau Node.js untuk menjalankan fitur utamanya.

## Persyaratan

* PHP 8.0 atau versi lebih baru.
* MariaDB 10.4+ atau MySQL yang kompatibel.
* Apache atau Nginx.
* Ekstensi PDO MySQL aktif.
* XAMPP direkomendasikan untuk instalasi lokal.
* Browser modern seperti Google Chrome, Microsoft Edge, atau Mozilla Firefox.

## Instalasi Lokal

1. Clone repositori ke dalam direktori `htdocs`:

   ```bash
   git clone <alamat-repositori> C:\xampp\htdocs\lms
   ```

2. Jalankan Apache dan MySQL melalui XAMPP Control Panel.

3. Buka phpMyAdmin dan buat database:

   ```sql
   CREATE DATABASE lms_smk_jayabuana
   CHARACTER SET utf8mb4
   COLLATE utf8mb4_unicode_ci;
   ```

4. Impor file database berikut:

   ```text
   database/lms_smk_jayabuana.sql
   ```

5. Jika dump utama belum memuat perubahan terbaru, jalankan migrasi sesuai urutan:

   ```text
   database/2026_07_23_komponen_penilaian.sql
   database/2026_07_23_penyempurnaan_rekap_nilai.sql
   database/2026_07_23_notifikasi_materi_siswa.sql
   database/2026_07_24_metadata_pengumpulan_tugas.sql
   database/2026_07_24_hak_akses_pertemuan.sql
   database/2026_07_24_video_youtube_materi.sql
   database/2026_07_24_wajib_unduh_materi.sql
   database/2026_07_25_jenis_tugas.sql
   ```

6. Periksa konfigurasi pada `config/database.php`:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'lms_smk_jayabuana');
   ```

7. Buka aplikasi melalui browser:

   ```text
   http://localhost/lms/
   ```

> Jangan menyimpan akun, kata sandi, API key, atau kredensial produksi di dalam README maupun repositori publik.

## Deployment ke Hosting

Untuk deployment produksi, unggah kode aplikasi kemudian ekspor database aktif dari phpMyAdmin lokal dan impor hasilnya ke database server.

```text
MySQL/MariaDB Lokal
        ↓
Export Database SQL
        ↓
Import ke Database Hosting
        ↓
Sesuaikan config/database.php
        ↓
Uji Sistem
```

Folder `database` berisi dump awal dan catatan migrasi. Folder tersebut bukan database aktif dan tidak dibaca otomatis oleh aplikasi.

Apabila menggunakan hasil ekspor database lokal terbaru, migrasi yang sudah diterapkan tidak perlu dijalankan kembali.

Hal yang perlu diperiksa saat deployment:

* Sesuaikan kredensial database produksi.
* Sesuaikan nilai `$base_url` apabila nama folder bukan `/lms`.
* Pastikan direktori unggahan dapat ditulis oleh web server.
* Gunakan protokol HTTPS.
* Nonaktifkan tampilan error PHP pada lingkungan produksi.
* Aktifkan pencatatan error ke dalam file log.
* Simpan cadangan database dan berkas unggahan secara berkala.
* Jangan mengunggah data siswa atau contoh jawaban sensitif ke repositori publik.

### Verifikasi Setelah Instalasi

Setelah dump dan migrasi selesai diimpor, periksa alur berikut menggunakan akun uji:

1. Admin membuat atau memeriksa pengajaran guru dan kelas.
2. Guru membuka akses pertemuan serta mengunggah materi.
3. Siswa membuka, mengunduh, dan melakukan review materi.
4. Guru membuat masing-masing jenis tugas.
5. Siswa mengirim jawaban dan guru melakukan penilaian.
6. Admin membuka Monitoring Tugas dan Rekap Nilai Akademik.

Untuk tugas Pilihan Ganda, pastikan guru telah mengisi seluruh opsi A-D dan memilih satu kunci jawaban pada setiap pertanyaan.

## Struktur Proyek

```text
lms/
├── admin/                # Modul administrator
├── guru/                 # Modul pembelajaran dan penilaian guru
├── siswa/                # Modul pembelajaran siswa
├── assets/               # CSS, JavaScript, gambar, dan berkas unggahan
├── config/               # Database, sesi, autentikasi, dan helper
├── database/             # Dump database serta migrasi
├── includes/             # Header, sidebar, navbar, dan footer bersama
├── index.php             # Pengarah halaman berdasarkan peran
├── login.php             # Autentikasi pengguna
├── logout.php            # Pengakhiran sesi pengguna
├── manifest.webmanifest  # Metadata Progressive Web App
└── sw.js                 # Service worker aplikasi
```

## Keamanan

Beberapa perlindungan yang diterapkan pada aplikasi:

* Hak akses berbasis peran.
* Password hashing dan verifikasi password.
* PDO prepared statements.
* Perlindungan CSRF pada aksi penting.
* Validasi kepemilikan data oleh guru.
* Validasi jenis, ukuran, dan akses berkas unggahan.
* Session regeneration setelah autentikasi.
* Sanitasi keluaran untuk membantu mencegah XSS.
* Pencatatan aktivitas pengguna.
* Pembatasan akses halaman berdasarkan status login dan peran.
* Validasi input pada sisi server.

Sebelum digunakan pada server publik, lakukan pengujian keamanan dan perbarui PHP, database, serta web server secara berkala.

## Catatan Database

Database yang digunakan oleh aplikasi berada pada layanan MySQL atau MariaDB, bukan di dalam folder `database`.

Karena itu, selalu lakukan ekspor database aktif sebelum:

* Memindahkan aplikasi ke perangkat lain.
* Melakukan deployment ke hosting.
* Melakukan perubahan struktur tabel.
* Memasang pembaruan besar.
* Menghapus atau menginstal ulang XAMPP.

Tabel tambahan untuk sistem pembelajaran dan penilaian antara lain:

* `komponen_penilaian`
* `nilai_komponen`
* `catatan_siswa_pengajaran`
* `riwayat_nilai`
* `akses_pertemuan`
* `materi_siswa_dibaca`
* `materi_siswa_diunduh`
* `pertanyaan_tugas`
* `opsi_tugas`
* `jawaban_tugas`

Migrasi `2026_07_25_jenis_tugas.sql` menambahkan kolom `jenis_tugas`, membuat tabel pertanyaan dan jawaban tugas, serta mengizinkan pengumpulan berbasis formulir tanpa file. Tugas lama tetap kompatibel dan menggunakan jenis `portofolio`.

## Riwayat Versi

### V1.0.0

**Status:** Active Development

Rilis awal LMS SMKS Jaya Buana dengan modul utama:

* Autentikasi Administrator, Guru, dan Siswa.
* Dashboard berdasarkan peran pengguna.
* Manajemen data akademik.
* Materi pembelajaran.
* Video YouTube pada materi.
* Penguncian akses pertemuan.
* Wajib unduh materi.
* Review materi dan penguncian tugas berdasarkan prasyarat belajar.
* Lima jenis tugas dan pengumpulan jawaban berbasis file atau formulir.
* Penilaian otomatis tugas pilihan ganda.
* Absensi pembelajaran.
* Ulangan harian.
* Rekap dan laporan nilai.
* Monitoring dan pusat laporan Administrator.
* Kontrol operasional dan audit aktivitas.
* Dukungan tampilan responsif.
* Dukungan Progressive Web App.

## Rencana Pengembangan

Pengembangan berikutnya dapat mencakup:

* Notifikasi real-time.
* Integrasi email sekolah.
* Sistem pesan antara guru dan siswa.
* Kalender akademik.
* Forum diskusi pembelajaran.
* Bank soal terpusat.
* Analisis perkembangan siswa.
* Backup database otomatis.
* Audit keamanan lebih lanjut.
* Penyempurnaan mode offline PWA.

## Kontribusi

Kontribusi dapat dilakukan melalui issue atau pull request:

1. Buat branch fitur.
2. Lakukan perubahan secara terfokus.
3. Uji alur Administrator, Guru, dan Siswa yang terdampak.
4. Jangan sertakan kredensial atau data pribadi.
5. Jangan sertakan berkas unggahan milik pengguna.
6. Ajukan pull request dengan penjelasan perubahan.
7. Sertakan hasil pengujian yang telah dilakukan.

## Lisensi

Hak penggunaan dan distribusi mengikuti kebijakan SMKS Jaya Buana.

Tambahkan berkas lisensi resmi apabila repositori akan dipublikasikan sebagai proyek sumber terbuka.

---

<div align="center">

  <img src="https://readme-typing-svg.demolab.com?font=Poppins&weight=600&size=16&duration=3000&pause=1000&color=198754&center=true&vCenter=true&width=650&lines=Dibuat+untuk+mendukung+pembelajaran+digital;SMKS+Jaya+Buana;WebApp+V1.0.0" alt="Footer LMS SMKS Jaya Buana">

  <br>

Dibuat untuk mendukung pembelajaran digital di <strong>SMKS Jaya Buana</strong>

<br><br>

**LMS SMKS Jaya Buana — WebApp V1.0.0**

</div>
