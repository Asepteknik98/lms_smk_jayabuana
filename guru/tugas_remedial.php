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
<div class="container-fluid p-4">
<a class="btn btn-outline-secondary mb-3" href="tugas.php?rekap_pengajaran=<?= $pid ?>&amp;rekap_panel=remedial&amp;remedial_tugas_id=<?= $tid ?>#rekap-tugas">Kembali ke Remedial / Undo</a>
<h4>Buat Tugas Remedial</h4><p class="text-muted"><?= sanitize($data['info']['nama_kelas'].' · '.$data['info']['nama_mapel']) ?></p>
<?php if($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif ?>
<?php if($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif ?>
<?php if(!$ready): ?><div class="alert alert-warning">Penugasan remedial belum diaktifkan. Hubungi admin untuk menjalankan migrasi penugasan remedial.</div><?php else: ?>
<form method="post" class="card card-body mb-4">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="buat"><input type="hidden" name="token" value="<?= sanitize($_SESSION['pr_token']) ?>">
<p>Peserta otomatis: <strong><?= count($eligible) ?> siswa dengan nilai di bawah 75</strong>. Siswa yang belum dinilai tidak disertakan. Tugas langsung tampil di halaman tugas peserta.</p>
<details class="mb-3"><summary>Lihat peserta</summary><?php foreach($eligible as $s): ?><div><?= sanitize($s['nama_lengkap']) ?></div><?php endforeach ?></details>
<label class="form-label" for="prJudul">Judul tugas remedial</label><input id="prJudul" class="form-control mb-3" name="judul" maxlength="200" required>
<label class="form-label" for="prInstruksi">Soal / instruksi</label><textarea id="prInstruksi" class="form-control mb-3" name="instruksi" rows="5" maxlength="20000" required></textarea>
<div class="form-text mb-3">Siswa mengirim jawaban teks. Instruksi dapat menyertakan tautan bahan tugas; siswa dapat menuliskan tautan hasil pekerjaan dalam jawaban.</div>
<label class="form-label" for="prDeadline">Tenggat</label><input id="prDeadline" type="datetime-local" class="form-control mb-3" name="deadline" required>
<button class="btn btn-warning align-self-start" <?= !$eligible?'disabled':'' ?>>Terbitkan Tugas Remedial</button></form>
<h5>Tugas Remedial yang Diterbitkan</h5>
<?php if(!$assignments): ?><p class="text-muted">Belum ada tugas remedial.</p><?php endif ?>
<?php foreach($assignments as $assignment):
$q=$db->prepare('SELECT a.*,s.nama_lengkap,h.nilai_remedial,b.dibatalkan_pada FROM peserta_penugasan_remedial a JOIN siswa s ON s.id=a.siswa_id LEFT JOIN remedial_tugas h ON h.id=a.remedial_id LEFT JOIN remedial_tugas_batal b ON b.remedial_id=h.id WHERE a.penugasan_id=? ORDER BY s.nama_lengkap');$q->execute([$assignment['id']]);$recipients=$q->fetchAll(); ?>
<div class="card card-body mb-3"><h5><?= sanitize($assignment['judul']) ?></h5><small class="text-muted">Tenggat: <?= sanitize($assignment['deadline']) ?></small><p style="white-space:pre-wrap"><?= sanitize($assignment['instruksi']) ?></p>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Siswa</th><th>Jawaban</th><th>Nilai Remedial</th></tr></thead><tbody>
<?php foreach($recipients as $s): ?><tr><td><?= sanitize($s['nama_lengkap']) ?></td><td><?php if($s['dikumpulkan_pada']): ?><small><?= sanitize($s['dikumpulkan_pada']) ?></small><details><summary>Lihat jawaban</summary><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?= sanitize($s['jawaban']) ?></div></details><?php else: ?>Belum mengumpulkan<?php endif ?></td><td>
<?php if($s['remedial_id']): ?><?= sanitize((string)$s['nilai_remedial']) ?><?= $s['dibatalkan_pada']?' (Dibatalkan melalui Undo)':'' ?>
<?php elseif($s['dikumpulkan_pada']): ?><form method="post" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="nilai"><input type="hidden" name="penugasan_id" value="<?= (int)$assignment['id'] ?>"><input type="hidden" name="siswa_id" value="<?= (int)$s['siswa_id'] ?>"><input type="number" name="nilai" min="0" max="100" step="0.01" class="form-control" aria-label="Nilai remedial <?= sanitize($s['nama_lengkap']) ?>" required><button class="btn btn-primary">Simpan</button></form><?php else: ?>—<?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div></div><?php endforeach ?>
<?php endif ?></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
