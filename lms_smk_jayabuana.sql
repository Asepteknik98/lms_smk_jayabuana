-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 25, 2026 at 06:58 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lms_smk_jayabuana`
--

-- --------------------------------------------------------

--
-- Table structure for table `akses_pertemuan`
--

CREATE TABLE `akses_pertemuan` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `pertemuan_ke` tinyint(3) UNSIGNED NOT NULL,
  `status` enum('Dibuka','Dikunci') NOT NULL DEFAULT 'Dikunci',
  `wajib_unduh_materi` tinyint(1) NOT NULL DEFAULT 0,
  `dibuka_pada` datetime DEFAULT NULL,
  `diperbarui_pada` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `akses_pertemuan`
--

INSERT INTO `akses_pertemuan` (`id`, `pengajaran_id`, `pertemuan_ke`, `status`, `wajib_unduh_materi`, `dibuka_pada`, `diperbarui_pada`) VALUES
(1, 1, 1, 'Dikunci', 0, NULL, '2026-07-24 16:03:49'),
(2, 1, 2, 'Dikunci', 0, NULL, '2026-07-24 15:46:22'),
(3, 1, 4, 'Dikunci', 0, NULL, '2026-07-24 15:46:07'),
(4, 13, 3, 'Dikunci', 0, NULL, '2026-07-24 16:03:45'),
(5, 13, 5, 'Dikunci', 0, NULL, '2026-07-24 15:23:02'),
(6, 14, 1, 'Dibuka', 0, '2026-07-24 19:47:27', '2026-07-24 12:47:27'),
(7, 13, 2, 'Dibuka', 0, '2026-07-24 19:47:27', '2026-07-24 12:47:27'),
(8, 1, 6, 'Dibuka', 0, '2026-07-24 19:47:27', '2026-07-24 12:47:27'),
(21, 1, 3, 'Dikunci', 0, NULL, '2026-07-24 15:15:58'),
(38, 13, 1, 'Dibuka', 0, '2026-07-24 23:10:47', '2026-07-24 16:10:47'),
(39, 13, 4, 'Dikunci', 0, NULL, '2026-07-25 14:09:12'),
(40, 13, 8, 'Dikunci', 0, NULL, '2026-07-25 14:08:51'),
(45, 13, 7, 'Dibuka', 0, '2026-07-25 23:31:44', '2026-07-25 16:31:44');

-- --------------------------------------------------------

--
-- Table structure for table `catatan_siswa_pengajaran`
--

CREATE TABLE `catatan_siswa_pengajaran` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `catatan` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `catatan_siswa_pengajaran`
--

INSERT INTO `catatan_siswa_pengajaran` (`id`, `pengajaran_id`, `siswa_id`, `catatan`, `updated_at`) VALUES
(2, 1, 2, 'perlu perbaikan nilai', '2026-07-23 14:45:31');

-- --------------------------------------------------------

--
-- Table structure for table `detail_absensi`
--

CREATE TABLE `detail_absensi` (
  `id` int(10) UNSIGNED NOT NULL,
  `sesi_absensi_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `status` enum('Hadir','Sakit','Izin','Alpa') NOT NULL DEFAULT 'Alpa',
  `waktu_checkin` datetime DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `detail_absensi`
--

INSERT INTO `detail_absensi` (`id`, `sesi_absensi_id`, `siswa_id`, `status`, `waktu_checkin`, `keterangan`, `bukti_file`, `ip_address`, `updated_at`) VALUES
(3, 1, 2, 'Alpa', NULL, NULL, NULL, NULL, '2026-07-24 07:09:18'),
(10, 4, 2, 'Hadir', '2026-07-23 23:19:54', NULL, NULL, '::1', '2026-07-23 16:19:54'),
(11, 5, 3, 'Alpa', NULL, 'Tidak melakukan check-in sampai sesi ditutup', NULL, NULL, '2026-07-23 16:36:13'),
(12, 6, 2, 'Hadir', '2026-07-23 23:47:45', NULL, NULL, '::1', '2026-07-23 16:47:45'),
(13, 9, 2, 'Hadir', '2026-07-24 10:18:37', NULL, NULL, '::1', '2026-07-24 03:18:37'),
(14, 10, 3, 'Hadir', '2026-07-24 13:19:47', NULL, NULL, '::1', '2026-07-24 06:19:47'),
(15, 12, 2, 'Hadir', '2026-07-24 13:34:51', NULL, NULL, '::1', '2026-07-24 06:34:51'),
(22, 13, 3, 'Hadir', '2026-07-25 09:03:07', NULL, NULL, '::1', '2026-07-25 02:03:07');

-- --------------------------------------------------------

--
-- Table structure for table `guru`
--

CREATE TABLE `guru` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guru`
--

INSERT INTO `guru` (`id`, `user_id`, `nip`, `nama_lengkap`, `email`, `created_at`) VALUES
(1, 9, '1350507902', 'Ahmad Sirod Nasution', 'asepsetiadi4781@gmail.com', '2026-07-22 01:36:11'),
(2, 11, '12345', 'Ari Ramadhan, S.Pd', 'Ari123@gmail.com', '2026-07-22 13:19:19'),
(3, 13, '1350507903', 'Rofiq Okvianto, M.Pd', 'rofiq1@gmail.com', '2026-07-22 14:39:29'),
(4, 18, '1350507904', 'Asep Setiadi, S.Kom', 'asepsetiadi478@gmail.com', '2026-07-22 16:07:10'),
(5, 19, '1350507905', 'Oka Vitaloka, S.E', 'okavitaloka1@gmail.com', '2026-07-23 16:11:23');

-- --------------------------------------------------------

--
-- Table structure for table `jawaban_siswa`
--

CREATE TABLE `jawaban_siswa` (
  `id` int(10) UNSIGNED NOT NULL,
  `sesi_ujian_id` int(10) UNSIGNED NOT NULL,
  `soal_id` int(10) UNSIGNED NOT NULL,
  `jawaban_pg` enum('A','B','C','D','E') DEFAULT NULL,
  `jawaban_esai` text DEFAULT NULL,
  `is_benar` tinyint(1) DEFAULT 0,
  `nilai_esai` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jawaban_siswa`
--

