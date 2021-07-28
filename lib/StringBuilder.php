<?php
class StringBuilder{
    private static $lb = "\n";
    private $buff = array();
    public function clear(){ $this->buff = array(); return $this;}
    public function toString(){ $r = StringUtil::__EMPTY; foreach($this->buff as $s){ $r.=$s; } return $r; }
    public function count(){ return count($this->buff); }
    public function isEmpty(){ if($this->count() == 0){return true;} foreach($this->buff as $s){ if(StringUtil::isNotEmpty($s)){ return false; }} return true; }
    public function isNotEmpty(){ return !$this->isEmpty(); }
    public function append(...$str){ if(!empty($str)){ $this->st($str); } return $this; }
    public function appendLine(...$str){ if(!empty($str)){ $this->append(...$str); } $this->append(self::$lb); return $this;}
    //public function insert($str, $index = 0){ $this->append($str, $index);  return $this;}
    
    private function st($v){
        if(StringUtil::isEmpty($v)){return;}
        if(is_array($v)){
            foreach($v as $s){ $this->st($s); }
        }elseif($v instanceof StringBuilder){
            $this->st($v->buff);
        }else{
            $this->buff[] = StringUtil::toString($v);
        }
    }
}
?>