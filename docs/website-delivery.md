# Native Website-Auslieferung ab Skye 3.1

Die neuen Funktionen sind additiv. Ohne Projektfilter bleiben Script- und
Stylesheet-Registrierungen vollständig nativ. CDN-Auslieferung ist standardmässig
ausgeschaltet. Es gibt keinen HTML-Output-Buffer, keine HTML-Ersetzung, keinen
Minifier und keine Änderung an Core-, Plugin- oder Quell-Assets.

## Geprüfte statische Scripts

`skye_static_script_bundle` erhält zunächst `false`. Ein Projekt kann einen
bewusst geprüften, geschlossenen klassischen Scriptgraphen beschreiben:

```php
array(
    'native_deferred_initialization' => true,
    'carrier' => 'last-original-handle',
    'sources' => array(
        'first-original-handle' => array(
            'url' => $canonical_url,
            'version' => $exact_registered_version,
            'dependencies' => $exact_registered_dependencies,
        ),
        // Weitere geprüfte Quellen, einschliesslich des letzten Originalhandles.
    ),
);
```

Dieser Filter ist ein ausdrücklicher Ausführungsvertrag. Das Projekt muss die
vollständige native Abhängigkeitsmenge, verwendeten Provider-Versionen und
Inline-Initialisierungen kennen. Es muss beweisen, dass diese auf DOM-/Provider-
Bereitschaft warten und die gemeinsame Ausführung auf dem letzten ursprünglichen
Handle vertragen. Sofortige Inline-Verbraucher, zusätzliche fremde Consumer,
Module, individuelle Script-Tag-Integrationen oder neue Provider-Funktionen
benötigen eine neue Prüfung und ansonsten den nativen Fallback.

Erst nach der nativen Footer-Abhängigkeitsauflösung werden die tatsächlichen
Quellen in deren tatsächlicher Reihenfolge zusammengefügt. Sie werden weder
minifiziert noch umgeschrieben; Lizenzkommentare bleiben enthalten. Frühere
Quellhandles werden native Aliase, der letzte ursprüngliche Handle erhält die
Hashdatei und behält seine native `defer`-Absicht. Core entscheidet weiter über
die tatsächlich zulässige Ladestrategie. Native `before`, `after`, lokalisierte
Daten, Nonces und Übersetzungen bleiben an ihren ursprünglichen Handles und
werden weder gelesen noch in die Datei übernommen. Ein Alias erhält keine
Ladestrategie, da Core Strategien für quelllose Aliase ausdrücklich ablehnt.

Unbekannte Quellen, Versionen, Abhängigkeiten, Metadaten, vorzeitig gedruckte
Handles, fehlende Footer-Gruppe, ein anderer Träger oder nicht verfügbare
Dateien/Cacheablage erhalten alle nativen Einzelquellen. Nur anonyme gewöhnliche
GETs qualifizieren sich; POST, HEAD, Login, Admin, AJAX, REST, Cron, CLI,
Preview, Feeds, Fehler- und geschützte Ansichten bleiben ausgeschlossen.

## Vollständige Stylesheets einbetten

`skye_inline_stylesheets` erhält ein leeres Array. Das optionale Ergebnis ist
nach nativen Handles indiziert; jeder Wert enthält dieselben Felder `url`,
`version` und `dependencies`. Das komplette bekannte Stylesheet erscheint an
seiner bisherigen nativen Cascade-Position mit unveränderter Media-Bedingung.
Core druckt vorhandene eigene Inline-Regeln weiterhin separat dahinter.

