<?php
namespace DB;
abstract class IConf{
    public static function __CONF($conName = null){
        if(empty($conName)){ return static::def(); }
        return forward_static_call(array(static::class, $conName)); 
    }

    /** デフォルトのConnection情報を返す */
    public static abstract function def();
}

class Connection {
    private static $conns = array();
    private static $instance = array();
    public $connection = null;
    
    public function fromDefined($conName = null, $singleton = true){
        if($singleton){
            if(isset(self::$instance[$conName])){ return self::$instance[$conName]; }
        }
        if(isset(self::$conns[$conName])){
            $this->connection = self::$conns[$conName];
        }else{
            $this->connection = array();
            if(!class_exists(DB_CONFIG_CLASS)){ return null; }
            $this->connection = \DbConf::__CONF($conName);
            if(empty($this->connection)){ return null; }
        }
        
        $adp = new Adapter($this);
        if($singleton){ self::$instance[$conName] = $adp; }
        return $adp;
    }
    public function fromArray(array $dsn, $singleton = true){
        if($singleton){
            $key = base64_encode(json_encode($dsn));
            if(isset(self::$instance[$key])){ return self::$instance[$key]; }
        }
        
        $this->connection = $dsn;
        $adp = new Adapter($this);
        if($singleton){ self::$instance[$key] = $adp; }
        return $adp;
    }
}

class Adapter {
    private $_pdo;
    private $confClass;
    private $confName;
    private $conf;
    private $connected; // 0:none, 1:connected, -1:error;

    //logs
    private $log_lvl = 1;
    private $log_file_sel = null;
    private $log_file_exec = null;
    private $log_file_err = null;
    //sqldir
    private $dir_sql = null;

    public $hasError = false;

    private function pdo(){ if(empty($this->_pdo)){ $this->init_con(); } return $this->_pdo; }
    private function init_con(){ $this->_pdo = $this->getPDOObject(); }
    
    public function __construct(Connection $con){
        $this->log_lvl = 1;
        if(empty($con->connection) || !is_array($con->connection)){ return false; }
        $this->conf = $con->connection;
        $this->log_file_sel = $this->getConf("LOG_SELECT_FILE");
        $this->log_file_exec = $this->getConf("LOG_EXECUTE_FILE");
        $this->log_file_err = $this->getConf("LOG_ERROR_FILE");
        //dir
        $this->dir_sql = $this->getConf("DIR_SQL");
        if(!empty($this->dir_sql)){ 
            $this->dir_sql = str_replace("/", DIRECTORY_SEPARATOR, $this->dir_sql);
            if(substr($this->dir_sql, -1) !== DIRECTORY_SEPARATOR){ $this->dir_sql .= DIRECTORY_SEPARATOR; }
        }
        
        return true;
    }
    
    /** Setconf */
    public function setConf($conf){
         $this->rollback(); $this->connected = 0; $this->_pdo = null;
    }
    
    /** デストラクタ */
    function __destruct(){ $this->rollback(); $this->connected = 0; $this->_pdo = null;  }
    
