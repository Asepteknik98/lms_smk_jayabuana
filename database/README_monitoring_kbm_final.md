# Laporan integrasi akhir Monitoring KBM — tahap 10

## Revisi formulir semester penuh

Form baru Sesi Kelas dan Waktu JP mengikuti awal/akhir semester tanpa isian tanggal berulang. Penugasan ketua berlaku sejak hari ini atau awal semester (mana yang lebih akhir) sampai akhir semester; tetap dapat dicabut. Jadwal baru otomatis dimulai paling cepat besok/awal semester sampai akhir semester agar tidak membuat riwayat mundur. Edit jadwal tetap meminta tanggal efektif, dengan akhir mengikuti semester. Server menentukan tanggal melalui penanda `semester_penuh=1`, tidak mempercayai tanggal kiriman browser. API lama tanpa penanda tetap mempertahankan rentang eksplisit untuk kompatibilitas. Jadwal dan pertemuan yang sudah ada tidak diperpanjang/diubah otomatis.

Sesuai persetujuan pengguna, konfigurasi sesi X TKJ 1 periode Ganjil 2026/2027 yang sebelumnya 19–23 Oktober diperluas menjadi 16 Agustus–31 Desember 2026. Sebelum perubahan diperiksa tidak ada penempatan bertumpang tindih atau pertemuan kelas tersebut; salinan nilai konfigurasi lama disimpan pada file sementara lokal. Ini penyesuaian data konfigurasi yang diminta pengguna, bukan migrasi. Jadwal lama tetap mempunyai masa berlaku aslinya; gunakan Edit bila perlu memperpanjangnya.

Dokumen ini merangkum kondisi akhir tahap 2–10. Dokumen tahap sebelumnya memuat kondisi pada saat tahap tersebut selesai; gunakan aturan status tahap 6 dan panduan akhir ini untuk operasional terkini.

## Fitur yang tersedia

1. Admin membuat, mengedit, menonaktifkan dan mereset password akun Akademik tanpa akun/password bawaan. Username unik, password ter-hash, role lama tidak diubah. Akademik menggunakan login LMS yang sama, dashboard khusus, profil sendiri dan logout.
2. Admin mengatur periode tahun ajaran/semester, penempatan kelas sesi pagi/siang, JP 1–6, istirahat, kalender tanpa KBM, ketua kelas, serta jadwal berelasi ke pengajaran LMS. Validasi bentrok guru/kelas, rentang tanggal, urutan waktu dan larangan melintasi istirahat diterapkan server.
3. Ketua kelas memakai akun siswa yang sama, izin tambahan aktif dan kelasnya sendiri. Check-in/out memakai waktu server, CSRF dan transaksi dengan kunci kelas. Catatan ganda dicegah; penutupan terlambat tidak mengisi waktu palsu.
4. Dashboard admin/Akademik memakai layanan status pusat, pengelompokan sesi, filter, ringkasan, legenda, waktu pembaruan serta peringatan catatan belum ditutup dan konfigurasi belum lengkap.
5. Riwayat menggabungkan arsip pertemuan dan jadwal bertanggal tanpa laporan, menyimpan nama historis, JP, jam rencana, aktual, pelapor, status dan durasi. Admin dapat mengoreksi dengan alasan wajib dan jejak sebelum/sesudah tanpa menghapus laporan asli. Akademik hanya membaca.
6. Excel admin mencakup seluruh hasil filter, dengan sel teks aman, waktu kosong yang tetap kosong, format tanggal/waktu/durasi, pelapor dan penanda koreksi.

## Pemeriksaan yang dijalankan

Pada 16 Oktober 2026, pengujian dijalankan dari salinan kode uji terhadap MariaDB lokal terisolasi di port 3307. Konfigurasi koneksi workspace tidak diubah. Fixture memverifikasi `@@datadir` terhadap marker direktori sementara sebelum menulis dan membuat database uji acak; tidak memakai data sekolah, mereset database atau menerapkan migrasi produksi.

Perintah pemeriksaan: `node --test tests/*.test.cjs` pada salinan terisolasi; `php -l` pada 37 file integrasi; `node --check assets/js/monitoring-kbm-dashboard.js`; `node --check tests/monitoring-kbm-siswa.test.cjs`; `git diff --check`.

