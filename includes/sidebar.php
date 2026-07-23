<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$sidebar_profile_name = $_SESSION['username'] ?? 'Pengguna';
$sidebar_profile_role = [1 => 'Administrator', 2 => 'Guru', 3 => 'Siswa'][$role_id] ?? 'Pengguna';
$sidebar_mapel_siswa = [];
if (class_exists('Database')) {
    try {
        $sidebar_db = Database::getInstance();
        if ($role_id === 2) {
            $sidebar_profile_stmt = $sidebar_db->prepare('SELECT nama_lengkap FROM guru WHERE user_id = ?');
            $sidebar_profile_stmt->execute([$_SESSION['user_id']]);
            $sidebar_profile_name = $sidebar_profile_stmt->fetchColumn() ?: $sidebar_profile_name;
        } elseif ($role_id === 3) {
            $sidebar_profile_stmt = $sidebar_db->prepare('SELECT nama_lengkap FROM siswa WHERE user_id = ?');
            $sidebar_profile_stmt->execute([$_SESSION['user_id']]);
            $sidebar_profile_name = $sidebar_profile_stmt->fetchColumn() ?: $sidebar_profile_name;
        }
    } catch (Throwable $e) {
        error_log('Profil sidebar gagal dimuat: ' . $e->getMessage());
    }
}
if ($role_id === 3 && class_exists('Database')) {
    try {
        $sidebar_db = Database::getInstance();
        $sidebar_stmt_mapel = $sidebar_db->prepare(
            'SELECT p.id AS pengajaran_id, m.nama_mapel
             FROM siswa s
             JOIN pengajaran p ON p.kelas_id = s.kelas_id
             JOIN mapel m ON m.id = p.mapel_id
             WHERE s.user_id = ?
             ORDER BY m.nama_mapel'
        );
        $sidebar_stmt_mapel->execute([$_SESSION['user_id']]);
        $sidebar_mapel_siswa = $sidebar_stmt_mapel->fetchAll();
    } catch (Throwable $e) {
        error_log('Sidebar mapel siswa gagal dimuat: ' . $e->getMessage());
    }
}
?>
<style>
    .sidebar-profile {
        border-bottom: 1px solid rgba(255,255,255,.08);
        text-align: center;
    }
    .sidebar-profile-logo {
        height: 132px;
        padding: 12px 20px 16px;
        display: grid;
        place-items: center;
        background: linear-gradient(180deg, #edf2f7 0%, #e3e9f0 100%);
        overflow: hidden;
    }
    .sidebar-profile-logo img {
        display: block;
        width: 96px;
        height: 96px;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        mix-blend-mode: multiply;
    }
    .sidebar-profile-info {
        padding: 13px 12px 14px;
        border-top: 1px solid rgba(255,255,255,.12);
        background: linear-gradient(180deg, #4c5967 0%, #414c59 100%);
    }
    .sidebar-profile-name {
        display: block;
        color: #fff;
        font-size: .88rem;
        font-weight: 750;
        line-height: 1.3;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }
    .sidebar-profile-role {
        display: block;
        margin-top: 5px;
        color: #b8c1cc;
        font-size: .78rem;
        font-weight: 650;
    }
    #sidebar-wrapper .list-group {
        padding: 3px 7px 12px;
        margin-top: 7px !important;
        gap: 2px;
    }
    #sidebar-wrapper .list-group-item {
        min-height: 42px;
        padding: 9px 11px;
        border: 0 !important;
        border-radius: 9px;
        color: #a7b3c5;
        font-size: .84rem;
        line-height: 1.25;
        display: flex;
        align-items: center;
        transition: color .18s ease, background .18s ease, transform .18s ease;
    }
    #sidebar-wrapper .list-group-item i {
        width: 21px;
        margin-right: 7px !important;
        color: #8fa0b7;
        font-size: .88rem;
        text-align: center;
        transition: color .18s ease;
    }
    #sidebar-wrapper .list-group-item:hover {
        color: #e2e8f0;
        background: rgba(255,255,255,.055);
        transform: translateX(2px);
    }
    #sidebar-wrapper .list-group-item:hover i {
        color: #7dd3fc;
    }
    #sidebar-wrapper .list-group-item.active {
        color: #38bdf8;
        background: #111c30;
        box-shadow: inset 3px 0 0 #38bdf8, 0 4px 12px rgba(2,8,23,.12);
    }
    #sidebar-wrapper .list-group-item.active i {
        color: #38bdf8;
    }
    #sidebar-wrapper .list-group-item.text-danger {
        margin-top: 10px !important;
        color: #fb7185 !important;
    }
    #sidebar-wrapper .list-group-item.text-danger i {
        color: #fb7185;
    }
    @media (max-height: 700px) {
        .sidebar-profile-logo { height: 108px; padding: 9px 20px 12px; }
        .sidebar-profile-logo img { width: 80px; height: 80px; }
        .sidebar-profile-info { padding: 10px 10px 11px; }
        #sidebar-wrapper .list-group-item { min-height: 38px; padding: 7px 10px; font-size: .8rem; }
        #sidebar-wrapper .list-group-item i { font-size: .82rem; }
    }
