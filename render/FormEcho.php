<?php
class FormEcho{
    
    private static $_bundle = null;
    private static $_npfx = null;
    private static $_nsfx = null;
    public static function set_bundle(IData $data, $name_prefix = null, $name_suffix = null){
        self::$_bundle = $data;
        self::$_npfx = $name_prefix;
        self::$_nsfx = $name_suffix;
    }
    public static function end_bundle(){
        self::$_bundle = null;
        self::$_npfx = null;
        self::$_nsfx = null;
    }
    private static function _name($name, $add_q = false){
        $q = $add_q ? "?" : "";
        return 'name="'. $q .htmlspecialchars(self::$_npfx.$name.self::$_nsfx).'"';
    }
    
    public static function get($name, $nullVal = null){
        if(self::$_bundle === null){
            return Form::getVal($name, $nullVal);
        }else{
            return self::$_bundle->getVal($name, $nullVal);
        }
    }
    public static function isEmpty($name){
        if(self::$_bundle === null){
            return Form::isEmpty($name);
        }else{
            return self::$_bundle->isEmpty($name);
        }
    }
    public static function getFormObject(){
        return Form::getFormObject();
    }
    
    public static function callFunc($funcName, ... $args){
        $obj = self::getFormObject();
        if($obj === null){ return null; }
        if(is_callable(array($obj, $funcName))){
            if(count($args) > 0){
                return $obj->$funcName(... $args);
            }else{
                return $obj->$funcName();
            }
        }
        return null;
    }
    
    public static function hasList(){
        $obj = self::getFormObject();
        if($obj === null){ return false; }
        if($obj instanceof IListForm){
            return $obj->hasList();
        }
        return false;
    }
    public static function getList(){
        return Form::getList();
    }
    public static function listDetail($format = "{start}~{end} / {max}"){
        $obj = self::getFormObject();
        if($obj === null){ return ""; }
        $re = $format;
        if($obj instanceof IListForm){
            $max = $obj->getMax();
            $end = $obj->getEnd();
            if(empty($obj->hasList())){
                $start = 0;
            }else{
                $start = $obj->getStart();
            }
            
            $re = str_replace("{max}", $max, $re);
            $re = str_replace("{start}", $start, $re);
            $re = str_replace("{end}", $end, $re);
        }
        return $re;
    }
    
    public static function text($name){
        echo htmlspecialchars(self::get($name));
    }
    
    public static function multiLine($name, $tag = "p", $class=""){
        foreach(StringUtil::toHtmlText(self::get($name), $tag, $class) as $s){
            echo $s;
        }
    }
    
    public static function if_equal($name, $val, $eqText, $elseText = ""){
        if(self::get($name) == $val){
            echo htmlspecialchars($eqText);
        }else{
            echo htmlspecialchars($elseText);
        }
    }
    public static function if_empty($name, $eqText, $elseText = ""){
        if(self::isEmpty($name)){
            echo htmlspecialchars($eqText);
        }else{
            echo htmlspecialchars($elseText);
        }
    }
    
    public static function tag_csrfToken(){ 
        echo "<input type='hidden' name='".Secure::getCsrfName()."' value='".htmlspecialchars(Secure::getCsrfToken())."' />";
    }

    //HTMLタグ補助
    public static function tag_hidden(...$name){
        foreach($name as $n){
            if(StringUtil::isNotEmpty($n)){
                echo '<input type="hidden" '.self::_name($n).' value="'.htmlspecialchars(self::get($n)).'"/>';
            }
        }
    }
    
    public static function tag_option($name, $optionVal, $optionText){
        $val = self::get($name);
        $attr = "";
        if(is_array($val) && count($val) > 0){
            if(in_array($optionVal, $val, FALSE)){
                $attr = " selected";
            }
        }else if($val == $optionVal){
            $attr = " selected";
        }
        echo '<option value="'.htmlspecialchars($optionVal).'" '.$attr.'>'.htmlspecialchars($optionText)."</option>";
    }
    public static function tag_unCheckedHidden($name, $optionVal = Flags::OFF){
        echo '<input type="hidden" name="?'.$name.'" value="'.htmlspecialchars($optionVal).'"/>';
    }
    
