<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
check_access([2]);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Permintaan tidak sah.'); }
$db=Database::getInstance();
$stmt=$db->prepare('SELECT id FROM guru WHERE user_id=?');$stmt->execute([$_SESSION['user_id']]);$guru_id=(int)$stmt->fetchColumn();
$pengajaran_id=(int)($_POST['pengajaran_id']??0);$action=$_POST['action']??'';
$stmt=$db->prepare('SELECT id FROM pengajaran WHERE id=? AND guru_id=?');$stmt->execute([$pengajaran_id,$guru_id]);
if(!$stmt->fetchColumn()){http_response_code(403);exit('Pengajaran bukan milik Anda.');}
$back='rekap_nilai.php?pengajaran_id='.$pengajaran_id;
try {
 if($action==='save_period'){
   require_once __DIR__.'/../includes/rekap_akademik.php';
   $jenis=is_string($_POST['jenis']??null)?$_POST['jenis']:'';
   $back.='&jenis_capaian='.ra_jenis($jenis).'#capaianPenilaian';
   ra_save($db,$guru_id,$pengajaran_id,(int)($_POST['siswa_id']??0),$jenis,$_POST['nilai_periode']??'',$_POST['deskripsi']??'',$_POST['saran']??'');
   $_SESSION['flash_success']='Nilai, deskripsi capaian, dan saran '.$jenis.' berhasil disimpan dan tersedia bagi admin.';
 } elseif(in_array($action,['add','update'],true)){
   $id=(int)($_POST['id']??0);$nama=trim($_POST['nama_komponen']??'');$bobot=(float)($_POST['bobot']??0);
   $namaDiizinkan=['Ulangan Harian','Tugas Harian','Kehadiran','UTS','UAS','Ujian Praktik'];
   if(!in_array($nama,$namaDiizinkan,true)||$bobot<=0||$bobot>100)throw new RuntimeException('Nama dan bobot komponen tidak valid.');
   $stmt=$db->prepare('SELECT id FROM komponen_penilaian WHERE pengajaran_id=? AND nama_komponen=? AND id<>?');
   $stmt->execute([$pengajaran_id,$nama,$action==='update'?$id:0]);
   if($stmt->fetchColumn())throw new RuntimeException('Komponen '.$nama.' sudah ditambahkan.');
   $sql='SELECT COALESCE(SUM(bobot),0) FROM komponen_penilaian WHERE pengajaran_id=?'.($action==='update'?' AND id<>?':'');
   $stmt=$db->prepare($sql);$stmt->execute($action==='update'?[$pengajaran_id,$id]:[$pengajaran_id]);
   if((float)$stmt->fetchColumn()+$bobot>100.0001)throw new RuntimeException('Total bobot tidak boleh melebihi 100%.');
   if($action==='add'){$stmt=$db->prepare('INSERT INTO komponen_penilaian(pengajaran_id,nama_komponen,bobot,urutan) VALUES(?,?,?,(SELECT COALESCE(MAX(k.urutan),0)+1 FROM komponen_penilaian k WHERE k.pengajaran_id=?))');$stmt->execute([$pengajaran_id,$nama,$bobot,$pengajaran_id]);}
   else{$stmt=$db->prepare('SELECT id FROM komponen_penilaian WHERE id=? AND pengajaran_id=?');$stmt->execute([$id,$pengajaran_id]);if(!$stmt->fetchColumn())throw new RuntimeException('Komponen tidak ditemukan.');$stmt=$db->prepare('UPDATE komponen_penilaian SET nama_komponen=?,bobot=? WHERE id=? AND pengajaran_id=?');$stmt->execute([$nama,$bobot,$id,$pengajaran_id]);}
   $_SESSION['flash_success']='Komponen penilaian berhasil disimpan.';
 } elseif($action==='delete'){
   $stmt=$db->prepare('DELETE FROM komponen_penilaian WHERE id=? AND pengajaran_id=?');$stmt->execute([(int)($_POST['id']??0),$pengajaran_id]);
   $_SESSION['flash_success']='Komponen beserta nilainya berhasil dihapus.';
 } elseif($action==='save_settings'){
   $kkm=(float)($_POST['kkm']??0);if($kkm<1||$kkm>100)throw new RuntimeException('KKM harus berada di antara 1 sampai 100.');
   $stmt=$db->prepare('UPDATE pengajaran SET kkm=? WHERE id=? AND guru_id=?');$stmt->execute([$kkm,$pengajaran_id,$guru_id]);
   $_SESSION['flash_success']='Pengaturan KKM berhasil disimpan.';
 } elseif($action==='save_note'){
   $siswa_id=(int)($_POST['siswa_id']??0);$catatan=trim($_POST['catatan']??'');
   $stmt=$db->prepare('SELECT COUNT(*) FROM siswa s JOIN pengajaran p ON p.kelas_id=s.kelas_id WHERE s.id=? AND p.id=? AND p.guru_id=?');$stmt->execute([$siswa_id,$pengajaran_id,$guru_id]);
   if(!$stmt->fetchColumn())throw new RuntimeException('Siswa tidak ditemukan pada kelas ini.');
   if($catatan===''){$db->prepare('DELETE FROM catatan_siswa_pengajaran WHERE pengajaran_id=? AND siswa_id=?')->execute([$pengajaran_id,$siswa_id]);}
   else{$stmt=$db->prepare('INSERT INTO catatan_siswa_pengajaran(pengajaran_id,siswa_id,catatan) VALUES(?,?,?) ON DUPLICATE KEY UPDATE catatan=VALUES(catatan)');$stmt->execute([$pengajaran_id,$siswa_id,$catatan]);}
   $_SESSION['flash_success']='Catatan siswa berhasil disimpan.';
 } elseif($action==='save_scores'){
   require_once __DIR__.'/../includes/penilaian_override.php';
   po_save($db,$guru_id,$pengajaran_id,$_POST['nilai']??[],$_POST['sumber']??[]);
   $_SESSION['flash_success']='Nilai dan pilihan otomatis/manual berhasil disimpan.';
 } else throw new RuntimeException('Aksi tidak valid.');
} catch(Throwable $e){if($db->inTransaction())$db->rollBack();$_SESSION['flash_error']=$e->getMessage();}
redirect($back);
