# Native mobile Navigation ab Skye 3.2

Der Parentstandard verwendet ein vollflächiges natives `<dialog>` mit grosser,
linksbündiger Navigation, einem sichtbaren Menü-/Schliessen-Text und ruhiger
Bewegung. Vorbild für die Anordnung war die am 04.11.2026 gelesene
Volksgarage-Navigation; deren Markenfarben, jQuery und Bootstrap-Collapse-Code
werden nicht übernommen. Skye behält Bootstrap 6 Alpha und dessen bisherige
Drawer-/Menu-APIs unverändert. Der neue Controller verwendet die native
Dialog-API und lädt keine zweite Bootstrap-/Floating-UI-Instanz.

## Aktivierung und kleine Assets

Im Parent ist der neue Standard automatisch aktiv. Bestehende Child-Themes
behalten ihren eigenen Header und entscheiden sich ausdrücklich für die neue
Komponente:

```php
add_filter( 'skye_mobile_navigation_enabled', '__return_true' );
```

Der öffentliche Filter erlaubt auch ein Parent-Opt-out. Enqueue erfolgt nur bei
aktivierter Integration und einem tatsächlich zugewiesenen Menü an der
konfigurierten Location. Es gibt zwei unabhängige native Handles:

| Handle | Quelle | Verhalten |
| --- | --- | --- |
| `skye/mobile-navigation` | `assets/scripts/mobile-navigation.js` | Keine Abhängigkeiten; `defer`, Footer. |
| `skye/mobile-navigation-style` | `assets/styles/mobile-navigation.scss` | Vollständige kleine Komponenten-CSS. |

Beide URLs stammen ausschliesslich aus dem **Parentmanifest**. Ein eigenes
Childmanifest kann sie nicht in fehlende Childdateien umlenken.
`MobileNavigation::asset_uri( 'mobile-navigation.js' )` beziehungsweise `.css`
liefert genau diese beiden geprüften URLs. Fehlende Builddateien verhindern
beide Enqueues; es werden keine ungeprüften Source-URLs ausgegeben. Native
WordPress-Assetfilter, einschliesslich der konfigurierten CDN-Zuordnung, bleiben
wirksam. Strenge projektspezifische Assetpläne müssen diese zwei neuen eigenen
Handles ausdrücklich anhand der tatsächlichen Parentversion/-URLs zulassen.
Andernfalls gilt deren vollständiger nativer Fallback. Die bisherige
`skye/js`-Kompatibilitätsdatei wird nicht pauschal entfernt oder umdefiniert.

## Explizites Modell und native Menüidentitäten

```php
use Rocketsky\Skye\Packages\MobileNavigation;

$ui = MobileNavigation::model();
```

`skye_mobile_navigation_options` filtert die Standardwerte. Ein optionales Array
an `model()` überschreibt sie pro Instanz. Verfügbare Schlüssel:

- `id`: eindeutige Dialog-ID, höchstens 48 ASCII-Zeichen; beginnt mit einem
  Buchstaben, danach Buchstaben/Ziffern/Bindestrich/Unterstrich.
- `breakpoint`: genau 768, 992 oder 1200 CSS-Pixel; Standard 1200. CSS und
  `matchMedia` verwenden dieselbe Grenze, ohne getrennte Resize-Pollingschleife.
- `location`: native registrierte Location; Standard `primary_navigation`.
- `label`, `title`, `open_text`, `close_text`, `open_label`, `close_label`:
  übersetzbare, begrenzte reine UI-Texte. Der sichtbare Text muss im zugänglichen
  Buttonnamen enthalten bleiben, etwa «Menü» / «Menü öffnen».

Desktop, Dialog und native Detailsfallback sind **drei getrennte native
Serialisierungen**. Für jede wird ein anderer eigener Prefix gesetzt:

```php
$arguments = array(
	'theme_location' => $ui['location'],
	'container' => false,
	'echo' => false,
	'fallback_cb' => false,
	'depth' => 0,
	'skye_bootstrap' => false,
	'menu_class' => 'project-desktop-menu',
);
$desktop = wp_nav_menu( MobileNavigation::menu_args( $arguments, $ui['id'] . '-desktop' ) );
$arguments['menu_class'] = 'skye-navigation-menu';
$mobile = wp_nav_menu( MobileNavigation::menu_args( $arguments, $ui['id'] . '-mobile' ) );
$arguments['menu_class'] = 'skye-menu-fallback';
$fallback = wp_nav_menu( MobileNavigation::menu_args( $arguments, $ui['id'] . '-fallback' ) );
```

