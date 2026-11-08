# Seitenbaukasten und Standardeditor

Skye 3.5.0 kann beide Inhaltsquellen desselben Dokuments aufbewahren und eine
davon für die Ausgabe wählen. Der Classic Editor speichert weiterhin
`post_content`; das originale ACF PRO speichert weiterhin den flexiblen Baukasten.
Die zusätzliche Auswahl liegt ausschliesslich in `_skye_content_source`.
Beim Lesen wird nichts migriert oder gespeichert.

## Voreinstellung und Änderung

| Dokument | Quelle ohne gespeicherte Auswahl |
| --- | --- |
| Bestehendes Template `templates/pagebuilder.php` | `builder` |
| Gewöhnliche Seite oder anderer nativer Editor | `editor` |

Eine gültig gespeicherte Auswahl gilt für beide Templatewege. Eine Standardseite
kann damit den Baukasten verwenden; das bisherige Baukastentemplate kann den
Standardeditor verwenden. Fremde Werte werden nicht umgedeutet.

Die native Metabox «Inhalt bearbeiten mit» gehört zum originalen WordPress-
Beitragsformular. Beide Eingaben bleiben darin vorhanden. Erst eine vollständig
initialisierte eigene Steuerung blendet die inaktive Oberfläche mit CSS aus.
Ohne JavaScript, bei fehlender Zuordnung oder unbekannter Provideranbindung
bleiben die nativen Oberflächen sichtbar.

Im geprüften Originalprofil stehen Auswahl und Buildergruppe direkt nach dem
Titel vor Standardeditor und Yoast. Der eigene Chooser wird vor der originalen
ACF-Registrierung angelegt. Danach verschiebt Core ausschliesslich die exakt
identifizierte ursprüngliche ACF-Box in denselben `acf_after_title`-Kontext.
Der Originalcallback mit seinen Argumenten, Titel und Priorität bleibt erhalten;
die gespeicherte Feldgruppe und ihre Definition werden nicht verändert. Ein
fremder oder fehlender Renderer wird nicht verschoben und erlaubt keine
verborgenen Editoroberflächen. Vorhandene normale Feldgruppen können damit ohne
persistente Positionsmigration eingebaut werden.

**Änderung gegenüber älteren Projekten:** Eine ausdrücklich gespeicherte
Baukastenwahl darf bei einem leeren Baukasten oder verlorener ACF-Anbindung
keinen aufbewahrten Standardeditor veröffentlichen. Der Baukasten bleibt die
gebundene Quelle. Ohne gültiges Feldprofil bleibt seine Ausgabe leer.
Nicht verwaltete alte Templates ohne ausdrückliche Auswahl behalten bei einer
unbekannten/fehlenden Anbindung ihren bisherigen nativen Fallback.

## Native Feldzuordnung

Das Standardfeld heisst weiterhin `pagebuilder`. Im jeweiligen Dokument müssen
die anwendbaren nativen ACF-Gruppen genau ein passendes flexibles Wurzelfeld
enthalten. Innere gleichnamige Felder oder globale Namensaliase ersetzen diese
Identität nicht. Es werden nur Definitionen gelesen, keine Baukastenwerte zur
Auswahlentscheidung formatiert.

Ein Projekt kann seine bestehende unabhängige Identität ausdrücklich über
`skye_content_sources` anpassen. Der Filter erhält die bisherige Definition
`array( 'name' => 'pagebuilder', 'key' => '' )` und das genaue `WP_Post`-Objekt.
Ein passender Vertrag verwendet beispielsweise:

```php
array( 'name' => 'pagebuilder', 'key' => 'field_customer_pagebuilder' )
```

Name und Key müssen zur tatsächlich anwendbaren ursprünglichen Felddefinition
passen. Mehrdeutigkeit, abweichende Keys, ein bedingtes ganzes Wurzelfeld oder
deaktivierte/schreibgeschützte Wurzelkontrollen werden abgelehnt.
Klone beziehungsweise zwei aktuelle Definitionen mit derselben Storageidentität
müssen im Projekt bereinigt werden.

