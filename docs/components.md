# Komponenten, Daten und Standardansichten

Skye 3 bleibt ein klassisches PHP-Theme mit `lib/`, `lib/package/`, `components/`, `views/` und optionalen `templates/`. Neue Modelle und Entscheidungen stehen in `lib/presentation.php`; die Templates enthalten HTML, Schleifen und Escaping. Native WordPress-Serializer wie `the_content()`, `wp_link_pages()` und der vorbereitete Kommentarablauf behalten ihre eigenen Verträge.

## Komponenten laden

```php
get_component( 'posts', 'element', array( 'article' => skye_post_model( get_the_ID() ) ) );
$html = skye_component_html( 'navigation/toggle', false, array( 'label' => $label ) );
```

Der Loader erhält Ein-/Zweiargumentaufrufe, Kindtheme-Overrides und den Fallback `components/name/name.php`. Neue Daten werden ausdrücklich im dritten Array übergeben. Er übernimmt keinen lokalen Aufruferscope. `name`, `second`, `args` und `template` sind reserviert und können durch `extract(..., EXTR_SKIP)` nicht überschrieben werden. Pfadbestandteile dürfen Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten; absolute Pfade, `..`, Dateiendungen und andere Steuerzeichen gelangen nicht zum Resolver. Fehlende Komponenten liefern `false`, der gepufferte Adapter eine leere Zeichenkette. Renderfehler werden nach Wiederherstellung der Ausgabepuffer weitergereicht.

Der Loadervertrag garantiert weiterhin die alte Aufrufform, aber nicht die impliziten globalen Datenverträge der früheren News-/Website-Komponenten. Modelle tragende Komponenten bekommen jetzt die dokumentierten kleinen Argumente:

| Komponente | Kontext |
| --- | --- |
| `header`, `navigation` | `brand` aus `skye_brand_model()`, `navigation` aus `skye_navigation_model()` |
| `header/brand`, `footer` | `brand` mit Name, URL, optionaler Logo-URL und nativer Bildausgabe |
| `page/header`, `pageheader/simple` | `header` aus `skye_page_header_model()` |
| `posts/element`, `search` | `article` aus `skye_post_model()` |
| `posts/meta` | `article` aus `skye_post_meta_model()` oder dem vollständigen Kartenmodell |
| `navigation/toggle` | übersetztes Klartext-`label` |
| `media/picture` | validierte `webp_source`, `sizes` und unverändertes `native_image` vom Medienpaket |
| `assets/stylesheet` | bereits nativ serialisierte `preload_html` und `stylesheet_html` vom Performancepaket |
| `header/font-preloads` | vorbereitete eigene `fonts` mit URL, Typ und CORS-Vertrag |
| `icons/glyph` | validiertes installiertes Provider-Modell `glyph`; keine fremden SVG-Strings |

Native Serializer-Ausgaben werden als solche ausgegeben; gespeicherte Texte, Attribute und URLs werden am HTML-Ausgabepunkt escaped. Absichtlich erlaubter Lead-Richtext wird mit `wp_kses_post()` begrenzt. Der Debugbaustein ist nur hinter `WP_DEBUG` und `manage_options` erreichbar; Aufrufer dürfen auch dort keine Geheimnisse ausgeben.

## Rohdaten ohne ACF-Abfragen

`get( $selector, $post_id = false, $format_value = true )` liefert einen einzelnen **rohen nativen Wert**. Das gilt auch bei aktivem ACF. Dadurch entstehen keine zusätzlichen ACF-Felddefinitions-/Formatierungsabfragen für den bestehenden schnellen Metadatenweg. Der dritte Parameter bleibt als Kompatibilitätsargument akzeptiert und wird nicht als WordPress-`single`-Schalter missverstanden. Wer formatierte ACF-Strukturen benötigt, ruft `get_field()` am ausdrücklich dafür vorgesehenen Modellrand auf; das optionale Header-Gruppenmodell tut dies nach der Zugriffsprüfung.

