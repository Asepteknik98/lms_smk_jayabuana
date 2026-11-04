<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../config/auth.php';
check_access([3]);
require_once __DIR__.'/../includes/kredit_poin.php';
$db = Database::getInstance();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Halaman ini hanya untuk melihat poin.');
}
$q = $db->prepare('SELECT s.id,s.nama_lengkap,k.nama_kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.user_id=?');
$q->execute([$_SESSION['user_id']]);
$siswa = $q->fetch();
if (!$siswa) { http_response_code(404); exit('Data siswa tidak ditemukan.'); }
$ready = kps_ready($db);
$summary = $ready ? kps_summary($db, (int)$siswa['id']) : null;
$riwayat = [];
$page = max(1, (int)($_GET['page'] ?? 1));
$pages = $summary ? max(1, (int)ceil($summary['jumlah'] / 20)) : 1;
$page = min($page, $pages);
if ($ready) {
    $q = $db->prepare('SELECT p.*,COALESCE(g.nama_lengkap,u.username) pencatat FROM kredit_poin_siswa p
        JOIN users u ON u.id=p.pencatat_id LEFT JOIN guru g ON g.user_id=u.id
        WHERE p.siswa_id=? ORDER BY p.tanggal DESC,p.id DESC LIMIT 20 OFFSET '.(($page-1)*20));
    $q->execute([(int)$siswa['id']]);
    $riwayat = $q->fetchAll();
}
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/kredit-poin.css">
<link rel="stylesheet" href="../assets/css/kredit-poin-history.css?v=<?=filemtime(__DIR__.'/../assets/css/kredit-poin-history.css')?>">
<div id="page-content-wrapper" class="kps-page kps-student-page">
    <nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h1 class="h5 fw-bold mb-1">Kredit Poin Siswa</h1><small class="text-muted"><?= sanitize($siswa['nama_lengkap']) ?> &middot; <?= sanitize($siswa['nama_kelas'] ?? 'Kelas belum ditentukan') ?></small></div><a href="index.php" class="btn btn-sm btn-outline-primary">Dashboard</a></nav>
    <main class="kps-main p-3 p-md-4">
    <?php if (!$ready): ?>
        <div class="alert alert-info">Kredit Poin Siswa belum diaktifkan. Silakan hubungi admin sekolah.</div>
    <?php else: ?>
        <?php require __DIR__.'/../includes/kredit_poin_summary.php'; ?>
        <section class="card kps-card kps-student-history"><div class="card-body p-3 p-md-4"><div class="kps-history-heading"><span class="kps-history-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Riwayat Pelanggaran</h2><p>Catatan pelanggaran dan pengurangan poinmu.</p></div><span class="kps-history-count"><?= $summary['jumlah'] ?> catatan</span></div>
        <?php if (!$riwayat): ?><div class="text-center text-muted py-5"><i class="fa-solid fa-shield-halved fa-2x mb-3" aria-hidden="true"></i><p class="mb-0">Belum ada pelanggaran yang tercatat.</p></div><?php endif ?>
        <?php foreach($riwayat as $item):$poin_pelanggaran=abs((int)$item['perubahan']);$kategori=$poin_pelanggaran<=10?'ringan':($poin_pelanggaran<=25?'sedang':'berat');$nama_kategori=ucfirst($kategori); ?>
        <article class="kps-entry"><span class="kps-delta is-minus"><?=number_format((int)$item['perubahan'],0,',','.')?><small>poin dikurangi</small></span><div class="kps-entry-copy">
        <span class="kps-category kps-severity-<?=$kategori?>"><i class="fa-solid <?=$kategori==='berat'?'fa-circle-exclamation':($kategori==='sedang'?'fa-triangle-exclamation':'fa-circle-info')?>" aria-hidden="true"></i><?=$nama_kategori?></span>
        <h3><?=nl2br(sanitize($item['alasan']))?></h3>
        <div class="kps-entry-meta"><span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?=date('d M Y',strtotime($item['tanggal']))?></span><span><i class="fa-solid fa-user-pen" aria-hidden="true"></i><?=sanitize($item['pencatat'])?></span></div>
        <?php if(($item['catatan']??null)||($item['penanganan']??null)):?><details class="kps-student-detail"><summary><i class="fa-regular fa-comment-dots" aria-hidden="true"></i>Catatan &amp; penanganan<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div><?php if($item['catatan']??null):?><p><strong>Catatan</strong><?=nl2br(sanitize($item['catatan']))?></p><?php endif ?><?php if($item['penanganan']??null):?><p><strong>Penanganan</strong><?=sanitize($item['penanganan'])?></p><?php endif ?></div></details><?php endif ?>
        </div></article><?php endforeach ?>
        <?php if ($pages > 1): ?><nav class="d-flex align-items-center justify-content-between mt-3" aria-label="Halaman riwayat"><span class="small text-muted">Halaman <?= $page ?> dari <?= $pages ?></span><div class="d-flex gap-2"><?php if ($page > 1): ?><a class="btn btn-sm btn-outline-primary" href="?page=<?= $page-1 ?>">Sebelumnya</a><?php endif ?><?php if ($page < $pages): ?><a class="btn btn-sm btn-outline-primary" href="?page=<?= $page+1 ?>">Berikutnya</a><?php endif ?></div></nav><?php endif ?>
        </div></section>
    <?php endif ?>
    </main>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