Keine `conditional_logic` am gesamten Baukasten verwenden, um zwischen den
Quellen umzuschalten. ACFs native `hideDisable`-Logik entfernt dessen Kontrollen
sonst aus der Formularübermittlung. Innere fachliche Feldbedingungen behalten
ihren eigenen nativen Vertrag. Die Quelle wird durch Skye dargestellt, ohne
Felddefinitionen oder gespeicherte Referenzen umzuschreiben.

## Ungespeicherte Werte

Der Controller bindet das originale Formular `#post`, die Metabox
`#skye-content-source`, den exakten ACF-Wurzelkey und `#postdivrich`.
Nur wenn Postidentitäten, native ACF-Feldinstanz und Typ zusammenpassen, setzt
er `data-skye-content-sources-ready="true"` sowie den aktuellen Quellenzustand.
Auch die über `form.elements` zugeordneten Controls müssen einen bekannten
nativen `willValidate`-/`validity`-Vertrag besitzen. Bei unbekannten Controls
entfernt die Steuerung ihre Sichtbarkeitsmarker, bevor sie ein `invalid`-Ereignis
abfängt; beide Quellen bleiben dann sichtbar.

Die Inhaltsfelder werden weder deaktiviert, entfernt oder verschoben noch neu
initialisiert; bestehende Editorinstanzen bleiben erhalten. Eine ACF-Box, die
ausschliesslich diesen Wurzelbaukasten enthält, kann als Ganzes ausgeblendet
werden. Bei weiteren Feldern bleibt die
gemeinsame Box sichtbar; nur der eigene Wurzelbereich wird ausgeblendet.
Dynamische Änderungen werden erneut auf diese Grenze geprüft.

Vor Quellenwechsel und Submit synchronisiert die Steuerung bereits bestehende
visuelle TinyMCE-Instanzen über deren öffentliche `save()`-API. Instanz und
originale Textarea müssen zusammengehören. Code-Textareas bleiben unverändert;
der bisherige Dirty-Zustand wird erhalten. Es gibt keinen laufenden Vollscan
bei jeder Texteingabe.

Das native `formdata`-Ereignis kommt nach dem Aufbau der Formulareinträge. Dort
werden nur bereits vorhandene einmalige eigene Textareaeinträge mit dem
gerade synchronisierten Wert aktualisiert. Fehlende, deaktivierte,
geklonte, fremde oder mehrfach gleichnamige Controls werden nicht ergänzt.
Nonces und andere Formulareinträge bleiben unverändert.

`window.SkyeContentSources.getSource()` liefert erst bei tatsächlicher
Bereitschaft `builder` oder `editor`, sonst `null`. Nach Bereitschaft und
Quellenwechsel wird `skye:content-source:change` auf `document` mit
`{ source, postId }` ausgelöst. Beim Verlust der Bindung fällt `source` auf
`null` zurück. Die [Yoast-Anbindung](yoast-analysis.md) verwendet diese
Bereitschaft; versteckte inaktive Werte werden nicht als aktive Quelle
hinzugemischt.

## Prüfung und Speichern

Die Quelle besitzt eine eigene postgebundene Nonce. Zusätzlich müssen
`post_ID`, `skye_content_source_post` und `_acf_post_id` dieselbe konkrete
Identität tragen, die Core-Update-Nonce passen und `edit_post` erfüllt sein.
AJAX-Validierung und normaler POST-Save besitzen ihre jeweiligen nativen
ACF-Nonceverträge. Skye verbraucht keine fremde Nonce.

### HTML5 und nativer Submitter

Bei aktiver Editorquelle fängt der bereite Controller `invalid` in der
Capturephase ausschliesslich für benannte, aktivierte Controls seines exakten
inaktiven Builderroots ab. Das verhindert neue HTML5-Fehler dieses Zweigs im
originalen ACF-Invalid-Handler. Bestehende Fehler werden weder entfernt noch
zurückgesetzt; Feldconstraints werden nicht verändert. Bekannte ungültige
Controls ausserhalb des Roots behalten ihre nativen Handler und verhindern
den eigenen Submitretry. Die Prüfung erfasst auch über `form=` zugeordnete
Controls ausserhalb des Formular-DOMs.

