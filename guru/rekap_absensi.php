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
proses_penutupan_absensi_otomatis($db);

$pesan_sukses = $_SESSION['rekap_absensi_success'] ?? '';
$pesan_error = $_SESSION['rekap_absensi_error'] ?? '';
unset($_SESSION['rekap_absensi_success'], $_SESSION['rekap_absensi_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pengajaran_post = (int)($_POST['pengajaran_id'] ?? 0);
    $kelas_post = (int)($_POST['kelas_id'] ?? 0);
    $mode_post = ($_POST['mode'] ?? 'semua') === 'pertemuan' ? 'pertemuan' : 'semua';
    $sesi_filter_post = (int)($_POST['sesi_filter_id'] ?? 0);
    $redirect_url = 'rekap_absensi.php?' . http_build_query([
        'kelas_id' => $kelas_post,
        'pengajaran_id' => $pengajaran_post,
        'mode' => $mode_post,
        'sesi_id' => $sesi_filter_post,
    ]);

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['rekap_absensi_error'] = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
        header('Location: ' . $redirect_url);
        exit;
    }

    $sesi_id = (int)($_POST['sesi_id'] ?? 0);
    $siswa_id = (int)($_POST['siswa_id'] ?? 0);
    $status_dipilih = $_POST['status'] ?? '';
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

    if (!in_array($status_dipilih, ['Hadir', 'PKL', 'Sakit', 'Izin', 'Alpa'], true)) {
        $_SESSION['rekap_absensi_error'] = 'Status absensi tidak valid.';
    } elseif (!$data_absensi) {
        $_SESSION['rekap_absensi_error'] = 'Sesi atau siswa tidak ditemukan pada kelas yang Anda ampu.';
    } else {
        try {
            $status_baru = $status_dipilih === 'PKL' ? 'Hadir' : $status_dipilih;
            if ($status_dipilih === 'PKL') {
                $keterangan = preg_replace('/^PKL(?:\s*-\s*)?/i', '', $keterangan);
                $keterangan = 'PKL' . ($keterangan !== '' ? ' - ' . $keterangan : '');
            }
            $keterangan = mb_substr($keterangan, 0, 255);
            $stmt = $db->prepare(
                'INSERT INTO detail_absensi (sesi_absensi_id,siswa_id,status,keterangan)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status), keterangan=VALUES(keterangan),
                     waktu_checkin=NULL, ip_address=NULL'
            );
            $stmt->execute([$sesi_id, $siswa_id, $status_baru, $keterangan ?: null]);
            catat_log($_SESSION['user_id'], "Mengubah absensi {$data_absensi['nama_lengkap']} pertemuan {$data_absensi['pertemuan_ke']} menjadi $status_dipilih");
            $_SESSION['rekap_absensi_success'] = "Absensi {$data_absensi['nama_lengkap']} berhasil diubah menjadi $status_dipilih.";
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

$mode_rekap = ($_GET['mode'] ?? 'semua') === 'pertemuan' ? 'pertemuan' : 'semua';
$sesi_filter_id = (int)($_GET['sesi_id'] ?? 0);
$info = null; $rekap = []; $rekap_pertemuan = []; $total_sesi = 0; $sesi_manual = []; $daftar_sesi = []; $sesi_terpilih = null; $status_pertemuan = []; $sesi_pertemuan = [];
if ($pengajaran_id > 0) {
    $stmt = $db->prepare('SELECT p.id,p.kelas_id,m.nama_mapel,k.nama_kelas,p.semester,p.tahun_ajaran,g.nama_lengkap nama_guru FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id JOIN guru g ON g.id=p.guru_id WHERE p.id=? AND p.guru_id=?');
    $stmt->execute([$pengajaran_id,$guru_id]); $info = $stmt->fetch();
    if (!$info) { http_response_code(403); exit('Pengajaran tidak ditemukan atau bukan milik Anda.'); }
    $stmt = $db->prepare("SELECT COUNT(*) FROM sesi_absensi WHERE pengajaran_id=? AND status='Ditutup'");
    $stmt->execute([$pengajaran_id]); $total_sesi = (int)$stmt->fetchColumn();
    $stmt = $db->prepare("SELECT id,pertemuan_ke,tanggal,waktu_buka,waktu_tutup,status FROM sesi_absensi WHERE pengajaran_id=? ORDER BY pertemuan_ke DESC,tanggal DESC");
    $stmt->execute([$pengajaran_id]);
    $daftar_sesi = $stmt->fetchAll();
    foreach ($daftar_sesi as $sesi) $sesi_pertemuan[(int)$sesi['pertemuan_ke']] = $sesi;
    if ($mode_rekap === 'pertemuan') {
        foreach ($daftar_sesi as $sesi) {
            if ((int)$sesi['id'] === $sesi_filter_id) {
                $sesi_terpilih = $sesi;
                break;
            }
        }
        if (!$sesi_terpilih && $daftar_sesi) {
            $sesi_terpilih = $daftar_sesi[0];
            $sesi_filter_id = (int)$sesi_terpilih['id'];
        }
    }
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
    if ($sesi_terpilih) {
        $stmt = $db->prepare(
            "SELECT s.id,s.nisn,s.nama_lengkap,
                    CASE WHEN da.status IS NOT NULL THEN da.status WHEN sa.status='Ditutup' THEN 'Alpa' ELSE 'Belum' END status,
                    COALESCE(da.keterangan,'') keterangan,da.waktu_checkin
             FROM siswa s
             JOIN sesi_absensi sa ON sa.id=? AND sa.pengajaran_id=?
             LEFT JOIN detail_absensi da ON da.siswa_id=s.id AND da.sesi_absensi_id=?
             WHERE s.kelas_id=? ORDER BY s.nama_lengkap"
        );
        $stmt->execute([$sesi_filter_id,$pengajaran_id,$sesi_filter_id,$info['kelas_id']]);
        $rekap_pertemuan = $stmt->fetchAll();
    }
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
    $stmt = $db->prepare(
        "SELECT s.id siswa_id,sa.pertemuan_ke,
                CASE
                    WHEN da.status='Hadir' AND UPPER(COALESCE(da.keterangan,'')) REGEXP '^PKL([[:space:]]*-[[:space:]]*)?' THEN 'PKL'
                    WHEN da.status IS NOT NULL THEN da.status
                    WHEN sa.status='Ditutup' THEN 'Alpa'
                    ELSE 'Belum'
                END status
         FROM siswa s
         JOIN sesi_absensi sa ON sa.pengajaran_id=?
         LEFT JOIN detail_absensi da ON da.sesi_absensi_id=sa.id AND da.siswa_id=s.id
         WHERE s.kelas_id=?"
    );
    $stmt->execute([$pengajaran_id,$info['kelas_id']]);
    foreach ($stmt->fetchAll() as $item) $status_pertemuan[(int)$item['siswa_id']][(int)$item['pertemuan_ke']] = $item['status'];
}

$format = $_GET['format'] ?? '';
if (in_array($format,['excel','pdf'],true) && $info) {
    $safe = preg_replace('/[^A-Za-z0-9_-]+/','_',$info['nama_kelas'].'_'.$info['nama_mapel']);
    $label_rekap = $mode_rekap === 'pertemuan' && $sesi_terpilih
        ? 'Pertemuan '.$sesi_terpilih['pertemuan_ke'].' | '.date('d/m/Y',strtotime($sesi_terpilih['tanggal']))
        : 'Semua Pertemuan | Sesi tercatat: '.count($daftar_sesi).' | Sesi ditutup: '.$total_sesi;
    $suffix_rekap = $mode_rekap === 'pertemuan' && $sesi_terpilih ? '_Pertemuan_'.$sesi_terpilih['pertemuan_ke'] : '_Semua_Pertemuan';
    $filename='Rekap_Absensi_'.trim($safe,'_').$suffix_rekap.'_'.date('Ymd');
    if ($format === 'excel') {
        $xml=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$filename.'.xls"'); header('Cache-Control: no-store');
        echo '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>'; ?>
<?php echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="H"><Font ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/></Style><Style ss:ID="T"><Font ss:Bold="1" ss:Size="14"/></Style></Styles><Worksheet ss:Name="Rekap Absensi"><Table>'; ?>
<Row><Cell ss:MergeAcross="8" ss:StyleID="T"><Data ss:Type="String">REKAP ABSENSI SISWA - SMK JAYA BUANA</Data></Cell></Row>
<Row><Cell ss:MergeAcross="8"><Data ss:Type="String"><?= $xml($info['nama_mapel'].' | '.$info['nama_kelas'].' | '.$info['nama_guru']) ?></Data></Cell></Row>
<Row><Cell ss:MergeAcross="8"><Data ss:Type="String"><?= $xml('Semester '.$info['semester'].' | '.$info['tahun_ajaran'].' | '.$label_rekap) ?></Data></Cell></Row><Row/>
<?php if($mode_rekap === 'pertemuan' && $sesi_terpilih): ?>
<Row><?php foreach(['No','NISN','Nama Siswa','Status','Waktu Check-in','Keterangan'] as $h): ?><Cell ss:StyleID="H"><Data ss:Type="String"><?= $xml($h) ?></Data></Cell><?php endforeach ?></Row>
<?php foreach($rekap_pertemuan as $i=>$r): ?><Row><Cell><Data ss:Type="Number"><?= $i+1 ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['nisn']) ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['nama_lengkap']) ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['status']) ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['waktu_checkin']?date('H:i:s',strtotime($r['waktu_checkin'])):'-') ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['keterangan']?:'-') ?></Data></Cell></Row><?php endforeach ?>
<?php else: ?>
<Row><?php foreach(array_merge(['No','NISN','Nama Siswa'],array_map(static fn($p)=>'P'.$p,range(1,20)),['Hadir','Sakit','Izin','Alpa','Kehadiran (%)']) as $h): ?><Cell ss:StyleID="H"><Data ss:Type="String"><?= $xml($h) ?></Data></Cell><?php endforeach ?></Row>
<?php foreach($rekap as $i=>$r): ?><Row><Cell><Data ss:Type="Number"><?= $i+1 ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['nisn']) ?></Data></Cell><Cell><Data ss:Type="String"><?= $xml($r['nama_lengkap']) ?></Data></Cell><?php for($p=1;$p<=20;$p++): ?><Cell><Data ss:Type="String"><?= $xml($status_pertemuan[(int)$r['id']][$p]??'-') ?></Data></Cell><?php endfor ?><?php foreach(['hadir','sakit','izin','alpa'] as $k): ?><Cell><Data ss:Type="Number"><?= (int)$r[$k] ?></Data></Cell><?php endforeach ?><Cell><Data ss:Type="Number"><?= number_format($r['persentase'],2,'.','') ?></Data></Cell></Row><?php endforeach ?>
<?php endif ?>
</Table></Worksheet></Workbook><?php exit;
    }
    function ra_pdf_text(mixed $v): string {$v=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$v);return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$v);}
    $lines=['REKAP ABSENSI SISWA - SMK JAYA BUANA',$info['nama_mapel'].' | '.$info['nama_kelas'].' | '.$info['nama_guru'],'Semester '.$info['semester'].' | '.$info['tahun_ajaran'].' | '.$label_rekap,str_repeat('-',112)];
    if($mode_rekap === 'pertemuan' && $sesi_terpilih){$lines[]=sprintf('%-3s %-15s %-28s %-10s %-12s %-32s','No','NISN','Nama','Status','Check-in','Keterangan');$lines[]=str_repeat('-',112);foreach($rekap_pertemuan as $i=>$r){$nama=function_exists('mb_strimwidth')?mb_strimwidth($r['nama_lengkap'],0,28,'..','UTF-8'):substr($r['nama_lengkap'],0,28);$ket=function_exists('mb_strimwidth')?mb_strimwidth($r['keterangan']?:'-',0,32,'..','UTF-8'):substr($r['keterangan']?:'-',0,32);$lines[]=sprintf('%-3d %-15s %-28s %-10s %-12s %-32s',$i+1,$r['nisn'],$nama,$r['status'],$r['waktu_checkin']?date('H:i:s',strtotime($r['waktu_checkin'])):'-',$ket);}}
    else{$lines[]='Kode: H=Hadir, P=PKL (dihitung Hadir), S=Sakit, I=Izin, A=Alpa, B=Belum check-in, -=Belum ada sesi';$lines[]=sprintf('%-3s %-13s %-20s %s %3s %3s %3s %3s %6s','No','NISN','Nama',implode(' ',array_map(static fn($p)=>sprintf('%02d',$p),range(1,20))),'H','S','I','A','%');$lines[]=str_repeat('-',112);foreach($rekap as $i=>$r){$nama=function_exists('mb_strimwidth')?mb_strimwidth($r['nama_lengkap'],0,20,'..','UTF-8'):substr($r['nama_lengkap'],0,20);$kode=[];for($p=1;$p<=20;$p++){$status=$status_pertemuan[(int)$r['id']][$p]??'-';$kode[]=in_array($status,['Hadir','PKL','Sakit','Izin','Alpa','Belum'],true)?$status[0]:'-';}$lines[]=sprintf('%-3d %-13s %-20s %s %3d %3d %3d %3d %6.1f',$i+1,$r['nisn'],$nama,implode('  ',$kode),$r['hadir'],$r['sakit'],$r['izin'],$r['alpa'],$r['persentase']);}}
    if(!($mode_rekap === 'pertemuan' ? $rekap_pertemuan : $rekap))$lines[]='Belum ada siswa pada kelas ini.'; $chunks=array_chunk($lines,47); $objs=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>'];$kids=[];$n=4;
    foreach($chunks as $chunk){$p=$n++;$c=$n++;$kids[]="$p 0 R";$cmd="BT /F1 8 Tf 35 560 Td 11 TL\n";foreach($chunk as $line)$cmd.='('.ra_pdf_text($line).") Tj T*\n";$cmd.='ET';$objs[$p]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $c 0 R >>";$objs[$c]="<< /Length ".strlen($cmd)." >>\nstream\n$cmd\nendstream";}
    $objs[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objs);$pdf="%PDF-1.4\n";$off=[0];foreach($objs as $i=>$o){$off[$i]=strlen($pdf);$pdf.="$i 0 obj\n$o\nendobj\n";}$xref=strlen($pdf);$max=max(array_keys($objs));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ',$off[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');header('Content-Length: '.strlen($pdf));header('Cache-Control: no-store');echo $pdf;exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div id="page-content-wrapper" style="background:#f5f7fb;min-width:0">
<nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>Rekap Absensi</h5><small class="text-muted">Rekap per pertemuan dan seluruh pertemuan kelas</small></div></nav>
<main class="container-fluid p-3 p-md-4" style="max-width:1150px;margin:auto">
<?php if($pesan_sukses): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($pesan_sukses) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
<?php if($pesan_error): ?><div class="alert alert-danger alert-dismissible fade show"><?= sanitize($pesan_error) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
<section class="card border-0 shadow-sm mb-3" style="border-radius:18px"><div class="card-body p-3 p-md-4"><form method="get" class="row g-2 align-items-end" id="formFilterRekap">
<div class="col-md-3"><label class="form-label fw-semibold">Kelas</label><select name="kelas_id" id="filterKelas" class="form-select" required><?php foreach($kelas_list as $id_kelas=>$nama_kelas): ?><option value="<?= (int)$id_kelas ?>" <?= $kelas_id===(int)$id_kelas?'selected':'' ?>><?= sanitize($nama_kelas) ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label class="form-label fw-semibold">Mata Pelajaran</label><select name="pengajaran_id" id="filterPengajaran" class="form-select" required><?php foreach($pengajaran_kelas as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $pengajaran_id===(int)$p['id']?'selected':'' ?>><?= sanitize($p['nama_mapel'].' ('.$p['semester'].' / '.$p['tahun_ajaran'].')') ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label class="form-label fw-semibold">Jenis Rekap</label><select name="mode" id="filterMode" class="form-select"><option value="semua" <?= $mode_rekap==='semua'?'selected':'' ?>>Semua Pertemuan</option><option value="pertemuan" <?= $mode_rekap==='pertemuan'?'selected':'' ?>>Per Pertemuan</option></select></div>
<div class="col-md-3"><label class="form-label fw-semibold">Pertemuan</label><select name="sesi_id" id="filterSesi" class="form-select" <?= $mode_rekap==='semua'?'disabled':'' ?>><?php if(!$daftar_sesi): ?><option value="">Belum ada sesi absensi</option><?php else: foreach($daftar_sesi as $sesi): ?><option value="<?= (int)$sesi['id'] ?>" <?= $sesi_filter_id===(int)$sesi['id']?'selected':'' ?>>Pertemuan <?= (int)$sesi['pertemuan_ke'] ?> · <?= date('d/m/Y',strtotime($sesi['tanggal'])) ?> · <?= sanitize($sesi['status']) ?></option><?php endforeach; endif ?></select></div>
<div class="col-12"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Tampilkan Rekap</button></div>
</form></div></section>
<?php if(!$pengajaran_list): ?><div class="alert alert-warning">Admin belum memberikan pengajaran kepada Anda.</div><?php elseif($info): ?>
<section class="card border-0 shadow-sm" style="border-radius:18px"><div class="card-body p-3 p-md-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h6 class="fw-bold mb-1"><?= sanitize($info['nama_mapel'].' — '.$info['nama_kelas']) ?></h6><small class="text-muted"><?= $mode_rekap==='pertemuan'&&$sesi_terpilih?'Pertemuan '.(int)$sesi_terpilih['pertemuan_ke'].' · '.date('d/m/Y',strtotime($sesi_terpilih['tanggal'])).' · '.$sesi_terpilih['status']:'Semua pertemuan · '.count($daftar_sesi).' sesi tercatat · '.$total_sesi.' sesi ditutup' ?></small></div><div class="d-flex gap-2"><?php $query_ekspor=http_build_query(['kelas_id'=>$kelas_id,'pengajaran_id'=>$pengajaran_id,'mode'=>$mode_rekap,'sesi_id'=>$sesi_filter_id]); ?><a class="btn btn-sm btn-success" href="?<?= sanitize($query_ekspor) ?>&amp;format=excel"><i class="fa-solid fa-file-excel me-1"></i>Excel</a><a class="btn btn-sm btn-danger" href="?<?= sanitize($query_ekspor) ?>&amp;format=pdf"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a></div></div>
<?php if($mode_rekap==='pertemuan'): ?>
<?php if(!$sesi_terpilih): ?><div class="alert alert-info mb-0">Belum ada sesi absensi yang ditutup untuk mata pelajaran ini.</div><?php else: ?>
<div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>No</th><th>Siswa</th><th>Status</th><th>Waktu Check-in</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody><?php foreach($rekap_pertemuan as $i=>$r): ?><tr><td><?= $i+1 ?></td><td><strong><?= sanitize($r['nama_lengkap']) ?></strong><br><small class="text-muted"><?= sanitize($r['nisn']) ?></small></td><td><span class="badge <?= $r['status']==='Hadir'?'bg-success':($r['status']==='Alpa'?'bg-danger':($r['status']==='Belum'?'bg-secondary':'bg-warning text-dark')) ?>"><?= sanitize($r['status']) ?></span></td><td><?= $r['waktu_checkin']?date('H:i:s',strtotime($r['waktu_checkin'])):'—' ?></td><td><?= sanitize($r['keterangan']?:'—') ?></td><td><?php if($sesi_terpilih['status']==='Ditutup'): $opsi_sesi=$sesi_manual[(int)$r['id']]??[]; ?><button type="button" class="btn btn-sm btn-outline-warning text-nowrap" data-bs-toggle="modal" data-bs-target="#modalAbsensiManual" onclick='isiAbsensiManual(<?= json_encode(["id"=>(int)$r["id"],"nama"=>$r["nama_lengkap"],"sesi"=>array_values(array_filter($opsi_sesi,static fn($s)=>(int)$s["sesi_id"]===$sesi_filter_id))],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-user-pen me-1"></i>Ubah Status</button><?php else: ?><span class="badge bg-success-subtle text-success">Sesi masih dibuka</span><?php endif ?></td></tr><?php endforeach ?></tbody></table></div>
<?php endif ?>
<?php else: ?>
<div class="d-flex flex-wrap gap-2 mb-3 small"><span class="badge bg-success">H = Hadir</span><span class="badge bg-primary">P = PKL</span><span class="badge bg-warning text-dark">S = Sakit</span><span class="badge bg-info text-dark">I = Izin</span><span class="badge bg-danger">A = Alpa</span><span class="badge bg-secondary">B = Belum check-in</span><span class="badge bg-light text-dark border">— = Belum ada sesi</span></div>
<div class="table-responsive"><table class="table table-hover table-bordered align-middle text-center" style="font-size:.875rem"><thead class="table-light"><tr><th rowspan="2">No</th><th rowspan="2" class="text-start" style="min-width:180px">Siswa</th><th colspan="20">Status Pertemuan</th><th colspan="5">Ringkasan</th><th rowspan="2">Aksi</th></tr><tr><?php for($p=1;$p<=20;$p++): ?><th title="Pertemuan <?= $p ?>">P<?= $p ?></th><?php endfor ?><th>H</th><th>S</th><th>I</th><th>A</th><th>%</th></tr></thead><tbody><?php foreach($rekap as $i=>$r): ?><tr><td><?= $i+1 ?></td><td class="text-start"><strong><?= sanitize($r['nama_lengkap']) ?></strong><br><small class="text-muted"><?= sanitize($r['nisn']) ?></small></td><?php for($p=1;$p<=20;$p++): $status_p=$status_pertemuan[(int)$r['id']][$p]??null; $kode_p=$status_p?substr($status_p,0,1):'—'; $kelas_p=$status_p==='Hadir'?'bg-success':($status_p==='PKL'?'bg-primary':($status_p==='Sakit'?'bg-warning text-dark':($status_p==='Izin'?'bg-info text-dark':($status_p==='Alpa'?'bg-danger':($status_p==='Belum'?'bg-secondary':'bg-light text-dark border'))))); ?><td title="<?= sanitize($status_p?:'Belum ada sesi') ?>"><span class="badge <?= $kelas_p ?>"><?= $kode_p ?></span></td><?php endfor ?><td class="text-success fw-semibold"><?= (int)$r['hadir'] ?></td><td><?= (int)$r['sakit'] ?></td><td><?= (int)$r['izin'] ?></td><td class="text-danger fw-semibold"><?= (int)$r['alpa'] ?></td><td><span class="badge bg-primary-subtle text-primary"><?= number_format($r['persentase'],1) ?>%</span></td><td><?php $opsi_sesi=$sesi_manual[(int)$r['id']]??[]; ?><button type="button" class="btn btn-sm btn-outline-warning text-nowrap" <?= !$opsi_sesi?'disabled':'' ?> data-bs-toggle="modal" data-bs-target="#modalAbsensiManual" onclick='isiAbsensiManual(<?= json_encode(["id"=>(int)$r["id"],"nama"=>$r["nama_lengkap"],"sesi"=>$opsi_sesi],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-user-pen me-1"></i>Input Status</button></td></tr><?php endforeach ?><?php if(!$rekap): ?><tr><td colspan="28" class="text-center text-muted py-4">Belum ada siswa pada kelas ini.</td></tr><?php endif ?></tbody></table></div>
<?php endif ?>
</div></section><?php endif ?>
</main></div>
<div class="modal fade" id="modalAbsensiManual" tabindex="-1" aria-labelledby="judulAbsensiManual" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" id="formAbsensiManual"><div class="modal-header"><div><h5 class="modal-title fw-bold" id="judulAbsensiManual">Ubah Status Absensi</h5><small class="text-muted" id="namaSiswaManual"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="pengajaran_id" value="<?= $pengajaran_id ?>"><input type="hidden" name="kelas_id" value="<?= $kelas_id ?>"><input type="hidden" name="mode" value="<?= sanitize($mode_rekap) ?>"><input type="hidden" name="sesi_filter_id" value="<?= $sesi_filter_id ?>"><input type="hidden" name="siswa_id" id="siswaIdManual"><div class="alert alert-info small"><i class="fa-solid fa-circle-info me-1"></i>Pilih pertemuan yang sudah ditutup, kemudian tentukan status baru. PKL akan tercatat dan dihitung sebagai Hadir.</div><label class="form-label fw-semibold">Pertemuan</label><select class="form-select mb-3" name="sesi_id" id="sesiManual" required></select><label class="form-label fw-semibold">Status Baru</label><select class="form-select mb-3" name="status" id="statusManual" required><option value="Hadir">Hadir</option><option value="PKL">PKL</option><option value="Sakit">Sakit</option><option value="Izin">Izin</option><option value="Alpa">Alpa</option></select><label class="form-label fw-semibold">Keterangan</label><textarea class="form-control" name="keterangan" id="keteranganManual" rows="3" maxlength="255" placeholder="Contoh: Surat dokter / izin dari orang tua / lokasi PKL"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Status</button></div></form></div></div></div>
<script>
document.getElementById('filterKelas')?.addEventListener('change',function(){document.getElementById('filterPengajaran').disabled=true;document.getElementById('formFilterRekap').submit();});
document.getElementById('filterPengajaran')?.addEventListener('change',function(){document.getElementById('filterSesi').disabled=true;document.getElementById('formFilterRekap').submit();});
document.getElementById('filterMode')?.addEventListener('change',function(){document.getElementById('filterSesi').disabled=this.value!=='pertemuan';document.getElementById('formFilterRekap').submit();});
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
    const keterangan=opsi.dataset.keterangan||'';
    const statusTampilan=statusSekarang==='Hadir'&&/^PKL(?:\s*-\s*)?/i.test(keterangan)?'PKL':statusSekarang;
    document.getElementById('statusManual').value=['Hadir','PKL','Sakit','Izin','Alpa'].includes(statusTampilan)?statusTampilan:'Sakit';
    document.getElementById('keteranganManual').value=statusTampilan==='PKL'?keterangan.replace(/^PKL(?:\s*-\s*)?/i,''):keterangan;
}
function formatTanggal(tanggal){const bagian=tanggal.split('-');return bagian.length===3?bagian[2]+'/'+bagian[1]+'/'+bagian[0]:tanggal;}
document.getElementById('formAbsensiManual')?.addEventListener('submit',function(event){
    const opsi=document.getElementById('sesiManual').selectedOptions[0];
    if(opsi&&opsi.dataset.status==='Hadir'&&!confirm('Siswa saat ini tercatat Hadir. Yakin ingin mengubahnya?'))event.preventDefault();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
