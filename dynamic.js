"use strict";

function dimm()     {
    $("#dimmer").style.zIndex = 2;
    $("#dimmer").style.opacity = 1;
}
function undimm() {
    $("#dimmer").style.zIndex = -2;
    $("#dimmer").style.opacity = 0;
}

let dimmTimer = null; // global dimm-timer

function doRequest(url, callback, nodim=false) {
    if (!nodim && dimmTimer === null) dimmTimer = setTimeout("dimm()", 250);
    request.get(url, function(answer) {
        try {
            $("#errors").innerHTML = "";
            $("#errors").style.display = "none";
            callback(JSON.parse(answer));
        } catch(e) {
            $("#errors").innerHTML = answer+"<hr/>"+e;
            $("#errors").style.display = "block";
            //throw e;
        }
        if (dimmTimer !== null) {
            clearTimeout(dimmTimer);
            dimmTimer = null;
            undimm();
        }
    });
}

// simple get.php wrapper.
function fetch(what, para, callback) {
    let nodim = false;
    if (what == "current" || what=="playlist") nodim = true;
    if (para === null) {
        doRequest("get.php?"+what, callback, nodim)
    } else {
        doRequest("get.php?"+what+"=" + encodeURIComponent(para), callback, nodim)
    }
}

// simple do.php wrapper.
function action(what, para, callback=null) {
    doRequest("do.php?"+what+"=" + encodeURIComponent(para), function(answer) {
        if (answer) alert("ERROR: "+ answer);
        if (callback !== null) callback();
        updatecurrent();
    });
}

// create clickable spans in filepath
function spanclick(e) {
    // get all previous siblings
    let path = "";
    while (e != null) {
        path = e.innerText + "/" + path;
        e = e.previousElementSibling;
    }
    adder.goto(path.slice(0,-1));
    showadd();
}

function linkify(path) {
    if (!path) return "";
    let parts = path.split("/");
    if (parts[0].lastIndexOf(":") == parts[0].length - 1) return path.escapeHtml();
    let res = "<div id='fileitems'>";
    for (let i = 0; i < parts.length-1; i++) {
        //let nspan = elem("span", "", [], parts[i]);
        res+="<span onclick=\"spanclick(this)\">"+parts[i].escapeHtml()+"</span>/";
    }
    res += parts[parts.length-1];
    return res+"</div>";
}

// every second in control-screen...
// current track-info
let currentinfo = {
    "trackinfo": {"file": ""},
    "time": "0",
    "state": "pause",
    "playlistcount": 0,
    "next": {"file": ""}
};

let displayinfo = false;
function allinfo(ti) {
    if (displayinfo) {
        let s ="<table>";
        let not = ['file', 'duration', 'Time', 'Artist', 'Title', 'Album', 'fromshuffle', 'Id'];
        for (let k in ti) {
            if (not.indexOf(k) >= 0) continue;
            if (ti[k] == null || ti[k] == "") continue;
            s += "<tr><th>"+k.replaceAll("_", " ").escapeHtml()+"</th><td>"+ti[k].escapeHtml()+"</td></tr>\n";
        }
        return s+"</table>";
    } else {
        return '<img src="get.php?albumart&'+ti.Id+'"/>';
    }
}

function toggleinfo() {
    displayinfo = !displayinfo;
    $("#albumart").innerHTML = allinfo(currentinfo.trackinfo);
}

