<?php
namespace Bin;

class Dat{
    public static $_MODELS = array();
    public static $_REDIRECT_MODELS = array();
}

class RouteingMap{
    public static $maps = array();
    private static $item = null;
    private static $arguments = array();
    
    public static function isFound(){ return !empty(self::$item); }
    public static function getItem(){ return self::$item; }
    
    public static function getSysf(){ return !empty(self::$item) ? self::$item["sysf"] : null; }
    public static function getRoutePath(){ return !empty(self::$item) ? self::$item["route"] : null; }
    public static function getArguments(){ return self::$arguments; }
    public static function getArgument($name){ if(isset(self::$arguments[$name])){ return self::$arguments[$name];} return null; }
    
    public static function set($method, $uri, $route, $sysf, array $arg = null){
        $regP = "/\{(.+?)\}/";
        $regPAny = "/\{(.+?)\*\}/";
        $pattern = "";
        $aindex = array();
        $bi = 0;
        if($uri !== null){
            foreach (explode('/', $uri) as $p){
                if(empty($p)){ continue; }
                if($p === '*'){
                    if(!empty($pattern)){$pattern.="\/";}
                    $pattern .= "(.*)";
                    ++$bi;
                    $aindex[$bi] = "*";
                    break;
                }
                if(preg_match($regP, $p)){
                    $match = array();
                    preg_match_all($regP, $p, $match);
                    $c = count($match);
                    for($i=1; $i<$c; ++$i){
                        foreach($match[$i] as $m){
                            ++$bi;
                            $aindex[$bi] = str_replace('*', '', $m);
                        }
                        $p = preg_replace($regPAny, "([^\/]*)", $p);
                        $p = preg_replace($regP, "([^\/]+)", $p);
                    }
                }
                if(!empty($pattern)){$pattern.="\/";}
                $pattern.= $p;
            }
            $pattern = "/^[\/]?".$pattern."\/?$/";
        }

        self::$maps[] = array("method"=> strtolower($method), "uri"=>$uri, "route"=>$route, "pattern"=>$pattern, "aindex"=>$aindex, "arg"=>$arg, "sysf"=>$sysf);
    }
    
    public static function find(){
        $useCache = (defined("USE_ROUTE_CACHE") && USE_ROUTE_CACHE === true);
        $uri = \Request::getPathInfo();
        $method = strtolower(\Request::getMethod());
        if(\StringUtil::right($uri, 1) !== '/'){$uri .= '/'; }
        if($useCache){ return self::cacheRoute($method, $uri); }
        //\Loader::app(\Conf::ROUTEING_CONF);
        include_once \Conf::ROUTEING_CONF;
        return self::macheRoute($method, $uri);
    }
    
    private static function cacheRoute($method, $uri){
        $fi = new \FileInfo(\Path::app(\Conf::ROUTEING_CONF));
        if($fi->notExists()){ return null; }
        $cache = $method."_".sha1($uri);
        $cfi = \Cache::fileInfo_route($fi->hash(), $cache);
        $dir = $cfi->baseDirectory();
        
        if($cfi->exists()){
            $a = json_decode($cfi->read(), true);
            self::$item = $a['route'];
            self::$arguments = $a['arguments'];
            \HistoryStack::add("route", "use cache", array($dir, $cache));
            return self::$item;
        }
        $mfi = \Cache::fileInfo_route($fi->hash(), \SysConf::ROUTE_MAP_CACHE);
        if($mfi->notExists()){
            include_once $fi->fullName();
            $mfi->save(json_encode(self::$maps));
            \HistoryStack::add("route", "map create cache", array($dir, \SysConf::ROUTE_MAP_CACHE));
        }else{
            self::$maps = json_decode($mfi->read(), true);
            \HistoryStack::add("route", "map use cache", array($dir, \SysConf::ROUTE_MAP_CACHE));
        }
        $r = self::macheRoute($method, $uri);
        $cfi->save(json_encode(array("uri"=>$uri, "route"=>self::$item, "arguments"=>self::$arguments)));
        \HistoryStack::add("route", "create cache", array($dir, $cache));
        return $r;
    }
    
