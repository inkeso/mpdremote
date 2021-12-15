<?php

// See config.php for STREAMBOOKMARKS and PODCASTS


function streambookmarks() {
    // return a filelist of stream-bookmarks
    // ['file' => 'http://stream-url', 'Title' => "Stream!"]
    $res = array();
    $bmf = fopen(STREAMBOOKMARKS, "r");
    while (($line = fgets($bmf, 4096)) !== false) {
        $fo = explode("\t", $line, 2);
        $res[] = array("Title"=> $fo[0], "file"=> trim($fo[1]));
    }
    fclose($bmf);
    return($res);
}

function podcast($what=null) {
    // if $what is null, a list of podcasts (names) is returned.
    // Matching file-entries otherwise (cached in session).
    $res = array();
    $bmf = fopen(PODCASTS, "r");
    $pods = array();
    while (($line = fgets($bmf, 4096)) !== false) {
        $fo = explode("\t", $line, 2);
        $pods[$fo[0]] = trim($fo[1]);
    }
    fclose($bmf);
    if ($what == null) {
        return array_keys($pods);
    } else {
        // check session stream caching :)
        if (!isset($_SESSION["POD_".$what])) {
            $res = array();
            if ((strpos($pods[$what], "https://soundcloud.com") === 0)) {
                require_once("soundcloud.php");
                $tracks = sc_tracklist(sc_resolve($pods[$what])->id);
                $entries = array();
                $baseuri = substr($_SERVER['SCRIPT_URI'], 0, strrpos($_SERVER['SCRIPT_URI'],"/"));
                foreach ($tracks as $tr) {
                    $res[] = array(
                        'file'=> $baseuri."/scproxy.php?scid=".$tr->id,
                        'Title'=>$tr->title,
                        'Artist' => $tr->user->username,
                        'Time' => round(intval($tr->duration)/1000)
                    );
                }
            } else { // assume plain RSS Podcast feed otherwise
                $doc = new DOMDocument();
                $doc->Load($pods[$what]);
                $xpath = new DOMXpath($doc);
                if($xpath) {
                    $itms = $xpath->query("//rss/channel/item");
                    foreach ($itms as $i) {
                        $res[] = array(
                            "file" => $i->getElementsByTagName("enclosure")->item(0)->attributes->getNamedItem("url")->value,
                            "Artist" => $what,
                            "Title"=> $i->getElementsByTagName("title")->item(0)->nodeValue,
                            "Time"=> fromHumanTime($i->getElementsByTagNameNS("*", "duration")->item(0)->nodeValue)
                        );
                    }
                }
            }
            $_SESSION["POD_".$what] = $res;
        }
        return $_SESSION["POD_".$what];
    }
}

?>
