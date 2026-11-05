# Explizite responsive WebP-Vorladung

Seit Skye 3.3.0 kann ein Projekt das tatsächlich früh sichtbare Bild über dieselbe native `Images`-Pipeline für Bild und Vorladung auswählen. Die API entscheidet weder über Seiten, Layoutnamen noch Bild-IDs. Sie erzeugt keine Varianten und erhöht kein Bildbudget.

## Ein gemeinsamer Vertrag für Bild und Hinweis

`Images::webp_preload( $id, $size, $attributes )` liefert einen Descriptor für `wp_preload_resources` oder `false`. `Images::append_webp_preload( $resources, $id, $size, $attributes )` ergänzt ihn konservativ in eine vorhandene Liste. Dieselbe positive Integer-ID, derselbe native Grössenname beziehungsweise dieselbe Dimensionsanfrage und dasselbe Attributarray müssen später an `wp_get_attachment_image()` gehen.

Das Projekt muss die tatsächliche Slotbreite ausdrücklich als `sizes` liefern und `loading` auf `eager` setzen. `fetchpriority => high` ist für das ausgewählte LCP-Bild möglich; die API verändert diesen Wert nicht. Lazy-/Auto-Sizes, eigene `src`-/`srcset`-Overrides, unbekannte Attribute und Bildfilter behalten ihre gewöhnliche Bildausgabe ohne zusätzlichen Hinweis. Die optionalen CORS-Werte fehlen, sind leer, `anonymous` oder `use-credentials` genau wie am Bild. Die API ergänzt kein CORS-Attribut von sich aus.

Beispiel für bereits aus dem aktiven Seitenmodell ermittelte Werte:

```php
use Rocketsky\Skye\Packages\Images;

$image_attributes = array(
	'sizes' => $actual_layout_slot,
	'loading' => 'eager',
	'fetchpriority' => 'high',
	'alt' => $image_description,
);

add_filter( 'wp_preload_resources', static function ( $resources ) use ( $image_id, $image_size, $image_attributes ) {
	return Images::append_webp_preload( $resources, $image_id, $image_size, $image_attributes );
}, 100 );

$image_html = wp_get_attachment_image( $image_id, $image_size, false, $image_attributes );
```

Die Auswahl muss vor der nativen Head-Ausgabe feststehen. `100` liegt nach Skyes Ressourcenproduzenten und dem nativen CDN-Mapper (Priorität 90); eigene spätere Produzenten dürfen keinen konkurrierenden Hinweis für dasselbe Bild hinzufügen. Die API installiert selbst keine Hooks und verändert keine Schriftvorladungen. Der vorhandene `skye_preload_resources`-Filter bleibt verfügbar; zum Abgleich mit allen bereits vorhandenen Core-/Skye-Hinweisen ist die gezeigte Ergänzung am nativen Hook zweckmässig.

## Formate, Duplikate und Fallback

Der Descriptor verwendet `Images::webp_source()` und damit genau die geprüften WebP-Kandidaten der vorhandenen Picture-Komponente. `imagesizes` bleibt der explizite Bildwert. Es gibt bewusst kein `href`: Browser ohne responsive Vorladung sollen keinen zusätzlichen JPEG-/PNG-Fallback laden. Native Originaldateien, Bildmetadaten und das ursprüngliche IMG bleiben unverändert.

Die Ergänzungsfunktion vergleicht vorhandene Bild-`href`s und responsive Kandidatenlisten mit beiden nativen Formaten. Bereits eine überschnittene WebP- oder Original-JPEG-/PNG-Teilmenge verhindert einen weiteren Hinweis, auch bei einer vorhandenen `imagesrcset` ohne `href`. Vorhandene Attribute, CORS und Media-Bedingungen werden weder korrigiert noch entfernt. Ein unbekannter beziehungsweise relativer Kandidat lässt die bestehende Liste vollständig unverändert; die API errät keine Auflösung relativer URLs. Höchstens 128 bestehende Ressourcen, 64 Kandidaten je Liste und 16 KiB je Srcset werden untersucht.

Fehlendes WebP, ausgeschaltete Medienpipeline, fehlende/überschriebene Picture-Komponente, Administratorausgabe und unbekannte Bildausgabe-/Sizing-/Sourcefilter liefern keinen Hinweis. Der unveränderte native Skye-CDN-Mapper wird erkannt; seine Bild- und Ressourcenfilter behalten ihre ursprüngliche URL-Zuordnung. Andere Filter werden nicht entfernt oder umgangen. Die gewöhnliche Bildausgabe bleibt für alle Fälle verantwortlich.

## Prüfungen und Grenzen

Die Ergänzungen in `tests/media-pipeline.php` verwenden echtes WordPress-Quellmaterial, GD/WebP, native Metadaten und den tatsächlichen Picture-Komponentenloader. Der Core-Preload-Renderer muss genau dieselben WebP-Kandidaten/Slotwerte ohne `href` ausgeben. Zusätzlich werden CORS, bestehende Schrift-Hinweise, native responsive Original-Teilmengen, unbekannte Attribute/Filter und fehlende Alternativen geprüft. Die Suite startet keine Website und kontaktiert keinen Provider. SQLite übersetzt Test-SQL; das ist kein MySQL-/InnoDB-Mehrprozessnachweis.

Am 05.11.2026 bestanden auf CentOS 7.9 mit PHP 8.3.36 und GD 2.3.3 beide vollständigen Medienläufe gegen WordPress 7.1.4 und 6.8.12 jeweils **590/590** Prüfungen; davon **50** für responsive Vorladungen. Der unveränderte SQL-Testadapter benötigte eine private aktuelle SQLite-Bibliothek (3.54.1) ausschliesslich im jeweiligen CLI-Prozess; die native Hostingbibliothek 3.7.17 unterstützt dessen bestehende UPSERT-Syntax nicht. Es gab keine System-/FPM- oder Kundenänderung. [Maschinenlesbarer Nachweis](evidence/responsive-image-preloads-native-3.3.0.json).

Ein Vorladehinweis allein belegt weder die im Browser ausgewählte Kandidatendatei noch einen bestimmten LCP-/Speed-Index-Wert. Vor dem Projekteinsatz bleiben die echte Netzwerkprüfung auf Doppelabrufe, die passende Slotbreite und Browser-/Geräteabnahme erforderlich. Diese Quellenänderung aktualisiert keine bestehende Kundeninstallation.

Primärquellen, gelesen am 05.11.2026: [Core-Vorladungen](https://developer.wordpress.org/reference/functions/wp_preload_resources/), [native Bildattribute](https://developer.wordpress.org/reference/functions/wp_get_attachment_image/) und [native responsive Kandidaten](https://developer.wordpress.org/reference/functions/wp_get_attachment_image_srcset/). Core serialisiert responsive Bildhinweise auch ohne `href`; der Quellcode enthält diese Ausnahme trotz des allgemeinen Required-Vermerks im Parameterkommentar.
