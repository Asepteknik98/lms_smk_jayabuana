# Ulangan Harian

Fitur memakai ujian, soal_ujian, sesi_ujian, jawaban_siswa, dan nilai_ujian
yang sudah ada. Migrasi `2026_10_22_ujian_harian.sql` menambah tiga tabel:
pengaturan/nomor UH, penerima siswa tertentu, dan metadata berkas jawaban.
Migrasi boleh dijalankan ulang. Ujian lama mendapat nomor berdasarkan urutan
pembuatan dalam pengajaran masing-masing; data soal, sesi dan nilai lama
tidak diubah. Migrasi ini sudah diterapkan pada database lokal workspace.
Untuk hosting lain, terapkan SQL tersebut sebelum memakai fitur baru.

Guru membuat UH dengan jenis **Ulangan Harian**, memilih kelas/mapel,
jadwal, seluruh kelas atau siswa tertentu, dan metode CBT atau unggah berkas.
UH langsung terlihat oleh penerima; pengerjaan baru tersedia saat jadwal
dimulai. Siswa di luar kelas atau di luar daftar penerima tidak dapat
membuka maupun mengirim jawaban. Penerima dan metode dikunci setelah ada
peserta. Pengajaran sudah memisahkan kelas, mapel, semester dan tahun ajaran.

CBT tetap memakai durasi, PG dan esai. PG dinilai otomatis sesuai bobot;
esai berisi jawaban harus diperiksa guru. Nilai gabungan ditetapkan setelah
semua esai yang dijawab dinilai. Esai kosong saat waktu habis bernilai nol.
Nilai yang menunggu pemeriksaan tidak dihitung sebagai nilai final.

Metode berkas mengikuti waktu mulai dan tenggat, tanpa timer CBT.
Guru menulis soal/instruksi pada formulir. Siswa mengirim satu berkas
PDF, JPG, PNG atau DOCX maksimal 10 MB, satu kali. Guru membuka
**Hasil & Jawaban**, memilih detail siswa, mengunduh jawaban dan memberi
nilai serta catatan. Folder `storage/ujian` harus bisa ditulis PHP dan
akses langsungnya diblokir `.htaccess`. Pada server selain Apache,
konfigurasikan larangan akses langsung yang setara. Endpoint unduh
memeriksa kepemilikan guru/siswa dan mengirim berkas sebagai lampiran.

Untuk beberapa kelas, selesaikan soal di kelas asal lalu gunakan
**Salin ulangan beserta soal ke kelas lain** pada Hasil & Jawaban.
Tujuan harus mapel, semester dan tahun ajaran yang sama, milik guru tersebut.
Salinan langsung ditujukan ke seluruh siswa kelas tujuan. Soal, penerima,
nomor dan nilai salinan selanjutnya dikelola per kelas; revisi soal asal
tidak mengubah salinan yang sudah dibuat.

Bagian **Rekap Ulangan Harian** di halaman guru menampilkan UH 1, UH 2,
dan seterusnya, status pengerjaan dan rata-rata. Tautan Ujian Harian pada
Rekap Nilai menuju bagian ini. Nilai final dari sesi yang selesai langsung
masuk komponen otomatis Ulangan Harian. Nilai manual lama tetap mengikuti
jadwal berakhir. Tidak mengerjakan hingga tenggat bernilai nol; bukan
penerima dan jawaban selesai yang menunggu penilaian tidak dirata-ratakan.
Nilai pengganti manual pada komponen Rekap Nilai tetap berlaku sampai guru
memilih Kembali ke Otomatis. Bobot komponen tetap mengikuti pengaturan guru.

Pengujian `node --test tests/ujian-harian.test.cjs` membuat database uji
bernama acak, menyalin struktur kosong dan menghapusnya setelah selesai.
Tidak memakai atau mengubah data sekolah. Pengujian mencakup HTTP nyata:
CSRF/akses penerima, CBT, nilai esai berbobot, unggah berkas, penolakan
format/akses asing, salin kelas dan nilai final pada halaman Rekap Nilai.

## Impor nilai dan remedial

Pada bagian Rekap Ulangan Harian, pilih **Impor nilai** atau
**Remedial / Undo**, kemudian pilih UH asal. Impor menerima `.xlsx` dan
`.xls` maksimal 5 MB, memakai template untuk tepat satu UH dan hanya siswa
penerima. Unggah untuk pratinjau, lalu konfirmasikan penyimpanan. Nilai
0–100 diterima, sel kosong dilewati, dan nilai tersimpan tidak ditimpa.
Sesi aktif atau jawaban menunggu pemeriksaan dilewati. Simpan mengecek
ulang hak akses dan nilai; perubahan setelah pratinjau membatalkan seluruh
impor agar tidak terjadi penyimpanan sebagian. Pratinjau berlaku 15 menit.

Remedial tersedia setelah jadwal UH asal berakhir untuk siswa bernilai
di bawah 75. Nilai awal yang belum diperiksa harus diselesaikan terlebih
dahulu; tidak mengerjakan setelah tenggat bernilai awal 0. Guru dapat
memasukkan hasil remedial atau menerbitkan ujian remedial CBT/berkas kepada
siswa terpilih. CBT menyalin soal dari UH asal. Nilai final remedial otomatis
memperbarui UH asal memakai nilai tertinggi dari nilai awal dan percobaan
yang masih berlaku. Ujian remedial tidak menambah nomor/kolom UH atau ikut
dirata-ratakan lagi sebagai ujian tersendiri.

