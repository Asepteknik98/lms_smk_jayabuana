<?php
require_once __DIR__.'/rekap_akademik.php';
$jenisCapaian=ra_jenis($_GET['jenis_capaian']??'UTS');
$periodComponents=array_values(array_filter($komponen,static fn($k)=>$k['nama_komponen']===$jenisCapaian));
$periodComponent=count($periodComponents)===1?$periodComponents[0]:null;
$capaian=[];
if($periodComponent){$stmt=$db->prepare('SELECT siswa_id,deskripsi,saran FROM capaian_penilaian WHERE komponen_id=?');$stmt->execute([$periodComponent['id']]);foreach($stmt->fetchAll() as $cp)$capaian[$cp['siswa_id']]=$cp;}
?>
<section class="card grade-card mb-3 mt-3" id="capaianPenilaian"><div class="card-body p-3 p-md-4">
<h6>Nilai, Deskripsi Capaian, dan Saran UTS / UAS</h6>
<p class="text-muted">Data yang disimpan langsung dapat dipantau dan diunduh admin. Isi kemampuan yang sudah dikuasai siswa serta saran peningkatannya untuk mata pelajaran ini. Nilai UTS/UAS menggunakan komponen yang sama dengan tabel nilai di atas; bobot digunakan untuk nilai akhir gabungan.</p>
<div class="d-flex gap-2 mb-3"><?php foreach(['UTS','UAS'] as $jenisTab): ?><a class="btn <?= $jenisCapaian===$jenisTab?'btn-primary':'btn-outline-primary' ?>" href="?pengajaran_id=<?= $selected ?>&amp;jenis_capaian=<?= $jenisTab ?>#capaianPenilaian"><?= $jenisTab ?></a><?php endforeach ?></div>
<?php if(!$periodComponent): ?><div class="alert alert-warning">Tambahkan tepat satu komponen <?= $jenisCapaian ?> melalui bagian Komponen Penilaian sebelum mengisi nilai dan capaian.</div>
<?php else: ?>
<?php foreach($siswa as $s): $cp=$capaian[$s['id']]??[];$saved=$nilai[$s['id']][$periodComponent['id']]??null; ?>
<form method="post" action="rekap_nilai_action.php" class="border rounded p-3 mb-3">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save_period"><input type="hidden" name="pengajaran_id" value="<?= $selected ?>"><input type="hidden" name="siswa_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="jenis" value="<?= $jenisCapaian ?>">
<div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong><?= sanitize($s['nama_lengkap']) ?> <small class="text-muted">NISN <?= sanitize($s['nisn']??'') ?></small></strong><span class="badge <?= $saved!==null&&trim($cp['deskripsi']??'')!==''&&trim($cp['saran']??'')!==''?'bg-success':'bg-warning text-dark' ?>"><?= $saved!==null&&trim($cp['deskripsi']??'')!==''&&trim($cp['saran']??'')!==''?'Lengkap':'Belum lengkap' ?></span></div>
<div class="row g-3"><div class="col-md-2"><label class="form-label" for="periodNilai<?= (int)$s['id'] ?>">Nilai <?= $jenisCapaian ?></label><input id="periodNilai<?= (int)$s['id'] ?>" class="form-control" name="nilai_periode" type="number" min="0" max="100" step="0.01" placeholder="Belum diisi" value="<?= $saved===null?'':number_format((float)$saved,2,'.','') ?>"></div>
<div class="col-md-5"><label class="form-label" for="periodDeskripsi<?= (int)$s['id'] ?>">Deskripsi Capaian Pembelajaran</label><textarea id="periodDeskripsi<?= (int)$s['id'] ?>" class="form-control" name="deskripsi" rows="3" maxlength="2000" placeholder="Kemampuan atau materi yang sudah dikuasai siswa"><?= sanitize($cp['deskripsi']??'') ?></textarea></div>
<div class="col-md-5"><label class="form-label" for="periodSaran<?= (int)$s['id'] ?>">Saran Capaian Pembelajaran</label><textarea id="periodSaran<?= (int)$s['id'] ?>" class="form-control" name="saran" rows="3" maxlength="2000" placeholder="Materi yang perlu ditingkatkan dan langkah latihan yang disarankan"><?= sanitize($cp['saran']??'') ?></textarea></div></div>
<button class="btn btn-primary btn-sm mt-3">Simpan <?= $jenisCapaian ?> Siswa Ini</button>
</form>
<?php endforeach ?>
<?php if(!$siswa): ?><p class="text-muted">Belum ada siswa pada kelas ini.</p><?php endif ?>
<?php endif ?></div></section>
