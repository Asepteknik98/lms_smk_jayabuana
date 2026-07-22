<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([1]);
$db = Database::getInstance();

$statistik = [
    'guru' => (int)$db->query('SELECT COUNT(*) FROM guru')->fetchColumn(),
    'siswa' => (int)$db->query('SELECT COUNT(*) FROM siswa')->fetchColumn(),
    'kelas' => (int)$db->query('SELECT COUNT(*) FROM kelas')->fetchColumn(),
    'mapel' => (int)$db->query('SELECT COUNT(*) FROM mapel')->fetchColumn(),
    'pengajaran' => (int)$db->query('SELECT COUNT(*) FROM pengajaran')->fetchColumn(),
    'materi' => (int)$db->query('SELECT COUNT(*) FROM materi')->fetchColumn(),
    'tugas' => (int)$db->query('SELECT COUNT(*) FROM tugas')->fetchColumn(),
    'ujian' => (int)$db->query('SELECT COUNT(*) FROM ujian')->fetchColumn(),
];

$perlu_tindakan = [
    'akun_nonaktif' => (int)$db->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn(),
    'guru_tanpa_pengajaran' => (int)$db->query('SELECT COUNT(*) FROM guru g WHERE NOT EXISTS (SELECT 1 FROM pengajaran p WHERE p.guru_id = g.id)')->fetchColumn(),
    'siswa_tanpa_kelas' => (int)$db->query('SELECT COUNT(*) FROM siswa WHERE kelas_id IS NULL')->fetchColumn(),
    'tugas_belum_dinilai' => (int)$db->query('SELECT COUNT(*) FROM pengumpulan_tugas WHERE nilai IS NULL')->fetchColumn(),
    'absensi_dibuka' => (int)$db->query("SELECT COUNT(*) FROM sesi_absensi WHERE status = 'Dibuka'")->fetchColumn(),
];

$stmt_absensi = $db->query(
    "SELECT sa.id, sa.pertemuan_ke, sa.waktu_tutup, g.nama_lengkap AS nama_guru,
            m.nama_mapel, k.nama_kelas,
            COUNT(da.id) AS sudah_tercatat,
            (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = p.kelas_id) AS total_siswa
     FROM sesi_absensi sa
     JOIN pengajaran p ON p.id = sa.pengajaran_id
     JOIN guru g ON g.id = p.guru_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     LEFT JOIN detail_absensi da ON da.sesi_absensi_id = sa.id
     WHERE sa.status = 'Dibuka'
     GROUP BY sa.id
     ORDER BY sa.waktu_tutup ASC
     LIMIT 5"
);
$absensi_aktif = $stmt_absensi->fetchAll();

