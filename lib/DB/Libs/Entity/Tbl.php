<?php
namespace DB\Libs\Entity;
class Tbl extends _ABS_Entity{
    public static function TABLE(){ return "tbl"; }
    public static function CREATE_TABLE_SQL(){
        return self::__CR_TB([
            "sch_id" => "INTEGER NOT NULL",
            "tbl_id" => "INTEGER PRIMARY KEY AUTOINCREMENT",

            "phy_name" => "TEXT",
            "log_name" => "TEXT",
            "ent_name" => "TEXT",

            "is_view" => "INTEGER DEFAULT 0",
            "view_sql" => "TEXT",
        ]);
    }
    use __Tbl;
    public function getId(){ return $this->tbl_id; }
}
trait __Tbl{
    public $sch_id;
    public $tbl_id;

    public $phy_name;
    public $log_name;
    public $ent_name;
    //
    public $is_view;
    public $view_sql;
    //
    public $collation;
    public $comment;
    public $updated_at;
}