| Identität | Speicherweg |
| --- | --- |
| `false`, `null`, leere Zeichenkette | aktueller nativer Loop-Post |
| positive Integer-ID oder reine positive Ziffernfolge, `WP_Post` | Postmetadaten |
| `WP_User`, `user_N` | Benutzermetadaten |
| `WP_Term`, `term_N` | Termmetadaten |
| `WP_Comment`, `comment_N` | Kommentarmetadaten |
| `option`, `options` | `options_`-Optionspräfix |
| `theme_options` | bestehendes `theme_options_`-Präfix |

Keine Quelle fällt auf eine andere zurück. Null-/Leer-/Nullzahlwerte sind kein Anlass, Daten eines Benutzers mit derselben numerischen ID oder eine globale Option zu lesen. Native WordPress-Rückgabetypen bleiben erhalten: nicht serialisierte gespeicherte Zahlen kommen als Zeichenketten, gespeichertes `false` gewöhnlich als leere Zeichenkette; eine native Filterantwort `false` bleibt ebenfalls unverändert. Fehlende gültige Metadaten bleiben die native leere Antwort; ungültige Identitäten liefern `false`. Feldschlüssel `field_...` sind kein Ersatz für native Feldnamen. Der Helper ist Datenzugriff und keine Berechtigungsprüfung.

`get_image()` delegiert an `wp_get_attachment_image_url()`. Es gibt keinen dauerhaften Theme-URL-Transient mehr. Der primäre Taxonomiehelper prüft Taxonomie, Providerfehler, die Zugehörigkeit einer primären Auswahl und den nativen Fallback. `make_slug()` verlangt eine explizite Eingabe statt eines zufälligen alten `$data`-Globals.

## Zugriff und Routing

`skye_content_access_state()` vereinigt native Passwortanforderungen und die optionale Members-API. Eigene Feld-, Excerpt-, Thumbnail- und Autorenmodelle prüfen diesen Vertrag vor dem Lesen. Das Haupt-Rendering wählt bei Einschränkungen die vollständige native Passwort-/Provider-Ausgabe; der optionale ACF-Baukasten wird ebenfalls davor abgefangen. Native Inhaltsfilter bleiben aktiv. Der separate frühe Members-Preflight gehört zur Bootstrap-Integration; ein Passwortformular darf weiterhin nativ angezeigt werden.

`Templates` routet Suche, 404, Posts-Startseite, Archive, Seiten und einzelne Beitragstypen zu existierenden Ansichten. Eine Posts-Startseite bleibt ein Archiv. Term-/Benutzer-/Datumsarchive interpretieren ihre Objekt-ID nicht als ID eines zufällig gleich nummerierten Posts; ein explizites Post-Type-Archiv erhält seinen Typ aus dem nativen Query-Vertrag. Zugewiesene sichere Vorlagen und typbezogene Views bleiben übersteuerbar. Zugewiesene Vorlagen müssen in der nativen, für den konkreten Beitrag gefilterten `WP_Theme::get_page_templates()`-Liste stehen; dies gilt auch für eigene singuläre Post Types. Manipulierte Slugs können dadurch keine Bootstrap-/Bibliotheksdateien oder die Dokumenthülle rekursiv laden. Es gibt keine automatische `ct-archive`-Datenbankabfrage und keinen Austausch des globalen Hauptposts. Der alte `get_archive_site()`-Einstieg akzeptiert nur eine ausdrücklich über `skye_archive_page` gelieferte veröffentlichte Postinstanz und verändert den normalen Router nicht.

404 rendert Suche und Orientierung ohne URI-Fragmentabfragen. Der alte `NotFound::get_similar_posts()`-Einstieg bleibt für explizite Projekte erhalten: eine begrenzte Suchzeichenkette, ein nativer Query mit höchstens fünf veröffentlichten Posts/Seiten, anschliessende Passwort-/Members-Prüfung. Die API wird vom Standard-404 nicht automatisch aufgerufen.

`templates/redirectchild.php` erkennt die tatsächlich auswählbare Vorlage. Eine native auf 20 direkte veröffentlichte Kinder begrenzte Abfrage sortiert nach `menu_order`, dann ID; gesperrte/passwortgeschützte Kinder werden übersprungen. Der erste erlaubte Kandidat wird mit `wp_safe_redirect()` temporär weitergeleitet, danach endet der Request. Ohne Kandidaten bleibt normaler Seiteninhalt sichtbar. Keine Regex-Auswertung gerenderten Menü-HTMLs und keine dauerhafte 301-Bindung an einen veränderlichen Seitenbaum.

