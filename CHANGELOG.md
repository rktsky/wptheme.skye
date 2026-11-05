# Änderungen

## 3.3.0 – vorbereiteter Quellstand

Basis: Skye 3.2.2, Commit `d2685a3b7acbc65e67f5d607b23f9c41a967350a`. Dieser isolierte Stand ist noch nicht veröffentlicht oder auf einer Kundenwebsite installiert.

- Formularadapter erhalten native Honeypots bytegleich. In Buttonfragmenten bekommen ausschliesslich tatsächliche Submitter die Buttonklassen; begleitende Sicherheitsfelder und Scripts bleiben unverändert.
- Progressive Formular-CSS-Auslieferung verweigert zusätzliche Render-/Script-Produzenten und prüft deren Registrierung vor der Head-Ausgabe erneut. Vollständiges GF-Basic und der native Honeypot-Fallback bleiben erhalten.
- Eine explizite WebP-Preload-API bereitet Hinweise aus den bereits vorhandenen Medienalternativen vor. Keine Seiten-/Hero-Erkennung und keine Bildkonvertierung beim Rendern.

Die übertragbaren Verträge sind in [Formulare](docs/forms-native-contracts.md) und [responsive WebP-Vorladungen](docs/responsive-image-preloads.md) beschrieben. Tatsächliche Quellprüfungen und offene Integrationsabnahmen werden dort getrennt geführt. Bestehende Kunden-Forks werden nicht automatisch aktualisiert; eine Übernahme benötigt einen gezielten Merge und erneute Projektprüfung.
