<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);
$db = Database::getInstance();
$stmt_guru = $db->prepare('SELECT id FROM guru WHERE user_id = ?');
$stmt_guru->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt_guru->fetchColumn() ?: 0);
proses_penutupan_absensi_otomatis($db);

$stmt_kelas = $db->prepare(
    'SELECT DISTINCT k.id,k.nama_kelas
     FROM pengajaran p
     JOIN kelas k ON k.id=p.kelas_id
     WHERE p.guru_id=?
     ORDER BY k.nama_kelas'
);
$stmt_kelas->execute([$guru_id]);
$kelas_list = $stmt_kelas->fetchAll();
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
if ($kelas_id > 0 && !array_filter($kelas_list, static fn($kelas) => (int)$kelas['id'] === $kelas_id)) {
    $kelas_id = 0;
}

$filter_kelas_sql = $kelas_id > 0 ? ' AND p.kelas_id = ?' : '';
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
     WHERE p.guru_id = ? $filter_kelas_sql
     GROUP BY sa.id
     ORDER BY sa.tanggal DESC, sa.pertemuan_ke DESC"
);
$parameter_sesi = [$guru_id];
if ($kelas_id > 0) $parameter_sesi[] = $kelas_id;
$stmt_sesi->execute($parameter_sesi);
$seluruh_sesi = $stmt_sesi->fetchAll();
$sesi_per_halaman = 5;
$total_sesi = count($seluruh_sesi);
$total_sesi_dibuka = count(array_filter($seluruh_sesi, fn($sesi) => $sesi['status'] === 'Dibuka'));
$total_sesi_selesai = $total_sesi - $total_sesi_dibuka;
$total_checkin = array_sum(array_map(fn($sesi) => (int)$sesi['jumlah_tercatat'], $seluruh_sesi));
$total_halaman = max(1, (int)ceil($total_sesi / $sesi_per_halaman));
$halaman = max(1, min((int)($_GET['page'] ?? 1), $total_halaman));
$sesi_list = array_slice($seluruh_sesi, ($halaman - 1) * $sesi_per_halaman, $sesi_per_halaman);
$query_filter = $kelas_id > 0 ? '&amp;kelas_id=' . $kelas_id : '';
?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<link rel="stylesheet" href="../assets/css/riwayat-absensi-guru.css?v=1">
<div id="page-content-wrapper" class="history-page">
    <nav class="navbar top-navbar px-3 px-md-4 py-3"><div class="history-header d-flex justify-content-between align-items-center gap-2 w-100"><div><h5 class="fw-bold mb-0"><span class="history-title-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>Riwayat Absensi</h5><small class="text-muted">Pantau seluruh sesi yang pernah dibuat</small></div><div class="history-header-actions d-flex gap-2"><a href="rekap_absensi.php" class="btn btn-sm btn-outline-success flex-shrink-0"><i class="fa-solid fa-chart-column me-1"></i><span>Rekap Kelas</span></a><a href="absensi.php" class="btn btn-sm btn-primary flex-shrink-0"><i class="fa-solid fa-plus me-1"></i><span>Buka Absensi</span></a></div></div></nav>
    <main class="container-fluid history-content p-3 p-md-4">
        <section class="card history-filter mb-3"><div class="card-body p-3"><div class="history-filter-heading"><span class="history-section-icon"><i class="fa-solid fa-filter" aria-hidden="true"></i></span><div><h2>Telusuri riwayat kelas</h2><p>Pilih kelas untuk melihat sesi dan ringkasan kehadirannya.</p></div></div><form method="get" class="row g-2 align-items-end"><div class="col-md-9"><label for="riwayatFilterKelas" class="form-label fw-semibold"><i class="fa-solid fa-chalkboard-user me-2" aria-hidden="true"></i>Filter Kelas</label><select id="riwayatFilterKelas" class="form-select" name="kelas_id" onchange="this.form.submit()"><option value="0">Semua Kelas</option><?php foreach($kelas_list as $kelas): ?><option value="<?= (int)$kelas['id'] ?>" <?= $kelas_id===(int)$kelas['id']?'selected':'' ?>><?= sanitize($kelas['nama_kelas']) ?></option><?php endforeach ?></select></div><div class="col-md-3"><a href="riwayat_absensi.php" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-rotate-left me-1"></i>Reset Filter</a></div></form></div></section>
        <section class="history-summary mb-3">
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-primary-subtle text-primary"><i class="fa-solid fa-calendar-days"></i></span><div><strong class="d-block"><?= $total_sesi ?></strong><small class="text-muted">Total Sesi</small></div></div></div>
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-success-subtle text-success"><i class="fa-solid fa-door-open"></i></span><div><strong class="d-block"><?= $total_sesi_dibuka ?></strong><small class="text-muted">Sedang Dibuka</small></div></div></div>
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-secondary-subtle text-secondary"><i class="fa-solid fa-circle-check"></i></span><div><strong class="d-block"><?= $total_sesi_selesai ?></strong><small class="text-muted">Selesai</small></div></div></div>
            <div class="card history-summary-card"><div class="card-body p-3 d-flex align-items-center gap-2"><span class="summary-icon bg-info-subtle text-info"><i class="fa-solid fa-users"></i></span><div><strong class="d-block"><?= $total_checkin ?></strong><small class="text-muted">Data Tercatat</small></div></div></div>
        </section>
        <section class="card history-panel"><div class="card-body p-3 p-md-4">
            <div class="history-list-heading d-flex justify-content-between align-items-center gap-2 mb-3"><div><h2 class="fw-bold mb-1"><span class="history-section-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>Sesi Absensi</h2><small class="text-muted">Maksimal 5 sesi per halaman</small></div><span class="badge bg-primary-subtle text-primary"><?= $total_sesi ?> sesi</span></div>
            <?php if (!$sesi_list): ?><div class="text-center py-5"><i class="fa-solid fa-calendar-xmark fa-2x text-muted mb-2"></i><p class="text-muted mb-0">Belum ada sesi absensi.</p></div><?php else: ?>
            <div class="table-responsive history-table-scroll d-none d-md-block" tabindex="0" role="region" aria-label="Riwayat sesi absensi, geser untuk melihat seluruh kolom"><table class="table history-table table-hover align-middle mb-0"><caption class="visually-hidden">Daftar sesi absensi beserta jadwal, status, dan ringkasan kehadiran</caption><thead class="table-light"><tr><th scope="col"><i class="fa-solid fa-book-open me-2" aria-hidden="true"></i>Pembelajaran</th><th scope="col"><i class="fa-solid fa-calendar-days me-2" aria-hidden="true"></i>Jadwal</th><th scope="col"><i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>Status</th><th scope="col"><i class="fa-solid fa-chart-pie me-2" aria-hidden="true"></i>Ringkasan</th><th scope="col"><i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Aksi</th></tr></thead><tbody><?php foreach($sesi_list as $sesi): ?><tr><td><strong><?= sanitize($sesi['nama_mapel']) ?></strong><br><small class="text-muted"><?= sanitize($sesi['nama_kelas']) ?> &middot; Pertemuan <?= (int)$sesi['pertemuan_ke'] ?></small></td><td><?= date('d/m/Y',strtotime($sesi['tanggal'])) ?><br><small class="text-muted"><?= date('H:i',strtotime($sesi['waktu_buka'])) ?>–<?= date('H:i',strtotime($sesi['waktu_tutup'])) ?></small></td><td><span class="badge <?= $sesi['status']==='Dibuka'?'bg-success':'bg-secondary' ?>"><?= sanitize($sesi['status']) ?></span></td><td><div class="attendance-chips"><span class="text-success"><i class="fa-solid fa-user-check me-1" aria-hidden="true"></i>Hadir <?= (int)$sesi['jumlah_hadir'] ?></span><span class="text-secondary"><i class="fa-solid fa-notes-medical me-1" aria-hidden="true"></i>Sakit <?= (int)$sesi['jumlah_sakit'] ?></span><span class="text-primary"><i class="fa-solid fa-envelope-open-text me-1" aria-hidden="true"></i>Izin <?= (int)$sesi['jumlah_izin'] ?></span><span class="text-danger"><i class="fa-solid fa-user-xmark me-1" aria-hidden="true"></i>Alpa <?= (int)$sesi['jumlah_alpa'] ?></span></div></td><td><?php if($sesi['status']==='Dibuka'): ?><form method="post" action="absensi.php" onsubmit="return confirm('Tutup sesi? Siswa yang belum check-in akan menjadi Alpa.');"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="tutup"><input type="hidden" name="sesi_id" value="<?= (int)$sesi['id'] ?>"><button class="btn btn-sm btn-danger"><i class="fa-solid fa-lock me-1"></i>Tutup</button></form><?php else: ?><small class="text-muted"><i class="fa-solid fa-check me-1"></i>Selesai</small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="d-md-none"><?php foreach($sesi_list as $sesi): ?><article class="session-mobile-card <?= $sesi['status']==='Dibuka'?'session-open':'' ?>"><div class="d-flex justify-content-between align-items-start gap-2 mb-2"><span class="badge bg-primary">Pertemuan <?= (int)$sesi['pertemuan_ke'] ?></span><span class="badge <?= $sesi['status']==='Dibuka'?'bg-success':'bg-secondary' ?>"><?= sanitize($sesi['status']) ?></span></div><h6 class="fw-bold mb-1"><?= sanitize($sesi['nama_mapel']) ?></h6><p class="small text-muted mb-3"><i class="fa-solid fa-school me-1"></i><?= sanitize($sesi['nama_kelas']) ?> &middot; <?= date('d/m/Y',strtotime($sesi['tanggal'])) ?><br><i class="fa-regular fa-clock me-1"></i><?= date('H:i',strtotime($sesi['waktu_buka'])) ?>–<?= date('H:i',strtotime($sesi['waktu_tutup'])) ?></p><div class="session-stats"><div><strong class="text-success"><?= (int)$sesi['jumlah_hadir'] ?></strong><small><i class="fa-solid fa-user-check me-1" aria-hidden="true"></i>Hadir</small></div><div><strong class="text-secondary"><?= (int)$sesi['jumlah_sakit'] ?></strong><small><i class="fa-solid fa-notes-medical me-1" aria-hidden="true"></i>Sakit</small></div><div><strong class="text-primary"><?= (int)$sesi['jumlah_izin'] ?></strong><small><i class="fa-solid fa-envelope-open-text me-1" aria-hidden="true"></i>Izin</small></div><div><strong class="text-danger"><?= (int)$sesi['jumlah_alpa'] ?></strong><small><i class="fa-solid fa-user-xmark me-1" aria-hidden="true"></i>Alpa</small></div></div><?php if($sesi['status']==='Dibuka'): ?><form method="post" action="absensi.php" class="mt-3" onsubmit="return confirm('Tutup sesi? Siswa yang belum check-in akan menjadi Alpa.');"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="tutup"><input type="hidden" name="sesi_id" value="<?= (int)$sesi['id'] ?>"><button class="btn btn-sm btn-danger w-100"><i class="fa-solid fa-lock me-1"></i>Tutup Sesi</button></form><?php endif; ?></article><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if($total_sesi>0): ?><nav class="history-pagination mt-3 pt-3 border-top" aria-label="Navigasi riwayat absensi"><div class="d-flex d-md-none justify-content-between align-items-center"><a class="btn btn-sm btn-outline-primary <?= $halaman<=1?'disabled':'' ?>" href="?page=<?= max(1,$halaman-1) ?><?= $query_filter ?>"><i class="fa-solid fa-chevron-left me-1"></i>Sebelumnya</a><small class="text-muted"><?= $halaman ?> / <?= $total_halaman ?></small><a class="btn btn-sm btn-outline-primary <?= $halaman>=$total_halaman?'disabled':'' ?>" href="?page=<?= min($total_halaman,$halaman+1) ?><?= $query_filter ?>">Berikutnya<i class="fa-solid fa-chevron-right ms-1"></i></a></div><ul class="pagination pagination-sm justify-content-center d-none d-md-flex mb-0"><li class="page-item <?= $halaman<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= max(1,$halaman-1) ?><?= $query_filter ?>">&laquo;</a></li><?php for($i=1;$i<=$total_halaman;$i++): ?><li class="page-item <?= $i===$halaman?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?><?= $query_filter ?>"><?= $i ?></a></li><?php endfor; ?><li class="page-item <?= $halaman>=$total_halaman?'disabled':'' ?>"><a class="page-link" href="?page=<?= min($total_halaman,$halaman+1) ?><?= $query_filter ?>">&raquo;</a></li></ul></nav><?php endif; ?>
        </div></section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