Es findet weder eine Auswahl von Selektoren noch eine Critical-CSS-Schätzung
statt. Gewöhnliche `url()`-Werte werden gegen die kanonische Quelldatei
aufgelöst; Daten- und Fragment-URLs bleiben erhalten. Imports, escaped Funktionen
oder URLs, `image()`/`image-set()`, fehlerhafte Kommentare/Strings und
HTML-Schliesstokens sind nicht unterstützt und behalten den ursprünglichen
Link. Fremde Linkattribute, SRI, alternative/bedingte/RTL-Varianten und bereits
veränderte native Tags behalten ebenfalls den Link. Die vorhandene
CriticalStyles-Auslieferung hat Vorrang: ein von ihr bereits veränderter Link
entspricht diesem strengeren Vertrag nicht.

Die Quellen werden ausschliesslich über native lokale Theme-, Plugin-, Includes-
und Content-Wurzeln aufgelöst. Es gibt keinen externen Download. Einzelquellen
sind auf 1 MiB begrenzt, vollständiges CSS auf 512 KiB, JS-Bundles auf 2 MiB.
Unbekannte Symlink-Ausbrüche und Traversalpfade werden abgelehnt; explizite native
Child-/Plugin-Wurzeln dürfen selbst Symlinks sein.

## Cache und Betrieb

Eigene Ableitungen liegen unter dem nativen Upload-Verzeichnis in
`skye-static-assets/`. Sie bestehen nur aus öffentlich ausgelieferten statischen
Bytes und nicht geheimen JSON-Hashmanifesten. Quelldateien werden nie verändert.
Dateien werden über atomare temporäre Dateien veröffentlicht, bei Treffer vor
Verwendung gegen ihren Hash geprüft und bei Beschädigung nur im eigenen Cache
neu aufgebaut. Der CSS-Eingangsschlüssel bindet Quellhash, kanonische Basis und
CDN-Konfiguration; nachfolgende Besucher benötigen keinen erneuten CSS-Parse.

Alte Hashdateien werden nicht automatisch gelöscht, weil normale Seiten- und
Browsercaches noch darauf zeigen können. Eine betriebliche Bereinigung darf
nur diesen eigenen Ordner und erst nach Ablauf sämtlicher referenzierender
Caches betreffen. Ein nicht beschreibbarer Cache ergibt native Auslieferung,
keine kaputte Quellreferenz. Statische Hashdateien eignen sich für lange
Browser-/CDN-Aufbewahrung; Serverheader bleiben Hostingkonfiguration.

## Font-Hints

`skye_font_preload_position` erhält ausserhalb des bisherigen geprüften
Critical-CSS-Pfads `head`. Ein Projekt kann explizit `body` wählen, etwa bei
geprüftem vollständig eingebettetem CSS. Die bestehende Implementierung bewegt
nur exakt identische eigene Build-Fonthints und erhält Datei, Typ und CORS.
Eine zusätzliche fremde Eigenschaft oder Same-URL-Kollision behält den
ursprünglichen Head-Hint. Speicherung und Ausgabe sind an die exakte native
Style-Registrierung gebunden; Ausgabe erfolgt einmal über `wp_body_open` und
die vorhandene Font-Komponente. Das Projekt muss diesen nativen Hook aufrufen.

## CDN standardmässig konfigurierbar

Unter **Design → Skye Auslieferung** verwaltet ein Administrator die native
Option `skye_delivery`: `cdn_enabled`, `cdn_url` und eine begrenzte Liste von
`prefixes`. Core Settings API übernimmt Nonce, Berechtigungen und Speicherung;
die Option ist nicht über REST veröffentlicht. Alternativ erhält
`skye_cdn_configuration` die gelesene Konfiguration. Auch Filterwerte durchlaufen
die gleiche Validierung.

HTTPS, ein gültiger öffentlicher Host und explizit erlaubte native statische
Pfade sind erforderlich. Die Voreinstellung erlaubt native Theme-/Plugin-/
Upload-Pfade und Includes-JS/CSS. Pfade können eingeschränkt werden. Zugangsdaten,
Queries/Fragmente in der Basis, private IP-Adressen, ausführbare Dateien,
Traversal und andere Pfade sind nicht zugelassen. Die Einrichtung lädt oder
kontaktiert das CDN nicht. Betrieb, TLS, passende MIME-Typen und Font-CORS müssen
vor Aktivierung bereitstehen.

