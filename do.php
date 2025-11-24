<?php
require_once ("inc/header.php");

if(maymod() == 0) die('["Not allowed"]');


$mpclient = connect();

// find playlist-index based on Id
// probably based on file is an option as well...
function findIdx($id, $playlist) {
    $pid = -1;
    foreach ($playlist as $k => $v) {
        if ($v["Id"] == $id) {
            $pid = $k;
            break;
        }
    }
    return $pid;
}

function findFile($file, $playlist) {
    $pid = -1;
    foreach ($playlist as $k => $v) {
        if ($v["file"] == $file) {
            $pid = $k;
            break;
        }
    }
    return $pid;
}

switch (count($_GET) ? array_keys($_GET)[0] : "unknown") {
    case 'prev':
        $mpclient->Previous();
        break;

    case 'next':
        $mpclient->Next();
        break;

    case 'pause':
        // if not playing: play.
        if ($mpclient->state != MPD_STATE_PLAYING) {
            $mpclient->Play();
        } else {
            $mpclient->Pause();
        }
        break;

    case 'skip':
        $to = floatval($_GET['skip']);
        $mpclient->SeekTo($to * ($to <= 1 ? $mpclient->current_track_length : 1));
        break;

    case 'go':
        $pid = findIdx($_GET['go'], $mpclient->playlist);
        if ($pid >= 0) $mpclient->SkipTo($pid);
        if ($mpclient->state != MPD_STATE_PLAYING) $mpclient->Play();
        break;

    case 'rm':
        $pid = findIdx($_GET['rm'], $mpclient->playlist);
        if ($pid >= 0) $mpclient->PLRemove($pid);
        break;

    case 'rmfile':
        $pid = findFile($_GET['rmfile'], $mpclient->playlist);
        if ($pid >= 0) $mpclient->PLRemove($pid);
        break;

    case 'mv':
        $ft=explode(",",$_GET['mv']);
        $fid = findIdx($ft[0], $mpclient->playlist);
        $tid = findIdx($ft[1], $mpclient->playlist);
        $mpclient->PLMoveTrack($fid, $tid);
        break;

    case 'add':
        if (strlen($_GET['add']) > 3) $mpclient->addfiles($_GET['add']);
        break;

    case 'refresh':
        $mpclient->DBRefresh($_GET['refresh']);
        // block until ready. (there might a better way)
        while (strpos("updating_db", $mpclient->SendCommand(MPD_CMD_STATUS)) !== false) usleep(250000);
        break;

    default:
        $mpclient->errStr = "unknown";
}

jout($mpclient->errStr);

$mpclient->Disconnect();
