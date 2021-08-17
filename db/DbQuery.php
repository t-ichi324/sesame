<?php
/**
 * (PHP 4, PHP 5, PHP 7)<br/>
 */
class DbQuery extends \DB\DatabaseIO{
    private static $_BIND_PFX = "_p_";
    
    private $table; //raw string
    private $joins = array();
    private $fldQuery = array();
    private $group, $order, $having; //raw string
    private $offset, $limit;
    
    private $sets = array();
    private $set_binds = array();
    private $wheres = array();
    private $where_binds = array();
    
    private $_b_idx = 0; //cnt: bind index
    private $_f_ro = false; //flag: require operator
    
    /**
     * DbQueryの条件のクリアを行う<br>
     * コネクション・トランザクション、AutoClear_Flagの状態は保持されます
     * @return Chainable
     */
    public function clear(){
        $this->fetchClass = null;
        $this->table = null;
        $this->fldQuery = array();
        $this->joins = array();
        $this->clearAggrs();
        $this->clearWheres();
        $this->clearSets();
        $this->clearBinds();
        $this->_b_idx = 0;
        return $this;
    }
    
    /**
     * DbQueryの集計設定(group, having, order, offset, limit)のみをクリアする
     * @return Chainable
     */
    public function clearAggrs(){ $this->group = null; $this->order = null; $this->having = null; $this->offset = null; $this->limit = null; return $this; }
    /**
     * DbQueryの条件設定(where, ands ors, exists系、関連スタドパラメータ)のみをクリアする
     * @return Chainable
     */
    public function clearWheres(){ $this->_f_ro = false; $this->wheres= array(); $this->where_binds = array();  return $this; }
    /**
     * DbQueryの更新パラメータ設定(set系、関連スタドパラメータ)のみをクリアする
     * @return Chainable
     */
    public function clearSets(){ $this->sets = array(); $this->set_binds = array(); return $this; }
    
    /**
     * <b>[ Table指定 ]</B><br>
     * テーブル名を指定
     * @param string $name テーブル名 または select sql
     * @param type $alias テーブルエイリアス
     * @return Chainable
     */
    public function table($name, $alias = null){
        if($name instanceof DbQuery){ return $this->table_DbQuery($name, $alias); }
        if(is_object($name)){ return $this->table_Entity($name, $alias); }
        
        return $this->_tblname($name, $alias);
    }
    private function _tblname($name, $alias){
        $this->table = self::BQ($name, true);
        if(!empty($alias)){
            $this->table .= " ".self::BQ($alias);
        }
        return $this;
    }


    /**
     * <b>[ Table指定 ]</B><br>
     * DbQueryを結合
     * @param DbQuery $q 他DbQueryインスタンス
     * @param type $alias テーブルエイリアス
     * @return Chainable
     */
    public function table_DbQuery(DbQuery $q, $alias = null){
        $query = $q->toQuerySelect();
        $sql = $query[self::$_QRY_SQL];
        if(isset($query[self::$_QRY_BIND]) && is_array($query[self::$_QRY_BIND])){
            foreach($query[self::$_QRY_BIND] as $v){ $this->where_binds[] = $v; }
        }
        return $this->_tblname("(".$sql.")", $alias);
    }
    /**
     * <b>[ Table指定 ]</B><br>
     * DbQueryインスタンスから指定
     * @param string $class_name EntityClass:classを指定
     * @param type $alias テーブルエイリアス
     * @return Chainable
     */
    public function table_Entity($class_name, $alias = null){
        if(class_exists($class_name)){
            if(method_exists($class_name, "TABLE")){
                $n = $class_name::TABLE();
            }else{
                $n = $class_name;
            }
            $this->fetchClass($class_name);
        }else{
            $n = $class_name;
        }
        return $this->_tblname($n, $alias);
    }
    
