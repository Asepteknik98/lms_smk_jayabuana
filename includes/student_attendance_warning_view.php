<?php if($attendance_warnings): $warning_period=student_attendance_period(); ?>
<link rel="stylesheet" href="../assets/css/student-attendance-warning.css?v=<?=filemtime(__DIR__.'/../assets/css/student-attendance-warning.css')?>">
<section class="student-alpa-warning" role="alert" aria-labelledby="alpaWarningTitle">
<div class="alpa-warning-heading"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><h2 id="alpaWarningTitle">Peringatan Kehadiran</h2><p>Batas peringatan 3 kali Alpa per mata pelajaran telah tercapai.</p></div></div>
<div class="alpa-warning-period">Semester <?=sanitize($warning_period['semester'].' '.$warning_period['tahun_ajaran'])?></div>
<ul><?php foreach($attendance_warnings as $warning): ?><li><strong><?=sanitize($warning['nama_mapel'])?></strong><span><?=(int)$warning['jumlah_alpa']?> kali Alpa</span></li><?php endforeach ?></ul>
<p class="alpa-warning-action">Segera perbaiki kehadiran dan hubungi guru mata pelajaran atau wali kelas. Jika catatan keliru, mintalah pemeriksaan kepada guru.</p>
<a href="absensi.php" class="alpa-warning-link">Lihat Riwayat Absensi <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
</section>
<?php endif ?>
