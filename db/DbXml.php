<?php
/**
 * (PHP 4, PHP 5, PHP 7)<br/>
 */
class DbXml extends \DB\DatabaseIO{
    private static $EXT = ".xml";
    private static $EXT_LEN = -4;
    private static $TAG_SQL = "sql";
    private static $ATTR_KEY = "key";
    private static $ATTR_PARAM = "param";
    private static $REP_PTN = "#\{\{(.*?)\}\}#";
    
    private static $_xmls = array();
    
    private $filename;
    private $_sqls;
    private $_injects;

    /**
     * XMLファイルのrootエレメントを取得
     * @param string $filename xmlのファイルpath. 絶対Path or Path::db_sql()からの相対Pathを指定
     * @param string $sqlkey sqlタグのkey名を指定
     * @return SimpleXMLElement
     * @throws \Exception
     */
    public function _GET_XML($filename){
        if(empty($filename)){ throw  new Exception("please specify the xml filename."); }
        
        if(substr($filename, self::$EXT_LEN) !== self::$EXT){
            $fn = $filename.self::$EXT;
        }else{
           $fn = $filename ;
        }
        $hash = sha1($fn);
        $x = null;
        if(!isset(self::$_xmls[$hash])){
            if(file_exists($fn)){
                $path = $fn;
            }else{
                $path = $this->adp->getSqlFile($fn);
            }
            //file
            if(!file_exists($path)){ throw new \Exception('could not found filename "'.$path.'".'); }
            $x = \simplexml_load_file($path);
            if(empty($x)){ throw new \Exception('could not load xmlfile in "'.$path.'".'); }

            self::$_xmls[$hash] = $x;
        }else{
            $x = self::$_xmls[$hash];
            $x instanceof \SimpleXMLElement;
        }
        return $x;
    }
    
    /**
     * XMLファイルのsqlエレメントを取得.
     * @param string $filename xmlのファイルpath. 絶対Path or Path::db_sql()からの相対Pathを指定
     * @param string $sqlkey sqlタグのkey名を指定
     * @return SimpleXMLElement
     * @throws \Exception
     */
    public function _GET_XML_ELEMENT($filename, $sqlkey){
        $xml = $this->_GET_XML($filename);
        $xp = self::$TAG_SQL . ' [@'.self::$ATTR_KEY.'="'.$sqlkey.'"]';
        $find = $xml->xpath($xp);
        if(empty($find)){ throw new \Exception('could not found xpath "'.$xp.'" in "'.$path.'".'); }
        return $find[0];
    }
    
    /**
     * XMLファイルのsql文字列を取得.
     * @param string $filename xmlのファイルpath. 絶対Path or Path::db_sql()からの相対Pathを指定
     * @param string $sqlkey sqlタグのkey名を指定
     * @return string sql
     * @throws \Exception
     */
    public function _GET_XML_SQL($filename, $sqlkey){
        $ele = $this->_GET_XML_ELEMENT($filename, $sqlkey);
        return (string)$ele[0];
    }

    /**
     * DbXmlの条件のクリアを行う<br>
     * コネクション・トランザクション、AutoClear_Flagの状態は保持されます
     * @return Chainable
     */
    public function clear(){
        $this->clearBinds();
        $this->filename = null;
        $this->_sqls = array();
        $this->_injects = array();
        return $this;
    }
    
    /**
     * XMLファイルのsqlを設定する.
     * @param string $filename xmlのファイルpath. 絶対Path or Path::db_sql()からの相対Pathを指定
     * @return Chainable
     */
    public function file($filename){
        $this->filename = $filename;
        return $this;
    }
    
    /**
     * XMLファイルのsqlを設定する.
     * @param string $class_name EntityClass:classを指定
     * @return Chainable
     */
    public function file_Entity($class_name){
        if(class_exists($class_name)){
            if(method_exists($class_name, "XML")){
                $n = str_replace("`", "", $class_name::XML());
            }else{
                $n = $class_name;
            }
            $this->fetchClass($class_name);
        }else{
            $n = $class_name;
        }
        $this->filename = $n;
        return $this;
    }
    
    /**
     * 実行SQLを指定、XML内の別 key名のsqlを指定する。
     * @param string $sqlkey sqlタグのkey属性名を指定
     * @return Chainable
     */
    public function sql($key, array $bindParams = null){
        $this->_sqls = array();
        return $this->appendSql($key, $bindParams);
    }
    
    /**
     * SQLの末尾に、XML内の別 key名のsql を追加する。
     * @param string $sqlkey sqlタグのkey属性名を指定
     * @return Chainable
     */
    public function appendSql($key, array $bindParams = null){
        $this->_ap($key, true, $bindParams);
        return $this;
    }
    /**
     * SQLの末尾に、平文のSQLを追加する。
     * @param string $raw 平文のSQL
     * @param array $binds ストアドパラメータの [name=>val]配列
     * @return Chainable
     */
    public function appendRaw($raw, array $binds = null){
        $this->_ap($raw, false);
        if(!empty($binds)){ foreach($binds as $k => $v){ $this->bind($k, $v); } }
        return $this;
    }
    
