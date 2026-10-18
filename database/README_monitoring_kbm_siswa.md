# Monitoring KBM ketua kelas — tahap 5

Pembaruan tahap 6: status tampilan dan kelayakan waktu check-in kini memakai layanan pusat `monitoring_kbm_status.php`. Aturan warna, status kelas, peringatan terpisah dan dashboard Admin/Akademik dijelaskan di `README_monitoring_kbm_status.md`.

## Penerapan dan penggunaan

Tidak ada migrasi database baru pada tahap ini. Tetap memerlukan migrasi fondasi tahap 3 dan versi jadwal tahap 4 yang sudah disiapkan sebelumnya. Tidak ada migrasi produksi yang dijalankan otomatis. Deploy halaman, helper, sidebar dan pembaruan helper data bersama.

Admin lebih dahulu melengkapi periode, sesi kelas, pola JP/istirahat, data Pengajaran, jadwal KBM dan penugasan siswa sebagai ketua kelas melalui pengaturan tahap 4. Setelah siswa login pada akun siswa yang sama, menu **Monitoring KBM** muncul hanya jika akun, keanggotaan kelas, masa penugasan dan periode masih aktif berdasarkan tanggal server. Tidak ada role/akun ketua kelas terpisah. Siswa biasa tetap menggunakan menu LMS sebelumnya.

1. Buka **Monitoring KBM** (`siswa/monitoring_kbm.php`). Periksa kelas, sesi, tanggal dan waktu server (WIB).
2. Pilih JP 1–6. Jika jadwal mencakup JP 1–2, memilih JP 1 maupun JP 2 memilih blok dan catatan yang sama. Guru/mapel diambil dari arsip jadwal, tidak dapat diinput siswa. JP tanpa jadwal tidak menampilkan tombol check-in.
3. Tekan **Check-in Guru** ketika guru masuk pada jam KBM yang sedang berlangsung. Check-in tidak tersedia sebelum mulai, pada batas jam selesai, saat istirahat, hari tanpa jadwal atau libur terlingkup.
4. Setelah check-in muncul peringatan pertemuan aktif dengan tombol **Check-out Guru**. Tekan saat guru selesai mengajar. Waktu masuk/keluar dan durasi tercatat ditampilkan setelah selesai, termasuk hasil penutupan terlambat pada hari berikutnya.
5. Jika lupa check-out, waktu keluar tetap **NULL / Belum check-out**. Pertemuan lama muncul sebagai peringatan dan menghalangi check-in pertemuan lain sampai ditutup. Tidak ada waktu selesai otomatis, tidak ada durasi yang diisi dari jam rencana.
6. Gunakan **Muat Ulang** untuk memperbarui status. Jam yang tampil adalah waktu server saat halaman dimuat; kelayakan tombol selalu diperiksa ulang di server ketika ditekan, bukan berdasarkan jam HP.

Sumber data kehadiran adalah laporan ketua kelas, bukan deteksi fisik guru. Durasi tercatat menunjukkan selisih laporan aktual; penutupan terlambat dapat menghasilkan durasi lebih panjang dari jadwal dan perlu ditindaklanjuti admin.

## Izin dan pencatatan

- Halaman GET/POST memakai guard role siswa dan status akun proyek. Siswa tanpa penugasan aktif mendapat 403 walaupun membuka URL langsung. Menu hanya tampil bagi ketua aktif. Jika migrasi belum lengkap, halaman memberikan pesan belum dikonfigurasi (503); menu LMS lain tetap tersedia.
- Semua perubahan memerlukan POST dan CSRF. Form hanya menerima `action`, `id`, `csrf_token`; tanggal, waktu, kelas, guru, mapel, atau pelapor yang disisipkan siswa ditolak. Check-in menggunakan ID jadwal; check-out menggunakan ID pertemuan. Tidak ada endpoint edit, hapus, tanggal lampau, atau buka ulang.
- `kbm_student_record()` memverifikasi ulang user aktif, role siswa, kelas saat ini dan penugasan yang belum dicabut di dalam transaksi. Perubahan status akun, pencabutan dan perpindahan kelas berlaku pada request berikutnya. Pencabutan yang beradu dengan request berjalan diserialisasi melalui kunci penugasan: request yang memperoleh izin sebelum pencabutan selesai dapat menyelesaikan transaksinya; request berikutnya ditolak.
- Waktu berasal dari `CURRENT_TIMESTAMP` database pada koneksi UTC+7. Check-in hanya memilih jadwal hari server saat ini, pada rentang **mulai inklusif, selesai eksklusif**. Guru/mapel/sesi/JP menggunakan snapshot jadwal; pertemuan dibentuk secara idempoten bila belum ada. `dibuat_oleh` pada pertemuan adalah user pembuat (dapat berupa ketua kelas), sedangkan identitas siswa dan penugasannya dicatat pada laporan. Tidak ada akun admin tiruan.
- Seluruh tanggal/periode diperiksa untuk pertemuan aktif kelas yang sama. Ketua saat ini boleh menutup laporan lama kelasnya, termasuk laporan ketua sebelumnya/periode lalu, dengan waktu aktual server hari penutupan. Identitas pelapor masuk tetap utuh; identitas pelapor keluar dicatat terpisah. Akun yang izinnya dicabut tidak dapat menutupnya sendiri lagi.
- Klik ulang check-in yang sudah tercatat tidak mengganti waktu masuk. Klik ulang check-out tidak mengganti waktu selesai. Pertemuan selesai tidak dapat dibuka ulang. Setelah koreksi admin, siswa tidak dapat mengubah catatan tersebut; pertemuan hasil koreksi admin yang masih terbuka memerlukan tindak lanjut admin. Ini menjaga audit koreksi tetap berlaku.

