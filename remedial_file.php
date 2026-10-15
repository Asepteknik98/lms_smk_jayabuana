<?php
require_once __DIR__.'/config/session.php';
require_once __DIR__.'/config/auth.php';
require_once __DIR__.'/config/database.php';
check_access([2,3]);$db=Database::getInstance();
$q=$db->prepare('SELECT a.file_jawaban,a.nama_file,s.user_id AS siswa_user,g.user_id AS guru_user,s.kelas_id AS siswa_kelas,p.kelas_id FROM peserta_penugasan_remedial a JOIN penugasan_remedial r ON r.id=a.penugasan_id JOIN tugas t ON t.id=r.tugas_id JOIN pengajaran p ON p.id=t.pengajaran_id JOIN guru g ON g.id=p.guru_id JOIN siswa s ON s.id=a.siswa_id WHERE a.penugasan_id=? AND a.siswa_id=?');
$q->execute([(int)($_GET['id']??0),(int)($_GET['siswa']??0)]);$row=$q->fetch();
$role=(int)$_SESSION['role_id'];$user=(int)$_SESSION['user_id'];
if(!$row||!$row['file_jawaban']||($role===2&&(int)$row['guru_user']!==$user)||($role===3&&((int)$row['siswa_user']!==$user||(int)$row['siswa_kelas']!==(int)$row['kelas_id']))){http_response_code(403);exit('File tidak tersedia untuk Anda.');}
$name=$row['file_jawaban'];
if(!preg_match('/^[a-f0-9]{48}\.(pdf|docx|jpg|jpeg|png|webp)$/D',$name)){http_response_code(404);exit;}
$path=__DIR__.'/storage/remedial/'.$name;if(!is_file($path)){http_response_code(404);exit('File tidak ditemukan.');}
header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
header("Content-Disposition: attachment; filename=\"portofolio.".pathinfo($name,PATHINFO_EXTENSION)."\"; filename*=UTF-8''".rawurlencode($row['nama_file']));
header('Content-Length: '.filesize($path));readfile($path);exit;
