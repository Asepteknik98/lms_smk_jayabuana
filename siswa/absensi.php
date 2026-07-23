<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([3]);
$db = Database::getInstance();

$stmt_siswa = $db->prepare(
    'SELECT s.id, s.kelas_id, s.nama_lengkap, k.nama_kelas
     FROM siswa s
     LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE s.user_id = ?'
);
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();
$siswa_id = (int)($siswa['id'] ?? 0);
$kelas_id = (int)($siswa['kelas_id'] ?? 0);
$filter_pengajaran=(int)($_GET['pengajaran_id']??0);
$stmt_mapel=$db->prepare('SELECT p.id,m.nama_mapel,g.nama_lengkap nama_guru FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id WHERE p.kelas_id=? ORDER BY m.nama_mapel');
$stmt_mapel->execute([$kelas_id]);$daftar_mapel=$stmt_mapel->fetchAll();

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
        redirect('absensi.php');
    }

    if (($_POST['action'] ?? '') !== 'checkin') {
        $_SESSION['flash_error'] = 'Aksi tidak dikenali.';
        redirect('absensi.php');
    }

    $sesi_id = (int)($_POST['sesi_id'] ?? 0);
    if (!$siswa_id || !$kelas_id || $sesi_id < 1) {
        $_SESSION['flash_error'] = 'Data Siswa atau sesi absensi tidak valid.';
        redirect('absensi.php');
    }

    try {
        $db->beginTransaction();
        $stmt_sesi = $db->prepare(
            "SELECT sa.id, sa.pertemuan_ke, sa.waktu_buka, sa.waktu_tutup,
                    sa.status, m.nama_mapel
             FROM sesi_absensi sa
             JOIN pengajaran p ON p.id = sa.pengajaran_id
             JOIN mapel m ON m.id = p.mapel_id
             WHERE sa.id = ? AND p.kelas_id = ?
             FOR UPDATE"
        );
        $stmt_sesi->execute([$sesi_id, $kelas_id]);
        $sesi = $stmt_sesi->fetch();

        if (!$sesi) {
            throw new RuntimeException('Sesi absensi tidak ditemukan untuk kelas Anda.');
        }
        if ($sesi['status'] !== 'Dibuka') {
            throw new RuntimeException('Sesi absensi sudah ditutup oleh Guru.');
        }

        $sekarang = new DateTime();
        $waktu_buka = new DateTime($sesi['waktu_buka']);
        $waktu_tutup = new DateTime($sesi['waktu_tutup']);

        if ($sekarang < $waktu_buka) {
            throw new RuntimeException('Sesi absensi belum dibuka.');
        }
        if ($sekarang > $waktu_tutup) {
            throw new RuntimeException('Waktu check-in sudah berakhir. Hubungi Guru jika ada kendala.');
        }

        $status = 'Hadir';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt_checkin = $db->prepare(
            'INSERT INTO detail_absensi
                (sesi_absensi_id, siswa_id, status, waktu_checkin, ip_address)
             VALUES (?, ?, ?, NOW(), ?)'
        );
        $stmt_checkin->execute([$sesi_id, $siswa_id, $status, $ip_address]);
        $db->commit();

        catat_log($_SESSION['user_id'], "Check-in absensi {$sesi['nama_mapel']} pertemuan {$sesi['pertemuan_ke']}: $status");
        $_SESSION['flash_success'] = 'Absensi berhasil dicatat.';
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Check-in absensi gagal: ' . $e->getMessage());
        $_SESSION['flash_error'] = $e->getCode() === '23000'
            ? 'Anda sudah melakukan check-in pada sesi ini.'
            : 'Check-in gagal disimpan. Silakan coba kembali.';
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['flash_error'] = $e->getMessage();
    }
    redirect('absensi.php');
}

$stmt_aktif = $db->prepare(
    "SELECT sa.id, sa.pertemuan_ke, sa.tanggal, sa.waktu_buka,
            sa.waktu_tutup, sa.status AS status_sesi,
            p.semester, p.tahun_ajaran, m.nama_mapel, g.nama_lengkap AS nama_guru,
            da.status AS status_siswa, da.waktu_checkin
     FROM sesi_absensi sa
     JOIN pengajaran p ON p.id = sa.pengajaran_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN guru g ON g.id = p.guru_id
     LEFT JOIN detail_absensi da ON da.sesi_absensi_id = sa.id AND da.siswa_id = ?
     WHERE p.kelas_id = ? AND sa.status = 'Dibuka'
     ORDER BY
       CASE WHEN da.id IS NULL AND NOW() BETWEEN sa.waktu_buka AND sa.waktu_tutup THEN 0
            WHEN da.id IS NULL AND NOW() < sa.waktu_buka THEN 1
            WHEN da.id IS NOT NULL THEN 2
            ELSE 3 END,
       sa.waktu_buka DESC, sa.id DESC"
);
$stmt_aktif->execute([$siswa_id, $kelas_id]);
$sesi_aktif = $stmt_aktif->fetchAll();