`menu_args()` erhält sämtliche Native-/Providerargumente und ergänzt `menu_id`
sowie den ausdrücklichen Marker `skye_navigation_id_prefix`. Der öffentliche
`nav_menu_item_id`-Filter stellt ausschliesslich für markierte Ausgaben eindeutige
`<prefix>-item-<ID>`-Werte her. Das geschieht nach Cores Wiederholungsfilter, der
sonst bei derselben gespeicherten Menüitem-ID spätere IDs entfernt. Unmarkierte
Menüs behalten ihren früheren Providerwert oder die native leere Wiederholungs-ID.
Es werden keine fertigen HTML-Strings mit Regex repariert und keine Core-Hooks
entfernt. Provider mit eigenen zusätzlichen IDs im Item-HTML müssen auch diese
bereits bei ihrer nativen Ausgabe eindeutig halten.

`mobile` und `fallback` benötigen alle gewünschten Hierarchieebenen (`depth=0`)
und keine ausschliesslich JavaScript-bedienbaren Submenu-Toggles. Der Parent
bereitet die drei Pfade in `skye_navigation_model()` vor und erhält dessen
bisherige `html`-/`fallback`-Schlüssel. Child-Themes übergeben ihr Modell explizit:

```php
get_component( 'navigation/mobile', false, array(
	'mobile_navigation' => $ui,
	'navigation' => array(
		'html' => is_string( $desktop ) ? $desktop : '',
		'mobile' => is_string( $mobile ) ? $mobile : '',
		'fallback' => is_string( $fallback ) ? $fallback : '',
	),
) );
```

Ohne vollständiges Mobile-/Fallback-HTML bleibt das vollständige statische
Desktop-HTML in allen Breiten sichtbar; eine unvollständige Providerantwort
darf die Navigation nicht verschwinden lassen. Menü-HTML ist die Ausgabe des
nativen vertrauenswürdigen Renderers, kein ungeprüftes redaktionelles Roh-HTML.

## Stabile Marke und Ereignisse

Ein bestehender eigener Logo-Link oder Wrapper im selben `<header>` kann
`data-skye-navigation-brand-for="<Dialog-ID>"` tragen. Beim Öffnen wird exakt
dieser DOM-Knoten in den Dialog-Kopfslot verschoben und beim Schliessen an seinem
ursprünglichen Platz wieder eingesetzt. Es wird kein zweites Logo serialisiert;
SVG-IDs, laufende Listener und die Identität des Links bleiben erhalten. Das
Markenmodell und etwaige Logoanimationen bleiben im Projekt-Theme. Skye startet
keine Markenanimation und benötigt kein verborgenes Duplikat.

Vom Komponentenroot bubblen zwei `CustomEvent`s:

- `skye:navigation:opened` nach nativer Modalaktivierung und anfänglicher
  Fokusübergabe, `detail = { id, reason: 'toggle' }`.
- `skye:navigation:closed` nach Schliessen, Markenrückgabe und Fokusbereinigung,
  `detail = { id, reason }`. Gründe: `close`, `escape`, `fragment`, `link` oder
  `breakpoint`. Es werden keine Menüdatensätze oder privaten Daten übertragen.

Damit kann das Theme seine eigene Bewegungspolitik anpassen. Ereignisse ändern
keine Navigation und dürfen keine erforderlichen Links unterdrücken.

## Darstellung und Zugänglichkeit

Die CSS-Variablen werden auf `.skye-mobile-navigation` überschrieben:
`--skye-navigation-bg`, `--skye-navigation-color`, `--skye-navigation-accent`,
`--skye-navigation-font`, `--skye-navigation-link-size`,
`--skye-navigation-link-weight`, `--skye-navigation-padding` und
`--skye-navigation-content-width`. Farben und Schrift sind somit projektbezogen;
das Basistheme enthält keine fremde Markenpalette. Safe-Area-Abstände,
scrollbarer Inhalt, mindestens 44-Pixel-Steuerungen und umbrochene lange Links
gehören zum Standard. Die kurze Komponentenanimation endet bei `reduce` sofort.

