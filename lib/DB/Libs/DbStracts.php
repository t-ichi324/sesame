<?php
namespace DB\Libs;
class DbStracts {
    private $q;
    public function __construct(\DbQuery $q) {
        $this->q = $q;
        $this->q->_log(-1);
    }

    public function beginTran(){ $this->q->beginTran(); }
    public function commit(){ $this->q->commit(); }
    public function rollback(){ $this->q->rollback(); }

    public function setup(){
        $r = "";
        if(!$this->q->table("sqlite_master")->where("tbl_name", Entity\Sch::TABLE())->isExists()){
            $this->q->executeRaw(Entity\Sch::CREATE_TABLE_SQL());
            $r.= Entity\Sch::TABLE()."\n";
        }
        if(!$this->q->table("sqlite_master")->where("tbl_name", Entity\Tbl::TABLE())->isExists()){
            $this->q->executeRaw(Entity\Tbl::CREATE_TABLE_SQL());
            $r.= Entity\Tbl::TABLE()."\n";
        }
        if(!$this->q->table("sqlite_master")->where("tbl_name", Entity\Fld::TABLE())->isExists()){
            $this->q->executeRaw(Entity\Fld::CREATE_TABLE_SQL());
            $r.= Entity\Fld::TABLE()."\n";
        }
        return $r;
    }
    private function insertUpdate($idname, $tbl, $e){
        $q = $this->q;
        $e->updated_at = now();
        $id = $e->getId();
        if(\StringUtil::isNotEmpty($id) && $q->table($tbl)->where($idname, $id)->isExists()){
            $q->table($tbl)->setArray($e->toArray($idname))->where($idname, $id)->update();
            return $id;
        }else{
            $q->table($tbl)->setArray($e->toArray($idname))->insert();
            return $q->lastInsertId();
        }
    }
    private function insertFld($e){
        $q = $this->q;
        $e->updated_at = now();
        $q->table(Entity\Fld::TABLE())->setArray($e->toArray());
        $q->insert();
    }

    public function q_sch(){ return $this->q->table_Entity(Entity\Sch::class)->ordrBy("phy_name"); }
    public function get_schs(){ return $this->q_sch()->select(); }
    public function get_sch($sch_id){ return $this->q_sch()->where("sch_id", $sch_id)->selectFirst(); }
    public function get_sch_name($sch_id){ return $this->q_sch()->where("sch_id", $sch_id)->getValue("phy_name"); }
    public function del_sch($sch_id){
        $this->q_sch()->where("sch_id", $sch_id)->delete();
        $this->q_tbl()->where("sch_id", $sch_id)->delete();
        $this->q_fld()->where("sch_id", $sch_id)->delete();
    }
    public function set_sch(Entity\Sch $e){ return $this->insertUpdate("sch_id", Entity\Sch::TABLE(), $e); }

    public function q_tbl(){ return $this->q->table_Entity(Entity\Tbl::class)->ordrBy("phy_name"); }
    public function get_tbls($sch_id){ return $this->q_tbl()->where("sch_id", $sch_id)->select(); }
    public function get_tbl($tbl_id){ return $this->q_tbl()->where("tbl_id", $tbl_id)->selectFirst(); }
    public function get_tbl_name($tbl_id){ return $this->q_tbl()->where("tbl_id", $tbl_id)->getValue("phy_name"); }
    public function del_tbl($tbl_id){
        $this->q_tbl()->where("tbl_id", $tbl_id)->delete();
        $this->q_fld()->where("tbl_id", $tbl_id)->delete();
    }
    public function set_tbl(Entity\Tbl $e){ return $this->insertUpdate("tbl_id", Entity\Tbl::TABLE(), $e); }

    public function q_fld(){ return $this->q->table_Entity(Entity\Fld::class)->ordrBy("fld_id"); }
    public function get_flds($tbl_id){ return $this->q_fld()->where("tbl_id", $tbl_id)->select(); }
    public function get_fld($tbl_id, $fld_id){ return $this->q_fld()->where("fld_id", $fld_id)->ands("tbl_id", $tbl_id)->selectFirst(); }
    public function del_fld($tbl_id, $fld_id){ $this->q_fld()->where("fld_id", $fld_id)->ands("tbl_id", $tbl_id)->delete(); }
    public function set_fld(Entity\Fld $e){ return $this->insertFld($e); }

    public function del_tbl_fld($tbl_id){ $this->q_fld()->where("tbl_id", $tbl_id)->delete(); }

    //- GEN ----------------------------------------------------------------------

