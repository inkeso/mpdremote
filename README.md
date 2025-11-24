# MPDremote v3

mpdremote2 funktionierte gut, die Wartung / Erweiterung ist aber etwas
nervig, wegen der schlechten Struktur.
Daher ein kompletter Rewrite, der sich optisch aber kaum unterscheiden
wird.
Der Unterschied ist: Wir planen vorher mal ein wenig...

## Backend

### inc/config.php

Der Name sagt alles. Einstellungen für MPD-Adresse/-Port, Stream-
bookmarks usw.
Der `inc`-Ordner muss nicht von außen erreichbar sein. Hauptsache PHP
kann den lesen/includen.

### get.php Liefert Informationen

Response ist ein JSON-String.

Die Hauptstruktur sowohl für die aktuelle Titelanzeige als auch für die
Playlist und die Dateiliste sieht so aus:
```
TRACKINFO ::= {
    'Id': playlist-id,
    'file': "full/path/to/file.mp3",
    'Artist': "Unknown Artist",
    'Title': "Unknown Title",
    'Album': "Unknown Album",
    'Track': "12",             # Kann auch "8/14" sein o.ä.
    'Time': "351",             # Liedlänge in Sekunden

    # ... und mehr, je nachdem, welche Tags am Start sind.

    'fromshuffle': true         # wenn vom Shuffle hiunzugefügt.
    'inplaylist': 1             # Position in der playlist relativ
                                # zum aktuellen Track.
}
```

Dabei ist die Anwesenheit der Elemente nicht garantiert.

#### get.php?current

```javascript
    {
     'trackinfo': <TRACKINFO>,
     'time': 123,                       # Spieldauer in Sekunden
     'state': 'play',                   # 'play', 'pause' oder 'stop'
     'playlistcount': 123,              # Anzahl der Tracks in der Playlist
     'next': <TRACKINFO>                # Nächster Titel in Playlist
    }
```


#### get.php?playlist

```javascript
    [<TRACKINFO>, <TRACKINFO>, ...]
```

#### get.php?dir=<DIR>

<DIR> ist der komplette Pfad zum gewünschten Ordner, ausgehend vom
MPD-root.

```javascript
    {
     'directories': ['subdir1', 'subdir2', ...],
     'files': [<TRACKINFO>, <TRACKINFO>, ...]
    }
```

Die subdirs im `dirs`-array enthalten den kompletten Pfad.

##### Streams

- Im Ordner "streams" werden zusätzlich zu den dort vorhandenen Dateien/Ordnern:
  - Streambookmarks aus der Datei hinter STREAMBOOKMARKS geladen
  - Ordern mit div. Streams (Podcasts) aus PODCASTS (siehe config.php) generiert
- Podcast-Ordner bekommen keinen add-dir-button (dafürd dient get.php?podcastnames)

Beispiel `streambookmarks`
```
LoHRO	http://stream.lohro.de:8000/lohro.mp3
Saetchmo Livestream	http://stream.saetche.net:8000/echochamber
SomaFM Drone Zone	http://ice.somafm.com/dronezone
SomaFM Groove Salad	http://ice.somafm.com/groovesalad
917XFM	http://mp3channels.rockantenne.hamburg/917xfm
NTS	http://stream-relay-geo.ntslive.net/stream
```
Die Links verweisen direkt auf den stream

Beispiel `podcasts`
```
Saetchmo	https://hearthis.at/saetchmo/podcast/
Alternativlos	https://alternativlos.org/ogg.rss
Music for Programming	http://www.musicforprogramming.net/rss.php
KFMW AK	https://soundcloud.com/der-das-kfmw-ak/tracks
Laut & Luise	https://soundcloud.com/lautundluise/tracks
```
Bei den Podcasts gehen die Links auf entweder einen podcast-konformen RSS-Feed
oder eine soundcloud-seite aus der die dort gelisteten tracks extrahiert werden.
Beachte auch die Hinweise zum soundcloud-API-key in der `config.php.example`.

##### NEU / Recent

Sonderbahndlung des Ordners "NEU": `get.php?dir=NEU/... <n> Tage` gibt alle
Tracks (Absteigend nach Alter) zurück, die vor weniger als `<n>` Tagen
modifiziert/hinzugefügt wurden. ("Presets" auf 30, 90, 180 Tage; siehe get.php)


#### get.php?podcastnames

Gibt eine Liste der podcast-bookmarks zurück (nur die Namen).


#### get.php?search=<str>

Gibt eine Dateiliste mit Suchergebnissen zurück. Für die Einfachheit
ist es im gleichen Format wie ?dir=... aber `dirs` ist immer leer.
Denn nach Verzeichnissen suchen wir nicht. Noch nicht...


#### get.php?albumart

Gibt das Coverbild des aktuellen Titels zurück oder einen Platzhalter, wenn kein
Coverbild gefunden wurde. (cover.jpg, cover.png im Verzeichnis des aktuellen Tracks)
mpd unterstützt auch bmp und tiff, Browser nicht. Übertragen wird es trotzdem.
Daher lieber keine cover.bmp oder cover.tiff haben.

### do.php führt Aktionen aus.

Titel hinzufügen, Playlist sortieren, usw. Dabei wird vorher geprüft,
ob der Nutzer dazu auch berechtigt ist (lokale IP oder Log-in).
Im Erfolgsfall ist die Response leer, ansonsten enthält sie eine
Fehlerbeschreibung.

