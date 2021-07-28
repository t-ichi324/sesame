<?php
class CsvReader{
    private $fi;
    public function __construct($file) {
        $this->fi = new FileInfo($file);
    }
    public function getFileInfo(){
        $f = $this->fi;
        $f instanceof FileInfo;
        return $f;
    }
    
    public function isExists(){
        return $this->fi->exists();
    }
    
    public function read($callback = null){
        if(!$this->fi->exists()){ return array(); }
        $f = new SplFileObject($this->fi->fullName());
        $f->setFlags(SplFileObject::READ_CSV);
        $ret = array();
        $lineOf = 0;
        if($callback === null){
            foreach($f as $row){
                $lineOf++;
                $r = $row;
                if(!empty($r)){ $ret[] = $r; }
            }
        }else{
            foreach($f as $line){
                $lineOf++;
                $r = $callback($row, $lineOf);
                if(!empty($r)){ $ret[] = $r; }
            }
        }
        return $ret;
    }
}