Hasil aktual: **43 tes lulus, 0 gagal, 0 dilewati**, durasi sekitar 83 detik. Lint 37 file PHP dan kedua pemeriksaan sintaks JavaScript lulus. `git diff --check` tidak melaporkan kesalahan whitespace; Git hanya mengingatkan normalisasi LF/CRLF pada dua file yang sudah berubah sebelumnya. Tidak ditemukan kegagalan yang memerlukan perbaikan logika aplikasi dalam pemeriksaan ini. Tambahan skenario sesi siang/pergantian periode ikut dijalankan dalam suite tersebut. Server database uji dihentikan setelah pemeriksaan; server produksi tidak dihentikan.

| Cakupan | Pemeriksaan nyata |
| --- | --- |
| Akun dan login | Migrasi akun diulang, role dinamis, tanpa akun bawaan, CRUD/reset/nonaktif, username unik, password hash, CSRF, HTTP login tiga role lama dan Akademik, profil, logout, pencabutan sesi |
| Pengaturan | Migrasi fondasi/admin/indeks diulang di fixture, enam tab admin dirender, validasi guru/kelas bentrok, JP/istirahat/rentang, salah kelas, CSRF dan akses role lain |
| Pelaporan | Hanya ketua aktif dan kelas sendiri, penugasan dicabut, akun nonaktif, perpindahan kelas, input waktu palsu, sebelum/sesudah jadwal, istirahat, klik ulang, enam request check-in bersamaan dan dua jadwal bersamaan untuk satu kelas |
| Status pusat | Batas mulai/selesai, pergantian JP/blok, istirahat, sebelum/akhir sesi, kosong/libur, konfigurasi belum lengkap, tidak tercatat check-in, check-out terlupa, jadwal berikutnya dan hari berikutnya dengan peringatan lama terpisah |
| Periode dan sesi | Check-in terlambat selama jadwal siang, dashboard hijau, check-out; alur di semester Genap Januari 2027 dan tahun ajaran 2027/2028 Juli 2027, jadwal periode lama ditolak dan riwayat 2026 tetap utuh |
| Dashboard | Perubahan merah–hijau–kuning, filter/ringkasan, waktu server, role dan metode HTTP, akun Akademik nonaktif, jumlah query tetap saat 4 menjadi 84 kelas |
| Polling | Simulasi DOM/timer/Fetch: satu request aktif, respons lama diabaikan, tab tersembunyi, timeout, koneksi gagal mempertahankan data/timestamp, pulih dan akses kedaluwarsa |
| Riwayat/koreksi | Pagination/filter, jadwal tanpa check-in, baca tidak menulis, snapshot setelah nama master/jadwal berubah, alasan wajib, revisi basi ditolak, checkout sebelum check-in/future ditolak, laporan asli dan nilai sebelum/sesudah tetap tersimpan |
| Excel | Seluruh halaman, filter gabungan, kosong, koreksi dan durasi lebih dari 24 jam, akses lintas role/anonim/POST ditolak, lebih dari 50.000 hasil ditolak, pembacaan ulang 2.000 baris serta teks menyerupai formula tetap String |
| Regresi LMS | Pengujian tersedia untuk login lama, nilai manual, impor, rekap akademik/tugas/periode, remedial, portfolio dan absensi PKL |

Pengujian HTTP akun dan pengujian integrasi data/controller menggunakan fixture terpisah. Ini bukan satu skenario browser otomatis dari awal sampai akhir, dan bukan bukti bahwa semua fitur LMS yang tidak memiliki tes telah diuji.

## Perubahan file dan migrasi

Tahap 10 menambah skenario sesi siang/pergantian semester/tahun ajaran di `tests/monitoring-kbm-siswa.test.cjs`, laporan ini, dan rujukan status terkini pada dokumentasi fondasi. Tidak menambah fitur, mengubah logika aplikasi atau membuat migrasi baru.

Manifest integrasi tahap 2–9:

