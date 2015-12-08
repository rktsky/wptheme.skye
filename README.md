# WordPress Theme [Sage](https://roots.io/sage/)
## Anforderungen
Folgendes muss installiert werden:

* sass (`sudo gem install sass`)
* [node.js](http://nodejs.org)
* gulp (`npm install -g gulp`)
* bower (`npm install -g bower`)

## Installieren des Themes

Navigiere in WordPress auf den Ordner Themes: `cd web/[projektname]/wp-content/themes`

Klonen
```bash
git clone git@github.com:cubetech/wptheme.sage.git
```

Umbenennen
```bash
mv wptheme.sage wptheme.meinname
```

## Theme konfigurieren
Nach der Installation des Themes navigiere weiter zum installiertem Theme `cd wptheme.meinname/`

Nun müssen die nötigen Ressourcen geladen werden:
```bash
npm install
bower install --save
gulp
```

Die --save Option wird benötigt, damit bower die Libraries als Abhängigkeiten im .bower.json registriert.

Für das sukzessive Kompilieren von CSS und Javascript wird jetzt der Befehl `gulp watch --fast` eingegeben.

## Wie weiter?
Nun kannst du mit Coda fortfahren. Die CSS und Javascript Dateien werden automatisch geladen.

## Pakete installieren
Um eine Library zu installieren, kann diese unter http://bower.io/search diese gesucht werden.
Eine Library wird danach mit `bower install libraryname --save` installiert.
