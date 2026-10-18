# Fondasi Monitoring KBM — tahap 3

Pembaruan tahap 4: pengaturan admin sudah tersedia. Deploy helper terkini bersama migrasi `2026_10_16_monitoring_kbm_admin.sql`; ikuti `README_monitoring_kbm_admin.md` untuk menu dan aturan versi jadwal. Keterangan “belum ada halaman konfigurasi” di bawah adalah kondisi akhir tahap 3, bukan kondisi setelah tahap 4.

Belum ada halaman konfigurasi, endpoint check-in/out, job otomatis, atau dashboard monitoring aktif. Dashboard Akademik tahap 2 tetap menampilkan “Monitoring KBM belum dikonfigurasi”. Role Akademik yang sudah ada tidak diubah atau dibuat ulang.

> Acuan operasional akhir: [Laporan tahap 10](README_monitoring_kbm_final.md). Dokumen fondasi ini menyimpan kondisi tahap 3; pernyataan tentang halaman yang belum tersedia dan rencana status bukan kondisi akhir. Saat ini check-in tanpa check-out tetap hijau pada pertemuannya dengan peringatan setelah jadwal berakhir; warna utama kelas mengikuti jadwal berjalan dan catatan lama tampil terpisah. Tidak ada penutupan otomatis atau jam selesai buatan. Koreksi admin dapat mencatat check-out aktual pada tanggal berikutnya setelah diverifikasi, tanpa mengganti laporan asli.

## Penerapan manual

1. Cadangkan database dan uji pada salinan staging terlebih dahulu. Pastikan migrasi akun Akademik tahap 2 telah diterapkan.
2. Periksa tabel `users`, `kelas`, `guru`, `siswa`, `mapel`, `pengajaran` menggunakan InnoDB dengan PK `INT UNSIGNED`, sesuai skema proyek. MariaDB proyek 10.4 mendukung FK, JSON, dan CHECK yang digunakan.
3. Jalankan `database/2026_10_16_monitoring_kbm_data.sql` melalui tab SQL phpMyAdmin pada database LMS yang benar. Tidak dijalankan otomatis oleh PHP atau saat login. DDL MariaDB melakukan implicit commit; bila gagal, periksa penyebab dan struktur sebelum melanjutkan.
4. Migrasi dapat dijalankan kembali pada struktur yang sama (`IF NOT EXISTS`, referensi sesi idempoten). Ini bukan alat untuk memperbaiki skema yang sudah dimodifikasi manual. Tidak ada `DROP`, `TRUNCATE`, pembuatan akun, atau perubahan data/role lama.
5. Deploy helper dan konfigurasi waktu bersama. Konfigurasi operasional akan diisi pada tahap berikutnya melalui admin. Jangan mengisi jam sekolah atau ketua kelas dengan asumsi otomatis.

Migrasi hanya menambahkan tabel KBM dan dua referensi `pagi`/`siang`. Tidak ada seed periode, jadwal, pertemuan, atau laporan kehadiran.

## Struktur dan sumber data

| Tabel | Fungsi |
| --- | --- |
| `kbm_periode` | Tahun ajaran, semester, rentang tanggal; unik tahun/semester |
| `kbm_sesi_sekolah` | Referensi sesi pagi/siang, berbeda dari pertemuan |
| `kbm_pola_waktu` | Versi pola per periode, sesi, hari ISO Senin=1 sampai Minggu=7, masa berlaku |
| `kbm_slot_waktu` | JP 1–6 dan slot istirahat, urutan dan jam |
| `kbm_kelas_sesi` | Penempatan kelas pada sesi dengan masa berlaku dalam periode |
| `kbm_ketua_kelas` | Izin tambahan siswa per kelas/periode, masa berlaku, waktu dan admin pencabut |
| `kbm_jadwal` | Satu blok JP mingguan, relasi pengajaran/pola/kelas-sesi, masa berlaku dan snapshot pengajaran |
| `kbm_hari_tanpa_kbm` | Pengecualian tanggal, seluruh sekolah atau kelas/sesi tertentu, alasan |
| `kbm_pertemuan` | Pelaksanaan bertanggal, unik `(jadwal_id,tanggal)`, snapshot nama/identitas/periode/sesi/JP/jam |
| `kbm_laporan` | Maksimal satu laporan asli per pertemuan; waktu masuk/keluar, siswa dan penugasan pelapor, nama pelapor |
| `kbm_koreksi_admin` | Riwayat tambahan: admin, alasan, waktu pencatatan, nilai lama dan nilai baru; laporan asli dipertahankan |

