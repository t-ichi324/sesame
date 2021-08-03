<?php
/**
 * @author "https://arc.isukasoft.com/"
 * @version "sesame-2.x"
 */
/** ExceptionのラッパーClass. */
abstract class IException extends Exception{abstract public function statusCode(); }
/** コンテンツが存在しない. */
class NoContentException extends IException{public function statusCode(){return 204;}}
/** リクエストが不正である. */
class BadRequestException extends IException{public function statusCode(){return 400;}}
/** 認証エラー. */
class UnauthorizedhException extends IException{public function statusCode(){return 401;}}
/** アクセスが許可されていない場合(CRLF不一致などでも利用). */
class ForbiddenException extends IException {public function statusCode(){return 403;}}
/** リソースが見つからない場合. */
class NotFoundException extends IException {public function statusCode(){return 404;}}
/** システムエラー. */
class InternalServerException extends IException{public function statusCode(){return 500;} }
/** IPフィルター等にヒット. */
class FilterException extends IException{public function statusCode(){return 404;} }

/** Sesameの親クラス. */
abstract class ISesameBase {
    abstract static function run();
    
    protected static function runResult($result){
        \Bin\ResultResolver::run($result);
    }

    public static function setConfig($confName){
        define("__X_FILE_SITE_CONF", $confName);
        
        if(!file_exists(__X_FILE_SITE_CONF)){
            HistoryStack::add("error", "config not foud");
            echo "NOT FOUND COUNFIG. Please check file.<br> - ".__X_FILE_SITE_CONF;
            exit();
        }
        
        include_once __X_FILE_SITE_CONF;
        date_default_timezone_set(Conf::ZONE_ID);
        set_time_limit(Conf::TIME_lIMIT);
        
        //Cookie（クッキー）を送信するパスの設定を行います。
        ini_set("session.cookie_path", Cookie::PATH());
        
        if(defined("SESSION_TIMEOUT")){ ini_set( 'session.gc_maxlifetime', SESSION_TIMEOUT);}
        
        if(defined("COOKIE_NAME")){ session_name(COOKIE_NAME); }
        session_start();
        
        //restore model
        if(isset($_SESSION[SysConf::SESSION_REDIRECT_MODELS_NAME]) && !empty($_SESSION[SysConf::SESSION_REDIRECT_MODELS_NAME])){
            foreach ($_SESSION[SysConf::SESSION_REDIRECT_MODELS_NAME] as $k => $v){ \Bin\Dat::$_MODELS[$k] = $v; }
        }
        unset($_SESSION[SysConf::SESSION_REDIRECT_MODELS_NAME]);
        
        //メモリの上限値を設定します。
        // 上限設定を無くす場合（メモリを無制限にする場合）には「-1」を設定して下さい。
        ini_set("memory_limit", Conf::MEMORY_LIMIT);
        
        ClassLoader::register();
        
        HistoryStack::add("config", "load", $confName);
        
        \Bin\HandleResolver::findRoute();
    }
}


/** Controller親クラス. */
abstract class IController{
    /** <b>[ABSTRACT]</b> */
    public abstract function index();
    
    /**
     * mapping 
     * protected > public にオーバライドして使用 */
    protected function __id($name){}

    /**
     * 事前実行ファンクション
     * @param string $func invoke function名
     */
    public function __pre_invoke_handler($func, $callname){}
    /**
     * 事後実行ファンクション
     * @param string $func invoke function名
     */
    public function __after_invoke_handler($func, $callname){}
    /**
     * Exception実行ファンクション
     * @param string $func invoke function名
     */
    public function __exception_invoke_handler($func, $callname, Exception $ex){}
    /**
     * NotFound実行ファンｋション
     * @param string $func invoke function名
     */
    public function __not_found($func, $callname){ throw new NotFoundException(); }
}

/** ErrorController親クラス. */
abstract class IErrorController extends IController{
    /** <b>[ABSTRACT]</b> */
    public abstract function code($ex, $statusCode);
}

/** 認証が必要なController親クラス. */
abstract class IAuthController extends IController{ }

class Async{
    public static function php($phpfile, ... $params){
        $args = "";
        foreach($params as $v){ $args.= " ".$v; }
        if (strpos(PHP_OS, 'WIN')!==false){
            //windows
            $cmd = "php ".$phpfile."".$args;
            pclose(popen("start /B ".$cmd, 'r'));
        }
        else {
            //linux
            $cmd = "nohup php -c '".$phpfile."'".$args." > /dev/null &";
            exec($cmd);
        }
    }
}

/**
 * 環境判断<br>
 */
class Env{
    private static $r = null;
    /** 
     * <b>ENV_FILE</b>の内容を取得
     */
    public static function name(){
        if(self::$r === null){
            if(defined("ENV_FILE") && is_file(ENV_FILE)){ self::$r = strtolower(str_replace("\n", '', file_get_contents(ENV_FILE))); }
            if(empty(self::$r)){ self::$r = "real";}
        }
        return self::$r;
    }
    /** 
     * <b>ENV_FILE</b>の内容が、$nameと同じか判断
     */
    public static function is($name){ return (strtolower($name) == self::name()); }
    /** 
     * <b>ENV_FILE</b>の内容が、"real"であるか
     */
    public static function isReal(){ return ("real" == self::name()); }
    /** 
     * <b>ENV_FILE</b>の内容が、"draft"であるか
     */
    public static function isDraft(){ return ("draft" == self::name()); }
    /** 
     * <b>ENV_FILE</b>の内容が、"test"であるか
     */
    public static function isTest(){ return ("test" == self::name()); }
    /** 
     * <b>ENV_FILE</b>の内容が、"dev"であるか
     */
    public static function isDev(){ return ("dev" == self::name()); }
    
