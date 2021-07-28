<?php
/**
 * @version "sesame-2.x"
 * 
REQUIRED PKG(Ubuntu)
sudo apt-get install mbstring
sudo apt-get install php-zip
sudo apt-get install php-json
sudo apt-get install php-xml
sudo apt-get install php-sqlite3
 */
class Sesame extends ISesameBase {
    /** Dispatch the programs.<br/> */
    public static function run(){
        try{
            try{
                try{
                    $result = \Bin\Handler::run();
                } catch (UnauthorizedhException $ex){
                    $result = \Bin\Handler::errorRun($ex, $ex->statusCode());
                    Log::accessError($ex);
                } catch (Exception $ex) {
                    if($ex instanceof IException){
                        if($ex->statusCode() >= 400 && $ex->statusCode() < 500){
                            Log::accessError($ex);
                        }else{
                            Log::error($ex);
                        }
                        $result = \Bin\Handler::errorRun($ex, $ex->statusCode());
                    }else{
                        $result = \Bin\Handler::errorRun($ex, 500);
                        Log::error($ex);
                    }
                }
            } catch (Exception $ex) {
                Log::error($ex);
                $result = null;
            }

            //Result
            self::runResult($result);

            Log::access();
        } catch (Exception $ex) {
            Log::access();
            Log::error($ex);
            http_response_code(500);
            require __X_FILE_ERR_HTML;
        }
    }
}

/** Lang */
function __($key, ... $args){
    return LangLocale::getText($key, ... $args);
}
?>