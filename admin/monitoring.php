<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([1]);
$db = Database::getInstance();

$statistik = [
    'guru' => (int)$db->query('SELECT COUNT(*) FROM guru')->fetchColumn(),
    'siswa' => (int)$db->query('SELECT COUNT(*) FROM siswa')->fetchColumn(),
    'pengajaran' => (int)$db->query('SELECT COUNT(*) FROM pengajaran')->fetchColumn(),
    'materi' => (int)$db->query('SELECT COUNT(*) FROM materi')->fetchColumn(),
    'tugas' => (int)$db->query('SELECT COUNT(*) FROM tugas')->fetchColumn(),
    'ujian' => (int)$db->query('SELECT COUNT(*) FROM ujian')->fetchColumn(),
    'sesi_absensi' => (int)$db->query('SELECT COUNT(*) FROM sesi_absensi')->fetchColumn(),
    'pengumpulan' => (int)$db->query('SELECT COUNT(*) FROM pengumpulan_tugas')->fetchColumn(),
];

$guru_list = $db->query(
    "SELECT g.id, g.nip, g.nama_lengkap, u.username, u.is_active,
            (SELECT COUNT(*) FROM pengajaran p WHERE p.guru_id = g.id) AS total_pengajaran,
            (SELECT COUNT(*) FROM materi mat JOIN pengajaran p ON p.id = mat.pengajaran_id WHERE p.guru_id = g.id) AS total_materi,
            (SELECT COUNT(*) FROM tugas t JOIN pengajaran p ON p.id = t.pengajaran_id WHERE p.guru_id = g.id) AS total_tugas,
            (SELECT COUNT(*) FROM ujian uj JOIN pengajaran p ON p.id = uj.pengajaran_id WHERE p.guru_id = g.id) AS total_ujian,
            (SELECT COUNT(*) FROM sesi_absensi sa JOIN pengajaran p ON p.id = sa.pengajaran_id WHERE p.guru_id = g.id) AS total_absensi
     FROM guru g
     JOIN users u ON u.id = g.user_id
     ORDER BY g.nama_lengkap"
)->fetchAll();

$siswa_list = $db->query(
    "SELECT s.id, s.nisn, s.nama_lengkap, k.nama_kelas, u.username, u.is_active,
            (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.siswa_id = s.id) AS tugas_dikumpulkan,
            (SELECT COUNT(*) FROM nilai_ujian nu WHERE nu.siswa_id = s.id) AS ujian_selesai,
            (SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id = s.id) AS total_absensi,
            (SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id = s.id AND da.status IN ('Hadir','Terlambat')) AS hadir_absensi,
            (SELECT COUNT(*) FROM detail_absensi da WHERE da.siswa_id = s.id AND da.status = 'Alpa') AS alpa_absensi
     FROM siswa s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN kelas k ON k.id = s.kelas_id
     ORDER BY k.nama_kelas, s.nama_lengkap"
)->fetchAll();

$aktivitas_pembelajaran = $db->query(
    "SELECT 'Materi' AS jenis, mat.judul AS aktivitas, mat.created_at AS waktu,
            g.nama_lengkap AS pelaku, m.nama_mapel, k.nama_kelas
     FROM materi mat
     JOIN pengajaran p ON p.id = mat.pengajaran_id
     JOIN guru g ON g.id = p.guru_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     UNION ALL
     SELECT 'Tugas', t.judul, t.created_at, g.nama_lengkap, m.nama_mapel, k.nama_kelas
     FROM tugas t
     JOIN pengajaran p ON p.id = t.pengajaran_id
     JOIN guru g ON g.id = p.guru_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     UNION ALL
     SELECT 'Ujian', uj.nama_ujian, uj.created_at, g.nama_lengkap, m.nama_mapel, k.nama_kelas
     FROM ujian uj
     JOIN pengajaran p ON p.id = uj.pengajaran_id
     JOIN guru g ON g.id = p.guru_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     ORDER BY waktu DESC
     LIMIT 20"
)->fetchAll();

