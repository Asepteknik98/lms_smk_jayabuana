<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

check_access([1]);
$db = Database::getInstance();
proses_penutupan_absensi_otomatis($db);

$pengajaran_list = $db->query(
    'SELECT p.id, p.guru_id, p.kelas_id, p.mapel_id, p.semester, p.tahun_ajaran,
            g.nama_lengkap AS nama_guru, k.nama_kelas, m.nama_mapel
     FROM pengajaran p
     JOIN guru g ON g.id = p.guru_id
     JOIN kelas k ON k.id = p.kelas_id
     JOIN mapel m ON m.id = p.mapel_id
     ORDER BY g.nama_lengkap, k.nama_kelas, m.nama_mapel, p.tahun_ajaran DESC, p.semester'
)->fetchAll();

$pengajaran_id = (int)($_GET['pengajaran_id'] ?? 0);
$info = null;
foreach ($pengajaran_list as $item) {
    if ((int)$item['id'] === $pengajaran_id) {
        $info = $item;
        break;
    }
}
if (!$info && $pengajaran_list) {
    $info = $pengajaran_list[0];
    $pengajaran_id = (int)$info['id'];
}

$pertemuan_filter = (int)($_GET['pertemuan'] ?? 0);
if ($pertemuan_filter < 0 || $pertemuan_filter > 20) $pertemuan_filter = 0;

$daftar_sesi = [];
$siswa_list = [];
$status_siswa = [];
$ringkasan = [];
$sesi_terpilih = null;
$total_status = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0, 'Belum' => 0];

if ($info) {
    $stmt = $db->prepare(
        'SELECT id, pertemuan_ke, tanggal, waktu_buka, waktu_tutup, status
         FROM sesi_absensi
         WHERE pengajaran_id = ?
         ORDER BY pertemuan_ke'
    );
    $stmt->execute([$pengajaran_id]);
    $daftar_sesi = $stmt->fetchAll();

    foreach ($daftar_sesi as $sesi) {
        if ((int)$sesi['pertemuan_ke'] === $pertemuan_filter) $sesi_terpilih = $sesi;
    }
    if ($pertemuan_filter > 0 && !$sesi_terpilih) $pertemuan_filter = 0;

    $stmt = $db->prepare(
        'SELECT id, nisn, nis, nama_lengkap
         FROM siswa
         WHERE kelas_id = ?
         ORDER BY nama_lengkap'
    );
    $stmt->execute([(int)$info['kelas_id']]);
    $siswa_list = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT da.siswa_id, sa.pertemuan_ke, da.status, da.waktu_checkin,
                COALESCE(da.keterangan, \'\') AS keterangan
         FROM sesi_absensi sa
         JOIN detail_absensi da ON da.sesi_absensi_id = sa.id
         WHERE sa.pengajaran_id = ?'
    );
    $stmt->execute([$pengajaran_id]);
    foreach ($stmt->fetchAll() as $detail) {
        $status_siswa[(int)$detail['siswa_id']][(int)$detail['pertemuan_ke']] = $detail;
    }

    foreach ($siswa_list as $siswa) {
        $siswa_id = (int)$siswa['id'];
        $ringkasan[$siswa_id] = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0, 'Belum' => 0];
        foreach ($daftar_sesi as $sesi) {
            $pertemuan = (int)$sesi['pertemuan_ke'];
            $status = $status_siswa[$siswa_id][$pertemuan]['status']
                ?? ($sesi['status'] === 'Ditutup' ? 'Alpa' : 'Belum');
            $status_siswa[$siswa_id][$pertemuan] = array_merge(
                ['status' => $status, 'waktu_checkin' => null, 'keterangan' => ''],
                $status_siswa[$siswa_id][$pertemuan] ?? []
            );
            $ringkasan[$siswa_id][$status]++;
            if ($pertemuan_filter === 0 || $pertemuan === $pertemuan_filter) $total_status[$status]++;
        }
    }
}

