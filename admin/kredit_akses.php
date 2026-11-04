<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../config/auth.php';
check_access([1]);
require_once __DIR__.'/../includes/kredit_akses.php';
$db=Database::getInstance();$ready=kredit_access_ready($db);$error='';
$success=$_SESSION['kredit_akses_success']??'';unset($_SESSION['kredit_akses_success']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new InvalidArgumentException('Sesi keamanan berakhir. Muat ulang halaman sebelum menyimpan.');
        kredit_access_save($db,(int)$_SESSION['user_id'],$_POST);
        $_SESSION['kredit_akses_success']='Akses guru berhasil disimpan. Perubahan langsung berlaku pada menu dan halaman guru.';
        redirect('kredit_akses.php');
    }catch(Throwable $e){error_log('Pengaturan akses kredit: '.$e->getMessage());$error=$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'Pengaturan belum berhasil disimpan. Silakan coba lagi.';}
}
$gurus=$db->query('SELECT g.id,g.nama_lengkap,u.username FROM guru g JOIN users u ON u.id=g.user_id WHERE u.role_id=2 AND u.is_active=1 ORDER BY g.nama_lengkap,g.id')->fetchAll();
$selected=['poin'=>[],'aktivitas'=>[]];
if($ready)foreach($db->query('SELECT guru_id,modul FROM kredit_akses_guru')->fetchAll() as $row)$selected[$row['modul']][]=(int)$row['guru_id'];
if($error&&$_SERVER['REQUEST_METHOD']==='POST')foreach(['poin','aktivitas'] as $modul)$selected[$modul]=is_array($_POST[$modul]??null)?array_map('intval',array_filter($_POST[$modul],'is_scalar')):[];
$modules=['poin'=>['nama'=>'Kredit Poin Siswa','ikon'=>'fa-shield-halved','keterangan'=>'Mencatat pelanggaran dan pengurangan poin kedisiplinan.'],'aktivitas'=>['nama'=>'Kredit Aktivitas Siswa','ikon'=>'fa-medal','keterangan'=>'Menerbitkan acara dan memverifikasi klaim acara miliknya.']];
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/kredit-akses.css?v=<?=filemtime(__DIR__.'/../assets/css/kredit-akses.css')?>">
<link rel="stylesheet" href="../assets/css/kredit-akses-layout.css?v=<?=filemtime(__DIR__.'/../assets/css/kredit-akses-layout.css')?>">
<div id="page-content-wrapper" class="credit-access-page">
<nav class="navbar top-navbar p-3 p-md-4"><div class="credit-access-heading"><span><i class="fa-solid fa-user-lock" aria-hidden="true"></i></span><div><h1 class="h5 fw-bold mb-1">Pengaturan Akses Kredit Siswa</h1><small class="text-muted">Tentukan guru pengelola untuk setiap fitur</small></div></div></nav>
<main class="credit-access-main p-3 p-md-4">
<?php if($success):?><div class="alert alert-success" role="status"><?=sanitize($success)?></div><?php endif ?>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=sanitize($error)?></div><?php endif ?>
<?php if(!$ready):?><div class="alert alert-info">Jalankan migrasi <code>database/2026_11_03_kredit_akses_guru.sql</code> terlebih dahulu.</div><?php else: ?>
<form method="post" id="creditAccessForm">
<input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>">
<div class="credit-module-tabs" role="tablist" aria-label="Pilih fitur kredit"><?php foreach($modules as $modul=>$module):?><button type="button" id="creditTab-<?=$modul?>" role="tab" aria-controls="creditPanel-<?=$modul?>" aria-selected="<?=$modul==='poin'?'true':'false'?>" tabindex="<?=$modul==='poin'?'0':'-1'?>" data-credit-tab="<?=$modul?>"><i class="fa-solid <?=$module['ikon']?>" aria-hidden="true"></i><span><?=$modul==='poin'?'Kredit Poin':'Kredit Aktivitas'?></span></button><?php endforeach ?></div>
<div class="credit-access-toolbar"><div class="credit-access-search"><label for="creditTeacherSearch" class="visually-hidden">Cari guru</label><span class="credit-search-field"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="creditTeacherSearch" type="search" class="form-control" placeholder="Cari nama guru atau username..." autocomplete="off"></span></div><span class="credit-teacher-total"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i><strong><?=count($gurus)?></strong> guru aktif</span></div>
<div class="credit-access-grid">
<?php foreach($modules as $modul=>$module):?>
<section class="credit-access-card" id="creditPanel-<?=$modul?>" data-module="<?=$modul?>" aria-labelledby="creditAccess-<?=$modul?>"><div class="credit-module-heading"><span class="credit-module-icon"><i class="fa-solid <?=$module['ikon']?>" aria-hidden="true"></i></span><div><h2 id="creditAccess-<?=$modul?>"><?=sanitize($module['nama'])?></h2><p><?=sanitize($module['keterangan'])?></p></div></div>
<div class="credit-list-toolbar"><span><i class="fa-solid fa-users" aria-hidden="true"></i>Daftar guru</span><div class="credit-module-count" role="status" aria-live="polite"><i class="fa-solid fa-user-check" aria-hidden="true"></i><span data-selected-count="<?=$modul?>"><?=count(array_intersect($selected[$modul],array_column($gurus,'id')))?></span> dipilih</div></div>
<div class="credit-teacher-list" tabindex="0" role="group" aria-labelledby="creditAccess-<?=$modul?>">
<?php foreach($gurus as $guru):?><label class="credit-teacher-row" data-teacher-search="<?=sanitize(mb_strtolower($guru['nama_lengkap'].' '.$guru['username']))?>"><input type="checkbox" name="<?=$modul?>[]" value="<?=(int)$guru['id']?>" <?=in_array((int)$guru['id'],$selected[$modul],true)?'checked':''?>><span class="credit-teacher-icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span><span><strong><?=sanitize($guru['nama_lengkap'])?></strong><small><?=sanitize($guru['username'])?></small></span></label><?php endforeach ?>
<?php if(!$gurus):?><p class="credit-no-teachers">Belum ada guru dengan akun aktif.</p><?php endif ?>
<p class="credit-no-teachers" data-search-empty hidden>Guru tidak ditemukan.</p>
</div></section>
<?php endforeach ?>
</div>
<div class="credit-access-footer"><span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Hapus centang untuk mencabut akses guru.</span><button class="btn btn-primary" type="submit"><i class="fa-regular fa-floppy-disk me-2" aria-hidden="true"></i>Simpan Akses Guru</button></div>
</form>
<?php endif ?>
</main></div>
<script src="../assets/js/kredit-akses.js?v=<?=filemtime(__DIR__.'/../assets/js/kredit-akses.js')?>" defer></script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
