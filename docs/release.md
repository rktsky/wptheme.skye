# Qualität und Release-Verzeichnis

## Lokaler Export

Ein Release entsteht aus einem sauberen, vollständig committeten Checkout. Redis und ACF PRO sind weiterhin separate Originalpakete und werden nicht eingebettet. Bootstrap ist ausdrücklich auf `6.0.0-alpha.1` festgelegt.

```sh
npm ci --ignore-scripts
npm audit --audit-level=moderate
node tools/release.mjs /absoluter/existierender/elternordner/skye-3.0.0
```

Der Zielordner darf noch nicht existieren und muss ausserhalb des Quellcheckouts liegen. Das Programm baut den normalen Assetstand, prüft den unveränderten Gitstand nochmals und exportiert den erfassten Commit über `git archive` in ein temporäres Verzeichnis. Es schreibt keine ZIP-Datei und veröffentlicht oder installiert nichts. Nach abgeschlossener Prüfung wird das vorbereitete Verzeichnis an den Zielpfad verschoben. Vorhandene Ziele werden abgelehnt; ein Fehler entfernt ausschliesslich das vom Programm angelegte temporäre Verzeichnis.

`.gitattributes` ist die verbindliche Exportgrenze. Tests, Tools, CI-Konfiguration, Entwicklungsassets, Paketmanifeste und Entwicklungskonfiguration gehören nicht zum ausgelieferten Theme. Lokale `info/attributes`-Überschreibungen werden abgelehnt; globale Attributdateien verändern diesen Export nicht. Symlinks und Submodule sind keine zulässigen Releasequellen. Ignorierte Dateien unter den Build-Quellbereichen werden abgelehnt, damit etwa eine lokale `.env` nicht versehentlich als Bild-/Fontasset gebaut wird.

Es werden ausschliesslich die im gerade erzeugten `dist/assets.json` referenzierten Dateien und die dort ausdrücklich ausgewiesenen aktuellen `_licenses` übernommen. Jedes Asset muss eine reguläre lokale Datei mit passendem Inhaltsfingerprint sein. Pfadtraversal, Wrapper, Symlinks, fehlende Dateien, leere Lizenzen, ungültiges JSON und unvollständige Performanceverträge brechen den Export ab. Alte weiterhin im lokalen `dist/` vorhandene Hashdateien werden nicht mitgeliefert. Das ist von der absichtlichen Aufbewahrung alter Dateien bei einem laufenden Deployment zu unterscheiden.

Die eigene Theme-Lizenz sowie die tatsächlichen Bootstrap-/Font-Awesome-Lizenzen bleiben enthalten. Die erfassten Critical-PHP-Quellen müssen im Export denselben SHA-256-Digest wie im Build haben. `release.json` dokumentiert Commit, Theme-/Bootstrap-Version, verwendete Node-Version und SHA-256-Digests sämtlicher Nutzdateien. Die Datei inventarisiert sich selbst nicht. Theme- und Paketversion müssen übereinstimmen. Ein sauberes Exportverzeichnis ist ein überprüfbares Artefakt und kein Nachweis über eine bereits erfolgte Veröffentlichung.

Git, `tar`, die dokumentierte Node-/npm-Laufzeit und die aus dem Lockfile installierten Buildabhängigkeiten sind Voraussetzungen. Eine installierte Produktionssite oder Produktionszugangsdaten werden für den Export nicht benötigt.

## Prüfprogramme

```sh
node --test tests/release.test.mjs
SKYE_WORDPRESS_CORE=/absoluter/pfad/wordpress php tools/testing/run.php
```

Die Release-Regression arbeitet mit einem Wegwerf-Gitrepository und dem tatsächlichen Build-/Exportcode. Sie prüft aktuelle Lizenzdateien und unveränderte Manifestbytes, den Ausschluss alter Ausgaben und Entwicklungsdateien, Dirty-/Ignored-Quellen, lokale Exportüberschreibungen, vorhandene beziehungsweise unsichere Ziele, beschädigte Dateien, Symlinks und fehlende oder ungültige Manifestdaten.