$format = $_GET['format'] ?? '';
if (in_array($format, ['excel', 'pdf'], true) && $info) {
    $judul_pertemuan = $pertemuan_filter
        ? 'Pertemuan ' . $pertemuan_filter
        : 'Semua Pertemuan';
    $judul = 'Rekap Absensi - ' . $info['nama_mapel'] . ' - ' . $info['nama_kelas'];
    $subjudul = $info['nama_guru'] . ' | ' . $info['semester'] . ' | ' . $info['tahun_ajaran'] . ' | ' . $judul_pertemuan;
    $nama_file = 'Rekap_Absensi_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $info['nama_kelas'] . '_' . $info['nama_mapel'] . '_' . $judul_pertemuan) . '_' . date('Ymd');
    $headers = ['No', 'NISN', 'Nama Siswa'];
    $rows = [];

    if ($pertemuan_filter && $sesi_terpilih) {
        $headers = array_merge($headers, ['Status', 'Waktu Check-in', 'Keterangan']);
        foreach ($siswa_list as $index => $siswa) {
            $detail = $status_siswa[(int)$siswa['id']][$pertemuan_filter];
            $rows[] = [
                $index + 1, $siswa['nisn'] ?: $siswa['nis'], $siswa['nama_lengkap'],
                $detail['status'],
                $detail['waktu_checkin'] ? date('H:i:s', strtotime($detail['waktu_checkin'])) : '-',
                $detail['keterangan'] ?: '-',
            ];
        }
    } else {
        foreach ($daftar_sesi as $sesi) $headers[] = 'P' . (int)$sesi['pertemuan_ke'];
        $headers = array_merge($headers, ['Hadir', 'Sakit', 'Izin', 'Alpa', 'Belum', 'Kehadiran (%)']);
        foreach ($siswa_list as $index => $siswa) {
            $siswa_id = (int)$siswa['id'];
            $row = [$index + 1, $siswa['nisn'] ?: $siswa['nis'], $siswa['nama_lengkap']];
            foreach ($daftar_sesi as $sesi) $row[] = $status_siswa[$siswa_id][(int)$sesi['pertemuan_ke']]['status'];
            $selesai = $ringkasan[$siswa_id]['Hadir'] + $ringkasan[$siswa_id]['Sakit'] + $ringkasan[$siswa_id]['Izin'] + $ringkasan[$siswa_id]['Alpa'];
            $persentase = $selesai ? ($ringkasan[$siswa_id]['Hadir'] / $selesai) * 100 : 0;
            $row = array_merge($row, [
                $ringkasan[$siswa_id]['Hadir'], $ringkasan[$siswa_id]['Sakit'],
                $ringkasan[$siswa_id]['Izin'], $ringkasan[$siswa_id]['Alpa'],
                $ringkasan[$siswa_id]['Belum'], number_format($persentase, 1, ',', '.') . '%',
            ]);
            $rows[] = $row;
        }
    }

    $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nama_file . '.xls"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"><style>table{border-collapse:collapse}th,td{border:1px solid #64748b;padding:6px;white-space:nowrap}th{background:#dbeafe}</style></head><body>';
        echo '<h2>' . $escape($judul) . '</h2><p>' . $escape($subjudul) . '</p><table><thead><tr>';
        foreach ($headers as $header) echo '<th>' . $escape($header) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>' . $escape($cell) . '</td>';
            echo '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="' . count($headers) . '">Tidak ada data.</td></tr>';
        echo '</tbody></table></body></html>';
        exit;
    }

    $pdf_escape = static function ($text) {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string)$text);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    };
    $lines = [$judul, $subjudul, 'Dicetak: ' . date('d/m/Y H:i'), str_repeat('-', 145), implode(' | ', $headers), str_repeat('-', 145)];
    foreach ($rows as $row) {
        $line = implode(' | ', array_map('strval', $row));
        foreach (str_split($line, 145) as $part) $lines[] = $part;
    }
    if (!$rows) $lines[] = 'Tidak ada data.';
    $pages = array_chunk($lines, 48);
    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>'];
    $kids = [];
    $next = 4;
    foreach ($pages as $page_lines) {
        $page_object = $next++;
        $stream_object = $next++;
        $kids[] = $page_object . ' 0 R';
        $commands = "BT /F1 7 Tf 24 570 Td 10 TL\n";
        foreach ($page_lines as $line) $commands .= '(' . $pdf_escape($line) . ") Tj T*\n";
        $commands .= 'ET';
        $objects[$page_object] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $stream_object 0 R >>";
        $objects[$stream_object] = "<< /Length " . strlen($commands) . " >>\nstream\n$commands\nendstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "$number 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nama_file . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store');
    echo $pdf;
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pengajaran_json = array_map(static fn($p) => [
    'id' => (int)$p['id'], 'guru_id' => (int)$p['guru_id'], 'guru' => $p['nama_guru'],
    'kelas_id' => (int)$p['kelas_id'], 'kelas' => $p['nama_kelas'],
    'mapel_id' => (int)$p['mapel_id'], 'mapel' => $p['nama_mapel'],
    'semester' => $p['semester'], 'tahun' => $p['tahun_ajaran'],
], $pengajaran_list);

function badge_absensi_admin(string $status): string {
    return [
        'Hadir' => 'bg-success', 'Sakit' => 'bg-warning text-dark',
        'Izin' => 'bg-info text-dark', 'Alpa' => 'bg-danger', 'Belum' => 'bg-secondary',
    ][$status] ?? 'bg-light text-dark';
}
?>
<div id="page-content-wrapper" class="admin-attendance-page">
<style>
.admin-attendance-page{background:#f7f9fc;min-width:0}.attendance-content{max-width:1400px;margin:auto}.report-card{border:1px solid #e8edf5;border-radius:18px;box-shadow:0 8px 28px rgba(15,23,42,.045)}.report-hero{background:linear-gradient(135deg,#172554,#1d4ed8);color:#fff;border-radius:20px;padding:25px}.filter-card .form-label{font-size:.78rem;font-weight:700}.summary-box{border:1px solid #e8edf5;border-radius:14px;padding:14px;background:#fff}.summary-box strong{font-size:1.35rem}.attendance-table th{white-space:nowrap;font-size:.76rem}.attendance-table td{font-size:.82rem;vertical-align:middle}.status-cell{min-width:62px;text-align:center}@media(max-width:767.98px){.attendance-content{padding:14px!important}.report-hero{padding:20px;border-radius:17px}}
</style>
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>Rekap Absensi Admin</h5><small class="text-muted">Pantau kehadiran seluruh pengajaran</small></div></nav>
<main class="container-fluid attendance-content p-3 p-md-4">
    <section class="report-hero mb-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><small class="text-uppercase fw-semibold opacity-75">Laporan Akademik</small><h2 class="h4 fw-bold my-1">Rekap Kehadiran Siswa</h2><p class="mb-0 opacity-75">Pilih pengajaran dan pertemuan untuk menampilkan laporan terperinci.</p></div><i class="fa-solid fa-chart-column fa-2x opacity-50"></i></div></section>

    <?php if (!$pengajaran_list): ?>
        <div class="alert alert-warning">Belum ada data pengajaran yang dapat direkap.</div>
    <?php else: ?>
    <section class="card report-card filter-card mb-3"><div class="card-body p-3 p-md-4">
        <form method="get" id="formRekapAdmin" class="row g-2 align-items-end">
            <input type="hidden" name="pengajaran_id" id="pengajaranId" value="<?= $pengajaran_id ?>">
            <div class="col-md-4 col-xl-2"><label class="form-label">Guru</label><select id="filterGuru" class="form-select"></select></div>
            <div class="col-md-4 col-xl-2"><label class="form-label">Kelas</label><select id="filterKelas" class="form-select"></select></div>
            <div class="col-md-4 col-xl-2"><label class="form-label">Mata Pelajaran</label><select id="filterMapel" class="form-select"></select></div>
            <div class="col-md-4 col-xl-2"><label class="form-label">Semester</label><select id="filterSemester" class="form-select"></select></div>
            <div class="col-md-4 col-xl-2"><label class="form-label">Tahun Ajaran</label><select id="filterTahun" class="form-select"></select></div>
            <div class="col-md-4 col-xl-2"><label class="form-label">Pertemuan</label><select name="pertemuan" class="form-select"><option value="0">Semua Pertemuan</option><?php foreach($daftar_sesi as $sesi): ?><option value="<?= (int)$sesi['pertemuan_ke'] ?>" <?= $pertemuan_filter===(int)$sesi['pertemuan_ke']?'selected':'' ?>>Pertemuan <?= (int)$sesi['pertemuan_ke'] ?> · <?= date('d/m/Y',strtotime($sesi['tanggal'])) ?></option><?php endforeach ?></select></div>
            <div class="col-12"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Tampilkan Rekap</button></div>
        </form>
    </div></section>

    <?php if ($info): ?>
    <section class="card report-card"><div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><h6 class="fw-bold mb-1"><?= sanitize($info['nama_mapel']) ?> — <?= sanitize($info['nama_kelas']) ?></h6><small class="text-muted"><?= sanitize($info['nama_guru']) ?> · <?= sanitize($info['semester']) ?> · <?= sanitize($info['tahun_ajaran']) ?> · <?= $pertemuan_filter ? 'Pertemuan '.$pertemuan_filter : 'Semua pertemuan' ?></small></div>
            <div class="d-flex gap-2"><a class="btn btn-sm btn-success" href="?<?= http_build_query(['pengajaran_id'=>$pengajaran_id,'pertemuan'=>$pertemuan_filter,'format'=>'excel']) ?>"><i class="fa-solid fa-file-excel me-1"></i>Excel</a><a class="btn btn-sm btn-danger" href="?<?= http_build_query(['pengajaran_id'=>$pengajaran_id,'pertemuan'=>$pertemuan_filter,'format'=>'pdf']) ?>"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a></div>
        </div>
        <div class="row g-2 mb-3">
            <?php foreach([['Hadir','success'],['Sakit','warning'],['Izin','info'],['Alpa','danger'],['Belum','secondary']] as [$label,$warna]): ?><div class="col-6 col-md"><div class="summary-box"><small class="text-muted"><?= $label ?></small><strong class="d-block text-<?= $warna ?>"><?= (int)$total_status[$label] ?></strong></div></div><?php endforeach ?>
        </div>
        <div class="table-responsive"><table class="table table-hover attendance-table align-middle">
            <thead class="table-light"><tr><th>No</th><th>NISN</th><th style="min-width:180px">Nama Siswa</th>
            <?php if($pertemuan_filter): ?><th>Status</th><th>Check-in</th><th>Keterangan</th>
            <?php else: foreach($daftar_sesi as $sesi): ?><th title="<?= date('d/m/Y',strtotime($sesi['tanggal'])) ?>">P<?= (int)$sesi['pertemuan_ke'] ?></th><?php endforeach ?><th>H</th><th>S</th><th>I</th><th>A</th><th>B</th><th>%</th><?php endif ?>
            </tr></thead><tbody>
            <?php foreach($siswa_list as $index=>$siswa): $sid=(int)$siswa['id']; ?><tr><td><?= $index+1 ?></td><td><?= sanitize($siswa['nisn']?:$siswa['nis']) ?></td><td><strong><?= sanitize($siswa['nama_lengkap']) ?></strong></td>
            <?php if($pertemuan_filter): $detail=$status_siswa[$sid][$pertemuan_filter]; ?><td><span class="badge <?= badge_absensi_admin($detail['status']) ?>"><?= sanitize($detail['status']) ?></span></td><td><?= $detail['waktu_checkin']?date('H:i:s',strtotime($detail['waktu_checkin'])):'-' ?></td><td><?= sanitize($detail['keterangan']?:'-') ?></td>
            <?php else: foreach($daftar_sesi as $sesi): $status=$status_siswa[$sid][(int)$sesi['pertemuan_ke']]['status']; ?><td class="status-cell"><span class="badge <?= badge_absensi_admin($status) ?>" title="<?= sanitize($status) ?>"><?= sanitize(substr($status,0,1)) ?></span></td><?php endforeach; $selesai=$ringkasan[$sid]['Hadir']+$ringkasan[$sid]['Sakit']+$ringkasan[$sid]['Izin']+$ringkasan[$sid]['Alpa']; $persen=$selesai?($ringkasan[$sid]['Hadir']/$selesai)*100:0; ?><td><?= $ringkasan[$sid]['Hadir'] ?></td><td><?= $ringkasan[$sid]['Sakit'] ?></td><td><?= $ringkasan[$sid]['Izin'] ?></td><td><?= $ringkasan[$sid]['Alpa'] ?></td><td><?= $ringkasan[$sid]['Belum'] ?></td><td><?= number_format($persen,1,',','.') ?>%</td><?php endif ?></tr><?php endforeach ?>
            <?php if(!$siswa_list): ?><tr><td colspan="<?= $pertemuan_filter?6:max(9,count($daftar_sesi)+9) ?>" class="text-center text-muted py-5">Belum ada siswa pada kelas ini.</td></tr><?php endif ?>
            </tbody>
        </table></div>
        <?php if(!$daftar_sesi): ?><div class="alert alert-info mb-0">Belum ada sesi absensi pada pengajaran ini.</div><?php endif ?>
    </div></section>
    <?php endif ?>
    <?php endif ?>
</main>
</div>
<script>
const dataPengajaran = <?= json_encode($pengajaran_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const pilihanAwal = <?= json_encode($info ? ['guru_id'=>(int)$info['guru_id'],'kelas_id'=>(int)$info['kelas_id'],'mapel_id'=>(int)$info['mapel_id'],'semester'=>$info['semester'],'tahun'=>$info['tahun_ajaran']] : []) ?>;
const elemenFilter = {
    guru: document.getElementById('filterGuru'), kelas: document.getElementById('filterKelas'),
    mapel: document.getElementById('filterMapel'), semester: document.getElementById('filterSemester'),
    tahun: document.getElementById('filterTahun')
};
function opsiUnik(data, nilai, label) {
    return [...new Map(data.map(item => [String(item[nilai]), {value:String(item[nilai]), label:item[label] ?? item[nilai]}])).values()];
}
function isiSelect(select, opsi, nilai) {
    select.replaceChildren(...opsi.map(function(item) {
        const option = document.createElement('option');
        option.value = item.value;
        option.textContent = String(item.label);
        return option;
    }));
    if (opsi.some(item => item.value === String(nilai))) select.value = String(nilai);
}
function perbaruiFilter(sumber) {
    let data = dataPengajaran;
    isiSelect(elemenFilter.guru, opsiUnik(data,'guru_id','guru'), sumber==='awal'?pilihanAwal.guru_id:elemenFilter.guru.value);
    data = data.filter(item => String(item.guru_id) === elemenFilter.guru.value);
    isiSelect(elemenFilter.kelas, opsiUnik(data,'kelas_id','kelas'), sumber==='awal'?pilihanAwal.kelas_id:elemenFilter.kelas.value);
    data = data.filter(item => String(item.kelas_id) === elemenFilter.kelas.value);
    isiSelect(elemenFilter.mapel, opsiUnik(data,'mapel_id','mapel'), sumber==='awal'?pilihanAwal.mapel_id:elemenFilter.mapel.value);
    data = data.filter(item => String(item.mapel_id) === elemenFilter.mapel.value);
    isiSelect(elemenFilter.semester, opsiUnik(data,'semester','semester'), sumber==='awal'?pilihanAwal.semester:elemenFilter.semester.value);
    data = data.filter(item => item.semester === elemenFilter.semester.value);
    isiSelect(elemenFilter.tahun, opsiUnik(data,'tahun','tahun'), sumber==='awal'?pilihanAwal.tahun:elemenFilter.tahun.value);
    const terpilih = data.find(item => item.tahun === elemenFilter.tahun.value);
    document.getElementById('pengajaranId').value = terpilih ? terpilih.id : '';
}
Object.values(elemenFilter).forEach(select => select?.addEventListener('change', () => perbaruiFilter('ubah')));
if (dataPengajaran.length) perbaruiFilter('awal');
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
