<?php
class RenderFunc extends IRenderFunc{
    public static function existsKeyword($key) {
        $pre = mb_substr($key, 0, 1);
        
        /*
         * [@] Common functions
         * [:] Meta functions
         * [*] Form functions / From::get()
         * [+] Model::get()
         * [/] url
         * [?] url-query
         */
        if($pre === "@" || $pre === ":" || $pre === "*" ||  $pre === "+" || $pre === "/" || $pre === "?"){
            return true;
        }
        return false;
    }
    
    private static function p2s($param){
        return '"'.(str_replace("[", '".',  str_replace("]",'."', trim($param)))).'"';
    }

    public static function call($key, $param) {
        if($key === '@code'){
            return '<?php '.$param.' ?>';
        }
        
        if ($key === '@each'){
            return '<?php foreach('.$param.'): ?>';
        }
        if ($key === '@endeach' || $key === '@end-each'){
            return '<?php endforeach; ?>';
        }
        
        if ($key === '@if'){
            return '<?php if('.$param.'): ?>';
        }
        if ($key === '@else'){
            return '<?php else: ?>';
        }
        if ($key === '@elseif' || $key === '@else-if'){
            return '<?php elseif ('.$param.'): ?>';
        }
        if ($key === '@endif' || $key === '@end-if' ){
            return '<?php endif; ?>';
        }
        if ($key === '@if-ajax'){
            return '<?php if(Request::isAjax()): ?>';
        }
        if ($key === '@if-not-ajax'){
            return '<?php if(!Request::isAjax()): ?>';
        }
        
        //url
        if($key == '@uri'){
            if(empty($param)){
                return '<?= htmlspecialchars(Request::getUri());?>';
            }
            return '<?= htmlspecialchars(Url::relative(Request::getUri(), '.self::p2s($param).'));?>';
        }
        if($key == '@url'){
            if(empty($param)){
                return '<?= htmlspecialchars(Request::getUrl());?>';
            }
            return '<?= htmlspecialchars(Url::get(Request::getUrl(), '.self::p2s($param).'));?>';
        }
        
        //Layout
        if ($key === '@layout') {
            $p = explode(",", $param);
            if(count($p) === 1){
                return '<?php Render::setLayout('.self::p2s($p[0]).');?>';
            }else{
                return '<?php if(!Request::isAjax()){ Render::setLayout('.self::p2s($p[0]).'); }else { Render::setLayout('.self::p2s($p[1]).'); }?>';
            }
        }
        //section
        if ($key === '@section' || $key === '@sec') {
            return '<?php $__ob_key = '.self::p2s($param).';ob_start(); ?>';
        }
        if ($key === '@endsection' || $key === '@end-section' || $key === '@endsec' || $key === '@end-sec') {
            return '<?php if($__ob_key !== null){ Render::$section[$__ob_key][] = ob_get_clean();} $__ob_key = null; ?>';
        }
        if ($key === '@section-yield' || $key === '@sec-yield' || $key === '@yield') {
            return '<?php Render::sectionYield('.self::p2s($param).');?>';
        }

        //require
        if ($key === '@require'){
            return '<?php Render::echoRequire('.self::p2s($param).');?>';
        }
        if ($key === '@require-ajax'){
            return '<?php if(Request::isAjax()){ Render::echoRequire('.self::p2s($param).'); } ?>';
        }
        if ($key === '@require-not-ajax'){
            return '<?php if(!Request::isAjax()){ Render::echoRequire('.self::p2s($param).'); } ?>';
        }
        
        // [:] META cuntions
        //require
        if ($key === ':require'){
            return '<?php Render::echoRequire(Meta::get_vprefix('.self::p2s($param).'));?>';
        }
        if ($key === ':require-ajax'){
            return '<?php if(Request::isAjax()){ Render::echoRequire(Meta::get_vprefix('.self::p2s($param).')); } ?>';
        }
        if ($key === ':require-not-ajax'){
            return '<?php if(!Request::isAjax()){ Render::echoRequire(Meta::get_vprefix('.self::p2s($param).')); } ?>';
        }
        
        
        if($key == ':title'){
            return "<?= htmlspecialchars(Meta::get_title());?>";
        }
        if($key == ':description' || $key === ':desc'){
            return "<?= htmlspecialchars(Meta::get_description());?>";
        }
        if($key == ':description-html' || $key === ':desc-html'){
            return "<?= StringUtil::toHtmlText(Meta::get_description());?>";
        }
        if($key == ':description-p' || $key === ':desc-p'){
            return "<?= StringUtil::toHtmlText(Meta::get_description(),'p');?>";
        }
        if($key == ':url'){
            return '<?= htmlspecialchars(Meta::get_url('.self::p2s($param).'));?>';
        }
        if($key == ':action' || $key === ':act' || $key == ':aurl'){
            return '<?= htmlspecialchars(Meta::get_action('.self::p2s($param).'));?>';
        }
        
        // [*] Form Function / From::get()
        if ($key === '*if-has-list'){
            return '<?php if(FormEcho::hasList()): ?>';
        }
        if ($key === '*each-list'){
            return '<?php foreach(FormEcho::getList() as '.$param.'): ?>';
        }
        
        if ($key === '*list-detail'){
            if(empty($param)){
                return '<?= FormEcho::listDetail(); ?>';
            }else{
                return '<?= FormEcho::listDetail('.$param.'); ?>';
            }
        }
        if ($key === '*' || mb_substr($key,0, 1) === "*"){
            $nm = str_replace('*', ' ', $key.' '.$param);
            $c = '<?php ';
            foreach(explode(' ', $nm) as $v){
                if(empty($v)) continue;
                $c .= 'FormEcho::text('.self::p2s($v).');';
            }
            $c .= '?>';
            return $c;
        }
        
        // [+] Model::get()
        if ($key === '+' || mb_substr($key,0, 1) === "+"){
            $nm = str_replace('+', ' ', $key.' '.$param);
            $c = '<?php ';
            foreach(explode(' ', $nm) as $v){
                if(empty($v)) continue;
                $c .= 'echo htmlspecialchars(Model::get('.self::p2s($v).'));';
            }
            Model::get();
            $c .= '?>';
            return $c;
        }
        
        //url
        if (mb_substr($key,0, 1) === "/"){ return '<?= Url::get('.self::p2s($key).');?>'; }
        
        //query
        if (mb_substr($key,0, 1) === "?"){ return '<?= Url::queryString('.self::p2s($key).');?>'; }
        
        // Add
        if ($key == '@file'){
            return '<?php if(file_exists('.self::p2s($param).')){ readfile('.self::p2s($param).'); } ?>';
        }
        if ($key == '@file-br'){
            return '<?php if(file_exists('.self::p2s($param).')){ \$__fg = fopen('.self::p2s($param).', "r"); while(!feof(\$__fg)){ echo htmlspecialchars(fgets(\$__fg)).<br>; } fclose(\$__fg); } ?>';
        }
        
        return '<?= htmlspecialchars('.mb_substr($key, 1).$param.');?>';
    }
}
?>