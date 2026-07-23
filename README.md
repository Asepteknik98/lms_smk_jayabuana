<div align="center">
  <img src="assets/img/jb-mobile.png" alt="Logo SMKS Jaya Buana" width="120">

  # LMS SMKS Jaya Buana

  **Platform pembelajaran digital untuk mengelola kegiatan akademik sekolah dalam satu sistem.**

  [![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
  [![MariaDB](https://img.shields.io/badge/MariaDB-10.4-003545?logo=mariadb&logoColor=white)](https://mariadb.org/)
  [![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
  ![Status](https://img.shields.io/badge/status-active-success)

  <p>
    <strong>Admin</strong> · <strong>Guru</strong> · <strong>Siswa</strong>
  </p>
</div>

---

## Tentang Aplikasi

LMS SMKS Jaya Buana adalah aplikasi berbasis web yang mendukung rangkaian kegiatan belajar mengajar, mulai dari pengelolaan data akademik, distribusi materi, tugas, absensi, ujian berbasis komputer, hingga rekap nilai siswa.

Aplikasi dibangun menggunakan PHP native, MariaDB/MySQL, Bootstrap 5, dan JavaScript. Antarmuka dirancang responsif agar dapat digunakan melalui komputer, tablet, maupun telepon seluler.

## Fitur Utama

### Administrator

- Dashboard dan ringkasan aktivitas LMS.
- Manajemen akun administrator, guru, dan siswa.
- Pengelolaan data guru dan siswa.
- Pengelolaan kelas, jurusan, serta mata pelajaran.
- Pengaturan pengajaran berdasarkan guru, kelas, semester, dan tahun ajaran.
- Monitoring kegiatan siswa.
- Ekspor data monitoring.
- Pencatatan log aktivitas pengguna.

### Guru

- Dashboard kegiatan pembelajaran.
- Membuat, memperbarui, dan membagikan materi.
- Membuat tugas beserta lampiran dan tenggat waktu.
- Melihat, mengunduh, dan mem-preview jawaban siswa.
- Memberikan nilai serta catatan pada tugas.
- Membuka sesi absensi dan mengatur status Hadir, Sakit, Izin, atau Alpa.
- Rekap absensi per kelas dengan ekspor Excel dan PDF.
- Membuat kuis/ujian dan mengelola soal.
- Impor soal dari template Excel.
- Memeriksa jawaban pilihan ganda dan esai.
- Memantau siswa yang sudah, sedang, atau belum mengerjakan ujian.
- Rekap nilai otomatis dari kehadiran, tugas, dan ulangan harian.
- Pengaturan bobot komponen dan KKM per pengajaran.
- Penanda ketuntasan dan siswa yang memerlukan remedial.
- Catatan perkembangan untuk setiap siswa.
- Riwayat perubahan nilai manual.
- Cetak laporan nilai individual siswa.
- Ekspor rekap nilai ke Excel dan PDF.

### Siswa

- Dashboard pembelajaran dan pengingat kegiatan.
- Mengakses serta mengunduh materi.
- Melihat tugas, mengunggah jawaban, dan membaca catatan guru.
- Melakukan check-in pada sesi absensi yang aktif.
- Mengikuti kuis/ujian berbasis komputer.
- Peringatan tugas atau kuis yang belum dikerjakan.

## Alur Rekap Nilai

Nilai siswa dihitung berdasarkan komponen dan bobot yang ditentukan guru.

| Komponen | Sumber | Perhitungan |
|---|---|---|
| Kehadiran | Sesi absensi yang telah ditutup | Rata-rata seluruh sesi |
| Tugas Harian | Tugas yang telah melewati tenggat | Rata-rata seluruh tugas |
| Ulangan Harian | Kuis yang telah selesai | Rata-rata seluruh kuis |
| UTS/UAS | Input guru | Nilai manual |

Kegiatan yang belum selesai belum masuk ke dalam rata-rata. Siswa yang tidak mengikuti kegiatan yang telah selesai memperoleh nilai `0` untuk kegiatan tersebut. Nilai akhir kemudian dibandingkan dengan KKM untuk menentukan status **Tuntas** atau **Remedial**.

## Teknologi

- PHP 8.x
- MariaDB/MySQL
- HTML5, CSS3, dan JavaScript
- Bootstrap 5
- Font Awesome
- PDO dengan prepared statements
- Apache melalui XAMPP untuk pengembangan lokal

Proyek ini tidak memerlukan Composer atau Node.js untuk menjalankan fitur utamanya.

## Persyaratan

- PHP 8.0 atau lebih baru.
- MariaDB 10.4+ atau MySQL yang kompatibel.
- Apache/Nginx dengan ekstensi PDO MySQL aktif.
- XAMPP direkomendasikan untuk instalasi lokal.

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

4. Impor [database/lms_smk_jayabuana.sql](database/lms_smk_jayabuana.sql).

5. Jika dump utama belum memuat perubahan terbaru, jalankan migrasi sesuai urutan:

   ```text
   database/2026_07_23_komponen_penilaian.sql
   database/2026_07_23_penyempurnaan_rekap_nilai.sql
   database/2026_07_23_notifikasi_materi_siswa.sql
   ```

6. Periksa konfigurasi pada `config/database.php`:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'lms_smk_jayabuana');
   ```

7. Buka aplikasi:

   ```text
   http://localhost/lms/
   ```

> Jangan menyimpan akun atau kata sandi produksi di README maupun repositori publik.

## Deployment ke Hosting

Untuk deployment produksi, unggah kode aplikasi lalu ekspor database aktif dari phpMyAdmin lokal dan impor hasilnya ke database server.

```text
MySQL/MariaDB lokal → Export SQL → Import ke server → Sesuaikan config/database.php
```

Folder `database` berisi dump awal dan catatan migrasi. Folder tersebut bukan database aktif dan tidak dibaca otomatis oleh aplikasi. Jika menggunakan hasil ekspor database lokal terbaru, migrasi yang sudah diterapkan tidak perlu dijalankan kembali.

Hal yang perlu diperiksa saat deployment:

- Sesuaikan kredensial database produksi.
- Sesuaikan nilai `$base_url` pada konfigurasi akses jika nama folder bukan `/lms`.
- Pastikan direktori unggahan dapat ditulis oleh web server.
- Gunakan HTTPS.
- Nonaktifkan tampilan error PHP pada lingkungan produksi.
- Simpan cadangan database dan berkas unggahan secara berkala.
- Jangan mengunggah data siswa atau contoh jawaban sensitif ke repositori publik.

## Struktur Proyek

```text
lms/
├── admin/       # Modul administrator
├── guru/        # Modul pembelajaran dan penilaian guru
├── siswa/       # Modul pembelajaran siswa
├── assets/      # CSS, JavaScript, gambar, dan berkas unggahan
├── config/      # Koneksi database, sesi, autentikasi, dan helper
├── database/    # Dump database serta migrasi
├── includes/    # Header, sidebar, dan footer bersama
├── index.php    # Pengarah halaman berdasarkan peran
├── login.php    # Autentikasi pengguna
└── logout.php   # Pengakhiran sesi
```

## Keamanan

Beberapa perlindungan yang digunakan:

- Hak akses berbasis peran.
- Password hashing dan verifikasi password.
- PDO prepared statements.
- Perlindungan CSRF pada aksi penting.
- Validasi kepemilikan data oleh guru.
- Validasi jenis, ukuran, dan akses berkas unggahan.
- Session regeneration saat autentikasi.
- Sanitasi keluaran untuk membantu mencegah XSS.
- Pencatatan aktivitas pengguna.

Sebelum digunakan pada server publik, lakukan pengujian keamanan dan perbarui PHP, database, serta web server secara berkala.

## Catatan Database

Database yang sedang digunakan aplikasi berada pada layanan MySQL/MariaDB, bukan di folder `database`. Karena itu, selalu lakukan ekspor database aktif sebelum memindahkan aplikasi atau melakukan pembaruan besar.

Tabel tambahan untuk sistem penilaian antara lain:

- `komponen_penilaian`
- `nilai_komponen`
- `catatan_siswa_pengajaran`
- `riwayat_nilai`

## Kontribusi

Kontribusi dapat dilakukan melalui issue atau pull request:

1. Buat branch fitur.
2. Lakukan perubahan secara terfokus.
3. Uji alur Admin, Guru, dan Siswa yang terdampak.
4. Jangan sertakan kredensial, data pribadi, atau berkas unggahan pengguna.
5. Ajukan pull request dengan penjelasan perubahan dan hasil pengujian.

## Lisensi

Hak penggunaan dan distribusi mengikuti kebijakan SMKS Jaya Buana. Tambahkan berkas lisensi apabila repositori akan dipublikasikan sebagai proyek sumber terbuka.

---

<div align="center">
  Dibuat untuk mendukung pembelajaran digital di <strong>SMKS Jaya Buana</strong>.
</div>
