<?php 
/** <p>date($format) | default: "Y-m-d H:i:s"</p> */
function now($format = "Y-m-d H:i:s"){ return date($format); }
function now_fileName($format = "YmdHis"){ return date($format); }
/** <p>htmlspecialchars | ENT_QUOTES | UTF-8</p> */
function h($string){ return htmlspecialchars($string, ENT_QUOTES, 'UTF-8'); }

function base64url_encode($data){ return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); } 
function base64url_decode($data){ return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); }

/** <p>フラグ</p> */
class Flags{
    /** <p>フラグ固定値 : 1</p> */
    const ON = "1";
    /** <p>フラグ固定値 : 0</p> */
    const OFF = "0";
    /** <p>フラグ判定</p> */
    public static function isON($v){ return (self::ON == strval($v)); }
    /** <p>フラグ判定</p> */
    public static function isOFF($v){ return ! self::isOn($v); }
}

/** <p>文字系Util</p> */
class StringUtil{
    private static $_enc = 'UTF-8';
    /** <p>空白文字</p> */
    const __EMPTY = "";
    /** <p>StringUtilで利用するエンコードを指定(デフォルトはUTF-8)</p> */
    public static function setEncoding($encoding){ self::$_enc = $encoding; }
    
    /** <p>強制的にstring型へ変換</p> */
    public static function toString($str, $defaultValue = ""){ 
        try{
            if(is_array($str) || is_object($str)){return json_encode($str);}
            return strval($str); 
        } catch (Exception $ex){ return $defaultValue; }
    }

    /** <p>空白文字もしくはNULLの場合TRUE</p> */
    public static function isEmpty($str){ return (empty($str) && $str !== "0" && $str !== 0 && $str !== 0.0); }
    /** <p>空白文字もしくはNULLの場合FALSE</p> */
    public static function isNotEmpty($str){ return self::isEmpty($str) ? false : true;}

    /** <p>バイト数を返す</p> */
    public static function byteCount($str){ return strlen(self::toString($str)); }
    /** <p>文字数を返す</p> */
    public static function length($str){ return mb_strlen(self::toString($str), self::$_enc); }
    
    /** <p>アルファベットのみか</p> */
    public static function isAlpha($str){ return preg_match("/^[a-zA-Z]+$/",self::toString($str)); }
    /** <p>小文字アルファベットのみか</p> */
    public static function isAlphaLower($str){ return preg_match("/^[a-z]+$/",self::toString($str)); }
    /** <p>小文字アルファベットのみか</p> */
    public static function isAlphaUper($str){ return preg_match("/^[A-Z]+$/",self::toString($str)); }
    /** <p>数字のみか</p> */
    public static function isNumber($str){ return preg_match("/^[0-9]+$/",self::toString($str)); }
    /** <p>アルファベットと数字のみか</p> */
    public static function isAlphaNumber($str){ return preg_match("/^[a-zA-Z0-9]+$/",self::toString($str)); }
    
    /** <p>アルファベットを小文字へ</p> */
    public static function trim($str){ return trim(self::toString($str)); }
    /** <p>アルファベットを大文字へ</p> */
    public static function upperCase($str){ return mb_strtoupper(self::toString($str), self::$_enc); }
    /** <p>アルファベットを小文字へ</p> */
    public static function lowerCase($str){ return mb_strtolower(self::toString($str), self::$_enc); }
    /** <p>半角カナ/英数/スペースを全角へ</p> */
    public static function wideCase($str){ return mb_convert_kana(self::toString($str), 'KVAS', self::$_enc); }
    /** <p>全角カナ/英数/スペースを半角へ</p> */
    public static function narrowCase($str){ return mb_convert_kana(self::toString($str), 'kas', self::$_enc); }
    
    /** <p>カナ => 全角 / 英数とスペース => 半角へ</p> */
    public static function systemizeCase($str){ return mb_convert_kana(self::toString($str), 'KVas', self::$_enc); }
    
    public static function hiranaga($str){ return mb_convert_kana(self::toString($str), 'HVc', self::$_enc); }
    public static function katakana($str){ return mb_convert_kana(self::toString($str), 'hC', self::$_enc); }
    
