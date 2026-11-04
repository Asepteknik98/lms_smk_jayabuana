# Penyimpanan rapor — langkah 3

Migrasi: `2026_10_28_rapor.sql`. Berkas ini belum otomatis dijalankan oleh aplikasi.
Pemasangan ke database aktif memerlukan persetujuan tersendiri. Buat cadangan
database sebelum pemasangan. Migrasi hanya membuat tiga tabel baru; tidak mengisi
data rapor, mengubah nilai sumber, atau menambah menu.

- `rapor`: satu rapor per siswa, tahun ajaran, semester, dan jenis UTS/UAS.
  Menyimpan salinan identitas, nomor absen opsional, kehadiran manual, catatan,
  nama penanda tangan, tempat/tanggal penerbitan, serta pembuat/pengubah terakhir.
- `rapor_mapel`: salinan nilai akhir, deskripsi, saran, nama mata pelajaran,
  kelompok, guru, urutan, dan waktu pengambilan sumber per pengajaran.
- `rapor_ekstrakurikuler`: kegiatan dan grade A–D manual. Grade NULL berarti
  belum diisi, bukan grade D.

Nilai akhir NULL berarti belum tersedia/lengkap; nol adalah nilai sah.
Nilai akhir harus mengikuti rumus rekap, bukan langsung nilai komponen UTS/UAS.
Deskripsi dan saran mengikuti jenis rapor pada tabel induk.
Jumlah dan rata-rata dihitung dari detail, tidak disimpan terpisah.

Salinan rapor tidak berubah otomatis saat rekap berubah. Mekanisme mengambil
ulang sumber akan dibuat pada tahap halaman pengisian, setelah alurnya disetujui.
Simpan induk dan detail dalam satu transaksi. Validasi guru, kelas, siswa,
semester, tahun ajaran, kelengkapan nilai, dan hak akses wajib dilakukan di server.
Hanya admin (role 1) dan guru (role 2) yang akan diizinkan mengakses fitur.
Rancangan tabel tidak memberikan akses aplikasi dengan sendirinya.

Penghapusan siswa yang memiliki rapor ditolak untuk menjaga dokumen historis.
Penghapusan kelas, pengajaran, atau akun pembuat tidak menghapus salinan rapor.
Penghapusan rapor menghapus detailnya, bukan data sumber rekap.
Keanggotaan kelas historis dan nomor absen belum ditambahkan ke tabel siswa.
Data kelas tersimpan pada rapor; cetak historis harus menggunakan salinan ini.

Ukuran F4, pembagian dua halaman, letak saran, serta kebijakan pembaruan sumber
masih perlu dipastikan pada tahap tampilan. Struktur ini tidak mengunci layout.

## Halaman pengisian — langkah 4

Menu **Rapor Siswa** tersedia untuk admin dan guru. Halaman tidak menjalankan
migrasi otomatis. Jika tabel belum tersedia, halaman menampilkan pemberitahuan
dan tidak membuka formulir penyimpanan.

Pilih periode, kelas dan siswa, lalu tambahkan mapel dan guru. Server mengambil
nilai akhir berbobot serta deskripsi/saran sesuai UTS/UAS. Isian belum lengkap
dapat disimpan sebagai draf. Ekstrakurikuler, kehadiran, catatan, dan nama
penanda tangan diisi manual. Nilai sumber tidak dapat diedit dari formulir ini.

Tombol **Perbarui dari rekap** per baris mengambil nilai terbaru saat disimpan.
Baris lain mempertahankan salinan tersimpan. Penyimpanan memakai transaksi,
CSRF, validasi sumber dan pemeriksaan versi untuk menolak formulir usang.
Menghapus baris baru berlaku setelah Simpan. Fitur cetak disiapkan pada tahap
berikutnya. Pengujian simpan/edit memakai tabel sementara tanpa foreign key,
tidak memasang migrasi atau mengubah catatan sekolah.

## Pratinjau F4 — langkah 5

Pada rapor yang sudah disimpan, buka **Pratinjau F4 (data tersimpan)**.
Halaman `admin/rapor_cetak.php` dan `guru/rapor_cetak.php` membaca salinan rapor,
bukan menghitung ulang nilai sumber. Akses dibatasi di server untuk admin/guru.
Halaman tidak menjalankan migrasi dan tidak menyimpan perubahan apa pun.

