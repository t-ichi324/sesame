<?php
/***
 * 事前にGoogle Recaptchaにサイトを登録してください。
 * https://www.google.com/recaptcha/about/
 * 
 * HTML -----------
    <script src='https://www.google.com/recaptcha/api.js'></script>
    <form>
        <div class="g-recaptcha" data-sitekey="[***-***]"></div>
        <button type="submit">SUBMIT</button>
    </form>

 * PHP ------------
    $key = "[***-***]";
    $log = __DIR__ . "/GoogleRecaptcha.log";
    $code = GoogleRecaptcha::valid($key, $log);
    if($code === 1){ echo("reCAPTCHAをチェックしてください");  return ""; }
    if($code === 2){ echo("reCAPTCHA認証エラー"); return ""; }

 */
class GoogleRecaptcha {
    private static $GOOGLE_API = "https://www.google.com/recaptcha/api/siteverify";
    private static $POST_NAME = "g-recaptcha-response";
    
    /***
     * 0 : Success
     * 1 : Not Checked
     * 2 : Valid Error
     */
    public static function valid($secret, $log_file = null){
        $api = null; $json = null;
        try{
            $captcha;
            if(isset($_POST[self::$POST_NAME])){ $captcha=$_POST[self::$POST_NAME]; }
            if(!$captcha){
                self::log($log_file, "ERR[1]", $api, $json);
                return 1;
            }
            
            $api = self::$GOOGLE_API . "?secret=".$secret."&response=".$captcha."&remoteip=".$_SERVER['REMOTE_ADDR'];
            $json = file_get_contents($api);
            $data = json_decode($json, true);
            
            if(isset($data["success"]) && $data["success"] === true){
                self::log($log_file, "SUCCESS", $api, $json);
                return 0;
            }
        } catch (Exception $ex) {}
        
        self::log($log_file, "ERR[2]", $api, $json);
        return 2;
    }
    
    private static function log($log_file, $msg, $api, $json){
        if(empty($log_file)) { return; }
        try{
            $line = date("Y-m-d H:i:s")."\t" .
                    $_SERVER["REMOTE_ADDR"]."\t" .
                    $_SERVER["REQUEST_METHOD"]."\t" .
                    $_SERVER["REQUEST_URI"]."\t" .
                    $msg."\t" .
                    session_id();
            $line .= "\t".(isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "");
            if(!empty($api)){
                $line .= "\n >>> ".$api."\t" .$json;
            }
            error_log($line."\n", 3, $log_file);
            
        } catch (Exception $ex) {}
    }
}