    /** <p>スネークケース(先頭大文字をアンダースコア)へ</p> */
    public static function snakeCase($str, $ucfirst = false){ $v = ltrim(strtolower(preg_replace('/[A-Z]/', '_\0', $str)), '_');if($ucfirst){ return ucfirst($v); }else{return lcfirst($v); }}
    /** <p>キャメルケース(アンダースコア後を大文字へ)へ</p> */
    public static function camelCase($str, $ucfirst = false){ $v = strtr(ucwords(strtr($str, ['_' => ' '])), [' ' => '']); if($ucfirst){ return ucfirst($v);}else{  return lcfirst($v); }}
    
    /** <p>先頭からN文字目までを取得</p> */
    public static function left($str, $length){ return mb_substr(self::toString($str), 0, NumUtil::toInt($length), self::$_enc);}
    /** <p>後方からからN文字目までを取得</p> */
    public static function right($str, $length){ return mb_substr(self::toString($str), (NumUtil::toInt($length) * -1), NULL, self::$_enc);}
    /** <p>文字の中間を取得</p> */
    public static function mid($str, $start, $length = 0){ $s = NumUtil::toInt($start); $e = NumUtil::toInt($length); if($e === 0){ return mb_substr($str, $s, NULL, self::$_enc);} return mb_substr($str, $s, $e, self::$_enc);}
    
    /** <p>先頭にパッディングを挿入</p> */
    public static function padLeft($str, $len, $padChar = ' '){ return str_pad(self::toString($str), $len, $padChar, STR_PAD_LEFT); }
    /** <p>後方にパッディングを挿入</p> */
    public static function padRight($str, $len, $padChar = ' '){ return (str_pad(self::toString($str), $len, $padChar, STR_PAD_RIGHT) === false); }
    
    public static function ellipsis($str, $max, $sym = "..."){if(self::length($str) > $max){ return self::left($str, $max).$sym; } return $str;}
    
    /** <p>文字を置換する</p> */
    public static function replace($str, $search, $replace){
        return str_replace($search, $replace, self::toString($str));
    }
    
    /** <p>Like(*)検索</p> */
    public static function like($str, $search, $wildcard = "*"){
        return fnmatch($search, self::toString($str));
    }
    
    /** <p>特定の文字を含むか</p>*/
    public static function contains($str, ... $searchwords){
        if(empty($searchwords)) { return false; }
        foreach($searchwords as $c){
            if(strpos(self::toString($str), self::toString($c)) !== false){
                return true;
            }
        }
        return false;
    }

    /** <p>文字列を配列へ変換</p> */
    public static function split($str, $delimiter){ return explode(self::toString($delimiter), self::toString($str)); }
    public static function splitFirst($str, $delimiter){ return self::split($str, $delimiter)[0]; }
    public static function splitLast($str, $delimiter){ $x = self::split($str, $delimiter)[0]; $i = count($x)-1; return $i > 0 ? $x[$i] : self::__EMPTY; }
    
    /** <p>文字列を改行区切りで配列に変換</p> */
    public static function lineToArray($txt, $trim_line = true, $ignore_empty = true){
        $ret = array();
        foreach(explode("\n", $txt) as $line){
            $v = ($trim_line) ? trim($line) : $line;
            if($ignore_empty){
                if(!self::isEmpty($v)){
                    $ret[] = $v;
                }
            }else{
                $ret[] = $v;
            }
        }
        return $ret;
    }
    
    /** <p>配列を文字列へ変換</p> */
    public static function arrayToString($arr, $delimiter = ","){
        if(self::isEmpty($arr)){ return ""; }
        if(is_array($arr)){
            $r = "";
            foreach ($arr as $v){
                if($r !== ""){ $r.=$delimiter;}
                $r.=$v;
            }
            return $r;
        }
        return self::toString($arr);
    }
    
    /** <p>可変長配列を準備参照し、isNotEmptyの値を返す</p> */
    public static function defaultVal(...$strs){ foreach($strs as $s){ if(self::isNotEmpty($s)){ return $s; }} return self::__EMPTY; }
    
    /** <p>パスワード・クレカ等の表示を隠す目的</p> */
    public static function toHidden($str, $replace_char = "*"){
        $v = self::toString($str);
        $max = self::length($v);
        $r = "";
        for($i = 0; $i< $max; $i++){
            $r.= $replace_char;
        }
        return $r;
    }
    