Nur ein echter Klick bindet den tatsächlichen Submitter, auch beim impliziten
Tastaturklick des Browsers. Dieser Auftrag verfällt im nächsten Task, sofern
weder seine eigene Invalid-Queue noch die zugehörige native Validierung läuft.
Ein nachträglich abgebrochener Klick wird vor dem Retry erneut erkannt.
Ein späteres eigenständiges `checkValidity()` oder `reportValidity()` startet
dadurch keinen Speichervorgang.

Nach dem Invalidzyklus prüft ein späterer Task Quelle, Root, Submitter und die
übrigen Controls erneut und synchronisiert beide Inhaltsquellen. Anschliessend
verwendet er `form.requestSubmit(originalSubmitter)`. Der originale Buttonwert,
etwa `publish`, bleibt damit Teil des nativen Vorgangs. Ausschliesslich für
diesen synchronen Aufruf erhält der Submitter `formNoValidate = true`;
`finally` stellt Anwesenheit und genauen Rohwert seines vorherigen
`formnovalidate`-Attributs wieder her. Das Formular erhält kein `noValidate`.
Submit-Handler, ACF-AJAX-Prüfung und Nonces bleiben im nativen Ablauf.

ACF sperrt seine Submitbuttons nach dem erfolgreichen ursprünglichen Click-Replay
erneut. Nach einem passenden öffentlichen `validation_success` bindet die Steuerung
deshalb einen Beleg an den schon bei Validierungsbeginn zugeordneten Auftrag,
Post, Quelle, Root und denselben damals nativ freigegebenen Submitter.
Nur dieser Beleg erlaubt einmalig den originalen
öffentlichen Handoff `acf.enableSubmit($(originalSubmitter))` vor dem erneuten
`requestSubmit`. Es gibt kein allgemeines `unlockForm`; fremde oder über ein
Fieldset deaktivierte Submitter werden nicht freigegeben.

Zwischen den öffentlichen ACF-Hooks `validation_begin` und
`validation_success`/`validation_failure` ist nur die eigene Quellenauswahl
`inert`. Ihre Inputs bleiben Formularbestandteile. Nach Abschluss oder Fehler
wird der vorherige Inert-Zustand wiederhergestellt. Ein Quellen-, DOM- oder
Providerwechsel nimmt ebenfalls die eigene Sperre zurück und entfernt die
Sichtbarkeitsmarker.

### Serverseitige ACF-Prüfung

ACF PRO 6.8.11 prüft `required` vor seinen Wertefiltern. Flexible Content
validiert seine Kinder bereits im Typfilter. Ein späteres `return true`
am Wurzelkey würde bestehende Kindfehler nicht sauber aufheben und könnte
frühere Entscheidungen überschreiben.

Deshalb verwendet Skye den öffentlichen Hook `acf/validate_save_post`:

1. Bei Priorität 4 wird nur der authentifiziert zugeordnete **inaktive**
   Wurzelzweig vorübergehend aus der Validierung genommen.
2. Der unveränderte originale Validator läuft bei 5.
3. Bei 6 wird das identische eingeschränkte Array samt ursprünglicher Reihenfolge
   wiederhergestellt. Der folgende originale Save erhält beide Quellen.

Die Momentaufnahme entsteht im normalen POST erst nach der originalen
Feldbeschränkung. Bereits verworfene fremde Feldkeys werden nicht wieder
eingeführt. AJAX validiert dieselbe exakt gebundene Wurzel ohne Schreibvorgang;
der spätere normale Save führt seine native Beschränkung erneut aus.

