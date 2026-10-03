<?php
// Presentation fragment inside the corresponding student's table row.
$activePeriod=($_GET['jenis_capaian']??'UTS')==='UAS'?'UAS':'UTS';
?>
<div class="inline-capaian" data-student="<?= (int)$s['id'] ?>">
<div class="capaian-heading"><div><strong>Capaian: <?= sanitize($s['nama_lengkap']) ?></strong><small>NISN <?= sanitize($s['nisn']??'') ?></small></div><button type="button" class="btn btn-sm btn-light capaian-close">Tutup</button></div>
<div class="capaian-periods" role="group" aria-label="Jenis capaian">
<?php foreach(['UTS','UAS'] as $period): ?><button type="button" class="btn btn-sm <?= $activePeriod===$period?'btn-primary':'btn-outline-primary' ?> capaian-period" data-period="<?= $period ?>" aria-pressed="<?= $activePeriod===$period?'true':'false' ?>" aria-controls="period<?= $period.(int)$s['id'] ?>"><?= $period ?></button><?php endforeach ?>
</div>
<?php foreach(['UTS','UAS'] as $period):
 $matches=array_values(array_filter($komponen,static fn($k)=>$k['nama_komponen']===$period));
 $component=count($matches)===1?$matches[0]:null;
 $cp=$component?($capaianPerKomponen[$component['id']][$s['id']]??[]):[];
 $saved=$component?($nilai[$s['id']][$component['id']]??null):null;
?>
<div class="capaian-period-panel" id="period<?= $period.(int)$s['id'] ?>" data-period="<?= $period ?>" <?= $activePeriod!==$period?'hidden':'' ?>>
<?php if(!$component): ?><p class="alert alert-warning mb-0">Tambahkan tepat satu komponen <?= $period ?> melalui Komponen Penilaian sebelum mengisi capaian.</p><?php else: ?>
<form method="post" action="rekap_nilai_action.php" class="inline-capaian-form" data-period="<?= $period ?>">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save_period"><input type="hidden" name="pengajaran_id" value="<?= $selected ?>"><input type="hidden" name="siswa_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="jenis" value="<?= $period ?>">
<div class="capaian-fields">
<div><label for="periodNilai<?= $period.(int)$s['id'] ?>">Nilai <?= $period ?></label><input id="periodNilai<?= $period.(int)$s['id'] ?>" class="form-control period-score" name="nilai_periode" type="number" min="0" max="100" step="0.01" placeholder="Belum diisi" value="<?= $saved===null?'':number_format((float)$saved,2,'.','') ?>"><small class="text-muted">Nilai yang sama dengan tabel.</small></div>
<div><label for="periodDeskripsi<?= $period.(int)$s['id'] ?>">Deskripsi Capaian Pembelajaran</label><textarea id="periodDeskripsi<?= $period.(int)$s['id'] ?>" class="form-control" name="deskripsi" rows="3" maxlength="2000" placeholder="Kemampuan atau materi yang sudah dikuasai siswa"><?= sanitize($cp['deskripsi']??'') ?></textarea></div>
<div><label for="periodSaran<?= $period.(int)$s['id'] ?>">Saran Capaian Pembelajaran</label><textarea id="periodSaran<?= $period.(int)$s['id'] ?>" class="form-control" name="saran" rows="3" maxlength="2000" placeholder="Materi yang perlu ditingkatkan dan langkah latihan"><?= sanitize($cp['saran']??'') ?></textarea></div>
</div>
<div class="capaian-footer"><button class="btn btn-primary btn-sm">Simpan <?= $period ?> Siswa Ini</button><span class="badge <?= $saved!==null&&trim($cp['deskripsi']??'')!==''&&trim($cp['saran']??'')!==''?'bg-success':'bg-warning text-dark' ?>"><?= $saved!==null&&trim($cp['deskripsi']??'')!==''&&trim($cp['saran']??'')!==''?'Lengkap':'Belum lengkap' ?></span></div>
</form>
<?php endif ?></div><?php endforeach ?>
</div>