$sql_riwayat =
    "SELECT sa.pertemuan_ke, sa.tanggal, p.semester, p.tahun_ajaran,
            m.nama_mapel, g.nama_lengkap AS nama_guru,
            da.status, da.waktu_checkin, da.keterangan
     FROM detail_absensi da
     JOIN sesi_absensi sa ON sa.id = da.sesi_absensi_id
     JOIN pengajaran p ON p.id = sa.pengajaran_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN guru g ON g.id = p.guru_id
     WHERE da.siswa_id = ?".($filter_pengajaran>0?' AND p.id=?':'')."
     ORDER BY sa.tanggal DESC, COALESCE(da.waktu_checkin,sa.waktu_buka) DESC, sa.pertemuan_ke DESC";
$stmt_riwayat = $db->prepare($sql_riwayat);
$stmt_riwayat->execute($filter_pengajaran>0?[$siswa_id,$filter_pengajaran]:[$siswa_id]);
$riwayat = $stmt_riwayat->fetchAll();
$riwayat_per_halaman=5;$total_halaman_riwayat=max(1,(int)ceil(count($riwayat)/$riwayat_per_halaman));
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .attendance-page { background:#f5f7fb; min-width:0; }
    .attendance-content { max-width:1100px; margin:0 auto; }
    .attendance-card { border:0; border-radius:17px; box-shadow:0 6px 20px rgba(15,23,42,.06); overflow:hidden; }
    .attendance-card.is-ready { border:1px solid rgba(25,135,84,.22); box-shadow:0 8px 24px rgba(25,135,84,.10); }
    .attendance-card .session-title { font-size:1rem; line-height:1.35; overflow-wrap:anywhere; }
    .attendance-meta { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; }
    .attendance-meta > div { background:#f8fafc; border-radius:10px; padding:9px 6px; text-align:center; min-width:0; }
    .attendance-meta small { display:block; color:#64748b; font-size:.65rem; margin-bottom:2px; }
    .attendance-meta strong { font-size:.78rem; white-space:nowrap; }
    .checkin-button { min-height:48px; border-radius:12px; font-weight:700; }
    .history-item { border-bottom:1px solid #edf1f5; padding:13px 0; }
    .history-item:last-child { border-bottom:0; }
    .history-copy { min-width:0; }
    .history-copy strong,.history-copy small { overflow-wrap:anywhere; }
    @media (max-width:575.98px) {
        .attendance-content { padding:13px!important; }
        .attendance-card { border-radius:14px; }
        .attendance-card .card-body { padding:15px!important; }
        .attendance-grid { display:block; margin:0; }
        .attendance-grid > .attendance-slide { display:none; width:100%; padding:0; }
        .attendance-grid > .attendance-slide.is-active { display:block; }
        .attendance-pagination { display:flex!important; }
    }
</style>
<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-3 px-md-4 py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
            <div><h5 class="mb-0 fw-bold"><i class="fa-solid fa-user-check text-primary me-2"></i>Absensi Saya</h5><small class="text-muted">Check-in saat guru membuka sesi</small></div>
            <span class="badge bg-primary-subtle text-primary px-3 py-2"><?= sanitize($siswa['nama_kelas'] ?? 'Kelas belum ditentukan') ?></span>
        </div>
    </nav>

    <div class="container-fluid attendance-content p-3 p-md-4">
        <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($success) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <?php if (!$siswa): ?>
            <div class="alert alert-danger">Profil Siswa tidak ditemukan. Hubungi Administrator.</div>
        <?php elseif (!$kelas_id): ?>
            <div class="alert alert-warning">Anda belum ditempatkan pada kelas.</div>
        <?php else: ?>
            <?php if ($sesi_aktif): ?>
            <div class="row g-3 attendance-grid mb-3">
                <?php foreach ($sesi_aktif as $sesi_index=>$sesi): ?>
                    <?php
                    $sekarang = new DateTime();
                    $belum_dibuka = $sekarang < new DateTime($sesi['waktu_buka']);
                    $sudah_berakhir = $sekarang > new DateTime($sesi['waktu_tutup']);
                    $sudah_checkin = !empty($sesi['status_siswa']);
                    ?>
                    <div class="col-12 col-md-6 col-xl-4 attendance-slide <?= $sesi_index===0?'is-active':'' ?>" data-attendance-index="<?= $sesi_index ?>">
                        <article class="card attendance-card <?= (!$sudah_checkin && !$sudah_berakhir && !$belum_dibuka) ? 'is-ready' : '' ?> h-100">
                            <div class="card-body p-3 d-flex flex-column">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <span class="badge bg-primary">Pertemuan <?= (int)$sesi['pertemuan_ke'] ?></span>
                                    <?php if($sudah_checkin): ?><span class="badge bg-success">Selesai</span><?php elseif($sudah_berakhir): ?><span class="badge bg-secondary">Berakhir</span><?php elseif($belum_dibuka): ?><span class="badge bg-secondary">Belum mulai</span><?php else: ?><span class="badge bg-success"><i class="fa-solid fa-circle fa-2xs me-1"></i>Bisa check-in</span><?php endif; ?>
                                </div>
                                <h2 class="session-title fw-bold mb-1"><?= sanitize($sesi['nama_mapel']) ?></h2>
                                <p class="text-muted mb-3" style="font-size:.75rem;"><i class="fa-solid fa-user-tie me-1"></i><?= sanitize($sesi['nama_guru']) ?> &middot; <?= sanitize($sesi['semester']) ?></p>
                                <div class="attendance-meta mb-3">
                                    <div><small>Tanggal</small><strong><?= date('d/m/Y', strtotime($sesi['tanggal'])) ?></strong></div>
                                    <div><small>Waktu</small><strong><?= date('H:i', strtotime($sesi['waktu_buka'])) ?>–<?= date('H:i', strtotime($sesi['waktu_tutup'])) ?></strong></div>
                                    <div><small>Check-in dibuka</small><strong>s.d. <?= date('H:i', strtotime($sesi['waktu_tutup'])) ?></strong></div>
                                </div>
                                <div class="mt-auto">
                                <?php if ($sudah_checkin): ?>
                                    <div class="alert alert-success py-2 mb-0 text-center small"><i class="fa-solid fa-circle-check me-1"></i><strong>Sudah Absen</strong> pukul <?= date('H:i:s', strtotime($sesi['waktu_checkin'])) ?></div>
                                <?php elseif ($sudah_berakhir): ?>
                                    <div class="alert alert-danger mb-0 text-center"><strong>Waktu Check-in Berakhir</strong><br><small>Hubungi Guru jika mengalami kendala.</small></div>
                                <?php elseif ($belum_dibuka): ?>
                                    <button class="btn btn-secondary w-100" disabled>Belum Dibuka</button>
                                <?php else: ?>
                                    <form method="post" action="absensi.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="checkin">
                                        <input type="hidden" name="sesi_id" value="<?= (int)$sesi['id'] ?>">
                                        <button type="submit" class="btn btn-success checkin-button w-100"><i class="fa-solid fa-fingerprint me-2"></i>Check-in Sekarang</button>
                                    </form>
                                <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if(count($sesi_aktif)>1): ?><nav class="attendance-pagination d-none justify-content-between align-items-center gap-2 mb-4" aria-label="Navigasi sesi absensi"><button type="button" class="btn btn-outline-primary btn-sm" id="attendancePrev" disabled><i class="fa-solid fa-chevron-left me-1"></i>Sebelumnya</button><span class="small text-muted"><strong id="attendanceCurrent">1</strong> / <?= count($sesi_aktif) ?></span><button type="button" class="btn btn-primary btn-sm" id="attendanceNext">Selanjutnya<i class="fa-solid fa-chevron-right ms-1"></i></button></nav><?php endif ?>
            <?php endif; ?>

            <div class="card attendance-card"><div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><h6 class="fw-bold mb-0">Riwayat Kehadiran</h6><form method="get" class="d-flex gap-1 flex-grow-1 flex-md-grow-0"><select class="form-select form-select-sm" name="pengajaran_id" aria-label="Filter mata pelajaran"><option value="0">Semua Mata Pelajaran</option><?php foreach($daftar_mapel as $mapel): ?><option value="<?= (int)$mapel['id'] ?>" <?= $filter_pengajaran===(int)$mapel['id']?'selected':'' ?>><?= sanitize($mapel['nama_mapel'].' — '.$mapel['nama_guru']) ?></option><?php endforeach ?></select><button class="btn btn-sm btn-primary"><i class="fa-solid fa-filter"></i><span class="visually-hidden">Terapkan filter</span></button></form></div>
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Tanggal</th><th>Pembelajaran</th><th>Pertemuan</th><th>Status</th><th>Check-in/Keterangan</th></tr></thead>
                        <tbody>
                        <?php foreach ($riwayat as $item): ?>
                            <?php $label_status=$item['status']?:'Belum Absen';$warna_status=['Hadir'=>'bg-success','Sakit'=>'bg-warning text-dark','Izin'=>'bg-info text-dark','Alpa'=>'bg-danger'][$label_status]??'bg-secondary'; ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($item['tanggal'])) ?></td>
                                <td><strong><?= sanitize($item['nama_mapel']) ?></strong><br><small class="text-muted"><?= sanitize($item['nama_guru']) ?> · <?= sanitize($item['semester']) ?></small></td>
                                <td><?= (int)$item['pertemuan_ke'] ?></td>
                                <td><span class="badge <?= $warna_status ?>"><?= sanitize($label_status) ?></span></td>
                                <td><?= $item['waktu_checkin'] ? date('H:i:s', strtotime($item['waktu_checkin'])) : sanitize($item['keterangan'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-md-none">
                    <?php foreach ($riwayat as $riwayat_index=>$item): ?>
                        <?php $label_status=$item['status']?:'Belum Absen';$warna_status=['Hadir'=>'bg-success','Sakit'=>'bg-warning text-dark','Izin'=>'bg-info text-dark','Alpa'=>'bg-danger'][$label_status]??'bg-secondary'; ?>
                        <div class="history-item history-mobile-item <?= $riwayat_index>=$riwayat_per_halaman?'d-none':'' ?>" data-history-page="<?= intdiv($riwayat_index,$riwayat_per_halaman)+1 ?>"><div class="d-flex align-items-start gap-2"><span class="badge <?= $warna_status ?> mt-1"><?= sanitize($label_status) ?></span><div class="history-copy flex-grow-1"><strong class="d-block small"><?= sanitize($item['nama_mapel']) ?></strong><small class="text-muted d-block"><?= date('d/m/Y',strtotime($item['tanggal'])) ?> &middot; Pertemuan <?= (int)$item['pertemuan_ke'] ?></small><small class="text-muted"><?= sanitize($item['nama_guru']) ?><?php if($item['waktu_checkin']): ?> &middot; <?= date('H:i:s',strtotime($item['waktu_checkin'])) ?><?php elseif($item['keterangan']): ?> &middot; <?= sanitize($item['keterangan']) ?><?php endif; ?></small></div></div></div>
                    <?php endforeach; ?>
                    <?php if($total_halaman_riwayat>1): ?><nav class="d-flex justify-content-between align-items-center gap-2 pt-3" aria-label="Navigasi riwayat kehadiran"><button type="button" class="btn btn-sm btn-outline-primary" id="historyPrev" disabled><i class="fa-solid fa-chevron-left me-1"></i>Sebelumnya</button><span class="small text-muted"><strong id="historyCurrent">1</strong> / <?= $total_halaman_riwayat ?></span><button type="button" class="btn btn-sm btn-primary" id="historyNext">Selanjutnya<i class="fa-solid fa-chevron-right ms-1"></i></button></nav><?php endif ?>
                </div>
                <?php if (!$riwayat): ?><p class="text-center text-muted mb-0">Belum ada riwayat absensi.</p><?php endif; ?>
            </div></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php if(count($sesi_aktif)>1): ?><script>
document.addEventListener('DOMContentLoaded',function(){const cards=[...document.querySelectorAll('.attendance-slide')],prev=document.getElementById('attendancePrev'),next=document.getElementById('attendanceNext'),current=document.getElementById('attendanceCurrent');let index=0;function show(target){index=Math.max(0,Math.min(target,cards.length-1));cards.forEach((card,i)=>card.classList.toggle('is-active',i===index));current.textContent=index+1;prev.disabled=index===0;next.disabled=index===cards.length-1}prev.addEventListener('click',()=>show(index-1));next.addEventListener('click',()=>show(index+1));show(0)});
</script><?php endif ?>
<?php if($total_halaman_riwayat>1): ?><script>
document.addEventListener('DOMContentLoaded',function(){const items=[...document.querySelectorAll('.history-mobile-item')],prev=document.getElementById('historyPrev'),next=document.getElementById('historyNext'),current=document.getElementById('historyCurrent'),total=<?= $total_halaman_riwayat ?>;let page=1;function show(target){page=Math.max(1,Math.min(target,total));items.forEach(item=>item.classList.toggle('d-none',Number(item.dataset.historyPage)!==page));current.textContent=page;prev.disabled=page===1;next.disabled=page===total}prev.addEventListener('click',()=>show(page-1));next.addEventListener('click',()=>show(page+1));show(1)});
</script><?php endif ?>
