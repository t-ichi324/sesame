<?php

//DEF **************************************************************************
define("__X_REQUEST_AT", microtime(true));
const __X_LOADER_DIR = __DIR__.DIRECTORY_SEPARATOR;
const __X_LOADER_DIR_APP = __X_LOADER_DIR."app".DIRECTORY_SEPARATOR;
const __X_LOADER_DIR_RES = __X_LOADER_DIR."res".DIRECTORY_SEPARATOR;
const __X_LOADER_DIR_DB = __X_LOADER_DIR."db".DIRECTORY_SEPARATOR;
const __X_LOADER_DIR_LIB = __X_LOADER_DIR."lib".DIRECTORY_SEPARATOR;
const __X_LOADER_DIR_VENDER = __X_LOADER_DIR."vender".DIRECTORY_SEPARATOR;
const __X_LOADER_DIR_RENDER = __X_LOADER_DIR."render".DIRECTORY_SEPARATOR;

if(!defined("__X_FILE_ERR_HTML")){ define("__X_FILE_ERR_HTML", __X_LOADER_DIR_RES."error-html.php"); }
if(!defined("__X_FILE_SYSCONF")){ define("__X_FILE_SYSCONF", "SysConf.php"); }
if(!defined("__X_FILE_DISPATHER")){ define("__X_FILE_DISPATHER", "Sesame.php"); }

//SETUP ************************************************************************
//errors
ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
error_reporting(E_ALL);
//register
register_shutdown_function(function(){
    $ex = error_get_last();
    if(isset($ex['type'])){
        $t = $ex['type'];
        if($t === E_ERROR || $t === E_PARSE || $t === E_CORE_ERROR || $t === E_COMPILE_ERROR || $t === E_USER_ERROR ){
            ob_get_clean();
            HistoryStack::add("shutdown error handle", "", $ex);
            $ex["file"] = _X_ERR_IF_RVD_THROW($ex["file"]);
            ErrorStack::add($ex);
            if(class_exists("Log")){ Log::fatal($ex); }
            require __X_FILE_ERR_HTML;
        }
    }
});
set_error_handler(function($errno, $errstr, $errfile, $errline){
    $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
    if(class_exists("Log")){ Log::error($ex); }
    throw $e;
});


//LOAD *************************************************************************
//SYS
require __X_LOADER_DIR.__X_FILE_SYSCONF;

//APP-DIR
require __X_LOADER_DIR_APP.'stdlib.php';
require __X_LOADER_DIR_APP.'App.php';
require __X_LOADER_DIR_APP.'Bin.php';
require __X_LOADER_DIR_APP.'Auth.php';
require __X_LOADER_DIR_APP.'Meta.php';
//DB
require __X_LOADER_DIR_DB.'loader.php';
//BOOT
require __X_LOADER_DIR.__X_FILE_DISPATHER;


//CLASSES **********************************************************************
class ErrorStack{
    private static $items = array();
    public static function has(){ return !empty(self::items());}
    public static function add($ex){
        if($ex !== null){
            if($ex instanceof Exception){
                $f =  _X_ERR_IF_RVD_THROW($ex->getFile());
                self::$items[] = array("message"=>$ex->getMessage(), "code"=>$ex->getCode(), "file"=>$f, "line"=> $ex->getLine(), "class"=> get_class($ex), "time"=> microtime(true));
             }else{
                self::$items[] = array("class"=>"", "message"=>$ex, "time"=> _X_RESPONSE_TIME());
            }
        }
    }
    public static function items(){return self::$items;}
    public static function echoAll(){
        foreach(self::$items as $e){
            $t = _X_MICROTIME_TO_DATE($e['time'], 'H:i:s');
            echo "ERROR [{$t}]: {$e['class']}\n";
            if(is_string($e["message"])){
                echo "\tmessage => {$e['message']}\n";
            }else{
                echo "\tmessage => "; print_r($e['message']); echo "\n";
            }
            if(isset($e['code'])){ 
                echo "\tcode => {$e['code']}\n";
                echo "\tfile => {$e['file']} ({$e['line']})\n"; 
            }
        }
    }
}
class HistoryStack{
    private static $items = array();
    public static function has(){ return !empty(self::items());}
    public static function add($title, $sub, $param = null){
        self::$items[] = array("title"=>$title, "sub"=>$sub, "param"=>$param, "time"=> _X_RESPONSE_TIME());
    }
    public static function items(){return self::$items;}
    public static function echoAll(){
        foreach(self::$items as $e){
            echo "TRAC [{$e['time']}]: {$e['title']} =>\t {$e['sub']}\n";
            if(!empty($e['param'])){
                $p = _X_EXCEPTION_TO_ARRAY($e['param']);
                if(is_array($p) || is_object($p)){
                    echo "\t=> ".json_encode($p)."\n";
                }else{
                    echo "\t=> ".$p."\n";
                }
            }
        }
    }
}
class ClassLoader{
    private static $PFX_TRAIT = "__";
    private static $PFX_TRAIT_LEN = 2;
    private static $dirs = null;
    
    public static function register(){
        spl_autoload_register(array(self::class, "load"));
    }
    
    public static function addDir(... $expDirs){
        self::$dirs = array_merge($expDirs, self::getDirs());
    }
    
    private static function getDirs(){
        if(self::$dirs === null){
            self::$dirs = __AutoloadMap();
        }
        return self::$dirs;
    }
    private static function gNs($class){
        $ar = explode('\\', $class);
        $c = count($ar);
        $ni = $c-1;
        $r = "";
        for($i = 0; $i < $c; $i++){
            $name = $ar[$i];
            //trait
            if($i === $ni){
                if(substr($name,0, self::$PFX_TRAIT_LEN) === self::$PFX_TRAIT){ $name = substr($name, self::$PFX_TRAIT_LEN); }
            }
            $r .= DIRECTORY_SEPARATOR.$name;
        }
        return $r;
    }
    public static function load($class){
        $ns = self::gNs($class);
        $hists = array();
        foreach(self::getDirs() as $d){
            $dir = rtrim(str_replace("\\", "/", $d), "/");
            $f = $dir.$ns.".php";
            $hists[] = $f;
            if(is_readable($f)){
                require $f;
                return;
            }
        }
        HistoryStack::add(self::class, "Class '".$class."' not found;");
        foreach ($hists as $fn){
            HistoryStack::add(self::class." @ ".$class, $fn);
        }
    }
}
//x-func
function _X_ERR_IF_RVD_THROW($f){ if(defined("_X_RENDER_LOADED")){ $x = RenderVDat::FILE(); if(!empty($x)){ return RenderVDat::FILE(); }} return $f; }
function _X_RESPONSE_TIME(){ return microtime(true) - __X_REQUEST_AT; }
function _X_MICROTIME_TO_DATE($microtime, $format = 'Y-m-d H:i:s'){ $at = explode('.',$microtime); if(count($at) > 1){ return date($format, $at[0]).'.'.$at[1]; }else{ return date($format, $at[0]).'.0'; } }
function _X_EXCEPTION_TO_ARRAY($obj){
    if(empty($obj) || !($obj instanceof Exception)) { return $obj; }
    $r["message"] = $obj->getMessage();
    $r["code"] = $obj->getCode();
    $r["file"] = $obj->getFile();
    $r["line"] = $obj->getLine();
    $r["previous"] = $obj->getPrevious();
    return $r;
}

HistoryStack::add("request", $_SERVER["REQUEST_URI"]);
?>