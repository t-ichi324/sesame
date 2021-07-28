<?php
/** 非同期のログの書き出し。第一引数へbase64可したメッセージ、第二引数へPath */
try{
    if(!empty($argv) && count($argv) > 2){
        $line = base64_decode($argv[1]);
        $file = $argv[2];
        error_log($line."\n", 3, $file);
    }else{
    }
} catch (Exception $ex) {}
?>