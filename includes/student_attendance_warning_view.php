<?php
$attendance_warnings = $attendance_warnings ?? [];
if($attendance_warnings): $warning_period=student_attendance_period(); ?>
<link rel="stylesheet" href="../assets/css/student-attendance-warning.css?v=<?=filemtime(__DIR__.'/../assets/css/student-attendance-warning.css')?>">
<?php if(!empty($attendance_warning_compact)): ?>
<section class="student-alpa-warning alpa-warning-compact" role="alert" aria-labelledby="alpaWarningTitle">
<div class="alpa-warning-heading"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><h2 id="alpaWarningTitle">Peringatan Kehadiran</h2><p><strong><?=count($attendance_warnings)?> mata pelajaran</strong> mencapai batas Alpa.</p></div></div>
<button type="button" class="alpa-details-button" data-alpa-open aria-haspopup="dialog" aria-controls="alpaWarningDialog">Lihat Rincian <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
</section>
<dialog id="alpaWarningDialog" class="alpa-warning-dialog" aria-labelledby="alpaDialogTitle" aria-describedby="alpaDialogDescription">
<div class="alpa-dialog-heading"><div><h2 id="alpaDialogTitle">Rincian Peringatan Kehadiran</h2><p>Semester <?=sanitize($warning_period['semester'].' '.$warning_period['tahun_ajaran'])?></p></div><button type="button" data-alpa-close aria-label="Tutup rincian kehadiran"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
<div class="alpa-dialog-body" tabindex="0" role="region" aria-label="Daftar mata pelajaran yang mencapai batas Alpa">
<p id="alpaDialogDescription">Peringatan muncul mulai 3 kali Alpa pada mata pelajaran yang sama dalam semester aktif.</p>
<ul class="alpa-dialog-list"><?php foreach($attendance_warnings as $warning): ?><li><strong><?=sanitize($warning['nama_mapel'])?></strong><span><?=(int)$warning['jumlah_alpa']?> kali Alpa</span></li><?php endforeach ?></ul>
<p class="alpa-warning-action">Segera perbaiki kehadiran dan hubungi guru mata pelajaran atau wali kelas. Jika catatan keliru, mintalah pemeriksaan kepada guru.</p>
</div>
<div class="alpa-dialog-footer"><button type="button" data-alpa-close>Tutup</button><a href="absensi.php">Lihat Riwayat Absensi <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
</dialog>
<script src="../assets/js/student-attendance-warning.js?v=<?=filemtime(__DIR__.'/../assets/js/student-attendance-warning.js')?>" defer></script>
<?php else: ?>
<section class="student-alpa-warning" role="alert" aria-labelledby="alpaWarningTitle">
<div class="alpa-warning-heading"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><h2 id="alpaWarningTitle">Peringatan Kehadiran</h2><p>Batas peringatan 3 kali Alpa per mata pelajaran telah tercapai.</p></div></div>
<div class="alpa-warning-period">Semester <?=sanitize($warning_period['semester'].' '.$warning_period['tahun_ajaran'])?></div>
<ul><?php foreach($attendance_warnings as $warning): ?><li><strong><?=sanitize($warning['nama_mapel'])?></strong><span><?=(int)$warning['jumlah_alpa']?> kali Alpa</span></li><?php endforeach ?></ul>
<p class="alpa-warning-action">Segera perbaiki kehadiran dan hubungi guru mata pelajaran atau wali kelas. Jika catatan keliru, mintalah pemeriksaan kepada guru.</p>
<a href="absensi.php" class="alpa-warning-link">Lihat Riwayat Absensi <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
</section>
<?php endif; endif ?>
