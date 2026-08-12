<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/auth.php'; // Memuat file auth.php

check_access([2]); // Dashboard ini hanya untuk Guru.

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// 1. Ambil Data Detail Guru
$stmt_guru = $db->prepare("SELECT * FROM guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();

$guru_id = $guru['id'] ?? 0;

// 2. Ambil Ringkasan Statistik Pengajaran Guru
// Total Kelas / Mapel yang Diampu
$stmt_p = $db->prepare("SELECT COUNT(*) FROM pengajaran WHERE guru_id = ?");
$stmt_p->execute([$guru_id]);
$total_pengajaran = $stmt_p->fetchColumn();

// Total pertemuan unik yang memiliki materi, dipisahkan per mapel dan kelas.
$stmt_pertemuan = $db->prepare(
    "SELECT mat.pengajaran_id,COUNT(DISTINCT mat.pertemuan_ke) total_pertemuan
     FROM materi mat
     JOIN pengajaran p ON p.id=mat.pengajaran_id
     WHERE p.guru_id=?
     GROUP BY mat.pengajaran_id"
);
$stmt_pertemuan->execute([$guru_id]);
$pertemuan_per_pengajaran = [];
foreach ($stmt_pertemuan->fetchAll() as $row) {
    $pertemuan_per_pengajaran[(int)$row['pengajaran_id']] = (int)$row['total_pertemuan'];
}

// Total Tugas yang Pernah Dibuat
$stmt_tugas = $db->prepare("
    SELECT COUNT(*) 
    FROM tugas t
    JOIN pengajaran p ON t.pengajaran_id = p.id
    WHERE p.guru_id = ?
");
$stmt_tugas->execute([$guru_id]);
$total_tugas = $stmt_tugas->fetchColumn();

// Total Ulangan Harian yang Dibuat
$stmt_ujian = $db->prepare("
    SELECT COUNT(*) 
    FROM ujian u
    JOIN pengajaran p ON u.pengajaran_id = p.id
    WHERE p.guru_id = ?
");
$stmt_ujian->execute([$guru_id]);
$total_ujian = $stmt_ujian->fetchColumn();

// Total Tugas Siswa yang Belum Dinilai
$stmt_pending = $db->prepare("
    SELECT COUNT(*) 
    FROM pengumpulan_tugas pt
    JOIN tugas t ON pt.tugas_id = t.id
    JOIN pengajaran p ON t.pengajaran_id = p.id
    WHERE p.guru_id = ? AND pt.nilai IS NULL
");
$stmt_pending->execute([$guru_id]);
$tugas_belum_dinilai = $stmt_pending->fetchColumn();

// 3. Ambil Daftar Kelas & Mata Pelajaran yang Diampu
$stmt_mapel_kelas = $db->prepare("
    SELECT p.id as pengajaran_id, m.nama_mapel, k.nama_kelas, k.tingkat,
           (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id) as total_siswa
    FROM pengajaran p
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ?
    ORDER BY k.nama_kelas ASC, m.nama_mapel ASC
");
$stmt_mapel_kelas->execute([$guru_id]);
$daftar_pengajaran = $stmt_mapel_kelas->fetchAll();
$pengajaran_per_mapel = [];
foreach ($daftar_pengajaran as $pengajaran_item) {
    $pengajaran_per_mapel[$pengajaran_item['nama_mapel']][] = $pengajaran_item;
}

// 4. Ambil 5 Pengumpulan Tugas Terbaru yang Membutuhkan Penilaian
$stmt_recent_sub = $db->prepare("
    SELECT pt.id as pengumpulan_id, pt.dikumpulkan_pada, s.nama_lengkap as nama_siswa, 
           t.judul as judul_tugas, k.nama_kelas, m.nama_mapel
    FROM pengumpulan_tugas pt
    JOIN siswa s ON pt.siswa_id = s.id
    JOIN tugas t ON pt.tugas_id = t.id
    JOIN pengajaran p ON t.pengajaran_id = p.id
    JOIN kelas k ON p.kelas_id = k.id
    JOIN mapel m ON p.mapel_id = m.id
    WHERE p.guru_id = ? AND pt.nilai IS NULL
    ORDER BY pt.dikumpulkan_pada DESC
    LIMIT 5
");
$stmt_recent_sub->execute([$guru_id]);
$recent_submissions = $stmt_recent_sub->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .teacher-dashboard { --teacher-bg:#f5f7fb; background:var(--teacher-bg); min-width:0; width:100%; }
    .teacher-main { width:100%; max-width:none; margin:0; padding:28px 32px!important; }
    .teacher-welcome {
        position:relative;
        overflow:hidden;
        min-height:160px;
        border-radius:22px;
        color:#fff;
        background-image:
            linear-gradient(110deg,rgba(23,37,84,.95) 0%,rgba(29,78,216,.83) 58%,rgba(59,130,246,.64) 100%),
            url('../assets/img/batikjb.webp');
        background-size:cover;
        background-position:center;
        background-repeat:no-repeat;
        box-shadow:0 12px 30px rgba(37,99,235,.2);
        display:flex;
        flex-direction:column;
        justify-content:center;
    }
    .teacher-welcome::after { content:""; position:absolute; inset:0; background:linear-gradient(90deg,rgba(15,23,42,.18),transparent 70%); pointer-events:none; }
    .teacher-welcome-copy { position:relative; z-index:1; max-width:720px; }
    .teacher-quick { min-height:104px; padding:16px 10px; border:1px solid #e7ecf4; border-radius:17px; background:#fff; color:#1e293b; text-decoration:none; display:flex; flex-direction:column; justify-content:center; align-items:center; gap:9px; font-size:.88rem; font-weight:650; text-align:center; box-shadow:0 5px 18px rgba(15,23,42,.05); transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
    .teacher-quick:active { transform:scale(.98); }
    .teacher-quick-icon { width:40px; height:40px; border-radius:12px; display:grid; place-items:center; font-size:1rem; }
    .teacher-stat { min-height:92px; border:1px solid #edf1f6; border-radius:17px; box-shadow:0 5px 18px rgba(15,23,42,.05); color:inherit; text-decoration:none; transition:transform .2s ease,box-shadow .2s ease; }
    .teacher-stat .card-body { padding:18px; }
    .teacher-stat .stat-icon { width:46px; height:46px; border-radius:13px; display:grid; place-items:center; flex:0 0 46px; font-size:1.05rem; }
    .teacher-panel { border:1px solid #edf1f6; border-radius:19px; box-shadow:0 6px 22px rgba(15,23,42,.06); overflow:visible; }
    .teaching-mobile-item,.grading-item { border-bottom:1px solid #edf1f5; padding:13px 0; }
    .teaching-mobile-item:last-child,.grading-item:last-child { border-bottom:0; }
    .item-copy { min-width:0; }
    .item-copy strong,.item-copy small { overflow-wrap:anywhere; }
    .teacher-dashboard-nav { min-height:72px; }
    .teacher-dashboard-nav .teacher-nip { white-space:nowrap; }
    .teacher-panel .table > :not(caption) > * > * { padding:.85rem .75rem; }
    .meeting-summary { border-color:#edf1f5; box-shadow:0 3px 14px rgba(15,23,42,.035); }
    .meeting-summary .card-body { padding:20px!important; }
    .meeting-summary-head { padding-bottom:13px; border-bottom:1px solid #eef2f6; }
    .meeting-summary-icon { width:34px; height:34px; border-radius:10px; display:grid; place-items:center; flex:0 0 34px; font-size:.82rem; }
    .meeting-summary-eyebrow { font-size:.65rem; letter-spacing:.07em; }
    .meeting-groups { display:grid; gap:8px; }
    .meeting-group { border:1px solid #e8edf3; border-radius:12px; background:#fff; overflow:hidden; }
    .meeting-group summary { min-width:0; padding:12px 13px; cursor:pointer; list-style:none; display:flex; align-items:center; justify-content:space-between; gap:12px; user-select:none; }
    .meeting-group summary::-webkit-details-marker { display:none; }
    .meeting-group summary::after { content:"+"; width:24px; height:24px; border-radius:7px; background:#f1f5f9; color:#64748b; display:grid; place-items:center; flex:0 0 24px; font-size:.9rem; font-weight:600; }
    .meeting-group[open] summary::after { content:"−"; }
    .meeting-group-title { min-width:0; flex:1; }
    .meeting-group-title strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#334155; font-size:.8rem; }
    .meeting-group-title small { display:block; margin-top:2px; color:#94a3b8; font-size:.68rem; }
    .meeting-group-list { padding:0 12px 10px; border-top:1px solid #f1f4f7; }
    .meeting-item { min-width:0; padding:9px 2px; border-bottom:1px solid #f1f4f7; color:#1e293b; text-decoration:none; display:flex; align-items:center; justify-content:space-between; gap:10px; transition:color .18s ease; }
    .meeting-item:last-child { border-bottom:0; }
    .meeting-item-copy { min-width:0; }
    .meeting-item-subject { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.78rem; font-weight:700; color:#334155; }
    .meeting-item-class { display:block; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.7rem; color:#94a3b8; }
    .meeting-item-count { min-width:42px; padding-left:10px; border-left:1px solid #edf1f5; text-align:center; flex:0 0 auto; }
    .meeting-item-count strong { display:block; color:#15803d; font-size:1.05rem; line-height:1; }
    .meeting-item-count small { display:block; margin-top:3px; color:#94a3b8; font-size:.56rem; text-transform:uppercase; letter-spacing:.04em; }
    @media (hover:hover) { .teacher-quick:hover,.teacher-stat:hover { transform:translateY(-3px); box-shadow:0 10px 25px rgba(15,23,42,.09); border-color:#dbe5f2; } .meeting-item:hover { color:#166534; } .meeting-group summary:hover { background:#fafcfd; } }
    @media (min-width:1400px) {
        .teacher-main { padding:32px 40px!important; }
        .teacher-content-primary { flex:0 0 66.666667%; max-width:66.666667%; }
        .teacher-content-secondary { flex:0 0 33.333333%; max-width:33.333333%; }
    }
    @media (max-width:991.98px) {
        .teacher-main { padding:20px!important; }
        .teacher-welcome { min-height:145px; }
        .teacher-panel { height:auto!important; }
    }
    @media (max-width:575.98px) {
        .teacher-main { padding:13px!important; }
        .teacher-dashboard-nav { min-height:64px; padding-right:12px!important; }
        .teacher-dashboard-nav h5 { font-size:1rem; }
        .teacher-dashboard-nav small { font-size:.7rem; }
        .teacher-dashboard-nav .teacher-nip { max-width:108px; padding:7px 9px!important; overflow:hidden; text-overflow:ellipsis; font-size:.68rem; }
        .teacher-welcome { min-height:138px; border-radius:17px; padding:20px!important; }
        .teacher-welcome h1 { font-size:1.2rem; line-height:1.35; }
        .teacher-welcome p { max-width:85%; font-size:.78rem; line-height:1.5; }
        .teacher-quick { min-height:92px; border-radius:14px; font-size:.8rem; }
        .teacher-quick-icon { width:42px; height:42px; }
        .teacher-panel { border-radius:15px; }
        .teacher-panel .card-body { padding:16px!important; }
        .teacher-stat .card-body { padding:11px!important; }
        .teacher-stat { min-height:78px; }
        .teacher-stat .stat-icon { width:38px; height:38px; flex-basis:38px; border-radius:11px; }
        .teacher-stat h3 { font-size:1.25rem; }
        .teacher-stat small { font-size:.66rem; }
        .grading-item { align-items:flex-start!important; }
        .grading-item .btn { min-width:58px; }
        .dropdown-menu { max-width:calc(100vw - 30px); }
        .meeting-summary .card-body { padding:15px!important; }
        .meeting-summary-head { margin-bottom:10px!important; padding-bottom:11px; }
        .meeting-groups { gap:6px; }
        .meeting-group summary { padding:10px 11px; }
        .meeting-group-list { padding:0 10px 7px; }
        .meeting-item { padding:8px 1px; }
        .meeting-item-subject { font-size:.75rem; }
        .meeting-item-count strong { font-size:1rem; }
    }
</style>
<div id="page-content-wrapper" class="teacher-dashboard">
    <nav class="navbar teacher-dashboard-nav bg-white border-bottom px-3 px-md-4 py-3"><div class="d-flex justify-content-between align-items-center gap-2 w-100"><div><h5 class="fw-bold mb-0">Dashboard Guru</h5><small class="text-muted">Pusat kegiatan mengajar</small></div><span class="teacher-nip badge bg-success-subtle text-success px-3 py-2">NIP <?= sanitize($guru['nip'] ?? '-') ?></span></div></nav>
    <main class="teacher-main p-3 p-md-4">
        <section class="teacher-welcome p-4 p-xl-5 mb-4"><div class="teacher-welcome-copy"><small class="opacity-75"><i class="fa-solid fa-hand-sparkles me-1"></i>Selamat datang</small><h1 class="h4 fw-bold mt-1 mb-2"><?= sanitize($guru['nama_lengkap'] ?? $_SESSION['username']) ?></h1><p class="mb-0 opacity-75">Kelola kegiatan mengajar dengan cepat dari perangkat apa pun.</p></div></section>

        <section class="mb-4" aria-labelledby="aksiMengajar"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-bold mb-0" id="aksiMengajar">Aksi Cepat</h2><small class="text-muted d-none d-sm-inline">Pilih kegiatan</small></div><div class="row g-2">
            <div class="col-6 col-sm-3"><a class="teacher-quick" href="materi.php"><span class="teacher-quick-icon bg-primary-subtle text-primary"><i class="fa-solid fa-book-open"></i></span><span>Materi</span></a></div>
            <div class="col-6 col-sm-3"><a class="teacher-quick" href="tugas.php"><span class="teacher-quick-icon bg-warning-subtle text-warning"><i class="fa-solid fa-list-check"></i></span><span>Tugas</span></a></div>
            <div class="col-6 col-sm-3"><a class="teacher-quick" href="absensi.php"><span class="teacher-quick-icon bg-success-subtle text-success"><i class="fa-solid fa-clipboard-user"></i></span><span>Absensi</span></a></div>
            <div class="col-6 col-sm-3"><a class="teacher-quick" href="ujian.php"><span class="teacher-quick-icon bg-danger-subtle text-danger"><i class="fa-solid fa-file-circle-question"></i></span><span>Ulangan Harian</span></a></div>
        </div></section>

        <div class="row g-2 mb-4">
            <div class="col-6 col-lg-3"><a href="#pengajaran-saya" class="card teacher-stat h-100"><div class="card-body d-flex align-items-center gap-2"><span class="stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-chalkboard"></i></span><div><h3 class="fw-bold mb-0"><?= (int)$total_pengajaran ?></h3><small class="text-muted">Kelas & Mapel</small></div></div></a></div>
            <div class="col-6 col-lg-3"><a href="tugas.php" class="card teacher-stat h-100"><div class="card-body d-flex align-items-center gap-2"><span class="stat-icon bg-info-subtle text-info"><i class="fa-solid fa-file-lines"></i></span><div><h3 class="fw-bold mb-0"><?= (int)$total_tugas ?></h3><small class="text-muted">Total Tugas</small></div></div></a></div>
            <div class="col-6 col-lg-3"><a href="ujian.php" class="card teacher-stat h-100"><div class="card-body d-flex align-items-center gap-2"><span class="stat-icon bg-danger-subtle text-danger"><i class="fa-solid fa-laptop-code"></i></span><div><h3 class="fw-bold mb-0"><?= (int)$total_ujian ?></h3><small class="text-muted">Ulangan Harian</small></div></div></a></div>
            <div class="col-6 col-lg-3"><a href="#antrean-penilaian" class="card teacher-stat h-100"><div class="card-body d-flex align-items-center gap-2"><span class="stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-pen-ruler"></i></span><div><h3 class="fw-bold mb-0"><?= (int)$tugas_belum_dinilai ?></h3><small class="text-muted">Perlu Dinilai</small></div></div></a></div>
        </div>

        <section class="card teacher-panel meeting-summary mb-4" aria-labelledby="totalPertemuanGuru"><div class="card-body"><div class="meeting-summary-head d-flex align-items-center gap-2 mb-3"><span class="meeting-summary-icon bg-success-subtle text-success"><i class="fa-solid fa-calendar-check"></i></span><div><small class="meeting-summary-eyebrow text-success fw-semibold">REKAP PEMBELAJARAN</small><h2 class="h6 fw-bold mb-0" id="totalPertemuanGuru">Pertemuan per Mapel & Kelas</h2></div></div><?php if(!$pengajaran_per_mapel): ?><p class="small text-muted mb-0">Belum ada pengajaran dari admin.</p><?php else: ?><div class="meeting-groups"><?php foreach($pengajaran_per_mapel as $nama_mapel=>$daftar_kelas): ?><details class="meeting-group"><summary><span class="meeting-group-title"><strong><?= sanitize($nama_mapel) ?></strong><small><?= count($daftar_kelas) ?> kelas · ketuk untuk melihat rincian</small></span></summary><div class="meeting-group-list"><?php foreach($daftar_kelas as $p): $jumlah_pertemuan=$pertemuan_per_pengajaran[(int)$p['pengajaran_id']]??0; ?><a href="materi.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>" class="meeting-item"><span class="meeting-item-copy"><span class="meeting-item-class"><?= sanitize($p['nama_kelas']) ?></span></span><span class="meeting-item-count"><strong><?= $jumlah_pertemuan ?></strong><small>Pertemuan</small></span></a><?php endforeach ?></div></details><?php endforeach ?></div><?php endif ?></div></section>

        <div class="row g-3">
            <div class="col-lg-7 teacher-content-primary" id="pengajaran-saya"><section class="card teacher-panel h-100"><div class="card-body p-4"><h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-book-open text-primary me-2"></i>Pengajaran Saya</h2>
                <?php if(!$daftar_pengajaran): ?><div class="text-center py-4"><i class="fa-solid fa-circle-info text-muted fa-2x mb-2"></i><p class="small text-muted mb-0">Belum ada kelas atau mata pelajaran dari admin.</p></div><?php else: ?>
                <div class="table-responsive d-none d-md-block"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Mata Pelajaran</th><th>Kelas</th><th>Siswa</th><th>Aksi</th></tr></thead><tbody><?php foreach($daftar_pengajaran as $p): ?><tr><td><strong class="text-primary"><?= sanitize($p['nama_mapel']) ?></strong></td><td><span class="badge bg-secondary"><?= sanitize($p['nama_kelas']) ?></span></td><td><?= (int)$p['total_siswa'] ?> siswa</td><td><div class="dropdown"><button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Kelola</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="materi.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>"><i class="fa-solid fa-book-open me-2 text-primary"></i>Materi</a></li><li><a class="dropdown-item" href="tugas.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>"><i class="fa-solid fa-list-check me-2 text-warning"></i>Tugas</a></li><li><a class="dropdown-item" href="ujian.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>"><i class="fa-solid fa-file-pen me-2 text-danger"></i>Ulangan Harian</a></li><li><a class="dropdown-item" href="rekap_nilai.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>"><i class="fa-solid fa-chart-column me-2 text-success"></i>Rekap Nilai</a></li></ul></div></td></tr><?php endforeach; ?></tbody></table></div>
                <div class="d-md-none"><?php foreach($daftar_pengajaran as $p): ?><article class="teaching-mobile-item"><div class="d-flex justify-content-between align-items-start gap-2"><div class="item-copy"><strong class="d-block"><?= sanitize($p['nama_mapel']) ?></strong><small class="text-muted d-block"><?= sanitize($p['nama_kelas']) ?> &middot; <?= (int)$p['total_siswa'] ?> siswa</small></div><div class="dropdown"><button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Kelola</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="materi.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>">Materi</a></li><li><a class="dropdown-item" href="tugas.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>">Tugas</a></li><li><a class="dropdown-item" href="ujian.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>">Ulangan Harian</a></li><li><a class="dropdown-item" href="rekap_nilai.php?pengajaran_id=<?= (int)$p['pengajaran_id'] ?>">Rekap Nilai</a></li></ul></div></div></article><?php endforeach; ?></div>
                <?php endif; ?>
            </div></section></div>

            <div class="col-lg-5 teacher-content-secondary" id="antrean-penilaian"><section class="card teacher-panel h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Perlu Dinilai</h2><a href="tugas_penilaian.php" class="small text-decoration-none">Lihat semua</a></div>
                <?php if(!$recent_submissions): ?><div class="text-center py-4"><i class="fa-solid fa-circle-check text-success fa-2x mb-2"></i><p class="small text-muted mb-0">Semua tugas sudah dinilai.</p></div><?php else: foreach($recent_submissions as $sub): ?><article class="grading-item d-flex align-items-center gap-2"><span class="teacher-quick-icon bg-warning-subtle text-warning flex-shrink-0"><i class="fa-solid fa-file-circle-check"></i></span><div class="item-copy flex-grow-1"><strong class="d-block small"><?= sanitize($sub['nama_siswa']) ?></strong><small class="text-muted d-block"><?= sanitize($sub['judul_tugas']) ?> &middot; <?= sanitize($sub['nama_kelas']) ?></small><small class="text-primary"><?= date('d M, H:i',strtotime($sub['dikumpulkan_pada'])) ?></small></div><a href="tugas_penilaian.php?id=<?= (int)$sub['pengumpulan_id'] ?>" class="btn btn-sm btn-warning text-white">Nilai</a></article><?php endforeach; endif; ?>
            </div></section></div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
