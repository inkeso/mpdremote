<?php

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
    3 - Admin
    */
    global $users;
    global $allowIP;
    
    if (isset($_SESSION['usr']) && isset($_SESSION['pwd'])) {
        $validAdmin = (isset($users[$_SESSION['usr']]) && 
                      ($users[$_SESSION['usr']] === $_SESSION['pwd']));
        if ($validAdmin) return 3;
    }
    
    $validIP = preg_match($allowIP, getenv('REMOTE_ADDR'));
    if ($validIP) return 2;
    
    $validToken = checkToken($_SESSION['token']);
    if ($validToken) return 1;
    
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
        return hrTime($duration);
    }
    
    if (strpos($tline, " until ")) {
        $valid = substr($tline, strpos($tline, " until ")+7)+0;
        if ($valid > time()) {
            return hrTime($valid-time());
        } else {
            unlink($tfile);
            $_SESSION = array();
            session_destroy();
            return FALSE; // token timed out
        }
    }
    return FALSE; // invalid token-file
}

// Case insensitive Sort-function (currently unused --> natcasesort)
function cmp ($a, $b) {
    $tmp[0]=strtoupper($a);
    $tmp[1]=strtoupper($b);
    sort($tmp);
    return (strcmp(strtoupper($tmp[1]) , strtoupper($b))) ? 1 : -1;
}

// make a nice "##. Artist - Title"-String, even out of untagged files
// if $nonu is true, tracknumber won't be prepended
function mkTitle($val, $forceFile=false) {
    $title = $val['Artist']." - ".$val['Title'];
    
    // Show Tracknumber (by Tag)
    if (isset($val['Track']) && strlen($val['Track']) > 0) {
        $trn = explode("/", $val['Track']);
        $title = $trn[0] ." ".$title;
    }
    
    $file = $val['file'];
    if ($forceFile || (strlen($val['Title']) < 1) || ($val['Title'] == $file))
        // TODO: ersetze '-4' durch position des letzten punktes (erweiterung wegschneiden)
        $title = substr($file, strrpos($file,"/")+1, -4);
        // remove leading "00 "
        $title = preg_replace('/^00 /','',$title);

    return $title;
}

function hrTime($sec) {
    $h = intval($sec / 3600);
    $m = intval(($sec - $h*3600) / 60);
    $s = $sec % 60;
    if ($h>0)
        return sprintf ("%d:%02d:%02d", $h, $m, $s);
    else
        return sprintf ("%d:%02d", $m, $s);
}

function getMPMids() {
    $buffer = "";
    $socket = @fsockopen("127.0.0.1",55443);
    if ($socket) {
        fwrite($socket, "historyids");
        while (!feof($socket)) { $buffer.= fgets($socket, 8192); }
        fclose($socket);
    }
    return explode(",",trim($buffer));
}


// add a single file. or a dir full of files. Or an array with files.
// mpd can recurse dirs itself. but the order is messed up. *sigh*
// Doing own recursion and adding each file seperately is slower.
// It may timeout, when adding large folders (increasing php-script-
// timeout is an option...)
//
// Since every file is added separately, it may interferes with 
// autoplaylist, so we use a wrapper-function to deactivate it 
// temporally.
//
// actually this should not be needed, since tracks are always added 
// before shuffle. but it doesn't hurt.

function apl_service($on) {
    $stream = stream_socket_client("tcp://localhost:55443", $errno, $errstr);
    $res = false;
    if ($stream) {
        fwrite($stream, "config apl service ".($on ? "True" : "False"));
        stream_socket_shutdown($stream, STREAM_SHUT_WR); 
        $res = stream_get_contents($stream) == "True";
        fclose($stream);
    }
    return($res);
}

function addfiles($mpd, $fi) {
    apl_service(false);
    // get length of playlist. Tracks are added at the end, so this will
    // be the first PLID to move.
    $start_id = count($mpd->playlist);
    
    // really add them
    addfiles_do($mpd, $fi);
    
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
    apl_service(true);
    return $stop_id - $start_id + 1;
}

function addfiles_do($mpd, $fi) {
    // Add single file or dir.
    if (is_string($fi)) {
        // what about streams?
        if (substr($fi, 0, 7 ) === "http://") {
            $mpd->PLAdd($fi);
        } else {
            $dir = $mpd->GetDir($fi); // yes, this works on files as well
            foreach($dir['directories'] as $k=>$v) addfiles_do($mpd, $v);
            foreach($dir['files'] as $k=>$v) $mpd->PLAdd($v['file']);
        }
    }
    
    // Add array of files
    if (is_array($fi)) {
        // $mpd->PLAddBulk($fi);                   // Q: why not?
        foreach($fi as $k=>$v) $mpd->PLAdd($v);
    }
    // A: bulkadd is queued, so moving afterwards doesn't work, since the
    // playlist isn't modified yet (I suppose). I didn't find a way to 
    // wait for the queue to finish. Most likely something else is going on.
}

?>
