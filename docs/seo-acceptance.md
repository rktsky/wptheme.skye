# SEO-Abnahme eines Skye-Projekts

Skye stellt eine gemeinsame [Quellenwahl](editor-sources.md), deren
[Yoast-Anbindung](yoast-analysis.md) und optionale semantische Projektionen
bereit. Die Website erhält ihre Suchmetadaten, Sprachen und Inhalte im jeweiligen
WordPress-Projekt. Ein Themeupdate legt keine Fokusphrasen fest, erzeugt keine
Bewertungen und ändert keine Indexierung.

## Originalplugins und Inhaltsquelle

Yoast SEO und **ACF Content Analysis for Yoast SEO** werden bei ACF-Projekten als
separate Originalplugins installiert und aktualisiert. Ihre Versionen und der
tatsächliche Analyseweg gehören in den Projektnachweis. Skye enthält keine
Plugin-Kopie und aktiviert keine Plugins selbst.

Im Classic Editor muss Yoast den Inhalt beurteilen, der auch auf der Seite
erscheint. Ein Seitenbaukasten benötigt dafür eine zum Layout passende Projektion:
Überschriften mit ihrer echten Ebene, Absätze, Links, sichtbare ausgewählte Karten
und Bilder mit ihren tatsächlichen Alternativtexten. Interne IDs, API-Felder,
inaktive Entwürfe und versteckte Einstellungen sind kein Seiteninhalt. Schon
vorhandene Projektadapter vor einer neuen Anbindung prüfen; Inhalte nicht zweimal
beitragen. Der native ACF-Collector bleibt erhalten. Die bereite Quellenwahl entfernt nur
den inaktiven Core-Eingang beziehungsweise dessen ACF-Append; unbekannte
Layoutsemantik wird dadurch noch nicht als vollständig abgebildet behauptet.

Skyes Quellenwahl bewahrt beide Fassungen in ihren ursprünglichen Speichern
und verwendet nur die gewählte Quelle für Rendering und Analyse. Beim Merge
die echten Rootkeys, Feldgruppen und Komponentenüberschreibungen an den
[Quellenvertrag](editor-sources.md) anbinden. Ganze Wurzelbaukästen dürfen nicht
mehr per Source-Conditional-Logic deaktiviert werden.

Für die Abnahme beide Quellen ungespeichert ändern, wechseln, über das native
Formular mit gültiger Nonce speichern, neu laden und beide neuen Werte prüfen.
Dabei auch den visuellen TinyMCE-Editor verwenden. Ein unvollständiger inaktiver
Builderentwurf muss erhalten bleiben; seine Aktivierung muss wieder die normale
Validierung auslösen. Frühere fremde Fehler, falsche Nonces, fehlende Rechte und
unbekannte Callbackfenster dürfen nicht durch die Ausnahme verschwinden. Ohne
eigenen bereiten Controller bleiben beide Oberflächen sichtbar und die native
Feldprüfung aktiv.