    public function export_csv($tbl_id){
        $tbl = $this->get_tbl($tbl_id);
        $flds = $this->get_flds($tbl_id);

        $tbl instanceof Entity\Tbl;
        $cls_name = \StringUtil::defaultVal($tbl->phy_name);

        $c = new \CsvBuilder();
        $c->appendArray(Entity\Fld::getCsvLineHeader());

        foreach($flds as $fld){
            $fld instanceof Entity\Fld;
            $c->appendArray($fld->getCsvLine());
        }
        return array("name"=> $cls_name, "file"=>$c->getFileInfo()->fullName());
    }
    public function import_csv($tbl_id, $csv){
        $tbl = $this->get_tbl($tbl_id);
        $tbl instanceof Entity\Tbl;
        $c = new \CsvReader($csv);
        if(!$c->isExists()){ return false; }
        $rows = $c->read();
        $line = 0;
        $this->q->beginTran();
        try{
            $this->del_tbl_fld($tbl->tbl_id);
            foreach ($rows as $r){
                if(count($r) < 14){ continue; }
                if($line > 0){
                    $e = new \DB\Libs\Entity\Fld();
                    $e->setCsvLine($r);
                    $e->sch_id = $tbl->sch_id;
                    $e->tbl_id = $tbl->tbl_id;
                    $e->fld_id = $line;
                    $this->insertFld($e);
                }
                $line ++;
            }
            $this->q->commit();
        } catch (Exception $ex) {
            $this->q->rollback();
            return false;
        }
        return true;
    }
    public function export_json($tbl_id){
        $tbl = $this->get_tbl($tbl_id);
        $flds = $this->get_flds($tbl_id);

        $tbl instanceof Entity\Tbl;
        $cls_name = \StringUtil::defaultVal($tbl->phy_name);

        $j = array();
        $tbl->sch_id = 0;
        $j["tbl"] = $tbl;
        $j["flds"] = $flds;

        $file = \Path::download_tmpfile();
        file_put_contents($file, json_encode($j));
        return array("name"=> $cls_name, "file"=>$file);
    }

    public function import_json($sch_id, $json){
        $f = new \FileInfo($json);
        if(!$f->exists()){ return false; }
        $j = json_decode($f->read(), true);

        $tbl = new Entity\Tbl($j["tbl"]);
        $tbl->sch_id = $sch_id;
        $tbl->tbl_id = null;

        $this->q->beginTran();
        try{
            $tbl->tbl_id = $this->set_tbl($tbl);
            $rows = $j["flds"];
            foreach ($rows as $r){
                $e = new \DB\Libs\Entity\Fld($r);
                $e->tbl_id = $tbl->tbl_id;
                $this->insertFld($e);
            }
            $this->q->commit();
        } catch (Exception $ex) {
            $this->q->rollback();
            return false;
        }
        return true;
    }

