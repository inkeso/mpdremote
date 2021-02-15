<?php

require_once("config.php");
require_once("mpd.class.php");
require_once("functions.php");

session_start();
header("Cache-Control: no-cache");
header("Expires: -1");
header("Content-Type: text/html; charset=utf-8");

// allow login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["token"])) $_SESSION['token'] = $_POST["token"];
    if (isset($_POST["usr"]) && isset($_POST["pw"]) && array_key_exists($_POST["usr"], ACCESS["users"])) {
        $hash = ACCESS["users"][$_POST["usr"]];
        if (password_verify($_POST["pw"], $hash)) {
            // store user in Session
            $_SESSION['usr'] = $_POST["usr"];
        }
    }
}

?>
