# Lokale Komponenten aus Plugins

Skye 3.5.1 ergänzt ausschliesslich fehlende Komponenten um den Filter
`skye_component_template(false, $name, $args)`. `get_component()` prüft weiterhin
zuerst seinen kombinierten relativen Namen und sucht native Child-/Theme-Dateien.
Auch der verschachtelte Fallback für alte Ein- und Zweiargumentaufrufe hat
Vorrang. Erst danach wird der Filter aufgerufen. Der Marker
`SKYE_COMPONENT_RESOLVER_VERSION = 1` bezeichnet diesen implementierten Vertrag.

Ein vertrauenswürdiges Plugin darf für einen eigenen exakten Komponentennamen
den festen Pfad seiner lokalen PHP-Komponente zurückgeben. Filter dürfen keine
ungeprüften Request-, ACF- oder Metadaten als Include-Pfad übernehmen. Der
Renderer verwirft Nichtstrings, leere oder überlange Pfade, NUL-Zeichen,
Stream-Wrapper, fehlende/unlesbare Dateien und kanonische Ziele ohne `.php`.
Resolverfehler liefern `false`; Ausnahmen innerhalb einer tatsächlich geladenen
Komponente bleiben sichtbare Programmfehler. `skye_component_html()` stellt
dabei weiterhin die ursprüngliche Pufferhöhe her.

Die expliziten `$args` bleiben der einzige zusätzliche Präsentationskontext.
`extract(..., EXTR_SKIP)` schützt unter anderem `$name`, `$template` und `$args`.
Der normale Builder übergibt seine formatierte ACF-Zeile als `row`; ein Plugin
kann sie verarbeiten, ohne den impliziten ACF-Zeilencursor erneut zu lesen.

Die konkrete Änderung an `lib/rocketsky/helpers.php` und die reguläre
Quellversionierung wurden am 13. November 2026 ausdrücklich freigegeben.
Grundlage ist der bestätigte öffentliche 3.5.0-Stand
`e791ced155ba80dffe2bdecf80e7c963388f9d2d`. Diese Änderung wird im separaten
eigenen Skye-Quell-Worktree vorgenommen. Installierte Themes, Kunden-Forks,
WordPress und andere Originalpakete bleiben unangetastet. Es erfolgt kein
automatischer Push, Releaseexport oder Rollout. Bestehende eigene Dispatcher
mit einer Layout-Allowlist benötigen weiterhin eine gesondert geprüfte
Projektintegration; der neue Helper ändert deren Aufrufweg nicht.

## Tatsächliche Prüfung

`tests/component-resolver-native.php` führt den echten geänderten Helper mit
den originalen Core-Hooks und `locate_template()` aus. Auf originalem,
checksum-geprüftem WordPress 7.1.4 und PHP 8.4.27 bestehen 28 Assertions zu
Child-/Parent-/Legacy-Vorrang, frischen Kontexten, reservierten lokalen Werten,
alten Aufrufen, Fehlern und Pfadgrenzen. Dieser Lauf benötigt keine Datenbank.

Die native Rendezvous-Integration führt ausserdem den unveränderten aktuellen
Skye-Builder mit dokumentierten ACF-Zeilen-Fixtures durch den echten neuen
Plugin-Resolver aus: 22 Assertions bestehen ohne installierte Theme-Brücke.
Das umfasst zwei Termine/Sprachen, private Entwürfe, die Passwortgrenze vor
Zeilenreads und den Vorrang einer nachträglich angelegten Theme-Komponente.
Dies ist kein nativer ACF-PRO-Speicher-/Browsernachweis und kein Nachweis einer
bereits aktualisierten Kundenseite. Der Assetbuild wird durch diesen reinen
PHP-Vertrag nicht verändert; ein späterer regulärer Export erstellt seine
üblichen aktuellen Asset-/Quellfingerprints erneut.