    /**
     * 動作不安定
     * @param type $tableOrQuery
     * @param type $alias
     * @param type $onExpr
     * @return Chainable
     */
    public function leftJoin($tableOrQuery, $alias, $onExpr){
        $this->joins[] = "LEFT JOIN ".self::BQ($tableOrQuery, true)." AS ".self::BQ($alias)." ON ".$onExpr." ";
        return $this;
    }
    /**
     * 動作不安定
     * @param type $tableOrQuery
     * @param type $alias
     * @param type $onExpr
     * @return Chainable
     */
    public function rightJoin($tableOrQuery, $alias, $onExpr){
        $this->joins[] = "RIGHT JOIN ".self::BQ($tableOrQuery, true)." AS ".self::BQ($alias)." ON ".$onExpr." ";
        return $this;
    }
    
    /**
     * <b>[ Select: 式 ]</B><br>
     * フィールド内クエリを追加する
     * @param string $field_alias 式エイリアス
     * @param string $query 式
     * @return Chainable
     */
    public function fieldQuery($field_alias, $query){ $this->fldQuery[$field_alias] = $query;  return $this;}
    
    /**
     * <b>[ Select: 集計用 ]</B><br>
     * @param type $fiels
     * @return Chainable
     */
    public function groupBy(... $fiels){$this->group = self::XBQ($fiels, " GROUP BY "); return $this;}
    /**
     * <b>[ Select: 集計用 ]</B><br>
     * HAVINGを設定する
     * @param type $fiels
     * @return Chainable
     */
    public function having(... $fiels){$this->having = self::XBQ($fiels, " HAVING "); return $this;}
    
    /**
     * <b>[ Select: 集計.Pager ]</B><br>
     * 取得順を指定する。
     * @param string $fiels フィールド名 ("ASC DESC"は同一文字列内へ)
     * @return Chainable
     */
    public function ordrBy(... $fiels){$this->order = self::XBQ($fiels, " ORDER BY "); return $this;}
    
