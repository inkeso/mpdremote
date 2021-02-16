<?php
require_once ("inc/header.php");
$mod = maymod();

// don't want to check each property
error_reporting(E_ALL & ~E_NOTICE);

function get($what, $parm=null, $more=[]) {
    $burl = substr($_SERVER['SCRIPT_URI'], 0, strrpos($_SERVER['SCRIPT_URI'], "static.php"));
    $burl .= "/get.php?".$what;
    if (!is_null($parm)) $burl .= "=".urlencode($parm);
    foreach($more as $k => $v) $burl .= '&'.urlencode($k)."=".urlencode($v);
    return json_decode(file_get_contents($burl));
}

function act($what, $parm=null) {
    $burl = substr($_SERVER['SCRIPT_URI'], 0, strrpos($_SERVER['SCRIPT_URI'], "static.php"));
    $burl .= "/do.php?".$what;
    if (!is_null($parm)) $burl .= "=".urlencode($parm);
    return json_decode(file_get_contents($burl));
}


// which tab?
$tab = "c";
$tab = (isset($_GET['t']) && in_array($_GET['t'], ['p','a'])) ? $_GET['t'] : "c";

// append this to every link / URL to force reloading for dumb browsers.
$stamp = "&stamp=".implode("-",hrtime());

// handle actions
if ($mod) {
    if (isset($_GET["a"]) && in_array($_GET["a"], ['prev', 'next', 'pause'])) {
        act($_GET["a"]);
        // reload to avoid double-action on reload
        header("Location: static.php?t=c".$stamp);
        die();
    }

    foreach (["rm", "mv", "go"] as $a) {
        if (isset($_GET[$a])) {
            act($a, $_GET[$a]);
            // reload to avoid double-action on reload
            header("Location: static.php?t=p".$stamp);
            die();
        }
    }
    foreach (["rmfile", "add", "refresh"] as $a) {
        if (isset($_GET[$a])) {
            act($a, $_GET[$a]);
            // reload to avoid double-action on reload
            header("Location: static.php?t=a&dir=".urlencode($_GET["dir"]).$stamp);
            die();
        }
    }

}

?><html><head>
    <title>mpd:r3mote static</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta http-equiv="Cache-Control" content="must-revalidate" />
    <meta http-equiv="Cache-Control" content="no-cache" />
    <!--meta http-equiv="refresh" content="5"-->
    <meta http-equiv="Expires" content="-1" />
    <link rel="shortcut icon" href="favicon.ico" />
    <style>
        html,body,table {
            background:#222;
            color: #ddd;
            font-family: DejaVu Sans;
            font-size: 10pt;
        }
        table, tr, td, th {
            border: 1px solid #333;
            border-collapse: collapse;
            padding: 0.4em;
        }
        table {
            width: 100%;
        }
        a {
            text-decoration: none;
            color: #00C2FF;
        }
        a:hover {
            background: #0A346A;
        }
        tr.big a {
            font-size: 200%;
            font-family: mono;
        }
        th a { display: block; }
        img {
            max-width: 300px;
            max-height: 300px;
        }
        tr.hilight td, tr.hilight th {
            background: #333;
        }

        input {
            background: #333;
            border: 1px solid #444;
            color: #fff;
        }

    </style>
</head><body>
<table class="head">
  <tr>
    <th><a href="static.php?t=c<?=$stamp?>">Controls</a></th>
    <th><a href="static.php?t=p<?=$stamp?>">Playlist</a></th>
    <th><a href="static.php?t=a<?=$stamp?>">Add Song</a></th>
  </tr>
