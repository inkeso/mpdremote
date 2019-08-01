<?php

require_once ("inc/header.php");

$mpclient = new mpd($host,$port,$password);

// Action request?
if (isset($_GET["action"]) && ($mod > 0)) {
    switch ($_GET["action"]) {
        case "prev":
            $mpclient->Previous();
            if ($mpclient->state != MPD_STATE_PLAYING) $mpclient->Play();
            break;
        case "pause":
            $mpclient->Pause();
            break;
        case "next":
            $mpclient->Next();
            if ($mpclient->state != MPD_STATE_PLAYING) $mpclient->Play();
            break;
        case "skip":
            $mpclient->SeekTo(intval($_GET['p'] * $mpclient->current_track_length));
            break;
    }
    $mpclient->RefreshInfo();
}

// playing or pausing? 
$play = ($mpclient->state == MPD_STATE_PLAYING)? "||" : "&gt;";

if ($mpclient->state == MPD_STATE_PLAYING || $mpclient->state == MPD_STATE_PAUSED) {
    $ctid  = $mpclient->current_track_id;
    $artst = $mpclient->playlist[$ctid]['Artist'];
    $track = $mpclient->playlist[$ctid]['Title'];
    $album = $mpclient->playlist[$ctid]['Album'];
    $filen = $mpclient->playlist[$ctid]['file'];
    if ($filen == $track) $track = substr($filen, strrpos($filen,"/")+1,-4);
    $cpos  = humanTime($mpclient->current_track_position);
    $clen  = humanTime($mpclient->current_track_length);
    $cperc = @intval(($mpclient->current_track_position / $mpclient->current_track_length)*100);
} else {
    $ctid  = 0;
    $artst = "";
    $track = "Silence...";
    $album = "";
    $filen = "";
    $cpos  = humanTime(0);
    $clen  = humanTime(0);
    $cperc = 0;
}

$mpclient->Disconnect();

echo <<<OUTPUT

<div id="songinfo">
    <table cellspacing="0" cellpadding="0">
    <tr><td class="desc">Artist</td><td colspan="3" class="artist">$artst</td></tr>
    <tr><td class="desc">Title</td><td colspan="3" class="track">$track</td></tr>
    <tr><td class="desc">Album</td><td colspan="3" class="album">$album</td></tr>
    <tr><td class="desc">File</td><td colspan="3" class="file">$filen</td></tr>
    <tr>
        <td class="desc">Time</td>
        <td class="time">$cpos</td>
        <td class="progress">
            <div id="progress"><div id="progress_inn" style="width:$cperc%"></div>
        </td>
        <td class="time">$clen</td>
    </tr></table>
</div>

OUTPUT;


if ($mod > 0) echo <<<OUTPUT

<div id="controls">
    <button onclick="dispatch('controls.php?action=prev');">&lt;&lt;</button>
    <button onclick="dispatch('controls.php?action=pause');">$play</button>
    <button onclick="dispatch('controls.php?action=next');">&gt;&gt;</button>
</div>

OUTPUT;

if (isset($_SESSION['token']) && $vtok = checkToken($_SESSION['token'])) echo <<<OUTPUT

    <div id="guestticket">Fahrschein noch gültig: $vtok</div>

OUTPUT;


?>
