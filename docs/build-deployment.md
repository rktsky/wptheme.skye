# Build und Veröffentlichung

## Laufzeiten und Installation

Der Build ist auf Node 24 LTS (`>=24.15.0 <25`) und npm 12 festgelegt, mit `npm@12.2.1` als Paketmanager. Lokal geprüft: Node 24.22.1, npm 12.2.1. Produktions-PHP braucht weder Node noch npm, wenn die erzeugten Assets mit veröffentlicht werden.

```sh
npm ci
npm run build
npm run test:build
npm run test:performance
npm run watch
```

Der normale Build erzeugt minifizierte, mit SHA-256 versionierte CSS-/JS-Dateien, Mobile-/Critical-CSS, die erlaubte Free-Icon-Teilmenge und `dist/assets.json`. Eigene Fonts und Bilder sowie die Original-Webfonts werden ebenfalls unveränderlich unter Inhaltsnamen veröffentlicht. Im CSS werden nur bekannte eigene relative Referenzen entsprechend zugeordnet. Die Runtime liest eine gemeinsame Manifestaufnahme pro Request. Der Critical-Vertrag bindet neben dem Klasseninventar die tatsächlichen SHA-256-Bytes sämtlicher konfigurierter PHP-Quellen; auch eine Verhaltensänderung ohne neue Klasse ändert seinen Hash. `_licenses` benennt die exakten aktuellen Lizenzdateien für den Releaseprozess. Versteckte Platzhalter werden nicht als öffentliche Assets veröffentlicht. Sie schreibt weder gespeicherte URLs noch Attachmentmetadaten um.

`assets/manifest.json` ist der deklarierte Eingabevertrag. `styles` und `scripts` enthalten eigene Einstiegspunkte, `performance.mobileBreakpoint` den gemeinsamen CSS-/PHP-Umschaltpunkt, `critical` die eigenen Quellpfade/Interaktionsklassen und `icons` Free-Schlüssel wie `solid/arrow-right`. `fonts` enthält nur tatsächlich veröffentlichte eigene WOFF2-Dateien, etwa `{ "file": "fonts/example.woff2" }`. Die Standardkonfiguration verwendet Systemfonts und keine zusätzlichen Font-Preloads.

Der Build übernimmt die vollständige Bootstrap-CSS-Konfiguration. Selektive Komponentenimporte sind ein bewusst zu prüfender Projektschritt; die in Volksgarage nicht verwendeten Komponenten werden nicht pauschal aus neuen Projekten entfernt. JavaScript lädt für die vorhandene Navigation die originalen Drawer- und Menu-Module. Weitere Komponenten werden über eigene Einstiegspunkte ausdrücklich ergänzt.

## Fehler und Watch

Alle Kompilierungen und Validierungen laufen zunächst in einem separaten Arbeitsverzeichnis. Erst nach Erfolg werden unveränderliche Dateien ergänzt und die Manifestdatei per Rename ausgetauscht. Ein Fehler lässt das vorherige Manifest und alle von ihm referenzierten Bytes bestehen. Alte Inhaltsdateien bleiben für noch offene Seiten verfügbar. Der Releaseprozess nimmt das Manifest und seine aktuellen Ausgaben sowie Lizenzdateien auf; Quellwerkzeuge gehören nicht ins öffentlich ausführbare Theme.

`.skye-build.lock` verhindert konkurrierende Veröffentlichungen. Ein abgestürzter Prozess kann seinen Lock hinterlassen. Vor dessen Entfernung muss geprüft werden, dass die in `owner.json` genannte Build-PID nicht mehr läuft. Der Build bricht bei belegtem Lock ab, statt einen möglicherweise noch aktiven Schreiber zu übergehen.

Watch beobachtet Assets, Buildwerkzeuge und die im Manifest genannten PHP-Quellen. Änderungen und neue Komponenten lösen den normalen Build aus; Events werden serialisiert. Fehler werden gemeldet, der folgende gültige Stand kann wieder veröffentlicht werden. Bei SIGTERM wartet Watch auf den laufenden Build. Es gibt keine nachträglichen manuellen Patches erzeugter Dateien.

## Abhängigkeiten

Direkte Buildpakete sind exakt im Lockfile gebunden. Bootstrap `6.0.0-alpha.1` ist auf ausdrücklichen Benutzerwunsch gewählt; öffentliche V6-APIs, Klassen und CSS-Tokens ersetzen die alten V5-Annahmen. Alpha ist eine gezielt gewählte, noch veränderliche Schnittstelle; Updates brauchen erneut Menü-, Formular-, Build- und Browserregressionen. Vorhandene V5-Projektinhalte müssen nach der Migration geprüft werden.

