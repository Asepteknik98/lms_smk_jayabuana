<?php
if(!isset($_SESSION['user_id'])||(int)($_SESSION['role_id']??0)!==1){http_response_code(403);exit;}
require_once __DIR__.'/monitoring_kbm_report.php';
require_once __DIR__.'/monitoring_kbm_pkl.php';
$db=Database::getInstance();
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
$tabs=['kelas'=>['school','Sesi Kelas'],'pkl'=>['people-group','Kelas PKL Gabungan'],'ketua'=>['user-shield','Pengguna'],'jadwal'=>['clipboard-list','Laporan KBM']];
$tab=is_string($_GET['tab']??null)&&isset($tabs[$_GET['tab']])?$_GET['tab']:'kelas';
$classId=is_string($_GET['kelas']??null)&&ctype_digit($_GET['kelas'])?(int)$_GET['kelas']:0;
$pklId=$tab==='ketua'&&is_string($_GET['pkl']??null)?$_GET['pkl']:'';
if($tab==='ketua'&&is_string($_GET['kelas']??null)&&str_starts_with($_GET['kelas'],'pkl:'))$pklId=substr($_GET['kelas'],4);
$ready=kbm_admin_ready($db)&&kbm_simple_ready($db)&&kbm_pilihan_tanpa_jam_ready($db);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman lalu coba lagi.');
        if(!$ready)throw new RuntimeException('Pembaruan penyederhanaan Monitoring KBM belum diterapkan oleh pengelola sistem.');
        $action=$_POST['action']??null;
        if(in_array($action,['pkl_save','pkl_delete'],true)){
            $pklClasses=kbm_rows($db,'SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas');
            $_SESSION['kbm_success']=kbm_pkl_save($_POST,$pklClasses);
        }elseif($action==='pkl_sesi'){
            $_SESSION['kbm_success']=kbm_pkl_session($db,(int)$_SESSION['user_id'],$_POST);
        }elseif($action==='pkl_ketua'){
            $_SESSION['kbm_success']=kbm_pkl_add_users($db,(int)$_SESSION['user_id'],$_POST);
        }else $_SESSION['kbm_success']=kbm_admin_submit($db,(int)$_SESSION['user_id'],$_POST);
    }catch(PDOException $e){error_log('Pengaturan KBM sederhana: '.$e->getMessage());$_SESSION['kbm_error']='Pengaturan belum tersimpan. Muat ulang dan periksa kembali.';}
    catch(InvalidArgumentException|RuntimeException $e){$_SESSION['kbm_error']=$e->getMessage();}
    redirect('monitoring_kbm.php?'.http_build_query(['tab'=>$tab,'kelas'=>$classId,'pkl'=>$pklId]));
}
$success=$_SESSION['kbm_success']??'';$error=$_SESSION['kbm_error']??'';unset($_SESSION['kbm_success'],$_SESSION['kbm_error'],$_SESSION['kbm_old']);
$classes=kbm_rows($db,'SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas');
$pklGroups=[];$pklAvailable=true;
try{$pklGroups=kbm_pkl_read();}catch(RuntimeException $e){$error=$e->getMessage();$pklAvailable=false;}
$selectedPkl=null;
foreach($pklGroups as $group)if($group['id']===$pklId)$selectedPkl=$group;
if($pklId!==''&&!$selectedPkl){$error='Kelompok PKL tidak ditemukan. Pilih ulang kelas.';$pklId='';}
if(!$classId&&$classes)$classId=(int)$classes[0]['id'];
$sessions=$chairs=$students=$reports=[];$reportData=null;
$reportToday=(string)$db->query('SELECT CURRENT_DATE')->fetchColumn();
try{$reportPeriod=kbm_report_period($_GET,$reportToday);}catch(InvalidArgumentException $e){$reportPeriod=kbm_report_period([],$reportToday);$error=$e->getMessage();}

