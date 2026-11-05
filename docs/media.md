# Automatische Medienoptimierung

## Explizite Auswahl kleinerer responsiver Kandidaten

Ab 3.4 steht `Rocketsky\Skye\Packages\ImageCandidates::prune( $sources, $image_src, $image_meta )` für bereits durch Core ausgewählte `srcset`-Kandidaten bereit. Der Aufrufer wählt den Bildumfang ausdrücklich; Skye registriert dafür keinen globalen Filter. Der ursprüngliche Rocketsky-Adapter bleibt auf das konfigurierte Kundenlogo begrenzt und verwendet dieselbe Entscheidung.

Ein Kandidat entfällt nur bei einer breiteren, zugleich bytekleineren Alternative mit nachgewiesen gleicher Bildart und passender nativer Cropgeometrie. Die gelieferten Metadaten müssen Dateiname, Breite, Höhe und positive Bytezahl eindeutig belegen. Query-basierte Bildoptimierer, fremde Ursprünge, abweichende Formate, mehrdeutige Metadaten und unbekannte Werte behalten ihre nativen Kandidaten. Grenzen: 128 Quellen und 256 Metadatensätze. Die Funktion liest keine Dateien, fragt keine Datenbank ab und verändert weder Medien noch Metadaten.

Herkunft: `wptheme.rocketsky/lib/logo-sources.php`, geprüfter Stand `e18d608`. Die native PNG-/WebP-Regression verwendet die vollständigen ursprünglichen Metadaten. Core liefert vor der Auswahl 830w, 300w und 768w; ausschliesslich das schwerere 768w-Bild entfällt. Das belegt diese Auswahlentscheidung, keine allgemeine Ladezeitverbesserung. [Nativer Hook](https://developer.wordpress.org/reference/hooks/wp_calculate_image_srcset/), [Core-Kandidatenauswahl](https://developer.wordpress.org/reference/functions/wp_calculate_image_srcset/).

## Hintergrundpipeline und Herkunft

Die Pipeline verallgemeinert die abgeschlossenen Website-Entwicklungen `d79c640b` und `f81a8bd7`, geprüft gegen den sauberen Stand `6fecb117`. `MediaStore`, `WebpGenerator` und `NativeSizes` bleiben eigene Packages unter `lib/package/media/`. Die alten Volksgarage-Namen, festen Projekt-Crops und Layout-/Breitenannahmen gehören nicht zur Implementierung.

Skye registriert ausschliesslich die in `lib/config/image_sizes.php` oder über `skye_image_sizes` angegebenen eigenen Grössen. Eine leere Konfiguration ist gültig. Der Hintergrunddienst verwendet die tatsächlich in WordPress registrierten Grössen, einschliesslich unveränderter Core- und Plugin-Grössen. Vorhandene benannte Varianten und deren Crops werden nicht überschrieben; für eine neue Cropfamilie einen neuen Grössennamen verwenden. Das entspricht dem additiven Vertrag von `wp_get_missing_image_subsizes()`.

Die bestehende `Images`-Klasse und ihre ursprünglichen Registrierungs-/Hinweismethoden sind erstmals im Skye-Verlauf `d1054de` vom 04.03.2021 belegt. Die neue Pipeline und die neuen Präsentationsmethoden werden mit Skye 3.0.0 eingeführt. Historische Website-Daten werden nicht durch ein unbewiesenes ursprüngliches Skye-Datum umetikettiert.

## Automatischer Ablauf

`Images.php` lädt die Paketabhängigkeiten. Der Theme-Bootstrap erzeugt je eine Instanz von `Images` und `MediaPipeline`. Die Pipeline bleibt auch bei deaktivierter Optimierung instanziiert, damit sie ihre bereits vorhandenen Events geordnet stoppen kann.

1. Beim ersten `init` legt sie einen nicht automatisch geladenen Scanstand und ein geprüftes Installationskennzeichen an. Wiederholte gewöhnliche Aufrufe lesen nur das automatisch geladene Kennzeichen; sie suchen keine Jobs und prüfen keine Bilddateien.
2. Native Upload-, Metadaten-, Regenerierungs- und Bildbearbeitungshooks speichern eine neue Jobidentität. Sie kodieren kein zusätzliches Bild. Alle Änderungen eines Requests werden zu einem Dispatcher-Event zusammengefasst.
3. Die bestehende Bibliothek wird in aufsteigenden ID-Batches von höchstens 20 JPEG-/PNG-Anhängen entdeckt. Der Cursor rückt nur nach bestätigter Jobablage beziehungsweise einem bestehenden Backfilljob vor. Scheitert ein Schreibvorgang, stoppt der Batch vor diesem Bild und bleibt zur Wiederholung offen. Native Löschmarker werden von der Entdeckung ausgeschlossen. Es gibt keine Offset-Pagination und keinen Bibliotheksscan im Bildrenderer.
4. Ein Worker bearbeitet höchstens vier Anhänge und erzeugt höchstens zwei fehlende native Varianten innerhalb eines gemeinsamen weichen Budgets von sechs Sekunden. WebP-Erzeugung ist zusätzlich auf höchstens 32 Dateien je Anhang begrenzt. Ein laufender nativer Codec kann nicht gefahrlos mitten in einer Datei unterbrochen werden; das Budget wird vor der nächsten Operation erneut geprüft.
5. Neue oder geänderte registrierte Grössen werden in `admin_init` und beim Hintergrund-Recovery erkannt. Die Bibliotheksentdeckung startet dann automatisch neu. Eine vorübergehend fehlende Registrierung löscht das bekannte Schema nicht. Vorhandene Originale und benannte Varianten bleiben erhalten.
6. Eine stündliche Recovery stellt Dispatcher für dauerhaft gespeicherte Jobs wieder her und setzt unterbrochene Entdeckung fort. Zusätzlich startet sie eine ausschliessliche Hintergrundprüfung vorhandener eigener Inventare, um verlorene Bereinigungstermine wiederherzustellen. Sie beginnt keine Suche bei gewöhnlichen Frontend-Aufrufen.

WordPress-Cron und ein vorhandener Hosting-Cron verwenden dieselben nativen Hooks. Ist `DISABLE_WP_CRON` aktiv, muss das Hosting den gewöhnlichen WordPress-Cron regelmässig ausführen. Das Theme installiert keinen eigenen Serverdienst und verändert keine Hostingkonfiguration.

## Sicherheit und Konsistenz

Eine exakte, zeitlich begrenzte Datenbanklease schützt den Encoder. Ein SQL-`INSERT IGNORE` reserviert die Lease; ein abgelaufener Wert kann nur mit exaktem Vergleich ersetzt werden. Die Sperre verlässt sich nicht auf Redis oder `add_option()` als Mutex.

Jede Veröffentlichung vergleicht in derselben SQL-Anweisung die Jobrevision, die native Metadatenzeile, den Dateizeiger, die Existenz des Anhangs, einen möglichen Löschmarker und die aktuelle Lease. Veraltete Arbeit kann dadurch weder gelöschte Core-Metadaten neu anlegen noch neuere Bearbeitungen überschreiben. Native Filter, Metadatenaktionen und Cacheinvalidierung bleiben Teil des gemeinsamen Speicherwegs.

Alle Quellpfade müssen zu lokalen, lesbaren JPEG-/PNG-Dateien innerhalb des Uploadverzeichnisses gehören. Zielpfade werden exklusiv reserviert und vor ihrer Verwendung in einer eigenen Inventarliste gespeichert. Symlinks, Traversal, Wrapper, fremde Verzeichnisse und unpassende Dateinamen werden nicht als eigene Ausgaben übernommen. Es gibt keine globale Dateisuche und keine Löschung fremder oder älterer unbekannter Dateien.

Originalbytes und native Metadaten bleiben bestehen. Fehlende native Grössen werden additiv eingetragen. WebP-Dateien werden nur als vollständiger geprüfter Alternativensatz veröffentlicht, wenn jede tatsächlich erzeugte Datei strikt kleiner als ihre exakte native Quelle ist. Eine einzige grössere oder gleich grosse Variante lässt den gesamten nativen Satz im Einsatz. Animierte PNGs werden nicht abgeflacht; Transparenz und native Orientierung bleiben Teil der Prüfungen.

Ein vorübergehender Fehler bleibt mit begrenzter exponentieller Wiederholungsverzögerung bis höchstens einem Tag wiederherstellbar. Der Fehlerdatensatz enthält einen technischen Code und die Anzahl der Versuche, keine Bildinhalte oder Dateipfade. Ein endgültiger fehlender Grössenvorteil beendet den Job ohne Endlosschleife.

## Darstellung und Erweiterung

`Images::webp_source( $attachment_id, $size = 'full' )` liefert `false` oder ein Array mit `src`, `srcset`, `width`, `height` und `jpeg_src`. Der Vertrag verwendet Core-Grössenauswahl und Core-`srcset`; ein unpassender Crop, unbekannter Dateiverweis, ungültiges Schema oder fremder injizierter Kandidat behält den nativen Fallback. Es gibt weder Dateizugriff noch Hashberechnung, Konvertierung, Queueabfrage oder persistenten URL-Cache in diesem Renderweg. Nur requestlokale Ergebnisse werden wiederverwendet. Der normale Metadatenfilter sowie abgeschlossene native Add-/Update-/Delete-Hooks für Bildmetadaten und Dateizeiger verwerfen positive und negative Ergebnisse; auch die permanente Anhangslöschung setzt den Cache zurück.

Der Filter `wp_get_attachment_image` ergänzt über `components/media/picture.php` einen geprüften WebP-`source`. Das vollständige ursprüngliche `img` bleibt bytegleich mit seinen nativen Alttexten, Dimensionen, `sizes`-, Lade- und Prioritätsattributen erhalten. Bestehende `picture`-Elemente, mehrfache/umhüllte Bilder, Icons, Adminausgabe und ein fehlender Komponentenrenderer bleiben unverändert. Die Ausgabe verwendet den gemeinsamen `skye_component_html()`-Puffervertrag, keine zweite Resolverimplementierung.

Während einer additiven nativen Grössenerweiterung bleiben bereits veröffentlichte, weiterhin korrekt gebundene Alternativen verwendbar. Neu hinzugekommene native Varianten behalten ihren Fallback, bis ein vollständiger neuer Alternativensatz publizierbar ist. `validate_webp_metadata()` ist deshalb strenger als die rein lesende Darstellung eines bestehenden Satzes: die Veröffentlichung verlangt alle aktuellen JPEG-/PNG-Varianten.

Eigene Medienmetadaten verwenden `skye_webp` und `_skye_webp_*`. Jobs und Inventare liegen in Postmetadaten statt in automatisch geladenen Optionen; der native Metadatencache kann sie beim ersten Laden der Anhangsmetadaten mitlesen. Der Installationsstand liegt in `skye_webp_pipeline_version`, der Cursor in `skye_webp_pipeline_scan`, die zuletzt bekannte Registrierung in `skye_webp_registered_sizes`. Der separate, nicht automatisch geladene Bereinigungscursor liegt in `skye_webp_cleanup_scan`. Zero im Installationskennzeichen bedeutet eine unvollständige Einrichtungs- oder Abschalttransaktion. So erzeugt ein gescheiterter erster Schreibvorgang keine unbeobachteten Events; ein fehlgeschlagenes Unscheduling wird wiederholt beziehungsweise bei Wiedereinschalten vollständig repariert.

## Abschalten, Wechsel und Bereinigung

Ein eigenes Deployment-/Projektplugin kann die Optimierung über den dokumentierten Policyfilter deaktivieren:

```php
add_filter( 'skye_media_pipeline_enabled', '__return_false' );
```

Damit entfallen neue Jobs, Dispatcherarbeit und WebP-Ausgabe. Geplante Pipeline-Events werden entfernt; dauerhaft gespeicherte Jobs, Scanfortschritt und Originale bleiben zur Wiederaufnahme bestehen. Ein Themewechsel stoppt ebenfalls die eigenen Events. Das Wiederaktivieren stellt die Dispatcher wieder her. Ein gescheitertes natives Entfernen des Events bleibt erkennbar und wird beim nächsten passenden Aufruf erneut behandelt.

Nicht mehr publizierte eigene WebP-Dateien werden mindestens 24 Stunden für bereits ausgelieferte HTML-Antworten behalten. Scheitert die Aktualisierung des eigenen Inventars, speichert derselbe native Metadatenvorgang zusätzliche `skye_webp_retirement`-Fristen atomar mit der erfolgreichen Bearbeitung. Cleanup verwendet die jeweils spätere Frist. Nachfolgende native Bearbeitungen tragen diese eigenen Fristen weiter; ein kaputter Fristwert blockiert eine unbewiesene vorzeitige Löschung. Die kleinen Fristdatensätze können nach der Dateibereinigung bis zur Anhangslöschung in den Metadaten verbleiben. Normale Bereinigung schützt veröffentlichte und fortsetzbare Ausgaben sowie native Core- und Backupreferenzen. Eigene native Grössen bleiben aus Rücksicht auf Core-Bildbearbeitung und Wiederherstellung bis zur endgültigen Anhangslöschung erhalten; das ist eine bewusste konservative Dateiaufbewahrung. Eine dauerhafte Löschung entfernt ausschliesslich zu diesem Anhang gehörende inventarisierte eigene Ausgaben. Core bleibt für seine Originale und nativen Backupdateien verantwortlich.

Die Wiederherstellung von Bereinigungsterminen liest unter derselben Workerlease höchstens 20 vorhandene Inventare je Ereignis und hat ein weiches Budget von sechs Sekunden. Sie verwendet einen aufsteigenden ID-Cursor und liest für die Schutzentscheidung die nativen Metadaten, Backups, fortsetzbaren Ausgaben und das Inventar; sie untersucht keine Bilddateien. Ausschliesslich eigene WebP-Dateien ausserhalb dieser Schutzlisten benötigen einen Termin. Vollständig publizierte Sätze, fortsetzbare Ausgaben und konservativ aufbewahrte native Grössen erzeugen keine zusätzlichen Bereinigungsevents. Ein bereits vorhandener Termin wird bestätigt, ohne ihn zu duplizieren.

Fehlgeschlagene Datenbanklesevorgänge oder nicht bestätigte Termine stoppen vor dem betroffenen Anhang. Unvollständige Batches erhalten einen weiteren Termin nach 30 Sekunden; ein fehlgeschlagener erster Auswahllesevorgang nach 60 Sekunden. Die tatsächliche Ausführung hängt vom nativen Cron ab; die stündliche Recovery kann auch diesen Dispatcher wiederherstellen. Ein fehlgeschlagenes Speichern des Cursors wiederholt lediglich bereits bestätigte Arbeit. Weitere Batches folgen nur bei unvollständigem Durchgang; ein abgeschlossener Durchgang setzt den Cursor für den nächsten stündlichen Zyklus zurück und endet. Korrupten, nicht beweisbaren Inventardaten wird keine Löschberechtigung entnommen; sie blockieren aber auch keine späteren Anhänge. Der tatsächliche Bereinigungshook prüft Lease, aktuelle Schutzlisten und Mindestaufbewahrung erneut. Die vollständige Schutzprüfung eines einzelnen Anhangs kann das weiche Budget überschreiten; danach beginnt kein weiterer Anhang in diesem Batch.

## Tatsächliche Prüfung

```sh
SKYE_WORDPRESS_CORE=/absoluter/pfad/wordpress php tests/media-pipeline.php
```

Der Test benötigt echtes Core-Quellmaterial, PDO SQLite und GD mit WebP-Unterstützung. Er startet keine WordPress-Installation, liest keine realen Uploads und blockiert native HTTP-Anfragen. SQLite führt die tatsächlichen Produktionsprädikate aus; der Adapter übersetzt ausschliesslich MySQL-Syntax. Hooks, Cron, Metadaten, Bildauswahl, HTML-Parser und GD-Encoder sind echt. Die Suite prüft Produktionsklassen, den tatsächlichen Komponentenloader und die tatsächliche Picture-Komponente.

Geprüft am 03.11.2026 mit PHP 8.3.36, WordPress 7.1.4 und GD/WebP: Lease-/CAS-Grenzen, vollständige kleinere JPEG-/PNG-Sätze, Transparenz, fehlerhafte Quellen/Encoder, konkurrierende Revisionen und Löschungen, native Wiederherstellung, Aufbewahrung, Schedulerfehler, Abschaltung/Wiederaufnahme, Konfigurationsänderungen und unveränderte Frontend-Fallbacks. Die endgültige Anzahl der Assertions steht im JSON-Ergebnis der jeweiligen Ausführung.

Ein deterministischer Foto-PNG-Satz mit drei Varianten hatte im Test 12'059 native Bytes und 4'812 WebP-Bytes. Ein flacher PNG mit 277 nativen Bytes und 330 WebP-Bytes wurde korrekt abgelehnt. Diese Grössen sind Fixture-Messungen, keine allgemeine Zusage einer prozentualen Ladezeitverbesserung.

Nicht durch diese Suite belegt sind MySQL-/InnoDB-Mehrprozessverhalten, ein externer Redis-Provider, ein reales Hosting-Cron, Imagick sowie Browser-/Geräteabnahme. Die Integrationsprüfung auf einem Wegwerf-WordPress muss parallele Workerprozesse, Upload/Bearbeitung/Regenerierung/Löschung und den tatsächlichen Hosting-Cron abdecken; sie darf keine produktiven Kundendateien verwenden.

## Konsultierte Primärquellen

- [Native fehlende Grössen](https://developer.wordpress.org/reference/functions/wp_get_missing_image_subsizes/): nur tatsächlich mögliche fehlende benannte Varianten ergänzen.
- [Nativer Bildeditor](https://developer.wordpress.org/reference/functions/wp_get_image_editor/): Codecfehler als `WP_Error` behandeln und Originale erhalten.
- [Native Metadaten](https://developer.wordpress.org/reference/functions/wp_update_attachment_metadata/): Core-Filter und Metadatenverträge am gemeinsamen CAS-Speicherweg beachten.
- [Einmalige Cronevents](https://developer.wordpress.org/reference/functions/wp_schedule_single_event/): Dublettenfenster, Fehler und verzögerte Wiederaufnahme berücksichtigen.
- [Hook abschalten](https://developer.wordpress.org/reference/functions/wp_unschedule_hook/): alle eigenen Argumentvarianten entfernen und native Fehler erkennbar halten.
