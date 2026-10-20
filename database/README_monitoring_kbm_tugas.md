# Status KBM: Guru hanya memberikan tugas

Siswa/ketua kelas dapat memilih status ini setelah memilih guru dan rentang JP, sebelum check-in. Tombol dan status tampil abu-abu. Waka hanya membaca laporan pada dashboard dan riwayat.

Laporan menyimpan identitas pelapor dan waktu server pada snapshot KBM yang sudah ada, tanpa membuat waktu kehadiran. JP yang dilaporkan terkunci; gunakan pembatalan dengan alasan jika salah. Tidak membutuhkan migrasi SQL tambahan pada instalasi monitoring KBM yang sudah berjalan.

## Unggah ke hosting

Ekstrak isi monitoring-kbm-tugas-update.zip ke folder utama LMS dengan struktur folder tetap. Timpa file yang sesuai, lalu muat ulang halaman siswa dan waka. Paket berisi semua file fitur yang berubah; unggah seluruh isinya bersama-sama.

## File aplikasi yang diperbarui

- `assets/css/monitoring-kbm-dashboard.css`
- `assets/css/monitoring-kbm-report.css`
- `assets/css/monitoring-kbm-student.css`
- `assets/js/monitoring-kbm-dashboard.js`
- `includes/monitoring_kbm_board.php`
- `includes/monitoring_kbm_dashboard.php`
- `includes/monitoring_kbm_data.php`
- `includes/monitoring_kbm_export.php`
- `includes/monitoring_kbm_history.php`
- `includes/monitoring_kbm_history_page.php`
- `includes/monitoring_kbm_matrix.php`
- `includes/monitoring_kbm_matrix_export.php`
- `includes/monitoring_kbm_matrix_page.php`
- `includes/monitoring_kbm_pilihan.php`
- `includes/monitoring_kbm_pilihan_page.php`
- `includes/monitoring_kbm_report_page.php`
- `includes/monitoring_kbm_siswa.php`
- `includes/monitoring_kbm_status.php`
