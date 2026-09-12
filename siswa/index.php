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

$progress_belajar = [
    ['label'=>'Tugas', 'data'=>$progress_tugas, 'aksi'=>'dikumpulkan', 'catatan'=>'Tugas yang sudah dapat diakses', 'warna'=>'primary', 'ikon'=>'fa-list-check'],
    ['label'=>'Materi', 'data'=>$progress_materi, 'aksi'=>'dibuka', 'catatan'=>'Materi pada pertemuan terbuka', 'warna'=>'info', 'ikon'=>'fa-book-open-reader'],
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
    .student-dashboard .student-tab-nav { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:4px; padding:8px; border-bottom:1px solid #e8edf5; }
    .student-dashboard .student-tab-nav .nav-link { min-height:44px; padding:10px 4px; font-size:.88rem; font-weight:600; color:#64748b; border-radius:10px; }
    .student-dashboard .student-tab-nav .nav-link.active { color:#fff; background:var(--student-blue); }
    .student-dashboard .student-tab-nav .nav-link:hover:not(.active) { color:#1d4ed8; background:#eff6ff; }
    .student-dashboard .student-learning-tabs,.student-dashboard .student-tab-section { min-width:0; }
    .student-dashboard .student-empty-state { display:flex; align-items:center; gap:10px; padding:8px 0; }
    .student-dashboard .student-empty-state > i { flex-shrink:0; }
    @media (max-width:575.98px) {
        .student-dashboard .welcome-card { min-height:0; padding:16px!important; }
        .student-dashboard .welcome-layout { gap:12px; }
        .student-dashboard .welcome-card h1 { margin-top:3px; margin-bottom:4px!important; font-size:1.1rem; }
        .student-dashboard .student-credit-link { min-height:60px; padding:10px; gap:10px; }
        .student-dashboard .student-credit-score { width:40px; height:40px; flex-basis:40px; font-size:1.25rem; }
        .student-dashboard .student-credit-copy strong { font-size:.85rem; }
        .student-dashboard .student-credit-copy small { font-size:.72rem; margin-top:2px; }
        .student-dashboard .quick-link { min-height:74px; padding:10px 8px; gap:6px; font-size:.8rem; }
        .student-dashboard .quick-link .quick-icon { width:32px; height:32px; flex-basis:32px; font-size:.95rem; border-radius:10px; }
        .student-dashboard .student-main > .mb-4 { margin-bottom:16px!important; }
        .student-dashboard .student-tab-section > .card-body { padding:16px!important; }
        .student-dashboard .student-tab-section .row.g-4 { --bs-gutter-y:16px; }
        .student-dashboard .student-tab-nav .nav-link { font-size:.8rem; }
    }
    /* Penyelarasan visual dashboard: biru sekolah, permukaan ringan, dan ruang lega. */
    .student-dashboard { --student-blue:#2459c4; --student-bg:#f5f7fb; --student-radius:20px; --student-shadow:0 6px 24px rgba(24,45,86,.035); color:#24324b; }
    .student-dashboard .student-main { max-width:1120px; }
    .student-dashboard .student-topbar { background:rgba(255,255,255,.92); border-color:#edf1f7; }
    .student-dashboard .welcome-card { min-height:170px; padding:28px!important; border:1px solid rgba(255,255,255,.12); background-image:linear-gradient(110deg,rgba(16,34,70,.98),rgba(28,65,131,.94) 60%,rgba(36,89,196,.88)),url('../assets/img/batikjb.webp'); box-shadow:0 10px 26px rgba(29,61,121,.12); }
    .student-dashboard .welcome-card::after { background:radial-gradient(ellipse at top right,rgba(161,193,255,.18),transparent 65%); }
    .student-dashboard .welcome-card h1 { font-size:1.65rem; font-weight:700!important; letter-spacing:-.035em; }
    .student-dashboard .welcome-meta { font-size:.8rem; }
    .student-dashboard .student-credit-link { flex:0 1 340px; min-height:76px; padding:14px; background:rgba(255,255,255,.09); border-color:rgba(255,255,255,.22); color:#fff; box-shadow:none; border-radius:16px; }
    .student-dashboard .student-credit-link:hover { color:#fff; background:rgba(255,255,255,.15); box-shadow:none; }
    .student-dashboard .student-credit-score { background:#fff; color:#2459c4; border-radius:12px; width:46px; height:46px; flex-basis:46px; }
    .student-dashboard .student-credit-copy strong { font-size:.9rem; }
    .student-dashboard .student-credit-copy small,.student-dashboard .student-credit-copy small b { color:#dbe7ff; }
    .student-dashboard .quick-link { flex-direction:row; justify-content:flex-start; min-height:86px; padding:18px; gap:14px; text-align:left; border-color:#e9edf5; border-radius:16px; box-shadow:0 2px 6px rgba(24,45,86,.02); }
    .student-dashboard .quick-link:hover { background:#fff; border-color:#b5c8ed; box-shadow:0 6px 16px rgba(24,45,86,.055); }
    .student-dashboard .quick-link > span:last-child { min-width:0; line-height:1.4; }
    .student-dashboard .quick-link > span:last-child small { display:block; font-size:.72rem; margin-top:2px; }
    .student-dashboard #menuCepat { font-size:.82rem; color:#627189; font-weight:600!important; }
    .student-dashboard .section-card { border-color:#e9edf5; }
    .student-dashboard .attendance-summary { background:#fbfcff; border-radius:16px; box-shadow:none; }
    .student-dashboard .attendance-summary h2 { font-size:.9rem; }
    .student-dashboard .attendance-summary .badge { font-weight:600; border-radius:8px; }
    .student-dashboard .student-learning-tabs { padding:8px; border-radius:22px; }
    .student-dashboard .student-tab-nav { border:0; border-radius:14px; background:#f2f5fa; margin:4px; padding:5px; gap:4px; }
    .student-dashboard .student-tab-nav .nav-link { color:#627189; border-radius:10px; min-height:44px; font-size:.85rem; }
    .student-dashboard .student-tab-nav .nav-link.active { background:#fff; color:#2459c4; box-shadow:0 2px 7px rgba(24,45,86,.08); }
    .student-dashboard .student-tab-section > .card-body { padding:24px!important; }
    .student-dashboard .student-tab-section h2 { font-size:1rem; letter-spacing:-.015em; }
    .student-dashboard .student-tab-section .form-select { border-color:#e1e7f0; background-color:#fbfcff; border-radius:10px; min-height:44px; font-size:.85rem; }
    .student-dashboard .student-tab-section .progress { background:#edf1f7; height:6px; }
    .student-dashboard .student-tab-section .progress-bar { border-radius:6px; }
    .student-dashboard .student-empty-state { padding:12px 0; color:#64748b; }
    .student-dashboard .student-grade-card { background:#fbfcff; border-radius:16px; border-color:#e9edf5; }
    @media (max-width:767.98px) {
        .student-dashboard .student-credit-link { flex-basis:100%; max-width:none; }
        .student-dashboard .welcome-layout { gap:18px; }
    }
    @media (max-width:575.98px) {
        .student-dashboard .student-main { padding:18px 16px 24px!important; }
        .student-dashboard .welcome-card { min-height:0; padding:20px!important; border-radius:20px; }
        .student-dashboard .welcome-card h1 { font-size:1.35rem; margin-top:5px; }
        .student-dashboard .welcome-profile > small { font-size:.75rem; }
        .student-dashboard .student-credit-link { min-height:64px; padding:10px 12px; gap:12px; }
        .student-dashboard .student-credit-score { width:40px; height:40px; flex-basis:40px; }
        .student-dashboard .student-credit-copy strong { font-size:.82rem; }
        .student-dashboard .student-credit-copy small { font-size:.72rem; line-height:1.45; }
        .student-dashboard .quick-link { min-height:72px; padding:12px 10px; gap:10px; font-size:.78rem; border-radius:14px; }
        .student-dashboard .quick-link .quick-icon { width:34px; height:34px; flex-basis:34px; font-size:.95rem; }
        .student-dashboard .student-main > .mb-4 { margin-bottom:20px!important; }
        .student-dashboard .student-learning-tabs { padding:5px; border-radius:18px; }
        .student-dashboard .student-tab-section > .card-body { padding:18px 14px!important; }
        .student-dashboard .student-tab-nav .nav-link { font-size:.78rem; }
    }
    .student-dashboard .progress-eyebrow { display:block; font-size:.62rem; letter-spacing:.14em; color:#71819d; font-weight:700; margin-bottom:8px; }
    .student-dashboard .progress-identity { display:flex; align-items:center; gap:12px; padding:12px; background:#f4f7fc; border-radius:12px; }
    .student-dashboard .progress-identity > div { min-width:0; overflow-wrap:anywhere; }
    .student-dashboard .progress-identity strong { display:block; font-size:.85rem; }
    .student-dashboard .progress-identity small { display:block; color:#64748b; font-size:.73rem; margin-top:3px; }
    .student-dashboard .progress-orbit-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; align-items:start; }
    .student-dashboard .progress-orbit-card { min-width:0; border:1px solid #e6edf6; border-radius:18px; background:linear-gradient(155deg,#fff,#f8fafd); overflow:hidden; }
    .student-dashboard .progress-orbit-card summary { list-style:none; cursor:pointer; display:flex; flex-direction:column; align-items:center; padding:16px 8px 12px; gap:10px; text-align:center; }
    .student-dashboard .progress-orbit-card summary::-webkit-details-marker { display:none; }
    .student-dashboard .progress-orbit-card summary:focus-visible { outline:3px solid var(--student-blue); outline-offset:-4px; border-radius:18px; }
    .student-dashboard .orbit-label { font-size:.8rem; font-weight:650; display:flex; align-items:center; gap:6px; }
    .student-dashboard .orbit-label i { color:var(--orbit-color); }
    .student-dashboard .progress-orbit { width:94px; height:94px; border-radius:50%; background:conic-gradient(var(--orbit-color) var(--orbit-value),#eaf0f7 0); display:grid; place-items:center; position:relative; }
    .student-dashboard .progress-orbit::before { content:""; position:absolute; inset:7px; border-radius:50%; background:#fff; box-shadow:0 2px 12px rgba(30,57,105,.04); }
    .student-dashboard .progress-orbit strong { position:relative; color:#253b61; font-size:1.6rem; letter-spacing:-.04em; font-variant-numeric:tabular-nums; }
    .student-dashboard .progress-orbit strong small { font-size:.75rem; margin-left:2px; color:#71819d; }
    .student-dashboard .orbit-count { font-size:.72rem; color:#61708a; overflow-wrap:anywhere; }
    .student-dashboard .orbit-hint { font-size:.65rem; color:#657694; }
    .student-dashboard .orbit-hint i { margin-left:3px; transition:transform .2s ease; }
    .student-dashboard .progress-orbit-card[open] .orbit-hint i { transform:rotate(180deg); }
    .student-dashboard .orbit-detail { border-top:1px solid #e6edf6; padding:12px; font-size:.73rem; color:#61708a; }
    .student-dashboard .orbit-detail p { margin-bottom:6px; }
    .student-dashboard #progressFeedback:empty { display:none; }
    .student-dashboard #interactiveProgress[aria-busy="true"] .progress-orbit-grid { opacity:.5; }
    .student-dashboard .tab-pane.active { animation:student-panel-in .2s ease-out; }
    @keyframes student-panel-in { from { opacity:.5; transform:translateY(3px); } to { opacity:1; transform:translateY(0); } }
    @media(min-width:992px) { .student-dashboard .progress-orbit-grid { grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); } }
    @media(prefers-reduced-motion:reduce) { .student-dashboard .tab-pane.active { animation:none; } .student-dashboard .orbit-hint i { transition:none; } }
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

        <section class="card section-card attendance-summary mb-4" aria-labelledby="kehadiranHariIni">
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

        <div class="card section-card student-learning-tabs">
            <div class="nav nav-pills student-tab-nav" role="tablist" aria-label="Ringkasan belajar siswa">
                <button class="nav-link active" id="tab-progress" data-bs-toggle="pill" data-bs-target="#panel-progress" type="button" role="tab" aria-controls="panel-progress" aria-selected="true"><i class="fa-solid fa-chart-simple me-1" aria-hidden="true"></i>Progress</button>
                <button class="nav-link" id="tab-tugas" data-bs-toggle="pill" data-bs-target="#panel-tugas" type="button" role="tab" aria-controls="panel-tugas" aria-selected="false" tabindex="-1"><i class="fa-solid fa-list-check me-1" aria-hidden="true"></i>Tugas</button>
                <button class="nav-link" id="tab-ulangan" data-bs-toggle="pill" data-bs-target="#panel-ulangan" type="button" role="tab" aria-controls="panel-ulangan" aria-selected="false" tabindex="-1"><i class="fa-solid fa-file-pen me-1" aria-hidden="true"></i>Ulangan</button>
                <button class="nav-link" id="tab-nilai" data-bs-toggle="pill" data-bs-target="#panel-nilai" type="button" role="tab" aria-controls="panel-nilai" aria-selected="false" tabindex="-1"><i class="fa-solid fa-graduation-cap me-1" aria-hidden="true"></i>Nilai</button>
            </div>
            <div class="tab-content">
                <div class="tab-pane show active" id="panel-progress" role="tabpanel" aria-labelledby="tab-progress" tabindex="0">
<section class="student-tab-section" aria-labelledby="progressBelajar" id="interactiveProgress">
    <div class="card-body p-3 p-md-4">
        <div class="progress-intro"><span class="progress-eyebrow">RUANG BELAJARMU</span><h2 class="h6 fw-bold mb-1" id="progressBelajar">Setiap langkah berarti</h2><p class="small text-muted mb-3">Lihat perjalanan belajarmu, satu pelajaran setiap waktu.</p></div>
        <?php if ($progress_mapel_list): ?>
        <form method="get" action="index.php#progressBelajar" class="mb-3" id="progressFilter">
            <label for="progressMapel" class="form-label small text-muted">Mata pelajaran / guru</label>
            <select id="progressMapel" name="progress_pengajaran_id" class="form-select w-100">
                <option value="0">Semua mata pelajaran (gabungan)</option>
                <?php foreach ($progress_mapel_list as $pilihan): ?>
                <option value="<?= (int)$pilihan['id'] ?>" <?= $progress_pengajaran_id === (int)$pilihan['id'] ? 'selected' : '' ?>><?= sanitize($pilihan['nama_mapel'].' / '.$pilihan['nama_guru'].' / '.$pilihan['semester'].' '.$pilihan['tahun_ajaran']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-outline-primary mt-2">Tampilkan</button></noscript>
        </form>
        <?php endif; ?>
        <div class="progress-identity mb-3">
            <i class="fa-solid fa-book-open text-primary" aria-hidden="true"></i>
            <div>
                <?php if ($progress_pengajaran_id > 0): foreach ($progress_mapel_list as $identitas): if ((int)$identitas['id'] !== $progress_pengajaran_id) continue; ?>
                    <strong><?= sanitize($identitas['nama_mapel']) ?></strong><small><?= sanitize($identitas['nama_guru'].' ? '.$identitas['semester'].' '.$identitas['tahun_ajaran']) ?></small>
                <?php endforeach; else: ?>
                    <strong>Semua mata pelajaran</strong><small>Ringkasan gabungan dari guru di kelasmu</small>
                <?php endif; ?>
            </div>
        </div>
        <p id="progressFeedback" class="small text-muted mb-2" role="status" aria-live="polite"></p>
        <?php if (!$progress_belajar): ?><p class="small text-muted mb-0">Belum ada data progress belajar.</p><?php endif; ?>
        <div class="progress-orbit-grid">
            <?php foreach ($progress_belajar as $progress):
                $total_progress = (int)$progress['data']['total'];
                $selesai_progress = (int)$progress['data']['selesai'];
                $persen_progress = min(100, max(0, (int)round($selesai_progress / $total_progress * 100)));
                $warna_ring = ['primary'=>'#3868d9','info'=>'#089bb5','danger'=>'#ad5cb5','success'=>'#19876d'][$progress['warna']];
            ?>
            <details class="progress-orbit-card" style="--orbit-color:<?= $warna_ring ?>">
                <summary>
                    <span class="orbit-label"><i class="fa-solid <?= $progress['ikon'] ?>" aria-hidden="true"></i><?= $progress['label'] ?></span>
                    <span class="progress-orbit" style="--orbit-value:<?= $persen_progress ?>%" role="progressbar" aria-label="<?= $progress['label'] ?>" aria-valuenow="<?= $persen_progress ?>" aria-valuemin="0" aria-valuemax="100"><strong><?= $persen_progress ?><small>%</small></strong></span>
                    <span class="orbit-count"><?= $selesai_progress ?> / <?= $total_progress ?> <?= $progress['aksi'] ?></span>
                    <span class="orbit-hint">Detail <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span>
                </summary>
                <div class="orbit-detail"><p><?= $progress['catatan'] ?>.</p><span><?= $selesai_progress ?> dari <?= $total_progress ?> <?= $progress['aksi'] ?>.</span></div>
            </details>
            <?php endforeach; ?>

        </div>
    </div>
</section>
                </div>
                <div class="tab-pane" id="panel-tugas" role="tabpanel" aria-labelledby="tab-tugas" tabindex="0">
<section class="student-tab-section" aria-labelledby="tugasTerdekat">
                    <div class="card-body p-4">
                        <div class="section-heading">
                            <h2 class="h6 fw-bold mb-0" id="tugasTerdekat"><i class="fa-solid fa-list-check text-warning me-2"></i>Tugas Terdekat</h2>
                            <a href="tugas.php" class="small text-decoration-none">Lihat semua</a>
                        </div>
                        <?php if (!$tugas_terdekat): ?>
                            <div class="student-empty-state"><i class="fa-solid fa-circle-check text-success" aria-hidden="true"></i><p class="small text-muted mb-0">Tidak ada tugas yang menunggu.</p></div>
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
                <div class="tab-pane" id="panel-ulangan" role="tabpanel" aria-labelledby="tab-ulangan" tabindex="0">
                    <div id="jadwal-ujian">
<section class="student-tab-section" aria-labelledby="ulanganTerdekat">
                    <div class="card-body p-4">
                        <div class="section-heading">
                            <h2 class="h6 fw-bold mb-0" id="ulanganTerdekat"><i class="fa-solid fa-file-pen text-danger me-2"></i>Ulangan Terdekat</h2>
                            <a href="ujian.php" class="small text-decoration-none">Lihat semua</a>
                        </div>
                        <?php if (!$daftar_ujian): ?>
                            <div class="student-empty-state"><i class="fa-solid fa-calendar-check text-success" aria-hidden="true"></i><p class="small text-muted mb-0">Belum ada ulangan yang dijadwalkan.</p></div>
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
                <div class="tab-pane" id="panel-nilai" role="tabpanel" aria-labelledby="tab-nilai" tabindex="0">
<section class="student-tab-section" aria-labelledby="nilaiTerbaru">
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
                </div>
            </div>
        </div>
    </main>
</div>


<script>
(() => {
    let pendingProgress = null;
    document.addEventListener('change', async (event) => {
        if (event.target.id !== 'progressMapel') return;
        const section = document.getElementById('interactiveProgress');
        const feedback = section.querySelector('#progressFeedback');
        if (pendingProgress) pendingProgress.abort();
        const controller = new AbortController();
        pendingProgress = controller;
        const url = new URL('index.php', window.location.href);
        url.searchParams.set('progress_pengajaran_id', event.target.value);
        section.setAttribute('aria-busy', 'true');
        feedback.textContent = 'Memuat progress pelajaran?';
        try {
            const response = await fetch(url, { signal:controller.signal, credentials:'same-origin', cache:'no-store' });
            if (!response.ok || response.redirected) throw new Error('Progress unavailable');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = html.getElementById('interactiveProgress');
            if (!replacement || !replacement.querySelector('#progressMapel')) throw new Error('Progress unavailable');
            if (controller.signal.aborted) return;
            section.replaceWith(replacement);
            replacement.querySelector('#progressMapel').focus({preventScroll:true});
            replacement.querySelector('#progressFeedback').textContent = 'Progress diperbarui.';
            history.replaceState(null, '', url.pathname + url.search + '#progressBelajar');
        } catch (error) {
            if (error.name !== 'AbortError') feedback.textContent = 'Progress belum diperbarui. Pilih kembali pelajaran untuk mencoba lagi, atau muat ulang halaman.';
        } finally {
            if (pendingProgress === controller) {
                pendingProgress = null;
                document.getElementById('interactiveProgress')?.removeAttribute('aria-busy');
            }
        }
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
