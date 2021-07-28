<?php
class Log extends ILog {
    public static $IS_ASYNC = false;
    public static $IGNORLE_ACCESS = false;
    
    /**
     * アクセスログ
     */
    public static function access(){
        if(self::$IGNORLE_ACCESS !== false){ return; }
        self::tsv("access.log", array("user"=> Auth::getKey()));
    }
    /**
     * インフォメーションログ
     */
    public static function info($log){
        self::tsv("info.log", array("user"=> Auth::getKey(), "info"=>$log));
    }
    /**
     * アクセスエラーログ
     */
    public static function accessError($ex){
        if($ex instanceof Exception){
            self::tsv("access-error.log", array("user"=> Auth::getKey(), "error"=>$ex->getMessage()));
        }else{
            self::tsv("access-error.log", array("user"=> Auth::getKey(), "error"=>$ex));
        }
    }
    /**
     * エラーログ
     */
    public static function error($ex){
        ErrorStack::add($ex);
        if($ex instanceof Exception){
            self::tsv("error.log", array("user"=> Auth::getKey(), "error"=>$ex->getMessage()));
        }else{
            self::tsv("error.log", array("user"=> Auth::getKey(), "error"=>$ex));
        }
    }
    /**
     * 致命的エラーログ
     */
    public static function fatal($ex){
        if($ex instanceof Exception){
            self::tsv("fatal.log", array("user"=> Auth::getKey(), "error"=>$ex->getMessage()));
        }else{
            self::tsv("fatal.log", array("user"=> Auth::getKey(), "error"=>$ex));
        }
    }
}