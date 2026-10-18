# Dashboard Monitoring KBM — tahap 7

Pembaruan tahap 8: halaman rentang riwayat terpisah dan koreksi admin teraudit tersedia melalui menu Riwayat KBM. Lihat `README_monitoring_kbm_history.md`.

## Mode papan 40 kelas untuk Akademik

Revisi sesuai referensi pengguna (stylesheet versi 15): header navy dengan identitas sekolah dan tombol Menu LMS/Layar Penuh; tanggal Indonesia; tiga ringkasan merah, hijau, kuning; panel putih Monitoring Kelas dengan menu sesi, legenda, waktu pembaruan, indikator konfigurasi serta kartu berdot warna. Kuning berjudul **Kondisi lainnya** karena mencakup istirahat, sebelum jadwal, selesai dan tanpa KBM; tidak mengubah semua kondisi kuning menjadi Istirahat. Susunan laptop tetap 8 × 5 untuk 40 kelas. Perubahan terbatas pada view, CSS dan format tanggal tampilan JavaScript, tanpa perubahan layanan status, akses, query atau data. Empat tes polling/DOM dan lint lulus. Fixture browser lokal 40 kartu pada viewport 1348 × 616 menunjukkan 40/40 terlihat, 8 kolom/5 baris, tanpa scroll internal, dan dialog terbuka/tertutup; screenshot diperiksa. Fixture tidak menggunakan akun/data produksi dan ikon CDN tidak dimuat dalam fixture lokal.

Revisi visual terkini (stylesheet versi 14): tema terang netral, kartu putih dan teks gelap. Warna merah/hijau/kuning hanya sebagai garis tepi dan label status dengan latar lembut. Panel atas diringkas: menu sesi bersebelahan dengan filter opsional; ringkasan dan cakupan pada satu baris. Mode gelap sebelumnya diganti sesuai umpan balik pengguna. Tidak mengubah status pusat, query, data, akses atau polling. Lint template dan empat tes polling lulus. Fixture lokal dengan template kartu/CSS asli diuji melalui Chrome headless pada viewport 1348 ? 616: 40 kartu terlihat dalam 8 ? 5, tanpa scroll internal grid; dialog berhasil dibuka/ditutup dan screenshot diperiksa. Fixture memakai gaya layout dasar, bukan pengujian login/data produksi.

Dashboard Akademik menggunakan papan ringkas pada viewport minimal 1024 × 600: delapan kolom dan lima baris. Semua kelompok sesi diratakan ke satu grid sehingga 40 kelas dari sesi berbeda tetap dapat terlihat bersama. Tidak ada kelas fiktif; jumlah kartu mengikuti master kelas dan filter. Lebih dari 40 kelas tetap tersedia dengan scroll, layar kecil kembali memakai kartu responsif biasa. Filter lanjutan yang dibuka dapat menambah kebutuhan scroll.

Sidebar disembunyikan pada mode laptop dan dapat dibuka lewat **Menu LMS**. Tombol **Layar Penuh** menggunakan fasilitas browser; jika tidak tersedia gunakan F11. Kartu ringkas menampilkan kelas, guru, status dan penanda konfigurasi/catatan belum check-out. Klik kartu untuk dialog detail, termasuk JP, waktu, konfigurasi dan catatan; dialog mengikuti pembaruan kartu. Escape/Tutup mengembalikan fokus. Menu Semua Kelas/Pagi/Siang dan polling 12 detik tetap berjalan. Hak akses, perhitungan status, jadwal dan data laporan tidak berubah.

Pemeriksaan revisi: lint dua template PHP dan sintaks JavaScript lulus; empat pengujian polling/DOM lulus. Fixture lokal 40 kartu dari template asli (18 pagi, 17 siang, 5 belum diatur), CSS papan asli dan gaya layout dasar diuji melalui Chrome headless. Pada viewport terukur 1348 × 616 dan 1902 × 928, 40/40 kartu terlihat, delapan kolom/lima baris, tanpa scroll internal grid. Dialog memakai JavaScript asli dan berhasil membuka satu detail serta menutupnya. Fixture tidak menulis database dan tidak menggantikan uji penerimaan browser login LMS dengan seluruh stylesheet CDN, zoom pengguna atau perangkat sekolah.

## Akses dan penggunaan

- **Akademik:** login biasa lalu buka dashboard Akademik (`akademik/index.php`). Seluruh kelas tampil otomatis.
- **Admin:** menu **Monitoring → Monitoring KBM**, `admin/monitoring_kbm_status.php`.
- Pilih tanggal, sesi pagi/siang, kelas, dan status; ringkasan merah/hijau/kuning menghitung kelas **sesuai filter**. Reset / Hari Ini menghapus filter. Filter sesi juga menyediakan kelas yang sesinya belum diatur/perlu diperiksa; kelas yang memiliki sesi bertumpang tindih tidak dihitung dua kali.
- Kartu dikelompokkan menurut sesi, lalu menampilkan nama kelas, guru/mapel dari blok yang berjalan atau berikutnya, JP, jam WIB, status, waktu masuk/keluar bila tersedia, peringatan belum check-out, dan rincian konfigurasi yang belum lengkap. Buka **Jadwal & catatan** untuk seluruh blok pada tanggal tersebut, termasuk pertemuan selesai dan jadwal tanpa check-in.
- Guru/mapel yang tidak mempunyai blok berjalan/berikutnya tidak ditampilkan seolah sedang mengajar. Data pertemuan sebelumnya tetap tersedia di rincian/peringatan.

