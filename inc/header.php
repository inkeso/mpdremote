<?php

require_once("config.php");
require_once("mpd.class.php");
require_once("functions.php");

session_start();
header("Cache-Control: no-cache");
header("Expires: -1");
header("Content-Type: text/html; charset=utf-8");

// some misc globals:
$skin = getSkin();
$mod  = maymod();

// avoid magic_quotes
if (get_magic_quotes_gpc()) {
    function stripslashes_gpc(&$value)
    {
        $value = stripslashes($value);
    }
    array_walk_recursive($_GET, 'stripslashes_gpc');
    array_walk_recursive($_POST, 'stripslashes_gpc');
    array_walk_recursive($_COOKIE, 'stripslashes_gpc');
    array_walk_recursive($_REQUEST, 'stripslashes_gpc');
}

?>