    /**
     * <b>[ Select: Pager ]</B><br>
     * 取得開始位置を指定する
     * @param int $num オフセット
     * @return Chainable
     */
    public function offset($num){ $this->offset = $this->n2n($num); return $this; }
    /**
     * <b>[ Select: Pager ]</B><br>
     * 最大取得件数を設定する
     * @param int $num オフセット
     * @return Chainable
     */
    public function limit($num){ $this->limit = $this->n2n($num); return $this; }
    /**
     * <b>[ Select: Pager ]</B><br>
     * offset($page * $take) と limit($take)を設定する
     * @param int $take 最大取得件数
     * @param int $page ページ番号
     * @return Chainable
     */
    public function page($take, $page){ $this->offset($page * $take); $this->limit($take); return $this; }
    
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * 先頭カッコ [$preOp*] + "("を追加する。<br>
     * @param string $preOp "AND" or "OR"
     * @return Chainable
    */
    public function paren($preOp = "AND"){ $this->ifw(" ".$preOp." "); $this->wheres[] = "("; $this->_f_ro = false; return $this; }
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * 終端カッコ ")"を追加する
     * @return Chainable
     */
    public function parenEnd(){ $this->wheres[] = ")"; $this->_f_ro = true;  return $this;}

    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [AND*] + WHERE比較式
     * @param string $whereRaw 式
     * @param array $binds ストアドパラメータの [name=>val]配列
     * @return Chainable
     */
    public function whereRaw($whereRaw, array $binds = null){ $this->ifw(" AND "); return $this->w2r($whereRaw, $binds); }
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [AND*] + WHERE比較式
     * @param string $whereRaw 式
     * @param array $binds ストアドパラメータの [name=>val]配列
     * @return Chainable
     */
    public function andRaw($whereRaw, array $binds = null){ $this->ifw(" AND "); return $this->w2r($whereRaw, $binds); }
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [OR*] + WHERE比較式
     * @param string $whereRaw 式
     * @param array $binds ストアドパラメータの [name=>val]配列
     * @return Chainable
     */
    public function orRaw($whereRaw, array $binds = null){ $this->ifw(" OR "); return $this->w2r($whereRaw, $binds); }
    
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [AND*] + 比較分を追加する
     * @param string $fiels 対象フィールド
     * @param mixed $arg1 第三引数なし: ストアドパラメータ、第3引数あり：比較演算子（"AND","OR"）
     * @param mixed $arg2 ストアドパラメータ
     * @return Chainable
     */
    public function where($fiels, $arg1, $arg2 = null){$this->ifw("AND"); return $this->a2w(func_num_args(), $fiels, $arg1, $arg2); }
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [AND*] + 比較分を追加する
     * @param string $fiels 対象フィールド
     * @param mixed $arg1 第三引数なし: ストアドパラメータ、第3引数あり：比較演算子（"AND","OR"）
     * @param mixed $arg2 ストアドパラメータ
     * @return Chainable
     */
    public function ands($fiels, $arg1, $arg2 = null){ $this->ifw("AND"); return $this->a2w(func_num_args(), $fiels, $arg1, $arg2); }
    
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [OR*] + 比較分を追加する
     * @param string $fiels 対象フィールド
     * @param mixed $arg1 第三引数なし: ストアドパラメータ、第3引数あり：比較演算子（"AND","OR"）
     * @param mixed $arg2 ストアドパラメータ
     * @return Chainable
     */
    public function ors($fiels, $arg1, $arg2 = null){ $this->ifw("OR"); return $this->a2w(func_num_args(), $fiels, $arg1, $arg2); }
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [AND*] + 複数一致のLIKE文を追加する
     * @param array $fields 対象フィールド
     * @param array $keywords 対象キーワード
     * @param string $compare 一致条件 ("AND" or "OR")
     * @param boolean $pre_hit 前方一致フラグ（'%keyword'）
     * @param boolean $aftr_hit 後方一致フラグ（'keyword%'）
     * @return Chainable
     */
    public function whereLikes(array $fields, array $keywords,  $compare = "AND",  $pre_hit = true,  $aftr_hit = true){ $this->ifw("AND"); return $this->wls($fields, $keywords, $compare, $pre_hit, $aftr_hit); }
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [AND*] + 複数一致のLIKE文を追加する
     * @param array $fields 対象フィールド
     * @param array $keywords 対象キーワード
     * @param string $compare 一致条件 ("AND" or "OR")
     * @param boolean $pre_hit 前方一致フラグ（'%keyword'）
     * @param boolean $aftr_hit 後方一致フラグ（'keyword%'）
     * @return Chainable
     */
    public function andLikes(array $fields, array $keywords,  $compare = "AND",  $pre_hit = true,  $aftr_hit = true){ $this->ifw("AND"); return $this->wls($fields, $keywords, $compare, $pre_hit, $aftr_hit); }
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * [OR*] + 複数一致のLIKE文を追加する
     * @param array $fields 対象フィールド
     * @param array $keywords 対象キーワード
     * @param string $compare 一致条件 ("AND" or "OR")
     * @param boolean $pre_hit 前方一致フラグ（'%keyword'）
     * @param boolean $aftr_hit 後方一致フラグ（'keyword%'）
     * @return Chainable
     */
    public function orLikes(array $fields, array $keywords,  $compare = "AND",  $pre_hit = true,  $aftr_hit = true){ $this->ifw("OR"); return $this->wls($fields, $keywords, $compare, $pre_hit, $aftr_hit); }
    
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * 別DbQueryインスタンスと結合し、[AND*] + EXISTSを追加する
     * @param DbQuery $query 別インスタンス
     * @param string $whereRaw ストアドパラメータを利用せず指定文字がそのままSQLに入る.
     * @return Chainable
     */
    public function whereExists(DbQuery $query, $whereRaw = null){ $this->ifw("AND"); $this->wex($query, $whereRaw); return $this;}
    
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * 別DbQueryインスタンスと結合し、[AND*] + EXISTSを追加する
     * @param DbQuery $query 別インスタンス
     * @param string $whereRaw ストアドパラメータを利用せず指定文字がそのままSQLに入る.
     * @return Chainable
     */
    public function andExists(DbQuery $query, $whereRaw = null){ $this->ifw("AND"); $this->wex($query, $whereRaw); return $this;}
    
