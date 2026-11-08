# Yoast und die aktive Inhaltsquelle

Skye 3.5.0 stellt zwei ausdrückliche Anbindungen für eigene Projektinhalte bereit.
Es installiert keine Plugins und kennt keine Feldnamen, Post Types oder
Builderlayouts eines Kundenprojekts. Ohne Projektfilter bleiben Analyse und
Auslieferung unverändert; es kommen keine Editorassets oder Inhaltsabfragen hinzu.

Yoast SEO und das Originalplugin
[ACF Content Analysis for Yoast SEO](https://wordpress.org/plugins/acf-content-analysis-for-yoast-seo/)
bleiben separate Installationen. Bei Skye mit ACF und Yoast gehört dieses Add-on
zum Projektsetup. Seine native Sammlung genügt, solange sie die tatsächlich
sichtbare Quelle vollständig und einmal erfasst.

## Gespeicherte Inhalte und Bildsuche

`skye_yoast_analysis_content` erhält drei Argumente:

| Argument | Vertrag |
| --- | --- |
| `$projection` | Anfangs `null`; ein früherer Projektfilter kann schon eine Projektion geliefert haben. |
| `$post` | Das genaue `WP_Post`-Objekt aus Yoasts zweitem Filterargument, unabhängig vom globalen Loop. |
| `$incoming` | Der bisherige Inhalt von `wpseo_pre_analysis_post_content`. |

Ein String ist die **vollständige** aktive Projektion. Auch ein leerer String ist
ein gültiges Ergebnis. `null`, unbekannte Typen oder eine Exception behalten
`$incoming`. Skye ruft den Projektfilter nur für öffentliche, zugängliche
Datensätze ohne Passwort auf. Nicht veröffentlichte und private Inhalte bleiben
nativ. Die bestehende Skye-Zugriffsgrenze gilt zusätzlich.

Der Projektcallback prüft seinen eigenen Post Type und seine aktive Quelle,
bevor er Felder liest. Er verwendet echte sichtbare Überschriften, Links und
Attachmentdaten. Er rendert weder die gesamte Seite noch Formulare, führt keine
Providerabfragen aus und schreibt weder Inhalte noch Yoast-Bewertungen.
Projektfremde Fälle geben die frühere Projektion unverändert zurück.

Der PHP-Filter ist **kein reiner Editorhook**: Yoast 28.8 verwendet ihn über
`WPSEO_Content_Images::get_images()` für die Bildsuche. Eine Projektion kann somit
Indexables und Social-Metadaten beeinflussen. Explizite Social-/Beitragsbilder,
wirkliche Bildbeschreibungen und Bildmetadaten müssen im Projekt geprüft werden.
Keine ORM-Felder verändern oder erfundene Bilder wählen, um Abfragen zu sparen.
Gemeinsame Firmen- oder Kartenmodelle nur laden, wenn der betreffende Abschnitt
sie wirklich benötigt.

## Ungespeicherte Änderungen im Editor

Ein Projekt registriert seinen eigenen Editoradapter vor Priorität 20 von
`admin_enqueue_scripts`. Dessen native Script-Abhängigkeiten enthalten
`skye-yoast-analysis` und die tatsächlich benötigten Projekt-/ACF-Abhängigkeiten.
Das Asset muss im eigenen Buildmanifest vorhanden sein.

Der Filter `skye_yoast_analysis_editor` erhält `null` und das genaue aktuelle
`WP_Post`-Objekt. Für seine bekannten Dokumente liefert das Projekt:

```php
array( 'id' => 'customer', 'script' => 'customer-yoast-analysis' )
```

Beide Werte sind begrenzte Bezeichner mit Buchstabe am Anfang und anschliessend
Buchstaben, Ziffern, `_` oder `-`; maximal 64 Zeichen. Für fremde Dokumente
bleibt das vorherige Filterergebnis erhalten. Der Projektcallback soll keine
Kartenkataloge oder entfernten Daten laden, um erst über seine Zuständigkeit
zu entscheiden.

Skye lädt den Adapter nur auf `post.php`/`post-new.php`, wenn Screen und
Post Type übereinstimmen und `current_user_can( 'edit_post', $post->ID )`
erfüllt ist. Entwürfe können hier durch den berechtigten Editor analysiert
werden; das ist von der öffentlichen PHP-Bildsuche getrennt. Fehlende Builds,
unbekannte Konfigurationen, ein bereits ausgeführter Projektadapter oder ein
vorher fremd registriertes `skye-yoast-analysis`-Handle behalten native
Auslieferung. Lokalisiert wird ausschliesslich die Projektkennung.

Ist das originale ACF-Add-on geladen oder sein Handle registriert, muss dessen
Scriptrecord zur geprüften 3.2 passen: Version, originale Plugin-URL und
Abhängigkeiten `jquery`/`underscore`. Fehlt dieser Record oder ist er unbekannt,
bleibt die gesamte Live-Brücke aus. Der interne Append-Auftrag wird damit nicht
pauschal für künftige Add-on-Versionen angenommen. Das Add-on veröffentlicht
keine eigene Versionskonstante; sein nativer Scriptrecord trägt den Headerwert.
Ohne Add-on bleibt eine reine Yoast-Projektion möglich. Der PHP-Vertrag für
gespeicherte Inhalte ist davon unabhängig.

Im eigenen Adapter wird genau ein Callback angemeldet:

```js
const analysis = window.SkyeYoastAnalysis;
analysis.register( 'customer', ( beforeAcf, combined ) => {
	const source = readCurrentOwnedSource();
	if ( source === 'editor' ) return beforeAcf;
	if ( source !== 'builder' ) return null;
	return projectCurrentVisibleContent();
} );
```

`readCurrentOwnedSource()` und `projectCurrentVisibleContent()` sind die
bestehenden projektspezifischen Funktionen, keine von Skye gelieferten
Feldsammler. Sie lesen die tatsächlichen ungespeicherten ACF-/TinyMCE-Werte.
Die Projektion ist synchron und enthält nur die aktive sichtbare Quelle.
Konfigurations-IDs, inaktive Entwürfe, Formular-UI und Footertexte werden
nicht als Seiteninhalt angehängt. Überschriftenebenen, reale Links und Bilder
entsprechen den tatsächlichen Templates.

Nach einer relevanten Änderung ruft das Projekt
`analysis.refresh( 'customer' )` auf. Dazu gehören Texteingaben, TinyMCE-Änderungen,
Auswahl, neue/gelöschte/sortierte Zeilen und Quellenwechsel. Es werden nur die
eigenen Kontrollen beobachtet. Der Aufruf bündelt mehrere Ereignisse für
100 ms und verwendet Yoasts `pluginReloaded()`; er speichert und lädt keine
Inhalte. `register()` bestätigt die Annahme des Callbacks, auch wenn Yoast
noch nicht bereit ist. `refresh()` liefert bis zur nativen Registrierung
`false`. Eine asynchron geladene Projektdatei kann sich vor oder nach
`YoastSEO:ready` anmelden.

## Collector, Reihenfolge und Fehler

Das unveränderte ACF-Add-on 3.2 sammelt bei Analysepriorität 10. Skye merkt sich
bei 5 den Eingang vor diesem Append und lässt ihn unverändert weiterlaufen.
Bei 20 liefert ein bereiter Projektcallback die vollständige aktive Projektion.
Er erhält sowohl den Eingang vor ACF als auch das kombinierte Ergebnis.

Ohne Callback, bei `null`, unbekanntem Rückgabetyp oder Exception bleibt das
vollständige kombinierte Ergebnis einschliesslich des nativen Collectors
erhalten. Es gibt **keinen PHP-Leaf-Ausschluss**: Das Add-on bietet dafür einen
serverseitigen Namensfilter, aber keinen öffentlichen JS-Filter mit einer
an die tatsächliche Callbackbereitschaft gebundenen Lebensdauer. Ein
vorzeitig gesetzter Ausschluss könnte den Fallback unvollständig machen.

Der gespeicherte Eingang wird einmal pro synchroner Analyse verbraucht;
ein abgebrochener Durchlauf verfällt an der nächsten Microtask-Grenze.
Ohne passenden aktuellen Eingang erfolgt keine Projektion. Der in Yoast 28.8
gelesene native Modifikationslauf ist synchron und sortiert nach Priorität.
Ein fremder Beitrag zwischen 5 und 20 gehört zu `combined`: Ein vollständiger
Ersatz muss ihn gegebenenfalls ausdrücklich einbeziehen. Beiträge nach 20
behalten ihre native Reihenfolge. Das ist im Projekt zu prüfen, kein
allgemeiner Konfliktlöser für andere Analyseintegrationen.

Vor der Übernahme bestehende **eigene** Analysebrücken und deren Leaf-Ausschlüsse
ablösen. Zwei parallel registrierte Projektprojektionen sind kein unterstützter
Zusammenschluss. Originalplugins bleiben unverändert. Der alte
Rocketsky-Adapter liefert das belegte Semantik-Learning, seine festen
`field_rs_*`-/CPT-/Layoutzuordnungen gehören weiterhin ins Kundenprojekt.

## Prüfung

`tests/yoast-analysis.test.cjs` führt die echte Skye-JavaScriptdatei mit
geordneten nativen API-Gegenstellen aus. Abgedeckt sind Collector-Fallback,
komplette aktive Quelle, ungespeicherte Änderungen und gebündeltes Refresh,
beide Initialisierungsreihenfolgen, Fehler, aktuelle Eingangszuordnung und
spätere fremde Beiträge. Das ist kein Browser- oder nativer Yoast-Worker-Test.

`tests/yoast-analysis-native.php` verwendet eine isolierte lokale
WordPress-/SQLite-Installation mit veröffentlichtem Standarddokument und Admin.
Es prüft die tatsächlichen Core-Hooks, öffentliche/private Identitäten,
Fehlerrückgaben, vollständige Script-Abhängigkeiten und fremde Handles. Wenn
das Original Yoast geladen ist, wird auch seine echte Bildsuche geprüft.
Die genaue Provider-/Browserabnahme und ausgeführten Laufzeiten gehören in
den jeweiligen [Prüfnachweis](project-learnings-3.5.0.md).

Im Kundenprojekt die asynchrone native Analyse abschliessen lassen, speichern,
neu laden und beide Register mit ihren einzelnen Meldungen prüfen. Gesamtampeln
ersetzen keine Prüfung offener roter/oranger Hinweise. Scores werden nie
zugewiesen oder kopiert. Canonicals, Social-Bilder, JSON-LD und echte ungecachte
Frontendabfragen separat kontrollieren; die Editoranzeige beweist diese
Produktionsverträge nicht.

## Quellen

Am 8. November 2026 wurden die
[öffentliche Yoast-Analyse-API](https://developer.yoast.com/customization/yoast-seo/adding-custom-data-analysis/)
und die unveränderten Originalquellen Yoast 28.8 sowie ACF Content Analysis 3.2
gelesen. Relevant sind `inc/class-wpseo-content-images.php`, der native
`_applyModifications()`-Aufruf, im Add-on `inc/assets.php`,
`js/src/app.js`, `js/src/collect/collect.js` und
`inc/configuration/configuration.php`. Der letzte Name dokumentiert den
öffentlichen `Yoast\\WP\\ACF\\blacklist_name`-Filter nach Leafnamen; Skye setzt ihn
absichtlich nicht. Core-Verträge:
[Post-Sichtbarkeit](https://developer.wordpress.org/reference/functions/is_post_publicly_viewable/),
[Objektberechtigung](https://developer.wordpress.org/reference/functions/current_user_can/),
[Script-Registrierung](https://developer.wordpress.org/reference/functions/wp_register_script/)
und [Script-Abhängigkeiten](https://developer.wordpress.org/reference/functions/wp_enqueue_script/).
