<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);
$db = Database::getInstance();

$stmt_guru = $db->prepare('SELECT id FROM guru WHERE user_id = ?');
$stmt_guru->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt_guru->fetchColumn() ?: 0);
proses_penutupan_absensi_otomatis($db);

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
        redirect('absensi.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'buka') {
        $pengajaran_id = (int)($_POST['pengajaran_id'] ?? 0);
        $pengajaran_ids = is_array($_POST['pengajaran_ids'] ?? null)
            ? array_values(array_unique(array_filter(
                array_map('intval', $_POST['pengajaran_ids']),
                static fn(int $id): bool => $id > 0
            )))
            : array_filter([$pengajaran_id]);
        $pertemuan_ke = (int)($_POST['pertemuan_ke'] ?? 0);
        $tanggal = trim($_POST['tanggal'] ?? '');
        $waktu_buka_input = trim($_POST['waktu_buka'] ?? '');
        $waktu_tutup_input = trim($_POST['waktu_tutup'] ?? '');

        $waktu_buka = DateTime::createFromFormat('Y-m-d\TH:i', $waktu_buka_input);
        $waktu_tutup = DateTime::createFromFormat('Y-m-d\TH:i', $waktu_tutup_input);
        $tanggal_valid = DateTime::createFromFormat('Y-m-d', $tanggal);

        if (!$pengajaran_ids || $pertemuan_ke < 1 || $pertemuan_ke > 20 || !$tanggal_valid || !$waktu_buka || !$waktu_tutup) {
            $_SESSION['flash_error'] = 'Data sesi absensi belum lengkap atau tidak valid.';
            redirect('absensi.php');
        }

        if ($waktu_buka >= $waktu_tutup || $tanggal !== $waktu_buka->format('Y-m-d')) {
            $_SESSION['flash_error'] = 'Waktu tutup harus setelah waktu buka pada tanggal yang sama.';
            redirect('absensi.php');
        }

        $placeholder_pengajaran = implode(',', array_fill(0, count($pengajaran_ids), '?'));
        $stmt_milik = $db->prepare("SELECT id,mapel_id,tahun_ajaran,semester FROM pengajaran WHERE id IN ($placeholder_pengajaran) AND guru_id=?");
        $stmt_milik->execute([...$pengajaran_ids, $guru_id]);
        $pengajaran_valid = $stmt_milik->fetchAll();
        if (count($pengajaran_valid) !== count($pengajaran_ids)) {
            $_SESSION['flash_error'] = 'Salah satu pengajaran tidak valid atau bukan milik Anda.';
            redirect('absensi.php');
        }
        $kelompok_valid = array_unique(array_map(
            static fn(array $item): string => $item['mapel_id'] . '|' . $item['tahun_ajaran'] . '|' . $item['semester'],
            $pengajaran_valid
        ));
        if (count($kelompok_valid) !== 1) {
            $_SESSION['flash_error'] = 'Kelas gabungan harus berasal dari mapel, semester, dan tahun ajaran yang sama.';
            redirect('absensi.php');
        }

        $pertemuan_sudah_ada = false;
        try {
            $db->beginTransaction();

            $stmt_pertemuan = $db->prepare(
                'SELECT 1
                 FROM sesi_absensi
                 WHERE pengajaran_id = ? AND pertemuan_ke = ?
                 LIMIT 1'
            );
            $stmt = $db->prepare(
                "INSERT INTO sesi_absensi
                    (pengajaran_id, pertemuan_ke, tanggal, waktu_buka, waktu_tutup, status)
                 VALUES (?, ?, ?, ?, ?, 'Dibuka')"
            );
            $jumlah_ditutup = 0;
            foreach ($pengajaran_ids as $id_pengajaran) {
                $stmt_pertemuan->execute([$id_pengajaran, $pertemuan_ke]);
                if ($stmt_pertemuan->fetchColumn()) {
                    $pertemuan_sudah_ada = true;
                    throw new RuntimeException('Absensi untuk pertemuan tersebut sudah tersedia pada salah satu kelas.');
                }
                $jumlah_ditutup += proses_penutupan_absensi_otomatis($db, $id_pengajaran, true);
                $stmt->execute([
                    $id_pengajaran,
                    $pertemuan_ke,
                    $tanggal,
                    $waktu_buka->format('Y-m-d H:i:s'),
                    $waktu_tutup->format('Y-m-d H:i:s'),
                ]);
            }
            $db->commit();
            $jumlah_kelas = count($pengajaran_ids);
            catat_log($_SESSION['user_id'], "Membuka absensi pertemuan $pertemuan_ke untuk $jumlah_kelas kelas");
            $_SESSION['flash_success'] = "Sesi absensi pertemuan ke-$pertemuan_ke berhasil dibuka untuk $jumlah_kelas kelas."
                . ($jumlah_ditutup ? " $jumlah_ditutup sesi sebelumnya otomatis ditutup." : '');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Gagal membuka sesi absensi: ' . $e->getMessage());
            $_SESSION['flash_error'] = $pertemuan_sudah_ada || $e->getCode() === '23000'
                ? 'Absensi untuk pertemuan tersebut sudah tersedia. Pilih pertemuan lain.'
                : 'Sesi absensi gagal dibuat.';
        }
        redirect('absensi.php');
    }

    if ($action === 'tutup') {
        $sesi_id = (int)($_POST['sesi_id'] ?? 0);
        try {
            $stmt_sesi = $db->prepare(
                "SELECT sa.id, sa.pertemuan_ke
                 FROM sesi_absensi sa
                 JOIN pengajaran p ON p.id = sa.pengajaran_id
                 WHERE sa.id = ? AND p.guru_id = ? AND sa.status = 'Dibuka'"
            );
            $stmt_sesi->execute([$sesi_id, $guru_id]);
            $sesi = $stmt_sesi->fetch();
            if (!$sesi) {
                throw new RuntimeException('Sesi tidak ditemukan, bukan milik Anda, atau sudah ditutup.');
            }
            if (!tutup_sesi_absensi($db, $sesi_id, $guru_id)) {
                throw new RuntimeException('Sesi sudah ditutup.');
            }

            catat_log($_SESSION['user_id'], "Menutup absensi pertemuan {$sesi['pertemuan_ke']}");
            $_SESSION['flash_success'] = 'Sesi ditutup. Siswa yang belum check-in otomatis tercatat Alpa.';
        } catch (Throwable $e) {
            error_log('Gagal menutup sesi absensi: ' . $e->getMessage());
            $_SESSION['flash_error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Sesi absensi gagal ditutup.';
        }
        redirect('absensi.php');
    }

    $_SESSION['flash_error'] = 'Aksi tidak dikenali.';
    redirect('absensi.php');
}

