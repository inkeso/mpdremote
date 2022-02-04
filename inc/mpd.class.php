<?php
/*
 * IKS 11/2020
 * - added new search syntax for mpd >= 0.21 to allow dir-based search:
 *      Search($type,$string) --> Search($type,$string,$dir="")
 * - cleaned up debugging/logging and some functions
 * - removed all other version-checks and deprecated stuff
 *
 * Sven Ginka 03/2010
 * Version mpd.class.php-1.3
 * - take over from Hendrik Stoetter
 * - removed "split()" as this function is marked depracted
 * - added property "xfade" (used by IPodMp, phpMp+)
 * - added property "bitrate" (used by phpMp+)
 * - added define "MPD_SEARCH_FILENAME"
 * - included sorting algorithm "msort"
 * - added function validateFile() for guessing a title if no ID3 data is given
 *
 * Hendrik Stoetter 03/2008
 * - this a lightly modified version of mod.class Version 1.2.
 * - fixed some bugs and added some new functions
 * - Changes:
 *      GetDir($url) -> GetDir(url,$sort)
 *      var $stats
 *
 *  Benjamin Carlisle 05/05/2004
 *
 *  mpd.class.php - PHP Object Interface to the MPD Music Player Daemon
 *  Version 1.2, Released 05/05/2004
 *  Copyright (C) 2003-2004  Benjamin Carlisle (bcarlisle@24oz.com)
 *  http://mpd.24oz.com/ | http://www.musicpd.org/
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

$mpd_debug = 0;

// Create common command definitions for MPD to use
// See: https://www.musicpd.org/doc/html/protocol.html

define("MPD_CMD_STATUS",      "status");
define("MPD_CMD_STATISTICS",  "stats");
define("MPD_CMD_SETVOL",      "setvol");
define("MPD_CMD_PLAY",        "play");
define("MPD_CMD_STOP",        "stop");
define("MPD_CMD_PAUSE",       "pause");
define("MPD_CMD_NEXT",        "next");
define("MPD_CMD_PREV",        "previous");
define("MPD_CMD_PLLIST",      "playlistinfo");
define("MPD_CMD_PLADD",       "add");
define("MPD_CMD_PLREMOVE",    "delete");
define("MPD_CMD_PLCLEAR",     "clear");
define("MPD_CMD_PLSHUFFLE",   "shuffle");
define("MPD_CMD_PLLOAD",      "load");
define("MPD_CMD_PLSAVE",      "save");
define("MPD_CMD_KILL",        "kill");
define("MPD_CMD_REFRESH",     "update");
define("MPD_CMD_REPEAT",      "repeat");
define("MPD_CMD_LSDIR",       "lsinfo");
define("MPD_CMD_SEARCH",      "search");
define("MPD_CMD_START_BULK",  "command_list_begin");
define("MPD_CMD_END_BULK",    "command_list_end");
define("MPD_CMD_FIND",        "find");
define("MPD_CMD_RANDOM",      "random");
define("MPD_CMD_SEEK",        "seek");
define("MPD_CMD_PLSWAPTRACK", "swap");
define("MPD_CMD_PLMOVETRACK", "move");
define("MPD_CMD_PASSWORD",    "password");
define("MPD_CMD_TABLE",       "list");
define("MPD_CMD_PLMOVE",      "move" );

// Predefined MPD Response messages
define("MPD_RESPONSE_ERR", "ACK");
define("MPD_RESPONSE_OK",  "OK");

// MPD State Constants
define("MPD_STATE_PLAYING", "play");
define("MPD_STATE_STOPPED", "stop");
define("MPD_STATE_PAUSED",  "pause");

// MPD Searching Constants
define("MPD_SEARCH_ARTIST",     "artist");
define("MPD_SEARCH_TITLE",      "title");
define("MPD_SEARCH_ALBUM",      "album");
define("MPD_SEARCH_GENRE",      "genre");
define("MPD_SEARCH_ANY",        "any");
define("MPD_SEARCH", array(
    MPD_SEARCH_ARTIST,
    MPD_SEARCH_TITLE,
    MPD_SEARCH_ALBUM,
    MPD_SEARCH_GENRE,
    MPD_SEARCH_ANY
));


// MPD Cache Tables
define("MPD_TBL_ARTIST","artist");
define("MPD_TBL_ALBUM", "album");
define("MPD_TBL_GENRE", "genre");


function addLog($text){
    global $mpd_debug;
    $style="background-color:lightgrey;border:thin solid grey;margin:5px;padding:5px";
    if ($mpd_debug) echo '<div style="'.$style.'">log:>'.$text.'</div>';
}
function addErr($err){
    global $mpd_debug;
    if ($mpd_debug) echo 'error:>'.$err.'<br>';
}

class mpd {
    // TCP/Connection variables
    var $host;
    var $port;
    var $password;
    var $mpd_sock   = NULL;
    var $connected  = FALSE;

    // MPD Status variables
    var $mpd_version    = "(unknown)";
    var $state;
    var $current_track_position;
    var $current_track_length;
    var $current_track_id;
    var $volume;
    var $repeat;
    var $random;
    var $uptime;
    var $playtime;
    var $db_last_refreshed;
    var $num_songs_played;
    var $playlist_count;
    var $xfade;
    var $bitrate;
    var $num_artists;
    var $num_albums;
    var $num_songs;
    var $playlist       = array();
    var $stats;

    // Misc Other Vars
    var $mpd_class_version = "1.3";
    var $errStr      = "";       // Used for maintaining information about the last error message
    var $command_queue;          // The list of commands for bulk command sending

    // =================== BEGIN OBJECT METHODS ================

    /* mpd() : Constructor
     *
     * Builds the MPD object, connects to the server, and refreshes all local object properties.
     */
    function mpd($srv,$port,$pwd = NULL, $debug= FALSE ) {
        $this->host = $srv;
        $this->port = $port;
        $this->password = $pwd;

        global $mpd_debug;
        $mpd_debug = $debug;

        $resp = $this->Connect();
        if ( is_null($resp) ) {
            addErr( "Could not connect" );
            return;
        } else {
            addLog( "connected");
            list ( $this->mpd_version ) = sscanf($resp, MPD_RESPONSE_OK . " MPD %s\n");
            if ( ! is_null($pwd) ) {
                if ( is_null($this->SendCommand(MPD_CMD_PASSWORD,$pwd)) ) {
                    $this->connected = FALSE;
                    addErr("bad password");
                    return;  // bad password or command
                }
                if ( is_null($this->RefreshInfo()) ) { // no read access -- might as well be disconnected!
                    $this->connected = FALSE;
                    addErr("Password supplied does not have read access");
                    return;
                }
            } else {
                if ( is_null($this->RefreshInfo()) ) { // no read access -- might as well be disconnected!
                    $this->connected = FALSE;
                    addErr("Password required to access server");
                    return;
                }
            }
        }
    }

    /* Connect()
     *
     * Connects to the MPD server.
     *
     * NOTE: This is called automatically upon object instantiation; you should not need to call this directly.
     */
    function Connect() {
        addLog( "mpd->Connect() / host: ".$this->host.", port: ".$this->port."\n" );
        $this->mpd_sock = fsockopen($this->host,$this->port,$errNo,$errStr,10);
        if (!$this->mpd_sock) {
            addErr("Socket Error: $errStr ($errNo)");
            return NULL;
        } else {
            $counter=0;
            while(!feof($this->mpd_sock)) {
                $counter++;
                if ($counter > 10){
                    addErr("no file end");
                    return NULL;
                }
                $response =  fgets($this->mpd_sock,1024);
                addLog( $response );

                if (strncmp(MPD_RESPONSE_OK,$response,strlen(MPD_RESPONSE_OK)) == 0) {
                    $this->connected = TRUE;
                    return $response;
                }
                if (strncmp(MPD_RESPONSE_ERR,$response,strlen(MPD_RESPONSE_ERR)) == 0) {
                    // close socket
                    fclose($this->mpd_sock);
                    addErr("Server responded with: $response");
                    return NULL;
                }
            }
            // close socket
            fclose($this->mpd_sock);
            // Generic response
            addErr("Connection not available");
            return NULL;
        }
    }

    /* SendCommand()
     *
     * Sends a generic command to the MPD server. Several command constants are pre-defined for
     * use (see MPD_CMD_* constant definitions above).
     */
    function SendCommand($cmdStr,$arg1 = "",$arg2 = "") {
        addLog("mpd->SendCommand() / cmd: ".$cmdStr.", args: ".$arg1." ".$arg2 );
        // Clear out the error String
        $this->errStr = NULL;
        $respStr = "";

        if ( ! $this->connected ) {
            addErr( "mpd->SendCommand() / Error: Not connected");
        } else {
            if (strlen($arg1) > 0) $cmdStr .= ' "'.addslashes($arg1).'"';
            if (strlen($arg2) > 0) $cmdStr .= ' "'.addslashes($arg2).'"';

            fputs($this->mpd_sock,"$cmdStr\n");
            while(!feof($this->mpd_sock)) {
                // binary responses are a bit more than 8KiB.
                $response = fgets($this->mpd_sock, 8448);
                //addLog($response);

                // An OK signals the end of transmission -- we'll ignore it
                if (strncmp(MPD_RESPONSE_OK,$response,strlen(MPD_RESPONSE_OK)) == 0) {
                    break;
                }

                // An ERR signals the end of transmission with an error! Let's grab the single-line message.
                if (strncmp(MPD_RESPONSE_ERR,$response,strlen(MPD_RESPONSE_ERR)) == 0) {
                    list ( $junk, $errTmp ) = strtok(MPD_RESPONSE_ERR . " ",$response );
                    addErr( strtok($errTmp,"\n") );
                    return NULL;
                }

                // Build the response string
                $respStr .= $response;
            }
            addLog("mpd->SendCommand() / response: '".$respStr."'\n");
        }
        return $respStr;
    }

    /* QueueCommand()
     *
     * Queues a generic command for later sending to the MPD server. The CommandQueue can hold
     * as many commands as needed, and are sent all at once, in the order they are queued, using
     * the SendCommandQueue() method. The syntax for queueing commands is identical to SendCommand().
     */
    function QueueCommand($cmdStr,$arg1 = "",$arg2 = "") {
        addLog("mpd->QueueCommand() / cmd: ".$cmdStr.", args: ".$arg1." ".$arg2);
        if ( ! $this->connected ) {
            addErr("mpd->QueueCommand() / Error: Not connected");
            return NULL;
        } else {
            if ( strlen($this->command_queue) == 0 ) {
                $this->command_queue = MPD_CMD_START_BULK . "\n";
            }
            if (strlen($arg1) > 0) $cmdStr .= " \"$arg1\"";
            if (strlen($arg2) > 0) $cmdStr .= " \"$arg2\"";

            $this->command_queue .= $cmdStr ."\n";
            addLog("mpd->QueueCommand() / return");
        }
        return TRUE;
    }

    /* SendCommandQueue()
     *
     * Sends all commands in the Command Queue to the MPD server. See also QueueCommand().
     */
    function SendCommandQueue() {
        addLog("mpd->SendCommandQueue()");
        if ( ! $this->connected ) {
            addErr("mpd->SendCommandQueue() / Error: Not connected");
            return NULL;
        } else {
            $this->command_queue .= MPD_CMD_END_BULK . "\n";
            if ( is_null($respStr = $this->SendCommand($this->command_queue)) ) {
                return NULL;
            } else {
                $this->command_queue = NULL;
                addLog("mpd->SendCommandQueue() / response: '".$respStr."'\n");
            }
        }
        return $respStr;
    }

    /* AdjustVolume()
     *
     * Adjusts the mixer volume on the MPD by <modifier>, which can be a positive (volume increase),
     * or negative (volume decrease) value.
     */
    function AdjustVolume($modifier) {
        addLog("mpd->AdjustVolume()");
        if ( ! is_numeric($modifier) ) {
            $this->errStr = "AdjustVolume() : argument 1 must be a numeric value";
            return NULL;
        }
        $this->RefreshInfo();
        $newVol = $this->volume + $modifier;
        $ret = $this->SetVolume($newVol);
        addLog("mpd->AdjustVolume() / return");
        return $ret;
    }

    /* SetVolume()
     *
     * Sets the mixer volume to <newVol>, which should be between 1 - 100.
     */
    function SetVolume($newVol) {
        addLog("mpd->SetVolume()");
        if ( ! is_numeric($newVol) ) {
            $this->errStr = "SetVolume() : argument 1 must be a numeric value";
            return NULL;
        }
        // Forcibly prevent out of range errors
        if ( $newVol < 0 )   $newVol = 0;
        if ( $newVol > 100 ) $newVol = 100;
        if ( ! is_null($ret = $this->SendCommand(MPD_CMD_SETVOL,$newVol))) $this->volume = $newVol;
        addLog("mpd->SetVolume() / return");
        return $ret;
    }

    /* GetDir()
     *
     * Retrieves a database directory listing of the <dir> directory and places the results into
     * a multidimensional array. If no directory is specified, the directory listing is at the
     * base of the MPD music path.
     */
    function GetDir($dir = "",$sort = "") {
        addLog( "mpd->GetDir()" );
        $resp = $this->SendCommand(MPD_CMD_LSDIR,$dir);
        $listArray = $this->_parseFileListResponse($resp);
        if ($listArray==null) return null;
        // we have 3 differnt items: directory, playlist and file, sort them individually:
        // playlist and directory by name, files by msort (sorting by filename, see below)
        natcasesort($listArray['directories']);
        natcasesort($listArray['playlists']);
        usort($listArray['files'],"msort");
        addLog( "mpd->GetDir() / return ".print_r($listArray,true));
        return $listArray;
    }

    /* GetDirTest() (Unoffical add) -- Returns readable dir contents
     *
     * Retrieves a database directory listing of the <dir> directory and places the results into
     * a multidimensional array. If no directory is specified, the directory listing is at the
     * base of the MPD music path.
     */
    function GetDirTest($dir = "") {
        addLog("mpd->GetDir()");
        $resp = $this->SendCommand(MPD_CMD_LSDIR,$dir);
        #$dirlist = $this->_parseFileListResponse($resp);
        $dirlist = $this->_parseFileListResponseHumanReadable($resp);
        addLog("mpd->GetDir() / return ".print_r($dirlist, true));
        return $dirlist;
    }

    /* PLAdd()
     *
     * Adds each track listed in a single-dimensional <trackArray>, which contains filenames
     * of tracks to add, to the end of the playlist. This is used to add many, many tracks to
     * the playlist in one swoop.
     */
    function PLAddBulk($trackArray) {
        addLog("mpd->PLAddBulk()");
        $num_files = count($trackArray);
        for ( $i = 0; $i < $num_files; $i++ ) {
            $this->QueueCommand(MPD_CMD_PLADD,$trackArray[$i]);
        }
        $resp = $this->SendCommandQueue();
        $this->RefreshInfo();
        addLog("mpd->PLAddBulk() / return");
        return $resp;
    }

    /* PLAdd()
     *
     * Adds the file <file> to the end of the playlist. <file> must be a track in the MPD database.
     */
    function PLAdd($fileName) {
        addLog("mpd->PLAdd()");
        if ( ! is_null($resp = $this->SendCommand(MPD_CMD_PLADD,$fileName))) $this->RefreshInfo();
        addLog("mpd->PLAdd() / return");
        return $resp;
    }

    /* PLMoveTrack()
     *
     * Moves track number <origPos> to position <newPos> in the playlist. This is used to reorder
     * the songs in the playlist.
     */
    function PLMoveTrack($origPos, $newPos) {
        addLog("mpd->PLMoveTrack()");
        if ( ! is_numeric($origPos) ) {
            $this->errStr = "PLMoveTrack(): argument 1 must be numeric";
            return NULL;
        }
        if ( $origPos < 0 or $origPos > $this->playlist_count ) {
            $this->errStr = "PLMoveTrack(): argument 1 out of range";
            return NULL;
        }
        if ( $newPos < 0 ) $newPos = 0;
        if ( $newPos > $this->playlist_count ) $newPos = $this->playlist_count;
        if ( ! is_null($resp = $this->SendCommand(MPD_CMD_PLMOVETRACK,$origPos,$newPos))) $this->RefreshInfo();
        addLog("mpd->PLMoveTrack() / return");
        return $resp;
    }

    /* PLShuffle()
     *
     * Randomly reorders the songs in the playlist.
     */
    function PLShuffle() {
        addLog("mpd->PLShuffle()");
        if ( ! is_null($resp = $this->SendCommand(MPD_CMD_PLSHUFFLE))) $this->RefreshInfo();
        addLog("mpd->PLShuffle() / return");
        return $resp;
    }

    /* PLLoad()
     *
     * Retrieves the playlist from <file>.m3u and loads it into the current playlist.
     */
    function PLLoad($file) {
        addLog("mpd->PLLoad()");
        if ( ! is_null($resp = $this->SendCommand(MPD_CMD_PLLOAD,$file))) $this->RefreshInfo();
        addLog("mpd->PLLoad() / return");
        return $resp;
    }

    /* PLSave()
     *
     * Saves the playlist to <file>.m3u for later retrieval. The file is saved in the MPD playlist
     * directory.
     */
    function PLSave($file) {
        addLog("mpd->PLSave()");
        $resp = $this->SendCommand(MPD_CMD_PLSAVE,$file);
        addLog("mpd->PLSave() / return");
        return $resp;
    }

    /* PLClear()
     *
     * Empties the playlist.
     */
    function PLClear() {
        addLog("mpd->PLClear()");
        if ( ! is_null($resp = $this->SendCommand(MPD_CMD_PLCLEAR))) $this->RefreshInfo();
        addLog("mpd->PLClear() / return");
        return $resp;
    }

    /* PLRemove()
     *
     * Removes track <id> from the playlist.
     */
    function PLRemove($id) {
        addLog("mpd->PLRemove()");
        if ( ! is_numeric($id) ) {
            $this->errStr = "PLRemove() : argument 1 must be a numeric value";
            return NULL;
        }
        if ( ! is_null($resp = $this->SendCommand(MPD_CMD_PLREMOVE,$id))) $this->RefreshInfo();
        addLog("mpd->PLRemove() / return");
        return $resp;
    }

    /* SetRepeat()
     *
     * Enables 'loop' mode -- tells MPD continually loop the playlist. The <repVal> parameter
     * is either 1 (on) or 0 (off).
     */
    function SetRepeat($repVal) {
        addLog("mpd->SetRepeat()");
        $rpt = $this->SendCommand(MPD_CMD_REPEAT,$repVal);
        $this->repeat = $repVal;
        addLog("mpd->SetRepeat() / return");
        return $rpt;
    }

    /* SetRandom()
     *
     * Enables 'randomize' mode -- tells MPD to play songs in the playlist in random order. The
     * <rndVal> parameter is either 1 (on) or 0 (off).
     */
    function SetRandom($rndVal) {
        addLog("mpd->SetRandom()");
        $resp = $this->SendCommand(MPD_CMD_RANDOM,$rndVal);
        $this->random = $rndVal;
        addLog("mpd->SetRandom() / return");
        return $resp;
    }

    /* Shutdown()
     *
     * Shuts down the MPD server (aka sends the KILL command). This closes the current connection,
     * and prevents future communication with the server.
     */
    function Shutdown() {
        addLog("mpd->Shutdown()");
        $resp = $this->SendCommand(MPD_CMD_SHUTDOWN);
        $this->connected = FALSE;
        unset($this->mpd_version);
        unset($this->errStr);
        unset($this->mpd_sock);
        addLog("mpd->Shutdown() / return");
        return $resp;
    }

    /* DBRefresh()
     *
     * Tells MPD to rescan the music directory for new tracks, and to refresh the Database. Tracks
     * cannot be played unless they are in the MPD database.
     */
    function DBRefresh($dir = "") {
        addLog("mpd->DBRefresh()");
        $resp = $this->SendCommand(MPD_CMD_REFRESH, $dir);
        // Update local variables
        $this->RefreshInfo();
        addLog("mpd->DBRefresh() / return");
        return $resp;
    }

    /* Play()
     *
     * Begins playing the songs in the MPD playlist.
     */
    function Play() {
        addLog("mpd->Play()");
        if ( ! is_null($rpt = $this->SendCommand(MPD_CMD_PLAY) )) $this->RefreshInfo();
        addLog("mpd->Play() / return");
        return $rpt;
    }

    /* Stop()
     *
     * Stops playing the MPD.
     */
    function Stop() {
        addLog("mpd->Stop()");
        if ( ! is_null($rpt = $this->SendCommand(MPD_CMD_STOP) )) $this->RefreshInfo();
        addLog("mpd->Stop() / return");
        return $rpt;
    }

    /* Pause()
     *
     * Toggles pausing on the MPD. Calling it once will pause the player, calling it again
     * will unpause.
     */
    function Pause() {
        addLog("mpd->Pause()");
        if ( ! is_null($rpt = $this->SendCommand(MPD_CMD_PAUSE) )) $this->RefreshInfo();
        addLog("mpd->Pause() / return");
        return $rpt;
    }

    /* SkipTo()
     *
     * Skips directly to the <idx> song in the MPD playlist.
     */
    function SkipTo($idx) {
        addLog("mpd->SkipTo()");
        if ( ! is_numeric($idx) ) {
            $this->errStr = "SkipTo() : argument 1 must be a numeric value";
            return NULL;
        }
        if ( ! is_null($rpt = $this->SendCommand(MPD_CMD_PLAY,$idx))) $this->RefreshInfo();
        addLog("mpd->SkipTo() / return");
        return $idx;
    }

    /* SeekTo()
     *
     * Skips directly to a given position within a track in the MPD playlist. The <pos> argument,
     * given in seconds, is the track position to locate. The <track> argument, if supplied is
     * the track number in the playlist. If <track> is not specified, the current track is assumed.
     */
    function SeekTo($pos, $track = -1) {
        addLog("mpd->SeekTo()");
        if ( ! is_numeric($pos) ) {
            $this->errStr = "SeekTo() : argument 1 must be a numeric value";
            return NULL;
        }
        if ( ! is_numeric($track) ) {
            $this->errStr = "SeekTo() : argument 2 must be a numeric value";
            return NULL;
        }
        if ( $track == -1 ) {
            $track = $this->current_track_id;
        }
        if ( ! is_null($rpt = $this->SendCommand(MPD_CMD_SEEK,$track,$pos))) $this->RefreshInfo();
        addLog("mpd->SeekTo() / return");
        return $pos;
    }

    /* Next()
     *
     * Skips to the next song in the MPD playlist. If not playing, returns an error.
     */
    function Next() {
        addLog("mpd->Next()");
        if ( ! is_null($rpt = $this->SendCommand(MPD_CMD_NEXT))) $this->RefreshInfo();
        addLog("mpd->Next() / return");
        return $rpt;
    }

    /* Previous()
     *
     * Skips to the previous song in the MPD playlist. If not playing, returns an error.
     */
    function Previous() {
        addLog("mpd->Previous()");
        if ( ! is_null($rpt = $this->SendCommand(MPD_CMD_PREV))) $this->RefreshInfo();
        addLog("mpd->Previous() / return");
        return $rpt;
    }

    /* Search()
     *
     * Searches the MPD database. The search <type> should be one of MPD_SEARCH
     * The search <string> is a case-insensitive locator string. Anything that contains
     * <string> will be returned in the results.
     */
    function Search($type,$string,$dir="") {
        addLog("mpd->Search()");
        if ( !in_array($type, MPD_SEARCH) ) {
            addErr( "mpd->Search(): invalid search type" );
            return NULL;
        } else {
            $ver = explode(".",$this->mpd_version);
            if (intval($ver[1]) >= 21 || intval($ver[0]) > 0) {
                // Advanced search syntax since mpd 0.21
                if (strlen($dir)>0) {
                    $string = "(($type contains '$string') AND (base '$dir'))";
                } else {
                    $string = "($type contains '$string')";
                }
                if ( is_null($resp = $this->SendCommand(MPD_CMD_SEARCH,$string))) return NULL;
            } else {
                if ( is_null($resp = $this->SendCommand(MPD_CMD_SEARCH,$type,$string))) return NULL;
            }
            $searchlist = $this->_parseFileListResponse($resp);
        }
        addLog( "mpd->Search() / return ".print_r($searchlist,true) );
        return $searchlist;
    }

    function Recent($string) {
        addLog("mpd->Search()");
        $ver = explode(".",$this->mpd_version);
        if (intval($ver[1]) >= 21 || intval($ver[0]) > 0) {
            if (is_null($resp = $this->SendCommand(MPD_CMD_SEARCH, "(modified-since '$string')"))) return NULL;
        } else {
            if ( is_null($resp = $this->SendCommand(MPD_CMD_SEARCH,"modified-since",$string))) return NULL;
        }
        $searchlist = $this->_parseFileListResponse($resp);
        addLog( "mpd->Search() / return ".print_r($searchlist,true) );
        return $searchlist;
    }

    /* Find()
     *
     * Find() looks for exact matches in the MPD database. The find <type> should be one of MPD_SEARCH.
     * The find <string> is a case-insensitive locator string. Anything that exactly matches
     * <string> will be returned in the results.
     */
    function Find($type,$string) {
        addLog("mpd->Find()");
        if ( !in_array($type, MPD_SEARCH) ) {
            $this->errStr = "mpd->Find(): invalid find type";
            return NULL;
        } else {
            if ( is_null($resp = $this->SendCommand(MPD_CMD_FIND,$type,$string)))   return NULL;
            $searchlist = $this->_parseFileListResponse($resp);
        }
        addLog("mpd->Find() / return ".print_r($searchlist,true));
        return $searchlist;
    }

    /* Disconnect()
     *
     * Closes the connection to the MPD server.
     */
    function Disconnect() {
        addLog("mpd->Disconnect()");
        fclose($this->mpd_sock);
        $this->connected = FALSE;
        unset($this->mpd_version);
        unset($this->errStr);
        unset($this->mpd_sock);
    }

    /* GetGenres()
     *
     * only $genre given:
     * Returns the list of all artists in the database, matching given genre.
     * (sorted with meaningless numeric keys)
     *
     * $genre and $artist given:
     * Returns a multidimensiona array file => "Artist - Title"
     *
    */
    function GetGenreArtist($genre, $artist=null) {
        addLog("mpd->GetGenreArtist()");
        if (is_null($artist)) {
            $abg = $this->Find(MPD_SEARCH_GENRE, $genre);
            $result = array();
            foreach ($abg['files'] as $key => $value) {
                if ($value['Genre'] == $genre) $result[$value['Artist']] = 1;
            }
            $result = array_keys($result);
            natcasesort($result);
            return ($result);
        } else {
            $result = array();
            // Abusing Find-function. Yeah, this is dirty!
            $found = $this->Find(MPD_SEARCH_GENRE, '"'.addslashes($genre).'" "artist" "'.addslashes($artist).'"');
            foreach($found['files'] as $key => $value) {
                $result[$value['file']] = array($value['Title'].
                                                (isset($value['Album'])?" [".$value['Album']."]":""),
                                                $value['Time']);
            }
            // natcasesort($result);
            return($result);
        }
       addLog("mpd->GetGenres()");
    }


    function GetGenres() {
        addLog("mpd->GetGenres()");
        if ( is_null($resp = $this->SendCommand(MPD_CMD_TABLE, MPD_TBL_GENRE))) return NULL;
        $arArray = array();
        $arLine = strtok($resp,"\n");
        $arName = "";
        $arCounter = -1;
        while ( $arLine ) {
            list ( $element, $value ) = explode(": ",$arLine);
            if ( $element == "Genre" ) {
                $arCounter++;
                $arName = $value;
                $arArray[$arCounter] = $arName;
            }
            $arLine = strtok("\n");
        }
        addLog("mpd->GetGenres()");
        return $arArray;
    }

    /* GetArtists()
     *
     * Returns the list of artists in the database in an associative array.
    */
    function GetArtists() {
        addLog("mpd->GetArtists()");
        if ( is_null($resp = $this->SendCommand(MPD_CMD_TABLE, MPD_TBL_ARTIST)))    return NULL;
        $arArray = array();
        $arLine = strtok($resp,"\n");
        $arName = "";
        $arCounter = -1;
        while ( $arLine ) {
            list ( $element, $value ) = explode(": ",$arLine);
            if ( $element == "Artist" ) {
                $arCounter++;
                $arName = $value;
                $arArray[$arCounter] = $arName;
            }
            $arLine = strtok("\n");
        }
        addLog("mpd->GetArtists()");
        return $arArray;
    }

    /* GetAlbums()
     *
     * Returns the list of albums in the database in an associative array. Optional parameter
     * is an artist Name which will list all albums by a particular artist.
    */
    function GetAlbums( $ar = NULL) {
        addLog("mpd->GetAlbums()");
        if ( is_null($resp = $this->SendCommand(MPD_CMD_TABLE, MPD_TBL_ALBUM, $ar )))   return NULL;
        $alArray = array();
        $alLine = strtok($resp,"\n");
        $alName = "";
        $alCounter = -1;
        while ( $alLine ) {
            list ( $element, $value ) = explode(": ",$alLine);
            if ( $element == "Album" ) {
                $alCounter++;
                $alName = $value;
                $alArray[$alCounter] = $alName;
            }
            $alLine = strtok("\n");
        }
        addLog("mpd->GetAlbums()");
        return $alArray;
    }

    /* GetCover()
     *
     * Returns a bytestring of the cover of given file
     * (cover.jpg or cover.png in same dir)

     → https://www.musicpd.org/doc/html/protocol.html#binary

    Some commands can return binary data. This is initiated by a line
    containing binary: 1234 (followed as usual by a newline). After that,
    the specified number of bytes of binary data follows, then a newline,
    and finally the OK line.

    If the object to be transmitted is large, the server may choose a
    reasonable chunk size and transmit only a portion. Usually, the
    response also contains a size line which specifies the total
    (uncropped) size, and the command usually has a way to specify an
    offset into the object; this way, the client can copy the whole file
    without blocking the connection for too long.

    albumart <file> <offset>
    albumart foo/bar.ogg 0

    */

    // TODO: Some covers don't load correctly! Why?
    function GetCover( $filepath = NULL ) {
        addLog("mpd->GetCover()");
        if (is_null($filepath)) return "";
        $size = 0;
        $data = "";
        while ($res = $this->SendCommand('albumart', $filepath, strlen($data))) {
            $rx = explode("\n", $res, 3);
            if ($size === 0) $size = intval(explode(": ",$rx[0])[1]);
            $readbytes = intval(explode(": ",$rx[1])[1]);
            $data .= substr($rx[2], 0, $readbytes);
            if (strlen($data) >= $size) break;
        }
        return $data;
    }

    //*******************************************************************************//
    //***************************** INTERNAL FUNCTIONS ******************************//
    //*******************************************************************************//
    /*
     * checks the file entry and complete it if necesarry
     * checked fields are 'Artist', 'Genre' and 'Title'
     *
     */
    private function _validateFile( $fileItem ){
        $filename = $fileItem['file'];
        if (!isset($fileItem['Artist'])){ $fileItem['Artist']=null; }
        if (!isset($fileItem['Genre'])){ $fileItem['Genre']=null; }
        // special conversion for streams
        if (stripos($filename, 'http' )!==false){
            if (!isset($fileItem['Title'])) $title = ''; else $title=$fileItem['Title'];
            if (!isset($fileItem['Name'])) $name = ''; else $name=$fileItem['Name'];
            if (!isset($fileItem['Artist'])) $artist = ''; else $artist=$fileItem['Artist'];

            if (strlen($title.$name.$artist)==0){
                $fileItem['Title'] = $filename;
            } else {
                $fileItem['Title'] = $title.' '.$name;
                $fileItem['Artist'] = $artist;
            }
            if (!isset($fileItem['Album'])) $fileItem['Album'] = "[stream]";
        }
        if (!isset($fileItem['Title'])){
            $file_parts = explode('/', $filename);
            $fileItem['Title'] = $filename;
        }
        return $fileItem;
    }

    /*
     * take the response of mpd and split it up into
     * items of types 'file', 'directory' and 'playlist'
     *
     */
    private function _extractItems( $resp ){
        if ( $resp == null ) {
            addLog('empty file list');
            return NULL;
        }
        // strip unwanted chars
        $resp = trim($resp);
        // split up into lines
        $lineList = explode("\n", $resp );
        $array = array();
        $item=null;
        foreach ($lineList as $line ){
            list ( $element, $value ) = explode(": ",$line);
            // if one of the key words come up, store the item
            if (($element == "directory") or ($element=="playlist") or ($element=="file")){
                if ($item){
                    $array[] = $item;
                }
                $item = array();
            }
            $item[$element] = $value;
        }
        // check if there is a last item to store
        if (sizeof($item)>0){
            $array[] = $item;
        }
        return $array;
    }

    /* _parseFileListResponse()
     *
     * Builds a multidimensional array with MPD response lists.
     *
     * NOTE: This function is used internally within the class. It should not be used.
     */
    private function _parseFileListResponse($resp) {
        $valuesArray = $this->_extractItems($resp);
        if ($valuesArray == null) return null;
        $ret = array("directories"=>array(), "playlists"=>array(), "files"=>array());
        foreach ( $valuesArray as $item ) {
            if (isset($item['file'])){
                $ret['files'][] = $this->_validateFile($item);
            } else if (isset($item['directory'])){
                $ret['directories'][] = $item['directory'];
            } else if (isset($item['playlist'])){
                $ret['playlists'][] = $item['playlist'];
            } else {
                addErr('should not enter this');
            }
        }
        addLog( print_r($valuesArray,true) );
        return $ret;
    }

    /* RefreshInfo()
     *
     * Updates all class properties with the values from the MPD server.
     *
     * NOTE: This function is automatically called upon Connect() as of v1.1.
     */
    function RefreshInfo() {
        // Get the Server Statistics
        $statStr = $this->SendCommand(MPD_CMD_STATISTICS);
        if ( !$statStr ) {
            return NULL;
        } else {
            $stats = array();
            $statStr=trim($statStr);
            $statLine = explode( "\n", $statStr );
            foreach ( $statLine as $line ) {
                list ( $element, $value ) = explode(": ",$line);
                $stats[$element] = $value;
            }
        }

        // Get the Server Status
        $statusStr = $this->SendCommand(MPD_CMD_STATUS);
        if ( ! $statusStr ) {
            return NULL;
        } else {
            $status = array();
            $statusStr=trim($statusStr);
            $statusLine = explode("\n", $statusStr );
            foreach ($statusLine as $line) {
                list($element, $value) = explode(": ",$line);
                $status[$element] = $value;
            }
        }

        // Get the Playlist
        $plStr = $this->SendCommand(MPD_CMD_PLLIST);
        $array = $this->_parseFileListResponse($plStr);
        $playlist = $array['files'];
        $this->playlist_count = count($playlist);
        $this->playlist = array();
        if (sizeof($playlist)>0) {
            foreach ($playlist as $item) $this->playlist[$item['Pos']] = $item;
        }

        // Set Misc Other Variables
        $this->state = $status['state'];
        if ( ($this->state == MPD_STATE_PLAYING) || ($this->state == MPD_STATE_PAUSED) ) {
            $this->current_track_id = $status['song'];
            list ($this->current_track_position, $this->current_track_length ) = explode(":",$status['time']);
        } else {
            $this->current_track_id = -1;
            $this->current_track_position = -1;
            $this->current_track_length = -1;
        }

        $this->repeat = $status['repeat'];
        $this->random = $status['random'];
        $this->db_last_refreshed = $stats['db_update'];
        $this->volume = $status['volume'];
        $this->uptime = $stats['uptime'];
        $this->playtime = $stats['playtime'];
        $this->num_songs_played = $stats['songs'];
        $this->num_artists = $stats['artists'];
        $this->num_songs = $stats['songs'];
        $this->num_albums = $stats['albums'];
        $this->xfade = (isset($status['xfade']) ? $status['xfade'] : '');
        if(isset($status['bitrate'])) $this->bitrate = $status['bitrate'];
        else $this->bitrate = FALSE;

        return TRUE;
    }

}   // ---------------------------- end of class ------------------------------

function msort($a, $b) { return strnatcasecmp($a["file"], $b["file"]); }
?>
