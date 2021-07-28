<?php
class ContentsResolve{
    private static $CON = ["driver" => "sqlite", "dsn"=> __DIR__."/Contents.db", "ATTR_ERRMOD" => false];
    public static function _DB_CON(){ return self::$CON; }
    public static function getMimeType($filename, $default = "application/octet-stream"){
        $inf = pathinfo($filename);
        $ext = isset($inf["extension"]) ? $inf["extension"] : "";
        if($ext === null || $ext == ""){ return $default; }
        if(!strstr($ext, '.')){ $ext = ".".$ext; }

        $q = new DbQuery(self::$CON);
        $type = $q->table("mime")->where("ext", $ext)->getValue("type");
        if(empty($type)){ return $default; }
        return $type;
    }
}
?>