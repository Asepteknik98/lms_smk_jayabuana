# Target JP harian dan mingguan

Target disiapkan **admin**, sedangkan guru hanya dapat melihat target dan JP
tercatat miliknya pada halaman Rekap JP Mengajar. Target berlaku per guru,
berupa pola Senin–Jumat yang berulang. Angka mingguan selalu dihitung dari
jumlah lima target harian, sehingga tidak ada dua target yang bertentangan.

## Penerapan

1. Cadangkan database hosting seperti biasa.
2. Setelah migrasi rekap mengajar yang sudah tersedia, terapkan manual
   `database/2026_10_19_target_jp_guru.sql` pada database LMS yang tepat.
   Skrip hanya menambahkan tabel `target_jp_guru`; tidak mengubah atau mengisi
   tabel guru, pengajaran, realisasi, validasi, maupun target bawaan.
3. Unggah `includes/target_jp_guru.php`, `includes/target_jp_guru_page.php`,
   `assets/css/target-jp-guru.css`, dan revisi
   `includes/rekap_mengajar_module.php`.
4. Buka Admin → Monitoring JP Guru → Target JP harian & mingguan.

Tidak ada migrasi yang dijalankan otomatis pada database utama. Tanpa migrasi,
halaman memberi pemberitahuan bahwa target belum aktif; rekap lama dan ekspor
tetap tersedia.

## Cara menggunakan

- Admin membuka **Atur target guru**, memilih guru dan tanggal Senin mulai
  berlaku, lalu mengisi lima target harian. Nilai 0 berarti tanpa target,
  bukan konfigurasi yang hilang. Batas 12 JP/hari mengikuti dua sesi dengan
  masing-masing enam JP pada modul realisasi.
- Contoh: Senin 6, Selasa 4, Rabu 0, Kamis 6, Jumat 4
  menghasilkan target 20 JP/minggu.
- Pilih tanggal mana pun dalam minggu yang akan dilihat. Batas minggu selalu
  Senin–Jumat berdasarkan tanggal kalender WIB, termasuk lintas bulan/tahun.
- Tabel harian menunjukkan **JP tercatat / target**. Kolom mingguan menunjukkan
  target total, realisasi, kekurangan dan kelebihan. JP desimal lama tetap
  ditampilkan dengan ketelitiannya, bukan dibulatkan menjadi JP utuh.
- Admin dapat mengklik **Atur target** pada baris guru untuk menggunakan pola
  yang ditampilkan sebagai dasar pengaturan berikutnya.
- Guru membuka Rekap JP Mengajar untuk melihat perbandingan sendiri.
  Parameter guru lain pada URL tidak mengubah cakupan akun guru.

## Riwayat dan batas lingkup

- Target pertama boleh dimulai pada Senin minggu berjalan atau minggu depan.
  Target yang sudah berlaku pada minggu berjalan tidak boleh diganti angka
  barunya; perubahan dimulai paling cepat Senin berikutnya. Minggu lampau
  tidak dapat dikonfigurasi ulang.
- Perubahan pola untuk minggu mendatang disimpan sebagai versi baru; versi
  lama tidak dihapus. Versi dengan tanggal berlaku paling baru dipilih; bila
  tanggal sama, revisi terakhir dipakai. Log admin merekam identitas, pola,
  total, tanggal berlaku, serta referensi versi sebelumnya.
- Penyimpanan dilindungi role/status admin aktif, CSRF, validasi server,
  transaksi dan kunci baris guru. Pengiriman ganda dengan isi sama tidak
  menggandakan target. Kegagalan audit membatalkan penyimpanan.
- Realisasi memakai `rekap_mengajar` dan `pengajaran` yang sudah ada, bukan
  lama check-in pada Monitoring KBM. Tidak membuat realisasi otomatis atau
  mengubah hitungan/validasi realisasi lama.
- Perbandingan minggu mencakup semua kelas/mapel guru; filter kelas/mapel di
  rekap bulanan tetap berlaku hanya pada rincian lama. Tanggal minggu target
  ditampilkan secara terpisah dari bulan/rentang laporan yang lama.
- Hari libur tidak mengurangi target otomatis. Kekurangan minggu berjalan
  belum menjadi kesimpulan akhir, dan pencapaian target bukan bukti kehadiran.
- Ekspor Excel/PDF lama tetap berisi realisasi dan rekap yang sudah ada;
  fitur ini belum menambahkan kolom target pada ekspor.

## Pemeriksaan

Pengujian dijalankan pada salinan TEMP dengan MariaDB terisolasi port 3307,
memeriksa `@@datadir` sebelum membuat database uji. Database sekolah tidak
direset atau dimigrasi.

- PHP lint ketiga berkas helper/view/modul.
- `tests/target-jp-guru.test.cjs`: migrasi berulang tanpa target bawaan;
  target harian/mingguan; realisasi lintas kelas/mapel; minggu lintas tahun;
  target kosong dan nol; versi masa depan dan riwayat lama; validasi angka,
  tanggal dan guru; penolakan nonadmin/nonaktif; klik bersamaan;
  rollback audit; CSRF; profil guru sendiri dan URL guru lain;
  penyimpanan melalui controller admin; penolakan POST guru; serta
  kelangsungan rekap ketika migrasi target belum tersedia.
