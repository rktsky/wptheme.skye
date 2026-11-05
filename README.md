# Skye 3

Skye 3 ist ein klassisches WordPress-Basistheme mit **Bootstrap 6.0.0-alpha.1**. Beiträge und Widgets werden im Classic Editor bearbeitet. Der Block-Editor ist deaktiviert.

## Voraussetzungen

- PHP ab 8.3; lokal geprüft mit PHP 8.3.36.
- WordPress ab 6.8.12; native Regressionen und Integrationsstände sind in [Prüfungen](docs/testing.md) aufgeführt. WordPress 6.8 unterstützt PHP 8.3/8.4, PHP 8.5 wird mit WordPress 7.1.4 geprüft; die Laufzeiten müssen auch zu Core passen.
- Build und Watch: Node 14.21.3 und npm 6.14.18 für die bestehenden CentOS-7-Systeme. Die Versionen und Abhängigkeiten stehen in `.nvmrc`, `package.json` und `package-lock.json`.
- ACF PRO und Redis bleiben eigenständige Installationsbausteine. Der Kern rendert auch ohne beide.

## Installation und Entwicklung

```sh
npm ci --ignore-scripts
npm run build
npm run watch
```

Das Theme mit vollständigem `dist/` in WordPress aktivieren. Ein reiner Quellcheckout ohne Build enthält keine ausgelieferten Styles/Skripte. `watch` baut auch bei relevanten PHP-Änderungen neu; erzeugte Dateien werden nicht manuell gepatcht. Der Build veröffentlicht nur eine vollständige Ausgabe und erhält bei Fehlern den vorherigen Stand.

Skye lässt sich über `WP_DEFAULT_THEME` in der jeweiligen `wp-config.php` als Standardtheme festlegen. Dafür den tatsächlich installierten Themeordner verwenden; das Theme selbst lädt für diese Einstellung zu spät. [Konfiguration und Fallback-Grenzen](docs/build-deployment.md#skye-als-standardtheme).

Vor eigenen Änderungen im Quellcheckout `AGENTS.md` und die [Coding Standards](docs/coding-standards.md) lesen. PHP-/JS-Logik, Styling und reine Formatierung gehören in getrennte Commits mit ihren jeweiligen Prüfnachweisen. Funktionen und Modelle liegen in `lib/`, wiederverwendbares HTML in `components/`, Ansichten in `views/`, SASS und Verhalten in den vorhandenen Assetordnern.

```php
get_component( 'posts', 'element', array( 'article' => skye_post_model( get_the_ID() ) ) );
```

Der bestehende Loader unterstützt weiterhin Ein-/Zweiargumentaufrufe und Child-Theme-Overrides. Neue Daten werden als Komponentenargumente übergeben. Verträge und Änderungen der Legacy-APIs stehen in [Komponenten](docs/components.md) und [Migration](docs/migration-3.md).

## Enthaltene Verbesserungen

- Native WordPress-Abhängigkeiten und versionierte Assets; ab 3.1 optionale geprüfte statische Bundles und vollständiges CSS über native Filter ohne HTML-Optimierungsplugin.
- Automatischer vollständiger Mobile-CSS-Build, quellgebundene Critical-CSS-Erzeugung und konservative Auslieferung. Critical CSS benötigt einen ausdrücklichen Vertrag für den gesamten ersten Viewport; andere Ansichten behalten vollständiges CSS.
- Native Formularadapter, gezielte optionale Gravity-Forms-Optimierungen, konfigurierbare Font-/Bild-Preloads und Icon-Subset mit vollständigem Fallback.
- Automatische begrenzte Medienjobs für vollständige kleinere WebP-Alternativen; native Originale und Bildmetadaten bleiben erhalten. Keine Konvertierung oder Dateiprüfung beim Bildrendern.
- Vollständige Standardansichten, native Menüs, Passwort-/optionale Members-Grenzen und sichere ACF-Einstellungsrechte.
- Native Mailblockade nur auf Staging mit ausdrücklichem Projekt-Opt-out; Produktion bleibt nativ.
- Ab 3.2 eine generische vollflächige mobile Navigation mit nativer Dialogmodalität, stabiler Markenidentität und funktionierendem Detailsfallback; bestehende Childs integrieren sie ausdrücklich.
- Ab 3.2.2 öffnen externe HTTP(S)-Links in nativen Menüs und redaktionellen Inhalten mit `target="_blank"`, `noopener` und `noreferrer`; ein gemeinsamer Helper steht expliziten Theme-Links zur Verfügung.
- Keine pauschale SVG-Uploadfreigabe, Pluginabschaltung, eigene MO-Dauercaches oder produktiven Datenmigrationen beim Seitenaufruf.

## Dokumentation

- [Build, Konfiguration und Abhängigkeiten](docs/build-deployment.md)
- [Performance-Verträge und Grenzen](docs/performance.md)
- [Native statische Auslieferung, CDN und Core-Update-Setup](docs/website-delivery.md)
- [Native Mailgrenzen auf Staging](docs/environment-mail.md)
- [Mobile Navigation und Child-Integration](docs/mobile-navigation.md)
- [Externe Links und Theme-Helper](docs/external-links.md)
- [Medienpipeline und Betrieb](docs/media.md)
- [Komponenten und Rohdatenhelfer](docs/components.md)
- [Sicherheit und getrennte Infrastruktur](docs/security.md)
- [Migration von Skye 2](docs/migration-3.md)
- [Backport-Herkunft](docs/backports.md)
- [Prüfungen und offene Abnahme](docs/testing.md)
- [Release](docs/release.md)

Skye 3 ersetzt bestehende Projekt-Themes nicht automatisch. Änderungen zuerst in deren Entwicklungsumgebung übernehmen und gegen eigene ACF-Inhalte, Plugins und Anpassungen prüfen. Bootstrap-6-Alpha-Kompatibilität ist Teil dieser Migration; ein Bootstrap-5-Theme wird nicht durch Austausch der Bibliotheksdatei migriert.

## Lizenzen

Der eigene Skye-Code folgt der vorhandenen BSD-3-Clause-Lizenz in [LICENSE](LICENSE). Die früher abweichenden MIT-Metadaten wurden daran angeglichen. Drittanbieter behalten ihre eigenen Lizenzen; der Build legt die tatsächlich verteilten Nachweise unter `dist/licenses/` ab. Bootstrap, Font Awesome und ihre unveränderten Paketquellen werden separat verwaltet. ACF-PRO-Code, Lizenzen und Zugangsdaten gehören nicht in dieses öffentliche Repository.
