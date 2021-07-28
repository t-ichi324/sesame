<?php
class ZipUtil {
    public static function unZip($zipfile, $dir){
        if(!is_file($zipfile)){ return false; }
        if(empty($dir)) { return false; }
        if(!is_dir($dir)){ mkdir($dir, 0777); }
        
        set_time_limit(0);
        
        $zip = new ZipArchive();
        if( $zip->open($zipfile) === true){
            $zip->extractTo($dir);
            $zip->close();
            return true;
        }
        return false;
    }
    
    public static function toZip($dir, $zipfile, $containsDirName = false, $callback_func = null){
        if(empty($zipfile)) { return false; }
        
        if(!is_dir($dir)){ return false; }
        
        set_time_limit(0);
        
        $result = array();
        $zdir = "";
        if($containsDirName && is_dir($dir)){ $zdir = pathinfo($dir)["basename"]; }
        self::preZip($result, $dir, $zdir, $callback_func);
        
        if(!empty($result)){
            $zip = new ZipArchive();
            if($zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true ){
                self::addZip($zip, $result);
                $zip->close();
                return true;
            }
        }
        return false;
    }
    
    private static function preZip(array &$result, $dir, $zdir, $callback_func = null){
        if(!file_exists($dir)){ return false; }
        $pi = pathinfo($dir);
        $base = $pi["basename"];
        
        if($callback_func !== null){
            try{
                if($callback_func($base, $zdir) === false){ return false; }
            } catch (Exception $ex) {
                return false;
            }
        }
        
        if(is_dir($dir)){
            $fs = scandir($dir);
            foreach($fs as $f){
                if(empty($f) || $f == "." || $f == ".."){ continue; }
                $path = $dir.DIRECTORY_SEPARATOR.$f;
                if(!file_exists($path)) { continue; }
                $key = (($zdir === "") ? "" : $zdir."/").$f;
                if(is_file($path)){
                    self::preZip($result, $path, $zdir, $callback_func);
                    //$result[$key] = $path;
                }elseif(is_dir($path)){
                    $result[$key] = array();
                    if(self::preZip($result[$key], $path, $key, $callback_func) === false){
                        unset($result[$key]);
                    }
                }
            }
        }else{
            if(is_file($dir)){
                $key = (($zdir === "") ? "" : $zdir."/").$base; 
                $result[$key] = $dir;
            }
        }
        return true;
    }
    private static function addZip(ZipArchive &$zip, array $result){
        if(empty($result)){ return; }
        foreach($result as $k => $v){
            if(is_array($v)){
                $zip->addEmptyDir($k);
                self::addZip($zip, $v);
            }else{
                $zip->addFile($v, $k);
            }
        }
    }
}
?>