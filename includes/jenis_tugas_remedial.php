<?php
function pr_types(): array {
    return ['portofolio'=>'Portofolio','pilihan_ganda'=>'Pilihan Ganda','esai'=>'Essai','youtube'=>'Link Video YouTube','merangkum'=>'Merangkum','video'=>'Pembuatan Video'];
}

function pr_types_ready(PDO $db): bool {
    try {$db->query('SELECT jenis,konfigurasi FROM penugasan_remedial LIMIT 0');$db->query('SELECT file_jawaban,nama_file FROM peserta_penugasan_remedial LIMIT 0');return true;}
    catch(PDOException $e){if(in_array($e->getCode(),['42S22','42S02'],true))return false;throw $e;}
}

function pr_http_url(string $url): bool {
    return strlen($url)<=2000 && filter_var($url,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true);
}

function pr_config(string $type,array $input): array {
    if(!isset(pr_types()[$type]))throw new RuntimeException('Pilih jenis tugas yang tersedia.');
    $config=[];
    if($type==='youtube'){
        $url=$input['youtube_url']??'';
        if(!is_string($url)||!pr_http_url($url)||!in_array(strtolower((string)parse_url($url,PHP_URL_HOST)),['youtube.com','www.youtube.com','m.youtube.com','youtu.be'],true))throw new RuntimeException('Masukkan tautan video YouTube yang valid.');
        $config['youtube_url']=$url;
    }
    if(in_array($type,['pilihan_ganda','esai'],true)){
        $questions=$input['soal']??[];
        if(!is_array($questions)||!count($questions)||count($questions)>100)throw new RuntimeException('Isi 1 sampai 100 soal.');
        foreach($questions as $q){
            if(!is_array($q)||!is_string($q['teks']??null)||trim($q['teks'])===''||mb_strlen($q['teks'])>5000)throw new RuntimeException('Setiap soal wajib diisi, maksimal 5.000 karakter.');
            $item=['teks'=>trim($q['teks'])];
            if($type==='pilihan_ganda'){
                $options=$q['opsi']??[];$key=$q['kunci']??null;
                if(!is_array($options)||count($options)!==4||!is_scalar($key)||!preg_match('/^[0-3]$/D',(string)$key))throw new RuntimeException('Isi empat pilihan dan kunci jawaban pada setiap soal.');
                foreach(array_values($options) as $option){if(!is_string($option)||trim($option)===''||mb_strlen($option)>2000)throw new RuntimeException('Pilihan jawaban wajib diisi, maksimal 2.000 karakter.');$item['opsi'][]=trim($option);}
                $item['kunci']=(int)$key;
            }
            $config['soal'][]=$item;
        }
    }
    return $config;
}

function pr_answer(array $task,array $input): string {
    $type=$task['jenis']??'teks';$config=json_decode($task['konfigurasi']??'[]',true)?:[];
    if(in_array($type,['pilihan_ganda','esai'],true)){
        $answers=$input['jawaban_soal']??[];$text=[];
        if(!is_array($answers))throw new RuntimeException('Format jawaban tidak valid.');
        foreach($config['soal']??[] as $i=>$q){
            $answer=$answers[$i]??null;
            if(!is_string($answer)||trim($answer)==='')throw new RuntimeException('Jawab semua soal terlebih dahulu.');
            if($type==='pilihan_ganda'){
                if(!preg_match('/^[0-3]$/D',$answer))throw new RuntimeException('Pilihan jawaban tidak valid.');
                $answer=chr(65+(int)$answer).'. '.$q['opsi'][(int)$answer];
            }elseif(mb_strlen($answer)>5000)throw new RuntimeException('Jawaban per soal maksimal 5.000 karakter.');
            $text[]=($i+1).'. '.$q['teks']."\nJawaban: ".trim($answer);
        }
        if(!$text)throw new RuntimeException('Soal tugas belum tersedia. Hubungi guru.');
        return implode("\n\n",$text);
    }
    $answer=$input['jawaban']??'';
    if(!is_string($answer))throw new RuntimeException('Jawaban tidak valid.');
    $answer=trim($answer);
    if($type==='video'&&!pr_http_url($answer))throw new RuntimeException('Masukkan tautan video hasil karya Anda yang valid.');
    return $answer;
}

function pr_upload(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>10*1024*1024||!is_uploaded_file($file['tmp_name']??''))throw new RuntimeException('Unggah file portofolio maksimal 10 MB.');
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    $allowed=['pdf'=>['application/pdf'],'docx'=>['application/zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'webp'=>['image/webp']];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if(!isset($allowed[$ext])||!in_array($mime,$allowed[$ext],true))throw new RuntimeException('File harus PDF, DOCX, JPG, PNG, atau WEBP.');
    $name=bin2hex(random_bytes(24)).'.'.$ext;$folder=__DIR__.'/../storage/remedial/';
    if(!is_dir($folder)||!is_file($folder.'.htaccess'))throw new RuntimeException('Penyimpanan portofolio belum tersedia. Hubungi admin.');
    if(!move_uploaded_file($file['tmp_name'],$folder.$name))throw new RuntimeException('File belum berhasil disimpan.');
    return ['file_jawaban'=>$name,'nama_file'=>mb_substr(basename(str_replace('\\','/',$file['name'])),0,255)];
}
