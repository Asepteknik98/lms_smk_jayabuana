<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../includes/rekap_tugas_module.php';
check_access([2]);
$db=Database::getInstance();
$q=$db->prepare('SELECT id FROM guru WHERE user_id=?');$q->execute([$_SESSION['user_id']]);$guru=(int)$q->fetchColumn();
if(!$guru){http_response_code(403);exit('Data guru tidak ditemukan.');}
$schema=rt_schema_ready($db);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $pid=(int)($_POST['pengajaran_id']??0);
    try{
        if(!is_string($_POST['csrf_token']??null) || !verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
        if(!$schema)throw new RuntimeException('Pencatatan status belum tersedia. Hubungi admin.');
        foreach(['status','nilai','catatan'] as $key)if(!is_string($_POST[$key]??null))throw new RuntimeException('Format isian tidak valid.');
        rt_save($db,$guru,(int)$_SESSION['user_id'],$pid,(int)($_POST['siswa_id']??0),(int)($_POST['tugas_id']??0),$_POST['status'],$_POST['nilai'],$_POST['catatan']);
        $_SESSION['rekap_tugas_success']='Status dan nilai tugas berhasil disimpan.';
    }catch(Throwable $e){
        error_log('Rekap tugas: '.$e->getMessage());
        $_SESSION['rekap_tugas_error']=$e instanceof PDOException?'Data gagal disimpan. Tidak ada perubahan yang diterapkan.':$e->getMessage();
    }
    header('Location: rekap_tugas.php?'.http_build_query(['pengajaran_id'=>$pid]));exit;
}
$q=$db->prepare('SELECT p.id,p.kelas_id,p.semester,p.tahun_ajaran,m.nama_mapel,k.nama_kelas FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.guru_id=? ORDER BY k.nama_kelas,p.tahun_ajaran DESC,p.semester,m.nama_mapel');
$q->execute([$guru]);$assignments=$q->fetchAll();
$pid=(int)($_GET['pengajaran_id']??($assignments[0]['id']??0));
$data=['info'=>null,'tasks'=>[],'students'=>[]];
if($pid){
    try{$data=rt_data($db,$guru,$pid,$schema);}
    catch(PDOException $e){error_log('Baca rekap tugas: '.$e->getMessage());http_response_code(500);exit('Rekap tugas belum dapat dimuat. Hubungi admin.');}
    catch(RuntimeException $e){http_response_code(403);exit('Pengajaran tidak ditemukan atau bukan milik Anda.');}
}
$info=$data['info'];$tasks=$data['tasks'];$students=$data['students'];
$success=$_SESSION['rekap_tugas_success']??'';$error=$_SESSION['rekap_tugas_error']??'';
unset($_SESSION['rekap_tugas_success'],$_SESSION['rekap_tugas_error']);