$stmt_pengajaran = $db->prepare(
    'SELECT p.id, p.mapel_id, p.kelas_id, p.tahun_ajaran, p.semester, m.nama_mapel, k.nama_kelas
     FROM pengajaran p
     JOIN mapel m ON m.id = p.mapel_id
     JOIN kelas k ON k.id = p.kelas_id
     WHERE p.guru_id = ?
     ORDER BY p.tahun_ajaran DESC, p.semester, m.nama_mapel, k.nama_kelas'
);
$stmt_pengajaran->execute([$guru_id]);
$pengajaran_list = $stmt_pengajaran->fetchAll();
$kelompok_pengajaran = [];
foreach ($pengajaran_list as $pengajaran_item) {
    $kunci = $pengajaran_item['mapel_id'] . '|' . $pengajaran_item['tahun_ajaran'] . '|' . $pengajaran_item['semester'];
    $kelompok_pengajaran[$kunci]['label'] = $pengajaran_item['nama_mapel']
        . ' (' . $pengajaran_item['semester'] . ', ' . $pengajaran_item['tahun_ajaran'] . ')';
    $kelompok_pengajaran[$kunci]['kelas'][] = $pengajaran_item;
}
$ada_kelas_gabungan = count(array_filter(
    $kelompok_pengajaran,
    static fn(array $kelompok): bool => count($kelompok['kelas']) > 1
)) > 0;