if($ready){
    $sessions=kbm_simple_sessions($db);$chairs=kbm_simple_chair_slots($db);
    if($tab==='ketua'){
        $userClassIds=$selectedPkl?$selectedPkl['kelas_ids']:[$classId];
        $marks=implode(',',array_fill(0,count($userClassIds),'?'));
        $students=kbm_rows($db,'SELECT s.id,s.nama_lengkap,s.nis,k.nama_kelas FROM siswa s JOIN users u ON u.id=s.user_id JOIN kelas k ON k.id=s.kelas_id WHERE s.kelas_id IN ('.$marks.') AND u.role_id=3 AND u.is_active=1 ORDER BY s.nama_lengkap,s.id',$userClassIds);
    }
    if($tab==='jadwal'){
        try{$reportData=kbm_history_page($db,['tanggal_mulai'=>$reportPeriod['tanggal_mulai'],'tanggal_selesai'=>$reportPeriod['tanggal_selesai'],'halaman'=>$_GET['halaman']??'1','per_halaman'=>'25']);$reports=$reportData['rows'];}
        catch(InvalidArgumentException $e){$error=$e->getMessage();}
    }
}
$sessionByClass=[];foreach($sessions as $s)$sessionByClass[$s['kelas_id']]=$s;
$chairByClass=[];foreach($chairs as $s)$chairByClass[$s['kelas_id']][]=$s;
$selectedUsers=$selectedPkl?kbm_pkl_slots($chairs,$selectedPkl['kelas_ids']):($chairByClass[$classId]??[]);$selectedUserIds=array_map('intval',array_column($selectedUsers,'siswa_id'));$slotsFull=count($selectedUsers)>=4;
$availableStudents=array_values(array_filter($students,fn($s)=>!in_array((int)$s['id'],$selectedUserIds,true)));
function kbm_simple_h(mixed $v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function kbm_simple_token(): void{echo '<input type="hidden" name="csrf_token" value="'.kbm_simple_h($_SESSION['csrf_token']).'">';}
require __DIR__.'/header.php';require __DIR__.'/sidebar.php';
?>
<style>.kbm-settings{max-width:1350px;margin:auto}.kbm-settings .card{border:1px solid #dfe7f0;border-radius:16px;box-shadow:0 5px 18px #1f385908}.kbm-settings .form-control,.kbm-settings .form-select,.kbm-settings .btn{min-height:44px}.kbm-settings .form-label{font-weight:600}.kbm-settings .table th{background:#f1f5fa;color:#50647b}.kbm-settings .nav-link{border-radius:10px}.kbm-settings .table td{vertical-align:middle}.kbm-class-list .kbm-class-scroll{max-height:clamp(280px,48vh,470px);border:1px solid #e0e7f0;border-radius:10px;scrollbar-gutter:stable}.kbm-class-list table{table-layout:fixed;min-width:570px;font-size:.92rem}.kbm-class-list thead th{position:sticky;top:0;z-index:1;box-shadow:0 1px 0 #dfe7f0}.kbm-class-list td,.kbm-class-list th{padding:10px 14px}.kbm-class-list tbody tr:last-child td{border-bottom:0}.kbm-class-list .btn{min-height:34px;padding:5px 11px;border-radius:8px;font-size:.85rem}.kbm-class-list .kbm-col-class{width:25%}.kbm-class-list .kbm-col-session{width:20%}.kbm-class-list .kbm-col-action{width:155px}.kbm-class-search{width:250px;max-width:100%}.kbm-session-badge{display:inline-block;padding:4px 9px;border-radius:6px;background:#f0f2f5;color:#65758a;font-size:.82rem}.kbm-session-morning{background:#edf4ff;color:#285fa5}.kbm-session-afternoon{background:#fff4e1;color:#966318}.kbm-users-details summary{cursor:pointer;color:#285fa5;font-weight:600;font-size:.85rem}.kbm-users-details[open]{min-width:195px}@media(max-width:767px){.kbm-class-search{width:100%}.kbm-class-list .btn{min-height:44px}.kbm-class-list .kbm-class-scroll{max-height:380px}}
</style>
<div id="page-content-wrapper"><nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h1 class="h4 fw-bold mb-1"><i class="fa-solid fa-sliders text-primary me-2" aria-hidden="true"></i>Pengaturan Monitoring KBM</h1><span class="text-muted">Cukup atur sesi kelas dan tunjuk pengguna untuk mulai melaporkan KBM.</span></div></nav>
<main class="container-fluid p-3 p-md-4 kbm-settings">
<?php if($success): ?><div class="alert alert-success" role="status"><?= kbm_simple_h($success) ?></div><?php endif ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= kbm_simple_h($error) ?></div><?php endif ?>
<?php if(!$ready): ?><div class="alert alert-warning">Pengelola sistem perlu menerapkan pembaruan <strong>2026_10_18_monitoring_kbm_sederhana.sql</strong> satu kali. Setelah itu pengaturan sesi dan pengguna dapat langsung digunakan.</div><?php else: ?>
<div class="alert alert-primary"><i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i>Pengguna langsung memilih JP dan guru, lalu check-in/check-out. Tidak perlu mengatur semester, jam JP, atau hari libur.</div>
<nav class="nav nav-pills gap-2 mb-4" aria-label="Pengaturan Monitoring KBM"><?php foreach($tabs as $key=>[$icon,$label]): ?><a class="nav-link <?= $tab===$key?'active':'bg-white border' ?>" href="?<?= kbm_simple_h(http_build_query(['tab'=>$key,'kelas'=>$classId])) ?>"><i class="fa-solid fa-<?= $icon ?> me-2" aria-hidden="true"></i><?= $label ?></a><?php endforeach ?></nav>
<section class="card mb-4"><div class="card-body p-3 p-md-4"><h2 class="h5 fw-bold mb-3"><?= $tabs[$tab][1] ?></h2>
<?php if(!$classes): ?><div class="alert alert-warning">Tambahkan kelas pada Master Data terlebih dahulu.</div>
<?php elseif($tab==='kelas'): ?>
<p class="text-muted">Pilih kelas dan sesi. Sesi berlaku sampai admin mengubahnya kembali.</p>
<form method="post" class="row g-3"><?php kbm_simple_token() ?><input type="hidden" name="action" value="simple_sesi"><div class="col-md-6"><label for="kelasSesi" class="form-label">Kelas</label><select id="kelasSesi" name="kelas_id" class="form-select" required><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $classId===(int)$c['id']?'selected':'' ?>><?= kbm_simple_h($c['nama_kelas']) ?></option><?php endforeach ?></select></div><div class="col-md-6"><label for="sesiSekolah" class="form-label">Sesi sekolah</label><select id="sesiSekolah" name="sesi_kode" class="form-select" required><option value="pagi" <?= ($sessionByClass[$classId]['sesi_kode']??'')==='pagi'?'selected':'' ?>>Pagi</option><option value="siang" <?= ($sessionByClass[$classId]['sesi_kode']??'')==='siang'?'selected':'' ?>>Siang</option></select></div><div class="col-12"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Simpan Sesi Kelas</button></div></form>
<?php elseif($tab==='pkl'): require __DIR__.'/monitoring_kbm_pkl_page.php'; ?>
<?php elseif($tab==='ketua'): require __DIR__.'/monitoring_kbm_users_page.php'; ?>
<?php else: require __DIR__.'/monitoring_kbm_report_page.php'; endif ?>
</div></section>
<?php if(!in_array($tab,['jadwal','pkl'],true)): ?><section class="card kbm-class-list"><div class="card-body p-3 p-md-4"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3"><div><h2 class="h6 fw-bold mb-1">Daftar Kelas <span class="badge bg-light text-secondary border ms-1"><?= count($classes) ?></span></h2><small class="text-muted">Cari kelas atau geser daftar. Buka jumlah pengguna untuk melihat rinciannya.</small></div><div class="kbm-class-search"><label class="visually-hidden" for="kbmClassSearch">Cari kelas</label><input id="kbmClassSearch" class="form-control" type="search" placeholder="Cari kelas..." autocomplete="off" aria-controls="kbmClassTable"></div></div><div class="table-responsive kbm-class-scroll" role="region" aria-label="Daftar kelas, dapat digeser" tabindex="0"><table id="kbmClassTable" class="table mb-0"><colgroup><col class="kbm-col-class"><col class="kbm-col-session"><col><col class="kbm-col-action"></colgroup><thead><tr><th>Kelas</th><th>Sesi</th><th>Pengguna</th><th>Aksi</th></tr></thead><tbody><?php foreach($classes as $c): $classUsers=$chairByClass[$c['id']]??[]; ?><tr data-class-name="<?= kbm_simple_h(mb_strtolower($c['nama_kelas'])) ?>"><td class="fw-semibold"><?= kbm_simple_h($c['nama_kelas']) ?></td><td><span class="kbm-session-badge <?= ($sessionByClass[$c['id']]['sesi_kode']??'')==='pagi'?'kbm-session-morning':(($sessionByClass[$c['id']]['sesi_kode']??'')==='siang'?'kbm-session-afternoon':'') ?>"><?= kbm_simple_h($sessionByClass[$c['id']]['nama_sesi']??'Belum dipilih') ?></span></td><td><?php if(!$classUsers): ?><span class="text-muted">Belum ditunjuk</span><?php else: ?><details class="kbm-users-details"><summary><?= count($classUsers) ?>/4 pengguna <span class="text-muted fw-normal">&middot; Lihat</span></summary><div class="mt-2"><?php foreach($classUsers as $chair): ?><div class="d-flex gap-2 align-items-center justify-content-between flex-wrap border rounded p-2 mb-2"><div><?= kbm_simple_h($chair['nama_lengkap']) ?><?php if(!$chair['is_active']||(int)$chair['role_id']!==3||(int)$chair['kelas_sekarang']!==(int)$c['id']): ?><small class="text-danger d-block">Akun nonaktif atau bukan siswa aktif kelas ini. Cabut untuk mengosongkan slot.</small><?php endif ?></div><?php if($tab==='ketua'): ?><form method="post" data-confirm="Cabut izin pengguna ini? Pengguna lain dan riwayat laporan tetap tersimpan."><?php kbm_simple_token() ?><input type="hidden" name="action" value="cabut_ketua"><input type="hidden" name="id" value="<?= (int)$chair['id'] ?>"><button class="btn btn-outline-danger btn-sm" aria-label="Cabut penugasan <?= kbm_simple_h($chair['nama_lengkap']) ?>">Cabut Penugasan</button></form><?php endif ?></div><?php endforeach ?></div></details><?php endif ?></td><td><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary btn-sm" href="?tab=<?= $tab ?>&amp;kelas=<?= (int)$c['id'] ?>">Atur <?= $tab==='kelas'?'Sesi':'Pengguna' ?></a></div></td></tr><?php endforeach ?><tr id="kbmClassEmpty" <?= $classes?'hidden':'' ?>><td colspan="4" class="text-center text-muted py-4">Kelas tidak ditemukan.</td></tr></tbody></table></div><small id="kbmClassCount" class="text-muted d-block mt-2" aria-live="polite"><?= count($classes) ?> kelas</small></div></section><?php endif ?>
<?php endif ?></main></div>
<script>document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirm))event.preventDefault();}));
const kbmClassSearch=document.getElementById('kbmClassSearch');
if(kbmClassSearch){
    const rows=Array.from(document.querySelectorAll('#kbmClassTable [data-class-name]'));
    kbmClassSearch.addEventListener('input',function(){
        const term=this.value.trim().toLocaleLowerCase('id-ID');let visible=0;
        for(const row of rows){row.hidden=!row.dataset.className.includes(term);if(!row.hidden)visible++;}
        document.getElementById('kbmClassEmpty').hidden=visible>0;
        document.getElementById('kbmClassCount').textContent=visible+' dari '+rows.length+' kelas';
    });
}
</script>
<?php require __DIR__.'/footer.php'; ?>
