<?php
// Optional, additive administration settings. Existing reports work without this table.
function rapor_admin_ready(PDO $db): bool {
    try{$db->query('SELECT 1 FROM rapor_administrasi LIMIT 0');return true;}
    catch(PDOException $e){if($e->getCode()==='42S02')return false;throw $e;}
}
function rapor_admin_load(PDO $db,int $kelas,string $tahun,string $semester,string $jenis,bool $lock=false): ?array {
    if(!rapor_admin_ready($db))return null;
    $q=$db->prepare('SELECT * FROM rapor_administrasi WHERE kelas_id=? AND tahun_ajaran=? AND semester=? AND jenis=?'.($lock?' FOR UPDATE':''));$q->execute([$kelas,$tahun,$semester,$jenis]);return $q->fetch()?:null;
}
function rapor_admin_version(?array $settings): string {return hash('sha256',json_encode($settings,JSON_THROW_ON_ERROR));}
function rapor_admin_assert_open(PDO $db,int $kelas,string $tahun,string $semester,string $jenis): void {
    // Match the administrator's lock order, including FK parent rows for new reports.
    if($kelas>0&&$db->inTransaction()&&rapor_admin_ready($db)){$q=$db->prepare('SELECT id FROM kelas WHERE id=? FOR UPDATE');$q->execute([$kelas]);$q->fetchColumn();}
    $settings=rapor_admin_load($db,$kelas,$tahun,$semester,$jenis,$db->inTransaction());
    if(!empty($settings['terkunci']))throw new RuntimeException('Rapor kelas dan periode ini sudah dikunci admin. Hubungi admin untuk membuka kembali sebelum melakukan koreksi.');
}
function rapor_admin_complete(array $report): bool {
    if((int)($report['jumlah_mapel']??0)<1||(int)($report['mapel_belum_lengkap']??0)>0)return false;
    foreach(['nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'] as $field)if(trim((string)($report[$field]??''))==='')return false;
    return true;
}
function rapor_admin_reports(PDO $db,string $tahun,string $semester,string $jenis,int $kelas=0): array {
    $sql="SELECT r.*, (SELECT COUNT(*) FROM rapor_mapel m WHERE m.rapor_id=r.id) jumlah_mapel, (SELECT COUNT(*) FROM rapor_mapel m WHERE m.rapor_id=r.id AND (m.nilai_akhir IS NULL OR TRIM(COALESCE(m.deskripsi,''))='' OR TRIM(COALESCE(m.saran,''))='')) mapel_belum_lengkap FROM rapor r WHERE tahun_ajaran=? AND semester=? AND jenis=?";
    $args=[$tahun,$semester,$jenis];if($kelas){$sql.=' AND kelas_id=?';$args[]=$kelas;}$sql.=' ORDER BY nama_kelas,nama_siswa,id';
    $q=$db->prepare($sql);$q->execute($args);$reports=$q->fetchAll();foreach($reports as &$r)$r['lengkap']=rapor_admin_complete($r);unset($r);return $reports;
}
function rapor_admin_save(PDO $db,array $data,int $user): void {
    if(!rapor_admin_ready($db))throw new RuntimeException('Penyimpanan administrasi rapor belum tersedia.');
    $kelas=filter_var($data['kelas_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $tahun=rapor_text($data,'tahun_ajaran',10);$semester=rapor_text($data,'semester',10);$jenis=rapor_text($data,'jenis',3);
    if(!$kelas||!preg_match('/^\d{4}\/\d{4}$/',$tahun)||!in_array($semester,['Ganjil','Genap'],true)||!in_array($jenis,['UTS','UAS'],true))throw new RuntimeException('Pilih kelas dan periode yang valid.');
    $action=rapor_text($data,'admin_action',20);
    if(!in_array($action,['settings','lock','unlock'],true))throw new RuntimeException('Tindakan tidak valid.');
    $db->beginTransaction();
    try{
        // Serialize first-time settings against the existing class row.
        $q=$db->prepare('SELECT id FROM kelas WHERE id=? FOR UPDATE');$q->execute([$kelas]);if(!$q->fetchColumn())throw new RuntimeException('Kelas tidak ditemukan.');
        $old=rapor_admin_load($db,$kelas,$tahun,$semester,$jenis,true);
        if(!hash_equals(rapor_admin_version($old),rapor_text($data,'settings_version',64)))throw new RuntimeException('Pengaturan telah berubah. Muat ulang halaman sebelum menyimpan.');
        if($action==='settings'&&!empty($old['terkunci']))throw new RuntimeException('Buka kunci rapor terlebih dahulu sebelum mengubah pengaturan.');
        $values=$old??['wali_guru_id'=>null,'nama_wali_kelas'=>'','nama_kepala_sekolah'=>'','tempat_terbit'=>'','tanggal_terbit'=>null,'terkunci'=>0];
        if($action==='settings'){
            $guru=filter_var($data['wali_guru_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if($guru===false)throw new RuntimeException('Wali kelas tidak valid.');
            $values['wali_guru_id']=$guru?:null;$values['nama_wali_kelas']='';
            if($guru){$q=$db->prepare('SELECT nama_lengkap FROM guru WHERE id=?');$q->execute([$guru]);$name=$q->fetchColumn();if($name===false)throw new RuntimeException('Guru tidak ditemukan.');$values['nama_wali_kelas']=$name;}
            foreach(['nama_kepala_sekolah','tempat_terbit'] as $key)$values[$key]=rapor_text($data,$key,100);
            $date=rapor_text($data,'tanggal_terbit',10);$dt=$date?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
            if($date&&(!$dt||$dt->format('Y-m-d')!==$date))throw new RuntimeException('Tanggal penerbitan tidak valid.');$values['tanggal_terbit']=$date?:null;
        }else{
            if($action==='lock'){
                $reports=rapor_admin_reports($db,$tahun,$semester,$jenis,$kelas);$q=$db->prepare('SELECT id FROM siswa WHERE kelas_id=?');$q->execute([$kelas]);$students=$q->fetchAll(PDO::FETCH_COLUMN);
                $saved=array_column($reports,'siswa_id');
                if(!$reports||array_filter($reports,static fn($r)=>!$r['lengkap'])||array_diff($students,$saved))throw new RuntimeException('Lengkapi seluruh rapor dan buat rapor siswa yang belum tersedia sebelum mengunci kelas.');
            }
            $values['terkunci']=$action==='lock'?1:0;
        }
        $db->prepare('INSERT INTO rapor_administrasi(kelas_id,tahun_ajaran,semester,jenis,wali_guru_id,nama_wali_kelas,nama_kepala_sekolah,tempat_terbit,tanggal_terbit,terkunci,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE wali_guru_id=VALUES(wali_guru_id),nama_wali_kelas=VALUES(nama_wali_kelas),nama_kepala_sekolah=VALUES(nama_kepala_sekolah),tempat_terbit=VALUES(tempat_terbit),tanggal_terbit=VALUES(tanggal_terbit),terkunci=VALUES(terkunci),updated_by=VALUES(updated_by)')->execute([$kelas,$tahun,$semester,$jenis,$values['wali_guru_id'],$values['nama_wali_kelas'],$values['nama_kepala_sekolah'],$values['tempat_terbit'],$values['tanggal_terbit'],$values['terkunci'],$user]);
        // Updating existing report headers is explicit, optional and transactional.
        if($action==='settings'&&($data['apply_existing']??'')==='1'){
            $previous=[];
            if(rapor_history_ready($db)){foreach(rapor_admin_reports($db,$tahun,$semester,$jenis,$kelas) as $report)$previous[]=rapor_load($db,(int)$report['id'],true);}
            $db->prepare('UPDATE rapor SET nama_wali_kelas=?,nama_kepala_sekolah=?,tempat_terbit=?,tanggal_terbit=?,updated_by=? WHERE kelas_id=? AND tahun_ajaran=? AND semester=? AND jenis=?')->execute([$values['nama_wali_kelas'],$values['nama_kepala_sekolah'],$values['tempat_terbit'],$values['tanggal_terbit'],$user,$kelas,$tahun,$semester,$jenis]);
            foreach($previous as $report)rapor_history_report($db,$report,rapor_load($db,(int)$report['id'],true),$user,'headers','Menerapkan data penerbitan dari admin');
        }
        $historyKeys=array_flip(['wali_guru_id','nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit','terkunci']);
        rapor_history_record($db,['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis],$user,$action,['settings'=>'Mengatur wali kelas dan data penerbitan','lock'=>'Mengunci rapor kelas','unlock'=>'Membuka kembali rapor kelas'][$action],array_intersect_key($old??[],$historyKeys),array_intersect_key(rapor_admin_load($db,$kelas,$tahun,$semester,$jenis,true)??[],$historyKeys));
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
