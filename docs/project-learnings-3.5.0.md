# Übernommene Projekt-Learnings 3.5.0

Geprüft am 8. November 2026 gegen Skye 3.4.1, `9d96fc8`.
Der eigene Kandidat entsteht auf `feature/project-learnings-3.5.0`.
Kundenprojekte wurden als Quellen gelesen; Änderungen erfolgen im eigenen
Skye-Quellstand. Core, Originalplugins und Abhängigkeiten behalten ihre Bytes.

## Übernommene Verträge

| Quelle | Generische Umsetzung | Nachweis |
| --- | --- | --- |
| Rocketsky: abgeschlossene semantische Yoast-Anbindung aus `f42cada` / Website 1.5.1 | Zwei ausdrückliche Projektverträge für gespeicherte öffentliche Inhalte und ungespeicherte Editorprojektionen. Der originale ACF-Collector bleibt vollständig verfügbar; feste Kundenfelder und Kartenmodelle bleiben im Projekt. | [Yoast-Anbindung](yoast-analysis.md) |
| Rocketsky: Crawleraudit `b754c7b26d323f9b24691626e5e6b97a0aea3712` | Begrenzter anonymer Audit mit ausdrücklicher Inventar-, Sprach-, Schema- und Textumfangsentscheidung. Keine feste Rocketsky-Organisation oder Projektsprache. | [Crawlerprüfung](crawler-audit.md) |
| Francesca: `7900e20ab505db296f31589e62b7e7b132fd4b30` | Frischer physischer POSIX-Pfadfastpath; bestehende Eingabe-, Root-, Descriptor- und Aliasgrenzen bleiben erhalten. | [Dateipfadprüfung](path-identity-fastpath.md) |
| Hostbliss: `d51020e72336e1a200f9df78f0b4819db2e804c8` | Enger Git-1.8-Fallback für den sauberen Releaseexport; unbekannte Antworten stoppen weiterhin. | [Releasekompatibilität](release-compatibility.md) |
| Speo: `16d91cf75443aaa1c34160a87228c45fead11138` | Interne Quelldokumentation vom Export ausschliessen und den Ausschluss zusätzlich im Publisher prüfen. | [Releasegrenze](release-compatibility.md) |
| Native Options-Batches aus Rocketsky/Francesca | Die bisherigen drei Skye-Einstellungen vor ihren normalen `get_option()`-Aufrufen über `wp_prime_option_caches()` laden. Defaults, Filter und gezielte Invalidierung bleiben nativ. | Native Queryprobe unten |

Die Quellen belegen jeweils ein Learning, nicht unveränderte universelle
Kundenimplementierungen. Die neue Yoast-Brücke verwendet insbesondere **keine
statischen Leaf-Ausschlüsse**: Erst ein tatsächlich bereiter synchroner
Projektcallback ersetzt die vollständige Analysequelle. Ohne ihn und bei
Fehlern bleibt der native kombinierte Beitrag erhalten. Der konsumierte
ACF-Collector ist an seinen gelesenen 3.2-Scriptvertrag gebunden.

## Weitere gesichtete Projekte

| Projekt / geprüfter Stand | Ergebnis |
| --- | --- |
| Volksgarage Website, `6fecb117677f192bba8763a3965e40f3a1fd5497` | Native Assetgraphen, Formcontrols, Mobile-/Critical-CSS und Medienpipeline sind bereits Skye-Grundlage. Neuere uncommittete Gestaltung war keine Backportquelle. |
| Volksgarage Portal, `de041627b2e41d03678ea1a4ccb92a5434828980` | Komponentenargumente, Coding-/Dokumentationsstandards und Options-/Menügrenzen sind schon übernommen. Rechnungen, Lager und Kalender bleiben Fachlogik. |
| Konsulenten, `263e5d6d51d24727a310136720f050cdda98ba3d` | Statische Auslieferung, WebP-Fallbacks und externe Linkpolicy sind funktional abgedeckt. Porträtgeometrie, Sprachmenüs und die Entscheidung gegen die dortige Asset-Domain bleiben projektspezifisch. |
| Speo, beim Audit `d008d82a0cdecdc15f4e0745491e55840cbc9240` | Ausser der Exportgrenze ist Thumbnail-Batching ein nützliches Collection-Learning. Wissensbereich, Commerce-Publisher und Sprachinhalte bleiben im Projekt. Parallele Footerarbeit wurde nicht als abgenommen behandelt. |
| Skye-Website, `40dc6f6cce38dad51e11dae0cf692403a9a715d8` | Die Body-Fonthint-Auswahl nutzt schon die vorhandene Basis-API. Ein dekorativer Vogel und dessen Animation begründen keine universelle Bild- oder Fontentscheidung. Redis und Matomo bleiben separate Betriebsintegrationen. |
| Hostbliss, Rich-Text-Verträge aus `298ee42` / `e33bf1b` | Ein gemeinsames begrenztes Fragment für Frontend und Analyse verhindert abweichende Texte. Kein neuer globaler Sanitizer: vorhandene Rich-Text- und `the_content`-Verträge dürfen nicht pauschal enger werden. |
| Francesca, frühe Matomo-Optionsabfrage und Delivery Health | Provideroptionen müssen teilweise vor dem Themeboot geprimt werden. Ein später Themehook wäre wirkungslos. Die dortigen SQL-/Mutex-/CAS-Verträge sind kein ungeprüfter Themebackport und keine Freigabe für neue eigene Datenbankabfragen. |

