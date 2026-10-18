# Revisi pelaporan siswa: pilih JP → pilih guru → check-in → check-out

## Ruang lingkup

### Pengganti reset: tombol aksi di tabel dan Hapus teraudit

Sesuai pilihan pengguna, tombol reset manual dihapus dari tampilan. Tabel **Jadwal dan laporan hari ini** mempunyai kolom **Check-in / Check-out** di kanan Masuk/Selesai dan kolom **Hapus** setelahnya. Satu rentang memakai tombol gabungan (rowspan), sehingga JP 1–3 tidak membuat tiga laporan/tombol independen. Tombol di ringkasan atas menjadi tautan menuju tabel; checkout catatan hari sebelumnya tetap tersedia di peringatan agar tidak menghalangi catatan berikutnya.

**Hapus** membuka isian alasan wajib (maksimal 500 karakter), lalu **Batalkan Catatan** mengarsipkan pilihan guru hari ini pada kelas ketua yang masih aktif. Berlaku sebelum check-in, sesudah check-in, sesudah checkout maupun jika sudah ada koreksi; tidak berlaku pada jadwal mingguan admin, kelas lain atau tanggal lampau. Jadwal ditandai nonaktif dan slot pilihan unik dilepas, bukan dihapus. Snapshot menyimpan `dibatalkan`, `pembatalan_oleh`, `pembatalan_pada`, `pembatalan_alasan`. Bila belum ada pertemuan, snapshot pertemuan dibuat untuk menyimpan bukti pembatalan. Laporan asli, waktu masuk/keluar, pelapor serta koreksi admin tidak diubah.

Pembatalan dikeluarkan dari monitoring aktif dan daftar siswa, tidak menghalangi pemilihan rentang/guru pengganti, dan tidak dapat di-check-in/check-out kembali melalui request lama. Riwayat admin/Akademik dan Excel tetap menyertakan catatan dengan status **Dibatalkan**, identitas pengguna, waktu dan alasan pembatalan; filter Dibatalkan tersedia. Catatan terbuka yang dibatalkan tetap mempunyai checkout asli NULL, tanpa jam selesai palsu. Pembatalan berulang tidak mengganti alasan/pelaku pertama. Semua operasi memakai POST, CSRF, pengecekan ulang izin ketua/kelas/periode/hari dan kunci kelas yang sama dengan pelaporan. Tidak ada migrasi baru. Helper reset lama hanya dipertahankan untuk kompatibilitas internal; menu reset tidak ditampilkan lagi. Catatan revisi reset sebelumnya di bawah merupakan sejarah implementasi.

Hasil pemeriksaan revisi tabel/Hapus: **43 tes lulus, 0 gagal, 0 dilewati**, sekitar 82 detik pada salinan kode/database terisolasi. Mencakup posisi kolom aksi dan hilangnya tombol reset, alasan kosong/CSRF/role/kelas lain ditolak, pembatalan sebelum check-in/saat aktif/setelah selesai, pengulangan idempoten, laporan asli tidak berubah, checkout catatan batal ditolak, indikator aktif dibersihkan tanpa waktu keluar palsu, pilihan pengganti tersedia, serta status/alasan/pelaku pada filter riwayat dan Excel. Lint sembilan file PHP revisi lulus. Database sekolah tidak diubah.

### Reset pilihan sebelum check-in

Tombol **Reset Pilihan JP dan Guru** tersedia untuk pilihan harian ketua kelas yang belum mempunyai check-in/koreksi. Jika baru memilih JP di layar, **Reset Pilihan JP** mengosongkan pilihan layar saja. Reset tersimpan memakai POST + CSRF dan pengecekan ulang ketua aktif/kelas/periode/tanggal di dalam kunci kelas yang sama dengan check-in. Pilihan ditandai nonaktif hari ini, tanggal pilihan unik dilepas agar JP dapat dipilih ulang, serta `reset_oleh`/`reset_pada` dicatat pada snapshot. Tidak menghapus baris jadwal atau pertemuan. Jadwal admin dan tanggal lain tidak dapat direset dari menu ini. Jika check-in atau koreksi sudah tercatat, reset ditolak, termasuk setelah check-out. Pengulangan reset bersifat idempoten. Rencana admin yang sempat digantikan akan kembali terlihat jika belum mempunyai pilihan aktif pengganti; laporan asli tidak diubah. Setelah reset, tombol JP tidak berwarna hijau sampai dipilih kembali. Tidak memerlukan migrasi baru.

