# Laporan admin Monitoring KBM

Pada Pengaturan Monitoring KBM, buka **Laporan KBM** (sebelumnya Laporan Hari Ini).
Pilih Harian, Mingguan, atau Bulanan, tentukan tanggal acuan, lalu klik
Tampilkan Laporan. Nama hari mengikuti tanggal pilihan. Tombol Hari Ini
mengembalikan pilihan ke laporan harian hari ini berdasarkan waktu server WIB.

Mingguan menggunakan Senin–Minggu; bulanan menggunakan bulan kalender dari
tanggal acuan. Akhir periode berjalan dibatasi sampai hari ini. Tanggal masa
depan dan masukan tidak valid ditolak. Tabel menampilkan 25 catatan per halaman.

Unduh Excel memakai endpoint admin yang sudah ada `admin/riwayat_kbm_export.php`
dan rentang tanggal persis seperti tabel. Unduhan mencakup seluruh hasil,
termasuk pembatalan dan koreksi, tanpa batas pagination. Kolom mencakup nomor,
hari, tanggal, tahun ajaran, semester, sesi, kelas, guru, mata pelajaran, JP awal
dan akhir, waktu jadwal, check-in/out aktual, durasi, pelapor, status, catatan,
dan identitas/alasan/waktu koreksi. Waktu belum tercatat dibiarkan kosong di
Excel; durasi belum lengkap tidak dianggap final. Ekspor tetap dibatasi 50.000
catatan/100 MB dengan pesan bila perlu mempersempit filter.

Tidak ada migrasi atau perubahan aturan check-in/out, hak akses, maupun data
lama. Gunakan migrasi Monitoring KBM yang sudah diperlukan sebelumnya.

## Pemeriksaan yang dijalankan

- PHP lint tiga berkas halaman/helper: lulus.
- `node --test tests/monitoring-kbm-report.test.cjs`: harian, batas minggu,
  lintas tahun, Februari kabisat, bulan berjalan, masukan tidak valid: lulus.
- `node --test tests/monitoring-kbm-export.test.cjs`: 2.000 baris, teks literal
  aman, waktu kosong dan baca ulang workbook: lulus.
- Pada salinan TEMP dengan MariaDB terisolasi port 3307:
  `node --test tests/monitoring-kbm.test.cjs tests/monitoring-kbm-history.test.cjs tests/monitoring-kbm-export.test.cjs tests/monitoring-kbm-report.test.cjs`:
  4 pemeriksaan lulus. Setelah perluasan pengujian integrasi, pemeriksaan
  `monitoring-kbm.test.cjs` diulang dan lulus: halaman admin ketiga periode,
  tautan filter unduhan, penolakan role lain, validasi tanggal, jumlah baris
  Excel dibandingkan riwayat, 24 kolom, tanggal setiap baris sesuai filter,
  tanpa formula, dan pembacaan ulang workbook untuk ketiga periode.
- Belum diuji manual pada Microsoft Excel atau perangkat Android/iPhone.
- Tidak menjalankan migrasi/deployment pada database utama.

## Pembaruan tampilan laporan

Tampilan menggunakan CSS khusus `assets/css/monitoring-kbm-report.css` yang
dibatasi pada `.kbm-report`. Ringkasan hari/periode/jumlah menjadi kartu;
tabel menggunakan header tetap, baris berselang, badge JP, dan label status
yang mengikuti warna layanan status pusat. Waktu aktual ditampilkan dengan
jam dan tanggal terpisah, tetap WIB. Pada layar kecil kartu menyesuaikan dan
tabel dapat digeser. Filter, query, pagination, dan isi unduhan tidak berubah.

Setelah perubahan tampilan: lint PHP lulus; pemeriksaan terisolasi
`monitoring-kbm.test.cjs`, `monitoring-kbm-report.test.cjs`, dan
`monitoring-kbm-export.test.cjs` lulus (3 pemeriksaan). Termasuk render halaman,
kompilasi JavaScript inline, filter periode dan baca ulang Excel. Belum
memverifikasi tampilan secara langsung pada browser/perangkat pengguna.
