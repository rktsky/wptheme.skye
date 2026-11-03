# Verbindliche Entwicklungsregeln

Vor Änderungen [docs/coding-standards.md](docs/coding-standards.md) lesen.

- Logik, Styling und reine Formatierung in getrennten Commits führen. Abhängigkeiten und tatsächliche Prüfnachweise im jeweiligen Commit beschreiben.
- Funktionen, Klassen, Datenzugriffe, Rechte und View-Modelle gehören nach `lib/`, grössere Umfänge in Packages unter `lib/package/`. Bestehende Architektur erhalten.
- `components/` und `views/` enthalten dokumentierte HTML-Templates. Keine Funktionsdeklarationen, Abfragen oder Mutationen dort. Komponenten ausschliesslich mit `get_component()` laden und kleine Kontexte explizit im dritten Array übergeben.
- HTML als HTML; Darstellung in `assets/styles/`, Verhalten in `assets/scripts/`. Escaping nach Ausgabekontext.
- Tabs, LF und lesbare Abstände, insbesondere `function xyz( $data, $zxy ) {`.
- Jede eigene PHP-Datei hat einen Zweckheader. Jede eigene Funktion/Methode einen englischen DocBlock mit Zweck UND Begründung, typisierten `@param`/`@return`, belegtem `@since` und relevanten Fehler-/Security-/Nebenwirkungsverträgen. Historische Angaben anhand Git prüfen, nicht erfinden.
- Vor WordPress-Implementierung die betroffenen offiziellen APIs und Coding-/Dokumentationsstandards lesen; Quellen im Arbeitsnachweis nennen. Keine deprecated oder privaten APIs verwenden.
- DRY anhand wirklicher Aufrufer und gleicher Verträge; Performance, Daten- und Rechtebegrenzung von Beginn an prüfen.
- Classic Editor ist verbindlich. Gutenberg ist keine angebotene Alternative. Native öffentliche Schnittstellen und Regressionen statt Core-Patches verwenden.
- Alle generell übertragbaren abgeschlossenen Website-Performanceverbesserungen integrieren, einschliesslich automatischem Mobile-/Critical-CSS und Medienpipeline. Projektspezifische Ergebnisse neu erzeugen; unbekannte Fälle behalten vollständigen nativen Fallback.
- Bootstrap erhalten. Abhängigkeiten nur mit belegtem Nutzen, Herkunft, Lizenz und Sicherheits-/Ladebedarf; Lockfiles pflegen. Fremdcode und generierte Dateien nicht umformatieren.
- Redis und ACF PRO bleiben separate Originalpakete. Keine Geheimnisse einchecken, keine stillen Server-/Plugininstallationen, kein konkurrierender Object-Cache.
- Echte Funktionen/Templates und Fehlerfälle testen. Browser-/Geräteabdeckung ehrlich dokumentieren; Emulation ist kein echter Gerätetest. Keine produktiven Nachrichten, Zahlungen, Kalender- oder Druckaktionen in Tests.
- Die direkte Änderung des eigenen Skye-Quellrepos wurde am 03.11.2026 für den dokumentierten Umfang freigegeben. Das erlaubt keine Änderungen an WordPress-, Plugin- oder sonstigen Drittanbieter-Originalen.
- Kein ZIP für vollständig in Git enthaltene Änderungen. Kein Push oder Produktivrollout ohne entsprechenden Auftrag.
