# Tahap 9 — Unduh Excel riwayat Monitoring KBM

Admin membuka **Riwayat Monitoring KBM**, memilih filter, menekan **Tampilkan Riwayat**, lalu **Unduh Excel Semua Hasil**. Tombol menggunakan filter yang sudah diterapkan, bukan perubahan formulir yang belum ditampilkan. Akun akademik tidak mendapatkan tombol dan endpoint menolak semua role selain admin.

## File dan integrasi

- `admin/riwayat_kbm_export.php`: autentikasi/status akun melalui guard LMS, hanya admin dan metode GET, respons attachment setelah file selesai dibangun.
- `includes/monitoring_kbm_export.php`: pemetaan 24 kolom, validasi filter, query seluruh hasil, pembuatan file sementara dan pembatasan ukuran.
- `includes/monitoring_kbm_history.php`: kondisi SQL filter dipakai bersama oleh pagination dan ekspor; sumber arsip/virtual pertemuan dan aturan status tetap sama.
- `includes/monitoring_kbm_history_page.php`: tombol khusus admin.
- `includes/rekap_akademik.php`: writer Excel LMS yang sudah ada mendapat mode iterable `ra_excel_parts`; pemanggilan lama `ra_excel` tetap tersedia.

Format mengikuti ekspor proyek: SpreadsheetML Excel 2003 dengan ekstensi `.xls`, bukan XLS biner atau XLSX. Pustaka SimpleXLS/SimpleXLSX yang tersedia merupakan pembaca; tidak ditambahkan dependensi penulis baru. Aplikasi spreadsheet tertentu dapat memberi pemberitahuan format/ekstensi untuk format XML ini, sebagaimana ekspor LMS sebelumnya.

## Isi dan keamanan

Terdapat nomor, hari, tanggal, tahun ajaran, semester, sesi, kelas, guru terjadwal, mata pelajaran, JP awal/akhir, waktu jadwal awal/akhir, check-in/out efektif, durasi, dua pelapor asli, status pusat, catatan, penanda koreksi, identitas numerik admin koreksi, waktu dan alasan koreksi terakhir. Nama historis menggunakan sumber riwayat, termasuk pertemuan tanpa check-in. Seluruh audit sebelum/sesudah tetap tersedia pada halaman detail; unduhan menampilkan keadaan efektif dan penanda koreksi terakhir.

Tanggal dan timestamp menggunakan sel DateTime dengan format tanggal/waktu; durasi memakai angka Excel dengan format `[h]:mm:ss`, sehingga durasi melebihi 24 jam tidak terpotong. Waktu/pelapor yang tidak tercatat benar-benar sel kosong; catatan menjelaskan ketidaklengkapan. Durasi final kosong jika salah satu waktu belum tersedia. Waktu menggunakan server/WIB sebagaimana riwayat.

Semua kolom teks secara eksplisit bertipe String tanpa atribut Formula, termasuk teks berawalan `=`, `+`, `-`, atau `@`. Karakter XML di-escape. Tidak mengekspor password, token, username akun atau profil akun lain. Identitas admin hanya `Admin #ID` untuk penanda koreksi.

## Konsistensi dan hasil besar

- Ekspor hanya menerima enam filter riwayat: tanggal mulai/selesai, sesi, kelas, guru, status. Pagination dan parameter detail tidak memengaruhi hasil.
- COUNT dan pembacaan seluruh hasil memakai snapshot transaksi REPEATABLE READ dan satu waktu server; jumlah baris keluaran diverifikasi terhadap COUNT.
- SELECT data tidak menggunakan LIMIT pagination, memakai cursor PDO tanpa buffer dan writer iterable ke file sementara, bukan memuat semua baris ke memori aplikasi.
- Batas 50.000 pertemuan, ukuran 100 MB, rentang pencarian maksimal 366 hari dan batas waktu endpoint 120 detik. Jika batas terlampaui atau hasil kosong, kembali ke riwayat dengan pesan; tidak mengirim file parsial atau memotong hasil secara diam-diam.
- File sementara otomatis ditutup setelah dikirim atau bila gagal; respons tidak boleh di-cache. Session lock dilepas selama persiapan unduhan.

Tidak ada migrasi baru pada tahap ini. Gunakan fondasi/migrasi Monitoring KBM tahap sebelumnya sesuai prosedur penerapan proyek; tidak menjalankan migrasi produksi otomatis.

## Pemeriksaan

Tes integrasi pada server database terisolasi membandingkan jumlah/isi file dengan riwayat, mencakup beberapa halaman, filter gabungan, pertemuan tanpa laporan, koreksi admin dan durasi lintas hari; menguji hasil kosong, pembatasan hasil lebih dari 50.000 serta akses admin/akademik/guru/siswa/anonim/metode POST. Tes writer membaca kembali 2.000 baris melalui pembaca Excel proyek dan DOM XML, memeriksa tipe teks menyerupai formula, sel kosong dan struktur workbook. Pemeriksaan ini tidak membuka aplikasi Microsoft Excel desktop secara otomatis.
