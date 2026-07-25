<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([1]);
$db = Database::getInstance();

$guru_id=(int)($_GET['guru_id']??0);$kelas_id=(int)($_GET['kelas_id']??0);$mapel_id=(int)($_GET['mapel_id']??0);
$semester=in_array($_GET['semester']??'',['Ganjil','Genap'],true)?$_GET['semester']:'';
$tahun_ajaran=trim($_GET['tahun_ajaran']??'');$pertemuan=(int)($_GET['pertemuan']??0);
$status_filter=in_array($_GET['status']??'',['aktif','selesai','belum_dinilai','terlambat','belum_lengkap'],true)?$_GET['status']:'';
$q=trim($_GET['q']??'');$halaman=max(1,(int)($_GET['page']??1));$tugas_id=(int)($_GET['tugas_id']??0);$per_halaman=10;
if($pertemuan<1||$pertemuan>20)$pertemuan=0;if(mb_strlen($q)>100)$q=mb_substr($q,0,100);if(mb_strlen($tahun_ajaran)>10)$tahun_ajaran='';

$guru_list=$db->query('SELECT id,nama_lengkap FROM guru ORDER BY nama_lengkap')->fetchAll();
$kelas_list=$db->query('SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();
$mapel_list=$db->query('SELECT id,nama_mapel FROM mapel ORDER BY nama_mapel')->fetchAll();
$tahun_list=$db->query('SELECT DISTINCT tahun_ajaran FROM pengajaran ORDER BY tahun_ajaran DESC')->fetchAll(PDO::FETCH_COLUMN);

$where=[];$params=[];
if($guru_id){$where[]='p.guru_id=?';$params[]=$guru_id;}if($kelas_id){$where[]='p.kelas_id=?';$params[]=$kelas_id;}
if($mapel_id){$where[]='p.mapel_id=?';$params[]=$mapel_id;}if($semester!==''){$where[]='p.semester=?';$params[]=$semester;}
if($tahun_ajaran!==''){$where[]='p.tahun_ajaran=?';$params[]=$tahun_ajaran;}if($pertemuan){$where[]='t.pertemuan_ke=?';$params[]=$pertemuan;}
if($q!==''){$where[]='(t.judul LIKE ? OR t.deskripsi LIKE ? OR g.nama_lengkap LIKE ? OR m.nama_mapel LIKE ? OR k.nama_kelas LIKE ?)';$cari='%'.$q.'%';array_push($params,$cari,$cari,$cari,$cari,$cari);}
$where_sql=$where?' WHERE '.implode(' AND ',$where):'';

$summary_sql="SELECT t.id,t.judul,t.deskripsi,t.pertemuan_ke,t.deadline,t.file_lampiran,t.created_at,
 p.semester,p.tahun_ajaran,p.kelas_id,g.nama_lengkap nama_guru,m.nama_mapel,k.nama_kelas,
 (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id=p.kelas_id) total_siswa,
 (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id) terkumpul,
 (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id AND pt.nilai IS NOT NULL) dinilai,
 (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id AND pt.nilai IS NULL) belum_dinilai,
 (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id AND pt.dikumpulkan_pada>t.deadline) terlambat
 FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN guru g ON g.id=p.guru_id
 JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id $where_sql";
$outer=[];
if($status_filter==='aktif')$outer[]='x.deadline>=NOW()';
elseif($status_filter==='selesai')$outer[]='x.deadline<NOW()';
elseif($status_filter==='belum_dinilai')$outer[]='x.belum_dinilai>0';
elseif($status_filter==='terlambat')$outer[]='x.terlambat>0';
elseif($status_filter==='belum_lengkap')$outer[]='x.terkumpul<x.total_siswa';
$outer_sql=$outer?' WHERE '.implode(' AND ',$outer):'';

$stmt=$db->prepare("SELECT COUNT(*) FROM ($summary_sql) x $outer_sql");$stmt->execute($params);$total_tugas=(int)$stmt->fetchColumn();
$total_halaman=max(1,(int)ceil($total_tugas/$per_halaman));$halaman=min($halaman,$total_halaman);$offset=($halaman-1)*$per_halaman;
$stmt=$db->prepare("SELECT x.*,GREATEST(x.total_siswa-x.terkumpul,0) belum_mengumpulkan FROM ($summary_sql) x $outer_sql ORDER BY x.deadline DESC,x.id DESC LIMIT ? OFFSET ?");
$i=1;foreach($params as $param)$stmt->bindValue($i++,$param,PDO::PARAM_STR);$stmt->bindValue($i++,$per_halaman,PDO::PARAM_INT);$stmt->bindValue($i,$offset,PDO::PARAM_INT);$stmt->execute();$tugas_list=$stmt->fetchAll();

$stmt=$db->prepare("SELECT COUNT(*) total,COALESCE(SUM(x.terkumpul),0) terkumpul,COALESCE(SUM(x.belum_dinilai),0) belum_dinilai,COALESCE(SUM(x.terlambat),0) terlambat FROM ($summary_sql) x $outer_sql");
$stmt->execute($params);$ringkasan=$stmt->fetch();

$detail_tugas=null;$detail_siswa=[];
if($tugas_id){
 $stmt=$db->prepare("SELECT x.*,GREATEST(x.total_siswa-x.terkumpul,0) belum_mengumpulkan FROM ($summary_sql) x WHERE x.id=?");
 $detail_params=$params;$detail_params[]=$tugas_id;$stmt->execute($detail_params);$detail_tugas=$stmt->fetch();
 if($detail_tugas){
  $stmt=$db->prepare('SELECT s.id,s.nis,s.nisn,s.nama_lengkap,pt.id pengumpulan_id,pt.file_tugas,pt.nama_file_asli,pt.ukuran_file,pt.catatan,pt.nilai,pt.catatan_guru,pt.dikumpulkan_pada FROM siswa s LEFT JOIN pengumpulan_tugas pt ON pt.siswa_id=s.id AND pt.tugas_id=? WHERE s.kelas_id=? ORDER BY s.nama_lengkap');
  $stmt->execute([$tugas_id,$detail_tugas['kelas_id']]);$detail_siswa=$stmt->fetchAll();
 }
}
$query_state=['guru_id'=>$guru_id,'kelas_id'=>$kelas_id,'mapel_id'=>$mapel_id,'semester'=>$semester,'tahun_ajaran'=>$tahun_ajaran,'pertemuan'=>$pertemuan,'status'=>$status_filter,'q'=>$q];
$filter_aktif=array_filter($query_state,static fn($v)=>$v!==''&&$v!==0);
?>
<?php require_once __DIR__.'/../includes/sidebar.php'; ?>
<div id="page-content-wrapper" class="task-monitor-page">
<style>
.task-monitor-page{background:#f7f9fc;min-width:0}.task-content{max-width:1400px;margin:auto}.task-hero{background:linear-gradient(135deg,#172554,#1d4ed8);color:#fff;border-radius:22px;padding:28px;box-shadow:0 18px 44px rgba(29,78,216,.17)}.task-card,.metric-card{border:1px solid #e8edf5!important;border-radius:18px!important;box-shadow:0 8px 28px rgba(15,23,42,.045)!important}.metric-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:13px}.filter-card .form-label{font-size:.76rem;font-weight:700}.task-table th{white-space:nowrap;font-size:.75rem;text-transform:uppercase;color:#64748b}.task-table td{font-size:.83rem;vertical-align:middle}.task-title{max-width:290px;overflow-wrap:anywhere}.detail-anchor{scroll-margin-top:15px}.pagination .page-link{border:0;border-radius:9px!important;margin:0 2px}.submission-mobile{border:1px solid #e8edf5;border-radius:14px;padding:14px}.submission-mobile+.submission-mobile{margin-top:10px}@media(max-width:767.98px){.task-content{padding:14px!important}.task-hero{padding:21px;border-radius:18px}}
</style>
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i>Monitoring Tugas</h5><small class="text-muted">Pantau tugas dan pengumpulan seluruh siswa</small></div></nav>
<main class="container-fluid task-content p-3 p-md-4">
<section class="task-hero mb-3"><div class="d-flex justify-content-between align-items-center gap-3"><div><small class="text-uppercase fw-semibold opacity-75">Kontrol Akademik</small><h2 class="h4 fw-bold my-1">Tugas dan Pengumpulan</h2><p class="mb-0 opacity-75">Pantau deadline, kelengkapan pengumpulan, keterlambatan, dan penilaian.</p></div><i class="fa-solid fa-file-circle-check fa-2x opacity-50"></i></div></section>
<div class="row g-2 mb-3"><?php foreach([['Tugas Ditemukan',(int)$ringkasan['total'],'fa-list-check','primary'],['Total Pengumpulan',(int)$ringkasan['terkumpul'],'fa-file-arrow-up','success'],['Belum Dinilai',(int)$ringkasan['belum_dinilai'],'fa-pen-to-square','warning'],['Terlambat',(int)$ringkasan['terlambat'],'fa-clock','danger']] as [$label,$jumlah,$icon,$warna]): ?><div class="col-6 col-xl-3"><div class="card metric-card p-3 h-100"><div class="d-flex align-items-center gap-3"><span class="metric-icon bg-<?= $warna ?>-subtle text-<?= $warna ?>"><i class="fa-solid <?= $icon ?>"></i></span><div><small class="text-muted"><?= $label ?></small><h4 class="fw-bold mb-0"><?= $jumlah ?></h4></div></div></div></div><?php endforeach ?></div>
<section class="card task-card filter-card mb-3"><div class="card-body p-3"><form method="get" class="row g-2 align-items-end">
<div class="col-md-6 col-xl-3"><label class="form-label">Cari Tugas</label><input type="search" name="q" value="<?= sanitize($q) ?>" class="form-control" placeholder="Judul, guru, mapel, kelas..."></div>
<div class="col-6 col-md-3 col-xl"><label class="form-label">Guru</label><select name="guru_id" class="form-select"><option value="0">Semua</option><?php foreach($guru_list as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $guru_id===(int)$r['id']?'selected':'' ?>><?= sanitize($r['nama_lengkap']) ?></option><?php endforeach ?></select></div>
<div class="col-6 col-md-3 col-xl"><label class="form-label">Kelas</label><select name="kelas_id" class="form-select"><option value="0">Semua</option><?php foreach($kelas_list as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $kelas_id===(int)$r['id']?'selected':'' ?>><?= sanitize($r['nama_kelas']) ?></option><?php endforeach ?></select></div>
<div class="col-6 col-md-3 col-xl"><label class="form-label">Mapel</label><select name="mapel_id" class="form-select"><option value="0">Semua</option><?php foreach($mapel_list as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $mapel_id===(int)$r['id']?'selected':'' ?>><?= sanitize($r['nama_mapel']) ?></option><?php endforeach ?></select></div>
<div class="col-6 col-md-3 col-xl"><label class="form-label">Semester</label><select name="semester" class="form-select"><option value="">Semua</option><option <?= $semester==='Ganjil'?'selected':'' ?>>Ganjil</option><option <?= $semester==='Genap'?'selected':'' ?>>Genap</option></select></div>
<div class="col-6 col-md-3 col-xl"><label class="form-label">Tahun</label><select name="tahun_ajaran" class="form-select"><option value="">Semua</option><?php foreach($tahun_list as $r): ?><option value="<?= sanitize($r) ?>" <?= $tahun_ajaran===$r?'selected':'' ?>><?= sanitize($r) ?></option><?php endforeach ?></select></div>
<div class="col-6 col-md-3 col-xl"><label class="form-label">Pertemuan</label><select name="pertemuan" class="form-select"><option value="0">Semua</option><?php for($n=1;$n<=20;$n++): ?><option value="<?= $n ?>" <?= $pertemuan===$n?'selected':'' ?>><?= $n ?></option><?php endfor ?></select></div>
<div class="col-6 col-md-3 col-xl"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua</option><option value="aktif" <?= $status_filter==='aktif'?'selected':'' ?>>Masih Aktif</option><option value="selesai" <?= $status_filter==='selesai'?'selected':'' ?>>Deadline Lewat</option><option value="belum_dinilai" <?= $status_filter==='belum_dinilai'?'selected':'' ?>>Belum Dinilai</option><option value="terlambat" <?= $status_filter==='terlambat'?'selected':'' ?>>Ada Keterlambatan</option><option value="belum_lengkap" <?= $status_filter==='belum_lengkap'?'selected':'' ?>>Belum Lengkap</option></select></div>
<div class="col-6 col-xl-auto"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Terapkan</button></div><?php if($filter_aktif): ?><div class="col-6 col-xl-auto"><a href="monitoring_tugas.php" class="btn btn-light border w-100">Reset</a></div><?php endif ?>
</form></div></section>
<?php if($detail_tugas): ?><section id="detailPengumpulan" class="card task-card detail-anchor mb-3"><div class="card-body p-3 p-md-4"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h6 class="fw-bold mb-1">Detail Pengumpulan: <?= sanitize($detail_tugas['judul']) ?></h6><small class="text-muted"><?= sanitize($detail_tugas['nama_mapel']) ?> · <?= sanitize($detail_tugas['nama_kelas']) ?> · <?= sanitize($detail_tugas['nama_guru']) ?> · Deadline <?= date('d/m/Y H:i',strtotime($detail_tugas['deadline'])) ?></small></div><a href="?<?= http_build_query($query_state) ?>" class="btn btn-sm btn-light border align-self-start"><i class="fa-solid fa-xmark me-1"></i>Tutup Detail</a></div>
<div class="table-responsive d-none d-md-block"><table class="table task-table table-hover align-middle"><thead class="table-light"><tr><th>Siswa</th><th>Status</th><th>Waktu</th><th>File/Ukuran</th><th>Nilai</th><th>Catatan</th></tr></thead><tbody>
<?php foreach($detail_siswa as $s): $terkumpul=!empty($s['pengumpulan_id']);$terlambat=$terkumpul&&strtotime($s['dikumpulkan_pada'])>strtotime($detail_tugas['deadline']);$deadline_lewat=time()>strtotime($detail_tugas['deadline']); ?><tr><td><strong><?= sanitize($s['nama_lengkap']) ?></strong><br><small class="text-muted">NISN <?= sanitize($s['nisn']?:'-') ?></small></td><td><?php if($terlambat): ?><span class="badge bg-danger">Terlambat</span><?php elseif($terkumpul): ?><span class="badge bg-success">Terkumpul</span><?php elseif($deadline_lewat): ?><span class="badge bg-danger">Tidak Mengumpulkan</span><?php else: ?><span class="badge bg-secondary">Belum Mengumpulkan</span><?php endif ?></td><td><?= $terkumpul?date('d/m/Y H:i',strtotime($s['dikumpulkan_pada'])):'—' ?></td><td><?php if($terkumpul): ?><div class="d-flex flex-wrap align-items-center gap-1"><a href="tugas_file.php?jenis=jawaban&amp;id=<?= (int)$s['pengumpulan_id'] ?>&amp;mode=preview" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i></a><a href="tugas_file.php?jenis=jawaban&amp;id=<?= (int)$s['pengumpulan_id'] ?>&amp;mode=download" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-download"></i></a><small class="text-muted"><?= sanitize($s['nama_file_asli']?:$s['file_tugas']) ?><?= $s['ukuran_file']?' · '.number_format($s['ukuran_file']/1024,1,',','.').' KB':'' ?></small></div><?php else: ?>—<?php endif ?></td><td><?= $s['nilai']!==null?'<strong>'.number_format((float)$s['nilai'],2,',','.').'</strong>':'<span class="text-muted">Belum dinilai</span>' ?></td><td><small><?= sanitize($s['catatan']?:'-') ?><?php if($s['catatan_guru']): ?><br><span class="text-primary">Guru: <?= sanitize($s['catatan_guru']) ?></span><?php endif ?></small></td></tr><?php endforeach ?></tbody></table></div>
<div class="d-md-none"><?php foreach($detail_siswa as $s): $terkumpul=!empty($s['pengumpulan_id']);$terlambat=$terkumpul&&strtotime($s['dikumpulkan_pada'])>strtotime($detail_tugas['deadline']);$deadline_lewat=time()>strtotime($detail_tugas['deadline']); ?><article class="submission-mobile"><div class="d-flex justify-content-between gap-2"><strong><?= sanitize($s['nama_lengkap']) ?></strong><?php if($terlambat): ?><span class="badge bg-danger">Terlambat</span><?php elseif($terkumpul): ?><span class="badge bg-success">Terkumpul</span><?php elseif($deadline_lewat): ?><span class="badge bg-danger">Tidak Mengumpulkan</span><?php else: ?><span class="badge bg-secondary">Belum</span><?php endif ?></div><small class="text-muted"><?= $terkumpul?date('d/m/Y H:i',strtotime($s['dikumpulkan_pada'])):'Belum ada waktu pengumpulan' ?></small><?php if($terkumpul): ?><div class="d-flex gap-2 mt-3"><a href="tugas_file.php?jenis=jawaban&amp;id=<?= (int)$s['pengumpulan_id'] ?>&amp;mode=preview" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary flex-fill">Preview</a><a href="tugas_file.php?jenis=jawaban&amp;id=<?= (int)$s['pengumpulan_id'] ?>&amp;mode=download" class="btn btn-sm btn-outline-success flex-fill">Unduh</a></div><div class="small mt-2">Nilai: <strong><?= $s['nilai']!==null?number_format((float)$s['nilai'],2,',','.'):'Belum dinilai' ?></strong></div><?php endif ?></article><?php endforeach ?></div>
</div></section><?php elseif($tugas_id): ?><div class="alert alert-danger">Tugas tidak ditemukan pada hasil filter yang dipilih.</div><?php endif ?>
<section class="card task-card"><div class="card-body p-0"><div class="px-3 px-md-4 py-3 border-bottom"><h6 class="fw-bold mb-0">Daftar Tugas</h6><small class="text-muted">Menampilkan <?= $total_tugas?$offset+1:0 ?>–<?= min($offset+$per_halaman,$total_tugas) ?> dari <?= $total_tugas ?> tugas</small></div><div class="table-responsive"><table class="table task-table table-hover mb-0"><thead class="table-light"><tr><th class="ps-4">Tugas</th><th>Pengajaran</th><th>Deadline</th><th>Pengumpulan</th><th>Penilaian</th><th>Keterlambatan</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($tugas_list as $t): ?><tr><td class="ps-4"><div class="task-title"><strong><?= sanitize($t['judul']) ?></strong><br><small class="text-muted">Pertemuan <?= (int)$t['pertemuan_ke'] ?><?php if($t['file_lampiran']): ?> · <a href="tugas_file.php?jenis=lampiran&amp;id=<?= (int)$t['id'] ?>&amp;mode=download">Lampiran</a><?php endif ?></small></div></td><td><strong><?= sanitize($t['nama_mapel']) ?></strong><br><small class="text-muted"><?= sanitize($t['nama_kelas']) ?> · <?= sanitize($t['nama_guru']) ?><br><?= sanitize($t['semester']) ?> <?= sanitize($t['tahun_ajaran']) ?></small></td><td><span class="<?= strtotime($t['deadline'])<time()?'text-danger':'text-success' ?>"><?= date('d/m/Y H:i',strtotime($t['deadline'])) ?></span><br><small><?= strtotime($t['deadline'])<time()?'Selesai':'Aktif' ?></small></td><td><strong><?= (int)$t['terkumpul'] ?>/<?= (int)$t['total_siswa'] ?></strong><?php if($t['belum_mengumpulkan']): ?><br><small class="text-danger"><?= (int)$t['belum_mengumpulkan'] ?> belum</small><?php endif ?></td><td><span class="text-success"><?= (int)$t['dinilai'] ?> dinilai</span><?php if($t['belum_dinilai']): ?><br><span class="text-warning"><?= (int)$t['belum_dinilai'] ?> belum</span><?php endif ?></td><td><span class="badge <?= $t['terlambat']?'bg-danger':'bg-light text-dark' ?>"><?= (int)$t['terlambat'] ?></span></td><td><a href="?<?= http_build_query(array_merge($query_state,['page'=>$halaman,'tugas_id'=>(int)$t['id']])) ?>#detailPengumpulan" class="btn btn-sm btn-primary text-nowrap"><i class="fa-solid fa-users-viewfinder me-1"></i>Detail</a></td></tr><?php endforeach ?><?php if(!$tugas_list): ?><tr><td colspan="7" class="text-center text-muted py-5">Tidak ada tugas sesuai filter.</td></tr><?php endif ?></tbody></table></div>
<?php if($total_halaman>1): ?><nav class="p-3 border-top"><ul class="pagination pagination-sm justify-content-center mb-0"><li class="page-item <?= $halaman<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>max(1,$halaman-1)])) ?>">&laquo;</a></li><?php for($n=max(1,$halaman-2);$n<=min($total_halaman,$halaman+2);$n++): ?><li class="page-item <?= $n===$halaman?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>$n])) ?>"><?= $n ?></a></li><?php endfor ?><li class="page-item <?= $halaman>=$total_halaman?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_state,['page'=>min($total_halaman,$halaman+1)])) ?>">&raquo;</a></li></ul></nav><?php endif ?>
</div></section></main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
