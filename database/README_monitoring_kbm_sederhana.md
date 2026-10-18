# Monitoring KBM sederhana — 18 Oktober 2026

Pengaturan admin sekarang hanya **Sesi Kelas**, **Ketua Kelas**, dan **Laporan Hari Ini**. Form periode/semester, pola waktu JP, dan hari tanpa KBM telah dihapus dari halaman admin. Setelah migrasi, endpoint juga menolak aksi lama untuk membuat/mengubah pengaturan tersebut maupun jadwal guru admin.

## Penerapan

1. Cadangkan database LMS.
2. Pastikan migrasi fondasi, pengaturan admin, dan pilihan guru sudah diterapkan.
3. Impor **2026_10_18_monitoring_kbm_sederhana.sql** pada database LMS yang benar melalui phpMyAdmin. File ini sudah mencakup perubahan tanpa jam JP tanggal 17; tidak perlu mengimpor file tanggal 17 secara terpisah.
4. Muat ulang Pengaturan Monitoring KBM. Pilih sesi pagi/siang untuk setiap kelas, lalu tunjuk ketua kelas dari siswa aktif kelas tersebut.

Migrasi disiapkan untuk penerapan manual dan tidak dijalankan otomatis pada database utama. SQL dapat dijalankan ulang. Tidak ada penghapusan tabel/data, pembuatan periode tersembunyi, atau akun bawaan.

## Perubahan logika

- Konfigurasi sesi dan ketua kelas baru mempunyai `periode_id=NULL`. Kolom ini dilonggarkan pada tabel yang sudah ada; tidak membuat sumber data kelas/siswa baru. Rentang teknis penugasan baru berakhir `9999-12-31`, tanpa formulir tanggal atau ketergantungan semester. Izin berlaku sampai dicabut/diganti.
- Sesi kelas mengikuti versi konfigurasi terakhir. Izin pengguna mengikuti penugasan per siswa yang belum dicabut, maksimal empat pengguna per kelas. Penugasan lama yang belum dicabut tetap bisa dipakai walaupun semester/tanggal akhirnya sudah lewat. Siswa harus tetap aktif dan berada di kelas yang sama.
- Admin dapat menambahkan 1–4 pengguna sesuai kebutuhan; penambahan tidak mencabut pengguna sebelumnya. Penggantian dilakukan dengan mencabut orang yang dimaksud lalu menambahkan penggantinya. Identitas pelapor lama tetap dipertahankan. Lihat [panduan pengguna per kelas](README_monitoring_kbm_pengguna.md).
- Ketua kelas langsung memilih JP 1–6/rentang dan guru aktif sekolah. Tidak ada pembatasan jam JP atau hari libur. Waktu masuk/selesai berasal dari server saat check-in/check-out, bukan perangkat siswa.
- Tetap ada kunci transaksi per kelas, validasi JP berurutan, penolakan tumpang tindih JP, satu pertemuan aktif per kelas, CSRF, pemeriksaan role/status akun, serta penolakan kelas lain. Check-out terlupa tetap harus ditutup; tidak dibuatkan waktu selesai otomatis.
- Dashboard Waka tetap memakai layanan status yang sama, tiga warna, dan interval pembaruan yang sudah ada. Tanpa laporan, kelas berstatus kuning “Belum ada laporan ketua kelas”. Kekurangan sesi/ketua tetap ditampilkan sebagai konfigurasi yang perlu dilengkapi.
- Riwayat lama tetap dibaca dari snapshot/perhitungan historis. Tabel periode, jam JP, dan hari libur lama disimpan untuk arsip saja. Tabel kecil `kbm_penyederhanaan` menyimpan tanggal peralihan satu kali, agar jadwal admin lama tidak terus menghasilkan laporan “tanpa check-in” setelah alur itu dihentikan. Ini penanda versi sistem, bukan periode yang harus diatur admin.
- Riwayat dan Excel mempertahankan waktu aktual, koreksi, pembatalan, dan nama pelapor. Perubahan sesi kelas tidak menulis ulang sesi pada pertemuan yang sudah tersimpan.

## Asumsi metadata arsip

Untuk laporan baru, tahun ajaran/semester sebagai **label arsip** diturunkan dari tanggal server: Juli–Desember Ganjil, Januari–Juni Genap; tahun ajaran dimulai Juli. Label ini bukan syarat akses atau masa berlaku dan tidak mengubah label pada laporan lama. Jika kalender resmi sekolah berbeda, label dapat disesuaikan pada helper `kbm_simple_school_year`; pengaturan ini tidak perlu diisi guru/siswa.

## Pemeriksaan aktual

- Suite yang sudah ada: **43 lulus, 0 gagal** (termasuk alur siswa, hak akses, pencabutan izin, klik ganda/request bersamaan, polling, riwayat, Excel, login akademik, dan fitur nilai/rekap/absensi lama).
- Uji tambahan `monitoring-kbm-simple.test.cjs` lulus pada database baru dengan **nol periode, nol pola/slot jam, nol hari libur**. Memeriksa konfigurasi sesi/ketua, check-in ganda, penolakan siswa lain, status hijau Waka, check-out, riwayat tanpa jam fiktif, perubahan sesi tanpa perubahan snapshot, pencabutan izin, dan penugasan tetap berlaku pada tahun berikutnya.
- Uji migrasi terbaru dijalankan dua kali, juga langsung dari fondasi/pilihan guru tanpa migrasi tanggal 17. Uji halaman/admin dijalankan ulang dan lulus setelah penyesuaian terakhir. Total **44 pengujian berbeda lulus**.
- Sintaks PHP pada 10 berkas modul diperiksa dan lulus. Pengujian memakai server MariaDB terisolasi port 3307 dengan pemeriksaan direktori data; database utama tidak diubah.
- Belum dilakukan: penerapan migrasi pada database utama dan pemeriksaan visual melalui browser dengan akun nyata.