    public static function timezone(){ return date_default_timezone_get(); }
    public static function max_size_memory(){ return NumUtil::parse_size(ini_get("memory_limit")); }
    public static function max_size_post(){
        $max = self::max_size_memory();
        $c = NumUtil::parse_size(ini_get("post_max_size"));
        if($max > 0 && $c > $max){  $c = $max; }
        return $c;
    }
    public static function max_size_uploads(){
        $max = self::max_size_post();
        $c = NumUtil::parse_size(ini_get("upload_max_filesize"));
        if($max > 0 && $c > $max){  $c = $max; }
        return $c;
    }
}

/**
 * ルーティング.<br/>
 * ex:<br/>
 * <self>::add("privacy", "index", "privacy"); // get and post.
 * <self>::add("contact", "contact"); // get ony.
 * <self>::add("contact", "contact", "post"); // get ony.
 */
class Route{
    public static function add($uri, $route, array $arg = null){ \Bin\RouteingMap::set("", $uri, $route, "r", $arg);}
    public static function get($uri, $route, array $arg = null){ \Bin\RouteingMap::set("get", $uri, $route, "r", $arg);}
    public static function post($uri, $route, array $arg = null){ \Bin\RouteingMap::set("get", $uri, $route, "r", $arg);}
    public static function head($uri, $route, array $arg = null){ \Bin\RouteingMap::set("get", $uri, $route, "r", $arg);}
    public static function deny($uri){ \Bin\RouteingMap::set("", $uri, null, "deny", null); }
}

abstract class ILog{
    public static $__ASYNC = false;
    
    abstract public static function access();
    abstract public static function info($msg);
    abstract public static function error($ex);
    abstract public static function accessError($ex);
    abstract public static function fatal($ex);

    protected static function _write($file, $line){
        try{
            if(class_exists("Path")){
                if(!is_dir(Path::log())){ mkdir(Path::log(), 0777, true); }
                $saveTo = Path::log($file);
            }else{
                $saveTo = __DIR__.DIRECTORY_SEPARATOR.$file;
            }
            if(static::$__ASYNC){
                $log64 = __X_LOADER_DIR_RES."async-log64.php";
                Async::php($log64, base64_encode($line), $saveTo);
            }else{
                error_log($line."\n", 3, $saveTo);
            }
        } catch (Exception $ex) {}
    }
    protected static function _defaultset($log, $sid, $ua) {
        if(class_exists("Response")){ $code = Response::getStatusCode(); }else{$code = "---"; }
        $a = array(
            'date' => date("Y-m-d H:i:s"), 
            'ip'=> $_SERVER["REMOTE_ADDR"],
            'code' => $code, 
            'method'=> $_SERVER["REQUEST_METHOD"],
            'uri'=> $_SERVER["REQUEST_URI"],
        );
        if(is_array($log) || is_object($log)){
            $a = array_merge($a, _X_EXCEPTION_TO_ARRAY($log));
        }else{
            $a["log"] = $log;
        }
        if($sid){ $a["sid"] = session_id(); }
        if($ua){ $a["ua"] = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : ""; }
        return $a;
    }
    
    public static function plain($file, $text){
        self::_write($file, $text);
    }
    public static function json($file, $log, $sid=true, $ua=true){
        $line = json_encode(static::_defaultset($log, $sid, $ua));
        self::_write($file, $line);
    }
    public static function csv($file, $log, $sid=true, $ua=true){
        try{
            $a = static::_defaultset($log, $sid, $ua);
            $line = "";
            foreach ($a as $v){
                if(is_array($v) || is_object($v)){ $v = json_encode($v); }
                if($line !== ""){  $line.=","; }
                $line.= '"'.str_replace('"', '""', $v).'"';
            }
            self::_write($file, $line);
        } catch (Exception $ex) {}
    }
    public static function tsv($file, $log, $sid=true, $ua=true){
        try{
            $a = static::_defaultset($log, $sid, $ua);
            $line = "";
            foreach ($a as $v){
                if(is_array($v) || is_object($v)){ $v = json_encode($v); }
                if($line !== ""){  $line.="\t"; }
                $line.= str_replace("\t", "   ", $v);
            }
            self::_write($file, $line);
        } catch (Exception $ex) {}
    }
}

////////////////////////////////////////////////////////////////////////////////
// MODELS
////////////////////////////////////////////////////////////////////////////////

/**
 * データ格納
 */
abstract class IData{
    private static function u2b_64($url64){ return str_replace(".", "/", str_replace("-", "+", $url64)); }
    private static function b2u_64($base64){ return str_replace("/", ".", str_replace("+", "-", str_replace("=", "", $base64))); }
    
    /**
     * <p>コンストラクタ.</p>
     * @param $source [IData継承インスタンス] or [連想配列] or [JSON文字列] or NULL
     * @param $ignoreNull NULLデータを無視するか
     */
    function __construct($source = null, $ignoreNull = false, $overwrite = false){
        $this->bind($source, $ignoreNull, $overwrite);
    }
    
