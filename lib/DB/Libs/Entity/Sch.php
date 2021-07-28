<?php
namespace DB\Libs\Entity;
class Sch extends _ABS_Entity{
    public static function TABLE(){ return "sch"; }
    public static function CREATE_TABLE_SQL(){
        return self::__CR_TB([
            "sch_id" => "INTEGER PRIMARY KEY AUTOINCREMENT",

            "phy_name" => "TEXT",
            "log_name" => "TEXT",

            "db_type" => "TEXT",
            "engine" => "TEXT",
        ]);
    }
    use __Sch;
    public function getId(){ return $this->sch_id; }
}
trait __Sch{
    public $sch_id;

    public $phy_name;
    public $log_name;
    //
    public $db_type;
    public $engine;
    //
    public $collation;
    public $comment;
    public $updated_at;
}