Warna dan penentuan status tetap berasal dari layanan pusat tahap 6. Peringatan laporan sebelumnya tidak mengubah warna utama jadwal berikutnya. Sistem melaporkan jadwal dan laporan ketua kelas, bukan mendeteksi kehadiran fisik guru.

## Konfigurasi belum lengkap vs tidak ada KBM

Dashboard mendeteksi periode belum tersedia, penempatan sesi belum ada/bertumpang tindih, pola JP sesi belum ada, pola hari ini tidak lengkap/tidak valid, pola tidak sesuai masa berlaku jadwal, jadwal kelas belum pernah dikonfigurasi pada penempatan sesi tersebut, serta ketua aktif yang belum tersedia. Rincian masalah ditampilkan di kartu dan dapat difilter melalui **Konfigurasi belum lengkap**. Indikator warna tetap mengikuti jadwal yang diketahui bila sudah tersedia, dengan peringatan konfigurasi terpisah.

Kelas yang sudah mempunyai konfigurasi jadwal tetapi tidak dijadwalkan pada hari tersebut menampilkan **Tidak ada jadwal KBM**, bukan dianggap gagal konfigurasi. Jejak jadwal yang sengaja dinonaktifkan tetap menjadi bukti konfigurasi sehingga penonaktifan semua blok tidak menghapus riwayat atau dianggap belum pernah diatur. Hari tanpa KBM eksplisit menampilkan alasan libur sesuai cakupan kelas/sesi. Data sesi yang belum diatur tidak disamakan dengan jadwal kosong. Bila memang ada hari tanpa KBM sebelum konfigurasi awal selesai, admin dapat menggunakan kalender hari tanpa KBM yang tersedia.

## Pembaruan otomatis dan kegagalan koneksi

Dashboard memakai Fetch API dengan interval **sekitar 12 detik setelah respons sebelumnya selesai**, tanpa reload halaman. Tidak memerlukan WebSocket, service tambahan atau framework baru. Filter dan tombol **Perbarui Sekarang** juga memuat bagian kartu/ringkasan saja. Rincian yang sedang dibuka dipertahankan bila kelas masih terlihat. Dropdown kelas ikut diperbarui jika data kelas berubah.

- Hanya satu request aktif per tab. Perubahan filter saat request berjalan digabung menjadi satu request lanjutan; respons filter lama tidak diterapkan.
- Timeout client 10 detik membatalkan request yang tersangkut. Request berikutnya dijadwalkan setelah request sebelumnya selesai/gagal.
- Tab tersembunyi tidak memulai polling baru; request yang sudah berjalan boleh selesai. Tab yang kembali aktif memperbarui segera. Navigasi dan pemulihan halaman dari cache browser ditangani agar polling dapat berjalan kembali tanpa dua timer.
- Saat jaringan, respons server atau pembacaan data gagal, kartu, ringkasan dan **waktu pembaruan terakhir yang berhasil** tetap dipertahankan. Pemberitahuan menyatakan data belum diperbarui; sistem mencoba kembali sekitar 12 detik kemudian. Hasil lama tidak diubah menjadi data kosong.
- Respons 401/403 menghentikan polling dan meminta muat ulang/login kembali. Notifikasi autentikasi tetap ditampilkan walaupun filter sempat berubah.
- Tanggal dan waktu pembaruan berasal dari `CURRENT_TIMESTAMP` server dengan UTC+7/Asia/Jakarta. Jam perangkat tidak menentukan status. Saat memilih Hari Ini, polling mengikuti pergantian tanggal server; tanggal riwayat tetap mengikuti pilihan pengguna.
- Data bukan real-time seketika: keterbaruan mengikuti interval, durasi request, keterlihatan tab dan koneksi. Keterangan ini tampil di halaman. Tanpa JavaScript, filter GET dan muat ulang manual tetap tersedia.

## Endpoint dan hak akses

Endpoint GET `admin/monitoring_kbm_data.php` dan `akademik/monitoring_kbm_data.php` memakai pembaca bersama `includes/monitoring_kbm_feed.php`. JSON berisi fragmen HTML kartu yang sudah di-escape, ringkasan, pilihan kelas, filter dan waktu server. Tidak mengirim password atau data akun siswa. HTML hanya dibentuk template server; nama kelas pada pilihan browser dimasukkan lewat `textContent`.

