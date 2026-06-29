<?php
@session_start();  // make sure session is started, SOUNDCLOUD_CLIENT_ID will be cached there.

define("SOUNDCLOUD_PLAYLISTFORMAT", "m3u");

if (defined("SOUNDCLOUD_CLIENT") && defined("SOUNDCLOUD_SECRET")) {
    /// v3 API (needs API-key), preferred!
    // Register app: http://soundcloud.com/you/apps
    // Put Client ID and Secret in config.php.
    // → https://developers.soundcloud.com/docs/api/explorer/open-api
    // → https://developers.soundcloud.com/docs/api/guide#authentication

    if (!defined("SOUNDCLOUD_TOKENFILE")) {
        define("SOUNDCLOUD_TOKENFILE", "/tmp/soundcloud-token");
    }

    function fetchjson($url, $options=array()) {
        if (count($options) == 0) {  // Auto-Token
            $otok = sc_get_token();
            $options['header'] = "Authorization: OAuth $otok\r\n";
        }
        $context = stream_context_create(array('http' => $options));
        return json_decode(file_get_contents("https://api.soundcloud.com/$url", false, $context));
    }

    function sc_obtain_token() {
        /* returns:
        object(stdClass) {
          access_token  =>  string(33)
          expires_in    =>  int(3599)
          refresh_token =>  string(32)
          scope         =>  string(0)
          token_type    =>  string(6) "bearer"
        }
        */
        $options = array(
            'method'  => 'POST',
            'header'  => "accept: application/json; charset=utf-8\r\n".
                         "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(array(
                "grant_type" => "client_credentials",
                "client_id" => SOUNDCLOUD_CLIENT,
                "client_secret" => SOUNDCLOUD_SECRET
            ))
        );
        $tok = fetchjson('oauth2/token', $options);
        $tok->expires_in += time();
        return $tok;
    }

    function sc_refresh_token($refreshtoken) {
        /* return: same as sc_obtain_token() */
        $options = array(
            'method'  => 'POST',
            'header'  => "accept: application/json; charset=utf-8\r\n".
                         "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(array(
                "grant_type" => "refresh_token",
                "client_id" => SOUNDCLOUD_CLIENT,
                "client_secret" => SOUNDCLOUD_SECRET,
                "refresh_token" => $refreshtoken
            ))
        );
        $tok = fetchjson('oauth2/token', $options);
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

    function sc_resolve($url)   { return fetchjson('resolve?url='.urlencode($url)); }
    function sc_tracklist($uid) { return fetchjson("users/$uid/tracks"); }
    function sc_track($tid)     { return fetchjson("tracks/$tid"); }

    function sc_stream($trackid) {
        $strms = fetchjson("tracks/$trackid/streams");
        $otok = sc_get_token();
        $options = array('header' => "Authorization: OAuth $otok\r\n");
        $context = stream_context_create(array('http' => $options));
        readfile($strms->http_mp3_128_url, false, $context);
    }


} else {    /// v2-API with scraped key. Defunct due to captchas. [2026-01]

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

    if (!array_key_exists("SOUNDCLOUD_CLIENT_ID", $_SESSION)) {
        $_SESSION["SOUNDCLOUD_CLIENT_ID"] = get_scv2_key();
        //trigger_error("Acquired Soundcloud Client ID: ".$_SESSION["SOUNDCLOUD_CLIENT_ID"], E_USER_NOTICE);
    }
    define("SOUNDCLOUD_CLIENT_ID", $_SESSION["SOUNDCLOUD_CLIENT_ID"]);

    function fetchjson($url) {
        $url = $url.(strpos($url,"?")!==false ? '&' : '?')."client_id=".SOUNDCLOUD_CLIENT_ID;
        return json_decode(file_get_contents("https://api-v2.soundcloud.com/$url", false));
    }

    function sc_resolve($url) { return fetchjson('resolve?url='.urlencode($url)); }
    function sc_tracklist($userid) { return fetchjson("users/$userid/tracks?limit=50")->collection; }
    function sc_track($trackid) { return fetchjson("tracks/$trackid"); }

    function sc_stream($trackid) {
        $media = fetchjson("tracks/$trackid")->media->transcodings;
        // find "progressive" stream
        foreach ($media as $mx) {
            if ($mx->format->protocol == "progressive")
            break;
        }
        //$surl = json_decode(file_get_contents($mx->url, false)))->url;
        //header('Content-Type: '.$mx->format->mime_type);
        //readfile($surl, false);
        readfile($mx->url, false);
    }

}
