<?php
/** Small XLSX writer using inline strings; does not require ext-zip or external tools. */
function ew_zip(array $files): string {
    $body='';$directory='';$count=0;
    foreach($files as $name=>$data){
        $offset=strlen($body);$size=strlen($data);$crc=crc32($data);$length=strlen($name);
        $body.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,33,$crc,$size,$size,$length,0).$name.$data;
        $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,33,$crc,$size,$size,$length,0,0,0,0,0,$offset).$name;$count++;
    }
    return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($directory),strlen($body),0);
}
function ew_column(int $index): string {
    $name='';for($index++;$index>0;$index=intdiv($index-1,26))$name=chr(65+(($index-1)%26)).$name;return $name;
}
function ew_workbook(array $sheets): string {
    $x=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1|ENT_SUBSTITUTE,'UTF-8');
    $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $types='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $workbook='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $relationships='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';$files=[];$index=0;
    foreach($sheets as $sheet){
        $index++;$name=$sheet['name'];$rows=$sheet['rows'];$max=max(1,...array_map('count',$rows));$last=ew_column($max-1).max(1,count($rows));
        $types.='<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbook.='<sheet name="'.$x($name).'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
        $relationships.='<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$ns.'"><dimension ref="A1:'.$last.'"/><sheetViews><sheetView workbookViewId="0">';
        $freeze=(int)($sheet['freeze']??1);if($freeze)$xml.='<pane ySplit="'.$freeze.'" topLeftCell="A'.($freeze+1).'" activePane="bottomLeft" state="frozen"/>';
        $xml.='</sheetView></sheetViews><sheetFormatPr defaultRowHeight="22"/><cols>';
        foreach($sheet['widths']??[] as $i=>$width)$xml.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.(float)$width.'" customWidth="1"/>';
        $xml.='</cols><sheetData>';
        foreach($rows as $r=>$row){$xml.='<row r="'.($r+1).'">';foreach($row as $c=>$value){$ref=ew_column($c).($r+1);$style=$r===(int)($sheet['header']??0)?1:(in_array($c,$sheet['numericColumns']??[],true)?2:0);
            if(is_int($value)||is_float($value))$xml.='<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
            else $xml.='<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$x($value).'</t></is></c>';
        }$xml.='</row>';}$xml.='</sheetData>';
        if(!empty($sheet['filter']))$xml.='<autoFilter ref="A'.((int)($sheet['header']??0)+1).':'.$last.'"/>';
        $rules=$sheet['validation']??[];if($rules){$xml.='<dataValidations count="'.count($rules).'">';foreach($rules as $rule){$xml.='<dataValidation type="'.$x($rule['type']).'" allowBlank="1" showErrorMessage="1" errorStyle="stop" errorTitle="Isian tidak valid" error="'.$x($rule['error']).'" sqref="'.$x($rule['range']).'"'.($rule['type']==='whole'||$rule['type']==='decimal'?' operator="between"':'').'><formula1>'.$x($rule['first']).'</formula1>';if(isset($rule['second']))$xml.='<formula2>'.$x($rule['second']).'</formula2>';$xml.='</dataValidation>';}$xml.='</dataValidations>';}
        $files['xl/worksheets/sheet'.$index.'.xml']=$xml.'</worksheet>';
    }
    $files['[Content_Types].xml']=$types.'</Types>';
    $files['_rels/.rels']='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $files['xl/workbook.xml']=$workbook.'</sheets></workbook>';
    $files['xl/_rels/workbook.xml.rels']=$relationships.'<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $files['xl/styles.xml']='<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="'.$ns.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF334155"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EFF8"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFill="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    return ew_zip($files);
}