    public function gen_entity_php($tbl_id, $opt_tbl_case = "low", $opt_fld_case = "low", $add_sch = false){
        $tbl = $this->get_tbl($tbl_id);
        $flds = $this->get_flds($tbl_id);

        $tbl instanceof Entity\Tbl;

        //$sch_name = ($add_sch) ? $this->get_schs($tbl->sch_id) : "";
        $tbl_name_raw = self::nameToCase($tbl->phy_name, $opt_tbl_case);
        $tbl_name = "`".$tbl_name_raw."`";
        $cls_name = \StringUtil::defaultVal($tbl->ent_name, $tbl->phy_name);
        $trait_name = "__".$cls_name;

        $INDENT = "    ";

        $sb = new \StringBuilder();
        $sb->appendLine("<?php namespace Entity;");
        $sb->appendLine("/**");
        $sb->appendLine(" * <b>entity</b> @ ", $tbl_name_raw, "<br>");
        $this->addEntityDoc($sb, $tbl->log_name, "logical");
        $this->addEntityDoc($sb, $tbl->phy_name, "physical");
        $this->addEntityDoc($sb, $tbl->comment, "comment");
        $sb->appendLine("**/");
        $sb->appendLine("class ", $cls_name, " extends \\IEntity{");
        $sb->appendLine($INDENT, "use ", $trait_name, ";");
        $sb->appendLine($INDENT, '/** @return string "', $tbl_name_raw, '" (<b>table</b> or <b>view</b> name). */');
        $sb->appendLine($INDENT, "public static function TABLE(){");
        $sb->appendLine($INDENT,$INDENT,  'return "', $tbl_name_raw ,'";');
        $sb->appendLine($INDENT, "}");
        $sb->appendLine($INDENT, '/** @return string "', $tbl_name_raw, '" (<b>xml-sql</b> file name). */');
        $sb->appendLine($INDENT, "public static function XML(){");
        $sb->appendLine($INDENT,$INDENT,  'return "', $tbl_name_raw ,'";');
        $sb->appendLine($INDENT, "}");
        $sb->appendLine("}");
        $sb->appendLine();

        $sb->appendLine("/**");
        $sb->appendLine(" * <b>trait</b> @ ", $tbl_name_raw, "<br>");
        $this->addEntityDoc($sb, $tbl->log_name, "logical");
        $this->addEntityDoc($sb, $tbl->phy_name, "physical");
        $this->addEntityDoc($sb, $tbl->comment, "comment");
        $sb->appendLine("**/");
        $sb->appendLine("trait ", $trait_name, "{");
        foreach($flds as $fld){
            $fld instanceof Entity\Fld;
            if(\StringUtil::isEmpty($fld->phy_name)){ continue; }

            $sb->appendLine($INDENT, "/**");
            $this->addEntityDoc($sb, $fld->log_name, "logical", $INDENT);
            $this->addEntityDoc($sb, $fld->phy_name, "physical", $INDENT);
            $this->addEntityDoc($sb, $fld->data_type, "type", $INDENT);
            $this->addEntityDoc($sb, $fld->data_size, "size", $INDENT);
            $this->addEntityDoc($sb, $fld->def, "def", $INDENT);
            if(\Flags::isON($fld->is_ai)){
                $this->addEntityDocFlag($sb, $fld->is_ai, "primary(AI)", $INDENT);
            }else{
                $this->addEntityDocFlag($sb, $fld->is_pk, "primary", $INDENT);
            }
            $this->addEntityDocFlag($sb, $fld->is_nn, "notnull", $INDENT);
            $this->addEntityDocFlag($sb, $fld->is_uq, "unique", $INDENT);
            $this->addEntityDoc($sb, $fld->comment, "comment", $INDENT);
            $sb->appendLine($INDENT, "**/");
            $sb->appendLine($INDENT, "public $", $fld->phy_name, ";");
        }
        $sb->appendLine("}");
        $sb->appendLine("?>");
        return array("name"=> $cls_name, "data"=>$sb->toString(), "filename"=>$cls_name.".php");
    }