    /** コネクション生成 */
    public function getPDOObject($option = array()){
        try{
            $dsn = $this->getDsnString();
            $p = new \PDO($dsn, $this->getConf("username"), $this->getConf("password"), $option);
            $cs = $this->getConf("charaset");
            $tz = $this->getConf("timezone");
            if(!empty($cs)){ $p->query("SET NAMES {$cs};"); }
            if(!empty($tz)){ $p->query("SET SESSION time_zone = '{$tz}';"); }

            if($this->hasConf("ATTR_ERRMODE")){
                $p->setAttribute(\PDO::ATTR_ERRMODE, $this->getConf("ATTR_ERRMODE"));
            }else{
                $p->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            }
            if($this->hasConf("ATTR_EMULATE_PREPARES")){
                $p->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $this->getConf("ATTR_EMULATE_PREPARES"));
            }
            
            return $p;
        } catch (\Exception $ex) {
            $this->wlog_err("DB CONNECTION ERR", array("MESSAGE"=>$ex->getMessage(),"CONF"=>$this->confName, "VALUES"=>$this->conf), $ex);
            throw $ex;
        }
    }
    
    public function getConf($key, $nullVal = null){ return (!empty($this->conf) && isset($this->conf[$key])) ? $this->conf[$key] : $nullVal; }
    public function hasConf($key){ return isset($this->conf[$key]); }
    public function getConfName(){ return$this->confName; }
    public function getConfClass(){ return$this->confClass; }
    public function getDriver(){ return $this->getConf("driver");}
    public function getDsn($key = null, $nullVal = null){
        $dsn = $this->getConf("dsn", array());
        if($key === null){return $dsn; }
        if(isset($dsn[$key])){ return $dsn[$key]; }
        return $nullVal;
    }
    public function getDsnString(){
        $ret = $this->getConf("driver","N/A").":";
        $dsn = $this->getConf("dsn", "null");
        if(is_array($dsn)){
            foreach ($dsn as $k=>$v){
                if(is_string($k) && !empty($k)){ $ret .= $k."="; }
                $ret .= $v.";";
            }
        }elseif(is_string($dsn)){
            $ret .= $dsn;
        }
        return $ret;
    }
    public function getSqlFile($filename){ return $this->dir_sql . $filename; }
    
    public function isConnected(){ return $this->connected === 1;}
    public function beginTran(){ $this->pdo()->beginTransaction(); }
    public function commit(){ $this->pdo()->commit(); }
    public function rollback(){ if($this->_pdo !== null && $this->connected === 1){ try{ $this->_pdo->rollback(); } finally{ } } }
    public function lastInsertId(){ return $this->pdo()->lastInsertId(); }
    
    /** 実行系SQL */
    public function execute($sql, array $binds = null){
        try{
            $this->wlog_exe($sql, $binds);
            $stmt = $this->pdo()->prepare($sql);
            $i = 0;
            if(isset($binds)){
                foreach($binds as $k => $v){
                    $key = ":".$k;
                    $stmt->bindValue($key, $v);
                }         
            }
            return $stmt->execute();
        } catch (\Exception $ex) {
            $this->wlog_err($sql, $binds, $ex);
            throw $ex;
        }
    }
    
    /** SELECT分からバインド文字列を用い一覧を取得する */
    public function select($sql, array $binds = null, $fetchClass = null){
        try{
            $stmt = $this->pdo()->prepare($sql);
            $stmt instanceof \PDOStatement;
            $i = 0;
            if(!empty($binds)){
                foreach($binds as $k => $v){
                    $key = ":".$k;
                    $stmt->bindValue($key, $v);
                }
            }
            $this->wlog_sel($sql, $binds);
            $stmt->execute();
            if(empty($fetchClass)){
                $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            }else{
                if($fetchClass === "stdClass"){
                    $stmt->setFetchMode(\PDO::FETCH_OBJ);
                }else{
                    $stmt->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $fetchClass, [[]]);
                }
            }

            return $stmt->fetchAll();
        } catch (\Exception $ex) {
            $this->wlog_err($sql, $binds, $ex);
            throw $ex;
        }
    }    
    
    //logs
    /** log-level: -1, 0, 1, 2 */
    public function _log($level){
        if($level == null || !is_numeric($level)){ $this->level = 1; }
        else{ $this->log_lvl = (int)$level; }
    }
    private function wlog_err($sql, $binds, $ex){
        $this->hasError = true;
        if($this->log_lvl < 0 || $this->log_file_err === null){ return; }
        $log = $this->l2j(array('err'=>$ex, 'sql' =>  $sql, 'binds' => $binds));
        try{ error_log($log, 3, $this->log_file_err);
        } catch (\Exception $ex) { $this->log_file_err = null; }
    }
    private function wlog_exe($sql, $binds){
        if($this->log_lvl < 1 || $this->log_file_exec === null){ return; }
        $log = $this->l2j(array( 'sql' =>  $sql, 'binds' => $binds));
        try{ error_log($log, 3, $this->log_file_exec);
        } catch (\Exception $ex) { $this->log_file_exec = null;}
    }
    private function wlog_sel($sql, $binds){
        if($this->log_lvl < 2 || $this->log_file_sel === null){ return; }
        $log = $this->l2j(array('sql' =>  $sql, 'binds' => $binds));
        try{ error_log($log, 3, $this->log_file_sel);
        } catch (\Exception $ex) { $this->$this->log_file_sel = null; }
    }
    
    // privates
    private function l2j(array $any){
        $p = date("Y-m-d H:i:s").
            "\t".$_SERVER["REMOTE_ADDR"].
            "\t".$_SERVER["REQUEST_METHOD"].
            "\t".$_SERVER["REQUEST_URI"];
        $p.="\t".json_encode($any);
        return $p."\n";
    }
}