Master user, siswa, kelas, guru, mata pelajaran dan relasi `pengajaran` dipakai ulang. Tidak ditemukan tabel jadwal mingguan yang sesuai di proyek: `rekap_mengajar` mencatat realisasi mengajar, bukan jadwal induk. `kbm_jadwal` menjadi sumber jadwal mingguan KBM tunggal. Tidak disalin ke modul jadwal lain. Identitas guru/mapel/kelas dipilih dari `pengajaran`, bukan diinput bebas. Snapshot merupakan arsip historis, bukan master kedua.

Semua FK KBM memakai `RESTRICT` agar penghapusan master tidak menghapus riwayat. Penghapusan master yang telah dipakai KBM akan ditolak database; arsipkan/nonaktifkan sesuai fasilitas proyek. Pergantian semester membuat baris periode dan jadwal baru, tidak mengosongkan periode lama. Nama dan identitas pada jadwal disalin saat pembuatan; snapshot pertemuan dibuat dari arsip tersebut. Perubahan master sesudahnya tidak mengganti arsip. Pola, penempatan kelas dan jadwal lama tidak ditimpa. Versi selanjutnya harus memakai rentang tanggal yang tidak bertumpang tindih; helper tahap ini hanya menambah versi, belum menyediakan alur perubahan rentang versi yang sudah aktif.

## Validasi dan pemakaian lapisan data

`includes/monitoring_kbm_data.php` adalah API PHP internal, bukan endpoint HTTP. Semua query berparameter untuk nilai input. Operasi tulis mengecek admin aktif di server dan menggunakan transaksi. Penguncian baris admin aktif dalam urutan ID menyerialisasi penulisan konfigurasi/koreksi melalui helper, termasuk lintas periode; tahap awal ini mengutamakan konsistensi dibanding paralelisme konfigurasi. Jangan memanggilnya di dalam transaksi lain atau melewati helper dengan SQL dari endpoint.

- `kbm_configure($db,$adminId,$type,$data)`: tipe `periode`, `pola_waktu`, `kelas_sesi`, `ketua_kelas`, `hari_tanpa_kbm`. Field sesuai tabel; `pola_waktu` menerima array `slots` dengan `jenis`, `jp` (NULL untuk istirahat), `mulai`, `selesai` (`HH:MM:SS`). ID admin harus berasal dari session terautentikasi.
- `kbm_create_schedule(...)`: menerima `kelas_sesi_id`, `pola_id`, `pengajaran_id`, `jp_mulai`, `jp_selesai`, `berlaku_mulai`, `berlaku_sampai`. Mengecek kecocokan kelas/periode/sesi, masa berlaku, bentrok guru/kelas berdasarkan jam nyata, dan istirahat. JP 1–6 lengkap, berurutan; pola slot tidak boleh beririsan. Blok JP 1–4 yang melewati istirahat setelah JP 2 ditolak: buat dua jadwal JP 1–2 dan JP 3–4. Setiap blok memiliki identitas pertemuan sendiri.
- `kbm_chair_permission($db,$userId,$periodeId,$kelasId)`: izin saat ini berdasarkan waktu server, siswa aktif, role siswa lama (`3`), keanggotaan kelas dan penugasan belum dicabut. `kbm_revoke_chair(...)` mencatat pencabutan tanpa menghapus laporan terdahulu.
- `kbm_expected($db,'2026-10-16')`: daftar jadwal tanggal tersebut tanpa menulis. Hari libur terlingkup dikecualikan. Hari tanpa jadwal tidak mempunyai KBM.
- `kbm_materialize($db,$adminId,$date)`: membuat pertemuan terjadwal dan snapshot secara idempoten; pemanggilan ulang tidak mengganti snapshot. Harus dipanggil secara eksplisit oleh proses admin/job terotorisasi pada tahap operasional. Tidak membuat check-in otomatis. Job belum dipasang pada tahap ini.
- `kbm_day_report(...)`: gabungan pertemuan tersimpan dan jadwal yang belum diwujudkan, dengan LEFT JOIN laporan dan koreksi terakhir. Jadwal tanpa check-in tetap muncul; GET laporan tidak membuat baris database. Pertemuan lama tetap tampil walaupun kalender belakangan diberi pengecualian. Perubahan kalender retrospektif bukan penghapusan riwayat; alur pembatalan operasional perlu ditentukan di tahap berikutnya.
- `kbm_correct(...)`: menerima ID pertemuan, waktu masuk/keluar atau NULL dan alasan wajib. Menambahkan riwayat koreksi, tidak menimpa laporan asli; NULL pada koreksi terakhir berarti pembatalan waktu, bukan fallback ke nilai asli. Waktu koreksi harus valid pada tanggal pertemuan, keluar tidak mendahului masuk.