    public function gen_create_sql($tbl_id, $opt_tbl_case = "low", $opt_fld_case = "low", $add_sch = false){
        $tbl = $this->get_tbl($tbl_id);
        $flds = $this->get_flds($tbl_id);

        $tbl instanceof Entity\Tbl;

        $sch_name = ($add_sch) ? $this->get_schs($tbl->sch_id) : "";

        $INDENT = "    ";
        $DP_PHY = " PHISYCAL: ";
        $DP_LOG = " LOGICAL : ";
        $DP_CMT = " COMMENT : ";
        $DP_MOD = " # ";

        $tbl_name_raw = self::nameToCase($tbl->phy_name, $opt_tbl_case);
        $tbl_name = "`".$tbl_name_raw."`";

        $sb = new \StringBuilder();
        $sb->appendLine("/*-------------------------------");
        if($add_sch){
            $sb->appendLine("SCHEMA : ", $sch_name);
            $sch_name = "`".$sch_name."`.";
        }
        $sb->appendLine($DP_PHY, $tbl->phy_name);
        $sb->appendLine($DP_LOG, $tbl->log_name);
        $sb->appendLine($DP_CMT, $tbl->comment);
        $sb->appendLine("-------------------------------*/");

        if(!\Flags::isON($tbl->is_view)){
            $sb->appendLine("/* DORP TABLE */");
            $sb->appendLine("DROP TABLE  IF EXISTS ", $sch_name, $tbl_name, ";");
            $sb->appendLine();

            $sb->append("CREATE TABLE ", $sch_name, $tbl_name, "(");

            $pk = array();
            $ix = array();
            $uq = array();
            $cnt = 0;
            foreach($flds as $fld){
                $fld instanceof Entity\Fld;
                if(\StringUtil::isEmpty($fld->phy_name)){ continue; }
                if($cnt > 0){ $sb->append(","); };
                $sb->appendLine();

                $fld_name_raw = self::nameToCase($fld->phy_name, $opt_fld_case);
                $fld_name = "`".$fld_name_raw."`";
                $sb->append("  ")->append($fld_name);

                //Data Type / Size
                $size = $fld->data_size;
                $type = \StringUtil::upperCase($fld->data_type);
                if(\StringUtil::isEmpty($type)){ $type=" VARCHAR"; }
                if(\StringUtil::contains($type, "DOUBLE", "DATETIME", "DATE", "BOOLEAN")){
                    $size = "";
                }else{
                    if(\StringUtil::isEmpty($size)){
                        if( $type == "VARCHAR") { $size = "255"; }
                        elseif($type == "INT")  { $size = "11"; }
                    }
                }
                $sb->append(" ");
                $sb->append($type);
                if(\StringUtil::isNotEmpty($size)){ $sb->append("(",$size,")"); }

                //Primary / Index
                if(\Flags::isON($fld->is_pk)){ $pk[] = $fld_name_raw; }
                if(\Flags::isON($fld->is_ix)){ $ix[] = $fld_name_raw; }

                //BINARY
                if(\Flags::isON($fld->is_bin)) { $sb->append(" BINARY"); }

                //ZEROFILL
                if(\Flags::isON($fld->is_zf)) { $sb->append(" ZEROFILL"); }

                //Unsigned
                if(\Flags::isON($fld->is_un)){ $sb->append(" UNSIGNED"); }

                //Not Null
                if(\Flags::isON($fld->is_nn)){ $sb->append(" NOT NULL"); }

                //AutoInc
                if(\Flags::isON($fld->is_ai)){ $sb->append(" AUTO_INCREMENT"); }

                //DEFULAT
                if(\StringUtil::isNotEmpty($fld->def)){
                    $sb->append(" DEFAULT ");
                    if($type == "VARCHAR" || \StringUtil::contains($type, "TEXT")){
                        $sb->append("'", str_replace("'", "''", $fld->def), "'");
                    }else{
                        $sb->append($fld->def);
                    }
                }

                //Comment
                if(\StringUtil::isNotEmpty($fld->log_name) || \StringUtil::isNotEmpty($fld->comment)){
                    $sb->append(" COMMENT '");
                    $haslog = false;
                    if(\StringUtil::isNotEmpty($fld->log_name)){
                        $sb->append($fld->log_name);
                        $haslog = true;
                    }
                    if(\StringUtil::isNotEmpty($fld->comment)){
                        if($haslog){ $sb->append("\\n"); }
                        $sb->append(str_replace("'", "''", $fld->comment));
                    }
                    $sb->append("'");
                }
                $cnt ++;
            }
            $sb->appendLine();

            //Primary Index Uniq
            $sb2 = new \StringBuilder();
            self::addCreateIndex($sb2, $pk, "PRIMARY KEY");
            self::addCreateIndex($sb2, $uq, "UNIQUE INDEX", "uqidx_");
            self::addCreateIndex($sb2, $ix, "INDEX", "idx_");

            $sb->appendLine($sb2);

            $sb->appendLine(")");

            //collation (char)
            if(\StringUtil::isNotEmpty($tbl->collation)){
                $char = \StringUtil::split($tbl->collation, "_")[0];
                if(\StringUtil::isNotEmpty($char)){
                    $sb->appendLine("DEFAULT CHARSET=", $char);
                }
                $sb->appendLine("COLLATE=", $tbl->collation);
            }

            //Comment
            if(\StringUtil::isNotEmpty($tbl->log_name) || \StringUtil::isNotEmpty($tbl->comment)){
                $sb->append("COMMENT '");
                $haslog = false;
                if(\StringUtil::isNotEmpty($tbl->log_name)){
                    $sb->append($tbl->log_name);
                    $haslog = true;
                }
                if(\StringUtil::isNotEmpty($tbl->comment)){
                    if($haslog){ $sb->append("\\n"); }
                    $sb->append(str_replace("'", "''", $tbl->comment));
                }
                $sb->appendLine("'");
            }
        }else{
            $sb->appendLine("/* DORP VIEW */");
            $sb->appendLine("DROP VIEW IF EXISTS ", $sch_name, $tbl_name, ";");
            $sb->appendLine();

            $sb->appendLine("CREATE VIEW ", $sch_name, $tbl_name, " AS ");
            $sb->appendLine($tbl->view_sql);
        }
        $sb->append(";");

        if($tbl->is_view){
            $fpfx = "[VIEW]";
        }else{
            $fpfx = "[TABLE]";
        }
        
        return array("name"=> $tbl_name_raw, "data"=>$sb->toString(), "filename"=>$fpfx.$tbl_name_raw.".sql");
    }

