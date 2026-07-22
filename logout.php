<?php
require_once 'config/session.php';
require_once 'config/helper.php';

if (isset($_SESSION['user_id'])) {
    catat_log($_SESSION['user_id'], "Logout");
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

redirect('login.php');