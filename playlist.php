<?php

require_once ("inc/header.php");

$mpclient = new mpd($host,$port,$password);

// Action request?
if (isset($_GET["action"]) && ($mod > 0)) {
    switch ($_GET["action"]) {
        case "play":
            $mpclient->SkipTo($_GET['p']);
            if ($mpclient->state != MPD_STATE_PLAYING) $mpclient->Play();
            break;
        case "del":
            $mpclient->PLRemove($_GET['p']);
            break;
        case "move":
            $cpos=$_GET['p'];
            $npos=$cpos + ($_GET['d'] == "u" ? -1 : 1);
            $mpclient->PLMoveTrack($cpos, $npos);
            break;
    }
    $mpclient->RefreshInfo();
}

// Get current playing ID (Playlist-index)
if ($mpclient->state == MPD_STATE_PLAYING || $mpclient->state == MPD_STATE_PAUSED) {
    $ctid = $mpclient->current_track_id;
} else {
    $ctid = "";
}

// Get IDs added by MusicPlayerMagic (only the last few, because IDs wrap to 0 at 5535)
$mpmids = getMPMids();
// echo "<pre>";print_r($mpmids);echo "</pre>";


if (isset($_GET["con"])) {
    $cwidth = 80;
    if (is_numeric($_GET["con"])) {
        $cwidth = $_GET["con"];
    }
    // print ANSI
    $counter = -1;
    foreach($mpclient->playlist as $key=>$val) {
        $counter += 1;
        if (!is_numeric($key)) continue;
        $title = " ".mkTitle($val);
        $playtime = humanTime($val['Time']); // ['Id']
        if ($ctid == $key) echo "\e[48;5;23m"; // current track background-color
        if (in_array($val['Id'], $mpmids)) {
            echo "\e[37m"; // shuffle track color [white]
        } else {
            echo "\e[93m"; // manual track color [yellow]
        }
        // restrain stringlen
        $titpad = $cwidth - strlen($playtime) - 4 - mb_strlen($title);
        if ($titpad < 0) {
            $title = mb_substr($title,0, $cwidth - strlen($playtime) - 4);
        } else {
            $title = ($title. str_repeat(" ",$titpad));
        }
        echo "$title \e[38;5;247m[\e[38;5;255m$playtime\e[38;5;247m] \e[0m\n";
    }
} else { // HTML
    echo '<div id="playlist"><table cellspacing="0" cellpadding="0" width="100%">';

    $counter = -1;
    foreach($mpclient->playlist as $key=>$val) {
        $counter += 1;
        $stripes = (($counter % 4) > 1) ? "oddrow" : "evenrow";
        if (!is_numeric($key)) continue;
        
        $title = mkTitle($val, false);
        $playtime = humanTime($val['Time']); // ['Id']
        $b1 = ($ctid == $key) ? 'hilight' : '';
        if (in_array($val['Id'], $mpmids)) $b1.= ' shuffle';
        if ($mod > 0) {
            echo <<<OUTPUT
    <tr class="$b1 $stripes">
        <td>
            <div class="nobreak">
                <button onclick="dispatch('playlist.php?action=del&p=$key');">X</button>
            </div
        </td>
        <td width="100%">
            <a class="filelink" onclick="dispatch('playlist.php?action=play&p=$key');">$title</a>
        </td>
        <td>
            <div class="nobreak">
                <span>[$playtime]</span>
                <button onclick="dispatch('playlist.php?action=move&p=$key&d=u');">↑</button><button onclick="dispatch('playlist.php?action=move&p=$key&d=d');">↓</button>
            </div>
        </td>
    </tr>
OUTPUT;
        } else { // Non-privileged
            echo <<<OUTPUT
    <tr class="$b1 $stripes">
        <td width="100%">
            $title
        </td>
        <td>
            <div class="nobreak">
                <span>[$playtime]</span>
            </div>
        </td>
    </tr>
OUTPUT;
        }
    }

    echo '</table></div>';
}

$mpclient->Disconnect();

?>