INSERT INTO `jawaban_siswa` (`id`, `sesi_ujian_id`, `soal_id`, `jawaban_pg`, `jawaban_esai`, `is_benar`, `nilai_esai`) VALUES
(1, 1, 2, 'B', NULL, 0, NULL),
(2, 1, 3, 'C', NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `jawaban_tugas`
--

CREATE TABLE `jawaban_tugas` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengumpulan_id` int(10) UNSIGNED NOT NULL,
  `pertanyaan_id` int(10) UNSIGNED NOT NULL,
  `opsi_id` int(10) UNSIGNED DEFAULT NULL,
  `jawaban_teks` longtext DEFAULT NULL,
  `benar` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kelas`
--

CREATE TABLE `kelas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `tingkat` enum('X','XI','XII') NOT NULL,
  `jurusan` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `tingkat`, `jurusan`, `created_at`) VALUES
(1, 'X TKJ 1', 'X', 'Teknik Komputer dan Jaringan', '2026-07-21 16:13:18'),
(2, 'XI TKJ 1', 'XI', 'Teknik Komputer dan Jaringan', '2026-07-21 16:13:18'),
(3, 'XII DTF 1', 'XII', 'Desain Teknik Furniture', '2026-07-21 16:22:23');

-- --------------------------------------------------------

--
-- Table structure for table `komponen_penilaian`
--

CREATE TABLE `komponen_penilaian` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `nama_komponen` varchar(100) NOT NULL,
  `bobot` decimal(5,2) UNSIGNED NOT NULL,
  `urutan` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `komponen_penilaian`
--

INSERT INTO `komponen_penilaian` (`id`, `pengajaran_id`, `nama_komponen`, `bobot`, `urutan`, `created_at`) VALUES
(3, 1, 'Ulangan Harian', 10.00, 1, '2026-07-23 02:59:10'),
(4, 1, 'Tugas Harian', 10.00, 2, '2026-07-23 02:59:27'),
(5, 1, 'Kehadiran', 30.00, 3, '2026-07-23 02:59:39'),
(6, 1, 'UTS', 20.00, 4, '2026-07-23 02:59:52'),
(7, 1, 'UAS', 30.00, 5, '2026-07-23 02:59:59'),
(8, 13, 'Ulangan Harian', 10.00, 1, '2026-07-23 05:49:04'),
(9, 13, 'Tugas Harian', 10.00, 2, '2026-07-23 05:49:10'),
(10, 13, 'Kehadiran', 30.00, 3, '2026-07-23 05:49:21'),
(11, 13, 'UTS', 20.00, 4, '2026-07-23 05:49:28'),
(12, 13, 'UAS', 30.00, 5, '2026-07-23 05:49:35');

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `user_id`, `aktivitas`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:33:53'),
(2, 11, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:33:59'),
(3, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:36:12'),
(4, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:36:18'),
(5, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:36:51'),
(6, 9, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:36:51'),
(7, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:37:44'),
(8, 9, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:38:38'),
(9, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:38:45'),
(10, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:39:32'),
(11, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:39:38'),
(12, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:39:45'),
(13, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:39:53'),
(14, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:40:06'),
(15, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:40:11'),
(16, 10, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:41:19'),
(17, 10, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:41:19'),
(18, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:41:48'),
(19, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:41:57'),
(20, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:42:32'),
(21, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:42:40'),
(22, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:43:18'),
(23, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:43:26'),
(24, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:46:05'),
(25, 9, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:46:06'),
(26, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:52:52'),
(27, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:52:52'),
(28, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:53:36'),
(29, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:53:43'),
(30, 1, 'Menambahkan penugasan mengajar ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:54:08'),
(31, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:54:29'),
(32, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:54:35'),
(33, 13, 'Membuat modul pertemuan 1: contoh saja', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:55:29'),
(34, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:55:46'),
(35, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:55:53'),
(36, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:55:59'),
(37, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:56:18'),
(38, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:58:13'),
(39, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:58:13'),
(40, 10, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:58:13'),
(41, 10, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 14:58:14'),
(42, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:59:38'),
(43, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 14:59:44'),
(44, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:03:43'),
(45, 9, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:03:43'),
(46, 13, 'Membuka absensi pertemuan 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:05:01'),
(47, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:05:10'),
(48, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:05:19'),
(49, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:07:18'),
(50, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:07:20'),
(51, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:07:54'),
(52, 12, 'Check-in absensi Bahasa Inggris pertemuan 20: Hadir', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:07:55'),
(53, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:07:56'),
(54, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:08:57'),
(55, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:09:04'),
(56, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:09:25'),
(57, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:09:34'),
(58, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:10:52'),
(59, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:10:52'),
(60, 12, 'Check-in absensi Bahasa Inggris pertemuan 1: Hadir', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:11:15'),
(61, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:11:29'),
(62, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:11:36'),
(63, 13, 'Menutup absensi pertemuan 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:12:56'),
(64, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:14:55'),
(65, 9, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:14:55'),
(66, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:18:58'),
(67, 9, 'Logout', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:18:59'),
(68, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:19:14'),
(69, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:25:56'),
(70, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:26:27'),
(71, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:27:32'),
(72, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 15:27:39'),
(73, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:30:25'),
(74, 9, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:30:26'),
(75, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:33:57'),
(76, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:38:52'),
(77, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:43:42'),
(78, 1, 'Import siswa: 1 berhasil, 0 gagal', '::1', 'curl/8.21.0', '2026-07-22 15:43:44'),
(79, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:47:31'),
(80, 1, 'Import siswa: 1 berhasil, 0 gagal', '::1', 'curl/8.21.0', '2026-07-22 15:47:32'),
(81, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:52:35'),
(82, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:54:50'),
(83, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 15:57:43'),
(84, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:01:53'),
(85, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:04:34'),
(86, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:04:52'),
(87, 1, 'Menambahkan penugasan mengajar ID: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:04:56'),
(88, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:05:24'),
(89, 1, 'Menambahkan penugasan mengajar ID: 10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:07:26'),
(90, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:10:35'),
(91, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:12:30'),
(92, 1, 'Menambahkan penugasan mengajar ID: 12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:12:56'),
(93, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:19:25'),
(94, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:27:50'),
(95, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:28:06'),
(96, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:28:17'),
(97, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:29:42'),
(98, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:29:52'),
(99, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:30:04'),
(100, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:35:42'),
(101, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:37:24'),
(102, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:44:13'),
(103, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:54:53'),
(104, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:55:26'),
(105, 13, 'Membuat tugas: CODEX_TEST_TUGAS_220726', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:55:27'),
(106, 13, 'Mengubah tugas: CODEX_TEST_TUGAS_220726', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:55:27'),
(107, 13, 'Menghapus tugas: CODEX_TEST_TUGAS_220726', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:55:28'),
(108, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:56:51'),
(109, 13, 'Menilai tugas Hisam: CODEX_TEST_NILAI', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 16:56:52'),
(110, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:57:08'),
(111, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:57:15'),
(112, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:58:06'),
(113, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:58:12'),
(114, 13, 'Membuat tugas: contoh 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 16:59:37'),
(115, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:00:16'),
(116, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:00:24'),
(117, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:02:41'),
(118, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:02:47'),
(119, 13, 'Membuat tugas: contoh 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:03:17'),
(120, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:03:36'),
(121, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:03:44'),
(122, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:06:27'),
(123, 12, 'Mengirimkan jawaban tugas ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:07:25'),
(124, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:09:40'),
(125, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:09:48'),
(126, 13, 'Menilai tugas Hisam: contoh 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:10:40'),
(127, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:11:45'),
(128, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:11:53'),
(129, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:18:13'),
(130, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:21:06'),
(131, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:22:45'),
(132, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:26:20'),
(133, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:32:32'),
(134, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:34:33'),
(135, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:38:34'),
(136, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:40:52'),
(137, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:43:37'),
(138, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:47:13'),
(139, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:49:02'),
(140, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:49:07'),
(141, 13, 'Membuat tugas: Kerjakan halaman 32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:50:12'),
(142, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:50:31'),
(143, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:50:38'),
(144, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:54:33'),
(145, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 17:54:40'),
(146, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 17:57:31'),
(147, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:02:39'),
(148, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:08:20'),
(149, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:12:17'),
(150, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:14:29'),
(151, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:22:45'),
(152, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:25:18'),
(153, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:27:03'),
(154, 13, 'Membuat tugas: CODEX_SUBMIT_TEST_2307', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:27:03'),
(155, 13, 'Membuat tugas: Kerjakan halaman 50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 18:28:01'),
(156, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 18:28:26'),
(157, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 18:28:33'),
(158, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 18:30:53'),
(159, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 18:30:59'),
(160, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:33:13'),
(161, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:36:08'),
(162, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:38:18'),
(163, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.8875', '2026-07-22 18:42:15'),
(164, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 01:11:25'),
(165, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 01:42:45'),
(166, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 01:42:54'),
(167, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 01:42:58'),
(168, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 01:43:11'),
(169, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:04:23'),
(170, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:04:29'),
(171, 1, 'Menambahkan penugasan mengajar ID: 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:05:01'),
(172, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:05:11'),
(173, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:05:17'),
(174, 13, 'Membuat ujian baru: Ujian TOEFL 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:21:02'),
(175, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:21:38'),
(176, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:21:47'),
(177, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:22:51'),
(178, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:22:56'),
(179, 9, 'Mengimpor 1 soal Excel ke ujian: UJIAN PROBE EXCEL', 'UNKNOWN', 'UNKNOWN', '2026-07-23 03:28:41'),
(180, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:31:11'),
(181, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:31:18'),
(182, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:32:00'),
(183, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:32:07'),
(184, 13, 'Menambahkan soal ke ujian ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:32:52'),
(185, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:33:00'),
(186, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:33:07'),
(187, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:35:48'),
(188, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:35:53'),
(189, 13, 'Menambahkan soal ke ujian ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:36:45'),
(190, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:36:47'),
(191, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:36:55'),
(192, 12, 'Menyelesaikan Ujian ID: 1 dengan Nilai PG: 50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:49:31'),
(193, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:50:24'),
(194, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 03:50:29'),
(195, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:43:19'),
(196, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:43:28'),
(197, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:43:49'),
(198, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:43:54'),
(199, 13, 'Membuat tugas: Mikrotik', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:44:46'),
(200, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:45:18'),
(201, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:45:26'),
(202, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:45:31'),
(203, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:45:47'),
(204, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:46:08'),
(205, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:46:13'),
(206, 14, 'Mengirimkan jawaban tugas ID: 39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:47:02'),
(207, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:47:18'),
(208, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:47:23'),
(209, 13, 'Menilai tugas fatir: Mikrotik', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:48:30'),
(210, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:50:37'),
(211, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:50:43'),
(212, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:51:22'),
(213, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:51:27'),
(214, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:54:26'),
(215, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:54:32'),
(216, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 05:58:01'),
(217, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 06:00:37'),
(218, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 06:01:19'),
(219, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 06:01:26'),
(220, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 06:04:15'),
(221, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 13:03:30'),
(222, 13, 'Mengubah absensi Hisam pertemuan 1 menjadi Izin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 13:25:13'),
(223, 13, 'Mengubah absensi Hisam pertemuan 1 menjadi Alpa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 13:25:25'),
(224, 13, 'Mengubah absensi Hisam pertemuan 1 menjadi Sakit', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 13:25:42'),
(225, 13, 'Membuat ujian baru: Ujian Mikrotik', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 14:06:36'),
(226, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 14:56:21'),
(227, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:01:38'),
(228, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:01:42'),
(229, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:01:49'),
(230, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:01:51'),
(231, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:07:15'),
(232, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:07:18'),
(233, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:07:27'),
(234, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:09:47'),
(235, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 15:46:34'),
(236, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:01:53'),
(237, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:02:26'),
(238, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:08:59'),
(239, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:09:07'),
(240, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:13:42'),
(241, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:13:50'),
(242, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:14:29'),
(243, 19, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:14:33'),
(244, 19, 'Membuat tugas: PKK tugas 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:15:23'),
(245, 19, 'Membuat modul pertemuan 1: PKK day one', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:15:53'),
(246, 19, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:16:06'),
(247, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:16:13'),
(248, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:16:55'),
(249, 19, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:17:00'),
(250, 19, 'Membuka absensi pertemuan 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:17:41'),
(251, 19, 'Menghapus tugas: PKK tugas 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:17:55'),
(252, 19, 'Membuat tugas: PKK tugas 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:18:17'),
(253, 19, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:18:22'),
(254, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:18:34'),
(255, 12, 'Check-in absensi Produk Kreatif dan Kewirausahaan pertemuan 1: Hadir', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:19:54'),
(256, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:22:56'),
(257, 19, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:23:01'),
(258, 19, 'Mengubah tugas: PKK tugas 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:23:13'),
(259, 19, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:23:15'),
(260, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:23:24'),
(261, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:27:29'),
(262, 19, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:27:34'),
(263, 19, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:27:43'),
(264, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:27:51'),
(265, 12, 'Mengirimkan jawaban tugas ID: 41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:31:49'),
(266, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:34:36'),
(267, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:34:42'),
(268, 13, 'Membuka absensi pertemuan 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:35:14'),
(269, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:35:42'),
(270, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:35:48'),
(271, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:35:59'),
(272, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:36:04'),
(273, 13, 'Menutup absensi pertemuan 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:36:13'),
(274, 13, 'Membuka absensi pertemuan 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:36:32'),
(275, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:36:38'),
(276, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:36:45'),
(277, 12, 'Check-in absensi Bahasa Inggris pertemuan 3: Hadir', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-07-23 16:47:45'),
(278, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 17:13:02'),
(279, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 17:13:39'),
(280, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 17:24:38'),
(281, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:56:37'),
(282, 13, 'Membuka absensi pertemuan 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:59:45'),
(283, 13, 'Membuat modul pertemuan 2: kucing', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:02:42'),
(284, 13, 'Membuat tugas: kucing', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:05:02'),
(285, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:10:50'),
(286, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:10:59'),
(287, 12, 'Check-in absensi Bahasa Inggris pertemuan 4: Hadir', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:18:37'),
(288, 12, 'Mengirimkan jawaban tugas ID: 42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:20:16'),
(289, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:23:25'),
(290, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 03:23:32'),
(291, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:06:52'),
(292, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:08:51'),
(293, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:16:29'),
(294, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:16:48'),
(295, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:17:45'),
(296, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:17:53'),
(297, 13, 'Membuka absensi pertemuan 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:18:22'),
(298, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:18:26'),
(299, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:18:50'),
(300, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:19:00'),
(301, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:19:05'),
(302, 14, 'Check-in absensi Dasar-Dasar Mikrotik pertemuan 5: Hadir', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:19:47'),
(303, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:20:53'),
(304, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:21:10');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `aktivitas`, `ip_address`, `user_agent`, `created_at`) VALUES
(305, 13, 'Membuat modul pertemuan 5: tesssssssss', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:21:42'),
(306, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:21:50'),
(307, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:21:56'),
(308, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:24:30'),
(309, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:24:38'),
(310, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:25:28'),
(311, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:25:35'),
(312, 13, 'Membuat modul pertemuan 4: main voli', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:29:57'),
(313, 13, 'Membuka absensi pertemuan 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:31:37'),
(314, 13, 'Membuat tugas: membuat video servis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:33:10'),
(315, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:34:24'),
(316, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:34:28'),
(317, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:34:32'),
(318, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:34:38'),
(319, 12, 'Check-in absensi Bahasa Inggris pertemuan 6: Hadir', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:34:51'),
(320, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:43:49'),
(321, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:43:57'),
(322, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:48:46'),
(323, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:48:52'),
(324, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:53:41'),
(325, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:53:46'),
(326, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:54:57'),
(327, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:55:02'),
(328, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:57:14'),
(329, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:57:18'),
(330, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:58:18'),
(331, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:58:24'),
(332, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 07:02:38'),
(333, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 07:02:43'),
(334, 13, 'Mengubah absensi Hisam pertemuan 1 menjadi Alpa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 07:09:18'),
(335, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 07:15:29'),
(336, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 07:15:51'),
(337, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 07:16:06'),
(338, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:10:53'),
(339, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:20:57'),
(340, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:21:04'),
(341, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:29:27'),
(342, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:29:32'),
(343, 13, 'Membuat modul pertemuan 3: materi video mikrotik', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:32:40'),
(344, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:32:47'),
(345, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:32:53'),
(346, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:47:20'),
(347, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 09:47:26'),
(348, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:40:52'),
(349, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:42:15'),
(350, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:42:25'),
(351, 13, 'Dikunci akses pertemuan 2 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:48:15'),
(352, 13, 'Dibuka akses pertemuan 2 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:48:19'),
(353, 13, 'Dikunci akses pertemuan 2 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:48:41'),
(354, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:52:43'),
(355, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 12:52:49'),
(356, 14, 'Logout', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-07-24 13:12:36'),
(357, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-07-24 13:12:48'),
(358, 13, 'Membuat modul pertemuan 7: IP ADDRESS 192.168.11.1/24', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-07-24 13:14:21'),
(359, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:06:27'),
(360, 13, 'Dibuka akses pertemuan 2 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:13:32'),
(361, 13, 'Dibuka akses pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:13:43'),
(362, 13, 'Dikunci akses pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:13:50'),
(363, 13, 'Mengubah syarat unduh materi pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:15:50'),
(364, 13, 'Mengubah syarat unduh materi pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:15:52'),
(365, 13, 'Mengubah syarat unduh materi pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:15:54'),
(366, 13, 'Mengubah syarat unduh materi pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:15:55'),
(367, 13, 'Mengubah syarat unduh materi pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:15:57'),
(368, 13, 'Mengubah syarat unduh materi pertemuan 3 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:15:58'),
(369, 13, 'Dikunci akses pertemuan 3 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:17:25'),
(370, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:22:18'),
(371, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:22:24'),
(372, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:22:39'),
(373, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:22:49'),
(374, 13, 'Dikunci akses pertemuan 5 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:23:02'),
(375, 13, 'Dibuka akses pertemuan 3 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:23:10'),
(376, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:23:13'),
(377, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:23:20'),
(378, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:25:06'),
(379, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:25:11'),
(380, 13, 'Mengubah tugas: Rangkum video tersebut ya anak-anak', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:25:53'),
(381, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:26:01'),
(382, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:26:06'),
(383, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:26:19'),
(384, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:26:24'),
(385, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:29:42'),
(386, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:29:46'),
(387, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:30:16'),
(388, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:30:21'),
(389, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:39:27'),
(390, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:39:33'),
(391, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:44:44'),
(392, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:44:52'),
(393, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:45:52'),
(394, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:45:57'),
(395, 13, 'Dikunci akses pertemuan 4 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:46:07'),
(396, 13, 'Dikunci akses pertemuan 1 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:46:18'),
(397, 13, 'Dikunci akses pertemuan 2 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:46:22'),
(398, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:46:27'),
(399, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:46:35'),
(400, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:47:02'),
(401, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:47:14'),
(402, 13, 'Dibuka akses pertemuan 1 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:47:23'),
(403, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:47:26'),
(404, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:47:34'),
(405, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:55:45'),
(406, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:55:55'),
(407, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:02:40'),
(408, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:02:49'),
(409, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:03:17'),
(410, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:03:24'),
(411, 13, 'Dikunci akses pertemuan 3 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:03:45'),
(412, 13, 'Dikunci akses pertemuan 1 pada pengajaran ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:03:49'),
(413, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:03:54'),
(414, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:04:00'),
(415, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:04:46'),
(416, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:04:52'),
(417, 13, 'Membuat modul pertemuan 1: Mikrotik lanjutan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:05:30'),
(418, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:06:00'),
(419, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:06:05'),
(420, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:06:10'),
(421, 12, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:06:19'),
(422, 12, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:06:22'),
(423, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:06:33'),
(424, 13, 'Dibuka akses pertemuan 1 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:10:47'),
(425, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:10:51'),
(426, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:10:56'),
(427, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:11:10'),
(428, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:11:15'),
(429, 13, 'Membuat modul pertemuan 2: Mikrotik Tahap 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:11:34'),
(430, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:11:54'),
(431, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:12:00'),
(432, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:12:13'),
(433, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:12:17'),
(434, 13, 'Membuat tugas: Silahkan salin ulang materi tersebut', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:14:43'),
(435, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:14:49'),
(436, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:14:54'),
(437, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:15:51'),
(438, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:15:56'),
(439, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:16:09'),
(440, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:16:13'),
(441, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:19:45'),
(442, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:19:51'),
(443, 13, 'Mengubah tugas: Silahkan salin ulang materi tersebut', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:20:04'),
(444, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:20:06'),
(445, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:20:10'),
(446, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:20:53'),
(447, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:20:58'),
(448, 13, 'Membuat modul pertemuan 4: Mikrotik WAN dan WLAN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:21:41'),
(449, 13, 'Dibuka akses pertemuan 4 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:21:48'),
(450, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:21:51'),
(451, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:21:55'),
(452, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:22:29'),
(453, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:22:35'),
(454, 13, 'Membuat tugas: Tugas Mikrotik WAN dan WLAN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:23:12'),
(455, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:23:17'),
(456, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:23:22'),
(457, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:29:57'),
(458, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:30:03'),
(459, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:34:58'),
(460, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:35:05'),
(461, 14, 'Mengirimkan jawaban tugas ID: 44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:36:19'),
(462, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:36:23'),
(463, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:36:28'),
(464, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:37:04'),
(465, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:37:09'),
(466, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:37:25'),
(467, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:37:29'),
(468, 13, 'Menilai tugas fatir: Silahkan salin ulang materi tersebut', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:38:54'),
(469, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:39:35'),
(470, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:39:40'),
(471, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:40:46'),
(472, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 16:40:56'),
(473, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 01:56:20'),
(474, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:02:05'),
(475, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:02:15'),
(476, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:02:20'),
(477, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:02:31'),
(478, 13, 'Membuka absensi pertemuan 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:02:57'),
(479, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:02:59'),
(480, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:03:03'),
(481, 14, 'Check-in absensi Dasar-Dasar Mikrotik pertemuan 8: Hadir', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:03:07'),
(482, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:03:51'),
(483, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:03:55'),
(484, 13, 'Mengubah tugas: kucing', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:09:13'),
(485, 13, 'Mengubah tugas: kucing', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 02:09:41'),
(486, 13, 'Membuat modul pertemuan 8: tes saja', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:12:59'),
(487, 13, 'Dibuka akses pertemuan 8 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:13:28'),
(488, 13, 'Dikunci akses pertemuan 8 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:13:33'),
(489, 13, 'Dibuka akses pertemuan 8 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:13:37'),
(490, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:13:40'),
(491, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:13:48'),
(492, 13, 'Membuat tugas: contoh saja qqqq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:17:41'),
(493, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:17:47'),
(494, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:17:52'),
(495, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:22:31'),
(496, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:22:37'),
(497, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:26:17'),
(498, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 06:26:23'),
(499, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 13:07:49'),
(500, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 13:07:56'),
(501, 1, '[OPERASIONAL ADMIN] Akses pertemuan diubah Dibuka -> Dikunci | Rofiq Okvianto, M.Pd | Dasar-Dasar Mikrotik | XI TKJ 1 | Pertemuan 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:08:51'),
(502, 1, '[OPERASIONAL ADMIN] Akses pertemuan diubah Dibuka -> Dikunci | Rofiq Okvianto, M.Pd | Dasar-Dasar Mikrotik | XI TKJ 1 | Pertemuan 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:09:12'),
(503, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:50:55'),
(504, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:50:59'),
(505, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:06:45'),
(506, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-07-25 15:06:52'),
(507, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:09:12'),
(508, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:09:17'),
(509, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:11:11'),
(510, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:11:16'),
(511, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:11:28'),
(512, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:11:34'),
(513, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:13:20'),
(514, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:14:07'),
(515, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:16:22'),
(516, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:17:05'),
(517, 1, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:31:42'),
(518, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 15:31:46'),
(519, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:19:08'),
(520, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:19:13'),
(521, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:20:07'),
(522, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:20:12'),
(523, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:20:39'),
(524, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:20:45'),
(525, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:29:24'),
(526, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:29:28'),
(527, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:30:39'),
(528, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:30:44'),
(529, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:31:19'),
(530, 13, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:31:24'),
(531, 13, 'Dibuka akses pertemuan 7 pada pengajaran ID 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:31:44'),
(532, 13, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:31:46'),
(533, 14, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:31:51'),
(534, 14, 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:43:05'),
(535, 1, 'Login Berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 16:49:21');

-- --------------------------------------------------------

--
-- Table structure for table `mapel`
--

CREATE TABLE `mapel` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode_mapel` varchar(20) NOT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `kelompok` varchar(50) DEFAULT 'Muatan Kejuruan',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mapel`
--

INSERT INTO `mapel` (`id`, `kode_mapel`, `nama_mapel`, `kelompok`, `created_at`) VALUES
(1, 'TKJ-01', 'Administrasi Infrastruktur Jaringan', 'Muatan Kejuruan', '2026-07-21 16:13:18'),
(2, 'MTK-01', 'Matematika', 'Muatan Nasional', '2026-07-21 16:13:18'),
(3, 'BING-01', 'Bahasa Inggris', 'Muatan Nasional', '2026-07-21 16:13:18'),
(4, 'TKJ6', 'Dasar-Dasar Mikrotik', 'Muatan Kejuruan', '2026-07-21 16:21:42'),
(6, 'PKK-1', 'Produk Kreatif dan Kewirausahaan', 'Muatan Nasional', '2026-07-23 16:13:18');

-- --------------------------------------------------------

--
-- Table structure for table `materi`
--

CREATE TABLE `materi` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `pertemuan_ke` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `materi`
--

INSERT INTO `materi` (`id`, `pengajaran_id`, `pertemuan_ke`, `judul`, `deskripsi`, `video_url`, `file_path`, `created_at`) VALUES
(1, 1, 1, 'contoh saja', 'faf', NULL, 'materi_1784732129_d980760e.pdf', '2026-07-22 14:55:29'),
(2, 14, 1, 'PKK day one', NULL, NULL, 'materi_9a074496d6b545f448822a2f.pdf', '2026-07-23 16:15:53'),
(3, 1, 2, 'kucing', NULL, NULL, 'materi_5434a8bbfe63860d247029db.docx', '2026-07-24 03:02:42'),
(4, 13, 5, 'tesssssssss', NULL, NULL, 'materi_03208a92a20169c5708e8f1f.pdf', '2026-07-24 06:21:42'),
(5, 1, 4, 'main voli', 'https://www.youtube.com', NULL, 'materi_eef147e84c84794724748078.docx', '2026-07-24 06:29:57'),
(6, 13, 3, 'materi video mikrotik', 'Silahkan ditonton sampai selesai', 'https://youtu.be/3jsI6h2EwGA?si=SyH0uX6tZ2mpzZtO', NULL, '2026-07-24 09:32:40'),
(7, 13, 7, 'IP ADDRESS 192.168.11.1/24', NULL, NULL, 'materi_34fad0b4f8904fc03ed91a97.docx', '2026-07-24 13:14:21'),
(8, 13, 1, 'Mikrotik lanjutan', NULL, NULL, 'materi_1c4fec158d35252e0317120e.docx', '2026-07-24 16:05:30'),
(9, 13, 2, 'Mikrotik Tahap 2', NULL, NULL, 'materi_934f91d79d9e9664914454cb.docx', '2026-07-24 16:11:34'),
(10, 13, 4, 'Mikrotik WAN dan WLAN', 'Materi merujuk kepada mikrotik', NULL, 'materi_baef57663cc9ad825b8ba20b.pdf', '2026-07-24 16:21:41'),
(11, 13, 8, 'tes saja', NULL, 'https://youtu.be/bS9eXS6VucU?si=sM8_FRDtFS5AOUve', 'materi_d9c11432e654c6e33600f616.pdf', '2026-07-25 06:12:59');

-- --------------------------------------------------------

--
-- Table structure for table `materi_siswa_dibaca`
--

CREATE TABLE `materi_siswa_dibaca` (
  `materi_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `dibaca_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `materi_siswa_dibaca`
--

INSERT INTO `materi_siswa_dibaca` (`materi_id`, `siswa_id`, `dibaca_pada`) VALUES
(1, 2, '2026-07-24 09:22:50'),
(2, 2, '2026-07-23 16:20:22'),
(3, 2, '2026-07-24 09:23:00'),
(4, 3, '2026-07-24 09:19:15'),
(5, 2, '2026-07-24 09:29:09'),
(6, 3, '2026-07-24 15:39:41'),
(7, 3, '2026-07-25 16:39:42'),
(8, 3, '2026-07-24 16:20:18'),
(9, 3, '2026-07-24 16:20:42'),
(10, 3, '2026-07-24 16:23:40'),
(11, 3, '2026-07-25 06:19:07');

-- --------------------------------------------------------

--
-- Table structure for table `materi_siswa_diunduh`
--

CREATE TABLE `materi_siswa_diunduh` (
  `materi_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `diunduh_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `materi_siswa_diunduh`
--

INSERT INTO `materi_siswa_diunduh` (`materi_id`, `siswa_id`, `diunduh_pada`) VALUES
(8, 3, '2026-07-24 23:15:17'),
(9, 3, '2026-07-24 23:20:40'),
(10, 3, '2026-07-24 23:23:39'),
(11, 3, '2026-07-25 13:18:45');

-- --------------------------------------------------------

--
-- Table structure for table `nilai_komponen`
--

CREATE TABLE `nilai_komponen` (
  `id` int(10) UNSIGNED NOT NULL,
  `komponen_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `nilai` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `nilai_komponen`
--

INSERT INTO `nilai_komponen` (`id`, `komponen_id`, `siswa_id`, `nilai`, `updated_at`) VALUES
(2, 6, 2, 0.00, '2026-07-23 14:41:43'),
(3, 7, 2, 0.00, '2026-07-23 04:01:40');

-- --------------------------------------------------------

--
-- Table structure for table `nilai_ujian`
--

CREATE TABLE `nilai_ujian` (
  `id` int(10) UNSIGNED NOT NULL,
  `ujian_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `nilai_pg` decimal(5,2) DEFAULT 0.00,
  `nilai_esai` decimal(5,2) DEFAULT 0.00,
  `nilai_total` decimal(5,2) DEFAULT 0.00,
  `selesai_pada` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `nilai_ujian`
--

INSERT INTO `nilai_ujian` (`id`, `ujian_id`, `siswa_id`, `nilai_pg`, `nilai_esai`, `nilai_total`, `selesai_pada`) VALUES
(1, 1, 2, 50.00, 0.00, 50.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `opsi_tugas`
--

CREATE TABLE `opsi_tugas` (
  `id` int(10) UNSIGNED NOT NULL,
  `pertanyaan_id` int(10) UNSIGNED NOT NULL,
  `label_opsi` char(1) NOT NULL,
  `teks_opsi` text NOT NULL,
  `is_benar` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengajaran`
--

CREATE TABLE `pengajaran` (
  `id` int(10) UNSIGNED NOT NULL,
  `guru_id` int(10) UNSIGNED NOT NULL,
  `mapel_id` int(10) UNSIGNED NOT NULL,
  `kelas_id` int(10) UNSIGNED NOT NULL,
  `tahun_ajaran` varchar(10) DEFAULT '2025/2026',
  `semester` enum('Ganjil','Genap') DEFAULT 'Genap',
  `kkm` decimal(5,2) UNSIGNED NOT NULL DEFAULT 75.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengajaran`
--

INSERT INTO `pengajaran` (`id`, `guru_id`, `mapel_id`, `kelas_id`, `tahun_ajaran`, `semester`, `kkm`, `created_at`) VALUES
(1, 3, 3, 1, '2026/2027', 'Ganjil', 75.00, '2026-07-22 14:54:08'),
(8, 1, 1, 1, '2026/2027', 'Ganjil', 75.00, '2026-07-22 16:04:56'),
(10, 4, 4, 2, '2026/2027', 'Ganjil', 75.00, '2026-07-22 16:07:26'),
(12, 2, 2, 1, '2026/2027', 'Ganjil', 75.00, '2026-07-22 16:12:56'),
(13, 3, 4, 2, '2026/2027', 'Ganjil', 75.00, '2026-07-23 03:05:01'),
(14, 5, 6, 1, '2026/2027', 'Ganjil', 75.00, '2026-07-23 16:13:18');

-- --------------------------------------------------------

--
-- Table structure for table `pengumpulan_tugas`
--

CREATE TABLE `pengumpulan_tugas` (
  `id` int(10) UNSIGNED NOT NULL,
  `tugas_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `file_tugas` varchar(255) DEFAULT NULL,
  `nama_file_asli` varchar(255) DEFAULT NULL,
  `ukuran_file` int(10) UNSIGNED DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `catatan_guru` text DEFAULT NULL,
  `dikumpulkan_pada` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengumpulan_tugas`
--

INSERT INTO `pengumpulan_tugas` (`id`, `tugas_id`, `siswa_id`, `file_tugas`, `nama_file_asli`, `ukuran_file`, `catatan`, `nilai`, `catatan_guru`, `dikumpulkan_pada`) VALUES
(2, 4, 2, 'jawaban_4_2_1784740045.docx', NULL, NULL, 'saya sudah ya pak', 80.00, 'kurang tepat ya jawaban nya', '2026-07-22 17:07:25'),
(4, 39, 3, 'jawaban_39_3_1784785622.pdf', NULL, NULL, NULL, 75.00, 'masih banyak yang salah', '2026-07-23 05:47:02'),
(5, 41, 2, 'jawaban_41_2_1784824309.docx', NULL, NULL, NULL, NULL, NULL, '2026-07-23 16:31:49'),
(6, 42, 2, 'jawaban_42_2_1784863216.docx', 'COVER MODUL.docx', 1052940, NULL, NULL, NULL, '2026-07-24 03:20:16'),
(7, 44, 3, 'jawaban_44_3_1784910979.pdf', 'materi_baef57663cc9ad825b8ba20b.pdf', 322024, NULL, 90.00, 'Ditingkatkan lagi jawaban nya ya', '2026-07-24 16:36:19');

-- --------------------------------------------------------

--
-- Table structure for table `pertanyaan_tugas`
--

CREATE TABLE `pertanyaan_tugas` (
  `id` int(10) UNSIGNED NOT NULL,
  `tugas_id` int(10) UNSIGNED NOT NULL,
  `pertanyaan` text NOT NULL,
  `urutan` smallint(5) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_nilai`
--

CREATE TABLE `riwayat_nilai` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `komponen_id` int(10) UNSIGNED NOT NULL,
  `guru_id` int(10) UNSIGNED NOT NULL,
  `nilai_lama` decimal(5,2) UNSIGNED DEFAULT NULL,
  `nilai_baru` decimal(5,2) UNSIGNED NOT NULL,
  `diubah_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `riwayat_nilai`
--

INSERT INTO `riwayat_nilai` (`id`, `pengajaran_id`, `siswa_id`, `komponen_id`, `guru_id`, `nilai_lama`, `nilai_baru`, `diubah_pada`) VALUES
(1, 1, 2, 6, 3, 0.00, 56.00, '2026-07-23 14:41:29'),
(2, 1, 2, 6, 3, 56.00, 0.00, '2026-07-23 14:41:43');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `nama_role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `nama_role`) VALUES
(1, 'Admin'),
(2, 'Guru'),
(3, 'Siswa');

-- --------------------------------------------------------

--
-- Table structure for table `sesi_absensi`
--

CREATE TABLE `sesi_absensi` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `pertemuan_ke` tinyint(3) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_buka` datetime NOT NULL,
  `waktu_tutup` datetime NOT NULL,
  `status` enum('Dibuka','Ditutup') NOT NULL DEFAULT 'Dibuka',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sesi_absensi`
--

INSERT INTO `sesi_absensi` (`id`, `pengajaran_id`, `pertemuan_ke`, `tanggal`, `waktu_buka`, `waktu_tutup`, `status`, `created_at`) VALUES
(1, 1, 1, '2026-07-22', '2026-07-22 22:09:42', '2026-07-22 22:39:42', 'Ditutup', '2026-07-22 15:05:01'),
(4, 14, 1, '2026-07-23', '2026-07-23 23:17:00', '2026-07-24 23:47:00', 'Ditutup', '2026-07-23 16:17:41'),
(5, 13, 3, '2026-07-23', '2026-07-23 23:34:00', '2026-07-24 00:04:00', 'Ditutup', '2026-07-23 16:35:13'),
(6, 1, 3, '2026-07-23', '2026-07-23 23:36:00', '2026-07-24 00:06:00', 'Ditutup', '2026-07-23 16:36:32'),
(9, 1, 4, '2026-07-24', '2026-07-24 09:59:00', '2026-07-24 10:30:00', 'Ditutup', '2026-07-24 02:59:45'),
(10, 13, 5, '2026-07-24', '2026-07-24 13:17:00', '2026-07-24 14:47:00', 'Ditutup', '2026-07-24 06:18:22'),
(12, 1, 6, '2026-07-24', '2026-07-24 13:31:00', '2026-07-24 14:01:00', 'Ditutup', '2026-07-24 06:31:37'),
(13, 13, 8, '2026-07-25', '2026-07-25 09:02:00', '2026-07-25 09:38:00', 'Ditutup', '2026-07-25 02:02:57');

-- --------------------------------------------------------

--
-- Table structure for table `sesi_ujian`
--

CREATE TABLE `sesi_ujian` (
  `id` int(10) UNSIGNED NOT NULL,
  `ujian_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `status` enum('Berlangsung','Selesai') NOT NULL DEFAULT 'Berlangsung'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sesi_ujian`
--

INSERT INTO `sesi_ujian` (`id`, `ujian_id`, `siswa_id`, `waktu_mulai`, `waktu_selesai`, `status`) VALUES
(1, 1, 2, '2026-07-23 10:31:21', '2026-07-23 10:49:31', 'Selesai');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `kelas_id` int(10) UNSIGNED DEFAULT NULL,
  `nis` varchar(20) DEFAULT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id`, `user_id`, `kelas_id`, `nis`, `nisn`, `nama_lengkap`, `email`, `created_at`) VALUES
(1, 10, 3, '12345', '434', 'Ari aja', 'tes@gmail.com', '2026-07-22 01:42:37'),
(2, 12, 1, NULL, '4341', 'Hisam', 'hisam1@gmail.com', '2026-07-22 13:39:27'),
(3, 14, 2, NULL, '43412', 'fatir', 'fatir1@gmail.com', '2026-07-22 14:42:28');

-- --------------------------------------------------------

--
-- Table structure for table `soal_ujian`
--

CREATE TABLE `soal_ujian` (
  `id` int(10) UNSIGNED NOT NULL,
  `ujian_id` int(10) UNSIGNED NOT NULL,
  `tipe_soal` enum('PG','ESAI') NOT NULL DEFAULT 'PG',
  `pertanyaan` text NOT NULL,
  `opsi_a` text DEFAULT NULL,
  `opsi_b` text DEFAULT NULL,
  `opsi_c` text DEFAULT NULL,
  `opsi_d` text DEFAULT NULL,
  `opsi_e` text DEFAULT NULL,
  `kunci_jawaban` enum('A','B','C','D','E') DEFAULT NULL,
  `bobot` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `soal_ujian`
--

INSERT INTO `soal_ujian` (`id`, `ujian_id`, `tipe_soal`, `pertanyaan`, `opsi_a`, `opsi_b`, `opsi_c`, `opsi_d`, `opsi_e`, `kunci_jawaban`, `bobot`) VALUES
(2, 1, 'PG', 'Ada berapa jurusan di SMK Jaya Buana?', '10', '9', '8', '7', '6', 'A', 1),
(3, 1, 'PG', 'Tahun Lahir SMK Jaya Buana kapan?', '2014', '2010', '2012', '1990', '2019', 'C', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tugas`
--

CREATE TABLE `tugas` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `pertemuan_ke` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `jenis_tugas` enum('portofolio','esai','pilihan_ganda','merangkum','video') NOT NULL DEFAULT 'portofolio',
  `deadline` datetime NOT NULL,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tugas`
--

INSERT INTO `tugas` (`id`, `pengajaran_id`, `pertemuan_ke`, `judul`, `deskripsi`, `jenis_tugas`, `deadline`, `file_lampiran`, `created_at`) VALUES
(3, 1, 1, 'contoh 2', 'tugas isi ya', 'portofolio', '2026-07-22 13:00:00', 'tugas_6e64c947700699139aa52a0f.docx', '2026-07-22 16:59:37'),
(4, 1, 1, 'contoh 3', 'silahkan isi', 'portofolio', '2026-07-23 01:04:00', 'tugas_b4bd0b6422b93271e123c539.docx', '2026-07-22 17:03:17'),
(21, 1, 1, 'Kerjakan halaman 32', 'Silahkan di isi menggunakan buku dicatat dengan tangan', 'portofolio', '2026-07-23 01:00:00', 'tugas_94698e80db08fc21f26ca7db.pdf', '2026-07-22 17:50:12'),
(38, 1, 2, 'Kerjakan halaman 50', 'Silahkan kerjakan dengan tertib ya.', 'portofolio', '2026-07-23 04:30:00', 'tugas_a7b4c670212541e7abe81e99.pdf', '2026-07-22 18:28:01'),
(39, 13, 2, 'Mikrotik', NULL, 'portofolio', '2026-07-24 12:44:00', 'tugas_5ea4cfa3fefa7eb0846ba263.pdf', '2026-07-23 05:44:46'),
(41, 14, 1, 'PKK tugas 1', NULL, 'portofolio', '2026-07-24 13:18:00', 'tugas_0e657f26feaf3a42d7dc6e5f.docx', '2026-07-23 16:18:17'),
(42, 1, 2, 'kucing', NULL, 'portofolio', '2026-07-24 11:05:00', 'tugas_dd007fa87f4f072e955b3872.docx', '2026-07-24 03:05:02'),
(43, 1, 6, 'Rangkum video tersebut ya anak-anak', NULL, 'portofolio', '2026-07-25 13:32:00', NULL, '2026-07-24 06:33:10'),
(44, 13, 1, 'Silahkan salin ulang materi tersebut', 'wajib dicatat dan difoto jawaban', 'portofolio', '2026-07-25 23:14:00', 'tugas_8b6715caa52a97aa0225a803.pdf', '2026-07-24 16:14:43'),
(45, 13, 4, 'Tugas Mikrotik WAN dan WLAN', 'Silahkan kerjakan dengan  sungguh2 tugas ini', 'portofolio', '2026-07-25 23:23:00', 'tugas_0b4626d0e4d0d4097a5c9723.pdf', '2026-07-24 16:23:12'),
(46, 13, 8, 'contoh saja qqqq', NULL, 'portofolio', '2026-07-26 13:17:00', 'tugas_d4218745711e04811af4ef60.pdf', '2026-07-25 06:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `ujian`
--

CREATE TABLE `ujian` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajaran_id` int(10) UNSIGNED NOT NULL,
  `nama_ujian` varchar(255) NOT NULL,
  `jenis_ujian` enum('Kuis','UTS','UAS') DEFAULT 'Kuis',
  `durasi_menit` int(10) UNSIGNED NOT NULL DEFAULT 60,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime NOT NULL,
  `acak_soal` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ujian`
--

INSERT INTO `ujian` (`id`, `pengajaran_id`, `nama_ujian`, `jenis_ujian`, `durasi_menit`, `waktu_mulai`, `waktu_selesai`, `acak_soal`, `created_at`) VALUES
(1, 1, 'Ujian TOEFL 1', 'Kuis', 60, '2026-07-23 10:20:00', '2026-07-23 11:20:00', 1, '2026-07-23 03:21:02'),
(3, 13, 'Ujian Mikrotik', 'Kuis', 60, '2026-07-23 21:06:00', '2026-07-23 22:06:00', 1, '2026-07-23 14:06:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$WjQRuvdGfeT3gy7M8pP3u.U1ye0jCBDbEGukWjrl2QsBeeHOue0wC', 'admin@smkjayabuana.sch.id', 1, 1, '2026-07-21 16:20:22', '2026-07-22 14:31:56'),
(9, '1234', '$2y$10$qmZKPNiqJvLQZKT24nsEeez4DD.KSAU8jGdNBVMQbzJaJtKM2Xq.6', 'asepsetiadi4781@gmail.com', 2, 1, '2026-07-22 01:36:11', '2026-07-22 16:06:47'),
(10, 'ari1', '$2y$10$MHIHpmGiryBUeXgT05T5Lu2Y8/z5/Pcv5ERl1Z.lwgHjQEYJj2Amm', 'tes@gmail.com', 3, 1, '2026-07-22 01:42:37', '2026-07-22 14:41:09'),
(11, 'ari12', '$2y$10$qmZKPNiqJvLQZKT24nsEeez4DD.KSAU8jGdNBVMQbzJaJtKM2Xq.6', 'Ari123@gmail.com', 2, 1, '2026-07-22 13:19:19', '2026-07-22 14:36:38'),
(12, 'hisam', '$2y$10$MHIHpmGiryBUeXgT05T5Lu2Y8/z5/Pcv5ERl1Z.lwgHjQEYJj2Amm', 'hisam1@gmail.com', 3, 1, '2026-07-22 13:39:27', '2026-07-22 17:57:32'),
(13, 'rofiq1', '$2y$10$uF6/mfHGUkY4iBDk6UKHT.yQ6ZGrmGub.cRcdl19MJgu6zqtsQA2C', 'rofiq1@gmail.com', 2, 1, '2026-07-22 14:39:29', '2026-07-22 18:42:16'),
(14, 'fatir1', '$2y$10$max4ZD3/6KSMViV/1JFMlextW0T3baOtV9vHNp/fXC6SjggzcGIUa', 'fatir1@gmail.com', 3, 1, '2026-07-22 14:42:28', '2026-07-22 14:42:28'),
(18, 'asep62', '$2y$10$wcT5DIyj20vEHDbfd5T9HeYDpQ.915nl7OkFw0hA0//37Hu.PJI3K', 'asepsetiadi478@gmail.com', 2, 1, '2026-07-22 16:07:10', '2026-07-22 16:07:10'),
(19, 'oka1', '$2y$10$bTiXoVTY2Q8NeJQQLg6KXuNDUI/1YN.3eHt444Qfvd2bnPXaJoHxW', 'okavitaloka1@gmail.com', 2, 1, '2026-07-23 16:11:23', '2026-07-23 16:11:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `akses_pertemuan`
--
ALTER TABLE `akses_pertemuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_akses_pertemuan` (`pengajaran_id`,`pertemuan_ke`);

--
-- Indexes for table `catatan_siswa_pengajaran`
--
ALTER TABLE `catatan_siswa_pengajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_catatan_pengajaran_siswa` (`pengajaran_id`,`siswa_id`),
  ADD KEY `fk_catatan_siswa` (`siswa_id`);

--
-- Indexes for table `detail_absensi`
--
ALTER TABLE `detail_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_detail_absensi_siswa` (`sesi_absensi_id`,`siswa_id`),
  ADD KEY `fk_detail_absensi_siswa` (`siswa_id`);

--
-- Indexes for table `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_guru_user` (`user_id`);

--
-- Indexes for table `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_jawaban` (`sesi_ujian_id`,`soal_id`),
  ADD KEY `fk_jawaban_soal` (`soal_id`);

--
-- Indexes for table `jawaban_tugas`
--
ALTER TABLE `jawaban_tugas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_jawaban_tugas` (`pengumpulan_id`,`pertanyaan_id`),
  ADD KEY `idx_jawaban_pertanyaan` (`pertanyaan_id`),
  ADD KEY `idx_jawaban_opsi` (`opsi_id`);

--
-- Indexes for table `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_kelas_nama` (`nama_kelas`);

--
-- Indexes for table `komponen_penilaian`
--
ALTER TABLE `komponen_penilaian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_komponen_pengajaran` (`pengajaran_id`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_user` (`user_id`);

--
-- Indexes for table `mapel`
--
ALTER TABLE `mapel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mapel_kode` (`kode_mapel`);

--
-- Indexes for table `materi`
--
ALTER TABLE `materi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_materi_pertemuan` (`pengajaran_id`,`pertemuan_ke`),
  ADD KEY `fk_materi_pengajaran` (`pengajaran_id`);

--
-- Indexes for table `materi_siswa_dibaca`
--
ALTER TABLE `materi_siswa_dibaca`
  ADD PRIMARY KEY (`materi_id`,`siswa_id`),
  ADD KEY `idx_materi_dibaca_siswa` (`siswa_id`);

--
-- Indexes for table `materi_siswa_diunduh`
--
ALTER TABLE `materi_siswa_diunduh`
  ADD PRIMARY KEY (`materi_id`,`siswa_id`),
  ADD KEY `idx_materi_diunduh_siswa` (`siswa_id`);

--
-- Indexes for table `nilai_komponen`
--
ALTER TABLE `nilai_komponen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nilai_komponen_siswa` (`komponen_id`,`siswa_id`),
  ADD KEY `idx_nilai_komponen_siswa` (`siswa_id`);

--
-- Indexes for table `nilai_ujian`
--
ALTER TABLE `nilai_ujian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nilai_ujian` (`ujian_id`,`siswa_id`),
  ADD KEY `fk_nilai_siswa` (`siswa_id`);

--
-- Indexes for table `opsi_tugas`
--
ALTER TABLE `opsi_tugas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_opsi_tugas` (`pertanyaan_id`,`label_opsi`);

--
-- Indexes for table `pengajaran`
--
ALTER TABLE `pengajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pengajaran` (`guru_id`,`mapel_id`,`kelas_id`,`tahun_ajaran`,`semester`),
  ADD KEY `fk_pengajaran_mapel` (`mapel_id`),
  ADD KEY `fk_pengajaran_kelas` (`kelas_id`);

--
-- Indexes for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pengumpulan` (`tugas_id`,`siswa_id`),
  ADD KEY `fk_pengumpulan_siswa` (`siswa_id`);

--
-- Indexes for table `pertanyaan_tugas`
--
ALTER TABLE `pertanyaan_tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pertanyaan_tugas` (`tugas_id`,`urutan`);

--
-- Indexes for table `riwayat_nilai`
--
ALTER TABLE `riwayat_nilai`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_riwayat_pengajaran_siswa` (`pengajaran_id`,`siswa_id`,`diubah_pada`),
  ADD KEY `fk_riwayat_siswa` (`siswa_id`),
  ADD KEY `fk_riwayat_komponen` (`komponen_id`),
  ADD KEY `fk_riwayat_guru` (`guru_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sesi_absensi_pertemuan` (`pengajaran_id`,`pertemuan_ke`);

--
-- Indexes for table `sesi_ujian`
--
ALTER TABLE `sesi_ujian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sesi_ujian` (`ujian_id`,`siswa_id`),
  ADD KEY `fk_sesi_siswa` (`siswa_id`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_siswa_user` (`user_id`),
  ADD UNIQUE KEY `uk_siswa_nis` (`nis`),
  ADD KEY `fk_siswa_kelas` (`kelas_id`);

--
-- Indexes for table `soal_ujian`
--
ALTER TABLE `soal_ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_soal_ujian` (`ujian_id`);

--
-- Indexes for table `tugas`
--
ALTER TABLE `tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tugas_pengajaran` (`pengajaran_id`);

--
-- Indexes for table `ujian`
--
ALTER TABLE `ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ujian_pengajaran` (`pengajaran_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_username` (`username`),
  ADD UNIQUE KEY `uk_users_email` (`email`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `akses_pertemuan`
--
ALTER TABLE `akses_pertemuan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `catatan_siswa_pengajaran`
--
ALTER TABLE `catatan_siswa_pengajaran`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `detail_absensi`
--
ALTER TABLE `detail_absensi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `jawaban_tugas`
--
ALTER TABLE `jawaban_tugas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `komponen_penilaian`
--
ALTER TABLE `komponen_penilaian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=536;

--
-- AUTO_INCREMENT for table `mapel`
--
ALTER TABLE `mapel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `materi`
--
ALTER TABLE `materi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `nilai_komponen`
--
ALTER TABLE `nilai_komponen`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `nilai_ujian`
--
ALTER TABLE `nilai_ujian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `opsi_tugas`
--
ALTER TABLE `opsi_tugas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pengajaran`
--
ALTER TABLE `pengajaran`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `pertanyaan_tugas`
--
ALTER TABLE `pertanyaan_tugas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `riwayat_nilai`
--
ALTER TABLE `riwayat_nilai`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sesi_ujian`
--
ALTER TABLE `sesi_ujian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `soal_ujian`
--
ALTER TABLE `soal_ujian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tugas`
--
ALTER TABLE `tugas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `ujian`
--
ALTER TABLE `ujian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `akses_pertemuan`
--
ALTER TABLE `akses_pertemuan`
  ADD CONSTRAINT `fk_akses_pertemuan_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `catatan_siswa_pengajaran`
--
ALTER TABLE `catatan_siswa_pengajaran`
  ADD CONSTRAINT `fk_catatan_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_catatan_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `detail_absensi`
--
ALTER TABLE `detail_absensi`
  ADD CONSTRAINT `fk_detail_absensi_sesi` FOREIGN KEY (`sesi_absensi_id`) REFERENCES `sesi_absensi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detail_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `guru`
--
ALTER TABLE `guru`
  ADD CONSTRAINT `fk_guru_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD CONSTRAINT `fk_jawaban_sesi` FOREIGN KEY (`sesi_ujian_id`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jawaban_soal` FOREIGN KEY (`soal_id`) REFERENCES `soal_ujian` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jawaban_tugas`
--
ALTER TABLE `jawaban_tugas`
  ADD CONSTRAINT `fk_jawaban_opsi` FOREIGN KEY (`opsi_id`) REFERENCES `opsi_tugas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_jawaban_pengumpulan` FOREIGN KEY (`pengumpulan_id`) REFERENCES `pengumpulan_tugas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jawaban_pertanyaan` FOREIGN KEY (`pertanyaan_id`) REFERENCES `pertanyaan_tugas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `komponen_penilaian`
--
ALTER TABLE `komponen_penilaian`
  ADD CONSTRAINT `fk_komponen_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `materi`
--
ALTER TABLE `materi`
  ADD CONSTRAINT `fk_materi_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `materi_siswa_dibaca`
--
ALTER TABLE `materi_siswa_dibaca`
  ADD CONSTRAINT `fk_materi_dibaca_materi` FOREIGN KEY (`materi_id`) REFERENCES `materi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materi_dibaca_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `materi_siswa_diunduh`
--
ALTER TABLE `materi_siswa_diunduh`
  ADD CONSTRAINT `fk_materi_diunduh_materi` FOREIGN KEY (`materi_id`) REFERENCES `materi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materi_diunduh_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nilai_komponen`
--
ALTER TABLE `nilai_komponen`
  ADD CONSTRAINT `fk_nilai_komponen` FOREIGN KEY (`komponen_id`) REFERENCES `komponen_penilaian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nilai_komponen_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nilai_ujian`
--
ALTER TABLE `nilai_ujian`
  ADD CONSTRAINT `fk_nilai_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nilai_ujian` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `opsi_tugas`
--
ALTER TABLE `opsi_tugas`
  ADD CONSTRAINT `fk_opsi_tugas` FOREIGN KEY (`pertanyaan_id`) REFERENCES `pertanyaan_tugas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pengajaran`
--
ALTER TABLE `pengajaran`
  ADD CONSTRAINT `fk_pengajaran_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pengajaran_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pengajaran_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `mapel` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD CONSTRAINT `fk_pengumpulan_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pengumpulan_tugas` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pertanyaan_tugas`
--
ALTER TABLE `pertanyaan_tugas`
  ADD CONSTRAINT `fk_pertanyaan_tugas` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `riwayat_nilai`
--
ALTER TABLE `riwayat_nilai`
  ADD CONSTRAINT `fk_riwayat_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_riwayat_komponen` FOREIGN KEY (`komponen_id`) REFERENCES `komponen_penilaian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_riwayat_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_riwayat_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  ADD CONSTRAINT `fk_sesi_absensi_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sesi_ujian`
--
ALTER TABLE `sesi_ujian`
  ADD CONSTRAINT `fk_sesi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sesi_ujian` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_siswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `soal_ujian`
--
ALTER TABLE `soal_ujian`
  ADD CONSTRAINT `fk_soal_ujian` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tugas`
--
ALTER TABLE `tugas`
  ADD CONSTRAINT `fk_tugas_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ujian`
--
ALTER TABLE `ujian`
  ADD CONSTRAINT `fk_ujian_pengajaran` FOREIGN KEY (`pengajaran_id`) REFERENCES `pengajaran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
