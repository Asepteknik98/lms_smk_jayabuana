<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
check_access([2]);

$db = Database::getInstance();
$stmt = $db->prepare('SELECT id FROM guru WHERE user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt->fetchColumn() ?: 0);

$pesan_sukses = $_SESSION['rekap_absensi_success'] ?? '';
$pesan_error = $_SESSION['rekap_absensi_error'] ?? '';
unset($_SESSION['rekap_absensi_success'], $_SESSION['rekap_absensi_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pengajaran_post = (int)($_POST['pengajaran_id'] ?? 0);
    $kelas_post = (int)($_POST['kelas_id'] ?? 0);
    $redirect_url = 'rekap_absensi.php?kelas_id=' . $kelas_post . '&pengajaran_id=' . $pengajaran_post;

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['rekap_absensi_error'] = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
        header('Location: ' . $redirect_url);
        exit;
    }

    $sesi_id = (int)($_POST['sesi_id'] ?? 0);
    $siswa_id = (int)($_POST['siswa_id'] ?? 0);
    $status_baru = $_POST['status'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');

    $stmt = $db->prepare(
        "SELECT sa.id, sa.pertemuan_ke, p.id pengajaran_id, p.kelas_id, s.nama_lengkap,
                da.id detail_id, da.status status_sekarang
         FROM sesi_absensi sa
         JOIN pengajaran p ON p.id = sa.pengajaran_id
         JOIN siswa s ON s.id = ? AND s.kelas_id = p.kelas_id
         LEFT JOIN detail_absensi da ON da.sesi_absensi_id = sa.id AND da.siswa_id = s.id
         WHERE sa.id = ? AND p.id = ? AND p.guru_id = ? AND sa.status = 'Ditutup'"
    );
    $stmt->execute([$siswa_id, $sesi_id, $pengajaran_post, $guru_id]);
    $data_absensi = $stmt->fetch();

    if (!in_array($status_baru, ['Sakit', 'Izin', 'Alpa'], true)) {
        $_SESSION['rekap_absensi_error'] = 'Status absensi tidak valid.';
    } elseif (!$data_absensi) {
        $_SESSION['rekap_absensi_error'] = 'Sesi atau siswa tidak ditemukan pada kelas yang Anda ampu.';
    } else {
        try {
            $stmt = $db->prepare(
                'INSERT INTO detail_absensi (sesi_absensi_id,siswa_id,status,keterangan)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status), keterangan=VALUES(keterangan),
                     waktu_checkin=NULL, ip_address=NULL'
            );
            $stmt->execute([$sesi_id, $siswa_id, $status_baru, $keterangan ?: null]);
            catat_log($_SESSION['user_id'], "Mengubah absensi {$data_absensi['nama_lengkap']} pertemuan {$data_absensi['pertemuan_ke']} menjadi $status_baru");
            $_SESSION['rekap_absensi_success'] = "Absensi {$data_absensi['nama_lengkap']} berhasil diubah menjadi $status_baru.";
        } catch (PDOException $e) {
            error_log('Input manual absensi gagal: ' . $e->getMessage());
            $_SESSION['rekap_absensi_error'] = 'Status absensi gagal disimpan. Silakan coba kembali.';
        }
    }

    header('Location: ' . $redirect_url);
    exit;
}

$stmt = $db->prepare('SELECT p.id,p.kelas_id,m.nama_mapel,k.nama_kelas,p.semester,p.tahun_ajaran FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.guru_id=? ORDER BY k.nama_kelas,p.tahun_ajaran DESC,p.semester,m.nama_mapel');
$stmt->execute([$guru_id]);
$pengajaran_list = $stmt->fetchAll();
$kelas_list = [];
foreach ($pengajaran_list as $item) $kelas_list[(int)$item['kelas_id']] = $item['nama_kelas'];
$requested_pengajaran = (int)($_GET['pengajaran_id'] ?? 0);
$kelas_id = (int)($_GET['kelas_id'] ?? 0);
if ($kelas_id < 1 && $requested_pengajaran > 0) {
    foreach ($pengajaran_list as $item) if ((int)$item['id'] === $requested_pengajaran) { $kelas_id = (int)$item['kelas_id']; break; }
}
if ($kelas_id < 1) $kelas_id = (int)(array_key_first($kelas_list) ?? 0);
$pengajaran_kelas = array_values(array_filter($pengajaran_list, static fn($item) => (int)$item['kelas_id'] === $kelas_id));
$pengajaran_id = $requested_pengajaran;
if (!array_filter($pengajaran_kelas, static fn($item) => (int)$item['id'] === $pengajaran_id)) {
    $pengajaran_id = (int)($pengajaran_kelas[0]['id'] ?? 0);
}

$info = null; $rekap = []; $total_sesi = 0; $sesi_manual = [];
if ($pengajaran_id > 0) {
    $stmt = $db->prepare('SELECT p.id,p.kelas_id,m.nama_mapel,k.nama_kelas,p.semester,p.tahun_ajaran,g.nama_lengkap nama_guru FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id JOIN guru g ON g.id=p.guru_id WHERE p.id=? AND p.guru_id=?');
    $stmt->execute([$pengajaran_id,$guru_id]); $info = $stmt->fetch();
    if (!$info) { http_response_code(403); exit('Pengajaran tidak ditemukan atau bukan milik Anda.'); }
    $stmt = $db->prepare("SELECT COUNT(*) FROM sesi_absensi WHERE pengajaran_id=? AND status='Ditutup'");
    $stmt->execute([$pengajaran_id]); $total_sesi = (int)$stmt->fetchColumn();
    $stmt = $db->prepare("SELECT s.id,s.nisn,s.nama_lengkap,
        SUM(CASE WHEN da.status='Hadir' THEN 1 ELSE 0 END) hadir,
        SUM(CASE WHEN da.status='Sakit' THEN 1 ELSE 0 END) sakit,
        SUM(CASE WHEN da.status='Izin' THEN 1 ELSE 0 END) izin,
        SUM(CASE WHEN sa.id IS NOT NULL AND (da.id IS NULL OR da.status='Alpa') THEN 1 ELSE 0 END) alpa,
        COUNT(sa.id) total_tercatat
        FROM siswa s
        LEFT JOIN sesi_absensi sa ON sa.pengajaran_id=? AND sa.status='Ditutup'
        LEFT JOIN detail_absensi da ON da.sesi_absensi_id=sa.id AND da.siswa_id=s.id
        WHERE s.kelas_id=? GROUP BY s.id ORDER BY s.nama_lengkap");
    $stmt->execute([$pengajaran_id,$info['kelas_id']]); $rekap = $stmt->fetchAll();
    foreach ($rekap as &$row) {
        $row['persentase']=$total_sesi ? ((int)$row['hadir']/$total_sesi)*100 : 0;
    } unset($row);
    $stmt = $db->prepare(
        "SELECT s.id siswa_id, sa.id sesi_id, sa.pertemuan_ke, sa.tanggal,
                COALESCE(da.status, 'Alpa') status, COALESCE(da.keterangan, '') keterangan
         FROM siswa s
         JOIN sesi_absensi sa ON sa.pengajaran_id=? AND sa.status='Ditutup'
         LEFT JOIN detail_absensi da ON da.sesi_absensi_id=sa.id AND da.siswa_id=s.id
         WHERE s.kelas_id=?
         ORDER BY s.id,sa.tanggal DESC,sa.pertemuan_ke DESC"
    );
    $stmt->execute([$pengajaran_id,$info['kelas_id']]);
    foreach ($stmt->fetchAll() as $item) $sesi_manual[(int)$item['siswa_id']][] = $item;
}

$format = $_GET['format'] ?? '';
if (in_array($format,['excel','pdf'],true) && $info) {
    $safe = preg_replace('/[^A-Za-z0-9_-]+/','_',$info['nama_kelas'].'_'.$info['nama_mapel']);
    $filename='Rekap_Absensi_'.trim($safe,'_').'_'.date('Ymd');
    if ($format === 'excel') {
        $xml=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$filename.'.xls"'); header('Cache-Control: no-store');
        echo '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>'; ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="H"><Font ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/></Style><Style ss:ID="T"><Font ss:Bold="1" ss:Size="14"/></Style></Styles><Worksheet ss:Name="Rekap Absensi"><Table>
<Row><Cell ss:MergeAcross="8" ss:StyleID="T"><Data ss:Type="String">REKAP ABSENSI SISWA - SMK JAYA BUANA</Data></Cell></Row>
<Row><Cell ss:MergeAcross="8"><Data ss:Type="String"><?= $xml($info['nama_mapel'].' | '.$info['nama_kelas'].' | '.$info['nama_guru']) ?></Data></Cell></Row>
<Row><Cell ss:MergeAcross="8"><Data ss:Type="String"><?= $xml('Semester '.$info['semester'].' | '.$info['tahun_ajaran'].' | Total sesi: '.$total_sesi) ?></Data></Cell></Row><Row/>
<Row><?php foreach(['No','NISN','Nama Siswa','Hadir','Sakit','Izin','Alpa','Kehadiran (%)'] as $h): ?><Cell ss:StyleID="H"><Data ss:Type="String"><?= $xml($h) ?></Data></Cell><?php endforeach ?></Row>
<?php foreach($rekap as $i=>$r): ?><Row><Cell><Data ss:Type="Number"><?= $i+1 ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['nisn']) ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['nama_lengkap']) ?></Data></Cell><?php foreach(['hadir','sakit','izin','alpa'] as $k): ?><Cell><Data ss:Type="Number"><?= (int)$r[$k] ?></Data></Cell><?php endforeach ?><Cell><Data ss:Type="Number"><?= number_format($r['persentase'],2,'.','') ?></Data></Cell></Row><?php endforeach ?>
</Table></Worksheet></Workbook><?php exit;
    }
    function ra_pdf_text($v){$v=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$v);return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$v);}
    $lines=['REKAP ABSENSI SISWA - SMK JAYA BUANA',$info['nama_mapel'].' | '.$info['nama_kelas'].' | '.$info['nama_guru'],'Semester '.$info['semester'].' | '.$info['tahun_ajaran'].' | Total sesi: '.$total_sesi,str_repeat('-',112),sprintf('%-3s %-15s %-28s %7s %7s %7s %7s %9s','No','NISN','Nama','Hadir','Sakit','Izin','Alpa','Hadir %'),str_repeat('-',112)];
    foreach($rekap as $i=>$r){$nama=function_exists('mb_strimwidth')?mb_strimwidth($r['nama_lengkap'],0,28,'..','UTF-8'):substr($r['nama_lengkap'],0,28);$lines[]=sprintf('%-3d %-15s %-28s %7d %7d %7d %7d %9.2f',($i+1),$r['nisn'],$nama,$r['hadir'],$r['sakit'],$r['izin'],$r['alpa'],$r['persentase']);}
    if(!$rekap)$lines[]='Belum ada siswa pada kelas ini.'; $chunks=array_chunk($lines,47); $objs=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>'];$kids=[];$n=4;
    foreach($chunks as $chunk){$p=$n++;$c=$n++;$kids[]="$p 0 R";$cmd="BT /F1 8 Tf 35 560 Td 11 TL\n";foreach($chunk as $line)$cmd.='('.ra_pdf_text($line).") Tj T*\n";$cmd.='ET';$objs[$p]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $c 0 R >>";$objs[$c]="<< /Length ".strlen($cmd)." >>\nstream\n$cmd\nendstream";}
    $objs[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objs);$pdf="%PDF-1.4\n";$off=[0];foreach($objs as $i=>$o){$off[$i]=strlen($pdf);$pdf.="$i 0 obj\n$o\nendobj\n";}$xref=strlen($pdf);$max=max(array_keys($objs));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ',$off[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');header('Content-Length: '.strlen($pdf));header('Cache-Control: no-store');echo $pdf;exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div id="page-content-wrapper" style="background:#f5f7fb;min-width:0">
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>Rekap Absensi per Kelas</h5><small class="text-muted">Ringkasan kehadiran siswa pada kelas yang Anda ampu</small></div></nav>
<main class="container-fluid p-3 p-md-4" style="max-width:1150px;margin:auto">
<?php if($pesan_sukses): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($pesan_sukses) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
<?php if($pesan_error): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($pesan_error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
<section class="card border-0 shadow-sm mb-3" style="border-radius:18px"><div class="card-body p-3 p-md-4"><form method="get" class="row g-2 align-items-end" id="formFilterRekap"><div class="col-md-4"><label class="form-label fw-semibold">Kelas</label><select name="kelas_id" id="filterKelas" class="form-select" required><?php foreach($kelas_list as $id_kelas=>$nama_kelas): ?><option value="<?= (int)$id_kelas ?>" <?= $kelas_id===(int)$id_kelas?'selected':'' ?>><?= sanitize($nama_kelas) ?></option><?php endforeach ?></select></div><div class="col-md-5"><label class="form-label fw-semibold">Mata Pelajaran</label><select name="pengajaran_id" id="filterPengajaran" class="form-select" required><?php foreach($pengajaran_kelas as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $pengajaran_id===(int)$p['id']?'selected':'' ?>><?= sanitize($p['nama_mapel'].' ('.$p['semester'].' / '.$p['tahun_ajaran'].')') ?></option><?php endforeach ?></select></div><div class="col-md-3"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Tampilkan Rekap</button></div></form></div></section>
<?php if(!$pengajaran_list): ?><div class="alert alert-warning">Admin belum memberikan pengajaran kepada Anda.</div><?php elseif($info): ?>
<section class="card border-0 shadow-sm" style="border-radius:18px"><div class="card-body p-3 p-md-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h6 class="fw-bold mb-1"><?= sanitize($info['nama_mapel'].' — '.$info['nama_kelas']) ?></h6><small class="text-muted">Total <?= $total_sesi ?> sesi absensi</small></div><div class="d-flex gap-2"><a class="btn btn-sm btn-success" href="?pengajaran_id=<?= $pengajaran_id ?>&amp;format=excel"><i class="fa-solid fa-file-excel me-1"></i>Excel</a><a class="btn btn-sm btn-danger" href="?pengajaran_id=<?= $pengajaran_id ?>&amp;format=pdf"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a></div></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>No</th><th>Siswa</th><th>Hadir</th><th>Sakit</th><th>Izin</th><th>Alpa</th><th>Kehadiran</th><th>Aksi</th></tr></thead><tbody><?php foreach($rekap as $i=>$r): ?><tr><td><?= $i+1 ?></td><td><strong><?= sanitize($r['nama_lengkap']) ?></strong><br><small class="text-muted"><?= sanitize($r['nisn']) ?></small></td><td class="text-success"><?= (int)$r['hadir'] ?></td><td><?= (int)$r['sakit'] ?></td><td><?= (int)$r['izin'] ?></td><td class="text-danger"><?= (int)$r['alpa'] ?></td><td><span class="badge bg-primary-subtle text-primary"><?= number_format($r['persentase'],1) ?>%</span></td><td><?php $opsi_sesi=$sesi_manual[(int)$r['id']]??[]; ?><button type="button" class="btn btn-sm btn-outline-warning text-nowrap" <?= !$opsi_sesi?'disabled':'' ?> data-bs-toggle="modal" data-bs-target="#modalAbsensiManual" onclick='isiAbsensiManual(<?= json_encode(["id"=>(int)$r["id"],"nama"=>$r["nama_lengkap"],"sesi"=>$opsi_sesi],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-user-pen me-1"></i>Input Status</button></td></tr><?php endforeach ?><?php if(!$rekap): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada siswa pada kelas ini.</td></tr><?php endif ?></tbody></table></div>
</div></section><?php endif ?>
</main></div>
<div class="modal fade" id="modalAbsensiManual" tabindex="-1" aria-labelledby="judulAbsensiManual" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" id="formAbsensiManual"><div class="modal-header"><div><h5 class="modal-title fw-bold" id="judulAbsensiManual">Input Sakit / Izin</h5><small class="text-muted" id="namaSiswaManual"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="pengajaran_id" value="<?= $pengajaran_id ?>"><input type="hidden" name="kelas_id" value="<?= $kelas_id ?>"><input type="hidden" name="siswa_id" id="siswaIdManual"><div class="alert alert-info small"><i class="fa-solid fa-circle-info me-1"></i>Pilih pertemuan yang sudah ditutup, kemudian tentukan Sakit atau Izin. Status saat ini ditampilkan pada pilihan pertemuan.</div><label class="form-label fw-semibold">Pertemuan</label><select class="form-select mb-3" name="sesi_id" id="sesiManual" required></select><label class="form-label fw-semibold">Status Baru</label><select class="form-select mb-3" name="status" id="statusManual" required><option value="Sakit">Sakit</option><option value="Izin">Izin</option><option value="Alpa">Alpa</option></select><label class="form-label fw-semibold">Keterangan</label><textarea class="form-control" name="keterangan" id="keteranganManual" rows="3" maxlength="255" placeholder="Contoh: Surat dokter / izin dari orang tua"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Status</button></div></form></div></div></div>
<script>
document.getElementById('filterKelas')?.addEventListener('change',function(){document.getElementById('filterPengajaran').disabled=true;document.getElementById('formFilterRekap').submit();});
function isiAbsensiManual(data){
    document.getElementById('siswaIdManual').value=data.id;
    document.getElementById('namaSiswaManual').textContent=data.nama;
    const pilihan=document.getElementById('sesiManual');
    pilihan.replaceChildren();
    data.sesi.forEach(function(sesi){
        const opsi=document.createElement('option');
        opsi.value=sesi.sesi_id;
        opsi.textContent='Pertemuan '+sesi.pertemuan_ke+' · '+formatTanggal(sesi.tanggal)+' · saat ini: '+sesi.status;
        opsi.dataset.status=sesi.status;
        opsi.dataset.keterangan=sesi.keterangan||'';
        pilihan.appendChild(opsi);
    });
    pilihan.onchange=terapkanSesiManual;
    terapkanSesiManual();
}
function terapkanSesiManual(){
    const opsi=document.getElementById('sesiManual').selectedOptions[0];
    if(!opsi)return;
    const statusSekarang=opsi.dataset.status;
    document.getElementById('statusManual').value=['Sakit','Izin','Alpa'].includes(statusSekarang)?statusSekarang:'Sakit';
    document.getElementById('keteranganManual').value=opsi.dataset.keterangan;
}
function formatTanggal(tanggal){const bagian=tanggal.split('-');return bagian.length===3?bagian[2]+'/'+bagian[1]+'/'+bagian[0]:tanggal;}
document.getElementById('formAbsensiManual')?.addEventListener('submit',function(event){
    const opsi=document.getElementById('sesiManual').selectedOptions[0];
    if(opsi&&opsi.dataset.status==='Hadir'&&!confirm('Siswa saat ini tercatat Hadir. Yakin ingin mengubahnya?'))event.preventDefault();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