Server memeriksa ulang user, role dan status akun pada setiap request. Endpoint admin hanya untuk admin; endpoint akademik hanya untuk role Akademik yang sudah ada, tanpa hardcode nomor role baru. Guru, siswa biasa maupun ketua kelas ditolak oleh endpoint dashboard ini. Ketua tetap memakai halaman pelaporan siswa tersendiri. Akun yang dinonaktifkan ditolak pada request berikutnya. Pengaksesan helper feed langsung tidak menjadi pintu akses tambahan.

Endpoint hanya membaca (GET), menolak POST/aksi lain dengan 405, dan tidak menyediakan operasi check-in/out, edit jadwal, penunjukan ketua atau koreksi. Akademik tidak mendapat hak baru pada fasilitas admin/siswa. Filter divalidasi server; nilai array, sesi/status tidak dikenal, kelas tidak ditemukan dan tanggal tidak valid ditolak dengan 400. Tidak memakai input sebagai SQL mentah. Respons `Cache-Control: no-store, private`, tanpa CORS lintas origin; session ditutup setelah pemeriksaan akses agar polling tidak menahan kunci session halaman lain. CSRF tetap berlaku pada fasilitas tulis lama; endpoint ini tidak melakukan perubahan data.

## Efisiensi query dan indeks

Pembaca status kini memuat laporan, sesi kelas, pola/slot JP, kalender, konfigurasi jadwal, ketua dan laporan terbuka secara berkelompok, lalu membangun kartu di PHP. Slot untuk jadwal hari ini juga dimuat dalam satu query, bukan per jadwal. Pembacaan satu respons menggunakan snapshot transaksi konsisten dengan satu waktu server. Jumlah query tidak bertambah per kelas: pengujian membandingkan 4 dan 84 kelas dengan jumlah query sama, maksimal 14 SELECT/prepare untuk snapshot status (di luar pemeriksaan akses/kesiapan dan SET/transaksi).

Laporan terbuka dicari dari kandidat laporan/koreksi yang belum selesai, bukan melakukan lookup koreksi untuk seluruh pertemuan historis. Indeks koreksi terakhir per pertemuan dari tahap 3 tetap digunakan.

Siapkan backup dan uji di staging, lalu **terapkan manual** `database/2026_10_16_monitoring_kbm_dashboard.sql` sesudah migrasi tahap 3/4. Migrasi ini hanya menambahkan indeks:

- `kbm_laporan(checkout_at,pertemuan_id)`;
- `kbm_koreksi_admin(checkout_baru,checkin_baru,pertemuan_id,id)`.

MariaDB 10.4 mendukung ADD INDEX IF NOT EXISTS yang digunakan. Migrasi diuji dua kali untuk idempotensi, tidak mengganti data, role atau akun. Index tambahan direkomendasikan sebelum monitoring rutin digunakan agar pencarian laporan terbuka tidak memindai seluruh riwayat. Tidak ada migrasi produksi yang diterapkan otomatis.

## File dan pengujian

- Baru: `includes/monitoring_kbm_dashboard.php`, `includes/monitoring_kbm_cards.php`, `includes/monitoring_kbm_feed.php`, endpoint di kedua area, CSS/JS `monitoring-kbm-dashboard`, migrasi indeks, dan `tests/monitoring-kbm-polling.test.cjs`.
- Diperbarui: view bersama `includes/monitoring_kbm_board.php`, pembaca status/data, serta pengujian integrasi `tests/monitoring-kbm-siswa.test.cjs`. Rute dashboard tahap 6 tetap digunakan.
- Tes polling: request tidak tumpang tindih, penggabungan perubahan filter, mengabaikan respons lama, interval/retry, timeout, tab tersembunyi/aktif, akses kedaluwarsa, DOM tetap menyimpan data dan timestamp ketika koneksi gagal lalu diperbarui saat pulih.
- Tes integrasi: kelompok sesi, filter dan ringkasan, pesan konfigurasi, perpindahan merah → hijau → kuning setelah laporan server berubah, timestamp check-in/out, pembacaan tanpa penulisan, izin admin/Akademik, penolakan role lain/akun nonaktif/anonim/POST, indeks idempoten, serta jumlah query tetap ketika jumlah kelas bertambah.
- Lint PHP, `node --check` JavaScript dan `git diff --check`; regresi `node --test tests/*.test.cjs`.

Pada verifikasi ini server LMS normal sudah aktif di port 3306. Pengaman test menolak menulis ke server tersebut. Tes database dijalankan dari **salinan kode khusus test** yang koneksinya diarahkan ke MariaDB sementara di **3307**, setelah `@@datadir` diverifikasi sesuai marker test. Perubahan port hanya pada salinan test, bukan konfigurasi workspace/LMS. Jangan menjalankan tes database pada server produksi. Pemeriksaan UI mencakup render server dan simulasi DOM/polling; belum berupa pemeriksaan visual di browser/HP nyata.

Tahap ini berhenti pada dashboard dan akses monitoring baca. Tidak menambah ekspor atau antarmuka perubahan riwayat.