    public function gen_sql_xml($tbl_id, $opt_tbl_case = "low", $opt_fld_case = "low", $add_sch = false){
        $tbl = $this->get_tbl($tbl_id);
        $flds = $this->get_flds($tbl_id);

        $tbl instanceof Entity\Tbl;

        $sch_name = ($add_sch) ? $this->dev->get_schs($tbl->sch_id)->phy_name : "";

        $INDENT = "    ";
        $TAG_NM = "sql";
        $ATTR_NM = "key";

        $tbl_name_raw = self::nameToCase($tbl->phy_name, $opt_tbl_case);
        $tbl_name = "`".$tbl_name_raw."`";

        $pk = array();
        foreach($flds as $fld){
            $fld instanceof Entity\Tbl;
            if(\Flags::isON($fld->is_pk)){ $pk[] = self::nameToCase($fld->phy_name, $opt_fld_case); }
        }

        $sb = new \StringBuilder();

        $sb->appendLine('<?xml version="1.0" encoding="UTF-8"?>');
        $sb->appendLine('<root>');

        // select
        $sb->appendLine($INDENT, '<sql key="select">');
        $sb->appendLine($INDENT,$INDENT, "SELECT * FROM ", $tbl_name);
        $sb->appendLine($INDENT,$INDENT, "{{where}} {{group}} {{order}} {{limit}}");
        $sb->appendLine($INDENT, '</sql>')->appendLine();

        // select
        if(!empty($pk)){
            $sb->appendLine($this->_xs_get($INDENT, "get", $tbl_name, $flds, $pk));
        }

        if(! \Flags::isON($tbl->is_view)){
            //update
            $sb->appendLine($this->_xs_upd($INDENT, "update", $tbl_name, $flds, $pk));

            //ins
            $sb->appendLine($this->_xs_ins($INDENT, "insert", $tbl_name, $flds, $pk));

            //save
            $sb->appendLine($this->_xs_ins($INDENT, "save", $tbl_name, $flds, $pk, true));
        }

        //end
        $sb->appendLine('</root>');

        return array("name"=> $tbl_name_raw, "data"=>$sb->toString(), "filename"=>$tbl_name_raw.".xml");
    }
    private function _xs_whr($INDENT, $pk){
        $sb = new \StringBuilder();
        if(!empty($pk)){
            $cnt = 0;
            $sb->appendLine($INDENT,$INDENT, "WHERE");
            foreach ($pk as $v){
                $op = "";
                if($cnt > 0){ $op = " AND "; }

                $sb->appendLine($INDENT,$INDENT,$INDENT,$op, "`", $v, "` = :", $v);
                $cnt++;
            }
        }
        return $sb->toString();
    }
    private function _xs_get($INDENT, $key, $tbl_name, $flds, $pk){
        $sb = new \StringBuilder();
        $param = array();
        $sb->appendLine($INDENT,$INDENT, "SELECT * FROM ", $tbl_name);
        $sb->append($this->_xs_whr($INDENT, $pk));
        foreach($pk as $v){
            if(!in_array($v, $param)){
                $param[] = $v;
            }
        }
        $tag = $INDENT.'<sql key="'.$key.'" param="'. \StringUtil::arrayToString($param, ",") .'">'."\n";
        $tag.= $sb->toString();
        $tag.= $INDENT."</sql>\n";
        return $tag;
    }
    private function _xs_upd($INDENT, $key, $tbl_name, $flds, $pk){
        $sb = new \StringBuilder();
        $param = array();
        $s_val = array();        
        $s_fld = array();
        $cnt = 0;
        foreach($flds as $fld){
            $fld instanceof Entity\Fld;
            if(\StringUtil::isEmpty($fld->phy_name)){ continue; }
            if(!\Flags::isON($fld->is_xu)) { continue; }

            $param[] = $fld->phy_name;
            $s_fld[] = $INDENT.$INDENT.$INDENT;
            if($cnt > 0) { $s_fld[] = ","; $s_val[] = ","; }    
            $s_fld[] = "`".$fld->phy_name."` = :".$fld->phy_name;
            $s_fld[] = "\n";
            ++$cnt;
        }
        $sb->appendLine($INDENT,$INDENT, "UPDATE ", $tbl_name, " SET ");
        $sb->append(\StringUtil::arrayToString($s_fld, ""));
        $sb->append($this->_xs_whr($INDENT, $pk));
        foreach($pk as $v){
            if(!in_array($v, $param)){
                $param[] = $v;
            }
        }
        $sb->appendLine($INDENT,$INDENT, "{{where}}");

        $tag = $INDENT.'<sql key="'.$key.'" param="'. \StringUtil::arrayToString($param, ",") .'">'."\n";
        $tag.= $sb->toString();
        $tag.= $INDENT."</sql>\n";
        return $tag;
    }
    private function _xs_ins($INDENT, $key, $tbl_name, $flds, $pk, $add_upd = false){
        $sb = new \StringBuilder();
        $param = array();
        $s_val = array();
        $s_fld = array();
        $cnt = 0;
        foreach($flds as $fld){
            $fld instanceof Entity\Fld;
            if(\StringUtil::isEmpty($fld->phy_name)){ continue; }
            if(!\Flags::isON($fld->is_xi)) { continue; }

            $param[] = $fld->phy_name;
            $s_fld[] = $INDENT.$INDENT.$INDENT;
            $s_val[] = $INDENT.$INDENT.$INDENT;
            if($cnt > 0) { $s_fld[] = ","; $s_val[] = ","; }
            $s_fld[] = "`".$fld->phy_name."`";
            $s_val[] = ":".$fld->phy_name."";
            $s_fld[] = "\n";
            $s_val[] = "\n";
            ++$cnt;
        }
        $sb->appendLine($INDENT,$INDENT, "INSERT INTO ", $tbl_name, "(");
        $sb->append(\StringUtil::arrayToString($s_fld, ""));
        $sb->appendLine($INDENT,$INDENT, ")");
        $sb->appendLine($INDENT,$INDENT,"VALUES(");
        $sb->append(\StringUtil::arrayToString($s_val, ""));
        $sb->appendLine($INDENT,$INDENT, ")");

        if($add_upd){
            $sb->appendLine($INDENT,$INDENT, "ON DUPLICATE KEY UPDATE");
            $cnt = 0;
            foreach($flds as $fld){
                $fld instanceof Entity\Fld;
                if(!\Flags::isON($fld->is_xu)) { continue; }
                
                $param[] = $fld->phy_name;
                $sb->append($INDENT, $INDENT, $INDENT); 
                if($cnt > 0){ $sb->append(","); }
                $sb->append("`", $fld->phy_name, "` = VALUES(`", $fld->phy_name, "`)");
                $sb->appendLine();
                $cnt ++;
            }
        }

        $tag = $INDENT.'<sql key="'.$key.'" param="'. \StringUtil::arrayToString($param, ",") .'">'."\n";
        $tag.= $sb->toString();
        $tag.= $INDENT."</sql>\n";
        return $tag;
    }

