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
if (!$siswa) {
    http_response_code(404);
    exit('Data siswa atau kelas tidak ditemukan.');
}

$siswa_id = $siswa['id'] ?? 0;
$kelas_id = $siswa['kelas_id'] ?? 0;

// Kredit Aktivitas Siswa dihitung terhadap target akhir tiga tahun (X+XI+XII).
$stmt_kredit = $db->prepare('SELECT COALESCE(SUM(poin),0) FROM kak_aktivitas_siswa WHERE siswa_id=?');
$stmt_kredit->execute([$siswa_id]);
$total_poin_kredit = (int)$stmt_kredit->fetchColumn();
$target_kredit_per_tingkat = ['X'=>0,'XI'=>0,'XII'=>0];
foreach ($db->query('SELECT tingkat,target_poin FROM kak_target_tingkat')->fetchAll() as $target_kredit) {
    $target_kredit_per_tingkat[$target_kredit['tingkat']] = (int)$target_kredit['target_poin'];
}
$target_kredit_kumulatif = array_sum($target_kredit_per_tingkat);
$persentase_kredit = $target_kredit_kumulatif > 0
    ? min(100, (int)round($total_poin_kredit / $target_kredit_kumulatif * 100))
    : 0;

// Materi yang belum pernah dibuka siswa.
$stmt_materi_baru=$db->prepare("SELECT COUNT(*) AS total, COUNT(md.materi_id) AS selesai FROM materi mat JOIN pengajaran p ON p.id=mat.pengajaran_id JOIN akses_pertemuan ap ON ap.pengajaran_id=mat.pengajaran_id AND ap.pertemuan_ke=mat.pertemuan_ke AND ap.status='Dibuka' LEFT JOIN materi_siswa_dibaca md ON md.materi_id=mat.id AND md.siswa_id=? WHERE p.kelas_id=?");
$stmt_materi_baru->execute([$siswa_id,$kelas_id]);$progress_materi=$stmt_materi_baru->fetch();
$materi_baru=(int)$progress_materi['total']-(int)$progress_materi['selesai'];

// Sesi absensi yang sedang berlangsung dan belum di-check-in siswa.
$stmt_absensi_menunggu=$db->prepare("SELECT COUNT(*) FROM sesi_absensi sa JOIN pengajaran p ON p.id=sa.pengajaran_id LEFT JOIN detail_absensi da ON da.sesi_absensi_id=sa.id AND da.siswa_id=? WHERE p.kelas_id=? AND sa.status='Dibuka' AND NOW() BETWEEN sa.waktu_buka AND sa.waktu_tutup AND da.id IS NULL");
$stmt_absensi_menunggu->execute([$siswa_id,$kelas_id]);$absensi_menunggu=(int)$stmt_absensi_menunggu->fetchColumn();

