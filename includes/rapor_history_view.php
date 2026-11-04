<?php
// Context belongs to the authenticated admin/teacher report page that includes this view.
/** @var PDO $db */
/** @var int $kelas */
/** @var string $tahun */
/** @var string $semester */
/** @var string $jenis */
/** @var int $historyReportId */
$historyRows=rapor_history_list($db,$kelas,$tahun,$semester,$jenis,$historyReportId);
$historyLabels=['nomor_absen'=>'Nomor absen','sakit'=>'Sakit','izin'=>'Izin','alfa'=>'Alfa','catatan_wali_kelas'=>'Catatan wali kelas','nama_wali_kelas'=>'Nama wali kelas','wali_guru_id'=>'Penugasan wali kelas','nama_kepala_sekolah'=>'Nama kepala sekolah','tempat_terbit'=>'Tempat penerbitan','tanggal_terbit'=>'Tanggal penerbitan','mapel'=>'Nilai dan keterangan mata pelajaran','ekstra'=>'Ekstrakurikuler','terkunci'=>'Status kunci rapor','judul'=>'Judul laporan','penilaian'=>'Judul penilaian','konsentrasi'=>'Konsentrasi keahlian'];
?>
<details class="card rapor-fold rapor-audit-history mb-4"><summary><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Riwayat perubahan rapor <i class="fa-solid fa-chevron-down rapor-chevron" aria-hidden="true"></i></summary><div class="card-body"><p class="small text-muted">Menampilkan hingga 100 aktivitas terbaru pada kelas dan periode ini. Riwayat mulai dicatat sejak fitur ini diaktifkan.</p>
<?php if(!$historyRows): ?><p class="mb-0">Belum ada perubahan yang tercatat pada pilihan ini.</p><?php else: ?>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Siswa / lingkup</th><th>Aktivitas</th></tr></thead><tbody>
<?php foreach($historyRows as $historyRow): $changes=json_decode($historyRow['perubahan'],true);$parts=[];foreach(array_keys(is_array($changes)?$changes:[]) as $key)$parts[]=$historyLabels[$key]??$key; ?>
<tr><td><?= sanitize($historyRow['created_at']) ?><small class="d-block text-muted">WIB</small></td><td><?= sanitize($historyRow['nama_pengguna']) ?></td><td><?= sanitize($historyRow['nama_siswa']?:'Seluruh kelas') ?></td><td><strong><?= sanitize($historyRow['ringkasan']) ?></strong><small class="d-block text-muted">Bagian yang berubah: <?= sanitize(implode(', ',$parts)) ?></small></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?></div></details>
