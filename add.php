<?php
/*
 *      add.php
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
 *      Browsing and searching Files, add them to playlist.
 */

require_once("inc/header.php");

if($mod == 0) {
    header ("Location: login.php");
    die();
}

$mpclient = new mpd($host,$port,$password);

// dispatch action-request (add dir/file/artist/genre)
// [[TODO]] see below

// array("file" => array("Artist - Title", Time))
// array("full/path/to/dir" => array("deepest Dir", "DIR"))
// array("artist" => array("Artist", "ART")
// array("genre" => array("Genre", "GEN"))
$listing = isset($_SESSION['listing']) ? $_SESSION['listing'] : '';

// array("dispatcher-link-parameter" => "Name")
// remember to urlencode() the linkparameter(s)
// root is not included. Since it is always visible, it will be included 
// during HTML-generation
$crumbtrail = isset($_SESSION['crumbtrail']) ? $_SESSION['crumbtrail'] : '';

// a simple string for info-messages. may be set by GET or during execution.
// should be sanitized to avoid cross-site-scripting
if (isset($_GET['info']))
    $info = preg_replace("/[^A-Za-z0-9 ]/","", $_GET['info']);

// If Session is empty, get the root-dir
if (!isset($_SESSION['listing']) && !isset($_SESSION['crumbtrail'])) $_GET['dir'] = "";

if (isset($_GET['search'])) {
    // cache searchphrase in session and remove others
    // TODO: gemischte Suche in allen Feldern. Dazu also auf space exploden und einzeln suchen, anschließend array-intersect.
    // oder so ähnlich
    $_SESSION['search'] = $_GET['search'];
    if (isset($_SESSION['dir'])) unset($_SESSION['dir']);
    if (isset($_SESSION['genre'])) unset($_SESSION['genre']);
    if (isset($_SESSION['artist'])) unset($_SESSION['artist']);
    
    // new search. Search result contains files only
    $listing = array();
    $resi = $mpclient->Search(MPD_SEARCH_ANY, $_SESSION['search']);
    if (isset($resi['files'])) foreach ($resi['files'] as $key => $val) {
        $listing[$val['file']] = array(mkTitle($val), humanTime($val['Time']));
    }
    
    // crumbtrail Music » "keyword"
    $crumbtrail = array("search=".$_GET['search'] => $_GET['search']);

} elseif (isset($_GET['dir'])) {
    // cache current path in session
    $_SESSION['dir'] = $_GET['dir'];
    if (isset($_SESSION['search'])) unset($_SESSION['search']);
    if (isset($_SESSION['genre'])) unset($_SESSION['genre']);
    if (isset($_SESSION['artist'])) unset($_SESSION['artist']);
    
    // read dir, list subdirs and files
    $listing = array();
    $crumbtrail = array();
    
    $dir = $mpclient->GetDir($_SESSION['dir']);
    foreach ($dir['directories'] as $d) {
        $sd = strrpos("$d","/");
        if ($sd === False) {
            $sd = $d;
        } else {
            $sd = substr($d,$sd+1);
        }
        $listing[$d] = array($sd, "DIR");
    }
    foreach ($dir['files'] as $d) {
        // mkTitle(.., true) should not be neccessary, if everything is tagged right
        $listing[$d['file']] = array(mkTitle($d,true), humanTime($d['Time']));
    }
    
    // bonus: add real streams
    if ($_GET['dir'] == "streams") {
        $doc = new DOMDocument();
        $doc->Load("http://www.musicforprogramming.net/rss.php");
        $xpath = new DOMXpath($doc);
        if($xpath) {
            $itms = $xpath->query("//rss/channel/item");
            foreach ($itms as $i) {
                $title = $i->getElementsByTagName("title")->item(0)->nodeValue;
                $url = $i->getElementsByTagName("guid")->item(0)->nodeValue;
                $length = $i->getElementsByTagName("duration")->item(0)->nodeValue;
                $listing[$url] = array("Music for Programming - ".$title, $length);
            }
        }
    }
    
    // crumbtrail Music » Folder(s)
    $splitted = explode("/", $_SESSION['dir']);
    foreach ($splitted as $n => $j) {
        $crumbtrail["dir=".implode("/", array_slice($splitted, 0, $n+1))] = $j;
    }

} elseif (isset($_GET['genre'])) {
    // cache current genre in session
    $_SESSION['genre'] = $_GET['genre'];
    if (isset($_SESSION['search'])) unset($_SESSION['search']);
    if (isset($_SESSION['dir'])) unset($_SESSION['dir']);
    
    $listing = array();
    // crumbtrail Music » by Genre
    $crumbtrail = array("genre=" => "by Genre");
    if (!$_SESSION['genre']) { // if empty, list all genres
        foreach ($mpclient->GetGenres() as $gen) {
            $listing[$gen] = array($gen, "GEN");
        }
    } else { // if genre is set and nonempty: list matching artists
        foreach($mpclient->GetGenreArtist($_SESSION['genre']) as $art) {
            $listing[$art] = array($art, "ART");
        }
        // append current genre to crumbtrail
        $crumbtrail["genre=".$_SESSION['genre']] = $_SESSION['genre'];
    }

} elseif (isset($_GET['artist'])) {
    $_SESSION['artist'] = $_GET['artist'];
    if (isset($_SESSION['search'])) unset($_SESSION['search']);
    if (isset($_SESSION['dir'])) unset($_SESSION['dir']);
    
    $listing = array();
    $crumbtrail = array();
    
    if ($_SESSION['artist']) { // Artist set? List all of it's tracks!
        if (isset($_SESSION['genre']) && $_SESSION['genre']) {
            $resi = $mpclient->GetGenreArtist($_SESSION['genre'], $_SESSION['artist']);
            // in this case, the first value in the result-array is
            // already in the desired format Title [Album] (see mpd.class.php)
            $rkeys = array_keys($resi);
            natcasesort($rkeys);
            foreach ($rkeys as $key) {
                $listing[$key] = array($resi[$key][0], humanTime($resi[$key][1]));
            }
            $crumbtrail = array("genre=" => "by Genre", 
                                "genre=".urlencode($_SESSION['genre']) => $_SESSION['genre'],
                                "artist=".urlencode($_SESSION['artist']) => $_SESSION['artist']);
        } else {
            $resi = $mpclient->Search(MPD_SEARCH_ARTIST, $_SESSION['artist']);
            if (isset($resi['files'])) foreach ($resi['files'] as $key => $val) {
                $tit = $val['Title'];
                if (isset($val['Album'])) $tit .= " [".$val['Album']."]";
                $listing[$val['file']] = array($tit, humanTime($val['Time']));
            }
            $crumbtrail = array("artist=" => "by Artist", 
                                "artist=".urlencode($_SESSION['artist']) => $_SESSION['artist']);
        }
    } else { // No Artist? Than list all possible Artists (by Genre, if any)
        if (isset($_SESSION['genre']) && $_SESSION['genre']) {
            foreach($mpclient->GetGenreArtist($_SESSION['genre']) as $art) {
                $listing[$art] = array($art, "ART");
            }
            $crumbtrail = array("genre=" => "by Genre", 
                                "genre=".$_SESSION['genre'] => $_SESSION['genre']);
        } else {
            $arts = $mpclient->GetArtists();
            foreach ($arts as $art) {
                $listing[$art] = array($art, "ART");
            }
            $crumbtrail = array("artist=" => "by Artist");
        }
    }
}