    /**
     * SQLのインジェクトKeyを、XML内の別 key名のsqlで置き換える。
     * @param string $injectkey <b>{{injectkey}}</b>の名称
     * @param string $sqlkey sqlタグのkey属性名を指定
     * @return Chainable
     */
    public function injectSql($injectkey, $sqlkey, array $bindParams = null){
        $this->_ij($injectkey, $sqlkey, true, $bindParams);
        return $this;
    }

    /**
     * SQLのインジェクトKeyを、平文のSQLを追加する。
     * @param string $injectkey <b>{{injectkey}}</b>のカッコ内の文字列
     * @param string $raw 平文のSQL
     * @param array $binds ストアドパラメータの [name=>val]配列
     * @return Chainable
     */
    public function injectRaw($injectkey, $raw, array $binds = null){
        $this->_ij($injectkey, $raw, false);
        if(!empty($binds)){ foreach($binds as $k => $v){ $this->bind($k, $v); } }
        return $this;
    }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し結果を配列で取得<br>
     * 配列の要素は(fetchClass / fetchArray)の状況により変化
     * @return array entity(fetchClass) or array(fetchArray)
     */
    public function select(){
        $q = $this->toQuery();
        return $this->_run_select($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し先頭行の結果を取得<br>
     * 結果は(fetchClass / fetchArray)の状況により変化
     * @return entity|array entity(fetchClass) or array(fetchArray)
     */
    public function selectFirst(){
        $q = $this->toQuery();
        return $this->_run_selectFirst($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し、対象フィールド値の一覧を配列で取得
     * <br><b>ORDER BYは維持されません</b>
     * @param string $fieldname 対象フィールド名
     * @return array
     */
    public function getValues($fieldname){
        $q = $this->toQuery();
        return $this->_run_getValues($fieldname, $q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し、対象フィールド値の一覧を配列で取得
     * @param string $fieldname 対象フィールド名
     * @return array
     */
    public function getValue($fieldname, $defaultValue = null){
        $q = $this->toQuery();
        return $this->_run_getValue($fieldname, $q[self::$_QRY_SQL], $q[self::$_QRY_BIND], $defaultValue);
    }
    /**
     * <b>[ SQL実行 ]</B><br>
     * COUNT($fieldName)の結果を取得
     * @return int ヒット件数
     */
    public function getCount($auto_clear = true){
        $q = $this->toQuery();
        return $this->_run_getCount($q[self::$_QRY_SQL], $q[self::$_QRY_BIND], $auto_clear);
    }

    /**
     * <b>[ SQL実行 ]</B><br>
     * Inser Update Deleteなどを実行
     * @return int
     */
    public function execute(){
        $q = $this->toQuery();
        return $this->_run_exec($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    
    /**
     * 作成クエリを取得
     * @param string $fields 取得フィールド名
     * @return array ["sql"=>string, "stmt"=>array]
     */
    public function toQuery(){
        //sql
        $sql = implode("", $this->_sqls);
        
        //replace inject
        $sqli = preg_replace_callback(self::$REP_PTN, function ($m) {
            $key = trim($m[1]);
            if(isset($this->_injects[$key])){
                return $this->_injects[$key];
            }
            return "/* {{".$key."}} */";
        },$sql);
        
        return array(self::$_QRY_SQL=>$sqli, self::$_QRY_BIND=>$this->usr_binds);
    }
    
    private function _ap($val, $is_sqlkey, array $bindParams = null){
        if($val === null || $val === ""){ return; }
        if($is_sqlkey){
            $this->_sqls[] = $this->_xsql($val, $bindParams);
        }else{
            $this->_sqls[]  = $val;
        }
    }
    private function _ij($injectkey, $val, $is_sqlkey, array $bindParams = null){
        if($val === null || $val === ""){ return; }
        if($is_sqlkey){
            $this->_injects[$injectkey] = $this->_xsql($val, $bindParams);
        }else{
            $this->_injects[$injectkey]  = $val;
        }
    }
    private function _xsql($key, array $bindParam = null){
        $ele = $this->_GET_XML_ELEMENT($this->filename, $key);
        $ele instanceof \SimpleXMLElement;
        $at = "";
        foreach($ele->attributes() as $k => $v){
            if($k === self::$ATTR_PARAM){
                $at = $v;
            }
        }
        $sql = (string)$ele[0];
        
        $hasPrm = !empty($bindParam);
        if(empty($at)){
            if($hasPrm){
                foreach ($bindParam as $k=>$v){
                    $this->bind($k, $v);
                }
            }
        }else{
            foreach(explode(",", $at) as $name){
                $key = trim($name);
                if(empty($key)){ continue;}
                if($hasPrm && isset($bindParam[$key])){
                    $this->bind($key, $bindParam[$key]);
                }else{
                    $this->bind($key, null);
                }
            }
        }
        return $sql;
    }
}
