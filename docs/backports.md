# Backport-Nachweis

Die erneute projektübergreifende Prüfung für Skye 3.5.0 steht unter
[Projekt-Learnings 3.5.0](project-learnings-3.5.0.md). Sie ergänzt die folgende
historische Grundlage um die tatsächlich neu übernommenen Verträge und nennt
bereits abgedeckte sowie bewusst projektgebundene Ergebnisse.

Ausgang: Skye `4e4f2d8ec60a6add71bd612860a98fd20a7d48fd`. Verbindliche Standards aus Portal `de041627b2e41d03678ea1a4ccb92a5434828980`, insbesondere `bd378f7c` und `9910edc6`. Websitequelle ausschliesslich der abgeschlossene saubere Performance-Stand `6fecb117677f192bba8763a3965e40f3a1fd5497`; das nicht abgeschlossene Designexperiment wurde nicht als fertiger Backport behandelt.

Skye 3 übernimmt die allgemeinen Performanceverfahren der abgeschlossenen Arbeiten und verwendet Bootstrap **6 Alpha**. Projektspezifische Fonts, Icons und Layoutannahmen werden durch konfigurierbare Verfahren ersetzt. Die Freigaben sind in [implementation-scope.md](implementation-scope.md) dokumentiert.

| Herkunft | Generische Umsetzung in Skye 3 | Grenze / Nachweis |
| --- | --- | --- |
| Portal `bd378f7c`, `9910edc6` | AGENTS, Standards, Tabs, echte Komponentenargumente, vollständige PHP-Verträge, lokale Stil-/Architekturprüfung und CI | Alte PSR2-/Zwei-Leerzeichen-Regeln nicht kopiert; semantische Begründung und historische Genauigkeit bleiben Reviewpflicht |
| Portal Komponenten-/Kontextregressionen | Isolierter `get_component()` und ein gepufferter `skye_component_html()`-Adapter | Child-Overrides und alte Aufrufformen; kein vollständiger Scope oder Geheimnisobjekt |
| Portal Options-/Menü-Security | Native Capability-Grenze vor ACF-Speicherung; kein mutierender GET; native Menüs | Der alte HTML-Menücache wurde statt eines weiteren Cache-Reparaturmodells entfernt |
| Website `d2a658c`, `138361f`, `42df2e0` | Native Assets, vollständige Abhängigkeitsgraphen, verifizierte GF-Skriptstrategie und früh deklarierte Formassets | Unbekannte Provider, direkte Inline-Abhängigkeiten und andere Quellen behalten Coreverhalten |
| Website `cdc1930`, `e25e328` | FormControls ergänzt native Klassen statt Sass-Selector-Expansion | Werte, Namen, Nonces, Labels und Fremdattribute bleiben nativ; Klassen an Bootstrap 6 angepasst |
| Website `7e08d39` | `skye_public_asset_url` als endgültiger Auslieferungsfilter für eigene Assets | Kein CDN-Zwang und keine Änderung kanonischer gespeicherter URLs |
| Website `d79c640`, `f81a8bd` | Images, MediaPipeline und eigene MediaStore/WebpGenerator/NativeSizes-Packages | Keine Volksgarage-Crops oder Layoutscanner; begrenzte Hintergrundjobs, vollständige kleinere Alternativen, native Fallbacks |
| Website `4d123a0`, `5f6b04d` | Automatische Mobile-CSS-Ableitung und native komplementäre Medienbedingungen | Gemeinsamer Build-/Laufzeitgrenzwert; neue Bootstrap-6-Layer/Range-Syntax konservativ behandelt |
| Website `948c6a3`, `7953682` | Automatische kritische Extraktion, eigene PHP-Quellerkennung, Watch-Neubau und unveränderlicher Manifestvertrag | Tatsächliche Quellbytes sind im Buildfingerprint enthalten; kein Beweis beliebiger dynamischer erster Viewports |
| Website `86ce74b`, `356c624` | Progressive eigene Styles, einmalige URL-Anforderung, native Formularbereitschaft, spätes Opt-out und vollständiger Fallback | Nur explizit geprüfte Layout-/Providerverträge; keine universelle hero-/CDN-Geometrie |
| Website `259d546`, `fe30ff2` | Konfigurierbare responsive Bildhinweise und konservative eigene Fontpreloads am Body-Einstieg | Keine Markenfonts, kein fester Fontcount; fremde Hinweise/Attribute und unklare Ressourcen bleiben unverändert |
| Website `f91849b` | Alias für nachweislich leere lokale GF-Styles | Kanonische Quelle, reguläre lesbare Nicht-Symlink-Datei und native Metadaten geprüft; kein blindes Dequeue |
| Website `d2a658c`, `1a9cef1` | Vertraute installierte Iconmodelle mit explizitem Subset-Vertrag und vollständigem Fallback | Kein Kopieren der sieben lizenzierten Volksgarage-Glyphen; eigene freie Providerquellen |
| Portal sicherer Build | Vollständige Ausgabe in einem temporären Ziel, Fehlergrenzen und Rückkehr zum vorherigen Stand | Node 14.21.3/npm 6.14.18 für direkte CentOS-7-Builds; gelockte Abhängigkeiten und separate moderne Prüfharnesses |

