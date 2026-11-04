# Build und Veröffentlichung

## Laufzeiten und Installation

Build und Watch laufen mit **Node 14.21.3 und npm 6.14.18**. Das entspricht der bestehenden CentOS-7-Umgebung des Portals. `.nvmrc`, die Paketanforderungen und `.npmrc` legen diese Versionen und die strikte Prüfung der Paketanforderungen fest; `package-lock.json` verwendet das npm-6-Format v1.

```sh
node --version
npm --version
npm ci --ignore-scripts
npm run build
npm run watch
```

Die getrennten Browser- und Regressionstestprogramme verwenden lokal und in der CI Node 24. Build, Watch und Releaseexport auf CentOS 7 benötigen Node 14. Die Testharnesses starten den eigentlichen Compiler über `SKYE_BUILD_NODE`; ihre Protokolle nennen beide Laufzeiten. Die Produktionssite lädt nur PHP und die gebauten Assets.

Die offiziellen Node-14-Linux-Binaries passen zu glibc 2.17; Node 24 verlangt neuere Systembibliotheken. Der zusätzliche CI-Container prüft den Build deshalb mit dem unveränderten CentOS-7.9-Userland und dem offiziellen Node-14-Archiv. Er teilt den Kernel des Ubuntu-Runners und ersetzt keinen Test auf einem physischen CentOS-7-Host. Es werden weder glibc noch libstdc++ ersetzt. Node 14 erhält keine Upstream-Sicherheitsupdates mehr; der Kompatibilitätsstand bleibt auf die vorhandenen Buildsysteme begrenzt.

Der normale Build erzeugt minifizierte, mit SHA-256 versionierte CSS-/JS-Dateien, Mobile-/Critical-CSS, die erlaubte Free-Icon-Teilmenge und `dist/assets.json`. Eigene Fonts und Bilder sowie die Original-Webfonts werden ebenfalls unveränderlich unter Inhaltsnamen veröffentlicht. Im CSS werden nur bekannte eigene relative Referenzen entsprechend zugeordnet. Die Runtime liest eine gemeinsame Manifestaufnahme pro Request. Der Critical-Vertrag bindet neben dem Klasseninventar die tatsächlichen SHA-256-Bytes sämtlicher konfigurierter PHP-Quellen; auch eine Verhaltensänderung ohne neue Klasse ändert seinen Hash. `_licenses` benennt die exakten aktuellen Lizenzdateien für den Releaseprozess. Versteckte Platzhalter werden nicht als öffentliche Assets veröffentlicht. Sie schreibt weder gespeicherte URLs noch Attachmentmetadaten um.

`assets/manifest.json` ist der deklarierte Eingabevertrag. `styles` und `scripts` enthalten eigene Einstiegspunkte, `performance.mobileBreakpoint` den gemeinsamen CSS-/PHP-Umschaltpunkt, `critical` die eigenen Quellpfade/Interaktionsklassen und `icons` Free-Schlüssel wie `solid/arrow-right`. `fonts` enthält nur tatsächlich veröffentlichte eigene WOFF2-Dateien, etwa `{ "file": "fonts/example.woff2" }`. Die Standardkonfiguration verwendet Systemfonts und keine zusätzlichen Font-Preloads.

Der Build übernimmt die vollständige Bootstrap-CSS-Konfiguration. Selektive Komponentenimporte sind ein bewusst zu prüfender Projektschritt; die in Volksgarage nicht verwendeten Komponenten werden nicht pauschal aus neuen Projekten entfernt. JavaScript lädt für die vorhandene Navigation die originalen Drawer- und Menu-Module. Weitere Komponenten werden über eigene Einstiegspunkte ausdrücklich ergänzt.

## Fehler und Watch

Alle Kompilierungen und Validierungen laufen zunächst in einem separaten Arbeitsverzeichnis. Erst nach Erfolg werden unveränderliche Dateien ergänzt und die Manifestdatei per Rename ausgetauscht. Ein Fehler lässt das vorherige Manifest und alle von ihm referenzierten Bytes bestehen. Alte Inhaltsdateien bleiben für noch offene Seiten verfügbar. Der Releaseprozess nimmt das Manifest und seine aktuellen Ausgaben sowie Lizenzdateien auf; Quellwerkzeuge gehören nicht ins öffentlich ausführbare Theme.

