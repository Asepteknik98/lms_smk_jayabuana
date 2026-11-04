# Pengaturan akses kredit siswa

Jalankan `2026_11_03_kredit_akses_guru.sql`. Migrasi dapat diulang; tidak memberi akses otomatis kepada guru. Admin tetap dapat mengelola kedua modul.

Di sidebar admin, buka **Pengaturan Akses Kredit Siswa**. Centang guru secara terpisah pada Kredit Poin Siswa dan Kredit Aktivitas Siswa, lalu simpan. Masing-masing modul dapat memiliki beberapa guru; satu guru dapat dipilih pada keduanya. Kosongkan pilihan untuk mencabut semua akses guru pada modul tersebut. Hanya guru dengan akun aktif yang dapat dipilih.

Guru tanpa penunjukan tidak melihat menu modul dan mendapat HTTP 403 ketika membuka URL langsung, termasuk halaman bukti aktivitas. Pemeriksaan layanan juga berlaku saat mencatat pelanggaran, menerbitkan acara, atau memverifikasi klaim. Hak akses diperiksa dari database pada setiap permintaan, sehingga pencabutan tidak menunggu logout. Guru aktivitas tetap hanya meninjau acara miliknya; admin meninjau semua acara. Halaman siswa tetap tersedia.

Penyimpanan hanya untuk admin aktif, memakai CSRF pada halaman, validasi guru, transaksi database, dan log aktivitas. Pencarian daftar guru tidak menghapus centang pada guru yang tersembunyi.

Uji: `node tests/kredit-akses.test.cjs`, `node tests/kredit-poin.test.cjs`, dan `node tests/kak-klaim.test.cjs` menggunakan database pengujian terpisah.