    public static function attr_nameValChecked($name, $optionVal = Flags::ON, $isMulti = false){
        $val = self::get($name);
        $attr = "";
        if(is_array($val) && count($val) > 0){
            if(in_array($optionVal, $val, FALSE)){
                $attr = " checked";
            }
        }else if($val == $optionVal){
            $attr = " checked";
        }
        if($isMulti){
            $name .= "[]";
        }
        echo ' '.self::_name($name).' value="'.htmlspecialchars($optionVal).'"'.$attr;
    }
    
    public static function attr_val($name){
        $val = htmlspecialchars(self::get($name));
        echo ' value="'.$val.'"';
    }
    public static function attr_nameVal($name){
        $val = htmlspecialchars(self::get($name));
        echo ' '.self::_name($name).' value="'.$val.'"';
    }
    public static function attr_name($name){
        echo self::_name($name);
    }
    
    public static function tag_textarea($name, array $attrs = null){
        $val = htmlspecialchars(self::get($name));
        echo '<textarea '.self::_name($name).'';
        if(!empty($attrs)){
            foreach($attrs as $k => $v){
                echo " {$k}='{$v}'";
            }
        }
        echo '>'.$val.'</textarea>';
    }
    
    public static function tag_asortVal(){
        $sort = htmlspecialchars(self::get("sort"));
        $desc = htmlspecialchars(self::get("desc"));
        $tag = "<input type='hidden' name='sort' value='".$sort."'/>";
        $tag.= "<input type='hidden' name='desc' value='".$desc."'/>";
        echo $tag;
    }
    public static function attr_asortkey($name){
        $attr = " data-key='".$name."'";
        if(self::get("sort") === $name){
            if(empty(self::get("desc"))){
                $attr.= " data-desc='desc'";
            }
        }
        echo $attr;
    }
    
    public static function tag_refresh_form($url, $replacetarget, $this_id = "x-reflesh-form"){
        $f = self::getFormObject();
        if($f !== null && $f instanceof IForm){
            echo "<form action='".Url::get($url)."' method='post' id='".h($this_id)."' class='ajax-form' data-ajax-target='".h($replacetarget)."' style='display:none;'>";
            echo $f->tag_refresh();
            self::tag_csrfToken();
            echo "</form>";
        }
    }
}

class MetaEcho{
    //ECHOS
    protected static function ECHO_META($attr, $attr_val, $content){
        $tag = ' '.$attr.'="'.htmlspecialchars($attr_val).'"' . ' content="'.htmlspecialchars($content).'"';
        echo "<meta{$tag} />\n";
    }
    public static function get_title(){
        $t = Meta::get_title();
        if(\StringUtil::isEmpty($t)){
            $title = \Conf::SITE_NAME;
        }else{
            $title = $t.\Conf::TITLE_DELIMITER.\Conf::SITE_NAME;
        }
        return $title;
    }
    public static function tag_title($og = false){
        echo '<title>'.htmlspecialchars(self::get_title()).'</title>'."\n";
    }
    public static function tag_og_title(){
        self::ECHO_META("property", "og:title", self::get_title());
    }
    public static function tag_description(){
        $c = Meta::get_description();
        if(StringUtil::isEmpty($c)){ return; }
        self::ECHO_META("name", "description", str_replace("\n", "", trim($c)));
    }
    public static function tag_og_description(){
        $c = Meta::get_description();
        if(StringUtil::isEmpty($c)){ return; }
        self::ECHO_META("property", "og:description", str_replace("\n", "", trim($c)));
    }
    
    public static function tag_og_image($default_og_image = null){
        $img = Meta::get_image();
        if(StringUtil::isEmpty($img)){
            if($default_og_image === null){ return; }
            $img = $default_og_image;
        }
        self::ECHO_META("property", "og:image", \Url::get($img));
    }
    
    public static function tag_robots($force_noIndex = false){
        if($force_noIndex){
            self::ECHO_META("name", "robots", "noindex,nofollow,noarchive");
            return;
        }
        $c = Meta::get_robots();
        if(StringUtil::isEmpty($c)){ return; }
        self::ECHO_META("name", "robots", $c);
    }
    public static function tag_content_map(){
        $map = Meta::get_content_map();
        foreach($map as $k => $v){
            $c = $v["content"];
            if(StringUtil::isEmpty($c)){ continue; }
            self::ECHO_META($v["attr"], $v["attr_val"], $c);
        }
    }
}