`showModal()` liefert native Modalität und Hintergrund-Inertheit. Der Controller
setzt `aria-expanded`, fokussiert den ersten wirklichen Menülink und enthält Tab
und Shift+Tab im Dialog. Escape und die sichtbare Schliessen-Schaltfläche wirken
auch während der Öffnungsanimation. Alte Animation-/Close-Callbacks können einen
neu geöffneten Dialog nicht nachträglich schliessen. Beim Wechsel zum Desktop
werden Dialog, Scrollgrenze und Marke bereinigt; Fokus geht auf ein sichtbares
passendes Desktopziel. Es wird kein `role=menu` für normale Website-Links gesetzt.

Für reale Same-Document-Fragmente wird der Dialog zuerst geschlossen und danach
das Ziel fokussiert und angesteuert. Nur ein dafür nötiger temporärer negativer
Tabindex wird nach Fokusverlust entfernt. Modifierklicks, Downloads, neue Fenster
und fremde Dokumente behalten ihre native Navigation. Reduzierte Bewegung gilt
auch für die Fragmentansteuerung. Scrolllocking verwendet nur die Laufzeit-CSS-
Bedingung eines offenen eigenen Dialogs; vorhandene Inline-Scrollstyles werden
nicht überschrieben und nach dem Schliessen wieder wirksam.

Ohne JavaScript steht auf mobilen **und Desktopbreiten** ein echtes
`<details><summary>` mit sämtlichen Fallbacklinks bereit. Bei fehlgeschlagenem
Komponentenscript bleibt zumindest die mobile native Detailsfunktion erhalten;
Desktoplinks bleiben separat nativ. Fehlende `showModal()`-Unterstützung aktiviert
keinen defekten JavaScriptbutton. Ein bereits ausdrücklich geöffnetes natives Detailsmenü bleibt auch bei spät
geladenem JavaScript geöffnet. Der unverstärkte Summary- und verstärkte
Buttonzustand teilen dieselbe kompakte Geometrie.

## Tatsächliche Prüfung und Grenzen

Am 04.11.2026 lokal: Node 14.21.3-Build/Watch- und Releaseharness, alle 13
PHP-Regressionsprozesse, nativer WordPress-7.1.4-Menü-/Assettest (53 Assertions)
und vier Controller-VM-Szenarien mit tatsächlichem Quellprogramm. Der moderne
Node-24-Testharness bleibt getrennt vom Produktionsbuild. VM-DOM-Fixtures
belegen Zustands-/Racegrenzen und sind keine Browser- oder Gerätetests.

Die bestehende CI prüft Chromium, Firefox und WebKit mit der echten lokalen
WordPressinstallation: 320/390/768/1440 Pixel, Native-Modalität, Fokus-/Tabgrenzen,
Escape, Markenidentität, Breakpointwechsel, Fragmentlinks, global eindeutige
IDs, No-JavaScript auf Desktop/Mobil, Scriptfehler, reduzierte Bewegung und
Touch-/Querformatemulation. Deren Ergebnis zählt erst nach tatsächlicher
Ausführung. Im Subagent war kein CUA-Browser verfügbar; eine lokale visuelle
CUA-Abnahme wird dadurch nicht behauptet. Emulation/WebKit sind keine physischen
iOS-/Androidgeräte und kein echter Safari-/Edge-Nachweis.

Offizielle Primärquellen, geprüft am 04.11.2026:
[Bootstrap-6-Drawer](https://getbootstrap.com/docs/6.0/components/drawer/),
[WAI-ARIA-Modalvertrag](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/),
[nativer Menu-Serializer](https://developer.wordpress.org/reference/functions/wp_nav_menu/),
[öffentlicher Item-ID-Hook](https://developer.wordpress.org/reference/hooks/nav_menu_item_id/),
[bedarfsabhängige Menüerkennung](https://developer.wordpress.org/reference/functions/has_nav_menu/)
und [nativer Script-Enqueue](https://developer.wordpress.org/reference/functions/wp_enqueue_script/).
