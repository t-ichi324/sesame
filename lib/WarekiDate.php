<?php
class WarekiDate  {
    const ERA_MAP = [
        "R"=>["begin"=>"2019-05-01", "name"=>["jp"=>"令和", "j"=>"令", "e"=>"R", "en"=>"Reiwa"]],
        "H"=>["begin"=>"1989-01-08", "name"=>["jp"=>"平成", "j"=>"平", "e"=>"H", "en"=>"Heisei"]],
        "S"=>["begin"=>"1926-12-25", "name"=>["jp"=>"昭和", "j"=>"昭", "e"=>"S", "en"=>"Showa"]],
        "T"=>["begin"=>"1912-07-30", "name"=>["jp"=>"大正", "j"=>"大", "e"=>"T", "en"=>"Taisho"]],
        "M"=>["begin"=>"1868-10-23", "name"=>["jp"=>"明治", "j"=>"明", "e"=>"M", "en"=>"Meiji"]],
    ];
    private static function getJpWeek($num){
        $jw = array("日", "月", "火", "水", "木", "金", "土");
        if($num >= 0 && $num < 7){ return $jw[$num]; }
        return null;
    }
    public static function ERA_SHFT($era_key, $shift){
        $keys = array_keys(self::ERA_MAP);
        $i = array_search(strtoupper($era_key), $keys) - $shift;
        if(isset($keys[$i])){ return $keys[$i]; }
        return null;
    }
    public static function ERA_NAME($era_key, $name_format="jp"){
        $map = self::ERA_MAP;
        $ukey = strtoupper($era_key);
        if(isset($map[$ukey])){
            $name = $map[$ukey]["name"];
            if(isset($name[$name_format])){ return $name[$name_format]; }
            return $name["jp"];
        }
        return null;
    }
    public static function ERA_BEGIN_DATE($era_key, $format = "Y-m-d"){
        $map = self::ERA_MAP;
        $ukey = strtoupper($era_key);
        if(isset($map[$ukey])){ return date($format, strtotime($map[$ukey]["begin"]));}
        return null;
    }
    public static function ERA_LAST_DATE($era_key, $format = "Y-m-d"){
        $key = self::ERA_SHFT($era_key, +1);
        if($key === null) { return null; }
        $date = self::ERA_BEGIN_DATE($key);
        return date($format, strtotime($date." -1 day"));
    }
    public static function FROM_WAREKI($era_key, $wareki_year, $month = 1, $day = 1){
        $begin = self::ERA_BEGIN_DATE($era_key);
        $at = (int)date("Y", strtotime($begin)) - 1;
        $seireki_year = $at + $wareki_year;
        $time = $seireki_year."-".$month."-".$day;
        return new WarekiDate($time);
    }
    public static function FROM_SEIREKI($seireki_year, $month = 1, $day = 1){
        $time = $seireki_year."-".$month."-".$day;
        return new WarekiDate($time);
    }
    
    private $s_time;
    private $s_year = 0;
    private $w_year = 0;
    private $e_key = null;
    private $x_month = 1;
    private $x_day = 1;
    private $is_kaigen = false;
    
    public function __construct($date = null) {
        if($date === null){
            $this->_initalize(date("Y-m-d"));
        }else{
            $this->_initalize($date);
        }
    }
    private function _initalize($date){
        $this->s_time = strtotime($date);
        $this->s_year = (int)date("Y",$this->s_time);
        $this->x_month = (int)date("m",$this->s_time);
        $this->x_day = (int)date("d",$this->s_time);
        $this->is_kaigen = false;
        foreach (self::ERA_MAP as $gengo_key => $era){
            $era_time = strtotime($era["begin"]);
            $era_year = (int)date("Y",$era_time);
            if($this->s_year == $era_year){ $this->is_kaigen = true;}
            if($this->s_time >= $era_time){
                $era_year -= 1;
                $this->e_key = $gengo_key;
                $this->w_year = (int)($this->s_year - $era_year);
                //$this->is_kaigen = ($this->s_year === $era_year);
                return;
            }
        }
        $era_time = strtotime($era["begin"]);
        $era_year = (int)date("Y",$era_time);
        $this->e_key = $gengo_key;
        $this->w_year = (int)($this->s_year - $era_year);
    }
    public function toDate($format = "Y-m-d"){ return date($format, $this->s_time); }
    public function era_key(){ return $this->e_key; }
    public function gengo($era_name_format = "jp"){ return self::ERA_NAME($this->e_key, $era_name_format); }
    public function year(){ return $this->s_year; }
    public function year_jp($first_year = "元"){ return ($this->w_year == 1) ? $first_year : $this->w_year; }
    public function month(){ return $this->x_month; }
    public function day(){ return $this->x_day; }
    
    public function week_num(){ return (int)date("w",$this->s_time); }
    public function week_jp(){ return self::getJpWeek($this->week_num()); }
    
    public function addYear($num){
        $s = $this->toDate()." ";
        if($num >= 0){ $s.="+"; }
        $s.= $num." year";
        $this->_initalize($s);
    }
    public function addMonth($num){
        $s = $this->toDate()." ";
        if($num >= 0){ $s.="+"; }
        $s.= $num." month";
        $this->_initalize($s);
    }
    public function addDay($num){
        $s = $this->toDate()." ";
        if($num >= 0){ $s.="+"; }
        $s.= $num." day";
        $this->_initalize($s);
    }
    
    public function isKaigenYear(){ return $this->is_kaigen; }
    public function toString($era_name_format = "jp"){
        return $this->gengo($era_name_format).$this->year_jp("元")."年".$this->x_month."月".$this->x_day."日";
    }
}