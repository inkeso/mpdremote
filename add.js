/**********************************************************************\
*             Search, browse and add files/dirs/streams                *
\**********************************************************************/

"use strict"

let adder = {
    currentpath: "",    // keep track of where we are
    lifu: null,         // contains last item-list function
    podcasts: [],       // contains names of podcast-bookmarks

    createcrumbtrail: function() {
        let root = elem("button", "" , [], "Music");
        root.addEventListener("click", ev => {
            adder.goto("");
            //$("#search").value="";
        });

        let CT = $("#crumbtrail");
        //CT.replaceChildren(root); // altes Webkit mag das nicht? Schade.
        CT.innerHTML = ""
        CT.appendChild(root);

        let trail = this.currentpath.split("/");
        for (let di = 0; di < trail.length; di++) {
            if (trail[di].length == 0) continue;
            let crumb = elem("button", "" , [], trail[di]);
            crumb.addEventListener("click", ev => {
                adder.goto(trail.slice(0, di+1).join("/"));
            });
            CT.appendChild(crumb);
        }
    },

    dirrow: function(d){
        let row = elem("div", "", ["row", "dir"]);
        let refresh = elem("button", d, ["refresh"], "refresh");
        refresh.addEventListener("click", ev => {action("refresh", ev.target.id, ()=>this.items());});
        row.appendChild(refresh);
        let subdir = d.slice(d.lastIndexOf("/")+1);
        let line = elem("div", d, ["line"], subdir);
        line.addEventListener("click", ev => {this.goto(ev.target.id);});
        row.appendChild(line);

        // Only add "Add Dir" Buttons below toplevel and on real folders
        // (ie. not virtual podcast-bookmark-folders)
        let adddir = true;
        if (!this.currentpath) adddir = false;
        if (this.currentpath == "streams" && this.podcasts.includes(subdir)) adddir = false;
        if (adddir) {
            let addir = elem("button", d, ["adddir"], "playlist_add");
            addir.addEventListener("click", ev => {action("add", ev.target.id, ()=>this.items());});
            row.appendChild(addir);
        }
        return row;
    },

    filerow: function(f, showcd=true, showartist=true, showfilename=false) {
        let row = elem("div", f.file, ["row", "file"]);

        // Tracknumber or playlist-index in own div
        let rtag = elem("div", "", ["track"], "")
        if ("Disc" in f && showcd) rtag.innerText += f.Disc+"-";
        if ("Track" in f) rtag.innerText += f.Track;
        row.appendChild(rtag);
        if ("inplaylist" in f) {
            rtag.innerText = "["+f.inplaylist+"]";
            row.classList.add("inplaylist");
        }

        // create a title from filename or Tags
        let ltxt = "";
        if (f.Title.length < 1 || f.Title == f.file) {
            ltxt = f.file.slice(f.file.lastIndexOf("/")+1, f.file.lastIndexOf("."));
        } else {
            if (("Artist" in f) && showartist)
                ltxt += f.Artist+" - ";
            ltxt += f.Title;

        }
        let line = elem("div", "", ["line"], ltxt);
        row.appendChild(line);

        // insert Time
        if ("Time" in f) {
            let tim = elem("div", "", ["time"], parseInt(f.Time).humanTime());
            row.appendChild(tim);
        }

        // Insert filename?
        if (showfilename) row.appendChild(elem("div", "", ["lfile"], f.file))

        row.addEventListener("click", ev => {
            let gid = ev.target.id ? ev.target.id : ev.target.parentElement.id;
            if ("inplaylist" in f) {
                action("rmfile", gid, ()=>this.lifu());
            } else {
                action("add", gid, ()=>this.lifu());
            }
        });
        return row;
    },

    // get items, create list (diretory-based)
    items: function() {
        fetch("dir", this.currentpath, answer => {
            //console.log(answer);
            let IT = $("#itemlist");
            //IT.replaceChildren();     // Webkit mag das nicht? Schade.
            IT.innerHTML = "";

            // Add Directories
            answer.directories.forEach(d => {
                IT.appendChild(this.dirrow(d));
            });

            // Add Files. Omit Artist if it's the same for every file.
            let nart = new Set(answer.files.map(e=>e.Artist)).size;
            let nitm = answer.files.length;
            let showartist = (nart > 1 || nitm == 1);
            let showcd = new Set(answer.files.map(e=>e.Disc)).size > 1;

            answer.files.forEach(f => {
                IT.appendChild(this.filerow(f, showcd, showartist, false, adder.items));
            });

            // Repurpose Search-field to add streams-urls
            let SR = $("#search");
            SR.placeholder = this.currentpath == "streams" ? "http://stream.url" : "Suche...";

            this.lifu = ()=>this.items();
        });
    },

    // go to specified dir
    goto: function(dir=this.currentpath) {
        this.currentpath = dir;
        this.createcrumbtrail();
        this.items();
        scrollTo(0,0);
    },

    addfile: function(){},
    adddir: function(){},
    addstream: function(){},

    dosearch: function() {
        let term = $("#search").value;
        let cmd = "search="+encodeURIComponent(term);
        if (this.currentpath) {
            cmd+="&indir="+encodeURIComponent(this.currentpath);
        }
        fetch(cmd, null, (answer)=>{
            let IT = $("#itemlist");
            //IT.replaceChildren();
            IT.innerHTML = "";
            answer.forEach(f => {
                IT.appendChild(this.filerow(f, true, true, true));
            });
            this.lifu = ()=>this.dosearch();
        });
    },

    searchkey: function(ev) {
        let term = $("#search").value;
        if (ev.key == "Enter") { //  || term.length > 5) {
            if (adder.currentpath == "streams") { // add stream
                action("add", term);
            } else { // Normal search
                if (term.length > 0) {
                    adder.dosearch();
                } else {
                    adder.goto();
                }
            }
        }
    },


};
