<?php
require_once __DIR__.'/../config/session.php';require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../config/helper.php';require_once __DIR__.'/../config/auth.php';check_access([1]);
require_once __DIR__.'/../includes/kredit_pelanggaran_master.php';
$db=Database::getInstance();$ready=kps_catalog_ready($db);$error='';$success=$_SESSION['kps_master_success']??'';unset($_SESSION['kps_master_success']);
$delete_request=$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='hapus';
$edit_id=filter_var($_GET['edit']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]])?:0;
$form=['id'=>'0','kategori'=>'A','nama'=>'','poin'=>'','penanganan'=>'','aktif'=>'1'];
if($ready&&$edit_id){$q=$db->prepare('SELECT * FROM kredit_master_pelanggaran WHERE id=?');$q->execute([$edit_id]);$row=$q->fetch();if(!$row){http_response_code(404);exit('Pelanggaran tidak ditemukan.');}foreach($form as $key=>$value)$form[$key]=(string)($row[$key]??'');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    foreach($form as $key=>$value)$form[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
    try{
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new InvalidArgumentException('Sesi keamanan berakhir. Muat ulang halaman sebelum menyimpan.');
        if(!$ready)throw new InvalidArgumentException('Master Pelanggaran belum diaktifkan.');
        if($delete_request){
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$id)throw new InvalidArgumentException('Pelanggaran tidak valid.');
            $result=kps_catalog_delete($db,(int)$_SESSION['user_id'],$id);
            $_SESSION['kps_master_success']=$result==='deleted'?'Pelanggaran berhasil dihapus.':'Pelanggaran sudah dipakai dalam riwayat dan dinonaktifkan. Catatan siswa tetap tersimpan.';
            $context=[];foreach(['q','kategori','page'] as $key)if(is_string($_GET[$key]??null))$context[$key]=mb_substr($_GET[$key],0,100);
            redirect('master_pelanggaran.php'.($context?'?'.http_build_query($context):''));
        }
        kps_catalog_save($db,(int)$_SESSION['user_id'],$_POST);$_SESSION['kps_master_success']='Master Pelanggaran berhasil disimpan. Riwayat yang sudah ada tetap dipertahankan.';redirect('master_pelanggaran.php');
    }catch(Throwable $e){error_log('Master Pelanggaran: '.$e->getMessage());$error=$e instanceof InvalidArgumentException||$e instanceof DomainException?$e->getMessage():'Data belum berhasil disimpan.';}
}
$search=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
$category=is_string($_GET['kategori']??null)&&isset(KPS_KATEGORI[$_GET['kategori']])?$_GET['kategori']:'';
$page=max(1,(int)($_GET['page']??1));$rows=[];$count=0;$pages=1;
if($ready){
    $conditions=[];$params=[];
    if($search!==''){$conditions[]="nama LIKE ? ESCAPE '!'";$params[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';}
    if($category!==''){$conditions[]='kategori=?';$params[]=$category;}
    $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
    $q=$db->prepare('SELECT COUNT(*) FROM kredit_master_pelanggaran'.$where);$q->execute($params);$count=(int)$q->fetchColumn();$pages=max(1,(int)ceil($count/10));$page=min($page,$pages);
    $q=$db->prepare('SELECT * FROM kredit_master_pelanggaran'.$where.' ORDER BY kategori,urutan,id LIMIT 10 OFFSET '.(($page-1)*10));$q->execute($params);$rows=$q->fetchAll();
}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/kredit-poin.css"><link rel="stylesheet" href="../assets/css/kredit-poin-manage.css?v=<?=filemtime(__DIR__.'/../assets/css/kredit-poin-manage.css')?>"><link rel="stylesheet" href="../assets/css/master-pelanggaran.css?v=<?=filemtime(__DIR__.'/../assets/css/master-pelanggaran.css')?>">
<div id="page-content-wrapper" class="kps-page kps-manage">
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div class="kps-page-title"><span class="kps-title-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><h1 class="h5 fw-bold mb-1">Master Pelanggaran</h1><small class="text-muted">Jenis pelanggaran, poin standar, dan penanganan</small></div></div></nav>
<main class="kps-main p-3 p-md-4">
<?php if($success):?><div class="alert alert-success" role="status"><?=sanitize($success)?></div><?php endif ?>
<?php if($error&&$delete_request):?><div class="alert alert-danger" role="alert"><?=sanitize($error)?></div><?php endif ?>
<?php if(!$ready):?><div class="alert alert-info">Jalankan migrasi <code>database/2026_11_03_master_pelanggaran.sql</code>.</div><?php else: ?>
<div class="kps-rule-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Poin standar menjadi isian awal dan boleh disesuaikan saat mencatat pelanggaran. Penanganan bersifat informasi; tidak menonaktifkan akun siswa.</span></div>
<div class="kps-master-toolbar"><div><strong>Kelola aturan pelanggaran</strong><p>Daftar terpusat untuk pencatatan poin siswa.</p></div><button type="button" class="btn btn-primary" data-master-add aria-haspopup="dialog" aria-controls="kpsMasterDialog"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Tambah Pelanggaran</button></div>
<dialog id="kpsMasterDialog" class="kps-master-dialog kps-manage" aria-labelledby="masterDialogTitle" aria-describedby="masterDialogDescription" data-auto-open="<?=(!$delete_request&&($edit_id||$error))?'true':'false'?>">
<div class="master-dialog-heading"><span class="master-dialog-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><span class="master-dialog-eyebrow">MASTER KREDIT POIN</span><h2 id="masterDialogTitle"><?=(int)$form['id']>0?'Ubah':'Tambah'?> Pelanggaran</h2><p id="masterDialogDescription">Siapkan jenis pelanggaran, poin standar, dan penanganan.</p></div><button type="button" class="master-dialog-close" data-master-close aria-label="Tutup formulir pelanggaran" autofocus><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
<div class="master-dialog-body">
<?php if($error):?><div class="alert alert-danger master-modal-error" role="alert"><?=sanitize($error)?></div><?php endif ?>
<form method="post" class="row g-3" id="kpsMasterForm"><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="id" value="<?=sanitize($form['id'])?>">
<div class="col-md-8"><label for="masterKategori"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Kategori</label><select id="masterKategori" class="form-select" name="kategori" required><?php foreach(KPS_KATEGORI as $key=>$name):?><option value="<?=$key?>" <?=$key===$form['kategori']?'selected':''?>><?=sanitize($key.'. '.$name)?></option><?php endforeach ?></select></div>
<div class="col-md-4"><label for="masterPoin"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i>Poin Standar</label><input id="masterPoin" type="number" name="poin" class="form-control" min="1" max="100000" value="<?=sanitize($form['poin'])?>" required></div>
<div class="col-12"><label for="masterNama"><i class="fa-solid fa-list-check" aria-hidden="true"></i>Jenis Pelanggaran</label><textarea id="masterNama" class="form-control" name="nama" maxlength="500" rows="2" required><?=sanitize($form['nama'])?></textarea></div>
<div class="col-12"><label for="masterPenanganan"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>Penanganan <span class="text-muted">(opsional)</span></label><textarea id="masterPenanganan" class="form-control" name="penanganan" rows="2" maxlength="500"><?=sanitize($form['penanganan'])?></textarea></div>
<div class="col-12"><label class="kps-master-active"><input type="checkbox" name="aktif" value="1" <?=$form['aktif']==='1'?'checked':''?>>Tersedia pada dropdown pencatatan</label><small class="kps-field-help">Nonaktifkan pilihan yang tidak lagi digunakan. Riwayat lama tetap tersimpan.</small></div>
<div class="col-12"><div class="master-dialog-footer"><button type="button" class="btn btn-outline-secondary" data-master-close>Batal</button><button class="btn btn-primary" type="submit"><i class="fa-regular fa-floppy-disk me-2" aria-hidden="true"></i>Simpan Pelanggaran</button></div></div>
</form></div></dialog>
<dialog id="kpsMasterDeleteDialog" class="kps-master-dialog master-delete-dialog kps-manage" aria-labelledby="masterDeleteTitle" aria-describedby="masterDeleteDescription">
<div class="master-dialog-heading"><span class="master-dialog-icon"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></span><div><h2 id="masterDeleteTitle">Hapus Pelanggaran?</h2><p>Pastikan pilihan yang akan dihapus sudah benar.</p></div><button type="button" class="master-dialog-close" data-master-delete-close aria-label="Tutup konfirmasi hapus"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
<div class="master-dialog-body"><p class="master-delete-name" data-master-delete-name></p><p id="masterDeleteDescription">Pelanggaran yang belum dipakai akan dihapus. Jika sudah dipakai, pelanggaran hanya dinonaktifkan agar riwayat siswa tetap tersimpan.</p>
<form method="post" id="kpsMasterDeleteForm"><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="hapus"><input type="hidden" name="id" value=""><div class="master-dialog-footer"><button type="button" class="btn btn-outline-secondary" data-master-delete-close autofocus>Batal</button><button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can me-2" aria-hidden="true"></i>Ya, Hapus</button></div></form></div>
</dialog>
<section class="card kps-card"><div class="card-body p-3 p-md-4"><div class="kps-section-head"><span class="kps-section-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Daftar Pelanggaran</h2><p>Hanya admin yang dapat mengubah daftar ini.</p></div><span class="kps-count"><?=$count?> jenis</span></div>
<form method="get" class="kps-filter-form mb-3"><div><label for="masterCari"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>Cari pelanggaran</label><input id="masterCari" name="q" type="search" class="form-control" value="<?=sanitize($search)?>" maxlength="100" placeholder="Nama pelanggaran"></div><div><label for="masterFilter"><i class="fa-solid fa-filter" aria-hidden="true"></i>Kategori</label><select id="masterFilter" name="kategori" class="form-select"><option value="">Semua kategori</option><?php foreach(KPS_KATEGORI as $key=>$name):?><option value="<?=$key?>" <?=$key===$category?'selected':''?>><?=sanitize($key.'. '.$name)?></option><?php endforeach ?></select></div><button class="btn btn-outline-primary" type="submit">Tampilkan</button></form>
<p class="kps-master-grid-hint" id="masterGridHint"><i class="fa-solid fa-arrows-left-right me-1" aria-hidden="true"></i>Geser tabel ke samping untuk melihat seluruh kolom.</p>
<div class="kps-master-list" tabindex="0" role="region" aria-label="Daftar master pelanggaran" aria-describedby="masterGridHint">
<table class="table kps-master-table"><caption class="visually-hidden">Jenis pelanggaran, kategori, poin standar, penanganan, dan status</caption><thead><tr><th scope="col"><i class="fa-solid fa-hashtag" aria-hidden="true"></i>Kode</th><th scope="col"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Kategori</th><th scope="col"><i class="fa-solid fa-list-check" aria-hidden="true"></i>Jenis Pelanggaran</th><th scope="col"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i>Poin</th><th scope="col"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>Penanganan</th><th scope="col"><i class="fa-solid fa-toggle-on" aria-hidden="true"></i>Status</th><th scope="col">Aksi</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td class="master-code"><?=sanitize($row['kode'])?></td><td><?=sanitize($row['kategori'].'. '.KPS_KATEGORI[$row['kategori']])?></td><td class="master-name"><?=sanitize($row['nama'])?></td><td class="master-points"><span class="badge is-minus"><?=(int)$row['poin']?></span></td><td class="master-handling"><?=$row['penanganan']?sanitize($row['penanganan']):'—'?></td><td><span class="master-status <?=$row['aktif']?'is-active':'is-inactive'?>"><i class="fa-solid <?=$row['aktif']?'fa-circle-check':'fa-circle-minus'?>" aria-hidden="true"></i><?=$row['aktif']?'Aktif':'Nonaktif'?></span></td><td class="master-action"><div class="master-actions"><a class="btn btn-outline-primary" href="?<?=sanitize(http_build_query(['q'=>$search,'kategori'=>$category,'page'=>$page,'edit'=>(int)$row['id']]))?>" aria-label="Ubah <?=sanitize($row['nama'])?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></a><button class="btn btn-outline-danger" type="button" data-master-delete data-master-id="<?=(int)$row['id']?>" data-master-name="<?=sanitize($row['nama'])?>" aria-label="Hapus <?=sanitize($row['nama'])?>" aria-haspopup="dialog" aria-controls="kpsMasterDeleteDialog"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></td></tr><?php endforeach ?>
<?php if(!$rows):?><tr><td colspan="7" class="master-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i>Tidak ada pelanggaran yang sesuai.</td></tr><?php endif ?>
</tbody></table></div>
<?php if($count>0):?><nav class="kps-history-pagination" aria-label="Halaman master pelanggaran"><div><span><?=($page-1)*10+1?>–<?=min($page*10,$count)?> dari <?=$count?> jenis</span><small>Halaman <?=$page?> / <?=$pages?></small></div><div class="kps-history-page-buttons"><?php if($page>1):?><a class="btn btn-outline-primary" href="?<?=sanitize(http_build_query(['q'=>$search,'kategori'=>$category,'page'=>$page-1]))?>"><i class="fa-solid fa-chevron-left me-2" aria-hidden="true"></i>Sebelumnya</a><?php endif ?><?php if($page<$pages):?><a class="btn btn-outline-primary" href="?<?=sanitize(http_build_query(['q'=>$search,'kategori'=>$category,'page'=>$page+1]))?>">Berikutnya<i class="fa-solid fa-chevron-right ms-2" aria-hidden="true"></i></a><?php endif ?></div></nav><?php endif ?>
</div></section>
<?php endif ?>
</main></div><script src="../assets/js/master-pelanggaran.js?v=<?=filemtime(__DIR__.'/../assets/js/master-pelanggaran.js')?>" defer></script><?php require_once __DIR__.'/../includes/footer.php'; ?>