## Permintaan bersamaan

Penulisan memakai transaksi InnoDB dan mengunci baris master **kelas** (`SELECT ... FOR UPDATE`) sebelum memeriksa izin dan pertemuan aktif. Semua check-in/check-out kelas yang sama diserialisasi, termasuk session/perangkat berbeda dan ketua pengganti. Transaksi menggunakan **READ COMMITTED** agar pemeriksaan sesudah menunggu kunci melihat transaksi terbaru yang sudah commit. Keunikan `(jadwal_id,tanggal)` dan PK laporan per pertemuan tetap menjadi pengaman tambahan. Tidak mengandalkan penonaktifan tombol atau kunci session PHP saja.

Koreksi admin juga memakai mutex kelas sebelum mengunci pertemuan dan tidak dapat membuka pertemuan efektif kedua pada kelas yang sama. Helper admin KBM menggunakan READ COMMITTED per transaksi agar perubahan dan pemeriksaan konsisten; tidak mengubah isolation level global database atau modul LMS lain. Tidak ada pembuatan checkout otomatis maupun penghapusan laporan.

## File

- `siswa/monitoring_kbm.php`: controller GET/POST, CSRF, jadwal/JP, peringatan belum check-out, hasil durasi, tampilan responsif.
- `includes/monitoring_kbm_siswa.php`: izin ketua aktif, transaksi pelaporan, waktu server, pencatatan idempoten, durasi.
- `includes/monitoring_kbm_data.php`: penyimpanan pertemuan internal bersama, pembacaan pertemuan terbuka, koordinasi koreksi admin dengan kunci kelas.
- `includes/sidebar.php`: menu bersyarat untuk siswa yang ditugaskan.
- `tests/monitoring-kbm-siswa.test.cjs`: pengujian fungsi transaksi serta render/controller dan akses halaman.

## Pemeriksaan

`node --test tests/monitoring-kbm-siswa.test.cjs` memakai MariaDB sementara terpisah. Sebelum penulisan, test mencocokkan `@@datadir` dengan marker `%TEMP%/lms-akademik-test-path.txt`, lalu membuat database acak khusus test. Simulasi waktu hanya melalui session database milik test; tidak ada parameter waktu uji yang diterima aplikasi.

Pengujian mencakup alur normal, timestamp server, check-in terlalu awal/di batas selesai/istirahat/hari tanpa jadwal, penyisipan tanggal/waktu, checkout tanpa check-in, akses beda kelas/role/siswa biasa, akun nonaktif, siswa pindah kelas, pencabutan langsung, penutupan terlambat lintas hari oleh pengganti, pelestarian identitas pelapor, klik ulang, enam proses PHP check-in bersamaan pada satu blok, serta dua proses pada blok bentrok kelas yang sama. Fixture bentrok sengaja dimasukkan lewat SQL hanya pada database test untuk menguji mutex terpisah dari validasi jadwal admin. Hanya satu pertemuan aktif yang berhasil.

Controller juga diuji untuk POST valid dan CSRF salah, render HTML, kompilasi JavaScript inline, tampilan hasil checkout hari sebelumnya, serta penyembunyian menu dari siswa biasa sambil mempertahankan tautan Absensi dan Tugas. Jalankan `node --test tests/*.test.cjs` untuk regresi seluruh modul. Pemeriksaan tampilan mencakup HTML/CSS responsif dan sintaks JavaScript; belum berupa uji visual browser/HP otomatis.

Tahap ini berhenti pada pelaporan ketua kelas. Dashboard monitoring Akademik, ekspor riwayat dan halaman tindak lanjut admin tidak dikembangkan pada tahap ini.
