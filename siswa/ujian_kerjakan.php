<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

check_access([3]);
$db = Database::getInstance();

$ujian_id = intval($_GET['id'] ?? 0);

$stmt_s = $db->prepare("SELECT id,kelas_id FROM siswa WHERE user_id = ?");
$stmt_s->execute([$_SESSION['user_id']]);
$siswa = $stmt_s->fetch();
$siswa_id = $siswa['id'] ?? 0;

// Ujian hanya dapat dibuka oleh siswa pada kelas yang benar.
$stmt_u = $db->prepare("SELECT u.* FROM ujian u JOIN pengajaran p ON p.id=u.pengajaran_id WHERE u.id=? AND p.kelas_id=?");
$stmt_u->execute([$ujian_id,$siswa['kelas_id']??0]);
$ujian = $stmt_u->fetch();

if (!$ujian) {
    http_response_code(404);exit("Ujian tidak ditemukan untuk kelas Anda.");
}
$sekarang=time();$jadwal_mulai=strtotime($ujian['waktu_mulai']);$jadwal_selesai=strtotime($ujian['waktu_selesai']);
if($sekarang<$jadwal_mulai){exit("Ujian belum dimulai.");}
if($sekarang>$jadwal_selesai){exit("Jadwal ujian telah berakhir.");}
$stmt_count=$db->prepare('SELECT COUNT(*) FROM soal_ujian WHERE ujian_id=?');$stmt_count->execute([$ujian_id]);
if(!(int)$stmt_count->fetchColumn()){exit('Ujian belum memiliki soal.');}

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
$sisa_detik = min($durasi_detik - $waktu_terpakai,$jadwal_selesai-time());

if ($sisa_detik <= 0) {
    $sisa_detik = 0;
}