/**
 * データベース接続インターフェイス.
 */
abstract class DatabaseIO{
    protected static $_QRY_SQL = "sql";
    protected static $_QRY_BIND = "binds";
    
    protected $adp; //DB Adapter
    protected $fetchClass;
    protected $usr_binds = array();
    protected $auto_clear_flag = true;

    /**
     * <b>abstract function.<b><br>
     * 条件のクリアを行う.
     */
    public abstract function clear();
    public abstract function select();
    public abstract function selectFirst();
    public abstract function getCount($auto_clear = true);
    
    /**
     * コンストラクタ
     * @param type $connection
     * @param type $singleton
     */
    public function __construct($connection = null, $singleton = true){
        $con = new \DB\Connection();
        if(empty($connection) || is_string($connection)){
            $this->adp = $con->fromDefined($connection, $singleton);
        }elseif(is_array($connection)){
            $this->adp = $con->fromArray($connection, $singleton);
        }
        $this->adp instanceof \DB\Adapter;
        $this->autoClear_ON();
        $this->clear();
    }
    
    /**
     * デストラクタ
     */
    function __destruct(){ $this->adp = null; }

    /**
     * Adapterの取得
     * @return \DB\Adapter
     */
    public function _adapter(){ return $this->adp; }
    
    /**
     * <p>ログ出力レベル設定<p>
     * @param int $level <br>
     * <b>0</b>: errorのみ出力する.<br>
     * <b>1</b>: exec, errorを出力する <b>(デフォルト)</b>.<br>
     * <b>2</b>: select, exec, errorを出力する.<br>
     * <b>-1</b>: すべて出力しない.<br>
     */
    public function _log($level){
        if(!is_numeric($level) || $level > 2 || $level < -1){ $level = 1; }
        $this->adp->_log($level);
    }
    
    /**
     * クローンの作成
     * @return self
     */
    public function _clone(){ $new_query = clone $this; return $new_query; }
    
    /**
     * MySQL系のFunction
     * @return \DB\EXT\MySQL
     */
    public function _mysql(){
        include_once __DIR__.DIRECTORY_SEPARATOR.'ext'.DIRECTORY_SEPARATOR."MySql.php";
        return new \DB\ext\MySql($this);
    }

    /**
     * <b>[ トランザクション ]</b><br>
     * トランザクションの開始
     */
    public function beginTran(){ $this->adp->beginTran(); }
    /**
     * <b>[ トランザクション ]</b><br>
     * コミット
     */
    public function commit(){ $this->adp->commit();}
    /**
     * <b>[ トランザクション ]</b><br>
     * ロールバック
     */
    public function rollback(){ $this->adp->rollback(); }
    
    /**
     * ダイレクトなSQLを実行<br>
     * @param string $sql 実行クエリ
     * @param array $binds ストアドパラメータ
     * @return int
     */
    public function executeRaw($sql, array $binds = null){ return $this->adp->execute($sql, $binds); }
    
    /**
     * ダイレクトなSQLを実行<br>
     * @param string $sql 実行クエリ
     * @param array $binds ストアドパラメータ
     * @return array
     */
    public function selectRaw($sql, array $binds = null, $fetchClass = null){ return $this->adp->select($sql, $binds, $fetchClass); }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * データが存在するか判定
     * @return boolean
     */
    public function isExists($auto_clear = true){
        return ($this->getCount($auto_clear) > 0);
    }
    
    /**
     * オートインクリメント等で最後に追加したIDを取得
     * @return int
     */
    public function lastInsertId(){ return $this->adp->lastInsertId(); }
    
    /**
     * [ select: 取得データ型 ]<br>
     * Selectの結果を連想配列で返す
     * @return chainable
     */
    public function fetchArray(){ $this->fetchClass = null; return $this; }
    
