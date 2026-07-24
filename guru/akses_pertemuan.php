<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
check_access([2]);
$db = Database::getInstance();
$stmt = $db->prepare('SELECT id FROM guru WHERE user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$guru_id = (int)$stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['akses_error'] = 'Permintaan tidak sah. Silakan muat ulang halaman.';
    } else {
        $pengajaran_id=(int)($_POST['pengajaran_id']??0); $pertemuan=(int)($_POST['pertemuan_ke']??0);
        $action=$_POST['action']??'ubah_status';
        $cek=$db->prepare('SELECT COUNT(*) FROM pengajaran WHERE id=? AND guru_id=?');
        $cek->execute([$pengajaran_id,$guru_id]);
        if(!$cek->fetchColumn()||$pertemuan<1||$pertemuan>20) $_SESSION['akses_error']='Pengajaran atau nomor pertemuan tidak valid.';
        elseif($action!=='ubah_status') $_SESSION['akses_error']='Aksi tidak dikenali.';
        else {
            $status=($_POST['status']??'')==='Dibuka'?'Dibuka':'Dikunci';
            $db->prepare("INSERT INTO akses_pertemuan(pengajaran_id,pertemuan_ke,status,dibuka_pada) VALUES(?,?,?,CASE WHEN ?='Dibuka' THEN NOW() ELSE NULL END) ON DUPLICATE KEY UPDATE status=VALUES(status),dibuka_pada=CASE WHEN VALUES(status)='Dibuka' THEN NOW() ELSE NULL END")->execute([$pengajaran_id,$pertemuan,$status,$status]);
            catat_log($_SESSION['user_id'],"$status akses pertemuan $pertemuan pada pengajaran ID $pengajaran_id");
            $_SESSION['akses_success']="Pertemuan $pertemuan berhasil ".($status==='Dibuka'?'dibuka untuk siswa.':'dikunci dari siswa.');
        }
    }
    header('Location: akses_pertemuan.php?pengajaran_id='.(int)($_POST['pengajaran_id']??0)); exit;
}
$stmt=$db->prepare('SELECT p.id,m.nama_mapel,k.nama_kelas,p.semester,p.tahun_ajaran FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.guru_id=? ORDER BY p.tahun_ajaran DESC,p.semester,m.nama_mapel,k.nama_kelas');
$stmt->execute([$guru_id]); $pengajaran=$stmt->fetchAll();
$ids=array_map(static fn($p)=>(int)$p['id'],$pengajaran); $dipilih=(int)($_GET['pengajaran_id']??($ids[0]??0));
if(!in_array($dipilih,$ids,true))$dipilih=$ids[0]??0;
$akses=[];
if($dipilih){
    $stmt=$db->prepare("SELECT pertemuan_ke,status FROM akses_pertemuan WHERE pengajaran_id=?");
    $stmt->execute([$dipilih]); foreach($stmt->fetchAll() as $a)$akses[(int)$a['pertemuan_ke']]=$a;
    $stmt=$db->prepare("SELECT pertemuan_ke,MAX(ada_materi) ada_materi,MAX(nama_materi) nama_materi,SUM(jumlah_tugas) jumlah_tugas FROM (SELECT pertemuan_ke,1 ada_materi,judul nama_materi,0 jumlah_tugas FROM materi WHERE pengajaran_id=? UNION ALL SELECT pertemuan_ke,0,NULL,COUNT(*) FROM tugas WHERE pengajaran_id=? GROUP BY pertemuan_ke) isi GROUP BY pertemuan_ke");
    $stmt->execute([$dipilih,$dipilih]);
    foreach($stmt->fetchAll() as $isi){$nomor=(int)$isi['pertemuan_ke'];$akses[$nomor]=array_merge($akses[$nomor]??['status'=>'Dikunci'],$isi);}
}
$success=$_SESSION['akses_success']??null;unset($_SESSION['akses_success']);$error=$_SESSION['akses_error']??null;unset($_SESSION['akses_error']);
require_once __DIR__ . '/../includes/sidebar.php';
?>
<style>#page-content-wrapper~#page-content-wrapper{display:none}</style>
<style>.access-shell{max-width:1100px;margin:0 auto}.access-card{border:0;border-radius:17px;box-shadow:0 6px 22px rgba(15,23,42,.06)}.meeting-access{border:1px solid #e4eaf2;border-radius:14px;padding:15px;background:#fff}.meeting-access.is-open{border-color:#bbf7d0;background:#f7fff9}.status-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#f1f5f9;color:#64748b;flex:0 0 42px}.is-open .status-icon{background:#dcfce7;color:#15803d}</style>
<div id="page-content-wrapper"><nav class="navbar navbar-light top-navbar px-3 px-md-4 py-3"><div><h5 class="mb-0 fw-bold"><i class="fa-solid fa-lock-open text-primary me-2"></i>Hak Akses Pertemuan</h5><small class="text-muted">Materi wajib diunduh siswa sebelum tugas pada pertemuan terbuka dapat diakses</small></div></nav>
<main class="container-fluid access-shell p-3 p-md-4"><?php if($success):?><div class="alert alert-success"><?=sanitize($success)?></div><?php endif;if($error):?><div class="alert alert-danger"><?=sanitize($error)?></div><?php endif?>
<section class="card access-card mb-3"><div class="card-body p-3 p-md-4"><form method="get" class="row g-2 align-items-end"><div class="col-md-9"><label class="form-label fw-semibold">Mata Pelajaran & Kelas</label><select name="pengajaran_id" class="form-select" required><?php foreach($pengajaran as $p):?><option value="<?=(int)$p['id']?>" <?=$dipilih===(int)$p['id']?'selected':''?>><?=sanitize($p['nama_mapel'])?> — <?=sanitize($p['nama_kelas'])?> (<?=sanitize($p['semester'].' '.$p['tahun_ajaran'])?>)</option><?php endforeach?></select></div><div class="col-md-3 d-grid"><button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Tampilkan</button></div></form></div></section>
<?php if(!$pengajaran): ?>
<div class="alert alert-info">Belum ada pengajaran yang ditugaskan kepada Anda.</div>
<?php else: ?>
<div class="alert alert-primary small"><i class="fa-solid fa-circle-info me-1"></i>Setelah akses pertemuan dibuka, siswa wajib mengunduh materi yang tercantum sebelum tugas dapat diakses.</div>
<div class="row g-3">
<?php for($i=1;$i<=20;$i++): $a=$akses[$i]??null;$buka=($a['status']??'Dikunci')==='Dibuka'; ?>
<div class="col-12 col-md-6 col-xl-4"><article class="meeting-access <?=$buka?'is-open':''?>">
<div class="d-flex gap-3"><span class="status-icon"><i class="fa-solid <?=$buka?'fa-lock-open':'fa-lock'?>"></i></span><div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><strong>Pertemuan <?=$i?></strong><span class="badge <?=$buka?'bg-success':'bg-secondary'?>"><?=$buka?'Terbuka':'Terkunci'?></span></div>
<small class="text-muted d-block"><?php if(!empty($a['ada_materi'])): ?>Materi tersedia: <strong><?=sanitize($a['nama_materi']??'Tanpa judul')?></strong><?php else: ?>Belum ada materi<?php endif ?> · <?=(int)($a['jumlah_tugas']??0)?> tugas</small>
</div></div>
<form method="post" class="d-grid mt-3" onsubmit="return confirm('<?=$buka?'Kunci pertemuan ini dari siswa?':'Buka pertemuan ini untuk seluruh siswa di kelas?'?>')"><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="ubah_status"><input type="hidden" name="pengajaran_id" value="<?=$dipilih?>"><input type="hidden" name="pertemuan_ke" value="<?=$i?>"><input type="hidden" name="status" value="<?=$buka?'Dikunci':'Dibuka'?>"><button class="btn btn-sm <?=$buka?'btn-outline-danger':'btn-success'?>"><i class="fa-solid <?=$buka?'fa-lock':'fa-lock-open'?> me-1"></i><?=$buka?'Kunci Akses':'Buka Akses'?></button></form>
</article></div>
<?php endfor ?>
</div>
<?php endif ?>
</main></div>
<?php if($dipilih): ?><div id="page-content-wrapper" class="pt-0"><section class="container-fluid access-shell px-3 px-md-4 pb-4"><div class="card access-card"><div class="card-body p-3 p-md-4"><h6 class="fw-bold mb-1"><i class="fa-solid fa-file-arrow-down text-primary me-2"></i>Syarat Akses Tugas</h6><p class="small text-muted">Tentukan apakah siswa wajib mengunduh materi sebelum tugas dapat dibuka.</p><form method="post" class="row g-2 align-items-end"><input type="hidden" name="csrf_token" value="<?=sanitize($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="ubah_prasyarat"><input type="hidden" name="pengajaran_id" value="<?=$dipilih?>"><div class="col-md-5"><label class="form-label fw-semibold">Pertemuan</label><select class="form-select" name="pertemuan_ke" id="pertemuanPrasyarat"><?php for($nomor=1;$nomor<=20;$nomor++):?><option value="<?=$nomor?>" data-wajib="<?=!empty($akses[$nomor]['wajib_unduh_materi'])?'1':'0'?>">Pertemuan <?=$nomor?></option><?php endfor?></select></div><div class="col-md-4"><div class="form-check form-switch py-2"><input class="form-check-input" type="checkbox" role="switch" name="wajib_unduh_materi" id="wajibUnduhMateri"><label class="form-check-label fw-semibold" for="wajibUnduhMateri">Wajib unduh materi</label></div></div><div class="col-md-3 d-grid"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Syarat</button></div></form></div></div></section></div><script>
document.addEventListener('DOMContentLoaded',function(){
    const select=document.getElementById('pertemuanPrasyarat'),toggle=document.getElementById('wajibUnduhMateri');
    if(!select||!toggle)return;
    const sinkron=()=>toggle.checked=select.options[select.selectedIndex].dataset.wajib==='1';
    select.addEventListener('change',sinkron);sinkron();
});
</script><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
