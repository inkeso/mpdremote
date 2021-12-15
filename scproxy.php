<?php
require_once ("inc/soundcloud.php");

if (isset($_GET["scid"])) { // output data-stream...
    $trackid = preg_replace("/[^0-9]/", "", $_GET["scid"]);
    sc_stream($trackid);
}

if (isset($_GET["scpl"])) { // output playlist... (we use this to set better metadata)
    $trackid = preg_replace("/[^0-9]/", "", $_GET["scpl"]);
    $thisurl = $_SERVER['SCRIPT_URI'];
    $meta = sc_track($trackid);
    // replace normal ascii ":" with special-unicode "∶"
    // (because MPD truncates the string otherwise)
    $artist = str_replace(":", "∶", $meta->user->username);
    $title = str_replace(":", "∶", $meta->title);
    $seconds = floor($meta->duration/1000);

    $format = "rss";
    if (isset($_GET["xspf"])) $format = "xspf";
    if (isset($_GET["m3u"])) $format = "m3u";

    switch ($format) {
        case 'm3u':
            // PLS aka "audio/x-scpls" : Title and Length only
            // EXTM3U : Same but shorter...
            header('Content-Type: audio/x-mpegurl');
            header('Content-Disposition: attachment; filename="sc_'.$trackid.'.m3u"');
            $name = "$artist - $title";
            echo "#EXTM3U\n#EXTINF:$seconds,$name\n$thisurl?scid=$trackid\n";
            break;

        case 'xspf':
            // XSPF: Artist and Title, but no Length. MPD doesn't like this?
            header('Content-Type: application/xspf+xml');
            header('Content-Disposition: attachment; filename="sc_'.$trackid.'.xspf"');
            echo '<?xml version="1.0" encoding="UTF-8"?>
            <playlist version="1" xmlns="http://xspf.org/ns/0/"><trackList><track>
              <title>'.htmlspecialchars($title).'</title>
              <creator>'.htmlspecialchars($artist).'</creator>
              <location>'."$thisurl?scid=$trackid".'</location>
            </track></trackList></playlist>';
            break;

        case 'rss':
            // RSS: Artist and Title, but no Length. This works.
            header('Content-Type: application/rss+xml');
            header('Content-Disposition: attachment; filename="sc_'.$trackid.'.rss"');
            echo '<?xml version="1.0" encoding="UTF-8" ?>
            <rss version="2.0"><channel><item>
              <title>'.htmlspecialchars($title).'</title>
              <itunes:author>'.htmlspecialchars($artist).'</itunes:author>
              <enclosure url="'."$thisurl?scid=$trackid".'"/>
            </item></channel></rss>';
            break;
    }



    // None of the supported playlist-formats supports artist, title and length.
    // prepending the stream with an id3v2-block doesn't work either for setting
    // correct metadata. ...Anybody an idea?
}

