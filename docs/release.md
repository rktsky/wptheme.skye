# Qualität und Release-Verzeichnis

## Lokaler Export

Ein Release entsteht aus einem sauberen, vollständig committeten Checkout. Redis und ACF PRO sind weiterhin separate Originalpakete und werden nicht eingebettet. Bootstrap ist auf `6.0.0-alpha.1` festgelegt.

```sh
node --version # v14.21.3
npm --version # 6.14.18
npm ci --ignore-scripts
npm audit --audit-level=moderate
node tools/release.mjs /absoluter/existierender/elternordner/skye-3.5.0
```

Der Zielordner darf noch nicht existieren und muss ausserhalb des Quellcheckouts liegen. Das Programm baut den normalen Assetstand, prüft den unveränderten Gitstand nochmals und exportiert den erfassten Commit über `git archive` in ein temporäres Verzeichnis. Es schreibt keine ZIP-Datei und veröffentlicht oder installiert nichts. Nach abgeschlossener Prüfung wird das vorbereitete Verzeichnis an den Zielpfad verschoben. Vorhandene Ziele werden abgelehnt; ein Fehler entfernt ausschliesslich das vom Programm angelegte temporäre Verzeichnis.

`.gitattributes` ist die verbindliche Exportgrenze. Tests, Tools, interne Dokumentation, CI-Konfiguration, Entwicklungsassets, Paketmanifeste und Entwicklungskonfiguration gehören nicht zum ausgelieferten Theme. Der Publisher verweigert zusätzlich einen Export mit `docs/`. Für das Webroot den geprüften Export verwenden, nicht das vollständige Quellrepository. Lokale `info/attributes`-Überschreibungen werden abgelehnt; globale Attributdateien verändern diesen Export nicht. Symlinks und Submodule sind keine zulässigen Releasequellen. Ignorierte Dateien unter den Build-Quellbereichen werden abgelehnt, damit etwa eine lokale `.env` nicht versehentlich als Bild-/Fontasset gebaut wird.

Es werden ausschliesslich die im gerade erzeugten `dist/assets.json` referenzierten Dateien und die dort ausdrücklich ausgewiesenen aktuellen `_licenses` übernommen. Jedes Asset muss eine reguläre lokale Datei mit passendem Inhaltsfingerprint sein. Pfadtraversal, Wrapper, Symlinks, fehlende Dateien, leere Lizenzen, ungültiges JSON und unvollständige Performanceverträge brechen den Export ab. Alte weiterhin im lokalen `dist/` vorhandene Hashdateien werden nicht mitgeliefert. Das ist von der absichtlichen Aufbewahrung alter Dateien bei einem laufenden Deployment zu unterscheiden.

Ab 3.2 müssen auch `mobile-navigation.js` und `mobile-navigation.css` als
vollständige Parent-Buildartefakte vorhanden sein. Der Export prüft ihre
Inhaltsfingerprints und liefert sie samt nativem Paket und Komponenten mit;
das installierte Theme benötigt weder Node noch die ausgeschlossenen
Entwicklungssourcen. Ein Childmanifest ersetzt diese Paketartefakte nicht.

Ab 3.5 gehören `yoast-analysis.js`, `content-sources.js`,
`content-sources-yoast.js` und `content-sources.css` zum vollständigen Paketbuild.
Fehlende Rolleneinträge werden im Export abgelehnt. Die Ressourcen werden nur
in ihrer gültigen Backendintegration geladen. Der native Quellencontroller und
sein Yoast-Adapter sind deshalb auch ohne semantisches Kundenmodell vollständig
im Export vorhanden. Für Git 1.8 bleibt die
saubere Exportprüfung über den [engen Legacy-Fallback](release-compatibility.md)
erhalten; ein unbekannter Gitfehler bricht weiterhin ab.

Die eigene Theme-Lizenz sowie die fünf Original-Lizenzen von Bootstrap, Font Awesome und Floating UI bleiben enthalten. Die erfassten Critical-PHP-Quellen müssen im Export denselben SHA-256-Digest wie im Build haben. `release.json` dokumentiert Commit, Theme-/Bootstrap-Version, verwendete Node-Version und SHA-256-Digests sämtlicher Nutzdateien. Die Datei inventarisiert sich selbst nicht. Theme- und Paketversion müssen übereinstimmen. `.nvmrc` und `.npmrc` bleiben als Entwicklungskonfiguration aus dem Release ausgeschlossen. Ein sauberes Exportverzeichnis ist ein überprüfbares Artefakt und kein Nachweis über eine bereits erfolgte Veröffentlichung.