</table>
<?php
switch($tab) {
    case 'c':  //////////////////////////////////////////////////////// Controls
        $cti = get("current");
        // display image or info?
        $moreinfo = '<center><a href="?t=c&info'.$stamp.'"><img src="get.php?albumart&'.$cti->trackinfo->Id.'"></a></center>';
        if (isset($_GET["info"])) {
            $moreinfo = '<a href="?t=c'.$stamp.'"><table>';
            $not = ['file', 'duration', 'Time', 'Artist', 'Title', 'Album', 'fromshuffle', 'Id'];
            foreach (get_object_vars($cti->trackinfo) as $k=>$v) {
                if (in_array($k, $not)) continue;
                $moreinfo .= "<tr><th>".htmlentities(str_replace("_", " ", $k))."</th><td>".htmlentities($v)."</td></tr>\n";
            }
            $moreinfo .= "</table></a>";
        }
        ?><table>
          <tr><th>Artist</th><td width="100%"><?=$cti->trackinfo->Artist?></td>
              <td rowspan="6"><?=$moreinfo?></td>
          </tr>
          <tr><th>Title</th><td><?=htmlentities($cti->trackinfo->Title)?></td></tr>
          <tr><th>Album</th><td><?=htmlentities($cti->trackinfo->Album)?></td></tr>
          <tr><th>File</th> <td><?=htmlentities($cti->trackinfo->file)?></td></tr>
          <tr><th>Time</th> <td><?=humanTime($cti->time)?> /
                                <?=humanTime($cti->trackinfo->duration)?></td></tr>
          <tr><th>Next</th> <td><?=htmlentities($cti->next->Artist)?> - <?=htmlentities($cti->next->Title)?></td></tr>
        </table>
        <?php
        if ($mod) { ?>
            <table>
            <tr class="big"><th><a href="?a=prev<?=$stamp?>">&lt;&lt;</a></th>
                <th><a href="?a=pause<?=$stamp?>"><?=$cti->state == "play" ? "||" : "&gt;"?></a></th>
                <th><a href="?a=next<?=$stamp?>">&gt;&gt;</a></th>
            </tr>
            </table>
        <?php }
        break;

    case 'p':  //////////////////////////////////////////////////////// Playlist
        $playlist = get("playlist");
        echo "<table>";
        foreach ($playlist as $k => $v) {
            $dn = "&nbsp;";
            if ($k < count($playlist)-1) $dn = '<a href="?mv='.$v->Id.','.$playlist[$k+1]->Id.$stamp.'">&nbsp;↓&nbsp;</a>';
            $up = "&nbsp";
            if ($k > 0) $up = '<a href="?mv='.$v->Id.','.$playlist[$k-1]->Id.$stamp.'">&nbsp;↑&nbsp;</a>';

            echo '<tr class="'.($v->currently ? "hilight" : "").'">';
            echo '<th>'.$dn.'</th>';
            echo '<th>'.$up.'</th>';
            echo '<th><a href="?rm='.$v->Id.$stamp.'">&nbsp;x&nbsp;</a></th>';
            echo '<td width="100%"><a href="?go='.$v->Id.$stamp.'">'.htmlentities($v->Artist).' - '.htmlentities($v->Title).'</a></td>';
            echo '<td>'.humanTime($v->Time).'</td>';
            echo "</tr>\n";
        }
        echo "</table>";
        break;

    case 'a':  ///////////////////////////////////////////////////////////// Add
        if ($mod) {
            $cdir = $_GET["dir"];
            $app = '&dir='.urlencode($cdir).$stamp;
            if (isset($_GET["search"]) && strlen($_GET["search"]) > 0) {
                $alist = (object)['directories' => [], 'files' => get("search", $_GET["search"], ["indir"=>$cdir])];
            } else {
                $alist = get("dir", $cdir);
            }
            // prepare crumbtrail
            $crumbs = '<a href="?t=a&dir='.$stamp.'">Music</a>';
            $base = "";
            foreach (explode("/",$cdir) as $d) {
                $crumbs .= ' / <a href="?t=a&dir='.urlencode($base.$d).'">'.htmlentities($d).'</a>';
                $base .= $d."/";
            }
            echo '<form method="GET"><table><tr><td colspan="3">'.$crumbs.'</td></tr>'."\n";
            echo '<tr><td>Suche:</td><td colspan="2">';
            echo '<input name="t" value="a" type="hidden"/>';
            echo '<input name="dir" value="'.$cdir.'" type="hidden"/>';
            echo '<input name="search" style="width:100%" value="'.$_GET["search"].'"/>';
            echo '</td></tr>';

            // Show Dirs
            $podcastnames = get("podcastnames");
            foreach ($alist->directories as $d) {
                $ndir = urlencode($d);
                $tdir = htmlentities($cdir ? substr($d, strrpos($d, "/")+1) : $d);
                echo '<tr><th><a href="?t=a&refresh='.urlencode($d).$app.'" title="Refresh">[R]</a></th>';
                echo '<td width="100%"><a href="?t=a&dir='.$ndir.$stamp.'">'.$tdir.'</a></td>';
                $add = "&nbsp;";
                if ($cdir && !in_array(substr($d, strrpos($d, "/")+1), $podcastnames)) {
                    $add = '<a href="?t=a&add='.$ndir.$app.'">Add&nbsp;Dir</a>';
                }
                echo '<td>'.$add.'</td></tr>';
            }
            // Show Files
            // First check for same artist
            $art1 = $alist->files[0]->Artist;
            $same = count($alist->files) > 1;
            foreach ($alist->files as $f) {
                if ($f->Artist != $art1) {
                    $same = false;
                    break;
                }
            }
            // Now add to table
            foreach ($alist->files as $f) {
                $inplaylist = property_exists($f, "inplaylist");
                echo '<tr class="'.($inplaylist ? "hilight" : "").'"><td>';
                if ($inplaylist) {
                    echo "[".$f->inplaylist."]";
                    $link = '&rmfile='.urlencode($f->file);
                } else {
                    echo $f->Track ? $f->Track : "&nbsp;";
                    $link = '&add='.urlencode($f->file);
                }
                echo '</td><td width="100%"><a href="?t=a'.$link.$app.'">';
                if (!$same) echo htmlentities($f->Artist)." - ";
                if ($f->Title) {
                    echo htmlentities($f->Title);
                } else {
                    echo substr($f->file, strrpos($f->file, "/")+1);
                }
                echo '</a></td><td>'.humanTime($f->Time).'</td></tr>';
            }

            echo '</table></form>';
        } else { ?>
            <form action="" method="post">
                <table>
                    <tr><th>User</th><td><input type="text" name="usr"/></td></tr>
                    <tr><th>Password</th><td><input type="password" name="pw"/></td></tr>
                    <tr><th colspan="2"><input type="submit" value="login"/></th></tr>
                </table>
            </form>
        <?php }
        break;
}
?>

</body></html>
