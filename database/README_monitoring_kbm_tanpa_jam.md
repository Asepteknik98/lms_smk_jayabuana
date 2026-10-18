# Pelaporan ketua kelas tanpa pengaturan waktu JP

Ketua kelas dapat langsung memilih JP 1–6 atau rentang JP, memilih salah satu guru aktif sekolah, lalu check-in/check-out. Admin tidak perlu membuat pola waktu JP maupun jadwal guru untuk alur ini. Waktu aktual tetap berasal dari server Asia/Jakarta saat tombol ditekan.

## Penerapan

1. Cadangkan database.
2. Pastikan migrasi Monitoring KBM sebelumnya, termasuk `2026_10_16_monitoring_kbm_pilihan_guru.sql`, sudah diterapkan.
3. Impor `2026_10_17_monitoring_kbm_tanpa_jam.sql` melalui phpMyAdmin pada database LMS yang benar. Migrasi ini disiapkan untuk penerapan manual, tidak dijalankan otomatis pada database utama.
4. Muat ulang halaman Monitoring KBM siswa.

Migrasi mengizinkan `kbm_jadwal.pola_id` kosong khusus pilihan ketua kelas bertanggal tunggal. Jam rencana pada pertemuan boleh sama-sama kosong. Tidak ada jam pengganti/fiktif, penghapusan data, atau perubahan timestamp lama. SQL dapat dijalankan ulang; uji pada salinan database sebelum penerapan.

Pembagian kelas ke sesi pagi/siang, periode aktif, dan izin ketua kelas tetap diperlukan. Hari yang secara eksplisit ditetapkan admin sebagai hari tanpa KBM tetap ditolak. Tidak adanya pola waktu untuk hari tertentu tidak lagi menghalangi pilihan guru.

## Dampak tampilan dan data

- JP ditampilkan sebagai nomor tanpa jam jadwal. Pilihan rentang berwarna hijau hanya menunjukkan pilihan JP; check-in tetap harus ditekan untuk mencatat guru masuk.
- Satu kelas tetap hanya memiliki satu laporan aktif; check-out wajib dilakukan sebelum memilih guru berikutnya.
- Dashboard Waka mempertahankan indikator merah/hijau/kuning dan polling yang sudah tersedia. Tidak lagi menuntut kelengkapan pola waktu/jadwal guru untuk pelaporan bebas JP.
- Riwayat dan Excel menampilkan jam rencana kosong dengan penjelasan tanpa jadwal jam. Waktu aktual, durasi, koreksi, pembatalan teraudit, dan laporan lama tetap tersedia.
- Sebelum migrasi baru diterapkan, alur lama yang sudah memiliki pola JP tetap berfungsi. Kelas tanpa pola mendapatkan petunjuk migrasi satu kali, bukan permintaan mengisi waktu JP.

## Pemeriksaan

Uji integrasi pada `tests/monitoring-kbm-siswa.test.cjs` mencakup migrasi berulang, hari Sabtu tanpa pola waktu, pilihan rentang JP dan guru, penolakan kelas lain, check-in ganda, jam rencana NULL, dashboard akademik hijau, check-out, riwayat, Excel valid, dan pembatalan. Pengujian memakai database sementara pada server uji terisolasi, bukan database utama.

Hasil aktual: 42 pengujian lain pada suite penuh lulus. Uji integrasi siswa sempat menemukan label jam kosong di kartu Waka; label diperbaiki, lalu uji integrasi tersebut dijalankan ulang dan lulus (63,6 detik). Total 43 pengujian yang tersedia telah lulus, dengan pengulangan khusus pada uji yang diperbaiki. Jumlah query dashboard tetap konstan pada 4 maupun 84 kelas (15 query, termasuk pemeriksaan skema satu kali per pembacaan).

Uji sintaks PHP pada delapan berkas modul yang berubah lulus. Migrasi diuji dua kali pada database sementara. Tampilan browser dengan akun nyata dan penerapan migrasi database utama belum dilakukan.
