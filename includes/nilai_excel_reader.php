<?php
// Local-only workbook parsing. Uploads are never extracted or saved under the web root.
function ni_xml(string $xml, bool $html=false): DOMDocument {
    if($html)$xml=preg_replace('/<!DOCTYPE\s+html\s*>/i','',$xml);
    if(preg_match('/<!\s*(DOCTYPE|ENTITY)/i',$xml))throw new RuntimeException('File berisi deklarasi XML/HTML yang tidak didukung. Simpan ulang sebagai .xlsx.');
    $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
    try{$ok=$html?$doc->loadHTML('<?xml encoding="UTF-8">'.$xml,LIBXML_NONET):$doc->loadXML($xml,LIBXML_NONET);}
    finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    if(!$ok)throw new RuntimeException('Isi file Excel tidak dapat dibaca.');
    return $doc;
}

function ni_check_xlsx(string $data): void {
    // Bound actual decompression before passing the workbook to the reader.
    $end=strrpos($data,"PK\x05\x06");
    if($end===false||strlen($data)<$end+22)throw new RuntimeException('Arsip Excel rusak.');
    $e=unpack('vdisk/vstart/vlocal/vcount/Vsize/Voffset/vcomment',substr($data,$end+4,18));
    if($e['disk']||$e['start']||$e['count']>256||$e['count']<1)throw new RuntimeException('Struktur arsip Excel tidak didukung.');
    $pos=$e['offset'];$total=0;
    for($i=0;$i<$e['count'];$i++){
        if(substr($data,$pos,4)!=="PK\x01\x02"||strlen($data)<$pos+46)throw new RuntimeException('Direktori Excel rusak.');
        $h=unpack('vversion/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset',substr($data,$pos+4,42));
        if($h['flags']&1||!in_array($h['method'],[0,8],true)||$h['size']>8*1024*1024)throw new RuntimeException('Excel terenkripsi atau terlalu besar untuk diproses.');
        $name=substr($data,$pos+46,$h['name']);$pos+=46+$h['name']+$h['extra']+$h['comment'];
        $offset=$h['offset'];
        if(substr($data,$offset,4)!=="PK\x03\x04"||strlen($data)<$offset+30)throw new RuntimeException('Entri Excel rusak.');
        $local=unpack('vname/vextra',substr($data,$offset+26,4));
        $start=$offset+30+$local['name']+$local['extra'];
        if($start+$h['compressed']>strlen($data))throw new RuntimeException('Entri Excel terpotong.');
        $raw=substr($data,$start,$h['compressed']);
        $raw=$h['method']===8?@gzinflate($raw,8*1024*1024+1):$raw;
        if($raw===false||strlen($raw)!==$h['size'])throw new RuntimeException('Ukuran isi Excel tidak valid.');
        $total+=strlen($raw);if($total>20*1024*1024)throw new RuntimeException('Isi Excel terlalu besar. Pisahkan file per kelas.');
        if(preg_match('/\.(xml|rels)$/i',$name)){
            if(preg_match('/<!\s*(DOCTYPE|ENTITY)/i',$raw))throw new RuntimeException('Deklarasi XML pada Excel tidak didukung.');
            if(str_contains($name,'worksheets/')&&preg_match_all('/\br="([A-Z]+)(\d+)"/',$raw,$refs,PREG_SET_ORDER)){
                foreach($refs as $ref){$col=0;foreach(str_split($ref[1]) as $char)$col=$col*26+ord($char)-64;
                    if($col>100||(int)$ref[2]>5000)throw new RuntimeException('Maksimal 100 kolom dan 5000 baris per sheet.');}
            }
        }
    }
}

