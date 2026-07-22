<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

header('Content-Type: application/json');
check_access([1]);

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF Token']);
        exit();
    }

    // CREATE USER
    if ($action === 'create') {
        $username = sanitize($_POST['username'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id  = intval($_POST['role_id'] ?? 0);

        if (empty($username) || empty($email) || empty($password) || $role_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi!']);
            exit();
        }

        // Cek duplicate
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        if ($chk->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Username atau Email sudah terdaftar!']);
            exit();
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, role_id, is_active) VALUES (?, ?, ?, ?, 1)");
        
        if ($stmt->execute([$username, $hash, $email, $role_id])) {
            catat_log($_SESSION['user_id'], "Menambahkan user baru: $username");
            echo json_encode(['status' => 'success', 'message' => 'User berhasil ditambahkan']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
        }
        exit();
    }

    // UPDATE USER
    if ($action === 'update') {
        $id       = intval($_POST['id'] ?? 0);
        $email    = sanitize($_POST['email'] ?? '');
        $role_id  = intval($_POST['role_id'] ?? 0);
        $is_active= intval($_POST['is_active'] ?? 1);
        $password = $_POST['password'] ?? '';

        if ($id <= 0 || empty($email) || $role_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Data input tidak valid']);
            exit();
        }

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET email = ?, role_id = ?, is_active = ?, password = ? WHERE id = ?");
            $stmt->execute([$email, $role_id, $is_active, $hash, $id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET email = ?, role_id = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$email, $role_id, $is_active, $id]);
        }

        catat_log($_SESSION['user_id'], "Memperbarui data user ID: $id");
        echo json_encode(['status' => 'success', 'message' => 'User berhasil diperbarui']);
        exit();
    }

    // DELETE USER
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id === $_SESSION['user_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak dapat menghapus akun sendiri!']);
            exit();
        }

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            catat_log($_SESSION['user_id'], "Menghapus user ID: $id");
            echo json_encode(['status' => 'success', 'message' => 'User berhasil dihapus']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus user']);
        }
        exit();
    }
}

// READ SINGLE USER DATA (GET)
if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT id, username, email, role_id, is_active FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit();
}