    /**
     * <b>[ (+) WHERE句 ]</B><br>
     * 別DbQueryインスタンスと結合し、[OR*] + EXISTSを追加する
     * @param DbQuery $query 別インスタンス
     * @param string $whereRaw ストアドパラメータを利用せず指定文字がそのままSQLに入る.
     * @return Chainable
     */
    public function orExists(DbQuery $query, $whereRaw = null){ $this->ifw("OR"); $this->wex($query, $whereRaw); return $this;}

    /**
     * <b>[ (+) データ更新パラメータ ]</B><br>
     * Insert / Update用の登録・更新データを指定<br>
     * ストアドプロシージャを利用せず指定文字がそのままSQLに入る
     * @param string $name フィールド名
     * @param string $raw ストアドパラメータを利用せず指定文字がそのままSQLに入る
     * @return Chainable
     */
    public function setRaw($name, $raw){ $this->sets[$name] = $raw; return $this;}
    
    /**
     * <b>[ (+) データ更新パラメータ ]</B><br>
     * Insert / Update用の登録・更新データを指定
     * @param string $name フィールド名
     * @param mixed $val データ
     * @return Chainable
     */
    public function set($name, $val){ $this->setRaw($name, $this->_bind_set($val)); return $this;}
    
    /**
     * <b>[ (+) データ更新パラメータ ]</B><br>
     * kv_arrayのフィールド情報を、set(key, val)へ登録する
     * @param array $kv_array
     * @param bool $ignore_null nullデータを無視
     * @return Chainable
     */
    public function setArray(array $kv_array, $ignore_null = false){
        foreach ($kv_array as $k => $v){
            if($ignore_null && $v === null){ continue; }
            $this->set($k, $v);
        }
        return $this;
    }
    
    /**
     * <b>[ (+) データ更新パラメータ ]</B><br>
     * entityのフィールド情報を、set(fieldname, val)へ登録する<br>
     * table()を指定していない場合は、$entity->TABLE()を自動的に設定.
     * @param IEntity $entity
     * @param bool $ignore_null nullデータを無視
     * @return Chainable
     */
    public function setEntity(IEntity $entity, $ignore_null = false){
        if(empty($this->table)){ $this->table($entity->TABLE()); }
        return $this->setArray($entity->toArray(), $ignore_null);
    }

