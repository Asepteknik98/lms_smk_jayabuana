# Maksimal empat pengguna Monitoring KBM per kelas

Admin dapat menunjuk 1, 2, 3, atau 4 pengguna sesuai kebutuhan. Tidak wajib empat. Penambahan dilakukan satu per satu melalui **Pengaturan Monitoring KBM → Pengguna → pilih kelas → Tampilkan Siswa → pilih siswa aktif → Tambah Pengguna**.

Pengguna yang sudah ditunjuk tidak dicabut ketika pengguna lain ditambahkan. Halaman menampilkan jumlah slot (misalnya 2/4), seluruh nama pengguna, dan tombol Cabut Penugasan untuk masing-masing. Ketika mencapai 4, formulir penambahan tidak ditampilkan; server tetap menolak permintaan pengguna kelima. Untuk mengganti orang, cabut orang yang dimaksud, lalu tambahkan penggantinya.

Slot penugasan tetap dicadangkan sampai dicabut, termasuk saat akun dinonaktifkan atau siswa pindah kelas. Orang tersebut langsung tidak mempunyai akses, tetapi nama/slotnya tetap tampil agar admin dapat mencabutnya. Dengan demikian mengaktifkan akun kembali tidak membuat jumlah penugasan melebihi empat. Pengguna duplikat tidak menghabiskan slot baru.

## Izin dan riwayat

- Seluruh pengguna memakai akun siswa masing-masing; tidak ada role atau akun baru.
- Izin dibaca per siswa yang ditunjuk. Maksimal empat izin terbaru per kelas dapat digunakan; penugasan lama duplikat orang yang sama tidak menggandakan akses.
- Akun harus aktif, role siswa, dan masih berada di kelas yang ditunjuk. Pencabutan berlaku pada request berikutnya dan hanya mencabut orang tersebut.
- Check-in dan check-out dapat dilakukan oleh pengguna berbeda yang sama-sama diberi izin. Identitas/nama pelapor setiap tombol tetap disimpan secara terpisah.
- Mutex kelas dan perlindungan klik ganda tetap dipakai, sehingga penambahan pengguna tidak mengizinkan dua pertemuan aktif atau pencatatan ganda.
- Pencabutan tidak menghapus baris penugasan atau laporan lama. FK pelapor, waktu, pembatalan, dan koreksi tetap utuh.

## Perubahan dan penerapan

Perubahan berada di `includes/monitoring_kbm_simple.php` dan `includes/monitoring_kbm_simple_page.php`. Memakai tabel, indeks, transaksi admin, dan CSRF yang sudah ada. Tidak memerlukan migrasi tambahan pada instalasi Monitoring KBM sederhana.

Uji terkait diperluas di `tests/monitoring-kbm-simple.test.cjs` (penambahan, batas empat, permintaan admin bersamaan, pelapor berbeda, pencabutan, akun nonaktif, pindah kelas, dan keterjagaan riwayat) serta `tests/monitoring-kbm-siswa.test.cjs` (daftar empat pengguna, formulir penuh, pencabutan satu orang dan akses pengguna lain). Pengujian dijalankan pada MariaDB terisolasi, bukan database utama.

Hasil aktual: uji database sederhana/multi-pengguna lulus (2,1 detik), uji fondasi/pengaturan admin lulus (2,5 detik), dan uji integrasi siswa/admin/akademik diperluas dijalankan ulang dan lulus (73,8 detik). Uji terakhir sempat terhenti karena setup fixture memakai dua statement dalam satu `PDO::query`; fixture dipisah sebelum pengulangan yang lulus. Sintaks dua berkas PHP berubah lulus. Database utama tidak diubah; pengujian visual pada perangkat nyata belum dilakukan.

## Daftar kelas yang lebih ringkas

Tinggi tabel dibatasi dengan gulir di dalam daftar dan header tetap terlihat. Baris/tombol dibuat lebih padat pada desktop, sesi memakai badge kecil, dan pencarian kelas bekerja langsung di tampilan. Jumlah pengguna menjadi ringkasan yang dapat dibuka untuk melihat nama dan tombol pencabutan; semua form POST/CSRF dan logika penugasan tetap sama. Pencarian mempunyai jumlah hasil serta pesan kelas tidak ditemukan. Ukuran tombol HP tetap minimal 44px.

Pemeriksaan pembaruan tampilan: sintaks PHP/JavaScript lulus, render halaman dan validasi pengaturan admin pada uji `monitoring-kbm.test.cjs` lulus (3 detik), serta simulasi pencarian tanpa membedakan huruf besar/kecil, hasil kosong, dan pengosongan pencarian lulus. Tidak membutuhkan migrasi atau perubahan data.
