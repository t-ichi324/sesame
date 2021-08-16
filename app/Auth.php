<?php
/** <p>認証情報</p> */
class Auth{
    private static $tryRemember = false;
    private static $fetched = 0;
    private static $checked = 0;

    /** <p>ユーザのSessionIDを取得</p> */
    public static function getSessionId(){ return session_id(); }
    
    /** <p>認証中の<b>IAuthUser::SQL_AUTH</b>結果の連想配列を取得</p> */
    public static function getKey(){
        if(isset($_SESSION[SysConf::SESSION_AUTH_NAME."@key"])){ return $_SESSION[SysConf::SESSION_AUTH_NAME."@key"]; }
        return self::tryRemember();
    }
    
    public static function setKey($key, $value, $update_remember = true){
        $arr = self::getKey();
        if(!empty($arr)){
            $arr[$key] = $value;
            $_SESSION[SysConf::SESSION_AUTH_NAME."@key"] = $arr;
            if($update_remember){
                self::updateRemember($key, $value);
            }
        }
    }
    
    /** <p>認証中の<b>IAuthUser継承インスタンス</b>を取得</p><p>またRequest毎に1回<b>IAuthUser::SQL_FETCH</b>を実行して内容が更新する</p> */
    public static function getUser(){
        $key = self::getKey();
        if(!empty($key) && isset($_SESSION[SysConf::SESSION_AUTH_NAME."@cls"])){
            $cls = $_SESSION[SysConf::SESSION_AUTH_NAME."@cls"];
            if($cls === null){ return null; }
            if(self::$fetched === 0){
                self::$fetched = 1;
                if($cls->fetchData($key) === false){ return null; }
            }
            return $cls;
        }
        return null;
    }
    /** <p>認証中の<b>IAuthUser継承インスタンス</b>のフィールドを取得</p><p>またRequest毎に1回<b>IAuthUser::SQL_FETCH</b>を実行して内容が更新する</p> */
    public static function getVal($key, $nullVal = null){
        $user = self::getUser();
        if($user === null) { return $nullVal; }
        if($user instanceof IAuthUser){ return $user->getVal($key, $nullVal);}
        return $nullVal; 
    }
    
    public static function get($key = null, $nullVal){
        if(func_num_args() > 0){
            return self::getVal($key, $nullVal);
        }
        return self::getUser();
    }
    
    /** <p>認証中ユーザかの判定<p> */
    public static function check() {
        if(self::$checked === 0){ self::$checked = (self::getUser() !== null) ? 1 : 2;}
        return (self::$checked === 1);
    }
    
    private static function set($key, $cls){
        if($key !== null && $cls !== null){
            if($cls->isAuthenticated()){
                $_SESSION[SysConf::SESSION_AUTH_NAME."@key"] = $key;
                $_SESSION[SysConf::SESSION_AUTH_NAME."@cls"] = $cls;
                return;
            }
        }
        $_SESSION[SysConf::SESSION_AUTH_NAME."@key"] = null;
        $_SESSION[SysConf::SESSION_AUTH_NAME."@cls"] = null;
        unset($_SESSION[SysConf::SESSION_AUTH_NAME."@key"]);
        unset($_SESSION[SysConf::SESSION_AUTH_NAME."@cls"]);
        self::$tryRemember = true;
    }

    /** <p><b>IAuthUser継承インスタンス</b>よりキックされる。明示的に使用しない。<p> */
    public static function login(array $key, IAuthUser $user){
        self::$fetched = 0;
        self::$checked = 0;
        self::set($key, $user);
        $ret = self::check();
        return $ret;
    }
    /** <p>ログイン中のユーザをログアウトする。</p>*/
    public static function logout(IAuthUser $user = null) {
        if(func_num_args() === 0){ $user = self::getUser(); }
        if($user !== null && $user->isAuthenticated()){ $user->clear(); }
        self::set(null, null);
        self::clearRemember();
        return true;
    }
    
    private static function tryRemember(){
        try{
            if(self::$tryRemember === false){
                self::$tryRemember = true;
                if(Request::isGet()){
                    $rem = self::getRemember();
                    if($rem !== null && isset($rem["cl"]) && isset($rem["cred"])){
                        $cl = $rem["cl"];
                        $cred = $rem["cred"];
                        if(class_exists($cl) && !empty($cred)){
                            $user = new $cl(false);
                            $user->login($cred, true);
                            if(isset($_SESSION[SysConf::SESSION_AUTH_NAME."@key"])){ 
                                return $_SESSION[SysConf::SESSION_AUTH_NAME."@key"];
                            }else{
                            }
                        }
                    }
                }
                self::$checked = 2;
                self::$fetched = 1;
                self::clearRemember();
            }
        } catch (Exception $ex) {
            Log::error($ex);
        }
        return null;
    }
    
