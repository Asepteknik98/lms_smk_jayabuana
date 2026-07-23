<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);
$db = Database::getInstance();
$stmt_guru = $db->prepare('SELECT id FROM guru WHERE user_id = ?');
$stmt_guru->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt_guru->fetchColumn() ?: 0);

$stmt_sesi = $db->prepare(
    "SELECT sa.*, m.nama_mapel, k.nama_kelas,
            SUM(da.status = 'Hadir') AS jumlah_tercatat,
            SUM(da.status = 'Hadir') AS jumlah_hadir,
            SUM(da.status = 'Sakit') AS jumlah_sakit,
            SUM(da.status = 'Izin') AS jumlah_izin,
            SUM(da.status = 'Alpa') AS jumlah_alpa
     FROM sesi_absensi sa
     JOIN pengajaran p ON p.id = sa.pengajaran_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     LEFT JOIN detail_absensi da ON da.sesi_absensi_id = sa.id
     WHERE p.guru_id = ?
     GROUP BY sa.id
     ORDER BY sa.tanggal DESC, sa.pertemuan_ke DESC"
);
$stmt_sesi->execute([$guru_id]);
$seluruh_sesi = $stmt_sesi->fetchAll();
$sesi_per_halaman = 5;
$total_sesi = count($seluruh_sesi);
$total_sesi_dibuka = count(array_filter($seluruh_sesi, fn($sesi) => $sesi['status'] === 'Dibuka'));
$total_sesi_selesai = $total_sesi - $total_sesi_dibuka;
$total_checkin = array_sum(array_map(fn($sesi) => (int)$sesi['jumlah_tercatat'], $seluruh_sesi));
$total_halaman = max(1, (int)ceil($total_sesi / $sesi_per_halaman));
$halaman = max(1, min((int)($_GET['page'] ?? 1), $total_halaman));
$sesi_list = array_slice($seluruh_sesi, ($halaman - 1) * $sesi_per_halaman, $sesi_per_halaman);
?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .history-page { background:#f5f7fb; min-width:0; }
    .history-content { max-width:1100px; margin:0 auto; }
    .history-panel { border:0; border-radius:18px; box-shadow:0 6px 22px rgba(15,23,42,.06); }
    .history-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
    .history-summary-card { border:0; border-radius:15px; box-shadow:0 5px 18px rgba(15,23,42,.05); }
    .history-summary-card .summary-icon { width:38px; height:38px; flex:0 0 38px; display:grid; place-items:center; border-radius:11px; }
    .history-summary-card strong { font-size:1.2rem; line-height:1.1; }
    .history-summary-card small { font-size:.7rem; }
    .session-mobile-card { border:1px solid #e8edf4; border-radius:14px; padding:14px; background:#fff; box-shadow:0 4px 14px rgba(15,23,42,.035); }
    .session-mobile-card.session-open { border-color:rgba(25,135,84,.3); box-shadow:0 6px 18px rgba(25,135,84,.08); }
    .session-mobile-card + .session-mobile-card { margin-top:10px; }
    .session-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
    .session-stats > div { background:#f8fafc; border-radius:9px; padding:8px 3px; text-align:center; }
    .session-stats strong { display:block; line-height:1.1; }
    .session-stats small { color:#64748b; font-size:.65rem; }
    .history-table thead th { color:#64748b; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
    .history-table tbody td { padding-top:14px; padding-bottom:14px; }
    .attendance-chips { display:flex; flex-wrap:wrap; gap:4px; }
    .attendance-chips span { padding:4px 7px; border-radius:7px; background:#f8fafc; font-size:.68rem; white-space:nowrap; }
    .history-pagination .page-link { border-radius:8px!important; margin:0 2px; }
    @media(max-width:767.98px){.history-summary{grid-template-columns:repeat(2,1fr)}.history-content{padding:13px!important}.history-panel{border-radius:15px}.history-panel .card-body{padding:15px!important}}
    @media(max-width:575.98px){.history-summary{gap:7px}.history-summary-card .card-body{padding:11px!important}.history-summary-card strong{font-size:1.05rem}.session-mobile-card{padding:13px}}
</style>
<div id="page-content-wrapper" class="history-page">
    <nav class="navbar top-navbar px-3 px-md-4 py-3"><div class="d-flex justify-content-between align-items-center gap-2 w-100"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Riwayat Absensi</h5><small class="text-muted">Pantau seluruh sesi yang pernah dibuat</small></div><div class="d-flex gap-2"><a href="rekap_absensi.php" class="btn btn-sm btn-outline-success flex-shrink-0"><i class="fa-solid fa-chart-column me-1"></i><span class="d-none d-sm-inline">Rekap Kelas</span></a><a href="absensi.php" class="btn btn-sm btn-primary flex-shrink-0"><i class="fa-solid fa-plus me-1"></i><span class="d-none d-sm-inline">Buka Absensi</span></a></div></div></nav>
    <main class="container-fluid history-content p-3 p-md-4">
        <section class="history-summary mb-3">
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-primary-subtle text-primary"><i class="fa-solid fa-calendar-days"></i></span><div><strong class="d-block"><?= $total_sesi ?></strong><small class="text-muted">Total Sesi</small></div></div></div>
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-success-subtle text-success"><i class="fa-solid fa-door-open"></i></span><div><strong class="d-block"><?= $total_sesi_dibuka ?></strong><small class="text-muted">Sedang Dibuka</small></div></div></div>
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-secondary-subtle text-secondary"><i class="fa-solid fa-circle-check"></i></span><div><strong class="d-block"><?= $total_sesi_selesai ?></strong><small class="text-muted">Selesai</small></div></div></div>
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-info-subtle text-info"><i class="fa-solid fa-users"></i></span><div><strong class="d-block"><?= $total_checkin ?></strong><small class="text-muted">Data Tercatat</small></div></div></div>
        </section>
        <section class="card history-panel"><div class="card-body p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h6 class="fw-bold mb-1">Sesi Absensi</h6><small class="text-muted">Maksimal 5 sesi per halaman</small></div><span class="badge bg-primary-subtle text-primary"><?= $total_sesi ?> sesi</span></div>
            <?php if (!$sesi_list): ?><div class="text-center py-5"><i class="fa-solid fa-calendar-xmark fa-2x text-muted mb-2"></i><p class="text-muted mb-0">Belum ada sesi absensi.</p></div><?php else: ?>
            <div class="table-responsive d-none d-md-block"><table class="table history-table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Pembelajaran</th><th>Jadwal</th><th>Status</th><th>Ringkasan</th><th>Aksi</th></tr></thead><tbody><?php foreach($sesi_list as $sesi): ?><tr><td><strong><?= sanitize($sesi['nama_mapel']) ?></strong><br><small class="text-muted"><?= sanitize($sesi['nama_kelas']) ?> &middot; Pertemuan <?= (int)$sesi['pertemuan_ke'] ?></small></td><td><?= date('d/m/Y',strtotime($sesi['tanggal'])) ?><br><small class="text-muted"><?= date('H:i',strtotime($sesi['waktu_buka'])) ?>–<?= date('H:i',strtotime($sesi['waktu_tutup'])) ?></small></td><td><span class="badge <?= $sesi['status']==='Dibuka'?'bg-success':'bg-secondary' ?>"><?= sanitize($sesi['status']) ?></span></td><td><div class="attendance-chips"><span class="text-success">Hadir <?= (int)$sesi['jumlah_hadir'] ?></span><span class="text-secondary">Sakit <?= (int)$sesi['jumlah_sakit'] ?></span><span class="text-primary">Izin <?= (int)$sesi['jumlah_izin'] ?></span><span class="text-danger">Alpa <?= (int)$sesi['jumlah_alpa'] ?></span></div></td><td><?php if($sesi['status']==='Dibuka'): ?><form method="post" action="absensi.php" onsubmit="return confirm('Tutup sesi? Siswa yang belum check-in akan menjadi Alpa.');"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="tutup"><input type="hidden" name="sesi_id" value="<?= (int)$sesi['id'] ?>"><button class="btn btn-sm btn-danger"><i class="fa-solid fa-lock me-1"></i>Tutup</button></form><?php else: ?><small class="text-muted"><i class="fa-solid fa-check me-1"></i>Selesai</small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="d-md-none"><?php foreach($sesi_list as $sesi): ?><article class="session-mobile-card <?= $sesi['status']==='Dibuka'?'session-open':'' ?>"><div class="d-flex justify-content-between align-items-start gap-2 mb-2"><span class="badge bg-primary">Pertemuan <?= (int)$sesi['pertemuan_ke'] ?></span><span class="badge <?= $sesi['status']==='Dibuka'?'bg-success':'bg-secondary' ?>"><?= sanitize($sesi['status']) ?></span></div><h6 class="fw-bold mb-1"><?= sanitize($sesi['nama_mapel']) ?></h6><p class="small text-muted mb-3"><i class="fa-solid fa-school me-1"></i><?= sanitize($sesi['nama_kelas']) ?> &middot; <?= date('d/m/Y',strtotime($sesi['tanggal'])) ?><br><i class="fa-regular fa-clock me-1"></i><?= date('H:i',strtotime($sesi['waktu_buka'])) ?>–<?= date('H:i',strtotime($sesi['waktu_tutup'])) ?></p><div class="session-stats"><div><strong class="text-success"><?= (int)$sesi['jumlah_hadir'] ?></strong><small>Hadir</small></div><div><strong class="text-secondary"><?= (int)$sesi['jumlah_sakit'] ?></strong><small>Sakit</small></div><div><strong class="text-primary"><?= (int)$sesi['jumlah_izin'] ?></strong><small>Izin</small></div><div><strong class="text-danger"><?= (int)$sesi['jumlah_alpa'] ?></strong><small>Alpa</small></div></div><?php if($sesi['status']==='Dibuka'): ?><form method="post" action="absensi.php" class="mt-3" onsubmit="return confirm('Tutup sesi? Siswa yang belum check-in akan menjadi Alpa.');"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="tutup"><input type="hidden" name="sesi_id" value="<?= (int)$sesi['id'] ?>"><button class="btn btn-sm btn-danger w-100"><i class="fa-solid fa-lock me-1"></i>Tutup Sesi</button></form><?php endif; ?></article><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if($total_sesi>0): ?><nav class="history-pagination mt-3 pt-3 border-top" aria-label="Navigasi riwayat absensi"><div class="d-flex d-md-none justify-content-between align-items-center"><a class="btn btn-sm btn-outline-primary <?= $halaman<=1?'disabled':'' ?>" href="?page=<?= max(1,$halaman-1) ?>"><i class="fa-solid fa-chevron-left me-1"></i>Sebelumnya</a><small class="text-muted"><?= $halaman ?> / <?= $total_halaman ?></small><a class="btn btn-sm btn-outline-primary <?= $halaman>=$total_halaman?'disabled':'' ?>" href="?page=<?= min($total_halaman,$halaman+1) ?>">Berikutnya<i class="fa-solid fa-chevron-right ms-1"></i></a></div><ul class="pagination pagination-sm justify-content-center d-none d-md-flex mb-0"><li class="page-item <?= $halaman<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= max(1,$halaman-1) ?>">&laquo;</a></li><?php for($i=1;$i<=$total_halaman;$i++): ?><li class="page-item <?= $i===$halaman?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?><li class="page-item <?= $halaman>=$total_halaman?'disabled':'' ?>"><a class="page-link" href="?page=<?= min($total_halaman,$halaman+1) ?>">&raquo;</a></li></ul></nav><?php endif; ?>
        </div></section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
