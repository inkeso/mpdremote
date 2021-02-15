/**********************************************************************\
*         Knusperbrei deluxe: drag and drop in der Playlist            *
\**********************************************************************/

"use strict"

let playlist = {
    touchdelay: 500,  // on touch: hold delay before moving (ms)
    vibration: 80,    // on touch: when starting to move vibrate (ms)
    drag: false,      // currently dragging an item?
    scroll: null,     // drag-scroll-timeout
    from: null,       // which item is clicked/dragged
    targ: null,       // where is it dragged to
    touchtimer: null, // stores the setTimeout-reference
    touchly: 0,       // drag-start (Y) Position for manual scrolling
    items: [],        // array of div-box-items (DOM Nodes)
    MODE: "-",        // items in [A]bsolute or [R]elative position
    data: [],         // {id: "", title: "", time: "1:23", classes=[]}
    container: null,  // Element to contain playlist-itmes
    readonly: true,   // allow/disallow modifications
    // Action functions to be overwritten
    fmove: function(fr, to) { console.log("move : " + fr + " → " + to); },
    fdelete: function(id) { console.log("del : " + id); },
    fplay: function(id) { console.log("play : " + id); },

    // set absolute positions for drag and drop and other movements.
    setAbsolute: function() {
        if (this.MODE == "A") return;
        // So what do we have?
        this.MODE = "A";

        //this.items = $("div#playlist .box");
        this.items = this.container.$(".box");

        // get Item sizes and positions
        this.items.forEach(it => {
            let itsize = it.getBoundingClientRect();
            it.initY = itsize.top + scrollY;
            it.initH = itsize.height;
            it.style.top = it.initY + "px";
            //it.style.width = itsize.width + "px";
        });

        // fix container
        let csize = this.container.getBoundingClientRect();
        this.container.style.width = csize.width + "px";
        this.container.style.height = csize.height + "px";

        // set items to absolute positioning
        this.items.forEach((it) => {
            it.style.position = "absolute";
        });
    },

    // set items to relative. Nothing more. This should be the normal state.
    setRelative: function() {
        if (this.MODE == "R") return;
        this.MODE = "R";
        //this.items = $("div#playlist .box");
        this.items = this.container.$(".box");
        this.container.style.width  = null;
        this.container.style.height = null;
        this.items.forEach((it) => {
            it.style.position = "relative";
            it.style.top = null;
            it.style.width = null;
        });
    },

    // Create complete itemnode with everything it needs.
    // But you still have to add it somewhere in the DOM.
    createItem: function(dat) {
        let item = elem("div", dat.id, dat.classes.concat("box"));

        if (!playlist.readonly) {
            let delnode = elem("button", "", ["boxdel"], "delete_outline");
            item.appendChild(delnode);
        }
        item.appendChild(elem("div", "", ["boxinner"], dat.title));
        item.appendChild(elem("div", "", ["boxtime"],  dat.time));
        // I feel bad about mentioning the name of this object.
        item.addEvents = function() {
            this.addEventListener("mousedown", playlist.mousedrag);
            this.addEventListener("mouseover", playlist.mousemove);
            this.addEventListener("mouseup", playlist.drop);
            this.addEventListener("touchstart", playlist.touchdrag);
            this.addEventListener("touchmove", playlist.touchmove);
            this.addEventListener("touchend", playlist.drop);
        };
        return item;
    },

    waittransitions: 0,
    awaitrender: false,

    initialize: function(containerID="playlist") {
        this.container = $("#"+containerID);
        this.container.addEventListener("transitionend", ev => {
            if (this.waittransitions) clearTimeout(this.waittransitions);
            this.waittransitions = 0;
            if (this.awaitrender) {
                this.waittransitions = setTimeout(() => {
                    this.waittransitions = 0;
                    if (this.awaitrender) this.awaitrender();
                    this.awaitrender = false;
                }, 100);
            }
        });
        // assume we have data
        this.update();
    },

    // update shown items from data. rearranging accordingly.
    // call setAbsolute() sometime before.
    update: function() {
        this.setAbsolute(); // just in case it's not already set.
        this.container.style.height = null;
        // first create and append "shadow elements" for each date.
        this.data.forEach(dt => {
            let item = this.createItem(dt);
            item.id = "SHADOW___"+item.id;
            item.style.opacity = 0; // or use a class perhaps?
            this.container.appendChild(item);
        });

        let remitems = [];
        let csize = this.container.getBoundingClientRect();
        this.container.style.height = csize.height + "px";
        // check existing items
        this.items.forEach(it => {
            let sit = $("#SHADOW___"+it.id);
            if (sit) { // update position
                it.initY = sit.getBoundingClientRect().top + window.scrollY;
                it.style.top = it.initY + "px";
                remitems.push(sit)
            } else { // remove from DOM
                it.style.background = "#900000";
                it.style.opacity = 0;
                remitems.push(it);
            }
        });
        // assimilate / transform new elements from shadow to normal
        //$("div#playlist .box").forEach(it => {
        this.container.$(".box").forEach(it => {
            if (!it.id.startsWith("SHADOW___")) return; // not a shadow-item
            if ($("#"+it.id.substr(9))) return;         // item exists
            it.initY = it.getBoundingClientRect().top + window.scrollY;
            it.initH = it.getBoundingClientRect().height;
            it.id = it.id.substr(9);
            it.style.opacity = 1;
            it.style.top = it.initY + "px";
            it.style.width = (csize.width - csize.x/3) + "px";
            if (!this.readonly) it.addEvents();
        });
        //$("div#playlist .box").forEach(it => { it.style.position = "absolute"; });
        this.container.$(".box").forEach(it => { it.style.position = "absolute"; });

        this.awaitrender = () => {
            // the container should now contain as many items as data.
            // So let's reorder DOM accordingly
            remitems.forEach(it => {
                try { this.container.removeChild(it); } catch(e) {}
            });

            this.data.forEach(dt => {
                let item = $("#"+dt.id);
                if (item == null) return;
                item.classList = ["box"];
                for (let cl of dt.classes) item.classList.add(cl);
                this.container.appendChild(item);
            });
            this.setRelative();
        }
        // just in case transitionend doesn't fire... cleanup shadow-items
        setTimeout(() => {
            //$("div#playlist .box").forEach(it => {
            this.container.$(".box").forEach(it => {
                if (!it.id.startsWith("SHADOW___")) return; // not a shadow-item
                this.container.removeChild(it);
            });

            if (this.awaitrender) {
                this.awaitrender();
                this.awaitrender = false;
            }
        }, 100);

    },

    resetclass: function(it) {
        it.classList.remove("hover");
        it.classList.remove("move");
    },

    move: function(ly) {
        if (this.from != null) {                 // Start moving
            this.setAbsolute();
            this.drag = true;
            this.from.classList.add("move");
        }
        if (this.drag) {                         // Moving in progress
            if (this.scroll) {
                clearInterval(this.scroll);
                this.scroll = null;
            }
            let my = ly - scrollY;
            let thres = innerHeight / 8;
            if (my < thres) {
                this.scroll = setInterval(()=>{scrollBy(0,-1)}, my/2);
            } else if (my > innerHeight - thres) {
                this.scroll = setInterval(()=>{scrollBy(0,1)}, (innerHeight-my)/2);
            }

            // Recalculate item offsets
            this.items.forEach(it => {
                let y = it.initY;
                it.style.top = y + "px";
                if (it.id != this.from.id) this.resetclass(it);
                if (y > this.from.initY && y < ly) {            // above
                    it.style.top = (y - this.from.initH) + "px";
                }
                if (y < this.from.initY && y + it.initH > ly) { // below
                    it.style.top = (y + this.from.initH) + "px";
                }
                // find target
                if (ly > y && ly < y + it.initH) this.targ = it;
            });
            // move dragged item
            if (this.targ && this.targ.id != this.from.id) {
                let ptst = parseInt(this.targ.style.top);
                if (ly < this.from.initY)
                    this.from.style.top = ptst - this.from.initH + "px";
                if (ly > this.from.initY)
                    this.from.style.top = ptst + this.targ.initH + "px";
            }
        }
    },

    // Event Gehändl.
    // Ich muss ekligerweise dieses Objekt beim Namen nennen, weil "this"
    // das auslösende Element ist.
    mousedrag: function(ev) {
        playlist.from = this;
    },

    touchdrag: function(ev) {
        playlist.from = this;
        playlist.touchly = ev.touches[0].pageY;
        if (!playlist.touchtimer && ev.touches && ev.touches.length == 1) {
            playlist.touchtimer = setTimeout(() => {
                if (playlist.vibration) navigator.vibrate(playlist.vibration);
                playlist.move(playlist.touchly);
                playlist.touchtimer = null;
            }, playlist.touchdelay);
        }
        ev.preventDefault();
    },

    mousemove: function(ev, dit) {
        if (this) dit = this;
        if (!playlist.drag) { // hover
            //$("div#playlist .box").forEach(playlist.resetclass);
            playlist.container.$(".box").forEach(playlist.resetclass);
            dit.classList.add("hover");
        }
        playlist.move(ev.clientY + scrollY);
    },

    touchmove: function(ev) {
        if (ev.touches && ev.touches.length == 1) { // held 1 finger
            if (playlist.drag) {                    // move
                playlist.move(ev.touches[0].pageY);
            } else {                                // scroll
                let delta = playlist.touchly - ev.touches[0].pageY;
                scrollBy(0, delta);
                if (playlist.touchtimer && Math.abs(delta) > 10) {
                    clearTimeout(playlist.touchtimer);
                    playlist.touchtimer = null;
                    playlist.from = null;
                }
            }
            ev.preventDefault();
        }
    },

    drop: function(ev) {
        /****** MOVE ******/
        if (playlist.drag && playlist.targ && playlist.targ.id != playlist.from.id) {
            // rearrange data-array.
            let fromidx = -1;
            let targidx = -1;
            for (let i = 0; i<playlist.data.length; i++) {
                if (playlist.data[i].id == playlist.from.id) fromidx = i;
                if (playlist.data[i].id == playlist.targ.id) targidx = i;
            }
            if (fromidx >= 0 && targidx >= 0) playlist.data.move(fromidx, targidx);
            playlist.fmove(playlist.from.id, playlist.targ.id);

        /****** DELETE ******/
        } else if (!playlist.drag && playlist.from != null) {
            if (ev.target.className == "boxdel") {
                // remove from data-array
                let xidx = -1;
                for (let i = 0; i < playlist.data.length && xidx < 0; i++) {
                    if (playlist.data[i].id == playlist.from.id) xidx = i;
                }
                playlist.data.splice(xidx,1);
                playlist.fdelete(playlist.from.id);

        /****** PLAY ******/
            } else {
                for (let i = 0; i<playlist.data.length; i++) {
                    // remove hilight from all, add it to the this item
                    playlist.data[i].classes =
                        playlist.data[i].classes.filter(i=>i!="hilight");
                    if (playlist.data[i].id == playlist.from.id) {
                        playlist.data[i].classes.push("hilight");
                    }
                }

                playlist.fplay(playlist.from.id);
            }
        }

        if (playlist.from) {
            playlist.items.forEach(playlist.resetclass);
            playlist.from.classList.add("hover");
        }
        playlist.from = null;
        playlist.drag = false;
        playlist.targ = null;
        if (playlist.scroll) {
            clearInterval(playlist.scroll);
            playlist.scroll = null;
        }

        playlist.update();
    }
};