$pertemuan_terpakai = [];
if ($pengajaran_list) {
    $stmt_pertemuan_terpakai = $db->prepare(
        'SELECT sa.pengajaran_id, sa.pertemuan_ke
         FROM sesi_absensi sa
         JOIN pengajaran p ON p.id = sa.pengajaran_id
         WHERE p.guru_id = ?
         ORDER BY sa.pengajaran_id, sa.pertemuan_ke'
    );
    $stmt_pertemuan_terpakai->execute([$guru_id]);
    foreach ($stmt_pertemuan_terpakai->fetchAll() as $sesi) {
        $pertemuan_terpakai[(int)$sesi['pengajaran_id']][] = (int)$sesi['pertemuan_ke'];
    }
}

$awal = new DateTime();
$akhir = (clone $awal)->modify('+30 minutes');
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
    .teacher-attendance-page { background:#f5f7fb; min-width:0; }
    .attendance-form-content { max-width:980px; margin:0 auto; }
    .attendance-intro { border:0; border-radius:18px; color:#fff; background:linear-gradient(135deg,#0f766e,#0d9488); box-shadow:0 10px 25px rgba(13,148,136,.16); overflow:hidden; position:relative; }
    .attendance-intro::after { content:""; position:absolute; width:150px; height:150px; border-radius:50%; right:-45px; bottom:-85px; background:rgba(255,255,255,.1); }
    .attendance-form-card { border:0; border-radius:18px; box-shadow:0 7px 24px rgba(15,23,42,.07); }
    .attendance-form-card .form-label { font-size:.83rem; margin-bottom:.38rem; }
    .attendance-form-card .form-control,.attendance-form-card .form-select { min-height:44px; border-radius:10px; border-color:#dfe5ee; }
    .attendance-class-list { max-height:220px; overflow-y:auto; }
    .time-field { background:#f8fafc; border:1px solid #edf1f5; border-radius:13px; padding:12px; height:100%; }
    .open-attendance-button { min-height:48px; border-radius:11px; font-weight:700; }
    .attendance-tip { border-radius:12px; background:#eff6ff; color:#475569; font-size:.76rem; line-height:1.5; }
    @media(max-width:575.98px){
        .attendance-form-content { padding:13px!important; }
        .attendance-intro { border-radius:15px; padding:17px!important; }
        .attendance-intro h1 { font-size:1.05rem; }
        .attendance-intro p { font-size:.73rem; line-height:1.45; }
        .attendance-form-card { border-radius:15px; }
        .attendance-form-card .card-body { padding:16px!important; }
        .attendance-form-card .form-control,.attendance-form-card .form-select { min-height:42px; font-size:16px; }
        .time-field { padding:10px; }
    }
</style>
<div id="page-content-wrapper" class="teacher-attendance-page">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-3 px-md-4 py-3">
        <div class="d-flex justify-content-between align-items-center gap-2 w-100"><div><h5 class="mb-0 fw-bold"><i class="fa-solid fa-wifi text-primary me-2"></i>Absensi Online</h5><small class="text-muted">Buka sesi check-in mandiri untuk siswa</small></div><a href="riwayat_absensi.php" class="btn btn-sm btn-outline-primary flex-shrink-0"><i class="fa-solid fa-clock-rotate-left me-1"></i><span class="d-none d-sm-inline">Riwayat</span></a></div>
    </nav>
    <div class="container-fluid attendance-form-content p-3 p-md-4">
        <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($success) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <section class="attendance-intro p-4 mb-3"><div class="position-relative"><small class="opacity-75">Kelas online</small><h1 class="h5 fw-bold mb-2">Siapkan Absensi Siswa</h1><p class="mb-0 opacity-75">Pilih kelas dan atur waktu check-in. Siswa akan melihat sesi setelah berhasil dibuka.</p></div></section>

        <section class="card attendance-form-card"><div class="card-body p-4">
                    <div class="mb-3"><h6 class="fw-bold mb-1">Buka Sesi Baru</h6><small class="text-muted">Pastikan kelas, pertemuan, dan waktu sudah sesuai.</small></div>
                    <?php if (!$pengajaran_list): ?>
                        <div class="alert alert-warning mb-0">Admin belum memberikan pengajaran kepada Anda.</div>
                    <?php else: ?>
                    <form method="post" action="absensi.php" id="formAbsensi">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="buka">
                        <div class="row g-3"><div class="col-12">
                            <?php if ($ada_kelas_gabungan): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold d-block">Mode Kelas</label>
                                    <div class="btn-group w-100" role="group" aria-label="Mode kelas absensi">
                                        <input type="radio" class="btn-check" name="mode_kelas_absensi" id="modeAbsensiTunggal" value="tunggal" checked>
                                        <label class="btn btn-outline-primary" for="modeAbsensiTunggal">Kelas Tunggal</label>
                                        <input type="radio" class="btn-check" name="mode_kelas_absensi" id="modeAbsensiGabungan" value="gabungan">
                                        <label class="btn btn-outline-primary" for="modeAbsensiGabungan">Kelas Gabungan</label>
                                    </div>
                                </div>
                                <div id="pilihanGabunganAbsensi" class="d-none">
                                <label class="form-label fw-semibold">Mapel dan Kelas Tujuan</label><div class="attendance-class-list border rounded-3 p-2">
                                    <?php foreach ($kelompok_pengajaran as $kunci_kelompok => $kelompok): ?>
                                        <div class="small fw-bold text-primary px-2 pt-2"><?= sanitize($kelompok['label']) ?></div>
                                        <?php foreach ($kelompok['kelas'] as $item): $id_kelas_absensi='absensiKelas'.(int)$item['id']; ?>
                                            <div class="form-check px-2 py-2 border-bottom">
                                                <input class="form-check-input ms-0 me-2 kelas-absensi" type="checkbox" name="pengajaran_ids[]" value="<?= (int)$item['id'] ?>" data-group="<?= sanitize($kunci_kelompok) ?>" id="<?= $id_kelas_absensi ?>" disabled>
                                                <label class="form-check-label" for="<?= $id_kelas_absensi ?>"><?= sanitize($item['nama_kelas']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Pilih satu atau beberapa kelas untuk membuka absensi pada waktu yang sama.</div>
                                </div>
                            <?php endif; ?>
                            <div id="pilihanTunggalAbsensi">
                                <label class="form-label fw-semibold">Mapel dan Kelas</label>
                                <select name="pengajaran_id" id="pengajaranAbsensi" class="form-select" required>
                                    <option value="">-- Pilih Pengajaran --</option>
                                    <?php foreach ($pengajaran_list as $item): ?>
                                        <option value="<?= (int)$item['id'] ?>"><?= sanitize($item['nama_mapel']) ?> — <?= sanitize($item['nama_kelas']) ?> (<?= sanitize($item['semester']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-7 col-md-6">
                            <label class="form-label fw-semibold">Pertemuan</label>
                            <select name="pertemuan_ke" id="pertemuanAbsensi" class="form-select" required disabled>
                                <option value="">-- Pilih Pertemuan --</option>
                                <?php for ($i = 1; $i <= 20; $i++): ?><option value="<?= $i ?>" data-label="Pertemuan <?= $i ?>">Pertemuan <?= $i ?></option><?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-5 col-md-6"><label class="form-label fw-semibold">Tanggal</label><input type="date" name="tanggal" value="<?= $awal->format('Y-m-d') ?>" class="form-control" required></div>
                        <div class="col-12 col-md-6"><div class="time-field"><label class="form-label fw-semibold">Waktu Buka</label><input type="datetime-local" name="waktu_buka" value="<?= $awal->format('Y-m-d\TH:i') ?>" class="form-control" required></div></div>
                        <div class="col-12 col-md-6"><div class="time-field"><label class="form-label fw-semibold">Waktu Tutup</label><input type="datetime-local" name="waktu_tutup" value="<?= $akhir->format('Y-m-d\TH:i') ?>" class="form-control" required></div></div>
                        <div class="col-12"><div class="attendance-tip p-3"><i class="fa-solid fa-circle-info text-primary me-1"></i>Siswa yang check-in selama sesi dibuka akan tercatat Hadir. Siswa yang belum check-in saat sesi ditutup akan tercatat Alpa.</div></div>
                        <div class="col-12"><button class="btn btn-primary open-attendance-button w-100" type="submit"><i class="fa-solid fa-door-open me-2"></i>Buka Absensi Sekarang</button></div></div>
                    </form>
                    <?php endif; ?>
        </div></section>
    </div>
</div>
<script>
const adaKelasGabunganAbsensi=<?= $ada_kelas_gabungan ? 'true' : 'false' ?>;
const pertemuanTerpakai = <?= json_encode($pertemuan_terpakai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const pengajaranAbsensi = document.getElementById('pengajaranAbsensi');
const pertemuanAbsensi = document.getElementById('pertemuanAbsensi');
const kelasAbsensi = Array.from(document.querySelectorAll('.kelas-absensi'));
function aturModeAbsensi(mode){
    const gabungan=adaKelasGabunganAbsensi&&mode==='gabungan';
    document.getElementById('pilihanGabunganAbsensi')?.classList.toggle('d-none',!gabungan);
    document.getElementById('pilihanTunggalAbsensi').classList.toggle('d-none',gabungan);
    pengajaranAbsensi.disabled=gabungan;
    pengajaranAbsensi.required=!gabungan;
    kelasAbsensi.forEach(function(checkbox){checkbox.disabled=!gabungan});
    perbaruiPilihanPertemuan();
}

function perbaruiPilihanPertemuan() {
    if (!pertemuanAbsensi) return;

    const modeGabungan=adaKelasGabunganAbsensi&&document.getElementById('modeAbsensiGabungan')?.checked;
    const pengajaranIds = modeGabungan
        ? kelasAbsensi.filter(function(checkbox){return checkbox.checked}).map(function(checkbox){return checkbox.value})
        : (pengajaranAbsensi?.value ? [pengajaranAbsensi.value] : []);
    const sudahAda = new Set();
    pengajaranIds.forEach(function(id){
        (pertemuanTerpakai[id] || []).forEach(function(pertemuan){sudahAda.add(String(pertemuan))});
    });

    pertemuanAbsensi.value = '';
    pertemuanAbsensi.disabled = pengajaranIds.length===0;
    Array.from(pertemuanAbsensi.options).forEach(function (option) {
        if (!option.value) return;
        const terpakai = sudahAda.has(option.value);
        option.disabled = terpakai;
        option.textContent = option.dataset.label + (terpakai ? ' — sudah ada' : '');
    });
}

pengajaranAbsensi?.addEventListener('change', perbaruiPilihanPertemuan);
document.querySelectorAll('input[name="mode_kelas_absensi"]').forEach(function(radio){
    radio.addEventListener('change',function(){aturModeAbsensi(this.value)});
});
kelasAbsensi.forEach(function(checkbox){
    checkbox.addEventListener('change',function(){
        if(this.checked){
            const grup=this.dataset.group;
            kelasAbsensi.forEach(function(pilihan){
                if(pilihan.dataset.group!==grup)pilihan.checked=false;
            });
        }
        perbaruiPilihanPertemuan();
    });
});
document.getElementById('formAbsensi')?.addEventListener('submit',function(event){
    const modeGabungan=adaKelasGabunganAbsensi&&document.getElementById('modeAbsensiGabungan')?.checked;
    if(modeGabungan && !kelasAbsensi.some(function(checkbox){return checkbox.checked})){
        event.preventDefault();
        Swal.fire({icon:'warning',title:'Pilih Kelas',text:'Pilih minimal satu kelas untuk membuka absensi.'});
    }
});
perbaruiPilihanPertemuan();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
