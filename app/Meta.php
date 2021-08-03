<?php
/** 現在のページのメタ情報 */
class Meta{
    //@ sys
    private static $_vprefix = null;
    private static $_breadcrumb = array();
    private static $_breadcrumb_show = true;
    private static $_header = array();
    private static $_vd_csrf = false;
    private static $_vd_roles = null;
    
    private static $_action_url;
    private static $_url;
    private static $_title;
    private static $_desc;
    private static $_og_image;
    private static $_robots;
    
    private static $_filter = array();
    
    //@ mao
    private static $_content_map = array();

    public static function clear(){ 
        self::$_vprefix = null;
        self::$_breadcrumb = array();
        self::$_breadcrumb_show = true;
        self::$_vd_csrf = null;
        self::$_vd_roles = null;
        self::$_header = array();
        
        self::$_action_url = null;
        self::$_url = null;
            
        self::$_title = null;
        self::$_desc = null;
        self::$_og_image = null;
        self::$_robots = null;
        
        self::$_filter = array();
        
        self::$_content_map = array();
    }
    
    /**
     * Sitemap.dbに登録されているデータを設定する。
     * @return type
     */
    public static function MAP_LOAD(){
        $e = Sitemap::getByRequest();
        if($e === null) { HistoryStack::add("Meta::MapLoad", "NOT FOUND"); return; }
        HistoryStack::add("Meta::MapLoad", "FIND", $e);
        if($e->parents_id){
            self::clear_breadcrumb();
            $pList = Sitemap::getParentsList($e->parents_id);
            if(!empty($pList)){
                foreach(array_reverse($pList) as $pe){
                    $pe instanceof SitemapItem;
                    self::breadcrumb($pe->title, $pe->loc);
                }
            }
            self::breadcrumb($e->title, $e->loc);
        }else{
            self::url($e->loc); 
            if($e->title){ self::title($e->title); }
        }
        if(!Flags::isON($e->is_publish)){ self::robots("noindex,nofollow,noarchive"); }
        if($e->description){  self::description($e->description); }
        if($e->image){ self::image($e->image); }
    }

    public static function set_content($attr, $attr_val, $content){ $key = $attr."@".$attr_val; self::$_content_map[$key]=["content"=>$content, "attr"=>$attr, "attr_val"=>$attr_val]; }
    public static function get_content($attr, $attr_val){ $key = $attr."@".$attr_val; if(isset(self::$_content_map[$key])){ return self::$_content_map[$key]["content"]; } return null; }
    public static function get_content_map(){ return self::$_content_map; }
 

    public static function get_header(){ return self::$_header; }
    
    /**
     * <b>[ Sys ]</B><br>
     * Viewのプレフィックスを指定
     * @param string $vprefix viweフォルダ名
     */
    public static function vprefix($vprefix){
        if(StringUtil::left($vprefix,1) == "+"){
            $vp = self::$_vprefix."/".trim(StringUtil::mid($vprefix,1), "/");
        }else{
            $vp = $vprefix;
        }
        self::$_vprefix = trim($vp, '/');
    }
    /**
     * <b>[ Sys ]</B><br>
     * Viewのプレフィックスを取得
     * @param string $adds [可変長引数] ディレクトリに追加する階層pathを指定
     * @return string viewフォルダ名
     */
    public static function get_vprefix(... $adds){ return Path::combine(self::$_vprefix, $adds); }
    /**
     * <b>[ Sys ]</B><br>
     * ページを開く権限を持つロールを指定
     * @param string $roles [可変長引数] ロール名
     */
    public static function roles(... $roles){ self::$_vd_roles = $roles; }
    /**
     * <b>[ Sys ]</B><br>
     * ロールによる認証が必要か判定
     * @return bool
     */
    public static function is_valid_role(){ return !empty(self::$_vd_roles); }
    /**
     * <b>[ Sys ]</B><br>
     * 許可ロール一覧を取得
     * @return array
     */
    public static function get_valid_roles(){ return self::$_vd_roles; }
    
    /**
     * <b>[ Sys ]</B><br>
     * CSRFトークンの検証を行います。
     * @param bool $bool true / false
     */
    public static function csrf($bool){ self::$_vd_csrf = ($bool === true || Flags::isON($bool)); }
    
    /**
     * <b>[ Sys ]</B><br>
     * CSRFトークンの検証が必要か判断
     * @return bool
     */
    public static function is_valid_csrf(){ return self::$_vd_csrf; }


