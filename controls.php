<?php
/*
 *      controls.php
 *      
 *      Copyright 2012 Eloi Maelzer <maelzer@gmx.de>
 *      
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.
 *      
 *      
 *      Show song information and controls
 */
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
    $cpos  = hrTime($mpclient->current_track_position);
    $clen  = hrTime($mpclient->current_track_length);
    $cperc = @intval(($mpclient->current_track_position / $mpclient->current_track_length)*100);
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
