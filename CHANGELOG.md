# Änderungen

## 3.5.0

- Gemeinsame Quellenwahl für Classic Editor und ACF-Baukasten. Beide Entwürfe behalten ihre nativen Speicherwege; der Controller erhält ungespeicherte Inputs und synchronisiert vorhandene TinyMCE-Instanzen. Die eng begrenzte inaktive Validierung verwendet das geprüfte ACF-PRO-6.8.11-Profil ohne Fehlerreset oder Vendoränderung.
- Automatische Yoast-Kopplung an die bereite Quellenwahl. Der originale ACF-Collector bleibt erhalten; semantische Projektionen sind zusätzlich möglich. Keine Score-Schreibzugriffe. Die PHP-Projektion kann Yoasts Bildsuche beeinflussen; Social-Bildprioritäten werden im Projekt geprüft.
- `SourcePublicContext` bindet automatische Auszüge, native öffentliche REST-/Feed-Inhalte und Yoast-Auszugvariablen an die aktive Quelle. Der inaktive ACF-Root wird im öffentlichen Editor-REST vor der Werteaufbereitung ausgeschlossen.
- Native Batch-Abfrage der drei bisherigen Theme-Einstellungen; `get_option()` und gezielte Invalidierung bleiben erhalten.
- Frischer POSIX-Pfadfastpath aus Francesca; bestehende Eingabegrenzen, Aliasprüfung und Windows-Verhalten bleiben erhalten.
- Releaseexport mit engem Git-1.8-Fallback und Ausschluss der internen Quelldokumentation. Der Publisher prüft diesen Ausschluss zusätzlich.
- Generischer öffentlicher Crawleraudit aus Rocketsky und eine SEO-Abnahme mit nativen Yoast-/ACF-Verträgen.

Bei der Übernahme beachten: Ein gebundener Builder fällt bei leerem Inhalt nicht
mehr auf den inaktiven Editor zurück. Eine ausdrücklich gespeicherte Builderwahl
bleibt auch bei verlorenem Feldprofil verbindlich. `components/page/content.php`
ist jetzt der Dispatcher; reine native Editorüberschreibungen gehören nach
`components/page/native.php`. [Quellenwahl und Migration](docs/editor-sources.md).
Öffentliche Builderexporte über REST, Feeds und automatische Auszüge bleiben
ohne gültigen semantischen PHP-Callback leer. Vor dem Merge den Callback und
die Exportwege prüfen. Manuelle Auszüge, explizite SEO-/Social-Beschreibungen
und autorisierte native REST-Edit-Leser behalten ihre nativen Verträge.
[Öffentliche Quellenabgrenzung](docs/public-content-sources.md).

Core- und ACF-Saves bleiben getrennt. Bei direktem POST kann eine spätere
ACF-Ablehnung bereits gespeicherten Coreinhalt nicht zurücknehmen. Die
[Teilupdategrenze](docs/editor-sources.md#grenze-bei-abgelehnten-speicheraufrufen)
ist Teil der Fehlerabnahme; eine gemeinsame Transaktion wird nicht zugesagt.

Herkunft, Projektausschlüsse und tatsächliche Prüfungen:
[Projekt-Learnings 3.5.0](docs/project-learnings-3.5.0.md).

## 3.4.1

- Offene und aktive Adminmenüs behalten die Markenfarben, wenn der Mauszeiger vom Hauptlink ins Untermenü wechselt. Icons, Tastaturfokus und die entsprechenden Adminbar-Zustände sind einbezogen.
- Normale Verwaltungslinks verwenden einen lesbaren Markenton auf weissen und nativen hellgrauen Flächen. Native Löschfarben, Statushinweise und Fokusmarkierungen bleiben erhalten; beide nativen RGB-Variablenformen sind angebunden.
- Die Korrektur baut ausschliesslich auf dem ausgelieferten 3.4.0-Quellstand `b78d1177c18042a2371611a763ffd8f14989aede` auf. Öffentliche Frontendstyles und Loginstyles werden nicht geändert.

## 3.4.0

- Backend- und Loginfarben entstehen aus der bestehenden Websitepalette. Das Login verwendet die vorhandene Logoquelle oder den Websitenamen; Kundenthemes können ihre bereits verwendeten Producer anbinden. Keine zweite redaktionelle Konfiguration und keine Änderung der Authentifizierung.
- Kleine eigene Backend-/Loginstyles statt vollständigem Frontend-CSS. Automatische Text-, Fokus- und Kontrolltöne berücksichtigen helle Markenfarben sowie dunkle Websitehintergründe; native Auswahlmarkierungen und deaktivierte Controls bleiben erhalten.
- Lokale Assetquellen werden auch bei Request-Cachetreffern gegen frische Pfad-, Root- und Descriptoridentitäten geprüft. Gemeinsame begrenzte Pfadauflösung verwirft extern ausgetauschte Zwischenverzeichnisse und Symlink-Escapes.
- Explizite metadatenbasierte Auswahl kleinerer responsiver Bildkandidaten. Die bisherige Rocketsky-Logoentscheidung ist als eigene API verfügbar; andere Bilder werden nicht automatisch umgeschrieben.

Verträge: [Backend-Branding](docs/backend-branding.md), [lokale Quellgrenzen](docs/security.md) und [Bildkandidaten](docs/media.md). Die Formular- und Medienverträge aus dem vorbereiteten Stand 3.3.0 sind enthalten. Node 14.21.3/npm 6.14.18 und die getrennten Originalpakete bleiben erhalten. Kundenübernahmen und ihr tatsächlicher Rollout werden im jeweiligen Projekt dokumentiert.

## 3.3.0 – vorbereiteter Quellstand

Basis: Skye 3.2.2, Commit `d2685a3b7acbc65e67f5d607b23f9c41a967350a`. Die folgenden Verträge wurden im Quellstand `cebddb5` vorbereitet und sind in 3.4.0 enthalten. Kundenübernahmen werden separat geprüft.

- Formularadapter erhalten native Honeypots bytegleich. In Buttonfragmenten bekommen ausschliesslich tatsächliche Submitter die Buttonklassen; begleitende Sicherheitsfelder und Scripts bleiben unverändert.
- Progressive Formular-CSS-Auslieferung verweigert zusätzliche Render-/Script-Produzenten und prüft deren Registrierung vor der Head-Ausgabe erneut. Vollständiges GF-Basic und der native Honeypot-Fallback bleiben erhalten.
- Eine explizite WebP-Preload-API bereitet Hinweise aus den bereits vorhandenen Medienalternativen vor. Keine Seiten-/Hero-Erkennung und keine Bildkonvertierung beim Rendern.

Die übertragbaren Verträge sind in [Formulare](docs/forms-native-contracts.md) und [responsive WebP-Vorladungen](docs/responsive-image-preloads.md) beschrieben. Tatsächliche Quellprüfungen und offene Integrationsabnahmen werden dort getrennt geführt. Bestehende Kunden-Forks werden nicht automatisch aktualisiert; eine Übernahme benötigt einen gezielten Merge und erneute Projektprüfung.