Ukuran cetak memakai F4 210 × 330 mm portrait dengan margin 10 mm.
Mata pelajaran dibagi ke dua halaman berdasarkan tinggi hasil render, dengan
ukuran teks 10 sampai minimum 8 pt. Halaman kedua memuat jumlah/rata-rata,
ekstrakurikuler, kehadiran, catatan dan area tanda tangan. Saran ditampilkan
bersama deskripsi dengan label tersendiri. Tanda tangan berupa area kosong
untuk ditandatangani; tidak ada gambar tanda tangan yang dibuat otomatis.
Kop menggunakan logo dan motif sekolah yang sudah tersedia. Logo mitra pada
referensi Excel belum disertakan karena asetnya belum tersedia di proyek.

Jika teks tetap melampaui dua halaman pada ukuran minimum, halaman memberi
peringatan dan menonaktifkan cetak. Tidak ada pemotongan atau penghilangan isi.
Nilai yang belum lengkap tidak dianggap nol; jumlah/rata-rata ditandai belum
lengkap jika ada nilai kosong. Nilai/deskripsi/saran kosong diberi pemberitahuan.

Pada dialog cetak pilih F4/custom 210 × 330 mm, skala 100%, dan matikan
header/footer browser. Jangan memilih Legal (ukurannya berbeda).
Fitur Simpan PDF menggunakan dialog cetak browser. Unduhan PDF server belum
disediakan. Cetak satu kelas dijelaskan pada langkah 6 di bawah.

Verifikasi tahap 5: sintaks PHP/JavaScript; render fixture 9 mapel dan 23 kegiatan
di Chrome headless menghasilkan PDF 2 halaman dengan MediaBox sekitar
595 × 935 pt (F4). Skenario narasi berlebihan menonaktifkan tombol cetak.
Fixture tidak menggunakan data siswa nyata. Hasil printer fisik belum diuji.

## Dua pilihan cetak — langkah 6

- **Cetak per siswa / Pratinjau F4** tersedia setelah membuka rapor tersimpan.
- **Cetak satu kelas** tersedia setelah memilih kelas/periode yang mempunyai
  rapor tersimpan. Pratinjau menampilkan jumlah siswa dan total halaman.

Cetak kelas memakai filter kelas, tahun ajaran, semester, dan jenis UTS/UAS.
Data dibaca dalam satu transaksi; proses tidak menyimpan atau memperbarui nilai.
Urutan berdasarkan nomor absen, lalu nama dan ID rapor. Nomor absen kosong
diletakkan paling akhir. Nomor kosong/ganda diberitahukan sebelum cetak.

Semua rapor tersimpan yang cocok dicetak, termasuk rapor historis siswa yang
sudah pindah kelas. Daftar siswa yang belum mempunyai rapor dibandingkan dengan
anggota kelas saat ini dan ditampilkan secara jelas; sistem tidak membuat rapor
kosong otomatis untuk mereka. Nilai/deskripsi/saran belum lengkap ditampilkan
sebagai peringatan beserta nama siswa, tanpa menghilangkan rapornya.

Masing-masing siswa tetap mendapat dua halaman F4 dan dimulai pada halaman baru.
Jika satu rapor melampaui kapasitas dua halaman, seluruh tombol cetak
dinonaktifkan dan nama siswa ditampilkan, sehingga tidak ada siswa dilewati
diam-diam. Tidak ada migrasi otomatis atau perubahan hak akses pada tahap ini.

Validasi: `node --test tests/rapor-print-batch.test.cjs tests/rekap-akademik.test.cjs`.
Uji memakai tabel sementara untuk memeriksa filter periode/kelas, urutan absen,
nilai nol vs belum lengkap, siswa tanpa rapor, dan kelas kosong. Fixture browser
tiga siswa menghasilkan PDF enam halaman F4. Skenario satu siswa dengan narasi
berlebihan menonaktifkan cetak kelas dan menyebut nama siswa tersebut.

## Hasil pengujian — langkah 7

Tanggal pemeriksaan: 28 Oktober 2026.

Perintah uji:

```powershell
node --test tests/rapor-flow.test.cjs tests/rapor-print-batch.test.cjs tests/rekap-akademik.test.cjs tests/nilai-manual-komponen.test.cjs tests/nilai-manual-target.test.cjs
```

Hasil: 26 pengujian lulus. Cakupan:

- Nilai akhir berbobot, pemisahan capaian UTS/UAS, nilai nol, nilai kosong,
  dan bobot yang belum lengkap.
- Simpan/edit, isian manual, salinan nilai, pembaruan eksplisit dari rekap,
  penolakan nilai sumber palsu, sumber kelas lain, dan pengajaran ganda.
- Validasi grade, kehadiran, tanggal, formulir belum siap, rapor periode ganda,
  transaksi rollback, formulir usang, penghapusan detail, dan kelas historis.
