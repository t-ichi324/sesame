<?php
define("DB_CONFIG_CLASS","DbConf");
if(!defined("DB_LOWER_CASE_NAME")){ define("DB_LOWER_CASE_NAME", true); }

require_once __DIR__.DIRECTORY_SEPARATOR.'Adapter.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'DbQuery.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'DbXml.php';
