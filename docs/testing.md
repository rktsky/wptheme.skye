# Tatsächlicher Prüfnachweis

Stand: 04.11.2026, Skye 3.0.0 auf dem Arbeitsbranch `refactor/skye-3`. Dieser Nachweis betrifft das integrierte Basistheme, nicht eine produktive Volksgarage-Installation. Logik, Styling und reine Formatierung sind getrennt in Git nachvollziehbar; [Herkunft](backports.md), [Migration](migration-3.md) und [Freigabe](implementation-scope.md) beschreiben den Umfang.

## Lokale Umgebung

macOS 14.8.3 (23J220), Apple Silicon/arm64; PHP 8.3.36 mit GD/WebP, PDO SQLite, DOM, mbstring und Tokenizer. Der ursprüngliche Prüfstand verwendete Node 24.22.2/npm 12.2.1. Die CentOS-Anpassung baut mit dem offiziellen Node-14.21.3-Darwin-x64-Binary unter Rosetta und npm 6.14.18; die modernen Testharnesses laufen davon getrennt mit Node 24. Node wurde aus dem offiziellen Distributionsverzeichnis bezogen und gegen dessen SHA-256-Liste geprüft. Produktions-PHP verwendet keine Node-/Composer-Pakete. Die separaten Qualitätswerkzeuge sind mit Composer 2.11.1 installiert und gelockt: PHP CS Fixer 3.95.27 und PHP Parser 5.9.0.

Die nativen Core-Prüfungen verwenden unveränderte WordPress-Quellen 6.8.12 und 7.1.4. Vollständige Wegwerf-Installationen verwenden zusätzlich das unveränderte SQLite-Plugin 3.1.3 und phasenweise das originale Classic-Editor-Plugin 1.7.0. Archivherkunft, Prüfsummen, Eigentumsgrenzen und Wiederholung sind in [Testinstallation](testing-setup.md) dokumentiert. Mail und externe HTTP-Transporte sind gesperrt; die Browser greifen nur auf den Loopbackserver zu. ACF PRO, Redis und produktive Zugangsdaten sind nicht installiert.

## Ausgeführte Prüfungen

