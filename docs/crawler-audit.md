# Öffentliche SEO- und Crawlerprüfung

`tools/audit-crawlers.py` prüft aktuelle öffentliche URLs über anonyme GETs.
Das Werkzeug ändert weder WordPress noch Indexierung oder Botregeln. Es läuft
ausserhalb des Webroots; Werkzeuge und Berichte gehören nicht ins Theme-Release.
Es benötigt Python ab 3.9 mit Standardbibliothek. Die Website selbst benötigt
dadurch keine Python-Laufzeit.

## Inventar und Projektbedingungen

Ein frisches natives WordPress-Inventar soll alle veröffentlichten öffentlichen
Seiten, Post Types und relevanten Archive enthalten. Eine alte Importdatei oder
die Navigation allein belegt keine Vollständigkeit. Beispiel für ein eigenes
Inventar; die Adressen sind ausdrücklich Platzhalter:

```json
{
  "schema_version": 1,
  "base_url": "https://example.test/",
  "captured_at": "2026-11-08T09:00:00Z",
  "pages": [
    {"url": "https://example.test/", "expected_language": "en"},
    {"url": "https://example.test/de/", "expected_language": "de-CH"},
    {"url": "https://example.test/result/", "expected_indexable": false}
  ],
  "archives": []
}
```

```sh
python3 tools/audit-crawlers.py \
  --base-url https://example.test/ --mode staging \
  --inventory /private/current-inventory.json \
  --expected-language en --minimum-main-characters 200 \
  --required-schema-type Organization \
  --output /private/new-crawler-report.json
```

Sprache, Textumfang und Schematyp im Beispiel sind Projektentscheidungen.
Ohne diese Flags prüft das Werkzeug eine vorhandene HTML-Sprache und nichtleeren
serverseitigen Hauptinhalt; es verlangt keinen bestimmten JSON-LD-Entitätstyp.
`expected_language` im einzelnen Inventareintrag hat Vorrang vor dem globalen
Sprachflag. Schematypen lassen sich mehrfach angeben, etwa `Person` bei einem
passenden persönlichen Auftritt. Das Werkzeug erfindet keine Organisation,
Angebote, Inhalte oder Mindesttextlänge für ein Projekt.

Stage erfordert ein ausdrückliches Inventar und behält `noindex`. Im Modus
`live` ist zusätzlich eine Entdeckung über die native Sitemap möglich. Diese
findet keine Seiten, die bereits in der Sitemap fehlen. Für eine vollständige
Abnahme deshalb auch auf Live ein aktuelles natives Inventar vergleichen.

## Umfang und Grenzen

Ein Lauf prüft höchstens 500 URLs für sechs feste Requestprofile: gewöhnlicher
Browser, Googlebot, bingbot, OAI-SearchBot, Claude-SearchBot und PerplexityBot.
Maximal zwei Worker laufen gleichzeitig. Sitemapentdeckung ist auf 16 Dokumente
und Antworten auf zwei Millionen Bytes begrenzt. Query-, Credential- und
Fragment-URLs werden weder als Inventar akzeptiert noch über Redirects verfolgt.
Off-origin-Redirects bleiben gesperrt. HTTP ist nur für ausdrücklich lokale
Loopback-QA zulässig; sonst ist eine HTTPS-Originwurzel erforderlich.

Geprüft werden unter anderem Status, HTML-Typ, H1, Sprache, Titel,
Beschreibung, Canonical, Open Graph, gültiges JSON-LD, native Robotsregeln,
Snippetfreigabe und die externe Linkpolicy. Direkte Sitemap-`loc`-Einträge werden
von Bild-/Video-Erweiterungen unterschieden. Stage-Sitemap-404 ist eine
Beobachtung. Ein echter Seiten-404 benötigt keinen zusätzlichen `noindex`-Tag.
Fehlende interne Sprungziele sind Fehler; mögliche verwaiste Seiten und weitere
Links ausserhalb des Inventars sind Beobachtungen. Formulare werden nur gezählt.
Das Werkzeug sendet keine Formulare ab und folgt keinen weiteren Inhaltslinks.

Die JSON-Ausgabe enthält öffentliche Metadaten, reduzierte Linkziele und
Inhaltshashes, keine vollständigen HTML-Antworten, Cookies oder HTTP-Rohfehler.
URL-Queries und Fragmente werden aus Linknachweisen entfernt. Einen neuen
Report legt es exklusiv mit Modus `0600` an. Vorhandene Dateien und Symlinks
führen vor den Requests zum Abbruch; sie werden nicht überschrieben.

Gleiche Metadaten für die Requestprofile sind ein Prüfkriterium. Unterschiede
im Haupttext werden separat ausgewiesen: etwa zeitabhängige öffentliche
Kennzahlen können zwischen GETs wechseln. Ein bestandener Lauf beweist weder
identisches Gesamtdokument noch Zugang aus echten Bot-IP-Netzen, Aufnahme in
Suchindizes, Rankings oder KI-Zitate. Training-Crawler-Präferenzen werden
getrennt von Such-Crawlern beobachtet. Änderungen an WAF oder Botregeln gehören
nicht zum Werkzeug. Fehlende `llms.txt`-, MCP- oder A2A-Dienste werden nicht als
allgemeiner Fehler eines normalen Websiteprojekts erfunden.

## Herkunft und Prüfung

Quelle ist Rocketsky Website, Commit
`b754c7b26d323f9b24691626e5e6b97a0aea3712`, dort
`tools/audit-crawlers.py` und `tools/test-audit-crawlers.py`. Skye ersetzt die
Rocketsky-spezifische Sprach-, Organisations- und Textumfangserwartung durch die
oben genannten ausdrücklichen Projektbedingungen. Die vorhandenen 30
Offlineregressionen bleiben erhalten; sieben zusätzliche Fälle prüfen
mehrsprachige Inventare, Person-Schema, kurze Texte, ungültige Projektflags,
falsche Inventartypen und den Schutz bestehender Reportdateien.

```sh
python3 tools/test-audit-crawlers.py
```

Die 37 Tests verwenden synthetische Antworten und native Parser ohne Netzwerk.
Sie sind kein durchgeführter Websitecrawl, WordPress-Editor- oder Geräteabnahme.
Die Primärquellen im Werkzeug behandeln das
[Sitemap-Protokoll](https://www.sitemaps.org/protocol.html),
[Googles technische Suchanforderungen](https://developers.google.com/search/docs/essentials/technical)
und [Robots-Metadaten](https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag)
sowie die getrennten Anbieter-Crawlerverträge. Deren konkrete Fassung muss bei
einer späteren Projektabnahme erneut geprüft werden.
