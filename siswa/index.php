<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/auth.php';

// Dashboard ini hanya dapat diakses oleh Siswa (role_id = 3).
check_access([3]);

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// 1. Ambil Data Detail Siswa & Kelas
$stmt_siswa = $db->prepare("
    SELECT s.*, k.nama_kelas, k.tingkat 
    FROM siswa s 
    JOIN kelas k ON s.kelas_id = k.id 
    WHERE s.user_id = ?
");
$stmt_siswa->execute([$user_id]);
$siswa = $stmt_siswa->fetch();

$siswa_id = $siswa['id'] ?? 0;
$kelas_id = $siswa['kelas_id'] ?? 0;

// Materi yang belum pernah dibuka siswa.
$stmt_materi_baru=$db->prepare("SELECT COUNT(*) FROM materi mat JOIN pengajaran p ON p.id=mat.pengajaran_id LEFT JOIN materi_siswa_dibaca md ON md.materi_id=mat.id AND md.siswa_id=? WHERE p.kelas_id=? AND md.materi_id IS NULL");
$stmt_materi_baru->execute([$siswa_id,$kelas_id]);$materi_baru=(int)$stmt_materi_baru->fetchColumn();

// Sesi absensi yang sedang berlangsung dan belum di-check-in siswa.
$stmt_absensi_menunggu=$db->prepare("SELECT COUNT(*) FROM sesi_absensi sa JOIN pengajaran p ON p.id=sa.pengajaran_id LEFT JOIN detail_absensi da ON da.sesi_absensi_id=sa.id AND da.siswa_id=? WHERE p.kelas_id=? AND sa.status='Dibuka' AND NOW() BETWEEN sa.waktu_buka AND sa.waktu_tutup AND da.id IS NULL");
$stmt_absensi_menunggu->execute([$siswa_id,$kelas_id]);$absensi_menunggu=(int)$stmt_absensi_menunggu->fetchColumn();

// 2. Ambil Ringkasan Statistik Siswa
// Total Tugas Aktif (Belum Dikerjakan/Dikumpulkan)
$stmt_tugas = $db->prepare("
    SELECT COUNT(*) 
    FROM tugas t
    JOIN pengajaran p ON t.pengajaran_id = p.id
    WHERE p.kelas_id = ? 
    AND t.deadline >= NOW()
    AND t.id NOT IN (SELECT tugas_id FROM pengumpulan_tugas WHERE siswa_id = ?)
");
$stmt_tugas->execute([$kelas_id, $siswa_id]);
$tugas_aktif = $stmt_tugas->fetchColumn();

// Total Ujian/Kuis Mendatang
$stmt_ujian = $db->prepare("
    SELECT COUNT(*)
    FROM ujian u
    JOIN pengajaran p ON u.pengajaran_id = p.id
    LEFT JOIN sesi_ujian su ON su.ujian_id=u.id AND su.siswa_id=?
    WHERE p.kelas_id = ? AND u.waktu_selesai >= NOW() AND (su.id IS NULL OR su.status<>'Selesai')
");
$stmt_ujian->execute([$siswa_id,$kelas_id]);
$ujian_aktif = $stmt_ujian->fetchColumn();

// 3. Ambil Daftar Ujian Online Mendatang / Berlangsung
$stmt_cbt = $db->prepare("
    SELECT u.*, m.nama_mapel, g.nama_lengkap as nama_guru
    FROM ujian u
    JOIN pengajaran p ON u.pengajaran_id = p.id
    JOIN mapel m ON p.mapel_id = m.id
    JOIN guru g ON p.guru_id = g.id
    LEFT JOIN sesi_ujian su ON su.ujian_id=u.id AND su.siswa_id=?
    WHERE p.kelas_id = ? AND u.waktu_selesai >= NOW() AND (su.id IS NULL OR su.status<>'Selesai')
    ORDER BY u.waktu_mulai ASC
    LIMIT 5
");
$stmt_cbt->execute([$siswa_id,$kelas_id]);
$daftar_ujian = $stmt_cbt->fetchAll();

// Tugas terdekat yang belum dikumpulkan, agar siswa langsung melihat prioritasnya.
$stmt_tugas_dekat = $db->prepare("
    SELECT t.id, t.judul, t.deadline, t.pertemuan_ke, m.nama_mapel
    FROM tugas t
    JOIN pengajaran p ON p.id = t.pengajaran_id
    JOIN mapel m ON m.id = p.mapel_id
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
    WHERE p.kelas_id = ? AND t.deadline >= NOW() AND pt.id IS NULL
    ORDER BY t.deadline ASC
    LIMIT 3
");
$stmt_tugas_dekat->execute([$siswa_id, $kelas_id]);
$tugas_terdekat = $stmt_tugas_dekat->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .student-dashboard { --student-blue:#2563eb; --student-bg:#f5f7fb; background:var(--student-bg); min-width:0; }
    .student-topbar { position:sticky; top:0; z-index:20; background:rgba(255,255,255,.96); backdrop-filter:blur(10px); border-bottom:1px solid #e9edf5; }
    .student-main { max-width:1180px; margin:0 auto; }
    .welcome-card { background:linear-gradient(135deg,#2563eb,#1d4ed8); border-radius:20px; color:#fff; overflow:hidden; position:relative; }
    .welcome-card::after { content:""; position:absolute; width:150px; height:150px; border-radius:50%; background:rgba(255,255,255,.09); right:-45px; bottom:-75px; }
    .quick-link { min-height:108px; border:1px solid #e8edf5; border-radius:16px; background:#fff; color:#172033; text-decoration:none; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; font-weight:650; box-shadow:0 5px 18px rgba(15,23,42,.05); transition:.2s ease; }
    .quick-link:active { transform:scale(.98); }
    .quick-icon { width:43px; height:43px; border-radius:13px; display:grid; place-items:center; font-size:1.1rem; }
    .compact-stat { border:0; border-radius:15px; box-shadow:0 5px 18px rgba(15,23,42,.05); }
    .section-card { border:0; border-radius:18px; box-shadow:0 5px 20px rgba(15,23,42,.05); }
    .item-row { text-decoration:none; color:inherit; display:flex; align-items:center; gap:12px; padding:14px 0; border-bottom:1px solid #edf0f5; min-width:0; }
    .item-row:last-child { border-bottom:0; }
    .item-copy { min-width:0; flex:1; }
    .item-copy strong,.item-copy small { overflow-wrap:anywhere; }
    @media (max-width:991.98px) {
        #page-content-wrapper { width:100%; min-width:0; }
    }
    @media (max-width:575.98px) {
        .student-topbar { padding:12px 14px!important; }
        .student-main { padding:14px!important; }
        .welcome-card { border-radius:17px; padding:18px!important; }
        .welcome-card h1 { font-size:1.18rem; line-height:1.35; }
        .welcome-meta { font-size:.78rem; }
        .quick-link { min-height:94px; border-radius:14px; font-size:.88rem; }
        .section-card { border-radius:16px; }
        .section-card .card-body { padding:17px!important; }
        .compact-stat .card-body { padding:12px!important; }
        .compact-stat h3 { font-size:1.35rem; }
        .compact-stat small { font-size:.7rem; }
    }
</style>
<div id="page-content-wrapper" class="student-dashboard">
    <nav class="student-topbar px-4 py-3">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="min-w-0"><strong class="d-block text-truncate">Dashboard Siswa</strong><small class="text-muted d-block text-truncate"><?= sanitize($siswa['nama_kelas'] ?? 'Kelas belum ditentukan') ?></small></div>
            </div>
            <div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-sm btn-outline-primary d-none" data-install-pwa><i class="fa-solid fa-mobile-screen-button me-1"></i>Pasang</button><img src="../assets/img/jb-mobile.png" width="38" height="38" class="object-fit-contain flex-shrink-0" alt="Logo sekolah"></div>
        </div>
    </nav>

    <main class="student-main p-3 p-md-4">
        <?php if($absensi_menunggu): ?><a href="absensi.php" class="alert alert-warning border-warning d-flex align-items-center gap-3 text-decoration-none text-dark shadow-sm"><span class="quick-icon bg-warning text-dark flex-shrink-0"><i class="fa-solid fa-bell fa-shake"></i></span><span class="flex-grow-1"><strong class="d-block">Absensi sedang dibuka!</strong><small>Anda memiliki <?= $absensi_menunggu ?> sesi yang belum di-check-in. Ketuk di sini sebelum waktunya berakhir.</small></span><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
        <section class="welcome-card p-4 mb-3">
            <small class="opacity-75">Selamat datang 👋</small>
            <h1 class="h4 fw-bold mb-2 position-relative"><?= sanitize($siswa['nama_lengkap'] ?? $_SESSION['username']) ?></h1>
            <div class="welcome-meta opacity-75 position-relative">NIS <?= sanitize($siswa['nis'] ?? '-') ?> &middot; NISN <?= sanitize($siswa['nisn'] ?? '-') ?></div>
        </section>

        <section class="mb-4" aria-labelledby="menuCepat"><h2 class="h6 fw-bold mb-3" id="menuCepat">Mau belajar apa hari ini?</h2><div class="row g-2">
            <div class="col-6 col-md-3"><a href="materi.php" class="quick-link"><span class="quick-icon bg-primary-subtle text-primary position-relative"><i class="fa-solid fa-book-open-reader"></i><?php if($materi_baru): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $materi_baru>99?'99+':$materi_baru ?><span class="visually-hidden">materi baru</span></span><?php endif ?></span><span>Materi<?php if($materi_baru): ?> <small class="text-danger fw-bold"><?= $materi_baru ?> baru</small><?php endif ?></span></a></div>
            <div class="col-6 col-md-3"><a href="tugas.php" class="quick-link"><span class="quick-icon bg-warning-subtle text-warning"><i class="fa-solid fa-list-check"></i></span><span>Tugas</span></a></div>
            <div class="col-6 col-md-3"><a href="absensi.php" class="quick-link"><span class="quick-icon bg-success-subtle text-success position-relative"><i class="fa-solid fa-user-check"></i><?php if($absensi_menunggu): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $absensi_menunggu ?><span class="visually-hidden">absensi menunggu check-in</span></span><?php endif ?></span><span>Absensi<?php if($absensi_menunggu): ?> <small class="text-danger fw-bold">Check-in!</small><?php endif ?></span></a></div>
            <div class="col-6 col-md-3"><a href="ujian.php" class="quick-link"><span class="quick-icon bg-danger-subtle text-danger"><i class="fa-solid fa-file-pen"></i></span><span>Ujian</span></a></div>
        </div></section>

        <div class="row g-2 mb-4">
            <div class="col-6"><div class="card compact-stat h-100"><div class="card-body text-center"><h3 class="fw-bold text-warning mb-0"><?= (int)$tugas_aktif ?></h3><small class="text-muted">Tugas belum selesai</small></div></div></div>
            <div class="col-6"><div class="card compact-stat h-100"><div class="card-body text-center"><h3 class="fw-bold text-danger mb-0"><?= (int)$ujian_aktif ?></h3><small class="text-muted">Ujian menunggu</small></div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6"><section class="card section-card h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 fw-bold mb-0"><i class="fa-solid fa-clock text-warning me-2"></i>Tugas Terdekat</h2><a href="tugas.php" class="small text-decoration-none">Lihat semua</a></div>
                <?php if(!$tugas_terdekat): ?><div class="text-center py-4"><i class="fa-solid fa-circle-check text-success fa-2x mb-2"></i><p class="small text-muted mb-0">Tidak ada tugas yang menunggu.</p></div><?php else: foreach($tugas_terdekat as $t): ?>
                    <a href="tugas.php" class="item-row"><span class="quick-icon bg-warning-subtle text-warning flex-shrink-0"><i class="fa-solid fa-file-lines"></i></span><span class="item-copy"><strong class="d-block"><?= sanitize($t['judul']) ?></strong><small class="text-muted d-block"><?= sanitize($t['nama_mapel']) ?> · Pertemuan <?= (int)$t['pertemuan_ke'] ?></small><small class="text-danger"><i class="fa-regular fa-clock me-1"></i><?= date('d M, H:i',strtotime($t['deadline'])) ?></small></span><i class="fa-solid fa-chevron-right text-muted small"></i></a>
                <?php endforeach; endif; ?>
            </div></section></div>

            <div class="col-lg-6" id="jadwal-ujian"><section class="card section-card h-100"><div class="card-body p-4"><div class="d-flex justify-content-between"><h2 class="h6 fw-bold mb-2"><i class="fa-solid fa-calendar-check text-danger me-2"></i>Jadwal Ujian</h2><a href="ujian.php" class="small text-decoration-none">Lihat semua</a></div>
                <?php if(!$daftar_ujian): ?><div class="text-center py-4"><i class="fa-solid fa-calendar-check text-success fa-2x mb-2"></i><p class="small text-muted mb-0">Belum ada kuis yang dijadwalkan.</p></div><?php else: foreach($daftar_ujian as $u): $belum_mulai=strtotime($u['waktu_mulai'])>time(); ?>
                    <div class="item-row"><span class="quick-icon bg-danger-subtle text-danger flex-shrink-0"><i class="fa-solid fa-file-pen"></i></span><span class="item-copy"><strong class="d-block"><?= sanitize($u['nama_ujian']) ?></strong><small class="text-muted d-block"><?= sanitize($u['nama_mapel']) ?> · <?= (int)$u['durasi_menit'] ?> menit</small><small class="<?= $belum_mulai?'text-muted':'text-success fw-semibold' ?>"><?= $belum_mulai?'Mulai '.date('d M, H:i',strtotime($u['waktu_mulai'])):'Sedang berlangsung' ?></small></span><a href="ujian_kerjakan.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm <?= $belum_mulai?'btn-light disabled':'btn-danger' ?>"><?= $belum_mulai?'Nanti':'Mulai' ?></a></div>
                <?php endforeach; endif; ?>
            </div></section></div>

        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
