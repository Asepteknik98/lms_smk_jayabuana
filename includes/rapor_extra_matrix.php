<?php
// Template context supplied by rapor_batch_view.php (or a report render fixture).
/** @var PDO $db */
/** @var array $editableStudents */
/** @var array $savedByStudent */
/** @var array|null $posted */
/** @var array $batchContext */
/** @var array $batchExtraNames */
$matrixReports=[];
foreach($editableStudents as $student)$matrixReports[(int)$student['id']]=rapor_load($db,(int)$savedByStudent[(int)$student['id']]['id']);
$matrixPosted=[];
if(($posted['batch_action']??'')==='extras'&&is_string($posted['extras_payload']??null)){
    $decoded=json_decode($posted['extras_payload'],true);
    if(is_array($decoded))foreach($decoded as $row)if(is_array($row)&&is_scalar($row['id']??null))$matrixPosted[(int)$row['id']]=$row;
}
?>
<section class="card mb-4 rapor-batch rapor-matrix-card" id="rapor-extras">
<div class="card-body">
<div class="rapor-section-title"><span class="rapor-section-number"><i class="fa-solid fa-medal" aria-hidden="true"></i></span><div><h2>Nilai ekstrakurikuler setiap siswa</h2><p>Satu siswa bisa mengikuti beberapa kegiatan, dengan grade berbeda pada setiap kegiatan.</p></div></div>
<form method="post" id="rapor-extra-matrix-form">
<?php foreach($batchContext as $key=>$value): ?><input type="hidden" name="<?= $key ?>" value="<?= sanitize((string)$value) ?>"><?php endforeach ?>
<input type="hidden" name="batch_action" value="extras"><input type="hidden" name="extras_payload" id="rapor-extras-payload"><input type="hidden" name="batch_complete" value="1">
<div class="rapor-matrix-guide"><div><strong>Klik grade langsung di tabel</strong><p>A, B, C, atau D untuk kegiatan yang diikuti. Pilih &minus; untuk mengosongkan.</p></div><span><?= count($editableStudents) ?> siswa &bull; <?= count($batchExtraNames) ?> kegiatan</span></div>
<div class="rapor-matrix-search" data-rapor-view-control>
<div><label class="form-label" for="rapor-matrix-student-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Cari siswa</label><input type="search" class="form-control" id="rapor-matrix-student-search" placeholder="Nama siswa" autocomplete="off"></div>
<div><label class="form-label" for="rapor-matrix-activity-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Cari eskul</label><input type="search" class="form-control" id="rapor-matrix-activity-search" placeholder="Contoh: Pramuka" autocomplete="off"></div>
<button type="button" class="btn btn-outline-secondary" id="rapor-matrix-search-reset"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Tampilkan semua</button>
<p class="small text-muted mb-0" id="rapor-matrix-filter-status" role="status"></p>
</div>
<div class="rapor-matrix-toolbar"><label for="rapor-extra-new">Kegiatan tambahan</label><div><input id="rapor-extra-new" class="form-control" maxlength="150" placeholder="Nama kegiatan baru"><button type="button" class="btn btn-outline-primary" id="rapor-extra-add"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah kegiatan</button></div></div>
<p class="rapor-table-hint">Semua kegiatan tercantum di tabel. Geser ke kanan untuk melihat kegiatan berikutnya; nama siswa tetap terlihat.</p>
<div class="table-responsive rapor-extra-matrix" tabindex="0" role="region" aria-label="Tabel nilai ekstrakurikuler siswa">
<table class="table"><thead><tr><th scope="col">Nama siswa</th><?php foreach($batchExtraNames as $name): ?><th scope="col" data-extra-column="<?= sanitize($name) ?>"><?= sanitize($name) ?></th><?php endforeach ?></tr></thead><tbody>
<?php foreach($editableStudents as $student): $r=$matrixReports[(int)$student['id']];$retry=$matrixPosted[(int)$r['id']]??[];$gradeMap=[];foreach(rapor_ekstra_template($r['ekstra']) as $e){$activityKey=rapor_ekstra_key($e['nama_kegiatan']);if(!array_key_exists($activityKey,$gradeMap)||($e['grade']??'')!=='')$gradeMap[$activityKey]=$e['grade']??'';}$retryGrades=[];if(is_array($retry['grades']??null))foreach($retry['grades'] as $retryName=>$retryGrade)if(is_string($retryName)&&is_string($retryGrade))$retryGrades[rapor_ekstra_key($retryName)]=$retryGrade; ?>
<tr data-report-id="<?= (int)$r['id'] ?>" data-version="<?= sanitize(is_string($retry['version']??null)?$retry['version']:rapor_version($r)) ?>" data-student-name="<?= sanitize($student['nama_lengkap']) ?>">
<th scope="row"><span class="rapor-matrix-avatar" aria-hidden="true"><?= sanitize(mb_substr($student['nama_lengkap'],0,1)) ?></span><span><?= sanitize($student['nama_lengkap']) ?><small data-extra-count></small></span></th>
<?php foreach($batchExtraNames as $name): $value=$retryGrades[rapor_ekstra_key($name)]??$gradeMap[rapor_ekstra_key($name)]??''; ?>
<td data-extra-name="<?= sanitize($name) ?>" data-grade="<?= sanitize($value) ?>"><div class="rapor-grade-buttons" role="group" aria-label="<?= sanitize($student['nama_lengkap'].' - '.$name) ?>"><?php foreach([''=>'−','A'=>'A','B'=>'B','C'=>'C','D'=>'D'] as $grade=>$label): ?><button type="button" data-grade-value="<?= $grade ?>" aria-pressed="<?= $value===$grade?'true':'false' ?>" aria-label="<?= $grade===''?'Kosongkan':'Grade '.$grade ?>"><?= $label ?></button><?php endforeach ?></div></td>
<?php endforeach ?></tr><?php endforeach ?></tbody></table></div>
<div class="rapor-matrix-footer"><p>Grade disimpan khusus untuk masing-masing siswa dan kegiatan.</p><button class="btn btn-primary btn-lg" id="rapor-extras-save" disabled>Simpan nilai ekstrakurikuler</button></div>
<noscript><p class="text-danger">Aktifkan JavaScript untuk mengisi nilai ekstrakurikuler kelas.</p></noscript>
</form></div></section>
