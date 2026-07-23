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
           (SELECT COUNT(*) FROM soal_ujian WHERE ujian_id = u.id) as total_soal,
           (SELECT COUNT(*) FROM siswa WHERE kelas_id = p.kelas_id) as total_siswa,
           (SELECT COUNT(*) FROM sesi_ujian WHERE ujian_id = u.id) as total_peserta,
           (SELECT COUNT(*) FROM sesi_ujian WHERE ujian_id = u.id AND status='Selesai') as peserta_selesai
    FROM ujian u
    JOIN pengajaran p ON u.pengajaran_id = p.id
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ? $filter_sql ORDER BY u.id DESC
");
$stmt_u->execute($filter_params);
$ujian_list = $stmt_u->fetchAll();
$soal_per_ujian = [];
if ($ujian_list) {
    $ids_ujian = array_map(static fn($u) => (int)$u['id'], $ujian_list);
    $placeholder = implode(',', array_fill(0, count($ids_ujian), '?'));
    $stmt_soal = $db->prepare("SELECT * FROM soal_ujian WHERE ujian_id IN ($placeholder) ORDER BY ujian_id,id");
    $stmt_soal->execute($ids_ujian);
    foreach ($stmt_soal->fetchAll() as $soal) $soal_per_ujian[(int)$soal['ujian_id']][] = $soal;
}
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
            <div class="d-flex flex-wrap gap-2"><a class="btn btn-success btn-sm" href="ujian_excel.php?action=template"><i class="fa-solid fa-file-excel me-1"></i> Unduh Template Soal</a><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUjian" onclick="resetFormUjian()">
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
                            <th>Siswa</th>
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
                            <td class="small text-nowrap"><span class="badge bg-primary-subtle text-primary"><?= (int)$u['total_peserta'] ?>/<?= (int)$u['total_siswa'] ?> mulai</span><br><span class="text-success"><?= (int)$u['peserta_selesai'] ?> selesai</span> &middot; <span class="text-danger"><?= max(0,(int)$u['total_siswa']-(int)$u['total_peserta']) ?> belum</span></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1"><button class="btn btn-sm btn-outline-primary" onclick='openAddSoalModal(<?= (int)$u["id"] ?>,<?= json_encode($u["nama_ujian"],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>,<?= json_encode($soal_per_ujian[(int)$u["id"]]??[],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'>
                                    <i class="fa-solid fa-folder-plus me-1"></i> Kelola Soal
                                </button><?php if((int)$u['total_peserta']===0): ?><button class="btn btn-sm btn-outline-success" onclick='openImportModal(<?= (int)$u["id"] ?>,<?= json_encode($u["nama_ujian"],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-file-arrow-up me-1"></i>Upload Excel</button><?php else: ?><button class="btn btn-sm btn-outline-secondary" disabled title="Soal dikunci karena ujian sudah memiliki peserta"><i class="fa-solid fa-lock me-1"></i>Soal Dikunci</button><?php endif ?><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalCreateUjian" onclick='editUjian(<?= json_encode(["id"=>(int)$u["id"],"pengajaran_id"=>(int)$u["pengajaran_id"],"nama_ujian"=>$u["nama_ujian"],"jenis_ujian"=>$u["jenis_ujian"],"durasi_menit"=>(int)$u["durasi_menit"],"waktu_mulai"=>date("Y-m-d\\TH:i",strtotime($u["waktu_mulai"])),"waktu_selesai"=>date("Y-m-d\\TH:i",strtotime($u["waktu_selesai"])),"acak_soal"=>(int)$u["acak_soal"]],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>'><i class="fa-solid fa-pen"></i></button><button class="btn btn-sm btn-outline-danger" onclick='hapusUjian(<?= (int)$u["id"] ?>,<?= json_encode($u["nama_ujian"],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-trash"></i></button><a class="btn btn-sm btn-outline-dark" href="ujian_hasil.php?ujian_id=<?= (int)$u['id'] ?>"><i class="fa-solid fa-chart-column me-1"></i>Hasil & Jawaban</a></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$ujian_list): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="fa-solid fa-filter-circle-xmark fa-2x mb-2 d-block"></i>Tidak ada ujian pada filter yang dipilih.</td></tr>
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
                <h5 class="modal-title fw-bold" id="judulModalUjian">Buat Ujian Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCreateUjian">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="ujian_id" id="editUjianId">
                    <input type="hidden" id="aksiUjian" value="create_ujian">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Mapel & Kelas</label>
                        <select name="pengajaran_id" id="editPengajaranUjian" class="form-select" required>
                            <?php foreach($pengajaran_list as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= sanitize($p['nama_mapel']) ?> (<?= sanitize($p['nama_kelas']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Ujian</label>
                        <input type="text" name="nama_ujian" id="editNamaUjian" class="form-control" maxlength="255" placeholder="Contoh: Kuis Harian Topologi" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Jenis Ujian</label>
                            <select name="jenis_ujian" id="editJenisUjian" class="form-select">
                                <option value="Kuis">Kuis</option>
                                <option value="UTS">UTS</option>
                                <option value="UAS">UAS</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Durasi (Menit)</label>
                            <input type="number" name="durasi_menit" id="editDurasiUjian" class="form-control" min="1" max="600" value="60" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Waktu Mulai</label>
                        <input type="datetime-local" name="waktu_mulai" id="editMulaiUjian" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Waktu Selesai</label>
                        <input type="datetime-local" name="waktu_selesai" id="editSelesaiUjian" class="form-control" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="acak_soal" id="acakSoal" value="1" checked>
                        <label class="form-check-label fw-semibold" for="acakSoal">Acak Urutan Soal Siswa</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="tombolSimpanUjian">Simpan Jadwal</button>
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
                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="ujian_id" id="modalUjianId">
                    <input type="hidden" name="soal_id" id="modalSoalId">
                    <input type="hidden" id="aksiSoal" value="add_soal">
                    <div id="daftarSoalModal" class="mb-3"></div>

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
                            <input type="number" name="bobot" id="bobotSoal" class="form-control" min="1" max="100" value="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pertanyaan Soal</label>
                        <textarea name="pertanyaan" id="pertanyaanSoal" class="form-control" rows="3" required></textarea>
                    </div>

                    <div id="pgContainer">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6"><input type="text" name="opsi_a" id="opsiA" class="form-control" placeholder="Pilihan A"></div>
                            <div class="col-md-6"><input type="text" name="opsi_b" id="opsiB" class="form-control" placeholder="Pilihan B"></div>
                            <div class="col-md-6"><input type="text" name="opsi_c" id="opsiC" class="form-control" placeholder="Pilihan C"></div>
                            <div class="col-md-6"><input type="text" name="opsi_d" id="opsiD" class="form-control" placeholder="Pilihan D"></div>
                            <div class="col-md-12"><input type="text" name="opsi_e" id="opsiE" class="form-control" placeholder="Pilihan E"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-success">Kunci Jawaban Benar</label>
                            <select name="kunci_jawaban" id="kunciSoal" class="form-select">
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
                    <button type="button" class="btn btn-light d-none" id="batalEditSoal" onclick="resetFormSoal()">Batal Edit</button><button type="submit" class="btn btn-primary" id="tombolSimpanSoal">Tambahkan Soal</button>
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
        $.post('ujian_action.php?action=' + document.getElementById('aksiUjian').value, $(this).serialize(), function(res) {
            if(res.status === 'success') {
                if(document.getElementById('aksiUjian').value==='create_ujian'){
                    bootstrap.Modal.getInstance(document.getElementById('modalCreateUjian'))?.hide();
                    Swal.fire({title:'Ujian berhasil dibuat',text:'Apakah Anda ingin mengunggah soal Excel sekarang?',icon:'success',showCancelButton:true,confirmButtonText:'Ya, Upload Excel',cancelButtonText:'Nanti'}).then(function(result){
                        if(result.isConfirmed)openImportModal(res.ujian_id,res.nama_ujian);
                        else location.reload();
                    });
                }else{
                    Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                }
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        }, 'json').fail(function(xhr){Swal.fire('Gagal!',xhr.responseJSON?.message||'Ujian gagal diproses.','error');});
    });

    $('#formAddSoal').on('submit', function(e) {
        e.preventDefault();
        $.post('ujian_action.php?action=' + document.getElementById('aksiSoal').value, $(this).serialize(), function(res) {
            if(res.status === 'success') {
                Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        }, 'json').fail(function(xhr){Swal.fire('Gagal!',xhr.responseJSON?.message||'Soal gagal diproses.','error');});
    });
});

let soalAktif=[];
function openAddSoalModal(id, nama, daftar) {
    $('#modalUjianId').val(id);
    $('#titleSoalModal').text('Kelola Soal: ' + nama);
    soalAktif=daftar||[];renderDaftarSoal();resetFormSoal();
    modalSoal.show();
}

function openImportModal(id, nama) {
    document.getElementById('importUjianId').value = id;
    document.getElementById('importUjianName').textContent = nama;
    modalImport.show();
}

function resetFormUjian(){
    document.getElementById('formCreateUjian').reset();
    document.getElementById('editUjianId').value='';
    document.getElementById('aksiUjian').value='create_ujian';
    document.getElementById('judulModalUjian').textContent='Buat Ujian Baru';
    document.getElementById('tombolSimpanUjian').textContent='Simpan Jadwal';
}
function editUjian(data){
    document.getElementById('formCreateUjian').reset();
    document.getElementById('editUjianId').value=data.id;
    document.getElementById('aksiUjian').value='update_ujian';
    document.getElementById('editPengajaranUjian').value=data.pengajaran_id;
    document.getElementById('editNamaUjian').value=data.nama_ujian;
    document.getElementById('editJenisUjian').value=data.jenis_ujian;
    document.getElementById('editDurasiUjian').value=data.durasi_menit;
    document.getElementById('editMulaiUjian').value=data.waktu_mulai;
    document.getElementById('editSelesaiUjian').value=data.waktu_selesai;
    document.getElementById('acakSoal').checked=Number(data.acak_soal)===1;
    document.getElementById('judulModalUjian').textContent='Edit Ujian';
    document.getElementById('tombolSimpanUjian').textContent='Simpan Perubahan';
}
async function kirimAksiUjian(action,fields){
    const data=new FormData();data.append('csrf_token',<?= json_encode($_SESSION['csrf_token']) ?>);
    Object.entries(fields).forEach(([key,value])=>data.append(key,value));
    const response=await fetch('ujian_action.php?action='+action,{method:'POST',body:data});
    const body=await response.json();
    if(!response.ok||body.status!=='success')throw new Error(body.message||'Permintaan gagal.');
    return body;
}
function hapusUjian(id,nama){
    Swal.fire({title:'Hapus ujian?',text:nama+' beserta soal dan seluruh hasil siswa akan dihapus.',icon:'warning',showCancelButton:true,confirmButtonText:'Hapus',cancelButtonText:'Batal',confirmButtonColor:'#dc3545'}).then(async function(result){
        if(!result.isConfirmed)return;
        try{const body=await kirimAksiUjian('delete_ujian',{ujian_id:id});Swal.fire('Berhasil!',body.message,'success').then(()=>location.reload());}
        catch(error){Swal.fire('Gagal!',error.message,'error');}
    });
}
function renderDaftarSoal(){
    const container=document.getElementById('daftarSoalModal');container.replaceChildren();
    const title=document.createElement('div');title.className='d-flex justify-content-between align-items-center mb-2';
    const strong=document.createElement('strong');strong.textContent='Soal Tersimpan';
    const badge=document.createElement('span');badge.className='badge bg-primary';badge.textContent=soalAktif.length+' soal';
    title.append(strong,badge);container.appendChild(title);
    if(!soalAktif.length){const empty=document.createElement('div');empty.className='alert alert-light small';empty.textContent='Belum ada soal pada ujian ini.';container.appendChild(empty);return;}
    const list=document.createElement('div');list.className='list-group mb-3';
    soalAktif.forEach(function(soal,index){
        const item=document.createElement('div');item.className='list-group-item d-flex justify-content-between align-items-start gap-2';
        const copy=document.createElement('div');copy.className='small';const label=document.createElement('strong');label.textContent=(index+1)+'. ['+soal.tipe_soal+'] ';
        const question=document.createTextNode(soal.pertanyaan);copy.append(label,question);
        const actions=document.createElement('div');actions.className='btn-group btn-group-sm';
        const edit=document.createElement('button');edit.type='button';edit.className='btn btn-outline-secondary';edit.innerHTML='<i class="fa-solid fa-pen"></i>';edit.onclick=()=>editSoal(soal);
        const del=document.createElement('button');del.type='button';del.className='btn btn-outline-danger';del.innerHTML='<i class="fa-solid fa-trash"></i>';del.onclick=()=>hapusSoal(soal.id);
        actions.append(edit,del);item.append(copy,actions);list.appendChild(item);
    });container.appendChild(list);
}
function resetFormSoal(){
    document.getElementById('modalSoalId').value='';
    document.getElementById('aksiSoal').value='add_soal';
    document.getElementById('tipeSoalSelect').value='PG';
    document.getElementById('bobotSoal').value=1;
    document.getElementById('pertanyaanSoal').value='';
    ['opsiA','opsiB','opsiC','opsiD','opsiE'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('kunciSoal').value='A';toggleTipeSoal('PG');
    document.getElementById('tombolSimpanSoal').textContent='Tambahkan Soal';
    document.getElementById('batalEditSoal').classList.add('d-none');
}
function editSoal(soal){
    document.getElementById('modalSoalId').value=soal.id;
    document.getElementById('aksiSoal').value='update_soal';
    document.getElementById('tipeSoalSelect').value=soal.tipe_soal;
    document.getElementById('bobotSoal').value=soal.bobot;
    document.getElementById('pertanyaanSoal').value=soal.pertanyaan;
    document.getElementById('opsiA').value=soal.opsi_a||'';document.getElementById('opsiB').value=soal.opsi_b||'';
    document.getElementById('opsiC').value=soal.opsi_c||'';document.getElementById('opsiD').value=soal.opsi_d||'';
    document.getElementById('opsiE').value=soal.opsi_e||'';document.getElementById('kunciSoal').value=soal.kunci_jawaban||'A';
    toggleTipeSoal(soal.tipe_soal);document.getElementById('tombolSimpanSoal').textContent='Simpan Perubahan';
    document.getElementById('batalEditSoal').classList.remove('d-none');
}
function hapusSoal(id){
    Swal.fire({title:'Hapus soal?',text:'Jawaban siswa untuk soal ini juga akan terhapus.',icon:'warning',showCancelButton:true,confirmButtonText:'Hapus',cancelButtonText:'Batal',confirmButtonColor:'#dc3545'}).then(async function(result){
        if(!result.isConfirmed)return;
        try{const body=await kirimAksiUjian('delete_soal',{soal_id:id});Swal.fire('Berhasil!',body.message,'success').then(()=>location.reload());}
        catch(error){Swal.fire('Gagal!',error.message,'error');}
    });
}
function toggleTipeSoal(val) {
    if(val === 'ESAI') {
        $('#pgContainer').hide();
    } else {
        $('#pgContainer').show();
    }
}
</script>