// Cache generated dir- and filelist in session.
$_SESSION['listing'] = $listing;
$_SESSION['crumbtrail'] = $crumbtrail;

// Action request (add song/dir, refresh)?
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case "addfile":
            $newsongs = addfiles($mpclient, $_GET['p']);
            if ($newsongs > 1) $info = "$newsongs songs added";
        break;
        case "addart":
            $newsongs = addfiles($mpclient, array_keys($listing));
            header("Location: add.php?artist&info=$newsongs+songs+added");
        break;
        case "addgen":
            // TODO -- Probably not needed //
        break;
        case "del":
            $mpclient->PLRemove($_GET['p']);
        break;
        case "refresh":
            $mpclient->DBRefresh($_GET['p']);
            // block until ready. (there might a better way)
            while (array_key_exists("updating_db", $mpclient->GetStatus()))
                usleep(250000);
                $info = $_GET['p']." updated";
        break;
        
    }
    $mpclient->RefreshInfo();
}

//fetch filenames in current playlist
$plf = array();
foreach($mpclient->playlist as $key=>$val) $plf[] = $val['file'];

////////////////////////////
// Generate output / HTML //
////////////////////////////

// Crumbtrail
$crumbhtml = '<a class="dirlink" onclick="dispatch(\'add.php?dir\');">Music</a>';
if ($crumbtrail) foreach ($crumbtrail as $key => $val) {
    if ($val) $crumbhtml .= '<a class="dirlink" onclick="dispatch(\'add.php?'.$key.'\');">'.$val.'</a>';
}

// dir/filelists
$fileshtml = '';
$dirshtml = '';

