# Skye Coding Standards

Diese Standards stammen aus den Portalcommits `bd378f7c` und `9910edc6` und gelten auch für Skye. Redis und ACF PRO bleiben separate Installationen. Die Freigaben stehen in [implementation-scope.md](implementation-scope.md).

## Arbeitsweise und Commits
Logik- und Styling-Änderungen strikt in getrennten Commits führen. Funktionale
PHP-/JavaScript-Änderungen mit ihren Regressionen und visuelle SASS-/Asset-
Änderungen mit ihren Darstellungsprüfungen getrennt beschreiben. Benötigt ein
Feature beide, Abhängigkeit und Reihenfolge nennen. Der Gesamtstand bleibt
prüfpflichtig. Reine Formatierung getrennt von Verhaltensänderungen halten.
Eine zusätzliche verbindliche Vorgabe für Commit-Titelpräfixe ist in den
genannten Quellen nicht belegt.

Vor WordPress-bezogener Implementierung die einschlägige offizielle API-,
Coding- und Dokumentationsreferenz lesen. Konkrete Quellen und daraus folgende
Verträge im Arbeits- oder Reviewnachweis nennen. Native WordPress-APIs und ihre
Security-/Cache-Verträge wiederverwenden. Ersatzimplementierungen begründen.

## Bestehende Architektur
Theme-Grundstruktur und get_component() erhalten. Funktionen, Klassen,
Datenzugriffe, Berechtigungen, Validierung, Zustandsübergänge und vorbereitete
View-Modelle gehören nach lib/. Fachliche Unterordner verwenden; grössere
zusammengehörige Umfänge objektorientiert im bestehenden lib/package/ kapseln.
Eine notwendige Architekturabweichung vor Umsetzung konkret begründen und
freigeben lassen. Normale Arbeiten innerhalb der Struktur brauchen dadurch
keine zusätzliche Freigabe; die Originalpaketregel bleibt anwendbar.

components/ und views/ enthalten dokumentierte HTML-Templates. Darstellung,
Escaping, Schleifen und einfache rein lesende Aufbereitung sind dort zulässig.
Keine Funktions-/Klassendeklarationen, Abfragen oder Zustandsänderungen in
Templates. HTML als HTML schreiben; keine zusammengesetzten Seitenlayouts in
PHP-Strings. Styles nach assets/styles/, Verhalten nach assets/scripts/.

## Komponentenverträge
Komponenten ausschliesslich mit get_component() laden. Bestehende Ein- und
Zweiargumentaufrufe sowie Child-Theme-Overrides erhalten. Benötigte Daten als
kleines ausdrückliches Array im dritten Argument übergeben. Keine implizite
Scope-Weitergabe, kein get_defined_vars(), keine vollständigen Objektgraphen
oder Geheimnisse. name, second, args und template bleiben reserviert.
Optionale Werte mit ausdrücklichen Defaults behandeln; ein vorausgesetzter
Schlüssel darf beim Filtern nicht wegen null verschwinden. Gemeinsame
Ergebnisse vor dem Rendering berechnen, nicht durch Scope-Rückschreiben.