Nur gleichursprüngliche öffentliche statische URLs werden über native
Script-/Style-, Attachment-, Bildgrössen-, Srcset- und Preload-Filter gemappt.
Build-Assets, Fonts und eigene Bundle-URLs nutzen dieselbe Abbildung. Ein
gewöhnlicher `ver`-Query bleibt erhalten; signierte, Nonce- oder andere unbekannte
Queries bleiben am Ursprung. Admin, angemeldete Besucher, Form-POSTs,
Formaktionen, öffentliche Seiten-URLs, Content, GUIDs und serverseitige
HTTP-Aufrufe werden nicht verändert. Keine globale HTML-Ersetzung läuft.
`skye_canonical_asset_url` reversiert nur die genau konfigurierte eigene
Abbildung für die lokale statische Dateiauflösung.

## Neue gebündelte Themes bei Core-Updates verhindern

Vor dem Laden von WordPress gehört folgende native Setupkonstante in
`wp-config.php`:

```php
define( 'CORE_UPGRADE_SKIP_NEW_BUNDLED', true );
```

Der Name hat **kein `WP_`-Präfix**. Core prüft diese Konstante sowohl bei der
Paketwahl als auch vor dem Kopieren neuer gebündelter Inhalte. Damit werden
neue gebündelte Standardthemes und gegebenenfalls gebündelte Plugins bei
Core-Updates übersprungen. Bereits vorhandene Themes werden weder gelöscht
noch von ihren normalen eigenen Updates ausgeschlossen. Eine bewusste
Installation durch Administratoren bleibt möglich.

Ein Theme kann diese vor dem Updatebootstrap benötigte Konfiguration nicht
zuverlässig selbst setzen. Skye zeigt deshalb ihren tatsächlichen Status in
der Einstellungsseite, bearbeitet aber weder wp-config.php noch Coredateien.

## Primärquellen und tatsächliche Prüfung

Am 04.11.2026 gelesen: [native Abhängigkeitsauflösung](https://developer.wordpress.org/reference/hooks/print_scripts_array/),
[Core-Scriptausgabe und Aliase](https://developer.wordpress.org/reference/classes/wp_scripts/do_item/),
[Script-Metadaten](https://developer.wordpress.org/reference/functions/wp_script_add_data/),
[native Style-Tags](https://developer.wordpress.org/reference/hooks/style_loader_tag/),
[Preload-Records](https://developer.wordpress.org/reference/hooks/wp_preload_resources/),
[Settings API](https://developer.wordpress.org/reference/functions/register_setting/),
[Attachment-URLs](https://developer.wordpress.org/reference/hooks/wp_get_attachment_url/),
[Srcset-Records](https://developer.wordpress.org/reference/hooks/wp_calculate_image_srcset/)
und [Core-Upgrader](https://developer.wordpress.org/reference/classes/core_upgrader/upgrade/).
Die zwei konkreten Konstantengrenzen stehen in den unveränderten offiziellen
Dateien `wp-admin/includes/class-core-upgrader.php` und
`wp-admin/includes/update-core.php` von WordPress 7.1.4.

`tests/performance-static-delivery.php` prüft 52 positive und negative Verträge
mit den echten nativen WordPress-Parsern und Abhängigkeitsregistrierungen.
PHP 8.3.36 mit WordPress 6.8.12 und 7.1.4: bestanden. Die offizielle 6.8.12-Quelle
wurde gegen alle 3230 offiziellen WordPress.org-Dateichecksummen geprüft.
Die komplette bestehende PHP-Regressionsliste einschliesslich des neuen Falls
lief lokal mit 6.8.12 und 7.1.4 grün (je 11 Prozesse). Das ist kein ausgeführter GitHub-CI-Lauf
und kein Browser-/Geräte- oder Lighthouse-Abnahmenachweis.
