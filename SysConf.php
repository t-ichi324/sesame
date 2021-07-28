<?php
class SysConf {
    const IGNORE_EXTS = array("html", "htm", "php");
    const CSRF_NAME = "_csrf_token";
    const SESSION_CSRF_NAME = "_csrf_token";
    const SESSION_AUTH_NAME = "_AUTH_";
    const SESSION_REDIRECT_MODELS_NAME = "_REDIRECT_MODELS_";
    const X_REFRESH_PARAM_NAME = "x-refresh-param";
    
    const PUBROOT_SCRIPT_NAME = "index.php";

    const DIR_APP_CONTROLLER = "controller";
    const DIR_APP_VIEW = "view";
    const DIR_APP_MODEL = "model";
    
    const DIR_DB_ENTITY = "entity";
    const DIR_DB_SQL = "sql";
    
    const DIR_CACHE = "cache";
    const DIR_CACHE_VIEW = "view";
    const DIR_CACHE_ROUTE = "route";
    const DIR_TMP_DOWNLOAD = "download";
    
    const SUFFIX_CONTROLLER = "Controller";
    const SUFFIX_VIEW = ".html";
    const EXT_CONTROLLER = ".php";
    const EXT_VIEW = ".php";
    
    const DEFAULT_CLASS = "Index";
    const DEFAULT_FUNC = "index";
    const DEFAULT_VIEW = "index";
    
    const DEFAULT_ERROR_ROUTEING = "Error";
    const USE_NAMESPACE = false;
    
    const ROUTE_MAP_CACHE = "0.json";
    const ROUTE_PREFIX_REQUITE = "!";
    const ROUTE_PREFIX_OPTIONAL = "?";
    const ROUTE_PREFIX_ATTR = "@";
    
    const RES_KEY = "-k";
    const RES_HEAD = "-h";
    const RES_BODY = "-b";
    const RES_STATUS = "-s";
    const RES_I_TEXT = "#t";
    const RES_I_JSON = "#j";
    const RES_I_FILE = "#f";
    const RES_I_NOCONTENT = "#204";
    const RES_I_NOTFOUND = "#404";
    const RES_I_SERVERERR = "#500";
    const RES_I_REDIRECT = "#rd";
    const RES_I_FOWARD = "#fw";
    
    const ENC_METHOD = "AES-256-CBC";
    
    //DOWNLOAD
    const STREAM_CHUNK = 1024;
    const STREAM_DELAY = 0;
}

function __AutoloadMap(){
    return [
        Path::app(SysConf::DIR_APP_MODEL),
        Path::lib(),
        Path::sesameVender(),
        Path::sesameLib(),
    ];
}
