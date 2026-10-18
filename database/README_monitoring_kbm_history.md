# Riwayat Monitoring KBM — tahap 8

## Akses dan penggunaan

- Admin: **Monitoring → Riwayat KBM**, `admin/riwayat_kbm.php`. Dapat membaca detail dan menyimpan koreksi dengan alasan wajib.
- Akademik: menu **Riwayat KBM**, `akademik/riwayat_kbm.php`. Dapat membaca seluruh data dan audit, tanpa formulir koreksi. POST ditolak 403.
- Dashboard kedua area juga memiliki tautan **Riwayat & Detail KBM**. Guru, siswa biasa, dan ketua kelas ditolak oleh halaman riwayat melalui guard server. Ketua tetap menggunakan catatan kelas hari ini pada halaman siswa.

Default pencarian adalah awal bulan hingga tanggal server hari ini. Filter tersedia untuk rentang tanggal, sesi, kelas, guru, serta status (termasuk **Tidak tercatat check-in**, **Sudah check-in / belum check-out**, dan **Hasil koreksi admin**). Tanggal awal/akhir inklusif. Untuk menjaga beban query, satu pencarian dibatasi **366 hari**; pilih rentang lain untuk tahun yang berbeda. Tanggal masa depan, format invalid, nilai array dan identifier tidak valid ditolak.

Pagination dilakukan di server, dengan 10/25/50 baris per halaman dan urutan stabil tanggal terbaru, waktu jadwal, kelas, dan ID jadwal. Filter tetap terbawa pada tautan pagination maupun detail. Permintaan halaman melampaui hasil diarahkan ke halaman terakhir yang tersedia.

## Data yang ditampilkan

Hari/tanggal memakai nama hari dan bulan Indonesia, termasuk tahun. Setiap baris memuat tahun ajaran, semester, sesi, nama kelas, guru, mata pelajaran, rentang JP, tanggal/jam jadwal, waktu masuk dan keluar efektif, durasi bila lengkap, pelapor masuk/keluar, status pusat dan catatan.

Tampilan desktop memakai tabel; pada HP setiap baris berubah menjadi blok dengan label per isian. **Detail / Audit** menampilkan laporan siswa asli berdampingan dengan catatan efektif serta seluruh riwayat koreksi. ID siswa asli tetap tersedia pada detail, dan nama pelapor menggunakan snapshot laporan, bukan nama profil yang mungkin kemudian berubah. Admin pembuat koreksi ditampilkan sebagai ID permanen dan username akun terkait. Username pada tampilan dibaca dari akun; identitas audit yang tetap adalah `admin_id` dengan FK pelindung penghapusan.

Jika check-in belum ada, durasi belum tersedia. Jika check-in ada tetapi checkout belum ada, tampil **Belum check-out**, tanpa durasi final tiruan. Durasi lintas tanggal dihitung dari kedua timestamp aktual dengan zona Asia/Jakarta; penutupan terlambat dapat lebih lama daripada jam rencana.

## Jadwal tanpa check-in dan arsip lama

`includes/monitoring_kbm_history.php` menghasilkan gabungan:

1. `kbm_pertemuan` yang sudah disimpan, dengan LEFT JOIN laporan dan koreksi terakhir;
2. jadwal mingguan yang diekspansi per tanggal dalam rentang, termasuk jadwal yang belum memiliki baris pertemuan/laporan.

Cabang kedua tidak menyertakan pasangan jadwal/tanggal yang sudah tersimpan, sehingga tidak menggandakan hasil. Masa berlaku periode, sesi, pola JP dan versi jadwal, hari ISO, pengecualian kalender, JP berurutan, serta larangan melintasi istirahat tetap diperiksa. Pertemuan pada versi jadwal yang sudah nonaktif dikecualikan hanya sejak tanggal efektif nonaktif, bukan tanggal masa lalu sebelumnya.

Nama/identitas historis berasal dari snapshot pertemuan atau snapshot jadwal, bukan nama master terbaru. Slot dan pola versi lama tidak ditimpa oleh pengaturan admin; pergantian jadwal membuat versi baru. Filter kelas/guru juga mengambil pilihan dari arsip, sehingga pergantian nama master tidak menghilangkan akses ke baris lama. Jika satu identitas mempunyai lebih dari satu nama historis, pemilih menyajikan satu nama arsip; nama pada setiap baris tetap sesuai snapshot masing-masing.

Jadwal yang sudah berakhir dan tidak memiliki check-in efektif tetap tampil **Tidak tercatat check-in**. Status tersebut menyatakan ketiadaan laporan, bukan kesimpulan otomatis tentang keberadaan fisik guru. GET riwayat/detail tidak membuat baris laporan, tidak membuat jam checkout, dan tidak menulis warna sebagai sumber kebenaran.

Query menggunakan recursive CTE MariaDB 10.4 untuk tanggal (maksimal 366 iterasi). COUNT dan halaman hasil dijalankan dalam satu transaksi pembacaan REPEATABLE READ dengan satu waktu server. Filter status memakai pendamping SQL pada layanan status pusat; label/warna hasil tetap dihitung oleh `kbm_meeting_status()`. Pengujian memeriksa kesesuaian kode status query dengan layanan PHP.

## Koreksi admin

