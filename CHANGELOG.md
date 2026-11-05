# Änderungen

## 3.4.0

- Backend- und Loginfarben entstehen aus der bestehenden Websitepalette. Das Login verwendet die vorhandene Logoquelle oder den Websitenamen; Kundenthemes können ihre bereits verwendeten Producer anbinden. Keine zweite redaktionelle Konfiguration und keine Änderung der Authentifizierung.
- Kleine eigene Backend-/Loginstyles statt vollständigem Frontend-CSS. Automatische Text-, Fokus- und Kontrolltöne berücksichtigen helle Markenfarben sowie dunkle Websitehintergründe; native Auswahlmarkierungen und deaktivierte Controls bleiben erhalten.
- Lokale Assetquellen werden auch bei Request-Cachetreffern gegen frische Pfad-, Root- und Descriptoridentitäten geprüft. Gemeinsame begrenzte Pfadauflösung verwirft extern ausgetauschte Zwischenverzeichnisse und Symlink-Escapes.
- Explizite metadatenbasierte Auswahl kleinerer responsiver Bildkandidaten. Die bisherige Rocketsky-Logoentscheidung ist als eigene API verfügbar; andere Bilder werden nicht automatisch umgeschrieben.

Verträge: [Backend-Branding](docs/backend-branding.md), [lokale Quellgrenzen](docs/security.md) und [Bildkandidaten](docs/media.md). Die Formular- und Medienverträge aus dem vorbereiteten Stand 3.3.0 sind enthalten. Node 14.21.3/npm 6.14.18 und die getrennten Originalpakete bleiben erhalten. Kundenübernahmen und ihr tatsächlicher Rollout werden im jeweiligen Projekt dokumentiert.

## 3.3.0 – vorbereiteter Quellstand

Basis: Skye 3.2.2, Commit `d2685a3b7acbc65e67f5d607b23f9c41a967350a`. Die folgenden Verträge wurden im Quellstand `cebddb5` vorbereitet und sind in 3.4.0 enthalten. Kundenübernahmen werden separat geprüft.

- Formularadapter erhalten native Honeypots bytegleich. In Buttonfragmenten bekommen ausschliesslich tatsächliche Submitter die Buttonklassen; begleitende Sicherheitsfelder und Scripts bleiben unverändert.
- Progressive Formular-CSS-Auslieferung verweigert zusätzliche Render-/Script-Produzenten und prüft deren Registrierung vor der Head-Ausgabe erneut. Vollständiges GF-Basic und der native Honeypot-Fallback bleiben erhalten.
- Eine explizite WebP-Preload-API bereitet Hinweise aus den bereits vorhandenen Medienalternativen vor. Keine Seiten-/Hero-Erkennung und keine Bildkonvertierung beim Rendern.

Die übertragbaren Verträge sind in [Formulare](docs/forms-native-contracts.md) und [responsive WebP-Vorladungen](docs/responsive-image-preloads.md) beschrieben. Tatsächliche Quellprüfungen und offene Integrationsabnahmen werden dort getrennt geführt. Bestehende Kunden-Forks werden nicht automatisch aktualisiert; eine Übernahme benötigt einen gezielten Merge und erneute Projektprüfung.
