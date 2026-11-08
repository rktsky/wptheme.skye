# Übernommene Projekt-Learnings 3.5.0

Geprüft am 8. November 2026 gegen Skye 3.4.1, `9d96fc8`.
Der eigene Kandidat liegt auf `feature/project-learnings-3.5.0`; die
Laufzeitänderungen sind in Logikcommit `8ea3dae` und Stylecommit `277f7a5` getrennt.
Kundenprojekte wurden als Quellen gelesen; Änderungen erfolgen im eigenen
Skye-Quellstand. Core, Originalplugins und Abhängigkeiten behalten ihre Bytes.

## Übernommene Verträge

| Quelle | Generische Umsetzung | Nachweis |
| --- | --- | --- |
| Rocketsky: abgeschlossene semantische Yoast-Anbindung aus `f42cada` / Website 1.5.1 | Zwei ausdrückliche Projektverträge für gespeicherte öffentliche Inhalte und ungespeicherte Editorprojektionen. Der originale ACF-Collector bleibt vollständig verfügbar; feste Kundenfelder und Kartenmodelle bleiben im Projekt. | [Yoast-Anbindung](yoast-analysis.md) |
| Neue Skye-Quellenwahl aus den Quellen-/FormData-Learnings von Rocketsky und Speo | Native Speicherwege für beide Entwürfe, explizite Ausgabequelle, CSS-Sichtbarkeit nach Bereitschaft und enges ACF-Validierungsfenster. Die alte Whole-Builder-Conditional-Logic wird nicht kopiert. | [Quellenwahl](editor-sources.md) |
| Öffentliche Quellenprüfung im Skye-Kandidaten | Automatische Auszüge, native öffentliche REST-/Feed-Inhalte und Yoast-Auszugvariablen verwenden die aktive Quelle. Ein inaktiver ACF-Root wird vor der REST-Werteaufbereitung ausgeschlossen. | [Öffentliche Inhaltsquellen](public-content-sources.md) |
| Rocketsky: Crawleraudit `b754c7b26d323f9b24691626e5e6b97a0aea3712` | Begrenzter anonymer Audit mit ausdrücklicher Inventar-, Sprach-, Schema- und Textumfangsentscheidung. Keine feste Rocketsky-Organisation oder Projektsprache. | [Crawlerprüfung](crawler-audit.md) |
| Francesca: `7900e20ab505db296f31589e62b7e7b132fd4b30` | Frischer physischer POSIX-Pfadfastpath; bestehende Eingabe-, Root-, Descriptor- und Aliasgrenzen bleiben erhalten. | [Dateipfadprüfung](path-identity-fastpath.md) |
| Hostbliss: `d51020e72336e1a200f9df78f0b4819db2e804c8` | Enger Git-1.8-Fallback für den sauberen Releaseexport; unbekannte Antworten stoppen weiterhin. | [Releasekompatibilität](release-compatibility.md) |
| Speo: `16d91cf75443aaa1c34160a87228c45fead11138` | Interne Quelldokumentation vom Export ausschliessen und den Ausschluss zusätzlich im Publisher prüfen. | [Releasegrenze](release-compatibility.md) |
| Native Options-Batches aus Rocketsky/Francesca | Die bisherigen drei Skye-Einstellungen vor ihren normalen `get_option()`-Aufrufen über `wp_prime_option_caches()` laden. Defaults, Filter und gezielte Invalidierung bleiben nativ. | Native Queryprobe unten |

