<?php
require_once __DIR__.'/../config/auth.php';check_access([1,2]);
require_once __DIR__.'/kak_staff.php';
kredit_access_require(Database::getInstance(),(int)$_SESSION['user_id'],'aktivitas');
$db=Database::getInstance();$user_id=(int)$_SESSION['user_id'];$actor=kak_staff_actor($db,$user_id);$ready=kak_claim_ready($db)&&kak_master_ready($db);
$kak_views=['buat'=>['Buat Acara','fa-calendar-plus'],'verifikasi'=>['Verifikasi Klaim','fa-clipboard-check'],'acara'=>['Acara Diterbitkan','fa-calendar-check']];
$kak_view=is_string($_GET['halaman']??null)&&isset($kak_views[$_GET['halaman']])?$_GET['halaman']:'buat';
$success=$_SESSION['kak_staff_success']??'';$error=$_SESSION['kak_staff_error']??'';unset($_SESSION['kak_staff_success'],$_SESSION['kak_staff_error']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new InvalidArgumentException('Sesi keamanan berakhir. Silakan muat ulang halaman.');
        if(!$ready)throw new InvalidArgumentException('Modul klaim aktivitas belum diaktifkan.');
        $action=$_POST['action']??'';
        if($action==='buat_acara'){
            kak_staff_create($db,$user_id,$_POST);
            $_SESSION['kak_staff_success']='Acara diterbitkan. Siswa harus mengunggah bukti dan mendapatkan persetujuan sebelum aktivitas disetujui.';
        }elseif($action==='ubah_tanggal'){
            kak_staff_update_date($db,$user_id,$_POST);
            $_SESSION['kak_staff_success']='Tanggal acara berhasil diperbarui.';
        }elseif($action==='verifikasi'){
            $changed=kak_staff_review($db,$user_id,$_POST);
            $_SESSION['kak_staff_success']=$changed?'Verifikasi tersimpan. Status aktivitas siswa sudah diperbarui.':'Klaim ini sudah diverifikasi sebelumnya.';
        }else throw new InvalidArgumentException('Tindakan tidak valid.');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Pengelolaan klaim aktivitas: '.$e->getMessage());$_SESSION['kak_staff_error']=$e instanceof InvalidArgumentException||$e instanceof DomainException?$e->getMessage():'Proses gagal disimpan. Silakan coba kembali.';}
    $return_query=[];
    foreach(['claims_q','claims_status','claims_page','events_q','events_page'] as $key)if(is_string($_GET[$key]??null))$return_query[$key]=mb_substr($_GET[$key],0,100);
    $return_query['halaman']=($_POST['action']??'')==='ubah_tanggal'?'acara':(( $_POST['action']??'')==='verifikasi'?'verifikasi':$kak_view);
    redirect('kak.php?'.http_build_query($return_query).(($return_query['halaman']==='verifikasi')?'#kakReviewPanel':''));
}
if(($_GET['export']??'')==='excel'){
    if(!$ready||!in_array($kak_view,['verifikasi','acara'],true)){http_response_code(400);exit('Pilih halaman rekap klaim atau acara terlebih dahulu.');}
    require_once __DIR__.'/kak_export.php';
    $kind=$kak_view==='verifikasi'?'claims':'events';
    $sheets=kak_export_sheets($db,$user_id,$kind,['q'=>$_GET[$kind==='claims'?'claims_q':'events_q']??'','status'=>$_GET['claims_status']??'']);
    $workbook=ew_workbook($sheets);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="rekap-aktivitas-'.$kak_view.'-'.date('Ymd-His').'.xlsx"');
    header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
    echo $workbook;exit;
}
$kelas_list=$kak_view==='buat'?$db->query('SELECT id,nama_kelas,tingkat FROM kelas ORDER BY nama_kelas')->fetchAll():[];
$master_activities=$ready&&$kak_view==='buat'?$db->query('SELECT id,kode,tingkat,nama,poin FROM kak_master_aktivitas WHERE aktif=1 ORDER BY tingkat,urutan,id')->fetchAll():[];
$siswa_list=$kak_view==='buat'?$db->query('SELECT s.id,s.kelas_id,s.nama_lengkap,s.nis,k.nama_kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id ORDER BY k.nama_kelas,s.nama_lengkap')->fetchAll():[];
$events=[];$claims=[];
$claim_list=$event_list=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1,'search'=>'','status'=>''];
if($ready){
    if($kak_view==='verifikasi')$claim_list=kak_staff_list($db,$user_id,'claims',['q'=>$_GET['claims_q']??'','status'=>$_GET['claims_status']??'','page'=>$_GET['claims_page']??1]);
    if($kak_view==='acara')$event_list=kak_staff_list($db,$user_id,'events',['q'=>$_GET['events_q']??'','page'=>$_GET['events_page']??1]);
    $claims=$claim_list['rows'];$events=$event_list['rows'];
}
$panel_query=['halaman'=>$kak_view,'claims_q'=>$claim_list['search'],'claims_status'=>$claim_list['status'],'claims_page'=>$claim_list['page'],'events_q'=>$event_list['search'],'events_page'=>$event_list['page']];
$panel_url=static function(array $changes,string $anchor)use($panel_query):string{return 'kak.php?'.http_build_query(array_replace($panel_query,$changes)).'#'.$anchor;};
require_once __DIR__.'/header.php';require_once __DIR__.'/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/kak-klaim.css?v=<?=filemtime(__DIR__.'/../assets/css/kak-klaim.css')?>">
<link rel="stylesheet" href="../assets/css/kak-staff.css?v=<?=filemtime(__DIR__.'/../assets/css/kak-staff.css')?>">
<link rel="stylesheet" href="../assets/css/kak-staff-design.css?v=<?=filemtime(__DIR__.'/../assets/css/kak-staff-design.css')?>">
<div id="page-content-wrapper" class="kak-staff">
<nav class="navbar top-navbar p-3 p-md-4"><div class="kak-page-title"><span class="kak-title-icon"><i class="fa-solid fa-medal" aria-hidden="true"></i></span><div><h1 class="h5 fw-bold mb-1">Kredit Aktivitas Siswa</h1><small class="text-muted">Kelola acara sekolah dan verifikasi bukti siswa</small></div></div></nav>
<main class="kak-staff-main p-3 p-md-4">
<nav class="kak-page-tabs" aria-label="Halaman Kredit Aktivitas Siswa"><?php foreach($kak_views as $key=>[$label,$icon]):?><a href="kak.php?halaman=<?=$key?>" class="kak-page-tab <?=$kak_view===$key?'is-active':''?>" <?=$kak_view===$key?'aria-current="page"':''?>><i class="fa-solid <?=$icon?>" aria-hidden="true"></i><span><?=$label?></span></a><?php endforeach ?></nav>
<?php if($success):?><div class="alert alert-success" role="status"><?=sanitize($success)?></div><?php endif ?>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=sanitize($error)?></div><?php endif ?>
<?php if(!$ready):?><div class="alert alert-info">Jalankan migrasi klaim aktivitas dan <code>2026_11_03_master_aktivitas.sql</code> terlebih dahulu.</div><?php else: ?>
<?php if($kak_view==='buat'): ?>
<section class="card kak-staff-card mb-3"><div class="card-body p-3 p-md-4"><div class="kak-section-head"><span class="kak-section-icon"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Buat Acara untuk Diklaim</h2><p>Tentukan kegiatan dan bukti yang diperlukan. Siswa dapat mengirim bukti tanpa batas waktu.</p></div></div>
<div class="kak-composer-grid"><form method="post" class="row g-3" data-kak-staff-form>
<input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="buat_acara">
<fieldset class="kak-form-group"><legend><span class="kak-group-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span>Peserta &amp; jadwal<small>01 · Tentukan sasaran kegiatan</small></span></legend>
<div class="col-md-8"><label for="acaraKelas"><i class="fa-solid fa-school" aria-hidden="true"></i>Target Kelas</label><select id="acaraKelas" name="kelas_id" class="form-select" aria-controls="acaraSiswaTarget acaraNama" aria-describedby="acaraTargetHelp" required><option value="">Pilih kelas atau angkatan</option><optgroup label="Satu Angkatan"><option value="tingkat:X">Semua kelas 10 (X)</option><option value="tingkat:XI">Semua kelas 11 (XI)</option><option value="tingkat:XII">Semua kelas 12 (XII)</option></optgroup><optgroup label="Kelas Tertentu"><?php foreach($kelas_list as $r):?><option value="<?=(int)$r['id']?>" data-tingkat="<?=sanitize($r['tingkat'])?>"><?=sanitize($r['nama_kelas'])?></option><?php endforeach ?></optgroup></select><small id="acaraTargetHelp" class="kak-target-help" role="status" aria-live="polite">Pilih satu kelas atau seluruh kelas dalam satu angkatan.</small></div>
<div class="col-md-4"><label for="acaraTanggal"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Tanggal acara</label><input id="acaraTanggal" name="tanggal_acara" type="date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
<div class="col-12" id="acaraSiswaTarget" data-kak-target="siswa" hidden><label for="acaraSiswa"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i>Siswa di Kelas Terpilih</label><select id="acaraSiswa" name="siswa_id" class="form-select" aria-describedby="acaraSiswaHelp" disabled><option value="">Semua siswa di kelas ini</option></select><small class="kak-target-help" id="acaraSiswaHelp" role="status" aria-live="polite">Pilih kelas untuk menampilkan siswa.</small></div>
</fieldset>
<fieldset class="kak-form-group"><legend><span class="kak-group-icon is-gold"><i class="fa-solid fa-medal" aria-hidden="true"></i></span><span>Detail Aktivitas<small>02 · Pilih kegiatan dari master aktivitas</small></span></legend>
<div class="col-12"><label for="acaraNama"><i class="fa-solid fa-list-check" aria-hidden="true"></i>Nama acara / aktivitas</label><select id="acaraNama" name="aktivitas_id" class="form-select" aria-describedby="acaraAktivitasHelp" required disabled><option value="">Pilih kelas terlebih dahulu</option></select><small id="acaraAktivitasHelp" class="kak-target-help" role="status" aria-live="polite">Aktivitas ditampilkan sesuai tingkat kelas.</small></div>