Pengujian revisi reset: `tests/monitoring-kbm-siswa.test.cjs` lulus pada database terisolasi, termasuk reset sukses, klik ulang, penolakan role/kelas lain, CSRF salah, pemilihan ulang rentang setelah reset, penolakan setelah check-in/check-out dan riwayat yang hanya memuat laporan pengganti yang sah. Lint lima file PHP revisi serta sintaks tes JavaScript lulus. Database sekolah tidak diubah.

### Aturan terbaru: tanpa pembatas jam pelaporan

Sesuai arahan pengguna berikutnya, pilihan guru/rentang JP dan check-in pada alur ketua kelas tidak lagi dibatasi jam mulai/selesai JP atau jeda istirahat. JP 1–6 tetap merupakan nomor rentang berurutan; jam rencana dari pola kelas hanya referensi. Rentang dapat melewati jeda istirahat. Pemilihan dan pencatatan tetap untuk tanggal server hari ini, dengan akun/kelas/penugasan aktif, CSRF, kunci kelas, larangan tumpang tindih dan satu pertemuan aktif. Tidak dapat mengisi waktu masuk mundur atau mengubah laporan tersimpan. Check-out tetap memakai waktu aktual server dan diperlukan sebelum berpindah guru.

Status pusat mengenali sumber `ketua_kelas` dari snapshot jadwal: pilihan hari ini tanpa check-in merah, dengan check-in hijau, setelah check-out kuning. Dashboard memakai laporan aktif hari ini, atau pilihan terbaru bila tidak ada yang aktif, sehingga check-in di luar jam rencana tetap tampil hijau. Catatan hari sebelumnya yang terbuka hanya peringatan dan tidak membuat hari berikutnya hijau. Filter SQL riwayat dan Excel mengikuti status yang sama. Jadwal admin lama tetap memakai aturan waktu sebelumnya. Rujukan pembatasan jam/istirahat dalam catatan revisi lama di bawah telah digantikan untuk sumber pilihan ketua kelas. Konfigurasi sesi, nomor JP dan izin ketua tetap dipakai; tidak mengubah data atau desain dashboard Waka dan tidak membutuhkan migrasi baru.

Pemeriksaan aturan tanpa pembatas jam: **43 tes lulus, 0 gagal, 0 dilewati**, termasuk pemilihan/check-in pukul 23.00 setelah seluruh jam rencana selesai, check-in pukul 05.01 sebelum jam rencana dimulai, rentang melintasi istirahat, guru dapat diklik setelah jam rencana, status hijau pada feed Waka dan filter riwayat, check-out menjadi kuning serta catatan terbuka hari sebelumnya tidak membuat hari berikutnya hijau. Lima file PHP revisi lolos lint. Pengujian menggunakan database terisolasi; database sekolah tidak diubah.

### Pembaruan rentang JP

Ketua dapat memilih JP awal lalu JP akhir dengan mengklik tombol (misalnya 1 lalu 2, atau 3 lalu 5). Satu rentang JP berurutan memakai satu guru dan satu pasangan check-in/check-out. Semua tombol dalam rentang berwarna hijau sebagai penanda pilihan. Form mengirim `jp` dan `jp_selesai`, sedangkan waktu rencana dihitung dari slot server. Nomor JP harus 1–6, akhir tidak sebelum awal, tidak bertumpang tindih dengan pilihan/laporan lain dan tidak melintasi istirahat. Jika ada istirahat di tengah, buat blok terpisah. Rentang terkunci setelah pertemuan tercatat; form check-in yang memakai rentang basi ditolak. Riwayat, dashboard Waka dan Excel menggunakan JP awal/akhir yang sama, tanpa membuat laporan per JP. Tidak perlu migrasi baru selain migrasi pilihan guru yang telah disiapkan sebelumnya. Keterangan satu JP di catatan revisi awal di bawah digantikan oleh dukungan rentang ini.

Pemeriksaan revisi rentang: suite lengkap 43 tes lulus. Setelah penyempurnaan klik awal/akhir, pengujian integrasi siswa diulang dan lulus, termasuk JP 1–2 dan JP 3–5, satu pertemuan per rentang, dua tombol hijau untuk dua JP, form rentang basi, JP terbalik/di luar batas, tumpang tindih, istirahat, status Waka saat berada di JP tengah, serta rentang pada riwayat. Lint empat file PHP dan sintaks tes JavaScript lulus. Semua pengujian database tetap memakai MariaDB terisolasi; tidak ada migrasi baru atau perubahan data sekolah untuk revisi rentang ini.

