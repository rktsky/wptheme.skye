# Freigegebener Umfang

Am 03.11.2026 hat der Projekteigentümer erklärt:

> Einverstanden, setze die aktualisierte Vorlage vollständig um. Die Änderungen am Skye-Originalrepository sind freigegeben. Redis und ACF Pro bleiben separat. Halte Dich strikt an die Coding-, Commit- und Dokumentationsregeln.

Grundlage: Audit gegen Skye `4e4f2d8`, Portal `de041627` und abgeschlossene Website `6fecb117`. Die uncommitteten Website-Designexperimente sind keine Backportquelle.

Der Umfang umfasst Standards/Dokumentation, Sicherheitskorrekturen, explizite Komponenten-/Datenverträge, funktionierende Standardansichten, zwingende klassische Bearbeitung, native Assetabhängigkeiten, reproduzierbaren Build, automatische allgemeine Mobile-/Critical-CSS-/Medienoptimierungen, gezielte Entfernung veralteter Paketkopien und Release-/Migrationsnachweise.

Die frühere Einschränkung der Critical-/Mobile-CSS-Backports ist aufgehoben: Wiederverwendbare Build- und Auslieferungsmechanik gehört hinein; die konkreten Volksgarage-Inventare, Fonts, Glyphen und Geometrieannahmen nicht. Die Medienpipeline gehört zum Lieferumfang, ihre Aktivierung richtet sich nach den unterstützten Formaten und der Projektkonfiguration.

Redis und ACF PRO werden nicht eingebettet. Ihre separate Einrichtung und Lizenz-/Updateverwaltung werden dokumentiert. Keine Installation oder Änderung an produktiven Volksgarage-Systemen, kein Push/Release ist beauftragt. WordPress und beibehaltene Drittanbieterdateien bleiben unverändert.

Logik, Styling und reine Formatierung werden getrennt committet. Jeder Arbeitsschritt nennt tatsächliche Prüfungen und offene Grenzen. Die Umsetzung ist erst nach Integration und gezielten Regressionen abgeschlossen; frühere Websiteprüfungen ersetzen keine Skye-Prüfung.
