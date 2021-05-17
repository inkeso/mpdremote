/**********************************************************************\
*         --  JKS  --   some JS-helpers I use all the time             *
\**********************************************************************/

"use strict"

// jQuery in a nutshell. v0.2
function $(x) {
    return x.startsWith("#")
        ? document.getElementById(x.substr(1))
        : document.querySelectorAll(x);
}
HTMLElement.prototype.$ = $;

// Why not?!
NodeList.prototype.filter = NodeList.prototype.filter || Array.prototype.filter;

/**********************************************************************\
* DOM helper and stuff                                                 *
\**********************************************************************/

// replaceChildren for older (mobile-)browsers
if (!HTMLElement.prototype.replaceChildren) {
    HTMLElement.prototype.replaceChildren = function(x) {
        this.innerHTML = ""
        while (x.children.length > 0) this.appendChild(x.firstChild);
    }
}

// Create an HTML-Element with (optional) id, class(es), inner text
function elem(tag, id="", cls=[], text="") {
    let elem = document.createElement(tag);
    if (id) elem.id = id;
    // we need top copy the classes-array
    if (cls.length > 0) for (let x of cls) elem.classList.add(x);
    if (text) elem.textContent = text;
    return elem;
}

/**********************************************************************\
* STRING functions                                                     *
\**********************************************************************/

//...
String.prototype.ellipsize = function(len) {
    if (this.length > len) {
        // 2/3 ... 1/3
        return this.slice(0, len/3 * 2) + "…" + this.slice(-len/3);
    }
    return this;
};

// replace some html-chars
String.prototype.escapeHtml = function() {
    return this.replace(/[&<>"'\/]/g, s => {
        let entityMap = {
            "&": "&amp;", "<": "&lt;", ">": "&gt;",
            '"': '&quot;', "'": '&#39;', "/": '&#x2F;'
        };
        return entityMap[s];
    });
}


/**********************************************************************\
* NUMBER functions / conversions                                       *
\**********************************************************************/

// format a number: (what about linksbündig? evtl mit neg. Vorzeichen?)
//  (1.23456).format("2.3") → " 1.234"
//  (1.23456).format("02.3") → "01.234"
//  (1.23456).format("02") → "01"
//  (1.23456).format("3") → "  1"
//     (1236).format("2") → "1236"
//  (1.23456).format("02.") → "01.23456"
//  (1.23456).format(".3") → "1.234"
//      (7.1).format("4.3") → "   7.100"

Number.prototype.format = function(fstr) {
    let comp = fstr.split(".");
    // precision first
    let result = this.toFixed(comp[1]);
    // now padding
    let pad = " ";
    if (comp[0][0] == "0") pad = "0";
    let plen = comp[0] - result.split(".")[0].length;
    if (plen > 0) result = pad.repeat(plen) + result;
    return result
}

// Convert Seconds to human readable time (String)
Number.prototype.humanTime = function() {
    if (isNaN(this)) return "?";
    let h = Math.floor(this / 3600);
    let m = Math.floor((this - h*3600) / 60);
    let s = this % 60;
    let ms = m + ":" + s.format("02");
    if (h > 0) ms = h + ":" + (m < 10 ? "0" : "") + ms;
    return ms;
}

// Convert bytes to human readable String
Number.prototype.humanBytes = function() {
    let abbrevs = [
        [2**80,'YiB'], [2**70,'ZiB'], [2**60,'EiB'],
        [2**50,'PiB'], [2**40,'TiB'], [2**30,'GiB'],
        [2**20,'MiB'], [2**10,'KiB'], [2**0, 'B']
    ];
    let p = abbrevs.filter(p => this >= p[0])[0];
    if (p[1] == 'B') return `${this} B`;
    return `${(this/p[0]).toFixed(2)} ${p[1]}`;
}


/**********************************************************************\
* ARRAY functions                                                      *
\**********************************************************************/

Array.prototype.move = function(fromidx, toidx) {
    this.splice(toidx, 0, this.splice(fromidx, 1)[0]);
}

/**********************************************************************\
* AJAX - Simple Requests without errorhandling                         *
\**********************************************************************/
var request = {
    // Async POST-Request an 'url' mit 'parameters' ["foo=bla", "hui=pfui"].
    // Probaly sollten wir hier so richtig {"key":"val"} draus machen.
    // danach 'callback' aufrufen
    "post": function(url, parameters, callback) {
        var request = new XMLHttpRequest();
        if (request) {
            request.open("POST", url, true);
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    callback(request.responseText);
                }
            };
            request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            request.send(parameters.join('&'));
        }
        return request;
    },

    // Async GET-Request an 'url', ruft 'callback' auf, wenns klappt
    "get": function(url, callback) {
        var request = new XMLHttpRequest();
        if (request) {
            request.open("GET", url, true);
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    callback(request.responseText);
                }
            };
            request.send();
        }
        return request;
    }
}