Voraussetzung ist der geprüfte Originalstand 6.8.11 mit seiner exakten
Callbackmenge im Fenster 4–6. Neben dem Postvalidator sind darin die originalen
Nav-Menü- und Widgetvalidatoren registriert; ihre Kontexte müssen beim
Postformular inaktiv sein. Aktive Menü-/Widget- oder Blockeditor-Kontexte,
zusätzliche Callbackproduzenten und unbekannte Versionen behalten die
vollständige native Feldprüfung und beide sichtbaren Oberflächen. Frühere und spätere Fehler bleiben erhalten. Es gibt keinen
Fehlerreset, keine Änderung von `required` und keine Mutation des ACF-Feldstores.

Die Wiederherstellung ist an die genaue eingeschränkte Eingabe gebunden.
Eine unerwartete Änderung erzeugt einen zusätzlichen Fehler und wird nicht
blind überschrieben. Verschachtelte Validierung verbraucht keine äussere
Momentaufnahme. Eine Unterbrechung besitzt eine reine Request-Wiederherstellung
beim Shutdown, ohne Speicherung.

Die Quellenauswahl wird innerhalb von `acf/save_post` nach dem originalen
ACF-Speichercallback gesetzt. Im normalen Postpfad erreicht eine abgelehnte
ACF-Validierung diesen eigenen Speichercallback nicht. Core und ACF behalten
Sanitizer, Rechte und ihre Inhalts- beziehungsweise Revisionsabläufe. Das
Aktivieren eines unvollständigen Baukastens unterliegt wieder der normalen
Validierung.

### Grenze bei abgelehnten Speicheraufrufen

Core schreibt `post_content`, bevor der ACF-Posthandler auf `save_post` seine
serverseitige Feldvalidierung ausführt. Ohne vorherige AJAX-Validierung kann
bei ausgeschaltetem JavaScript oder einem direkten POST deshalb bereits ein
neuer Core-Editorinhalt gespeichert sein, obwohl ACF-Felder oder Quellenwahl
anschliessend abgelehnt werden. Ein negativer nativer CLI-Save hat diese
Teilaktualisierung bestätigt. Er ist kein Browser-/HTTP-Abnahmenachweis.

Die Quellenwahl führt keine gemeinsame Core-/ACF-Transaktion und keinen
allgemeinen Rollback ein. Die Wiederherstellung des kurzzeitig abgeschirmten
Requestzweigs stellt Formulareingaben wieder her; sie nimmt keine bereits
persistierten Coreänderungen zurück. Eine Fehlermeldung darf deshalb nicht als
Beleg gelten, dass alle beteiligten Speicher unverändert geblieben sind.

Die negative Browserkontrolle muss separat zeigen, ob die native AJAX-Prüfung
den nachfolgenden POST tatsächlich verhindert. Danach Coreinhalt, ACF-Werte und
Quellenauswahl unabhängig zurücklesen. Der Erhalt beider Entwürfe beim
Quellenwechsel und erfolgreichen Speichern ist von dieser Fehlergrenze zu
unterscheiden.

## Rendering und Übernahme bestehender Projekte

`ContentSources::mode()` liest die ausdrückliche beziehungsweise historische
Entscheidung. `analysis_source()` bindet eine gespeicherte Quelle auch bei
einem Providerverlust. Gewöhnliche nicht verwaltete Editorseiten ohne
Quellenmetadata oder Projektprofil benötigen dafür keine ACF-Gruppensuche.
Metadaten- und Profilreads werden nicht als grundsätzlich abfragefrei behauptet.

`components/page/content.php` verwendet den gemeinsamen Dispatcher.
`components/page/native.php` führt ausschliesslich die native
`the_content()`-Pipeline und Pagination aus. Der Fallback kann somit nicht
rekursiv in die Quellenwahl gelangen. Schutzprüfung und WordPress-Passwortform
bleiben vor eigenen Baukastenwerten wirksam.

