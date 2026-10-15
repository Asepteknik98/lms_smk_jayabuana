<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/penugasan_remedial.php';
check_access([2]); $db=Database::getInstance();
$q=$db->prepare('SELECT id FROM guru WHERE user_id=?');$q->execute([$_SESSION['user_id']]);$guru=(int)$q->fetchColumn();
$pid=(int)($_GET['pengajaran_id']??0);$tid=(int)($_GET['tugas_id']??0);
try {$data=rt_data($db,$guru,$pid,rt_schema_ready($db));$eligible=pr_eligible($data,$tid);}
catch(Throwable $e){http_response_code(403);exit('Tugas tidak tersedia untuk Anda.');}
$ready=pr_ready($db)&&rt_cancel_ready($db);$error='';
if(!isset($_SESSION['pr_token']))$_SESSION['pr_token']=bin2hex(random_bytes(24));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!$ready)throw new RuntimeException('Penugasan remedial belum diaktifkan. Hubungi admin.');
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
        if(($_POST['action']??'')==='buat'){
            foreach(['judul','instruksi','deadline','token'] as $key)if(!is_string($_POST[$key]??null))throw new RuntimeException('Format isian tidak valid.');
            if(!hash_equals($_SESSION['pr_token'],$_POST['token']))throw new RuntimeException('Form sudah diproses. Muat ulang halaman.');
            pr_create($db,$guru,(int)$_SESSION['user_id'],$pid,$tid,$_POST['judul'],$_POST['instruksi'],$_POST['deadline'],$_POST['token']);
            $_SESSION['pr_token']=bin2hex(random_bytes(24));$_SESSION['pr_success']='Tugas remedial diterbitkan dan tersedia di halaman tugas siswa peserta.';
        }elseif(($_POST['action']??'')==='nilai'){
            if(!is_string($_POST['nilai']??null))throw new RuntimeException('Nilai tidak valid.');
            // Parent task is checked separately so a forged assignment ID cannot cross this page context.
            $q=$db->prepare('SELECT id FROM penugasan_remedial WHERE id=? AND tugas_id=?');$q->execute([(int)($_POST['penugasan_id']??0),$tid]);
            if(!$q->fetchColumn())throw new RuntimeException('Penugasan tidak ditemukan.');
            pr_grade($db,$guru,(int)$_SESSION['user_id'],$pid,(int)$_POST['penugasan_id'],(int)($_POST['siswa_id']??0),$_POST['nilai']);
            $_SESSION['pr_success']='Nilai tersimpan pada riwayat remedial dan rekap tugas asal.';
        }else throw new RuntimeException('Aksi tidak valid.');
        header('Location: tugas_remedial.php?'.http_build_query(['pengajaran_id'=>$pid,'tugas_id'=>$tid]));exit;
    }catch(Throwable $e){error_log('Penugasan remedial: '.$e->getMessage());$error=$e instanceof PDOException?'Data gagal disimpan. Silakan muat ulang halaman.':$e->getMessage();}
}
$assignments=[];
if($ready){$q=$db->prepare('SELECT * FROM penugasan_remedial WHERE tugas_id=? ORDER BY id DESC');$q->execute([$tid]);$assignments=$q->fetchAll();}
$success=$_SESSION['pr_success']??'';unset($_SESSION['pr_success']);
require_once __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/tugas-remedial.css?v=1">
<main class="pr-page">
<a class="pr-back" href="tugas.php?rekap_pengajaran=<?= $pid ?>&amp;rekap_panel=remedial&amp;remedial_tugas_id=<?= $tid ?>#rekap-tugas"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Kembali ke Remedial / Undo</a>
<header class="pr-heading"><div class="pr-heading-icon"><i class="fa-solid fa-file-pen" aria-hidden="true"></i></div><div><h1>Buat Tugas Remedial</h1><p><?= sanitize($data['info']['nama_kelas']) ?> &middot; <?= sanitize($data['info']['nama_mapel']) ?></p></div></header>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<?php if(!$ready): ?><div class="alert alert-warning">Penugasan remedial belum diaktifkan. Hubungi admin untuk menjalankan migrasi penugasan remedial.</div><?php else: ?>
<div class="pr-layout">
<form method="post" class="pr-card">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="buat"><input type="hidden" name="token" value="<?= sanitize($_SESSION['pr_token']) ?>">
<div class="pr-card-heading"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><div><h2>Detail tugas</h2><p>Siapkan soal dan tentukan batas pengumpulan.</p></div></div>
<div class="pr-form-body">
<div class="pr-field"><label class="form-label" for="prJudul">Judul tugas remedial<span class="pr-required" aria-hidden="true">*</span></label><input id="prJudul" class="form-control" name="judul" maxlength="200" placeholder="Contoh: Remedial Algoritma Pemrograman" required></div>
<div class="pr-field"><label class="form-label" for="prInstruksi">Soal / instruksi<span class="pr-required" aria-hidden="true">*</span></label><textarea id="prInstruksi" class="form-control" name="instruksi" rows="6" maxlength="20000" placeholder="Tuliskan soal, petunjuk pengerjaan, dan ketentuan pengumpulan..." aria-describedby="prAnswerHint" required></textarea>
<div class="pr-answer-hint" id="prAnswerHint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Siswa mengirim jawaban teks atau tautan hasil pekerjaan. Anda juga dapat menyertakan tautan bahan tugas dalam instruksi.</p></div></div>
<div class="pr-field pr-deadline"><label class="form-label" for="prDeadline"><i class="fa-regular fa-calendar me-2 text-muted" aria-hidden="true"></i>Tenggat pengumpulan<span class="pr-required" aria-hidden="true">*</span></label><input id="prDeadline" type="datetime-local" class="form-control" name="deadline" aria-describedby="prDeadlineHint" required><p id="prDeadlineHint" class="pr-hint">Batas tanggal dan waktu siswa mengirim jawaban.</p></div>
</div>
<div class="pr-form-footer"><span><i class="fa-solid fa-users me-1" aria-hidden="true"></i>Hanya dikirim kepada peserta remedial.</span><button class="btn btn-primary" <?= !$eligible?'disabled':'' ?>><i class="fa-regular fa-paper-plane me-2" aria-hidden="true"></i>Terbitkan Tugas Remedial</button></div>
</form>
<aside class="pr-side" aria-label="Informasi peserta remedial">
<section class="pr-card pr-participants"><h2 class="pr-side-label"><i class="fa-solid fa-user-group" aria-hidden="true"></i>Peserta remedial</h2><div class="pr-participant-count"><strong><?= count($eligible) ?></strong><span>siswa terpilih otomatis</span></div><span class="pr-threshold">Nilai tugas di bawah 75</span><p class="pr-side-copy">Peserta mengikuti nilai pada tugas asal. Siswa yang belum dinilai tidak disertakan.</p>
<details><summary>Lihat daftar peserta</summary><ul class="pr-participant-list"><?php foreach($eligible as $s): ?><li><span class="pr-student-icon"><i class="fa-regular fa-user" aria-hidden="true"></i></span><span><?= sanitize($s['nama_lengkap']) ?></span></li><?php endforeach ?></ul><?php if(!$eligible): ?><p class="pr-hint">Tidak ada siswa yang perlu remedial pada tugas ini.</p><?php endif ?></details></section>
<section class="pr-card pr-delivery"><i class="fa-solid fa-bolt" aria-hidden="true"></i><h3>Langsung tersedia untuk siswa</h3><p>Setelah diterbitkan, tugas muncul di halaman tugas peserta. Nilai yang Anda berikan terhubung ke riwayat remedial tugas asal.</p></section>
</aside>
</div>
<section class="pr-published" aria-labelledby="prPublishedTitle">
<div class="pr-published-heading"><div><h2 id="prPublishedTitle">Tugas remedial diterbitkan</h2><p>Pantau jawaban dan berikan nilai kepada peserta.</p></div><span class="pr-count-badge"><?= count($assignments) ?> tugas</span></div>
<?php if(!$assignments): ?><div class="pr-card pr-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><h3>Belum ada tugas remedial</h3><p>Tugas yang Anda terbitkan akan tampil di sini.</p></div><?php endif ?>
<?php foreach($assignments as $assignment):
$q=$db->prepare('SELECT a.*,s.nama_lengkap,h.nilai_remedial,b.dibatalkan_pada FROM peserta_penugasan_remedial a JOIN siswa s ON s.id=a.siswa_id LEFT JOIN remedial_tugas h ON h.id=a.remedial_id LEFT JOIN remedial_tugas_batal b ON b.remedial_id=h.id WHERE a.penugasan_id=? ORDER BY s.nama_lengkap');$q->execute([$assignment['id']]);$recipients=$q->fetchAll(); ?>
<article class="pr-card pr-assignment"><div class="pr-assignment-top"><div class="pr-assignment-title"><h3><?= sanitize($assignment['judul']) ?></h3><span class="pr-threshold">Remedial</span></div><div class="pr-meta"><span><i class="fa-regular fa-clock me-1" aria-hidden="true"></i>Tenggat: <?= sanitize($assignment['deadline']) ?></span><span><i class="fa-solid fa-users me-1" aria-hidden="true"></i><?= count($recipients) ?> peserta</span></div><p class="pr-instructions"><?= sanitize($assignment['instruksi']) ?></p></div>
<div class="table-responsive"><table class="table"><caption class="visually-hidden">Jawaban dan nilai peserta <?= sanitize($assignment['judul']) ?></caption><thead><tr><th scope="col">Siswa</th><th scope="col">Jawaban</th><th scope="col">Nilai remedial</th></tr></thead><tbody>
<?php foreach($recipients as $s): ?><tr><td class="pr-student-name"><?= sanitize($s['nama_lengkap']) ?></td><td><?php if($s['dikumpulkan_pada']): ?><small class="pr-submitted"><?= sanitize($s['dikumpulkan_pada']) ?></small><details><summary>Lihat jawaban</summary><div class="pr-answer"><?= sanitize($s['jawaban']) ?></div></details><?php else: ?><span class="pr-status pr-status-pending"><i class="fa-regular fa-clock" aria-hidden="true"></i>Belum mengumpulkan</span><?php endif ?></td><td>
<?php if($s['remedial_id']): ?><span class="pr-status <?= $s['dibatalkan_pada']?'pr-status-cancelled':'pr-status-success' ?>"><?= sanitize((string)$s['nilai_remedial']) ?><?= $s['dibatalkan_pada']?' &middot; Dibatalkan melalui Undo':'' ?></span>
<?php elseif($s['dikumpulkan_pada']): ?><form method="post" class="pr-grade-form"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="nilai"><input type="hidden" name="penugasan_id" value="<?= (int)$assignment['id'] ?>"><input type="hidden" name="siswa_id" value="<?= (int)$s['siswa_id'] ?>"><input type="number" name="nilai" min="0" max="100" step="0.01" class="form-control" placeholder="0–100" aria-label="Nilai remedial <?= sanitize($s['nama_lengkap']) ?>" required><button class="btn btn-primary">Simpan</button></form><?php else: ?><span class="text-muted">&mdash;</span><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div></article><?php endforeach ?></section>
<?php endif ?></main>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