    public static function filter_allow($ip){
        if(is_array($ip)){
            foreach ($ip as $v){ self::filter_allow($v); }
        }elseif(is_string($ip)){
            foreach(explode("\n", $ip) as $v){
                $t = trim($v);
                if($t != ""){
                    self::$_filter["a"][] = $t;
                }
            }
        }
    }
    public static function filter_deny($ip){
        if(is_array($ip)){
            foreach ($ip as $v){ self::filter_deny($v); }
        }elseif(is_string($ip)){
            foreach(explode("\n", $ip) as $v){
                $t = trim($v);
                if($t != ""){
                    self::$_filter["d"][] = $t;
                }
            }
        }
    }
    public static function clear_filter(){ self::$_filter = array(); }
    public static function is_filtered(){
        $ip = Request::getRemoteAddr();
        $def = false;
        if(isset(self::$_filter["d"])){
            if(in_array($ip, self::$_filter["d"])){ return true; }
        }
        if(isset(self::$_filter["a"])){
            $def = true;
            if(in_array($ip, self::$_filter["a"])){ return false; }
        }
        return $def;
    }

    /**
     * <b>[ Sys / Meta ]</B><br>
     * パンくずの階層にタイトルとURLを追加します。
     * @param string $title ページタイトル
     * @param string $url
     */
    public static function breadcrumb($title, $url = null){
        if($url === null){ $url = Request::getUrl(); }
        self::title($title);
        self::url($url);
        $item = ["title"=> self::get_title(), "url"=>self::get_url()];
        self::$_breadcrumb[] = $item;
    }
    /**
     * パンくずの階層の配列を取得します。
     * @return array 
     */
    public static function get_breadcrumb(){ return self::$_breadcrumb; }
    public static function clear_breadcrumb(){ self::$_breadcrumb = array(); }

    public static function breadcrumb_show($bool){ self::$_breadcrumb_show = $bool; }
    public static function is_breadcrumb_show(){ return (self::$_breadcrumb_show === true); }

    /**
     * <b>[ Sys / Meta ]</B><br>
     * ページタイトルの設定
     * @param string $title ページタイトル
     */
    public static function title($title){ self::$_title = $title; }
    /**
     * <b>[ Sys / Meta ]</B><br>
     * ページタイトルの取得
     * @return string
     */
    public static function get_title(){ return self::$_title; }
    
    
    /**
     * <b>[ Sys ]</B><br>
     * ページURLの設定
     * @param string $url ページURL、"+"から始まる場合、事前の現在の"url"に結合します。
     */
    public static function url($url){
        if(StringUtil::left($url,1) == "+"){
            $url = self::get_url(StringUtil::mid($url,1));
        }elseif(StringUtil::left($url, 1) == "*"){
            $url = self::get_url(StringUtil::mid($url,1));
        }else{
            $url = Url::get($url);
        }
        self::$_url = $url;
    }
    /**
     * <b>[ Sys ]</B><br>
     * ページURLの取得
     * @param string $adds [可変長引数] URLに追加するpathを指定
     * @return string URL
     */
    public static function get_url(... $adds){ return Url::get(self::$_url, $adds); }
    
    /**
     * <b>[ Sys ]</B><br>
     * アクションURLの設定
     * @param string $url アクションURL、"+"から始まる場合、事前の現在の"url"に結合します。
     */
    public static function action($url){ 
        if(StringUtil::left($url,1) == "+"){
            $url = self::get_url(StringUtil::mid($url,1));
        }else{
            $url = Url::get($url);
        }
        self::$_action_url = $url;
    }
    
    /**
     * <b>[ Sys ]</B><br>
     * アクションURLの取得
     * @param string $adds [可変長引数] URLに追加するpathを指定
     * @return string URL
     */
    public static function get_action(... $adds){ return Url::get(self::$_action_url, $adds); }

    /**
     * <b>[ Meta ]</B><br>
     * ページ概要の設定
     * @param string $content
     */
    public static function description($content){ self::$_desc = $content; }
    /**
     * <b>[ Meta ]</B><br>
     * ページ概要の取得
     * @return string
     */
    public static function get_description(){ return self::$_desc; }
    
    /**
     * <b>[ Meta ]</B><br>
     * robotsの設定（"noindex,nofollow,noarchive"など）
     * @param string $content 
     */
    public static function robots($content){ self::$_robots = $content; }
    
    /**
     * <b>[ Meta ]</B><br>
     * robotsの取得（"noindex,nofollow,noarchive"など）
     * @return string "noindex,nofollow,noarchive"など
     */
    public static function get_robots(){ return self::$_robots ; }
    
    /**
     * <b>[ Meta ]</B><br>
     * OG:IMAGEの設定
     * @param string $url
     */
    public static function image($url){ self::$_img = $url; }
    
    /**
     * <b>[ Meta ]</B><br>
     * OG:IMAGEの取得
     * @return string
     */
    public static function get_image(){ return self::$_img; }
}

