<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helper.php';

if (!function_exists('check_access')) {
    function check_access($allowed_roles = []) {
        $base_url = '/lms';

        // 1. Cek Login
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            header("Location: " . $base_url . "/login.php");
            exit();
        }

        $user_role = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
        $allowed_roles_int = array_map('intval', $allowed_roles);

        // 2. Jika role user tidak sesuai dengan halaman yang diakses
        if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles_int, true)) {
            
            // Hancurkan session yang salah agar tidak looping terus-menerus
            session_unset();
            session_destroy();
            
            // Lempar kembali ke login
            header("Location: " . $base_url . "/login.php?error=unauthorized");
            exit();
        }
    }
}