Die Quellen belegen jeweils ein Learning, nicht unveränderte universelle
Kundenimplementierungen. Die neue Yoast-Brücke verwendet **keine statischen
Leaf-Ausschlüsse**. Ein bereiter Quellencontroller begrenzt bereits den nativen
Beitrag auf die aktive Quelle. Ein optionaler synchroner Projektcallback kann
diese vollständig semantisch projizieren. Ohne bekannte Quellenbereitschaft
bleibt der vollständige native Beitrag erhalten; bei einem fehlerhaften Mapper
und bekannter Quelle deren nativer aktiver Beitrag. Der konsumierte
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
Builder. Diese kann ungespeicherte Inputs deaktivieren. Skye ersetzt diesen
Ansatz durch die [gemeinsame Quellenwahl](editor-sources.md): Die originalen
Kontrollen bleiben im Formular, bestehende TinyMCE-Werte werden synchronisiert,
und die Sichtbarkeit beginnt erst mit verifizierter Bereitschaft. Ein
unvollständiger inaktiver Builderzweig wird nur im bekannten nativen
Validierungsfenster ausgenommen und vor dem Speichern wiederhergestellt.
Frühere Fehler, Nonces, Rechte und die native Feldbeschränkung bleiben erhalten. Das
stellt den kurzzeitig abgeschirmten Requestzweig wieder her, nicht bereits
persistierte Coreänderungen. Core und ACF bleiben getrennte Speicherphasen;
ein negativer nativer CLI-Save bestätigte die
[Teilupdategrenze](editor-sources.md#grenze-bei-abgelehnten-speicheraufrufen).
Eine gemeinsame Transaktion oder allgemeine Rücknahme wird nicht behauptet.

Die neue Kopplung verwendet den originalen ACF-Collector für Builderwerte und
Core-Inhalt für den Editor. Unbekannte Layoutsemantik benötigt weiterhin eine
bewusste Projektprojektion. Sichtbarkeit, Speichern, Rendering und Analyse
werden als zusammengehöriger Ablauf geprüft.

### Anpassungen bestehender Projekte

Ein gebundener Builder publiziert bei leerem Inhalt oder fehlenden Komponenten
keinen aufbewahrten Editorentwurf mehr. Eine gültig gespeicherte Builderwahl gilt
auch bei verlorenem Feldprofil oder fehlendem Provider. Nicht verwaltete alte
Templates mit unbekannter Anbindung behalten ihren früheren nativen Fallback.
Diese Grenze verlangt beim Merge eine Prüfung der bisherigen leeren Builderfälle.

Automatische Builderauszüge sowie native öffentliche REST-/Feed-Inhalte benötigen
den semantischen PHP-Callback `skye_yoast_analysis_content`. Ohne gültige
Projektion bleiben diese Exporte leer. Den Callback mit den tatsächlichen Layouts
vor dem Merge prüfen. Zwischen seinen HTML-Blöcken muss Leerraum erhalten bleiben,
damit native Textableitungen benachbarte Absätze nicht zusammenziehen. Manuelle
Auszüge, explizite SEO-/Social-Beschreibungen und autorisierte native REST-Edit-Leser
behalten ihre nativen Verträge.

`components/page/content.php` wird zum gemeinsamen Dispatcher.
`components/page/native.php` enthält die reine Core-Inhaltsausgabe. Bestehende
Overrides müssen ihren tatsächlichen Zweck an diesem Vertrag ausrichten;
ein rekursiver Rückruf zum Dispatcher ist kein nativer Fallback. Alte eigene
Quellencontroller und Leaf-Ausschlüsse nicht parallel weiterbetreiben.

Es wurden keine Kundenmetadaten, Scorewerte, Canonicals, Schema-Angebote oder
`llms.txt`-Einstellungen kopiert. Die Seiten- und Sprachkonfiguration gehört
weiterhin in die jeweilige Installation.

## Lokaler Prüfstand

Der aktuelle Kandidat wurde in getrennten lokalen Quellclones mit originalem
WordPress 7.1.4, PHP 8.3.36 und SQLite Integration 3.1.3 geprüft. Mail und externe
WordPress-HTTP-Anfragen waren in den nativen Fixtures gesperrt.

| Prüfung | Ergebnis |
| --- | --- |
| JavaScript, Build und Release | 54 Tests bestanden: 24 Quellencontroller-VM-, 13 Yoast-VM-, 4 Navigations-, 1 Critical-Aggregat mit 70 Assertions, 4 Build- und 8 Releasefälle. |
| PHP-Runner mit Core ohne aktives ACF | 21 getrennte Prozesse bestanden. |
| ACF PRO 6.8.11 / Yoast 28.8 / ACF Content Analysis 3.2 | 41 Quellen-, 58 Yoast- und 57 öffentliche Quellenassertions bestanden. |
| Original ACF Free 6.8.11 / Core | Nach nativer Aktivierung und Versionsnachweis in einem frischen Request: 7 Quellen- und 41 öffentliche Quellenassertions bestanden. |
| Build | Node 14.21.3/npm 6.14.18 erzeugte 31 Assets aus dem gelockten Graphen; der moderne Testharness lief separat. |
| Quellverträge und Formatierung | 120 Dateien ohne Architektur-/Vertragsbefund; 119 Dateien ohne erforderliche Formatterkorrektur. Syntax-, LF- und Whitespaceprüfungen bestanden. |
| Crawleraudit | 37 Offlinefälle bestanden. |

Die Releasefälle verwenden tatsächliche Git-, Compiler- und Exportaufrufe.
Nur die belegten Git-1.8-Antworten werden emuliert. Die VM-Fälle bleiben von
nativen Browser- und Providerprüfungen getrennt.

Die Settings-Fixture zählte drei kalte Einzelabfragen und eine native
Batch-Abfrage für dieselben drei nicht automatisch geladenen Werte. Warme Reads,
native Aktualisierung und gezielte Entfernung wurden ebenfalls geprüft.
Core speichert skalares `false` beim Optionsschreiben als leere Zeichenfolge;
die Erwartung folgt dieser nativen Regel.

### Browser mit Originalplugins

Der lokale Browserlauf verwendete den Quellencontroller `7f0adfc0` und die
Originalprovider. Beide Quellen wurden ungespeichert geändert, umgeschaltet,
über echte Publish-/Update-Aufrufe gespeichert und wieder gelesen. Native
FormData enthielt beide Entwürfe. Ein unvollständiger inaktiver Builder blieb
erhalten; seine Aktivierung sowie fremde HTML5-Constraints und ein früherer
ACF-Fehler wurden separat geprüft.

Der originale Yoast-Worker zählte 12 Wörter im Builder, 13 nach dessen
ungespeicherter Änderung und 1 im Standardeditor. Eine gültige registrierte
Projektsemantik war in dieser Kontrolle enthalten. Öffentliche Metadaten und
REST-Inhalte wurden auf die aktive Quelle geprüft. Der autorisierte native
Edit-Aufruf antwortete mit 200, der anonyme mit 401.

Der Chooser passte bei 320, 390, 768 und 1440 Pixeln in die emulierten
Desktop-Viewports. Pfeiltasten wechselten in beide Richtungen bei erhaltenen
Werten. Ohne tatsächliches Controllerasset fehlte der Ready-Marker, beide
Oberflächen blieben sichtbar, reale Controls aktiv und Clone-Controls nativ
deaktiviert. Nach Entfernung der eigenen Fixtureflagge und Reload war die
Editorquelle wieder korrekt bereit. Bei 320 Pixeln bleibt ein 9-Pixel-Überlauf
der nativen Toolbar; der Quellenchooser liegt innerhalb des Viewports.
Die Nachweise liegen in `browser-fallback-responsive-final.json` und
`editor-source-final.png` im Belegverzeichnis.

Ein direkter POST ohne vorherige AJAX-Prüfung kann Coreinhalt bereits schreiben,
bevor ACF ablehnt. Die [Teilupdategrenze](editor-sources.md#grenze-bei-abgelehnten-speicheraufrufen)
bleibt auch ohne JavaScript bestehen. Eine gemeinsame Transaktion und eine
vollständige No-JavaScript-Browserabnahme werden daraus nicht abgeleitet.

### Herkunft und offene Abschlussarbeiten

Die privaten Fixtures verwendeten getrennte Providerinstallationen. Alle
1949 Yoast- und 627 ACF-Free-Dateien stimmen mit WordPress.org-Prüfsummen überein.
Beim ACF-Add-on stimmen 78 von 80 Dateien dort überein; die abweichenden
`readme.txt` und `yoast-acf-analysis.php` wurden vor dem Laden zusätzlich
bytegleich gegen den offiziellen SVN-Tag 3.2 geprüft. Die 721 ACF-PRO-Dateien
waren bytegleich mit der unveränderten Rocketsky-Referenz; ein unabhängiger
Abgleich mit einem kommerziellen Herstellerarchiv liegt nicht vor. Keine
Providerdatei wurde für eine passende Prüfsumme oder Versionsgrenze geändert.
Die bereinigten Einzelbelege
liegen ausserhalb des Repos unter `outputs/skye-project-learnings-20261108/`.

GitHub Actions bestand bei `0be2590` mit allen neun Jobs. Dieser frühere Lauf
deckt die neuen Module im aktuellen Endstand nicht ab. Die finale CI, ein
sauberer Export des abschliessenden Commits und die Übernahme in `master`
sind noch offen. Der finale Zusammenfassungsbeleg
wird an diese tatsächlichen Quellstände gebunden.

Die Dateipfad-Microbench belegt keinen bestimmten Seitengewinn. Neue
FPM-/PageSpeed-Messungen und eine physische Geräteabnahme wurden für dieses
Basistheme nicht durchgeführt.