    /**
     * <b>[ SQL実行 ]</B><br>
     * Insertを実行<br>
     * sets()の情報が適応される
     * @return int
     */
    public function insert(){
        $q = $this->toQueryInsert();
        return $this->_run_exec($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    /**
     * <b>[ SQL実行 ]</B><br>
     * Updateを実行<br>
     * sets()の情報が適応される
     * @return int
     */
    public function update(){
        $q = $this->toQueryUpdate();
        return $this->_run_exec($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    /**
     * <b>[ SQL実行 ]</B><br>
     * Insert Updateを実行 (現在MySQL用)
     * @param string $keyFields 取得フィールド名
     * @return int
     */
    public function insertUpdate(... $keyFields){
        $q = $this->toQueryInsertUpdate(... $keyFields);
        return $this->_run_exec($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    /**
     * <b>[ SQL実行 ]</B><br>
     * Deleteを実行
     * @return int
     */
    public function delete(){
        $q = $this->toQueryDelete();
        return $this->_run_exec($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し結果を配列で取得<br>
     * 配列の要素は(fetchClass / fetchArray)の状況により変化
     * @param string $fields 対象フィールド名
     * @return array entity(fetchClass) or array(fetchArray)
     */
    public function select(... $fields){
        $q = $this->toQuerySelect(... $fields);
        return $this->_run_select($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し先頭行の結果を取得<br>
     * 結果は(fetchClass / fetchArray)の状況により変化
     * @param string $fields 対象フィールド名
     * @return entity|array entity(fetchClass) or array(fetchArray)
     */
    public function selectFirst(... $fields){
        $q = $this->toQuerySelect(... $fields);
        return $this->_run_selectFirst($q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し、対象フィールド値の一覧を配列で取得
     * @param string $fieldName 対象フィールド名
     * @return array
     */
    public function getValues($fieldName){
        $q = $this->toQuerySelect($fieldName);
        return $this->_run_getValues($fieldName, $q[self::$_QRY_SQL], $q[self::$_QRY_BIND]);
    }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * Selectを実行し、対象フィールド値を取得
     * @param string $fieldName 対象フィールド名
     * @return dbtype
     */
    public function getValue($fieldName, $defaultVal = null){
        $q = $this->toQuerySignle($fieldName, false);
        return $this->_run_getValue($fieldName, $q[self::$_QRY_SQL], $q[self::$_QRY_BIND], $defaultVal);
    }
    private function getSingleAggrs($fieldName, $auto_clear){
        $q = $this->toQuerySignle($fieldName." AS `_exp_q0_` ", true);
        return $this->_run_getValue("`_exp_q0_`", $q[self::$_QRY_SQL], $q[self::$_QRY_BIND], null, $auto_clear);
    }
    
    /**
     * COUNT($fieldName)の結果を取得
     * @return int ヒット件数
     */
    public function getCount($auto_clear = true){ $r = $this->getSingleAggrs("COUNT(*)", $auto_clear); return empty($r) ? 0 : $r; }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * MAX($fieldName)の結果を取得
     * @param string $fieldName 対象フィールド名
     * @return dbtype number or null
     */
    public function getMax($fieldName, $auto_clear = true){ return $this->getSingleAggrs("MAX(".$fieldName.")", $auto_clear); }
    
    /**
     * MIN($fieldName)の結果を取得
     * @param string $fieldName 対象フィールド名
     * @return dbtype number or null
     */
    public function getMin($fieldName, $auto_clear = true){ return $this->getSingleAggrs("MIN(".$fieldName.")", $auto_clear); }
    
    /**
     * <b>[ SQL実行 ]</B><br>
     * AVE($fieldName)の結果を取得
     * @param string $fieldName 対象フィールド名
     * @return dbtype
     */
    public function getAve($fieldName, $auto_clear = true){ return $this->getSingleAggrs("AVE(".$fieldName.")", $auto_clear); }
    
    /**
     * select用 作成クエリを取得
     * @param string $fields 取得フィールド名
     * @return array ["sql"=>string, "stmt"=>array]
     */
    public function toQuerySelect(... $fields){
        $f = "";
        foreach($fields as $v){
            if(!empty($f)){ $f .= ","; }
            $f .= $this->gFS($v);
        }
        if(empty($f)){
            $f = "*";
            if(!empty($this->fldQuery)){
                foreach($this->fldQuery as $k => $v){
                    $f .= ",".$this->gFS($k);
                }
            }
        }
        $sql = "SELECT ".$f." FROM ".$this->table." ";
        foreach($this->joins as $j){
            $sql .= $j;
        }
        $where =  $this->gWS();
        $aggr = $this->group.$this->having.$this->order;
        $page_binds = array();
        $page = "";
        if(!empty($this->limit)){
            $page .= " LIMIT " . $this->limit; /*$this->_bind($this->limit, $page_binds);*/
        }
        if(!empty($this->offset)){
            $page .= " OFFSET " . $this->offset; /*$this->_bind($this->offset, $page_binds);*/
        }
        
        return self::mkQ($sql.$where.$aggr.$page, [$this->where_binds, $page_binds, $this->usr_binds]);
    }
    
    /**
     * select toQuerySignle用 作成クエリを取得
     * @param string $field 取得フィールド名
     * @return array ["sql"=>string, "stmt"=>array]
     */
    public function toQuerySignle($field, $is_agger = false){
        $f = $this->gFS($field);
        $sql = "SELECT ".$f." FROM ".$this->table." ";
        foreach($this->joins as $j){
            $sql .= $j;
        }
        $where =  $this->gWS();
        if($is_agger){
            $aggr = $this->group.$this->having;
            $page = "";
        }else{
            $aggr = $this->group.$this->having.$this->order;
            $page = " LIMIT 1";
        }
        
        return self::mkQ($sql.$where.$aggr.$page, [$this->where_binds, $this->usr_binds]);
    }
    
    /**
     * delete用 作成クエリを取得
     * @return array ["sql"=>string, "stmt"=>array]
     */
    public function toQueryDelete(){
        $sql = "DELETE FROM ".$this->table." ";
        $where = $this->gWS();
        
        return self::mkQ($sql.$where, [$this->where_binds, $this->usr_binds]);
    }
    
    /**
     * update用 作成クエリを取得
     * @return array ["sql"=>string, "stmt"=>array]
     */
    public function toQueryUpdate(){
        $prm = "";
        foreach ($this->sets as $k => $v){
            if(!empty($prm)) { $prm .= ","; }
            $prm .= self::BQ($k)."=".$v;
        }
        $sql = "UPDATE ".$this->table;
        foreach($this->joins as $j){
            $sql .= $j;
        }
        $sql .= " SET ".$prm;
        $where = $this->gWS();

        return self::mkQ($sql.$where, [$this->set_binds, $this->where_binds, $this->usr_binds]);
    }
    
    /**
     * insert用 作成クエリを取得
     * @return array ["sql"=>string, "stmt"=>array]
     */
    public function toQueryInsert(){
        $prm = ""; $fld = "";
        foreach ($this->sets as $k => $v){
            if(!empty($prm)) { $prm .= ","; $fld .= ","; }
            $prm .= $v; $fld .= self::BQ($k);
        }
        $sql = "INSERT INTO ".$this->table."(".$fld.") VALUES (".$prm.")";

        return self::mkQ($sql, [$this->set_binds, $this->usr_binds]);
    }
    
    /**
     * insert update用 作成クエリを取得 (MySQL用)
     * @param string $keyFields 取得フィールド名
     * @return array ["sql"=>string, "stmt"=>array]
     */
    public function toQueryInsertUpdate(... $keyFields){
        $r = $this->toQueryInsert();
        $sql = $r[self::$_QRY_SQL];
        $bind = $r[self::$_QRY_BIND];
        $fld = "";
        foreach ($this->sets as $k => $v){
            if(empty($keyFields) || !in_array($k, $keyFields)){
                if(!empty($fld)) { $fld.=","; }
                $f = self::BQ($k);
                $fld .= $f."=VALUES(".$f.")";
            }
        }
        return self::mkQ($sql." ON DUPLICATE KEY UPDATE ".$fld, [$bind]);
    }
    
    //privates func
    private static function mkQ($sql, array $bind_arrays){
        $b = array();
        foreach($bind_arrays as $bx){
            foreach($bx as $k => $v){ $b[$k] = $v; }
        }
        return [self::$_QRY_SQL => $sql, self::$_QRY_BIND => $b];
    }    
    private static function BQ($f, $bracket = false){
        if($f === null || $f === ""){ return ""; }
        if(is_array($f)){
            $n = "";
            foreach($f as $v){
                if($f === null || $f === "") { continue; }
                if(!empty($n)){ $n .= ","; }
                $n .= self::BQ($v);
            }
            return $n;
        }
        if(preg_match('/[\(\)\+\-\*\/\.\s =,`]+/', $f)){
            if($bracket){ return "(".$f.")"; }
            return $f;
        }
        if(DB_LOWER_CASE_NAME){
            return '`'.mb_strtolower($f).'`';
        }
        return '`'.$f.'`';
    }
    private static function XBQ($f, $prefix){
        $r = self::BQ($f, false);
        if(empty($r)) { return ""; }
        return $prefix.$r;
    }
    private function gFS($f){
        if(empty($f) || empty($this->fldQuery)){ return self::BQ($f);}
        if(!isset($this->fldQuery[$f])){ return self::BQ($f); }
        return "(".$this->fldQuery[$f].") AS ".self::BQ($f);
    }
    private function gWS(){
        if(empty($this->wheres)){ return ""; }
        $r = "";
        foreach ($this->wheres as $e){
            if(!empty($r)) { $r.= " "; }
            $r.=$e;
        }
        return " WHERE ".$r;  
    }
    private function w2r($query, array $binds = null){
        $this->wheres[] = $query;
        if(!empty($binds)){
            foreach($binds as $k => $v){ $this->bind($k, $v); }
        }
        $this->_f_ro = true;
        return $this;
    }
    private function n2n($v, $nullVal = null){if(is_numeric($v)){ return $v; } return $nullVal; }
    private function ifw($raw){ if($this->_f_ro){ $this->wheres[] = $raw; }}
    private function a2w($argsNum, $fields, $arg1, $arg2){
        $this->wheres[] = self::BQ($fields);
        if($argsNum === 2){
            if(is_array($arg1)){
                $this->wheres[] = "IN";
            }else{
                if($arg1 === NULL){
                    $this->wheres[] = "IS";
                }else{
                    $this->wheres[] = "=";
                }
            }
            $val = $arg1;
        }else{ 
            $this->wheres[] = $arg1;
            $val = $arg2;
        }
        $this->a2wst($val);
        $this->_f_ro = true;
        return $this;
    }
    private function a2wst($val){
        if(is_array($val)){
            $ins = "";
            foreach ($val as $v){ 
                if(!empty($ins)){ $ins.=",";}
                if($v === null){
                    $ins.="NULL";;
                }else{
                    $ins .= $this->_bind_w($v);
                }
            }
            $this->wheres[] = "(".$ins.")";
        }else{
            if($val === null){
                $this->wheres[] = " NULL ";
            }else{
                $this->wheres[] = $this->_bind_w($val);
            }
        }
    }
    private function wls(array $fields, array $keywords,  $compare = "AND",  $pre_hit = true,  $aftr_hit = true){
        $kw = array();
        foreach ($keywords as $v){
            $w = trim($v);
            if(empty($w)){ continue;}
            $kw[] = $w;
        }
        if(empty($kw)){ return $this; }
        
        $cnt1 = 0;
        $this->wheres[] = "(";
        foreach ($kw as $v){
            $like = ($pre_hit ? "%" : "").$v.($aftr_hit ? "%" : "");
            if($cnt1 > 0){ $this->wheres[] = $compare; }
            $this->wheres[] = "(";
            $this->_f_ro = false;
            foreach ($fields as $f){
                $this->ors(self::BQ($f), "LIKE", $like);
            }
            $this->wheres[] = ")";
            $cnt1 += 1;
        }
        $this->wheres[] = ")";
        
        $this->_f_ro = true;
        return $this;
    }
    private function wex(DbQuery $query, $whereRaw = null){
        if($whereRaw !== null){ $query->whereRaw($whereRaw); }
        $q = $query->toQuerySelect("NULL AS x");
        $this->wheres[] = "EXISTS(".$q[self::$_QRY_SQL].")";
        foreach($q[self::$_QRY_BIND] as $val){ $this->where_binds[] = $val; }
        $this->_f_ro = true;
    }

    private function _bind($val, array &$append_arr){
        $k= self::$_BIND_PFX.$this->_b_idx;
        ++$this->_b_idx;
        $append_arr[$k] = $val;
        return ":".$k." ";
    }
    private function _bind_w($val){
        return $this->_bind($val, $this->where_binds);
    }
    private function _bind_set($val){
        return $this->_bind($val, $this->set_binds);
    }
}
?>