$sesi_absensi = $db->query(
    "SELECT sa.id, sa.pertemuan_ke, sa.tanggal, sa.status,
            g.nama_lengkap AS nama_guru, m.nama_mapel, k.nama_kelas,
            COUNT(da.id) AS total_tercatat,
            SUM(da.status IN ('Hadir','Terlambat')) AS hadir,
            SUM(da.status = 'Sakit') AS sakit,
            SUM(da.status = 'Izin') AS izin,
            SUM(da.status = 'Alpa') AS alpa
     FROM sesi_absensi sa
     JOIN pengajaran p ON p.id = sa.pengajaran_id
     JOIN guru g ON g.id = p.guru_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     LEFT JOIN detail_absensi da ON da.sesi_absensi_id = sa.id
     GROUP BY sa.id
     ORDER BY sa.tanggal DESC, sa.id DESC
     LIMIT 20"
)->fetchAll();

$selected_guru = null;
$guru_id = (int)($_GET['guru_id'] ?? 0);
if ($guru_id > 0) {
    $stmt = $db->prepare(
        "SELECT g.id, g.nip, g.nama_lengkap, g.email, u.username, u.is_active,
                p.id AS pengajaran_id, p.tahun_ajaran, p.semester,
                m.nama_mapel, k.nama_kelas
         FROM guru g
         JOIN users u ON u.id = g.user_id
         LEFT JOIN pengajaran p ON p.guru_id = g.id
         LEFT JOIN mapel m ON m.id = p.mapel_id
         LEFT JOIN kelas k ON k.id = p.kelas_id
         WHERE g.id = ?
         ORDER BY p.tahun_ajaran DESC, p.semester, m.nama_mapel"
    );
    $stmt->execute([$guru_id]);
    $selected_guru = $stmt->fetchAll();
}