    /** <p>文字列をHTMLタグに加工し変換、改行の場合は各行をタグで囲む or 終端にBRタグを付与。</p> */
    public static function toHtmlText($str, $tag = "", $class = "", $htmlEnc = true){
        $arr = explode("\n",  $str);
        $cnt = count($arr);
        if($cnt === 0){ return ""; }
        if($cnt === 1 && self::isEmpty($arr[0])){ return ""; }
        
        $t = strtolower($tag);
        if(StringUtil::isNotEmpty($t)){
            if(StringUtil::isNotEmpty($class)){
                $t1 = "<{$t} class='{$class}'>";
            }else{
                $t1 = "<{$t}>";
            }
            $t2 = "</{$t}>";
        }else{
            $t1 = null;
            $t2 = "<br>";
        }
        $ret = "";

        $pattern = '/((?:https?|ftp):\/\/[-_.!~*\'()a-zA-Z0-9;\/?:@&=+$,%#]+)/';
        $replace = '<a href="$1">$1</a>';

        foreach($arr as $line){
            if(self::isEmpty(trim($line))){
                $ret.=$t1.$t2."\n";
            }else{
                if($htmlEnc){
                    $x = preg_replace($pattern, $replace, htmlspecialchars($line));
                    $x = trim(str_replace(" ", "&nbsp;", $x));
                    $x2 = str_replace("<a&nbsp;href=", "<a href=", $x);
                }else{
                    $x2 = trim($line);
                }
                $ret.= $t1.$x2.$t2."\n";
            }
        }
        return $ret;
    }
    
    /** <p>再帰的な文字結合</p> */
    public static function combine_reflexively($delimiter, $data, $callback_func = null){
        $buff = self::__EMPTY;
        $x = self::__EMPTY;
        $c = 0;
        foreach($data as $p){
            if(self::isNotEmpty($p)){
                if($c===0){ if(self::left($p, 1) === $delimiter){ $x = $delimiter; } }
                if(is_array($p)){$p = self::combine_reflexively($delimiter, $p); }
                if($callback_func !== null){ $p = $callback_func($p); }
                $p = trim($p, $delimiter);
                if((self::isNotEmpty($buff)) && (self::isNotEmpty($p))){ $buff.=$delimiter; }
                $buff.=$p;
                ++$c;
            }
        }
        return $x.$buff;
    }
    
    /** <p>SQLパラメータ文字列へ変換</p> */
    public static function sqlStr($param, $default = "NULL"){
        if(self::isEmpty($param)){ return $default; }
        $s = self::toString($param);
        return "'".str_replace("\\", "\\\\", str_replace("\n", "\\n", str_replace("\t", "\\t", str_replace("'", "''", $s))))."'";
    }
    
    /** <p>ファイルネーム正規化</p> */
    public static function fileNameNormalizer($filename, $urlencode = true){
        $deny = array("\\","/",":","*","?","\"","<",">","|");
        $allow = array("￥","／","：","＊","？","”","＜","＞","｜");
        if($urlencode){
            return rawurlencode(str_replace($deny, $allow, $filename));
        }
        return str_replace($deny, $allow, $filename);
    }
}

/** <p>数値系Util</p> */
class NumUtil{
    /** <p>ゼロであるか判定</p> */
    public static function isZero($var){ return ($var === 0 || $var === "0"); }
    /** <p>数値形式(整数or少数)であるか判定</p> */
    public static function isNumeric($var){ return ($var !== null && is_numeric($var)); }
    /** <p>整数形式であるか判定</p> */
    public static function isInt($var){ if(self::isNumeric($var)){ return (strpos($var ,'.') === false); } return false; }
    /** <p>少数形式であるか判定</p> */
    public static function isFloat($var){ if(self::isNumeric($var)){ return (strpos($var ,'.') !== false); } return false; }
    /** <p>数値形式(整数or少数)であるか判定</p> */
    public static function isNotNumeric($var) { return !self::isNumeric($var); }
    /** <p>整数形式であるか判定</p> */
    public static function isNotInt($var) { return !self::isInt($var); }
    /** <p>少数形式であるか判定</p> */
    public static function isNotFloat($var) { return !self::isFloat($var); }
    
    /** <p>整数値(int型)へ変換</p> */
    public static function toInt($var, $defaultValue = 0){if(self::isNotNumeric($var)){ return $defaultValue; }return intval($var); }
    /** <p>少数値(float型)へ変換</p> */
    public static function toFloat($var, $defaultValue = 0){if(self::isNotNumeric($var)){ return $defaultValue; } return floatval($var); }
    
