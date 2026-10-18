# Tampilan monitoring admin yang ringkas

Perubahan hanya pada view `includes/monitoring_kbm_board.php` dan CSS khusus
`assets/css/monitoring-kbm-admin-board.css`. CSS dibatasi pada
`.kbm-admin-monitor`, sehingga tampilan layar monitoring akademik tetap memakai
desain sebelumnya.

- Filter berada dalam panel yang dapat dibuka/tutup. Panel terbuka otomatis
  bila tanggal bukan hari ini atau filter sesi/kelas/status sedang digunakan.
- Ringkasan tiga warna menjadi satu baris kartu kecil dengan jumlah kelas.
- Keterangan warna dipindahkan ke panel Panduan warna.
- Judul, informasi pembaruan, dan kartu kelas lebih ringkas. Tidak ada data
  kelas, peringatan belum check-out, atau konfigurasi yang dihilangkan.
- ID elemen, form GET, tautan riwayat, sumber status dan interval polling tidak
  berubah. Tidak ada migrasi.

Pemeriksaan: lint PHP lulus. Lima pemeriksaan status/polling lulus, termasuk
batas waktu, request bersamaan, mempertahankan data ketika koneksi gagal,
memulihkan pembaruan, menghentikan polling tab tidak aktif, dan sesi berakhir.
Pemeriksaan integrasi `tests/monitoring-kbm-siswa.test.cjs` pada salinan TEMP
dengan MariaDB terisolasi port 3307 juga lulus (69,4 detik), mencakup dashboard,
akses role, pelaporan, riwayat, ekspor, dan request bersamaan. Database utama
tidak dimigrasi. Tampilan belum diperiksa manual pada perangkat pengguna.

## Kartu kelas lebih kecil

Kartu admin kini setinggi 140px, dengan kolom otomatis minimal 175px pada
desktop dan dua kolom pada HP. Kartu menampilkan kelas, guru, status, serta
penanda catatan belum selesai/konfigurasi. Warna seluruh kartu tetap mengikuti
status pusat. Rincian yang sebelumnya memenuhi kartu dipindahkan ke dialog
detail: klik kartu atau fokuskan dengan keyboard lalu tekan Enter. Dialog
menggunakan handler yang sudah ada di `monitoring-kbm-dashboard.js`, tetap
diperbarui saat polling, dan mengembalikan fokus saat ditutup. Rincian waktu,
konfigurasi dan riwayat tidak dihapus. Tanpa JavaScript, rincian tetap tampil
di kartu melalui aturan noscript. Tampilan akademik tidak diubah.

Setelah perubahan ini: lint PHP dan empat pengujian polling/DOM lulus.
