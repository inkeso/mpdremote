<?php

// simple wrapper for "print json"
function jout($arr) {
    header("Content-Type: text/json; charset=utf-8");
    print(json_encode($arr, JSON_PRETTY_PRINT));
}

// set skin and return its CSS
function getSkin() {
    if (!isset($_SESSION["skin"])) $_SESSION["skin"] = "default";
    if (isset($_GET["skin"])) $_SESSION["skin"] = $_GET["skin"];
    if (!file_exists("skins/".$_SESSION["skin"].".css")) $_SESSION["skin"] = "default";
    return $_SESSION["skin"];
}

// Is Playlistmodification / Skipping allowed?
function maymod() {
    /* Authentication is three-fold. Return values are:
    0 - not authorized
    1 - Token is valid (guest user)
    2 - Lokal IP
    3 - Logged in user
    */
    $users = ACCESS['users'];
    $allowIP = ACCESS['allowIP'];

    if (isset($_SESSION['usr']) && array_key_exists($_SESSION['usr'], $users)) return 3;
    if (preg_match($allowIP, getenv('REMOTE_ADDR'))) return 2;
    if (array_key_exists('token', $_SESSION) && checkToken($_SESSION['token'])) return 1;
    return 0;
}

function createToken($duration) {
    $characters='abcdefghijklmnopqrstuvwxyz1234567890';
    $length=10;
    $chars_length = strlen($characters)-1;
    mt_srand((double)microtime()*1000000);
    $token = '';
    while(strlen($token) < $length){
        $rand_char = mt_rand(0, $chars_length);
        $token .= $characters[$rand_char];
    }
    file_put_contents("tokens/".$token, "Token valid for ".$duration);
    return $token;
}

function checkToken($token) {
    if (!$token) return FALSE;
    $tfile = 'tokens/'.$token;
    if (!file_exists($tfile)) return FALSE;
    // read token-line
    $tline = file_get_contents($tfile);

    if (strpos($tline, " for ")) {
        $duration = substr($tline, strpos($tline, " for ")+5);
        file_put_contents($tfile, "Token valid until ".(time()+$duration));
        return humanTime($duration);
    }

    if (strpos($tline, " until ")) {
        $valid = substr($tline, strpos($tline, " until ")+7)+0;
        if ($valid > time()) {
            return humanTime($valid-time());
        } else {
            unlink($tfile);
            $_SESSION = array();
            session_destroy();
            return FALSE; // token timed out
        }
    }
    return FALSE; // invalid token-file
}

// need this in JS as well... actually, it's not used in any php-file...
function humanTime($sec) {
    $h = intval($sec / 3600);
    $m = intval(($sec - $h*3600) / 60);
    $s = $sec % 60;
    if ($h>0)
        return sprintf ("%d:%02d:%02d", $h, $m, $s);
    else
        return sprintf ("%d:%02d", $m, $s);
}

function fromHumanTime($str) {
    $smh = array_reverse(explode(":",$str));
    $s = intval($smh[0]);
    if (isset($smh[1])) $s += 60*intval($smh[1]);
    if (isset($smh[2])) $s += 60*60*intval($smh[2]);
    return $s;
}

// This fails gracefully, if no MPMagic is running
function getMPMids() {
    $buffer = "";
    $socket = @fsockopen(MPMAGICONF['host'],MPMAGICONF['port']);
    if ($socket) {
        fwrite($socket, "historyids");
        while (!feof($socket)) { $buffer.= fgets($socket, 8192); }
        fclose($socket);
    }
    return explode(",",trim($buffer));
}

// simple wrapper for class instanciation
function connect($debug=false) {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        die("<big><strong>MPD Connection failed</strong></big><br/>".$errstr."\n");
    }, E_WARNING);
    $mpd = new mpd(MPDCONFIG['host'],MPDCONFIG['port'],MPDCONFIG['password'], $debug);
    restore_error_handler();
    return $mpd;
}

// add a single file. or a dir full of files. Or an array with files.
// mpd can recurse dirs itself. but the order is messed up. *sigh*
// Doing own recursion and adding each file seperately is slower.
// It may timeout, when adding large folders (increasing php-script-
// timeout is an option...) So max. amout of files to add is limited.

function addfiles($mpd, $fi) {
    // get length of playlist. Tracks are added at the end, so this will
    // be the first PLID to move.
    $start_id = count($mpd->playlist);

    // really add them

    // soundcloud via plugin:
    // https://github.com/MusicPlayerDaemon/MPD/blob/master/src/playlist/plugins/SoundCloudPlaylistPlugin.cxx
    //
    // It is broken at the moment. We use our own streamproxy. Which is lacking metadata :/
    // So we use a Playlist plugin to kind of fix this:

    // transform direct track-links
    if (strpos($fi, "https://soundcloud.com") === 0) {
        require_once("soundcloud.php");
        $trackid = sc_resolve($fi)->id;
        // all executable scripts are at basepath, so this works:
        $baseuri = substr($_SERVER['SCRIPT_URI'], 0, strrpos($_SERVER['SCRIPT_URI'],"/"));
        $fi = $baseuri."/scproxy.php?scid=$trackid";
    }

    // check for soundcloud-proxy links and load as playlist (scid → scpl)
    $scpl = preg_match("#(http.*/scproxy.php)\\?scid=([0-9]+)#", $fi, $matches);
    if ($scpl) {
        require_once("soundcloud.php");
        $mpd->PLLoad($matches[1]."?".SOUNDCLOUD_PLAYLISTFORMAT."&scpl=".$matches[2]);
    } else {
        addfiles_do($mpd, $fi);
    }

    // get length of playlist again. Since all the songs are added now,
    // the last index is the last PLID to move.
    $pl = $mpd->playlist;
    $stop_id = count($pl)-1;

    // Now check for songs after current. If they are user-added, new
    // tracks are moved AFTER them.
    $shuffled = getMPMids();
    $curt = $mpd->current_track_id + 1;
    while ((!in_array($pl[$curt]['Id'], $shuffled)) && ($curt < (count($pl) - 1))) $curt += 1;
    if ($curt < (count($pl) - 1)) { // move new song(s) to $curt
        for ($i = $start_id; $i <= $stop_id; $i++)
            $mpd->PLMoveTrack($i, $curt + $i - $start_id);
            // TODO: try this via CommandQueue. It probably won't work.
            // (see above)
    }
    return $stop_id - $start_id + 1;
}

$filesadded = 0;
function addfiles_do($mpd, $fi) {
    global $filesadded;

    // Add single file or whole dir
    if (is_string($fi)) {
        // what about streams?
        if (preg_match('#^http(s?)://.*#', $fi) === 1) {
            $filesadded += 1;
            if ($filesadded > MAXFILESADD) return;
            $mpd->PLAdd($fi);
        } else {
            $dir = $mpd->GetDir($fi); // yes, this works on files as well
            foreach($dir['directories'] as $k=>$v) addfiles_do($mpd, $v);
            foreach($dir['files'] as $k=>$v) {
                $filesadded += 1;
                if ($filesadded > MAXFILESADD) return;
                $mpd->PLAdd($v['file']);
            }
        }
    }

    // Add array of files
    if (is_array($fi)) {
        // $mpd->PLAddBulk($fi);                   // Q: why not?
        foreach($fi as $k=>$v) {
            $filesadded += 1;
            if ($filesadded > MAXFILESADD) return;
            $mpd->PLAdd($v);
        }
    }
    // A: bulkadd is queued, so moving afterwards doesn't work, since the
    // playlist isn't modified yet (I suppose). I didn't find a way to
    // wait for the queue to finish. Most likely something else is going on.
}


?>