    /** <p>合計値を取得</p> */
    public static function sum(...$vars){ $c = 0; foreach($vars as $v){ $c += self::toFloat($v); } return $c;}
    /** <p>最大値を取得</p> */
    public static function max(...$vars){$c = NULL;foreach($vars as $v){ $e = self::toFloat($v, NULL); if($e !== NULL && ($c === NULL || $c < $e)){ $c = $e; }} return $c; }
    /** <p>最小値を取得</p> */
    public static function min(...$vars){$c = NULL;foreach($vars as $v){ $e = self::toFloat($v, NULL); if($e !== NULL && ($c === NULL || $c > $e)){ $c = $e; }} return $c; }
    
    /** <p>四捨五入</p> */
    public static function round($var, $precision = 0){ return round(self::toFloat($var), $precision); }
    /** <p>切り上げ</p> */
    public static function ceil($var, $precision = 0){ 
        $f = self::toFloat($var);
        if($precision !== 0){ $c = 10 ** $precision; return ceil($f * $c) / $c; }
        return ceil($f);
    }
    /** <p>切り捨て</p> */
    public static function floor($var, $precision = 0){
        $f = self::toFloat($var);
        if($precision !== 0){ $c = 10 ** self::toInt($precision,1); return floor($f * $c) / $c; }
        return floor($f);
    }
    
    public static function parse_size($size){
        if($size < 0){ return 0; }
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        else {
            return round($size);
        }
    }
}

/** <p>Url操作</p> */
class Url{
    const __SEPARATOR = "/";
    const __QUERY = "?";
    const __QUERY_AND = "&";
    const __QUERY_SET = "=";
    const __SCHEME_DMT = "://";
    public static function baseUri(){ return rtrim(Request::getScriptName(), SysConf::PUBROOT_SCRIPT_NAME); }
    public static function baseUrl(){return self::combine(Request::getUrlHost(), self::baseUri()); }
    
    public static function get(... $paths){
        $c = self::combine(... $paths);
        if(strstr($c, self::__SCHEME_DMT)){ return $c; }
        return self::combine(self::baseUrl(), $c);
    }
    public static function relative(... $paths){
        $c = self::combine(... $paths);
        if(strstr($c, self::__SCHEME_DMT)){ return $c; }
        return $c;
    }
    
    public static function queryString($q){
        if(StringUtil::isEmpty($q)){return StringUtil::__EMPTY;}
        if(is_array($q)){
            $ret = self::queryInnner(StringUtil::__EMPTY, $q);
        } else {
            $ret = ltrim($q, self::__QUERY);
        }
        if(empty($ret)){ return StringUtil::__EMPTY; }
        return self::__QUERY.$ret;
    }
    private static function queryInnner($ret, array $q){
        foreach ($q as $k => $v){
            if($v === null || $v === StringUtil::__EMPTY){ continue; }
            if(!empty($ret)){ $ret .= self::__QUERY_AND; }
            if(is_array($v)){
                $ret .= self::queryInnner($ret, $v);
            }else{
                $ret .= urlencode($k).self::__QUERY_SET.urlencode($v);
            }
        }
        return $ret;
    }
    public static function parseQuery($url){
        $arr = array();
        parse_str($url, $arr);
        return $arr;
    }
    
    public static function combineQuery(... $queries){
        $arr = array();
        foreach($queries as $q){
            $tmp = array();
            if(is_array($q)){ $tmp = $q; }else{ $tmp = self::parseQuery($q); }
            foreach($tmp as $k => $v){
                $arr[$k] = $v;
            }
        }
        return $arr;
    }

    /** <p>URLを結合</p> */
    public static function combine(... $paths){
        $scm = self::__SEPARATOR;
        $oi = array();
        $oq = array();
        self::_combine_reflexively($paths, $scm, $oi, $oq);
        $url = StringUtil::arrayToString($oi, self::__SEPARATOR);
        return $scm.$url.self::queryString($oq);
    }
    
