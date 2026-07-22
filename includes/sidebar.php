<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$sidebar_mapel_siswa = [];
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
<div id="sidebar-wrapper">
    <div class="sidebar-heading d-flex align-items-center gap-3">
        <span class="sidebar-school-logo">
            <img src="../assets/img/jb.png" alt="Logo SMK Jaya Buana">
        </span>
        <span class="sidebar-school-name">SMK JAYA BUANA</span>
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
        .portal-sidebar-trigger { display:grid; place-items:center; position:fixed; top:11px; left:12px; z-index:1035; width:42px; height:42px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; color:#1e293b; box-shadow:0 4px 14px rgba(15,23,42,.12); }
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
