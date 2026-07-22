<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([3]);
$db = Database::getInstance();

$ujian_id = intval($_GET['id'] ?? 0);

$stmt_s = $db->prepare("SELECT id FROM siswa WHERE user_id = ?");
$stmt_s->execute([$_SESSION['user_id']]);
$siswa = $stmt_s->fetch();
$siswa_id = $siswa['id'] ?? 0;

// Ambil Detail Ujian
$stmt_u = $db->prepare("SELECT * FROM ujian WHERE id = ?");
$stmt_u->execute([$ujian_id]);
$ujian = $stmt_u->fetch();

if (!$ujian) {
    die("Ujian tidak ditemukan.");
}

// Inisialisasi atau Dapatkan Sesi Ujian
$stmt_sesi = $db->prepare("SELECT * FROM sesi_ujian WHERE ujian_id = ? AND siswa_id = ?");
$stmt_sesi->execute([$ujian_id, $siswa_id]);
$sesi = $stmt_sesi->fetch();

if (!$sesi) {
    $stmt_ins = $db->prepare("INSERT INTO sesi_ujian (ujian_id, siswa_id, waktu_mulai, status) VALUES (?, ?, NOW(), 'Berlangsung')");
    $stmt_ins->execute([$ujian_id, $siswa_id]);
    $sesi_id = $db->lastInsertId();
    $waktu_mulai = time();
} else {
    if ($sesi['status'] === 'Selesai') {
        die("Anda telah menyelesaikan ujian ini.");
    }
    $sesi_id = $sesi['id'];
    $waktu_mulai = strtotime($sesi['waktu_mulai']);
}

// Hitung Sisa Waktu Detik
$durasi_detik = $ujian['durasi_menit'] * 60;
$waktu_terpakai = time() - $waktu_mulai;
$sisa_detik = $durasi_detik - $waktu_terpakai;

if ($sisa_detik <= 0) {
    $sisa_detik = 0;
}