    private static function _combine_reflexively($paths, &$scm, array &$oi, array &$oq){
        if(is_array($paths)){
            foreach($paths as $p){self::_combine_reflexively($p, $scm, $oi, $oq); }
        }else{
            if(StringUtil::isEmpty($paths)){ return; }
            if(empty($oi) && StringUtil::contains($paths, self::__SCHEME_DMT)){
                $scm = StringUtil::splitFirst($paths, self::__SCHEME_DMT).self::__SCHEME_DMT;
                $paths = StringUtil::mid($paths, strlen($scm));
            }
            
            $ps = explode(self::__SEPARATOR, str_replace(DIRECTORY_SEPARATOR, self::__SEPARATOR, $paths));
            foreach($ps as $p){
                if(strstr($p, self::__QUERY)){
                    $ex = explode(self::__QUERY, $p);
                    $p = $ex[0];
                    foreach(self::parseQuery($ex[1]) as $k => $v){ $oq[$k] = $v; }
                }
                if(StringUtil::isNotEmpty($p)){
                    if($p === "."){
                        //--
                    }elseif($p === ".." && count($oi) > 0){
                        array_pop($oi);
                    }else{
                        $oi[] = $p;
                    }
                }
            }
        }
    }
}

/** <p>物理Path操作</p> */
class Path{
    /** <p>SESAMEのディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function sesame(... $paths){ return self::combine(__X_LOADER_DIR, $paths); }
    /** <p>SESAMEの"lib"ディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function sesameLib(... $paths){ return self::combine(__X_LOADER_DIR_LIB, $paths); }
    /** <p>SESAMEの"vender"ディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function sesameVender(... $paths){ return self::combine(__X_LOADER_DIR_VENDER, $paths); }
    /** <p>サイトのアプリケーションディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function app(... $paths){ return self::combine(Conf::DIR_APP, $paths); }
    /** <p>サイトの公開ディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function pub(... $paths){ return self::combine(Conf::DIR_PUB, $paths); }
    /** <p>ライブラリディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function lib(... $paths){ return self::combine(Conf::DIR_LIB, $paths); }
    /** <p>一時ファイルディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function tmp(... $paths){ return self::combine(Conf::DIR_TMP, $paths); }
    /** <p>ログディレクトリを取得</p>
     *  @param variadic: string or array[string] */
    public static function log(... $paths){ return self::combine(Conf::DIR_LOG, $paths); }
    
    /** <p>ダウンロードファイル作成フォルダを取得。24時間より前のファイルは削除される。</p> */
    public static function download($filename = null){
        $d = new DirectoryInfo(self::tmp(SysConf::DIR_TMP_DOWNLOAD));
        if(!$d->exists()){ $d->make(); }
        $cdt = date('YmdHis', strtotime("-24 hour"));
        foreach($d->getFilePaths() as $f){
            $fdt = date('YmdHis', fileatime($f));
            if($fdt < $cdt){
                unlink($f);
            }
        }
        if(StringUtil::isEmpty($filename)){
            return $d->fullName();
        }
        return $d->getFileInfo($filename)->fullName();
    }
    
    public static function download_tmpfile(){
        $ms = explode('.',microtime(true))[1];
        $nm = "f".date('Ymdhis')."_".$ms.""."_".session_id().".tmp";
        return self::download($nm);
    }
    public static function download_tmpdirectory(){
        $ms = explode('.',microtime(true))[1];
        $nm = "f".date('Ymdhis')."_".$ms.""."_".session_id()."_tmp";
        return self::download($nm);
    }
    
    public static function upload($filename = null){
        $d = new DirectoryInfo(self::tmp(SysConf::DIR_TMP_UPLOAD));
        if(!$d->exists()){ $d->make(); }
        $cdt = date('YmdHis', strtotime("-24 hour"));
        foreach($d->getFilePaths() as $f){
            $fdt = date('YmdHis', fileatime($f));
            if($fdt < $cdt){
                unlink($f);
            }
        }
        if(StringUtil::isEmpty($filename)){
            return $d->fullName();
        }
        return $d->getFileInfo($filename)->fullName();
    }
    
    /** <p>正規化</p> */
    public static function normalize($path){
        $hie = array();
        $trm = trim(str_replace("\\", "/", $path));
        $pre = "";
        for($i = 0; $i < strlen($trm); $i++) { if($trm[$i] !== "/"){ break; } $pre .= "/"; }
        $trm = trim($trm, "/");
        $arr = explode("/", $trm);
        foreach($arr as $p){
            if($p === "" || $p === "."){ continue; }
            if($p === ".."){ array_pop($hie); continue; }
            $hie[] = $p;
        }
        return $pre . implode(DIRECTORY_SEPARATOR, $hie);
    }
    