#### do.php?refresh=<dir>

Datenbank aktualisieren. Ggf. für einen Unterordner, aber <dir> darf auch leer
sein. Der Aufruf blockiert solange, bis das upddate durch ist bzw. bis php
das skript abbricht (Update seitens mpd läuft dann trotzdem weiter).

Ich hatte überlegt das so nur anzustoßen und dann per request zu fragen, obs
noch läuft, aber was soll das, dauert normalerweise ja nicht lange.

#### do.php?add=<file>

Datei oder Verzeichnis zur Playlist hinzufügen. Die Datei (bzw. alle Dateien im
Verzeichnis, bis zu max. 500 (siehe inc/config.php) wird nach dem aktuellen
Lied oder dem letzten von Hand hinzugefügten Lied in die Playlist eingefügt.
außerdem kann man so direkt streambare urls hinzufügen sowie soundcloud-links
(sowohl zu einzelnen Tracks als auch zu Playlists, Users, etc)

#### do.php?pause, do.php?next, do.php?prev

Pause toggeln, Nächster Track, Vorheriger Track.

#### do.php?podcastnames

gibt ein Array der podcast-namen zurück

#### do.php?skip=<to>

Im aktuellen Lied zur angegebenen Stelle springen.
Wenn 0 ≤ <to> ≤ 1: zu dem Bruchteil des Liedes springen
(Neue Zeit in Sekunden = <to> * Länge des Liedes in Sekunden)
Wenn <to> > 1: Zu der Zeit (in Sekunden) springen.

#### do.php?mv=<from>,<to>

Track in der Playlist verschieben. <from> ist die zu verschiebende Id.
<to> ist die Id, an dessen Stelle das eingefügt wird.

Vielleicht für später: Gruppen verschieben.

#### do.php?go=<id>

Zum Lied mit der Id <id> innerhalb der aktuellen Playlist springen.
Falls <id> nicht in der playlist ist, passiert nichts.

#### do.php?rm=<id>

Track <id> aus Playlist entfernen.

#### do.php?rmfile=<filename>

Track anhand des Dateinamens aus Playlist entfernen.


## Frontend

Das ist das eigentlich schöne an diesem rewrite: Ich kann da mehr als
ein Frontend machen, sogar eins, daß ganz ohne JS funktioniert und damit
auch in Krüppelbrowsern wie dillo, netsurf, lynx, w3m usw. funktionieren
dürfte.

Hauptaugenmerk liegt aber natürlich auf der komfortablen, dynamischen
JS-SPA, die dann auch für Tatschgeräte gut funktionieren soll.

### index.php - das Hauptfrontend

Bei »Controls« wird neben dem aktuellen nun auch das nächste Lied angezeigt
und man kann es dort auch löschen.
Außerdem kann man im Dateipfad die übergeordneten Ordner anklicken um direkt
dorthinzugelangen (im Tab »Add Song«)

Die Playlist ist per drag'n'drop umsortierbar und updated sich dynamisch von selbst.

Bei »Add Song« ist das Suchfeld immer eingeblendet und agiert Ordnerweise. Im
Ordner "streams" kann man statt zu Suchen eine URL pasten, die dann direkt der
Playlist hinzugefügt wird. Unterstützt werden dort Direktlinks zu medien-
dateien/-streams & soundcloud (dort auch Links zu Playlisten, Usern, etc. also
mehr als eine Datei auf einmal). Keine Ahnung ob links zu .m3u oder .pls auch
funktionieren würden. (TODO: rausfinden)

#### Parameter

##### index.php?skin=<CSS-file>

CSS aus dem skins-ordner laden. Bsp: `index.php?skin=foo` läd die Datei
skins/foo.css. Wenn die nicht gefunden wird oder der parameter leer ist,
wird die `skins/default.css` geladen.
Wie gehabt ist das eigentliche Layout abgetrennt in der `layout.css` und
wird immer vorher geladen. Dort sind KEINE Farb- oder Größenangaben!

#### index.php?player[=visu][#play]

Eine Streamabspielmöglichkeit kann per parameter `...&player` eingebunden werden.
Damit das klappt, muss der Pfad in der `inc/player.html` angepasst werden.

Setzt man den Parameter `...&player=visu`, wird im Hintergrund der Seite eine
Visualisierung dargestellt, sofern vom Browser unterstütz.

Wenn man dann noch `#play` anhängt, wird der stream direkt beim laden gestartet.
(Sofern der Browser das zulässt, meistens funktioniert das nicht)

### static.php

Das JS-freie old-school-HTML-frontend. CSS ist inline, also keine skins.
Funktioniert auch in Browsern wie netsurf, dillo, w3m, (e)links etc.
Ist nicht ganz so komfortabel, dafür sehr resource-schonend.

### telnet.php (_TODO_)

Für wenns mal ganz ohne Browser gehen muss würd ich hier denn noch ein
terminal-frontend bauen. Vielleicht. So richtig mit ANSI und allem
pipapo. Ich weiß aber noch nicht, wie/ob damit sinnvoll interagiert werden kann.



# TODO
 - Feedback beim Hinzufügen von stream-urls und dirs
 - Moar Layout(s) (s.o.)

## Ideen:
 - Playlists speichern/laden. Aber was machen wir dann mit MPM?
   Der pfuscht ja dauernd dazwischen...
 - MPM in der Playlist schaltbar machen
 - Recent streams (die letzten 𝒏 manuell hinzugefügten links)

