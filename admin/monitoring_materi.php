<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([1]);
$db = Database::getInstance();

$guru_id = (int)($_GET['guru_id'] ?? 0);
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$mapel_id = (int)($_GET['mapel_id'] ?? 0);
$semester = in_array($_GET['semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_GET['semester'] : '';
$tahun_ajaran = trim($_GET['tahun_ajaran'] ?? '');
$pertemuan = (int)($_GET['pertemuan'] ?? 0);
$status_akses = in_array($_GET['status_akses'] ?? '', ['Dibuka', 'Dikunci'], true) ? $_GET['status_akses'] : '';
$q = trim($_GET['q'] ?? '');
$halaman = max(1, (int)($_GET['page'] ?? 1));
$per_halaman = 10;

if ($pertemuan < 1 || $pertemuan > 20) $pertemuan = 0;
if (mb_strlen($q) > 100) $q = mb_substr($q, 0, 100);
if (mb_strlen($tahun_ajaran) > 10) $tahun_ajaran = '';

$guru_list = $db->query('SELECT id,nama_lengkap FROM guru ORDER BY nama_lengkap')->fetchAll();
$kelas_list = $db->query('SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();
$mapel_list = $db->query('SELECT id,nama_mapel FROM mapel ORDER BY nama_mapel')->fetchAll();
$tahun_list = $db->query('SELECT DISTINCT tahun_ajaran FROM pengajaran ORDER BY tahun_ajaran DESC')->fetchAll(PDO::FETCH_COLUMN);

$where = [];
$params = [];
if ($guru_id > 0) { $where[] = 'p.guru_id=?'; $params[] = $guru_id; }
if ($kelas_id > 0) { $where[] = 'p.kelas_id=?'; $params[] = $kelas_id; }
if ($mapel_id > 0) { $where[] = 'p.mapel_id=?'; $params[] = $mapel_id; }
if ($semester !== '') { $where[] = 'p.semester=?'; $params[] = $semester; }
if ($tahun_ajaran !== '') { $where[] = 'p.tahun_ajaran=?'; $params[] = $tahun_ajaran; }
if ($pertemuan > 0) { $where[] = 'mat.pertemuan_ke=?'; $params[] = $pertemuan; }
if ($status_akses !== '') { $where[] = "COALESCE(ap.status,'Dikunci')=?"; $params[] = $status_akses; }
if ($q !== '') {
    $where[] = '(mat.judul LIKE ? OR mat.deskripsi LIKE ? OR g.nama_lengkap LIKE ? OR m.nama_mapel LIKE ? OR k.nama_kelas LIKE ?)';
    $cari = '%' . $q . '%';
    array_push($params, $cari, $cari, $cari, $cari, $cari);
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$from_sql =
    ' FROM materi mat
      JOIN pengajaran p ON p.id=mat.pengajaran_id
      JOIN guru g ON g.id=p.guru_id
      JOIN mapel m ON m.id=p.mapel_id
      JOIN kelas k ON k.id=p.kelas_id
      LEFT JOIN akses_pertemuan ap ON ap.pengajaran_id=mat.pengajaran_id
                                  AND ap.pertemuan_ke=mat.pertemuan_ke ';

$stmt = $db->prepare('SELECT COUNT(*)' . $from_sql . $where_sql);
$stmt->execute($params);
$total_materi = (int)$stmt->fetchColumn();
$total_halaman = max(1, (int)ceil($total_materi / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset = ($halaman - 1) * $per_halaman;

$stmt = $db->prepare(
    "SELECT mat.id,mat.pertemuan_ke,mat.judul,mat.deskripsi,mat.video_url,mat.file_path,mat.created_at,
            p.semester,p.tahun_ajaran,g.nama_lengkap AS nama_guru,m.nama_mapel,k.nama_kelas,
            COALESCE(ap.status,'Dikunci') AS status_akses,ap.dibuka_pada
     $from_sql $where_sql
     ORDER BY mat.created_at DESC,mat.id DESC LIMIT ? OFFSET ?"
);
$index = 1;
foreach ($params as $param) $stmt->bindValue($index++, $param, PDO::PARAM_STR);
$stmt->bindValue($index++, $per_halaman, PDO::PARAM_INT);
$stmt->bindValue($index, $offset, PDO::PARAM_INT);
$stmt->execute();
$materi_list = $stmt->fetchAll();

$stmt = $db->prepare(
    "SELECT COUNT(*) total,
            SUM(CASE WHEN COALESCE(ap.status,'Dikunci')='Dibuka' THEN 1 ELSE 0 END) dibuka,
            SUM(CASE WHEN COALESCE(ap.status,'Dikunci')='Dikunci' THEN 1 ELSE 0 END) dikunci,
            SUM(CASE WHEN mat.file_path IS NOT NULL AND mat.file_path<>'' THEN 1 ELSE 0 END) dengan_file
     $from_sql $where_sql"
);
$stmt->execute($params);
$ringkasan = $stmt->fetch() ?: ['total'=>0,'dibuka'=>0,'dikunci'=>0,'dengan_file'=>0];

$query_state = [
    'guru_id'=>$guru_id,'kelas_id'=>$kelas_id,'mapel_id'=>$mapel_id,'semester'=>$semester,
    'tahun_ajaran'=>$tahun_ajaran,'pertemuan'=>$pertemuan,'status_akses'=>$status_akses,'q'=>$q,
];
$filter_aktif = $guru_id || $kelas_id || $mapel_id || $semester || $tahun_ajaran || $pertemuan || $status_akses || $q !== '';

function url_video_materi_admin(?string $url): ?string {
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host);
    return in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be'], true) ? $url : null;
}
?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div id="page-content-wrapper" class="material-monitor-page">
<style>
.material-monitor-page{background:#f7f9fc;min-width:0}.monitor-content{max-width:1400px;margin:auto}.monitor-hero{background:linear-gradient(135deg,#172554,#1d4ed8);color:#fff;border-radius:22px;padding:28px;box-shadow:0 18px 44px rgba(29,78,216,.17)}.monitor-card,.metric-card{border:1px solid #e8edf5!important;border-radius:18px!important;box-shadow:0 8px 28px rgba(15,23,42,.045)!important}.metric-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:13px}.filter-card .form-label{font-size:.76rem;font-weight:700;margin-bottom:.35rem}.material-table th{white-space:nowrap;font-size:.75rem;text-transform:uppercase;color:#64748b}.material-table td{vertical-align:middle;font-size:.83rem}.material-title{max-width:280px;overflow-wrap:anywhere}.material-mobile{border:1px solid #e8edf5;border-radius:15px;padding:15px}.material-mobile+.material-mobile{margin-top:10px}.pagination .page-link{border:0;border-radius:9px!important;margin:0 2px}.pagination .active .page-link{background:#1d4ed8}@media(max-width:767.98px){.monitor-content{padding:14px!important}.monitor-hero{padding:21px;border-radius:18px}}
</style>
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-book-open text-primary me-2"></i>Monitoring Materi</h5><small class="text-muted">Pantau materi yang dipublikasikan seluruh guru</small></div></nav>
<main class="container-fluid monitor-content p-3 p-md-4">
    <section class="monitor-hero mb-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><small class="text-uppercase fw-semibold opacity-75">Kontrol Akademik</small><h2 class="h4 fw-bold my-1">Materi Pembelajaran Guru</h2><p class="mb-0 opacity-75">Lihat isi, file, video, dan status akses setiap pertemuan.</p></div><i class="fa-solid fa-folder-tree fa-2x opacity-50"></i></div></section>

    <div class="row g-2 mb-3">
        <?php foreach([
            ['Materi Ditemukan',(int)$ringkasan['total'],'fa-book-open','primary'],
            ['Akses Dibuka',(int)$ringkasan['dibuka'],'fa-lock-open','success'],
            ['Akses Dikunci',(int)$ringkasan['dikunci'],'fa-lock','secondary'],
            ['Memiliki File',(int)$ringkasan['dengan_file'],'fa-paperclip','info'],
        ] as [$label,$jumlah,$icon,$warna]): ?><div class="col-6 col-xl-3"><div class="card metric-card p-3 h-100"><div class="d-flex align-items-center gap-3"><span class="metric-icon bg-<?= $warna ?>-subtle text-<?= $warna ?>"><i class="fa-solid <?= $icon ?>"></i></span><div><small class="text-muted"><?= $label ?></small><h4 class="fw-bold mb-0"><?= $jumlah ?></h4></div></div></div></div><?php endforeach ?>
    </div>

    <section class="card monitor-card filter-card mb-3"><div class="card-body p-3">
        <form method="get" id="filterMateriAdmin" class="row g-2 align-items-end">
            <div class="col-md-6 col-xl-3"><label class="form-label">Cari Materi</label><input type="search" name="q" value="<?= sanitize($q) ?>" class="form-control" placeholder="Judul, guru, mapel, kelas..."></div>
            <div class="col-6 col-md-3 col-xl"><label class="form-label">Guru</label><select name="guru_id" class="form-select"><option value="0">Semua</option><?php foreach($guru_list as $guru): ?><option value="<?= (int)$guru['id'] ?>" <?= $guru_id===(int)$guru['id']?'selected':'' ?>><?= sanitize($guru['nama_lengkap']) ?></option><?php endforeach ?></select></div>
            <div class="col-6 col-md-3 col-xl"><label class="form-label">Kelas</label><select name="kelas_id" class="form-select"><option value="0">Semua</option><?php foreach($kelas_list as $kelas): ?><option value="<?= (int)$kelas['id'] ?>" <?= $kelas_id===(int)$kelas['id']?'selected':'' ?>><?= sanitize($kelas['nama_kelas']) ?></option><?php endforeach ?></select></div>
            <div class="col-6 col-md-3 col-xl"><label class="form-label">Mapel</label><select name="mapel_id" class="form-select"><option value="0">Semua</option><?php foreach($mapel_list as $mapel): ?><option value="<?= (int)$mapel['id'] ?>" <?= $mapel_id===(int)$mapel['id']?'selected':'' ?>><?= sanitize($mapel['nama_mapel']) ?></option><?php endforeach ?></select></div>
            <div class="col-6 col-md-3 col-xl"><label class="form-label">Semester</label><select name="semester" class="form-select"><option value="">Semua</option><option value="Ganjil" <?= $semester==='Ganjil'?'selected':'' ?>>Ganjil</option><option value="Genap" <?= $semester==='Genap'?'selected':'' ?>>Genap</option></select></div>
            <div class="col-6 col-md-3 col-xl"><label class="form-label">Tahun</label><select name="tahun_ajaran" class="form-select"><option value="">Semua</option><?php foreach($tahun_list as $tahun): ?><option value="<?= sanitize($tahun) ?>" <?= $tahun_ajaran===$tahun?'selected':'' ?>><?= sanitize($tahun) ?></option><?php endforeach ?></select></div>
            <div class="col-6 col-md-3 col-xl"><label class="form-label">Pertemuan</label><select name="pertemuan" class="form-select"><option value="0">Semua</option><?php for($i=1;$i<=20;$i++): ?><option value="<?= $i ?>" <?= $pertemuan===$i?'selected':'' ?>><?= $i ?></option><?php endfor ?></select></div>
            <div class="col-6 col-md-3 col-xl"><label class="form-label">Akses</label><select name="status_akses" class="form-select"><option value="">Semua</option><option value="Dibuka" <?= $status_akses==='Dibuka'?'selected':'' ?>>Dibuka</option><option value="Dikunci" <?= $status_akses==='Dikunci'?'selected':'' ?>>Dikunci</option></select></div>
            <div class="col-6 col-md-3 col-xl-auto"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Terapkan</button></div>
            <?php if($filter_aktif): ?><div class="col-6 col-md-3 col-xl-auto"><a href="monitoring_materi.php" class="btn btn-light border w-100">Reset</a></div><?php endif ?>
        </form>
    </div></section>

    <section class="card monitor-card"><div class="card-body p-0">
        <div class="d-flex justify-content-between align-items-center gap-2 px-3 px-md-4 py-3 border-bottom"><div><h6 class="fw-bold mb-0">Daftar Materi</h6><small class="text-muted">Menampilkan <?= $total_materi?$offset+1:0 ?>–<?= min($offset+$per_halaman,$total_materi) ?> dari <?= $total_materi ?> materi</small></div></div>
        <div class="table-responsive d-none d-md-block"><table class="table table-hover material-table mb-0"><thead class="table-light"><tr><th class="ps-4">Materi</th><th>Pengajaran</th><th>Pertemuan</th><th>Akses</th><th>Media</th><th>Tanggal</th></tr></thead><tbody>
            <?php foreach($materi_list as $materi): $video_admin=url_video_materi_admin($materi['video_url']); ?><tr><td class="ps-4"><div class="material-title"><strong><?= sanitize($materi['judul']) ?></strong><?php if($materi['deskripsi']): ?><details><summary class="small text-primary mt-1">Lihat deskripsi</summary><div class="small text-muted mt-1"><?= nl2br(sanitize($materi['deskripsi'])) ?></div></details><?php endif ?></div></td><td><strong><?= sanitize($materi['nama_mapel']) ?></strong><br><small class="text-muted"><?= sanitize($materi['nama_kelas']) ?> · <?= sanitize($materi['nama_guru']) ?><br><?= sanitize($materi['semester']) ?> · <?= sanitize($materi['tahun_ajaran']) ?></small></td><td><span class="badge bg-primary">P<?= (int)$materi['pertemuan_ke'] ?></span></td><td><span class="badge <?= $materi['status_akses']==='Dibuka'?'bg-success':'bg-secondary' ?>"><i class="fa-solid <?= $materi['status_akses']==='Dibuka'?'fa-lock-open':'fa-lock' ?> me-1"></i><?= sanitize($materi['status_akses']) ?></span></td><td><div class="d-flex flex-wrap gap-1"><?php if($materi['file_path']): ?><a href="materi_file.php?id=<?= (int)$materi['id'] ?>&amp;mode=preview" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i></a><a href="materi_file.php?id=<?= (int)$materi['id'] ?>&amp;mode=download" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-download"></i></a><?php endif ?><?php if($video_admin): ?><a href="<?= sanitize($video_admin) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger"><i class="fa-brands fa-youtube"></i></a><?php endif ?><?php if(!$materi['file_path']&&!$video_admin): ?><span class="text-muted">—</span><?php endif ?></div></td><td><small><?= date('d/m/Y',strtotime($materi['created_at'])) ?><br><span class="text-muted"><?= date('H:i',strtotime($materi['created_at'])) ?></span></small></td></tr><?php endforeach ?>
            <?php if(!$materi_list): ?><tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-folder-open fa-2x mb-2 opacity-50"></i><p class="mb-0">Tidak ada materi sesuai filter.</p></td></tr><?php endif ?>
        </tbody></table></div>
        <div class="d-md-none p-3"><?php foreach($materi_list as $materi): $video_admin=url_video_materi_admin($materi['video_url']); ?><article class="material-mobile"><div class="d-flex justify-content-between gap-2 mb-2"><span class="badge bg-primary">Pertemuan <?= (int)$materi['pertemuan_ke'] ?></span><span class="badge <?= $materi['status_akses']==='Dibuka'?'bg-success':'bg-secondary' ?>"><?= sanitize($materi['status_akses']) ?></span></div><h6 class="fw-bold mb-1"><?= sanitize($materi['judul']) ?></h6><p class="small text-muted mb-3"><?= sanitize($materi['nama_mapel']) ?> · <?= sanitize($materi['nama_kelas']) ?><br><?= sanitize($materi['nama_guru']) ?> · <?= sanitize($materi['semester']) ?> <?= sanitize($materi['tahun_ajaran']) ?></p><?php if($materi['deskripsi']): ?><details class="small mb-3"><summary class="text-primary">Lihat deskripsi</summary><div class="text-muted mt-2"><?= nl2br(sanitize($materi['deskripsi'])) ?></div></details><?php endif ?><div class="d-flex gap-2"><?php if($materi['file_path']): ?><a href="materi_file.php?id=<?= (int)$materi['id'] ?>&amp;mode=preview" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary flex-fill"><i class="fa-solid fa-eye me-1"></i>Preview</a><a href="materi_file.php?id=<?= (int)$materi['id'] ?>&amp;mode=download" class="btn btn-sm btn-outline-success flex-fill"><i class="fa-solid fa-download me-1"></i>Unduh</a><?php endif ?><?php if($video_admin): ?><a href="<?= sanitize($video_admin) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger flex-fill"><i class="fa-brands fa-youtube me-1"></i>Video</a><?php endif ?></div></article><?php endforeach ?><?php if(!$materi_list): ?><div class="text-center text-muted py-4">Tidak ada materi sesuai filter.</div><?php endif ?></div>
        <?php if($total_halaman>1): ?><nav class="p-3 border-top" aria-label="Halaman materi"><ul class="pagination pagination-sm justify-content-center mb-0"><li class="page-item <?= $halaman<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>max(1,$halaman-1)])) ?>">&laquo;</a></li><?php $mulai=max(1,$halaman-2);$akhir=min($total_halaman,$halaman+2);for($i=$mulai;$i<=$akhir;$i++): ?><li class="page-item <?= $i===$halaman?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>$i])) ?>"><?= $i ?></a></li><?php endfor ?><li class="page-item <?= $halaman>=$total_halaman?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>min($total_halaman,$halaman+1)])) ?>">&raquo;</a></li></ul></nav><?php endif ?>
    </div></section>
</main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
