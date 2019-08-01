<?php

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
$allowIP = '/^(127\.0\.0\.1|192\.168\.1\.[0-9]{1,3})$/';

// a named array of users and their hashed passwords, which are allowed
// to control the player even from a non-local URL.

// example: admin / admin. Generate hashes in admin.php
$users = array(
    'admin' => '$2y$10$4Gn5jZs.J12B4gHJ88wCP.JpBkJUVsrfElh22GMRD0aIbXdGyUNBS'
);

?>
