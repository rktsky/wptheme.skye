# Allgemeine Performance-Verträge

## Native Auslieferung

Skye verwendet native WordPress-Enqueues mit Inhaltsnamen. Core bleibt für Abhängigkeiten, Inline-Daten und Skriptstrategien zuständig. Das Theme kopiert weder jQuery noch sonstige Core-JavaScript-Dateien in jede HTML-Antwort; sein eigener Drawer-/Menu-Einstieg hat keine jQuery-Abhängigkeit. Blockinhalte behalten benötigte Frontend-Assets, obwohl der Editor klassisch ist. Übersetzungen, XML-RPC und Customizer erhalten ihre nativen Betreiberverträge.

`Rocketsky\Skye\Assets\asset_uri()` liefert die endgültige Ausgabe-URL. Der optionale Filter `skye_public_asset_url` darf nur eine HTTP(S)-URL ohne Zugangsdaten liefern und wirkt ausschliesslich auf eigene Buildassets. Metadaten und kanonische Attachment-URLs bleiben unverändert. Ein CDN ist keine Voraussetzung. Child-Themes verwenden ihr eigenes vollständiges Manifest oder den kompletten Parent-Build; fremde Manifestdateien werden nicht mit Parent-Dateien gemischt.

## Mobile und Critical CSS

Der normale Build erzeugt die vollständige CSS-Datei und einen Mobile-Peer. Er entfernt nur einfache `min-width`- beziehungsweise Bootstrap-6-`width >= Npx`-Bedingungen, die unter dem gemeinsamen Breakpoint nachweislich falsch sind. Unbedingte `@layer`-Hüllen sind zulässig; fremde Einheiten, Mischbedingungen, Supports-/Container-Kontexte und unbekannte Syntax bleiben erhalten. Native komplementäre Media-Bedingungen erlauben auch einen späteren Viewportwechsel. Build und Runtime lesen denselben Breakpoint.

Critical CSS wird aus denselben vollständigen Quellen abgeleitet. Die eigenen PHP-Quellen liefern literal belegte Klassen; dynamische Zustände und Klassenfamilien stehen im Manifest. CSS-Kaskade und Layerreihenfolge bleiben bestehen. Unbekannte Syntax wird konservativ erhalten. Das Ergebnis ist kein Beweis, dass beliebige erste Viewports vollständig abgedeckt sind.

Der Standardpfad lädt deshalb vollständige normale Styles. Ein Projekt aktiviert progressive Auslieferung erst mit einem tatsächlich geprüften ersten Viewport:

```php
add_filter( 'skye_critical_layout', 'project_verified_layout', 10, 2 );
```

Die Projektfunktion liefert nur für seine geprüfte Route ein Array mit dem exakten generierten `id`, `build_hash` und `first_viewport => true`; sonst `false`. Der Build-Hash bindet die konfigurierten Shell-/Header-/Navigations-/Pageheader-Quellen einschliesslich der tatsächlich verwendeten Präsentations-, Komponenten-, Routing- und Titelhelfer sowie die vollständige CSS-Ausgabe. Er bestätigt keine beliebigen Views, Projekterweiterungen oder Inhalte; deren ersten Viewport muss das Projekt prüfen und relevante eigene Quellen in `source_paths` ergänzen. Kopieren eines Rückgabearrays ersetzt keine Geometrie-/Inhalts-/Zustandsprüfung. `skye_progressive_styles` kann den Pfad zusätzlich requestbezogen verweigern. Authentifizierte, geschützte, Admin-, API-, Vorschau- und Schreibkontexte behalten normale Ausgabe. Child-Theme-Änderungen brauchen einen eigenen Build und eine eigene Abnahme.

Nur die tatsächlichen eigenen unveränderten nativen Styles werden optimiert. Das komplette Stylesheet bleibt erhalten, der Mobile-Handle wird dabei zum nativen Alias, um keinen zweiten CSS-Abruf auszulösen. Unerwartete Registrierung, Metadaten, Abhängigkeiten, Inline-Daten oder späteres Opt-out behalten den ursprünglichen Pfad. Die Critical-Datei ist auf 96 KiB begrenzt; das ist ein Sicherheits-/Leistungsbudget für die umfangreichen V6-Root-Tokens, kein Zielwert. Der kleine Loader aktiviert Original-Links in ihrer Reihenfolge, ohne Layoutmessung im Head. No-JS erhält ein echtes Stylesheet. Fehler und spätere Styles werden begrenzt behandelt.

## Formulare

`Performance` spiegelt Gravity Forms' tatsächlich aktive Defer-Entscheidung für überprüfte Handles in WordPress-Metadaten. Core darf Abhängigkeiten weiterhin blockierend ausgeben, wenn Inline-Code oder andere Verbraucher dies verlangen. Unbekannte Provider bleiben nativ. Ein nachweislich leeres lokales Provider-Stylesheet kann als quellloser Alias seine Abhängigkeiten und Inline-Regeln behalten; geänderte Dateien/URLs/Attribute behalten den normalen Abruf.

PHP-gerenderte Formulare müssen vor dem Head über Gravity Forms angemeldet werden. Der Filter `skye_form_assets` kann kleine vorbereitete Beschreibungen `array( array( 'id' => 3, 'ajax' => false ) )` liefern. Skye ruft dafür die native Providerfunktion auf, dedupliziert identische Angaben und erfindet weder ACF-Layoutnamen noch neue Datenbankabfragen.