// Ambil Soal
$order_by = $ujian['acak_soal'] ? "RAND()" : "su.id ASC";
$stmt_soal = $db->prepare("
    SELECT su.*, js.jawaban_pg, js.jawaban_esai 
    FROM soal_ujian su
    LEFT JOIN jawaban_siswa js ON (js.soal_id = su.id AND js.sesi_ujian_id = ?)
    WHERE su.ujian_id = ? ORDER BY $order_by
");
$stmt_soal->execute([$sesi_id, $ujian_id]);
$soal_list = $stmt_soal->fetchAll();
?>

<div class="container py-4">
    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <h4 class="fw-bold mb-1"><?= sanitize($ujian['nama_ujian']) ?></h4>
            <p class="text-muted small">Jangan menutup atau memuat ulang halaman saat ujian berlangsung.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="p-3 bg-danger text-white rounded-3 d-inline-block">
                <i class="fa-solid fa-clock me-2"></i> Sisa Waktu: <span id="timer" class="fw-bold fs-5">00:00:00</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Soal Section -->
        <div class="col-md-8">
            <?php foreach($soal_list as $idx => $s): ?>
                <div class="card border-0 shadow-sm p-4 mb-3 soal-item" id="soal-box-<?= $idx + 1 ?>" style="<?= $idx > 0 ? 'display:none;' : '' ?>">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="badge bg-primary fs-6">Soal No. <?= $idx + 1 ?> (<?= $s['tipe_soal'] ?>)</span>
                        <span class="text-muted small">Bobot: <?= $s['bobot'] ?></span>
                    </div>

                    <p class="fs-6 mb-4"><?= nl2br(sanitize($s['pertanyaan'])) ?></p>

                    <?php if($s['tipe_soal'] === 'PG'): ?>
                        <div class="list-group mb-3">
                            <?php foreach(['A' => $s['opsi_a'], 'B' => $s['opsi_b'], 'C' => $s['opsi_c'], 'D' => $s['opsi_d'], 'E' => $s['opsi_e']] as $key => $val): if(!$val) continue; ?>
                                <label class="list-group-item list-group-item-action">
                                    <input type="radio" name="soal_<?= $s['id'] ?>" value="<?= $key ?>" <?= ($s['jawaban_pg'] === $key) ? 'checked' : '' ?> onchange="simpanJawaban(<?= $s['id'] ?>, 'PG', '<?= $key ?>', <?= $idx + 1 ?>)">
                                    <strong class="ms-2"><?= $key ?>.</strong> <?= sanitize($val) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <textarea class="form-control" rows="4" placeholder="Tuliskan jawaban esai Anda..." onchange="simpanJawaban(<?= $s['id'] ?>, 'ESAI', this.value, <?= $idx + 1 ?>)"><?= sanitize($s['jawaban_esai'] ?? '') ?></textarea>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mt-4">
                        <?php if($idx > 0): ?>
                            <button class="btn btn-secondary btn-sm" onclick="showSoal(<?= $idx ?>)"><i class="fa-solid fa-arrow-left me-1"></i> Sebelumnya</button>
                        <?php else: ?><div></div><?php endif; ?>

                        <?php if($idx < count($soal_list) - 1): ?>
                            <button class="btn btn-primary btn-sm" onclick="showSoal(<?= $idx + 2 ?>)">Selanjutnya <i class="fa-solid fa-arrow-right ms-1"></i></button>
                        <?php else: ?>
                            <button class="btn btn-success btn-sm" onclick="confirmFinish()"><i class="fa-solid fa-check me-1"></i> Selesaikan Ujian</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Navigasi Nomor Soal -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3">
                <h6 class="fw-bold mb-3">Navigasi Soal</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach($soal_list as $idx => $s): 
                        $is_answered = ($s['jawaban_pg'] !== null || !empty($s['jawaban_esai']));
                    ?>
                        <button class="btn btn-sm <?= $is_answered ? 'btn-success' : 'btn-outline-secondary' ?> nav-btn" id="nav-btn-<?= $idx + 1 ?>" style="width: 45px;" onclick="showSoal(<?= $idx + 1 ?>)">
                            <?= $idx + 1 ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let totalDetik = <?= $sisa_detik ?>;
let sesiId = <?= $sesi_id ?>;

// Countdown Timer Engine
let countdown = setInterval(function() {
    if (totalDetik <= 0) {
        clearInterval(countdown);
        Swal.fire('Waktu Habis!', 'Ujian secara otomatis dikumpulkan.', 'warning').then(() => {
            finishUjian();
        });
    } else {
        totalDetik--;
        let jam = Math.floor(totalDetik / 3600);
        let menit = Math.floor((totalDetik % 3600) / 60);
        let detik = totalDetik % 60;

        $('#timer').text(
            (jam < 10 ? "0" + jam : jam) + ":" +
            (menit < 10 ? "0" + menit : menit) + ":" +
            (detik < 10 ? "0" + detik : detik)
        );
    }
}, 1000);

function showSoal(no) {
    $('.soal-item').hide();
    $('#soal-box-' + no).show();
}

function simpanJawaban(soalId, tipe, jawaban, no) {
    $.post('ujian_action.php?action=save_jawaban', {
        sesi_id: sesiId,
        soal_id: soalId,
        tipe: tipe,
        jawaban: jawaban
    }, function(res) {
        if(res.status === 'success') {
            $('#nav-btn-' + no).removeClass('btn-outline-secondary').addClass('btn-success');
        }
    }, 'json');
}

function confirmFinish() {
    Swal.fire({
        title: 'Selesaikan Ujian?',
        text: "Pastikan seluruh pertanyaan telah Anda jawab dengan benar.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim Sekarang'
    }).then((result) => {
        if (result.isConfirmed) {
            finishUjian();
        }
    });
}

function finishUjian() {
    $.post('ujian_action.php?action=finish_ujian', { sesi_id: sesiId }, function(res) {
        if(res.status === 'success') {
            Swal.fire('Ujian Selesai!', res.message, 'success').then(() => {
                window.location.href = 'index.php';
            });
        }
    }, 'json');
}
</script>