    /**
     * <p>データバインド.</p>
     * @param $source [IData継承インスタンス] or [連想配列] or [JSON文字列]
     * @param $ignoreNull NULLデータを無視するか
     */
    public function bind($source = null, $ignoreNull = false, $overwrite = false){
        if(empty($source)){
            if(!$ignoreNull && !$overwrite){ $this->clear(); }
            return null;
        }
        if(is_array($source)){
             $this->bindArray($source, $ignoreNull, $overwrite);
        }else if($source instanceof IData){
            $this->bindData($source, $ignoreNull, $overwrite);
        }else if(is_string($source)){ 
             $this->bindJson($source, $ignoreNull, $overwrite);
        }
    }
    
    /**
     * <p>フィールド名(public)のフィールドが存在するか.</p>
     * @param $key フィールド名
     * @return bool
     */
    public function hasField($key){
        return in_array($key, $this->getFields());
    }
    
    /**
     * <p>フィールド名(public)の一覧を取得する.</p>
     * @return array
     */
    protected function getFields(){
        $ref = new ReflectionClass($this);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
        $ret = array();
        foreach($props as $p){
            if(!$p->isStatic()){
                $ret[] = $p->getName();
            }
        }
        return $ret;
    }
    
    /** <p>情報をクリア</p> */
    public function clear(){
        $props = $this->getFields();
        foreach($props as $p){ $this->$p = null; }
    }
    
    /**
     * <p>[IData継承インスタンス]データバインド.</p>
     * @param $source [IData継承インスタンス]
     * @param $ignoreNull NULLデータを無視するか
     */
    public function bindData(IData $data, $ignoreNull = false, $overwrite = false){
        $props = $this->getFields();
        foreach($props as $p){
            //連想配列Keyとフィールド名が一致するデータをセット
            if(! $overwrite){
                $this->$p = null;
            }
            if(property_exists($data, $p)){
                if(!($ignoreNull && $data->$p === null)){
                    $this->$p = $data->$p;
                }
            }
        }
    }
    /**
     * <p>[連想配列]データバインド.</p>
     * @param $source [連想配列]
     * @param $ignoreNull NULLデータを無視するか
     */
    public function bindArray(array $row, $ignoreNull = false, $overwrite = false){
        $props = $this->getFields();

        foreach($props as $p){
            if(! $overwrite){
                $this->$p = null;
            }
            if(isset($row[$p])){
                if(!($ignoreNull && $row[$p] === null)){
                    $this->$p = $row[$p];
                }
            }
        }
    }
    
    /**
     * <p>連想配列へ変換</p>
     * @param $ignores [可変長配列]無視するフィールド名
     * @return array [連想配列]
     */
    public function toArray(... $ignores){
        $props = $this->getFields();
        $row = array();
        foreach($props as $p){
            $row[$p] = $this->$p;
        }
        if(!empty($ignores)){
            foreach($ignores as $ig){
                unset($row[$ig]);
            }
        }
        return $row;
    }
    
    /**
     * <p>連想配列へ変換（NULLデータを除く）</p>
     * @param $ignores [可変長配列]無視するフィールド名
     * @return array [連想配列]
     */
    public function toArray_ignoreNull(... $ignores){
        $props = $this->getFields();
        $row = array();
        foreach($props as $p){
            $v = $this->$p;
            if($v === null || $v === "") { continue; }
            $row[$p] = $this->$p;
        }
        if(!empty($ignores)){
            foreach($ignores as $ig){
                if(isset($row[$ig])){
                    unset($row[$ig]);
                }
            }
        }
        return $row;
    }
    
    /**
     * <p>[JSON]データバインド.</p>
     * @param $source [JSON文字列]
     * @param $ignoreNull NULLデータを無視するか
     */
    public function bindJson($json, $ignoreNull = false, $overwrite = false){
        $row = json_decode($json, true);
        if($row == null){
            $row = array();
        }
        $this->bindArray($row, $ignoreNull, $overwrite);
    }
    