    private static function macheRoute($method, $uri){
        self::$item = null;
        self::$arguments = array();
        foreach(self::$maps as $v){
            $pattern = $v["pattern"];
            $aindex = $v["aindex"];
            if(!empty($v["method"]) && $v["method"] !== $m){ continue; }
            if(preg_match($pattern, $uri)){
                $arg = $v["arg"];
                if(!empty($arg)){ self::$arguments = $arg;}
                
                $match = array();
                preg_match_all($pattern, $uri, $match);
                $c = count($match);
                for($i=1; $i<$c; ++$i){
                    $key = $aindex[$i];
                    if($key === "*"){
                        $v['route'] = str_replace("*", $match[$i][0], $v['route']);
                    }else{
                        self::$arguments[$aindex[$i]] = $match[$i][0];
                    }
                }
                self::$item = $v;
                \HistoryStack::add("routeing", "find", $v);
                return $v;
            }
        }
        return null;
    }
}

class HandleResolver{
    private static $inBound;
    private static $path, $routes, $cnt;
    private static $dir, $class, $func, $ext;
    private static $is_deny = false;
    
    public static function findRoute(){
        $r = RouteingMap::find();
        //Routeingが見つからない
        if($r === null){ return self::set(); }
        
        $route = $r["route"];
        if(\StringUtil::isNotEmpty($route)){ self::set($route); }
        self::$is_deny = ($r["sysf"] === "deny");
    }
    
    public static function set($routePath = null){
        self::$is_deny = false;
        
        if($routePath === null){ 
            self::$inBound = false;
            self::$path = strtolower(\Request::getPathInfo());
        }else{
            self::$inBound = true;
            self::$path = strtolower($routePath);
        }
        self::$routes = array();
        foreach(explode('/', self::$path) as $v){
            if(empty($v)){ continue;}
            self::$routes[] = $v;
        }
        self::$cnt = count(self::$routes);
        self::$dir = null;
        self::$class = null;
        self::$func = null;
        if(self::$cnt === 0){ return; }
        $rp = pathinfo(self::$routes[self::$cnt -1]);
        self::$routes[self::$cnt -1] = $rp['filename'];
        if(isset($rp['extension'])){
            if(!in_array($rp['extension'], \SysConf::IGNORE_EXTS)){
                self::$ext = strtolower($rp['extension']);
            }
        }else{
            self::$ext = null;
        }
        \HistoryStack::add("handler", "resolve", self::$routes);
    }
    
    public static function isDeny(){ return self::$is_deny; }
    
    /** dir(+)/classs(-)/func(-) */
    public static function candidate1(){
        self::$func = null;
        self::$class = null;
        self::$dir = null;
        foreach(self::$routes as $v){ self::$dir .= $v.DIRECTORY_SEPARATOR; }
    }
    /** dir(*)/classs(+)/func(-) */
    public static function candidate2(){
        $routes = self::$routes;
        self::$func = null;
        self::$class = array_pop($routes);
        self::$dir = null;
        foreach($routes as $v){self::$dir .= $v.DIRECTORY_SEPARATOR; }
    }
    /** dir(*)/classs(-)/func(+) */
    public static function candidate3(){
        $routes = self::$routes;
        self::$func = array_pop($routes);
        self::$class = null;
        self::$dir = null;
        foreach($routes as $v){ self::$dir .= $v.DIRECTORY_SEPARATOR; }
    }
    /** dir(*)/classs(+)/func(+) */
    public static function candidate4(){
        $routes = self::$routes;
        self::$func = array_pop($routes);
        self::$class = array_pop($routes);
        self::$dir = null;
        foreach($routes as $v){self::$dir .= $v.DIRECTORY_SEPARATOR; }
    }
    
