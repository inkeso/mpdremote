<?php
require_once ("inc/header.php");
require_once ("inc/streams.php");

// add shuffle-info to playlist-items
$mpmids = getMPMids();
function addFromShuf($val) {
    global $mpmids;
    if (array_key_exists('Id', $val) && in_array($val['Id'], $mpmids)) $val['fromshuffle'] = true;

    // Monkeypatch title for soundcloud... Until we have a better extractor.
    $pattern = '#^https://cf-media.sndcdn.com/([^?]+)\?.*#';
    $val['Title'] = preg_replace($pattern, 'Soundcloud ${1}', array_key_exists('Title', $val) ? $val['Title'] : '');
    return $val;
}

function plfile($val) {
    return array_key_exists('file', $val) ? $val['file'] : '';
}

$mpclient = connect();

switch (count($_GET) ? array_keys($_GET)[0] : "current") {
    case 'current':
        $play = ($mpclient->state);
        if ($play == MPD_STATE_PLAYING || $play == MPD_STATE_PAUSED) {
            $trinfo = addFromShuf($mpclient->playlist[$mpclient->current_track_id]);
            $trinfo['Time'] = $mpclient->current_track_length;
            $pos = $mpclient->current_track_position;
        } else {
            $trinfo = array("Title" => "Silence...");
            $pos = 0;
        }
        // include playlist?
        // at least include next track, if any
        $nexttrack = "";
        if (array_key_exists($mpclient->current_track_id, $mpclient->playlist))
            $nexttrack = $mpclient->playlist[$mpclient->current_track_id+1];
        jout(array(
            'trackinfo' => $trinfo,
            'time' => $pos,
            'state' => $play,
            'playlistcount' => $mpclient->playlist_count,
            'next' => $nexttrack
        ));

        break;

    case 'playlist':
        if ($mpclient->current_track_id >= 0) {
            $mpclient->playlist[$mpclient->current_track_id]["currently"] = true;
        }
        jout(array_map("addFromShuf", $mpclient->playlist));
        break;

    case 'dir':
        $plfiles = array_map("plfile", $mpclient->playlist);
        $dir = array("directories"=>[], "files"=>[]);
        $dirfetch = false;
        if ($_GET['dir'] == "") $dirfetch = true;

        //precheck
        $supdir = $mpclient->GetDir(implode("/",explode("/", $_GET['dir'], -1)));
        if ($supdir && isset($supdir['directories']) && in_array($_GET['dir'], $supdir['directories'])) $dirfetch = true;

        if ($dirfetch) {
            $dir = $mpclient->GetDir($_GET['dir']);
            if ($dir) {
                // Check if file is in playlist
                foreach ($dir['files'] as $k => $v) {
                    $inpl = array_search($v['file'], $plfiles);
                    if ($inpl !== false) {
                        $dir['files'][$k]['inplaylist'] = $inpl - $mpclient->current_track_id;
                    }
                }
                // fix directories-array (sorting)
                $dir['directories'] = array_values($dir['directories']);
            }
        }

        //  **** Special Folder Streams, see streams.php -- ********************
        if ($_GET['dir'] == "streams") { // add bookmarked streams & podcasts
            foreach(streambookmarks() as $k => $v) {
                $inpl = array_search($v['file'], $plfiles);
                if ($inpl !== false) $v['inplaylist'] = $inpl - $mpclient->current_track_id;
                $dir['files'][] = $v;
            }
            foreach(podcast() as $k => $v) {
                $dir['directories'][] = "streams/".$v;
            }
        }

        if (strpos($_GET['dir'], "streams/") === 0 && !$dirfetch) { // virtual folder
            $name = explode("/", $_GET['dir'], 2)[1];
            foreach(podcast($name) as $k => $v) {
                $inpl = false;
                if (isset($v['soundcloud_track'])) {
                    $scgrep = preg_grep("/.+api\\.soundcloud\\.com\\/tracks\\/".$v['soundcloud_track']."\\/stream.*/", $plfiles);
                    if (count($scgrep) > 0) $inpl = array_keys($scgrep)[0];
                } else {
                    $inpl = array_search($v['file'], $plfiles);
                }

                if ($inpl !== false) $v['inplaylist'] = $inpl - $mpclient->current_track_id;
                $dir['files'][] = $v;
            }
        }
        //  ****************************************************************

        jout($dir);
        break;

    case 'search':
        $listing = array();
        // ANY sucht in jedem Feld. Vielleicht wollen wir lieber 4x suchen:
        // MPD_SEARCH_ARTIST, MPD_SEARCH_TITLE, MPD_SEARCH_ALBUM, MPD_SEARCH_FILENAME
        $dir = isset($_GET['indir']) ? $_GET['indir'] : "";
        $resi = $mpclient->Search(MPD_SEARCH_ANY, $_GET['search'], $dir);
        if (!is_null($resi) && isset($resi['files'])) {
            $plfiles = array_map("plfile", $mpclient->playlist);
            foreach ($resi['files'] as $k => $v) {
                $inpl = array_search($v['file'], $plfiles);
                // relative to current song
                if ($inpl !== false) {
                    $resi['files'][$k]['inplaylist'] = $inpl - $mpclient->current_track_id;
                }
            }
            jout($resi['files']);
        } else {
            jout(array());
        }
        break;

    case 'podcastnames':
        jout(podcast());
        break;

    case 'albumart':
        $data = "";
        if (array_key_exists($mpclient->current_track_id, $mpclient->playlist)) {
            $fina = $mpclient->playlist[$mpclient->current_track_id]['file'];
            $data = $mpclient->GetCover($fina);
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
            echo readfile("skins/albumicon.png");
        }
        break;
}

$mpclient->Disconnect();
