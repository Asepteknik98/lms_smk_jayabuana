<?php
if(!isset($db,$isAdmin,$ownGuruId,$state)){http_response_code(404);exit;}
// Data disediakan oleh rekap_mengajar_module.php saat view ini dimuat.
$teacherFilter = isset($teacherFilter) ? (int)$teacherFilter : 0;
$teachers = isset($teachers) && is_array($teachers) ? $teachers : [];
$targetToday=(string)$db->query('SELECT CURRENT_DATE')->fetchColumn();$targetDate=$targetToday;$targetError='';$targetReport=null;
try{
    if(isset($_GET['tanggal_target'])){if(!is_string($_GET['tanggal_target']))throw new InvalidArgumentException('Tanggal target tidak valid.');rm_target_week($_GET['tanggal_target']);$targetDate=$_GET['tanggal_target'];}
    $targetReady=rm_target_ready($db);
    if($targetReady)$targetReport=rm_target_report($db,$targetDate,$isAdmin?$teacherFilter:$ownGuruId);
}catch(InvalidArgumentException $e){$targetError=$e->getMessage();}
catch(Throwable $e){error_log('Target JP: '.$e->getMessage());$targetError='Target JP belum dapat dimuat. Rekap JP yang sudah ada tetap tersedia.';}
$targetReady=$targetReady??false;$targetWeek=rm_target_week($targetToday);
$targetGuru=$isAdmin&&is_string($_GET['target_guru']??null)&&ctype_digit($_GET['target_guru'])?(int)$_GET['target_guru']:0;
$targetSelected=null;foreach($targetReport['rows']??[] as $targetRow)if($targetRow['guru_id']===$targetGuru)$targetSelected=$targetRow['versi'];
$targetNext=(new DateTimeImmutable($targetWeek['mulai']))->modify('+7 days')->format('Y-m-d');
$targetDefaultStart=$targetWeek['mulai'];
if($isAdmin&&$targetGuru&&$targetReady&&!$targetError){$targetCurrent=rm_target_latest($db,$targetGuru,$targetWeek['mulai']);if($targetCurrent)$targetDefaultStart=$targetNext;}
?>
<link rel="stylesheet" href="../assets/css/target-jp-guru.css?v=3">
<section class="card border-0 shadow-sm mb-4 rm-target-section"><details class="rm-target-shell" <?= !$isAdmin||isset($_GET['tanggal_target'])||$targetGuru?'open':'' ?>>
<summary><span><i class="fa-solid fa-bullseye me-2" aria-hidden="true"></i>Target JP harian &amp; mingguan</span><span class="rm-target-shell-hint">Lihat target dan JP tercatat</span></summary>
<div class="card-body">
<?php if($targetError): ?><div class="alert alert-warning" role="alert"><?= sanitize($targetError) ?></div>
<?php elseif(!$targetReady): ?><div class="alert alert-info">Target JP belum diaktifkan. Pengelola perlu menerapkan migrasi <strong>2026_10_19_target_jp_guru.sql</strong>. Rekap dan validasi JP tetap berjalan seperti biasa.</div>
<?php else: ?>
<form method="get" class="rm-target-week-form">
<?php foreach($state as $key=>$value): ?><input type="hidden" name="<?= sanitize($key) ?>" value="<?= sanitize($value) ?>"><?php endforeach ?>
<div><label for="rmTargetDate" class="form-label small">Pilih tanggal dalam minggu</label><input type="date" id="rmTargetDate" class="form-control" name="tanggal_target" value="<?= sanitize($targetDate) ?>" required min="2000-01-01" max="2099-12-31"></div><button class="btn btn-outline-primary">Tampilkan Minggu</button><span class="small text-muted">Senin <?= sanitize(date('d/m/Y',strtotime($targetReport['week']['mulai']))) ?> – Jumat <?= sanitize(date('d/m/Y',strtotime($targetReport['week']['selesai']))) ?></span>
</form>
<p class="small text-muted mt-3">Setiap sel harian: <strong>tercatat / target</strong>. Target mingguan adalah jumlah target Senin–Jumat. JP tercatat berasal dari rekap mengajar seluruh kelas dan mapel; filter kelas/mapel tidak mengurangi perbandingan ini. Tidak dihitung dari lama check-in.</p>
<?php if($isAdmin): ?><details class="rm-target-settings" <?= $targetGuru?'open':'' ?>><summary><i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Atur target guru</summary><form method="post" class="row g-2 align-items-end mt-1">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="target_save">
<div class="col-md-6"><label for="rmTargetGuru" class="form-label small"><i class="fa-solid fa-user-tie me-1" aria-hidden="true"></i>Guru</label><select id="rmTargetGuru" name="target_guru_id" class="form-select" required><option value="">Pilih guru</option><?php foreach($teachers as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $targetGuru===(int)$g['id']?'selected':'' ?>><?= sanitize($g['nama_lengkap']) ?></option><?php endforeach ?></select></div>
<div class="col-md-6"><label for="rmTargetStart" class="form-label small"><i class="fa-regular fa-calendar me-1" aria-hidden="true"></i>Berlaku mulai Senin</label><input type="date" id="rmTargetStart" class="form-control" name="berlaku_mulai" min="<?= sanitize($targetWeek['mulai']) ?>" value="<?= sanitize($targetDefaultStart) ?>" step="7" required></div>
<div class="col-12"><p class="small text-muted mb-2">Isi target sesi Pagi dan Siang (0?6 JP per sesi). Yang disimpan adalah total harian. Saat dibuka kembali, total dibagi ke Pagi terlebih dahulu (maksimal 6 JP), lalu sisanya ke Siang.</p></div>
<?php foreach(RM_TARGET_DAYS as $key=>$label): $dayTotal=(int)($targetSelected[$key]??0); ?><fieldset class="col-12 col-sm rm-target-day-input"><legend class="form-label small"><?= $label ?></legend>
<?php foreach(['pagi'=>'Pagi','siang'=>'Siang'] as $session=>$sessionLabel): ?><label for="rmTarget_<?= $key ?>_<?= $session ?>" class="form-label small"><?= $sessionLabel ?></label><input type="number" id="rmTarget_<?= $key ?>_<?= $session ?>" name="<?= $key ?>_<?= $session ?>" min="0" max="6" step="1" value="<?= $session==='pagi'?min(6,$dayTotal):max(0,$dayTotal-6) ?>" class="form-control mb-2" required inputmode="numeric"><?php endforeach ?>
<small class="d-block">Total: <output class="rm-target-day-total"><?= $dayTotal ?></output> JP</small></fieldset><?php endforeach ?>
<div class="col-12 mt-3"><strong class="small">Total otomatis: <output id="rmTargetWeeklyTotal" aria-live="polite"><?= array_sum(array_map(static fn($key)=>(int)($targetSelected[$key]??0),array_keys(RM_TARGET_DAYS))) ?> JP / minggu</output></strong></div>
<div class="col-12 d-flex gap-2 flex-wrap align-items-center mt-3"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Simpan Target JP</button><span class="small text-muted">0 berarti tanpa target; maksimal 12 JP per hari. Target minggu berjalan yang sudah ditetapkan tidak diubah; gunakan Senin berikutnya. Pola berlaku berulang sampai ada target baru.</span></div>
</form></details><?php endif ?>
<div class="table-responsive rm-target-table-wrap mt-3" tabindex="0" role="region" aria-label="Perbandingan target dan realisasi JP"><table class="table align-middle"><thead><tr><th>Guru</th><?php foreach(RM_TARGET_DAYS as $label): ?><th><?= $label ?></th><?php endforeach ?><th>Target minggu</th><th>Tercatat</th><th>Kekurangan</th></tr></thead><tbody>
<?php foreach($targetReport['rows'] as $targetRow): $weekResult=$targetRow['mingguan']; ?><tr><td><strong><?= sanitize($targetRow['nama']) ?></strong><small class="d-block text-muted"><?= sanitize($weekResult['label']) ?></small><?php if($targetRow['versi']): ?><small class="d-block text-muted">Berlaku <?= sanitize($targetRow['versi']['berlaku_mulai']) ?></small><?php endif ?><?php if($isAdmin): ?><a class="rm-target-edit" href="?<?= sanitize(http_build_query(array_merge($state,['tanggal_target'=>$targetDate,'target_guru'=>$targetRow['guru_id']]))) ?>">Atur target</a><?php endif ?></td>
<?php foreach($targetRow['harian'] as $result): ?><td><span class="rm-target-daily <?= $result['target']!==null&&$result['target']>0&&$result['kurang']==0?'rm-target-met':'' ?>" title="<?= sanitize($result['tanggal'].' — '.$result['label']) ?>"><?= sanitize(rm_target_number($result['aktual'])) ?> / <?= $result['target']===null?'—':(int)$result['target'] ?></span></td><?php endforeach ?>
<td><?= $weekResult['target']===null?'Belum diatur':(int)$weekResult['target'].' JP' ?></td><td><strong><?= sanitize(rm_target_number($weekResult['aktual'])) ?> JP</strong></td><td><?= $weekResult['kurang']===null?'—':sanitize(rm_target_number($weekResult['kurang'])).' JP' ?><?php if($weekResult['lebih']>0): ?><small class="d-block text-success">Lebih <?= sanitize(rm_target_number($weekResult['lebih'])) ?> JP</small><?php endif ?></td></tr><?php endforeach ?>
<?php if(!$targetReport['rows']): ?><tr><td colspan="9" class="text-center text-muted">Belum ada guru yang sesuai pilihan.</td></tr><?php endif ?></tbody></table></div>
<small class="d-block text-muted mt-2">Target adalah rencana beban mengajar, bukan bukti kehadiran. Kekurangan minggu berjalan belum merupakan hasil akhir. Koreksi dan validasi realisasi tetap mengikuti aturan yang sudah ada.</small>
<?php endif ?></div></details></section>
<?php if($isAdmin&&$targetReady&&!$targetError): ?><script>
(()=>{const output=document.getElementById('rmTargetWeeklyTotal');if(!output)return;const days=Array.from(document.querySelectorAll('.rm-target-day-input'));const update=()=>{let weekly=0;days.forEach(day=>{const total=Array.from(day.querySelectorAll('input')).reduce((sum,input)=>sum+(Number.isFinite(input.valueAsNumber)?input.valueAsNumber:0),0);day.querySelector('.rm-target-day-total').textContent=total;weekly+=total;});output.textContent=weekly+' JP / minggu';};days.forEach(day=>day.querySelectorAll('input').forEach(input=>input.addEventListener('input',update)));update();})();
</script><?php endif ?>