    public static function isInBound(){ return self::$inBound; }
    public static function toArray(){ return self::$routes; }
    public static function getPath(){ return self::$path; }
    public static function getFunc(){ return self::$func; }
    
    private static function g2cn(){
        $r = empty(self::$class) ? \SysConf::DEFAULT_CLASS : self::$class;
        return \StringUtil::camelCase(str_replace('-', '_',  $r), true).\SysConf::SUFFIX_CONTROLLER;
    }
    public static function getClassDir(){
        return self::$dir;
    }
    public static function getClassName(){
        $r = self::g2cn();
        if(\SysConf::USE_NAMESPACE && !empty(self::$dir)){ return str_replace(DIRECTORY_SEPARATOR, "\\", self::$dir).$r; }
        return $r;
    }
    public static function getClassFile(){
        $r = \Path::combine(self::$dir, self::g2cn().\SysConf::EXT_CONTROLLER);
        return $r;
    }
    public static function getFuncName($method = null){
        $r = empty(self::$func) ? \SysConf::DEFAULT_FUNC : self::$func;
        $func = \StringUtil::camelCase(str_replace('-', '_',  $r), false);
        $pfx = ""; $sfx = "";
        if(!empty($method)){ $pfx = "_".strtolower($method)."_"; }
        if(!empty(self::$ext)) { $sfx = "_".self::$ext; }
        return $pfx.$func.$sfx;
        //$ns = get_class_methods(self::$class);
    }
    public static function getExt(){
        return self::$ext;
    }
}

class Handler{
    private static $className;
    private static $class;
    private static $funcName;
    private static $seaClassNames = array();
    private static $seaFuncNames = array();
    
    public static function getClassName(){ return self::$className; }
    public static function getFuncName(){ return self::$funcName; }
    
    private static function preparation(){
        self::$className = null;
        //self::$class = null;
        self::$funcName = null;
        
        if(HandleResolver::isDeny()){
            \HistoryStack::add("handler", "-deny-", HandleResolver::toArray());
            throw new \NotFoundException("NotFound -deny-");
        }
        
        self::$class = self::getHandleClass();
        \HistoryStack::add("handler", "class search => ".HandleResolver::getPath(), self::$seaClassNames);
        if(self::$class === null){
            \HistoryStack::add("handler", "not found class", HandleResolver::toArray());
            throw new \NotFoundException("NotFound Route class");
        }
        self::$className = get_class(self::$class);
        self::$funcName = self::getHandleFunc(self::$class);
        \HistoryStack::add("handler", "func search => ".HandleResolver::getFunc(), self::$seaFuncNames);
        if(self::$funcName === null){
            self::$class = null;
            \HistoryStack::add("handler", "not found func", HandleResolver::toArray());
            throw new \NotFoundException("NotFound Route func");
        }
    }
    
    public static function run(){
        self::preparation();
        
        try{
            if(self::$class instanceof \IController){
                $r = self::invoke(RouteingMap::getArguments());
            }else{
                $r = self::invokeOther(RouteingMap::getArguments());
            }
        } catch (\Exception $ex) {
            \HistoryStack::add("handler", "invoked-error", $ex);
            throw $ex;
        }
        
        //\Responses
        return self::preResp($r);
    }
    
    public static function errorRun($ex, $statusCode){
        \Response::setStatusCode($statusCode);
        try{
            HandleResolver::set(\Conf::ERROR_ROUTEING);
            self::preparation();
        } catch (\Exception $ex2) {
            HandleResolver::set(\SysConf::DEFAULT_ERROR_ROUTEING);
            self::preparation();
        }

        try{
            if(self::$class instanceof \IErrorController){
                $r = self::invoke(array($ex, $statusCode));
            }else if(self::$class instanceof \IController){
                $r = self::invoke();
            }else{
                $r = self::invokeOther();
            }
            \HistoryStack::add("error-handler", "invoked", $r);
        } catch (\Exception $ex) {
            \HistoryStack::add("error-handler", "invoked-error", $ex);
            \Response::setStatusCode(500);
            throw $ex;
        }
        
        //\Responses
        return self::preResp($r);
    }
    
