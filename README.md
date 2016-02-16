# WordPress Theme [Sage](https://roots.io/sage/)
## Anforderungen
Folgendes muss installiert werden:

* sass (`sudo gem install sass`)
* [node.js](http://nodejs.org)
* gulp (`npm install -g gulp`)
* bower (`npm install -g bower`)

## Installieren des Themes

Navigiere in WordPress auf den Ordner Themes: `cd web/[projektname]/wp-content/themes`

### Klonen

```bash
git clone git@github.com:cubetech/wptheme.sage.git wptheme.meinname
```

### Neues Repo auf GitHub erstellen

Neues privates Repo für das Projekt auf GitHub gemäss Namenskonvention erstellen

### Origin anpassen und neues Origin einbinden

```bash
git remote rename origin sage
git remote add origin git@github.com:cubetech/wptheme.meintheme.git
git push -u origin master
```

Nun ist das Theme im Bereich Git bereit, um weiterentwickelt zu werden.
Der originale Upstream ist als `sage` noch verfügbar, so können Weiterentwicklungen per Rebase oder Cherrypick eingebunden werden.
Die History bleibt ebenfalls erhalten, damit nachträglich überprüft werden kann, in welchem Status das Theme exportiert wurde.

## Theme konfigurieren
Nach der Installation des Themes navigiere weiter zum installiertem Theme `cd wptheme.meinname/`

Nun müssen die nötigen Ressourcen geladen werden:
```bash
npm install
npm install -g bower
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
