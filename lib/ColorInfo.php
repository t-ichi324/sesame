<?php
class ColorInfo {
    private $is_error = true;
    private $r = 0;
    private $g = 0;
    private $b = 0;
    
    public function __construct($code_or_r = null, $g = null, $b = null) {
        if(func_num_args() == 1){
            $this->setCode($code_or_r);
        }elseif(func_num_args() == 3){
            $this->setRGB($code_or_r, $g, $b);
        }
    }

    public function _clone(){
        $n = new ColorInfo();
        $n->r = $this->r;
        $n->g = $this->g;
        $n->b = $this->b;
        $n->is_error = $this->is_error;
        return $n;
    }

    public function clear(){
        $this->r = 0;
        $this->g = 0;
        $this->b = 0;
        $this->is_error = false;
        return $this;
    }
    
    public function isError(){
        return $this->is_error;
    }
    
    /** 反転色 */
    public function adjust_inverted(){
        $this->setR(255 - $this->getR());
        $this->setG(255 - $this->getG());
        $this->setB(255 - $this->getB());
        return $this;
    }
    /** 補色 */
    public function adjust_complementary(){
        $base = $this->getComplementaryVal();
        $this->setR($base - $this->getR());
        $this->setG($base - $this->getG());
        $this->setB($base - $this->getB());
        return $this;
    }
    
    public function adjust_brightnesss($v){
        $hsv = $this->getHsv();
        $this->setHsv($hsv["h"], $hsv["s"], $v);
    }
    
    public function getComplementaryVal(){
        $max = NumUtil::max($this->getR(), $this->getG(), $this->getB());
        $min = NumUtil::min($this->getR(), $this->getG(), $this->getB());
        return ($max + $min);
    }
    
    public function getR(){ return $this->r; }
    public function getG(){ return $this->g; }
    public function getB(){ return $this->b; }
    public function getHexR(){ return $this->__dec_to_hex($this->r); }
    public function getHexG(){ return $this->__dec_to_hex($this->g); }
    public function getHexB(){ return $this->__dec_to_hex($this->b); }
    public function getHsv($round = false){ 
        $r = $this->__rgb_to_hsv($this->r, $this->g, $this->b);
        if($round){
            $r["h"] = NumUtil::round($r["h"]);
            $r["s"] = NumUtil::round($r["s"]);
            $r["v"] = NumUtil::round($r["v"]);
        }
        return $r;
    }
    public function getCode($add_sharp = true){
        $code = $this->getHexR().$this->getHexG().$this->getHexB();
        if($add_sharp){
            return "#".$code;
        }else{
            return $code;
        }
    }
    public function getVBA(){
        return ($this->r) + ($this->g * 256) + ($this->b * 256 * 256);
    }
    