    /** <p>Remeember情報をセット</p>*/
    public static function setRemember(array $cred, $className){
        $json = json_encode(array("cl"=>$className, "cred"=>$cred));
        $enc = Secure::encrypt($json, Conf::REMEMBER_COOKIE_PASSWORD);
        Cookie::set(Conf::REMEMBER_COOKIE_NAME, $enc, (int)Conf::REMEMBER_EXPIRE_DAY * 3600 * 24);
    }
    /** <p>Remeember情報を取得</p>*/
    public static function getRemember(){
        $enc = Cookie::get(Conf::REMEMBER_COOKIE_NAME);
        if($enc === null) { return null; }
        $json = Secure::decrypt($enc, Conf::REMEMBER_COOKIE_PASSWORD);
        if($json === null) { return null; }
        return json_decode($json, true);
    }
    /** <p>Remeember情報の部分更新</p>*/
    public static function updateRemember($credKey, $value){
        $rem = self::getRemember();
        if($rem !== null){
            $cl = $rem["cl"];
            $params = $rem["cred"];
            if(class_exists($cl) && !empty($params)){
                $params[$credKey] = $value;
                self::setRemember($params, $cl);
            }
        }
    }
    /** <p>Remeember情報を削除</p>*/
    public static function clearRemember(){ Cookie::clear(Conf::REMEMBER_COOKIE_NAME); self::$tryRemember = true; }

    
    /** <p>2段階認証のキーを取得</p>*/
    public static function get2fa_key(){
        return Secure::toHash(session_id().Request::getRemoteAddr());
    }
    /** <p>2段階認証のトークンを取得</p>*/
    public static function get2fa_token(array $login_param, $tokenLen = 5, $expire_min = 30){
        $expire = strtotime(now()." +" . $expire_min . "min");
        $token = Secure::genPassword($tokenLen, true, false, false);
        $tfa = [
            "key" => self::get2fa_key(),
            "cerd" => $login_param,
            "expire" => $expire,
            "token" => $token,
        ];
        $_SESSION["auth_tfa"] = $tfa;
        return $token;
    }
    /** <p>2段階認証のキーが有効か否か</p>*/
    public static function valid2fa_key($tfa_key){
        if(!isset($_SESSION["auth_tfa"]) || empty($_SESSION["auth_tfa"])){ return false; }
        $tfa = $_SESSION["auth_tfa"];
        if($tfa["expire"] >= time() && $tfa["key"] === $tfa_key){
            return true;
        }
        return false;
    }
    /** <p>2段階認証のトークンが有効な場合、ログイン情報を返す</p>*/
    public static function valid2fa_Token($tfa_key, $token){
        if(!isset($_SESSION["auth_tfa"]) || empty($_SESSION["auth_tfa"])){
            return null;
        }
        $tfa = $_SESSION["auth_tfa"];
        if($tfa["expire"] >= time() && $tfa["key"] === $tfa_key && $tfa["token"] === $token){
            $cert = $tfa["cerd"];
            if(!empty($cert)){
                unset($_SESSION["auth_tfa"]);
                return $cert;
            }
        }
        return null;
    }
    
    /** <p>ログイン中ユーザがロールを所持しているか判定。複数していの場合はいずれか１つ保持している場合はtrueを返す</p>*/
    public static function hasRole(... $roles){
        $user = self::getUser();
        if($user === null) { return false; }
        if($user instanceof IAuthUser){
            $userRoles = $user->getRoles();
            if(StringUtil::isNotEmpty($userRoles)){
                if(is_array($userRoles)){
                    foreach($userRoles as $u){ if(self::hasRoleIn($roles, $u)){ return true;} }
                }else{
                    return self::hasRoleIn($roles, $userRoles);
                }
            }
            return self::hasRoleIn($roles, "");
        }
        return StringUtil::isEmpty($roles);
    }
    private static function hasRoleIn($roles,  $compare) {
        if(StringUtil::isEmpty($roles)) { return true; }
        if(is_array($roles)){
            foreach($roles as $r){ if(self::hasRoleIn($r, $compare)){ return true; }}
        }else{
            if($roles === $compare){ return true; }
        }
        return false;
    }
}

abstract class IAuthUser extends IData{
    /** <p>ログイン時に使用するSQLを記載してください。結果はSQL_FETCHの引数へ転送されます。</p><br><p>AUTH/FATCHの双方が正常に完了した場合、Auth::getKey()へ格納されます。</p> */
    abstract public static function GET_AUTH_KEY(array $cred);
    /** <p>該当クラスにマッピングフィールドデータを取得するSQLを記載してください。</p><br><p>AUTH/FETCHの双方が正常に完了した場合、Auth::getUser()へバインドした当インスタンスが格納されます。</p> */
    abstract public static function GET_FETCH_DATA(array $akey);
    
    /** <p>ロール取得処理のファンクションを記載してください。</p><p>例) return explode(',', $this->roles); </p> */
    abstract public function getRoles();
    
