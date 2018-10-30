<?php
/*
 *      header.php
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
 
///////////////////////
// MPD-configuration //
///////////////////////

$host = 'localhost';
$port = 6600;
$password = null;

/////////////////////
// Access-controll //
/////////////////////

// IPs matching this RegExp are allowed to control the player without login.
$allowIP = '/^(127\.0\.0\.1|192\.168\.12\.[0-9]{1,3}|139\.30\.161\.208|139\.30\.73\.233)$/';

// a named array of users and their hashed passwords, which are allowed
// to control the player even from a non-local URL.
$users = array(
    'iks'  => '$1$Jy00m5T4$ezgwFQWyrQvNceX1PHWYk0',
    'jaye' => '$1$gaom564b$SODGxPuUhsu4gz3bZ1lx01'
);

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