if(($_GET['format']??'')==='csv' && $info){
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="Rekap_Tugas_'.$pid.'_'.date('Ymd').'.csv"');header('Cache-Control: no-store');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBFsep=;\r\n");
    $safe=static fn($v)=>is_string($v)&&preg_match('/^[=+\-@\t\r\n]/',$v)?"'".$v:$v;
    fputcsv($out,array_map($safe,[$info['nama_mapel'],$info['nama_kelas'],$info['tahun_ajaran'],$info['semester']]),';');
    $headers=['No','NIS','NISN','Nama Siswa'];foreach($tasks as $task){$label='P'.$task['pertemuan_ke'].' '.$task['judul'];$headers[]=$label.' - Nilai';$headers[]=$label.' - Status';}
    $headers=array_merge($headers,['Rata-rata tugas selesai','Jumlah tugas dihitung','Tugas selesai belum dinilai']);fputcsv($out,array_map($safe,$headers),';');
    foreach($students as $i=>$s){$row=[$i+1,(string)($s['nis']??''),(string)($s['nisn']??''),$s['nama_lengkap']];foreach($tasks as $task){$c=$s['cells'][$task['id']];$row[]=$c['nilai']??'';$row[]=$c['label'];}$row[]=$s['rata_rata']??'';$row[]=$s['jumlah_dihitung'];$row[]=$s['belum_dinilai'];fputcsv($out,array_map($safe,$row),';');}
    fclose($out);exit;
}
$closed=count(array_filter($tasks,static fn($t)=>(bool)$t['selesai']));
$pending=array_sum(array_column($students,'belum_dinilai'));
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/rekap-tugas.css">
<div id="page-content-wrapper" class="task-recap-page">
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-table-list text-primary me-2"></i>Rekap Tugas</h5><small class="text-muted">Riwayat pengerjaan, nilai, dan rata-rata siswa</small></div><a href="tugas.php" class="btn btn-sm btn-outline-secondary">Daftar Tugas</a></nav>
<main class="container-fluid p-3 p-md-4">
<?php if($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if(!$schema): ?><div class="alert alert-warning">Rekap dapat dilihat. Input status belum tersedia; hubungi admin untuk mengaktifkannya.</div><?php endif ?>
<section class="card recap-card mb-3"><div class="card-body"><form method="get" class="row g-3 align-items-end">
<div class="col-md-8"><label for="rekapPengajaran" class="form-label fw-semibold">Kelas dan mata pelajaran</label><select name="pengajaran_id" id="rekapPengajaran" class="form-select" onchange="this.form.submit()" <?= !$assignments?'disabled':'' ?>><?php foreach($assignments as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $pid===(int)$p['id']?'selected':'' ?>><?= sanitize($p['nama_kelas'].' · '.$p['nama_mapel'].' · '.$p['tahun_ajaran'].' / '.$p['semester']) ?></option><?php endforeach ?></select></div>
<div class="col-md-4"><button class="btn btn-primary" <?= !$assignments?'disabled':'' ?>>Tampilkan Rekap</button></div></form></div></section>
<?php if(!$info): ?><div class="alert alert-info">Belum ada pengajaran yang ditugaskan kepada Anda.</div><?php else: ?>
<div class="row g-3 mb-3"><?php foreach([[count($students),'Siswa'],[count($tasks),'Total Tugas'],[$closed,'Tugas Selesai'],[$pending,'Menunggu Penilaian']] as [$value,$label]): ?><div class="col-6 col-lg-3"><div class="card recap-card"><div class="card-body"><strong class="fs-4 text-primary"><?= (int)$value ?></strong><div class="small text-muted"><?= $label ?></div></div></div><?php endforeach ?></div>
<section class="card recap-card"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h6 class="fw-bold mb-1"><?= sanitize($info['nama_mapel'].' · '.$info['nama_kelas']) ?></h6><small class="text-muted"><?= sanitize($info['tahun_ajaran'].' / '.$info['semester']) ?></small></div><div class="d-flex gap-2 no-print"><a class="btn btn-sm btn-outline-success" href="?<?= sanitize(http_build_query(['pengajaran_id'=>$pid,'format'=>'csv'])) ?>"><i class="fa-solid fa-file-csv me-1"></i>Unduh CSV</a><button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Cetak / PDF</button></div></div>
<div class="alert alert-light border small">Setelah tenggat, tugas yang tidak dikerjakan bernilai <strong>0</strong> dan masuk rata-rata. Tugas masih terbuka dan tugas <strong>Belum Dinilai</strong> belum dihitung. Rata-rata bertanda “sementara” masih menunggu penilaian. Klik sel tugas untuk menginput status atau nilai.</div>
<?php if(!$tasks): ?><p class="text-muted text-center py-4">Belum ada tugas pada pengajaran ini.</p><?php else: ?>
<div class="mb-3 no-print"><label class="visually-hidden" for="cariSiswaRekap">Cari siswa</label><input class="form-control recap-search" id="cariSiswaRekap" type="search" placeholder="Cari nama, NIS, atau NISN siswa…"></div>
<div class="table-responsive recap-table-wrap"><table class="table table-bordered align-middle text-center recap-table"><thead class="table-light"><tr><th>No</th><th class="recap-sticky-name text-start">Siswa</th>
<?php foreach($tasks as $t): ?><th class="recap-task-heading"><span class="badge bg-primary-subtle text-primary">P<?= (int)$t['pertemuan_ke'] ?></span><div class="mt-1"><?= sanitize($t['judul']) ?></div><small class="fw-normal text-muted d-block"><?= sanitize(ucfirst($t['metode_pengumpulan'])) ?> · <?= date('d/m/Y H:i',strtotime($t['deadline'])) ?></small><span class="badge <?= $t['selesai']?'bg-secondary':'bg-success' ?>"><?= $t['selesai']?'Selesai':'Terbuka' ?></span></th><?php endforeach ?>
<th>Rata-rata</th><th>Belum Dinilai</th><th>Tidak Mengerjakan</th></tr></thead><tbody>
<?php foreach($students as $i=>$s): ?><tr class="recap-student-row" data-search="<?= sanitize(mb_strtolower($s['nama_lengkap'].' '.$s['nis'].' '.$s['nisn'],'UTF-8')) ?>"><td><?= $i+1 ?></td><td class="recap-sticky-name text-start"><strong><?= sanitize($s['nama_lengkap']) ?></strong><small class="d-block text-muted">NIS <?= sanitize((string)($s['nis']??'-')) ?><br>NISN <?= sanitize((string)($s['nisn']??'-')) ?></small></td>
<?php foreach($tasks as $t): $c=$s['cells'][$t['id']];$tone=['mengerjakan'=>'success','tidak_mengerjakan'=>'danger','belum_dinilai'=>'warning','terbuka'=>'secondary'][$c['status']]; ?>
<td><button type="button" class="btn recap-cell w-100 text-<?= $tone ?>" data-bs-toggle="modal" data-bs-target="#modalStatusTugas" data-siswa-id="<?= (int)$s['id'] ?>" data-tugas-id="<?= (int)$t['id'] ?>" data-nama="<?= sanitize($s['nama_lengkap']) ?>" data-judul="<?= sanitize($t['judul']) ?>" data-status="<?= $c['status']==='terbuka'?'otomatis':$c['status'] ?>" data-nilai="<?= $c['nilai']!==null?number_format($c['nilai'],2,'.',''):'' ?>" data-catatan="<?= sanitize($c['catatan']) ?>" data-pengumpulan="<?= $c['pengumpulan_id'] ?>" data-metode="<?= sanitize($t['metode_pengumpulan']) ?>" aria-label="<?= sanitize('Input status '.$s['nama_lengkap'].' untuk '.$t['judul']) ?>" <?= !$schema?'disabled':'' ?>><strong class="d-block fs-5"><?= $c['nilai']!==null?number_format($c['nilai'],2,',','.'):'—' ?></strong><small><?= $c['label'] ?></small></button></td>
<?php endforeach ?>
<td><strong class="fs-5 text-primary"><?= $s['rata_rata']!==null?number_format($s['rata_rata'],2,',','.'):'—' ?></strong><small class="d-block text-muted"><?= $s['jumlah_dihitung'] ?> tugas dihitung</small><?php if($s['belum_dinilai']): ?><span class="badge bg-warning-subtle text-warning-emphasis">Sementara</span><?php endif ?></td><td><?= $s['belum_dinilai'] ?></td><td><?= $s['tidak_mengerjakan'] ?></td></tr><?php endforeach ?>
<?php if(!$students): ?><tr><td colspan="<?= count($tasks)+5 ?>" class="text-muted py-4">Belum ada siswa pada kelas ini.</td></tr><?php endif ?>
<tr id="rekapSearchEmpty" hidden><td colspan="<?= count($tasks)+5 ?>" class="text-muted py-4">Siswa tidak ditemukan.</td></tr>
</tbody></table></div><?php endif ?></div></section><?php endif ?>
</main></div>
<div class="modal fade" id="modalStatusTugas" tabindex="-1" aria-labelledby="judulStatusTugas" aria-hidden="true"><div class="modal-dialog"><form method="post" class="modal-content" id="formStatusTugas">
<div class="modal-header"><h5 id="judulStatusTugas" class="modal-title">Input Status dan Nilai Tugas</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
<div class="modal-body"><p class="fw-bold mb-1" id="statusNamaSiswa"></p><p class="text-muted" id="statusJudulTugas"></p>
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="pengajaran_id" value="<?= $pid ?>"><input type="hidden" name="siswa_id" id="statusSiswaId"><input type="hidden" name="tugas_id" id="statusTugasId">
<label for="statusTugas" class="form-label">Status</label><select name="status" id="statusTugas" class="form-select mb-3"><option value="mengerjakan">Mengerjakan / Diterima Guru</option><option value="belum_dinilai">Belum Dinilai</option><option value="tidak_mengerjakan">Tidak Mengerjakan (0)</option><option value="otomatis">Gunakan Status Otomatis</option></select>
<label for="nilaiStatusTugas" class="form-label">Nilai</label><input type="number" name="nilai" id="nilaiStatusTugas" class="form-control mb-3" min="0" max="100" step="0.01" placeholder="0–100">
<label for="catatanStatusTugas" class="form-label">Catatan guru</label><textarea name="catatan" id="catatanStatusTugas" class="form-control" maxlength="1000" rows="3"></textarea>
<p class="small text-muted mt-3">Mengerjakan wajib disertai nilai, termasuk 0 bila hasilnya nol. Nilai tersimpan juga terbaca pada penilaian tugas yang sudah ada. Status Otomatis hanya melepas status manual; nilai dan jawaban tetap tersimpan.</p>
<a class="btn btn-sm btn-outline-primary" id="lihatJawabanTugas" hidden>Lihat jawaban / penilaian</a>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" <?= !$schema?'disabled':'' ?>>Simpan Status</button></div></form></div></div>
<script src="../assets/js/rekap-tugas.js"></script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