<div class="col-12"><label for="acaraDeskripsi"><i class="fa-regular fa-image" aria-hidden="true"></i>Keterangan / bukti yang diperlukan</label><textarea id="acaraDeskripsi" name="deskripsi" class="form-control" rows="2" maxlength="1000" placeholder="Contoh: unggah foto sertifikat atau dokumentasi keikutsertaan."></textarea></div>
</fieldset>
<div class="col-12"><div class="kak-form-footer"><span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Centang persetujuan setelah bukti diperiksa.</span><button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>Terbitkan Acara</button></div></div>
</form><aside class="kak-composer-aside" aria-label="Pratinjau acara"><details class="kak-event-preview" open><summary><span><i class="fa-regular fa-eye me-2" aria-hidden="true"></i>Pratinjau acara</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="kak-preview-body"><div class="kak-preview-cover"><span><i class="fa-solid fa-calendar-star" aria-hidden="true"></i><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span><small>AKTIVITAS SEKOLAH</small><strong><i class="fa-regular fa-square" aria-hidden="true"></i></strong></div><div class="kak-preview-copy"><span class="kak-preview-label">ACARA WAJIB</span><h3 data-preview-name>Nama kegiatan akan muncul di sini</h3><p data-preview-description>Keterangan bukti yang perlu dikirim siswa.</p><div class="kak-preview-meta"><span><i class="fa-regular fa-calendar" aria-hidden="true"></i><span data-preview-date>Tanggal acara</span></span><span><i class="fa-solid fa-users" aria-hidden="true"></i><span data-preview-target>Peserta belum dipilih</span></span></div><div class="kak-preview-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Status hijau setelah klaim disetujui.</div></div></div></details><p class="kak-preview-caption">Gambaran informasi acara yang akan dilihat siswa.</p></aside></div></div></section>
<?php endif ?>
<div class="kak-management-grid">
<?php if(in_array($kak_view,['verifikasi','acara'],true)): ?>
<div class="kak-export-bar"><span><i class="fa-solid fa-table" aria-hidden="true"></i>Unduh seluruh hasil pencarian dan filter ke Excel.</span><a class="btn btn-success" href="<?=sanitize('kak.php?'.http_build_query(array_replace($panel_query,['export'=>'excel'])))?>"><i class="fa-solid fa-file-excel me-2" aria-hidden="true"></i>Rekap Excel</a></div>
<?php endif ?>
<?php if($kak_view==='verifikasi'): ?>
<section class="card kak-staff-card mb-3" id="kakReviewPanel"><div class="card-body p-3 p-md-4"><div class="kak-section-head"><span class="kak-section-icon is-amber"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Verifikasi Klaim Siswa</h2><p>Periksa foto bukti, lalu centang kegiatan yang disetujui.</p></div><span class="kak-panel-count"><?=$claim_list['total']?></span></div>
<form method="get" action="kak.php#kakReviewPanel" class="kak-panel-filter">
<input type="hidden" name="halaman" value="verifikasi">
<label class="kak-panel-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="form-control" type="search" name="claims_q" value="<?=sanitize($claim_list['search'])?>" maxlength="100" placeholder="Cari siswa, kelas, atau acara" aria-label="Cari klaim siswa"></label><select class="form-select" name="claims_status" aria-label="Status klaim"><option value="">Semua status</option><?php foreach(['Menunggu','Disetujui','Ditolak'] as $status):?><option value="<?=$status?>" <?=$claim_list['status']===$status?'selected':''?>><?=$status?></option><?php endforeach ?></select><button class="btn btn-outline-primary" type="submit" aria-label="Tampilkan hasil pencarian klaim"><i class="fa-solid fa-filter" aria-hidden="true"></i></button></form>
<?php if(!$claims):?><div class="kak-staff-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><strong><?=$claim_list['search']||$claim_list['status']?'Klaim tidak ditemukan':'Belum ada klaim masuk'?></strong><p><?=$claim_list['search']||$claim_list['status']?'Coba nama atau status yang berbeda.':'Bukti yang dikirim siswa akan muncul di sini.'?></p></div><?php endif ?>
<p class="kak-grid-hint"><i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>Geser tabel untuk melihat seluruh kolom. Maksimal 10 data per halaman.</p>
<div class="kak-review-list kak-data-scroll" tabindex="0" role="region" aria-label="Tabel klaim siswa">
<table class="kak-data-table kak-claims-table"><caption class="visually-hidden">Klaim siswa dan verifikasi bukti</caption><thead><tr><th scope="col">No.</th><th scope="col"><i class="fa-regular fa-clock" aria-hidden="true"></i>Tanggal klaim</th><th scope="col"><i class="fa-solid fa-medal" aria-hidden="true"></i>Aktivitas</th><th scope="col"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i>Siswa / kelas</th><th scope="col"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Status</th><th scope="col"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>Bukti &amp; verifikasi</th></tr></thead><tbody>
<?php foreach($claims as $index=>$c):?><tr><td><?=($claim_list['page']-1)*10+$index+1?></td><td><?=date('d/m/Y',strtotime($c['created_at']))?><small class="kak-cell-meta"><?=date('H:i',strtotime($c['created_at']))?> WIB</small></td><td><strong><?=sanitize($c['nama'])?></strong></td><td><strong><?=sanitize($c['nama_lengkap'])?></strong><small class="kak-cell-meta"><?=sanitize($c['nama_kelas']??'Tanpa kelas')?></small></td><td><span class="claim-status status-<?=strtolower($c['status'])?>"><?=sanitize($c['status']==='Ditolak'?'Perlu perbaikan':$c['status'])?></span></td><td>
<details class="kak-review-details"><summary><span><i class="fa-solid <?= $c['status']==='Menunggu'?'fa-clipboard-check':'fa-eye' ?> me-2" aria-hidden="true"></i><?= $c['status']==='Menunggu'?'Tinjau Klaim':'Lihat Detail' ?></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="kak-review-content">
<a href="kak_bukti.php?id=<?=(int)$c['id']?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary my-3"><i class="fa-regular fa-image me-2" aria-hidden="true"></i>Lihat Foto Bukti<i class="fa-solid fa-arrow-up-right-from-square ms-2" aria-hidden="true"></i></a>
<?php if($c['catatan']):?><p class="small"><?=nl2br(sanitize($c['catatan']))?></p><?php endif ?>
<?php if($c['status']==='Menunggu'):?><form method="post" class="row g-2" data-kak-review-form><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="verifikasi"><input type="hidden" name="klaim_id" value="<?=(int)$c['id']?>"><div class="col-md-4"><label class="d-flex align-items-center gap-2"><input type="checkbox" name="disetujui" value="1" class="form-check-input"> Kegiatan disetujui</label><small>Centang untuk menyetujui. Jika perlu perbaikan, kosongkan dan isi catatan.</small></div><div class="col-md-8"><label for="review-<?=(int)$c['id']?>"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i>Catatan (wajib jika ditolak)</label><input id="review-<?=(int)$c['id']?>" name="catatan_verifikasi" class="form-control" maxlength="500"></div><div class="col-12"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-2" aria-hidden="true"></i>Simpan Verifikasi</button></div></form>
<?php elseif($c['catatan_verifikasi']):?><p class="small mb-0"><?=sanitize($c['catatan_verifikasi'])?></p><?php endif ?>
</div></details></td></tr><?php endforeach ?>
<?php if(!$claims):?><tr><td colspan="6" class="kak-cell-empty">Tidak ada klaim untuk ditampilkan.</td></tr><?php endif ?>
</tbody></table></div><?php $panel_records=$claim_list;$panel_page_key='claims_page';$panel_anchor='kakReviewPanel';require __DIR__.'/kak_staff_pagination.php'; ?></div></section>
<?php endif ?>
<?php if($kak_view==='acara'): ?>
<section class="card kak-staff-card mb-3" id="kakEventsPanel"><div class="card-body p-3 p-md-4"><div class="kak-section-head"><span class="kak-section-icon is-green"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Acara yang Diterbitkan</h2><p>Kegiatan yang tersedia untuk klaim siswa.</p></div><span class="kak-panel-count"><?=$event_list['total']?></span></div>
<form method="get" action="kak.php#kakEventsPanel" class="kak-panel-filter is-events">
<input type="hidden" name="halaman" value="acara">
<label class="kak-panel-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="form-control" type="search" name="events_q" value="<?=sanitize($event_list['search'])?>" maxlength="100" placeholder="Cari acara atau target kelas" aria-label="Cari acara"></label><button class="btn btn-outline-primary" type="submit" aria-label="Tampilkan hasil pencarian acara"><i class="fa-solid fa-filter" aria-hidden="true"></i></button></form>
<?php if(!$events):?><div class="kak-staff-empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i><strong><?=$event_list['search']?'Acara tidak ditemukan':'Belum ada acara'?></strong><p><?=$event_list['search']?'Coba kata pencarian yang berbeda.':'Mulai dengan menerbitkan acara di formulir atas.'?></p></div><?php endif ?>
<p class="kak-grid-hint"><i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>Geser tabel untuk melihat seluruh kolom. Maksimal 10 data per halaman.</p>
<div class="kak-published-list kak-data-scroll" tabindex="0" role="region" aria-label="Tabel acara diterbitkan"><table class="kak-data-table kak-events-table"><caption class="visually-hidden">Acara sekolah yang diterbitkan</caption><thead><tr><th scope="col">No.</th><th scope="col"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Tanggal acara</th><th scope="col"><i class="fa-solid fa-medal" aria-hidden="true"></i>Nama aktivitas</th><th scope="col"><i class="fa-solid fa-users" aria-hidden="true"></i>Sasaran</th><th scope="col"><i class="fa-solid fa-infinity" aria-hidden="true"></i>Periode klaim</th></tr></thead><tbody>
<?php foreach($events as $index=>$e):?><tr><td><?=($event_list['page']-1)*10+$index+1?></td><td><div class="kak-event-date-cell"><span><?=date('d/m/Y',strtotime($e['tanggal_acara']))?></span>
<button type="button" class="kak-edit-date-button" data-edit-event-date data-id="<?=(int)$e['id']?>" data-date="<?=sanitize($e['tanggal_acara'])?>" data-name="<?=sanitize($e['nama'])?>" aria-label="Edit tanggal <?=sanitize($e['nama'])?>" aria-haspopup="dialog" aria-controls="kakDateDialog"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></button></div></td><td><strong><?=sanitize($e['nama'])?></strong></td><td><?=sanitize($e['target_tingkat']?'Semua kelas '.(['X'=>'10','XI'=>'11','XII'=>'12'][$e['target_tingkat']]).' ('.$e['target_tingkat'].')':($e['nama_lengkap']??$e['nama_kelas']??'Semua siswa'))?></td><td>Tanpa batas waktu</td></tr><?php endforeach ?>
<?php if(!$events):?><tr><td colspan="5" class="kak-cell-empty">Tidak ada acara untuk ditampilkan.</td></tr><?php endif ?>
</tbody></table></div>
<dialog id="kakDateDialog" class="kak-date-dialog" aria-labelledby="kakDateTitle">
<div class="kak-date-dialog-heading"><h2 id="kakDateTitle">Edit Tanggal Acara</h2><button type="button" data-close-date aria-label="Tutup"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
<p data-date-event-name></p>
<form method="post" id="kakDateForm"><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="ubah_tanggal"><input type="hidden" name="acara_id"><label for="kakDateInput">Tanggal acara</label><input id="kakDateInput" type="date" name="tanggal_acara" min="1000-01-01" max="9999-12-31" class="form-control" required><div class="kak-date-dialog-actions"><button type="button" class="btn btn-outline-secondary" data-close-date>Batal</button><button type="submit" class="btn btn-primary">Simpan Tanggal</button></div></form>
</dialog>
<?php $panel_records=$event_list;$panel_page_key='events_page';$panel_anchor='kakEventsPanel';require __DIR__.'/kak_staff_pagination.php'; ?></div></section>
<?php endif ?>
</div>
<?php endif ?>
</main></div>
<script type="application/json" id="kakTargetStudents"><?=json_encode($siswa_list,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
<script type="application/json" id="kakMasterActivities"><?=json_encode($master_activities,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
<script src="../assets/js/kak-staff.js?v=<?=filemtime(__DIR__.'/../assets/js/kak-staff.js')?>" defer></script>
<?php require_once __DIR__.'/footer.php'; ?>
