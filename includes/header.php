<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

// Tentukan hak akses berdasarkan folder halaman yang sedang dibuka.
// Dengan demikian layout yang sama aman digunakan Admin, Guru, dan Siswa.
$script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$role_per_folder = [
    '/admin/' => [1],
    '/guru/'  => [2],
    '/siswa/' => [3],
];

$allowed_roles = [];
foreach ($role_per_folder as $folder => $roles) {
    if (strpos($script_path, $folder) !== false) {
        $allowed_roles = $roles;
        break;
    }
}

check_access($allowed_roles);
$needs_datatables = preg_match('~/(admin/(users|monitoring|pengajaran)|guru/materi)\.php$~',$script_path)===1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_SESSION['role_nama'] ?? 'Pengguna', ENT_QUOTES, 'UTF-8') ?> - LMS SMK Jaya Buana</title>
    <link rel="icon" type="image/png" href="../assets/img/jb-mobile.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 CSS -->
    <?php if($needs_datatables): ?><link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet"><?php endif; ?>
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root { --sidebar-width: 250px; }
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        #wrapper { display: flex; width: 100%; height: 100vh; height:100dvh; overflow-x: hidden; }
        #sidebar-wrapper { width: var(--sidebar-width); background: #1e293b; color: #fff; flex-shrink: 0; transition: all 0.3s; }
        #sidebar-wrapper .sidebar-heading { padding: 1.25rem 1.5rem; font-size: 1.2rem; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); }
        #sidebar-wrapper .sidebar-school-logo { width: 46px; height: 46px; padding: 4px; border-radius: 12px; background: #fff; box-shadow: 0 5px 14px rgba(0,0,0,0.22); flex: 0 0 46px; display: grid; place-items: center; overflow: hidden; }
        #sidebar-wrapper .sidebar-school-logo img { display: block; width: 100%; height: 100%; object-fit: contain; }
        #sidebar-wrapper .sidebar-school-name { font-size: 0.95rem; line-height: 1.3; letter-spacing: 0.035em; }
        #sidebar-wrapper .list-group-item { background: transparent; color: #94a3b8; border: none; padding: 0.8rem 1.5rem; font-weight: 500; }
        #sidebar-wrapper .list-group-item:hover, #sidebar-wrapper .list-group-item.active { background: #0f172a; color: #38bdf8; border-left: 4px solid #38bdf8; }
        #page-content-wrapper { flex-grow: 1; overflow-y: auto; }
        .top-navbar { background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-stat { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.04); }
    </style>
</head>
<body>
<div id="wrapper">
