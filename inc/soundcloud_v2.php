<?php

function fetchjson($url) {
    // append client id, hacky.
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
