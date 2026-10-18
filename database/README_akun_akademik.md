# Tahap 2 — Akun Wakil Kepala Sekolah Bidang Akademik

Tahap ini hanya menambahkan akun, login, pembatasan akses, profil sendiri, dan
dashboard awal. Belum ada jadwal, ketua kelas, check-in/out, atau monitoring KBM.

## Migrasi manual

1. Backup database tujuan dan periksa role yang sudah ada:
   `SELECT id,nama_role FROM roles ORDER BY id;`
2. Jalankan `database/2026_10_16_akun_akademik.sql` pada database LMS yang sesuai.
3. Unggah/perbarui file PHP tahap ini. Sebaiknya gunakan jendela pemeliharaan.

Migrasi hanya menambahkan role `Akademik` dan tabel `akademik_profil`, tanpa
mengubah role 1/2/3, menghapus data, atau membuat akun/password bawaan. ID Akademik
mengikuti AUTO_INCREMENT `roles`, tidak diasumsikan selalu 4. Skrip dapat diulang.
Jika nama role Akademik sudah dipakai secara tidak wajar atau ganda, periksa
konfliknya terlebih dahulu; aplikasi menolak konfigurasi role yang ambigu.

## Penggunaan admin

- Buka **Master Data → Akun Akademik**, atau **Manajemen User → Akun Akademik**.
- Isi nama lengkap, username unik, password 8–72 byte, dan status akun.
- **Edit / Reset Password** dapat mengubah nama, username, dan status.
- Pada edit, password kosong berarti tidak diubah. Isi password baru untuk reset.
- Akun Akademik dikelola melalui formulir khusus supaya profil namanya lengkap.
  Endpoint user umum menolak manipulasi akun Akademik lewat formulir lama.

Pengguna Akademik login melalui `login.php` yang sama dan diarahkan ke
`akademik/index.php`. Profil hanya menampilkan akun yang sedang login; perubahan
identitas/password dilakukan admin. Logout memakai `logout.php` yang sudah ada.

Status aktif dan role diperiksa dari database setiap request terlindungi. Akun
nonaktif/dihapus, perubahan role, atau perubahan password sejak login membatalkan
sesi. Akses lintas role menghasilkan HTTP 403. Dashboard/profil Akademik hanya
menerima GET/HEAD dan tidak menyediakan endpoint perubahan data.

## Pemeriksaan

`tests/akademik-account.test.cjs` menguji migrasi dua kali, tidak adanya akun
bawaan, ID role dinamis, pembuatan/edit/nonaktif/reset akun, CSRF, keunikan
username, hash password, login tiga role lama, dashboard/profil, akses lintas role,
logout, dan pencabutan sesi pada request berikutnya melalui HTTP.

Tes integrasi ini hanya berjalan pada MariaDB **terisolasi dalam direktori
sementara**, yang lokasinya tercatat di `%TEMP%/lms-akademik-test-path.txt`.
Tes memeriksa `@@datadir` sebelum membuat data uji dan menolak server lain.
Jangan menjalankannya pada database sekolah. Pengujian fitur lama memakai tabel
sementara per koneksi melalui fixture autentikasi yang disesuaikan.
# Tampilan profil akademik

Halaman `akademik/profil.php` memakai CSS khusus
`assets/css/akademik-profile.css`. Kartu identitas menampilkan banner sekolah,
ikon profil, nama, jabatan dan status aktif; informasi akun tersusun dua kolom
di desktop dan satu kolom pada layar kecil. Tidak menggunakan data profil
tambahan, foto, atau perubahan akun. Query profil sendiri, pembatasan role,
metode GET/HEAD, dan pengelolaan identitas/password oleh admin tetap sama.
Tidak ada migrasi untuk perubahan tampilan ini.

Pemeriksaan setelah revisi profil: PHP lint lulus; pengujian integrasi
`tests/akademik-account.test.cjs` pada salinan TEMP dengan MariaDB terisolasi
port 3307 lulus (83,3 detik), termasuk profil sendiri, penolakan POST profil,
login, perubahan identitas oleh admin dan pencabutan sesi. Tampilan belum
diperiksa manual pada perangkat pengguna. Database utama tidak dimigrasi.
