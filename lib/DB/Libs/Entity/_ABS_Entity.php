<?php
namespace DB\Libs\Entity;
abstract class _ABS_Entity extends \IEntity{
    protected static function __CR_TB(array $arr){
        $s = "CREATE TABLE ".static::TABLE()."(";
        foreach($arr as $k => $v){
            if(empty($v)){ $v = "TEXT"; }
            $s .= $k." ".$v.",";
        }
        $s.= "collation TEXT, comment TEXT, ";
        $s.= "updated_at TIMESTAMP DEFAULT (datetime(CURRENT_TIMESTAMP,'localtime')) );";
        return $s;
    }
}