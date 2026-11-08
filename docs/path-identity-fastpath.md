# Frische Dateipfade ohne wiederholten Vorfahren-Scan

Seit Skye 3.5.0 löst `PathIdentity::canonical()` physisch kanonische POSIX-Pfade
mit einem frischen nativen `realpath()` auf. Dieser Fall benötigt keinen
anschliessenden `lstat()`-Durchlauf über jeden Vorfahren. Ausgangspunkt ist der
abgeschlossene [Francesca-Adapter 1.3.2](https://github.com/rktsky/wptheme.francescaschori/commit/7900e20ab505db296f31589e62b7e7b132fd4b30).

Die vorhandene Eingabeprüfung läuft zuerst: absolute lokale Schreibweise,
keine Nullbytes, höchstens 16 KiB und 64 rootinklusive Vorfahren. Danach leert
`clearstatcache( true )` PHPs lokale Stat-/Realpath-Daten. Nur wenn das frische
Ergebnis exakt zur eingegebenen physischen Schreibweise passt, kehrt der
Resolver direkt zurück. Der bisher erlaubte abschliessende Verzeichnistrenner
bleibt zulässig.

Aliase und abweichende Schreibweisen verwenden den bisherigen Resolver mit
seinen 64 Vorfahren, 256 Prefixprüfungen und 32 Linkübergängen. Relative
Symlinkziele behalten ihre nativen Dot-Segment-Regeln. Der vollständige
Windows-Zweig bleibt unverändert; der neue schnelle Pfad gilt dort nicht.
Ein fehlendes oder nicht auflösbares Ziel bleibt `false`.

Es wird kein Pfad, Root oder Freigabeergebnis zwischengespeichert. Die Aufrufer
prüfen weiterhin erlaubte Roots, reguläre Dateien und Descriptoridentität vor
und nach dem Lesen. Bytebudgets und der begrenzte requestlokale Quellvorrat
ändern sich nicht. Die Operation verwirft PHPs gesamte lokale Dateisystem-
Cacheansicht und kann deshalb auch spätere Dateiprüfungen im selben Prozess
beeinflussen. Sie leert weder Redis noch OPcache und verändert keine Dateien
oder Dienste.

## Prüfnachweis vom 8. November 2026

`tests/path-identity-fastpath.php` prüft echte Dateien und native PHP-Aufrufe.
Die Datei ist nur per CLI ausführbar. Eigene temporäre Fixtures werden
abschliessend entfernt, ohne Symlinkzielen zu folgen.

35 Assertions bestanden lokal auf PHP 8.3.36/macOS. Darunter sind falsche Typen,
relative und Stream-Pfade, Nullbytes, überlange Eingaben, fehlende Ziele,
physische Pfade, erlaubte relative Aliase, Zyklen und überlange Linkketten
sowie die tatsächlich vorhandenen Verzeichnisse an der 64-/65-Vorfahrengrenze.
Separate PHP-Prozesse ersetzen warme Elternverzeichnisse und Rootaliases und
löschen warme Blätter. Diese Trennung verhindert, dass ein `rename()` im
Prüfprozess den nötigen Cachewechsel bereits selbst verdeckt.

Mit originalem WordPress 7.1.4 bestanden ausserdem die bestehenden Gruppen
Static Delivery mit 246, vollständiges CSS mit 52 und Assetverträge mit
35 Assertions. Darunter liegen die vorhandenen Root-/Quellgrenzen und
prozessübergreifenden Readerfälle. Die Critical-Styles-Gruppe benötigt den
vollständigen aktuellen Asset-Build und gehört zur abschliessenden Matrix;
ein erster Lauf ohne Build konnte keine gültige Critical-Manifest-ID lesen.
Ein Windows-Lauf und ein neuer FPM-Seitenvergleich wurden für diesen
Quellstand lokal nicht durchgeführt.

Ein vorab auf fünf alternierende Paare begrenzter CLI-Mikrovergleich nutzte
22 unveränderte physische Dateien und 2640 Auflösungen je Variante und Paar:

| Paar | Reihenfolge | Skye 3.4.1 | Kandidat |
| --- | --- | ---: | ---: |
| 1 | vorher, Kandidat | 71,27 ms | 29,48 ms |
| 2 | Kandidat, vorher | 72,79 ms | 29,40 ms |
| 3 | vorher, Kandidat | 72,15 ms | 29,81 ms |
| 4 | Kandidat, vorher | 73,62 ms | 29,51 ms |
| 5 | vorher, Kandidat | 72,93 ms | 30,10 ms |

Verglichen wurde der bisherige Resolver aus `9d96fc8` mit dem neuen Resolver,
nicht die gesamte Website. Der vollständige Mikro-Nachweis liegt ausserhalb
des Repos unter `outputs/skye-project-learnings-20261108/path-identity-micro.json`
und bindet beide Originalquellen per SHA-256. Diese Werte belegen keinen
bestimmten FPM-, Seiten- oder Speed-Index-Gewinn eines zukünftigen Projekts.

Reproduktion der funktionalen Regression:

```sh
php tests/path-identity-fastpath.php
```

Die nativen Verträge wurden am 8. November 2026 anhand der PHP-Dokumentation
für [clearstatcache](https://www.php.net/manual/en/function.clearstatcache.php),
[realpath](https://www.php.net/manual/en/function.realpath.php) und
[readlink](https://www.php.net/manual/en/function.readlink.php) geprüft.
Native Dateisystemfunktionen und bestehende Aufrufer bleiben massgeblich;
es wurden keine Core-, Plugin- oder Vendor-Dateien verändert.
