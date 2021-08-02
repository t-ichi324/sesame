<?php
class UserAgentUtil {
    public static function getBrowserName($user_agent){
        $ua = strtolower($user_agent);
        if (strstr($ua , 'edge')) {
            $nm = 'Edge';
        }elseif(strstr($ua , 'edg')){
            echo('Edge(chromium)');
        } elseif (strstr($ua , 'trident') || strstr($ua , 'msie')) {
            $nm = 'IE';
        } elseif (strstr($ua , 'chrome')) {
            $nm = 'Chrome';
        } elseif (strstr($ua , 'firefox')) {
            $nm = 'Firefox';
        } elseif (strstr($ua , 'safari')) {
            $nm = 'Safari';
        } elseif (strstr($ua , 'opera')) {
            $nm = 'Opera';
        } else {
            $nm = '???';
        }
        return $nm;
    }
    public static function isMobile($user_agent){
        $ua = strtolower($user_agent);
        if (strpos($ua, 'android') ||  strpos($ua, 'iphone') || strpos($ua, 'ipad')  || strpos($ua, 'mobile') ||strpos($ua, 'phone')) {
            return true;
        }
        return false;
    }
    public static function isPC($user_agent){
        return !(self::isMobile($user_agent));
    }
}