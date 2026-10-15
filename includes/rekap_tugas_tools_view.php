<?php
function rt_inline_render_tools(array $view): void {
    [
        'data'=>$data,'context'=>$context,'importPlan'=>$importPlan,
        'panel'=>$panel,'remedialReady'=>$remedialReady,
        'remedialSelected'=>$remedialSelected,'cancelReady'=>$cancelReady
    ]=$view;
    $info=$data['info'];
    $importTarget=null;
    foreach($data['tasks'] as $task)if((int)$task['id']===(int)($context['impor_tugas_id']??0))$importTarget=$task;
$hidden=function(string $action)use($context): void {
    echo '<input type="hidden" name="action" value="'.sanitize($action).'"><input type="hidden" name="csrf_token" value="'.sanitize($_SESSION['csrf_token']).'">';
    foreach($context as $key=>$value)echo '<input type="hidden" name="'.sanitize($key).'" value="'.sanitize((string)$value).'">';
};
$readyRows=$importPlan?count(array_filter($importPlan['rows'],static fn($r)=>$r['ready'])):0;
$invalidRows=$importPlan?count(array_filter($importPlan['rows'],static fn($r)=>$r['invalid'])):0;
?>
<div class="modal fade" id="modalImporTugas" tabindex="-1" aria-labelledby="judulImporTugas" aria-hidden="true" data-open="<?= $panel==='impor'?'1':'0' ?>"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h5 id="judulImporTugas" class="modal-title fw-bold">Impor Nilai Tugas Satu Kelas</h5><small class="text-muted"><?= sanitize($info['nama_kelas'].' · '.$info['nama_mapel']) ?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body">
        <ol class="small"><li>Pilih satu target tugas, lalu unduh template Excel untuk tugas tersebut.</li><li>Isi nilai 0&ndash;100. Kosongkan sel yang tidak ingin diisi. Jangan mengubah ID siswa atau ID tugas.</li><li>Unggah file, periksa target dan pratinjau nilai, kemudian konfirmasi penyimpanan.</li></ol>
        <form method="get" action="tugas.php#rekap-tugas" class="border rounded-3 p-3 mb-3">
            <?php foreach($context as $key=>$value): if($key==='impor_tugas_id')continue; ?><input type="hidden" name="<?= sanitize($key) ?>" value="<?= sanitize((string)$value) ?>"><?php endforeach ?>
            <input type="hidden" name="rekap_panel" value="impor">
            <label for="imporTargetTugas" class="form-label fw-semibold">Target tugas</label>
            <div class="d-flex flex-wrap gap-2"><select id="imporTargetTugas" name="impor_tugas_id" onchange="this.form.requestSubmit()" class="form-select flex-grow-1" style="flex-basis:300px;width:auto" required><option value="">-- Pilih target tugas --</option><?php foreach($data['tasks'] as $task): ?><option value="<?= (int)$task['id'] ?>" <?= $importTarget && (int)$importTarget['id']===(int)$task['id']?'selected':'' ?>>Tugas <?= (int)$task['nomor'] ?> &mdash; <?= sanitize($task['judul']) ?></option><?php endforeach ?></select><button class="btn btn-outline-primary">Pilih Target</button></div>
        </form>
        <p class="small text-muted">Template dan nilai yang diimpor hanya berlaku untuk satu tugas terpilih. Nilai yang sudah tersimpan akan dilewati; gunakan menu Remedial untuk perbaikan nilai.</p>
        <?php if($importTarget): ?>
        <div class="alert alert-info">Target impor: <strong>Tugas <?= (int)$importTarget['nomor'] ?> &mdash; <?= sanitize($importTarget['judul']) ?></strong></div>
        <a class="btn btn-outline-success mb-3" href="rekap_tugas.php?<?= sanitize(http_build_query(array_merge($context,['format'=>'template']))) ?>"><i class="fa-solid fa-file-excel me-1"></i>Unduh Template Tugas <?= (int)$importTarget['nomor'] ?></a>
        <form method="post" enctype="multipart/form-data" action="<?= sanitize(rt_inline_url($context)) ?>" class="border rounded-3 p-3 mb-3">
            <?php $hidden('rekap_tugas_import_preview'); ?>
            <label for="fileNilaiTugas" class="form-label fw-semibold">File nilai (.xlsx atau .xls, maksimal 5 MB)</label><input type="file" name="file_nilai" id="fileNilaiTugas" class="form-control mb-3" accept=".xlsx,.xls" required>
            <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-1"></i>Periksa dan Pratinjau Tugas <?= (int)$importTarget['nomor'] ?></button>
        </form>
        <?php else: ?><div class="alert alert-light border">Pilih target tugas untuk mengunduh template dan mengunggah nilai.</div><?php endif ?>
        <?php if($importPlan): ?>
        <div class="alert <?= $invalidRows?'alert-danger':'alert-info' ?>"><strong><?= $readyRows ?> nilai siap diimpor.</strong> <?= count($importPlan['rows'])-$readyRows ?> nilai dilewati atau perlu diperbaiki.<?php if($invalidRows): ?> Ada <?= $invalidRows ?> nilai tidak valid. Perbaiki file dan unggah ulang sebelum menyimpan.<?php endif ?></div>
        <div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead class="table-light"><tr><th>Baris Excel</th><th>Siswa</th><th>Tugas</th><th>Nilai Excel</th><th>Hasil Pemeriksaan</th></tr></thead><tbody><?php foreach($importPlan['rows'] as $row): ?><tr><td><?= (int)$row['row'] ?></td><td><?= sanitize($row['nama']) ?></td><td><?= sanitize($row['tugas']) ?></td><td><?= sanitize($row['raw']) ?></td><td class="<?= $row['ready']?'text-success':($row['invalid']?'text-danger':'text-muted') ?>"><?= sanitize($row['reason']) ?></td></tr><?php endforeach ?></tbody></table></div>
        <form method="post" action="<?= sanitize(rt_inline_url($context)) ?>"><?php $hidden('rekap_tugas_import_apply'); ?><input type="hidden" name="import_token" value="<?= sanitize($importPlan['token']) ?>"><button class="btn btn-success" <?= !$readyRows || $invalidRows?'disabled':'' ?>><i class="fa-solid fa-check me-1"></i>Konfirmasi Impor <?= $readyRows ?> Nilai</button></form>
        <?php endif ?>
    </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
</div></div></div>
<div class="modal fade" id="modalRemedialTugas" tabindex="-1" aria-labelledby="judulRemedialTugas" aria-hidden="true" data-open="<?= $panel==='remedial'?'1':'0' ?>"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form method="post" action="<?= sanitize(rt_inline_url($context)) ?>" class="modal-content" id="formRemedialTugas">
    <div class="modal-header"><div><h5 id="judulRemedialTugas" class="modal-title fw-bold">Remedial Nilai Tugas</h5><small class="text-muted"><?= sanitize($info['nama_kelas'].' · '.$info['nama_mapel']) ?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><?php $hidden('rekap_tugas_remedial'); ?>
        <div class="alert alert-info small">Nilai akhir memakai nilai tertinggi dari nilai awal dan remedial yang masih berlaku. Riwayat nilai awal dan setiap percobaan tetap tersimpan. Gunakan tombol Batalkan pada riwayat untuk meng-undo remedial yang sudah disimpan. Isi hanya siswa yang mengikuti remedial; sel kosong tidak diubah.</div>
        <?php if(!$remedialReady): ?><div class="alert alert-warning">Menu remedial belum diaktifkan. Hubungi admin.</div><?php endif ?>
        <p class="small text-muted">Daftar peserta hanya menampilkan siswa dengan nilai tugas di bawah 75. Siswa yang belum dinilai tidak ditampilkan. Riwayat siswa yang sudah tuntas tetap tersedia melalui Riwayat / Undo.</p>
        <label for="remedialPilihanTugas" class="form-label fw-semibold">Pilih tugas</label><select name="tugas_id" id="remedialPilihanTugas" class="form-select mb-3" required><?php foreach($data['tasks'] as $task): ?><option value="<?= (int)$task['id'] ?>" <?= !$task['selesai']?'disabled':'' ?> <?= (int)$task['id']===$remedialSelected && $task['selesai']?'selected':'' ?>>Tugas <?= (int)$task['nomor'] ?> · <?= sanitize($task['judul']) ?><?= !$task['selesai']?' (Masih Terbuka)':'' ?></option><?php endforeach ?></select>
        <div class="btn-group mb-3" role="group" aria-label="Daftar remedial"><button type="button" id="remedialFilterPeserta" class="btn btn-outline-primary active" aria-pressed="true">Perlu Remedial (&lt;75)</button><button type="button" id="remedialFilterRiwayat" class="btn btn-outline-secondary" aria-pressed="false">Riwayat / Undo</button></div>
        <a class="btn btn-warning mb-3" id="buatTugasRemedial" data-pengajaran="<?= (int)$context['rekap_pengajaran'] ?>" href="tugas_remedial.php?pengajaran_id=<?= (int)$context['rekap_pengajaran'] ?>&amp;tugas_id=<?= $remedialSelected ?>"><i class="fa-solid fa-plus me-1"></i>Buat / Kelola Tugas Remedial</a>
        <div class="table-responsive"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th>Siswa</th><th>Nilai Awal</th><th>Nilai Sekarang</th><th>Nilai Remedial</th><th>Catatan / Riwayat</th></tr></thead><tbody id="remedialDaftarSiswa"></tbody></table></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" id="simpanRemedialTugas" <?= !$remedialReady?'disabled':'' ?>><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Nilai Remedial</button></div>
</form></div></div>
<form method="post" action="<?= sanitize(rt_inline_url($context)) ?>" id="formBatalRemedialTugas" hidden><?php $hidden('rekap_tugas_remedial_cancel'); ?><input type="hidden" name="remedial_id" id="batalRemedialId"><input type="hidden" name="tugas_id" id="batalRemedialTugasId"><input type="hidden" name="alasan_batal" value="Dibatalkan oleh guru melalui riwayat remedial"></form>
<script type="application/json" id="rekapRemedialData"><?= json_encode(['tasks'=>$data['tasks'],'students'=>$data['students'],'ready'=>$remedialReady,'cancelReady'=>$cancelReady],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php } ?>
