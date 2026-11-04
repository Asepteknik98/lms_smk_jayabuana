<?php
require_once __DIR__.'/../config/session.php';require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../config/helper.php';require_once __DIR__.'/../config/auth.php';check_access([3]);
$db=Database::getInstance();
$stmt=$db->prepare("SELECT s.id,s.nama_lengkap,s.nis,s.nisn,k.nama_kelas,k.tingkat FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.user_id=?");$stmt->execute([$_SESSION['user_id']]);$siswa=$stmt->fetch();if(!$siswa){http_response_code(404);exit('Data siswa tidak ditemukan.');}
require_once __DIR__.'/../includes/kak_klaim.php';
$klaim_ready = kak_claim_ready($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf($_POST['csrf_token'])) throw new InvalidArgumentException('Sesi keamanan berakhir. Muat ulang halaman sebelum mengirim klaim.');
        if (!$klaim_ready) throw new InvalidArgumentException('Klaim aktivitas belum tersedia. Silakan hubungi sekolah.');
        kak_claim_submit($db,(int)$_SESSION['user_id'],$_POST,kak_claim_upload($_FILES));
        $_SESSION['kak_claim_success'] = 'Bukti berhasil dikirim. Klaim Anda menunggu verifikasi guru/admin.';
    } catch (Throwable $e) {
        error_log('Klaim aktivitas siswa: '.$e->getMessage());
        $_SESSION['kak_claim_error'] = $e instanceof InvalidArgumentException || $e instanceof DomainException ? $e->getMessage() : 'Klaim belum berhasil dikirim. Silakan coba lagi.';
    }
    redirect('kak.php#klaimAktivitas');
}
$klaim_success = $_SESSION['kak_claim_success'] ?? '';
$klaim_error = $_SESSION['kak_claim_error'] ?? '';
unset($_SESSION['kak_claim_success'],$_SESSION['kak_claim_error']);
$acara_klaim = $klaim_ready ? kak_claim_events($db,(int)$_SESSION['user_id']) : [];
$jumlah_aktivitas=count($acara_klaim);
$jumlah_disetujui=count(array_filter($acara_klaim,static fn($a)=>$a['status_klaim']==='Disetujui'));
?>
<?php require_once __DIR__.'/../includes/header.php';?><?php require_once __DIR__.'/../includes/sidebar.php';?>
<link rel="stylesheet" href="../assets/css/kak-klaim.css?v=<?=filemtime(__DIR__.'/../assets/css/kak-klaim.css')?>">

<style>.kak-page{background:#f5f7fb;min-width:0}.kak-main{max-width:1100px;margin:auto}.kak-card{border:0;border-radius:18px;box-shadow:0 6px 22px rgba(15,23,42,.06)}.kak-summary{color:#fff;background:linear-gradient(125deg,#172554,#1d4ed8 65%,#3b82f6)}.kak-number{font-size:2.15rem;line-height:1}@media(max-width:575.98px){.kak-main{padding:13px!important}.kak-card{border-radius:15px}}</style>
<div id="page-content-wrapper" class="kak-page"><nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-medal text-warning me-2"></i>Kredit Aktivitas Siswa</h5><small class="text-muted"><?=sanitize(($siswa['nama_kelas']?:'Kelas belum ditentukan').' · Tingkat '.($siswa['tingkat']?:'-'))?></small></div></nav><main class="kak-main p-3 p-md-4">
<section class="card kak-card kak-summary mb-3"><div class="card-body p-4">
<div class="kak-summary-copy"><small class="opacity-75">Persetujuan Aktivitas</small><div class="kak-summary-count"><strong><?=$jumlah_disetujui?> dari <?=$jumlah_aktivitas?></strong><span>aktivitas disetujui</span></div></div>
<div class="kak-summary-action"><a href="#klaimAktivitas" class="kak-open-claims"><i class="fa-solid fa-camera" aria-hidden="true"></i>Klaim Aktivitas<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><small>Unggah bukti dan pantau status klaimmu.</small></div>
</div></section>
<?php require __DIR__.'/../includes/kak_klaim_siswa_view.php'; ?>
</main></div><?php require_once __DIR__.'/../includes/footer.php';?>
