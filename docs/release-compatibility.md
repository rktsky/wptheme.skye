# Releaseexport mit älterem Git

Build und Watch bleiben bei Node 14.21.3 und npm 6.14.18. Der saubere
Releaseexport verwendet zusätzlich Git. Das auf CentOS 7 vorhandene Git 1.8
kennt `rev-parse --git-path info/attributes` noch nicht. `tools/release.mjs`
erkennt ausschliesslich dessen belegte Antworten und liest dann den tatsächlichen
Gitordner über `rev-parse --git-dir`. Die beiden Diagnoseschritte verwenden
explizit die C-Locale. Andere Fehler bleiben Fehler.

Moderne Git-Versionen behalten `--git-path`, insbesondere für verknüpfte
Worktrees. Ein leerer, mehrzeiliger, optionsähnlicher oder anders geformter
Pfad wird verweigert. Die Legacy-Gitordnerantwort muss ein bestehendes
Verzeichnis bezeichnen. Der Statusaufruf verwendet `--porcelain`, dessen
stabiles Format auch ältere Versionen unterstützen.

Uncommittete Änderungen, ignorierte Buildinputs und lokale `info/attributes`
stoppen weiterhin vor der Veröffentlichung. Lizenz-, Fingerprint-,
Quellidentitäts- und Manifestprüfungen bleiben erhalten. Es werden keine
Originalpakete verändert und keine Schutzprüfung wegen des Git-Alters übersprungen.

## Quellunterlagen bleiben ausserhalb des Webroots

`.gitattributes` schliesst zusätzlich `/docs` vom Git-Export aus. Der Publisher
verweigert einen Export, der diesen Ordner trotzdem enthält. Repository-README,
Lizenz und die verifizierten Laufzeitdateien bleiben Bestandteil des Themes.
Interne Prüfunterlagen, lokale Anweisungen und Entwicklungsprogramme werden
im Quellrepository aufbewahrt.

## Herkunft und Nachweise

Der enge Legacy-Resolver stammt aus Hostbliss-Commit
`d51020e72336e1a200f9df78f0b4819db2e804c8`. Dort wurde der tatsächliche Stagehost
mit Git 1.8.3.1 geprüft. Der Ausschluss der Quelldokumentation stammt aus Speos
`16d91cf75443aaa1c34160a87228c45fead11138`; der damalige HTTP-Nachweis bestätigte
`docs/security.md` nach dem bereinigten Export mit 404. Diese historischen
Kundennachweise werden nicht als neuer Skye-Hosttest ausgegeben.

`tests/release.test.mjs` startet den wirklichen Releasecompiler in eigenen
Git-Fixtures. Zusätzliche Fälle liefern nur für die konkrete Attributes-Abfrage
die belegten Legacy-Antworten; alle übrigen Befehle laufen über echtes Git.
Erfolgreiche Exporte, eine verweigerte lokale Attributes-Datei, unbekannte
Diagnostik und unzulässige Pfadantworten werden getrennt geprüft. Ein echter
verknüpfter Worktree prüft die moderne Attributes-Auflösung. Eine absichtlich
fehlende `/docs`-Exportregel muss an der zweiten Publishergrenze scheitern.

```sh
SKYE_BUILD_NODE=/path/to/node14 \
  /path/to/modern-node --test tests/release.test.mjs
```

Am 8. November 2026 bestanden lokal alle acht Releasefälle mit Node 14.21.3
als Compiler und Node 24.22.1 als Testharness auf macOS. Die Crawlerprüfung
bestand separat mit 37 Offlinetests. Es wurde dabei keine Website abgerufen.

Der moderne Testharness ersetzt den Produktionscompiler nicht. Die synthetische
Legacy-Antwort ist keine neue Ausführung von Git 1.8 oder einem CentOS-Kernel.
Die gesonderte CentOS-7-Userlandprüfung bleibt dafür zuständig. Native Verträge:
[git rev-parse](https://git-scm.com/docs/git-rev-parse),
[git status](https://git-scm.com/docs/git-status) und
[Git-Exportattribute](https://git-scm.com/docs/gitattributes).