Ketua kelas aktif menggunakan akun siswa yang sama dan kelasnya sendiri. Menu JP 1–6 dapat diklik; hijau pada tombol JP hanya tanda pilihan. Di bawahnya muncul semua guru sekolah dengan akun guru aktif, dilengkapi pencarian nama. Ketua memilih guru lalu menekan **Check-in Guru** saat guru masuk. Sebelum check-in status laporan JP berjalan merah; setelah check-in hijau. **Check-out Guru** mencatat akhir mengajar dengan waktu server dan status selesai kuning. Setelah catatan aktif ditutup, ketua dapat berpindah JP untuk guru berikutnya. Jam dan laporan enam JP ditampilkan di bagian bawah.

Tampilan dashboard Waka Akademik tidak diubah. Pelaporan memakai jadwal bertanggal, pertemuan, laporan, status pusat, audit, riwayat dan ekspor yang sudah tersedia. Admin dan fasilitas LMS lain tetap ada; penghapusan tab Periode/Jadwal admin tidak dikerjakan dalam revisi siswa ini.

## Penerapan

1. Cadangkan database dan uji pada staging. Pastikan empat migrasi Monitoring KBM terdahulu sudah diterapkan.
2. Jalankan **secara manual** `database/2026_10_16_monitoring_kbm_pilihan_guru.sql` pada database LMS yang tepat. Aplikasi tidak menjalankan migrasi saat membuka halaman, memilih guru atau login. Migrasi produksi tidak diterapkan otomatis dalam pengerjaan revisi ini.
3. Pasang file PHP revisi bersama migrasi. Sebelum skema tambahan siap, halaman siswa tetap menggunakan alur lama dan memberi informasi bahwa pelaporan pilihan guru belum diaktifkan. Jika penerapan DDL terhenti, periksa struktur; DDL MariaDB tidak sepenuhnya transactional.
4. Pastikan admin sudah mengatur sesi kelas, JP lengkap/istirahat untuk hari yang digunakan, serta ketua kelas aktif pada periode berjalan. Tidak perlu membuat jadwal guru admin untuk menggunakan pilihan guru. Periode dan pembagian waktu masih diperlukan agar server mengetahui kelas, sesi dan waktu JP yang sah.

Migrasi membuat `pengajaran_id`/`mapel_id` pada jadwal dan `mapel_id` pertemuan boleh NULL, menambah `tanggal_pilihan`, `dipilih_siswa_id`, dan indeks unik `(kelas_sesi_id,tanggal_pilihan,jp_mulai)`. Kolom baru NULL pada jadwal admin lama. Tidak menghapus atau mengganti data lama, tidak membuat role, akun, mata pelajaran atau pengajaran fiktif.

## Aturan pencatatan

