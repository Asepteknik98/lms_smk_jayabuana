<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/penugasan_remedial.php';
check_access([3]);
try {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST' || !is_string($_POST['csrf_token']??null) || !verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
    if(!is_string($_POST['jawaban']??null))throw new RuntimeException('Jawaban tidak valid.');
    pr_submit(Database::getInstance(),(int)$_SESSION['user_id'],(int)($_POST['penugasan_id']??0),$_POST['jawaban']);
    $_SESSION['pr_student_success']='Jawaban remedial berhasil dikirim. Menunggu penilaian guru.';
}catch(Throwable $e){error_log('Jawaban remedial: '.$e->getMessage());$_SESSION['pr_student_error']=$e instanceof PDOException?'Jawaban belum tersimpan. Silakan coba kembali.':$e->getMessage();}
header('Location: tugas.php#tugas-remedial');exit;
