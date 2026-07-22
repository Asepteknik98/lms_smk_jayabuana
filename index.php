<?php
require_once 'config/session.php';
require_once 'config/helper.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

switch ($_SESSION['role_id']) {
    case 1: redirect('admin/index.php'); break;
    case 2: redirect('guru/index.php'); break;
    case 3: redirect('siswa/index.php'); break;
    default: redirect('login.php'); break;
}