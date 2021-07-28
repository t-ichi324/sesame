<?php
namespace Seo;

class RobotsTxtGenelator{
    const __USER_AGENT = 'User-agent: ';
    const __ALLOW = 'Allow: ';
    const __DISALLOW = 'Disallow: ';
    const __SITEMAP = 'Sitemap: ';
    private $dir = array();
    private $sm = array();
    
    
    public function disallowAll($userAgent = "*"){
        $ua = \StringUtil::defaultVal($userAgent, "*");
        $this->dir[$ua]["d"][] = "/";
    }
    public function disallow($dir = "/", $userAgent = "*"){
        $ua = \StringUtil::defaultVal($userAgent, "*");
        $this->dir[$ua]["d"][] = \StringUtil::defaultVal($dir, "/");
    }
    
    public function allow($dir = "/", $userAgent = "*"){
        $ua = \StringUtil::defaultVal($userAgent, "*");
        $this->dir[$ua]["a"][] = \StringUtil::defaultVal($dir, "/");
    }
    
    public function sitemap($file){
        $this->sm[] = \Url::get($file);
    }
    
    public function ResponseTxt($expires_sec = 86400){
        if($expires_sec !== null){ \Response::setCacheExpires($expires_sec);  }
        $sb = new \StringBuilder();
        foreach($this->dir as $ua => $dirs){
            $sb->append(self::__USER_AGENT)->appendLine($ua);
            if(isset($dirs["d"])){
                foreach($dirs["d"] as $dir){
                    $sb->append(self::__DISALLOW)->appendLine($dir);
                }
            }
            if(isset($dirs["a"])){
                foreach($dirs["a"] as $dir){
                    $sb->append(self::__ALLOW)->appendLine($dir);
                }
            }
        }
        foreach($this->sm as $url){
            $sb->append(self::__SITEMAP)->appendLine($url);
        }
        return \Response::text($sb->toString());
    }
}