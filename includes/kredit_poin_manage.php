<?php
require_once __DIR__.'/../config/auth.php';
check_access([1,2]);
require_once __DIR__.'/kredit_poin.php';
$db = Database::getInstance();
kredit_access_require($db,(int)$_SESSION['user_id'],'poin');
$ready = kps_ready($db) && kps_catalog_ready($db);
$view=is_string($_GET['halaman']??null)&&$_GET['halaman']==='riwayat'?'riwayat':'catat';
$search=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
$error = '';
$success = $_SESSION['kps_success'] ?? '';
unset($_SESSION['kps_success']);
$input = ['kelas_id'=>'','siswa_id'=>'','pelanggaran_id'=>'','poin'=>'','tanggal'=>date('Y-m-d'),'catatan'=>''];
$request_key = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($input as $field => $default) {
        if (is_string($_POST[$field] ?? null)) $input[$field] = $_POST[$field];
    }
    try {
        if (!$ready) throw new InvalidArgumentException('Kredit Poin Siswa belum diaktifkan.');
        if (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf($_POST['csrf_token'])) {
            throw new InvalidArgumentException('Sesi keamanan berakhir. Muat ulang halaman sebelum menyimpan.');
        }
        $db->beginTransaction();
        $inserted = kps_record($db, (int)$_SESSION['user_id'], $_POST);
        if ($inserted) catat_log($_SESSION['user_id'], 'Mencatat pelanggaran siswa #'.(int)$_POST['siswa_id'].' (pengurangan '.(int)$_POST['poin'].' kredit poin)');
        $db->commit();
        $_SESSION['kps_success'] = $inserted ? 'Pelanggaran dan pengurangan poin berhasil dicatat.' : 'Pelanggaran ini sudah dicatat sebelumnya.';
        redirect('kredit_poin.php?halaman=riwayat&kelas_id='.(int)$_POST['kelas_id'].'&siswa_id='.(int)$_POST['siswa_id'].'#kpsHistory');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Kredit Poin Siswa: '.$e->getMessage());
        $error = $e instanceof InvalidArgumentException || $e instanceof DomainException ? $e->getMessage() : 'Pelanggaran gagal disimpan. Silakan coba lagi.';
    }
}
$kelas_list = $db->query('SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();
$siswa_list = $db->query('SELECT s.id,s.kelas_id,s.nama_lengkap,s.nis,k.nama_kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id ORDER BY k.nama_kelas,s.nama_lengkap')->fetchAll();
$pelanggaran_list=$ready&&$view==='catat'?$db->query('SELECT * FROM kredit_master_pelanggaran WHERE aktif=1 ORDER BY kategori,urutan,id')->fetchAll():[];
$filter_kelas = filter_var($_GET['kelas_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 0;
if(!in_array($filter_kelas,array_map('intval',array_column($kelas_list,'id')),true))$filter_kelas=0;
$filter = filter_var($_GET['siswa_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
$selected = null;
foreach ($siswa_list as $row) if ($filter_kelas && (int)$row['id'] === $filter && (int)$row['kelas_id'] === $filter_kelas) $selected = $row;
if (!$selected) $filter = 0;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $input['kelas_id']=$filter_kelas?(string)$filter_kelas:'';
    if($selected)$input['siswa_id']=(string)$filter;
}
$history = [];
$summary = $ready && $selected ? kps_summary($db, $filter) : null;
$page = filter_var($_GET['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
$count = 0;
$history_page_size = 10;
if(($_GET['export']??'')==='excel'){
    if(!$ready){http_response_code(400);exit('Kredit Poin Siswa belum diaktifkan.');}
    require_once __DIR__.'/kredit_poin_export.php';
    $file=ew_workbook(kps_export_sheets($db,(int)$_SESSION['user_id'],$filter_kelas,$filter,$search));
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="rekap-kredit-poin-'.date('Ymd-His').'.xlsx"');
    header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');echo $file;exit;
}
if ($ready&&$view==='riwayat') {
    $history_result=kps_history_page($db,(int)$_SESSION['user_id'],$filter_kelas,$filter,$page,$search);
    $count=$history_result['total'];$page=$history_result['page'];$pages=$history_result['pages'];$history=$history_result['rows'];
}
require_once __DIR__.'/header.php';
require_once __DIR__.'/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/kredit-poin.css">
<link rel="stylesheet" href="../assets/css/kredit-poin-manage.css?v=<?=filemtime(__DIR__.'/../assets/css/kredit-poin-manage.css')?>">
<link rel="stylesheet" href="../assets/css/kredit-poin-design.css?v=<?=filemtime(__DIR__.'/../assets/css/kredit-poin-design.css')?>">
<div id="page-content-wrapper" class="kps-page kps-manage">
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div class="kps-page-title"><span class="kps-title-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><div><h1 class="h5 fw-bold mb-1">Kredit Poin Siswa</h1><small class="text-muted">Kelola pelanggaran dan poin kedisiplinan siswa</small></div></div></nav>
<main class="kps-main p-3 p-md-4">
<nav class="kps-page-tabs" aria-label="Halaman Kredit Poin Siswa"><a href="kredit_poin.php?halaman=catat" class="kps-page-tab <?=$view==='catat'?'is-active':''?>" <?=$view==='catat'?'aria-current="page"':''?>><i class="fa-solid fa-file-pen" aria-hidden="true"></i>Catat Pelanggaran</a><a href="kredit_poin.php?halaman=riwayat" class="kps-page-tab <?=$view==='riwayat'?'is-active':''?>" <?=$view==='riwayat'?'aria-current="page"':''?>><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Riwayat Pelanggaran</a></nav>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if (!$ready): ?>
    <div class="alert alert-info">Kredit Poin Siswa atau Master Pelanggaran belum diaktifkan. Admin perlu menjalankan migrasi kredit poin dan <code>database/2026_11_03_master_pelanggaran.sql</code>.</div>
<?php else: ?>
<?php if($view==='catat'): ?>
<section class="card kps-card mb-3"><div class="card-body p-3 p-md-4">
    <div class="kps-section-head"><span class="kps-section-icon is-amber"><i class="fa-solid fa-file-pen" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Catat Pelanggaran Siswa</h2><p>Pilih kelas, lalu siswa untuk mencatat pelanggaran.</p></div></div>
    <div class="kps-rule-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Saldo awal <strong><?= KPS_SALDO_AWAL ?> poin</strong>. Pelanggaran mengurangi saldo hingga minimal <strong>0 poin</strong>.</span></div>
    <form method="post" class="row g-3" data-kps-student-group>
        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="request_key" value="<?= $request_key ?>">
        <input type="hidden" name="jenis" value="kurang">
        <fieldset class="kps-form-group"><legend><span class="kps-group-icon"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></span><span>Siswa &amp; pelanggaran<small>Pilih kelas, siswa, lalu jenis pelanggaran.</small></span></legend>
        <div class="col-md-4"><label for="kpsKelas" class="form-label"><i class="fa-solid fa-school" aria-hidden="true"></i>Kelas</label><select id="kpsKelas" name="kelas_id" class="form-select" data-kps-class required><option value="">Pilih kelas terlebih dahulu</option><?php foreach($kelas_list as $kelas):?><option value="<?=(int)$kelas['id']?>" <?=(string)$kelas['id']===$input['kelas_id']?'selected':''?>><?=sanitize($kelas['nama_kelas'])?></option><?php endforeach ?></select></div>
        <div class="col-md-8"><label for="kpsSiswa" class="form-label"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i>Siswa</label><select id="kpsSiswa" class="form-select" name="siswa_id" data-kps-student data-empty-label="Pilih siswa" aria-describedby="kpsStudentHelp" <?=$input['kelas_id']===''?'disabled':''?> required><option value="">Pilih siswa</option><?php foreach ($siswa_list as $row): if((string)$row['kelas_id']!==$input['kelas_id'])continue; ?><option value="<?= (int)$row['id'] ?>" <?= (string)$row['id'] === $input['siswa_id'] ? 'selected' : '' ?>><?= sanitize($row['nama_lengkap'].' · NIS '.($row['nis'] ?? '-')) ?></option><?php endforeach ?></select><small id="kpsStudentHelp" class="kps-field-help" data-kps-class-help role="status" aria-live="polite">Daftar siswa mengikuti kelas yang dipilih.</small></div>
        <div class="col-md-8"><label for="kpsPelanggaran" class="form-label"><i class="fa-solid fa-list-check" aria-hidden="true"></i>Jenis Pelanggaran</label><select id="kpsPelanggaran" name="pelanggaran_id" class="form-select" required aria-describedby="kpsViolationHelp"><option value="">Pilih jenis pelanggaran</option><?php foreach(KPS_KATEGORI as $kode=>$kategori):?><optgroup label="<?=sanitize($kode.'. '.$kategori)?>"><?php foreach($pelanggaran_list as $pelanggaran):if($pelanggaran['kategori']!==$kode)continue;?><option value="<?=(int)$pelanggaran['id']?>" data-poin="<?=(int)$pelanggaran['poin']?>" data-nama="<?=sanitize($pelanggaran['nama'])?>" data-penanganan="<?=sanitize($pelanggaran['penanganan']??'')?>" <?=(string)$pelanggaran['id']===$input['pelanggaran_id']?'selected':''?>><?=sanitize($pelanggaran['kode'].' · '.$pelanggaran['nama'].' ('.$pelanggaran['poin'].' poin)')?></option><?php endforeach ?></optgroup><?php endforeach ?></select><small class="kps-field-help" id="kpsViolationHelp">Daftar pelanggaran sesuai ketentuan sekolah.</small><div class="kps-master-selection" data-kps-violation-summary hidden><p data-kps-violation-name></p><p data-kps-handling></p></div></div>
        <div class="col-md-4"><label for="kpsPoin" class="form-label"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i>Poin Pelanggaran</label><input id="kpsPoin" type="number" name="poin" class="form-control" min="1" max="100000" step="1" value="<?= sanitize($input['poin']) ?>" placeholder="Otomatis dari pilihan" aria-describedby="kpsPoinHelp" required><small class="kps-field-help" id="kpsPoinHelp">Terisi otomatis dan tetap dapat diedit.</small></div>
        <div class="col-md-4"><label for="kpsTanggal" class="form-label"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Tanggal</label><input id="kpsTanggal" type="date" name="tanggal" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= sanitize($input['tanggal']) ?>" required></div>
        <div class="col-md-8"><label for="kpsCatatan" class="form-label"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Catatan Tambahan <span class="text-muted fw-normal">(opsional)</span></label><textarea id="kpsCatatan" name="catatan" class="form-control" maxlength="500" rows="2" placeholder="Tambahkan keterangan kejadian jika diperlukan"><?= sanitize($input['catatan']) ?></textarea></div>
        </fieldset>
        <div class="col-12"><div class="kps-form-footer"><span><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Setiap pelanggaran tersimpan di riwayat siswa.</span><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-2" aria-hidden="true"></i>Simpan Pelanggaran</button></div></div>
    </form>
</div></section>
<?php else: ?>
<div class="kps-export-bar"><span><i class="fa-solid fa-table" aria-hidden="true"></i>Rekap saldo siswa dan seluruh rincian sesuai filter.</span><a class="btn btn-success" href="<?=sanitize('kredit_poin.php?'.http_build_query(['halaman'=>'riwayat','kelas_id'=>$filter_kelas,'siswa_id'=>$filter,'q'=>$search,'export'=>'excel']))?>"><i class="fa-solid fa-file-excel me-2" aria-hidden="true"></i>Rekap Excel</a></div>
<section class="card kps-card mb-3"><div class="card-body p-3 p-md-4">
    <form method="get" action="kredit_poin.php#kpsHistory" class="kps-filter-form kps-history-filters" data-kps-student-group>
    <input type="hidden" name="halaman" value="riwayat">
    <div><label for="kpsSearch"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>Cari riwayat</label><input id="kpsSearch" class="form-control" type="search" name="q" maxlength="100" value="<?=sanitize($search)?>" placeholder="Nama, NIS, atau pelanggaran"></div>
    <div><label for="kpsFilterKelas" class="form-label"><i class="fa-solid fa-school" aria-hidden="true"></i>Filter Kelas</label><select id="kpsFilterKelas" class="form-select" name="kelas_id" data-kps-class><option value="">Semua kelas</option><?php foreach($kelas_list as $kelas):?><option value="<?=(int)$kelas['id']?>" <?=(int)$kelas['id']===$filter_kelas?'selected':''?>><?=sanitize($kelas['nama_kelas'])?></option><?php endforeach ?></select></div>
    <div><label for="kpsFilter" class="form-label"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i>Riwayat Siswa</label><select id="kpsFilter" class="form-select" name="siswa_id" data-kps-student data-empty-label="Semua siswa di kelas ini" <?=$filter_kelas?'':'disabled'?>><option value="">Semua siswa di kelas ini</option><?php foreach($siswa_list as $row):if((int)$row['kelas_id']!==$filter_kelas)continue;?><option value="<?=(int)$row['id']?>" <?=(int)$row['id']===$filter?'selected':''?>><?=sanitize($row['nama_lengkap'].' · NIS '.($row['nis']??'-'))?></option><?php endforeach ?></select></div><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Tampilkan</button></form>
</div></section>
<?php if ($summary): ?><h2 class="h6 fw-bold mb-3"><?= sanitize($selected['nama_lengkap']) ?></h2><?php require __DIR__.'/kredit_poin_summary.php'; ?><?php endif ?>
<section class="card kps-card" id="kpsHistory"><div class="card-body p-3 p-md-4"><div class="kps-section-head"><span class="kps-section-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1" id="kpsHistoryHeading">Riwayat Pelanggaran</h2><p>Gulir riwayat atau gunakan navigasi halaman untuk melihat catatan lainnya.</p></div><span class="kps-count"><?= $count ?> catatan</span></div>
<?php if (!$history): ?><div class="kps-manage-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><strong>Belum ada pelanggaran tercatat</strong><p>Catatan akan muncul setelah pelanggaran disimpan.</p></div><?php else: ?>
<p class="kps-grid-hint" id="kpsGridHint"><i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>Geser tabel ke samping untuk melihat seluruh kolom.</p>
<div class="table-responsive kps-history-wrap" tabindex="0" role="region" aria-labelledby="kpsHistoryHeading" aria-describedby="kpsGridHint"><table class="table kps-table"><thead><tr><th scope="col"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Tanggal</th><th scope="col"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i>Siswa</th><th scope="col"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i>Poin</th><th scope="col"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Keterangan</th><th scope="col"><i class="fa-solid fa-user-pen" aria-hidden="true"></i>Pencatat</th></tr></thead><tbody>
<?php foreach ($history as $row): ?><tr><td data-field="date" data-label="Tanggal"><?= date('d/m/Y',strtotime($row['tanggal'])) ?></td><td data-field="student" data-label="Siswa"><strong><?= sanitize($row['nama_lengkap']) ?></strong><small class="d-block text-muted"><?= sanitize($row['nama_kelas'] ?? 'Tanpa kelas') ?></small></td><td data-field="points" data-label="Pengurangan"><span class="badge is-minus"><?= number_format((int)$row['perubahan'],0,',','.') ?> poin</span><?php if($row['poin_standar']!==null&&abs((int)$row['perubahan'])!==(int)$row['poin_standar']):?><small class="d-block text-muted mt-1">Standar <?=(int)$row['poin_standar']?> poin</small><?php endif ?></td><td class="kps-reason" data-field="reason" data-label="Keterangan"><?= nl2br(sanitize($row['alasan'])) ?><?php if($row['catatan']||$row['penanganan']):?><details class="kps-history-detail"><summary><i class="fa-regular fa-comment-dots me-1" aria-hidden="true"></i>Lihat detail</summary><?php if($row['catatan']):?><small class="d-block text-muted mt-2">Catatan: <?=nl2br(sanitize($row['catatan']))?></small><?php endif ?><?php if($row['penanganan']):?><small class="d-block text-muted mt-2">Penanganan: <?=sanitize($row['penanganan'])?></small><?php endif ?></details><?php endif ?></td><td data-field="author" data-label="Dicatat oleh"><?= sanitize($row['pencatat']) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?php if($count>0):?><nav class="kps-history-pagination" aria-label="Halaman riwayat"><div><span><?=($page-1)*10+1?>–<?=min($page*10,$count)?> dari <?=$count?> catatan</span><small>Halaman <?=$page?> / <?=$pages?></small></div><div class="kps-history-page-buttons"><?php foreach([-1=>'Sebelumnya',1=>'Berikutnya'] as $step=>$label):if($page+$step<1||$page+$step>$pages)continue;?><a class="btn btn-sm btn-outline-primary" href="<?=sanitize('?'.http_build_query(['halaman'=>'riwayat','kelas_id'=>$filter_kelas,'siswa_id'=>$filter,'q'=>$search,'page'=>$page+$step]).'#kpsHistory')?>"><?=$label?></a><?php endforeach ?></div></nav><?php endif ?>
</div></section>
<?php endif;endif ?>
</main></div>
<script type="application/json" id="kpsClassStudents"><?=json_encode($siswa_list,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
<script src="../assets/js/kredit-poin-manage.js?v=<?=filemtime(__DIR__.'/../assets/js/kredit-poin-manage.js')?>" defer></script>
<?php require_once __DIR__.'/footer.php'; ?>
