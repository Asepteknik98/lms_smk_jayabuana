<?php
if(!isset($db,$ujian,$siswa_id,$ujian_id,$uhSettings)){http_response_code(404);exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $savedPath=null;
    try{
        if(!verify_csrf($_POST['csrf_token']??''))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
        $file=$_FILES['jawaban']??[];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>10*1024*1024)throw new RuntimeException('Unggah berkas jawaban maksimal 10 MB.');
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        $allowed=['pdf'=>['application/pdf'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'docx'=>['application/zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document']];
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!isset($allowed[$ext])||!in_array($mime,$allowed[$ext],true))throw new RuntimeException('Format jawaban harus PDF, JPG, PNG atau DOCX.');
        $db->beginTransaction();
        $eligible=uh_eligible_sql('u.id','s.id');
        $q=$db->prepare("SELECT u.id FROM ujian u JOIN pengajaran p ON p.id=u.pengajaran_id JOIN siswa s ON s.kelas_id=p.kelas_id JOIN ujian_harian_pengaturan h ON h.ujian_id=u.id AND h.metode='berkas' WHERE u.id=? AND s.id=? AND NOW() BETWEEN u.waktu_mulai AND u.waktu_selesai AND $eligible FOR UPDATE");$q->execute([$ujian_id,$siswa_id]);
        if(!$q->fetchColumn())throw new RuntimeException('Ulangan tidak tersedia atau tenggat telah berakhir.');
        $q=$db->prepare('SELECT id FROM sesi_ujian WHERE ujian_id=? AND siswa_id=?');$q->execute([$ujian_id,$siswa_id]);
        if($q->fetchColumn())throw new RuntimeException('Jawaban sudah dikirim dan tidak dapat dikirim ulang.');
        $folder=__DIR__.'/../storage/ujian';
        if(!is_dir($folder)&&!mkdir($folder,0755,true)&&!is_dir($folder))throw new RuntimeException('Penyimpanan tidak tersedia.');
        $name=bin2hex(random_bytes(20)).'.'.$ext;$savedPath=$folder.'/'.$name;
        if(!move_uploaded_file($file['tmp_name'],$savedPath))throw new RuntimeException('Berkas gagal disimpan.');
        $db->prepare("INSERT INTO sesi_ujian(ujian_id,siswa_id,waktu_mulai,waktu_selesai,status) VALUES(?,?,NOW(),NOW(),'Selesai')")->execute([$ujian_id,$siswa_id]);
        $db->prepare('INSERT INTO ujian_harian_berkas(ujian_id,siswa_id,nama_file,dikumpulkan_pada) VALUES(?,?,?,NOW())')->execute([$ujian_id,$siswa_id,$name]);
        $db->commit();header('Location: ujian.php?status=selesai');exit;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($savedPath&&is_file($savedPath))unlink($savedPath);$error=$e instanceof RuntimeException?$e->getMessage():'Jawaban gagal disimpan. Coba kembali.';}
}
$q=$db->prepare('SELECT b.*,n.nilai_total FROM ujian_harian_berkas b LEFT JOIN nilai_ujian n ON n.ujian_id=b.ujian_id AND n.siswa_id=b.siswa_id WHERE b.ujian_id=? AND b.siswa_id=?');$q->execute([$ujian_id,$siswa_id]);$submission=$q->fetch();
require __DIR__.'/header.php';require __DIR__.'/sidebar.php';
?>
<div id="page-content-wrapper"><nav class="navbar top-navbar px-4 py-3"><h1 class="h5 mb-0">Kumpulkan Jawaban Ulangan Harian</h1></nav><main class="container p-3 p-md-4" style="max-width:900px">
<a href="ujian.php" class="btn btn-outline-secondary mb-3">Kembali ke daftar</a>
<section class="card border-0 shadow-sm"><div class="card-body p-4"><span class="badge bg-primary mb-2">UH <?= (int)$uhSettings['nomor'] ?></span><h2 class="h5"><?= sanitize($ujian['nama_ujian']) ?></h2><p class="small text-muted">Tenggat: <?= sanitize($ujian['waktu_selesai']) ?>. Pengumpulan berkas mengikuti tenggat, tanpa timer CBT.</p>
<div class="bg-light rounded p-3 mb-3"><?= nl2br(sanitize($uhSettings['instruksi'])) ?></div>
<?php if($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif ?>
<?php if($submission): ?><div class="alert alert-success">Jawaban sudah dikirim. <?= $submission['nilai_total']===null?'Menunggu penilaian guru.':'Nilai: '.number_format((float)$submission['nilai_total'],2) ?></div><a class="btn btn-outline-primary" href="../guru/ujian_berkas.php?ujian_id=<?= $ujian_id ?>&amp;siswa_id=<?= $siswa_id ?>">Unduh jawaban</a><?php if($submission['catatan_guru']): ?><p class="mt-3">Catatan guru: <?= nl2br(sanitize($submission['catatan_guru'])) ?></p><?php endif ?>
<?php else: ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><label for="uhJawaban" class="form-label">Berkas jawaban (PDF, JPG, PNG, DOCX; maksimal 10 MB)</label><input id="uhJawaban" type="file" name="jawaban" class="form-control mb-3" accept=".pdf,.jpg,.jpeg,.png,.docx" required><p class="small text-muted">Periksa berkas sebelum mengirim. Jawaban hanya dapat dikirim satu kali.</p><button class="btn btn-primary">Kirim Jawaban</button></form><?php endif ?>
</div></section></main></div>
<?php require __DIR__.'/footer.php'; ?>