Database menegakkan FK, keunikan, JP 1–6, tanggal/jam berurutan, pasangan data checkout, dan alasan tidak kosong. Aturan lintas baris/tabel (bentrok jadwal, konsistensi semester, siswa satu kelas, larangan melintasi istirahat, rentang versi) ditegakkan helper, bukan CHECK. Aplikasi harus menggunakan helper untuk penulisan; akun SQL dengan izin tulis langsung tetap dapat melewati aturan aplikasi. Riwayat koreksi bersifat append-only melalui helper; belum ada pembatasan privilege SQL per tabel.

Endpoint tahap berikutnya wajib memanggil guard role/status proyek dan CSRF sebelum operasi tulis. Akademik hanya dapat membaca; siswa hanya dapat melapor untuk izin ketua yang masih berlaku; guru/siswa biasa tidak boleh mengakses monitoring. Tahap ini tidak menambah endpoint sehingga tidak membuka akses baru.

## Waktu dan pelaporan

`config/session.php` memakai `Asia/Jakarta`; sebelumnya `Asia/Bangkok`, sama-sama UTC+7 sehingga jam lokal modul lama tidak bergeser. PDO mengatur timezone **per koneksi** `+07:00`, tanpa mengubah zona global database atau membutuhkan tabel timezone MySQL. Semua DATETIME KBM adalah waktu lokal Jakarta, bukan UTC; `zona_waktu` tersimpan pada pertemuan. Helper memakai `DateTimeZone('Asia/Jakarta')` secara eksplisit dan waktu audit berasal dari `CURRENT_TIMESTAMP` server.

Pada endpoint pelaporan mendatang, waktu check-in/check-out wajib diambil dari server, bukan form/client; pelapor/penugasan dari session dan hasil validasi izin. `checkin_at` memiliki default server; checkout harus ditulis dengan `CURRENT_TIMESTAMP` dalam transaksi dan tidak dapat dilakukan sebelum check-in. Sumber informasi adalah **laporan ketua kelas**, bukan deteksi fisik guru. Durasi rencana dan waktu laporan tidak boleh disamakan.

Aturan status untuk tahap tampilan berikutnya: merah ketika blok KBM berlangsung tanpa check-in efektif; hijau ketika sedang berlangsung, sudah check-in efektif dan belum checkout; kuning untuk sebelum jam, istirahat, tidak ada KBM/libur, sudah checkout atau jam blok berakhir, dengan keterangan berbeda. Checkout terlupa tetap NULL, diberi keterangan “KBM selesai — check-out belum dilaporkan”. `ditutup_sistem_pada` tersedia untuk penutupan administratif otomatis nanti, **bukan** waktu checkout atau bukti kehadiran; tidak boleh mengisi checkout dengan jam selesai rencana. Koreksi hanya oleh admin dengan alasan dan riwayat lengkap.

## Pemeriksaan

- `C:/xampp/php/php.exe -l includes/monitoring_kbm_data.php` serta lint konfigurasi.
- `node --test tests/monitoring-kbm.test.cjs`: memerlukan MariaDB sementara di 127.0.0.1:3306 dan marker `%TEMP%/lms-akademik-test-path.txt`. Test memverifikasi `@@datadir` sama dengan marker sebelum membuat database acak terpisah. Menolak database normal; tidak menghapus database atau menyalin data produksi.
- Uji meliputi migrasi dua kali, tanpa akun bawaan, timezone, guard admin, validasi tanggal/JP, versi bertumpang tindih, ketua salah kelas, bentrok guru/kelas, larangan lintas istirahat, jadwal beda semester, laporan tanpa check-in, pembacaan tanpa perubahan data, pertemuan idempoten, snapshot setelah perubahan nama master, koreksi/NULL tanpa mengubah laporan asli, libur, pencabutan ketua, FK pelindung riwayat, serta pergantian semester.

Asumsi: satu ketua aktif per kelas pada rentang yang sama, JP bernomor 1–6 untuk setiap sesi/hari, satu blok KBM berada pada satu tanggal lokal, masa berlaku inklusif, dan tidak ada sesi lintas tengah malam. Jam, hari sekolah dan kalender libur harus diisi admin berdasarkan kebijakan sekolah. Belum ada migrasi produksi yang dijalankan atau konfigurasi KBM otomatis yang diaktifkan.
