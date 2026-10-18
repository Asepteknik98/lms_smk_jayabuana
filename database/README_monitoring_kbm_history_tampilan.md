# Tampilan Riwayat Monitoring KBM

Perubahan view di `includes/monitoring_kbm_history_page.php` dan CSS khusus
`assets/css/monitoring-kbm-history.css`. Filter tanggal, sesi dan kelas tetap
terlihat. Filter guru, status dan jumlah baris berada dalam panel Filter
lainnya yang otomatis terbuka ketika opsi tersebut sedang digunakan.

Tombol Excel ditempatkan pada bilah ekspor, tetap mengunduh seluruh hasil
filter menggunakan endpoint yang sudah ada. Tabel memakai header tetap dan
baris berselang; catatan panjang tersedia melalui Catatan laporan di setiap
baris. Status tetap terlihat dengan warna dari layanan pusat. Tampilan HP
menggunakan baris berbentuk kartu dan kontrol sentuh minimal 44px.

Seluruh query, validasi, CSRF, endpoint, hak akses, pagination, data ekspor,
form koreksi dan jejak audit tetap mengikuti implementasi sebelumnya. Tidak
ada migrasi database. Tampilan detail audit akademik ikut memakai CSS bersama;
halaman rekap matriks akademik tidak diubah.

Pemeriksaan yang dijalankan: PHP lint lulus. Pengujian
`monitoring-kbm-history.test.cjs` dan `monitoring-kbm-export.test.cjs` lulus:
validasi filter tanggal/ID serta teks aman, waktu kosong, dan baca ulang Excel
dengan 2.000 baris. Belum memeriksa tampilan manual pada perangkat pengguna.

Pengujian integrasi `monitoring-kbm-siswa.test.cjs` pada salinan TEMP dan
MariaDB terisolasi port 3307 lulus (74,2 detik), termasuk render riwayat admin
dan akademik, koreksi/audit, akses role serta unduhan. Database utama tidak
diubah atau dimigrasi.