    public function setR($dec){ $this->r = $this->__dec_max_min($dec); return $this; }
    public function setG($dec){ $this->g = $this->__dec_max_min($dec); return $this; }
    public function setB($dec){ $this->b = $this->__dec_max_min($dec); return $this; }
    public function setHexR($hex){ $this->setR($this->__hex_to_dec($hex)); return $this; }
    public function setHexG($hex){ $this->setG($this->__hex_to_dec($hex)); return $this; }
    public function setHexB($hex){ $this->setB($this->__hex_to_dec($hex)); return $this; }
    public function setRGB($r, $g, $b){
        $this->setR($r);
        $this->setG($g);
        $this->setB($b);
        return $this;
    }
    public function setHsv($h, $s, $v){
        $this->clear();
        $rgb = $this->__hsv_to_rgb($h, $s, $v);
        $this->setR($rgb["r"]);
        $this->setG($rgb["g"]);
        $this->setB($rgb["b"]);
    }
    public function setVBA($vba_code){
        $this->clear();
    }
    public function setCode($color_code){
        $this->clear();
        $code = trim(trim($color_code, "#"));
        $len = StringUtil::length($code);
        
        if(!ctype_xdigit($code)){
            $this->is_error = true;
            return false;
        }
        
        if($len == 6){
            $hr = StringUtil::mid($code, 0, 2);
            $hg = StringUtil::mid($code, 2, 2);
            $hb = StringUtil::mid($code, 4, 2);
        }elseif($len == 3){
            $hr = StringUtil::mid($code, 0, 1);
            $hg = StringUtil::mid($code, 1, 1);
            $hb = StringUtil::mid($code, 2, 1);
            $hr.=$hr;
            $hg.=$hg;
            $hb.=$hb;
        }else{
            $this->is_error = true;
            return $this;
        }
        $this->r = $this->__hex_to_dec($hr);
        $this->g = $this->__hex_to_dec($hg);
        $this->b = $this->__hex_to_dec($hb);
        return $this;
    }
    
    
    public function add($add_dec){
        $i = NumUtil::toInt($add_dec);
        $this->r = $this->__dec_max_min($this->r + $i);
        $this->g = $this->__dec_max_min($this->g + $i);
        $this->b = $this->__dec_max_min($this->b + $i);
        return $this;
    }
    public function addR($add_dec){
        $i = NumUtil::toInt($add_dec);
        $this->r = $this->__dec_max_min($this->r + $i);
        return $this;
    }
    public function addG($add_dec){
        $i = NumUtil::toInt($add_dec);
        $this->g = $this->__dec_max_min($this->g + $i);
        return $this;
    }
    public function addB($add_dec){
        $i = NumUtil::toInt($add_dec);
        $this->b = $this->__dec_max_min($this->b + $i);
        return $this;
    }

    protected function __dec_max_min($dec){
        $d = NumUtil::toInt($dec);
        if($d < 0){  $this->is_error = true; return 0;}
        if($d > 255){  $this->is_error = true; return 255; }
        return $d;
    }
    protected function __hex_to_dec($hex){
        return hexdec($hex);
    }
    protected function __dec_to_hex($dec){
        $r = strtolower(dechex($dec));
        if(strlen($r) == 1){ return "0".$r; }
        return $r;
    }
    
    protected function __rgb_to_hsv($r, $g, $b, $coneModel = false){
       $h = 0; // 0..360
        $s = 0; // 0..255
        $v = 0; // 0..255
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        if ($max == $min) {
        } else if ($max == $r) {
            $h = 60 * ($g - $b) / ($max - $min) + 0;
        } else if ($max == $g) {
            $h = (60 * ($b - $r) / ($max - $min)) + 120;
        } else {
            $h = (60 * ($r - $g) / ($max - $min)) + 240;
        }

        while ($h < 0) {
            $h += 360;
        }

        if ($coneModel) {
            $s = $max - $min;
        } else {
            if ($max == 0) {
                $s = 0;
            } else {
                $s = ($max - $min) / $max * 255;
            }
        }
        $v = $max;
        return ["h"=>$h, "s"=>$s, "v"=>$v];
    }
    protected function __hsv_to_rgb($h, $s, $v){
        $r = 0;
        $g = 0;
        $b = 0;
        
        while($h < 360){ $h += 360; }
        $h = $h % 360;
        
        if($s === 0){
            $r = $v;
            $g = $v;
            $b = $v;
            return ["r"=>$r, "g"=>$g, "b"=>$b];
        }
        
        $s = $s / 255;
        
        $i = floor($h / 60) % 6;
        $f = ($h / 60) - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);

        switch ($i) {
            case 0 :
                $r = $v;
                $g = $t;
                $b = $p;
                break;
            case 1 :
                $r = $q;
                $g = $v;
                $b = $p;
                break;
            case 2 :
                $r = $p;
                $g = $v;
                $b = $t;
                break;
            case 3 :
                $r = $p;
                $g = $q;
                $b = $v;
                break;
            case 4 :
                $r = $t;
                $g = $p;
                $b = $v;
                break;
            case 5 :
                $r = $v;
                $g = $p;
                $b = $q;
                break;
        }
        return ["r"=>$r, "g"=>$g, "b"=>$b];
    }
}