Bootstrap und Font Awesome Free liefern die vorhandenen UI-/Iconverträge, Sass kompiliert eigene Module, esbuild bündelt JavaScript, PostCSS/Autoprefixer/cssnano verarbeiten CSS, die beiden PostCSS-Parser sichern die konservative Ableitung, und Chokidar implementiert den vorhandenen Watch-Vertrag. Kein Paket führt Installationscode auf dem Webserver aus. Die Redis-/ACF-PRO-Installation ist ein separater Vorgang.

Am 03.11.2026 lieferte `npm audit` für den Lockfile-Stand null bekannte Meldungen in allen Kategorien. Das ist ein zeitgebundener Registry-Nachweis, keine Garantie für künftig unentdeckte Fehler. Original-Lizenzen von Bootstrap und Font Awesome werden nach `dist/licenses/` übernommen; Originalpakete werden nicht verändert.

## Herkunft und Quellen

Der neue Node-Build übernimmt die atomare Veröffentlichungsidee der abgeschlossenen Portalarbeit und die konservativen CSS-/Watch-Transformationen der abgeschlossenen Website. Gulp-/Bower-/Vinyl-Adapter sind entfernt. Herkunft der generischen Performanceverträge: `d2a658c`, `138361f`, `42df2e0`, `5f6b04d`, `948c6a3`, `7953682`, `86ce74b`, `356c624`, `259d546`, `fe30ff2`, `f91849b` und `7e08d39` im Websiteprojekt.

Offizielle Grundlagen, geprüft am 03.11.2026: [Bootstrap 6 Migration](https://getbootstrap.com/docs/6.0/migration/), [Navbar](https://getbootstrap.com/docs/6.0/components/navbar/), [Sass-Module](https://sass-lang.com/documentation/at-rules/use/), [WordPress Enqueue](https://developer.wordpress.org/reference/functions/wp_enqueue_script/). Die Paketkopien werden ausschliesslich über den Paketmanager aus der Registry bezogen.

## Tatsächliche Prüfung dieses Stands

`tests/build.test.mjs` prüft echte Kompilierung, unveränderte letzte Veröffentlichung nach absichtlich fehlerhafter Quelle, Pfadverweigerung, Quellenneuerkennung im echten Watch sowie konservative Media-/Layer-/Critical-Ableitung. `tests/critical-loader.test.cjs` führt 70 Bereitschafts-/Fehler-/Doppelaufrufprüfungen gegen die tatsächlichen Browserprogramme in isolierten DOM-Gegenstellen aus. Browser-/Geräteabnahme wird getrennt im globalen Prüfnachweis dokumentiert; diese Programme sind keine echten Geräte.

Der zusätzliche echte Browserlauf `tests/browser-critical.cjs` setzt die unter [testing-setup.md](testing-setup.md) erstellte isolierte Site, ihren laufenden Loopbackserver und die separat installierte Playwright-Testlaufzeit voraus:

```sh
SKYE_TEST_ORIGIN=http://127.0.0.1:8973 SKYE_BROWSER=chromium node tests/browser-critical.cjs
```

`SKYE_PLAYWRIGHT_MODULE` kann auf die isolierte Playwright-Installation zeigen; `SKYE_BROWSER_EXECUTABLE` benennt bei Bedarf einen ausdrücklich verwendeten Browser. Standardmässig wird der jeweilige Playwright-Browser verwendet. Die Fixture wird ausschliesslich unter `.git/runtime/` in einer lokalen Umgebung vorübergehend installiert; bestehende Dateien werden nie überschrieben. Sie prüft einen inhaltsleeren, bewusst begrenzten ersten Viewport mit echter verzögerter CSS-Antwort, keinen beliebigen Projektinhalt. Ergebnisse und Ansichten stehen unter `.git/runtime/browser-critical-<engine>/`. Lokal tatsächlich ausgeführt: Chrome 155.0.8059.40 auf macOS, 390/1440 CSS-Pixel, 31 erfolgreiche Assertions einschliesslich No-JS und spätem Opt-out. Firefox/WebKit können mit derselben Umgebung gewählt werden; ihre Ausführung ist getrennt nachzuweisen.
