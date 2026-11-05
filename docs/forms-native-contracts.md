# Native Formulare ab Skye 3.3

`FormControls` ergänzt ausschliesslich Darstellungsklassen an den vom Anbieter gerenderten sichtbaren Controls. Native GF-Honeypotfelder bleiben bytegleich. Die Submit-/Weiter-/Zurück-Adapter erkennen tatsächliche `button`- bzw. Input-Submitter; ein daneben ausgegebenes Hidden-Feld, eine Anti-Spam-Textarea oder ein Script erhält keine Buttonklassen. Formularzustand, Werte, Namen, Aktionen, Nonces und Zugänglichkeitsattribute bleiben beim Anbieter.

Skye ersetzt keine Gravity-Forms-Validierung und ergänzt keine eigenen Honeypot- oder Versionswerte. Akismets native Whole-form-Injektion mit versteckten Controls und JavaScript wird nicht umgeschrieben. Eine Entscheidung über deren technische Datenübermittlung gehört zur tatsächlichen Formular-/Providerkonfiguration und zur projektspezifischen Datenschutzerklärung.

## Vollständige Auslieferung als Fallback

Die bestehenden GF-Basic-/Theme-Styles bleiben vollständige native Ressourcen. Der bereits vorhandene Sonderfall für eine nachweislich leere Theme-Components-Datei erhält deren native Abhängigkeiten und Inline-CSS; er gilt nicht für Basic oder Honeypot-Styles. Formulare mit aktiviertem Honeypot, reicheren Feldern, Add-ons oder eigener Initialisierung behalten ihre normale vollständige Blocking-Auslieferung.

Zusätzlich prüft `CriticalStyles` die globalen, formularbezogenen und feldbezogenen nativen Render-Hooks. Nur Skyes eigene Klassenadapter und sein bekannter Readiness-Wrapper sind innerhalb des begrenzten einfachen Formularvertrags zulässig. Weitere Whole-form-, Field-, Button- oder Post-render-Produzenten behalten die native Auslieferung. Dazu zählt auch eine native Anti-Spam-Injektion; sie wird nicht als unbekannter Code ausgeführt, entfernt oder durch eine optimierte Kopie ersetzt. Es gibt keine Akismet-Versionstabelle oder Formular-ID-Liste.

Die Prüfung verwendet die bereits vorhandene Enqueue-Konfiguration und öffentliche `WP_Hook`-Registrierungen. Sie erzeugt keine zusätzlichen Formularabfragen und ruft keinen Producer zur Probe auf. Vor der Head-Ausgabe erfolgt eine erneute Prüfung, damit nach dem Provider-Enqueue ergänzte Hooks den ungedruckten Opt-in widerrufen können. Erst nach bereits gedrucktem Head eingeführte Layout-/Script-Erweiterungen können nicht rückwirkend vorhergesagt werden; ein Projekt muss solche Erzeuger in seinem gesamten First-viewport-Vertrag berücksichtigen.

## Quellregressionen

Lokal mit PHP 8.3.26 und den unveränderten Core-Quellen 7.1.4 sowie 6.8.12 geprüft:

| Programm | Assertions je Core | Tatsächlicher Umfang |
| --- | ---: | --- |
| `tests/performance-form-controls.php` | 129 | Echte WP-Hooks und HTML-Parser; sichtbare Controls, native HP-Form, benachbarte Sicherheitsfelder/Scripts, Submitter, Admin-/AJAX-Kontexte |
| `tests/performance-critical.php` | 54 | Native Register/Hookidentitäten; zusätzliche globale/formular-/feldbezogene Producer, geänderte Arity, kein Probeaufruf, spätes Pre-head-Opt-out und vollständige GF-Styles |
| `tests/performance-empty-style.php` | 51 | Vollständige Basic-/Theme-Abhängigkeiten, leere Aliasgrenze, unbekannte Quellen und private Kontexte |
| `tests/performance-strategies.php` | 46 | Native Scriptstrategien, Inline-Abhängigkeiten und unveränderter Provider-Fallback |