    /** <p>Pathを結合</p> */
    public static function combine(... $paths){
        $oi = array();
        $pfx = "";
        self::_combine_reflexively($paths, $oi, $pfx);
        return $pfx.StringUtil::arrayToString($oi, DIRECTORY_SEPARATOR);
    }

    private static function _combine_reflexively($paths, array &$oi, &$pfx){
        if(is_array($paths)){
            foreach($paths as $p){self::_combine_reflexively($p, $oi, $pfx); }
        }else{
            if($paths instanceof __IO_Info){ $paths = $paths->fullName(); }
            if(StringUtil::isEmpty($paths)){ return; }
            
            $ps = explode(DIRECTORY_SEPARATOR, str_replace("/", DIRECTORY_SEPARATOR, $paths));
            if(empty($oi) && $paths[0] == '/'){ $pfx = '/'; }
            foreach($ps as $p){
                if(StringUtil::isNotEmpty($p)){
                    if($p === "."){
                        //--
                    }elseif($p === ".." && count($oi) > 0){
                        array_pop($oi);
                    }else{
                        $oi[] = $p;
                    }
                }
            }
        }
    }    
}

/** IO抽象クラス */
abstract class __IO_Info{
    protected $full = null;
    protected $info = null;
    protected static function __IsExists($path, $isFile){
        $r = StringUtil::isNotEmpty($path) && file_exists($path);
        if($isFile){ return $r; }
        return $r && is_dir($path);
    }
    public function __construct($path) {
        $this->full = $path;
        $this->info = pathinfo($this->full);
    }
    protected function gi($n, $nv = ""){
        if($this->info === null){ return $nv; }
        if(isset($this->info[$n])){ return $this->info[$n]; }
        return $nv;
    }
    /** <p>存在確認</p> */
    public abstract function exists();
    /** <p>存在確認</p> */
    public function notExists(){ return !$this->exists(); }
    /** <p>フルパス名を取得</p> */
    public function fullName(){ return $this->full;  }
    /** <p>親フォルダの情報を取得</p> */
    public function baseDirectory(){ return $this->gi("dirname"); }
    /** <p>親フォルダの情報を取得</p> */
    public function baseDirectoryInfo(){ return new DirectoryInfo($this->baseDirectory()); }
    
    /** <p>リネーム</p> */
    public function rename($newName){if($this->exists() && !file_exists($newName)){ rename($this->full, $newName); if(file_exists($newName)){ $this->full = $newName; return true;}} return false; }
    /** <p>最終アクセス時刻(Unix timestamp)。存在しない場合はnull</p> */
    public function aTime(){ if($this->exists()){ return fileatime($this->full); } return null; }
    /** <p>更新時刻(Unix timestamp)。存在しない場合はnull</p> */
    public function mTime(){ if($this->exists()){ return filemtime($this->full); } return null; }
}

