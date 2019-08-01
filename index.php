<?php
/*
 *      index.php
 *      
 *      Copyright 2012 Eloi Maelzer <maelzer@gmx.de>
 *      
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.
 */ 

// for session and so on
require_once ("inc/header.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["token"])) $_SESSION['token'] = $_POST["token"];
    if (isset($_POST["usr"]) && isset($_POST["pw"]) && array_key_exists($_POST["usr"], $users)) {
        $hash = $users[$_POST["usr"]];
        if ($hash === crypt($_POST["pw"], $hash)) {
            // store user and hashed PW in Session
            $_SESSION['usr'] = $_POST["usr"];
            $_SESSION['pwd'] = $hash;
        }
    }
    header ("Location: index.php");
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>mpd:re:mote</title>
    <meta http-equiv="Cache-Control" content="must-revalidate" />
    <meta http-equiv="Cache-Control" content="no-cache" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta http-equiv="Expires" content="-1" />
    <meta name="viewport" content="width=device-width, user-scalable=no">
    <LINK REL="SHORTCUT ICON" HREF="mpdremote2.png">
    <script type="text/javascript" src="logic.js"></script>

    <link rel="stylesheet" type="text/css" href="skins/layout.css" />
    <link rel="stylesheet" type="text/css" href="skins/<?php echo $skin; ?>.css" />
</head>
<body>
    <!-- i'm so beta -->
    <div id="beta" style="position:fixed; z-index:-10; width:100%; height:100%; margin:0; padding:0; border:none; 
                background-image:url(skins/beta.png); background-repeat:no-repeat; background-position:center 40px;"></div>
    <!-- remove when grown up lol -->

    <div id="dimmer">Moment...</div>
    <div id="topnav">
        <button id="controls_tab" onclick="dispatch('controls.php')">Controls</button><button id="playlist_tab" onclick="dispatch('playlist.php')">Playlist</button><button id="add_tab" onclick="dispatch('add.php')">Add Song</button>
    </div>
    <div id="inner"></div>
</body>
<script type="text/javascript">
    // initial dispatch, yo
    dispatch("controls.php");
</script>
</html>