- Pemilihan guru adalah POST dengan CSRF, izin ketua dicek ulang dalam transaksi dan kunci kelas. Kelas, tanggal, tahun/semester, sesi, JP, waktu dan identitas pelapor berasal dari server/database, bukan isian siswa.
- Pilihan harian disimpan sebagai satu baris `kbm_jadwal` yang masa berlakunya hanya tanggal server hari ini dan satu JP. Snapshot `sumber=ketua_kelas` membedakannya dari jadwal mingguan admin. Semua pembacaan tetap memakai sumber jadwal KBM yang sama.
- Mata pelajaran diambil jika guru mempunyai tepat satu pengajaran di kelas dan periode tersebut. Jika tidak, referensi mapel/pengajaran NULL dan keterangan **Guru pilihan ketua kelas (mapel tidak ditentukan)**; tidak menebak mata pelajaran atau menambahkan pengajaran palsu.
- Guru boleh diganti sebelum pertemuan terbentuk. Setelah ada pertemuan/laporan/koreksi, pilihannya tidak boleh ditimpa. Form check-in menyertakan identitas guru yang ditampilkan; server menolak jika pilihan diubah pada tab lain sehingga tombol lama tidak mencatat guru yang berbeda diam-diam.
- Jadwal pilihan ketua menggantikan rencana admin yang bertumpang tindih pada tanggal/JP tersebut untuk pembacaan dan riwayat tanpa laporan. Laporan atau koreksi admin yang sudah tercatat tidak disembunyikan atau ditimpa; pemilihan guru ditolak jika JP sudah mempunyai laporan/koreksi.
- Check-in hanya selama jam JP, mulai inklusif–selesai eksklusif, bukan saat istirahat/hari tanpa KBM, bukan tanggal lampau atau masa depan. Guru dapat dipilih untuk JP yang belum dimulai hari ini, tetapi check-in tetap menunggu waktunya. Pemilihan guru untuk JP yang sudah berakhir ditolak.
- Satu kelas hanya satu pertemuan aktif. Ketua harus check-out dahulu sebelum memilih guru JP lain. Klik ulang dan request bersamaan memakai kunci kelas/keunikan yang sama dan tidak mengganti waktu laporan. Penugasan dicabut atau akun/kelas tidak sesuai langsung ditolak pada request berikutnya.
- Check-out terlupa tetap belum check-out dan menggunakan waktu aktual server ketika akhirnya ditutup; tidak dibuatkan waktu selesai palsu. Koreksi admin tetap melalui fasilitas audit yang ada.
- Pemilihan guru tidak otomatis check-in. Dashboard Waka mengikuti aturan waktu/status pusat setelah pilihan tersimpan: merah selama JP berjalan tanpa check-in, hijau sesudah check-in, kuning sesudah check-out/di luar waktu JP. Sebelum guru dipilih, tidak dibuat guru atau laporan palsu pada dashboard.
- Daftar bagian bawah adalah gabungan jam JP sesi, pilihan guru harian, jadwal lama yang masih berlaku dan laporan aktual; bukan jadwal guru tetap untuk minggu berikutnya. Satu JP per pilihan; pindah guru memerlukan check-out dan JP berikutnya.

## File

- Baru: `includes/monitoring_kbm_pilihan.php`, `includes/monitoring_kbm_pilihan_page.php`, migrasi dan dokumen ini.
- Diubah: `siswa/monitoring_kbm.php` untuk dispatch tampilan, pesan aktivasi dan redirect JP; `includes/monitoring_kbm_siswa.php` untuk pemilihan guru dan pemeriksaan guru form; `includes/monitoring_kbm_data.php` untuk mapel opsional dan penggantian rencana tanpa laporan; `includes/monitoring_kbm_history.php` untuk riwayat tanpa duplikasi rencana tumpang tindih; `tests/monitoring-kbm-siswa.test.cjs`.

## Pemeriksaan

Pengujian memakai salinan kode dengan MariaDB terisolasi port 3307 dan verifikasi `@@datadir`; tidak memakai database sekolah. Tes mencakup migrasi ulang, guru lintas kelas tanpa pengajaran, pemilihan tanpa check-in, penggantian sebelum pencatatan, guru pada form basi, status merah–hijau–kuning pada feed Waka, enam request check-in bersamaan menghasilkan satu laporan, pencabutan ketua, pindah JP, CSRF dan HTTP POST siswa, larangan akses siswa biasa/Akademik, serta kesesuaian riwayat dan ekspor setelah rencana admin digantikan. Hasil aktual `node --test tests/*.test.cjs`: **43 lulus, 0 gagal, 0 dilewati**, sekitar 83 detik. Lint enam file PHP revisi dan kompilasi skrip pencarian guru lulus. `git diff --check` tidak melaporkan kesalahan whitespace; hanya peringatan normalisasi line ending pada file yang sudah berubah sebelumnya. Server uji dihentikan setelah pemeriksaan. Migrasi tambahan tidak diterapkan ke database sekolah.

Belum berupa uji penerimaan HP fisik atau jaringan sekolah. Sesudah migrasi manual, uji di staging dengan ketua, kelas dan jam JP yang sesuai, lalu periksa dashboard Waka pada pembaruan sekitar 12 detik. Informasi berasal dari konfirmasi ketua kelas, bukan deteksi fisik guru.
# Pembaruan 17 Oktober: JP tanpa pengaturan jam

Alur terbaru menghapus syarat waktu JP untuk pilihan guru ketua kelas. Lihat [panduan migrasi tanpa jam](README_monitoring_kbm_tanpa_jam.md). Bagian lama yang menjelaskan kebutuhan pola waktu hanya berlaku sebelum migrasi tersebut diterapkan.

