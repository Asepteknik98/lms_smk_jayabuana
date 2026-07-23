<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);
$db = Database::getInstance();

// ID Guru
$stmt_g = $db->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt_g->execute([$_SESSION['user_id']]);
$guru = $stmt_g->fetch();
$guru_id = $guru['id'] ?? 0;

// Daftar Pengajaran
$stmt_p = $db->prepare("
    SELECT p.id, p.kelas_id, p.mapel_id, m.nama_mapel, k.nama_kelas
    FROM pengajaran p
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ?
");
$stmt_p->execute([$guru_id]);
$pengajaran_list = $stmt_p->fetchAll();
$filter_kelas = max(0, (int)($_GET['kelas_id'] ?? 0));
$filter_mapel = max(0, (int)($_GET['mapel_id'] ?? 0));
$kelas_filter_list = [];
$mapel_filter_list = [];
foreach ($pengajaran_list as $item) {
    $kelas_filter_list[(int)$item['kelas_id']] = $item['nama_kelas'];
    if ($filter_kelas === 0 || (int)$item['kelas_id'] === $filter_kelas) {
        $mapel_filter_list[(int)$item['mapel_id']] = $item['nama_mapel'];
    }
}
if ($filter_mapel > 0 && !isset($mapel_filter_list[$filter_mapel])) {
    $filter_mapel = 0;
}

// Daftar Ujian Guru
$filter_sql = '';
$filter_params = [$guru_id];
if ($filter_kelas > 0) {
    $filter_sql .= ' AND p.kelas_id = ?';
    $filter_params[] = $filter_kelas;
}
if ($filter_mapel > 0) {
    $filter_sql .= ' AND p.mapel_id = ?';
    $filter_params[] = $filter_mapel;
}
$stmt_u = $db->prepare("
    SELECT u.*, m.nama_mapel, k.nama_kelas, 
           (SELECT COUNT(*) FROM soal_ujian WHERE ujian_id = u.id) as total_soal
    FROM ujian u
    JOIN pengajaran p ON u.pengajaran_id = p.id
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ? $filter_sql ORDER BY u.id DESC
");
$stmt_u->execute($filter_params);
$ujian_list = $stmt_u->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-3">
        <h5 class="mb-0 fw-bold">Manajemen CBT & Kuis Online</h5>
    </nav>

    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm p-3 mb-3">
            <form method="get" id="filterUjianForm" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Filter Kelas</label>
                    <select name="kelas_id" id="filterKelasUjian" class="form-select">
                        <option value="0">Semua Kelas</option>
                        <?php foreach ($kelas_filter_list as $kelas_id => $nama_kelas): ?>
                            <option value="<?= (int)$kelas_id ?>" <?= $filter_kelas === (int)$kelas_id ? 'selected' : '' ?>><?= sanitize($nama_kelas) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Filter Mata Pelajaran</label>
                    <select name="mapel_id" id="filterMapelUjian" class="form-select">
                        <option value="0">Semua Mata Pelajaran</option>
                        <?php foreach ($mapel_filter_list as $mapel_id => $nama_mapel): ?>
                            <option value="<?= (int)$mapel_id ?>" <?= $filter_mapel === (int)$mapel_id ? 'selected' : '' ?>><?= sanitize($nama_mapel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Terapkan</button>
                </div>
            </form>
        </div>
        <?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($_SESSION['flash_success']);unset($_SESSION['flash_success']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
        <?php if(!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($_SESSION['flash_error']);unset($_SESSION['flash_error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h6 class="fw-bold my-auto"><i class="fa-solid fa-list-check me-2 text-primary"></i> Daftar Jadwal Ujian</h6>
            <div class="d-flex flex-wrap gap-2"><a class="btn btn-success btn-sm" href="ujian_excel.php?action=template"><i class="fa-solid fa-file-excel me-1"></i> Unduh Template Soal</a><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUjian">
                <i class="fa-solid fa-plus me-1"></i> Buat Ujian Baru
            </button></div>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Mapel / Kelas</th>
                            <th>Nama Ujian</th>
                            <th>Durasi</th>
                            <th>Waktu Pelaksanaan</th>
                            <th>Soal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ujian_list as $u): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($u['nama_mapel']) ?></strong><br>
                                <small class="text-muted"><?= sanitize($u['nama_kelas']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary me-1"><?= $u['jenis_ujian'] ?></span>
                                <?= sanitize($u['nama_ujian']) ?>
                            </td>
                            <td><?= $u['durasi_menit'] ?> Menit</td>
                            <td class="small">
                                <?= date('d/m/y H:i', strtotime($u['waktu_mulai'])) ?> s/d<br>
                                <?= date('d/m/y H:i', strtotime($u['waktu_selesai'])) ?>
                            </td>
                            <td><span class="badge bg-info text-dark"><?= $u['total_soal'] ?> Soal</span></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1"><button class="btn btn-sm btn-outline-primary" onclick='openAddSoalModal(<?= (int)$u["id"] ?>,<?= json_encode($u["nama_ujian"],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'>
                                    <i class="fa-solid fa-folder-plus me-1"></i> Kelola Soal
                                </button><button class="btn btn-sm btn-outline-success" onclick='openImportModal(<?= (int)$u["id"] ?>,<?= json_encode($u["nama_ujian"],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-file-arrow-up me-1"></i>Upload Excel</button><a class="btn btn-sm btn-outline-dark" href="ujian_hasil.php?ujian_id=<?= (int)$u['id'] ?>"><i class="fa-solid fa-chart-column me-1"></i>Hasil & Jawaban</a></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$ujian_list): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-filter-circle-xmark fa-2x mb-2 d-block"></i>Tidak ada ujian pada filter yang dipilih.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportExcel" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="ujian_excel.php?action=import" enctype="multipart/form-data"><div class="modal-header"><div><h5 class="modal-title fw-bold">Upload Soal Excel</h5><small class="text-muted" id="importUjianName"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="ujian_id" id="importUjianId"><div class="alert alert-info small"><i class="fa-solid fa-circle-info me-1"></i>Gunakan template resmi. Sistem akan membaca pertanyaan, pilihan, kunci jawaban, dan bobot dari setiap baris.</div><label class="form-label fw-semibold">File Excel (.xls)</label><input class="form-control" type="file" name="file_excel" accept=".xls,application/vnd.ms-excel" required><div class="form-text">Maksimal 2 MB. Hapus baris contoh sebelum mengunggah.</div></div><div class="modal-footer"><a href="ujian_excel.php?action=template" class="btn btn-outline-success"><i class="fa-solid fa-download me-1"></i>Template</a><button class="btn btn-primary" type="submit"><i class="fa-solid fa-upload me-1"></i>Impor Soal</button></div></form></div></div>

<!-- Modal Buat Ujian -->
<div class="modal fade" id="modalCreateUjian" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Buat Ujian Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCreateUjian">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Mapel & Kelas</label>
                        <select name="pengajaran_id" class="form-select" required>
                            <?php foreach($pengajaran_list as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= sanitize($p['nama_mapel']) ?> (<?= sanitize($p['nama_kelas']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Ujian</label>
                        <input type="text" name="nama_ujian" class="form-control" placeholder="Contoh: Kuis Harian Topologi" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Jenis Ujian</label>
                            <select name="jenis_ujian" class="form-select">
                                <option value="Kuis">Kuis</option>
                                <option value="UTS">UTS</option>
                                <option value="UAS">UAS</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Durasi (Menit)</label>
                            <input type="number" name="durasi_menit" class="form-control" value="60" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Waktu Mulai</label>
                        <input type="datetime-local" name="waktu_mulai" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Waktu Selesai</label>
                        <input type="datetime-local" name="waktu_selesai" class="form-control" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="acak_soal" id="acakSoal" value="1" checked>
                        <label class="form-check-label fw-semibold" for="acakSoal">Acak Urutan Soal Siswa</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Soal -->
<div class="modal fade" id="modalAddSoal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="titleSoalModal">Tambah Soal Ujian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddSoal">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="ujian_id" id="modalUjianId">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipe Soal</label>
                            <select name="tipe_soal" id="tipeSoalSelect" class="form-select" onchange="toggleTipeSoal(this.value)">
                                <option value="PG">Pilihan Ganda (PG)</option>
                                <option value="ESAI">Esai / Uraian</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bobot Nilai</label>
                            <input type="number" name="bobot" class="form-control" value="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pertanyaan Soal</label>
                        <textarea name="pertanyaan" class="form-control" rows="3" required></textarea>
                    </div>

                    <div id="pgContainer">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6"><input type="text" name="opsi_a" class="form-control" placeholder="Pilihan A"></div>
                            <div class="col-md-6"><input type="text" name="opsi_b" class="form-control" placeholder="Pilihan B"></div>
                            <div class="col-md-6"><input type="text" name="opsi_c" class="form-control" placeholder="Pilihan C"></div>
                            <div class="col-md-6"><input type="text" name="opsi_d" class="form-control" placeholder="Pilihan D"></div>
                            <div class="col-md-12"><input type="text" name="opsi_e" class="form-control" placeholder="Pilihan E"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-success">Kunci Jawaban Benar</label>
                            <select name="kunci_jawaban" class="form-select">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="E">E</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Tambahkan Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let modalSoal;
let modalImport;

document.getElementById('filterKelasUjian')?.addEventListener('change', function () {
    document.getElementById('filterMapelUjian').disabled = true;
    document.getElementById('filterUjianForm').submit();
});
document.getElementById('filterMapelUjian')?.addEventListener('change', function () {
    document.getElementById('filterUjianForm').submit();
});

$(document).ready(function() {
    modalSoal = new bootstrap.Modal(document.getElementById('modalAddSoal'));
    modalImport = new bootstrap.Modal(document.getElementById('modalImportExcel'));

    $('#formCreateUjian').on('submit', function(e) {
        e.preventDefault();
        $.post('ujian_action.php?action=create_ujian', $(this).serialize(), function(res) {
            if(res.status === 'success') {
                Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        }, 'json');
    });

    $('#formAddSoal').on('submit', function(e) {
        e.preventDefault();
        $.post('ujian_action.php?action=add_soal', $(this).serialize(), function(res) {
            if(res.status === 'success') {
                Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        }, 'json');
    });
});

function openAddSoalModal(id, nama) {
    $('#modalUjianId').val(id);
    $('#titleSoalModal').text('Kelola Soal: ' + nama);
    modalSoal.show();
}

function openImportModal(id, nama) {
    document.getElementById('importUjianId').value = id;
    document.getElementById('importUjianName').textContent = nama;
    modalImport.show();
}

function toggleTipeSoal(val) {
    if(val === 'ESAI') {
        $('#pgContainer').hide();
    } else {
        $('#pgContainer').show();
    }
}
</script>