/** ディレクトリIO */
class DirectoryInfo extends __IO_Info{
    /** <p>存在確認</p> */
    public function exists(){ return self::__IsExists($this->full, FALSE);}
    /** <p>ディレクトリ名を取得</p> */
    public function name(){ return $this->gi("basename"); }
    /** <p>ファイルPathの一覧取得</p> */
    public function getFilePaths(... $ptrns){
        $ret = array();
        if(!$this->exists()){ return $ret; }
        if(StringUtil::isEmpty($ptrns)){
            foreach(glob($this->full.DIRECTORY_SEPARATOR."{*,.[!.]*,..?*}", GLOB_BRACE) as $f){ if(is_file($f)){ $ret[] = $f;} }
        }else{
            foreach($ptrns as $p){
                foreach(glob($this->full.DIRECTORY_SEPARATOR.$p, GLOB_BRACE) as $f){ if(is_file($f)){ $ret[] = $f;} }
            }
        }
        return $ret;
    }
    /** <p>ファイル名(pathを含まない)の一覧を取得</p> */
    public function getFileNames(... $ptrns){
        $ret = array();
        foreach($this->getFilePaths(... $ptrns) as $v){
            $p = pathinfo($v);
            $ret[] = $p["basename"];
        }
        return $ret;
    }
    /** <p>ファイルInfoの一覧を取得</p> */
    public function getFileInfos(... $ptrns){
        $ret = array();
        foreach($this->getFilePaths(... $ptrns) as $v){ $ret[] = new FileInfo($v); }
        return $ret;
    }
    /** <p>ファイルPathを取得</p> */
    public function getFilePath($childName){
        return Path::combine($this->full, $childName);
    }
    /** <p>ファイルInfoを取得</p> */
    public function getFileInfo($childName){
        return new FileInfo($this->getFilePath($childName));
    }
    /** <p>ディレクトリの一覧を取得</p> */
    public function getDirectoryPaths(... $ptrns){
        $ret = array();
        if(!$this->exists()){ return $ret; }
        if(StringUtil::isEmpty($ptrns)){
            foreach(glob($this->full.DIRECTORY_SEPARATOR."{*,.[!.]*,..?*}", GLOB_BRACE | GLOB_ONLYDIR) as $f){ if(is_dir($f)){ $ret[] = $f;} }
        }else{
            foreach($ptrns as $p){
                foreach(glob($this->full.DIRECTORY_SEPARATOR.$p, GLOB_BRACE | GLOB_ONLYDIR) as $f){ if(is_dir($f)){ $ret[] = $f;} }
            }
        }
        return $ret;
    }
    /** <p>ディレクトリInfoの一覧を取得</p> */
    public function getDirectoryInfos(... $ptrns){
        $ret = array();
        foreach($this->getDirectoryPaths(... $ptrns) as $v){ $ret[] = new DirectoryInfo($v); }
        return $ret;
    }
    /** <p>子ディレクトリのフルPathを取得</p> */
    public function getDirectoryPath($childName){ return $this->full.DIRECTORY_SEPARATOR.$childName; }
    /** <p>子ディレクトリのInfoを取得</p> */
    public function getDirectoryInfo($childName){ return new DirectoryInfo(Path::combine($this->full, $childName)); }
    
    /** <p>ディレクトリが存在しない場合作成します</p> */
    public function make($mode = 0777){
        if(empty($this->full) || $this->exists()){ return; }
        mkdir($this->full, $mode, true);
    }
    /** <p>ディレクトリを削除します</p> */
    public function delete($delete_files = false){
        if(!$this->exists()){ return; }
        if($delete_files){
            foreach ($this->getDirectoryInfos() as $i){ $i->delete(true); }
            foreach ($this->getFileInfos() as $i){ $i->delete(true); }
        }
        rmdir($this->full);
    }
}

/** ファイルIO */
class FileInfo extends __IO_Info {
    /** <p>存在確認</p> */
    public function exists(){ return self::__IsExists($this->full, TRUE);}
    
    /** <p>ファイル名を取得</p> */
    public function name($needExtention = true){ 
        if($needExtention){ return $this->gi("basename"); }
        return basename($this->full, $this->extension(TRUE));
    }
    /** <p>拡張子を取得</p> */
    public function extension($needDot = true){ return ($needDot ? "." : "") . $this->gi("extension"); }
    
    /** <p>ファイル情報を文字列として取得</p> */
    public function read($nullVal = null){
        if($this->notExists()){ return $nullVal; }
        return file_get_contents($this->full);
    }
    /** <p>ファイル情報をhtmlspecialcharsでエンコードした文字列として取得</p> */
    public function readH($nullVal = null){
        if($this->notExists()){ return h($nullVal); }
        return h(file_get_contents($this->full));
    }

    /** <p>sha1ハッシュを取得。存在しない場合はNULLを返す</p> */
    public function hash(){ if($this->notExists()){ return null; } return sha1_file($this->full); }
    
    /** <p>ファイルへ情報を書き込みます</p> */
    public function save($data, $lock = true){
        if(empty($this->full)){ return; }
        $bs = $this->baseDirectoryInfo();
        if($bs->notExists()){ $bs->make($mode); }
        $opt = (($lock === true) ? LOCK_EX : 0);
        file_put_contents($this->full, $data, $opt);
    }
    /** <p>ファイルを削除します</p> */
    public function delete(){
        if($this->notExists()){ return; }
        unlink($this->full);
    }
    
    /** <p>ディレクトリが存在しない場合作成します</p> */
    public function makeDirectory($mode = 0777){
        $this->baseDirectoryInfo()->make($mode);
    }
    
}

