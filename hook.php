<?php

error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "error.log");

$botClass = $_GET["class"];
$botKey = $_GET["key"];
$input = json_decode(file_get_contents('php://input'),true);

if (!$botClass || !$botKey || !$input){
    error_log("Missing Class/Key/Input");
    die("Missing Class/Key/Input");
}

if (strpos($botClass, '/') !== FALSE){
    error_log("Illegal class location");
    die("Illegal class location");
}


set_include_path(get_include_path() . PATH_SEPARATOR . getcwd());
chdir($botClass);

include_once $botClass.".php";

if ( $botClass::getKey() != $botKey ) {
    error_log("Bot Key mismatch");
    error_log($botClass::getKey().",".$botKey);
    die("Bot Key mismatch");
}

$bot = new $botClass();
$bot->handleInput($input);

?>
