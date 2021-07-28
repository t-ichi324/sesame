<?php
namespace DB\Libs\Entity;
class Fld extends _ABS_Entity{
    public static function TABLE(){ return "fld"; }
    public static function CREATE_TABLE_SQL(){
        return self::__CR_TB([
            "sch_id" => "INTEGER NOT NULL",
            "tbl_id" => "INTEGER NOT NULL",
            "fld_id" => "INTEGER NOT NULL",

            "phy_name" => "TEXT",
            "log_name" => "TEXT",

            "data_type" => "TEXT",
            "data_size" => "INTEGER",
            "is_pk" => "INTEGER DEFAULT 0",
            "is_nn" => "INTEGER DEFAULT 0",
            "is_bin" => "INTEGER DEFAULT 0",
            "is_un" => "INTEGER DEFAULT 0",
            "is_zf" => "INTEGER DEFAULT 0",
            "is_uq" => "INTEGER DEFAULT 0",
            "is_ix" => "INTEGER DEFAULT 0",
            "is_ai" => "INTEGER DEFAULT 0",
            "is_xi" => "INTEGER DEFAULT 0",
            "is_xu" => "INTEGER DEFAULT 0",
            "def" => "TEXT",
        ]);
    }
    public static function getCsvLineHeader(){
        $h = [  "phy_name","log_name","data_type","data_size",
                "is_pk","is_nn","is_bin","is_un","is_zf","is_uq","is_ix","is_ai",
                "def",
                "collation","comment"];
        return $h;
    }
    use __Fld;
    public function getId(){ return $this->fld_id; }

    public function getCsvLine(){
        return $this->toArray("sch_id","tbl_id","fld_id", "updated_at");
    }
    public function setCsvLine(array $data){
        $this->phy_name = $data[0];
        $this->log_name = $data[1];
        $this->data_type = $data[2];
        $this->data_size = $data[3];
        $this->is_pk = $data[4];
        $this->is_nn = $data[5];
        $this->is_bin = $data[6];
        $this->is_un = $data[7];
        $this->is_zf = $data[8];
        $this->is_uq = $data[9];
        $this->is_ix = $data[10];
        $this->is_ai = $data[11];
        $this->is_xi = $data[12];
        $this->is_xu = $data[13];
        $this->def = $data[14];
        $this->collation = $data[15];
        $this->comment = $data[16];
    }
}
trait __Fld{
    public $sch_id;
    public $tbl_id;
    public $fld_id;

    public $phy_name;
    public $log_name;
    //
    public $data_type;
    public $data_size;
    public $is_pk; //PRIMARY KEY
    public $is_nn; //not NULL
    public $is_bin; //binary
    public $is_un; //unsigned
    public $is_zf; //zero fill
    public $is_uq; //unique
    public $is_ix; //index
    public $is_ai; //auto increment
    public $is_xi; //xml insert(false)
    public $is_xu; //xml update(false)
    public $def;
    //
    public $collation;
    public $comment;
    public $updated_at;
}