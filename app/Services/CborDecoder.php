<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use RuntimeException;

final class CborDecoder
{
    private int $offset=0;
    public function __construct(private readonly string $data){}
    public function decode():mixed{return $this->item();}
    private function item():mixed
    {
        if($this->offset>=strlen($this->data))throw new RuntimeException('Truncated CBOR value.');
        $initial=ord($this->data[$this->offset++]);$major=$initial>>5;$length=$this->length($initial&31);
        return match($major){
            0=>$length,
            1=>-1-$length,
            2=>$this->bytes($length),
            3=>$this->bytes($length),
            4=>$this->array($length),
            5=>$this->map($length),
            7=>$this->simple($length),
            default=>throw new RuntimeException('Unsupported CBOR type.'),
        };
    }
    private function length(int $additional):int
    {
        if($additional<24)return $additional;
        $bytes=match($additional){24=>1,25=>2,26=>4,27=>8,default=>throw new RuntimeException('Indefinite CBOR is not supported.')};
        $raw=$this->bytes($bytes);$value=0;for($i=0;$i<$bytes;$i++)$value=($value<<8)|ord($raw[$i]);return $value;
    }
    private function bytes(int $length):string
    {
        if($length<0||$this->offset+$length>strlen($this->data))throw new RuntimeException('Truncated CBOR bytes.');
        $value=substr($this->data,$this->offset,$length);$this->offset+=$length;return $value;
    }
    private function array(int $length):array{$value=[];for($i=0;$i<$length;$i++)$value[]=$this->item();return $value;}
    private function map(int $length):array{$value=[];for($i=0;$i<$length;$i++){$key=$this->item();if(!is_int($key)&&!is_string($key))throw new RuntimeException('Invalid CBOR map key.');$value[$key]=$this->item();}return $value;}
    private function simple(int $value):mixed{return match($value){20=>false,21=>true,22=>null,default=>throw new RuntimeException('Unsupported CBOR simple value.')};}
}