/** キャッシュ操作 */
class Cache{
    public static function clean($dir){
        $r = new DirectoryInfo(Path::combine(Conf::DIR_TMP, SysConf::DIR_CACHE, $dir));
        if($r->exists()){
            $nm = $r->fullName()."_".now_fileName();
            if($r->rename($nm)){$r->delete(true); }
        }
    }
    /** キャッシュフォルダのDirectoryInfoを取得 */
    public static function directoryInfo($dir = null){
        $r = new DirectoryInfo(Path::combine(Conf::DIR_TMP, SysConf::DIR_CACHE, $dir));
        $r->make();
        return $r;
    }
    /** キャッシュファイルのFileInfoを取得 */
    public static function fileInfo($dir, $filename){
        $d = self::directoryInfo($dir);
        return $d->getFileInfo($filename);
    }
    
    public static function clean_route(){ self::clean(SysConf::DIR_CACHE_ROUTE);}
    public static function clean_view(){ self::clean(SysConf::DIR_CACHE_VIEW);}
    public static function directoryInfo_route($hash_d = ""){ return self::directoryInfo(SysConf::DIR_CACHE_ROUTE.DIRECTORY_SEPARATOR.$hash_d); }
    public static function fileInfo_route($hash_d, $name){ return self::fileInfo(SysConf::DIR_CACHE_ROUTE.DIRECTORY_SEPARATOR.$hash_d, $name);}
    public static function directoryInfo_view(){ return self::directoryInfo(SysConf::DIR_CACHE_VIEW); }
    public static function fileInfo_view($name){ return self::fileInfo(SysConf::DIR_CACHE_VIEW, $name); }
}

/**
 * セキュリティ関係
 */
class Secure{
    public static function genPassword($len, $use_num = true, $use_alpha_low = true, $use_alpha_up = false, $any_symbchars = null){
        $len = NumUtil::toInt($len, 0);
        $c_low = "abcdefghijklmnopqrstuvwxyz";
        
        $str = "";
        if($use_num){ $str .= "0123456789"; }
        if($use_alpha_low){ $str .= $c_low; }
        if($use_alpha_up){ $str .= StringUtil::upperCase($c_low); }
        if(StringUtil::isNotEmpty($any_symbchars)){ $str .= $any_symbchars; }
        $str_len = StringUtil::length($str)-1;
        if($str_len <= 0) { return ""; }
        
        $pw = "";
        for($i = 0; $i < $len; $i++){
            $ridx = rand(0, $str_len);
            $pw .= mb_substr($str, $ridx, 1);
        }
        return $pw;
        //return substr(bin2hex(openssl_random_pseudo_bytes($len)), 0, $len);
    }
    public static function toHash($str){ return sha1($str); }
    
    public static function getCsrfName(){
        return SysConf::CSRF_NAME;
    }
    public static function getCsrfToken() {
        if(! isset($_SESSION[SysConf::SESSION_CSRF_NAME])){
            $_SESSION[SysConf::SESSION_CSRF_NAME] = self::genPassword(16);
        }
        return $_SESSION[SysConf::SESSION_CSRF_NAME];
    }
    public static function compareCsrfToken(){
        if(Request::isPost()){
            return (Form::getCsrfToken() === self::getCsrfToken());
        }
        return true;
    }
    
    public static function encrypt($data, $password, $iv = null){
        try{
            if($iv === null){
                $iv = self::createFixIV($password);
            }
            $c = openssl_encrypt($data, SysConf::ENC_METHOD, $password, 0, $iv);
            return base64_encode($c);
        } catch (Exception $ex) {
            Log::error($ex);
            return null;
        }
    }
    public static function decrypt($data, $password, $iv = null){
        try{
            if($iv === null){
                $iv = self::createFixIV($password);
            }
            $d = base64_decode($data);
            return openssl_decrypt($d, SysConf::ENC_METHOD, $password, 0, $iv);
        } catch (Exception $ex) {
            Log::error($ex);
            return null;
        }
    }
    public static function createFixIV($souce){
        $len = openssl_cipher_iv_length(SysConf::ENC_METHOD);
        $h = sha1($souce);
        while(strlen($h) < $len){ $h .= $h; }
        $iv = substr($h, 0, $len);
        return $iv;
    }
}