$selected_siswa = null;
$siswa_id = (int)($_GET['siswa_id'] ?? 0);
if ($siswa_id > 0) {
    $stmt = $db->prepare(
        "SELECT s.id, s.nisn, s.nama_lengkap, s.email, k.nama_kelas, u.username, u.is_active,
                CASE WHEN da.status = 'Terlambat' THEN 'Hadir' ELSE da.status END AS status_absensi, da.waktu_checkin, sa.tanggal, sa.pertemuan_ke,
                m.nama_mapel
         FROM siswa s
         JOIN users u ON u.id = s.user_id
         LEFT JOIN kelas k ON k.id = s.kelas_id
         LEFT JOIN detail_absensi da ON da.siswa_id = s.id
         LEFT JOIN sesi_absensi sa ON sa.id = da.sesi_absensi_id
         LEFT JOIN pengajaran p ON p.id = sa.pengajaran_id
         LEFT JOIN mapel m ON m.id = p.mapel_id
         WHERE s.id = ?
         ORDER BY sa.tanggal DESC, sa.pertemuan_ke DESC"
    );
    $stmt->execute([$siswa_id]);
    $selected_siswa = $stmt->fetchAll();
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div id="page-content-wrapper">
    <style>
        .monitoring-page { background: #f7f9fc; min-height: 100%; }
        .monitoring-hero { background: linear-gradient(135deg, #172554, #1d4ed8); border-radius: 22px; padding: 30px; color: #fff; box-shadow: 0 18px 45px rgba(29, 78, 216, .18); }
        .monitoring-hero .hero-icon { width: 56px; height: 56px; display: grid; place-items: center; border-radius: 16px; background: rgba(255,255,255,.14); font-size: 1.4rem; }
        .metric-card { border: 1px solid #e9eef6 !important; border-radius: 18px !important; box-shadow: 0 8px 24px rgba(15, 23, 42, .045) !important; transition: transform .2s ease, box-shadow .2s ease; }
        .metric-card:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(15, 23, 42, .08) !important; }
        .metric-icon { width: 48px; height: 48px; display: grid; place-items: center; flex: 0 0 48px; border-radius: 14px !important; }
        .monitor-nav { display: flex; gap: 8px; overflow-x: auto; padding: 6px; border: 1px solid #e7ecf4; border-radius: 16px; background: #fff; box-shadow: 0 8px 25px rgba(15, 23, 42, .04); scrollbar-width: none; }
        .monitor-nav::-webkit-scrollbar { display: none; }
        .monitor-tab { border: 0; background: transparent; color: #64748b; white-space: nowrap; padding: 11px 18px; border-radius: 11px; font-weight: 600; }
        .monitor-tab:hover { background: #eff6ff; color: #1d4ed8; }
        .monitor-tab.active { background: #1d4ed8; color: #fff; box-shadow: 0 7px 16px rgba(29, 78, 216, .22); }
        .monitor-panel { display: none; }
        .monitor-panel.active { display: block; width: 100%; }
        .content-card { border: 1px solid #e9eef6 !important; border-radius: 18px !important; box-shadow: 0 8px 28px rgba(15, 23, 42, .045) !important; }
        .content-card .table { margin-bottom: 0; }
        .content-card .table > :not(caption) > * > * { padding: .9rem .75rem; border-bottom-color: #edf1f6; }
        .content-card thead th { color: #64748b; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; background: #f8fafc; }
        .detail-card { border-radius: 18px !important; background: #fff; }
        .export-actions { display: flex; flex-wrap: wrap; gap: 7px; }
        @media (max-width: 767.98px) { .monitoring-hero { padding: 22px; border-radius: 18px; } .monitoring-page { padding: 18px !important; } }
    </style>
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-3">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-binoculars text-primary me-2"></i> Pusat Monitoring</h5>
    </nav>

    <div class="container-fluid p-4 monitoring-page">
        <div class="monitoring-hero mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                    <h3 class="fw-bold mb-1">Ringkasan Aktivitas LMS</h3>
                    <p class="mb-0 opacity-75">Pantau perkembangan pembelajaran Guru dan Siswa dari satu tempat.</p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php
            $cards = [
                ['Guru', $statistik['guru'], 'fa-chalkboard-user', 'primary'],
                ['Siswa', $statistik['siswa'], 'fa-user-graduate', 'success'],
                ['Pengajaran', $statistik['pengajaran'], 'fa-person-chalkboard', 'info'],
                ['Materi', $statistik['materi'], 'fa-book-open', 'warning'],
                ['Tugas', $statistik['tugas'], 'fa-list-check', 'danger'],
                ['Ujian', $statistik['ujian'], 'fa-file-circle-question', 'primary'],
                ['Sesi Absensi', $statistik['sesi_absensi'], 'fa-clipboard-user', 'success'],
                ['Pengumpulan', $statistik['pengumpulan'], 'fa-file-arrow-up', 'secondary'],
            ];
            foreach ($cards as [$label, $jumlah, $icon, $warna]):
            ?>
                <div class="col-6 col-md-3">
                    <div class="card metric-card h-100 p-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="metric-icon bg-<?= $warna ?> text-white"><i class="fa-solid <?= $icon ?>"></i></span>
                            <div><small class="text-muted"><?= sanitize($label) ?></small><h4 class="fw-bold mb-0"><?= (int)$jumlah ?></h4></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selected_guru): $profil = $selected_guru[0]; ?>
            <div class="card detail-card border-primary shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between"><h6 class="fw-bold">Detail Guru: <?= sanitize($profil['nama_lengkap']) ?></h6><a href="monitoring.php" class="btn-close"></a></div>
                <p class="small text-muted">Username: <?= sanitize($profil['username']) ?> · NIP: <?= sanitize($profil['nip'] ?: '-') ?> · Status: <?= $profil['is_active'] ? 'Aktif' : 'Nonaktif' ?></p>
                <div class="export-actions mb-3"><a href="monitoring_export.php?jenis=guru&amp;format=pdf&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf me-1"></i> PDF Guru</a><a href="monitoring_export.php?jenis=guru&amp;format=excel&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel me-1"></i> Excel Guru</a><a href="monitoring_export.php?jenis=absensi&amp;scope=guru&amp;format=pdf&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-outline-danger">PDF Absensi</a><a href="monitoring_export.php?jenis=absensi&amp;scope=guru&amp;format=excel&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-outline-success">Excel Absensi</a></div>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Mapel</th><th>Kelas</th><th>Semester</th><th>Tahun Ajaran</th></tr></thead><tbody>
                    <?php foreach ($selected_guru as $row): if (!$row['pengajaran_id']) continue; ?><tr><td><?= sanitize($row['nama_mapel']) ?></td><td><?= sanitize($row['nama_kelas']) ?></td><td><?= sanitize($row['semester']) ?></td><td><?= sanitize($row['tahun_ajaran']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
        <?php endif; ?>

        <?php if ($selected_siswa): $profil = $selected_siswa[0]; ?>
            <div class="card detail-card border-success shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between"><h6 class="fw-bold">Detail Siswa: <?= sanitize($profil['nama_lengkap']) ?></h6><a href="monitoring.php" class="btn-close"></a></div>
                <p class="small text-muted">Username: <?= sanitize($profil['username']) ?> · NISN: <?= sanitize($profil['nisn']) ?> · Kelas: <?= sanitize($profil['nama_kelas'] ?: '-') ?></p>
                <div class="export-actions mb-3"><a href="monitoring_export.php?jenis=siswa&amp;format=pdf&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf me-1"></i> PDF Siswa</a><a href="monitoring_export.php?jenis=siswa&amp;format=excel&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel me-1"></i> Excel Siswa</a><a href="monitoring_export.php?jenis=absensi&amp;format=pdf&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-outline-danger">PDF Absensi</a><a href="monitoring_export.php?jenis=absensi&amp;format=excel&amp;id=<?= (int)$profil['id'] ?>" class="btn btn-sm btn-outline-success">Excel Absensi</a></div>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Tanggal</th><th>Mapel</th><th>Pertemuan</th><th>Status</th><th>Check-in</th></tr></thead><tbody>
                    <?php foreach ($selected_siswa as $row): if (!$row['status_absensi']) continue; ?><tr><td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td><td><?= sanitize($row['nama_mapel']) ?></td><td><?= (int)$row['pertemuan_ke'] ?></td><td><?= sanitize($row['status_absensi']) ?></td><td><?= $row['waktu_checkin'] ? date('H:i:s', strtotime($row['waktu_checkin'])) : '-' ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
        <?php endif; ?>

        <div class="monitor-nav mb-4" role="tablist" aria-label="Kategori monitoring">
            <button class="monitor-tab active" type="button" data-target="guru"><i class="fa-solid fa-chalkboard-user me-2"></i>Guru</button>
            <button class="monitor-tab" type="button" data-target="siswa"><i class="fa-solid fa-user-graduate me-2"></i>Siswa</button>
            <button class="monitor-tab" type="button" data-target="aktivitas"><i class="fa-solid fa-wave-square me-2"></i>Aktivitas</button>
            <button class="monitor-tab" type="button" data-target="absensi"><i class="fa-solid fa-clipboard-check me-2"></i>Absensi</button>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-6 monitor-panel active" data-panel="guru">
                <div class="card content-card p-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h6 class="fw-bold mb-0">Monitoring Guru</h6><div class="export-actions"><a href="monitoring_export.php?jenis=guru&amp;format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf me-1"></i> Semua PDF</a><a href="monitoring_export.php?jenis=guru&amp;format=excel" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel me-1"></i> Semua Excel</a></div></div>
                    <div class="table-responsive"><table id="tableGuruMonitoring" class="table table-hover align-middle w-100"><thead class="table-light"><tr><th>Guru</th><th>Aktivitas</th><th>Status</th><th>Detail</th></tr></thead><tbody>
                        <?php foreach ($guru_list as $guru): ?><tr>
                            <td><strong><?= sanitize($guru['nama_lengkap']) ?></strong><br><small><?= sanitize($guru['username']) ?></small></td>
                            <td><small>Pengajaran <?= (int)$guru['total_pengajaran'] ?> · Materi <?= (int)$guru['total_materi'] ?> · Tugas <?= (int)$guru['total_tugas'] ?> · Ujian <?= (int)$guru['total_ujian'] ?> · Absensi <?= (int)$guru['total_absensi'] ?></small></td>
                            <td><span class="badge bg-<?= $guru['is_active'] ? 'success' : 'danger' ?>"><?= $guru['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td><div class="export-actions"><a href="monitoring.php?guru_id=<?= (int)$guru['id'] ?>" class="btn btn-sm btn-outline-primary">Lihat</a><a title="PDF guru" href="monitoring_export.php?jenis=guru&amp;format=pdf&amp;id=<?= (int)$guru['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-file-pdf"></i></a><a title="Excel guru" href="monitoring_export.php?jenis=guru&amp;format=excel&amp;id=<?= (int)$guru['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-excel"></i></a></div></td>
                        </tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>

            <div class="col-xl-6 monitor-panel" data-panel="siswa">
                <div class="card content-card p-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h6 class="fw-bold mb-0">Monitoring Siswa</h6><div class="export-actions"><a href="monitoring_export.php?jenis=siswa&amp;format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf me-1"></i> Semua PDF</a><a href="monitoring_export.php?jenis=siswa&amp;format=excel" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel me-1"></i> Semua Excel</a></div></div>
                    <div class="table-responsive"><table id="tableSiswaMonitoring" class="table table-hover align-middle w-100"><thead class="table-light"><tr><th>Siswa</th><th>Kelas</th><th>Aktivitas</th><th>Detail</th></tr></thead><tbody>
                        <?php foreach ($siswa_list as $siswa): ?><tr>
                            <td><strong><?= sanitize($siswa['nama_lengkap']) ?></strong><br><small><?= sanitize($siswa['username']) ?></small></td>
                            <td><?= sanitize($siswa['nama_kelas'] ?: '-') ?></td>
                            <td><small>Tugas <?= (int)$siswa['tugas_dikumpulkan'] ?> · Ujian <?= (int)$siswa['ujian_selesai'] ?> · Hadir <?= (int)$siswa['hadir_absensi'] ?> · Alpa <?= (int)$siswa['alpa_absensi'] ?></small></td>
                            <td><div class="export-actions"><a href="monitoring.php?siswa_id=<?= (int)$siswa['id'] ?>" class="btn btn-sm btn-outline-primary">Lihat</a><a title="PDF siswa" href="monitoring_export.php?jenis=siswa&amp;format=pdf&amp;id=<?= (int)$siswa['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-file-pdf"></i></a><a title="Excel siswa" href="monitoring_export.php?jenis=siswa&amp;format=excel&amp;id=<?= (int)$siswa['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-excel"></i></a></div></td>
                        </tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6 monitor-panel" data-panel="aktivitas"><div class="card content-card p-4 h-100"><h6 class="fw-bold mb-3">Aktivitas Pembelajaran Terbaru</h6><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Jenis</th><th>Aktivitas</th><th>Guru/Kelas</th><th>Waktu</th></tr></thead><tbody>
                <?php foreach ($aktivitas_pembelajaran as $item): ?><tr><td><span class="badge bg-secondary"><?= sanitize($item['jenis']) ?></span></td><td><?= sanitize($item['aktivitas']) ?><br><small><?= sanitize($item['nama_mapel']) ?></small></td><td><?= sanitize($item['pelaku']) ?><br><small><?= sanitize($item['nama_kelas']) ?></small></td><td><small><?= date('d/m/Y H:i', strtotime($item['waktu'])) ?></small></td></tr><?php endforeach; ?>
            </tbody></table></div><?php if (!$aktivitas_pembelajaran): ?><p class="text-muted text-center">Belum ada aktivitas.</p><?php endif; ?></div></div>

            <div class="col-xl-6 monitor-panel" data-panel="absensi"><div class="card content-card p-4 h-100"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h6 class="fw-bold mb-0">Monitoring Absensi Terbaru</h6><div class="export-actions"><a href="monitoring_export.php?jenis=absensi&amp;format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf me-1"></i> Semua PDF</a><a href="monitoring_export.php?jenis=absensi&amp;format=excel" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel me-1"></i> Semua Excel</a></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Pembelajaran</th><th>Status</th><th>Rekap</th></tr></thead><tbody>
                <?php foreach ($sesi_absensi as $sesi): ?><tr><td><strong><?= sanitize($sesi['nama_mapel']) ?></strong><br><small><?= sanitize($sesi['nama_kelas']) ?> · Pertemuan <?= (int)$sesi['pertemuan_ke'] ?> · <?= sanitize($sesi['nama_guru']) ?></small></td><td><span class="badge bg-<?= $sesi['status'] === 'Dibuka' ? 'success' : 'secondary' ?>"><?= sanitize($sesi['status']) ?></span></td><td><small>H <?= (int)$sesi['hadir'] ?> · S <?= (int)$sesi['sakit'] ?> · I <?= (int)$sesi['izin'] ?> · A <?= (int)$sesi['alpa'] ?></small></td></tr><?php endforeach; ?>
            </tbody></table></div><?php if (!$sesi_absensi): ?><p class="text-muted text-center">Belum ada sesi absensi.</p><?php endif; ?></div></div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(function () {
    $('#tableGuruMonitoring, #tableSiswaMonitoring').DataTable({ pageLength: 10 });

    $('.monitor-tab').on('click', function () {
        const target = $(this).data('target');
        $('.monitor-tab').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('.monitor-panel').removeClass('active');
        $('.monitor-panel[data-panel="' + target + '"]').addClass('active');
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });
});
</script>