- Akses empat peran ke empat halaman rapor; siswa/Akademik ditolak. Admin dan
  guru memakai halaman pada folder perannya masing-masing.
- Penolakan akses tanpa login, akun nonaktif, dan token CSRF tidak valid.
- Filter dan urutan cetak kelas, rapor belum lengkap, siswa tanpa rapor,
  serta regresi rekap dan input nilai manual yang sudah ada.

Seluruh berkas PHP rapor dan sidebar lolos pemeriksaan sintaks. Kedua berkas
JavaScript rapor lolos pemeriksaan sintaks. Pemeriksaan whitespace Git bersih.
Uji PDF Chrome headless dengan data contoh: 1 siswa = 2 halaman, 3 siswa = 6
halaman, 30 siswa = 60 halaman; ukuran halaman sekitar 595 × 935 pt (F4).
Skenario narasi berlebihan menonaktifkan cetak dan menyebut siswa terkait.

Pengujian database menggunakan tabel sementara per koneksi. Foreign key pada
salinan tabel uji dilepas karena tabel sementara tidak mendukungnya. Pemeriksaan
baca-saja terakhir menemukan ketiga tabel rapor sudah tersedia pada database
aktif, dengan foreign key dan CHECK nilai sesuai migrasi. Pemasangan tersebut
tidak dilakukan oleh proses pengujian ini. Efek penghapusan foreign key tidak
diuji pada data sekolah.
Pengujian akses/formulir HTTP masih berupa simulasi request melalui PHP CLI;
alur klik dan pengiriman formulir dengan data sekolah belum diuji melalui login
browser. Satu rapor aktif yang sudah tersimpan berhasil dirender baca-saja di
Chrome dan dinyatakan siap dalam dua halaman F4.

Pemeriksaan bersama pengguna yang masih diperlukan: login admin/guru, mencoba
menu dan simpan/edit melalui browser, serta menilai hasil cetak fisik pada kertas
F4. Tidak perlu menjalankan ulang migrasi pada database aktif yang tabelnya
sudah tersedia. Tidak ada data sekolah yang diubah oleh pengujian ini.
Logo mitra dan gambar tanda tangan belum disediakan.

## Revisi tampilan cetak mengikuti referensi dua lembar

Pembagian halaman kini tetap: seluruh nilai beserta jumlah/rata-rata pada
halaman pertama; ekstrakurikuler, absensi, catatan, dan tanda tangan pada halaman
kedua. Kop lengkap dan identitas siswa diulang di kedua halaman. Aturan ini
menggantikan pembagian mata pelajaran otomatis ke halaman kedua sebelumnya.
Tampilan memakai bingkai, huruf serif yang lebih ringkas, serta tabel rapat.
Ukuran teks menyesuaikan 8,5 sampai minimum 7 pt; cetak ditolak jika tetap tidak
muat, tanpa memindahkan nilai ke halaman kedua atau memotong isi.

Rapor tanpa detail mapel menampilkan petunjuk untuk melengkapi dan menyimpan
nilai dari halaman pengisian. Halaman cetak tidak mengisi nilai atau mengubah
database. Barcode, logo mitra, dan gambar tanda tangan belum ditambahkan karena
aset/sumbernya belum disediakan. Jangan menganggap placeholder sebagai nilai.

Uji layout: `node --test tests/rapor-print-layout.test.cjs`.
Fixture sembilan mapel menghasilkan dua halaman F4 pada Chrome, dengan semua
mapel dan jumlah/rata-rata di lembar pertama serta 23 kegiatan pada lembar kedua.

### Template kosong sesuai referensi

Halaman cetak menampilkan sembilan baris mapel bawaan dari referensi meskipun
belum ada detail nilai tersimpan. Baris ini hanya untuk tampilan, tidak memilih
guru atau menyimpan nilai. Data tersimpan dicocokkan berdasarkan nama mapel;
mapel tambahan tetap dipertahankan. Nilai/deskripsi/saran kosong dicetak kosong,
termasuk jumlah/rata-rata jika template belum lengkap. Nilai nol tetap dicetak 0.
Grid dan tinggi minimum baris menjaga bentuk template ketika belum diisi.
Aturan ini menggantikan pesan kosong pada tabel cetak sebelumnya; pemberitahuan
kelengkapan tetap tersedia di luar kertas pada pratinjau.

Logo mitra, barcode, dan gambar tanda tangan asli belum tersedia, sehingga
kemiripan visual 100% dengan gambar belum dapat dinyatakan tercapai. Tidak ada
logo, barcode identitas, atau tanda tangan yang dipalsukan untuk mengisi ruang.

