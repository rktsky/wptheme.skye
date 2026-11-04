# Migration von Skye 2 auf 3

Skye 3 ist eine neue Hauptversion. Änderungen in einer Entwicklungsableitung übernehmen; vorhandene Projektanpassungen, gespeicherte ACF-Inhalte und Pluginverträge gegen den gesamten Ablauf prüfen. Diese Arbeit veröffentlicht keine automatische Aktualisierung bestehender Volksgarage-Themes.

## Bootstrap 6 Alpha und Build

Skye 3 verwendet Bootstrap `6.0.0-alpha.1`. Der Build nutzt dessen originales CSS-Modul-/Layer-System und die benötigten originalen Drawer-/Menu-Daten-APIs. Eigene SASS-Dateien bleiben erhalten; Bootstrap-5-Variablen, Imports, Utilities und Collapse-/Dropdown-Markup sind keine unverändert gültigen Bootstrap-6-Verträge. In eigenen Komponenten die tatsächlichen Alpha-Dokumentationen und generierten Assets prüfen. Abhängigkeiten werden unverändert aus den gelockten Paketen gebaut, nicht im Vendor-Code korrigiert.

Gulp/Bower/JSCS/JSHint/Travis und das alte Roots-Regelwerk wurden ersetzt. Build und Watch bleiben auf CentOS 7 mit Node 14.21.3/npm 6.14.18 ausführbar. Die Versionen stehen in `.nvmrc` und im Paketmanifest; `npm ci --ignore-scripts` und `npm run build` ersetzen die alte Installationsfolge. Die frühere nicht existierende `gulp translate`-Anweisung wurde entfernt. Übersetzbare Strings bleiben bei WordPress; ein Projekt, das einen POT-Export benötigt, verwendet den dafür vorgesehenen WP-CLI-i18n-Befehl separat.

`dist/` muss vollständig aus einem erfolgreichen Build kommen. Teilweise kopierte Assets, händische Eingriffe und alte `assets.json` neben neuen Dateien sind kein unterstütztes Deployment. Der Releaseweg prüft Manifest und enthaltene Dateien. Quellen-/Watchänderungen lösen einen vollständigen sicheren Neubau aus. Leere Sass-Module und der nicht importierte alte Mixinrest wurden entfernt. Projektmodule ausdrücklich anlegen und importieren; die beibehaltenen eigenen Einstiegspunkte bleiben der Erweiterungsweg.

## Daten und Komponenten

- `get()` liefert raw/native, auch bei aktivem ACF. Der alte dritte Parameter bleibt aus Aufrufkompatibilität erhalten, bedeutet keine Listenauswahl und verspricht keine ACF-Formatierung. Für absichtliche formatierte Feldgrenzen ausdrücklich `get_field()` nutzen.
- Post-/User-/Term-/Optionsidentitäten werden getrennt behandelt. Leere Werte, `false` und `'0'` dürfen nicht zu Daten einer anderen Quelle führen. Aufrufer, die den alten fehlerhaften Fallback nutzten, müssen ihren Besitzer ausdrücklich angeben.
- `get_component()` behält die alte Aufrufform, übernimmt aber keinen lokalen Scope. Modelltragende Komponenten erhalten die Werte im dritten Argument. Neue gepufferte Ausgabe verwendet ausschliesslich `skye_component_html()`; der tatsächliche Loader wird nicht dupliziert.
- News-/404-Reste und die öffentliche Migration von `custom_teasertext` nach `lead` wurden entfernt. Falls ein Altprojekt diese Datenmigration braucht, muss es dafür eine ausdrückliche administrative Migration mit Backup und Ergebnisprüfung ausführen.
- Zugewiesene Templates müssen nativ auswählbare Theme-Templates sein. `base.php`, `index.php`, Bibliotheken und fremde Pfade sind keine gültigen Ersatzansichten. Eigene Post-Type-Templates bleiben über die native Registrierung möglich.

## Entfernte Eingriffe und Erweiterungen

Die eingebetteten Classic-Editor-/SVG-Kopien und der defekte Fremd-Walker wurden entfernt. Classic Editing übernimmt ein kleines eigenes Package über öffentliche Schnittstellen; Menüs verwenden native Walker und scoped Bootstrap-6-Filter. Die alte `MenuCache`-API ist ein cachefreier Darstellungsadapter; manuelle GET-Purges sind wirkungslos. Native LI-Elemente markierter Skye-Menüs verwenden `skye-menu-item` statt der mit Bootstrap 6 kollidierenden Klasse `menu-item`; IDs sowie Current-/Ancestor-/Objekt- und Providerklassen bleiben erhalten. Eigene CSS-/JS-Selektoren für diese LI-Klasse entsprechend migrieren. Untermenü-Links verwenden weiterhin die passende Bootstrap-Klasse.

Der eigene MO-Dauercache, die globale ACF-Pluginunterdrückung und pauschale Customizer-/XML-RPC-/Adminoberflächen-Eingriffe entfallen. Solche Betriebspolitik gehört in eine getrennte bewusste Websitekonfiguration. Alte Beispielpakete, Lizenzaktivator, Browserstack-Werbegrafik, doppelte Login-Datei und nicht verwendete Walker-/Sanitizer-Dateien gehören nicht zum neuen Kern.

ACF PRO und Redis separat bereitstellen. Ein fehlendes ACF führt im Basiskern nicht mehr zum pauschalen Absturz; eigene ACF-Komponenten brauchen weiterhin klare Voraussetzungen und Fallbacks. Vorhandene Redis-Anbieter werden nicht ersetzt oder ergänzt.

## Performance und Medien

Generische Websiteverbesserungen werden übernommen, konkrete Volksgarage-Schriften, Glyphen, Layoutgrössen und generierte Critical-CSS-Auszüge nicht. Ein erster Viewport benötigt eine ausdrückliche vollständige Critical-Layout-Zusage mit passendem Buildfingerprint. Ein bloss bekannter Header beweist diese Abdeckung nicht. Vollständiges CSS bleibt für unbekannte Situationen verfügbar.

Die Medienpipeline verarbeitet Jobs im Hintergrund und erhält native Originale. Eigene Zusatzgrössen bleiben in der vorhandenen Konfiguration. Vor dem Aktivieren in einem Altprojekt den dokumentierten Cron-/Speicher-/Formatevertrag und die Abschaltung prüfen. Details einschliesslich Fehleraufbewahrung und Cachegrenzen stehen in [Medien](media.md).

## Abnahme

Eigene Seiten, Such-/Archiv-/Fehlerzustände, ACF-Baukasten, Formulare/AJAX, Menüs, Passwort-/Members-Regeln, Medienänderung/-löschung und Ausgabe-Caches prüfen. Logik-/Styling-Commits gemeinsam testen. [Prüfungen](testing.md) unterscheidet tatsächliche lokale Nachweise von offenen Hosting-, Browser- und Gerätelücken; die früheren Website-Scores sind keine pauschale Skye-Abnahme.
