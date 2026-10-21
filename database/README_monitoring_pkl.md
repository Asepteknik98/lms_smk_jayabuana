# Kelompok PKL

Admin membuka Pengaturan Monitoring KBM > Kelas PKL Gabungan, membuat nama
kelompok, lalu memilih minimal dua kelas asal. Seluruh siswa kelas asal dianggap
sebagai anggota kelompok; pemilihan siswa individual belum disediakan. Satu kelas
asal hanya boleh berada dalam satu kelompok.

Metadata kelompok disimpan di `storage/monitoring-pkl/groups.php` dalam format
JSON dengan pengaman PHP, disertai penolakan akses HTTP melalui `.htaccess`.
Folder tersebut harus dapat ditulis PHP dan disertakan dalam cadangan aplikasi.
Penyimpanan memakai penguncian dan penggantian file sementara; data rusak tidak
ditimpa otomatis.

Tidak ada migrasi, tabel, atau kolom baru. Tombol **Simpan Sesi Kelompok** memakai
tabel `kbm_kelas_sesi` yang sudah ada, dalam satu transaksi untuk seluruh kelas
anggota. Jadi fitur ini bukan sepenuhnya bebas database: kelompoknya berbasis
file, tetapi sesi dan pelaporan memakai penyimpanan KBM yang sudah ada.

Kelompok tidak membuat identitas kelas baru dalam LMS, tidak memindahkan siswa,
dan tidak menggabungkan laporan. Di tab Pengguna, nama kelompok PKL tersedia
dalam pilihan kelas. Admin dapat mencentang beberapa siswa aktif dari seluruh
kelas anggota sekaligus, maksimal empat pengguna total untuk kelompok tersebut.
Nama siswa disertai kelas asal. Penugasan yang sudah ada di kelas asal ikut
dihitung; izin pelaporan setiap siswa tetap berlaku untuk kelas asalnya.
Admin dapat mencabut penugasan langsung dari tampilan kelompok.
Mengubah anggota atau menghapus kelompok tidak mengubah sesi yang
sudah disimpan. Admin dapat mengatur ulang sesi melalui Sesi Kelas.

Uji penyimpanan tanpa database: `C:/xampp/php/php.exe tests/monitoring-pkl.test.php`.

Pada papan monitoring Waka/Akademik, satu kelompok PKL ditampilkan sebagai satu
kartu. Kelas anggota tidak ditampilkan terpisah. Detail kartu tetap memuat status
dan catatan kelas asal. Ringkasan dan filter dihitung setelah penggabungan; papan
admin tetap memakai kartu kelas asal. Pembaruan otomatis menggunakan pengelompokan
yang sama. Status berbeda ditandai "Status beragam", dengan prioritas warna merah,
kuning, hijau, lalu abu-abu. Sesi yang belum seragam masuk kategori sesi belum
diatur. Pengelompokan ini hanya tampilan berdasarkan konfigurasi kelompok saat ini,
termasuk ketika tanggal lampau dipilih; riwayat laporan tidak diubah.
