<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use RuntimeException;

final class GcodeParser
{
    public function parse(string $path): array
    {
        $handle=@fopen($path,'rb');if(!$handle)throw new RuntimeException('Cannot read the G-code file.');$metadata=[];$usage=[];
        try{while(($line=fgets($handle,65536))!==false){$line=trim($line);if(!str_starts_with($line,';'))continue;if(preg_match('/^;\s*filament used \[g\]\s*=\s*(.+)$/i',$line,$match))$usage=$this->numbers($match[1]);foreach(['filament_type','filament_colour','filament_vendor','filament_settings_id','printer_model','printer_settings_id','estimated printing time (normal mode)'] as $key)if(preg_match('/^;\s*'.preg_quote($key,'/').'\s*=\s*(.*)$/i',$line,$match))$metadata[$key]=$this->values($match[1]);}}finally{fclose($handle);}
        if(!$usage)throw new RuntimeException('The G-code does not contain filament usage in grams.');$types=$metadata['filament_type']??[];$colors=$metadata['filament_colour']??[];$consumptions=[];foreach($usage as $index=>$grams)if($grams>0)$consumptions[]=['extruderIndex'=>$index,'estimatedWeightG'=>round($grams,2),'materialType'=>$types[$index]??null,'colorHex'=>$this->color($colors[$index]??null)];if(!$consumptions)throw new RuntimeException('The G-code reports zero filament usage.');return ['consumptions'=>$consumptions,'totalWeightG'=>array_sum(array_column($consumptions,'estimatedWeightG')),'metadata'=>$metadata,'sha256'=>hash_file('sha256',$path)];
    }

    private function numbers(string $value): array{return array_map('floatval',array_filter(array_map('trim',preg_split('/[,;]/',$value)?:[]),static fn(string $item):bool=>$item!==''));}
    private function values(string $value): array{return array_values(array_map(static fn(string $item):string=>trim($item," \t\n\r\0\x0B\""),array_filter(array_map('trim',preg_split('/[;,]/',$value)?:[]),static fn(string $item):bool=>$item!=='')));}
    private function color(?string $value): ?string{$value=trim((string)$value);return preg_match('/^#[0-9A-Fa-f]{6}$/',$value)?strtoupper($value):null;}
}