// Ambil Soal
$order_by = $ujian['acak_soal'] ? "RAND(".(int)$sesi_id.")" : "su.id ASC";
$stmt_soal = $db->prepare("
    SELECT su.*, js.jawaban_pg, js.jawaban_esai 
    FROM soal_ujian su
    LEFT JOIN jawaban_siswa js ON (js.soal_id = su.id AND js.sesi_ujian_id = ?)
    WHERE su.ujian_id = ? ORDER BY $order_by
");
$stmt_soal->execute([$sesi_id, $ujian_id]);
$soal_list = $stmt_soal->fetchAll();
$total_soal = count($soal_list);
$terjawab = count(array_filter($soal_list, static fn($soal) => $soal['jawaban_pg'] !== null || !empty($soal['jawaban_esai'])));
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    body { background:#f3f6fb; }
    .exam-shell { max-width:1440px; margin:0 auto; padding:24px 28px 40px; }
    .exam-header { position:sticky; top:0; z-index:1015; padding:18px 20px; margin-bottom:22px; border:1px solid #e5eaf2; border-radius:20px; background:rgba(255,255,255,.96); box-shadow:0 8px 28px rgba(15,23,42,.08); backdrop-filter:blur(12px); }
    .exam-eyebrow { color:#2563eb; font-size:.73rem; font-weight:750; letter-spacing:.08em; text-transform:uppercase; }
    .exam-title { color:#172033; font-size:1.35rem; line-height:1.3; }
    .exam-warning { color:#64748b; font-size:.78rem; }
    .exam-timer { min-width:205px; padding:12px 16px; border-radius:14px; color:#fff; background:linear-gradient(135deg,#dc2626,#ef4444); box-shadow:0 7px 18px rgba(220,38,38,.22); text-align:center; }
    .exam-timer small { display:block; margin-bottom:2px; color:rgba(255,255,255,.8); font-size:.65rem; text-transform:uppercase; letter-spacing:.08em; }
    .exam-timer #timer { font-variant-numeric:tabular-nums; letter-spacing:.04em; }
    .exam-progress { height:7px; margin-top:14px; border-radius:999px; background:#e8edf5; overflow:hidden; }
    .exam-progress-bar { height:100%; border-radius:inherit; background:linear-gradient(90deg,#2563eb,#22c55e); transition:width .25s ease; }
    .question-card { min-height:480px; padding:30px!important; border:1px solid #e5eaf2!important; border-radius:21px!important; box-shadow:0 8px 28px rgba(15,23,42,.06)!important; }
    .question-number { padding:8px 12px; border-radius:10px; font-size:.76rem; letter-spacing:.02em; }
    .question-weight { padding:7px 10px; border-radius:9px; background:#f1f5f9; color:#64748b; font-size:.75rem; }
    .question-text { color:#172033; font-size:1.08rem!important; line-height:1.75; overflow-wrap:anywhere; }
    .answer-list { display:grid; gap:10px; }
    .answer-option { position:relative; display:flex!important; align-items:flex-start; gap:12px; min-height:58px; padding:14px 16px!important; border:1px solid #dfe6ef!important; border-radius:14px!important; background:#fff; cursor:pointer; transition:border-color .18s ease,background .18s ease,transform .18s ease,box-shadow .18s ease; }
    .answer-option:hover { border-color:#93b4f8!important; background:#f8fbff; transform:translateY(-1px); }
    .answer-option:has(input:checked) { border-color:#2563eb!important; background:#eff6ff; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
    .answer-option input { position:absolute; opacity:0; pointer-events:none; }
    .answer-letter { width:30px; height:30px; flex:0 0 30px; display:grid; place-items:center; border-radius:9px; color:#475569; background:#eef2f7; font-weight:750; transition:.18s ease; }
    .answer-option:has(input:checked) .answer-letter { color:#fff; background:#2563eb; }
    .answer-copy { padding-top:3px; color:#334155; line-height:1.5; overflow-wrap:anywhere; }
    .question-actions { padding-top:18px; border-top:1px solid #edf1f5; }
    .question-actions .btn { min-height:42px; padding-inline:16px; border-radius:11px; font-weight:650; }
    .question-textarea { min-height:170px; border-radius:14px; padding:15px; line-height:1.6; }
    .exam-nav-card { position:sticky; top:132px; padding:20px!important; border:1px solid #e5eaf2!important; border-radius:19px!important; box-shadow:0 8px 26px rgba(15,23,42,.06)!important; }
    .exam-nav-scroll { max-height:390px; padding:3px 5px 8px 3px; overflow-y:auto; overscroll-behavior:contain; scrollbar-width:thin; scrollbar-color:#94a3b8 #eef2f7; }
    .exam-nav-scroll::-webkit-scrollbar { width:7px; }.exam-nav-scroll::-webkit-scrollbar-track { background:#eef2f7; border-radius:99px; }.exam-nav-scroll::-webkit-scrollbar-thumb { background:#94a3b8; border-radius:99px; }
    .exam-nav-grid { display:grid!important; grid-template-columns:repeat(5,minmax(42px,1fr)); gap:8px!important; }
    .exam-nav-grid .nav-btn { width:100%!important; aspect-ratio:1; border-radius:10px; font-weight:700; }
    .exam-nav-grid .nav-btn.is-current { box-shadow:0 0 0 3px rgba(37,99,235,.2); border-color:#2563eb; }
    .exam-legend { display:flex; flex-wrap:wrap; gap:12px; margin-top:16px; padding-top:14px; border-top:1px solid #edf1f5; color:#64748b; font-size:.68rem; }
    .legend-dot { width:9px; height:9px; display:inline-block; margin-right:4px; border-radius:3px; }
    .unanswered-alert { margin-top:14px; padding:11px 12px; border:1px solid #fecaca; border-radius:11px; color:#b91c1c; background:#fef2f2; font-size:.72rem; font-weight:650; cursor:pointer; }
    @media(max-width:991.98px){.exam-shell{padding:18px}.exam-header{top:8px}.exam-nav-card{position:static}.question-card{min-height:0}.exam-nav-grid{grid-template-columns:repeat(8,minmax(38px,1fr))}}
    @media(max-width:575.98px){
        .exam-shell{padding:10px 10px 28px}.exam-header{top:6px;padding:14px;border-radius:16px;margin-bottom:14px}.exam-header-main{align-items:flex-start!important}.exam-title{font-size:1rem}.exam-warning{display:none}.exam-timer{min-width:126px;padding:9px 8px;border-radius:11px}.exam-timer #timer{font-size:1rem!important}.exam-timer small{font-size:.57rem}.exam-progress{margin-top:11px}
        .question-card{padding:17px!important;border-radius:16px!important}.question-text{font-size:.98rem!important;line-height:1.65}.question-weight{font-size:.68rem}.answer-option{min-height:54px;padding:12px!important;border-radius:12px!important}.answer-copy{font-size:.88rem}.question-actions{gap:8px}.question-actions .btn{min-height:42px;padding:8px 11px;font-size:.76rem}
        .exam-nav-card{padding:16px!important;border-radius:16px!important}.exam-nav-grid{grid-template-columns:repeat(5,minmax(40px,1fr))}
    }
</style>
<div class="exam-shell">
    <header class="exam-header">
    <div class="row g-3 align-items-center exam-header-main">
        <div class="col">
            <span class="exam-eyebrow"><i class="fa-solid fa-shield-halved me-1"></i>CBT Siswa</span>
            <h1 class="exam-title fw-bold mt-1 mb-1"><?= sanitize($ujian['nama_ujian']) ?></h1>
            <p class="exam-warning mb-0"><i class="fa-solid fa-circle-info me-1"></i>Jawaban tersimpan otomatis. Jangan menutup halaman saat ujian berlangsung.</p>
        </div>
        <div class="col-auto">
            <div class="exam-timer">
                <small><i class="fa-regular fa-clock me-1"></i>Sisa waktu</small>
                <span id="timer" class="fw-bold fs-5">00:00:00</span>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-2 mt-3 small"><span class="text-muted">Progres jawaban <span id="saveStatus" class="ms-2 text-success"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Tersimpan</span></span><strong><span id="answeredCount"><?= $terjawab ?></span> / <?= $total_soal ?> soal</strong></div>
    <div class="exam-progress"><div class="exam-progress-bar" id="examProgressBar" style="width:<?= $total_soal ? ($terjawab/$total_soal)*100 : 0 ?>%"></div></div>
    </header>

    <div class="row g-4 align-items-start">
        <!-- Soal Section -->
        <div class="col-lg-9">
            <?php foreach($soal_list as $idx => $s): ?>
                <div class="card question-card mb-3 soal-item" id="soal-box-<?= $idx + 1 ?>" style="<?= $idx > 0 ? 'display:none;' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
                        <span class="badge bg-primary question-number">Soal <?= $idx + 1 ?> dari <?= $total_soal ?> &middot; <?= sanitize($s['tipe_soal']) ?></span>
                        <span class="question-weight"><i class="fa-solid fa-star me-1"></i>Bobot <?= (int)$s['bobot'] ?></span>
                    </div>

                    <div class="question-text mb-4"><?= nl2br(sanitize($s['pertanyaan'])) ?></div>

                    <?php if($s['tipe_soal'] === 'PG'): ?>
                        <div class="answer-list mb-4">
                            <?php foreach(['A' => $s['opsi_a'], 'B' => $s['opsi_b'], 'C' => $s['opsi_c'], 'D' => $s['opsi_d'], 'E' => $s['opsi_e']] as $key => $val): if(!$val) continue; ?>
                                <label class="answer-option">
                                    <input type="radio" name="soal_<?= $s['id'] ?>" value="<?= $key ?>" <?= ($s['jawaban_pg'] === $key) ? 'checked' : '' ?> onchange="simpanJawaban(<?= $s['id'] ?>, 'PG', '<?= $key ?>', <?= $idx + 1 ?>)">
                                    <span class="answer-letter"><?= $key ?></span><span class="answer-copy"><?= sanitize($val) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <textarea class="form-control question-textarea exam-essay-answer" rows="5" data-soal-id="<?= (int)$s['id'] ?>" data-soal-no="<?= $idx + 1 ?>" placeholder="Tuliskan jawaban esai Anda dengan jelas..." oninput="jadwalkanSimpanEsai(<?= $s['id'] ?>, this.value, <?= $idx + 1 ?>)" onchange="simpanJawaban(<?= $s['id'] ?>, 'ESAI', this.value, <?= $idx + 1 ?>)"><?= sanitize($s['jawaban_esai'] ?? '') ?></textarea>
                    <?php endif; ?>

                    <div class="question-actions d-flex justify-content-between mt-4">
                        <?php if($idx > 0): ?>
                            <button class="btn btn-outline-secondary" onclick="showSoal(<?= $idx ?>)"><i class="fa-solid fa-arrow-left me-1"></i> Sebelumnya</button>
                        <?php else: ?><div></div><?php endif; ?>

                        <?php if($idx < count($soal_list) - 1): ?>
                            <button class="btn btn-primary" onclick="showSoal(<?= $idx + 2 ?>)">Selanjutnya <i class="fa-solid fa-arrow-right ms-1"></i></button>
                        <?php else: ?>
                            <button class="btn btn-success" onclick="confirmFinish()"><i class="fa-solid fa-circle-check me-1"></i> Selesaikan Ujian</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Navigasi Nomor Soal -->
        <div class="col-lg-3">
            <aside class="card exam-nav-card">
                <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-bold mb-0">Navigasi Soal</h2><span class="badge bg-light text-dark" id="currentQuestionLabel">No. 1</span></div>
                <div class="exam-nav-scroll"><div class="exam-nav-grid">
                    <?php foreach($soal_list as $idx => $s): 
                        $is_answered = ($s['jawaban_pg'] !== null || !empty($s['jawaban_esai']));
                    ?>
                        <button class="btn btn-sm <?= $is_answered ? 'btn-success' : 'btn-outline-secondary' ?> nav-btn <?= $idx===0?'is-current':'' ?>" id="nav-btn-<?= $idx + 1 ?>" onclick="showSoal(<?= $idx + 1 ?>)" aria-label="Buka soal nomor <?= $idx + 1 ?>">
                            <?= $idx + 1 ?>
                        </button>
                    <?php endforeach; ?>
                </div></div>
                <div class="exam-legend"><span><i class="legend-dot bg-success"></i>Terjawab</span><span><i class="legend-dot bg-light border"></i>Belum dijawab</span></div>
                <button type="button" class="unanswered-alert w-100 text-start" id="unansweredAlert" onclick="goToFirstUnanswered()"><i class="fa-solid fa-triangle-exclamation me-1"></i><span id="unansweredCount"><?= $total_soal-$terjawab ?></span> soal belum dijawab</button>
            </aside>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let totalDetik = <?= $sisa_detik ?>;
let sesiId = <?= $sesi_id ?>;
const csrfToken = <?= json_encode($_SESSION['csrf_token'],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const totalSoal = <?= $total_soal ?>;
const pendingStorageKey = 'lms_exam_pending_' + sesiId;
const essayTimers = {};

function readPendingAnswers() {
    try { return JSON.parse(localStorage.getItem(pendingStorageKey) || '{}'); }
    catch (e) { return {}; }
}

function writePendingAnswer(soalId, tipe, jawaban, no) {
    const pending = readPendingAnswers();
    pending[soalId] = {tipe: tipe, jawaban: String(jawaban), no: no};
    localStorage.setItem(pendingStorageKey, JSON.stringify(pending));
}

function removePendingAnswer(soalId) {
    const pending = readPendingAnswers();
    delete pending[soalId];
    if (Object.keys(pending).length) localStorage.setItem(pendingStorageKey, JSON.stringify(pending));
    else localStorage.removeItem(pendingStorageKey);
}

function setSaveStatus(status) {
    const el = $('#saveStatus');
    if (status === 'saving') el.attr('class','ms-2 text-warning').html('<i class="fa-solid fa-spinner fa-spin me-1"></i>Menyimpan...');
    else if (status === 'offline') el.attr('class','ms-2 text-danger').html('<i class="fa-solid fa-cloud-bolt me-1"></i>Tersimpan di perangkat');
    else el.attr('class','ms-2 text-success').html('<i class="fa-solid fa-cloud-arrow-up me-1"></i>Tersimpan');
}

function renderTimer() {
    const jam = Math.floor(totalDetik / 3600);
    const menit = Math.floor((totalDetik % 3600) / 60);
    const detik = totalDetik % 60;
    $('#timer').text(
        String(jam).padStart(2, '0') + ':' +
        String(menit).padStart(2, '0') + ':' +
        String(detik).padStart(2, '0')
    );
}

function updateProgress() {
    const terjawab = $('.nav-btn.btn-success').length;
    const belum = Math.max(0, totalSoal - terjawab);
    $('#answeredCount').text(terjawab);
    $('#unansweredCount').text(belum);
    $('#unansweredAlert').toggleClass('d-none', belum === 0);
    $('#examProgressBar').css('width', totalSoal > 0 ? ((terjawab / totalSoal) * 100) + '%' : '0%');
}

renderTimer();
updateProgress();

// Countdown Timer Engine
let countdown = setInterval(function() {
    if (totalDetik <= 0) {
        clearInterval(countdown);
        Swal.fire('Waktu Habis!', 'Ujian secara otomatis dikumpulkan.', 'warning').then(() => {
            finishUjian(true);
        });
    } else {
        totalDetik--;
        renderTimer();
    }
}, 1000);

function showSoal(no) {
    $('.soal-item').hide();
    const target = $('#soal-box-' + no);
    target.show();
    $('.nav-btn').removeClass('is-current');
    $('#nav-btn-' + no).addClass('is-current');
    $('#currentQuestionLabel').text('No. ' + no);
    if (window.innerWidth < 992 && target.length) {
        target[0].scrollIntoView({behavior: 'smooth', block: 'start'});
    }
}

function simpanJawaban(soalId, tipe, jawaban, no) {
    writePendingAnswer(soalId, tipe, jawaban, no);
    setSaveStatus('saving');
    $.ajax({
        url: 'ujian_action.php?action=save_jawaban',
        method: 'POST',
        dataType: 'json',
        timeout: 12000,
        data: {
        sesi_id: sesiId,
        soal_id: soalId,
        tipe: tipe,
        jawaban: jawaban,
        csrf_token: csrfToken
        }
    }).done(function(res) {
        if(res.status === 'success') {
            removePendingAnswer(soalId);
            setSaveStatus('saved');
            const terisi = tipe === 'PG' || String(jawaban).trim() !== '';
            $('#nav-btn-' + no).toggleClass('btn-success', terisi).toggleClass('btn-outline-secondary', !terisi);
            updateProgress();
        }
    }).fail(function() {
        setSaveStatus('offline');
    });
}

function jadwalkanSimpanEsai(soalId, jawaban, no) {
    writePendingAnswer(soalId, 'ESAI', jawaban, no);
    setSaveStatus('saving');
    clearTimeout(essayTimers[soalId]);
    essayTimers[soalId] = setTimeout(function () {
        simpanJawaban(soalId, 'ESAI', jawaban, no);
    }, 700);
}

function retryPendingAnswers() {
    const pending = readPendingAnswers();
    Object.keys(pending).forEach(function(soalId) {
        const item = pending[soalId];
        if (item.tipe === 'PG') {
            $('input[name="soal_' + soalId + '"][value="' + item.jawaban + '"]').prop('checked', true);
        } else {
            $('.exam-essay-answer[data-soal-id="' + soalId + '"]').val(item.jawaban);
        }
        simpanJawaban(parseInt(soalId,10), item.tipe, item.jawaban, item.no);
    });
}

window.addEventListener('online', retryPendingAnswers);
window.addEventListener('beforeunload', function () {
    document.querySelectorAll('.exam-essay-answer').forEach(function(textarea) {
        const soalId = parseInt(textarea.dataset.soalId,10);
        const no = parseInt(textarea.dataset.soalNo,10);
        writePendingAnswer(soalId, 'ESAI', textarea.value, no);
        if (navigator.sendBeacon) {
            const data = new FormData();
            data.append('sesi_id', sesiId);
            data.append('soal_id', soalId);
            data.append('tipe', 'ESAI');
            data.append('jawaban', textarea.value);
            data.append('csrf_token', csrfToken);
            navigator.sendBeacon('ujian_action.php?action=save_jawaban', data);
        }
    });
});

if (Object.keys(readPendingAnswers()).length) {
    setTimeout(retryPendingAnswers, 500);
}

function getUnansweredNumbers() {
    return $('.nav-btn').filter(function () {
        return !$(this).hasClass('btn-success');
    }).map(function () {
        return parseInt($(this).text(), 10);
    }).get();
}

function goToFirstUnanswered() {
    const belum = getUnansweredNumbers();
    if (belum.length) showSoal(belum[0]);
}

function confirmFinish() {
    const belum = getUnansweredNumbers();
    if (belum.length) {
        const daftar = belum.slice(0, 15).join(', ') + (belum.length > 15 ? ' dan ' + (belum.length - 15) + ' lainnya' : '');
        Swal.fire({
            title: 'Ujian Belum Lengkap!',
            html: '<strong>' + belum.length + ' soal belum dijawab.</strong><br><span class="text-muted">Nomor: ' + daftar + '</span><br><br>Semua soal wajib dijawab sebelum ujian dapat dikirim.',
            icon: 'error',
            confirmButtonText: 'Jawab Soal yang Kosong',
            confirmButtonColor: '#dc2626',
            allowOutsideClick: false
        }).then(() => showSoal(belum[0]));
        return;
    }
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

function finishUjian(waktuHabis = false) {
    $.post('ujian_action.php?action=finish_ujian', { sesi_id: sesiId, waktu_habis: waktuHabis ? 1 : 0, csrf_token: csrfToken }, function(res) {
        if(res.status === 'success') {
            localStorage.removeItem(pendingStorageKey);
            Swal.fire('Ujian Selesai!', res.message, 'success').then(() => {
                window.location.href = 'index.php';
            });
        } else {
            Swal.fire({
                title: 'Belum Dapat Dikirim',
                text: res.message || 'Masih ada soal yang belum dijawab.',
                icon: 'error',
                confirmButtonText: 'Periksa Jawaban'
            }).then(goToFirstUnanswered);
        }
    }, 'json');
}
</script>