## Pengaturan judul melalui web

Admin dan guru membuka **Rapor Siswa → Atur judul rapor**. Pilih kelas, tahun
ajaran, semester, dan UTS/UAS, lalu atur judul laporan, judul penilaian, dan
konsentrasi keahlian. Pratinjau mengikuti teks isian. Kosongkan isian dan simpan
untuk kembali ke teks bawaan. Pengaturan berlaku pada cetak per siswa maupun
kelas, dan diulang di kedua halaman, termasuk untuk rapor tersimpan sebelumnya.

Penyimpanan memakai tabel `rapor_pengaturan_judul` dari migrasi
`2026_10_28_rapor_judul.sql`. Tabel ini ditambahkan tanpa mengubah tabel/nilai
rapor atau jurusan kelas. Pengaturan dibatasi admin/guru, dengan CSRF dan validasi
teks di server. Teks ditampilkan dengan HTML escaping. Cetak tetap memakai judul
bawaan jika tabel pengaturan belum terpasang pada instalasi lain.

Uji: `node --test tests/rapor-judul.test.cjs tests/rapor-flow.test.cjs tests/rapor-print-layout.test.cjs tests/rapor-print-batch.test.cjs`.
Hasil: 31 pengujian lulus, termasuk isolasi kelas/periode, kembali ke bawaan,
pengulangan judul di dua halaman, pembatasan akses dan keamanan teks cetak.

## Perbaikan pemeriksaan cetak untuk jumlah mapel lebih banyak

Tinggi minimum baris kini menyesuaikan dari 19 sampai 14 mm saat diperlukan,
dengan ukuran teks tetap diprioritaskan pada 8,5 pt. Ruang nama mapel/guru ikut
menyesuaikan. Tidak ada mapel yang dihapus atau dipindahkan ke halaman kedua.
Saat beforeprint, kelas print-measuring membuat rapor tetap terlihat untuk
diukur, lalu dilepas setelah validasi. Elemen tersembunyi tidak dianggap muat.

Verifikasi: rapor dengan 12 baris mapel berhasil menghasilkan dua halaman F4
di Chrome. Uji regresi script mencakup beforeprint dan penolakan isi yang memang
melebihi kapasitas. Jalankan node --test tests/rapor-print-script.test.cjs.

## Pilihan mapel dan guru oleh wali kelas

Cetak sekarang menggunakan hanya detail mata pelajaran yang dipilih dan disimpan
pada formulir rapor, sesuai urutan penambahan. Template tidak menambahkan nama
mapel bawaan, mengganti nama mapel, atau mengurutkan ulang pilihan tersimpan.
Nama guru, nilai akhir, deskripsi, dan saran mengikuti sumber pengajaran yang
sudah dipilih. Jika belum ada pilihan, cetak hanya menyediakan sembilan baris
kosong tanpa nama mapel atau guru untuk mempertahankan desain dua halaman.
Aturan ini menggantikan daftar sembilan nama mapel bawaan pada revisi sebelumnya.
Data rapor lama tidak ditulis ulang dan nilai sumber tidak berubah.

Verifikasi: 33 pengujian rapor lulus, termasuk cetak hanya dua mapel yang dipilih,
urutan dan nama guru tersimpan, grid kosong, sumber nilai, simpan/edit, dan akses.


## Administrasi rapor ? 3 November 2026

`admin/rapor.php` membuka monitoring semua kelas, filter periode/arsip,
penugasan wali kelas dan data penerbitan khusus rapor, pemeriksaan kelengkapan,
serta kunci/buka kembali per kelas dan periode. Tautan rapor individual lama tetap
berfungsi. Guru tetap memakai halaman pengisian bertahap yang sama.

Tabel tambahan `rapor_administrasi` disediakan oleh
`2026_11_03_rapor_administrasi.sql`. Halaman web tidak memasang migrasi otomatis.
Tanpa tabel ini monitoring tetap dapat dipakai. Penugasan wali kelas hanya
mencatat tanggung jawab rapor; tidak mengubah hak akses modul sekolah lainnya.

Data penanda tangan/penerbitan menjadi isian awal rapor baru. Admin dapat memilih
secara eksplisit untuk menerapkannya juga ke rapor tersimpan dalam kelas/periode
yang sama. Nilai sumber, salinan nilai, catatan dan ekstrakurikuler dipertahankan.

