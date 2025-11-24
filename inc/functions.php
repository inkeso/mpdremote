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



// extend mpd-class with some custom stuff
class mpdplus extends mpd {
    var $mpmids = null;
    var $filesadded = 0;


    // This fails gracefully, if no MPMagic is running
    function getMPMids() {
        $buffer = "";
        $socket = @fsockopen(MPMAGICONF['host'],MPMAGICONF['port']);
        if ($socket) {
            fwrite($socket, "historyids");
            while (!feof($socket)) { $buffer.= fgets($socket, 8192); }
            fclose($socket);
        }
        $this->mpmids = explode(",",trim($buffer));
    }


    function addFromShuf($val) {
        if (is_null($this->mpmids)) $this->getMPMids();
        if (array_key_exists('Id', $val) && in_array($val['Id'], $this->mpmids)) $val['fromshuffle'] = true;
        return $val;
    }


    // get what's currently playing as named array
    function get_current() {
        $play = ($this->state);
        if ($play == MPD_STATE_PLAYING || $play == MPD_STATE_PAUSED) {
            $trinfo = $this->addFromShuf($this->playlist[$this->current_track_id]);
            $trinfo['Time'] = $this->current_track_length;
            $pos = $this->current_track_position;
        } else {
            $trinfo = array("Title" => "Silence...");
            $pos = 0;
        }
        // include playlist?
        // at least include next track, if any
        $nexttrack = Array();
        if (array_key_exists($this->current_track_id, $this->playlist))
            if (count($this->playlist) > $this->current_track_id+1)
                $nexttrack = $this->playlist[$this->current_track_id+1];
        return array(
                'trackinfo' => $trinfo,
                'time' => $pos,
                'state' => $play,
                'playlistcount' => $this->playlist_count,
                'next' => $nexttrack
        );
    }


    function get_playlist() {
        if ($this->current_track_id >= 0) {
            $this->playlist[$this->current_track_id]["currently"] = true;
        }
        return array_map(array($this, 'addFromShuf'), $this->playlist);
    }


    function get_search($search, $indir="") {
        function plfile($val) {
            return array_key_exists('file', $val) ? $val['file'] : '';
        }
        $listing = array();
        // TODO: 'any' sucht in jedem Tag. Vielleicht wollen wir auch im filename suchen?
        $resi = $this->Search(MPD_SEARCH_ANY, $search, $indir);
        if (!is_null($resi) && isset($resi['files'])) {
            $plfiles = array_map("plfile", $this->playlist);
            foreach ($resi['files'] as $k => $v) {
                $inpl = array_search($v['file'], $plfiles);
                // relative to current song
                if ($inpl !== false) {
                    $resi['files'][$k]['inplaylist'] = $inpl - $this->current_track_id;
                }
            }
            return $resi['files'];
        } else {
            return array();
        }
    }


    function get_plfiles() {
        function plfile($val) {
            return array_key_exists('file', $val) ? $val['file'] : '';
        }
        return array_map("plfile", $this->playlist);
    }


    function get_dir($path) {
        $plfiles = $this->get_plfiles();
        $dir = array("directories"=>[], "files"=>[]);
        $dirfetch = false;
        if ($path == "") $dirfetch = true;

        //precheck
        $supdir = $this->GetDir(implode("/",explode("/", $path, -1)));
        if ($supdir && isset($supdir['directories']) && in_array($path, $supdir['directories'])) $dirfetch = true;

        if ($dirfetch) {
            $dir = $this->GetDir($path);
            if ($dir) {
                // Check if file is in playlist
                foreach ($dir['files'] as $k => $v) {
                    $inpl = array_search($v['file'], $plfiles);
                    if ($inpl !== false) {
                        $dir['files'][$k]['inplaylist'] = $inpl - $this->current_track_id;
                    }
                }
                // fix directories-array (sorting)
                $dir['directories'] = array_values($dir['directories']);
            }
        }

        //  **** Special Folder Streams, see streams.php -- ********************
        if ($path == "streams") { // add bookmarked streams & podcasts
            foreach(streambookmarks() as $k => $v) {
                $inpl = array_search($v['file'], $plfiles);
                if ($inpl !== false) $v['inplaylist'] = $inpl - $this->current_track_id;
                $dir['files'][] = $v;
            }
            foreach(podcast() as $k => $v) {
                $dir['directories'][] = "streams/".$v;
            }
        }

        if (strpos($path, "streams/") === 0 && !$dirfetch) { // virtual folder
            $name = explode("/", $path, 2)[1];
            foreach(podcast($name) as $k => $v) {
                $inpl = array_search($v['file'], $plfiles);
                if ($inpl !== false) $v['inplaylist'] = $inpl - $this->current_track_id;
                $dir['files'][] = $v;
            }
        }
        //  ****************************************************************

        //  **** Special Folder NEU ****************************************
        if ($path == "NEU") { // show recently added songs
            $dir['directories'][] = "NEU/... 30 Tage";
            $dir['directories'][] = "NEU/... 180 Tage";
            $dir['directories'][] = "NEU/... 360 Tage";
        }

        if (strpos($path, "NEU/") === 0 && !$dirfetch) { // virtual folder
            $name = explode("/", $path, 2)[1];
            $days = preg_replace("/... ([0-9]+) Tage/", "\\1", $name);
            $resi = $this->Recent(time()-60*60*24*$days);
            if (!is_null($resi) && isset($resi['files'])) {
                foreach ($resi['files'] as $k => $v) {
                    $inpl = array_search($v['file'], $plfiles);
                    // relative to current song
                    if ($inpl !== false) {
                        $resi['files'][$k]['inplaylist'] = $inpl - $this->current_track_id;
                    }
                }
                // sort resi['files'] by "Last-Modified" descending
                function cmp($a, $b) {
                    return -strcmp($a["Last-Modified"], $b["Last-Modified"]);
                }
                $recfi = $resi['files'];
                usort($recfi, "cmp");
                $dir['files'] = $recfi;
            }
        }
        //  ****************************************************************

        return($dir);
    }