function ni_read_excel(string $path, int $sheet=0): array {
    if($sheet<0||$sheet>29)throw new RuntimeException('Nomor sheet harus 1 sampai 30.');
    $size=filesize($path);if($size===false||$size<1||$size>5*1024*1024)throw new RuntimeException('Ukuran file maksimal 5 MB.');
    $data=file_get_contents($path);$rows=[];$name='Sheet '.($sheet+1);
    if(str_starts_with($data,"PK\x03\x04")){
        ni_check_xlsx($data);
        require_once __DIR__.'/vendor/simplexlsx/SimpleXLSX.php';
        $book=\Shuchkin\SimpleXLSX::parseData($data);
        if(!$book||!array_key_exists($sheet,$book->sheetNames()))throw new RuntimeException('File atau nomor sheet Excel tidak valid.');
        $name=$book->sheetName($sheet);[$cols,$count]=$book->dimension($sheet);
        if($cols>100||$count>5000)throw new RuntimeException('Maksimal 100 kolom dan 5000 baris per sheet.');
        $rows=$book->rows($sheet,5001);
    }elseif(str_starts_with($data,"\xD0\xCF\x11\xE0")){
        require_once __DIR__.'/vendor/simplexls/SimpleXLS.php';
        set_error_handler(static function($severity,$message){throw new RuntimeException('File XLS rusak atau tidak didukung. Simpan ulang sebagai .xlsx.');},E_WARNING|E_NOTICE);
        try{$book=\Shuchkin\SimpleXLS::parseData($data);}finally{restore_error_handler();}
        if(!$book||!array_key_exists($sheet,$book->sheetNames()))throw new RuntimeException('File XLS atau nomor sheet tidak valid. Simpan ulang sebagai .xlsx bila perlu.');
        if(($book->sheets[$sheet]['numCols']??0)>100||($book->sheets[$sheet]['numRows']??0)>5000)throw new RuntimeException('Maksimal 100 kolom dan 5000 baris per sheet.');
        $name=$book->sheetName($sheet);$rows=$book->rows($sheet,5001);
    }else{
        $isHtml=!preg_match('/<(?:\w+:)?Workbook\b/i',$data)&&(bool)preg_match('/<(html|table)\b/i',$data);
        $doc=ni_xml($data,$isHtml);$xp=new DOMXPath($doc);
        $tables=$isHtml?$xp->query('//table'):$xp->query('//*[local-name()="Worksheet"]');
        $node=$tables->item($sheet);if(!$node)throw new RuntimeException('File harus berupa Excel .xlsx atau .xls, dan sheet harus tersedia.');
        if(!$isHtml)$name=$node->getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet','Name')?:$name;
        $nodes=$isHtml?$xp->query('.//tr',$node):$xp->query('.//*[local-name()="Row"]',$node);
        $carry=[];$r=0;
        foreach($nodes as $row){
            if(++$r>5000)throw new RuntimeException('Maksimal 5000 baris per sheet.');
            if(!$isHtml){$index=(int)$row->getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet','Index');if($index){if($index>5000)throw new RuntimeException('Indeks baris terlalu besar.');$r=$index;}}
            $values=[];$col=0;
            foreach($row->childNodes as $cell){
                if(!$cell instanceof DOMElement||!in_array(strtolower($cell->localName),['td','th','cell'],true))continue;
                while(isset($carry[$col])&&$carry[$col]>=$r)$col++;
                if(!$isHtml){$index=(int)$cell->getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet','Index');if($index)$col=$index-1;}
                if($col>99)throw new RuntimeException('Maksimal 100 kolom per sheet.');
                $values[$col]=$isHtml?trim($cell->textContent):trim($xp->evaluate('string(./*[local-name()="Data"])',$cell));
                $span=$isHtml?max(1,(int)$cell->getAttribute('colspan')):1+(int)$cell->getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet','MergeAcross');
                $down=$isHtml?max(1,(int)$cell->getAttribute('rowspan')):1+(int)$cell->getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet','MergeDown');
                if($span>100||$down>5000)throw new RuntimeException('Ukuran sel gabungan terlalu besar.');
                if($down>1)for($j=$col;$j<$col+$span;$j++)$carry[$j]=$r+$down-1;
                $col+=$span;
            }
            $rows[$r-1]=$values;
        }
    }
    if(count($rows)>5000)throw new RuntimeException('Maksimal 5000 baris per sheet.');
    foreach($rows as $row){if(count($row)>100)throw new RuntimeException('Maksimal 100 kolom per sheet.');foreach($row as $value)if(is_string($value)&&mb_strlen($value)>4000)throw new RuntimeException('Teks sel terlalu panjang.');}
    return ['sheet'=>$name,'rows'=>$rows];
}