Auch einen abgelehnten Save getrennt prüfen. Core kann bei einem direkten
POST oder ohne AJAX-Vorprüfung bereits `post_content` speichern, bevor ACF seine
Felder ablehnt. Eine Fehlermeldung belegt keine gemeinsame Rücknahme. Die
[Core-/ACF-Teilupdategrenze](editor-sources.md#grenze-bei-abgelehnten-speicheraufrufen)
gilt zusätzlich zum Erhalt der ungespeicherten Inputs. In der negativen
Browserprobe nachweisen, dass die native AJAX-Prüfung den normalen POST stoppt,
und anschliessend die tatsächlichen Werte beider Speicher sowie die
Quellenauswahl kontrollieren.

Ausdrücklich gespeicherte Builderwahl mit leerem Inhalt beziehungsweise
entferntem Feldprofil separat prüfen: Der aufbewahrte Editor darf dabei nicht
öffentlich werden. Die reine native Inhaltskomponente ist ab 3.5
`components/page/native.php`; `components/page/content.php` ist der Dispatcher.

Vor dem Merge den semantischen PHP-Callback `skye_yoast_analysis_content` mit den
tatsächlichen Layouts prüfen. Die [öffentliche Quellenabgrenzung](public-content-sources.md)
verwendet ihn auch für automatische Builderauszüge, native öffentliche
REST-Inhalte und Feedvolltext. Ohne gültige Projektion bleiben diese
Builderexporte leer. Die Antworten nach einem Quellenwechsel und bei fehlendem
Mapper getrennt zurücklesen; auch Yoasts automatische Auszugvariable prüfen.
Manuelle Auszüge, explizite SEO-/Social-Beschreibungen und autorisierte native
REST-Edit-Leser müssen ihre nativen Werte behalten. Ein erfolgreicher
Editoranalyse-Test belegt diese Exportwege nicht.

Yoasts PHP-Analysefilter dient auch der Bildsuche für Social-Metadaten. Nach einer
Projektion deshalb Open Graph, Twitter, Beitragsbild und explizite Social-Bilder
erneut prüfen. Eigene Adapter beobachten die nativen Providerobjekte und verändern
keinen ORM-Zustand, um Abfragen oder Meldungen zu verstecken. Gemeinsame Firmen-
oder Kartenmodelle erst laden, wenn die tatsächliche Sektion sie benötigt. Nach
einem echten Quellenwechsel ausserdem die daraus abgeleiteten Metadaten erneut
lesen. Der Source-Marker wird nach dem nativen ACF-Save gespeichert; Yoast 28.8
hängt seinen Post-Indexer an `wp_insert_post`, nach Cores `save_post`. Diese
Quellenreihenfolge allein ersetzt keinen Save-/Readback-Nachweis. Ein manueller
Eingriff in Yoasts ORM darf einen veralteten Ableitungszustand nicht verdecken.

## Redaktion und technische Prüfung

Das aktuelle veröffentlichte Inventar mit allen öffentlichen Post Types und
sinnvollen Archiven aus WordPress ermitteln. Sprachfassungen, Navigation und
Sitemaps vergleichen; eine alte Importdatei genügt nicht. Für jede gewünschte
Seite Titel, Beschreibung, passende Fokusphrase und tatsächliche Analyse prüfen.
Auch die einzelnen roten und orangen SEO- und Lesbarkeitsmeldungen öffnen, die
asynchrone Analyse abwarten, nativ speichern und nach dem Neuladen kontrollieren.
Ein grüner Gesamtindikator allein schliesst einzelne Hinweise nicht aus.

Empfehlungen anhand des sichtbaren Inhalts bearbeiten. Keine unsichtbaren Texte,
Keyword-Wiederholungen oder erfundenen Bildbeschreibungen einsetzen. Eine fachlich
ungeeignete Meldung konkret benennen und begründen; sie bleibt im Nachweis offen.
Scores niemals direkt in Metadaten schreiben. Ein Neuaufbau der Yoast-Indexables
ersetzt die tatsächliche Editoranalyse nicht.

Für einfache ACF-Überschriften zuerst den öffentlichen Add-on-Filter
`Yoast\WP\ACF\headlines` prüfen: Er ordnet tatsächlichen Feldschlüsseln ihre
Headingebene zu. Namens- und Typsperren sind andere Verträge. Solange die native
Sammlung den sichtbaren Inhalt richtig erfasst, braucht es keine eigene Projektion.

Organisation oder Person, wichtige Seiten, Titelvorlagen und Archiveinstellungen
in Yoast anhand der realen Website einrichten. Ausserdem Status, HTTPS-Ziel,
Canonical, Sprache, Headingstruktur, sichtbare Inhalte, interne Links und die
verbundenen JSON-LD-Knoten im anonymen Server-HTML prüfen. Schema beschreibt
belegte Inhalte; Angebote, Bewertungen und FAQ-Berechtigungen nicht erfinden.
Private Ergebnisse und Hilfsseiten behalten ihre Zugriffsschranken und gehören
nicht in öffentliche Sitemaps. Nach relevanten CMS-Änderungen den nativen
Speichervorgang und den tatsächlichen Sitemap-Eintrag prüfen.

Der [Crawleraudit](crawler-audit.md) vergleicht die öffentlichen Antworten mit
mehreren Such-Crawler-Kennungen. Ein solcher GET belegt keinen Besuch aus dem
echten Botnetz und keine Aufnahme in einen Suchindex. Bestehende Daten aus Search
Console oder Bing Webmaster ergänzen die öffentliche Prüfung, wenn verfügbar.
Performanceprofile und sämtliche vorab festgelegten Messungen separat behalten.

## Optionale Discovery-Dateien

Bei Bedarf Yoasts native `llms.txt`-Funktion und die realen wichtigen Seiten prüfen.
Keinen zweiten Themegenerator neben dem Provider betreiben. Fehlende Seitentypen
bleiben unbesetzt. Ein physisches Textfile übernimmt WordPress' HTML-`noindex`
nicht automatisch: seine wirkliche Stage-Antwort prüfen und die optionale Ausgabe
ohne gleichwertigen Schutz dort wieder deaktivieren. Live separat abnehmen.

`llms.txt` ersetzt weder Robotsregeln noch Sitemaps oder brauchbares Server-HTML.
Google verlangt keine besondere KI-Datei. Interne `AGENTS.md`-Dateien bleiben im
Quellrepo. MCP-/A2A-Endpunkte nur für tatsächlich angebotene Dienste einrichten.
Such-Crawler und Trainings-Crawler sind getrennte Entscheidungen; eine SEO-Arbeit
ändert keine bisherigen Trainingspräferenzen.

Grundlagen, am 8. November 2026 geprüft:
[Yoast-Analyse-API](https://developer.yoast.com/customization/yoast-seo/adding-custom-data-analysis/),
[native Yoast-Discovery](https://developer.yoast.com/features/llms-txt/functional-specification/)
und [Googles KI-Suchhinweise](https://developers.google.com/search/docs/appearance/ai-features).
Die [Projektprüfung 3.5.0](project-learnings-3.5.0.md) nennt Herkunft und Grenzen.
