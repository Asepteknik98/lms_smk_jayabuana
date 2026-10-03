# Nilai pengganti otomatis dan Ujian Praktik

Pada Guru > Rekap Nilai, klik **Isi Manual** pada Ulangan Harian, Tugas Harian, atau Kehadiran untuk siswa tertentu. Masukkan nilai 0–100 dan klik **Simpan Nilai**. Klik **Kembali ke Otomatis**, lalu **Simpan Nilai**, untuk kembali memakai nilai kegiatan terbaru. Pergantian sumber belum tersimpan sampai tombol simpan ditekan.

Tambahkan **Ujian Praktik** melalui Komponen Penilaian dan sesuaikan bobot komponen lainnya agar total tetap 100%. Bobot praktik tidak ditentukan otomatis. Input praktik bersifat manual, ikut nilai akhir dan seluruh rekap/unduhan guru serta admin.

Tidak ada migrasi SQL baru untuk pembaruan ini. Nilai pengganti menggunakan baris `nilai_komponen` untuk komponen otomatis; pengembalian ke otomatis menghapus baris pengganti tersebut dan mencatat nilai sistem saat pengembalian pada `riwayat_nilai`. Data tugas, ujian, dan absensi asal tetap utuh. Ini memerlukan tabel yang sudah digunakan fitur rekap sebelumnya, termasuk `nilai_tugas_manual`.

Upload delapan file PHP berikut ke production sebagai satu pembaruan:

```text
admin/rekap_nilai.php
guru/rekap_nilai.php
guru/rekap_nilai_action.php
guru/rekap_action.php
guru/laporan_nilai_siswa.php
includes/penilaian_override.php
includes/rekap_akademik_admin.php
includes/rekap_akademik_matrix.php
```

`includes/penilaian_override.php` adalah file baru. File lainnya menggantikan versi sebelumnya. Tidak perlu mengupload folder tests.

Pengujian: `node --test tests/rekap-akademik.test.cjs`. Data uji memakai tabel sementara: nilai nol, validasi 0–100, akses guru/kelas, transaksi gagal, pengembalian ke nilai otomatis terbaru, riwayat, bobot praktik, tabel horizontal, dan ekspor.
