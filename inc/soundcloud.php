<?php
require_once("config.php");

if (defined("SOUNDCLOUD_CLIENT_ID")) { // activate soundcloud-stuff
    if (defined("SOUNDCLOUD_CLIENT_SECRET")) {
        /* API v3, using OAuth */
        define("SOUNDCLOUD_API", "https://api.soundcloud.com");
        define("SOUNDCLOUD_PLAYLISTFORMAT", "m3u");
        if (!defined("SOUNDCLOUD_TOKENFILE")) {
            define("SOUNDCLOUD_TOKENFILE", "/tmp/soundcloud-token");
        }
        require_once("soundcloud_v3.php");
    } else {
        define("SOUNDCLOUD_API", "https://api-v2.soundcloud.com");
        define("SOUNDCLOUD_PLAYLISTFORMAT", "rss");
        require_once("soundcloud_v2.php");
    }
} else {
    die("Soundcloud support disabled (no API key set)");
}

