# Öffentliche Ausgabe der aktiven Inhaltsquelle

`SourcePublicContext` ergänzt die [Quellenauswahl](editor-sources.md) für native
Auszüge, öffentliche REST-Antworten, Feeds und Yoast-Auszugvariablen. Die gespeicherten
Editor- und Builderentwürfe bleiben erhalten. Es gibt keinen globalen
`the_content`-Rewrite und keine nachträgliche Änderung von Core- oder Plugin-Dateien.

## Projektion und native Auszüge

Ein bekannter aktiver Builder verwendet dieselbe ausdrückliche PHP-Projektion
`skye_yoast_analysis_content` wie die [Yoast-Anbindung](yoast-analysis.md). Ohne
gültige Projektion wird für diese öffentlichen Exporte leerer Inhalt verwendet.
Passwortgeschützte, nicht öffentlich sichtbare oder durch die gemeinsame
Zugriffsprüfung gesperrte Builder liefern ebenfalls keine Projektion. Layouts
werden dafür nicht gerendert; der Projektadapter liefert semantisches HTML.
Zwischen benachbarten HTML-Blöcken Leerraum oder Zeilenumbrüche erhalten.
Native Textableitungen entfernen Tags, ergänzen aber nicht überall Absatzabstände;
ohne diese Trennung können Wörter zusammenrücken. Vor dem Merge den Callback
mit den tatsächlichen Layouts und Exportantworten prüfen.

Nach `wp_loaded` wird der originale `get_the_excerpt`-Callback
`wp_trim_excerpt` bei Priorität 10 durch einen delegierenden eigenen Callback
ersetzt. Voraussetzung sind genau eine originale Registrierung mit zwei
Argumenten und eine gerade nicht laufende Auszugfilterung. Alle bereits
registrierten Callbacks derselben Priorität werden über die öffentlichen
Remove-/Add-Hook-APIs in ihrer bisherigen Reihenfolge mit unveränderter
Argumentzahl wieder eingetragen. Fehlende oder veränderte Producer bleiben
unangetastet. Spätere Registrierungen folgen den normalen WordPress-Regeln;
wer den eigenen Producer entfernen will, muss dessen Callback entfernen.

Manuelle Auszüge, Editor-/unverwaltete Inhalte und bereits von früheren Filtern
gelieferte nicht leere Auszüge werden direkt an `wp_trim_excerpt` delegiert.
Nur aus Leerraum bestehende Core-Auszüge gelten wie im Original als automatisch.
Nur ein automatischer Builderauszug erhält einen `WP_Post`-Klon mit der aktiven
Projektion. Core führt seine Formatierung und die bestehenden Auszugfilter
einmal aus. Das Originalobjekt, Loopglobals und gespeicherte Inhalte werden
nicht ersetzt. Inaktiver Editorinhalt erreicht diesen Auszugformatter nicht.

Yoast 28.8 erzeugt seine automatische OG-/Twitter-Beschreibung zuletzt über
`get_the_excerpt`. Explizite Social-/SEO-Beschreibungen haben davor Vorrang und
werden nicht überschrieben. Der zusätzliche öffentliche Hook `wpseo_replacements`
behandelt ausschliesslich ein tatsächlich angefragtes `%%excerpt%%` ohne manuellen
Auszug. Ein Capture bei `PHP_INT_MIN` merkt sich den tatsächlichen nativen Wert
für das jeweilige originale Argumentobjekt; der Adapter bei Priorität 10 ändert
nur einen seitdem unveränderten Wert. Frühere abweichende Filterentscheidungen
nach diesem Capture bleiben erhalten. Ein bereits vor dem Capture unter derselben
minimalen Priorität registrierter Fremdproducer ist keine nachweisbare native
Ausgangsquelle und braucht eine eigene Projektprüfung.

Die Argument-ID muss ausdrücklich positiv und kanonisch sein. Der Argumentbody
muss mit dem gespeicherten Body oder dessen vom originalen öffentlichen
Yoast-Stringhelper normalisierter Fassung übereinstimmen; ein absichtlich
abweichender Projektklon bleibt unangetastet. Bei Yoast 28.8 verwendet der aktive
Text dessen geprüfte Zeichenbegrenzung. Ein unbekannter automatischer Producer
erhält für diesen eindeutig gebundenen Builder eine leere Variable. Literale
Beschreibungen, andere Variablen und `%%excerpt_only%%` bleiben nativ.
Yoasts Originalvariable behandelt bereits einen nicht leeren gespeicherten Wert
einschliesslich Leerraum als manuellen Auszug; diese vom Core-Auszug abweichende
Originalregel bleibt erhalten.

## REST und Feeds

Die REST-Grenze gilt für originale `WP_REST_Posts_Controller`-Leser und die
Kontexte `view`/`embed`. Ein vorhandenes `content.rendered` eines aktiven Builders
wird durch seine semantische Projektion ersetzt. `excerpt.rendered` verwendet
den oben beschriebenen nativen Auszugproducer. Status, Feldauswahl und leere
HEAD-Antworten bleiben erhalten. Ohne angefragtes `content.rendered` löst der
Antwortadapter keine zusätzliche Body-Projektion aus; ein angefragter Auszug
behält seinen eigenen Producer. Autorisierte `edit`-Antworten inklusive
`content.raw`, automatischem `excerpt.rendered` und beider gespeicherter Quellen
bleiben unverändert; unberechtigte
Bearbeitungsaufrufe werden weiterhin von Core abgelehnt.