| Bereich | Tatsächlicher Lauf | Ergebnis und Aussagegrenze |
| --- | --- | --- |
| Architektur und Dokumentation | PHP-Parser über 86 eigene PHP-Dateien; sieben positive/negative Checker-Fixtures | Null Befunde; erkennt die implementierten Architektur-/Tagregeln. Begründung und historische Genauigkeit wurden zusätzlich anhand Herkunft und Git geprüft; Tags allein beweisen diese nicht. |
| Format und Syntax | PHP CS Fixer über 85 Dateien; PHP-Parser; 16 JS-Syntax-/LF- und 15 Sass/CSS-LF-Prüfungen | Null vorgeschlagene Formatänderungen. Die tatsächliche Sass-Grammatik wird durch den Produktionsbuild geprüft. Kein Umformatieren fremder oder erzeugter Dateien. |
| Komponenten und Modelle | Echter Loader und echte Templates, je 91 Aussagen mit und ohne verfügbare ACF-API | Beide isolierten Pfade grün. Core-/ACF-API-Ausgänge sind hier begrenzte Gegenstellen; echte Core-Installation wird separat geprüft. ACF-Anwesenheit wird durch begrenzte Providergegenstellen dargestellt; das ersetzt keine echte ACF-PRO-Integration. |
| Security | 41 Aussagen gegen die tatsächlichen Theme-Callbacks über die echte Core-Hookengine | Beide Core-Versionen grün; begrenzte Rechte-/Optionsgegenstellen prüfen verweigerte Saves, Classic-Switch-Suppression, Multisite-Optionsgrenzen, Favicon-Abwesenheit und gezielte Cacheinvalidierung nach nativen Optionsänderungen. Kein Penetrationstest oder vollständiger Plugin-Audit. |
| Assets und Performance | 35 Asset-, 46 Strategie-, 51 Empty-Style-, 99 Formular- und 27 Critical-/Preload-Aussagen je Core-Version | Native Register, Parser, Abhängigkeiten und Serializer; unbekannte und beschädigte Verträge behalten ihren Fallback. Gravity Forms und Members werden an ihren begrenzten API-Grenzen geprüft, nicht als installierte Vollprodukte. |
| Medien | 540 Aussagen je Core-Version gegen Produktionsklassen, tatsächlichen Komponentenloader, SQLite-Prädikate und echten GD/WebP-Encoder | Native Quellen bleiben erhalten; vollständige kleinere Alternativen, Fehler, CAS/Leases, Aufbewahrung, Cron und Wiederaufnahme geprüft. Der finale Lauf prüft auch verlorene Discovery-/Cleanup-Termine und native Cacheinvalidierung; Umfang und offene Hostingprüfung in [Medien](media.md). |
| PHP-Gesamtlauf | Alle zehn ausdrücklich klassifizierten Programme, je in frischem Prozess | Auf PHP 8.3.36 mit Core 6.8.12 und 7.1.4 jeweils ohne fehlgeschlagenen Prozess. Keine dynamischen Skipfilter. |
| Build, Watch, Browserloader und Export | Acht Node-Tests, darunter echte Kompilierung, Watch-Neuerkennung, absichtlicher Quellfehler und Wiederaufnahme, 70 Loader-Aussagen und vier Exportregressionen | Acht bestanden, null übersprungen. Letzter gültiger Build bleibt nach Fehler unverändert. Die Exportregression verwendet ein eigenes Wegwerf-Gitrepository. |
| Native Installation | Beide vollständigen WordPress-Versionen, wiederholbares Setup, Themeaktivierung ohne ACF/Redis, danach originales Classic-Editor-Plugin mit konkurrierenden Präferenzen | Native Editor-/Widgetentscheidungen, sichere Manifeste/Pfade, unveränderte Plugin-Templates und keine öffentliche Datenmigration geprüft. Je sechs zusätzliche Aussagen prüfen den tatsächlichen nativen Footer-Widgetrenderer mit aktiver/leerer Sidebar ohne gespeicherte Widgetänderung. SQLite ist kein MySQL-/MariaDB-Nachweis. |
| Direkter HTTP-Zugriff | Sechs Standalone-PHP-Prüfprogramme über einen separaten lokalen Server | Je HTTP 403; der normale Testrouter verweigert Entwicklungsdateien zusätzlich. Releaseexport entfernt Tests und Tools vollständig. |
| Abhängigkeiten | Saubere Installation aus npm-Lockfile, npm-/Composer-Audit | Je null bekannte Sicherheitsmeldungen am Prüfdatum. Zeitgebundener Nachweis, keine Zusage über künftig unbekannte Lücken. |

Der Formatter meldet beim Aufruf aus dem Theme-Root die fehlende dortige `composer.json`. Das Theme hat absichtlich keine Composer-Runtime; das Manifest liegt ausschliesslich unter `tools/coding-standards/`. Die aktivierten Regeln verändern Whitespace und Klammerstruktur, keine PHP-Sprachfeatures. Der Hinweis wird nicht als Testfehler oder als unterdrückter Fehler dargestellt.

## Tatsächliche Browserläufe

Echte Headless-Browser auf macOS, gesteuert durch die separat vorhandene Playwright-Laufzeit 1.62.1. Die Produktionsseite enthält diese Laufzeit nicht. Die normale Suite prüft zehn Routen bei 320, 390, 768 und 1440 CSS-Pixeln: Start, Seite, Unterseite, Beitrag, lange Inhalte, gespeicherte Blockinhalte, Passwortschutz, Suche mit/ohne Ergebnis und 404. Hinzu kommen native Navigation, Drawer-/Menütastatur, Fokus/Skip-Link, No-JS, Ressourcenfehler/-dubletten und verweigerte Entwicklungspfade.

| Browser | Tatsächlich geprüft | Ergebnis |
| --- | --- | --- |
| Google Chrome 155.0.8059.40 | 40 Routen-/Viewportfälle; zusätzlich Touch-Emulation 390×844 und 844×390 | 196 registrierte Prüfpunkte bestanden. Touch-Emulation ist kein iPhone-/Android-Test. |
| Playwright Firefox 153.0 | 40 Routen-/Viewportfälle, Tastatur und No-JS | 192 registrierte Prüfpunkte bestanden. Keine Firefox-Touch-Geräteabnahme. |

