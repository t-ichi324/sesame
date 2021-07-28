<?php
/**
 * Send Email
 */
class Mailer{
    public static function set_auth($smtp, $smtp_port, $auth_username, $auth_password){
        ini_set("smtp", $smtp);
        ini_set("smtp_port", $smtp_port);
        ini_set("auth_username", $auth_username);
        ini_set("auth_password", $auth_password);
    }
    public static function set_charaset($language = 'Japanese', $encoding = 'UTF-8'){
        mb_language($language); 
        mb_internal_encoding($encoding);
    }

    public static function send($fromAddress, $senderName, $toAddress ,$subject, $body, $cc = null, $bcc = null){
        $encFromName = mb_encode_mimeheader($senderName, "ISO-2022-JP-MS");
        $from = $encFromName. " <".$fromAddress.">";
        $header = '';
        $header .= "Content-Type: text/plain; charset=ISO-2022-JP \r\n";
        $header .= "Return-Path: ".$fromAddress." \r\n";
        $header .= "From: ".$from." \r\n";
        $header .= "Sender: ".$fromAddress." \r\n";
        
        if(!empty($cc)){
            $header.= "Cc: ";
            if(is_array($cc)){
                $header.= StringUtil::arrayToString($cc, ",");
            }else{
                $header.= $cc;
            }
            $header.=" \r\n";
        }
        if(!empty($bcc)){
            $header.= "Bcc: ";
            if(is_array($bcc)){
                $header.= StringUtil::arrayToString($bcc, ",");
            }else{
                $header.= $bcc;
            }
            $header.=" \r\n";
        }
        
        $header .= "Reply-To: " . $fromAddress . " \r\n";
        $header .= "Organization: ".$encFromName." \r\n";
        $header .= "X-Sender: " . $fromAddress . " \r\n";
        $header .= "X-Priority: 3 \r\n";
        
        try{
            if(Env::isDev()){
                self::email_log($toAddress, $subject, $body, $header, "dev");
            }else{
                if(!@mb_send_mail($toAddress, $subject, $body, $header)){
                    self::email_log($toAddress, $subject, $body, $header, "faild");
                    return false;
                }
                self::email_log($toAddress, $subject, $body, $header, "success");
            }
            return true;   
        } catch (Exception $ex) {
            self::email_log($toAddress, $subject, $body, $header, $ex->getMessage());
            return false;
        }
    }
    
    private static function email_log($toAddress, $subject, $body, $header, $attr){
        try{
            $dir = new DirectoryInfo(Path::tmp("email"));
            $dir->make();
            $name =  Util::fileNameNormalizer(now_fileName()."_".$toAddress,false).".log";
            $saveTo = Path::combine($dir->fullName(), $name);
            
            $t = "ATTR: ".$attr."\n\n";
            $t.= "DATE: ".date("Y-m-d H:i:s")."\n"; 
            $t.= "IP: ".Request::getRemoteAddr()."\n";
            $t.= "URI: ".Request::getUri()."\n\n";
            
            $t.= "HEAD:\n".$header."\n";
            $t.= "TO: ".$toAddress."\n";
            $t.= "SUBJECT: ".$subject."\n";
            $t.= "BODY:\n".$body."\n";
            file_put_contents($saveTo, $t);
            
            Log::info(["send-email" =>$name]);
        } catch (Exception $ex) {
            Log::error($ex);
        }
    }
}
?>