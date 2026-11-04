<?php // Ringkasan yang sama untuk siswa dan pengelola; $summary berasal dari kps_summary(). ?>
<?php $warna_sisa_poin=$summary['persentase']>=100?'#22c55e':($summary['persentase']>=75?'#a3e635':($summary['persentase']>=50?'#facc15':($summary['persentase']>=25?'#fb923c':'#f87171'))); ?>
<section class="kps-balance mb-3" aria-labelledby="saldoPoin">
    <div class="kps-balance-copy">
        <h2 class="h6 mb-1" id="saldoPoin">Sisa Kredit Poin Siswa</h2>
        <strong class="kps-total"><?= number_format($summary['saldo'],0,',','.') ?></strong><span>poin</span>
        <div>Saldo awal: <strong><?= number_format($summary['awal'],0,',','.') ?> poin</strong></div>
        <p class="small mb-0 mt-2">Poin berkurang setiap kali ada pelanggaran yang dicatat oleh guru atau admin.</p>
    </div>
    <div class="kps-remaining">
        <div class="kps-remaining-label"><span>Sisa poin kedisiplinan</span><strong><?= $summary['persentase'] ?>%</strong></div>
        <div class="kps-progress mb-3" role="progressbar" aria-label="Sisa kredit poin" aria-valuenow="<?= $summary['saldo'] ?>" aria-valuemin="0" aria-valuemax="<?= $summary['awal'] ?>"><div class="kps-progress-bar" style="width:<?= $summary['persentase'] ?>%;background-color:<?= $warna_sisa_poin ?>"></div></div>
        <?php if ($summary['jumlah'] === 0): ?>
            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Belum ada pelanggaran tercatat</span>
        <?php elseif ($summary['saldo'] === 0): ?>
            <span class="badge bg-danger">Kredit poin sudah habis</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">Total pengurangan <?= number_format($summary['keluar'],0,',','.') ?> poin</span>
        <?php endif ?>
    </div>
</section>