if ($listing) {
    $lkeys = array_keys($listing);
    natcasesort($lkeys);
    $dcounter = -1;
    $fcounter = -1;
    foreach($lkeys as $key) {
        $dcounter += 1;
        $stripes = (($dcounter % 4) < 2) ? "oddrow" : "evenrow";
        $val = $listing[$key];
        $ukey = urlencode($key);
        switch($val[1]) {
            case "DIR":
                $dirshtml .= <<<HTML
    <tr class="${stripes}">
        <td>
            <div class="nobreak">
                <button onclick="dispatch('add.php?action=refresh&p={$ukey}');">R</button>
            </div>
        </td>
        <td width="100%">
            <a class="dirlink" onclick="dispatch('add.php?dir={$ukey}');">{$val[0]}</a>
        </td>
        <td>
            <div class="nobreak">
                <button onclick="dispatch('add.php?action=addfile&p={$ukey}');">Add Dir</button>
            </div>
        </td>
    </tr>
HTML;
            break;
            
            case "ART":
                $dirshtml .= <<<HTML
    <tr class="${stripes}">
        <td width="100%" colspan="2">
            <a class="dirlink" onclick="dispatch('add.php?artist={$ukey}');">{$val[0]}</a>
        </td>
        <td>
            <div class="nobreak">
                <button onclick="dispatch('add.php?action=addart&artist={$ukey}');">Add Artist</button>
            </div>
        </td>
    </tr>
HTML;
            break;
            
            case "GEN":
                $dirshtml .= <<<HTML
    <tr class="${stripes}">
        <td width="100%" colspan="2">
            <a class="dirlink" onclick="dispatch('add.php?genre={$ukey}');">{$val[0]}</a>
        </td>
        <td>
            <div class="nobreak">
                &nbsp; <!-- button onclick="dispatch('add.php?action=addgen&genre={$ukey}');">Add Genre</button -->
            </div>
        </td>
    </tr>
HTML;
            break;
            
            default:  // File!
                $fcounter += 1;
                $dcounter -= 1;
                $clss = (($fcounter % 4) > 1) ? "oddrow" : "evenrow";
                $pinl = "";
                $act  = "addfile&p=".$ukey;
                if (in_array($key, $plf)) {
                    $posip = array_search($key, $plf);
                    $clss.=' hilight';
                    $pinl="[".($posip - $mpclient->current_track_id)."]";
                    $act = "del&p=".$posip;
                }
                $fileshtml .= <<<HTML
    <tr class="{$clss}">
        <td>
            <div class="nobreak">$pinl</div>
        </td>
        <td width="100%">
            <a class="filelink" onclick="dispatch('add.php?action=$act');">{$val[0]}</a>
        </td>
        <td>{$val[1]}</td>
    </tr>
HTML;
        }
    }
}

// template & initial view
echo <<<HTML
    <div id="add">
    <div id="backcrumb">{$crumbhtml}</div>
HTML;

if (isset($info) && $info) echo "<div id=\"info\">{$info}</div>";

// we only show the searchbox when we're searching and on initial view
if (isset($_SESSION["search"]) || (isset($_SESSION['dir']) && $_SESSION['dir'] == "")) {
    $src = isset($_SESSION['search']) ? $_SESSION['search'] : '';
    echo <<<HTML
    <div id="search">
        <input type="text" size="20" name="search" id="searchbox" onkeypress="srch(event)" value="{$src}"/>
        <button id="searchbutton" onclick="srch(13);">Such!</button>
    </div>
HTML;
}

// two links (for browsing by genre or artist) are only shown on the initial view
if (isset($_SESSION['dir']) && $_SESSION['dir'] == "") echo <<<HTML
    <div id="stream">
        <input type="text" size="20" name="stream" id="streambox" onkeypress="stream(event)" value=""/>
        <button id="streambutton" onclick="stream(13);">Stream</button>
        <select id="streambookmarks" size="1" onchange="addstreambookmark()">>
            <option selected="selected" value="">Bookmarks</option>
            <option value="http://stream.lohro.de:8000/lohro.mp3">LoHRO</option>
            <option value="http://stream.saetche.net:8000/echochamber">Saetchmo Echochamber</option>
            <option value="http://ice.somafm.com/dronezone">SomaFM Drone Zone</option>
            <option value="http://ice.somafm.com/groovesalad">SomaFM Groove Salad</option>
            <option value="http://mp3channels.rockantenne.hamburg/917xfm">917XFM</option>
            <option value="http://stream-relay-geo.ntslive.net/stream">NTS</option>
        </select>
    </div>

    <div id="bytag">
        <a onclick="dispatch('add.php?genre');">Artists by Genre</a>
        <a onclick="dispatch('add.php?artist');">All Artists</a>
    </div>
HTML;

echo <<<HTML
    <table cellspacing="0" cellpadding="0" width="100%">
        {$dirshtml}
        {$fileshtml}
    </table>
</div>
HTML;


// DEBUG //

//echo "<pre>";
//echo "<h1>crumbtrail</h1>";
//print_r($crumbtrail);
//echo "<h1>Listing</h1>";
//print_r($listing);
//echo "<h1>Session</h1>";
//print_r($_SESSION);
//echo "</pre>";

?>