- Autentikasi/integrasi LMS: `config/auth.php`, `config/session.php`, `config/database.php`, `login.php`, `index.php`, `includes/header.php`, `includes/sidebar.php`, `admin/dashboard.php`, `admin/users.php`, `admin/user_action.php`.
- Akun: `includes/akun_akademik.php`, `admin/akun_akademik.php`, `akademik/profil.php`.
- Fondasi/pengaturan: `includes/monitoring_kbm_data.php`, `includes/monitoring_kbm_admin.php`, `admin/monitoring_kbm.php`.
- Siswa/status: `includes/monitoring_kbm_siswa.php`, `includes/monitoring_kbm_status.php`, `siswa/monitoring_kbm.php`.
- Dashboard: `includes/monitoring_kbm_board.php`, `includes/monitoring_kbm_cards.php`, `includes/monitoring_kbm_dashboard.php`, `includes/monitoring_kbm_feed.php`, `akademik/index.php`, `akademik/monitoring_kbm_data.php`, `admin/monitoring_kbm_status.php`, `admin/monitoring_kbm_data.php`, `assets/css/monitoring-kbm-dashboard.css`, `assets/js/monitoring-kbm-dashboard.js`.
- Riwayat/ekspor: `includes/monitoring_kbm_history.php`, `includes/monitoring_kbm_history_page.php`, `includes/monitoring_kbm_export.php`, `includes/rekap_akademik.php`, `admin/riwayat_kbm.php`, `akademik/riwayat_kbm.php`, `admin/riwayat_kbm_export.php`.
- Tes: `tests/akademik-account.test.cjs`, `tests/fixtures/auth-account.php`, `tests/monitoring-kbm*.test.cjs`; penyesuaian fixture autentikasi pada tes nilai/rekap lama. Dokumentasi rinci tersedia pada `database/README_akun_akademik.md` dan `database/README_monitoring_kbm_*.md`.

Empat migrasi tambahan yang diperlukan, sesuai urutan:

1. `database/2026_10_16_akun_akademik.sql` — role dinamis dan profil Akademik.
2. `database/2026_10_16_monitoring_kbm_data.sql` — fondasi data, sesi, pola, jadwal, ketua, laporan dan koreksi.
3. `database/2026_10_16_monitoring_kbm_admin.sql` — versi/nonaktif jadwal dan audit perubahan jadwal.
4. `database/2026_10_16_monitoring_kbm_dashboard.sql` — indeks laporan/koreksi terbuka.

## Penerapan manual

1. Cadangkan database dan file. Uji paket lengkap pada staging dengan versi PHP/ekstensi PDO MySQL, DOM/XML dan MariaDB yang sesuai proyek (pengujian memakai MariaDB XAMPP 10.4). Periksa tabel master InnoDB dan tipe FK `INT UNSIGNED`, role lama serta konflik nama role Akademik.
2. Dalam jendela pemeliharaan, jalankan empat migrasi di atas secara berurutan pada database LMS yang benar melalui phpMyAdmin/klien SQL. Tidak menjalankan `DROP`, `TRUNCATE`, reset database atau akun bawaan. Jika sebagian sudah diterapkan, periksa struktur dan gunakan sifat idempoten migrasi; jangan menganggap rerun dapat memperbaiki skema modifikasi manual. DDL MariaDB tidak seluruhnya bisa di-rollback.
3. Pasang file integrasi sebagai satu paket. Pastikan zona PHP `Asia/Jakarta`, koneksi database `+07:00`, waktu server tersinkron, session berfungsi, dan direktori sementara PHP dapat ditulis untuk Excel. Tidak perlu service WebSocket atau cron untuk dashboard/riwayat.
4. Admin melengkapi periode, sesi kelas, pola per hari, istirahat, kalender, pengajaran/jadwal dan ketua. Periksa pesan konfigurasi sebelum menggunakan hasil monitoring sebagai laporan operasional.
5. Lakukan uji penerimaan pada akun staging admin/Akademik/ketua/siswa biasa/guru. Verifikasi tampilan HP/laptop serta unduhan melalui aplikasi Excel sekolah sebelum penerapan produksi. Tidak ada deployment otomatis dalam pekerjaan ini.

## Panduan admin

1. **Master Data → Akun Akademik:** isi nama, username unik, password dan status aktif. Edit/reset melalui formulir yang sama; password kosong pada edit berarti tidak diganti. Nonaktifkan akun untuk mencabut akses pada request berikutnya.
2. **Master Data → Pengaturan Monitoring KBM:** buat/pilih periode, bagi kelas pagi/siang, lengkapi JP 1–6 dan istirahat per hari, pilih siswa aktif sekelas sebagai ketua, lalu buat jadwal dari pengajaran LMS. Jika blok melewati istirahat, pisah menjadi dua jadwal.
3. Untuk pergantian ketua, cabut penugasan lama kemudian tunjuk pengganti. Untuk semester/tahun baru, buat periode dan konfigurasi baru; jangan menghapus periode lama.
4. Edit/nonaktif jadwal menggunakan tanggal efektif paling cepat besok beserta alasan. Riwayat dan jadwal hari berjalan dipertahankan. Hari tanpa KBM ditambahkan mulai besok untuk mencegah perubahan riwayat tanpa laporan.
5. **Monitoring → Monitoring KBM:** periksa kelompok sesi, filter, rincian dan catatan belum check-out.
6. **Riwayat KBM:** pilih filter/rentang, buka Detail/Koreksi, masukkan waktu terverifikasi dan alasan. Periksa audit sebelum/sesudah; tidak ada hapus permanen. Catatan yang telah dikoreksi dan masih terbuka ditindaklanjuti admin.
7. Setelah menerapkan filter riwayat, klik **Unduh Excel Semua Hasil**. Jika kosong atau lebih dari 50.000 pertemuan/100 MB, ubah/persempit filter; tidak ada pemotongan diam-diam.