Riwayat menyimpan nilai awal, nilai sebelum, nilai remedial dan catatan.
Undo menghitung ulang nilai tanpa menghapus riwayat. Jika nilai diubah di
luar remedial, pembatalan ditolak agar perubahan lain tidak tertimpa.
Penilaian ulang ujian remedial yang telah dibatalkan tidak mengaktifkan
kembali percobaan tersebut. Ujian dengan hubungan/riwayat remedial tidak
dapat dihapus agar riwayat nilai tetap utuh. Nilai pengganti manual pada
komponen Rekap Nilai tetap mengikuti pilihan guru.

Terapkan `2026_10_22_remedial_ujian.sql` setelah migrasi UH utama; SQL ini
sudah diterapkan di database lokal. Tiga tabel baru menampung hubungan
ujian remedial, riwayat nilai dan pembatalan. Jumlah catatan ujian, soal,
sesi dan nilai lama tidak berubah ketika migrasi diterapkan.

Pengujian UH juga mencakup unggah template lewat HTTP, konfirmasi impor,
penolakan template asing, nilai invalid, pratinjau kedaluwarsa/berubah,
rollback seluruh impor, nilai tertinggi, undo, penolakan peserta asing,
remedial CBT/esai dan berkas, pengecualian remedial dari rata-rata/nomor UH,
dan tampilan nilai impor pada siswa. Tampilan halaman nilai diperiksa pada
lebar 360, 390, 768 dan 1440px menggunakan HTML fixture dan CSS Bootstrap.

## Perbaikan template Excel

Template soal dan nilai sekarang diunduh sebagai XLSX asli, bukan XML dengan
ekstensi XLS. Penulis XLSX lokal tidak membutuhkan ekstensi ZIP atau paket
tambahan di hosting. Impor tetap menerima XLS biner dan SpreadsheetML lama.

Halaman utama menyediakan Buat ulangan. Setelah disimpan, ulangan CBT
langsung membuka menu Soal. Di sana tersedia Impor Excel dan Tambah manual;
template campuran diunduh dari panel Impor Excel. Endpoint template PG dan
esai tetap tersedia untuk kompatibilitas. Sheet pertama **Soal**
berisi header dan 100 baris siap diisi, dengan pilihan tipe/kunci dan
validasi bobot. Petunjuk dan contoh dipisahkan ke sheet tersendiri sehingga
contoh tidak ikut diimpor. Impor mendukung maksimal 500 soal / 5 MB, PG dan
esai, mengabaikan baris kosong, serta melewati soal identik. Semua baris
divalidasi sebelum penyimpanan; pengajaran, metode CBT dan kunci peserta
diperiksa ulang dalam transaksi.

Template nilai tetap memuat identitas UH/pengajaran serta penerima. ID dan
NIS/NISN disimpan sebagai teks. Pembaca menerima sel kosong tambahan yang
dibuat Excel pada baris metadata; nilai tidak lagi ditolak hanya karena
Excel meratakan jumlah kolom seluruh sheet.

Pemeriksaan tambahan: `tests/ujian-excel.test.cjs` dan pengujian HTTP UH
mencakup seluruh unduhan template, XLSX yang dibuka/disimpan ulang melalui
openpyxl, XLS biner, PG/esai, bobot/kunci tidak valid, penolakan pemilik lain,
impor terkunci setelah ada peserta, pencegahan duplikat dan impor nilai
XLSX sampai konfirmasi. Tidak ada perubahan struktur database pada tahap ini.

## Alur guru yang disederhanakan

Buat ulangan ? isi soal ? siswa mengerjakan sesuai jadwal ? periksa hasil.
Pada daftar, Soal menampung template, impor dan editor manual. Hasil membuka
status serta jawaban siswa. Edit jadwal dan Hapus tersedia di Lainnya.
Pilihan penerima khusus dan metode berkas berada pada Opsi tambahan; UH biasa
memakai CBT untuk seluruh kelas. Tidak ada tombol unduh/impor ganda pada
halaman utama. Impor mengembalikan guru ke soal ulangan yang sama, termasuk
pesan validasi atau hasil impor. Setelah ada peserta, editor menjadi baca saja.
Siswa melihat Menunggu soal dari guru jika jadwal sudah dimulai tetapi soal
CBT belum tersedia.

Bobot kosong otomatis 1. Angka bulat Excel seperti 01, 1.00 dan 1,0 diterima,
tanpa mengubah nilai relatifnya. Bobot tetap bilangan bulat 1?100; pecahan
nonbulat dan nilai di luar batas ditolak dengan pesan baris yang jelas.
Template mengisi bobot awal 1 dengan format angka, dan baris yang belum
memiliki pertanyaan diabaikan. Jumlah bobot tidak wajib 100; nilai akhir
selalu dinormalisasi terhadap total bobot oleh perhitungan ujian.

Pemeriksaan browser memakai fixture terisolasi dan aplikasi PHP asli:
pembuatan UH, perpindahan otomatis ke soal, impor XLSX dengan bobot kosong
serta 3,00, penambahan manual, mode baca saja setelah ada peserta, dan lebar
360/390/768/1440px. Pengujian ini tidak mengubah data sekolah.