Kunci hanya dapat dilakukan jika semua rapor kelas lengkap dan setiap siswa
kelas sudah memiliki rapor pada kelas/periode itu. Setelah dikunci, penyimpanan
rapor individual, massal, nilai eskul dan judul cetak ditolak di server. Pratinjau
dan cetak tetap tersedia. Admin dapat membuka kunci untuk koreksi. Status lengkap
bukan persetujuan kepala sekolah; tidak ada persetujuan yang dibuat otomatis.


## Pencarian, pemeriksaan kelengkapan, dan riwayat perubahan

Monitoring admin dan ringkasan guru menyediakan pencarian dan tombol **Lihat
yang belum lengkap**. Pencarian pada tabel kehadiran hanya mengatur tampilan;
baris yang disembunyikan tetap disertakan saat menyimpan catatan satu kelas.
Tabel eskul memiliki pencarian siswa/kegiatan dan tombol **Tampilkan semua**.
Grade yang sudah diubah tetap dikirim saat disimpan, termasuk pada baris atau
kolom yang sedang tersembunyi oleh pencarian. Mencari tidak menandai rapor
sebagai memiliki perubahan yang belum disimpan.

`2026_11_03_rapor_riwayat.sql` menambahkan satu tabel khusus `rapor_riwayat`.
Riwayat mencakup pembuatan/edit rapor, kehadiran/catatan kelas, nilai eskul,
pengaturan penerbitan, judul cetak, serta kunci/buka kembali. Identitas pengguna
diambil dari pengguna yang melakukan penyimpanan. Aktivitas dan perubahan data
disimpan dalam transaksi yang sama; penyimpanan gagal tidak meninggalkan
aktivitas, dan penyimpanan tanpa perubahan isi tidak menambah aktivitas.

Riwayat pada halaman rapor menampilkan hingga 100 aktivitas terbaru sesuai
kelas, periode, dan siswa jika rapor individual sedang dibuka. Tidak ada
aktivitas lama yang direka atau dibuat ulang. Pencatatan dimulai saat tabel
riwayat dan fitur ini diaktifkan. Halaman web tidak memasang migrasi otomatis.
Pengujian database menggunakan tabel sementara, termasuk tabel riwayat, agar
aktivitas pengujian tidak masuk ke catatan sekolah.


## Laporan peringkat siswa

`admin/rapor_peringkat.php` dan `guru/rapor_peringkat.php` menyediakan laporan
**Peringkat 1?10** atau **Seluruh siswa**, pencarian siswa, serta cetak laporan.
Tautan tersedia pada monitoring admin, pengaturan kelas, tahap akhir pengisian
guru, dan informasi peringkat pada rapor individual yang sudah disimpan.

Peringkat dihitung hanya dari salinan nilai akhir di `rapor_mapel`, dipisahkan
menurut kelas, tahun ajaran, semester, dan jenis UTS/UAS. Rumus nilai sumber
serta data tersimpan tidak diubah. Tidak diperlukan migrasi database baru.
Kehadiran, eskul, deskripsi, dan catatan tidak menambah poin peringkat.

- Jumlah nilai dibagi jumlah mapel, lalu rata-rata dibulatkan dua angka desimal.
- Rata-rata sama mendapat peringkat sama dengan urutan 1, 2, 2, 4.
- Semua siswa seri pada peringkat 10 ditampilkan, meski jumlah nama melebihi 10.
- Nilai nol sah. Nilai kosong/tidak valid tidak dianggap nol dan tidak masuk
  peringkat. Siswa tanpa rapor tetap tercantum pada pilihan seluruh siswa.
- Susunan mapel antarrapor harus sama. Mapel berbeda atau ganda menahan seluruh
  penentuan peringkat kelas agar perbandingan tidak timpang.
- Jika masih ada rapor/nilai yang belum lengkap, peringkat siswa lainnya
  ditandai sementara. Peringkat dihitung kembali sesuai data yang tersimpan.

Peserta mencakup rapor tersimpan pada kelas/periode tersebut, termasuk salinan
historis siswa yang telah pindah, ditambah anggota kelas saat ini yang belum
memiliki rapor pada kelas itu. Identitas kelas historis tetap mengikuti rapor.
Rapor dari kelas yang sudah dihapus tidak diberi peringkat berdasarkan tebakan.

Hasil cetak rapor siswa mencantumkan peringkat kelas pada halaman pertama.
Cetak individual tetap membandingkan dengan seluruh kelas, bukan hanya siswa
yang dibuka. Nilai dan peringkat dibaca dalam satu transaksi yang konsisten.
Laporan peringkat kelas dicetak terpisah dari dua halaman F4 rapor siswa.
