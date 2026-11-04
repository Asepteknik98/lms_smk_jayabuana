<?php
require_once __DIR__.'/../config/kredit_poin.php';
require_once __DIR__.'/kredit_akses.php';
require_once __DIR__.'/kredit_pelanggaran_master.php';

function kps_ready(PDO $db): bool {
    $q = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='kredit_poin_siswa'");
    return (bool)$q->fetchColumn();
}

function kps_summary(PDO $db, int $siswa_id): array {
    $q = $db->prepare('SELECT COALESCE(SUM(-perubahan),0) keluar,
        COUNT(*) jumlah FROM kredit_poin_siswa WHERE siswa_id=?');
    $q->execute([$siswa_id]);
    $summary = array_map('intval', $q->fetch());
    $summary['awal'] = KPS_SALDO_AWAL;
    $summary['saldo'] = max(0, KPS_SALDO_AWAL - $summary['keluar']);
    $summary['persentase'] = KPS_SALDO_AWAL > 0 ? (int)round($summary['saldo'] / KPS_SALDO_AWAL * 100) : 0;
    return $summary;
}

function kps_history_page(PDO $db,int $user_id,int $kelas_id,int $siswa_id,int $page,string $search='',bool $all=false): array {
    if(!kredit_access_can($db,$user_id,'poin'))throw new DomainException('Anda tidak memiliki akses riwayat pelanggaran.');
    $where='';$params=[];
    if($siswa_id>0){
        $q=$db->prepare('SELECT id FROM siswa WHERE id=? AND kelas_id=?');$q->execute([$siswa_id,$kelas_id]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Siswa tidak sesuai dengan filter kelas.');
        $where=' WHERE p.siswa_id=?';$params=[$siswa_id];
    }elseif($kelas_id>0){$where=' WHERE s.kelas_id=?';$params=[$kelas_id];}
    $search=mb_substr(trim($search),0,100);
    if($search!==''){
        $where.=($where?' AND ':' WHERE ')."(s.nama_lengkap LIKE ? ESCAPE '!' OR s.nis LIKE ? ESCAPE '!' OR p.alasan LIKE ? ESCAPE '!')";
        $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';array_push($params,$pattern,$pattern,$pattern);
    }
    $from=' FROM kredit_poin_siswa p JOIN siswa s ON s.id=p.siswa_id';
    $q=$db->prepare('SELECT COUNT(*)'.$from.$where);$q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/10));$page=min(max(1,$page),$pages);
    $q=$db->prepare('SELECT p.*,s.nama_lengkap,s.nis,k.nama_kelas,COALESCE(g.nama_lengkap,u.username) pencatat'.$from.
        ' LEFT JOIN kelas k ON k.id=s.kelas_id JOIN users u ON u.id=p.pencatat_id LEFT JOIN guru g ON g.user_id=u.id'.$where.
        ' ORDER BY p.tanggal DESC,p.id DESC'.($all?'':' LIMIT 10 OFFSET '.(($page-1)*10)));
    $q->execute($params);
    return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
}

function kps_record(PDO $db, int $user_id, array $input): bool {
    if(!kredit_access_can($db,$user_id,'poin'))throw new DomainException('Anda belum ditunjuk admin untuk mengelola Kredit Poin Siswa.');
    $actor = $db->prepare('SELECT role_id FROM users WHERE id=? AND is_active=1');
    $actor->execute([$user_id]);
    if (!in_array((int)$actor->fetchColumn(), [1,2], true)) {
        throw new DomainException('Anda tidak memiliki akses untuk mengubah poin.');
    }
    $siswa_id = filter_var($input['siswa_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $kelas_id = filter_var($input['kelas_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $pelanggaran_id=filter_var($input['pelanggaran_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$pelanggaran_id||!kps_catalog_ready($db))throw new InvalidArgumentException('Pilih jenis pelanggaran dari daftar yang tersedia.');
    $catalog=$db->prepare('SELECT * FROM kredit_master_pelanggaran WHERE id=? AND aktif=1 FOR UPDATE');$catalog->execute([$pelanggaran_id]);$pelanggaran=$catalog->fetch();
    if(!$pelanggaran)throw new InvalidArgumentException('Jenis pelanggaran tidak tersedia atau sudah dinonaktifkan.');
    $poin = filter_var(($input['poin']??'')===''?$pelanggaran['poin']:$input['poin'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100000]]);
    $jenis = $input['jenis'] ?? '';
    $alasan = $pelanggaran['nama'];
    if(isset($input['catatan'])&&!is_string($input['catatan']))throw new InvalidArgumentException('Catatan tambahan tidak valid.');
    $catatan=is_string($input['catatan']??null)?trim($input['catatan']):'';
    $tanggal = is_string($input['tanggal'] ?? null) ? $input['tanggal'] : '';
    $key = is_string($input['request_key'] ?? null) ? $input['request_key'] : '';
    $tgl = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal);
    if ($jenis !== 'kurang') {
        throw new InvalidArgumentException('Kredit Poin Siswa hanya dapat dikurangi karena pelanggaran.');
    }
    if (!$kelas_id || !$siswa_id || !$poin ||
        mb_strlen($catatan) > 500 || !$tgl || $tgl->format('Y-m-d') !== $tanggal ||
        $tanggal > date('Y-m-d') || !preg_match('/\A[a-f0-9]{64}\z/', $key)) {
        throw new InvalidArgumentException('Pilih kelas terlebih dahulu, lalu lengkapi siswa, poin pelanggaran 1–100.000, tanggal (maksimal hari ini), dan keterangan pelanggaran maksimal 500 karakter.');
    }
    $q = $db->prepare('SELECT s.id FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.kelas_id=?');
    $q->execute([$siswa_id,$kelas_id]);
    if (!$q->fetchColumn()) throw new InvalidArgumentException('Siswa tidak ditemukan di kelas yang dipilih.');
    $q = $db->prepare('INSERT INTO kredit_poin_siswa(siswa_id,pencatat_id,tanggal,perubahan,alasan,request_key,pelanggaran_id,kategori_pelanggaran,poin_standar,penanganan,catatan)
        VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
    $q->execute([$siswa_id,$user_id,$tanggal,-$poin,$alasan,$key,$pelanggaran_id,$pelanggaran['kategori'],$pelanggaran['poin'],$pelanggaran['penanganan'],$catatan?:null]);
    return $q->rowCount() === 1;
}
