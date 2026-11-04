<?php
require_once __DIR__.'/rapor_ekstrakurikuler.php';
require_once __DIR__.'/rapor_print_template.php';
// Pure rendering: uses saved snapshots only; does not query or update grade sources.
/** @param mixed $value Saved report value to escape for HTML output. */
function rapor_print_escape($value): string {
    return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
/** @param mixed $value Saved numeric value; null renders as an empty cell. */
function rapor_print_number($value): string {
    return $value===null?'':rtrim(rtrim(number_format((float)$value,2,',','.'),'0'),',');
}
function rapor_print_date(?string $date): string {
    if(!$date)return '................................';
    $months=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    return $d?$d->format('j').' '.$months[(int)$d->format('n')].' '.$d->format('Y'):'................................';
}
function rapor_print_table(array $rows): void {
    ?>
    <table class="grades"><colgroup><col style="width:5%"><col style="width:45%"><col style="width:10%"><col style="width:40%"></colgroup>
    <thead><tr><th rowspan="2">NO</th><th rowspan="2">MATA PELAJARAN</th><th colspan="2">NILAI</th></tr><tr><th>NILAI</th><th>DESKRIPSI CAPAIAN PEMBELAJARAN</th></tr></thead>
    <tbody data-grade-body>
    <?php foreach($rows as $i=>$m): ?>
    <tr data-grade-row data-group="<?= rapor_print_escape($m['kelompok']?:'Mata Pelajaran') ?>"><td class="center"><?= $i+1 ?></td><td><div class="subject-name"><?= rapor_print_escape($m['nama_mapel']) ?></div><div class="teacher">Guru: <?= rapor_print_escape($m['nama_guru']) ?></div></td><td class="score"><?= rapor_print_number($m['nilai_akhir']) ?></td><td class="narrative"><div><?= nl2br(rapor_print_escape($m['deskripsi']??'')) ?></div><?php if(trim($m['saran']??'')!==''): ?><div class="advice"><strong>Saran:</strong> <?= nl2br(rapor_print_escape($m['saran'])) ?></div><?php endif ?></td></tr>
    <?php endforeach ?>
    <?php if(!$rows): ?><tr class="empty-grades"><td colspan="4">Belum ada mata pelajaran tersimpan. Lengkapi dan simpan nilai pada halaman pengisian rapor.</td></tr><?php endif ?>
    </tbody></table>
    <?php
}
function rapor_print_render(array $r): void {
    $titles=$r['judul_cetak']??[];
    $reportTitle=trim($titles['judul']??'')?:'LAPORAN PENCAPAIAN KOMPETENSI PESERTA DIDIK';
    $assessmentTitle=trim($titles['penilaian']??'')?:'PENILAIAN SUMATIF '.($r['jenis']==='UAS'?'AKHIR':'TENGAH').' SEMESTER';
    $concentration=trim($titles['konsentrasi']??'')?:($r['jurusan']?:'—');
    $rows=rapor_print_template_rows($r['mapel']);$complete=(bool)$rows;$sum=0;
    foreach($rows as $m){if($m['nilai_akhir']===null)$complete=false;else $sum+=(float)$m['nilai_akhir'];}
    ?>
    <article class="report" aria-label="Rapor <?= rapor_print_escape($r['nama_siswa']) ?>">
    <?php for($page=1;$page<=2;$page++): ?>
    <section class="sheet sheet-<?= $page ?>" aria-label="Halaman <?= $page ?>">
    <img class="sheet-watermark" src="../assets/img/jb.png" alt="" aria-hidden="true" draggable="false">
    <div class="sheet-content">
    <?php if(is_file(__DIR__.'/../assets/img/rapor-header.png')): ?>
    <header class="letterhead" data-image-header><img src="../assets/img/rapor-header.png?v=<?= (int)filemtime(__DIR__.'/../assets/img/rapor-header.png') ?>" width="3463" height="588" alt="Kop SMKS Jaya Buana dan logo mitra"></header>
    <?php else: ?>
    <header class="letterhead"><img src="../assets/img/jb.png" alt="Logo SMKS Jaya Buana"><div><div class="foundation">YAYASAN PONPES RIYADLUL JANNAH</div><div class="school-name">SMKS JAYA BUANA</div><div class="address">Jl. Bedeng - Tamiang KM. 01, Ds. Kemuning<br>Kec. Kresek, Kab. Tangerang, Prov. Banten 15620</div><em>“Muda - Mandiri - Maju”</em></div></header>
    <div class="ornament"></div>
    <?php endif ?>
    <h1><?= rapor_print_escape($reportTitle) ?><br><?= rapor_print_escape($assessmentTitle) ?></h1>
    <h2>KONSENTRASI KEAHLIAN: <?= rapor_print_escape($concentration) ?></h2>
    <div class="identity"><dl><dt>Nama Siswa</dt><dd>: <?= rapor_print_escape($r['nama_siswa']) ?></dd><dt>NIS</dt><dd>: <?= rapor_print_escape($r['nis']) ?></dd><dt>NISN</dt><dd>: <?= rapor_print_escape($r['nisn']) ?></dd></dl><dl><dt>Kelas</dt><dd>: <?= rapor_print_escape($r['nama_kelas']) ?></dd><dt>Semester</dt><dd>: <?= $r['semester']==='Genap'?'2 (Genap)':'1 (Ganjil)' ?></dd><dt>Tahun Ajaran</dt><dd>: <?= rapor_print_escape($r['tahun_ajaran']) ?></dd></dl></div>
    <?php if($page===1): ?>
    <?php rapor_print_table($rows); ?>
    <table class="totals"><colgroup><col style="width:50%"><col style="width:10%"><col style="width:40%"></colgroup><tr><th>Jumlah</th><td><?= $complete?rapor_print_number($sum):'' ?></td><td></td></tr><tr><th>Rata-rata</th><td><?= $complete?rapor_print_number($sum/count($rows)):'' ?></td><td></td></tr><?php if(isset($r['peringkat'])): $rankInfo=$r['peringkat']; ?><tr class="ranking-total"><th>Peringkat kelas<?= !empty($rankInfo['sementara'])?' (sementara)':'' ?></th><td colspan="2"><?= $rankInfo['peringkat']!==null?(int)$rankInfo['peringkat'].' dari '.(int)$rankInfo['peserta'].' siswa':'Belum tersedia' ?></td></tr><?php endif ?></table>
    <?php else: ?>
    <h3>C. EKSTRAKURIKULER</h3>
    <div class="extra-columns">
    <?php $extras=rapor_ekstra_template($r['ekstra']);$half=11;foreach([array_slice($extras,0,$half),array_slice($extras,$half)] as $col=>$activities): ?>
    <table class="extras"><thead><tr><th>NO</th><th>KEGIATAN EKSTRAKURIKULER</th><th>NILAI</th></tr></thead><tbody><?php foreach($activities as $i=>$e): ?><tr><td class="center"><?= $col*$half+$i+1 ?></td><td><?= rapor_print_escape($e['nama_kegiatan']) ?></td><td class="center"><?= rapor_print_escape(($e['grade']??'')!==''?$e['grade']:'-') ?></td></tr><?php endforeach ?></tbody></table>
    <?php endforeach ?>
    </div>
    <div class="notes"><table><thead><tr><th colspan="3">Absensi Kehadiran</th></tr></thead><tbody><?php foreach(['sakit'=>'Sakit','izin'=>'Izin','alfa'=>'Tanpa Keterangan'] as $key=>$label): ?><tr><td><?= $label ?></td><td class="center"><?= (int)$r[$key] ?></td><td>Hari</td></tr><?php endforeach ?></tbody></table><table><thead><tr><th>Catatan Wali Kelas</th></tr></thead><tbody><tr><td class="narrative"><?= nl2br(rapor_print_escape($r['catatan_wali_kelas']?:'—')) ?></td></tr></tbody></table></div>
    <div class="signatures"><div>Mengetahui:<br>Kepala Sekolah<div class="signature-space"></div><strong><?= rapor_print_escape($r['nama_kepala_sekolah']?:'................................') ?></strong></div><div>Wali Kelas<br>&nbsp;<div class="signature-space"></div><strong><?= rapor_print_escape($r['nama_wali_kelas']?:'................................') ?></strong></div><div>Diberikan di: <?= rapor_print_escape($r['tempat_terbit']?:'................') ?><br>Tanggal: <?= rapor_print_escape(rapor_print_date($r['tanggal_terbit'])) ?><br>Orang Tua/Wali Murid<div class="signature-space parent"></div><strong>................................</strong></div></div>
    <?php endif ?>
    </div>
    <footer class="page-footer"><span><?= rapor_print_escape('Raport SMK Jaya Buana|'.$r['tahun_ajaran']) ?></span><span>Halaman <?= $page ?> / 2</span></footer>
    </section>
    <?php endfor ?>
    </article>
    <?php
}