Native automatische Auszüge, öffentliche REST-/Feed-Antworten und Yoast-
Auszugvariablen verwenden die [öffentliche Quellenabgrenzung](public-content-sources.md).
Ein aktiver Builder benötigt dafür einen passenden semantischen PHP-Callback;
ohne gültige Projektion bleiben diese Builderexporte leer. Die öffentliche
Ausgabe und der Erhalt beider Entwürfe sind getrennt zu prüfen.

Vor einer Projektübernahme:

- Die tatsächlichen Rootkeys und deren ACF-Gruppen prüfen; eigene Wurzel-
  Source-Conditional-Logic entfernen und unabhängige Keys erhalten.
- Eigene Templateüberschreibungen auf den gemeinsamen Dispatcher beziehungsweise
  die passenden expliziten Quellen-APIs umstellen.
- Den semantischen Callback `skye_yoast_analysis_content` vor dem Merge mit den
  tatsächlichen Layouts und den öffentlichen Exportwegen prüfen.
- Vorhandene Quellenumschalter und Analyseadapter ablösen; keine parallelen
  Sichtbarkeitscontroller oder alten Leaf-Ausschlüsse beibehalten.
- Beide Quellen ohne Zwischenspeichern ändern, zur anderen wechseln, nativ
  speichern, neu laden und die beiden neuen Werte wieder öffnen. Einen
  unvollständigen inaktiven Entwurf speichern und danach dessen Aktivierung
  erneut validieren lassen.

Es gibt keine automatische Migration bestehender Kundeninstallationen.
Ein Repositorymerge allein ist kein Nachweis für deren Feldschema,
Templateüberschreibungen oder tatsächlichen Save-Ablauf.

## Nachweise und Quellen

`tests/content-sources-native.php` prüft native Standardentscheidungen und,
mit Original ACF PRO 6.8.11, echte Core-/ACF-Saves, neue Werte beider Quellen,
aktive Kindvalidierung, die bereits eingeschränkte Eingabe, frühere Fehler,
unbekannte Callbackfenster, Nonces/Identitäten/Rechte und tatsächliche
Komponenten inklusive leerem beziehungsweise verlorenem Builderprofil.
Eine Core-Fixture ohne ACF weist ihre fehlende Providerabdeckung ausdrücklich aus.
Controller-VM-Tests sind getrennt von echter Browser-FormData und HTTP-Saves.

Konkrete ausgeführte Versionen, Browserabläufe und noch fehlende Geräteabdeckung
stehen im [Projektlern-Nachweis](project-learnings-3.5.0.md). Die Aussagen über
ACF-Reihenfolge wurden anhand seiner unveränderten 6.8.11-Originalquellen
`includes/validation.php`, `includes/forms/form-post.php`,
`includes/acf-form-functions.php` und der Flexible-Content-/Repeatervalidatoren
gelesen. Interne Originalfunktionen wurden dafür nicht ersetzt oder direkt
aufgerufen.

Offizielle Verträge:
[ACF-Validierungshook](https://www.advancedcustomfields.com/resources/acf-validate_save_post/),
[Wertevalidierung](https://www.advancedcustomfields.com/resources/acf-validate_value/),
[Felddefinitionen](https://www.advancedcustomfields.com/resources/acf_get_fields/),
[Core-Hookrecords](https://developer.wordpress.org/reference/classes/wp_hook/),
[Meta-API](https://developer.wordpress.org/reference/functions/update_post_meta/),
[TinyMCE Editor API](https://www.tiny.cloud/docs/tinymce/latest/apis/tinymce.editor/),
[FormData-Ereignis](https://developer.mozilla.org/en-US/docs/Web/API/HTMLFormElement/formdata_event)
und [vorhandene Einträge aktualisieren](https://developer.mozilla.org/en-US/docs/Web/API/FormData/set).
Der native Submitterpfad folgt den HTML-Verträgen für
[`requestSubmit`](https://html.spec.whatwg.org/multipage/forms.html#dom-form-requestsubmit),
[Formularübermittlung und Validierung](https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#form-submission-algorithm)
sowie [`inert`](https://html.spec.whatwg.org/multipage/interaction.html#the-inert-attribute).