    /**
     * [ select: 取得データ型 ]<br>
     * Selectの結果を指定クラスのインスタンスで返す。
     * @param string $entity_name AnyClass::classを指定。（存在しない場合やNullの場合は"stdClass"にマッピングされる）
     * @return chainable
     */
    public function fetchClass($entity_name = null){
        $this->fetchClass = "stdClass";
        if(empty($entity_name)){ return $this; }
        if(is_object($entity_name)){
            $this->fetchClass = get_class($entity_name);
        }else if(is_string($entity_name) && class_exists($entity_name)){
            $this->fetchClass = $entity_name;
        }
        return $this;
    }
    
    /**
     * ストアドパラメータ bindパラメータの追加
     * @param string $bind_name バインド名(:は不要)
     * @param mixed $val 値
     * @return $this chainable
     */
    public function bind($bind_name, $val){ $this->usr_binds[ltrim($bind_name,':')] = $val; return $this; }

    /**
     * 指定したストアドパラメータをクリアする.
     * @return chainable
     */
    public function clearBinds(){ $this->usr_binds = array(); return $this; }
    
    /**
     * SQL実行ファンクション時に、DbQueryの条件のクリアを行う
     * @return chainable
     */
    public function autoClaer_OFF(){$this->auto_clear_flag = false; return $this; }
    /**
     * SQL実行ファンクション時に、DbQueryの条件のクリアを行わない
     * @return chainable
     */
    public function autoClear_ON(){$this->auto_clear_flag = true; return $this; }
    /**
     * SQL実行ファンクション時に、DbQueryの条件のクリアを行うか
     * @return boolean true:行う, false:行わない
     */
    public function autoClear_Flag(){ return $this->auto_clear_flag; }
    
    /** [protected] オートクリア実行 */
    protected function _run_autoClear(){ if($this->auto_clear_flag){ $this->clear(); }}
    
    /** [protected] */
    protected function _run_select($sql, array $binds = null){
        $r = $this->selectRaw($sql, $binds, $this->fetchClass);
        $this->_run_autoClear();
        return $r;
    }

    /** [protected] */
    protected function _run_selectFirst($sql, array $binds = null){
        $r = $this->selectRaw($this->wrapSql($sql, "*", " LIMIT 1"), $binds, $this->fetchClass);
        $this->_run_autoClear();
        if(count($r) > 0){ return $r[0]; }
        return null;
    }
    
    private function wrapSql($sql, $fld, $pfx){
        $wf = ($fld == "" || $fld == "*") ? "*" : $fld." AS `_expre_f0_`";
        $wq = rtrim(trim($sql),";");
        return "SELECT ".$wf." FROM(".$wq.") `_expr_q_` ". $pfx .";";
    }
    
    /** [protected] */
    protected function _run_getValues($field, $sql, array $binds = null, $run_clear = true){
        $r = $this->selectRaw($this->wrapSql($sql, $field, ""), $binds, null);
        if($run_clear){ $this->_run_autoClear(); }
        $ar = array();
        if(count($r) === 0){ return $ar; }
        foreach($r as $v){ $ar[] = array_shift($v); }
        return $ar;
    }
    /** [protected] */
    protected function _run_getValue($field, $sql, array $binds = null, $defaultValue = null, $run_clear = true){
        $r = $this->selectRaw($this->wrapSql($sql, $field, " LIMIT 1"), $binds, null);
        if($run_clear){ $this->_run_autoClear(); }
        
        if(count($r) === 0){ return $defaultValue; }
        return array_shift($r[0]);
    }
    
    /** [protected] */
    protected function _run_getCount($sql, array $binds = null, $run_clear = true){
        $r = $this->selectRaw($this->wrapSql($sql, "COUNT(*)", ""), $binds, null);
        if($run_clear){ $this->_run_autoClear(); }
        
        if(count($r) === 0){ return 0; }
        return array_shift($r[0]);
    }
    
    /** [protected] */
    protected function _run_exec($sql, array $binds = null){
        $r= $this->executeRaw($sql, $binds);
        $this->_run_autoClear();
        return $r;
    }
}