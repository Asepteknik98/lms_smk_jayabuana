<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/penugasan_remedial.php';
check_access([3]);
$file=[];
try {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST' || !is_string($_POST['csrf_token']??null) || !verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
    $db=Database::getInstance();$q=$db->prepare('SELECT id FROM siswa WHERE user_id=?');$q->execute([$_SESSION['user_id']]);$sid=(int)$q->fetchColumn();
    $id=(int)($_POST['penugasan_id']??0);$task=null;
    foreach(pr_student_tasks($db,$sid) as $candidate)if((int)$candidate['id']===$id)$task=$candidate;
    if(!$task||$task['dikumpulkan_pada']||strtotime($task['deadline'])<time())throw new RuntimeException('Tugas tidak tersedia untuk pengumpulan.');
    $answer=pr_answer($task,$_POST);
    if(($task['jenis']??'teks')==='portofolio'){
        $file=pr_upload($_FILES['file_portofolio']??[]);
        if($answer==='')$answer='Portofolio: '.$file['nama_file'];
    }
    pr_submit($db,(int)$_SESSION['user_id'],$id,$answer,$file);
    $_SESSION['pr_student_success']='Jawaban remedial berhasil dikirim. Menunggu penilaian guru.';
}catch(Throwable $e){if($file)@unlink(__DIR__.'/../storage/remedial/'.$file['file_jawaban']);error_log('Jawaban remedial: '.$e->getMessage());$_SESSION['pr_student_error']=$e instanceof PDOException?'Jawaban belum tersimpan. Silakan coba kembali.':$e->getMessage();}
header('Location: tugas.php#tugas-remedial');exit;
