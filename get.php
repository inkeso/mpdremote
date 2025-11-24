<?php
require_once ("inc/header.php");
require_once ("inc/streams.php");

$mpclient = connect();

switch (count($_GET) ? array_keys($_GET)[0] : "current") {
    case 'current':
        jout($mpclient->get_current());
        break;

    case 'playlist':
        jout($mpclient->get_playlist());
        break;

    case 'dir':
        jout($mpclient->get_dir($_GET['dir']));
        break;

    case 'search':
        $dir = isset($_GET['indir']) ? $_GET['indir'] : "";
        jout($mpclient->get_search($_GET['search'], $dir));
        break;

    case 'podcastnames':
        jout(podcast());
        break;

    case 'albumart':
        echo $mpclient->get_albumart();
        break;
}

$mpclient->Disconnect();
