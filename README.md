# Wordpresstheme [Sage](https://roots.io/sage/)
## Anforderungen
Folgendes muss installiert werden:

* sass (`sudo gem install sass`)
* [node.js](http://nodejs.org)
* gulp (`npm install -g gulp`)
* bower (`npm install -g bower`)

## Installieren des Themes

Navigiere in Wordpress auf den Ordner Themes: `cd web/[projektname]/wp-content/themes`

Klonen
git clone git@github.com:cubetech/wptheme.sage.git

Umbenennen
mv wptheme.sage wptheme.meinname

## Theme konfigurieren
Nach der Instalation des Themes navigiere weiter zum installiertem Theme `cd wptheme.meinname/`

Nun müssen die nötigen Ressourcen geladen werden:
* `npm install`
* `bower install`
* `gulp`

Für das sukzessive Kompilieren von CSS und Javascript wird jetzt der Befehl gulp watch --fast eingegeben.

## Wie weiter?
Nun kannst du mit Coda fortfahren. Die CSS und Javascript Dateien werden automatisch geladen.