    private static function preResp($r){
        if(is_array($r) && isset($r[\SysConf::RES_KEY])){
            $key = $r[\SysConf::RES_KEY];
            if($key === \SysConf::RES_I_FOWARD){
                $fw = $r[\SysConf::RES_BODY];
                \HistoryStack::add("handler", "foward", $fw);
                HandleResolver::set($fw[0]);
                return self::run($fw[1]);
            }else if($key === \SysConf::RES_I_NOTFOUND){
                throw new \NotFoundException();
            }else if($key === \SysConf::RES_I_SERVERERR){
                throw new \InternalServerException($r[\SysConf::RES_BODY]);
            }
        }
        return $r;
    }
    
    private static function invoke(array $argument = null){
        \HistoryStack::add('invoke', '"'.self::$className.'@'.self::$funcName.'"', $argument);
        $class = self::$class;
        $func = self::$funcName;
        $callname = HandleResolver::getFunc();
        try{
            //pre function
            $pre = $class->__pre_invoke_handler($func, $callname);
            
            //Auth
            if($class instanceof \IAuthController){
                \HistoryStack::add('valid-auth', self::$className);
                if(!\Auth::check()){ throw new \UnauthorizedhException(); }
            }
            
            //Secure: csrf
            if(\Meta::is_valid_csrf()){
                if(!\Secure::compareCsrfToken()){ throw new \ForbiddenException(); }
            }
            //Secure: role
            if(\Meta::is_valid_role()){
                if(!\Auth::hasRole(\Meta::get_valid_roles())){ throw new \ForbiddenException(); }
            }
            
            if($pre !== null){
                $r = $pre;
            }else{
                \HistoryStack::add('kick', '"'.self::$className.'@'.self::$funcName.'"', $argument);
                if(self::$class instanceof \IErrorController){
                    if(isset($argument[0]) && isset($argument[1])){
                        $r = $class->code($argument[0], $argument[1]);
                    }else{
                        $r = $class->$func($argument);
                    }
                }else{
                    if($func === "__id"){
                        $id_name = $callname .(empty(HandleResolver::getExt()) ? "" : "." .HandleResolver::getExt()) ;
                        $r = $class->__id($id_name);
                    }else{
                        $r = $class->$func($argument);
                    }
                }
            }
            
            $aft = $class->__after_invoke_handler($func, $callname);
            if($aft !== null){
                $r = $aft;
            }
            
            \HistoryStack::add("invoke", "success", array("result"=>$r));
            return $r;
        }catch (\Exception $ex) {
            $r = $class->__exception_invoke_handler($func, $callname, $ex);
            if($r !== null){return $r;}
            throw $ex;
        }
    }
    private static function invokeOther(array $argument = null){
        \HistoryStack::add("invoke-other", "run", array("class" => get_class(self::$class), "func" => self::$funcName, $argument));
        $class = self::$class;
        $func = self::$funcName;
        try{
            $r = $class->$func($argument);
            \HistoryStack::add("invoke-other", "success", array("result"=>$r));
            return $r;
        }catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    private static function getHandleClass(){
        self::$seaClassNames = array();
        HandleResolver::candidate1();
        $class = self::getHandleClassInner();
        if($class === null){
            HandleResolver::candidate2();
            $class = self::getHandleClassInner();
        }
        if($class === null){
            HandleResolver::candidate3();
            $class = self::getHandleClassInner();
        }
        if($class === null){
            HandleResolver::candidate4();
            $class = self::getHandleClassInner();
        }
        
        return $class;
    }
    private static function getHandleClassInner(){
        $className = HandleResolver::getClassName();
        $path = HandleResolver::getClassFile();
        self::$seaClassNames[] = $path;
        $file = \Path::app(\SysConf::DIR_APP_CONTROLLER, $path);
        /*
        $pvp = \Response::getViewPrefix();
        \Response::setViewPrefix(null);
         */
        \Meta::clear();
        if(file_exists($file)){
            try{
                self::requireDirAccess();
                
                require_once $file;
                if(class_exists($className)){
                    return new $className;
                }else{
                    \HistoryStack::add("controller", "not found class name", $className);
                }
            } catch (\Exception $ex) {
                \HistoryStack::add("controller", "load error", $ex);
            }
        }
        return null;
    }
    private static function requireDirAccess(){
        $bs = \Path::app(\SysConf::DIR_APP_CONTROLLER) . DIRECTORY_SEPARATOR;
        $arr = explode(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR. trim(HandleResolver::getClassDir(), DIRECTORY_SEPARATOR));
        foreach ($arr as $v){
            $bs .= $v.DIRECTORY_SEPARATOR;
            $file = $bs.\SysConf::FILE_CONTROLLER_DIRACCESS;
            if(file_exists($file)){
                \HistoryStack::add("-access", $file);
                require_once $file;
            }
        }
    }
    
    private static function getHandleFunc($class){
        self::$seaFuncNames = array();
        $method = \Request::getMethod();
        
        if(HandleResolver::isInBound()){
            $func = HandleResolver::getFuncName('private_'.$method);
            self::$seaFuncNames[] = self::$className."@".$func."()";
            if(is_callable(array($class, $func))){ return $func; }
            
            $func = HandleResolver::getFuncName('private');
            self::$seaFuncNames[] = self::$className."@".$func."()";
            if(is_callable(array($class, $func))){ return $func; }
        }
        
        if(\Request::isAjax()){
            $func = HandleResolver::getFuncName('ajax_'.$method);
            self::$seaFuncNames[] = self::$className."@".$func."()";
            if(is_callable(array($class, $func))){ return $func; }
            
            $func = HandleResolver::getFuncName('ajax');
            self::$seaFuncNames[] = self::$className."@".$func."()";
            if(is_callable(array($class, $func))){ return $func; }
        }
        
        $func = HandleResolver::getFuncName($method);
        self::$seaFuncNames[] = self::$className."@".$func."()";
        if(is_callable(array($class, $func))){ return $func; }

        $func = HandleResolver::getFuncName(null);
        self::$seaFuncNames[] = self::$className."@".$func."()";
        if(is_callable(array($class, $func))){ return $func; }
        
        /* __argument */
        $func = "__id";
        self::$seaFuncNames[] = self::$className."@".$func."()";
        if(is_callable(array($class, $func))){ return $func; }
        
        self::$seaFuncNames[] = self::$className."@null";
        return null;
    }
}

/**  */
class ResultResolver{
    public static function run($result){
        $view = null;
        $status = null;
        if(is_array($result)){
            $key = $result[\SysConf::RES_KEY];
            $head = $result[\SysConf::RES_HEAD];
            $body = $result[\SysConf::RES_BODY];
            $status = $result[\SysConf::RES_STATUS];
            \HistoryStack::add("resolver", "result type: ".$key, $result);
            
            if($key == \SysConf::RES_I_NOCONTENT){
                self::sendHeader($head);
                http_response_code(204); // NO CONTENT.
                return;
            }
            
            //HEAD
            if(strtolower(\Request::getMethod()) === "head"){  self::sendHeader($head); http_response_code($status); return;}
            
            //
            self::sendHeader($head);
            
            if($key === \SysConf::RES_I_REDIRECT){
                $_SESSION[\SysConf::SESSION_REDIRECT_MODELS_NAME] = \Bin\Dat::$_REDIRECT_MODELS;
                return;
            }
            
            if($key === \SysConf::RES_I_TEXT){
                http_response_code($status);
                echo $body;
                return;
            }
            
            if($key === \SysConf::RES_I_JSON){
                http_response_code($status);
                echo $body;
                return;
            }
            
            if($key === \SysConf::RES_I_FILE){
                if(file_exists($body)){
                    http_response_code($status);
                    self::streamDownload($body);
                }else{
                    http_response_code(404); // NOT FOUND
                }
                return;
            }
            $view = $body;
        }else{
            self::sendHeader(null);
            $status = \Response::getStatusCode();
            $view = (empty(\Meta::get_vprefix()) ? "" : \Meta::get_vprefix()."/").$result;
            \HistoryStack::add("resolver", "result type: view", array("vprefix"=> \Meta::get_vprefix(), "result"=>$result, "code"=>$status));
        }
        if(empty($view)){
            \HistoryStack::add("Render", "no result");
            throw new \InternalServerException("no result");
        }else{
            if(strtolower(\Request::getMethod()) === "head"){ return; }
            include __X_LOADER_DIR_APP."Render.php";
            try{
                \Response::setStatusCode($status);
                \Render::run($view);
            } catch (\Exception $ex) {
                \ErrorStack::add($ex);
                \Response::setStatusCode(500);
                try{
                    \Render::run(\Conf::ERROR_VIEW);
                } catch (\Exception $ex2) {
                    \HistoryStack::add("\Render", "error view error", $ex2);
                    throw $ex2;
                }
            }
        }
    }
    private static function streamDownload($filename){
        if(!is_file($filename)){ return; }
        $sp = \Response::getStreamSpeed();
        if($sp === null){
            $byteChunk = \SysConf::STREAM_CHUNK;
            $delay = \SysConf::STREAM_DELAY;
        }else{
            $byteChunk = \NumUtil::toInt($sp["chunk"], \SysConf::STREAM_CHUNK);
            $delay = \NumUtil::toInt($sp["delay"], \SysConf::STREAM_DELAY);
        }
        if($byteChunk < 1){ $byteChunk = 1024; }
        
        //タイムアウト回避
        set_time_limit(0);
        
        //バッファのクリーン
        while(ob_get_level()){ ob_end_clean(); }
        ob_start();
        try{
            if($fp = fopen($filename, 'rb')) {
                try{
                    //コネクションが生きている間ループ
                    if($delay < 1){
                        while(!feof($fp) and (connection_status() === 0)){
                            echo fread($fp, $byteChunk); ob_flush();flush();
                        }
                    }else{
                        while(!feof($fp) and (connection_status() === 0)){
                            echo fread($fp, $byteChunk); ob_flush();flush();
                            usleep($delay);
                        }
                    }
                } catch (\Exception $ex) { }
                ob_flush();
                fclose($fp);
            }
        } catch (\Exception $ex) {
        }
        ob_end_clean();
    }
    private static function sendHeader($head){
        $meta_head = \Meta::get_header();
        foreach($meta_head as $h){
            \HistoryStack::add("resolver", "add-header", $h);
            header($h);
        }
        
        $expires = \Response::getCacheExpires();
        if($expires > 0){
            header('Expires: ' . gmdate('D, d M Y H:i:s T', time() + $expires));
            header('Cache-Control: private, max-age=' . $expires);
            header('Pragma: ');
        }else{
            header('Expires: -1');
            header('Cache-Control:');
            header('Pragma: no-cache');
        }
        
        // クリックジャッキング対策(あまり意味ない)
        //header('X-FRAME-OPTIONS', 'SAMEORIGIN');
        // XSS攻撃を検知させる（検知したら実行させない）
        header("X-XSS-Protection: 1; mode=block");
        
        if(isset($head)){
            foreach($head as $h){
                \HistoryStack::add("resolver", "add-header", $h);
                header($h);
            }
        }
    }
}