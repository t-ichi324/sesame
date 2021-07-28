<?php
class Validator {
    private static $errorCnt = 0;
    private static $msgLvl = 1;
    
    /** <p>パスワードのガイド文字列</p> */
    public static function PASSWORD_GUIDE($min = Conf::USER_PW_MIN, $level = Conf::USER_PW_LEVEL){
        switch ($level){
            case 1:
                if($min < 2){ $min = 2; }
                $msg = __("valid.pw-guide-2", $min);
                break;
            case 2:
                if($min < 3){ $min = 3; }
                $msg = __("valid.pw-guide-3", $min);
                break;
            case 3:
                if($min < 4){ $min = 4; }
                $msg = __("valid.pw-guide-4", $min);
                break;
            default :
                if($min < 1){ $min = 1; }
                $msg = __("valid.pw-guide-1", $min);
                break;
        }
        return $msg;
    }
    
    /**
     * エラーメッセージのレベルを指定
     * @param int $level
     */
    public static function setMessageLevel($level){
        self::$msgLvl = $level;
    }

    /**
     * エラーメッセージの追加
     * @param string $msg エラーメッセージ
     */
    public static function addError($msg){
        self::$errorCnt += 1;
        switch (self::$msgLvl){
            case 1:
                Message::addWarning($msg);
                break;
            case 2:
                Message::addError($msg);
                break;
        }
    }
    /**
     * エラーが存在するか
     * @return bool
     */
    public static function hasError() {
        return self::$errorCnt > 0;
    }
    /**
     * 必須チェック
     */
    public static function isAllNotEmpty(... $fields) {
        $b = TRUE;
        foreach($fields as $f){
            if(empty($f)){
                $b = FALSE;
            }
        }
        return $b;
    }
    /**
     * 必須チェック
     */
    public static function required($field,  $name){
        if(empty($field)){
            self::addError( __("valid.required", $name) );
            return false;
        }
        return TRUE;
    }
    
    /**
     * パスワードの形式チェック<br>
     * <b>level:0</b> 長さ以外のチェックを行わない
     * <b>level:1</b> アルファベット・数字が含まれている
     * <b>level:2</b> アルファベット・数字・記号が含まれている
     * <b>level:3</b> 大文字と小文字アルファベット・数字・記号が含まれている
     */
    public static function password($field, $name, $min = Conf::USER_PW_MIN, $level = Conf::USER_PW_LEVEL){
        $msg = $name."は、".self::PASSWORD_GUIDE($min, $level);
        
        switch ($level){
            case 1:
                if($min < 2){ $min = 2; }
                break;
            case 2:
                if($min < 3){ $min = 3; }
                break;
            case 3:
                if($min < 4){ $min = 4; }
                break;
            default :
                if($min < 1){ $min = 1; }
                break;
        }
        
        if(empty($field)){
            self::addError($msg);
            return false;
        }
        
        $len = mb_strlen($field);
        if($len < $min){
            self::addError($msg);
            return false;
        }
        
        if($level > 0){
            $iLo = preg_match("/[a-z]+/", $field);
            $iUp = preg_match("/[A-Z]+/", $field);
            $iNm = preg_match("/[0-9]+/", $field);
            $iSy = preg_match("/[\!\#\$\%\(\)\*\+\-\.\/\:;\=\?\@\[\]\^_`\{\|\}]+/", $field);
            switch ($level){
                case 1:
                    if(!( ($iLo || $iUp) && $iNm) ){
                        self::addError($msg);
                        return false;
                    }
                    break;
                case 2:
                    if(!( ($iLo || $iUp) && $iNm & $iSy) ){
                        self::addError($msg);
                        return false;
                    }
                    break;
                case 3 :
                    if(!( $iLo && $iUp && $iNm & $iSy) ){
                        self::addError($msg);
                        return false;
                    }
                    break;
            }
        }
        return true;
    }
    
    /**
     * 桁数チェック
     */
    public static function lenMax($field,  $name,  $max){
        $len = mb_strlen($field);
        if($len > $max){
            self::addError( __("valid.lenMax", $name, $max) );
            return false;
        }
    }
    public static function lenMin($field,  $name,  $min){
        $len = mb_strlen($field);
        if($len < $min){
            self::addError( __("valid.lenMin", $name, $min) );
            return false;
        }
        return true;
    }
    public static function lenRange($field,  $name,  $min,  $max){
        $len = mb_strlen($field);
        if($len > $max || $len < $min){
            self::addError( __("valid.lenRange",$name, $min, $max) );
            return false;
        }
        return true;
    }
    
    public static function compare( $field1,  $field2,  $name){
        if($field1 !== $field2){
            self::addError( __("valid.compare",$name) );
            return false;
        }
        return true;
    }
    
    
    /** 数字のみ */
    public static function numOnly($field,  $name){
        if(!preg_match("/^[0-9]+$/", $field)){
            self::addError( __("valid.numOnly",$name) );
            return false;
        }
        return true;
    }
    /** アルファベットのみ */
    public static function alphaOnly($field,  $name){
        if(!preg_match("/^[a-zA-Z]+$/", $field)){
            self::addError( __("valid.alphaOnly",$name) );
            return false;
        }
        return true;
    }
    /** アルファベット + 数字のみ */
    public static function alphaNumOnly($field,  $name){
        if(!preg_match("/^[a-zA-Z0-9]+$/", $field)){
            self::addError( __("valid.alphaNumOnly",$name) );
            return false;
        }
        return true;
    }
    
    /**
     * メールアドレスのフォーマットチェック
     */
    public static function format_email($field,  $name){
        if(!preg_match('/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/iD', $field)){
            self::addError( __("valid.format_email",$name) );
            return false;
        }
        return true;
    }
    /**
     * 郵便番号（ハイフン有）のフォーマットチェック
     */
    public static function format_zipcode_hyphen($field,  $name){
        if(!preg_match('/^\d{3}\-\d{4}$/', $field)){
            self::addError( __("valid.format_zipcode_hyphen",$name) );
            return false;
        }
        return true;
    }
    /**
     * TEL・FAX（ハイフン有）のフォーマットチェック
     */
    public static function format_tel_hyphen($field,  $name){
        if(!preg_match('/^([0-9]{2,})\-([0-9]{2,})\-([0-9]{4,})$/', $field)){
            self::addError( __("valid.format_tel_hyphen",$name) );
            return false;
        }
        return true;
    }
}
