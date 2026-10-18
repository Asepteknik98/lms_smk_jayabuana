# Status pusat Monitoring KBM — tahap 6

Pembaruan tahap 7: dashboard kini memiliki filter, pengelompokan sesi, ringkasan dan polling 12 detik. Pembacaan status telah dibatch dan dilengkapi deteksi konfigurasi belum lengkap. Lihat `README_monitoring_kbm_dashboard.md` untuk indeks tambahan dan cara pengujian.

## Pemakaian

Tidak ada migrasi tambahan pada tahap 6. Gunakan skema tahap 3 dan 4 yang sudah disiapkan, dengan konfigurasi sekolah dari admin. Tidak ada migrasi produksi yang dijalankan oleh pengerjaan ini.

- Admin: **Monitoring → Monitoring KBM**, `admin/monitoring_kbm_status.php`.
- Akademik: dashboard `akademik/index.php` langsung menampilkan kartu seluruh kelas.
- Ketua kelas: menu Monitoring KBM siswa tetap memakai izin dan alur pelaporan tahap 5, kini menggunakan status pusat.

Admin dan Akademik memakai tampilan kartu bersama sesuai arah contoh gambar: nama kelas besar, guru, mata pelajaran, sesi/JP, latar hijau/merah/kuning, teks status dan peringatan terpisah. Grid empat kolom di layar besar menyesuaikan menjadi tiga, dua, lalu satu kolom pada HP. Buka **Jadwal & catatan** untuk detail setiap blok. Warna selalu disertai teks dan ikon. Semua teks dari database di-escape sebelum ditampilkan.

Pilih tanggal pada halaman monitoring untuk membaca riwayat, atau **Hari Ini** untuk kembali ke monitoring saat ini. Tanggal hanya boleh sampai tanggal server hari ini. Halaman monitoring Admin/Akademik hanya menerima GET/HEAD dan tetap memakai guard server masing-masing role. Akademik tidak mendapat hak menulis. Siswa biasa/guru tidak mendapat akses. Jika skema belum lengkap, dashboard memberi petunjuk belum dikonfigurasi tanpa menjalankan migrasi.

## Sumber kebenaran tunggal

`includes/monitoring_kbm_status.php` menyediakan:

- `kbm_meeting_status($meeting,$now)`: status satu pertemuan dari rencana waktu dan check-in/out efektif (termasuk koreksi admin terakhir).
- `kbm_class_status($meetings,$context,$open,$now)`: status utama kelas ditambah daftar peringatan terpisah.
- `kbm_monitoring_snapshot($db,$classId=null,$date=null)`: pembacaan data dan status untuk satu atau seluruh kelas. Satu `CURRENT_TIMESTAMP` database UTC+7 menjadi waktu acuan seluruh kelas pada satu respons. Pembacaan memakai transaksi REPEATABLE READ agar jadwal/laporan/koreksi konsisten dalam satu snapshot; tidak mengubah isolation level global. Tidak melakukan INSERT/UPDATE/DELETE atau mengisi checkout.

Controller Admin/Akademik memanggil view bersama `includes/monitoring_kbm_board.php`; siswa memakai layanan yang sama untuk ringkasan kelas, pertemuan, dan riwayat hari ini. Kelayakan waktu check-in pada helper siswa juga mengikuti status pusat untuk blok terjadwal. Validasi server, CSRF, mutex kelas dan idempotensi tahap 5 tetap berlaku.

Warna tidak disimpan sebagai penentu status. Nilai NULL dari koreksi admin tetap dihormati, tidak diganti kembali dengan waktu laporan asli. Timestamp dari masa depan tidak dianggap sebagai kejadian yang sudah berlangsung. Parameter `$now` hanya input internal layanan untuk pengujian; halaman tidak menerima waktu perangkat/browser atau parameter jam dari pengguna. Waktu sekolah tetap Asia/Jakarta/WIB.

## Aturan pertemuan

| Kondisi | Warna | Keterangan |
| --- | --- | --- |
| Sebelum rencana mulai, belum check-in | Kuning | Belum waktunya KBM |
| Mulai ≤ sekarang < selesai, belum check-in | Merah | Guru belum check-in |
| Sudah check-in, belum check-out | Hijau | Guru sudah check-in |
| Sudah check-out | Kuning | KBM selesai |
| Rencana selesai terlewati, tidak pernah check-in efektif | Kuning | Tidak tercatat check-in |
| Rencana selesai terlewati, sudah check-in tetapi belum check-out | Hijau | Guru sudah check-in, peringatan **Jadwal berakhir, belum check-out** |

Awal rentang inklusif, akhir eksklusif. Perpindahan JP di dalam satu blok (contoh JP 1–2) tidak menghasilkan pertemuan/status ganda. Pada batas akhir blok, jadwal berikutnya atau istirahat langsung menjadi acuan status utama kelas.

