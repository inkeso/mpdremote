/*
 *      logic.js
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
 *      We asume DOM, as every browser does by now.
 */

// We need one single global request-Object.
var req = new XMLHttpRequest();

// global Timer for songinfo-updates
var gTimer = null;

var currP = ""; // current "tab"

// show/hide "wait"-overlay
function dimm() {
    document.getElementById('dimmer').style.display='block';
}

function undimm() {
    document.getElementById('dimmer').style.display="none";
}

// Just GET a php-file. If it takes to long, the "wait"-overlay is shown.
// If the file isn't empty, the content is put into #inner-div
function dispatch(what) {
    var dimmTimer;
    var tabs = ["controls", "playlist", "add"];
    // ---vv--- relative tab-navigation
    if (what === "+1" || what === "-1") {
        var current = 0
        for (var i=1; i < tabs.length; i++) {
            if (document.getElementById(tabs[i]+"_tab").className.indexOf("hilight") > -1) current = i ;
        }
        current = current + parseInt(what);
        if (current < 0) current = tabs.length - 1;
        if (current >= tabs.length) current = 0;
        what = tabs[current] + ".php";
    }
    // ---^^--- relative tab-navigation
    if (gTimer) window.clearTimeout(gTimer);
    
    if (what.indexOf('controls.php') < 0) 
        dimmTimer = setTimeout("dimm()", 200)

    for (var i=0; i<tabs.length; i++) {
        if (what.indexOf(tabs[i]) == -1) {
            document.getElementById(tabs[i]+"_tab").className="";
            document.getElementById(tabs[i]+"_tab").disabled=false;
        }
    }
    document.getElementById(what.slice(0,what.indexOf("."))+"_tab").className="hilight";
    document.getElementById(what.slice(0,what.indexOf("."))+"_tab").disabled=true;
    
    req.open("GET", what, true);
    req.onreadystatechange = function() {
        if (req.readyState == 4) {
            clearTimeout(dimmTimer);
            var answer = req.responseText;
            if (req.status == 200 && answer.length > 2) {
                document.getElementById('inner').innerHTML = answer;
                // main content changes (no action is dispatched): scroll to top.
                if (what.indexOf("?action=") == -1) window.scrollTo(0,0);
                if (what.indexOf('controls.php') == 0)  {
                    document.getElementById("progress").onclick = jump;
                    gTimer = setTimeout('dispatch("controls.php")', 1000);
                }
            } // else { alert (answer); } // debug only
            undimm();
        }
    };
    req.send(null);
}


// jump to clicked position / "handle event"
function jump(e) {
    var x = 0;
    var Element = e.target ;
    var CalculatedTotalOffsetLeft = 0;
    while (Element.offsetParent) {
        CalculatedTotalOffsetLeft += Element.offsetLeft;
        Element = Element.offsetParent ;
    }
    x = e.pageX - CalculatedTotalOffsetLeft ;

    proz = (x / document.getElementById("progress").offsetWidth);
    dispatch('controls.php?action=skip&p='+proz);
}

// eventhandler for searching (add.php) using button or "Enter" on 
// input-field without any form/submit
function srch(e) {
    if ((e == 13) || (e.keyCode == 13)) {
        sb = document.getElementById('searchbox').value;
        if (sb.length > 2) {
            dispatch('add.php?search=' + escape(sb));
        } else {
            alert("Searchterm too short. Need at least 3 characters");
        }
    }
}
// eventhandler for adding a streamurl (add.php) using button or "Enter" on 
// input-field without any form/submit
function stream(e) {
    if ((e == 13) || (e.keyCode == 13)) {
        sb = document.getElementById('streambox').value.replace(/^https:\/\//, "http://");
        addstream(sb)
    }
}

function addstreambookmark() {
    var sbm = document.getElementById('streambookmarks')
    var uri = sbm.options[sbm.selectedIndex].value;
    if (uri) addstream(uri);
}

function addstream(uri) {
    if (uri.indexOf("http://") === 0 || uri.indexOf("https://") === 0) {
        confirm("Add »"+uri+"« to playlist?") && dispatch('add.php?action=addfile&p=' + escape(uri));
    } else {
        alert("Not a valid stream-URL");
    }
}