`.skye-build.lock` verhindert konkurrierende Veröffentlichungen. Ein abgestürzter Prozess kann seinen Lock hinterlassen. Vor dessen Entfernung muss geprüft werden, dass die in `owner.json` genannte Build-PID nicht mehr läuft. Der Build bricht bei belegtem Lock ab, statt einen möglicherweise noch aktiven Schreiber zu übergehen.

Watch beobachtet Assets, Buildwerkzeuge und die im Manifest genannten PHP-Quellen. Änderungen und neue Komponenten lösen den normalen Build aus; Events werden serialisiert. Fehler werden gemeldet, der folgende gültige Stand kann wieder veröffentlicht werden. Bei SIGTERM wartet Watch auf den laufenden Build. Es gibt keine nachträglichen manuellen Patches erzeugter Dateien.

## Abhängigkeiten

Direkte Buildpakete sind exakt im Lockfile gebunden. Bootstrap ist auf `6.0.0-alpha.1` festgelegt; öffentliche V6-APIs, Klassen und CSS-Tokens ersetzen die alten V5-Annahmen. Die Alpha-Schnittstellen können sich noch ändern; Updates brauchen erneut Menü-, Formular-, Build- und Browserregressionen. Vorhandene V5-Projektinhalte müssen nach der Migration geprüft werden.

Bootstrap und Font Awesome Free liefern UI und Icons. Sass 1.99.0 kompiliert die Styles synchron; Rollup 2.80.0 und Terser 5.51.2 bündeln und verkleinern die Browserprogramme. Lightning CSS 1.31.1 übernimmt Prefixing und Minifizierung, die PostCSS-Parser leiten Mobile-/Critical-CSS ab. Chokidar 4.0.3 beobachtet auch unter Linux neue Dateien und Unterordner. Floating UI ist eine direkte Abhängigkeit der verwendeten Bootstrap-Menüs; npm 6 installiert Peers nicht automatisch.

Die Versionen wurden unter echtem Node 14 geprüft. Neuere Versionen mehrerer Buildpakete benötigen Node 18/20/24; andere ältere Kandidaten enthalten bekannte Sicherheitslücken. Der gewählte Graph vermeidet diese Kandidaten und besteht die strikte npm-6-Installation. Die native Linux-Bibliothek von Lightning CSS 1.31.1 verlangt laut geprüftem ELF höchstens GLIBC 2.14 und keine libstdc++-Symbole. Originalpakete werden unverändert verwendet.

Die Browserziele entsprechen Bootstrap 6 Alpha: Safari/iOS ab 18, Chrome/Edge ab 130 und Firefox ab 132. Der Bundler erzeugt ein einzelnes Browserprogramm; eine Umwandlung auf ES2020 oder Unterstützung älterer Browser wird damit nicht zugesagt. Weitere Projektmodule müssen gegen dieselben Browserziele geprüft werden.

Der Abhängigkeitsstand wird mit npm 6 und einem aktuellen separaten Auditwerkzeug geprüft. Ein sauberes Paketaudit ist ein zeitgebundener Registry-Nachweis und keine Aussage über die Wartung von CentOS 7 oder Node 14. Die tatsächlichen Ergebnisse stehen im [Prüfnachweis](testing.md). `dist/licenses/` enthält die Original-Lizenzen von Bootstrap, Font Awesome und den drei gebündelten Floating-UI-Paketen.

## Herkunft und Quellen

Der neue Node-Build übernimmt die atomare Veröffentlichungsidee der abgeschlossenen Portalarbeit und die konservativen CSS-/Watch-Transformationen der abgeschlossenen Website. Gulp-/Bower-/Vinyl-Adapter sind entfernt. Herkunft der generischen Performanceverträge: `d2a658c`, `138361f`, `42df2e0`, `5f6b04d`, `948c6a3`, `7953682`, `86ce74b`, `356c624`, `259d546`, `fe30ff2`, `f91849b` und `7e08d39` im Websiteprojekt.

