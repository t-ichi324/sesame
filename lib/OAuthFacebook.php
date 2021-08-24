<?php
/***
 * 事前にFacebookのデベロッパー設定を行ってください
 * https://developers.facebook.com/apps/
 * > 「アプリを作成」→「生産者」→「Facebookログイン」→「ウェブ」から必須項目を登録
 * >「設定」→「ベーシック」のアプリIDと「app secret」のコードを取得
 * >「設定」→「ベーシック」の必須項目を設定
 * >「アプリレビュー」→「アクセスと機能」の「email」「public_profile」をアドバンスアクセスに設定
 * 
 * PHP ------------
    $client_id = "[***-***]";
    $client_secret = "[***-***]";
    $redirect = "[*****]";
    $log = __DIR__ . "/FacebookOAuth.log";
    $access_token = FacebookOAuth::getAccessToken(Form::get("code"), $client_id, $client_secret, $redirect, $log);
    $user_info = FacebookOAuth::getUserInfo($access_token, $log);
    print_r($user_info);
 */

class OAuthFacebook {
    private static $URL_AUTH = 'https://www.facebook.com/dialog/oauth';
    private static $URL_TOKEN = 'https://graph.facebook.com/v2.3/oauth/access_token';
    private static $URL_USERI = 'https://graph.facebook.com/me';
    private static $OAUTH_SCOPE = ['email', 'public_profile'];
    private static $ME_FIELDS = ['id','email','name','first_name','last_name'];
    
    public static function getUrlAuth($client_id, $callback){
        $param = array('client_id' => $client_id, 'redirect_uri'=>$callback);
        $param["scope"] = implode(',',self::$OAUTH_SCOPE);
        return self::$URL_AUTH."?".http_build_query($param);
    }

    public static function getAccessToken($code, $client_id, $client_secret, $callback, $log_file = null){
        if(!extension_loaded("curl")){
            echo "<p style='color:#f00;'>ERROR : Not installed 'curl'.</p>";
            return;
        }
        $param = ["code"=>$code, "client_id"=>$client_id, "client_secret"=>$client_secret, "redirect_uri" => $callback, "grant_type"=>"authorization_code"];
        try{
            $ch = curl_init(self::$URL_TOKEN);
            try{
                curl_setopt($ch, CURLOPT_POST, TRUE); 
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));

                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $r = curl_exec($ch);
                $info = curl_getinfo($ch);
                $result["header"] = substr($r, 0, $info["header_size"]);
                $result["body"] = substr($r, $info["header_size"]);
                curl_close($ch);
                
                $json = json_decode($result["body"]);
            } catch (Exception $ex) {
                curl_close($ch);
                $result["error"] = $ex->getMessage();
                echo print_r($ex->getMessage());
                $json = array();
            }

            if(empty($json) || isset($json->error)){
                self::log($log_file, "ERR[1]", self::$URL_TOKEN, $json);
                return null;
            }
            self::log($log_file, "SUCCESS", self::$URL_TOKEN, $json);
            return $json->access_token;
            
        } catch (Exception $ex) {
            echo $ex->getMessage();
            return null;
        }

    }
    public static function getUserInfo($access_token, $log_file = null){
        if(empty($access_token)){ return null; }
        $url = self::$URL_USERI. "?" ."access_token=".$access_token."&fields=". implode(',',self::$ME_FIELDS);
        
        $re = file_get_contents($url);
        $json = json_decode($re);
        if(empty($json) || isset($json->error)){
            self::log($log_file, "ERR[2]", $url, $json);
            return null;
        }
        self::log($log_file, "SUCCESS", $url, $json);
        return $json;
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
                if(is_array($json) || is_object($json)){
                    $line .= "\n >>> ".$api."\t" .json_encode($json);
                }else{
                    $line .= "\n >>> ".$api."\t" .$json;
                }
            }
            error_log($line."\n", 3, $log_file);
            
        } catch (Exception $ex) {}
    }
}