- `tests/rekap-period.test.cjs`: 14 pemeriksaan rekap lama, filter, batas
  tanggal, status validasi serta Excel/PDF.

Hasil akhir: ketiga pemeriksaan lint lulus dan gabungan kedua berkas pengujian
di atas lulus **15/15** (28,4 detik). Pengujian target mencakup JP desimal
lama, penjumlahan lintas mapel/kelas, dan minggu yang melintasi bulan.
Belum diuji manual di hosting atau perangkat pengguna.

## Acuan otomatis pada formulir Input Aktivitas Mengajar

Admin yang memilih guru dan tanggal memperoleh acuan target harian/mingguan,
JP tercatat pada tanggal itu dan sisa target. Data dibaca dari endpoint admin
`admin/target_jp_guru_data.php`; bukan dari angka yang dimasukkan perangkat.
Endpoint hanya menerima GET, menolak role lain dan akun nonaktif, serta tidak
menulis realisasi maupun target. Respons tidak disimpan pada cache browser.

Untuk catatan baru, sisa target harian bulat 1–6 JP mengisi rentang mulai JP 1
sampai sebanyak sisa target, sebagai saran yang bisa diubah admin. Admin wajib
memeriksa jam, mapel, kelas dan sesi sesuai kegiatan nyata, lalu menekan Simpan.
Target bukan jadwal terperinci per mapel/kelas, sehingga sistem tidak menebak
pengajaran atau sesi. Target lebih dari 6 JP perlu dicatat per blok/sesi.
Target nol, belum diatur, sudah tercapai atau sisa pecahan tidak mengisi
rentang otomatis. Realisasi tambahan tetap boleh dicatat mengikuti aturan
lama. Pada mode koreksi, rentang catatan lama selalu dipertahankan.

Permintaan lama dibatalkan ketika pilihan berganti. Respons terlambat tidak
menimpa guru/tanggal terbaru. Perubahan rentang manual saat data masih dimuat
tetap dipertahankan. Koneksi gagal tidak menghalangi input manual, dan tidak
menyebabkan realisasi tersimpan otomatis.

Berkas tambahan/perubahan untuk tahap ini:

- `admin/target_jp_guru_data.php`
- `includes/target_jp_guru.php`
- `includes/rekap_mengajar_module.php`
- `assets/js/target-jp-input.js`
- `assets/css/monitoring-jp-admin.css`

Tidak ada migrasi tambahan; menggunakan tabel target dari migrasi sebelumnya.

Pemeriksaan tahap acuan input: lint PHP helper, modul dan endpoint serta
`node --check` JavaScript lulus. Gabungan `target-jp-input.test.cjs`,
`target-jp-guru.test.cjs`, dan `rekap-period.test.cjs` lulus 18/18 pada salinan
TEMP/MariaDB terisolasi. Mencakup pengisian sisa target, mempertahankan rentang
manual/koreksi, respons usang, koneksi gagal, target kosong/nol/lebih dari enam/
pecahan, endpoint baca saja, penolakan role dan akun nonaktif, serta rekap lama.
Belum diverifikasi langsung pada hosting atau perangkat pengguna.

## Tata letak formulir admin

View input admin dipisahkan ke `includes/rekap_mengajar_input.php`. Kelompok
data pengajaran dan sesi/rentang JP memakai label berikon; acuan target berada
di samping pada desktop dan di bawah data pengajaran pada layar kecil. Tombol
simpan berada pada bilah terpisah. ID, nama input, nilai edit, form POST, CSRF,
script pilihan pengajaran dan pengisian saran JP tetap sama. Halaman guru
tidak memakai view input admin ini.

Untuk menerapkan revisi tampilan, unggah view baru tersebut beserta revisi
`includes/rekap_mengajar_module.php`, `includes/target_jp_guru_page.php`, dan
`assets/css/monitoring-jp-admin.css`. Tidak ada migrasi atau endpoint tambahan.

Setelah revisi tampilan, PHP lint tiga view/modul lulus dan 18 pengujian
target/input/rekap lama lulus. Fixture formulir menggunakan view PHP asli dan
CSS di Chrome headless pada lebar 1366, 1024, 390 dan 360px: tidak ada luapan
horizontal, kontrol muat di layar, label terhubung dengan input, acuan di
samping pada desktop dan tinggi dropdown 46px pada viewport HP. Ini simulasi
browser dengan CSS dasar pendukung; belum pemeriksaan pada HP/hosting asli.

Target hanya berlaku Senin-Jumat. Kolom Sabtu/Minggu lama tetap dipertahankan untuk kompatibilitas database, tetapi pengaturan baru menyimpan keduanya sebagai 0. Rekap target mingguan mengecualikan akhir pekan; catatan realisasi lama dan rekap bulanan tidak dihapus atau diubah. Tidak perlu migrasi tambahan.