function updatecurrent() {
    fetch("current", null, function(answer) {
        let ti = answer.trackinfo;
        let cu = currentinfo.trackinfo;
        // prepare values for output, add missing keys
        answer.time = parseInt(answer.time);
        ti.Artist = ("Artist" in ti && ti.Artist) ? ti.Artist : "\xa0";
        ti.Title =  ("Title"  in ti && ti.Title)  ? ti.Title  : "\xa0";
        ti.Album =  ("Album"  in ti && ti.Album)  ? ti.Album  : "\xa0";
        ti.Time =   ("Time"   in ti && ti.Time)   ? parseInt(ti.Time) : 0;

        // update only what is different
        if (currentinfo.playlistcount != answer.playlistcount) $("#plcount").innerHTML = "("+answer.playlistcount+")";
        if (ti.Artist != cu.Artist) $("#artist").innerHTML = ti.Artist.escapeHtml()
        if (ti.Title  != cu.Title)  $("#track").innerHTML =  ti.Title.escapeHtml()
        if (ti.Album  != cu.Album)  $("#album").innerHTML =  ti.Album.escapeHtml()
        if (ti.file   != cu.file)   {
            $("#file").innerHTML =   linkify(ti.file);
            $("#albumart").innerHTML = allinfo(ti);
        }
        if (ti.Time   != cu.Time)   $("#tottime").innerHTML = ti.Time.humanTime();
        if (currentinfo.time != answer.time) {
            $("#curtime").innerHTML = answer.time.humanTime();
            if ("Time" in ti) {
                let perc = (answer.time / ti.Time * 100);
                if (!isFinite(perc)) perc=1;
                $("#progress_inn").style.width = perc + "%";
            } else {
                $("#progress_inn").style.width = "0%"
            }
        }
        if (answer.next.file != currentinfo.next.file) {
            var v = answer.next;
            $("#nextup").innerHTML = ("Artist" in v && v["Artist"] != null ? (v.Artist + " - ") : "") + v.Title;
            if ("Time" in v) $("#nextup").innerHTML += " ["+parseInt(v.Time).humanTime()+"]";
            $("#nextup").attributes.trackid = v.Id;
        }
        if (currentinfo.state != answer.state) {
            $("#playbutton").innerHTML = answer.state == "play" ? "pause" : "play_arrow"
        }
        currentinfo = answer;
    });
}

function updatetooltip(e) {
    let Element = e.target;
    let CalculatedTotalOffsetLeft = 0;
    while (Element.offsetParent) {
        CalculatedTotalOffsetLeft += Element.offsetLeft;
        Element = Element.offsetParent ;
    }
    let x = (e.clientX - CalculatedTotalOffsetLeft) / $("#progress").offsetWidth;
    let hov = $("#progress_hover")
    hov.innerHTML = (currentinfo.trackinfo.Time * x).humanTime();
    let xoff = 0;
    try {
        xoff = hov.getClientRects()[0].width / 2;
    } catch {}
    hov.style.top = "calc("+e.clientY+"px - 2em)";

    let roff = Math.min(e.clientX - xoff, window.innerWidth - xoff*2);
    hov.style.left = roff + "px";
}

function skip(e) {
    let Element = e.target;
    let CalculatedTotalOffsetLeft = 0;
    while (Element.offsetParent) {
        CalculatedTotalOffsetLeft += Element.offsetLeft;
        Element = Element.offsetParent ;
    }
    let x = e.pageX - CalculatedTotalOffsetLeft ;
    action("skip", (x / $("#progress").offsetWidth));
}


// see also playlist.js. First, overwrite action-functions
playlist.fmove = (fid, tid) => { action("mv", fid+","+tid); }
playlist.fdelete = id => { action("rm", id); }
playlist.fplay = id => { action("go", id); }

// every 5 seconds in playlist-screen but only update on changes
// and when no dragging is in progress.

// TODO: display current song progress in playlist
function updateplaylist() {
    if (playlist.drag) return;
    fetch("playlist", null, function(answer) {
        let newdata = answer.map(e => {
            let row = {
                id: e.Id, title: "",
                time: parseInt(e.Time).humanTime(),
                classes: []
            }
            if ("Artist" in e && e.Artist) row.title += e.Artist + " - ";
            // Title should always be set...
            if ("Title" in e && e.Title) row.title += e.Title;
            if ("fromshuffle" in e && e.fromshuffle) row.classes.push("shuffle");
            if ("currently" in e && e.currently) row.classes.push("hilight");
            return row;
        });
        // only trigger update if new data mismatches
        let newstring = JSON.stringify(newdata);
        if (newstring != JSON.stringify(playlist.data)) {
            playlist.data = newdata;
            playlist.update();
            $("#plcount").innerHTML = "("+newdata.length+")";
        }
    });
}

let ctupdate = false;

function show(id) {
    if (ctupdate) {
        clearInterval(ctupdate);
        ctupdate = false;
    }
    for (let oid of ["controls", "playlist", "add", "login"]) {
        $('#'+oid).style.display = (oid == id ? "block" : "none");
        $('#'+$('#'+oid).attributes.button.textContent).classList = [];
    }
    $('#'+$('#'+id).attributes.button.textContent).classList.add("active");
}


