<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use RuntimeException;

final class GcodeParser
{
    private const METADATA_KEYS=['filament_type','filament_colour','extruder_colour','filament_vendor','filament_settings_id','printer_model','printer_settings_id','estimated printing time (normal mode)'];
    private const MAX_METADATA_SIZE=16777216;

    public function parse(string $path): array
    {
        $handle=@fopen($path,'rb');if(!$handle)throw new RuntimeException('Cannot read the G-code file.');
        try{$magic=$this->read($handle,4,false);rewind($handle);return $magic==='GCDE'?$this->parseBinary($handle,$path):$this->parseText($handle,$path);}finally{fclose($handle);}
    }

    private function parseText($handle,string $path):array
    {
        $metadata=[];$usage=[];while(($line=fgets($handle,65536))!==false){$line=trim($line);if(!str_starts_with($line,';'))continue;if(preg_match('/^;\s*filament used \[g\]\s*=\s*(.+)$/i',$line,$match))$usage=$this->numbers($match[1]);foreach(self::METADATA_KEYS as $key)if(preg_match('/^;\s*'.preg_quote($key,'/').'\s*=\s*(.*)$/i',$line,$match))$metadata[$key]=$this->values($match[1]);}return $this->result($metadata,$usage,$path);
    }

    private function parseBinary($handle,string $path):array
    {
        $header=$this->read($handle,10);$file=unpack('Vmagic/Vversion/vchecksum',$header);if(($file['magic']??0)!==0x45444347||($file['version']??0)!==1||!in_array($file['checksum']??-1,[0,1],true))throw new RuntimeException('Invalid or unsupported BGcode header.');
        $metadata=[];$usage=[];$stat=fstat($handle);$fileSize=(int)($stat['size']??0);
        while(ftell($handle)<$fileSize){$blockHeader=$this->read($handle,8,false);if($blockHeader==='')break;if(strlen($blockHeader)!==8)throw new RuntimeException('Truncated BGcode block header.');$block=unpack('vtype/vcompression/Vuncompressed',$blockHeader);$compression=(int)$block['compression'];if(!in_array($compression,[0,1,2,3],true))throw new RuntimeException('Unsupported BGcode compression type.');if($compression!==0){$extra=$this->read($handle,4);$blockHeader.=$extra;$block['compressed']=unpack('Vsize',$extra)['size'];}$parameterSize=match((int)$block['type']){0,1,2,3,4=>2,5=>6,default=>throw new RuntimeException('Unsupported BGcode block type.')};$parameters=$this->read($handle,$parameterSize);$dataSize=$compression===0?(int)$block['uncompressed']:(int)$block['compressed'];if($dataSize<0||$dataSize>$fileSize-ftell($handle))throw new RuntimeException('Invalid BGcode block size.');$isMetadata=in_array((int)$block['type'],[2,3,4],true);if($isMetadata){if($dataSize>self::MAX_METADATA_SIZE||(int)$block['uncompressed']>self::MAX_METADATA_SIZE)throw new RuntimeException('BGcode metadata is too large.');$encoded=$this->read($handle,$dataSize);}else{if(fseek($handle,$dataSize,SEEK_CUR)!==0)throw new RuntimeException('Cannot skip BGcode block.');$encoded='';}if((int)$file['checksum']===1){$stored=$this->read($handle,4);if($isMetadata&&$stored!==pack('V',crc32($blockHeader.$parameters.$encoded)))throw new RuntimeException('BGcode metadata checksum is invalid.');}if(!$isMetadata)continue;$encoding=unpack('vtype',$parameters)['type'];if($encoding!==0)throw new RuntimeException('Unsupported BGcode metadata encoding.');$decoded=$this->decompress($encoded,$compression,(int)$block['uncompressed']);foreach(preg_split('/\r?\n/',$decoded)?:[] as $line){$position=strpos($line,'=');if($position===false)continue;$key=strtolower(trim(substr($line,0,$position)));$value=trim(substr($line,$position+1));if($key==='filament used [g]')$usage=$this->numbers($value);elseif(in_array($key,self::METADATA_KEYS,true))$metadata[$key]=$this->values($value);}}
        return $this->result($metadata,$usage,$path);
    }

    private function decompress(string $data,int $compression,int $expectedSize):string
    {
        if($compression===0){if(strlen($data)!==$expectedSize)throw new RuntimeException('Invalid BGcode metadata size.');return $data;}if($compression===1){$decoded=@gzuncompress($data,$expectedSize);if($decoded===false||strlen($decoded)!==$expectedSize)throw new RuntimeException('Cannot decompress BGcode metadata.');return $decoded;}throw new RuntimeException('Heatshrink-compressed BGcode metadata is not supported. Export with uncompressed or Deflate metadata.');
    }

    private function result(array $metadata,array $usage,string $path):array
    {
        if(!$usage)throw new RuntimeException('The G-code does not contain filament usage in grams.');$types=$metadata['filament_type']??[];$colors=$metadata['filament_colour']??$metadata['extruder_colour']??[];$consumptions=[];foreach($usage as $index=>$grams)if($grams>0)$consumptions[]=['extruderIndex'=>$index,'estimatedWeightG'=>round($grams,2),'materialType'=>$types[$index]??null,'colorHex'=>$this->color($colors[$index]??null)];if(!$consumptions)throw new RuntimeException('The G-code reports zero filament usage.');return ['consumptions'=>$consumptions,'totalWeightG'=>array_sum(array_column($consumptions,'estimatedWeightG')),'metadata'=>$metadata,'sha256'=>hash_file('sha256',$path)];
    }

    private function read($handle,int $length,bool $required=true):string
    {
        if($length===0)return '';$data='';while(strlen($data)<$length&&!feof($handle)){$chunk=fread($handle,$length-strlen($data));if($chunk===false)throw new RuntimeException('Cannot read BGcode data.');$data.=$chunk;}if($required&&strlen($data)!==$length)throw new RuntimeException('Truncated BGcode file.');return $data;
    }

    private function numbers(string $value): array{return array_map('floatval',array_filter(array_map('trim',preg_split('/[,;]/',$value)?:[]),static fn(string $item):bool=>$item!==''));}
    private function values(string $value): array{return array_values(array_map(static fn(string $item):string=>trim($item," \t\n\r\0\x0B\""),array_filter(array_map('trim',preg_split('/[;,]/',$value)?:[]),static fn(string $item):bool=>$item!=='')));}
    private function color(?string $value): ?string{$value=trim((string)$value);return preg_match('/^#[0-9A-Fa-f]{6}$/',$value)?strtoupper($value):null;}
}