class Sitemap {
    public static function CHANGEFREQ(){ return ["daily","weekly","monthly","yearly", "never", "hourly","always"]; }
    public static function PRIORITY(){ return ["1.0","0.9","0.8","0.7","0.6","0.5","0.4","0.3","0.2","0.1"]; }
    public static $DB_FILE = "sitemap.db";

    public static function dbQuery(){
        $f = Path::app(static::$DB_FILE);
        $dsn = ["driver" => "sqlite", "dsn"=> $f, "ATTR_ERRMOD" => false];
        $q = new DbQuery($dsn);
        $q->fetchClass(SitemapItem::class);
        if(!file_exists($f)){ echo"none"; $q->executeRaw(file_get_contents(Path::sesame("res","sitemap-slite.sql"))); }
        return $q;
    }
    
    public static function XML(){
        $x = new Seo\SitemapXmlGenelator();
        $q = self::dbQuery();
        $rows = $q->table("map")->where("is_publish", 1)->ordrBy("priority DESC, loc")->select();
        foreach($rows as $e){
            $e instanceof SitemapItem;
            $x->addUrl($e->loc, $e->priority, $e->changefreq, $e->lastmod);
        }
        return $x->gen();
    }

    public static function getAll($ignore_private = false){
        $q = self::dbQuery();
        if($ignore_private){ $q->where("is_publish", 1); }
        return $q->table("map")->ordrBy("loc")->select();
    }

    public static function is_registable_loc($loc, $id = null){
        $q = self::dbQuery();
        $loc = trim(trim($loc), "/");
        if(StringUtil::isEmpty($loc)){ $loc = "/" ;}

        $q->table("map")->where("loc", $loc);
        if(StringUtil::isNotEmpty($id)){
            $q->ands("id", "!=", $id);
        }
        return !$q->isExists();
    }

    public static function get($id){
        $q = self::dbQuery();
        $q->table("map")->where("id", $id);
        $e = $q->selectFirst();
        $e instanceof \Bin\SitemapItem;
        return $e;
    }
    public static function getByLoc($loc){
        $loc = '/'.trim(trim($loc),'/');
        $q = self::dbQuery();
        $q->table("map")->where("loc", $loc);
        $e = $q->selectFirst();
        $e instanceof \Bin\SitemapItem;
        return $e;
    }
    
    public static function getByRequest(){
        $q = self::dbQuery();
        $q->table("map");
        $q->where("loc", "/".trim(\Request::getPathInfo(),"/"));
        $e = $q->selectFirst();
        $e instanceof \Bin\SitemapItem;
        return $e;
    }
    public static function getParentsList($parents_id){
        $q = self::dbQuery();
        $r  = array();
        $ids = array();
        $pid = $parents_id;
        $ids[] = $pid;
        while(StringUtil::isNotEmpty($pid)){
            $e = $q->table("map")->where("id", $pid)->fetchClass(SitemapItem::class)->selectFirst();
            $pid = null;
            if($e !== null){
                $r[] = $e;
                if(!in_array($e->parents_id, $ids)){
                    $pid = $e->parents_id;
                }
            }
        }
        return $r;
    }
    public static function getChildList($id){
        $q = self::dbQuery();
        return $q->table("map")->where("parents_id", $id)->fetchClass(SitemapItem::class)->select();
    }

    public static function save(SitemapItem $entity){
        $q = self::dbQuery();
        $id = $entity->id;
        return self::update($id, $entity->toArray("id"));
    }
    public static function update($id, array $data){
        $q = self::dbQuery();
        $find = StringUtil::isEmpty($id) ? false : $q->table("map")->where("id", $id)->isExists();

        if(isset($data["id"])){ unset($data["id"]); }
        if(isset($data["loc"])){
            $loc = trim(trim($data["loc"]), "/");
            if(StringUtil::isEmpty($loc)){ $loc = "/" ;}else{ $loc = "/".$loc;  }
            $data["loc"] = $loc;
        }
        if($find){
            $q->table("map")->where("id", $id)->setArray($data)->update();
        }else{
            $q->table("map")->setArray($data)->insert();
            $id = $q->lastInsertId();
        }
        return $id;
    }
    public static function delete($id){
        $q = self::dbQuery();
        $q->table("map")->where("id", $id)->delete();
    }
}

/**
 * <b>trait</b> @ sitemap<br>
 * - <b>logical : </b>サイトマップ
 * - <b>physical : </b>sitemap
**/
class SitemapItem extends IEntity { use __SitemapItem; }
trait __SitemapItem{
    public $id;
    public $parents_id;
    public $is_publish;
    
    public $loc;
    public $tag;
    public $title;
    public $description;
    public $og_image;
    public $lastmod;
    public $changefreq;
    public $priority;
}
?>