Der PHP-Runner führt jeden ausdrücklich klassifizierten Regressionseinstieg in einem frischen PHP-Prozess aus. ACF-Anwesenheit und -Abwesenheit sind eigene Core-Testfälle. Neue unklassifizierte PHP-Dateien unter `tests/` führen zum Fehler, statt stillschweigend nicht ausgeführt zu werden. Fixtures, der lokale Serverrouter und Programme für die echte Wegwerf-Site sind ausdrücklich aus diesem Standalone-Runner ausgeschlossen; die CI führt die Siteprüfungen separat aus. Ein fehlgeschlagener Prozess ergibt einen fehlgeschlagenen Gesamtlauf. Einzelprotokolle liegen unter `.git/runtime/php-tests/`; `SKYE_TEST_LOG_DIR` kann einen anderen lokalen Protokoll-Elternordner vorgeben.

## CI-Verträge

`.github/workflows/quality.yml` beschreibt folgende unabhängige Nachweise:

- Ein Node-Job verwendet Node `24.15.0` und npm `12.2.1`, installiert den Lockfile-Stand, führt das Abhängigkeitsaudit, den normalen Build, Build-/Watch-/Fehlerregressionen und den Release-Export aus. Die gebauten Assets werden unverändert an die weiteren Jobs übergeben.
- Vier PHP-Jobs kombinieren PHP `8.3`/`8.5` mit WordPress `6.8.12`/`7.1.4`. Sie installieren die gelockten Qualitätswerkzeuge mit Composer `2.11.1`, auditieren sie und prüfen Architektur, Dokumentation, Formatierung, Syntax, Standalone-Regressionsprozesse und die tatsächliche Wegwerf-Site. Die Originalerweiterung Classic Editor wird separat installiert und in zwei frischen Site-Prüfphasen zusätzlich aktiviert.
- Drei Browser-Jobs prüfen die echte lokale WordPress-Site mit Chromium, Firefox und WebKit unter Linux. Playwright `1.64.1` und sein gleich versioniertes `playwright-core` werden ausschliesslich als isoliertes CI-Werkzeug installiert. Der Router startet nur auf Loopback; die Browserprüfungen blockieren externe Requests. Die Serverprozesse werden auch bei Testfehlern beendet.

Coverage-Erweiterungen werden nicht installiert, da diese Tests keine Coverage-Berichte erzeugen. Überholte Prüfläufe derselben Workflow-/Event-/Ref-Kombination werden abgebrochen; andere Branches und Ereignistypen bleiben getrennt. `contents: read` und Checkout ohne gespeicherte Zugangsdaten reichen aus. Der Workflow enthält keinen Deployment- oder Schreibschritt zu einem Produktivsystem. Testfehler werden nicht über `continue-on-error`, dynamische Ausschlussfilter oder fehlende optionale Dateien verborgen.

Die Action-Versionen wurden am 03.11.2026 gegen die offiziellen Release-/Tagobjekte geprüft und auf Commit-SHAs festgelegt: Checkout `v7.0.1`, Setup Node `v7.1.0`, Upload Artifact `v7.0.2`, Download Artifact `v8.0.2` und Setup PHP `2.40.0`. Die Kommentare im Workflow nennen die lesbaren Versionen. Composer-/Playwright-Versionen wurden ebenfalls an ihren offiziellen Anbieterquellen geprüft. Ein geänderter Pin erfordert eine erneute Integrationsprüfung.

Diese Datei beschreibt die konfigurierten CI-Verträge. Ein lokal erfolgreicher Lauf ist kein tatsächlich erfolgter GitHub-Actions-Lauf. Chromium ist kein Edge-Nachweis, WebKit kein echter Safari-/iOS-Nachweis; Linux-Prüfungen ersetzen keine macOS-/Windows-/Touch-Geräteabnahme. Betriebssystem, Engine, reale Version und offene Geräteprüfungen gehören in den abschliessenden Testnachweis.

## Primärquellen

- [Git-Export und Attribute](https://git-scm.com/docs/git-archive): committierte Exportgrenze und Archive ohne Arbeitskopiedateien.
- [GitHub Checkout](https://github.com/actions/checkout), [Setup Node](https://github.com/actions/setup-node), [Upload Artifact](https://github.com/actions/upload-artifact), [Download Artifact](https://github.com/actions/download-artifact): offizielle Action-Eingaben und Releases.
- [Setup PHP](https://github.com/shivammathur/setup-php): PHP-/Extension-/Toolmatrix und `coverage: none`.
- [PHP-Unterstützung](https://www.php.net/supported-versions.php): Runtime-Unterstützung und dokumentierte Grenzen.
- [Composer-Versionen](https://getcomposer.org/versions), [Playwright](https://github.com/microsoft/playwright): Quellen der separat gepinnten Prüfwerkzeuge.