// Kehadiran hari ini mengikuti sesi dan status existing; dashboard hanya membaca.
$tanggal_kehadiran = date('Y-m-d');
$stmt_kehadiran_hari_ini = $db->prepare("
    SELECT sa.id, sa.pertemuan_ke, sa.waktu_buka, m.nama_mapel, g.nama_lengkap AS nama_guru, da.status
    FROM sesi_absensi sa
    JOIN pengajaran p ON p.id = sa.pengajaran_id
    JOIN mapel m ON m.id = p.mapel_id
    JOIN guru g ON g.id = p.guru_id
    LEFT JOIN detail_absensi da ON da.sesi_absensi_id = sa.id AND da.siswa_id = ?
    WHERE p.kelas_id = ? AND sa.tanggal = ?
    ORDER BY sa.waktu_buka ASC, sa.id ASC
");
$stmt_kehadiran_hari_ini->execute([$siswa_id, $kelas_id, $tanggal_kehadiran]);
$kehadiran_hari_ini = $stmt_kehadiran_hari_ini->fetchAll();
$ringkasan_kehadiran = [];
foreach ($kehadiran_hari_ini as $sesi_kehadiran) {
    $status_ringkas = $sesi_kehadiran['status'] ?: 'Belum Absen';
    $ringkasan_kehadiran[$status_ringkas] = ($ringkasan_kehadiran[$status_ringkas] ?? 0) + 1;
}

$tampilan_kehadiran = [
    'Hadir' => ['success', 'fa-circle-check'],
    'Izin' => ['primary', 'fa-circle-info'],
    'Sakit' => ['warning', 'fa-notes-medical'],
    'Alpa' => ['danger', 'fa-circle-xmark'],
    'Belum Absen' => ['secondary', 'fa-clock'],
];

// 3. Ambil Daftar Ulangan Harian Online Mendatang / Berlangsung
$stmt_cbt = $db->prepare("
    SELECT u.*, m.nama_mapel, g.nama_lengkap as nama_guru, su.status AS status_sesi
    FROM ujian u
    JOIN pengajaran p ON u.pengajaran_id = p.id
    JOIN mapel m ON p.mapel_id = m.id
    JOIN guru g ON p.guru_id = g.id
    LEFT JOIN sesi_ujian su ON su.ujian_id=u.id AND su.siswa_id=?
    WHERE p.kelas_id = ? AND u.waktu_selesai >= NOW() AND (su.id IS NULL OR su.status<>'Selesai')
    ORDER BY u.waktu_mulai ASC
    LIMIT 3
");
$stmt_cbt->execute([$siswa_id,$kelas_id]);
$daftar_ujian = $stmt_cbt->fetchAll();

// Prioritaskan tugas aktif, lalu terlambat, dan terakhir yang sudah dikumpulkan.
$stmt_tugas_dekat = $db->prepare("
    SELECT t.id, t.judul, t.deadline, t.pertemuan_ke, m.nama_mapel,
           pt.id AS pengumpulan_id, (t.deadline < NOW()) AS lewat_deadline
    FROM tugas t
    JOIN pengajaran p ON p.id = t.pengajaran_id
    JOIN mapel m ON m.id = p.mapel_id
    JOIN akses_pertemuan ap ON ap.pengajaran_id=t.pengajaran_id
                           AND ap.pertemuan_ke=t.pertemuan_ke AND ap.status='Dibuka'
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
    WHERE p.kelas_id = ?
    AND (NOT EXISTS(
        SELECT 1 FROM materi mat
        WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke
    ) OR (
        EXISTS(
            SELECT 1 FROM materi mat JOIN materi_siswa_dibaca msb
            ON msb.materi_id=mat.id AND msb.siswa_id=?
            WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke
        )
    ))
    ORDER BY CASE WHEN pt.id IS NOT NULL THEN 2 WHEN t.deadline < NOW() THEN 1 ELSE 0 END,
             CASE WHEN pt.id IS NULL AND t.deadline >= NOW() THEN t.deadline END ASC,
             t.deadline DESC, t.id DESC
    LIMIT 3
");
$stmt_tugas_dekat->execute([$siswa_id, $kelas_id, $siswa_id]);
$tugas_terdekat = $stmt_tugas_dekat->fetchAll();

// Filter progres hanya untuk pengajaran kelas siswa yang sedang login.
$stmt_progress_mapel = $db->prepare("SELECT p.id, m.nama_mapel, g.nama_lengkap AS nama_guru, p.semester, p.tahun_ajaran FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id WHERE p.kelas_id=? ORDER BY m.nama_mapel, g.nama_lengkap, p.tahun_ajaran DESC, p.semester");
$stmt_progress_mapel->execute([$kelas_id]);
$progress_mapel_list = $stmt_progress_mapel->fetchAll();
$progress_pengajaran_id = (int)($_GET['progress_pengajaran_id'] ?? 0);
if (!in_array($progress_pengajaran_id, array_map('intval', array_column($progress_mapel_list, 'id')), true)) $progress_pengajaran_id = 0;
$filter_progress = $progress_pengajaran_id > 0 ? ' AND p.id=' . $progress_pengajaran_id : '';
if ($progress_pengajaran_id > 0) {
    $stmt_progress_materi = $db->prepare("SELECT COUNT(*) AS total, COUNT(md.materi_id) AS selesai FROM materi mat JOIN pengajaran p ON p.id=mat.pengajaran_id JOIN akses_pertemuan ap ON ap.pengajaran_id=mat.pengajaran_id AND ap.pertemuan_ke=mat.pertemuan_ke AND ap.status='Dibuka' LEFT JOIN materi_siswa_dibaca md ON md.materi_id=mat.id AND md.siswa_id=? WHERE p.kelas_id=? AND p.id=?");
    $stmt_progress_materi->execute([$siswa_id, $kelas_id, $progress_pengajaran_id]);
    $progress_materi = $stmt_progress_materi->fetch();
}

// Agregasi progres sekali per kategori, tanpa menyimpan hasil atau query di loop.
$stmt_progress_tugas = $db->prepare("
    SELECT COUNT(*) AS total, COUNT(pt.id) AS selesai
    FROM tugas t
    JOIN pengajaran p ON p.id=t.pengajaran_id
    JOIN akses_pertemuan ap ON ap.pengajaran_id=t.pengajaran_id
        AND ap.pertemuan_ke=t.pertemuan_ke AND ap.status='Dibuka'
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.siswa_id=?
    WHERE p.kelas_id=? $filter_progress
    AND (NOT EXISTS(
        SELECT 1 FROM materi mat WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke
    ) OR EXISTS(
        SELECT 1 FROM materi mat JOIN materi_siswa_dibaca md ON md.materi_id=mat.id AND md.siswa_id=?
        WHERE mat.pengajaran_id=t.pengajaran_id AND mat.pertemuan_ke=t.pertemuan_ke
    ))
");
$stmt_progress_tugas->execute([$siswa_id, $kelas_id, $siswa_id]);
$progress_tugas = $stmt_progress_tugas->fetch();

$stmt_progress_ulangan = $db->prepare("
    SELECT COUNT(*) AS total, COALESCE(SUM(su.status='Selesai'),0) AS selesai
    FROM ujian u
    JOIN pengajaran p ON p.id=u.pengajaran_id
    LEFT JOIN sesi_ujian su ON su.ujian_id=u.id AND su.siswa_id=?
    WHERE p.kelas_id=? $filter_progress AND u.waktu_mulai<=NOW()
");
$stmt_progress_ulangan->execute([$siswa_id, $kelas_id]);
$progress_ulangan = $stmt_progress_ulangan->fetch();

$stmt_progress_kehadiran = $db->prepare("
    SELECT COUNT(*) AS total, COALESCE(SUM(da.status='Hadir'),0) AS selesai
    FROM detail_absensi da
    JOIN sesi_absensi sa ON sa.id=da.sesi_absensi_id
    JOIN pengajaran p ON p.id=sa.pengajaran_id
    WHERE da.siswa_id=? AND p.kelas_id=? $filter_progress AND sa.tanggal<=?
");
$stmt_progress_kehadiran->execute([$siswa_id, $kelas_id, $tanggal_kehadiran]);
$progress_kehadiran = $stmt_progress_kehadiran->fetch();

$progress_belajar = [
    ['label'=>'Tugas', 'data'=>$progress_tugas, 'aksi'=>'dikumpulkan', 'catatan'=>'Tugas yang sudah dapat diakses', 'warna'=>'primary', 'ikon'=>'fa-list-check'],
    ['label'=>'Materi', 'data'=>$progress_materi, 'aksi'=>'dibuka', 'catatan'=>'Materi pada pertemuan terbuka', 'warna'=>'info', 'ikon'=>'fa-book-open-reader'],
    ['label'=>'Ulangan', 'data'=>$progress_ulangan, 'aksi'=>'selesai', 'catatan'=>'Ulangan yang sudah memasuki waktu mulai', 'warna'=>'danger', 'ikon'=>'fa-file-pen'],
    ['label'=>'Kehadiran', 'data'=>$progress_kehadiran, 'aksi'=>'sesi hadir', 'catatan'=>'Dari absensi yang sudah tercatat', 'warna'=>'success', 'ikon'=>'fa-user-check'],
];
$progress_belajar = array_values(array_filter($progress_belajar, static fn($item) => (int)$item['data']['total'] > 0));


// Nilai milik siswa dari session tervalidasi; tanggal aktivitas existing, bukan tanggal penilaian.
$stmt_nilai_terbaru = $db->prepare("
    SELECT jenis, nama_mapel, nama_aktivitas, nilai, tanggal
    FROM (
        SELECT 'Tugas' AS jenis, pt.id AS sumber_id, m.nama_mapel,
               t.judul AS nama_aktivitas, pt.nilai, pt.dikumpulkan_pada AS tanggal
        FROM pengumpulan_tugas pt
        JOIN tugas t ON t.id=pt.tugas_id
        JOIN pengajaran p ON p.id=t.pengajaran_id
        JOIN mapel m ON m.id=p.mapel_id
        WHERE pt.siswa_id=? AND p.kelas_id=? AND pt.nilai IS NOT NULL
        UNION ALL
        SELECT 'Ulangan' AS jenis, nu.id AS sumber_id, m.nama_mapel,
               u.nama_ujian AS nama_aktivitas, nu.nilai_total AS nilai, nu.selesai_pada AS tanggal
        FROM nilai_ujian nu
        JOIN ujian u ON u.id=nu.ujian_id
        JOIN pengajaran p ON p.id=u.pengajaran_id
        JOIN mapel m ON m.id=p.mapel_id
        WHERE nu.siswa_id=? AND p.kelas_id=? AND nu.nilai_total IS NOT NULL
          AND EXISTS (SELECT 1 FROM sesi_ujian su WHERE su.ujian_id=nu.ujian_id
                      AND su.siswa_id=nu.siswa_id AND su.status='Selesai')
    ) nilai_siswa
    ORDER BY tanggal DESC, jenis ASC, sumber_id DESC
    LIMIT 5
");
$stmt_nilai_terbaru->execute([$siswa_id, $kelas_id, $siswa_id, $kelas_id]);
$nilai_terbaru = $stmt_nilai_terbaru->fetchAll();



?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .student-dashboard {
        --student-blue:#2563eb; --student-bg:#f5f7fb; --student-radius:16px;
        --student-shadow:0 3px 12px rgba(15,23,42,.045);
        background:var(--student-bg); min-width:0; line-height:1.5;
    }
    .student-dashboard .student-topbar { position:sticky; top:0; z-index:20; background:rgba(255,255,255,.96); backdrop-filter:blur(10px); border-bottom:1px solid #e9edf5; }
    .student-dashboard .min-w-0 { min-width:0; }
    .student-dashboard .student-main { width:100%; max-width:1180px; margin:0 auto; }
    .student-dashboard .welcome-card {
        min-height:156px; border-radius:var(--student-radius); color:#fff; overflow:hidden; position:relative;
        background-image:linear-gradient(110deg,rgba(23,37,84,.94) 0%,rgba(29,78,216,.82) 58%,rgba(37,99,235,.64) 100%),url('../assets/img/batikjb.webp');
        background-size:cover; background-position:center; background-repeat:no-repeat;
        display:flex; flex-direction:column; justify-content:center; box-shadow:var(--student-shadow);
    }
    .student-dashboard .welcome-card::after { content:""; position:absolute; inset:0; background:linear-gradient(90deg,rgba(15,23,42,.18),transparent 70%); pointer-events:none; }
    .student-dashboard .welcome-card > * { position:relative; z-index:1; }
    .student-dashboard .welcome-layout { display:flex; flex-wrap:wrap; align-items:center; gap:24px; }
    .student-dashboard .welcome-profile { flex:1 1 240px; min-width:0; overflow-wrap:anywhere; }
    .student-dashboard .welcome-card h1 { margin-top:6px; line-height:1.35; }
    .student-dashboard .welcome-meta { font-size:.85rem; }
    .student-dashboard .student-credit-link { flex:1 1 310px; max-width:410px; min-width:0; min-height:84px; padding:14px 16px; border:1px solid rgba(255,255,255,.85); border-radius:var(--student-radius); background:rgba(255,255,255,.97); color:#1d4ed8; text-decoration:none; display:flex; align-items:center; gap:14px; box-shadow:var(--student-shadow); transition:background .18s ease,box-shadow .18s ease; }
    .student-dashboard .student-credit-link:hover { color:#1d4ed8; background:#fff; box-shadow:0 4px 14px rgba(15,23,42,.08); }
    .student-dashboard .student-credit-score { width:54px; height:54px; flex:0 0 54px; padding:4px; border-radius:12px; display:grid; place-items:center; color:#fff; background:linear-gradient(135deg,#2563eb,#1d4ed8); font-size:1.6rem; line-height:1; font-weight:800; font-variant-numeric:tabular-nums; letter-spacing:-.04em; }
    .student-dashboard .student-credit-score.is-long { font-size:1.05rem; }
    .student-dashboard .student-credit-copy { min-width:0; overflow-wrap:anywhere; }
    .student-dashboard .student-credit-copy strong { display:block; font-size:1rem; line-height:1.4; }
    .student-dashboard .student-credit-copy small { display:block; margin-top:4px; color:#64748b; font-size:.8rem; }
    .student-dashboard .student-credit-copy small b { color:#1e40af; }
    .student-dashboard .quick-link { height:100%; min-height:112px; padding:16px 12px; border:1px solid #e8edf5; border-radius:var(--student-radius); background:#fff; color:#172033; text-decoration:none; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; font-size:.95rem; font-weight:600; box-shadow:var(--student-shadow); transition:border-color .18s ease,background .18s ease; }
    .student-dashboard .quick-link:hover { border-color:#b6ccf5; background:#f8faff; }
    .student-dashboard .quick-icon { width:44px; height:44px; flex:0 0 44px; border-radius:12px; display:grid; place-items:center; font-size:1.1rem; }
    .student-dashboard .quick-icon > i { width:1.25em; text-align:center; }
    .student-dashboard .section-card { border:1px solid #e8edf5; border-radius:var(--student-radius); box-shadow:var(--student-shadow); }
    .student-dashboard .section-heading { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:4px 12px; margin-bottom:8px; }
    .student-dashboard .section-heading h2 { flex:1 1 160px; line-height:1.5; }
    .student-dashboard .section-heading h2 i { display:inline-block; width:1.25em; text-align:center; }
    .student-dashboard .section-heading > a { display:inline-flex; align-items:center; min-height:44px; padding:8px 0; flex-shrink:0; font-weight:600; }
    .student-dashboard .item-row { text-decoration:none; color:inherit; display:flex; align-items:center; gap:12px; padding:16px 0; border-bottom:1px solid #edf0f5; min-width:0; }
    .student-dashboard .item-row:last-child { border-bottom:0; padding-bottom:0; }
    .student-dashboard a.item-row:hover .item-copy strong { color:var(--student-blue); }
    .student-dashboard .item-copy { min-width:0; flex:1; }
    .student-dashboard .item-copy strong { font-size:.93rem; line-height:1.45; }
    .student-dashboard .item-copy small { display:block; margin-top:3px; font-size:.8rem; }
    .student-dashboard .item-copy strong,.student-dashboard .item-copy small { overflow-wrap:anywhere; }
    .student-dashboard .btn { min-height:44px; padding:10px 14px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; }
    .student-dashboard .item-row > .btn { flex-shrink:0; min-width:66px; }
    .student-dashboard a:focus-visible,.student-dashboard button:focus-visible { outline:3px solid var(--student-blue); outline-offset:3px; }
    .student-dashboard .welcome-card a:focus-visible { outline-color:#facc15; }
    .student-dashboard .attendance-alert { padding:16px; border-radius:var(--student-radius); margin-bottom:24px; box-shadow:var(--student-shadow)!important; }
    .student-dashboard .progress-subject-select { flex:1 1 240px; width:0; min-width:0; max-width:100%; }
    .student-dashboard #progressBelajar { scroll-margin-top:100px; }
    .student-dashboard .student-grade-strip { display:flex; gap:12px; overflow-x:auto; min-width:0; padding:4px 3px 12px; scroll-snap-type:x proximity; }
    .student-dashboard .student-grade-strip:focus-visible { outline:3px solid var(--student-blue); outline-offset:3px; border-radius:10px; }
    .student-dashboard .student-grade-card { flex:0 0 min(280px,85%); min-width:0; padding:16px; border:1px solid #e8edf5; border-radius:14px; background:#f8faff; display:grid; grid-template-columns:1fr auto; align-content:start; align-items:center; gap:12px; scroll-snap-align:start; }
    .student-dashboard .student-grade-card .item-copy { grid-column:1 / -1; grid-row:2; }
    .student-dashboard .student-grade-card > .badge { grid-column:2; grid-row:1; }
    .student-dashboard .student-learning-progress { height:8px; border-radius:8px; }
    .student-dashboard .attendance-today-details summary { cursor:pointer; min-height:44px; padding:12px 0; width:fit-content; }
    .student-dashboard .attendance-today-details summary:focus-visible { outline:3px solid var(--student-blue); outline-offset:3px; }
    .student-dashboard .attendance-today-scroll { max-height:260px; overflow-y:auto; padding-right:8px; scrollbar-gutter:stable; }
    .student-dashboard .attendance-today-list > li + li { border-top:1px solid #edf0f5; }
    .student-dashboard .attendance-today-copy { min-width:0; flex:1 1 160px; overflow-wrap:anywhere; }
    .student-dashboard .attendance-today-list .badge { flex-shrink:0; }
    @media (max-width:1199.98px) {
        .student-dashboard .student-credit-link { max-width:none; }
    }
    @media (max-width:575.98px) {
        .student-dashboard { --student-radius:14px; }
        .student-dashboard .student-topbar { padding:12px 16px!important; }
        .student-dashboard .student-main { padding:16px!important; }
        .student-dashboard .welcome-card { padding:20px!important; }
        .student-dashboard .welcome-layout { gap:20px; }
        .student-dashboard .welcome-profile { flex-basis:100%; }
        .student-dashboard .welcome-card h1 { font-size:1.2rem; }
        .student-dashboard .welcome-meta { font-size:.8rem; }
        .student-dashboard .student-credit-link { flex-basis:100%; padding:12px; gap:10px; }
        .student-dashboard .student-credit-score { width:48px; height:48px; flex-basis:48px; font-size:1.45rem; }
        .student-dashboard .student-credit-score.is-long { font-size:1rem; }
        .student-dashboard .student-credit-copy strong { font-size:.93rem; }
        .student-dashboard .quick-link { min-height:108px; font-size:.88rem; }
        .student-dashboard .section-card .card-body { padding:16px!important; }
        .student-dashboard .item-row { gap:10px; }
        .student-dashboard .exam-item { flex-wrap:wrap; }
        .student-dashboard .exam-item .item-copy { flex-basis:calc(100% - 54px); }
        .student-dashboard .exam-item > .btn { margin-left:54px; }
    }
    @media (prefers-reduced-motion:reduce) {
        .student-dashboard .quick-link,.student-dashboard .student-credit-link { transition:none; }
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
        <?php if($absensi_menunggu): ?><a href="absensi.php" class="attendance-alert alert alert-warning border-warning d-flex align-items-center gap-3 text-decoration-none text-dark shadow-sm"><span class="quick-icon bg-warning text-dark flex-shrink-0"><i class="fa-solid fa-bell"></i></span><span class="flex-grow-1"><strong class="d-block">Absensi sedang dibuka!</strong><small>Anda memiliki <?= $absensi_menunggu ?> sesi yang belum di-check-in. Ketuk di sini sebelum waktunya berakhir.</small></span><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
        <section class="welcome-card p-4 mb-4">
            <div class="welcome-layout">
                <div class="welcome-profile"><small class="opacity-75">Selamat datang 👋</small>
                <h1 class="h4 fw-bold mb-2 position-relative"><?= sanitize($siswa['nama_lengkap'] ?? $_SESSION['username']) ?></h1>
                <div class="welcome-meta opacity-75 position-relative">NIS <?= sanitize($siswa['nis'] ?? '-') ?> &middot; NISN <?= sanitize($siswa['nisn'] ?? '-') ?></div></div>
                <a href="kak.php" class="student-credit-link flex-shrink-0"><span class="student-credit-score <?= strlen((string)$total_poin_kredit) > 3 ? 'is-long' : '' ?>" aria-label="Total <?= $total_poin_kredit ?> poin"><?= $total_poin_kredit ?></span><span class="student-credit-copy"><strong>Kredit Aktivitas Siswa</strong><small><?php if($target_kredit_kumulatif > 0): ?><b><?= $persentase_kredit ?>%</b> target kelas X–XII<?php else: ?>Target kelas X–XII belum ditetapkan<?php endif; ?></small></span><i class="fa-solid fa-chevron-right ms-auto small"></i></a>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="menuCepat"><h2 class="h6 fw-bold mb-3" id="menuCepat">Mau belajar apa hari ini?</h2><div class="row g-3">
            <div class="col-6 col-md-3"><a href="materi.php" class="quick-link"><span class="quick-icon bg-primary-subtle text-primary position-relative"><i class="fa-solid fa-book-open-reader"></i><?php if($materi_baru): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $materi_baru>99?'99+':$materi_baru ?><span class="visually-hidden">materi baru</span></span><?php endif ?></span><span>Materi<?php if($materi_baru): ?> <small class="text-danger fw-bold"><?= $materi_baru ?> baru</small><?php endif ?></span></a></div>
            <div class="col-6 col-md-3"><a href="tugas.php" class="quick-link"><span class="quick-icon bg-warning-subtle text-warning"><i class="fa-solid fa-list-check"></i></span><span>Tugas</span></a></div>
            <div class="col-6 col-md-3"><a href="absensi.php" class="quick-link"><span class="quick-icon bg-success-subtle text-success position-relative"><i class="fa-solid fa-user-check"></i><?php if($absensi_menunggu): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $absensi_menunggu ?><span class="visually-hidden">absensi menunggu check-in</span></span><?php endif ?></span><span>Absensi<?php if($absensi_menunggu): ?> <small class="text-danger fw-bold">Check-in!</small><?php endif ?></span></a></div>
            <div class="col-6 col-md-3"><a href="ujian.php" class="quick-link"><span class="quick-icon bg-danger-subtle text-danger"><i class="fa-solid fa-file-pen"></i></span><span>Ulangan Harian</span></a></div>
        </div></section>

        <section class="card section-card mb-4" aria-labelledby="kehadiranHariIni">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <h2 class="h6 fw-bold mb-0" id="kehadiranHariIni"><i class="fa-solid fa-user-check text-success me-2" aria-hidden="true"></i>Status Kehadiran Hari Ini</h2>
                    <small class="text-muted"><?= date('d/m/Y', strtotime($tanggal_kehadiran)) ?></small>
                </div>
                <?php if (!$kehadiran_hari_ini): ?>
                    <p class="small text-muted mb-0">Belum ada data kehadiran hari ini.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php foreach ($ringkasan_kehadiran as $status_ringkas => $jumlah_sesi):
                            [$warna_ringkas, $ikon_ringkas] = $tampilan_kehadiran[$status_ringkas] ?? ['secondary', 'fa-circle-info'];
                        ?>
                            <span class="badge bg-<?= $warna_ringkas ?>-subtle text-<?= $warna_ringkas ?>-emphasis px-3 py-2"><i class="fa-solid <?= $ikon_ringkas ?> me-1" aria-hidden="true"></i><?= sanitize($status_ringkas) ?> &middot; <?= $jumlah_sesi ?> sesi</span>
                        <?php endforeach; ?>
                    </div>
                    <details class="attendance-today-details mt-2">
                        <summary class="small text-primary fw-semibold">Lihat detail <?= count($kehadiran_hari_ini) ?> sesi</summary>
                        <div class="attendance-today-scroll" tabindex="0" role="region" aria-label="Detail kehadiran per sesi hari ini">
                    <ul class="list-unstyled mb-0 attendance-today-list">
                        <?php foreach ($kehadiran_hari_ini as $kehadiran):
                            $label_kehadiran = $kehadiran['status'] ?: 'Belum Absen';
                            [$warna_kehadiran, $ikon_kehadiran] = $tampilan_kehadiran[$label_kehadiran] ?? ['secondary', 'fa-circle-info'];
                        ?>
                            <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                                <div class="attendance-today-copy">
                                    <strong class="small d-block"><?= sanitize($kehadiran['nama_mapel']) ?></strong>
                                    <small class="text-muted d-block"><?= sanitize($kehadiran['nama_guru']) ?></small>
                                    <small class="text-muted">Pertemuan <?= (int)$kehadiran['pertemuan_ke'] ?> &middot; <?= date('H:i', strtotime($kehadiran['waktu_buka'])) ?></small>
                                </div>
                                <span class="badge bg-<?= $warna_kehadiran ?>-subtle text-<?= $warna_kehadiran ?>-emphasis px-3 py-2"><i class="fa-solid <?= $ikon_kehadiran ?> me-1" aria-hidden="true"></i><?= sanitize($label_kehadiran) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($progress_mapel_list): ?>
            <section class="card section-card mb-4" aria-labelledby="progressBelajar">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h6 fw-bold mb-3" id="progressBelajar"><i class="fa-solid fa-chart-simple text-primary me-2" aria-hidden="true"></i>Progress Belajar</h2>
                    <form method="get" action="index.php#progressBelajar" class="mb-3">
                        <label for="progressMapel" class="form-label small text-muted">Mata pelajaran / guru</label>
                        <div class="d-flex flex-wrap gap-2">
                            <select id="progressMapel" name="progress_pengajaran_id" class="form-select progress-subject-select">
                                <option value="0">Semua mata pelajaran (gabungan)</option>
                                <?php foreach ($progress_mapel_list as $pilihan): ?>
                                    <option value="<?= (int)$pilihan['id'] ?>" <?= $progress_pengajaran_id === (int)$pilihan['id'] ? 'selected' : '' ?>><?= sanitize($pilihan['nama_mapel'].' / '.$pilihan['nama_guru'].' / '.$pilihan['semester'].' '.$pilihan['tahun_ajaran']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Tampilkan</button>
                        </div>
                    </form>
                    <?php if (!$progress_belajar): ?><p class="small text-muted mb-0">Belum ada data progress untuk mata pelajaran ini.</p><?php endif; ?>
                    <div class="row g-4">
                        <?php foreach ($progress_belajar as $progress):
                            $total_progress = (int)$progress['data']['total'];
                            $selesai_progress = (int)$progress['data']['selesai'];
                            $persen_progress = min(100, max(0, (int)round($selesai_progress / $total_progress * 100)));
                        ?>
                            <div class="col-12 col-sm-6 col-xl">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <strong class="small"><i class="fa-solid <?= $progress['ikon'] ?> text-<?= $progress['warna'] ?> me-2" aria-hidden="true"></i><?= $progress['label'] ?></strong>
                                    <span class="small fw-bold"><?= $persen_progress ?>%</span>
                                </div>
                                <p class="small text-muted mb-2"><?= $selesai_progress ?> / <?= $total_progress ?> <?= $progress['aksi'] ?></p>
                                <div class="progress student-learning-progress" role="progressbar" aria-label="<?= $progress['label'] ?>: <?= $selesai_progress ?> dari <?= $total_progress ?> <?= $progress['aksi'] ?>" aria-valuenow="<?= $persen_progress ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-<?= $progress['warna'] ?>" style="width:<?= $persen_progress ?>%"></div>
                                </div>
                                <small class="d-block text-muted mt-2"><?= $progress['catatan'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <section class="card section-card h-100" aria-labelledby="tugasTerdekat">
                    <div class="card-body p-4">
                        <div class="section-heading">
                            <h2 class="h6 fw-bold mb-0" id="tugasTerdekat"><i class="fa-solid fa-list-check text-warning me-2"></i>Tugas Terdekat</h2>
                            <a href="tugas.php" class="small text-decoration-none">Lihat semua</a>
                        </div>
                        <?php if (!$tugas_terdekat): ?>
                            <div class="text-center py-4"><i class="fa-solid fa-circle-check text-success fa-2x mb-2"></i><p class="small text-muted mb-0">Tidak ada tugas yang menunggu.</p></div>
                        <?php else: foreach ($tugas_terdekat as $t):
                            $selesai = $t['pengumpulan_id'] !== null;
                            $terlambat = !$selesai && (bool)$t['lewat_deadline'];
                            $status_tugas = $selesai ? 'Selesai' : ($terlambat ? 'Terlambat' : 'Belum dikerjakan');
                            $warna_status = $selesai ? 'success' : ($terlambat ? 'danger' : 'warning');
                        ?>
                            <article class="item-row exam-item">
                                <span class="quick-icon bg-warning-subtle text-warning"><i class="fa-solid fa-file-lines"></i></span>
                                <div class="item-copy">
                                    <strong class="d-block"><?= sanitize($t['judul']) ?></strong>
                                    <small class="text-muted"><?= sanitize($t['nama_mapel']) ?></small>
                                    <small class="text-muted">Deadline <?= date('d M Y, H:i', strtotime($t['deadline'])) ?></small>
                                    <span class="badge bg-<?= $warna_status ?>-subtle text-<?= $warna_status ?>-emphasis mt-2"><?= $status_tugas ?></span>
                                </div>
                                <?php if (!$selesai && !$terlambat): ?>
                                    <a href="tugas.php#tugas-<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">Kerjakan<span class="visually-hidden">: <?= sanitize($t['judul']) ?></span></a>
                                <?php else: ?>
                                    <a href="tugas.php#tugas-<?= (int)$t['id'] ?>" class="btn btn-sm btn-light">Lihat<span class="visually-hidden">: <?= sanitize($t['judul']) ?></span></a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; endif; ?>
                    </div>
                </section>
            </div>
            <div class="col-12 col-xl-6" id="jadwal-ujian">
                <section class="card section-card h-100" aria-labelledby="ulanganTerdekat">
                    <div class="card-body p-4">
                        <div class="section-heading">
                            <h2 class="h6 fw-bold mb-0" id="ulanganTerdekat"><i class="fa-solid fa-file-pen text-danger me-2"></i>Ulangan Terdekat</h2>
                            <a href="ujian.php" class="small text-decoration-none">Lihat semua</a>
                        </div>
                        <?php if (!$daftar_ujian): ?>
                            <div class="text-center py-4"><i class="fa-solid fa-calendar-check text-success fa-2x mb-2"></i><p class="small text-muted mb-0">Belum ada ulangan yang dijadwalkan.</p></div>
                        <?php else: foreach ($daftar_ujian as $u):
                            $sekarang = time();
                            $bisa_mulai = $sekarang >= strtotime($u['waktu_mulai']) && $sekarang <= strtotime($u['waktu_selesai']);
                        ?>
                            <article class="item-row exam-item">
                                <span class="quick-icon bg-danger-subtle text-danger"><i class="fa-solid fa-file-pen"></i></span>
                                <div class="item-copy">
                                    <strong class="d-block"><?= sanitize($u['nama_ujian']) ?></strong>
                                    <small class="text-muted"><?= sanitize($u['nama_mapel']) ?> &middot; <?= (int)$u['durasi_menit'] ?> menit</small>
                                    <small class="text-muted">Mulai <?= date('d M Y, H:i', strtotime($u['waktu_mulai'])) ?></small>
                                    <small class="text-muted">Selesai <?= date('d M Y, H:i', strtotime($u['waktu_selesai'])) ?></small>
                                    <span class="badge bg-<?= $bisa_mulai ? 'success' : 'secondary' ?>-subtle text-<?= $bisa_mulai ? 'success' : 'secondary' ?>-emphasis mt-2"><?= $bisa_mulai ? 'Sedang berlangsung' : ($sekarang < strtotime($u['waktu_mulai']) ? 'Belum dimulai' : 'Berakhir') ?></span>
                                </div>
                                <?php if ($bisa_mulai): ?>
                                    <a href="ujian_kerjakan.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-danger"><?= $u['status_sesi'] === 'Berlangsung' ? 'Lanjutkan' : 'Mulai' ?><span class="visually-hidden">: <?= sanitize($u['nama_ujian']) ?></span></a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; endif; ?>
                    </div>
                </section>
            </div>
        </div>
        <section class="card section-card mt-4" aria-labelledby="nilaiTerbaru">
            <div class="card-body p-3 p-md-4">
                <h2 class="h6 fw-bold mb-2" id="nilaiTerbaru"><i class="fa-solid fa-graduation-cap text-primary me-2" aria-hidden="true"></i>Nilai Terbaru</h2>
                <?php if (!$nilai_terbaru): ?>
                    <p class="small text-muted mb-0">Belum ada nilai terbaru.</p>
                <?php else: ?>
                    <p class="small text-muted mb-2">Berdasarkan tanggal pengumpulan tugas atau selesai ulangan.</p>
                    <div class="student-grade-strip" tabindex="0" role="region" aria-label="Daftar nilai terbaru, geser ke samping untuk melihat nilai lainnya">
                    <?php foreach ($nilai_terbaru as $hasil_nilai): ?>
                        <article class="student-grade-card">
                            <span class="quick-icon bg-primary-subtle text-primary"><i class="fa-solid <?= $hasil_nilai['jenis'] === 'Tugas' ? 'fa-list-check' : 'fa-file-pen' ?>" aria-hidden="true"></i></span>
                            <div class="item-copy">
                                <strong class="d-block"><?= sanitize($hasil_nilai['nama_aktivitas']) ?></strong>
                                <small class="text-muted"><?= sanitize($hasil_nilai['nama_mapel']) ?> &middot; <?= $hasil_nilai['jenis'] ?></small>
                                <?php if ($hasil_nilai['tanggal']): ?>
                                    <small class="text-muted"><?= $hasil_nilai['jenis'] === 'Tugas' ? 'Dikumpulkan' : 'Selesai' ?> <?= date('d M Y, H:i', strtotime($hasil_nilai['tanggal'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-primary-subtle text-primary-emphasis fs-6 flex-shrink-0"><span class="visually-hidden">Nilai </span><?= sanitize($hasil_nilai['nilai']) ?></span>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
