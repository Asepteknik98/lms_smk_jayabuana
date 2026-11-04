<?php
require_once __DIR__.'/../config/session.php';require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../config/helper.php';require_once __DIR__.'/../config/auth.php';check_access([1]);
require_once __DIR__.'/../includes/kak_master.php';
$db=Database::getInstance();$ready=kak_master_ready($db);$error='';$success=$_SESSION['kak_master_success']??'';unset($_SESSION['kak_master_success']);
$edit=filter_var($_GET['edit']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]])?:0;
$deleting=$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='hapus';
$form=['id'=>'0','tingkat'=>'X','nama'=>'','aktif'=>'1'];
if($ready&&$edit){$q=$db->prepare('SELECT * FROM kak_master_aktivitas WHERE id=?');$q->execute([$edit]);$row=$q->fetch();if(!$row){http_response_code(404);exit('Aktivitas tidak ditemukan.');}foreach($form as $key=>$value)$form[$key]=(string)$row[$key];}
if($_SERVER['REQUEST_METHOD']==='POST'){
    foreach($form as $key=>$value)$form[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
    try{
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new InvalidArgumentException('Sesi keamanan berakhir. Muat ulang halaman.');
        if(!$ready)throw new InvalidArgumentException('Master Aktivitas belum diaktifkan.');
        if($deleting){
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$id)throw new InvalidArgumentException('Aktivitas tidak valid.');
            $result=kak_master_delete($db,(int)$_SESSION['user_id'],$id);
            $_SESSION['kak_master_success']=$result==='deleted'?'Aktivitas berhasil dihapus.':'Aktivitas sudah digunakan dan dinonaktifkan. Acara serta riwayat siswa tetap tersimpan.';
            $context=[];foreach(['q','tingkat','page'] as $key)if(is_string($_GET[$key]??null))$context[$key]=mb_substr($_GET[$key],0,100);
            redirect('master_aktivitas.php'.($context?'?'.http_build_query($context):''));
        }
        kak_master_save($db,(int)$_SESSION['user_id'],$_POST);$_SESSION['kak_master_success']='Master Aktivitas disimpan. Acara dan riwayat lama tetap tersimpan.';redirect('master_aktivitas.php');
    }catch(Throwable $e){error_log('Master Aktivitas: '.$e->getMessage());$error=$e instanceof InvalidArgumentException||$e instanceof DomainException?$e->getMessage():'Data belum berhasil disimpan.';}
}
$search=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
$grade=is_string($_GET['tingkat']??null)&&isset(KAK_TINGKAT[$_GET['tingkat']])?$_GET['tingkat']:'';
$page=max(1,(int)($_GET['page']??1));$rows=[];$count=0;$pages=1;
if($ready){
    $conditions=[];$params=[];
    if($search!==''){$conditions[]="nama LIKE ? ESCAPE '!'";$params[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';}
    if($grade!==''){$conditions[]='tingkat=?';$params[]=$grade;}
    $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
    $q=$db->prepare('SELECT COUNT(*) FROM kak_master_aktivitas'.$where);$q->execute($params);$count=(int)$q->fetchColumn();$pages=max(1,(int)ceil($count/10));$page=min($page,$pages);
    $q=$db->prepare('SELECT * FROM kak_master_aktivitas'.$where.' ORDER BY tingkat,urutan,id LIMIT 10 OFFSET '.(($page-1)*10));$q->execute($params);$rows=$q->fetchAll();
}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/kredit-poin.css"><link rel="stylesheet" href="../assets/css/kredit-poin-manage.css?v=<?=filemtime(__DIR__.'/../assets/css/kredit-poin-manage.css')?>"><link rel="stylesheet" href="../assets/css/master-pelanggaran.css?v=<?=filemtime(__DIR__.'/../assets/css/master-pelanggaran.css')?>">
<link rel="stylesheet" href="../assets/css/master-aktivitas.css?v=<?=filemtime(__DIR__.'/../assets/css/master-aktivitas.css')?>">
<div id="page-content-wrapper" class="kps-page kps-manage kak-master-page">
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div class="kps-page-title"><span class="kps-title-icon"><i class="fa-solid fa-medal" aria-hidden="true"></i></span><div><h1 class="h5 fw-bold mb-1">Master Aktivitas</h1><small class="text-muted">Daftar kegiatan per tingkat kelas</small></div></div></nav>
<main class="kps-main p-3 p-md-4">
<?php if($success):?><div class="alert alert-success" role="status"><?=sanitize($success)?></div><?php endif ?>
<?php if($error&&$deleting):?><div class="alert alert-danger" role="alert"><?=sanitize($error)?></div><?php endif ?>
<?php if(!$ready):?><div class="alert alert-info">Jalankan migrasi <code>database/2026_11_03_master_aktivitas.sql</code>.</div><?php else: ?>
<div class="kps-rule-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Aktivitas digunakan saat membuat acara. Bukti siswa diperiksa dan disetujui melalui ceklis oleh admin atau guru yang ditunjuk.</span></div>
<div class="kps-master-toolbar"><div><strong>Kelola aktivitas sekolah</strong><p>Daftar kegiatan terpisah untuk kelas 10, 11, dan 12.</p></div><button type="button" class="btn btn-primary" data-activity-add aria-haspopup="dialog" aria-controls="activityDialog"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Tambah Aktivitas</button></div>
<dialog id="activityDialog" class="kps-master-dialog kps-manage" aria-labelledby="activityDialogTitle" data-auto-open="<?=(!$deleting&&($edit||$error))?'true':'false'?>">
<div class="master-dialog-heading"><span class="master-dialog-icon"><i class="fa-solid fa-medal" aria-hidden="true"></i></span><div><span class="master-dialog-eyebrow">MASTER KREDIT AKTIVITAS</span><h2 id="activityDialogTitle"><?=(int)$form['id']?'Ubah':'Tambah'?> Aktivitas</h2><p>Atur tingkat kelas, nama kegiatan, dan ketersediaannya.</p></div><button type="button" class="master-dialog-close" data-activity-close aria-label="Tutup formulir" autofocus><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
<div class="master-dialog-body">
<?php if($error):?><div class="alert alert-danger" role="alert"><?=sanitize($error)?></div><?php endif ?>
<form method="post" class="row g-3" id="activityForm"><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="id" value="<?=sanitize($form['id'])?>">
<div class="col-12"><label for="activityGrade"><i class="fa-solid fa-school" aria-hidden="true"></i>Tingkat Kelas</label><select id="activityGrade" class="form-select" name="tingkat" required><?php foreach(KAK_TINGKAT as $key=>$label):?><option value="<?=$key?>" <?=$form['tingkat']===$key?'selected':''?>><?=$label?></option><?php endforeach ?></select></div>

<div class="col-12"><label for="activityName"><i class="fa-solid fa-medal" aria-hidden="true"></i>Nama Aktivitas</label><textarea id="activityName" name="nama" class="form-control" rows="2" maxlength="150" required><?=sanitize($form['nama'])?></textarea></div>
<div class="col-12"><label class="kps-master-active"><input type="checkbox" name="aktif" value="1" <?=$form['aktif']==='1'?'checked':''?>>Tersedia pada pilihan aktivitas</label><small class="kps-field-help">Nonaktifkan kegiatan yang tidak lagi digunakan. Acara dan riwayat lama tetap tersimpan.</small></div>
<div class="col-12"><div class="master-dialog-footer"><button type="button" class="btn btn-outline-secondary" data-activity-close>Batal</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-2" aria-hidden="true"></i>Simpan Aktivitas</button></div></div>
</form></div></dialog>
<section class="card kps-card"><div class="card-body p-3 p-md-4"><div class="kps-section-head"><span class="kps-section-icon"><i class="fa-solid fa-table-list" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Daftar Aktivitas</h2><p>Hanya admin yang dapat mengubah daftar ini.</p></div><span class="kps-count"><?=$count?> aktivitas</span></div>
<form method="get" class="kps-filter-form mb-3"><div><label for="activitySearch"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>Cari aktivitas</label><input id="activitySearch" name="q" class="form-control" type="search" maxlength="100" value="<?=sanitize($search)?>" placeholder="Nama kegiatan"></div><div><label for="activityFilter"><i class="fa-solid fa-filter" aria-hidden="true"></i>Tingkat Kelas</label><select id="activityFilter" name="tingkat" class="form-select"><option value="">Semua tingkat</option><?php foreach(KAK_TINGKAT as $key=>$label):?><option value="<?=$key?>" <?=$grade===$key?'selected':''?>><?=$label?></option><?php endforeach ?></select></div><button class="btn btn-outline-primary" type="submit">Tampilkan</button></form>
<dialog id="activityDeleteDialog" class="kps-master-dialog master-delete-dialog kps-manage" aria-labelledby="activityDeleteTitle" aria-describedby="activityDeleteDescription">
<div class="master-dialog-heading"><span class="master-dialog-icon"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></span><div><h2 id="activityDeleteTitle">Hapus Aktivitas?</h2><p>Periksa kembali kegiatan yang dipilih.</p></div><button type="button" class="master-dialog-close" data-activity-delete-close aria-label="Tutup konfirmasi hapus"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
<div class="master-dialog-body"><p class="master-delete-name" data-activity-delete-name></p><p id="activityDeleteDescription">Aktivitas yang belum digunakan akan dihapus. Jika sudah digunakan, aktivitas dinonaktifkan agar acara, klaim, dan riwayat siswa tetap tersimpan.</p><form method="post" id="activityDeleteForm"><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="hapus"><input type="hidden" name="id" value=""><div class="master-dialog-footer"><button type="button" class="btn btn-outline-secondary" data-activity-delete-close autofocus>Batal</button><button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can me-2" aria-hidden="true"></i>Ya, Hapus</button></div></form></div>
</dialog>
<p class="kps-master-grid-hint" id="activityGridHint">Geser tabel ke samping untuk melihat seluruh kolom.</p>
<div class="kps-master-list" tabindex="0" role="region" aria-label="Daftar master aktivitas" aria-describedby="activityGridHint"><table class="table kps-master-table"><thead><tr><th scope="col">Kode</th><th scope="col">Tingkat</th><th scope="col">Nama Aktivitas</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><?=sanitize($row['kode'])?></td><td><?=KAK_TINGKAT[$row['tingkat']]?></td><td class="master-name"><?=sanitize($row['nama'])?></td><td><span class="master-status <?=$row['aktif']?'is-active':'is-inactive'?>"><?=$row['aktif']?'Aktif':'Nonaktif'?></span></td><td class="master-action"><div class="master-actions"><a class="btn btn-outline-primary" href="?<?=sanitize(http_build_query(['q'=>$search,'tingkat'=>$grade,'page'=>$page,'edit'=>(int)$row['id']]))?>" aria-label="Ubah <?=sanitize($row['nama'])?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></a><button class="btn btn-outline-danger" type="button" data-activity-delete data-id="<?=(int)$row['id']?>" data-name="<?=sanitize($row['nama'])?>" aria-label="Hapus <?=sanitize($row['nama'])?>" aria-haspopup="dialog" aria-controls="activityDeleteDialog"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></td></tr><?php endforeach ?>
<?php if(!$rows):?><tr><td colspan="5" class="master-empty">Tidak ada aktivitas yang sesuai.</td></tr><?php endif ?>
</tbody></table></div>
<nav class="kps-history-pagination" aria-label="Halaman master aktivitas"><div><span><?=$count?($page-1)*10+1:0?>–<?=min($page*10,$count)?> dari <?=$count?> aktivitas</span><small>Halaman <?=$page?> / <?=$pages?></small></div><div class="kps-history-page-buttons"><?php foreach([-1=>'Sebelumnya',1=>'Berikutnya'] as $step=>$label):if($page+$step<1||$page+$step>$pages)continue;?><a class="btn btn-outline-primary" href="?<?=sanitize(http_build_query(['q'=>$search,'tingkat'=>$grade,'page'=>$page+$step]))?>"><?=$label?></a><?php endforeach ?></div></nav>
</div></section><?php endif ?>
</main></div><script src="../assets/js/master-aktivitas.js?v=<?=filemtime(__DIR__.'/../assets/js/master-aktivitas.js')?>" defer></script><?php require_once __DIR__.'/../includes/footer.php'; ?>