Ab 3.3 behalten zusätzliche native Formular-/Script-Produzenten die vollständige normale Auslieferung. Die Klassenadapter verändern keine Honeypot- oder begleitenden Sicherheitsfelder; [Rendergrenzen und getrennte WEBFORM-Abnahme](forms-native-contracts.md) beschreiben den tatsächlichen Umfang. Provider-Sicherheitsprüfungen werden für die Auslieferung weder abgeschaltet noch nachgebaut.

Progressive Formularinitialisierung gilt zusätzlich nur für den überprüften nativen Text-/E-Mail-Einzelfeldvertrag ohne unbekannte Add-ons, dynamische Layouts oder eigene Initialisierer. Der ursprüngliche Providerkörper, Aufrufkontext und die Argumente bleiben unverändert und werden pro Aufruf genau einmal freigegeben. Unbekannte Formulare behalten normale Styles und native Initialisierung. `FormControls` ergänzt nur Klassen an tatsächlichen Provider-/Kommentar-/Sucheingaben über die WordPress-HTML-API; Werte, Nonces, Reihenfolge und Accessibilityattribute werden nicht rekonstruiert.

## Icons, Fonts und Preloads

Der Build darf Free-Glyphen aus der installierten Originalbibliothek anhand ausdrücklich erlaubter Schlüssel übernehmen. `Icons::render( 'solid/arrow-right' )` rendert das geprüfte Modell über eine Komponente; beliebiges SVG oder Pro-Glyphen gehören nicht zu diesem Vertrag. Unbekannte Glyphen verwenden den vollständigen Font-Awesome-Pfad. Ohne einen projektweit geprüften `skye_icon_subset_complete`-Opt-in bleibt die vollständige CSS-Ausgabe aktiv. Gespeicherte/dynamische oder fremde Iconquellen sind keine statisch bewiesene Teilmenge.

Font-Preloads kommen ausschliesslich aus tatsächlich veröffentlichten konfigurierten eigenen WOFF2-Dateien. Im passenden Critical-Pfad dürfen nur exakt eigene unveränderte Hinweise einmal am Body-Anfang statt im Head erscheinen; Kollisionen und fremde Attribute bleiben nativ. Keine feste Volksgarage-Schriftliste und keine künstliche Zeitverzögerung. Bildhinweise können über `skye_preload_resources` auf den tatsächlichen gewählten Attachment-/Format-/Sizes-Vertrag abgebildet werden. Responsive Hinweise ohne redundante Fallback-Href verhindern einen doppelten Download in älteren Preload-Parsern. Es gibt keine allgemeine Hero-Heuristik, die beliebige Inhalte als geprüft erklärt.

## Messstand und Grenzen

Auf dem am 04.11.2026 mit Node 14 erzeugten V6-Alpha-Build: vollständige Haupt-CSS 365.655 Byte, Mobile-Peer 283.731 Byte, Critical-CSS 80.467 Byte und eigener Drawer-/Menu-Einstieg 50.443 Byte. Der Mobile-Peer ist gegenüber der vollständigen Datei desselben Builds rund 22 Prozent kleiner. Dies sind Rohgrössen vor HTTP-Kompression, keine gemessene Ladezeit oder ein allgemeines Lighthouse-Versprechen. Das Alpha-Upgrade ist kein Vergleich mit dem älteren V5-Design. Kleine Änderungen an eigenen Quellen verändern die Inhaltsnamen und diese Werte.

Native Gegenprüfungen auf WordPress 6.8.12 und 7.1.4 belegen 46 Skriptstrategie-, 99 Formular- und 51 leere-Stylesheet-Aussagen. Hinzu kommen unabhängige Pfad-/Manifest-/Icon-/Formdescriptor- und Critical-/Preload-Gegenprüfungen gegen echte Core-Parser und Register. Ein zusätzlicher echter Chrome-155-Browserlauf auf macOS prüft den ausdrücklich zugelassenen synthetischen ersten Viewport bei 390 und 1440 CSS-Pixeln: Die vollständige CSS-Antwort wird bis nach `DOMContentLoaded` zurückgehalten; Navigation, Überschriftgeometrie, einmaliger Abruf, No-JS und spätes Opt-out bleiben korrekt. Das ist eine kontrollierte Testseite ohne beliebigen Seiteninhalt, keine automatische Freigabe produktiver Layouts. `tests/browser-critical.cjs` installiert seine Fixture nur vorübergehend in der isolierten lokalen Testsite und entfernt sie auch bei Fehlern. Der vollständige Browser-/Geräte- und Mediennachweis steht in den jeweiligen globalen Test-/Mediendokumenten. Fehlende Geräte und Versionen bleiben offene Abnahmelücken.

Der Einmaligkeitsnachweis zählt vollständig abgeschlossene HTTP-200-Übertragungen. Durch das Test-Routing vor der Netzwerkfreigabe abgebrochene Versuche ohne Antwort und ohne Abschluss übertragen keine CSS-Datei; alle anderen zusätzlichen oder ungeklärten Versuche bleiben ein Fehler. Vollständiges Request-Tracking und die dem nativen Link zugeordnete geparste CSSOM sichern diese Unterscheidung ab. Einzelheiten und Grenzen stehen unter [Buildprüfung](build-deployment.md).

Offizielle Verträge: [Skriptstrategien](https://developer.wordpress.org/reference/functions/wp_enqueue_script/), [Stylesheet-Tag](https://developer.wordpress.org/reference/hooks/style_loader_tag/), [Gravity-Forms-Postrender](https://docs.gravityforms.com/gform_post_render_script/), [HTML-API](https://developer.wordpress.org/reference/classes/wp_html_tag_processor/). Die Implementierung verallgemeinert die in `docs/build-deployment.md` genannten abgeschlossenen Website-Commits; Website-Inventare, Branding und CDN-Provider wurden nicht übernommen.
