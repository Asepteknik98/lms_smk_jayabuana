<?php
require_once __DIR__.'/penilaian_override.php';
require_once __DIR__.'/rapor_administrasi.php';
require_once __DIR__.'/rapor_riwayat.php';

function rapor_ready(PDO $db): bool {
    $q=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('rapor','rapor_mapel','rapor_ekstrakurikuler')");
    return (int)$q->fetchColumn()===3;
}

function rapor_load(PDO $db, int $id, bool $lock=false): ?array {
    $ownsTransaction=!$db->inTransaction();if($ownsTransaction)$db->beginTransaction();
    try{
    $q=$db->prepare('SELECT * FROM rapor WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$r=$q->fetch();
    if(!$r){if($ownsTransaction)$db->commit();return null;}
    foreach(['mapel'=>'rapor_mapel','ekstra'=>'rapor_ekstrakurikuler'] as $key=>$table){
        $q=$db->prepare("SELECT * FROM $table WHERE rapor_id=? ORDER BY urutan,id".($lock?' FOR UPDATE':''));$q->execute([$id]);$r[$key]=$q->fetchAll();
    }
    if($ownsTransaction)$db->commit();return $r;
    }catch(Throwable $e){if($ownsTransaction&&$db->inTransaction())$db->rollBack();throw $e;}
}

function rapor_version(array $r): string { return hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }

// Read-only: all scores use the same components/overrides as the teacher recap.
function rapor_sources(PDO $db, int $kelas, int $siswa, string $tahun, string $semester, string $jenis): array {
    $q=$db->prepare('SELECT id FROM siswa WHERE id=? AND kelas_id=?');$q->execute([$siswa,$kelas]);
    if(!$q->fetchColumn())return [];
    $q=$db->prepare('SELECT p.id pengajaran_id,m.id mapel_id,m.nama_mapel,m.kelompok,g.nama_lengkap nama_guru FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id WHERE p.kelas_id=? AND p.tahun_ajaran=? AND p.semester=? ORDER BY m.nama_mapel,g.nama_lengkap,p.id');
    $q->execute([$kelas,$tahun,$semester]);$sources=[];
    foreach($q->fetchAll() as $p){
        $pid=(int)$p['pengajaran_id'];
        $q=$db->prepare('SELECT kp.id,kp.nama_komponen,kp.bobot,nk.nilai FROM komponen_penilaian kp LEFT JOIN nilai_komponen nk ON nk.komponen_id=kp.id AND nk.siswa_id=? WHERE kp.pengajaran_id=? ORDER BY kp.urutan,kp.id');$q->execute([$siswa,$pid]);$components=$q->fetchAll();
        $auto=po_automatic($db,$pid,$siswa);$total=0;$missing=false;
        foreach($components as $c){$v=$c['nilai']??($auto[$c['nama_komponen']]??null);if($v===null)$missing=true;else $total+=(float)$v*(float)$c['bobot']/100;}
        $ready=abs(array_sum(array_column($components,'bobot'))-100)<.001;
        $q=$db->prepare('SELECT cp.deskripsi,cp.saran FROM komponen_penilaian kp LEFT JOIN capaian_penilaian cp ON cp.komponen_id=kp.id AND cp.siswa_id=? WHERE kp.pengajaran_id=? AND kp.nama_komponen=? ORDER BY kp.id');$q->execute([$siswa,$pid,$jenis]);$notes=$q->fetchAll();
        $p['nilai_akhir']=$ready&&!$missing?round($total,2):null;
        $p['deskripsi']=count($notes)===1?($notes[0]['deskripsi']??''):'';
        $p['saran']=count($notes)===1?($notes[0]['saran']??''):'';
        $p['peringatan']=!$ready?'Bobot belum 100%':($missing?'Nilai belum lengkap':'');
        if(count($notes)>1)$p['peringatan'].=' Komponen '.$jenis.' ganda; periksa rekap.';
        $p['sumber_diambil_pada']=date('Y-m-d H:i:s');$sources[$pid]=$p;
    }
    return $sources;
}

function rapor_text(array $data,string $key,int $max): string {
    $v=$data[$key]??'';
    if(!is_string($v)||mb_strlen(trim($v))>$max)throw new RuntimeException('Isian '.$key.' tidak valid.');
    return trim($v);
}
/** @param mixed $v Raw form input, validated before conversion. */
function rapor_number($v,string $name,bool $nullable=false): ?int {
    if($nullable&&($v===''||$v===null))return null;
    if(!is_scalar($v)||!preg_match('/^\d{1,5}$/',(string)$v)||(int)$v>65535)throw new RuntimeException('Isian '.$name.' harus bilangan bulat 0–65535.');
    return (int)$v;
}
/** @param mixed $value Raw record ID, validated before conversion. */
function rapor_id($value,string $name): int {
    if((!is_int($value)&&!is_string($value))||!preg_match('/^\d{1,10}$/',(string)$value)||(float)$value>4294967295)throw new RuntimeException('ID '.$name.' tidak valid.');
    return (int)$value;
}

function rapor_save(PDO $db,array $data,int $user): int {
    if(($data['details_ready']??'')!=='1')throw new RuntimeException('Formulir belum selesai dimuat. Buka ulang halaman sebelum menyimpan.');
    $id=rapor_id($data['id']??0,'rapor');
    $ownsTransaction=!$db->inTransaction();
    if($ownsTransaction)$db->beginTransaction();
    try {
        // Use the same lock order as class saves and administrative updates.
        if($id){$q=$db->prepare('SELECT kelas_id,tahun_ajaran,semester,jenis FROM rapor WHERE id=?');$q->execute([$id]);$context=$q->fetch();if($context)rapor_admin_assert_open($db,(int)$context['kelas_id'],$context['tahun_ajaran'],$context['semester'],$context['jenis']);}
        $old=$id?rapor_load($db,$id,true):null;
        if($id&&!$old)throw new RuntimeException('Rapor tidak ditemukan.');
        if($old&&!hash_equals(rapor_version($old),rapor_text($data,'version',64)))throw new RuntimeException('Rapor telah diubah pengguna lain. Buka ulang sebelum menyimpan.');
        if($old){$r=$old;}else{
            $sid=rapor_id($data['siswa_id']??0,'siswa');$kelas=rapor_id($data['kelas_id']??0,'kelas');
            $q=$db->prepare('SELECT s.id siswa_id,s.kelas_id,s.nama_lengkap nama_siswa,s.nis,s.nisn,k.nama_kelas,k.jurusan FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.kelas_id=?');$q->execute([$sid,$kelas]);$r=$q->fetch();
            if(!$r)throw new RuntimeException('Siswa tidak sesuai kelas.');
            $r['tahun_ajaran']=rapor_text($data,'tahun_ajaran',10);$r['semester']=rapor_text($data,'semester',10);$r['jenis']=rapor_text($data,'jenis',3);
            if(!preg_match('/^\d{4}\/\d{4}$/',$r['tahun_ajaran'])||!in_array($r['semester'],['Ganjil','Genap'],true)||!in_array($r['jenis'],['UTS','UAS'],true))throw new RuntimeException('Periode rapor tidak valid.');
        }
        rapor_admin_assert_open($db,(int)$r['kelas_id'],$r['tahun_ajaran'],$r['semester'],$r['jenis']);
        foreach(['nomor_absen','sakit','izin','alfa'] as $key)$r[$key]=rapor_number($data[$key]??($key==='nomor_absen'?'':0),$key,$key==='nomor_absen');
        foreach(['nama_wali_kelas'=>100,'nama_kepala_sekolah'=>100,'tempat_terbit'=>100,'catatan_wali_kelas'=>4000] as $key=>$limit)$r[$key]=rapor_text($data,$key,$limit);
        $date=rapor_text($data,'tanggal_terbit',10);$dt=$date?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if($date&&(!$dt||$dt->format('Y-m-d')!==$date))throw new RuntimeException('Tanggal penerbitan tidak valid.');$r['tanggal_terbit']=$date?:null;
        $rows=$data['mapel']??[];$extras=$data['ekstra']??[];
        if(!is_array($rows)||!is_array($extras)||count($rows)>100||count($extras)>100)throw new RuntimeException('Daftar rapor tidak valid (maksimal 100 baris).');
        $existing=[];foreach($old['mapel']??[] as $m)$existing[(int)$m['id']]=$m;
        $sources=null;$details=[];$seen=[];$seenOld=[];
        foreach($rows as $row){
            if(!is_array($row))throw new RuntimeException('Baris mata pelajaran tidak valid.');
            $mid=rapor_id($row['id']??0,'detail');$pid=rapor_id($row['pengajaran_id']??0,'pengajaran');
            if($mid&&(!isset($existing[$mid])||isset($seenOld[$mid])))throw new RuntimeException('Detail rapor tidak valid.');
            $seenOld[$mid]=true;$prev=$existing[$mid]??null;
            if($prev&&(int)$prev['pengajaran_id']===$pid&&empty($row['refresh']))$detail=$prev;
            else {
                if($sources===null)$sources=rapor_sources($db,(int)$r['kelas_id'],(int)$r['siswa_id'],$r['tahun_ajaran'],$r['semester'],$r['jenis']);
                if(!isset($sources[$pid]))throw new RuntimeException('Sumber pengajaran tidak sesuai siswa, kelas, atau periode.');
                $detail=$sources[$pid];
            }
            if($pid&&isset($seen[$pid]))throw new RuntimeException('Pengajaran yang sama tidak boleh ditambahkan dua kali.');$seen[$pid]=true;
            $details[]=$detail;
        }
        $extraRows=[];
        foreach($extras as $row){
            if(!is_array($row))throw new RuntimeException('Ekstrakurikuler tidak valid.');
            $name=rapor_text($row,'nama_kegiatan',150);$grade=rapor_text($row,'grade',1);
            if(!$name&&$grade)throw new RuntimeException('Isi nama kegiatan untuk grade ekstrakurikuler.');
            if(!$name)continue;
            if(!in_array($grade,['','A','B','C','D'],true))throw new RuntimeException('Grade harus A, B, C, atau D.');
            $extraRows[]=[$name,$grade?:null];
        }
        $fields=['siswa_id','kelas_id','tahun_ajaran','semester','jenis','nama_siswa','nis','nisn','nama_kelas','jurusan','nomor_absen','sakit','izin','alfa','catatan_wali_kelas','nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'];
        $args=[];foreach($fields as $key)$args[]=$r[$key]??'';
        // Preserve SQL NULL for optional date and number fields.
        $args[array_search('nomor_absen',$fields,true)]=$r['nomor_absen'];$args[array_search('tanggal_terbit',$fields,true)]=$r['tanggal_terbit'];$args[array_search('kelas_id',$fields,true)]=$r['kelas_id'];
        if($id){$args[]=$user;$args[]=$id;$db->prepare('UPDATE rapor SET '.implode(',',array_map(static fn($f)=>$f.'=?',$fields)).',updated_by=? WHERE id=?')->execute($args);}
        else{$args[]=$user;$args[]=$user;$db->prepare('INSERT INTO rapor ('.implode(',',$fields).',created_by,updated_by) VALUES ('.implode(',',array_fill(0,count($args),'?')).')')->execute($args);$id=(int)$db->lastInsertId();}
        $db->prepare('DELETE FROM rapor_mapel WHERE rapor_id=?')->execute([$id]);
        $q=$db->prepare('INSERT INTO rapor_mapel(rapor_id,pengajaran_id,nama_mapel,nama_guru,kelompok,urutan,nilai_akhir,deskripsi,saran,sumber_diambil_pada) VALUES(?,?,?,?,?,?,?,?,?,?)');
        foreach($details as $i=>$m)$q->execute([$id,$m['pengajaran_id']?:null,$m['nama_mapel'],$m['nama_guru'],$m['kelompok'],$i+1,$m['nilai_akhir'],$m['deskripsi'],$m['saran'],$m['sumber_diambil_pada']]);
        $db->prepare('DELETE FROM rapor_ekstrakurikuler WHERE rapor_id=?')->execute([$id]);$q=$db->prepare('INSERT INTO rapor_ekstrakurikuler(rapor_id,nama_kegiatan,grade,urutan) VALUES(?,?,?,?)');
        foreach($extraRows as $i=>$e)$q->execute([$id,$e[0],$e[1],$i+1]);
        if(rapor_history_ready($db))rapor_history_report($db,$old,rapor_load($db,$id,true),$user,$old?'edit':'create',$old?'Memperbarui rapor siswa':'Membuat draf rapor siswa');
        if($ownsTransaction)$db->commit();return $id;
    }catch(Throwable $e){if($ownsTransaction&&$db->inTransaction())$db->rollBack();throw $e;}
}
