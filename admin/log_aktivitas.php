<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/auth.php';

check_access([1]);
$db = Database::getInstance();

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_log') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
        redirect('log_aktivitas.php');
    }
    try {
        $db->beginTransaction();
        $db->exec('DELETE FROM log_aktivitas');
        $db->commit();
        catat_log($_SESSION['user_id'], 'Membersihkan seluruh log aktivitas');
        $_SESSION['flash_success'] = 'Log lama berhasil dibersihkan. Tindakan ini dicatat sebagai log baru.';
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Gagal membersihkan log aktivitas: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Log aktivitas gagal dibersihkan.';
    }
    redirect('log_aktivitas.php');
}

$q = trim($_GET['q'] ?? '');
$filter_role_id = (int)($_GET['role_id'] ?? 0);
$tanggal_mulai = trim($_GET['tanggal_mulai'] ?? '');
$tanggal_akhir = trim($_GET['tanggal_akhir'] ?? '');
$per_page_options = [25, 50, 100];
$per_page = (int)($_GET['per_page'] ?? 25);
if (!in_array($per_page, $per_page_options, true)) $per_page = 25;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(u.username LIKE ? OR l.aktivitas LIKE ? OR l.ip_address LIKE ?)';
    $keyword = '%' . $q . '%';
    array_push($params, $keyword, $keyword, $keyword);
}
if ($filter_role_id > 0) {
    $where[] = 'u.role_id = ?';
    $params[] = $filter_role_id;
}
if ($tanggal_mulai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai)) {
    $where[] = 'l.created_at >= ?';
    $params[] = $tanggal_mulai . ' 00:00:00';
}
if ($tanggal_akhir !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir)) {
    $where[] = 'l.created_at <= ?';
    $params[] = $tanggal_akhir . ' 23:59:59';
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$base_from = ' FROM log_aktivitas l LEFT JOIN users u ON u.id=l.user_id LEFT JOIN roles r ON r.id=u.role_id ';

if (($_GET['export'] ?? '') === 'csv') {
    $stmt_export = $db->prepare('SELECT l.created_at,u.username,r.nama_role,l.aktivitas,l.ip_address,l.user_agent' . $base_from . $where_sql . ' ORDER BY l.created_at DESC');
    $stmt_export->execute($params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Log_Aktivitas_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBFsep=;\r\n");
    fputcsv($output, ['Waktu','Username','Role','Aktivitas','IP Address','Perangkat'], ';');
    while ($row = $stmt_export->fetch()) {
        $values = array_values($row);
        foreach ($values as &$value) {
            if (preg_match('/^[=+\-@]/', (string)$value)) $value = "'" . $value;
        }
        unset($value);
        fputcsv($output, $values, ';');
    }
    fclose($output);
    exit;
}

$stmt_count = $db->prepare('SELECT COUNT(*)' . $base_from . $where_sql);
$stmt_count->execute($params);
$total_filtered = (int)$stmt_count->fetchColumn();
$total_pages = max(1, (int)ceil($total_filtered / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt_log = $db->prepare('SELECT l.*,u.username,r.nama_role' . $base_from . $where_sql . ' ORDER BY l.created_at DESC,l.id DESC LIMIT ? OFFSET ?');
$index = 1;
foreach ($params as $param) $stmt_log->bindValue($index++, $param, PDO::PARAM_STR);
$stmt_log->bindValue($index++, $per_page, PDO::PARAM_INT);
$stmt_log->bindValue($index, $offset, PDO::PARAM_INT);
$stmt_log->execute();
$daftar_log = $stmt_log->fetchAll();

$total_log = (int)$db->query('SELECT COUNT(*) FROM log_aktivitas')->fetchColumn();
$log_hari_ini = (int)$db->query('SELECT COUNT(*) FROM log_aktivitas WHERE DATE(created_at)=CURDATE()')->fetchColumn();
$pengguna_aktif = (int)$db->query('SELECT COUNT(DISTINCT user_id) FROM log_aktivitas WHERE created_at >= NOW()-INTERVAL 24 HOUR')->fetchColumn();
$login_hari_ini = (int)$db->query("SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas='Login Berhasil' AND DATE(created_at)=CURDATE()")->fetchColumn();
$daftar_role = $db->query('SELECT id,nama_role FROM roles ORDER BY id')->fetchAll();
$query_state = ['q'=>$q,'role_id'=>$filter_role_id,'tanggal_mulai'=>$tanggal_mulai,'tanggal_akhir'=>$tanggal_akhir,'per_page'=>$per_page];
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div id="page-content-wrapper" class="log-page">
    <style>
        .log-page{background:#f7f9fc;min-height:100vh}.log-hero{background:linear-gradient(135deg,#172554,#1d4ed8);color:#fff;border-radius:22px;padding:28px;box-shadow:0 18px 44px rgba(29,78,216,.17)}.log-card,.stat-card{border:1px solid #e8edf5!important;border-radius:18px!important;box-shadow:0 8px 28px rgba(15,23,42,.045)!important}.stat-icon{width:45px;height:45px;display:grid;place-items:center;border-radius:13px}.filter-box{background:#fff;border:1px solid #e8edf5;border-radius:16px;padding:16px}.log-table thead th{background:#f8fafc;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}.log-table>:not(caption)>*>*{padding:.9rem .75rem;border-bottom-color:#edf1f6}.activity-dot{width:9px;height:9px;border-radius:50%;background:#3b82f6;display:inline-block}.pagination .page-link{border:0;border-radius:9px!important;margin:0 2px;color:#475569}.pagination .active .page-link{background:#1d4ed8}.device-details summary{cursor:pointer;color:#64748b;font-size:.75rem}.device-details div{max-width:360px;white-space:normal;font-size:.72rem;color:#64748b;margin-top:5px}.role-admin{background:#fee2e2;color:#b91c1c}.role-guru{background:#dcfce7;color:#15803d}.role-siswa{background:#dbeafe;color:#1d4ed8}@media(max-width:767.98px){.log-content{padding:18px!important}.log-hero{padding:22px;border-radius:18px}}
    </style>
    <nav class="navbar navbar-light bg-white border-bottom px-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Audit Aktivitas</h5><small class="text-muted">Jejak penggunaan dan keamanan LMS</small></div></nav>
    <div class="container-fluid p-4 log-content">
        <?php if($success): ?><script>Swal.fire({icon:'success',title:'Berhasil',text:<?= json_encode($success) ?>});</script><?php endif; ?>
        <?php if($error): ?><script>Swal.fire({icon:'error',title:'Gagal',text:<?= json_encode($error) ?>});</script><?php endif; ?>

        <section class="log-hero mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><small class="text-uppercase fw-semibold opacity-75">Keamanan Sistem</small><h3 class="fw-bold mb-1 mt-1">Log Aktivitas LMS</h3><p class="mb-0 opacity-75">Telusuri login, perubahan data, pembelajaran, dan aktivitas pengguna.</p></div><a href="?<?= http_build_query(array_merge($query_state,['export'=>'csv'])) ?>" class="btn btn-light text-success fw-semibold"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</a></div></section>

        <div class="row g-3 mb-4">
            <?php foreach ([['Total Log',$total_log,'fa-database','primary'],['Hari Ini',$log_hari_ini,'fa-calendar-day','success'],['Pengguna 24 Jam',$pengguna_aktif,'fa-users','warning'],['Login Hari Ini',$login_hari_ini,'fa-right-to-bracket','info']] as [$label,$value,$icon,$color]): ?><div class="col-6 col-lg-3"><div class="card stat-card p-3 h-100"><div class="d-flex align-items-center gap-3"><span class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>"><i class="fa-solid <?= $icon ?>"></i></span><div><small class="text-muted"><?= $label ?></small><h4 class="fw-bold mb-0"><?= number_format($value,0,',','.') ?></h4></div></div></div></div><?php endforeach; ?>
        </div>

        <form method="get" id="filterLog" class="filter-box mb-4"><div class="row g-2 align-items-end">
            <div class="col-lg-4"><label class="form-label small fw-semibold">Cari</label><input type="search" name="q" id="searchLog" value="<?= sanitize($q) ?>" class="form-control" placeholder="Username, aktivitas, atau IP" autocomplete="off"></div>
            <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Role</label><select name="role_id" id="roleLog" class="form-select"><option value="0">Semua Role</option><?php foreach($daftar_role as $role): ?><option value="<?= (int)$role['id'] ?>" <?= $filter_role_id===(int)$role['id']?'selected':'' ?>><?= sanitize($role['nama_role']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Dari</label><input type="date" name="tanggal_mulai" value="<?= sanitize($tanggal_mulai) ?>" class="form-control auto-log-filter"></div>
            <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Sampai</label><input type="date" name="tanggal_akhir" value="<?= sanitize($tanggal_akhir) ?>" class="form-control auto-log-filter"></div>
            <div class="col-6 col-lg-2"><label class="form-label small fw-semibold">Per Halaman</label><select name="per_page" class="form-select" onchange="this.form.submit()"><?php foreach($per_page_options as $option): ?><option value="<?= $option ?>" <?= $per_page===$option?'selected':'' ?>><?= $option ?> baris</option><?php endforeach; ?></select></div>
        </div></form>

        <div class="card log-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3 border-bottom"><div><h6 class="fw-bold mb-0">Catatan Aktivitas</h6><small class="text-muted">Menampilkan <?= $total_filtered?$offset+1:0 ?>–<?= min($offset+$per_page,$total_filtered) ?> dari <?= number_format($total_filtered,0,',','.') ?> hasil</small></div><div class="d-flex gap-2"><?php if($q!==''||$filter_role_id||$tanggal_mulai||$tanggal_akhir): ?><a href="log_aktivitas.php" class="btn btn-sm btn-light">Reset Filter</a><?php endif; ?><button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalClearLog"><i class="fa-solid fa-trash-can me-1"></i>Bersihkan</button></div></div>
            <div class="table-responsive"><table class="table log-table table-hover align-middle mb-0"><thead><tr><th class="ps-4">Waktu</th><th>Pengguna</th><th>Role</th><th>Aktivitas</th><th>IP / Perangkat</th></tr></thead><tbody>
            <?php foreach($daftar_log as $log): $role_class='role-'.strtolower($log['nama_role']??''); ?><tr><td class="ps-4"><small class="fw-semibold"><?= date('d/m/Y',strtotime($log['created_at'])) ?></small><br><small class="text-muted"><?= date('H:i:s',strtotime($log['created_at'])) ?></small></td><td><strong><?= sanitize($log['username']?:'Sistem') ?></strong></td><td><span class="badge <?= $role_class ?>"><?= sanitize($log['nama_role']?:'Sistem') ?></span></td><td><span class="activity-dot me-2"></span><?= sanitize($log['aktivitas']) ?></td><td><code><?= sanitize($log['ip_address']?:'-') ?></code><details class="device-details"><summary>Perangkat</summary><div><?= sanitize($log['user_agent']?:'Tidak diketahui') ?></div></details></td></tr><?php endforeach; ?>
            <?php if(!$daftar_log): ?><tr><td colspan="5" class="text-center py-5"><i class="fa-solid fa-inbox fa-2x text-muted mb-2"></i><p class="text-muted mb-0">Tidak ada log yang sesuai filter.</p></td></tr><?php endif; ?>
            </tbody></table></div>
            <?php if($total_pages>1): ?><div class="d-flex justify-content-between align-items-center px-4 py-3 border-top"><small class="text-muted">Halaman <?= $page ?> dari <?= $total_pages ?></small><ul class="pagination pagination-sm mb-0"><li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>$page-1])) ?>">‹</a></li><?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>$i])) ?>"><?= $i ?></a></li><?php endfor; ?><li class="page-item <?= $page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>$page+1])) ?>">›</a></li></ul></div><?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalClearLog" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header border-0"><h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Bersihkan Log?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Seluruh catatan lama akan dihapus permanen. Gunakan Export CSV terlebih dahulu jika diperlukan untuk arsip.</p><div class="alert alert-warning small mb-0">Tindakan pembersihan akan dicatat kembali sebagai log baru.</div><input type="hidden" name="action" value="clear_log"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"></div><div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger">Ya, Bersihkan</button></div></form></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
(function(){const form=document.getElementById('filterLog'),search=document.getElementById('searchLog'),role=document.getElementById('roleLog');let timer;search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>form.requestSubmit(),500)});role.addEventListener('change',()=>form.requestSubmit());document.querySelectorAll('.auto-log-filter').forEach(el=>el.addEventListener('change',()=>form.requestSubmit()));})();
</script>