Git, `tar`, die dokumentierte Node-/npm-Laufzeit und die aus dem Lockfile installierten Buildabhängigkeiten sind Voraussetzungen. Eine installierte Produktionssite oder Produktionszugangsdaten werden für den Export nicht benötigt.

## Übernahme von 3.5 in vorhandene Projekte

Vor dem Merge Rootfeld, native ACF-Gruppen, eigene Quellencontroller und
Analyseadapter prüfen. Source-Conditional-Logic am gesamten Builder durch den
[Quellenvertrag](editor-sources.md) ersetzen. Keine alten Sichtbarkeitscontroller
oder Leaf-Ausschlüsse parallel übernehmen.

Ein gebundener leerer Builder darf keinen inaktiven Editor veröffentlichen.
Eine gültig gespeicherte Builderwahl bleibt auch bei verlorenem Feldprofil
verbindlich. Diese Verhaltensänderung einschliesslich leerer alter Builderseiten
im Projekt ausdrücklich prüfen. Nicht verwaltete unbekannte Altprofile behalten
ihren nativen Fallback.

Auch [SourcePublicContext](public-content-sources.md) ändert die öffentlichen
Exporte: Automatische Builderauszüge, native öffentliche REST-Inhalte und
Feedvolltext bleiben ohne gültige semantische PHP-Projektion leer. Den Callback
`skye_yoast_analysis_content` deshalb vor dem Merge anhand der tatsächlichen
Layouts prüfen und die Exportantworten abnehmen. Manuelle Auszüge, explizite
SEO-/Social-Beschreibungen und autorisierte native REST-Edit-Leser behalten
ihre nativen Verträge.

`components/page/content.php` ist jetzt der gemeinsame Dispatcher;
`components/page/native.php` enthält die reine `the_content()`-Ausgabe mit
Pagination. Eigene Overrides entsprechend ihrem tatsächlichen Zweck anpassen,
ohne den nativen Fallback wieder in den Dispatcher zurückzuführen. Danach den
kompletten Ablauf mit beidseits ungespeicherten Änderungen, visuellen Editoren,
normalem noncegebundenem Save und Reload beider Quellen abnehmen. Ein sauberer
Export oder eine bestandene VM-Suite ersetzt diesen Browser-/Speichernachweis
nicht. Einen abgelehnten Browser-Save mit AJAX-Vorprüfung und einen direkten
POST ohne diese Vorprüfung getrennt prüfen. Core kann im zweiten Fall schon
persistiert sein, bevor ACF ablehnt; der Quellenvertrag führt keine gemeinsame
Transaktion ein. Der aktuelle Prüfstand gehört in den
[Projektlern-Nachweis](project-learnings-3.5.0.md).

## Prüfprogramme

```sh
SKYE_BUILD_NODE=/absoluter/pfad/node14 /absoluter/pfad/node24 --test tests/release.test.mjs
SKYE_WORDPRESS_CORE=/absoluter/pfad/wordpress php tools/testing/run.php
```

Der Node-24-Testprozess startet den tatsächlichen Node-14-Compiler über `SKYE_BUILD_NODE`; diese Trennung gilt auch für `tests/build.test.mjs`. Der einfache Loaderlauf `npm run test:performance` funktioniert direkt mit Node 14. Die Release-Regression arbeitet mit einem Wegwerf-Gitrepository und dem tatsächlichen Build-/Exportcode. Sie prüft aktuelle Lizenzdateien und unveränderte Manifestbytes, den Ausschluss alter Ausgaben und Entwicklungsdateien, Dirty-/Ignored-Quellen, lokale Exportüberschreibungen, vorhandene beziehungsweise unsichere Ziele, beschädigte Dateien, Symlinks und fehlende oder ungültige Manifestdaten.

