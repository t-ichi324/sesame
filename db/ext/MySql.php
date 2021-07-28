<?php
namespace DB\ext;

class MySql {
    private $q;
    private $adp;
    public function __construct(\DbQuery $dbquery) { $this->q = $dbquery; $this->adp = $this->q->_adapter(); }

    /**
     * MYSQLのダンプ保存<br>
     * コンフィグのbinが指定されていない場合、実行できない可能性あり.
     * @param string $filename 保存先
     * @return int
     */
    public function createDump($filename){
        $u = $this->adp->getConf("username");
        $p = $this->adp->getConf("password");
        $h = $this->adp->getDsn("host");
        $db = $this->adp->getDsn("dbname");
        $bin = $this->adp->getConf("bin");
        
        $cmd = $bin;
        if(!empty($cmd)){ $cmd .= DIRECTORY_SEPARATOR; }
        $cmd .= "mysqldump -u{$u} -p{$p}";
        
        if($h !== null) { $cmd.= " -h{$h}"; }
        if($db !== null) { $cmd.= " {$db}"; }
        $cmd .= ' > ' . $filename;
        $ret = system($cmd);
        return $ret;
    }
    
    /**
     * <p>CSVファイルをインポート</p>
     * @param string $file CSVファイル
     * @param string $table 登録テーブル
     * @param string $charaset 文キャラセット
     * @param string $lineTerminated 行末コード
     * @return int
     */
    public function csvLoader($file, $table, $charaset = "", $lineTerminated = "x'0D0A'"){
        $sql = ' load data local infile "'.str_replace("\\", "\\\\", $file).'"';
        $sql.= " into table `".$table."` FIELDS TERMINATED BY ','  ENCLOSED BY '\"' ";
        if(!empty($lineTerminated)){
            $sql.=" LINES TERMINATED BY x'0D0A'";
        }
        
        try{
            $q = $this->adp->getPDOObject([\PDO::MYSQL_ATTR_LOCAL_INFILE => true]);
            if(!empty($charaset)){ $q->exec("set character_set_database=".$charaset.";"); }
            $q->exec("TRUNCATE TABLE `".$table."`;");
            return $q->exec($sql);
        } catch (\Exception $ex) {
            if(class_exists("\Log")){ \Log::error($ex); }
            throw $ex;
        }
    }
}
