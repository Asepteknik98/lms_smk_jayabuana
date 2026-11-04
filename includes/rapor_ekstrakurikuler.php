<?php
function rapor_ekstra_key(string $name): string {return preg_replace('/[^\p{L}\p{N}]/u','',mb_strtolower($name));}
function rapor_ekstra_names(array $saved): array {
    $names=[];
    foreach(rapor_ekstra_template($saved) as $row){$name=$row['nama_kegiatan'];$key=rapor_ekstra_key($name);if(!isset($names[$key]))$names[$key]=$name;}
    return array_values($names);
}
function rapor_ekstra_template(array $saved): array {
    $names=['Osis','Pramuka','Paskibra','PMR','Bandung Karate Club','Hutbah Dan Dakwah','Sapabuana','Hadroh','Marawis','Basic English Club','Unit Pelatihan Tilawatil Qur`an','Pencak Silat','Futsal','Bola Voli','Bulutangkis','Bridge','Multimedia','Sepak Bola','Ultras','Buana Literasi','E-Sport','Sekar Suwara Buana','Tari Jabun'];
    $key=static fn($name)=>rapor_ekstra_key((string)$name);
    $result=[];$used=[];
    foreach($names as $name){
        $grade=null;
        foreach($saved as $i=>$row){
            if($key($row['nama_kegiatan']??'')===$key($name)){
                if(($row['grade']??'')!=='')$grade=$row['grade'];
                $used[$i]=true;
            }
        }
        $result[]=['nama_kegiatan'=>$name,'grade'=>$grade,'template'=>true];
    }
    // Retain custom activities from older reports instead of silently discarding grades.
    foreach($saved as $i=>$row)if(!isset($used[$i]))$result[]=$row;
    return $result;
}
