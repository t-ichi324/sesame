<?php
namespace Seo;
/**
 * Sitemap.xml
 */
class SitemapXmlGenelator {
    const __XML_DEF = '<?xml version="1.0" encoding="utf-8"?>';
    const __URLSET_HEADER = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    const __URLSET_FOOTER= '</urlset>';
    const __MAPI_HEADER = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    const __MAPI_FOOTER= '</sitemapindex>';
    private $urls = array();
    private $maps = array();
    public function addUrl($loc,  $priority = "0.8",  $changefreq = "monthly",  $lastmod = null){
        $url = array();
        $url["loc"] = \Url::get($loc);
        if(!empty($priority)){ $url["priority"] = $priority; }
        if(!empty($changefreq)){ $url["changefreq"] = $changefreq; }
        if(!empty($lastmod)){ $url["lastmod"] = $lastmod; }
        $this->urls[] = $url;
    }
    public function addMap( $loc, $lastmod = null){
        $url = array();
        $url["loc"] = $loc;
        if(!empty($lastmod)){ $url["lastmod"] = $lastmod; }
        $this->maps[] = $url;
    }
    //put your code here
    public function gen(){
        $urlset = '';
        $mapindex = '';
        if(!empty($this->urls)){
            foreach ($this->urls as $url){
                $urlset.="<url>";
                foreach ($url as $k => $v){
                    $urlset.="<$k>".$v."</$k>";
                }
                $urlset.="</url>"."\n";
            }
            $urlset = "\n".self::__URLSET_HEADER."\n".$urlset.self::__URLSET_FOOTER;
        }
        if(!empty($this->maps)){
            foreach ($this->maps as $url){
                $mapindex.="<sitemap>";
                foreach ($url as $k => $v){
                    $mapindex.="<$k>".$v."</$k>";
                }
                $mapindex.="</sitemap>"."\n";
            }
            $mapindex = "\n".self::__MAPI_HEADER."\n".$mapindex.self::__MAPI_FOOTER;
        }
        return self::__XML_DEF.$urlset.$mapindex;
    }
    
    public function ResponseXML($expires_sec = 86400){
        if($expires_sec !== null){ \Response::setCacheExpires($expires_sec); }
        return \Response::text($this->gen(), "text/xml");
    }
}