Für begrenzte sekundäre Kartenabfragen ist Speos Verwendung von
[`update_post_thumbnail_cache( $query )`](https://developer.wordpress.org/reference/functions/update_post_thumbnail_cache/)
aus `e039a594d6d2d6730f56671063aa4ed2da870699` übertragbar. Die query-eigenen
Post-/Metadatencaches vorher nativ primen. Skyes gewöhnlicher Hauptloop braucht
dafür keinen zusätzlichen Wrapper: Core primt seine Thumbnails bereits beim
nativen Thumbnailaufruf im Loop. Die Queryersparnis des Kundenprojekts wird
nicht als neuer Skye-Seitenwert ausgegeben.

## SEO-Abnahme und Quellenwahl

Die neue [SEO-Abnahme](seo-acceptance.md) hält die praktischen Learnings fest:
Originales ACF-/Yoast-Setup, tatsächliche aktive Quelle, einzelne native
SEO-/Lesbarkeitsmeldungen, Social-Bilder, aktuelle Inventare, Sitemaps und
geschützte Stage-Discovery.

Eine ältere Rocketsky-Umschaltung verwendet ACF-Conditional-Logic am gesamten
Builder. Ihr damaliger Nachweis zeigt nicht den später verlangten Ablauf mit
beidseits ungespeicherten Änderungen und nativem Speichern. Diese UI wird
deshalb nicht übernommen. Die Basis liefert eine Analyseintegration, keinen
universellen verlustfreien Builderumschalter für unbekannte Feldgruppen.

Es wurden keine Kundenmetadaten, Scorewerte, Canonicals, Schema-Angebote oder
`llms.txt`-Einstellungen kopiert. Die Seiten- und Sprachkonfiguration gehört
weiterhin in die jeweilige Installation.

## Ausgeführte lokale Prüfungen

Der integrierte Laufzeitkandidat `c1db29d` wurde in einem separaten lokalen
Quellclone mit originalem WordPress 7.1.4, PHP 8.3.36 und SQLite Integration
3.1.3 geprüft. Externe WordPress-HTTP-Anfragen und Mailtransporte sind in dieser
Fixture gesperrt. Die folgenden Angaben unterscheiden die Prüfarten:

- Die vollständige PHP-Liste besteht in **19 getrennten Prozessen**. Darunter
  sind vorhandene Medien-, Form-, statische/Critical-Auslieferungs-, Branding-
  und Navigationsfälle sowie die neuen Dateipfad- und Analyseprüfungen.
- Die neue native Yoast-Gruppe besteht ohne Originalprovider mit 39 Assertions.
  Nach nativer Aktivierung von originalem Yoast 28.8 und ACF Content Analysis 3.2
  besteht sie mit 41, einschliesslich Yoasts echter Bildsuche. ACF PRO und ein
  echter redaktioneller ACF-/Yoast-Browserworker wurden dabei nicht installiert
  oder als geprüft ausgegeben.
- Die Settings-Fixture zählt drei echte kalte Einzelabfragen und eine echte
  native Batch-Abfrage für dieselben drei nicht automatisch geladenen Werte.
  Warme Reads, native Aktualisierung und anschliessende gezielte Entfernung
  bestehen ebenfalls. Core speichert skalares `false` beim Optionsschreiben
  als leere Zeichenfolge; die kalte Erwartung berücksichtigt dies.
- Sieben JavaScript-VM-Gruppen führen die Produktionsquelle aus. Sie prüfen
  die geordnete API, aktuelle Eingänge, ungespeicherte Änderungen, gebündelten
  Refresh und Rückfälle; sie ersetzen keinen nativen Yoast-Worker.
- 37 Offline-Crawlerfälle und acht tatsächliche Releaseexportfälle bestehen.
  Der Legacy-Git-Teil emuliert nur die belegten alten Antworten; die sonstigen
  Git- und Compileraufrufe sind echt.
- Node 14.21.3/npm 6.14.18 installiert den unveränderten Lockgraphen und baut
  28 Assets. Der getrennte JavaScript-Testharness verwendet Node 24.22.1.
  Architektur-, PHP-Syntax-, Format- und Whitespaceprüfungen bestehen.

Die Originalprovider kamen als getrennte offizielle Archive in die private
Fixture. Alle 1949 Yoast-Dateien stimmen mit WordPress.org-Prüfsummen überein.
Beim ACF-Add-on stimmen 78 Dateien dort überein; die abweichenden
`readme.txt` und `yoast-acf-analysis.php` wurden vor dem Laden zusätzlich
bytegleich gegen den offiziellen SVN-Tag 3.2 geprüft. Es wurde keine Datei
angepasst, um eine Prüfsumme oder eine Versionsgrenze passend zu machen.
Der bereinigte Beleg liegt ausserhalb des Repos unter
`outputs/skye-project-learnings-20261108/`.

Der vollständige GitHub-Lauf und der abschliessende Export werden nach ihrer
Ausführung hier ergänzt. Die lokale Microbench der Dateipfadprüfung belegt
keinen bestimmten Seitengewinn. Ein neuer FPM-/Lighthousewert und eine neue
physische Geräteabnahme wurden für dieses Basistheme nicht erhoben.
