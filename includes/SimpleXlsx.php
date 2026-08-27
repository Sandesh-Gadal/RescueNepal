<?php
/** Minimal XLSX reader/writer for shared-hosting imports/exports. Requires ZipArchive + SimpleXML. */
class SimpleXlsx {
    public static function read(string $path): array {
        if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive extension is required for XLSX import.');
        $zip = new ZipArchive(); if ($zip->open($path)!==true) throw new RuntimeException('Could not open XLSX file.');
        $shared=[]; $s=$zip->getFromName('xl/sharedStrings.xml');
        if($s){$xml=simplexml_load_string($s); foreach($xml->si as $si){$texts=[]; if(isset($si->t))$texts[]=(string)$si->t; foreach($si->r as $r)$texts[]=(string)$r->t; $shared[]=implode('',$texts);}}
        $sheetXml=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close(); if(!$sheetXml) throw new RuntimeException('No first worksheet found.');
        $xml=simplexml_load_string($sheetXml); $rows=[];
        foreach($xml->sheetData->row as $row){$out=[]; foreach($row->c as $c){$ref=(string)$c['r']; preg_match('/([A-Z]+)(\d+)/',$ref,$m); $idx=self::colToIndex($m[1]); $type=(string)$c['t']; $v=(string)$c->v; $value=$type==='s' ? ($shared[(int)$v]??'') : $v; $out[$idx]=$value;} if($out){ksort($out); $max=max(array_keys($out)); $filled=[]; for($i=0;$i<=$max;$i++)$filled[]=$out[$i]??''; $rows[]=$filled;}}
        return $rows;
    }
    public static function write(string $path, array $sheets): void {
        if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive extension is required for XLSX export.');
        $zip=new ZipArchive(); if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Could not create XLSX.');
        $sheetCount=count($sheets); $contentTypes='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for($i=1;$i<=$sheetCount;$i++)$contentTypes.='<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $contentTypes.='</Types>'; $zip->addFromString('[Content_Types].xml',$contentTypes);
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $i=1; foreach($sheets as $name=>$rows){$wb.='<sheet name="'.self::xml($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>'; $rels.='<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>'; $zip->addFromString('xl/worksheets/sheet'.$i.'.xml',self::sheetXml($rows)); $i++;}
        $rels.='<Relationship Id="rId'.($sheetCount+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'; $wb.='</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml',$wb); $zip->addFromString('xl/_rels/workbook.xml.rels',$rels);
        $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B5CAB"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/></cellXfs></styleSheet>'); $zip->close();
    }
    private static function sheetXml(array $rows): string {
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
        foreach($rows as $r=>$row){$rn=$r+1; $xml.='<row r="'.$rn.'">'; foreach(array_values($row) as $c=>$v){$cell=self::indexToCol($c).$rn; $style=$r===0?' s="1"':''; if(is_int($v)||is_float($v))$xml.='<c r="'.$cell.'"'.$style.'><v>'.$v.'</v></c>'; else $xml.='<c r="'.$cell.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::xml((string)$v).'</t></is></c>'; } $xml.='</row>';}
        return $xml.'</sheetData></worksheet>';
    }
    private static function xml(string $s): string { return htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8'); }
    private static function colToIndex(string $letters): int {$n=0; for($i=0;$i<strlen($letters);$i++)$n=$n*26+(ord($letters[$i])-64); return $n-1;}
    private static function indexToCol(int $i): string {$s=''; $i++; while($i>0){$m=($i-1)%26; $s=chr(65+$m).$s; $i=intdiv($i-1,26);} return $s;}
}