    function get_albumart() {
        $data = "";
        if (array_key_exists($this->current_track_id, $this->playlist)) {
            $fina = $this->playlist[$this->current_track_id]['file'];
            $data = $this->GetCover($fina);
        }
        if ($data) {
            // Well yeah, there may be BMP or TIFF, but we don't take care of them (yet?)
            if (substr($data,1,3) == "PNG") {
                header('Content-type: image/png');
            } else {
                header('Content-type: image/jpeg');
            }
            echo $data;
        } else {
            header('Content-type: image/png');
            if(class_exists("Imagick")) {
                // lieber ein img mit den restlichen meta-infos generieren
                $txt = "";
                $not = array('file', 'duration', 'Time', 'Artist', 'Title', 'Album', 'fromshuffle', 'Id', 'Pos');
                $gmax = 0;
                $tid = $this->current_track_id;
                if (array_key_exists($tid, $this->playlist)) {
                    $arr = $this->playlist[$tid];
                    foreach($arr as $k => $v) {
                        if (strlen($k) > $gmax) $gmax = strlen($k);
                    }
                    foreach($arr as $k => $v) {
                        if (!in_array($k, $not)) $txt .= sprintf("%".$gmax."s : %s\n", $k, $v);
                    }
                }
                $img = new Imagick();
                $img->newImage(256, 256, new ImagickPixel("none"));
                // oder farbe oder muster nach albumname?
                //$img->newImage(256, 256, new ImagickPixel("#".crc32($arr["Album"])));
                $drw = new ImagickDraw();
                $drw->setFillColor('#FFFFFF30');
                $drw->setFont('skins/Fix6x13.ttf');
                $drw->setFontSize(13);
                $img->annotateImage($drw, 1, 10, 0, $txt);
                $img->setImageFormat('png');
            } else {
                $img = readfile("skins/albumicon.png");
            }
            return $img;
        }
    }


    // add a single file. or a dir full of files. Or an array with files.
    // mpd can recurse dirs itself. but the order is messed up. *sigh*
    // Doing own recursion and adding each file seperately is slower.
    // It may timeout, when adding large folders (increasing php-script-
    // timeout is an option...) So max. amout of files to add is limited.
    function addfiles($fi) {
        // get length of playlist. Tracks are added at the end, so this will
        // be the first PLID to move.
        $start_id = count($this->playlist);
        $this->filesadded = 0;

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
            $this->PLLoad($matches[1]."?".SOUNDCLOUD_PLAYLISTFORMAT."&scpl=".$matches[2]);
        } else {
            $this->addfiles_do($fi);
        }

        // get length of playlist again. Since all the songs are added now,
        // the last index is the last PLID to move.
        $pl = $this->playlist;
        $stop_id = count($pl)-1;

        // Now check for songs after current. If they are user-added, new
        // tracks are moved AFTER them.
        if (is_null($this->mpmids)) $this->getMPMids();
        $curt = $this->current_track_id + 1;
        while ((!in_array($pl[$curt]['Id'], $this->mpmids)) && ($curt < (count($pl) - 1))) $curt += 1;
        if ($curt < (count($pl) - 1)) { // move new song(s) to $curt
            for ($i = $start_id; $i <= $stop_id; $i++)
                $this->PLMoveTrack($i, $curt + $i - $start_id);
                // TODO: try this via CommandQueue. It probably won't work.
                // (see above)
        }
        return $stop_id - $start_id + 1;
    }


    function addfiles_do($fi) {
        // Add single file or whole dir
        if (is_string($fi)) {
            // what about streams?
            if (preg_match('#^http(s?)://.*#', $fi) === 1) {
                $this->filesadded += 1;
                if ($this->filesadded > MAXFILESADD) return;
                $this->PLAdd($fi);
            } else {
                $dir = $this->GetDir($fi); // yes, this works on files as well
                foreach($dir['directories'] as $k=>$v) $this->addfiles_do($v);
                foreach($dir['files'] as $k=>$v) {
                    $this->filesadded += 1;
                    if ($this->filesadded > MAXFILESADD) return;
                    $this->PLAdd($v['file']);
                }
            }
        }

        // Add array of files
        if (is_array($fi)) {
            // $this->PLAddBulk($fi);                   // Q: why not?
            foreach($fi as $k=>$v) {
                $this->filesadded += 1;
                if ($this->filesadded > MAXFILESADD) return;
                $this->PLAdd($v);
            }
        }
        // A: bulkadd is queued, so moving afterwards doesn't work, since the
        // playlist isn't modified yet (I suppose). I didn't find a way to
        // wait for the queue to finish. Most likely something else is going on.
    }
}


// simple wrapper for class instanciation
function connect($debug=false) {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        die("<big><strong>MPD Connection failed</strong></big><br/>".$errstr."\n");
    }, E_WARNING);
    $mpd = new mpdplus(MPDCONFIG['host'],MPDCONFIG['port'],MPDCONFIG['password'], $debug);
    restore_error_handler();
    return $mpd;
}

?>
