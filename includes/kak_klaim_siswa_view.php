<?php
$klaim_success = $klaim_success ?? '';
$klaim_error = $klaim_error ?? '';
$acara_klaim = $acara_klaim ?? [];
?>
<section class="card kak-card mb-3 kak-claims" id="klaimAktivitas" aria-labelledby="klaimHeading">
<div class="card-body p-3 p-md-4">
    <div class="claim-section-heading"><div><span class="claim-eyebrow">KEGIATAN SEKOLAH</span><h2 class="h6 fw-bold mb-1" id="klaimHeading">Klaim Aktivitas Siswa</h2><p class="small text-muted mb-0">Kirim bukti keikutsertaan dan pantau persetujuan kegiatanmu.</p></div><span class="claim-section-icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></span></div>
    <?php if ($klaim_success): ?><div class="alert alert-success mt-3 mb-0" role="status"><?= sanitize($klaim_success) ?></div><?php endif ?>
    <?php if ($klaim_error): ?><div class="alert alert-danger mt-3 mb-0" role="alert"><?= sanitize($klaim_error) ?></div><?php endif ?>
    <?php if (!$acara_klaim): ?>
    <div class="claim-empty"><span><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span><strong>Belum ada acara untuk diklaim</strong><p>Acara wajib yang ditentukan sekolah akan muncul di sini.</p></div>
    <?php else: ?>
    <p class="claim-verification-note"><i class="fa-regular fa-circle-check" aria-hidden="true"></i>Merah: belum mengirim bukti atau perlu perbaikan. Kuning: menunggu verifikasi. Hijau: disetujui.</p>
    <div class="claim-slider-controls" hidden data-claim-slider-controls><span>Geser untuk kegiatan lainnya</span><div><button type="button" data-claim-prev aria-label="Kegiatan sebelumnya"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button><button type="button" data-claim-next aria-label="Kegiatan berikutnya"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></div></div>
    <div class="claim-event-list" tabindex="0" role="region" aria-label="Daftar kegiatan siswa">
    <?php foreach ($acara_klaim as $acara): $event_id=(int)$acara['id']; $status=$acara['status_klaim']; $can_claim=(!$acara['klaim_id'] || $status==='Ditolak') && $acara['status']==='Terbit'; ?>
        <article class="claim-event event-<?=$status==='Disetujui'?'approved':($status==='Menunggu'?'pending':'incomplete')?>">
            <div class="claim-event-heading"><div class="claim-event-title"><span class="claim-required">Acara wajib</span><h3><?= sanitize($acara['nama']) ?></h3><?php if($acara['status']!=='Terbit'): ?><small class="text-muted">Acara diarsipkan</small><?php endif ?></div></div>
            <?php if ($acara['deskripsi']): ?><p class="claim-description"><?= nl2br(sanitize($acara['deskripsi'])) ?></p><?php endif ?>
            <div class="claim-event-meta"><span><i class="fa-regular fa-calendar me-1" aria-hidden="true"></i><?= date('d/m/Y',strtotime($acara['tanggal_acara'])) ?></span><span><i class="fa-solid fa-infinity me-1" aria-hidden="true"></i>Tanpa batas waktu klaim</span></div>
            <?php if ($status): ?>
                <div class="claim-status-row"><span class="claim-status status-<?= strtolower($status) ?>"><?= ['Menunggu'=>'Menunggu verifikasi','Disetujui'=>'Disetujui','Ditolak'=>'Perlu perbaikan'][$status] ?></span><a class="claim-proof-link" href="kak_bukti.php?id=<?= (int)$acara['klaim_id'] ?>" target="_blank" rel="noopener"><i class="fa-regular fa-image" aria-hidden="true"></i> Lihat Bukti</a></div>
                <?php if ($acara['catatan_verifikasi']): ?><p class="claim-review-note"><strong>Catatan guru/admin:</strong> <?= nl2br(sanitize($acara['catatan_verifikasi'])) ?></p><?php endif ?>
            <?php endif ?>
            <?php if (!$status): ?><span class="claim-status status-ditolak">Belum mengirim bukti</span><?php endif ?>
            <?php if ($can_claim): ?>
                <details class="claim-upload"><summary><span><i class="fa-solid fa-camera" aria-hidden="true"></i><?=$status==='Ditolak'?'Kirim Ulang Bukti':'Kirim Bukti'?></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                    <form method="post" enctype="multipart/form-data" class="claim-form">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="acara_id" value="<?= $event_id ?>"><input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                        <p class="form-label fw-semibold small" id="bukti-label-<?= $event_id ?>">Foto bukti keikutsertaan <span class="text-danger">*</span></p>
                        <div class="claim-photo-sources" role="group" aria-labelledby="bukti-label-<?= $event_id ?>">
                            <label class="claim-photo-source claim-camera" for="kamera-<?= $event_id ?>"><input id="kamera-<?= $event_id ?>" type="file" name="bukti_kamera" accept="image/*" capture="environment" aria-describedby="bukti-help-<?= $event_id ?>" data-claim-camera><i class="fa-solid fa-camera" aria-hidden="true"></i><span>Ambil Foto<small>Buka kamera</small></span></label>
                            <label class="claim-photo-source" for="bukti-<?= $event_id ?>"><input id="bukti-<?= $event_id ?>" type="file" name="bukti" accept="image/jpeg,image/png,image/webp" aria-describedby="bukti-help-<?= $event_id ?>" data-claim-file required><i class="fa-regular fa-images" aria-hidden="true"></i><span>Pilih Foto<small>Dari galeri / berkas</small></span></label>
                        </div>
                        <p class="claim-file-help" id="bukti-help-<?= $event_id ?>">Foto sertifikat atau keikutsertaan. JPG, PNG, WebP · Maks. 5 MB.</p>
                        <div class="claim-photo-result" data-claim-result hidden><img class="claim-preview" alt="Pratinjau foto bukti" data-claim-preview hidden><div class="claim-photo-caption"><span data-claim-filename></span><button type="button" data-claim-remove aria-label="Hapus foto bukti"><i class="fa-regular fa-trash-can me-1" aria-hidden="true"></i>Hapus</button></div></div>
                        <p class="text-danger small" data-claim-error role="alert" hidden></p>
                        <label class="form-label fw-semibold small" for="catatan-<?= $event_id ?>">Catatan <span class="text-muted fw-normal">(opsional)</span></label>
                        <textarea class="form-control mb-3" id="catatan-<?= $event_id ?>" name="catatan" maxlength="500" rows="2" placeholder="Tambahkan keterangan tentang bukti Anda"></textarea>
                        <button class="btn btn-primary claim-submit" type="submit">Kirim Klaim <i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></button><p class="claim-submit-note">Bukti akan diperiksa guru/admin sebelum aktivitas disetujui.</p>
                    </form>
                </details>
            <?php elseif (!$status): ?>
                <div class="claim-closed">Acara belum diterbitkan</div>
            <?php endif ?>
        </article>
    <?php endforeach ?>
    </div>
    <?php endif ?>
</div>
</section>
<script src="../assets/js/kak-klaim.js?v=<?=filemtime(__DIR__.'/../assets/js/kak-klaim.js')?>" defer></script>
