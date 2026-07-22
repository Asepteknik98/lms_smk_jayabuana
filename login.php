<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'config/helper.php';

// 1. Generate CSRF token jika belum ada di Session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Jika user SUDAH LOGIN, cegah mereka buka halaman login lagi (Auto Redirect)
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $base_url = '/lms';
    switch ((int)$_SESSION['role_id']) {
        case 1:
            header("Location: " . $base_url . "/admin/index.php");
            exit();
        case 2:
            header("Location: " . $base_url . "/guru/index.php");
            exit();
        case 3:
            header("Location: " . $base_url . "/siswa/index.php");
            exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!verify_csrf($csrf_token)) {
        $error = "Token Keamanan Tidak Valid! Silakan refresh halaman.";
    } elseif (empty($username) || empty($password)) {
        $error = "Username dan Password wajib diisi!";
    } else {
        try {
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                SELECT u.*, COALESCE(r.nama_role, 'Admin') as nama_role 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password'])) {
                    
                    // Regenerasi Session ID untuk keamanan
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role_id']   = $user['role_id'];
                    $_SESSION['role_nama'] = $user['nama_role'];

                    if (function_exists('catat_log')) {
                        catat_log($user['id'], "Login Berhasil");
                    }

                    // Absolute Redirect sesuai Role
                    $base_url = '/lms';
                    switch ((int)$user['role_id']) {
                        case 1:
                            header("Location: " . $base_url . "/admin/index.php");
                            break;
                        case 2:
                            header("Location: " . $base_url . "/guru/index.php");
                            break;
                        case 3:
                            header("Location: " . $base_url . "/siswa/index.php");
                            break;
                        default:
                            session_unset();
                            session_destroy();
                            header("Location: " . $base_url . "/login.php?error=unauthorized");
                            break;
                    }
                    exit();
            } else {
                // Pesan dibuat generik agar status username/akun tidak dapat ditebak.
                $error = "Username atau password tidak valid, atau akun tidak aktif.";
            }
        } catch (Exception $e) {
            error_log('Login gagal karena kesalahan sistem: ' . $e->getMessage());
            $error = "Terjadi gangguan pada sistem. Silakan coba kembali beberapa saat lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LMS SMK Jaya Buana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" type="image/png" href="assets/img/jb.png">
    <style>
        html, body {
            min-height: 100%;
        }

        body {
            background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.65)), 
                        url('assets/img/fotojb.jpeg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            padding: clamp(16px, 4vw, 32px);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card-login {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 380px;
            overflow: hidden;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }

        .logo-img {
            width: clamp(76px, 20vw, 96px);
            height: clamp(76px, 20vw, 96px);
            object-fit: contain;
            filter: drop-shadow(0px 2px 4px rgba(0, 0, 0, 0.2));
        }

        .login-title {
            font-size: clamp(1.05rem, 3.5vw, 1.25rem);
        }

        .btn-primary-custom {
            background: #0d6efd;
            border: none;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.4);
        }

        .input-group-text {
            background-color: rgba(255, 255, 255, 0.9);
            border-right: none;
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.9);
            border-left: none;
        }

        .form-control:focus {
            background-color: #ffffff;
            box-shadow: none;
        }

        .input-group-text,
        .form-control {
            min-height: 42px;
        }

        .password-help {
            color: #64748b;
            font-size: 0.79rem;
            line-height: 1.45;
        }

        @media (max-width: 575.98px) {
            body {
                background-attachment: scroll;
                padding: 12px;
            }

            .card-login {
                max-width: 360px;
                padding: 1.25rem !important;
                border-radius: 14px;
            }

            .logo-img {
                width: 76px;
                height: 76px;
            }

            .card-login .text-center.mb-4 {
                margin-bottom: 1rem !important;
            }

            .card-login .form-label {
                margin-bottom: 0.35rem;
                font-size: 0.88rem;
            }

            .input-group-text,
            .form-control {
                min-height: 46px;
            }

            .form-control {
                font-size: 16px;
            }

            .btn-primary-custom {
                min-height: 46px;
            }
        }

        @media (max-height: 700px) {
            body {
                align-items: flex-start;
            }

            .logo-img {
                width: 72px;
                height: 72px;
            }
        }

        @media (max-width: 359.98px) {
            body {
                padding: 8px;
            }

            .card-login {
                padding: 1rem !important;
            }

            .logo-img {
                width: 64px;
                height: 64px;
            }
        }
    </style>
</head>
<body>

<main class="card card-login p-4">
    <div class="logo-container">
        <img src="assets/img/jb.png" alt="Logo SMK Jaya Buana" class="logo-img">
    </div>

    <div class="text-center mb-4">
        <h1 class="login-title fw-bold text-dark mb-1">LMS SMKS JAYA BUANA</h1>
        <p class="text-muted small fw-semibold">Silakan masuk menggunakan akun Anda</p>
    </div>

    <?php if (!empty($error)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Gagal Login',
                text: <?= json_encode($error, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                confirmButtonColor: '#0d6efd'
            });
        </script>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        
        <div class="mb-3">
            <label class="form-label fw-semibold text-dark">Username / NIS</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-user text-primary"></i></span>
                <input type="text" name="username" class="form-control" required placeholder="Masukkan Username" autocomplete="off" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold text-dark">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock text-primary"></i></span>
                <input type="password" name="password" class="form-control" required placeholder="Masukkan Password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-primary-custom w-100 rounded-3">
            <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk Sekarang
        </button>

        <p class="password-help text-center mb-0 mt-3">
            <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>
            Jika lupa kata sandi, hubungi wali kelas atau admin sekolah.
        </p>
    </form>

    <div class="text-center mt-4">
        <small class="text-dark fw-semibold">&copy; 2026 SMKS Jaya Buana</small>
    </div>
</main>

</body>
</html>