Offizielle Grundlagen, geprüft am 03.11.2026: [Bootstrap 6 Migration](https://getbootstrap.com/docs/6.0/migration/), [Navbar](https://getbootstrap.com/docs/6.0/components/navbar/), [Browserziele](https://getbootstrap.com/docs/6.0/getting-started/browsers-devices/), [Sass-Module](https://sass-lang.com/documentation/at-rules/use/), [WordPress Enqueue](https://developer.wordpress.org/reference/functions/wp_enqueue_script/). Die Paketkopien werden ausschliesslich über den Paketmanager aus der Registry bezogen. Laufzeitquellen: [Node 14 Plattformanforderungen](https://github.com/nodejs/node/blob/v14.21.3/BUILDING.md), [Node 24 Plattformanforderungen](https://github.com/nodejs/node/blob/v24.22.2/BUILDING.md), [Node-Lebenszyklus](https://nodejs.org/en/about/previous-releases), [Lightning CSS](https://lightningcss.dev/).

## Tatsächliche Prüfung dieses Stands

`tests/build.test.mjs` prüft echte Kompilierung, unveränderte letzte Veröffentlichung nach absichtlich fehlerhafter Quelle, Pfadverweigerung, Quellenneuerkennung im echten Watch sowie konservative Media-/Layer-/Critical-Ableitung. `tests/critical-loader.test.cjs` führt 70 Bereitschafts-/Fehler-/Doppelaufrufprüfungen gegen die tatsächlichen Browserprogramme in isolierten DOM-Gegenstellen aus. Browser-/Geräteabnahme wird getrennt im globalen Prüfnachweis dokumentiert; diese Programme sind keine echten Geräte.

Der zusätzliche echte Browserlauf `tests/browser-critical.cjs` setzt die unter [testing-setup.md](testing-setup.md) erstellte isolierte Site, ihren laufenden Loopbackserver und die separat installierte Playwright-Testlaufzeit voraus:

```sh
SKYE_TEST_ORIGIN=http://127.0.0.1:8973 SKYE_BROWSER=chromium /absoluter/pfad/node24 tests/browser-critical.cjs
```

`SKYE_PLAYWRIGHT_MODULE` kann auf die isolierte Playwright-Installation zeigen; `SKYE_BROWSER_EXECUTABLE` benennt bei Bedarf einen ausdrücklich verwendeten Browser. Standardmässig wird der jeweilige Playwright-Browser verwendet. Die Fixture wird ausschliesslich unter `.git/runtime/` in einer lokalen Umgebung vorübergehend installiert; bestehende Dateien werden nie überschrieben. Sie prüft einen inhaltsleeren, bewusst begrenzten ersten Viewport mit echter verzögerter CSS-Antwort, keinen beliebigen Projektinhalt. Ergebnisse und Ansichten stehen unter `.git/runtime/browser-critical-<engine>/`. Lokal tatsächlich ausgeführt: Chrome 155.0.8059.40 und Firefox 153.0 auf macOS, je 390/1440 CSS-Pixel und 37 erfolgreiche Assertions einschliesslich No-JS und spätem Opt-out. Für die Aufnahme vor CSS-Freigabe umgeht der Test ausschliesslich Playwrights zusätzliche Font-Wartezeit unter einem geprüften Systemfont-Vertrag ohne `@font-face`/`@import`; Seiten- und Geometrieprüfungen bleiben aktiv. WebKit ist getrennt nachzuweisen; der lokal eingefrorene macOS-14-Driver scheiterte vor dem Seitenaufruf, siehe [Prüfungen](testing.md).

[Playwright-Routing deaktiviert den HTTP-Cache](https://playwright.dev/docs/api/class-browsercontext#browser-context-route). Ein dabei abgefangener Versuch ist noch kein Servertransfer: Der Test fordert genau einen vollständig abgeschlossenen HTTP-200-Abruf der CSS-Datei. Weitere Versuche sind nur zulässig, wenn der Browser sie nachweislich vor der Netzwerkfreigabe abgebrochen hat, ohne Antwortstatus und ohne Abschluss. Jede andere zusätzliche Übertragung, ein ungeklärter Versuch oder eine Lücke im begrenzten Tracker lässt die Prüfung scheitern. Der ursprüngliche Link muss zudem ein geladenes Stylesheet mit geparsten CSS-Regeln besitzen. Das misst die tatsächliche CSS-Auslieferung dieser kontrollierten Testseite; es ist keine allgemeine Ladezeitmessung.
