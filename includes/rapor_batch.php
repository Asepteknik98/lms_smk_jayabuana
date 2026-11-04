<?php
require_once __DIR__.'/rapor.php';
require_once __DIR__.'/rapor_ekstrakurikuler.php';

function rapor_batch(PDO $db,array $data,int $user): array {
    $kelas=rapor_id($data['kelas_id']??0,'kelas');
    $tahun=rapor_text($data,'tahun_ajaran',10);$semester=rapor_text($data,'semester',10);$jenis=rapor_text($data,'jenis',3);
    if(!$kelas||!preg_match('/^\d{4}\/\d{4}$/',$tahun)||!in_array($semester,['Ganjil','Genap'],true)||!in_array($jenis,['UTS','UAS'],true))throw new RuntimeException('Pilih kelas dan periode yang valid.');
    $action=$data['batch_action']??'';
    if(!in_array($action,['prepare','attendance','extras'],true))throw new RuntimeException('Tindakan kelas tidak valid.');
    if(($data['batch_complete']??'')!=='1')throw new RuntimeException('Data formulir kelas tidak lengkap. Muat ulang halaman atau hubungi administrator bila kelas terlalu besar.');
    $db->beginTransaction();
    try {
        rapor_admin_assert_open($db,$kelas,$tahun,$semester,$jenis);
        $q=$db->prepare('SELECT id FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');$q->execute([$kelas]);$students=$q->fetchAll(PDO::FETCH_COLUMN);
        if(!$students)throw new RuntimeException('Belum ada siswa di kelas ini.');
        $created=0;$skipped=0;$updated=0;
        if($action==='prepare'){
            $ids=$data['pengajaran']??[];
            if(!is_array($ids)||!$ids||count($ids)>100)throw new RuntimeException('Pilih sedikitnya satu mata pelajaran.');
            $q=$db->prepare('SELECT id FROM pengajaran WHERE kelas_id=? AND tahun_ajaran=? AND semester=?');$q->execute([$kelas,$tahun,$semester]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
            $mapel=[];$seen=[];
            foreach($ids as $id){$id=rapor_id($id,'pengajaran');if(!in_array($id,$allowed,true)||isset($seen[$id]))throw new RuntimeException('Pilihan pengajaran tidak sesuai kelas atau periode.');$seen[$id]=true;$mapel[]=['pengajaran_id'=>$id];}
            $common=[];foreach(['nama_wali_kelas','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'] as $key)$common[$key]=rapor_text($data,$key,100);
            foreach($students as $sid){
                $q=$db->prepare('SELECT id FROM rapor WHERE siswa_id=? AND tahun_ajaran=? AND semester=? AND jenis=?');$q->execute([$sid,$tahun,$semester,$jenis]);
                if($q->fetchColumn()){$skipped++;continue;}
                rapor_save($db,array_merge($common,['details_ready'=>'1','siswa_id'=>$sid,'kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis,'mapel'=>$mapel]),$user);$created++;
            }
        }elseif($action==='extras'){
            $payload=rapor_text($data,'extras_payload',2000000);
            try{$rows=json_decode($payload,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('Data ekstrakurikuler tidak valid.');}
            if(!is_array($rows)||!$rows||count($rows)>500)throw new RuntimeException('Daftar ekstrakurikuler siswa tidak valid.');
            $seen=[];
            foreach($rows as $row){
                if(!is_array($row))throw new RuntimeException('Baris ekstrakurikuler tidak valid.');
                $id=rapor_id($row['id']??0,'rapor');$r=rapor_load($db,$id,true);
                if(!$r||isset($seen[$id])||!in_array((string)$r['siswa_id'],array_map('strval',$students),true)||(int)$r['kelas_id']!==$kelas||$r['tahun_ajaran']!==$tahun||$r['semester']!==$semester||$r['jenis']!==$jenis)throw new RuntimeException('Rapor ekstrakurikuler tidak sesuai kelas atau periode.');
                $seen[$id]=true;
                if(!hash_equals(rapor_version($r),rapor_text($row,'version',64)))throw new RuntimeException('Ada rapor yang telah diubah. Muat ulang halaman sebelum menyimpan ekstrakurikuler.');
                $grades=$row['grades']??null;
                if(!is_array($grades)||count($grades)>100)throw new RuntimeException('Daftar kegiatan tidak valid (maksimal 100).');
                $existingActivities=[];foreach($r['ekstra'] as $activity)$existingActivities[rapor_ekstra_key($activity['nama_kegiatan'])][]=(int)$activity['id'];
                $seenActivities=[];
                foreach($grades as $name=>$grade){
                    $name=rapor_text(['name'=>(string)$name],'name',150);
                    if(!$name||!is_string($grade)||!in_array($grade,['','A','B','C','D'],true))throw new RuntimeException('Grade ekstrakurikuler harus A, B, C, D, atau kosong.');
                    $key=rapor_ekstra_key($name);if(isset($seenActivities[$key]))throw new RuntimeException('Kegiatan yang sama tidak boleh dikirim dua kali.');$seenActivities[$key]=true;
                    if(isset($existingActivities[$key]))foreach($existingActivities[$key] as $extraId)$db->prepare('UPDATE rapor_ekstrakurikuler SET grade=? WHERE id=? AND rapor_id=?')->execute([$grade?:null,$extraId,$id]);
                    elseif($grade!==''){$q=$db->prepare('SELECT COALESCE(MAX(urutan),0)+1 FROM rapor_ekstrakurikuler WHERE rapor_id=?');$q->execute([$id]);$db->prepare('INSERT INTO rapor_ekstrakurikuler(rapor_id,nama_kegiatan,grade,urutan) VALUES(?,?,?,?)')->execute([$id,$name,$grade,$q->fetchColumn()]);}
                }
                $q=$db->prepare('SELECT COUNT(*) FROM rapor_ekstrakurikuler WHERE rapor_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>100)throw new RuntimeException('Maksimal 100 kegiatan per siswa.');
                $db->prepare('UPDATE rapor SET updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$user,$id]);$updated++;
                if(rapor_history_ready($db))rapor_history_report($db,$r,rapor_load($db,$id,true),$user,'extras','Memperbarui nilai ekstrakurikuler siswa');
            }
        }else{
            $rows=$data['students']??[];
            if(!is_array($rows)||!$rows||count($rows)>500)throw new RuntimeException('Daftar siswa tidak valid.');
            foreach($rows as $sid=>$row){
                if(!is_array($row)||!in_array((string)$sid,array_map('strval',$students),true))throw new RuntimeException('Siswa tidak sesuai kelas.');
                $id=rapor_id($row['id']??0,'rapor');$r=rapor_load($db,$id,true);
                if(!$r||(int)$r['siswa_id']!==(int)$sid||(int)$r['kelas_id']!==$kelas||$r['tahun_ajaran']!==$tahun||$r['semester']!==$semester||$r['jenis']!==$jenis)throw new RuntimeException('Rapor tidak sesuai kelas atau periode.');
                if(!hash_equals(rapor_version($r),rapor_text($row,'version',64)))throw new RuntimeException('Ada rapor yang telah diubah. Muat ulang halaman sebelum menyimpan tabel kelas.');
                $values=[];foreach(['sakit','izin','alfa'] as $key)$values[]=rapor_number($row[$key]??'', $key);
                $note=array_key_exists('catatan_wali_kelas',$data)?rapor_text($data,'catatan_wali_kelas',4000):(string)($r['catatan_wali_kelas']??'');
                if(($data['preserve_note_if_empty']??'')==='1'&&$note==='')$note=(string)($r['catatan_wali_kelas']??'');
                $values[]=$note;$values[]=$user;$values[]=$id;
                $db->prepare('UPDATE rapor SET sakit=?,izin=?,alfa=?,catatan_wali_kelas=?,updated_by=? WHERE id=?')->execute($values);
                $extraName=rapor_text($data,'extra_name',150);if($extraName==='__custom')$extraName=rapor_text($data,'extra_custom',150);$grade=rapor_text($data,'extra_grade',1);
                if(!empty($row['apply_extra'])){
                    if(!$extraName||!in_array($grade,['A','B','C','D'],true))throw new RuntimeException('Isi nama kegiatan dan grade A–D sebelum menerapkan ekstrakurikuler.');
                    $q=$db->prepare('SELECT id FROM rapor_ekstrakurikuler WHERE rapor_id=? AND nama_kegiatan=?');$q->execute([$id,$extraName]);$extraId=$q->fetchColumn();
                    if($extraId)$db->prepare('UPDATE rapor_ekstrakurikuler SET grade=? WHERE rapor_id=? AND nama_kegiatan=?')->execute([$grade,$id,$extraName]);
                    else {$q=$db->prepare('SELECT COALESCE(MAX(urutan),0)+1 FROM rapor_ekstrakurikuler WHERE rapor_id=?');$q->execute([$id]);$db->prepare('INSERT INTO rapor_ekstrakurikuler(rapor_id,nama_kegiatan,grade,urutan) VALUES(?,?,?,?)')->execute([$id,$extraName,$grade,$q->fetchColumn()]);}
                }
                $updated++;
                if(rapor_history_ready($db))rapor_history_report($db,$r,rapor_load($db,$id,true),$user,'attendance','Memperbarui kehadiran dan catatan wali kelas');
            }
        }
        $db->commit();return compact('created','skipped','updated');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