    /**
     * <p>JSON形式へ変換</p>
     * @param $ignores [可変長配列]無視するフィールド名
     * @return string [JSON文字列]
     */
    public function toJson(... $ignores){
        return json_encode($this->toArray(...$ignores), JSON_UNESCAPED_UNICODE);
    }
    /**
     * <p>JSON形式へ変換（NULLデータを除く）</p>
     * @param $ignores [可変長配列]無視するフィールド名
     * @return string [JSON文字列]
     */
    public function toJson_ignoreNull(... $ignores){
        $a = $this->toArray_ignoreNull(...$ignores);
        if(empty($a)){ return "";}
        return json_encode($a, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * <p>[BASE64(json)]データバインド.</p>
     * @param $base64 $source [BASE64(json)文字列]
     * @param bool $ignoreNull NULLデータを無視するか
     */
    public function bindBase64($base64, $ignoreNull = false, $overwrite = false){
        $this->bindJson(base64_decode($base64), $ignoreNull, $overwrite);
    }
    /**
     * <p>JSON形式データをBASE64へ変換した文字列を取得</p>
     * @param string $ignores [可変長配列]無視するフィールド名
     * @return string [BASE64文字列]
     */
    public function toBase64(...  $ignores){
        return base64_encode($this->toJson(...$ignores));
    }
    /**
     * <p>JSON形式データをBASE64へ変換した文字列を取得（NULLデータを除く）</p>
     * @param string $ignores [可変長配列]無視するフィールド名
     * @return string [BASE64文字列]
     */
    public function toBase64_ignoreNull(...  $ignores){
        $j = $this->toJson_ignoreNull(...$ignores);
        if(empty($j)){ return ""; }
        return base64_encode($j);
    }

    /**
     * <p>[Url64(json)]データバインド.</p>
     * @param string $url64 [Url64(json)文字列]
     * @param bool $ignoreNull NULLデータを無視するか
     */
    public function bindUrl64($url64, $ignoreNull = false, $overwrite = false){
        $this->bindBase64(self::u2b_64($url64), $ignoreNull, $overwrite);
    }
    /**
     * <p>JSON形式データをURL64へ変換した文字列を取得</p>
     * @param string $ignores [可変長配列]無視するフィールド名
     * @return string [URL64文字列]
     */
    public function toUrl64(...  $ignores){
        return self::b2u_64($this->toBase64(...$ignores));
    }
    /**
     * <p>JSON形式データをURL64へ変換した文字列を取得（NULLデータを除く）</p>
     * @param string $ignores [可変長配列]無視するフィールド名
     * @return string [URL64文字列]
     */
    public function toUrl64_ignoreNull(...  $ignores){
        return self::b2u_64($this->toBase64_ignoreNull(... $ignores));
    }
    
    /**
     * <p>フィールドの値を取得</p>
     * @param string $key フィールド名
     * @param mixed $nullVal 該当しない場合のデフォルト
     * @return 値
     */
    public function getVal($key, $nullVal = null){
        if(property_exists($this, $key)){
            return $this->$key;
        }else{
            return $nullVal;
        }
    }
    /**
     * <p>フィールドの値を設定</p>
     * @param string $key フィールド名
     * @param string $val 値
     */
    public function setVal($key, $val){
        if(property_exists($this, $key)){
            $this->$key = $val;
        }
    }
    
    /**
     * <p>フィールドの値が(empty)か判定</p>
     * @param string $key フィールド名
     * @return bool
     */
    public function isEmpty($key){
        if(property_exists($this, $key)){
            return empty($this->$key);
        }
        return false;
    }
    /**
     * <p>フィールドの値が(empty)ではないか判定</p>
     * @param string $key フィールド名
     * @return bool
     */
    public function isNotEmpty($key){
        if(property_exists($this, $key)){
            return !empty($this->$key);
        }
        return false;
    }
    /**
     * <p>フィールドの何れかの値が(empty)か判定</p>
     * @param string $keys ...フィールド名
     * @return bool
     */
    public function isAnyEmpty(... $keys){
        foreach($keys as $k){
            if($this->isEmpty($k)){
                return true;
            }
        }
        return false;
    }
    /**
     * <p>フィールドの全ての値が(empty)か判定</p>
     * @param string $keys ...フィールド名
     * @return bool
     */
    public function isAllEmpty(... $keys){
        foreach($keys as $k){
            if(!$this->isEmpty($k)){
                return false;
            }
        }
        return true;
    }
    /**
     * <p>フィールドの全ての値が(empty)ではないか判定</p>
     * @param string $keys ...フィールド名
     * @return bool
     */
    public function isNotAllEmpty(... $keys){
        foreach($keys as $k){
            if($this->isEmpty($k)){
                return false;
            }
        }
        return true;
    }
}

/**
 * <p>Entity基盤クラス / abstract</p>
 */
abstract class IEntity extends IData{
    /** <p>SQLなどで利用する、TABLE名 / VIEW名、もしくはQuery文字列を返す。 </p> */
    public static function TABLE(){ return __CLASS__; }
    /*protected $__not_mapping__;*/
    private static $__props = array();
    private static function __getprp_key($v){ return strtolower(str_replace("_", "", $v)); }
    function __set($name, $value) {
        $cl = get_called_class();
        if(!isset(self::$__props[$cl])){ foreach($this->getFields() as $v){ self::$__props[$cl][self::__getprp_key($v)] = $v;}}
        $pn = self::__getprp_key($name);
        if(isset(self::$__props[$cl][$pn])){
            $p = self::$__props[$cl][$pn];
            $this->$p = $value;
        }
        /*$this->__not_mapping__[$name] = $value;*/
    }
    function __get($name) { return;/*return $this->__not_mapping__[$name];*/ }
}

/**
 * <p>フォーム情報保持 / static</p>
 */
class Form {
    //フォームソース
    private static $source = array();
    //Formインスタンス
    private static $bundling = null;
    private static $injected = 0;
    private static $path = null;
    private static function inject(){
        if(self::$injected !== 0) { return; }
        self::$source = Request::getFormValues();
        $ag = \Bin\RouteingMap::getArguments();
        if(!empty($ag)){
            foreach ($ag as $k => $v){
                if(!isset(self::$source[$k])){
                    self::$source[$k] = $v;
                }
            }
        }
        self::$injected = 1;
    }
    public static function getSource(){
        self::inject();
        return self::$source;
    }
    public static function bundle(IForm &$form){
        if($form !== null){
            self::$injected = 2;
            self::$bundling = $form;
        }
    }
    public static function getFormObject(){
        return self::$bundling;
    }
    public static function getCsrfToken(){
        return self::get(Secure::getCsrfName(), "");
    }
    public static function hasList(){
        if(self::$bundling === null){ return false; }
        if(self::$bundling instanceof IListForm){
            return self::$bundling->hasList();
        }
        return false;
    }
    public static function getList(){
        if(self::hasList()){
            return self::$bundling->getList();
        }
        return array();
    }
    public static function get($key = null, $nullVal = null){
        if($key === null){
            if(self::$bundling === null){ return self::$source; }
            return self::$bundling;
        }
        return self::getVal($key, $nullVal);
    }
    public static function getVal($key, $nullVal = null){
        self::inject();
        if(self::$injected === 1){
            if(isset(self::$source[$key])){ return self::$source[$key]; }
            return $nullVal;
        }else{
            return self::$bundling->getVal($key, $nullVal);
        }
    }
    
    public static function setVal($key, $val){
        self::inject();
        if(self::$injected === 1){
            self::$source[$key] = $val;
        }else{
            self::$bundling->setVal($key, $val);
        }
    }

    private static function s2a(... $ignores){
        $row = self::getSource();
        if(!empty($ignores)){
            foreach($ignores as $ig){ unset($row[$ig]); }
        }
        return $row;
    }
    public static function toJson(... $ignores){
        self::inject();
        if(self::$injected === 1){
            $a = self::s2a(...$ignores);
            return json_encode($a, JSON_UNESCAPED_UNICODE);
        }else{
            return self::$bundling->toJson_ignoreNull(... $ignores);
        }
    }
    public static function toBase64(...  $ignores){
        self::inject();
        if(self::$injected === 1){
            return base64_encode(self::toJson($ignores));
        }else{
            return self::$bundling->toBase64(... $ignores);
        }
    }
    
    public static function isEmpty($key){
        return empty(self::get($key));
    }
    public static function isNotEmpty($key){
        return !empty(self::get($key));
    }
    public static function isAnyEmpty(... $keys){
        foreach($keys as $k){
            if(self::isEmpty($k)){
                return true;
            }
        }
        return false;
    }
    public static function isAllEmpty(... $keys){
        foreach($keys as $k){
            if(!self::isEmpty($k)){
                return false;
            }
        }
        return true;
    }
    public static function isNotAllEmpty(... $keys){
        foreach($keys as $k){
            if(self::isEmpty($k)){
                return false;
            }
        }
        return true;
    }
    
    public static function path($setter = null){
        if(func_num_args() > 0){ self::$path = trim(explode('?',$setter)[0], '/'); }
        if(empty(self::$path)){
            return trim(explode('?',Request::getPathInfo())[0], '/');
        }
        return self::$path;
    }
}

/**
 * <p>単Form基盤クラス / abstract</p>
 */
abstract class IForm extends IData{
    function __construct($auto_inject = true) {
        parent::__construct();
        if($auto_inject){
            $this->__inject();
            $this->__bundle();
        }
    }
    function __destruct(){
        //
    }
    /** <p>Formクラスより情報を取得</p> */
    public function __inject(){ 
        if(Request::isAjax() && Form::isNotEmpty(SysConf::X_REFRESH_PARAM_NAME)){
            $this->bindBase64(Form::get(SysConf::X_REFRESH_PARAM_NAME));
            return;
        }
        $this->bind(Form::getSource());
    }
    
    /** <p>Formクラスへバンドル</p> */
    public function __bundle(){ Form::bundle($this);}
    
    /** <p>エラーが存在するか</p> */
    public function hasError(){ return false; }
    
    /** <p>DbQueryをセット(selectFirst()を実行しバインド)</p> */
    public function setDbQuery(DbQuery $q, ...$fields){ $this->bind($q->selectFirst(...$fields)); }
    
    /** <p>クエリ文字列を取得</p> */
    public function queryString(...$ignores){ return Url::queryString($this->toArray(...$ignores)); }
    
    public function tag_refresh(){
        $data = $this->toBase64_ignoreNull();
        return "<input type='hidden' name='".h(SysConf::X_REFRESH_PARAM_NAME)."' value='".h($data)."'>";
    }
}
/**
 * <p>リストForm基盤クラス / abstract</p>
 */
abstract class IListForm extends IForm{
    private $_idx_max   = 0;
    private $_idx_start = 0;
    private $_idx_end   = 0;
    private $takeLimit = 20;
    private $_list = array();
    public $p;
    public $take;
    public $sort;
    public $desc;
    
    public function setRawList(array $list){
        $this->_idx_max = count($list);
        $this->_idx_start = 1;
        $this->_list = $list;
        $this->_idx_end = $this->_idx_start + count($this->_list) - 1;
    }
    /** <p>DbQueryをセット(select()を実行し、$listへセット)</p> */
    public function setDbQueryList(\DbQuery $q, ...$fields){
        $ac_on = $q->autoClear_Flag();
        $q->offset(null)->limit(null);
        $this->_idx_max = $q->autoClaer_OFF()->getCount();
        $this->_idx_start = ($this->getPage()-1) * $this->getLimit() + 1;
        $q->offset($this->_idx_start - 1)->limit($this->getLimit());
        if($ac_on){ $q->autoClear_ON(); }
        if(!empty($this->sort)){
            if(empty($this->desc)){
                $q->ordrBy($this->sort);
            }else{
                $q->ordrBy($this->sort." desc");
            }
        }
        
        $this->_list = $q->select(...$fields);
        $this->_idx_end = $this->_idx_start + count($this->_list) - 1;
    }
    public function setLimit($take){ $this->takeLimit = $take; }
    public function hasList(){return !empty($this->_list); }
    public function getList(){return $this->_list; }
    public function getLimit() { return NumUtil::max(NumUtil::toInt($this->takeLimit), 1); }
    public function getPage() { return NumUtil::max(NumUtil::toInt($this->p), 1); }
    public function getMaxPage(){
        $maxc = $this->getMax();
        if($maxc === 0){return 0; }
        return ceil($maxc / $this->getLimit());
    }
    
    
    public function getStart(){ return $this->_idx_start; }
    public function getEnd(){ return $this->_idx_end; }
    public function getMax(){ return NumUtil::toInt($this->_idx_max, 0);}
    
    public function setPagerQueryIgnores(...$ignores){
        $this->ignores = $ignores;
    }
    public function getPagerList($currentFlag = "active", $offset = 0){
        $r = array();
        $max = $this->getMaxPage();
        if($max <= 1){ return $r; }
        $p = $this->getPage();
        if($offset <= 0){
            for($i=1; $i<=$max; ++$i){
                $r[$i] = ($i === $p) ? $currentFlag : "";
            }
        }else{
            $sp = $p - intval(($offset -1)/2);
            if($sp < 1) { $sp = 1; }
            $ep = $sp + $offset - 1;
            
            if($ep > $max){
                $ep = $max;
                if($max - $sp < $offset){
                    $sp = $ep - $offset;
                    if($sp < 1){
                        $sp = 1;
                    }
                }
            }
            for($i=$sp; $i<=$ep; ++$i){
                $r[$i] = ((int)$i === (int)$p) ? $currentFlag : "";
            }
        }
        return $r;
    }
    
    public function getPagerQuery($page = 1) {
        $arr = $this->toArray("p");
        $arr["p"] = $page;
        return Url::queryString($arr);
    }
    
    public function tag_refresh(){
        $data = $this->toBase64_ignoreNull();
        return "<input type='hidden' name='".h(SysConf::X_REFRESH_PARAM_NAME)."' value='".h($data)."'>";
    }
}

/**
 * <p>Model基盤クラス / abstract</p>
 */
abstract class IModel{
    public static function has() {
        return !empty(\Bin\Dat::$_MODELS[$k][get_called_class()]);
    }
    public static function isEmpty($key){
        if(!isset(\Bin\Dat::$_MODELS[get_called_class()][$key])){ return true; }
        if(empty(\Bin\Dat::$_MODELS[get_called_class()][$key])){ return true; }
        return false;
    }
    public static function isNotEmpty($key){
        return !self::isEmpty($key);
    }
    public static function set($key, $val){
        \Bin\Dat::$_MODELS[get_called_class()][$key] = $val;
    }
    public static function add($key, $val){
        \Bin\Dat::$_MODELS[get_called_class()][$key][] = $val;
    }
    public static function get($key = null, $nullVal = null){
        $cl = get_called_class();
        if(!isset(\Bin\Dat::$_MODELS[$cl])){ \Bin\Dat::$_MODELS[$cl] = array(); }
        if($key === null) { return \Bin\Dat::$_MODELS[$cl];}
        if(!isset(\Bin\Dat::$_MODELS[$cl][$key])){ return $nullVal; }
        return \Bin\Dat::$_MODELS[$cl][$key];
    }
    
    public static function clearAll(){
        \Bin\Dat::$_MODELS[get_called_class()] = array();
    }
    
    public static function toRedirect(){
        $cl = get_called_class();
        if(isset(\Bin\Dat::$_MODELS[$cl])){
            \Bin\Dat::$_REDIRECT_MODELS[$cl] = \Bin\Dat::$_MODELS[$cl];
        }
    }
    
    protected static function prop($key, $val){
        if($val !== null){ self::set($key, $val); }
        return self::get($key);
    }
}

/**
 * <p>Modelクラス / static</p>
 * <p>Formには登録しない一時的な情報を格納する</p>
 */
class Model extends IModel{}

/**
 * <p>メッセージクラス / static</p>
 * <p>ユーザに表示する処理結果やエラーのメッセージを格納する</p>
 */
class Message extends IModel {
    private static $SUC = "S";
    private static $INF = "I";
    private static $WAR = "W";
    private static $ERR = "E";
    public static function has() { return !empty(self::get()); }
    public static function hasSuccess()  { return !self::isEmpty(self::$SUC); }
    public static function hasInfo()  { return !self::isEmpty(self::$INF); }
    public static function hasWarning()  { return !self::isEmpty(self::$WAR); }
    public static function hasError()  { return !self::isEmpty(self::$ERR); }
    
    public static function addSuccess($msg){ self::add(self::$SUC, $msg); }
    public static function addInfo($msg){ self::add(self::$INF, $msg); }
    public static function addWarning($msg){ self::add(self::$WAR, $msg); }
    public static function addError($msg){ self::add(self::$ERR, $msg); }
    public static function getSuccess() { return self::get(self::$SUC); }
    public static function getInfo() { return self::get(self::$INF); }
    public static function getWarning() { return self::get(self::$WAR); }
    public static function getError() { return self::get(self::$ERR); }
    
    public static function isCloseable($flag = false){
        if(func_num_args() > 0){ self::set("closeable", $flag); return $flag; }
        return self::get("closeable", false);
    }
}


////////////////////////////////////////////////////////////////////////////////
// Request & Response
////////////////////////////////////////////////////////////////////////////////

class Request {    
    private static $_arr_path;
    private static $_arr_query;
    private static $_path_parts;
    
    public static function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    private static function get($n, $nv = ""){
        if(isset($_SERVER[$n])){return $_SERVER[$n];}
        return $nv;
    }
    /** <p>サーバー名（domain.com / localhost）</p> */
    public static function getServerName(){ return self::get("SERVER_NAME"); }
    /** <p>サーバー: IPアドレス</p> */
    public static function getServerAddr(){ return self::get("SERVER_ADDR"); }
    /** <p>サーバー: ポート番号</p> */
    public static function getServerPort(){ return self::get("SERVER_PORT"); }
    
    /** <p>ユーザ: IPアドレス</p> */
    public static function getRemoteAddr(){ return self::get("REMOTE_ADDR"); }
    /** <p>ユーザ: ポート番号</p> */
    public static function getRemotePort(){ return self::get("REMOTE_PORT"); }
    /** <p>ユーザ: ホスト</p> */
    public static function getRemoteHost(){ return self::get("REMOTE_HOST"); }
    /** <p>メソッド(GET/POST/HEAD)</p> */
    public static function getMethod(){ return self::get("REQUEST_METHOD"); }
    /** <p>ユーザエージェント</p> */
    public static function getUserAgent(){ return self::get('HTTP_USER_AGENT'); }
    
    /** <p>"http:"か"https:"を返す</p> */
    public static function getProtocol(){ return empty(self::get("HTTPS")) ? "http:" : "https:"; }
    /** <p>ドメイン名</p> */
    public static function getHost(){ return self::get("HTTP_HOST");}
    /** <p>URI</p> */
    public static function getUri(){ return self::get("REQUEST_URI"); }
    
    public static function getPathInfo(){ return self::get("PATH_INFO"); }
    
    /** アクセスされた、dirname, basename, filename, extensionの配列を返す */
    public static function getPathParts(){
        if(empty(self::$_path_parts)){ self::$_path_parts = pathinfo(self::getPathInfo()); }
        return self::$_path_parts;
    }
    /** ファイル名（拡張子あり） */
    public static function getBasename(){
        $p = self::getPathParts();
        return isset($p["basename"]) ? $p["basename"] : null;
    }
    /** ファイル名（拡張子なし） */
    public static function getFileName(){
        $p = self::getPathParts();
        return isset($p["filename"]) ? $p["filename"] : null;
    }
    /** 拡張子 */
    public static function getExtension(){
        $p = self::getPathParts();
        return isset($p["extension"]) ? $p["extension"] : null;
    }
    public static function getRedirectUrl() { return self::get("REDIRECT_URL"); }
    /** <p>"index.php"(実行スクリプト)を返す</p> */
    public static function getScriptName(){ return self::get("SCRIPT_NAME"); }
    public static function getPhpSelf(){ return self::get("PHP_SELF"); }
    /** <p>"http(s)://domain.name" を返す</p> */
    public static function getUrlHost(){ return self::getProtocol()."//".self::getHost(); }
    /** <p>"http(s)://domain.name/uri" を返す</p> */
    public static function getUrl(){ return self::getUrlHost().self::getUri();}
    /** <p>URLクエリを文字列として取得する</p> */
    public static function getQuery(){ return self::get("QUERY_STRING"); }
    /** <p>URLクエリを配列として取得する</p> */
    public static function getArrayQuery(){
        if(isset(self::$_arr_query)){ return  self::$_arr_query; }
        self::$_arr_query  = array();
        if(!empty(self::getQuery())){ parse_str(self::getQuery(), self::$_arr_query); }
        return self::$_arr_query;
    }
    public static function getArrayPath(){
        if(isset(self::$_arr_path)){ return self::$_arr_path;}
        if(! empty(self::getPathInfo())){ self::$_arr_path = explode("/", self::getPathInfo()); }
        else { self::$_arr_path = array(); }
        return self::$_arr_path;
    }
    
    public static function isLocalhost(){ return (self::getServerName() === 'localhost'); }
    public static function isPost(){ return (strtolower(self::getMethod()) === "post"); }
    public static function isGet(){ return (strtolower(self::getMethod()) === "get"); }
    
    public static function getFormValues(){
        if(self::isPost()){
            return $_POST;
        }
        return $_GET;
    }
    
    public static function getFormValue($name, $nullval = null){
        if(self::isPost()){
            if(isset($_POST[$name])){ return $_POST[$name];}
        }else{
            if(isset($_GET[$name])){ return $_GET[$name];}
        }
        return $nullval;
    }
    
    public static function getBrowser(){
        return UserAgentUtil::getBrowserName(self::getUserAgent());
    }
    public static function isMobile(){
        return UserAgentUtil::isMobile(self::getUserAgent());
    }
}


class Response{
    private static $statusCode = 200;
    private static $streamSpeed = null;
    private static $c_expires = 0;
    private static $ob_gzip = false;
    private static $xoring = false;

    private static function result($key, array $head = null, $body = null){
        return array(
            SysConf::RES_KEY  => $key,
            SysConf::RES_HEAD => $head,
            SysConf::RES_BODY => $body,
            SysConf::RES_STATUS => self::$statusCode,
        );
    }
    
    public static function setStatusCode($statusCode){
        if(self::$statusCode !== $statusCode){
            HistoryStack::add("response", "change-status", $statusCode);
            self::$statusCode = $statusCode;
            http_response_code($statusCode);
        }
    }
    public static function getStatusCode(){ return self::$statusCode; }
    public static function setStreamSpeed($byteChunk, $delayMicrosec){ self::$streamSpeed = array(); self::$streamSpeed["chunk"] = $byteChunk; self::$streamSpeed["delay"] = $delayMicrosec; }
    public static function getStreamSpeed(){ return self::$streamSpeed; }
    
    public static function setCacheExpires($sec = 86400){ self::$c_expires = $sec; }
    public static function getCacheExpires(){ return self::$c_expires; }
    
    public static function setObGZip($bool){ self::$ob_gzip = $bool; }
    public static function getObGZip(){ return self::$ob_gzip; }
    
    public static function setCrossOrign($allow){ self::$xoring = $allow; }
    public static function getCrossOrign(){ return self::$xoring; }
    
    /** <p>namespaceを利用しない場合、同名クラスをロード時にエラーが発生する可能性があります。</p> */
    public static function foward($route, array $argument = null){
        return self::result(SysConf::RES_I_FOWARD, null, array($route, $argument));
    }

    public static function view($view){
        return $view;
    }
    public static function noContent(){
        return self::result(SysConf::RES_I_NOCONTENT, null, null);
    }
    public static function notFound(){
        return self::result(SysConf::RES_I_NOTFOUND, null, null);
    }
    public static function serverError($msg = null){
        return self::result(SysConf::RES_I_SERVERERR, null, $msg);
    }
    public static function html($value, $charset = "utf-8"){
        return self::text($value, "text/html", $charset);
    }

    public static function text($value, $contentType="text/plain", $charset = "utf-8"){
        $head[] = "Content-type: {$contentType}; charset={$charset}";
        $head[] = 'Content-disposition: filename*='.$charset.'\'\''. Util::fileNameNormalizer(Request::getBasename());
        if(is_array($value) || is_object($value)){
            return self::result(SysConf::RES_I_TEXT, $head, json_encode($value));
        }else{
            return self::result(SysConf::RES_I_TEXT, $head, $value);
        }
    }
    
    public static function textDownload($value, $fileName = null, $mime = null, $charset = "utf-8"){
        if(empty($fileName)) { $fileName = Request::getBasename(); }
        if(empty($mime)){ $mime = ContentsResolve::getMimeType($fileName); }
        $head[] = 'Content-Type: '.$mime." charset={$charset}";
        $head[] = 'X-Content-Type-Options: nosniff';
        $head[] = 'Content-disposition: attachment; filename*=UTF-8\'\''.Util::fileNameNormalizer($fileName);
        $head[] = 'Connection: close';
        if(is_array($value) || is_object($value)){
            return self::result(SysConf::RES_I_TEXT, $head, json_encode($value));
        }else{
            return self::result(SysConf::RES_I_TEXT, $head, $value);
        }
    }
    
    private static function fileNotfound(){
        return self::result(SysConf::RES_I_NOCONTENT, null, null);
    }
    
    /** <p>ファイルをレスポンスします。<br>ファイルを表示するかダウンロードするかはブラウザに依存します。<br>mimeを指定しない場合は拡張子から自動的に判断します。</p>
     * <hr>
     * <p>また、setStreamSpeed(int, int)を兼用し、ファイルのダウンロード速度を制限できます。</p>
     */
    public static function file($filePath, $fileName = null, $mime = null){
        if(file_exists($filePath)){
            if(empty($fileName)) { $fileName = Request::getBasename(); }
            if(empty($mime)){ $mime = ContentsResolve::getMimeType($fileName); }
            $head[] = 'Content-Type: '.$mime;
            $head[] = 'Content-Length: '.filesize($filePath);
            $head[] = 'Content-disposition: filename*=UTF-8\'\''.Util::fileNameNormalizer($fileName);
            return self::result(SysConf::RES_I_FILE, $head, $filePath);
        }
        return self::fileNotfound();
    }
    /** <p>ダウンロード用ファイルをレスポンスします。<br>ファイルを強制的にダウンロードさせます。<br>mimeを指定しない場合は拡張子から自動的に判断します。</p>
     * <hr>
     * <p>また、setStreamSpeed(int, int)を兼用し、ファイルのダウンロード速度を制限できます。</p>
     *  */
    public static function download($filePath, $fileName = null, $mime = null){
        if(file_exists($filePath)){
            if(empty($fileName)) { $fileName = Request::getBasename(); }
            if(empty($mime)){ $mime = ContentsResolve::getMimeType($fileName); }
            $head[] = 'Content-Type: '.$mime;
            $head[] = 'Content-Length: '.filesize($filePath);
            $head[] = 'X-Content-Type-Options: nosniff';
            $head[] = 'Content-disposition: attachment; filename*=UTF-8\'\''.Util::fileNameNormalizer($fileName);
            $head[] = 'Connection: close';
            return self::result(SysConf::RES_I_FILE, $head, $filePath);
        }
        return self::fileNotfound();
    }
    /** <p>ファイルストリーミングをレスポンスします。<br>Cache-Control: must-revalidateを指定しているため、期限切れのリソースを使用するべきではありません。<br>mimeを指定しない場合は拡張子から自動的に判断します。</p> */
    public static function stream($filePath, $fileName = null, $mime = null){
        if(file_exists($filePath)){
            if(empty($fileName)) { $fileName = Request::getBasename(); }
            $head[] = 'Content-Type: application/octet-stream';
            $head[] = 'Content-disposition: attachment; filename*=UTF-8\'\''.Util::fileNameNormalizer($fileName);
            $head[] = 'Expires: 0';
            $head[] = 'Cache-Control: must-revalidate';
            $head[] = 'Pragma: public';
            $head[] = 'Content-Length: '.filesize($filePath);    
            return self::result(SysConf::RES_I_FILE, $head, $filePath);        
        }
        return self::fileNotfound();
    }
    
    public static function json($value, $charset = "utf-8"){
        $head[] = 'Content-type: application/json; charset='.$charset;
        $json = json_encode($value);
        if(json_last_error() !== JSON_ERROR_NONE){
            throw new Exception(json_last_error_msg());
        }
        return self::result(SysConf::RES_I_JSON, $head, $json);
    }
    public static function redirect($url = "/"){
        $head[] = 'Location: '. Url::get($url);
        return self::result(SysConf::RES_I_REDIRECT, $head, $url);
    }
}
