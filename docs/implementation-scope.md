# Freigegebener Umfang

Freigabe vom 03.11.2026:

> Einverstanden, setze die aktualisierte Vorlage vollständig um. Die Änderungen am Skye-Originalrepository sind freigegeben. Redis und ACF Pro bleiben separat. Halte Dich strikt an die Coding-, Commit- und Dokumentationsregeln.

Grundlage: Audit gegen Skye `4e4f2d8`, Portal `de041627` und abgeschlossene Website `6fecb117`. Die uncommitteten Website-Designexperimente sind keine Backportquelle.

Für Bootstrap gilt **6 Alpha** (`6.0.0-alpha.1`). Die Migration wird in Skye separat geprüft.

Der Umfang umfasst Standards/Dokumentation, Sicherheitskorrekturen, explizite Komponenten-/Datenverträge, funktionierende Standardansichten, zwingende klassische Bearbeitung, native Assetabhängigkeiten, reproduzierbaren Build, automatische allgemeine Mobile-/Critical-CSS-/Medienoptimierungen, gezielte Entfernung veralteter Paketkopien und Release-/Migrationsnachweise.

Die frühere Einschränkung der Critical-/Mobile-CSS-Backports ist aufgehoben: Wiederverwendbare Build- und Auslieferungsmechanik gehört hinein; die konkreten Volksgarage-Inventare, Fonts, Glyphen und Geometrieannahmen nicht. Die Medienpipeline gehört zum Lieferumfang, ihre Aktivierung richtet sich nach den unterstützten Formaten und der Projektkonfiguration.

Redis und ACF PRO werden nicht eingebettet. Ihre separate Einrichtung und Lizenz-/Updateverwaltung werden dokumentiert. Die ursprüngliche Freigabe enthält keine Installation oder Änderung an produktiven Volksgarage-Systemen und keinen Push bzw. veröffentlichten Release. WordPress und beibehaltene Drittanbieterdateien bleiben unverändert.

Logik, Styling und reine Formatierung werden getrennt committet. Jeder Arbeitsschritt nennt tatsächliche Prüfungen und offene Grenzen. Die Umsetzung ist erst nach Integration und gezielten Regressionen abgeschlossen; frühere Websiteprüfungen ersetzen keine Skye-Prüfung.

Erweiterung vom 04.11.2026: Push ins Skye-Originalrepository, vollständige GitHub-Actions-Prüfung und direkte Fehlerbehebung. Der Arbeitsbranch `refactor/skye-3` wird dafür in `rktsky/wptheme.skye` veröffentlicht; die Prüfergebnisse werden nach tatsächlicher Ausführung nachgeführt. WordPress- und Drittanbieter-Originaldateien bleiben auch bei CI-Kompatibilitätsfehlern unverändert.

Für Build und Watch bleibt am 04.11.2026 der direkte Betrieb auf CentOS 7 verbindlich. Die Referenzversionen sind Node 14.21.3 und npm 6.14.18 aus dem Portal. Ein ausschliesslicher Build auf GitHub ersetzt diesen Ablauf nicht.

Erweiterung vom 04.11.2026 aus dem Rocketsky-Websiteprojekt: Autoptimize entfernen, generische statische Auslieferungsfilter und Optimierungen im eigenen Skye-Quellrelease integrieren, eine standardmässig deaktivierte CDN-Konfiguration anbieten und die native Setupkonstante gegen neue gebündelte Themes dokumentieren. Die Umsetzung erfolgt isoliert auf `feature/website-performance-foundation` ab `f063767`; Website- und Provider-Inventare bleiben im eigenen Projektadapter. Drittanbieter-Originale bleiben unverändert. Veröffentlichung und Stage-Rollout werden separat koordiniert.

Weitere Freigabe vom 04.11.2026: die bislang projektspezifischen Staging-Mailgrenzen werden als wiederverwendbares `EnvironmentMail`-Paket mit öffentlichem `skye_block_staging_mail`-Filter in Skye übernommen. Andere Umgebungen und frühere native Entscheidungen bleiben erhalten; keine Maildateninspektion oder echte Testzustellung. Da 3.1.0 noch nicht veröffentlicht oder ausgerollt wurde, bleibt die Versionsnummer des erneuerten Kandidaten 3.1.0.

Erweiterung vom 04.11.2026 nach Veröffentlichung von 3.1.0: Die mobile Navigation
soll als wiederverwendbarer Skye-Standard nach dem Volksgarage-Vorbild umgesetzt
werden. Entwicklung erfolgt im separaten sauberen Quellclone auf
`feature/mobile-navigation-3.2.0` ab dem tatsächlichen Tag-Commit
`10ba3884893ede53364a1a3b54a7194dae280558`. Generische Logik, Grunddarstellung,
öffentliche Hooks und native No-JavaScript-/Zugänglichkeitsgrenzen gehören in
Skye 3.2.0; Markenfarben und Logoanimation bleiben im Website-Theme. Getrennte
Quellcommits und SSH-Featurepush zur vollständigen CI sind freigegeben. PR,
Releaseveröffentlichung und Website-Rollout werden anschliessend separat vom
Hauptchat koordiniert. Installierte Originalsourcen und Fremdpakete werden nicht
gepatcht; das parallel verwendete skye-upstream bleibt unangetastet.
