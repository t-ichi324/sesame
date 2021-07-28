<?php
define("_X_RENDER_LOADED", true);
abstract class IRenderFunc{
    abstract static function existsKeyword($key) ;
    abstract static function call($key, $param) ;
}
class RenderVDat{
    public static $CR = array();
    private static function IX(){ return count(self::$CR) - 1; }
    private static function RM(){ if(isset(self::$CR[self::IX()])){ unset(self::$CR[self::IX()]); } }
    public static function FILE(){ if(isset(self::$CR[self::IX()])){ return self::$CR[self::IX()]; } return null; }    
    
    private $f, $c;
    public function __construct(FileInfo $fi, FileInfo $ci = null){ $this->f = $fi; $this->c = $ci; }
    public function getOB(){
        if($this->c === null || $this->c->notExists()){ HistoryStack::add("view",  $this->f->fullName(), "-------NOT FOUND------"); Log::error(array( "-------NOT FOUND------", $this->f->fullName())); return null; }
        HistoryStack::add("view", $this->f->fullName(), array("cache" =>$this->c->name()));
        self::$CR[] = $this->f->fullName();
        ob_start();
        ob_implicit_flush(0);
        try{
            require $this->c->fullName();
            self::RM();
            return ob_get_clean();
        } catch (Exception $ex) {
            Meta::clear();
            ob_end_clean();
            Log::error($ex);
            throw $ex;
        }
    }
    public function flushOB(){
        if($this->c === null || $this->c->notExists()){ HistoryStack::add("view",  $this->f->fullName(), "-------NOT FOUND------"); Log::error(array( "-------NOT FOUND------", $this->f->fullName())); return null; }
        HistoryStack::add("view-f", $this->f->fullName(), array("cache" =>$this->c->name()));
        self::$CR[] = $this->f->fullName();
        ob_start();
        ob_implicit_flush(0);
        try{
            require $this->c->fullName();
            self::RM();
            ob_end_flush();
        } catch (Exception $ex) {
            Meta::clear();
            ob_end_clean();
            Log::error($ex);
            throw $ex;
        }
    }
}

class Render{
    const YIELD_NAME_CONTENT = "contents";
    private static $REP_PTN = "#\{\{(.*?)\}\}#";
    public static $section = array();
    public static $layout = null;
    
    //HTMLファイルをレンダリング
    public static function run($view){
        HistoryStack::add("render", "run", $view);
        $filename = self::getViewFileName($view);
        if(!file_exists($filename)){
            HistoryStack::add("view", $filename, "-------NOT FOUND------");
            Log::error(array( "-------NOT FOUND------", $filename)); 
            throw new InternalServerException("NotFound 'VIEW'\t=>\t".$view. "\t (" . $filename . ")");
        }
        if(Response::getObGZip()){ ob_start("ob_gzhandler");}
        self::createMain($filename);
    }
    
    //HTMLファイルを取得
    public static function getHtml($view){
        $filename = self::getViewFileName($view);
        if(!file_exists($filename)){ return ""; }
        return self::createMain($filename, false);
    }
    
    private static function createMain($filename, $rend = true){
        $rvd = self::getContent($filename);
        self::$section[self::YIELD_NAME_CONTENT][0] = $rvd->getOB();
        if(self::$layout !== null){
            if($rend){
                self::$layout->flushOB();
            }else{
                return self::$layout->getOb();
            }
        }else{
            if($rend){
                echo self::$section[self::YIELD_NAME_CONTENT][0];
            }else{
                return self::$section[self::YIELD_NAME_CONTENT][0];
            }
        }
    }
    
    private static function getViewFileName($viewName){
        $suffix = SysConf::SUFFIX_VIEW.SysConf::EXT_VIEW;
        $path = Path::combine(Conf::DIR_APP, SysConf::DIR_APP_VIEW, $viewName);
        if(file_exists($path.$suffix)){
            return $path.$suffix;
        }
        $path2 = Path::combine($path, SysConf::DEFAULT_VIEW);
        if(file_exists($path2.$suffix)){
            return $path2.$suffix;
        }
        return $path2.$suffix;
    }
    
    private static function getContent($filename){
        $fi = new FileInfo($filename);
        $hash = $fi->hash();
        
        //faild
        if(empty($hash)){
            return new RenderVDat($fi, null);
        }
        
        $ci = Cache::fileInfo_view($hash);
        
        // exists
        if ($ci->exists()) { return new RenderVDat($fi, $ci); }

        // not exists
        $content = $fi->read();
        $source = preg_replace_callback(self::$REP_PTN, function ($m) {
            $v = trim($m[1]);
            $key = explode(' ', $v)[0];
            if(isset($key)){
                if(RenderFunc::existsKeyword($key)){
                    $param =  trim(ltrim($v, $key));
                    return RenderFunc::call($key, $param);
                }
            }
            return '<?= htmlspecialchars('.trim($v,";").'); ?>';
            
        }, $content);
        $source .= "<?php // @ ".date("Y-m-d H:i:s")." [".$fi->fullName()."]"." ?>";
        
        $mintag = "<!--minify-->";
        if(substr($source, 0, StringUtil::length($mintag)) === $mintag){
            $search = array(
		'/\>[^\S ]+/s',
		'/[^\S ]+\</s',
		'/(\s)+/s',
                '/<!--[\s\S]*?-->/s'
            );
            $replace = array(
		'>',
		'<',
		'\\1',
                ''
            );
            $min = preg_replace($search, $replace, $source);
            $ci->save($min);
        }else{
            $ci->save($source);
        }
        
        return new RenderVDat($fi, $ci);
    }
    public static function echoRequire($file){
        $f = self::getViewFileName($file);
        if($f === null){ return; }
        $rvd = self::getContent($f);
        if($rvd !== null && $rvd instanceof RenderVDat){ $rvd->flushOB(); }else{ }
    }
    
    public static function setLayout($view){
        $fileName = self::getViewFileName($view);
        if($fileName === null){ return; }
        if(empty(self::$layout)){
            self::$layout = self::getContent($fileName);
        }
    }
    public static function sectionYield($key){
        if(isset(self::$section[$key])){
            foreach(self::$section[$key] as $sec){ echo $sec; }
        }
    }
}
require __X_LOADER_DIR_RENDER.'RenderFunc.php';
require __X_LOADER_DIR_RENDER.'FormEcho.php';
?>