Der PHP-Runner führt jeden ausdrücklich klassifizierten Regressionseinstieg in einem frischen PHP-Prozess aus. ACF-Anwesenheit und -Abwesenheit sind eigene Core-Testfälle. Neue unklassifizierte PHP-Dateien unter `tests/` führen zum Fehler, statt stillschweigend nicht ausgeführt zu werden. Fixtures, der lokale Serverrouter und Programme für die echte Wegwerf-Site sind ausdrücklich aus diesem Standalone-Runner ausgeschlossen; die CI führt die Siteprüfungen separat aus. Ein fehlgeschlagener Prozess ergibt einen fehlgeschlagenen Gesamtlauf. Einzelprotokolle liegen unter `.git/runtime/php-tests/`; `SKYE_TEST_LOG_DIR` kann einen anderen lokalen Protokoll-Elternordner vorgeben.

## CI-Verträge

`.github/workflows/quality.yml` beschreibt folgende unabhängige Nachweise:

- Der Assetjob installiert den npm-6-Lockfile-Stand mit Node `14.21.3` und npm `6.14.18`, strikter Engineprüfung und deaktivierten Installationsskripten. Der normale Build und der Releaseexport laufen tatsächlich unter Node 14. Die modernen `node:test`-Harnesses laufen separat mit Node `24.22.2` und starten ihre Compilerprozesse über `SKYE_BUILD_NODE`. Die gebauten Assets werden unverändert an PHP- und Browserjobs übergeben.
- Ein zusätzlicher Job prüft Build und Watch im originalen CentOS-7.9-Userland mit glibc 2.17. Checkout und JavaScript-Actions laufen auf dem Ubuntu-Host; im Container wird ausschliesslich das offizielle Node-14.21.3-Linux-x64-Archiv ausgeführt. Image und Archiv sind an feste Digests gebunden. Die Prüfung installiert den Graph frisch mit npm 6 und testet echte Buildfehler, Wiederaufnahme, neue Dateien, PHP-Quellenänderungen und SIGTERM während eines Builds. Weder Host-`node_modules` noch Systembibliotheksänderungen ersetzen diesen Lauf. Der Container teilt den Hostkernel; eine CentOS-3.10-Kernelprüfung wird nicht behauptet.
- Vier PHP-Jobs prüfen explizite Paare: WordPress `6.8.12` mit PHP `8.3` und `8.4`, WordPress `7.1.4` mit PHP `8.3` und `8.5`. Sie installieren die gelockten Qualitätswerkzeuge mit Composer `2.11.1`, auditieren sie und prüfen Architektur, Dokumentation, Formatierung, Syntax, Standalone-Regressionsprozesse und die tatsächliche Wegwerf-Site. Der tatsächliche Footer wird mit einem über Core registrierten Widget sowie einer leeren Sidebar geprüft, ohne Widgetoptionen zu speichern. Die Originalerweiterung Classic Editor wird separat installiert und in zwei frischen Site-Prüfphasen zusätzlich aktiviert.
- Dieselben vier PHP-Jobs führen nach dem Fixture-Setup zusätzlich die [native Updatewächter-Suite](../tools/update-guard/README.md) aus. Der offizielle WP-CLI-PHAR `2.12.0` wird aus dem festen Releasepfad geladen und vor Ausführung gegen SHA-256 `ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c` geprüft. `SKYE_SITE_ROOT` bleibt bei der vorhandenen `.git/runtime/site/wordpress`-Fixture; `SKYE_WP_CLI` zeigt separat auf `.git/runtime/wp-cli-2.12.0.phar`. Ein Download-, Digest- oder Testfehler lässt den Job fehlschlagen. Das vollständige native Protokoll liegt im bestehenden eng begrenzten PHP-Artefakt; eine zusätzliche begrenzte Fehlerannotation verwendet den vorhandenen gemeinsamen Serializer. Die Anzahl von neun Jobs und die bestehende Core-/PHP-Matrix ändern sich nicht.
- Drei Browser-Jobs prüfen die echte lokale WordPress-Site mit Chromium, Firefox und WebKit unter Linux. Playwright `1.64.1` und sein gleich versioniertes `playwright-core` werden ausschliesslich als isoliertes CI-Werkzeug installiert. Der Router startet nur auf Loopback; die Browserprüfungen blockieren externe Requests. Nach dem normalen Seitenlauf prüft jede Engine ohne optionalen Ausschluss auch den positiven Critical-CSS-Pfad: verzögertes vollständiges CSS, stabile Geometrie und Typografie, einzelne native Übertragung, spätes Opt-out und den Fallback ohne JavaScript. Der Test installiert seine eigene exklusive MU-Fixture nur in der lokalen Wegwerf-Site und entfernt die unveränderte Datei im Abschlussblock. Ein vorhandenes Plugin wird niemals überschrieben. Die Serverprozesse werden auch bei Testfehlern beendet; normale und Critical-Screenshots liegen in getrennten Engine-Verzeichnissen.