1. Pilih **Detail / Koreksi** pada baris yang akan diperbaiki.
2. Buka **Lengkapi / Koreksi Catatan**. Masukkan waktu aktual yang sudah diverifikasi dalam WIB, kemudian alasan wajib (maksimal 1.000 karakter).
3. Kosongkan waktu jika perlu membatalkan catatan keliru. Checkout tidak dapat disimpan tanpa check-in. Check-in koreksi harus berada pada tanggal pertemuan; checkout dapat berada pada tanggal berikutnya untuk penutupan terlambat. Keduanya tidak boleh di masa depan, dan checkout tidak boleh lebih awal daripada check-in. Pertemuan yang belum dimulai belum dapat dikoreksi.
4. Simpan. Catatan efektif mengikuti koreksi terakhir; label **Hasil koreksi admin** membedakannya dari **Laporan langsung siswa**. Pelapor siswa asli tetap dipertahankan. Waktu yang ditambahkan admin tidak diatribusikan seolah dilaporkan siswa.

Jika pertemuan belum mempunyai baris tersimpan, POST admin membentuk snapshot pertemuan tersebut lalu menambahkan koreksi **dalam transaksi yang sama**. Tidak membuat laporan siswa palsu. Bila validasi gagal, seluruh perubahan dibatalkan, termasuk pembentukan pertemuan baru.

Tabel `kbm_koreksi_admin` menyimpan waktu masuk/keluar sebelum dan sesudah (termasuk NULL), ID admin, alasan serta `CURRENT_TIMESTAMP` waktu koreksi. `kbm_laporan` tidak ditimpa. Semua koreksi lama tetap dapat dibaca, bukan hanya koreksi terakhir. Tidak tersedia tombol hapus permanen atau operasi penghapusan pada controller.

POST memerlukan admin aktif dan CSRF. Mutex kelas yang sama dengan pelaporan siswa digunakan, ditambah penguncian transaksi admin. Fingerprint revisi dibandingkan dengan kondisi terbaru setelah kunci diperoleh. Jika siswa/admin lain sudah mengubah catatan sejak form dibuka, permintaan ditolak agar admin memeriksa nilai terbaru sebelum menyimpan lagi. Membiarkan koreksi check-in tanpa checkout tetap merupakan pertemuan terbuka dan tidak boleh membuka pertemuan aktif kedua pada kelas yang sama.

## Penerapan dan file

Tidak memerlukan migrasi baru pada tahap ini; kolom laporan asli dan audit sudah tersedia dari tahap 3. Gunakan skema tahap 3/4 dan indeks tahap 7 yang sudah disiapkan, lalu deploy perubahan helper dan halaman bersama. Tidak ada migrasi atau perubahan data produksi yang dijalankan otomatis.

- Baru: `includes/monitoring_kbm_history.php` (query/filter/pagination/revisi), `includes/monitoring_kbm_history_page.php` (view/controller bersama), `admin/riwayat_kbm.php`, `akademik/riwayat_kbm.php`, dan `tests/monitoring-kbm-history.test.cjs`.
- Diperbarui: `includes/monitoring_kbm_data.php` (insert audit internal yang dapat dipakai satu transaksi; validasi checkout terlambat dan waktu masa depan), `includes/monitoring_kbm_status.php` (pendamping SQL kode status), sidebar dan tautan dashboard, serta pengujian integrasi siswa/monitoring yang memakai fixture terisolasi.

## Pemeriksaan

- Filter: rentang inklusif satu hari, nama arsip, sesi, kelas, guru, status dan koreksi, pagination 10 baris serta halaman berikutnya; penolakan tanggal terbalik/invalid/masa depan/rentang terlalu panjang dan input terstruktur yang tidak valid.
- Tanpa laporan: jadwal tanpa `kbm_pertemuan`/`kbm_laporan` tetap muncul sebagai **Tidak tercatat check-in**; membaca riwayat tidak menambah pertemuan.
- Koreksi: menambahkan waktu tanpa mengarang pelapor siswa, mempertahankan waktu asli NULL maupun laporan asli yang sudah ada, riwayat sebelum/sesudah, alasan dan admin; checkout lintas tanggal; durasi tidak final ketika checkout kosong; penolakan urutan waktu salah, checkout masa depan, alasan kosong, CSRF salah, revisi kedaluwarsa dan penulis non-admin.
- Arsip: perubahan nama guru/kelas di master dan pergantian jadwal untuk tanggal berikutnya tidak mengubah baris historis yang sudah diuji.
- Halaman: admin dapat POST valid; Akademik membaca detail/audit tanpa kontrol tulis dan POST ditolak; guru/ketua kelas tidak dapat membuka modul. Teks tanggal lengkap dan penandaan koreksi diperiksa melalui render HTML.
- Lint PHP, `git diff --check`, tes filter mandiri `node --test tests/monitoring-kbm-history.test.cjs`, serta regresi seluruh modul `node --test tests/*.test.cjs`.

Tes database dijalankan pada salinan aplikasi khusus pengujian yang diarahkan ke MariaDB sementara port 3307, setelah datadir diverifikasi. Database LMS port utama tidak digunakan untuk penulisan test. Konfigurasi koneksi workspace tidak diganti untuk pengujian. Pemeriksaan UI mencakup render HTML/CSS responsif; belum berupa pemeriksaan visual browser/HP otomatis.

Pekerjaan berhenti pada riwayat dan koreksi teraudit tahap 8, tanpa melanjutkan ekspor atau tahap berikutnya.