Die Suite prüft horizontale Menüpositionen tatsächlich über ihre Geometrie. Dadurch wurde die Bootstrap-6-Kollision zwischen nativen WordPress-`menu-item`-LI-Klassen und gleichnamigen Bootstrap-Bedienelementen sichtbar und behoben. Ein ungetrennter 240-Zeichen-Inhalt überschritt bei 320 Pixeln vorher die Inhaltsbreite; der separate Stylingfix verhindert diesen Überlauf. Screenshots der vollständigen Standardansichten, nach Tastaturbedienung und ohne JavaScript wurden lokal erzeugt und die Standardansichten visuell geprüft. Die jetzt nativ ausgegebenen Footer-Widgets waren in den tatsächlichen Browseransichten sichtbar; leere Sidebars sind zusätzlich durch die native Site-Suite geprüft.

Der zusätzliche positive Critical-Test verwendet eine ausdrücklich begrenzte synthetische Seite: kein beliebiger Bodyinhalt im ersten Viewport, Footer unterhalb davon, Systemfont. Bei 390 und 1440 Pixeln hält der Browser die vollständige CSS-Antwort bis nach `DOMContentLoaded` zurück. Chrome 155 und Firefox 153 bestanden je 37 Aussagen zu sichtbarer Geometrie/Typografie, einmaliger Übertragung, spätem Opt-out und No-JS. Die temporäre MU-Fixture wird nur in der eigenen lokalen Wegwerf-Site installiert, überschreibt nichts und wird im Abschlussblock entfernt. Der Screenshot vor CSS-Freigabe deaktiviert nur Playwrights Hilfswartezeit auf Fonts: Firefox wartet dabei auf die absichtlich gehaltene CSS-Antwort. Der Helper lässt dafür ausschliesslich den geprüften Systemfont-Vertrag ohne `@font-face`/`@import` zu und stellt die Prozesseinstellung wieder her; tatsächliche CSS-, Geometrie- und Typografieprüfungen bleiben aktiv. Dieser Test bestätigt keinen unbekannten Projekt-Viewport.

Die zusätzlich für macOS 14 erhältliche eingefrorene Playwright-WebKit-Ausgabe liess sich mit dieser Laufzeit nicht verwenden: `Page.overrideSetting` verweigerte `PushAPIEnabled` bereits vor dem ersten Seitenaufruf. Normal- und Critical-Versuch sind deshalb keine bestandenen WebKit-Läufe. Die isolierte Fixture und eigenen Prozesse wurden danach entfernt; Safari-/iOS-Abnahme bleibt offen.

## GitHub Actions im Originalrepository

