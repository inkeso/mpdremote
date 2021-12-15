<?php

function fetchjson($url, $options=array()) {
    $context = stream_context_create(array('http' => $options));
    return json_decode(file_get_contents($url, false, $context));
}

// → https://developers.soundcloud.com/docs/api/guide#authentication

function sc_obtain_token() {
    /* return:
    object(stdClass)#1 (5) {
      ["access_token"]=>  string(33) "2-271083--enZ7HGyHDS45giVj9kulqeC"
      ["expires_in"]=>  int(3599)
      ["refresh_token"]=>  string(32) "XmCBf5YdFX1eHEglTqvrxtrmuuVjgNhp"
      ["scope"]=>  string(0) ""
      ["token_type"]=>  string(6) "bearer"
    }
    */

    $url = SOUNDCLOUD_API.'/oauth2/token';
    $options = array(
        'method'  => 'POST',
        'header'  => "accept: application/json; charset=utf-8\r\n".
                     "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(array(
            "grant_type" => "client_credentials",
            "client_id" => SOUNDCLOUD_CLIENT_ID,
            "client_secret" => SOUNDCLOUD_CLIENT_SECRET
        ))
    );
    $tok = fetchjson($url, $options);
    $tok->expires_in += time();
    return $tok;
}

function sc_refresh_token($refreshtoken) {
    /* return: same as sc_obtain_token() */
    $url = SOUNDCLOUD_API.'/oauth2/token';
    $options = array(
        'method'  => 'POST',
        'header'  => "accept: application/json; charset=utf-8\r\n".
                     "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(array(
            "grant_type" => "refresh_token",
            "client_id" => SOUNDCLOUD_CLIENT_ID,
            "client_secret" => SOUNDCLOUD_CLIENT_SECRET,
            "refresh_token" => $refreshtoken
        ))
    );
    $tok = fetchjson($url, $options);
    $tok->expires_in += time();
    return $tok;
}

function sc_get_token() {
    $tok = json_decode(@file_get_contents(SOUNDCLOUD_TOKENFILE));
    $now = time();
    $renew = 900; // refresh token, if less than 15 minutes remain.
    if ($tok != NULL && isset($tok->expires_in) && $tok->expires_in-$renew > $now) { // mucho valid.
        return $tok->access_token;
    } else if ($tok == NULL || !isset($tok->expires_in) || $tok->expires_in <= $now) { // no or invalid file or token too old.
        $tok = sc_obtain_token();
    } else if ($tok->expires_in-$renew < $now) { // about to expire
        $tok = sc_refresh_token($tok->refresh_token);
    }
    file_put_contents(SOUNDCLOUD_TOKENFILE, json_encode($tok, JSON_PRETTY_PRINT));
    return $tok->access_token;
}


function sc_resolve($url) {
    $otok = sc_get_token();
    $options = array('header' => "Authorization: OAuth $otok\r\n");
    $stuff = fetchjson(SOUNDCLOUD_API.'/resolve?url='.urlencode($url), $options);
    return $stuff;
}

function sc_tracklist($uid) {
    $otok = sc_get_token();
    $options = array('header' => "Authorization: OAuth $otok\r\n");
    $tracks = fetchjson(SOUNDCLOUD_API."/users/$uid/tracks", $options);
    return $tracks;
}

function sc_track($tid) {
    $otok = sc_get_token();
    $options = array('header' => "Authorization: OAuth $otok\r\n");
    $tracks = fetchjson(SOUNDCLOUD_API."/tracks/$tid", $options);
    return $tracks;
}

// Playback via MPD-playlist-plugin doesn't work at the moment...
function sc_stream($trackid) {
    $otok = sc_get_token();
    $options = array('header' => "Authorization: OAuth $otok\r\n");
    $context = stream_context_create(array('http' => $options));
    readfile(SOUNDCLOUD_API."/tracks/$trackid/stream", false, $context);
}