## Schreibweise und Dokumentation
Tabs, LF und lesbare Abstände verwenden; keine Leerzeichen am Zeilenende.
Nicht leere PHP-Klammern mit inneren Abständen: function xyz( $data, $zxy ) {
und xyz( $data, $zxy );. Leere Klammern bleiben (). Kontrollstrukturen lesbar
über mehrere Zeilen schreiben; in HTML-Templates alternative Syntax bevorzugen.
Bestehende fachliche Namen, stabile APIs, Kommentare und Schutzmechanismen
erhalten. Legacy-Globals ausdrücklich deklarieren; neue Präsentationswerte
über Argumente übergeben.

Jede eigene PHP-Datei beschreibt am Anfang ihren Zweck. Jede eigene Funktion
und Methode erhält unmittelbar vor der Deklaration einen englischen DocBlock:
- Zweck und fachliche Begründung;
- typisierte @param-Angaben für tatsächliche Parameter;
- typisierter @return, bei fehlendem Rückgabewert void;
- belegtes @since: erste Projektversion oder belegtes Einführungsdatum;
- relevante Array-Strukturen, Null-/Fehlerfälle, Nebenwirkungen sowie
  Sicherheits- und Wiederholungsgrenzen.
Historische Angaben anhand Git prüfen, nie erfinden. Nach Prüfung unbekannten
Ursprung mit @since Unknown als Dokumentationslücke kennzeichnen. Keine
erfundenen Parameter und keine Kommentare, die nur Funktionsnamen wiederholen.
Bei Änderungen die betroffenen eigenen Verträge vollständig dokumentieren;
verbliebene Bestandslücken konkret erfassen. Das gilt auch für Backports.

URLs mit esc_url(), Attribute mit esc_attr(), Text mit esc_html() beziehungsweise
esc_textarea() ausgeben. Absichtlich zugelassenes HTML braucht einen eng
begrenzten wp_kses-Vertrag. Labels übersetzen, gespeicherte Werte nicht.
Native API-Formatvorlagen und Protokollserialisierung sind begrenzte Ausnahmen,
keine Erlaubnis für versteckte normale Seitenlayouts in lib/.

## Wiederverwendung, Performance und Security
Vor neuem Code bestehende Implementierungen und sämtliche betroffenen Aufrufer
prüfen. Bei gleichen fachlichen Verträgen tatsächlich eine gemeinsame generische
Implementierung verwenden. Unterschiede ausdrücklich begründen; Rechte- und
Providergrenzen nicht durch oberflächliche Ähnlichkeit verwischen.

Wiederholte Abfragen und I/O früh begrenzen. Batch-/Cache-Verträge und grosse
Fixtures beziehungsweise tatsächliche Abfragen prüfen. Aufgeräumter Quelltext
allein ist kein Performance-Nachweis. Rohgrösse, Transfer, Browser- und
Serverlaufzeit unterscheiden. Rollen, Objektberechtigungen, Eingaben, Escaping,
Geheimnisgrenzen und unklare Wiederholungen über die tatsächlichen Aufrufer
prüfen. Nonces ersetzen keine Objekt- oder nativen Erstellungsrechte.

## Frontend und Abhängigkeiten
Bootstrap und bestehende Theme-Komponenten verwenden. Gemeinsame konfigurierbare
Variablen und wo sinnvoll rem, em und Prozent verwenden. Eigene Icons über die
bestehenden Assetwege einbinden; Herkunft, Rechte und Nutzung dokumentieren.
Keine zweite parallele Layout- oder Komponentenwelt schaffen.

Keine in den aktuellen stabilen PHP-/WordPress-Versionen deprecated APIs neu
einsetzen. Mindestlaufzeit und tatsächliche Server-/Testlaufzeiten abgleichen;
erforderliche Laufzeitänderungen konkret abstimmen. Build und Watch müssen auf
den bestehenden CentOS-7-Systemen mit Node 14.21.3 und npm 6.14.18 laufen.
Moderne Testwerkzeuge dürfen davon getrennt in der CI laufen; sie dürfen
keine neue Laufzeit auf dem Buildserver voraussetzen.

Libraries nur mit belegtem Nutzen ergänzen. Vorhandene Lösungen, direkte und
transitive Abhängigkeiten, Herkunft, Lizenz, Pflege, Sicherheitslage und echten
Ladebedarf prüfen und dokumentieren. Lockfiles respektieren. Drittanbieter-
Originale und generierte Dateien nicht umformatieren oder eigenmächtig ändern.

## Gestaltung und Prüfumfang
Änderungen in der vollständigen Ansicht und im gesamten Ablauf beurteilen.
Vorhandenes zuerst vereinfachen oder wiederverwenden; zusätzliche Elemente
brauchen belegten Nutzen. Klare Hauptaktionen, ruhige Gestaltung, verständliche
Beschriftungen sowie sinnvolle Leer-, Fehler-, Lade- und grosse Datenzustände.
Für relevante UX-Entscheidungen aktuelle belastbare Forschung konsultieren;
Quelle, Prüfdatum und konkrete Anwendung dokumentieren. Heuristische Reviews
und technische Regressionen nicht als durchgeführte Nutzertests ausgeben.

Bei betroffenen Oberflächen gilt mindestens:
- macOS/Windows Desktop und Notebook: Chrome, Firefox, Safari auf macOS,
  Edge auf Windows; Maus und vollständige Tastaturbedienung;
- iPhone/iPad: Safari, Touch, Hoch- und Querformat;
- Android-Smartphone/-Tablet: Chrome, Touch, Hoch- und Querformat;
- 320, 390, 768 und 1440 CSS-Pixel sowie relevante Breakpoints.
Aktuelle stabile und im Betrieb relevante Versionen berücksichtigen. Ansicht,
Hauptablauf, Fokus, Dialoge, Navigation, lange Inhalte, Zoom und virtuelle
Tastatur je nach Gerät prüfen. Native Formular- und AJAX-Wege beide abdecken.

Nachweise nennen Version, Betriebssystem, Gerät oder Emulation, Viewport,
Ablauf und Ergebnis. Chromium ist kein Edge-Nachweis, WebKit kein echter
Safari-/iOS-Nachweis, schmaler Desktop kein Touch-Gerätetest. Fehlende
Prüfungen und erforderliche Abnahmen ausdrücklich offen ausweisen.

## Abnahme je Änderung
[ ] Konkrete Originalpaketänderungen freigegeben; Freigabeumfang dokumentiert.
[ ] Logik, Styling und reine Formatierung in passenden getrennten Commits.
[ ] Zweck, betroffene Aufrufer, Daten-/Berechtigungsgrenzen und Quellen erklärt.
[ ] Englische vollständige DocBlocks samt belegtem @since vorhanden.
[ ] README/Betriebsdokumentation und geänderte Verträge nachgeführt.
[ ] Tatsächlichen Loader und echte Templates nach Verlagerungen geprüft.
[ ] Betroffene Architektur-/Stilprüfungen und funktionale Regressionen ausgeführt.
[ ] Build und Fehler-/Wiederholungspfade bei betroffenen Änderungen geprüft.
[ ] Sicherheitskorrekturen mit tatsächlichem Aufrufweg und Negativfällen belegt.
[ ] Performance-Aussagen durch Messungen mit benannten Bedingungen belegt.
[ ] Relevante Browser-/Geräteprüfungen samt offenen Lücken dokumentiert.
[ ] Release enthält keine öffentlich ausführbaren Entwicklungsprogramme.
[ ] Keine produktiven Nachrichten, Kalenderänderungen, Zahlungen oder Druckjobs
    zu Testzwecken ausgelöst.
[ ] Kein zusätzliches ZIP für vollständig in Git enthaltene Änderungen erzeugt.

## Grenzen der Durchsetzung
Die alten Portaldateien ruleset.xml und ruleset-format.xml enthalten weiterhin
widersprüchliche Zwei-Leerzeichen-/PSR2-Regeln und sind keine geeignete Vorlage.
Massgeblich sind die oben genannten aktuellen Regeln und Tab-Konfigurationen.
Der aktive PHP-Formatter erzwingt innere Klammerabstände nicht vollständig.
Architektur- und Stilprüfungen decken nur ihre implementierten Teilregeln ab;
sie bestätigen keine vollständige rückwirkende Konformität. Auch der vorhandene
Portalcode enthält noch Dokumentationslücken. Geprüfte Testprogramme, lokale
Läufe, tatsächliche CI-Läufe und Produktionsabnahmen getrennt berichten.

