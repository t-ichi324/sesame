<?php
/**
 *
 * @author ichi
 */
class LangLocale{
    const DIR_NAME = "lang";
    const DEFAULT_LANG = "def";
    const DEFAULT_FILE = "__";
    const INI_EXT = ".ini";
    private static $REP_PTN = "#\{(\d*)\}#";
    public static $ini = array();
    public static $lang = "";
    
    public static function getPath($lang = null, $file = null){
        return Path::app(self::DIR_NAME,$lang,$file);
    }

    public static function setLang($lang){
        $x = StringUtil::lowerCase($lang);
        if(self::$lang !== $x){
            self::$lang = $x;
            self::$ini = array();
        }
    }
    public static function getLang(){
        return self::$lang;
    }
    
    public static function getText($key, ... $args){
        $rpos = strrpos($key, "."); 
        if($rpos > 0){
            $akey = str_replace(".", DIRECTORY_SEPARATOR, substr($key, 0, $rpos));
            $name = substr($key, $rpos + 1);
        }else{
            $akey = self::DEFAULT_FILE;
            $name = $key;
        }
        
        self::readIni($akey);
        if(isset(self::$ini[$akey][$name])){
            if(empty($args)){
                return self::replace(self::$ini[$akey][$name]);
            }
            return self::replace(self::$ini[$akey][$name], $args);
        }
        
        //not found
        if(class_exists("Log")){
            Log::error("LangLocale: NOT FOUND [".$key."]");
        }
        
        return $key;
    }
    public static function echoText($key, ... $args){
        echo self::getText($key, ... $args);
    }
    
    private static function readIni($akey){
        if(!isset(self::$ini[$akey])){
            $lang = (empty(self::$lang) ? self::DEFAULT_LANG : self::$lang);
            $file = $akey.self::INI_EXT;
            
            $path = self::getPath($lang, $file);
            if(file_exists($path)){
                try{
                    self::$ini[$akey] = parse_ini_file($path);
                    return;
                } catch (Exception $ex) {
                    Log::error("LangLocale: ERR [".$ex->getMessage()."]");
                }
            } else {
            }
            self::$ini[$akey] = array();
        }
    }
    
    private static function replace($text, array $args = null){
        return preg_replace_callback(self::$REP_PTN, function ($m) use ($args) {
            $v = trim($m[1]);
            if(!empty($args) && isset($args[$v])){
                return $args[$v];
            }
            return "";
        }, $text);
    }
}
