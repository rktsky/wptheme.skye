# Externe Links ab Skye 3.2.2

Externe HTTP(S)-Links erhalten `target="_blank"` sowie die Relationen `noopener`
und `noreferrer`. Vorhandene Relationen wie `nofollow`, `sponsored` oder `ugc`
bleiben einschliesslich ihrer Schreibweise erhalten. Auch `href`, CSS-Klassen,
Downloads, ARIA- und andere Attribute werden nicht verändert.

Die gemeinsame Implementierung verarbeitet die nativen Fragmente `the_content`
und `acf_the_content` mit `WP_HTML_Tag_Processor`. Die separate ACF-Pipeline ist
nötig, weil WYSIWYG-Felder nicht durch `the_content` laufen. ACF ist weiterhin
optional. Native Menüs verwenden `nav_menu_link_attributes`; dadurch gilt dieselbe
Regel für Desktop, mobile Navigation und ihren Detailsfallback.

Es gibt keinen vollständigen Seitenfilter, keine Änderung gespeicherter Inhalte,
keinen externen HTTP-Aufruf und keine globale JavaScript-Umschreibung.

## Explizite Theme-Links

Eigene Komponenten bereiten ihre rohen Attribute über denselben Helper vor:

```php
$attributes = skye_external_link_attributes( array(
	'href' => $url,
	'class' => 'button',
	'rel' => 'sponsored',
) );
```

Der Helper liefert ein Attributarray und kein HTML. Die vorhandene Komponente
übernimmt weiterhin das kontextgerechte Escaping. Nur bei einem erkannten externen
Webziel werden `target` und `rel` ergänzt beziehungsweise angepasst. Wiederholtes
Anwenden verändert das Ergebnis nicht weiter.

## URL-Grenze

`wp_parse_url()` zerlegt Ziel und `home_url()`. Verglichen werden Schema,
Host ohne Beachtung der ASCII-Grossschreibung und effektiver Port. Fehlende
Ports entsprechen 80 für HTTP beziehungsweise 443 für HTTPS. Ein anderer Port
oder ein anderes Schema ist ein anderer Origin. Hostpräfixe gelten nicht als
Übereinstimmung: `example.test.evil.test` gehört nicht zu `example.test`.

Protokollrelative Ziele wie `//extern.test/path` übernehmen zum Vergleich das
Home-Schema. Relative Links, Sprungmarken und andere Protokolle wie `mailto:`
oder `tel:` bleiben unverändert. Unvollständige oder mehrdeutige URLs, ungültige
Ports und ungültige geklammerte IPv6-Hosts behalten ihre bisherigen Attribute.

## Native Prüfung

```sh
SKYE_SITE_ROOT=/path/to/isolated/wordpress php tests/external-links-native.php
```

Am 04.11.2026 bestanden lokal 59 Aussagen mit WordPress 7.1.4 und PHP 8.3.36:
Helper, Origin-/Port-/Case-Grenzen, erhaltene Relationen und Attribute, tatsächliche
WordPress-/ACF-Contentfilter sowie Core-Menüs in Desktop-, Mobil- und Fallback-
Ausgabe. Es wurden keine HTTP-Anfragen ausgeführt. Das Programm entfernt sein
eigenes natives Testmenü und verändert keine gespeicherte Websitekonfiguration.
Dies ist kein Browser- oder Gerätenachweis und kein bereits erfolgter CI-Lauf.

## Primärquellen

- [WordPress HTML Tag Processor](https://developer.wordpress.org/reference/classes/wp_html_tag_processor/): vorhandene Tags finden und Attribute nativ ändern.
- [wp_parse_url](https://developer.wordpress.org/reference/functions/wp_parse_url/): native URL-Komponenten und protokollrelative Eingaben.
- [Native Menüattribute](https://developer.wordpress.org/reference/hooks/nav_menu_link_attributes/): bestehende Attribute innerhalb des Core-Walkers ergänzen.
- [ACF WYSIWYG](https://www.advancedcustomfields.com/resources/wysiwyg-editor/): eigener `acf_the_content`-Filter statt der normalen Content-Pipeline.