Der [GitHub-Lauf für `4201040`](https://github.com/rktsky/wptheme.skye/actions/runs/41817410124) auf `refactor/skye-3` bestand am 04.11.2026 **alle neun Jobs**. Er prüft den Node-14-Build und zusätzlich das originale CentOS-7.9-Userland auf Ubuntu 24.04. Der frühere [Acht-Job-Lauf für `df7fb75`](https://github.com/rktsky/wptheme.skye/actions/runs/41790028997) verwendete noch Node 24 als Compiler; der aktuelle Lauf ersetzt diesen Buildnachweis.

| Job | Tatsächlicher erfolgreicher Umfang |
| --- | --- |
| Build und Release | Node 14.21.3/npm 6.14.18, strikte gelockte Installation/Audit, Syntax, Produktionsbuild und sauberer Releaseexport; separater Node-24.22.2-Testprozess mit Node-14-Compiler für Build-/Watch-/Fehler-/Exportregressionen sowie alle 70 Loader-Aussagen |
| CentOS 7 / Node 14 | Originales CentOS 7.9 mit glibc 2.17, offizielle Node-14-Linux-Binaries, frische npm-6-Installation und alle sieben Build-/Watch-Prüfgruppen einschliesslich Wiederaufnahme und SIGTERM |
| PHP 8.3 / WordPress 6.8.12 | Vollständige zehn Standalone-Prozesse, darunter 540 Medienaussagen, sowie native Footer-/Siteprüfung vor und nach Original-Classic-Editor-Aktivierung |
| PHP 8.4 / WordPress 6.8.12 | Derselbe vollständige Prüfumfang auf der unterstützten neueren PHP-Laufzeit |
| PHP 8.3 / WordPress 7.1.4 | Derselbe vollständige Prüfumfang mit aktuellem Core |
| PHP 8.5 / WordPress 7.1.4 | Derselbe vollständige Prüfumfang mit aktuellem PHP und Core |
| Chromium | 40 Routen-/Viewportfälle sowie 37 Critical-Prüfpunkte, inklusive No-JS und spätem Opt-out |
| Firefox | Derselbe vollständige Seiten- und Critical-Umfang |
| WebKit | Derselbe vollständige Seiten- und Critical-Umfang; die Linux-Engine ist kein nativer Safari-/iOS-Nachweis |

Die Browser verwenden die separate Playwright-Laufzeit 1.64.1. Die genaue Engineversion steht im jeweiligen hochgeladenen `result.json`; im WebKit-Diagnoselauf wurde Version 27.2 tatsächlich gemeldet. Genaue PHP-Patchstände und Runnerdetails bleiben im jeweiligen Jobprotokoll nachvollziehbar. Die eng angegebenen PHP-/Browserartefakte schliessen ihre versteckten Testpfade ausdrücklich ein; keine vollständige `.git`-Struktur, Sitekonfiguration oder Datenbank wird hochgeladen.

Die vorangehenden [ersten](https://github.com/rktsky/wptheme.skye/actions/runs/41786012128), [zweiten](https://github.com/rktsky/wptheme.skye/actions/runs/41786557501), [dritten](https://github.com/rktsky/wptheme.skye/actions/runs/41787577241) und [vierten](https://github.com/rktsky/wptheme.skye/actions/runs/41788876131) Fehlerläufe bleiben einsehbar. Folgende tatsächlichen Probleme wurden korrigiert:

- Eigene wirkungslose `imagedestroy()`-Aufrufe entfernt; der SQLite-Testadapter verwendet ab PHP 8.4 `Pdo\Sqlite::createFunction()`. Die ältere native API bleibt gezielt auf der Mindestlaufzeit PHP 8.3.
- Die Matrix besteht aus vier vollständig ausgeführten unterstützten Paaren. WordPress 6.8 unterstützt PHP 8.5 laut [offizieller Core-Matrix](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) nicht; diese Kombination wird nicht durch Originalpatches oder Warnunterdrückung scheinbar grün gemacht.
- Die PNG-Testauswertung liest echte RGBA-Werte unabhängig von Palette oder Truecolor. Der alte Bitshift konnte einen gültigen Paletteindex mit Transparenz verwechseln. 15 zusätzliche echte Save-/Reload-Aussagen prüfen transparente, halbtransparente und opake Pixel beider Modelle; die native Resize-Transparenzgrenze bleibt erhalten.
- Der WebKit-Lauf belegte einen vor Netzwerkfreigabe abgebrochenen Preload und genau eine vollständig erfolgreiche CSS-Antwort. Die Prüfung zählt jetzt genau einen abgeschlossenen HTTP-200-Transfer, erlaubt weitere Versuche ausschliesslich bei nachgewiesenem Abbruch vor Freigabe ohne Antwortstatus und verlangt vollständige Beobachtung aller Versuche. Zusätzlich wird das tatsächlich zugeordnete, geladene Stylesheet mit nichtleerem CSSOM geprüft. Echte doppelte Übertragungen, fehlende Antworten und unerwartete Fehler schlagen weiterhin fehl.
- Begrenzte native GitHub-Fehlerannotationen enthalten die tatsächliche PHP-/Browserdiagnose. Vollständige Joblogs, Artefakte und fehlschlagende Exitcodes bleiben erhalten; dies ermöglicht die Diagnose auch bei zusätzlich geschütztem Logdownload.

Diese CI-Korrekturen betreffen Prüfwerkzeuge, Versionsdeklaration und Nachweise; der Theme-Produktionscode wurde dafür nicht geändert. Es wurden keine Tests übersprungen, Fehler unterdrückt oder WordPress-/Drittanbieter-Originaldateien gepatcht. Quellen: [GitHub Workflow Commands](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands#setting-an-error-message), [PHP 8.5 Deprecations](https://www.php.net/manual/en/migration85.deprecated.php).

## Direkter Build auf CentOS 7

Build und Watch verwenden jetzt den Portalstand Node 14.21.3/npm 6.14.18 mit Lockfile v1 und strikter Engineprüfung. Der lokale Build erzeugt 24 Assets mit fünf Original-Lizenzen. Beide Paketaudits (npm 6 sowie ein aktuelles separates npm) melden am 04.11.2026 null bekannte Advisories; der Lockfile-Hash bleibt dabei unverändert. Die native Lightning-CSS-Bibliothek wird erst für einen tatsächlichen Build geladen, sodass reine Manifestprüfungen keine fremde Prozessorarchitektur laden müssen.

Die drei Build-/Watch-Tests und vier Release-Testgruppen bestehen mit Compiler `v14.21.3` und Harness `v24.22.2`; der Release-Metadatensatz nennt die tatsächliche Compilerlaufzeit. Alle bisherigen Fehlerfälle bleiben aktiv. Die vollständige CSS-Layerreihenfolge bleibt nach der Optimierung in Full-, Mobile- und Critical-CSS gleich: `colors, config, root, reboot, layout, content, forms, components, custom, helpers, utilities`. Der Critical-Auszug liegt weiter unter seinem 96-KiB-Budget. Der direkte Node-14-Loaderlauf besteht alle 70 Assertions.

Der lokale CentOS-Lauf für `4201040` bestand am 04.11.2026 alle sieben Prüfgruppen: originales CentOS 7.9 mit glibc 2.17 und Node 14.21.3/npm 6.14.18; frische strikte Installation der 77 Pakete; normaler `npm run build`; fehlgeschlagener CLI-Build mit unveränderter letzter Ausgabe und erfolgreicher Wiederaufnahme; Fehler und Wiederaufnahme im laufenden Watch; neue PHP-Quellen und verschachtelte Assetdateien; SIGTERM während eines Builds mit sauberem Abschluss. Die Originaldateien von glibc, libstdc++ und Loader hatten vor und nach dem Lauf dieselben SHA-256-Digests. Das erste Teilprotokoll bleibt als fehlgeschlagen erhalten: Eine globale npm-Einstellung hatte auch den expliziten Build übersprungen. Die Korrektur begrenzt `--ignore-scripts` auf die Installation.

Der Lauf verwendete das digestgebundene CentOS-Image unter amd64-Emulation in einer lokalen ARM64-Linux-VM mit geteiltem Kernel `6.8.0-117-generic`. Die Quellen und das offizielle Node-Archiv wurden über eigene temporäre Docker-Volumes schreibgeschützt eingebunden; Systembibliotheken und VM-Konfiguration blieben unverändert. Beide eigenen Container und alle vier Volumes wurden entfernt. Die Ergebnisdateien liegen unter `.git/runtime/centos7-copy-proof-6f27c048284b4a12/`.

Der zusätzliche CentOS-Job im [GitHub-Lauf für `4201040`](https://github.com/rktsky/wptheme.skye/actions/runs/41817410124) bestand denselben vollständigen Build-/Watch-Umfang. Er verwendet dasselbe Originalimage und dasselbe offizielle Node-Archiv mit schreibgeschützten Host-Bind-Mounts auf dem Ubuntu-Runner. Der vorherige [Lauf für `06558ce`](https://github.com/rktsky/wptheme.skye/actions/runs/41816784219) dokumentiert den behobenen npm-Einstellungsfehler. Beide erfolgreichen Containerprüfungen teilen den jeweiligen Hostkernel und ersetzen keinen Test auf einem CentOS-7-Server mit Kernel 3.10.

| Originaldatei | Geprüfte Voraussetzung |
| --- | --- |
| Node 14.21.3 Linux x64 | Archiv-SHA-256 `05c08a107c50572ab39ce9e8663a2a2d696b5d262d5bd6f98d84b997ce932d9a`; tatsächliches ELF benötigt höchstens GLIBC 2.17, GLIBCXX 3.4.18 und CXXABI 1.3.5 |
| Lightning CSS 1.31.1 Linux x64 GNU | npm-Integrität geprüft; Binary-SHA-256 `751bc92429f1f1c70e330c01dcdc274d478def6f9a93ae0b14a98b6919f171a4`; höchstens GLIBC 2.14, keine libstdc++-Symbole |

Die tatsächlichen Node-14-Compilerprozesse und ihre Version werden von den modernen Build-/Release-Testharnesses getrennt ausgewiesen. Ein erfolgreicher Node-24-Testprozess allein gilt nicht als Node-14- oder CentOS-Nachweis. Ein Test auf einem realen CentOS-7-Host mit dessen Kernel bleibt vom Containerlauf zu unterscheiden.

## Reproduzieren und Protokolle

Voraussetzungen und Installation der Prüflaufzeiten sind oben und in [Testinstallation](testing-setup.md) beschrieben. Nach dem gelockten Installieren der Qualitätswerkzeuge und Buildabhängigkeiten:

```sh
composer install --working-dir=tools/coding-standards --no-interaction --prefer-dist --no-scripts
composer audit --working-dir=tools/coding-standards --no-interaction
npm ci --ignore-scripts
npm audit --audit-level=moderate
npm run lint:js
npm run lint:style
php tools/coding-standards/check.php
php tools/coding-standards/vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php
npm run build
SKYE_BUILD_NODE=/absoluter/pfad/node14 /absoluter/pfad/node24 --test tests/build.test.mjs tests/critical-loader.test.cjs tests/release.test.mjs
SKYE_WORDPRESS_CORE=/absoluter/pfad/wordpress php tools/testing/run.php
```

Die zweite Core-Version braucht einen eigenen expliziten Gesamtlauf. Die vollständig gestartete native Site zusätzlich mit `SKYE_SITE_ROOT=/absoluter/pfad/site/wordpress php tests/core-sidebar-native.php` sowie `tests/site-verify.php` vor und nach `--activate-classic-plugin` prüfen. Nach Start ihres lokalen Servers:

```sh
SKYE_TEST_ORIGIN=http://127.0.0.1:8973 SKYE_BROWSER=chromium /absoluter/pfad/node24 tests/browser.cjs
SKYE_TEST_ORIGIN=http://127.0.0.1:8973 SKYE_BROWSER=chromium /absoluter/pfad/node24 tests/browser-critical.cjs
```

`SKYE_PLAYWRIGHT_MODULE` benennt die separate Playwright-Installation, `SKYE_BROWSER_EXECUTABLE` bei Bedarf die tatsächlich geprüfte Browserdatei. Firefox/WebKit werden ausdrücklich über `SKYE_BROWSER` gewählt. Keine parallelen Critical-Läufe in derselben Site: Die MU-Fixture wird exklusiv angelegt. Fehlende Voraussetzungen ergeben einen Fehler, keinen stillen Skip.

Nicht eingecheckte lokale Rohprotokolle und Fixture-Daten liegen unter `.git/runtime/`: `php-tests/<lauf>/`, `browser-<engine>/result.json` samt Screenshots, `browser-critical-<engine>/result.json` und Setup-/Auditprotokolle. Sie enthalten lokale Installationspfade und gehören nicht ins öffentliche Theme. Die JSON-Ergebnisse nennen die tatsächlich verwendete Core-/PHP-/Browserversion; die Assertionzahl ist keine Prozentangabe zur Codeabdeckung. Der [Releaseexport](release.md) erfasst den sauberen Commit, Inventar und Nutzdatei-Digests in `release.json`; ein Export ist keine bereits erfolgte Veröffentlichung.

## Offene Abnahmegrenzen

- Der oben verlinkte grüne Hosted-Lauf belegt ausschliesslich seine deklarierten PHP-/WordPress-Paare und Linux-Engines. Andere Kombinationen werden nicht als ausgeführt ausgegeben.
- Native Safari-Versionen auf macOS/iPhone/iPad, Edge auf Windows und Chrome auf physischen Android-Geräten stehen aus; ebenso vollständige Notebook-/Tablet-/Touch-, Zoom- und virtuelle-Tastatur-Abnahme. Playwright-WebKit ersetzt keinen dieser Nachweise.
- MySQL-/InnoDB-Mehrprozessbetrieb, reales Hosting-Cron, Imagick, externer Redis-Provider und aktive ACF-PRO-/Gravity-Forms-/Members-Produkte müssen bei der jeweiligen Projektintegration geprüft werden. Die native SQLite-Site belegt keine produktive Serverkonfiguration.
- Keine generischen Lighthouse-Scores oder prozentualen Ladezeiten werden behauptet. Rohgrössen stehen in [Performance](performance.md); Medienfixturewerte in [Medien](media.md). Eine unbekannte Route erhält ohne vollständigen geprüften Critical-Vertrag den nativen Fallback.

Diese Grenzen werden nicht durch erfolgreich konfigurierte Workflows, schmale Desktopansichten oder frühere Volksgarage-Messungen ersetzt. Es wurden keine produktiven Inhalte, Kundendateien, Mails, Zahlungen oder externen Fachaktionen verändert.
