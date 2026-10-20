<?php
if(!isset($_SESSION['user_id'])||(int)($_SESSION['role_id']??0)!==1){http_response_code(403);exit;}
$periodQuery=['tab'=>'jadwal','periode_laporan'=>$reportPeriod['mode'],'tanggal'=>$reportPeriod['tanggal']];
$exportQuery=['tanggal_mulai'=>$reportPeriod['tanggal_mulai'],'tanggal_selesai'=>$reportPeriod['tanggal_selesai']];
$dayNames=[1=>'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
?>
<link rel="stylesheet" href="../assets/css/monitoring-kbm-report.css?v=2">
<div class="kbm-report">
<p class="kbm-report-intro">Pilih harian, mingguan (Senin–Minggu), atau bulanan. Periode berjalan ditampilkan sampai hari ini. Waktu laporan menggunakan WIB.</p>
<form method="get" class="row g-3 align-items-end kbm-report-filters">
<input type="hidden" name="tab" value="jadwal">
<div class="col-md-3"><label for="reportPeriod" class="form-label">Jenis laporan</label><select id="reportPeriod" name="periode_laporan" class="form-select"><?php foreach(['harian'=>'Harian','mingguan'=>'Mingguan','bulanan'=>'Bulanan'] as $key=>$label): ?><option value="<?= $key ?>" <?= $reportPeriod['mode']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label for="reportDate" class="form-label">Tanggal acuan</label><input id="reportDate" name="tanggal" type="date" class="form-control" value="<?= kbm_simple_h($reportPeriod['tanggal']) ?>" max="<?= kbm_simple_h($reportToday) ?>" required></div>
<div class="col-md-6 d-flex gap-2 flex-wrap"><button class="btn btn-primary">Tampilkan Laporan</button><a class="btn btn-outline-secondary" href="?tab=jadwal">Hari Ini</a><a class="btn btn-success" href="riwayat_kbm_export.php?<?= kbm_simple_h(http_build_query($exportQuery)) ?>"><i class="fa-solid fa-file-excel me-2" aria-hidden="true"></i>Unduh Excel</a></div>
</form>
<div class="kbm-report-summary" aria-label="Ringkasan laporan">
    <div class="kbm-report-metric"><span class="kbm-report-metric-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span><div><span class="kbm-report-eyebrow">Hari pilihan</span><strong id="reportDay"><?= kbm_simple_h($reportPeriod['hari']) ?></strong></div></div>
    <div class="kbm-report-metric kbm-report-range"><span class="kbm-report-metric-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span><div><span class="kbm-report-eyebrow">Periode <?= kbm_simple_h($reportPeriod['mode']) ?></span><strong><?= kbm_simple_h(kbm_date($reportPeriod['tanggal_mulai'])->format('d-m-Y').' s.d. '.kbm_date($reportPeriod['tanggal_selesai'])->format('d-m-Y')) ?></strong></div></div>
    <div class="kbm-report-metric"><span class="kbm-report-metric-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span><div><span class="kbm-report-eyebrow">Total hasil periode</span><strong><?= (int)($reportData['total']??0) ?> <small>catatan</small></strong></div></div>
</div>
<div class="kbm-report-table-heading"><div><h3>Rincian laporan</h3><p>Catatan kegiatan belajar mengajar berdasarkan konfirmasi pengguna.</p></div><span class="kbm-report-zone"><i class="fa-regular fa-clock" aria-hidden="true"></i> Waktu Indonesia Barat</span></div>
<div class="table-responsive kbm-report-table-wrap" role="region" aria-label="Rincian laporan KBM, geser untuk melihat semua kolom" tabindex="0"><table class="table kbm-report-table"><thead><tr><th scope="col">Hari / Tanggal</th><th scope="col">Kelas / Sesi</th><th scope="col">JP</th><th scope="col">Guru</th><th scope="col">Check-in (WIB)</th><th scope="col">Check-out (WIB)</th><th scope="col">Durasi</th><th scope="col">Status</th></tr></thead><tbody>
<?php foreach($reports as $r): $statusTone=in_array($r['status']['color'],['merah','hijau','kuning','abu'],true)?$r['status']['color']:'kuning'; ?><tr>
<td><span class="kbm-report-primary"><?= $dayNames[(int)kbm_date($r['tanggal'])->format('N')] ?></span><span class="kbm-report-secondary"><?= kbm_simple_h(kbm_date($r['tanggal'])->format('d-m-Y')) ?></span></td>
<td><span class="kbm-report-primary"><?= kbm_simple_h($r['nama_kelas']) ?></span><span class="kbm-report-session"><?= kbm_simple_h($r['nama_sesi']) ?></span></td>
<td><span class="kbm-report-jp"><?= (int)$r['jp_mulai'] ?>&ndash;<?= (int)$r['jp_selesai'] ?></span></td>
<td><span class="kbm-report-teacher"><?= kbm_simple_h($r['nama_guru']) ?></span></td>
<?php foreach(['checkin_efektif','checkout_efektif'] as $timeField): ?><td><?php if($r[$timeField]!==null): ?><time class="kbm-report-time" datetime="<?= kbm_simple_h(str_replace(' ','T',$r[$timeField]).'+07:00') ?>"><span class="kbm-report-primary"><?= kbm_simple_h(substr($r[$timeField],11)) ?></span><span class="kbm-report-secondary"><?= kbm_simple_h(kbm_date(substr($r[$timeField],0,10))->format('d-m-Y')) ?></span></time><?php else: ?><span class="kbm-report-missing">Belum tercatat</span><?php endif ?></td><?php endforeach ?>
<td><span class="<?= ($r['checkin_efektif']!==null && $r['checkout_efektif']!==null)?'kbm-report-duration':'kbm-report-missing' ?>"><?= kbm_simple_h(($r['checkin_efektif']!==null && $r['checkout_efektif']!==null)?$r['duration']:'Belum lengkap') ?></span></td>
<td><span class="kbm-report-status kbm-report-status-<?= $statusTone ?>"><span aria-hidden="true"></span><?= kbm_simple_h($r['status']['label']) ?></span></td></tr><?php endforeach ?>
<?php if(!$reports): ?><tr><td colspan="8"><div class="kbm-report-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><strong>Belum ada laporan pada periode ini.</strong><span>Pilih tanggal atau periode lain untuk melihat catatan KBM.</span></div></td></tr><?php endif ?></tbody></table></div>
<div class="kbm-report-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Excel memuat seluruh hasil periode beserta pelapor, pembatalan, dan koreksi admin. Tabel menampilkan 25 catatan per halaman.</span></div>
<?php if($reportData): $page=(int)$reportData['filters']['halaman']; ?><nav class="d-flex gap-2 align-items-center flex-wrap kbm-report-pagination" aria-label="Halaman laporan"><?php if($page>1): ?><a class="btn btn-outline-primary btn-sm" href="?<?= kbm_simple_h(http_build_query($periodQuery+['halaman'=>$page-1])) ?>">Sebelumnya</a><?php endif ?><span class="small text-muted">Halaman <?= $page ?> dari <?= $reportData['pages'] ?></span><?php if($page<$reportData['pages']): ?><a class="btn btn-outline-primary btn-sm" href="?<?= kbm_simple_h(http_build_query($periodQuery+['halaman'=>$page+1])) ?>">Berikutnya</a><?php endif ?></nav><?php endif ?>
</div>
<script>
document.getElementById('reportDate').addEventListener('change',function(){
    const date=new Date(this.value+'T12:00:00+07:00');
    document.getElementById('reportDay').textContent=Number.isNaN(date.getTime())?'Pilih tanggal':new Intl.DateTimeFormat('id-ID',{weekday:'long',timeZone:'Asia/Jakarta'}).format(date);
});
</script>
