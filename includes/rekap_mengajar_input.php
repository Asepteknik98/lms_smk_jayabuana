<?php
// View formulir admin. Penanganan POST tetap di rekap_mengajar_module.php.
if(!isset($isAdmin,$teachers)||!$isAdmin){http_response_code(404);exit;}
?>
<section class="card border-0 shadow-sm mb-4 rm-entry-panel"><details <?= $edit||$error?'open':'' ?>>
<summary><span><i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i><strong><?= $edit?'Ubah':'Input' ?> Aktivitas Mengajar</strong></span><span class="rm-entry-hint"><?= $edit?'Sedang mengoreksi catatan':'Buka untuk menambah catatan' ?></span></summary>
<div class="card-body"><p class="small text-muted rm-entry-intro">Pilih guru dan tanggal. Pengajaran mengikuti guru yang dipilih; target JP muncul sebagai acuan.</p>
<form method="post" class="rm-entry-form">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
<div class="rm-entry-fields" role="group" aria-labelledby="rmEntryTeachingLabel">
<h3 id="rmEntryTeachingLabel" class="rm-entry-group-title"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i>Data pengajaran</h3>
<div><label for="rmTeacher" class="form-label"><i class="fa-solid fa-user-tie" aria-hidden="true"></i>Guru</label><select id="rmTeacher" class="form-select" required><option value="">Pilih guru</option><?php foreach($teachers as $g): ?><option value="<?= (int)$g['id'] ?>"><?= sanitize($g['nama_lengkap']) ?></option><?php endforeach ?></select></div>
<div><label for="rmEntryDate" class="form-label"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Tanggal mengajar</label><input type="date" id="rmEntryDate" name="tanggal" class="form-control" value="<?= sanitize($edit['tanggal']??date('Y-m-d')) ?>" required></div>
<div><label for="rmSubject" class="form-label"><i class="fa-solid fa-book-open" aria-hidden="true"></i>Mata pelajaran</label><select id="rmSubject" class="form-select" required disabled><option value="">Pilih guru dahulu</option></select></div>
<div><label for="rmTeaching" class="form-label"><i class="fa-solid fa-school" aria-hidden="true"></i>Kelas / periode</label><select id="rmTeaching" name="pengajaran_id" class="form-select" required disabled><option value="">Pilih mapel dahulu</option></select></div>
</div>
<aside id="rmInputTarget" class="rm-input-target" data-endpoint="target_jp_guru_data.php" data-editing="<?= $edit?'1':'0' ?>" aria-label="Acuan target JP guru" aria-busy="false">
<div class="rm-input-target-heading"><strong><i class="fa-solid fa-bullseye" aria-hidden="true"></i>Acuan target JP</strong><button id="rmInputTargetRefresh" type="button" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-rotate" aria-hidden="true"></i><span>Perbarui</span></button></div>
<div class="rm-input-target-metrics">
<div><span><i class="fa-regular fa-calendar-check" aria-hidden="true"></i>Target harian</span><strong id="rmInputTarget_day">&mdash;</strong></div>
<div><span><i class="fa-solid fa-calendar-week" aria-hidden="true"></i>Target mingguan</span><strong id="rmInputTarget_week">&mdash;</strong></div>
<div><span><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>Sudah tercatat</span><strong id="rmInputTarget_actual">&mdash;</strong></div>
<div><span><i class="fa-regular fa-hourglass-half" aria-hidden="true"></i>Sisa harian</span><strong id="rmInputTarget_remaining">&mdash;</strong></div>
</div><p id="rmInputTargetMessage" class="small text-muted mb-0" role="status" aria-live="polite">Pilih guru dan tanggal untuk melihat target JP. Tidak ada catatan yang disimpan otomatis.</p>
</aside>
<div class="rm-entry-session" role="group" aria-labelledby="rmEntrySessionLabel">
<h3 id="rmEntrySessionLabel" class="rm-entry-group-title"><i class="fa-regular fa-clock" aria-hidden="true"></i>Waktu &amp; jumlah JP</h3>
<div><label for="rmEntrySession" class="form-label"><i class="fa-solid fa-cloud-sun" aria-hidden="true"></i>Sesi</label><select name="jenis_sesi" id="rmEntrySession" class="form-select" required><?php foreach(['Pagi','Siang'] as $s): ?><option <?= ($edit['jenis_sesi']??'Pagi')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach ?></select></div>
<div><label for="rmStart" class="form-label"><i class="fa-solid fa-play" aria-hidden="true"></i>JP mulai</label><select name="jam_ke_mulai" id="rmStart" class="form-select"><?php for($i=1;$i<=6;$i++): ?><option value="<?= $i ?>" <?= (int)($edit['jam_ke_mulai']??1)===$i?'selected':'' ?>><?= $i ?></option><?php endfor ?></select></div>
<div><label for="rmEnd" class="form-label"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>JP selesai</label><select name="jam_ke_selesai" id="rmEnd" class="form-select"><?php for($i=1;$i<=6;$i++): ?><option value="<?= $i ?>" <?= (int)($edit['jam_ke_selesai']??1)===$i?'selected':'' ?>><?= $i ?></option><?php endfor ?></select></div>
<div><label for="rmJp" class="form-label"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Jumlah JP</label><input id="rmJp" class="form-control" readonly></div>
</div>
<div class="rm-entry-submit"><p><i class="fa-solid fa-circle-info" aria-hidden="true"></i>Periksa kelas, sesi, dan rentang JP sebelum menyimpan.</p><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i><?= $edit?'Simpan Perubahan':'Simpan Aktivitas' ?></button></div>
</form></div></details></section>
