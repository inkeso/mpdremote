<?php
@session_start();  // make sure session is started, SOUNDCLOUD_CLIENT_ID will be cached there.

// get v2 API key
function get_scv2_key() {
    // extract all loaded javascript files from main page...
    $mainsite = file_get_contents("https://soundcloud.com/");
    $jsfiles = array();
    preg_match_all('|<script.+?src="(https://a-v2.+?\\.js)"|', $mainsite, $jsfiles);
    sort($jsfiles[1]); // sort because it seems the clientid is in "0-....js"
    // now check for aoccurance of client_id 
    foreach($jsfiles[1] as $js) {
        $clientid = array();
        if (preg_match_all('|client_id\\s*:\\s*"([0-9a-zA-Z]{32})"|', file_get_contents($js), $clientid)) {
            return $clientid[1][0];
        }
    }
    return null;
}

// Get API key and store it. It is valid for a few days, so no need to 
if (!array_key_exists("SOUNDCLOUD_CLIENT_ID", $_SESSION)) {
    $_SESSION["SOUNDCLOUD_CLIENT_ID"] = get_scv2_key();
    //trigger_error("Acquired Soundcloud Client ID: ".$_SESSION["SOUNDCLOUD_CLIENT_ID"], E_USER_NOTICE);
}

define("SOUNDCLOUD_API", "https://api-v2.soundcloud.com");
define("SOUNDCLOUD_PLAYLISTFORMAT", "rss");
define("SOUNDCLOUD_CLIENT_ID", $_SESSION["SOUNDCLOUD_CLIENT_ID"]);


function fetchjson($url) {
    $url = $url.(strpos($url,"?")!==false ? '&' : '?')."client_id=".SOUNDCLOUD_CLIENT_ID;
    return json_decode(file_get_contents($url, false));
}

function sc_resolve($url) {
    $stuff = fetchjson(SOUNDCLOUD_API.'/resolve?url='.urlencode($url));
    return $stuff;
}

function sc_tracklist($userid) {
    $tracks = fetchjson(SOUNDCLOUD_API."/users/$userid/tracks?limit=50");
    return $tracks->collection;
}

function sc_track($trackid) {
    $tracks = fetchjson(SOUNDCLOUD_API."/tracks/$trackid");
    return $tracks;
}

// Playback via MPD-playlist-plugin doesn't work at the moment...
function sc_stream($trackid) {
    $media = fetchjson(SOUNDCLOUD_API."/tracks/$trackid")->media->transcodings;
    // find "progressive" stream
    foreach ($media as $mx) {
        if ($mx->format->protocol == "progressive")
        break;
    }
    $surl = fetchjson($mx->url)->url;
    header('Content-Type: '.$mx->format->mime_type);
    readfile($surl, false);
}