## Panduan Akademik

Login melalui halaman LMS biasa. Dashboard Akademik menampilkan seluruh kelas dan kelompok sesi; gunakan filter sesi/kelas/status, rincian jadwal dan **Perbarui Sekarang**. Periksa legenda, waktu pembaruan terakhir, pesan koneksi dan konfigurasi. Buka Riwayat KBM untuk filter tanggal/guru/status serta detail/audit. Akun ini hanya membaca: tanpa pengaturan, check-in/out, koreksi atau unduhan Excel admin. Profil sendiri dan logout mengikuti menu LMS.

## Panduan ketua kelas

Gunakan akun siswa biasa yang telah ditunjuk admin. Buka **Monitoring KBM**, periksa kelas/sesi/tanggal dan pilih JP yang sesuai. JP dalam satu blok memakai pertemuan yang sama. Tekan **Check-in Guru** saat guru masuk, selama jadwal berlangsung, lalu **Check-out Guru** saat selesai. Periksa waktu dan durasi tercatat. Jika terlupa, tutup catatan lama dahulu; waktu penutupan adalah waktu server saat tombol ditekan, bukan jam selesai jadwal. Hubungi admin bila perlu koreksi. Siswa tidak dapat mengubah waktu, memilih tanggal lampau, kelas lain, menghapus atau membuka ulang pertemuan selesai. Halaman siswa diperbarui dengan **Muat Ulang**; polling otomatis berlaku pada dashboard admin/Akademik.

## Aturan status dan keterbatasan

- Merah: jadwal sedang berjalan tanpa check-in. Hijau pada pertemuan: sudah check-in tanpa check-out; setelah jadwal berakhir tetap hijau dengan peringatan. Kuning: sebelum mulai, selesai, istirahat, hari tanpa KBM atau jadwal kosong, dengan keterangan berbeda.
- Warna utama kelas mengikuti blok yang sedang berlangsung. Catatan lama yang belum ditutup menjadi peringatan terpisah; tidak membuat hari/jadwal berikutnya terus hijau. Jadwal lewat tanpa laporan berstatus **Tidak tercatat check-in**, bukan kesimpulan bahwa guru tidak hadir.
- Data berasal dari **konfirmasi ketua kelas** atau koreksi admin yang ditandai, bukan sensor/deteksi kehadiran fisik guru. Check-in terlambat hanya diperbolehkan selama blok masih berjalan; tidak mencatat waktu masuk mundur. Tidak ada check-out otomatis. Durasi final memerlukan dua waktu dan penutupan terlambat dapat memperpanjang durasi.
- Dashboard memperbarui sekitar **12 detik setelah request sebelumnya selesai**, dipengaruhi jaringan dan tab aktif; bukan real-time seketika. Saat koneksi putus data terakhir dipertahankan dengan peringatan. Jam perangkat tidak menjadi acuan.
- Satu ketua aktif per kelas pada rentang/periode; satu pertemuan aktif per kelas. Kalender/pola memakai satu tanggal WIB, JP 1–6; sesi lintas tengah malam tidak didukung. Pergantian periode memerlukan konfigurasi dan penugasan baru. Pola waktu/penempatan sesi yang sudah memenuhi rentang tidak mempunyai editor pemendekan versi di tahap ini; rencanakan rentang sebelum menyimpan.
- Format unduhan mengikuti exporter LMS: XML SpreadsheetML `.xls`, bukan XLSX/biner XLS. Aplikasi tertentu dapat memberi peringatan ekstensi/format. Data teks tidak dibuat sebagai formula.
- Belum diverifikasi: visual/browser HP nyata, pembukaan lewat Microsoft Excel desktop, jaringan sekolah sebenarnya, kapasitas banyak pengguna serentak pada server produksi, konfigurasi/data produksi dan seluruh modul lama di luar tes tersedia. Simulasi polling/DOM dan tes request bersamaan terbatas bukan load test produksi atau audit keamanan menyeluruh.