Alle acht Prozesse und damit 560 Assertions bestehen. Dieselbe Matrix wurde zusätzlich mit nativem PHP 8.3.36 auf CentOS 7.9 gegen beide Core-Stände und den frisch gebauten Assets ausgeführt: nochmals 560 Assertions ohne Fehler. Der [Quellnachweis](evidence/forms-native-contracts-3.3.0.json) enthält tatsächliche Ausgaben und Quellhashes. Architektur-/Vertragsprüfung: 98 Dateien ohne Befund; Formatter-Dry-run aller sechs geänderten Implementierungs-/Regressionsdateien: keine Änderung vorgeschlagen.

Diese Programme laden weder eine installierte GF-/Akismet-Integration noch eine Website-Datenbank. Ihre kontrollierten Providergrenzen sind **keine native WEBFORM- oder Browserabnahme**. Der gemeinsame Build erzeugte mit dem vorhandenen identischen Dependency-Lockgraph unter Node 14.21.3/CentOS 7.9 und lokalem Node 24.22.2 alle 26 Ausgaben samt Manifest bytegleich; [Buildbeleg](evidence/native-build-3.3.0.json). Keine Buildabhängigkeit wurde neu installiert und kein Systempaket verändert. Die separate private SQLite-Testbibliothek der Medienmatrix ist in deren Nachweis dokumentiert. Dies ist ein privater Buildnachweis, kein geprüfter Releaseexport oder Kundenrollout.

```sh
SKYE_WORDPRESS_CORE=/absolute/official/wordpress php tests/performance-form-controls.php
SKYE_WORDPRESS_CORE=/absolute/official/wordpress php tests/performance-critical.php
SKYE_WORDPRESS_CORE=/absolute/official/wordpress php tests/performance-empty-style.php
SKYE_WORDPRESS_CORE=/absolute/official/wordpress php tests/performance-strategies.php
```

## Native Integration pro Projekt

Mit den tatsächlich eingesetzten Originalplugins zusätzlich den nativen Browserweg für ungültige und gültige Anfragen prüfen. Nur Controls innerhalb des eigentlichen `form` werden übertragen; ein Wrapper kann fremde Controls enthalten. Die native Scriptinitialisierung muss ihre ursprünglichen signierten Zustands-/Versions- und Anti-Spam-Werte erzeugen. Keine fehlenden Werte durch eine neue produktive PHP-Hash-Injektion ersetzen und keine Honeypot-/Spamprüfung für den Test lockern.

Ein PHP-Integrationstest muss explizit den ursprünglichen WEBFORM-Kontext verwenden. Ein unqualifizierter `GFAPI::submit_form()`-Aufruf kann zusätzliche WEBFORM-Anti-Spamprüfungen überspringen und belegt deshalb deren Funktion nicht. Bei privaten Tests Mail-/HTTP-Transporte bereits vor Bootstrap blockieren. Synthetische Ham-/Spam-Antworten des Transports prüfen den ursprünglichen Integrationspfad; sie beweisen keine reale Providerklassifikation. GET-Rendering kann Formular-Aufrufzähler schreiben und gehört bei transaktionalen Tests bereits innerhalb der geprüften Transaktionsgrenze.

Für reale Abnahmen Fehlerzusammenfassung/Fokus, gültige Bestätigung, native AJAX-/Postback-Wege, Tastatur, schmale Ansichten, echte No-JS-Ersatzwege und alle beabsichtigten Provider prüfen. Testeinträge eindeutig kennzeichnen und wiederherstellbar entfernen. Die hier beschriebenen Quelltests behaupten keine solche Geräte-/Browserabdeckung und lösen keine produktiven Nachrichten aus.

Primärquellen, gelesen am 5. November 2026: [GF-Feldmarkup](https://docs.gravityforms.com/gform_field_content/), [GF-Whole-form-Filter](https://docs.gravityforms.com/gform_get_form_filter/), [GF-Post-render-Script](https://docs.gravityforms.com/gform_post_render_script/), [expliziter WEBFORM-Kontext](https://docs.gravityforms.com/submitting-forms-with-the-gfapi/), [Core-Hookregister](https://developer.wordpress.org/reference/classes/wp_hook/), [HTML-Klassenadapter](https://developer.wordpress.org/reference/classes/wp_html_tag_processor/add_class/).
