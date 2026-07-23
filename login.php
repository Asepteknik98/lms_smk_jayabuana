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
    <link rel="icon" type="image/png" href="assets/img/jb-mobile.png">
    <style>
        :root{--navy:#0b1f3a;--blue:#1769e0;--cyan:#37b8f1;--ink:#162033;--muted:#718096}
        *{box-sizing:border-box}html,body{min-height:100%}body{margin:0;min-height:100vh;min-height:100dvh;background:#eef3f9;font-family:Inter,"Segoe UI",sans-serif;color:var(--ink)}
        .login-shell{min-height:100vh;min-height:100dvh;display:grid;grid-template-columns:minmax(0,1.18fr) minmax(430px,.82fr)}
        .school-visual{position:relative;isolation:isolate;display:flex;align-items:flex-end;overflow:hidden;padding:clamp(32px,5vw,72px);background:url("assets/img/fotojb.jpeg") center/cover no-repeat;color:#fff}
        .school-visual::before{content:"";position:absolute;inset:0;z-index:-2;background:linear-gradient(180deg,rgba(5,18,38,.12) 10%,rgba(5,20,44,.35) 48%,rgba(5,19,41,.94) 100%)}
        .school-visual::after{content:"";position:absolute;z-index:-1;width:440px;height:440px;border:1px solid rgba(255,255,255,.13);border-radius:50%;left:-180px;bottom:-220px;box-shadow:0 0 0 70px rgba(255,255,255,.025),0 0 0 140px rgba(255,255,255,.018)}
        .visual-content{max-width:650px}.visual-badge{display:inline-flex;align-items:center;gap:9px;padding:8px 13px;border:1px solid rgba(255,255,255,.22);border-radius:999px;background:rgba(9,30,62,.38);backdrop-filter:blur(8px);font-size:.78rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        .visual-title{margin:22px 0 14px;font-size:clamp(2.15rem,4vw,4.2rem);font-weight:800;line-height:1.06;letter-spacing:-.04em}.visual-title span{color:#7dd3fc}.visual-copy{max-width:570px;margin:0;color:rgba(255,255,255,.78);font-size:clamp(.95rem,1.3vw,1.08rem);line-height:1.75}
        .visual-points{display:flex;flex-wrap:wrap;gap:18px 28px;margin-top:30px}.visual-point{display:flex;align-items:center;gap:9px;color:rgba(255,255,255,.9);font-size:.87rem;font-weight:650}.visual-point i{display:grid;place-items:center;width:28px;height:28px;border-radius:9px;background:rgba(56,189,248,.18);color:#7dd3fc}
        .login-panel{position:relative;display:flex;align-items:center;justify-content:center;padding:clamp(28px,5vw,72px);background:#fff}.login-panel::before{content:"";position:absolute;top:0;right:0;width:190px;height:190px;background:radial-gradient(circle at top right,rgba(55,184,241,.13),transparent 68%)}
        .login-card{position:relative;width:100%;max-width:430px}.brand-row{display:flex;align-items:center;gap:14px;margin-bottom:44px}.logo-wrap{width:66px;height:66px;display:grid;place-items:center;border:1px solid #e7edf5;border-radius:19px;background:#fff;box-shadow:0 10px 30px rgba(17,50,93,.1)}.logo-img{width:53px;height:53px;object-fit:contain}.brand-name{font-size:.92rem;font-weight:800;line-height:1.25;letter-spacing:.02em}.brand-subtitle{margin-top:3px;color:var(--muted);font-size:.75rem}
        .eyebrow{margin-bottom:8px;color:var(--blue);font-size:.76rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.login-title{margin:0;font-size:clamp(1.8rem,3vw,2.25rem);font-weight:800;letter-spacing:-.035em}.login-lead{margin:10px 0 32px;color:var(--muted);font-size:.94rem}
        .form-label{margin-bottom:8px;color:#2c3a50;font-size:.83rem;font-weight:750}.field-wrap{position:relative}.field-icon{position:absolute;z-index:2;left:17px;top:50%;transform:translateY(-50%);color:#8b9bb1}.login-input{height:54px;padding:0 48px;border:1.5px solid #dfe7f1;border-radius:14px;background:#f8fafc;font-size:.94rem;transition:.2s}.login-input::placeholder{color:#a4afbe}.login-input:focus{border-color:#54a5ef;background:#fff;box-shadow:0 0 0 4px rgba(23,105,224,.1)}.password-toggle{position:absolute;z-index:3;right:8px;top:50%;transform:translateY(-50%);width:38px;height:38px;border:0;border-radius:10px;background:transparent;color:#8795a8}.password-toggle:hover{background:#edf4fc;color:var(--blue)}
        .btn-login{height:54px;border:0;border-radius:14px;background:linear-gradient(135deg,#1769e0,#0754bd);box-shadow:0 12px 24px rgba(23,105,224,.24);font-weight:750;transition:.2s}.btn-login:hover,.btn-login:focus{background:linear-gradient(135deg,#0f61d5,#064aa8);transform:translateY(-1px);box-shadow:0 15px 28px rgba(23,105,224,.3)}.btn-login:disabled{transform:none}
        .password-help{margin:18px 0 0;color:#7b899c;font-size:.78rem;line-height:1.55;text-align:center}.login-footer{display:flex;justify-content:space-between;gap:12px;margin-top:42px;padding-top:20px;border-top:1px solid #edf1f6;color:#8b97a7;font-size:.72rem}
        @media(max-width:991.98px){.login-shell{display:block;position:relative;padding:24px;background:linear-gradient(rgba(5,19,41,.65),rgba(5,19,41,.78)),url("assets/img/fotojb.jpeg") center/cover fixed}.school-visual{display:none}.login-panel{min-height:calc(100vh - 48px);min-height:calc(100dvh - 48px);padding:34px;border-radius:26px;background:rgba(255,255,255,.96);box-shadow:0 28px 70px rgba(0,0,0,.28);backdrop-filter:blur(14px)}.brand-row{margin-bottom:34px}}
        @media(max-width:575.98px){.login-shell{padding:0;background:#fff}.login-panel{min-height:100vh;min-height:100dvh;padding:24px 20px;border-radius:0;align-items:flex-start}.login-card{padding-top:12px}.brand-row{margin-bottom:30px}.logo-wrap{width:58px;height:58px;border-radius:17px}.logo-img{width:47px;height:47px}.login-title{font-size:1.75rem}.login-lead{margin-bottom:27px}.login-input,.btn-login{height:52px}.login-input{font-size:16px}.login-footer{margin-top:32px;flex-direction:column;text-align:center;gap:5px}}
        @media(max-height:680px) and (min-width:992px){.brand-row{margin-bottom:24px}.login-lead{margin-bottom:20px}.login-footer{margin-top:24px}.school-visual{padding-top:30px;padding-bottom:30px}}
        @media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
    </style>
</head>
<body>

<main class="login-shell">
    <section class="school-visual" aria-label="Lingkungan SMKS Jaya Buana">
        <div class="visual-content">
            <span class="visual-badge"><i class="fa-solid fa-graduation-cap"></i> Learning Management System</span>
            <h2 class="visual-title">Memberikan Kepastian<br><span>di Masa Depan.</span></h2>
            <div class="visual-points">
                <span class="visual-point"><i class="fa-solid fa-book-open"></i> Materi Terpadu</span>
                <span class="visual-point"><i class="fa-solid fa-chart-line"></i> Progres Terukur</span>
                <span class="visual-point"><i class="fa-solid fa-shield-halved"></i> Akses Aman</span>
            </div>
        </div>
    </section>
    <section class="login-panel">
      <div class="login-card">
        <div class="brand-row">
            <span class="logo-wrap"><img src="assets/img/jb-mobile.png" alt="Logo SMKS Jaya Buana" class="logo-img"></span>
            <div><div class="brand-name">SMKS JAYA BUANA</div><div class="brand-subtitle">MUDA, MANDIRI, MAJU</div></div>
        </div>
        <div class="eyebrow">Selamat Datang</div>
        <h1 class="login-title">Masuk ke akun Anda</h1>
        <p class="login-lead">Gunakan akun sekolah untuk melanjutkan ke LMS.</p>

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

    <form action="login.php" method="POST" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        
        <div class="mb-3">
            <label class="form-label" for="username">Username / NIS</label>
            <div class="field-wrap">
                <i class="fa-regular fa-user field-icon" aria-hidden="true"></i>
                <input id="username" type="text" name="username" class="form-control login-input" required placeholder="Masukkan username atau NIS" autocomplete="username" autofocus value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="password">Kata Sandi</label>
            <div class="field-wrap">
                <i class="fa-solid fa-lock field-icon" aria-hidden="true"></i>
                <input id="password" type="password" name="password" class="form-control login-input" required placeholder="Masukkan kata sandi" autocomplete="current-password">
                <button class="password-toggle" type="button" id="passwordToggle" aria-label="Tampilkan kata sandi" aria-pressed="false"><i class="fa-regular fa-eye"></i></button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login w-100" id="loginButton">
            <span class="button-label"><i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Masuk ke LMS</span>
        </button>

        <p class="password-help text-center mb-0 mt-3">
            <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>
            Lupa kata sandi? Hubungi wali kelas atau administrator sekolah.
        </p>
    </form>

        <footer class="login-footer"><span>&copy; 2026 SMKS Jaya Buana</span><span>Sistem Pembelajaran Digital</span></footer>
      </div>
    </section>
</main>
<script>
const password=document.getElementById('password'),toggle=document.getElementById('passwordToggle');
toggle.addEventListener('click',()=>{const visible=password.type==='text';password.type=visible?'password':'text';toggle.setAttribute('aria-pressed',String(!visible));toggle.setAttribute('aria-label',visible?'Tampilkan kata sandi':'Sembunyikan kata sandi');toggle.innerHTML=`<i class="fa-regular ${visible?'fa-eye':'fa-eye-slash'}"></i>`;password.focus()});
document.getElementById('loginForm').addEventListener('submit',function(){const button=document.getElementById('loginButton');button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Memverifikasi akun...'});
</script>
</body>
</html>
