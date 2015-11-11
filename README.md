# Wordpresstheme [Sage](https://roots.io/sage/)
## Anforderungen
Folgendes muss installiert werden:

* sass (`sudo gem install sass`)
* [node.js](http://nodejs.org)
* gulp (`npm install -g gulp`)
* bower (`npm install -g bower`)

## Installieren des Themes

Navigiere in Wordpress auf den Ordner Themes: `cd web/[projektname]/wp-content/themes`

Klone folgendes Repository von github in diesen Ordner `git clone https://github.com/roots/sage default` (default steht für den Ordnername)

## Theme konfigurieren
Nach der Instalation des Themes navigiere weiter zum installiertem Theme `cd default/`

Nun müssen die nötigen Ressourcen geladen werden:
* `npm install`
* `bower install`
* `gulp`

Für das sukzessive Kompilieren von CSS und Javascript wird jetzt der Befehl gulp watch --fast eingegeben.

## Wie weiter?
Nun kannst du mit Coda fortfahren. Die CSS und Javascript Dateien werden automatisch geladen.


## Navigation
Damit die Navigation korrekt angezeigt wird, müssen zwei neue Files heruntergeladen und eingebunden werden.

* [wp_bootstrap_navwalker.php](https://github.com/twittem/wp-bootstrap-navwalker)
* [header.php](https://gist.github.com/retlehs/1b49f6c00d5140962d56)

Der Navwalker wird im `lib/` Ordner abgelegt und folgender Code in `functions.php` angepasst:

```php
$sage_includes = [
  ...
  'lib/wp_bootstrap_navwalker.php', // Boostrap Navigation
];
```

Danach muss das File `header.php` noch ersetzt werden unter `templates/`

Fertig ist die Installation. Yeeeah :D