## Berkas utama

`admin/monitoring_kbm.php`, `includes/monitoring_kbm_simple.php`, `includes/monitoring_kbm_simple_page.php`, serta integrasi pada helper admin/data/siswa/pilihan/status/history/export. Pengujian berada pada `tests/monitoring-kbm.test.cjs`, `tests/monitoring-kbm-siswa.test.cjs`, dan `tests/monitoring-kbm-simple.test.cjs`.

## Pemilihan guru di HP

Daftar kartu guru diganti dropdown native pada bagian “Guru untuk JP …”, langsung di bawah pilihan JP. Ketua kelas memilih nama lalu menekan **Pilih Guru**; ini menyimpan pilihan melalui POST/CSRF yang sama, tidak otomatis check-in. Tombol check-in/check-out tetap pada tabel laporan. Guru terpilih ditampilkan kembali dalam dropdown; pilihan dikunci setelah catatan tercatat, sesuai aturan server yang sudah ada.

Dropdown berukuran teks 16px dan tinggi minimal 48px; pada layar kecil dropdown/tombol tersusun vertikal dengan lebar penuh. Tidak memerlukan JavaScript tambahan, pustaka baru, atau migrasi. Pemeriksaan pada perangkat Android/iPhone fisik belum dilakukan.

## Progres JP dan laporan Guru Tidak Masuk

- JP yang sudah check-out tampil hijau dengan tulisan **Selesai** dan tombol nonaktif. Berlaku untuk seluruh rentang, misalnya JP 1–2. Status dibaca ulang dari laporan server, sehingga tetap terkunci setelah refresh. Pilihan otomatis berpindah ke JP yang belum selesai; jika semuanya sudah dilaporkan, dropdown dikunci dengan keterangan yang jelas.
- JP yang belum check-in setelah memilih guru berwarna merah; yang sedang mengajar hijau. Seleksi JP baru menggunakan biru agar berbeda dari progres selesai. Rentang baru tidak dapat melewati JP yang sudah selesai. Link JP pada tabel juga dinonaktifkan untuk catatan selesai.
- Tombol **Guru Tidak Masuk** tersedia setelah guru dipilih dan sebelum check-in, pada bagian guru serta tabel laporan. Konfirmasi menyebutkan bahwa laporan berlaku untuk seluruh rentang JP. Setelah dilaporkan, JP berwarna merah dan terkunci, lalu ketua dapat memilih JP berikutnya.
- Laporan tidak masuk menyimpan nama/identitas siswa pelapor dan waktu server pada snapshot JSON jadwal yang sudah tersedia. Pertemuan dimaterialisasi untuk arsip, tetapi **tidak membuat baris check-in/check-out atau durasi palsu**. Tidak memerlukan migrasi baru.
- Layanan status pusat meneruskan keterangan **Guru tidak masuk** berwarna merah ke Waka, admin, riwayat, dan Excel. Riwayat/Excel dapat difilter dengan status tersebut. Nama pelapor dan waktu pelaporan ditampilkan sebagai laporan ketua kelas, bukan hasil deteksi keberadaan fisik guru.
- Hijau pada JP selesai merupakan progres siswa; indikator utama Waka tetap mengikuti aturan sebelumnya (check-out berarti KBM selesai/kuning). Pencatatan JP berikutnya kembali menentukan status kelas yang terbaru.
- Pembatalan tetap tersedia untuk memperbaiki salah guru/JP, termasuk laporan tidak masuk. Metadata laporan asli dan alasan pembatalan tetap ada dalam riwayat. Koreksi admin tetap mempunyai jejak audit; koreksi waktu tidak menghapus laporan tidak masuk asli.
- Server memeriksa izin ketua aktif, kelas/tanggal sendiri, versi guru/rentang, CSRF pada endpoint, dan waktu server. Penguncian kelas menyerialisasi check-in dan laporan tidak masuk. Klik berulang tidak mengganti waktu/nama pelapor asli; check-in dan tidak masuk tidak boleh tercatat bersamaan untuk satu pertemuan.

Pemeriksaan aktual pembaruan ini: suite lengkap **44 lulus, 0 gagal**. Uji siswa mencakup tombol selesai/nonaktif, penolakan memilih ulang, enam request tidak masuk bersamaan, benturan check-in versus tidak masuk, CSRF, kelas/role lain, penugasan dicabut, status Waka, filter riwayat, Excel, pembatalan, semua JP selesai, serta koreksi admin yang tetap mempertahankan laporan asli. Revisi formulir koreksi kini mencakup laporan tidak masuk agar formulir lama ditolak jika laporan berubah. Setelah penguatan penolakan pilihan pada catatan terkoreksi admin, uji database baru dijalankan ulang dan lulus. Pengujian memakai server terisolasi; tampilan pada perangkat HP fisik belum diverifikasi.