    /** <p>２段階認証の有無</p> */
    public function is2fa(){ return false; }

    private $_authed = false;
    private $_checked_2fa = false;
    
    /** <p><b>SQL_AUTH()</b>後のファンクションを記載してください。</p> */
    protected function after_auth(array $akey){}
    /** <p><b>SQL_FETCH()</b>後のファンクションを記載してください。</p> */
    protected function after_fetch(){}

    /** <p>ユーザ情報をクリアし、ログアウトを行います。</p> */
    public function clear() { parent::clear(); $this->_authed = false; Auth::logout($this); }
    
    /** <p>ユーザ情報をクリアし、ログアウトを行います。</p> */
    public function logout(){ $this->clear(); }
    
    /** <p>コンストラクタ</p> */
    public function __construct(){ if(Auth::check()){ $this->bind(Auth::getUser()); $this->_authed = true; } }
    /** <p>ログイン中判定</p> */
    public function isAuthenticated(){ return $this->_authed;}
    
    /** <p>ログイン処理を行います</p>
     * <p>1.引数の<b>$cred</b>は<b>GET_AUTH_KEY()</b>の引数へ転送します。</p>
     * <p>2.<b>GET_AUTH_KEY()</b>成功後、取得した連想配列は<b>GET_FETCH_DATA()</b>の引数へ転送します。</p>
     * <p>3.<b>AUTH/FETCH</b>の双方が正常に完了した場合、AUTHで取得した連想配列は<b>Auth::getKey()</b>へ, FETCHで取得した連想配列は当インスタンスにバインドされ<b>Auth::getUser()</b>へ格納されます。</p>*/
    public function login(array $cred, $remember = false) {
        $this->logout();
        try{
            $akey = static::GET_AUTH_KEY($cred);
            if(empty($akey)){ self::THROW_ERROR_REASON(); }
            if($akey instanceof IData){ $akey = $akey->toArray(); }
            
            $this->after_auth($akey);
            
            if(!$this->fetchData($akey)){ self::THROW_ERROR_REASON(); }
            
            //2段階認証
            if($this->_checked_2fa !== true && $this->is2fa()){
                return true;
            }
            
            $this->_authed = true;
            
            if(!Auth::login($akey, $this)){ self::THROW_ERROR_REASON(); }
            if($remember){ Auth::setRemember($cred, get_class($this)); }
            
            return true;
        } catch (Exception $ex) {
            Log::error($ex);
        }
        $this->logout();
        return false;
    }
    
    public function login_2fa($tfa_key, $token){
        $this->logout();
        $cred = Auth::valid2fa_Token($tfa_key, $token);
        if(!empty($cred)){
            $this->_checked_2fa = true;
            return $this->login($cred, false);
        }
        return false;
    }
    
    public function fetchData(array $akey){
        try{
            $data = static::GET_FETCH_DATA($akey);
            if(empty($data)){ self::THROW_ERROR_REASON(); }
            if($data instanceof IData){ $data = $data->toArray(); }
            $this->bind($data);
            $this->after_fetch();
            return true;
        } catch (Exception $ex) {
            Log::error($ex);
        }
        $this->logout();
        return false;
    }
    
    protected static function THROW_ERROR_REASON($reson = ""){
        if(!empty($reson)){ Message::addError($reson); }
        throw new UnauthorizedhException($reson);
    }
}

class Ssession{
    public static function get( $key, $nullVal = null){
        if(isset($_SESSION[$key]) && $_SESSION[$key] !== null){ return $_SESSION[$key]; }
        return $nullVal;
    }
    public static function set( $key, $val){
        $_SESSION[$key] = $val;
    }
    public static function isEmpty( $key) {
        return empty(self::get($key));
    }
}
class Cookie {
    private static $sv = array();
    public static function get( $key, $nullVal = null){
        if(isset(self::$sv[$key])){ return self::$sv[$key]; }
        
        if(isset($_COOKIE[$key]) && !empty($_COOKIE[$key])){
            return $_COOKIE[$key];
        }
        return $nullVal;
    }
    public static function set($key, $val, $time = 0){
        if($time == 0){
            setcookie($key ,$val, 0, self::PATH());
        }else{
            setcookie($key ,$val, time()+$time, self::PATH());
        }
        self::$sv[$key] = $val;
    }
    
    public static function PATH(){
        $path = substr(trim(Request::getScriptName(), SysConf::PUBROOT_SCRIPT_NAME), 0, -1);
        if(defined("COOKIE_PATH") && !empty(COOKIE_PATH)){
            return $path."/".COOKIE_PATH;
        }else{
            return $path."/";
        }
    }

    public static function clear($key){
        self::set($key, null, -1000);
        self::$sv[$key] = "";
    }
    public static function isEmpty($key) {
        return empty(self::get($key));
    }
}
?>