<?php

require_once ("inc/header.php");

$skin = getSkin();
$mod = maymod();

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>mpd:r3mote</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta http-equiv="Cache-Control" content="must-revalidate" />
    <meta http-equiv="Cache-Control" content="no-cache" />
    <meta http-equiv="Expires" content="-1" />
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="stylesheet" type="text/css" href="layout.css" />
    <link rel="stylesheet" type="text/css" href="skins/<?=$skin?>.css" />
    <script type="text/javascript" src="jks.js"></script>
    <script type="text/javascript" src="playlist.js"></script>
    <script type="text/javascript" src="add.js"></script>
    <script type="text/javascript" src="dynamic.js"></script>
    <script type="text/javascript"><!--

// |||=========================================================================\
// ||| This is dirty and should be removed at some point, because there
// ··· _are_ precision touch-devices out there (but not in our userbase).
//      On the other hand, webkitgtk2 handles touchevents regardless of device,
//      so this will falsely lead to usage of large interface in e.g. midori
function pointercheck() {
    // since pointer-related mediaqueries may not be accurate
    // (looking at you, IceCat!)
    // we first check whether we can create a touchevent...
    let istouch = true;
    try {
        document.createEvent("TouchEvent");
    } catch (e) {
        istouch = false;
    }
    // ...then we check whether mediaquery matches
    if (istouch && window.matchMedia("(pointer: fine)").matches) {
        // conflict! use larger boxes to be on the safe side.
        // since we do not want any styles in JS, we have to find all
        // media queries for a coarse pointer and update them.
        for (let styles of document.styleSheets) {
            for (let rule of styles.cssRules) {
                if (rule instanceof CSSMediaRule) {
                    if (rule.conditionText && rule.conditionText.includes("pointer: coarse")) {
                        rule.media.appendMedium("(pointer: fine)");
                    }
                }
            }
        }
    }
}
pointercheck();
// ---=========================================================================/




function showcontrols() {
    show("controls");
    updatecurrent();
    ctupdate = window.setInterval(updatecurrent, 1000);
}

function showplaylist() {
    show("playlist");
    updateplaylist();
    // scroll window so current song is in the middle (if needed)
    var observer = new MutationObserver(function(mutations) {
       if (document.contains($(".hilight")[0])) {
            var y = $(".hilight")[0].initY;
            if ($("#playlist").clientHeight > innerHeight) scrollTo(0, y - innerHeight/2);
            observer.disconnect();
        }
    });
    observer.observe($("#playlist"), {
        attributes: false, childList: true, characterData: false, subtree: true
    });
    ctupdate = window.setInterval(updateplaylist, 5000);
}

<?php if ($mod == 0) { ?>
function linkify(path) { return path.escapeHtml(); }
<?php } ?>

// see also add.js
function showadd() {
    <?php if ($mod > 0) { ?>
        show("add");
        if (adder.lifu === null) {
            fetch("podcastnames", null, e => {adder.podcasts = e});
            adder.goto();
        } else {
            adder.lifu();
        }
    <?php } else { ?>
        show("login");
        $('#login').style.display="block";
    <?php } ?>
    // scroll to top
    scrollTo(0, 0);
}

// autostart after load
document.addEventListener('DOMContentLoaded', event => {
    <?php if ($mod > 0) { ?>
        $("#progress").addEventListener("click", skip);
        $("#progress").addEventListener("mousemove", updatetooltip);
        $('#search').addEventListener("keyup", adder.searchkey);
        $('#cbuttons').style.display = "block";
        playlist.readonly = false;
    <?php } else { ?>
        $('#b_add').innerHTML = "Login";
    <?php } ?>
    showcontrols();
    playlist.initialize();
    undimm();
});

--></script>
</head>
<body>
    <div id="dimmer">Moment... <noscript><p style="color:red">No JavaScript support! Use <a style="color:orange" href="static.php">Static interface</a> instead.</p></noscript></div>
    <div id="tabs">
        <button id="b_ctr" onclick="showcontrols();">Controls</button>
        <button id="b_pls" onclick="showplaylist();">Playlist <span id="plcount"></span></button>
        <button id="b_add" onclick="showadd();">Add Song</button>
    </div>
    <div id="controls" button="b_ctr">
        <div id="cinfo">
            <div class="desc">Artist</div><div id="artist"></div><div id="albumart" onclick="toggleinfo()"></div>
            <div class="desc">Title</div> <div id="track"></div>
            <div class="desc">Album</div> <div id="album"></div>
            <div class="desc">File</div>  <div id="file"></div>
            <div class="desc">Time</div>

            <div id="progressline">
                <div id="progress">
                    <div id="progress_inn" style="width:0%">
                        <div id="progress_hover">0:00:00</div>
                    </div>
                </div>
                <div id="curtime"></div>
                <div id="tottime"></div>
            </div>
            <div class="desc">Next</div>    <div id="nextup"<?php if ($mod > 0) {
                    ?> onclick="action('rm', this.attributes.trackid, updatecurrent);"<?php
                } else {
                    ?> class="nohover"<?php
                } ?>></div>
        </div>
        <div id="cbuttons">
            <button onclick="action('prev');">fast_rewind</button>
            <button id="playbutton" onclick="action('pause');">pause</button>
            <button onclick="action('next');">fast_forward</button>
        </div>
        <?php if (isset($_GET["player"])) readfile("inc/player.html"); ?>
        <div id="guestticket">Fahrschein noch gültig: </div>
    </div>
    <?php if (isset($_GET["player"]) && $_GET["player"] == "visu") readfile("inc/visu.html"); ?>

    <div id="playlist" button="b_pls"></div>

    <div id="add" button="b_add">
        <div id="crumbtrail"></div>
        <input id="search" name="search" placeholder="Suche..."/><button id="searchX" onclick="$('#search').value='';">×</button>
        <div id="itemlist"></div>
    </div>

    <div id="login" button="b_add">
        <form action="" method="post">
        <br/><input type="text" name="usr" placeholder="User"/>
        <br/><input type="password" name="pw" placeholder="Password"/>
        <br/><input type="submit" value="login"/>
        </form>
    </div>

    <div id="errors">
    </div>
</body>
</html>
