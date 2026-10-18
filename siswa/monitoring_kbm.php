<?php
require_once __DIR__.'/../config/auth.php';
check_access([3]);
require_once __DIR__.'/../includes/monitoring_kbm_siswa.php';
$db=Database::getInstance();
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
if(!kbm_admin_ready($db)){http_response_code(503);exit('Monitoring KBM belum dikonfigurasi. Hubungi admin sekolah.');}
$assignments=kbm_student_assignments($db,(int)$_SESSION['user_id']);
if(!$assignments){http_response_code(403);exit('Monitoring KBM hanya tersedia bagi ketua kelas dengan penugasan aktif.');}
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token'])) throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman lalu coba lagi.');
        $_SESSION['kbm_siswa_success']=kbm_student_post($db,(int)$_SESSION['user_id'],$_POST);
        if(($_POST['action']??'')==='checkout') $_SESSION['kbm_siswa_closed']=(int)$_POST['id'];
    } catch(KbmStudentForbidden $e) {http_response_code(403);exit($e->getMessage());}
    catch(PDOException $e) {error_log('Pelaporan KBM siswa: '.$e->getMessage());$_SESSION['kbm_siswa_error']='Pencatatan belum berhasil. Muat ulang halaman untuk memeriksa hasil sebelum mencoba lagi.';}
    catch(InvalidArgumentException|RuntimeException $e) {$_SESSION['kbm_siswa_error']=$e->getMessage();}
    if(in_array($_POST['action']??'', ['reset_pilihan','hapus_pilihan'],true)&&!isset($_SESSION['kbm_siswa_error']))redirect('monitoring_kbm.php?reset=1');
    redirect('monitoring_kbm.php'.(is_string($_POST['jp']??null)&&preg_match('/^[1-6]$/D',$_POST['jp'])?'?jp='.$_POST['jp'].(is_string($_POST['jp_selesai']??null)&&preg_match('/^[1-6]$/D',$_POST['jp_selesai'])?'&akhir='.$_POST['jp_selesai']:''):''));
}
$class=(int)$assignments[0]['kelas_id'];
require_once __DIR__.'/../includes/monitoring_kbm_pilihan.php';
if(kbm_pilihan_ready($db)){require __DIR__.'/../includes/monitoring_kbm_pilihan_page.php';exit;}
$snapshot=kbm_monitoring_snapshot($db,$class);$now=$snapshot['now'];$today=$snapshot['date'];
$classCard=$snapshot['classes'][0];$classStatus=$classCard['state']['primary'];
$periodIds=array_map('intval',array_column($assignments,'periode_id'));
$expected=array_values(array_filter(kbm_expected($db,$today),fn($r)=>(int)$r['kelas_id']===$class && in_array((int)$r['periode_id'],$periodIds,true)));
$reports=$classCard['meetings'];
$open=kbm_open_meetings($db,$class);$bySchedule=[];
foreach($reports as $r) $bySchedule[$r['jadwal_id']]=$r;
$jp=is_string($_GET['jp']??null)&&(int)$_GET['jp']>=1&&(int)$_GET['jp']<=6?(int)$_GET['jp']:0;
$selected=null;
foreach($expected as $r) if(($jp && $jp>=$r['jp_mulai'] && $jp<=$r['jp_selesai']) || (!$jp && $now>=$r['rencana_mulai'] && $now<$r['rencana_selesai'])) {$selected=$r;break;}
if(!$jp && !$selected && $expected) $selected=$expected[0];
$sessions=array_map(fn($s)=>['sesi_kode'=>$s],$classCard['context']['sessions']);
$holidays=array_map(fn($s)=>['alasan'=>$s],$classCard['context']['holidays']);
$breaks=array_values(array_filter($classCard['context']['breaks'],fn($b)=>$b['mulai']<=$now&&$now<$b['selesai']));
$closed=[];
if(!empty($_SESSION['kbm_siswa_closed'])) $closed=kbm_rows($db,'SELECT p.*,l.checkin_at,l.checkout_at FROM kbm_pertemuan p JOIN kbm_laporan l ON l.pertemuan_id=p.id WHERE p.id=? AND p.kelas_id=? AND l.checkout_at IS NOT NULL',[(int)$_SESSION['kbm_siswa_closed'],$class]);
unset($_SESSION['kbm_siswa_closed']);
$success=$_SESSION['kbm_siswa_success']??'';$error=$_SESSION['kbm_siswa_error']??'';unset($_SESSION['kbm_siswa_success'],$_SESSION['kbm_siswa_error']);
function kbm_s_h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function kbm_s_range(array $r): string {return 'JP '.$r['jp_mulai'].($r['jp_mulai']===$r['jp_selesai']?'':'–'.$r['jp_selesai']);}
function kbm_s_time(?string $v): string {return $v===null?'—':$v.' WIB';}
function kbm_s_button(string $action,int $id,string $label,string $class='primary'): void {
    echo '<form method="post" class="kbm-record-form"><input type="hidden" name="csrf_token" value="'.kbm_s_h($_SESSION['csrf_token']).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'"><button class="btn btn-'.$class.' w-100 fw-semibold"><i class="fa-solid fa-'.($action==='checkin'?'right-to-bracket':'right-from-bracket').' me-2" aria-hidden="true"></i>'.$label.'</button></form>';
}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<style>
.kbm-student{max-width:1100px;margin:auto}.kbm-student .card{border:1px solid #e1e8f0;border-radius:18px;box-shadow:0 5px 20px #17345908}.kbm-student .btn{min-height:48px;border-radius:12px}.kbm-student .jp-nav{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}.kbm-student .jp-nav a{padding:12px 3px;text-align:center;text-decoration:none;border:1px solid #d3e1f5;border-radius:12px;background:white;font-weight:650;color:#295fa2}.kbm-student .jp-nav a.active{background:#285fa5;color:white}.kbm-student .jp-nav a.unscheduled{color:#7b8797;background:#f0f3f7}.kbm-student .schedule-row{border-bottom:1px solid #e6ecf3;padding:15px 0}.kbm-student .schedule-row:last-child{border:0}.kbm-student .time-box{background:#f3f7fc;border-radius:12px;padding:14px;overflow-wrap:anywhere}.kbm-student .status{display:inline-block;border-radius:8px;padding:6px 10px;font-size:.82rem;font-weight:650}.kbm-student .icon-box{display:inline-grid;place-items:center;width:46px;height:46px;border-radius:13px;background:#e7f2ff;color:#285fa5}.kbm-student .muted{color:#63758b}@media(max-width:575.98px){.kbm-student .jp-nav{gap:5px}.kbm-student .jp-nav a{font-size:.85rem}.kbm-student h1{font-size:1.25rem}.kbm-student .card-body{padding:18px!important}}
</style>
<div id="page-content-wrapper">
<nav class="navbar top-navbar p-3 px-md-4"><div><h1 class="h4 fw-bold mb-1"><i class="fa-solid fa-chalkboard-user text-primary me-2" aria-hidden="true"></i>Monitoring KBM</h1><span class="text-muted">Laporan ketua kelas tentang kegiatan belajar mengajar.</span></div></nav>
<main class="container-fluid p-3 p-md-4 kbm-student">
<div class="alert alert-info">Pelaporan JP dengan pilihan guru belum diaktifkan. Hubungi admin untuk mengaktifkannya. Jadwal yang sudah tersedia tetap dapat digunakan di bawah ini.</div>
<?php if($success): ?><div class="alert alert-success" role="status"><?= kbm_s_h($success) ?></div><?php endif ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= kbm_s_h($error) ?></div><?php endif ?>
<?php foreach($closed as $r): $closedState=kbm_meeting_status($r,$now); ?><section class="alert alert-<?= kbm_s_h($closedState['bootstrap']) ?>" role="status"><h2 class="h6 fw-bold"><i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>Pertemuan selesai dicatat</h2><p class="fw-semibold"><?= kbm_s_h($closedState['label']) ?></p><p class="mb-2"><?= kbm_s_h($r['nama_mapel'].' · '.kbm_s_range($r).' · '.$r['tanggal']) ?></p><div>Masuk: <?= kbm_s_h(kbm_s_time($r['checkin_at'])) ?><br>Selesai: <?= kbm_s_h(kbm_s_time($r['checkout_at'])) ?><br>Durasi tercatat: <?= kbm_s_h(kbm_student_duration($r['checkin_at'],$r['checkout_at'])) ?></div></section><?php endforeach ?>
<section class="card mb-3"><div class="card-body p-4"><div class="d-flex gap-3 align-items-center"><span class="icon-box"><i class="fa-solid fa-school" aria-hidden="true"></i></span><div><h2 class="h5 fw-bold mb-1"><?= kbm_s_h($assignments[0]['nama_kelas']) ?></h2><span class="muted"><?= kbm_s_h($sessions?implode(' / ',array_map('ucfirst',array_column($sessions,'sesi_kode'))):'Sesi belum diatur') ?> · <?= kbm_s_h($today) ?></span></div></div><div class="small muted mt-3">Waktu server saat halaman dimuat: <strong><?= kbm_s_h(substr($now,11)) ?> WIB</strong>. Muat ulang untuk memperbarui jadwal dan status.</div><a class="btn btn-outline-secondary btn-sm mt-3" href="monitoring_kbm.php"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Muat Ulang</a></div></section>
<section class="alert alert-<?= kbm_s_h($classStatus['bootstrap']) ?> mb-3" aria-label="Status utama kelas"><h2 class="h6 fw-bold mb-1"><i class="fa-solid fa-<?= kbm_s_h($classStatus['icon']) ?> me-2" aria-hidden="true"></i><?= kbm_s_h($classStatus['label']) ?></h2><p class="small mb-0">Status kelas mengikuti jadwal yang berjalan. Catatan sebelumnya yang belum selesai ditampilkan terpisah.</p></section>
<?php foreach($open as $r): $openStatus=kbm_meeting_status($r,$now); ?><section class="alert alert-<?= kbm_s_h($openStatus['bootstrap']) ?> mb-3" role="alert"><h2 class="h6 fw-bold"><i class="fa-solid fa-<?= kbm_s_h($openStatus['icon']) ?> me-2" aria-hidden="true"></i><?= kbm_s_h($openStatus['label']) ?></h2><p class="fw-semibold"><?= kbm_s_h($openStatus['warning']?:'Belum check-out') ?></p><p class="mb-2"><?= kbm_s_h($r['nama_mapel'].' · '.$r['nama_guru'].' · '.kbm_s_range($r).' · '.$r['tanggal']) ?><br>Masuk: <?= kbm_s_h(kbm_s_time($r['checkin_efektif'])) ?></p><p class="small">Tekan check-out ketika guru selesai. Jika terlupa, penutupan tetap menggunakan waktu aktual server dan catatan ini harus ditutup sebelum pertemuan lain dimulai.</p><?php if($r['koreksi_id']): ?><p class="mb-0">Catatan telah dikoreksi admin. Hubungi admin untuk tindak lanjut.</p><?php else: kbm_s_button('checkout',(int)$r['pertemuan_id'],'Check-out Guru','warning'); endif ?></section><?php endforeach ?>
<section class="card mb-3"><div class="card-body p-4"><h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-list-ol text-primary me-2" aria-hidden="true"></i>Pilih JP hari ini</h2><nav class="jp-nav" aria-label="Pilihan jam pelajaran"><?php for($n=1;$n<=6;$n++): $has=false;foreach($expected as $r)if($n>=$r['jp_mulai']&&$n<=$r['jp_selesai'])$has=true;$active=$selected&&$n>=$selected['jp_mulai']&&$n<=$selected['jp_selesai']; ?><a href="?jp=<?= $n ?>" class="<?= $active?'active':(!$has?'unscheduled':'') ?>" <?= $active?'aria-current="true"':'' ?>>JP <?= $n ?></a><?php endfor ?></nav><p class="small muted mt-3 mb-0">JP dalam satu blok memakai catatan yang sama. Contoh: JP 1–2 dicatat satu kali.</p></div></section>
<?php if($classStatus['code']==='istirahat' && $breaks): ?><div class="alert alert-warning"><i class="fa-solid fa-mug-hot me-2" aria-hidden="true"></i><?= kbm_s_h($classStatus['label']) ?>. Check-in tidak tersedia sampai <?= kbm_s_h(substr($breaks[0]['selesai'],11,5)) ?> WIB. Pertemuan sebelumnya tetap dapat di-check-out.</div><?php endif ?>
<section class="card mb-3"><div class="card-body p-4">
<?php if(!$selected): ?><h2 class="h6 fw-bold">Tidak ada jadwal yang dapat dipilih</h2><p class="muted mb-0"><?= $holidays?'Hari tanpa KBM: '.kbm_s_h(implode('; ',array_column($holidays,'alasan'))):($expected?'JP yang dipilih tidak memiliki jadwal KBM. Pilih JP lain.':'Belum ada jadwal KBM hari ini. Jika seharusnya ada pembelajaran, hubungi admin untuk memeriksa sesi dan jadwal kelas.') ?></p>
<?php else: $r=$bySchedule[$selected['jadwal_id']]??$selected;$in=$r['checkin_efektif']??null;$out=$r['checkout_efektif']??null;$meetingStatus=kbm_meeting_status($r,$now);$corrected=!empty($r['koreksi_id']); ?>
<div class="d-flex justify-content-between gap-2 flex-wrap mb-3"><span class="status bg-primary-subtle text-primary"><?= kbm_s_h(kbm_s_range($selected).' · '.$selected['nama_sesi']) ?></span><span class="small muted"><?= kbm_s_h(substr($selected['rencana_mulai'],11,5).'–'.substr($selected['rencana_selesai'],11,5).' WIB') ?></span></div>
<h2 class="h4 fw-bold mb-2"><?= kbm_s_h($selected['nama_mapel']) ?></h2><p class="muted"><i class="fa-solid fa-user-tie me-2" aria-hidden="true"></i><?= kbm_s_h($selected['nama_guru']) ?></p>
<?php if($in!==null): ?><div class="row g-2 mb-3"><div class="col-sm-6"><div class="time-box"><small>Waktu masuk</small><div class="fw-semibold"><?= kbm_s_h(kbm_s_time($in)) ?></div></div></div><div class="col-sm-6"><div class="time-box"><small>Waktu selesai</small><div class="fw-semibold"><?= kbm_s_h(kbm_s_time($out)) ?></div></div></div></div><p class="fw-semibold"><?= kbm_s_h($out!==null?'Durasi tercatat: '.kbm_student_duration($in,$out):'Belum check-out') ?></p><?php endif ?>
<?php if($corrected): ?><div class="alert alert-info mb-0">Catatan telah dikoreksi admin. Siswa tidak dapat mengubah atau membukanya kembali.</div>
<?php elseif($meetingStatus['code']==='selesai'): ?><div class="alert alert-<?= kbm_s_h($meetingStatus['bootstrap']) ?> mb-0"><?= kbm_s_h($meetingStatus['label']) ?></div>
<?php elseif($in!==null): ?><p class="small muted mb-0">Gunakan tombol Check-out Guru pada peringatan pertemuan aktif di atas.</p>
<?php elseif($meetingStatus['code']==='belum_mulai'): ?><div class="alert alert-<?= kbm_s_h($meetingStatus['bootstrap']) ?> mb-0"><?= kbm_s_h($meetingStatus['label']) ?>. Check-in tersedia mulai <?= kbm_s_h(substr($selected['rencana_mulai'],11,5)) ?> WIB.</div>
<?php elseif($meetingStatus['code']==='tanpa_checkin'): ?><div class="alert alert-<?= kbm_s_h($meetingStatus['bootstrap']) ?> mb-0"><?= kbm_s_h($meetingStatus['label']) ?>. Hubungi admin bila diperlukan; status ini tidak menyimpulkan ketidakhadiran fisik guru.</div>
<?php elseif($open): ?><div class="alert alert-warning mb-0">Tutup pertemuan yang belum check-out terlebih dahulu.</div>
<?php else: kbm_s_button('checkin',(int)$selected['jadwal_id'],'Check-in Guru'); endif ?>
<?php endif ?>
</div></section>
<section class="card"><div class="card-body p-4"><h2 class="h6 fw-bold"><i class="fa-solid fa-calendar-day text-primary me-2" aria-hidden="true"></i>Jadwal hari ini</h2>
<?php if(!$reports): ?><p class="muted mb-0">Tidak ada jadwal KBM hari ini.</p><?php endif ?>
<?php foreach($reports as $r): $in=$r['checkin_efektif'];$out=$r['checkout_efektif'];$s=$r['status']; ?><article class="schedule-row"><div class="d-flex justify-content-between gap-2 flex-wrap"><strong><?= kbm_s_h(kbm_s_range($r).' · '.$r['nama_mapel']) ?></strong><span class="status bg-<?= kbm_s_h($s['bootstrap']) ?> <?= $s['bootstrap']==='warning'?'text-dark':'text-white' ?>"><?= kbm_s_h($s['label']) ?></span></div><?php if($s['warning']): ?><div class="small fw-bold mt-2"><?= kbm_s_h($s['warning']) ?></div><?php endif ?><div class="small muted mt-1"><?= kbm_s_h($r['nama_guru'].' · '.$r['nama_sesi'].' · '.substr($r['rencana_mulai'],11,5).'–'.substr($r['rencana_selesai'],11,5).' WIB') ?></div><?php if($in!==null): ?><div class="small mt-2">Masuk: <?= kbm_s_h(kbm_s_time($in)) ?><br>Selesai: <?= kbm_s_h(kbm_s_time($out)) ?><br>Durasi tercatat: <?= kbm_s_h(kbm_student_duration($in,$out)) ?></div><?php endif ?></article><?php endforeach ?>
</div></section><p class="small muted mt-3">Catatan bersumber dari laporan ketua kelas. Sistem tidak mendeteksi keberadaan fisik guru secara otomatis.</p>
</main></div>
<script>
document.querySelectorAll('.kbm-record-form').forEach(form=>form.addEventListener('submit',event=>{
 if(form.dataset.submitting==='1'){event.preventDefault();return;}
 form.dataset.submitting='1';const button=form.querySelector('button');button.disabled=true;button.textContent='Menyimpan…';
}));
window.addEventListener('pageshow',()=>document.querySelectorAll('.kbm-record-form').forEach(form=>{if(form.dataset.submitting==='1')location.reload();}));
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