</style>
<div id="sidebar-wrapper">
    <div class="sidebar-profile">
        <div class="sidebar-profile-logo">
            <img src="../assets/img/jb-mobile.png" alt="Logo SMKS Jaya Buana">
        </div>
        <div class="sidebar-profile-info">
            <span class="sidebar-profile-name"><?= sanitize($sidebar_profile_name) ?></span>
            <span class="sidebar-profile-role"><?= sanitize($sidebar_profile_role) ?></span>
        </div>
    </div>
    <div class="list-group list-group-flush mt-3">
        <?php if ($role_id === 1): ?>
        <a href="index.php" class="list-group-item list-group-item-action <?= ($current_page == 'index.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge me-2"></i> Dashboard
        </a>
        <a href="users.php" class="list-group-item list-group-item-action <?= ($current_page == 'users.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-users-gear me-2"></i> Manajemen User
        </a>
        <a href="guru.php" class="list-group-item list-group-item-action <?= ($current_page == 'guru.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-chalkboard-user me-2"></i> Data Guru
        </a>
        <a href="siswa.php" class="list-group-item list-group-item-action <?= ($current_page == 'siswa.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-user-graduate me-2"></i> Data Siswa
        </a>
        <a href="kelas.php" class="list-group-item list-group-item-action <?= ($current_page == 'kelas.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-school me-2"></i> Kelas & Jurusan
        </a>
        <a href="mapel.php" class="list-group-item list-group-item-action <?= ($current_page == 'mapel.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-book me-2"></i> Mata Pelajaran
        </a>
        <a href="pengajaran.php" class="list-group-item list-group-item-action <?= ($current_page == 'pengajaran.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-person-chalkboard me-2"></i> Pengajaran
        </a>
        <a href="monitoring.php" class="list-group-item list-group-item-action <?= ($current_page == 'monitoring.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-binoculars me-2"></i> Monitoring LMS
        </a>
        <a href="log_aktivitas.php" class="list-group-item list-group-item-action <?= ($current_page == 'log_aktivitas.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left me-2"></i> Log Aktivitas
        </a>
        <?php elseif ($role_id === 2): ?>
        <a href="index.php" class="list-group-item list-group-item-action <?= ($current_page === 'index.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge me-2"></i> Dashboard Guru
        </a>
        <a href="materi.php" class="list-group-item list-group-item-action <?= ($current_page === 'materi.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-book-open me-2"></i> Materi
        </a>
        <a href="tugas.php" class="list-group-item list-group-item-action <?= in_array($current_page, ['tugas.php', 'tugas_penilaian.php'], true) ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check me-2"></i> Tugas
        </a>
        <a href="absensi.php" class="list-group-item list-group-item-action <?= ($current_page === 'absensi.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-clipboard-user me-2"></i> Absensi
        </a>
        <a href="riwayat_absensi.php" class="list-group-item list-group-item-action <?= ($current_page === 'riwayat_absensi.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left me-2"></i> Riwayat Absensi
        </a>
        <a href="rekap_absensi.php" class="list-group-item list-group-item-action <?= ($current_page === 'rekap_absensi.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-clipboard-check me-2"></i> Rekap Absensi
        </a>
        <a href="ujian.php" class="list-group-item list-group-item-action <?= ($current_page === 'ujian.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-file-circle-question me-2"></i> Ujian
        </a>
        <a href="rekap_nilai.php" class="list-group-item list-group-item-action <?= ($current_page === 'rekap_nilai.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-column me-2"></i> Rekap Nilai
        </a>
        <?php elseif ($role_id === 3): ?>
        <a href="index.php" class="list-group-item list-group-item-action <?= ($current_page === 'index.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge me-2"></i> Dashboard Siswa
        </a>
        <div class="px-4 pt-3 pb-1 text-uppercase small fw-bold text-secondary" style="letter-spacing:.08em;">Mata Pelajaran</div>
        <a href="#sidebarMapelSiswa" class="list-group-item list-group-item-action d-flex align-items-center <?= $current_page === 'materi.php' ? 'active' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= $current_page === 'materi.php' ? 'true' : 'false' ?>" aria-controls="sidebarMapelSiswa">
            <i class="fa-solid fa-book-open-reader me-2"></i><span class="flex-grow-1">Semua Materi</span><i class="fa-solid fa-chevron-down small"></i>
        </a>
        <div class="collapse <?= $current_page === 'materi.php' ? 'show' : '' ?>" id="sidebarMapelSiswa">
            <a href="materi.php" class="list-group-item list-group-item-action <?= ($current_page === 'materi.php' && empty($_GET['pengajaran_id'])) ? 'active' : '' ?>" style="padding-left:2.25rem;font-size:.86rem;">
                <i class="fa-solid fa-layer-group me-2"></i>Tampilkan Semua
            </a>
            <?php foreach ($sidebar_mapel_siswa as $sidebar_mapel): ?>
            <a href="materi.php?pengajaran_id=<?= (int)$sidebar_mapel['pengajaran_id'] ?>" class="list-group-item list-group-item-action <?= ($current_page === 'materi.php' && (int)($_GET['pengajaran_id'] ?? 0) === (int)$sidebar_mapel['pengajaran_id']) ? 'active' : '' ?>" style="padding-left:2.25rem;font-size:.86rem;">
                <i class="fa-solid fa-angle-right me-2"></i><?= sanitize($sidebar_mapel['nama_mapel']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <a href="absensi.php" class="list-group-item list-group-item-action <?= ($current_page === 'absensi.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-user-check me-2"></i> Absensi Saya
        </a>
        <a href="tugas.php" class="list-group-item list-group-item-action <?= ($current_page === 'tugas.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check me-2"></i> Tugas
        </a>
        <a href="ujian.php" class="list-group-item list-group-item-action <?= in_array($current_page,['ujian.php','ujian_kerjakan.php'],true) ? 'active' : '' ?>">
            <i class="fa-solid fa-file-pen me-2"></i> Ujian
        </a>
        <?php endif; ?>
        <a href="../logout.php" class="list-group-item list-group-item-action text-danger mt-4">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Keluar
        </a>
    </div>
</div>
<?php if (in_array($role_id, [2, 3], true)): ?>
<style>
    .portal-sidebar-trigger,
    .portal-sidebar-overlay { display:none; }
    @media (max-width:991.98px) {
        #sidebar-wrapper { position:fixed; inset:0 auto 0 0; z-index:1045; height:100dvh; overflow-y:auto; transform:translateX(-100%); box-shadow:12px 0 30px rgba(15,23,42,.25); }
        body.student-menu-open #sidebar-wrapper { transform:translateX(0); }
        #page-content-wrapper { width:100%; min-width:0; }
        .portal-sidebar-trigger { display:grid; place-items:center; position:fixed; top:10px; left:12px; z-index:1035; width:44px; height:44px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; color:#1e293b; box-shadow:0 4px 14px rgba(15,23,42,.12); }
        .portal-sidebar-overlay { display:block; position:fixed; inset:0; z-index:1040; background:rgba(15,23,42,.45); opacity:0; visibility:hidden; transition:opacity .2s ease,visibility .2s ease; }
        body.student-menu-open .portal-sidebar-overlay { opacity:1; visibility:visible; }
        .portal-sidebar-overlay ~ #page-content-wrapper > nav:first-child { padding-left:4.25rem!important; min-height:64px; }
    }
</style>
<button type="button" class="portal-sidebar-trigger" id="studentMenuButton" aria-label="Buka menu <?= $role_id === 2 ? 'guru' : 'siswa' ?>" aria-controls="sidebar-wrapper" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
</button>
<div class="portal-sidebar-overlay" id="menuOverlay" aria-hidden="true"></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tombol = document.getElementById('studentMenuButton');
    const overlay = document.getElementById('menuOverlay');
    const sidebar = document.getElementById('sidebar-wrapper');
    const tutupMenu = () => {
        document.body.classList.remove('student-menu-open');
        tombol?.setAttribute('aria-expanded', 'false');
    };
    tombol?.addEventListener('click', function () {
        const terbuka = document.body.classList.toggle('student-menu-open');
        tombol.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
    });
    overlay?.addEventListener('click', tutupMenu);
    sidebar?.querySelectorAll('a.list-group-item[href]').forEach(function (tautan) {
        tautan.addEventListener('click', function () {
            if (!tautan.hasAttribute('data-bs-toggle')) tutupMenu();
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') tutupMenu();
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) tutupMenu();
    });
});
</script>
<?php endif; ?>
