<?php
require_once __DIR__ . '/database.php';

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function catat_log($user_id, $aktivitas) {
    $db = Database::getInstance();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    
    $stmt = $db->prepare("INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $aktivitas, $ip, $agent]);
}