Die PHP-Matrix folgt der [offiziellen WordPress-Kompatibilitätstabelle](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/), geprüft am 04.11.2026: WordPress 6.8 unterstützt PHP 8.3/8.4, ausdrücklich aber nicht 8.5; WordPress 7.1 unterstützt alle drei Versionen. Die vier deklarierten Kombinationen führen jeweils die vollständigen Prüfungen aus. Die nicht unterstützte Kombination WordPress 6.8/PHP 8.5 gehört nicht zum zugesagten Kompatibilitätsumfang.

Die beiden Uploads für PHP- und Browsernachweise aktivieren `include-hidden-files` ausdrücklich, da GitHub auch normale Dateien unter `.git/` als versteckt behandelt. Ihre Pfade bleiben auf die genannten Prüfprotokolle, Ergebnisdateien und Screenshots beschränkt; Datenbanken, lokale WordPress-Konfiguration und das übrige Git-Verzeichnis werden nicht hochgeladen.

Coverage-Erweiterungen werden nicht installiert, da diese Tests keine Coverage-Berichte erzeugen. Überholte Prüfläufe derselben Workflow-/Event-/Ref-Kombination werden abgebrochen; andere Branches und Ereignistypen bleiben getrennt. `contents: read` und Checkout ohne gespeicherte Zugangsdaten reichen aus. Der Workflow enthält keinen Deployment- oder Schreibschritt zu einem Produktivsystem. Testfehler werden nicht über `continue-on-error`, dynamische Ausschlussfilter oder fehlende optionale Dateien verborgen.

Die Action-Versionen wurden am 03.11.2026 gegen die offiziellen Release-/Tagobjekte geprüft und auf Commit-SHAs festgelegt: Checkout `v7.0.1`, Setup Node `v7.1.0`, Upload Artifact `v7.0.2`, Download Artifact `v8.0.2` und Setup PHP `2.40.0`. Die Kommentare im Workflow nennen die lesbaren Versionen. Composer-/Playwright-Versionen wurden ebenfalls an ihren offiziellen Anbieterquellen geprüft. Ein geänderter Pin erfordert eine erneute Integrationsprüfung.

Diese Datei beschreibt die konfigurierten CI-Verträge. Ein lokal erfolgreicher Lauf ist kein tatsächlich erfolgter GitHub-Actions-Lauf. Chromium ist kein Edge-Nachweis, WebKit kein echter Safari-/iOS-Nachweis; Linux-Prüfungen ersetzen keine macOS-/Windows-/Touch-Geräteabnahme. Betriebssystem, Engine, reale Version und offene Geräteprüfungen gehören in den abschliessenden Testnachweis.

## Primärquellen

- [Git-Export und Attribute](https://git-scm.com/docs/git-archive): committierte Exportgrenze und Archive ohne Arbeitskopiedateien.
- [GitHub Checkout](https://github.com/actions/checkout), [Setup Node](https://github.com/actions/setup-node), [Upload Artifact](https://github.com/actions/upload-artifact), [Download Artifact](https://github.com/actions/download-artifact): offizielle Action-Eingaben und Releases.
- [Setup PHP](https://github.com/shivammathur/setup-php): PHP-/Extension-/Toolmatrix und `coverage: none`.
- [PHP-Unterstützung](https://www.php.net/supported-versions.php): Runtime-Unterstützung und dokumentierte Grenzen.
- [WordPress-/PHP-Kompatibilität](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/): offiziell unterstützte Versionspaare, geprüft am 04.11.2026.
- [Offizielles Node-Distributionsverzeichnis](https://nodejs.org/dist/index.json): geprüfter Node-24-Patchstand `24.22.2` vom 27.10.2026.
- [Composer-Versionen](https://getcomposer.org/versions), [Playwright](https://github.com/microsoft/playwright): Quellen der separat gepinnten Prüfwerkzeuge.