    private static function nameToCase($name, $case){
        $n = "";
        if(\StringUtil::contains($case, "snake")){
            $n = \StringUtil::snakeCase($name);
        }

        if(\StringUtil::contains($case, "camel")){
            $n = \StringUtil::camelCase($name, true);
        }

        if(\StringUtil::contains($case, "low")){
            $n = \StringUtil::lowerCase($name);
        }
        if(\StringUtil::contains($case, "up")){
            $n = \StringUtil::upperCase($name);
        }
        return $n;
    }
    private static function addEntityDoc(\StringBuilder $sb, $val, $dp = null,  $indent = null){
        if(\StringUtil::isEmpty($val)){ return false; }
        if($dp !== null){
            $sb->appendLine($indent," * - <b>", $dp ," : </b>", $val);
        }else{
            $sb->appendLine($indent," * ", $val ,"<br/>");
        }
        return true;
    }
    private static function addEntityDocFlag(\StringBuilder $sb, $flag, $dp, $indent = null){
        if(\Flags::isON($flag)){
            $sb->appendLine($indent," * - <b>", $dp ,"</b>");
            return true;
        }
        return false;
    }
    private static function addCreateIndex(\StringBuilder $sb, $flds, $defName, $index_prefix = null){
        $cnt = 0;
        if(empty($flds)){ return $cnt; }
        $sb->append(",");
        if($index_prefix === null){ $sb->append("  ", $defName, " ("); }
        foreach($flds as $f){
            if($cnt > 0){ $sb->append(",");}
            if($index_prefix !== null){
                $sb->append(" ", $defName, " `" , $index_prefix, $f ,"` (");
                $sb->append("`", $f ,"`)");
            }else{
                $sb->append("`", $f, "`");
            }
            $cnt++;
        }
        if($index_prefix === null){
            $sb->appendLine(")");
        }
        return $cnt;
    }
}