## Aturan kelas dan peringatan

1. Pengecualian hari tanpa KBM menampilkan kuning beserta alasannya. Istirahat pada pola sesi menampilkan kuning **Istirahat**; rentangnya juga memakai mulai inklusif/selesai eksklusif.
2. Pada hari aktif, status utama mengikuti blok yang sedang berlangsung. Blok berikutnya tanpa check-in tetap merah walaupun ada laporan lama yang masih terbuka.
3. Bila tidak ada blok berjalan: sebelum/di sela jadwal berikutnya kuning **Belum waktunya KBM**; sesudah blok terakhir kuning **KBM selesai**; hari tanpa jadwal kuning **Tidak ada jadwal KBM**. Kelas tanpa penempatan sesi pada periode tanggal tersebut diberi keterangan konfigurasi belum lengkap.
4. Pertemuan sebelumnya yang belum check-out ditampilkan terpisah dengan tanggal, guru dan JP. Tidak mengganti warna utama kelas. Pertemuan yang sedang menjadi status utama tidak diduplikasi sebagai peringatan sebelumnya.
5. Pada hari berikutnya, hanya jadwal tanggal baru yang menjadi kandidat status utama. Laporan hari lama tetap ada di peringatan, sehingga tidak membuat kelas terus hijau.
6. Jika tidak ada jadwal berjalan setelah akhir sesi dan masih ada laporan terbuka, kartu utama kuning; detail pertemuan lama tetap hijau dengan peringatan. Inilah perbedaan status **kelas** dan **pertemuan**.

Status tersebut menyatakan kondisi jadwal dan laporan, bukan kesimpulan kehadiran fisik guru. Tidak ada penutupan otomatis, waktu selesai tiruan, atau perubahan durasi laporan.

## Riwayat tanpa check-in

Riwayat mengambil gabungan pertemuan tersimpan dan jadwal bertanggal yang belum memiliki laporan melalui `kbm_day_report()`, lalu memberi status pusat pada setiap blok. Jadwal berakhir tanpa check-in tampil **Tidak tercatat check-in**, termasuk jika belum ada baris `kbm_pertemuan` atau `kbm_laporan`. Jadwal/versi historis tetap menjadi sumber arsip. GET riwayat tidak membuat laporan atau timestamp palsu. Tidak diperlukan tabel warna atau pencatatan ketidakhadiran fisik otomatis.

Pada tanggal riwayat, kartu kelas merangkum kondisi akhir tanggal terpilih; detail pertemuan menggunakan catatan efektif terbaru yang tersedia saat dibaca. Ini bukan rekonstruksi seluruh perubahan data “as of” masa lalu. Koreksi admin terakhir dan checkout terlambat tetap terlihat sebagai catatan aktual. Peringatan hanya mengambil laporan yang saat ini masih terbuka dan berasal dari tanggal terpilih atau sebelumnya.

## File dan pemeriksaan

- Baru: `includes/monitoring_kbm_status.php`, `includes/monitoring_kbm_board.php`, `admin/monitoring_kbm_status.php`, `tests/monitoring-kbm-status.test.cjs`, dokumentasi ini.
- Diperbarui: `akademik/index.php`, `siswa/monitoring_kbm.php`, `includes/monitoring_kbm_siswa.php`, `includes/sidebar.php`, `tests/monitoring-kbm-siswa.test.cjs`.
- `node --test tests/monitoring-kbm-status.test.cjs`: pengujian aturan murni, tidak membutuhkan database. Mencakup tepat mulai/selesai, pergantian JP/blok, istirahat awal/akhir, akhir sesi, pergantian hari, jadwal kosong, libur, sesi siang, pembatalan waktu melalui koreksi, checkout dan checkout terlupa.
- Pengujian integrasi menggunakan database MariaDB sementara yang datadir-nya diverifikasi terhadap marker test. Memeriksa status yang sama pada admin/akademik/siswa, detail riwayat tanpa check-in, peringatan terpisah, akses role, penolakan POST Akademik/Admin pada halaman baca, tanggal invalid, serta GET tidak menambah pertemuan.
- `node --test tests/*.test.cjs`: regresi seluruh modul termasuk pelaporan siswa, permintaan bersamaan, konfigurasi admin dan akun Akademik.
- Lint PHP dan `git diff --check`. Pemeriksaan tampilan mencakup render HTML, struktur responsif dan sintaks JavaScript; belum ada uji visual otomatis di browser atau HP.

Tahap 6 selesai pada layanan status dan integrasi tampilannya. Tidak melanjutkan ekspor, koreksi melalui halaman baru, atau tahap berikutnya secara otomatis.