Im öffentlichen Editor-Modus wird nur der über `ContentSources::field()` eindeutig
zugeordnete inaktive flexible Builderroot über `acf/rest/get_fields` entfernt.
Dasselbe gilt für den aktiven Root eines passwortgeschützten, nicht öffentlichen
oder durch die gemeinsame Zugriffsprüfung gesperrten Builders. Eine für Core
gültige REST-Passworteingabe hebt diese engere Projektionsgrenze nicht auf;
autorisierte `edit`-Antworten bleiben nativ.
Das geschieht innerhalb des tatsächlichen nativen REST-Lesers vor ACF-Werte- und
Linkaufbereitung. Unabhängige Felder bleiben erhalten. Verschachtelte Requests
erhalten getrennte Kontexte; Aufrufe ausserhalb eines passenden nativen Lesers
verändern keine ACF-Felder. Es werden keine fremden REST-Links pauschal gelöscht.

Feedvolltext wird nur im tatsächlichen nativen Feedloop für dessen aktuellen
Post angepasst. Der Export verwendet die fertige semantische Projektion direkt;
ein zweiter Durchlauf durch `the_content` würde fremde Shortcode-/Provider-Effekte
wiederholen. RSS-Auszüge nutzen den nativen Auszugproducer.

Die REST- und Feed-Antwortfilter laufen nach der ursprünglichen Core-Aufbereitung.
Sie verhindern die Ausgabe inaktiver Copy in den genannten Antwortfeldern,
unterbinden aber keine bereits davor ausgeführten fremden Content-Callbacks.
Unbekannte Custom-REST-Controller, direkte Datenexporte, zusätzliche öffentlich
registrierte Metafelder und uneindeutige ACF-Profile benötigen eine eigene
Projektprüfung. Dies ist keine allgemeine Geheimhaltungsgrenze für jeden
WordPress- oder Plugin-Exporter.

## Originalquellen und Nachweis

Geprüft wurden die lokalen Quellen von WordPress 7.1.4, Yoast SEO 28.8 und der
unveränderten ACF-PRO-6.8.11-Referenz. Ihre Herkunft und die Grenzen des
Prüfsummenabgleichs stehen im [gemeinsamen Nachweis](project-learnings-3.5.0.md#herkunft-und-offene-abschlussarbeiten).
Entscheidend sind Core
`wp_trim_excerpt`, `WP_REST_Posts_Controller::prepare_item_for_response` und
`get_the_content_feed`, Yoasts
`Indexable_Post_Type_Presentation::generate_open_graph_description` und
`WPSEO_Replace_Vars::retrieve_excerpt` sowie ACFs öffentlicher
`acf/rest/get_fields`-Hook vor Werte-/Embed-Linkaufbereitung.

Offizielle Referenzen:

- [Core-Auszugfilter](https://developer.wordpress.org/reference/hooks/get_the_excerpt/)
- [Native Auszugerzeugung](https://developer.wordpress.org/reference/functions/wp_trim_excerpt/)
- [REST-Antwortfilter](https://developer.wordpress.org/reference/hooks/rest_prepare_this-post_type/)
- [REST-Callbackkontext](https://developer.wordpress.org/reference/hooks/rest_request_before_callbacks/)
- [Yoast Metadata API](https://developer.yoast.com/customization/apis/metadata-api/)

`tests/source-public-context-native.php` bestand am 08.11.2026 unter PHP 8.3.36
in der lokalen SQLite-Fixture mit 57 Assertions und den Originalprovidern
Yoast 28.8 und der ACF-PRO-6.8.11-Referenz. Die getrennte Free-/Core-Wiederholung
bestand nach nativer Aktivierung von Original ACF Free 6.8.11 in einem frischen
Request mit 41 Assertions. Sie prüft den unbekannten Replacement-Producer über
seinen öffentlichen Filtervertrag und verwendet keine erfundene Yoast-Version.
Mail und HTTP waren in den Fixtures gesperrt.

Der Nachweis umfasst echte Core-Auszüge, vorhandene Callbackreihenfolge und
Argumentzahlen, gespeicherten Whitespace samt `raw_excerpt`, frühere Fremdfilter,
abweichende Projektklone, native REST-Reads einschliesslich `edit`, HEAD und
Feldauswahl, Custom-Controller-Abgrenzung, ACF vor Wertehydration, geschützte und
private Builder sowie den nativen Feedloop. Ein harmloser registrierter Shortcode
belegt die unterschiedliche Ausführungsgrenze: Beim Builderauszug wird der
inaktive Editor nicht ausgeführt; bei REST/Feed lief der originale frühere
Coreformatter bereits, sein inaktives Ergebnis fehlt aber im endgültigen Inhalt.
Die tatsächliche lokale Browserabnahme und die noch offenen CI-/Exportgrenzen
stehen im [gemeinsamen Prüfstand](project-learnings-3.5.0.md#lokaler-prüfstand).
