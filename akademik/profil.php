<?php
require_once __DIR__.'/../config/auth.php';
check_akademik_access();
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);exit;}
$q=Database::getInstance()->prepare('SELECT u.username,u.is_active,a.nama_lengkap FROM users u LEFT JOIN akademik_profil a ON a.user_id=u.id WHERE u.id=?');$q->execute([$_SESSION['user_id']]);$profile=$q->fetch();
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/akademik-profile.css?v=2">
<div id="page-content-wrapper" class="academic-profile-page">
<nav class="navbar top-navbar px-4 py-3"><div><h1 class="h5 fw-bold mb-1">Profil Saya</h1><span class="profile-subtitle">Identitas akun akademik Anda</span></div><a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Dashboard</a></nav>
<main class="container-fluid p-3 p-md-4">
<section class="academic-profile-card" aria-labelledby="profileName">
<div class="profile-banner"><span><i class="fa-solid fa-graduation-cap me-2" aria-hidden="true"></i>SMKS Jaya Buana</span><span class="profile-banner-label">AKADEMIK</span></div>
<div class="profile-identity"><div class="profile-avatar"><img src="../assets/img/mehi.png" alt="Foto Wakil Kepala Sekolah Bidang Akademik" width="88" height="88" decoding="async"></div><div class="profile-identity-text"><span class="profile-eyebrow">Wakil Kepala Sekolah</span><h2 id="profileName"><?= sanitize($profile['nama_lengkap']??'Belum dilengkapi') ?></h2><p>Bidang Akademik</p></div><span class="profile-active"><span aria-hidden="true"></span>Akun aktif</span></div>
<div class="profile-content"><div class="profile-section-title"><i class="fa-regular fa-id-card" aria-hidden="true"></i><h3>Informasi akun</h3></div>
<dl class="profile-info-grid">
<div><dt>Nama lengkap</dt><dd><?= sanitize($profile['nama_lengkap']??'Belum dilengkapi') ?></dd></div>
<div><dt>Username</dt><dd class="profile-username"><?= sanitize($profile['username']) ?></dd></div>
<div><dt>Jabatan</dt><dd>Wakil Kepala Sekolah Bidang Akademik</dd></div>
<div><dt>Status</dt><dd><span class="profile-status-dot" aria-hidden="true"></span>Aktif</dd></div>
</dl>
<div class="profile-support"><span class="profile-support-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><div><h3>Pengelolaan akun</h3><p>Hubungi admin untuk mengubah identitas akun atau mereset password.</p></div></div>
</div></section>
</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