Der historische `rights()`-Helper bleibt ein ausdrücklich dokumentierter Login-/Rollengate für frühe Legacy-Aufrufer. Neue Schreib-/Objektrechte müssen `current_user_can()` mit dem konkreten Objekt prüfen. Eine verweigerte Antwort liefert 404/No-Cache und endet; der aufrufende Code darf vorher noch keine Ausgabe senden.

## Native Bootstrap-6-Navigation

Der unverändert mitgelieferte alte Fremd-Walker konnte seine unterstützte Bootstrap-Version nicht zurückgeben. Skye 3 verwendet deshalb den nativen WordPress-Walker und öffentliche, nur für `skye_bootstrap` markierte Menüs aktive Filter. Core behält aktuelle Seite, Vorfahren, `aria-current`, Sprache und Plugin-Berechtigungen pro Request. `MenuCache` bleibt als dünner alter Konstruktor-Einstieg erhalten; er speichert kein fertiges Menü-HTML. `?ctmenu=delete` hat keinen Effekt, der alte Purge-Callback erzeugt keine weitere Cachemutation.

Die auf ausdrücklichen Benutzerwunsch verwendete Version `6.0.0-alpha.1` nutzt `md:navbar-expand`, ein natives `dialog.drawer`, `data-bs-toggle="drawer"` und `data-bs-toggle="menu"` mit `.menu`/`.menu-item`. Ein separates Untermenü-Button lässt den übergeordneten Seitenlink navigierbar. Ein No-JavaScript-Pfad enthält vollständige normale Menülinks. Navigation ist auf zwei Ebenen beschränkt, entsprechend dem expliziten Navbar-/Untermenüvertrag. Die optische/Browserabnahme dieser neuen Alpha-Komponenten wird getrennt von PHP-Prüfungen dokumentiert.

## Herkunft und tatsächliche Prüfungen

Historische `@since`-Angaben wurden mit `git log --all --reverse -G` über die alten umbenannten Dateipfade ermittelt, insbesondere `3fd4db0` (Komponenten/Register, 2018), `b6516ee` (Bilder/Menü, 2021), `71ed84f` (Templates/NotFound/Excerpt, 2021), `6b7c27a` (Rechte, 2020), `4918f86` (Telefon/Debug, 2017) und `4e4f2d8` (Slug, 2025). Neue Verträge verwenden `@since 3.0.0`.

`php tests/core-test.php` und derselbe Lauf mit `--without-acf` führen die tatsächlichen Funktionen und Templates mit ausdrücklich isolierten nativen API-Gegenstellen aus. Die Tests prüfen Rohwerte/Quellgrenzen, aktive und fehlende ACF-API, Fehler von Term-/Yoast-APIs, Kindtheme-/Loader-/Puffergrenzen, Rollen, Menü-Cachefreiheit, Bootstrap-6-Markup, Routing, geschützte Modelle, vollständige leere Standardansichten und echte Exit-Pfade in PHP-Unterprozessen. Sie sind keine komplette WordPress-Installation, kein HTTP-/Browser- oder echter Gerätetest. Der gemeinsame Architektur-/Stilcheck ergänzt diese Tests, ersetzt sie aber nicht.

Konsultierte Primärquellen, 03.11.2026: [WordPress Metadaten](https://developer.wordpress.org/reference/functions/get_metadata/), [native Menüs](https://developer.wordpress.org/reference/functions/wp_nav_menu/), [Komponenten/Kindthemes](https://developer.wordpress.org/reference/functions/get_template_part/), [zulässige native Vorlagen](https://developer.wordpress.org/reference/classes/wp_theme/get_page_templates/), [sichere Weiterleitung](https://developer.wordpress.org/reference/functions/wp_safe_redirect/), [native Bild-URL](https://developer.wordpress.org/reference/functions/wp_get_attachment_image_url/), [Bootstrap-6-Navbar](https://getbootstrap.com/docs/6.0/components/navbar/), [Bootstrap-6-Menü](https://getbootstrap.com/docs/6.0/components/menu/).