## Zusätzliche Fehlerbehebungen aus der Skye-Prüfung

- Defekte, zu grosszügige SVG-Uploadintegration und veraltete Plugin-Kopien entfernt; keine Vendor-Innenpatches.
- Öffentliche Beitragsmigration entfernt, vollständige Standardansichten und optionaler ACF-Fallback hergestellt.
- Raw-Metadaten lesen aus dem richtigen Objekttyp; Termfehler, kaputte JSON-Manifeste und unsichere Pfade kontrolliert behandeln.
- Natives Menü mit Besucherzustand statt persistentem HTML; defekter Fremd-Walker entfällt.
- Native Templateauswahl gegen unzulässige/reursive Bibliotheks-/Einstiegspfade begrenzt; Archive benutzen ihre tatsächliche Objektidentität.
- Medienentdeckung stoppt vor einem nicht dauerhaft gespeicherten Job und setzt dort erneut an; direkte native Metadaten-/Dateizeigeränderungen invalidieren den requestlokalen Bildcache.
- Verlorene Medienbereinigungstermine werden aus dauerhaften eigenen Inventaren in begrenzten Hintergrundbatches wiederhergestellt; aktive/native/fortsetzbare Ausgaben bekommen keine unnötigen Cleanup-Timer.
- Medienaufbewahrung bei fehlgeschlagener Ledger-Aktualisierung durch einen mit derselben nativen Metadatenänderung gespeicherten Schutz ergänzt und gezielt regressiert.
- Favicon-Abwesenheit, Multisite-Optionswechsel und historische Dokumentationsdaten unabhängig überprüft; native Änderungen der drei Legacyoptionen invalidieren gezielt nur deren Lesecache.
- Der registrierte klassische Footer-Widgetbereich wird tatsächlich nativ ausgegeben; eine leere Sidebar erzeugt keinen leeren Wrapper.
- Funktionslose Legacy-Includes, ungenutzte Mixins und leere Sass-Module entfernt; aktive Full-/Mobile-CSS-Ausgabe dabei bytegleich erhalten.

## Bewusst nicht enthalten

Auftrags-/Rechnungs-/Kunden-/Lager-/OCR-/Kalender-/WhatsApp-Fachlogik, Wishlist, Instagram, Brevo-Startreihenfolge, Markenassets und der konkrete CDN-Provider gehören nicht ins Basistheme. Die neun auf der Website entfallenen Bootstrap-Imports beweisen keine allgemeine Nichtverwendung. Skye erhält die benötigte Bootstrap-6-Standarddarstellung und dokumentierte Konfiguration statt einer fremden Seiten-Whitelist.

Auf Website oder Portal gemessene Scores sind Herkunftsnachweise für deren konkreten Stand. Nur die in [Prüfungen](testing.md) ausgeführten Skye-Läufe werden als neue Skye-Nachweise geführt.
