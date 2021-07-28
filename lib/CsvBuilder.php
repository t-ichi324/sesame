<?php
class CsvBuilder{
    private static $BOM = "\xEF\xBB\xBF";
    private $fi;
    private $ub = true;
    private $enc = "UTF-8";
    
    public function __construct() {
        $this->fi = new FileInfo(Path::download_tmpfile());
    }
    
    public function setBom(){$this->ub = true;}
    public function unsetBom(){$this->ub = false;}
    public function setEncode($encodeing){ $this->enc = $encodeing; }

    public function getFileInfo(){
        $f = $this->fi;
        $f instanceof FileInfo;
        return $f;
    }
    
    public function setArray(array $data){
        $fi = $this->getFileInfo();
        $fp = fopen($fi->fullName(), 'w');
        if($this->ub){ fwrite($fp, self::$BOM); }
        if(!empty($data)){
            foreach ($data as $line){
                $line = str_replace("\n"," ", str_replace("\n", " ", $line));
                if(is_array($line)){
                    fputcsv($fp, $line);
                }else{
                    fputcsv($fp, $data);
                    break;
                }
            }
        }
        fclose($fp);
    }
    public function appendArray(array $data){
        $fi = $this->getFileInfo();
        if(!$fi->exists()){
            $this->setArray($data);
            return;
        }
        $fp = fopen($fi->fullName(), 'a');
        if(!empty($data)){
            foreach ($data as $line){
                $line = str_replace("\n"," ", str_replace("\n", " ", $line));
                if(is_array($line)){
                    fputcsv($fp, $line);
                }else{
                    fputcsv($fp, $data);
                    break;
                }
            }
        }
        fclose($fp);
    }
    
    public function ResponseDonwload($dlname = null){
        $fi = $this->getFileInfo();
        if($fi->exists()){
            return Response::download($fi->fullName(), $dlname);
        }else{
            return Response::notFound();
        }
    }
}