$total_pemeriksaan = 5;
$pemeriksaan_siap = 0;
$pemeriksaan_siap += $statistik['guru'] > 0 ? 1 : 0;
$pemeriksaan_siap += $statistik['siswa'] > 0 ? 1 : 0;
$pemeriksaan_siap += $statistik['kelas'] > 0 && $statistik['mapel'] > 0 ? 1 : 0;
$pemeriksaan_siap += $statistik['pengajaran'] > 0 ? 1 : 0;
$pemeriksaan_siap += $perlu_tindakan['siswa_tanpa_kelas'] === 0 ? 1 : 0;
$persentase_siap = (int)round(($pemeriksaan_siap / $total_pemeriksaan) * 100);
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div id="page-content-wrapper" class="admin-dashboard">
    <style>
        .admin-dashboard { background: #f7f9fc; min-height: 100vh; }
        .welcome-panel { background: linear-gradient(135deg, #172554, #1d4ed8); border-radius: 22px; color: #fff; padding: 30px; box-shadow: 0 18px 45px rgba(29,78,216,.18); }
        .dashboard-card { border: 1px solid #e8edf5 !important; border-radius: 18px !important; box-shadow: 0 8px 26px rgba(15,23,42,.045) !important; }
        .metric-link { color: inherit; text-decoration: none; display: block; height: 100%; }
        .metric-card { transition: transform .2s ease, box-shadow .2s ease; }
        .metric-link:hover .metric-card { transform: translateY(-4px); box-shadow: 0 15px 34px rgba(15,23,42,.09) !important; }
        .metric-icon { width: 52px; height: 52px; display: grid; place-items: center; border-radius: 15px; font-size: 1.2rem; }
        .mini-stat { border: 1px solid #edf1f6; border-radius: 15px; padding: 16px; background: #fff; height: 100%; }
        .action-link { display: flex; align-items: center; gap: 13px; padding: 14px; border: 1px solid #e8edf5; border-radius: 14px; color: #334155; text-decoration: none; transition: .2s ease; }
        .action-link:hover { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; transform: translateX(3px); }
        .action-icon { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 11px; background: #eff6ff; color: #1d4ed8; flex: 0 0 40px; }
        .attention-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-bottom: 1px solid #edf1f6; }
        .attention-item:last-child { border-bottom: 0; }
        .progress { height: 9px; background: #e8eef8; }
        @media (max-width: 767.98px) { .welcome-panel { padding: 22px; border-radius: 18px; } .dashboard-content { padding: 18px !important; } }
    </style>

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div><h5 class="mb-0 fw-bold">Dashboard Administrator</h5><small class="text-muted">Pusat kendali operasional LMS sekolah</small></div>
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill"><i class="fa-solid fa-shield-halved me-1"></i> Administrator</span>
        </div>
    </nav>

    <div class="container-fluid p-4 dashboard-content">
        <section class="welcome-panel mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div><small class="text-uppercase opacity-75 fw-semibold">LMS SMK Jaya Buana</small><h2 class="fw-bold mt-1 mb-2">Selamat datang, <?= sanitize($_SESSION['username']) ?></h2><p class="mb-0 opacity-75">Kelola data akademik dan pantau kesiapan pembelajaran dari satu dashboard.</p></div>
                <a href="monitoring.php" class="btn btn-light text-primary fw-semibold px-4"><i class="fa-solid fa-binoculars me-2"></i>Buka Monitoring</a>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <?php
            $metric_utama = [
                ['Total Guru', $statistik['guru'], 'guru.php', 'fa-chalkboard-user', 'primary'],
                ['Total Siswa', $statistik['siswa'], 'siswa.php', 'fa-user-graduate', 'success'],
                ['Total Kelas', $statistik['kelas'], 'kelas.php', 'fa-school', 'warning'],
                ['Mata Pelajaran', $statistik['mapel'], 'mapel.php', 'fa-book', 'info'],
            ];
            foreach ($metric_utama as [$label, $jumlah, $url, $icon, $warna]): ?>
                <div class="col-6 col-xl-3"><a class="metric-link" href="<?= $url ?>"><div class="card dashboard-card metric-card p-3 h-100"><div class="d-flex align-items-center justify-content-between gap-2"><div><small class="text-muted fw-semibold"><?= $label ?></small><h2 class="fw-bold mb-0 mt-1"><?= $jumlah ?></h2></div><span class="metric-icon bg-<?= $warna ?>-subtle text-<?= $warna ?>"><i class="fa-solid <?= $icon ?>"></i></span></div></div></a></div>
            <?php endforeach; ?>
        </div>

        <div class="card dashboard-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="fw-bold mb-1">Aktivitas Akademik</h6><small class="text-muted">Ringkasan konten dan proses pembelajaran</small></div><a href="monitoring.php" class="btn btn-sm btn-outline-primary">Lihat Detail</a></div>
            <div class="row g-3">
                <?php foreach ([['Pengajaran',$statistik['pengajaran'],'fa-person-chalkboard'],['Materi',$statistik['materi'],'fa-book-open'],['Tugas',$statistik['tugas'],'fa-list-check'],['Ujian',$statistik['ujian'],'fa-file-circle-question']] as [$label,$jumlah,$icon]): ?>
                    <div class="col-6 col-lg-3"><div class="mini-stat d-flex align-items-center gap-3"><span class="text-primary fs-4"><i class="fa-solid <?= $icon ?>"></i></span><div><small class="text-muted"><?= $label ?></small><h4 class="fw-bold mb-0"><?= $jumlah ?></h4></div></div></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-5">
                <div class="card dashboard-card p-4 h-100">
                    <h6 class="fw-bold mb-1">Perlu Tindakan</h6><p class="text-muted small mb-3">Masalah administratif yang perlu diperiksa.</p>
                    <?php
                    $tindakan = [
                        ['Akun nonaktif', $perlu_tindakan['akun_nonaktif'], 'users.php', 'fa-user-lock', 'secondary'],
                        ['Guru belum mendapat pengajaran', $perlu_tindakan['guru_tanpa_pengajaran'], 'pengajaran.php', 'fa-person-circle-exclamation', 'warning'],
                        ['Siswa belum memiliki kelas', $perlu_tindakan['siswa_tanpa_kelas'], 'siswa.php', 'fa-user-clock', 'danger'],
                        ['Tugas belum dinilai', $perlu_tindakan['tugas_belum_dinilai'], 'monitoring.php', 'fa-pen-to-square', 'info'],
                        ['Sesi absensi masih dibuka', $perlu_tindakan['absensi_dibuka'], 'monitoring.php', 'fa-door-open', 'success'],
                    ];
                    foreach ($tindakan as [$label,$jumlah,$url,$icon,$warna]): ?>
                        <div class="attention-item"><div class="d-flex align-items-center gap-3"><span class="text-<?= $warna ?>"><i class="fa-solid <?= $icon ?>"></i></span><span class="small fw-semibold"><?= $label ?></span></div><a href="<?= $url ?>" class="badge text-bg-<?= $jumlah > 0 ? $warna : 'light' ?> text-decoration-none rounded-pill"><?= $jumlah ?></a></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card dashboard-card p-4 h-100">
                    <h6 class="fw-bold mb-1">Akses Cepat</h6><p class="text-muted small mb-3">Menu yang paling sering digunakan Admin.</p>
                    <div class="d-grid gap-2">
                        <a href="pengajaran.php" class="action-link"><span class="action-icon"><i class="fa-solid fa-person-chalkboard"></i></span><div><strong class="d-block small">Atur Pengajaran</strong><small class="text-muted">Hubungkan Guru, mapel, dan kelas</small></div></a>
                        <a href="users.php" class="action-link"><span class="action-icon"><i class="fa-solid fa-users-gear"></i></span><div><strong class="d-block small">Kelola Pengguna</strong><small class="text-muted">Akun dan status akses LMS</small></div></a>
                        <a href="monitoring.php" class="action-link"><span class="action-icon"><i class="fa-solid fa-binoculars"></i></span><div><strong class="d-block small">Monitoring LMS</strong><small class="text-muted">Pantau Guru, Siswa, dan absensi</small></div></a>
                    </div>
                </div>
            </div>

            <div class="col-xl-3">
                <div class="card dashboard-card p-4 h-100">
                    <h6 class="fw-bold mb-1">Kesiapan Sistem</h6><p class="text-muted small">Kelengkapan data dasar LMS.</p>
                    <div class="text-center py-3"><div class="display-5 fw-bold text-primary"><?= $persentase_siap ?>%</div><small class="text-muted"><?= $pemeriksaan_siap ?> dari <?= $total_pemeriksaan ?> pemeriksaan siap</small></div>
                    <div class="progress mb-4"><div class="progress-bar" style="width: <?= $persentase_siap ?>%"></div></div>
                    <div class="small d-grid gap-2">
                        <span><i class="fa-solid fa-circle-check text-success me-2"></i>Data Guru dan Siswa</span>
                        <span><i class="fa-solid <?= $statistik['pengajaran'] ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?> me-2"></i>Penugasan mengajar</span>
                        <span><i class="fa-solid <?= !$perlu_tindakan['siswa_tanpa_kelas'] ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' ?> me-2"></i>Penempatan kelas Siswa</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($absensi_aktif): ?>
        <div class="card dashboard-card p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="fw-bold mb-1">Absensi Sedang Berlangsung</h6><small class="text-muted">Sesi yang saat ini masih dibuka Guru.</small></div><a href="monitoring.php" class="btn btn-sm btn-outline-primary">Pantau Semua</a></div>
            <div class="row g-3"><?php foreach ($absensi_aktif as $sesi): ?><div class="col-md-6 col-xl-4"><div class="mini-stat"><div class="d-flex justify-content-between mb-2"><span class="badge bg-success">Dibuka</span><small class="text-muted">Pertemuan <?= (int)$sesi['pertemuan_ke'] ?></small></div><strong><?= sanitize($sesi['nama_mapel']) ?></strong><div class="small text-muted"><?= sanitize($sesi['nama_kelas']) ?> · <?= sanitize($sesi['nama_guru']) ?></div><div class="small mt-2"><i class="fa-solid fa-users me-1"></i><?= (int)$sesi['sudah_tercatat'] ?>/<?= (int)$sesi['total_siswa'] ?> siswa tercatat</div></div></div><?php endforeach; ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
