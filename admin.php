<?php

require_once('inc/header.php');

// action?
if (isset($_GET["newToken"])) {
    unset($_SESSION['err']);
    // validate input (!!)
    $ndur = intval($_GET["newToken"]);
    if (($ndur > 60) && ($ndur < 172800)) {
        $token = createToken($ndur);
    } else {
        $_SESSION['err']="Ungültige Dauer";
    }
    header ("Location: admin.php");
}

if (isset($_GET["invalidate"])) {
    unset($_SESSION['err']);
    $invt = $_GET["invalidate"];
    if (preg_match('/^([a-z0-9]{10})$/', $invt)) {
        if (!@unlink('tokens/'.$_GET["invalidate"])) {
            $_SESSION['err']="Fehler beim Löschen des Fahrscheins ".$invt;
        }
    } else {
        $_SESSION['err']="Ach ja? Das ist aber kein gültiger Fahrschein.";
    }
    header ("Location: admin.php");
    
}

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>mpd:re:mote</title>
    <meta http-equiv="Cache-Control" content="must-revalidate" />
    <meta http-equiv="Cache-Control" content="no-cache" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta http-equiv="Expires" content="-1" />
    <meta name="viewport" content="width=device-width, user-scalable=no">
    <LINK REL="SHORTCUT ICON" HREF="mpdremote2.png">
    <script type="text/javascript" src="logic.js"></script>

    <link rel="stylesheet" type="text/css" href="skins/layout.css" />
    <link rel="stylesheet" type="text/css" href="skins/'.$skin.'.css" />
</head>
<body>
    <div id="songinfo">';

if (isset($_SESSION["err"])) echo "<b>".$_SESSION["err"]."</b><br/><br/>\n";

if ($mod < 2) die("Only valid admin-users and local users allowed.</div></body></head>");

function mkLink($s, $t) {
    return "<button onclick=\"location.href='admin.php?".$s."'\">".$t."</button>\n";
}


if ($handle = opendir('tokens')) {
    if (count(scandir('tokens')) > 2) {
        echo "<h3>Fahrscheinkontingent</h3>\n";
        echo "<style>
            th { text-align:left; background-color: rgba(0, 0, 0, .2); }
            td, th {
                border: 1px solid #111; 
                padding: .1em .5em; 
            }
            table { border-collapse: collapse; }
        </style>\n";
        echo "<table><tr><th>ID</th><th>Status</th><th>Gültig</th><th>Löschen</th></tr>\n";
    
        while (($tfile = readdir($handle)) !== FALSE) {
            if ($tfile != "." && $tfile != "..") {
                echo "<tr><td><tt>$tfile</tt></td><td>";
                $tline = file_get_contents('tokens/'.$tfile);
                if (strpos($tline, " for ")) {
                    echo "Unbenutzter Fahrschein</td><td>";
                    echo substr($tline, strpos($tline, " for ")+5) / 60;
                    echo " Minuten";
                }
                if (strpos($tline, " until ")) {
                    echo "Aktiver Fahrschein</td><td>";
                    echo date("d.m.y H:i:s", 
                              substr($tline, strpos($tline, " until ")+7));
                }
                echo "</td><td>";
                echo mkLink("invalidate=".$tfile, "X");
                echo "</td></tr>\n";
            }
        }
        closedir($handle);
        echo "</table>\n<hr/>\n";
    } else {
        echo "Aktuell keine Fahrscheine vorhanden";
    }
} else {
    echo "<b>Konnte Fahrscheinverzeichnis <tt>tokens</tt> nicht öffnen.</b>";
}
echo "<h3>Neuen Fahrschein erstellen</h3>";
echo mkLink("newToken=1800", "30 Minuten")." &ndash; ";
echo mkLink("newToken=3600", "60 Minuten")." &ndash; ";
echo mkLink("newToken=7200", "2 Stunden")." &ndash; ";
echo mkLink("newToken=14400", "4 Stunden")." &ndash; ";
echo mkLink("newToken=86400","24 Stunden")."\n";
echo "<hr/>";

echo "<h3>Password-hash generieren</h3>";
echo '<form method="POST">Password: <input type="text" name="pw"/>';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    echo "<tt>".password_hash($_POST['pw'], PASSWORD_DEFAULT)."</tt>";
    echo "(nach inc/config.php pasten)";
}
echo "</form></div></body></head>";
?>
