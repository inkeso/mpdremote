<?php
require_once ("inc/header.php");
require_once ("inc/streams.php");

// add shuffle-info to playlist-items
$mpmids = getMPMids();
function addFromShuf($val) {
    global $mpmids;
    if (array_key_exists('Id', $val) && in_array($val['Id'], $mpmids)) $val['fromshuffle'] = true;
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
        $nexttrack = Array();
        if (array_key_exists($mpclient->current_track_id, $mpclient->playlist))
            if (count($mpclient->playlist) > $mpclient->current_track_id+1)
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
                $inpl = array_search($v['file'], $plfiles);
                if ($inpl !== false) $v['inplaylist'] = $inpl - $mpclient->current_track_id;
                $dir['files'][] = $v;
            }
        }
        //  ****************************************************************

        //  **** Special Folder NEU ****************************************
        if ($_GET['dir'] == "NEU") { // show recently added songs
            $dir['directories'][] = "NEU/... 180 Tage";
        }

        if (strpos($_GET['dir'], "NEU/") === 0 && !$dirfetch) { // virtual folder
            $name = explode("/", $_GET['dir'], 2)[1];
            $days = preg_replace("/... ([0-9]+) Tage/", "\\1", $name);
            $resi = $mpclient->Recent(time()-60*60*24*$days);
            if (!is_null($resi) && isset($resi['files'])) {
                foreach ($resi['files'] as $k => $v) {
                    $inpl = array_search($v['file'], $plfiles);
                    // relative to current song
                    if ($inpl !== false) {
                        $resi['files'][$k]['inplaylist'] = $inpl - $mpclient->current_track_id;
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

        jout($dir);
        break;

    case 'search':
        $listing = array();
        // 'any' sucht in jedem Tag. Vielleicht wollen wir auch im filename suchen?
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
            if(class_exists("Imagick")) {
                // lieber ein img mit den restlichen meta-infos generieren
                $txt = "";
                $not = array('file', 'duration', 'Time', 'Artist', 'Title', 'Album', 'fromshuffle', 'Id', 'Pos');
                $gmax = 0;
                $tid = $mpclient->current_track_id;
                if (array_key_exists($tid, $mpclient->playlist)) {
                    $arr = $mpclient->playlist[$tid];
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

            echo $img;
        }
        break;
}

$mpclient->Disconnect();
