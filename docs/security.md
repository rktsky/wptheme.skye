# Sicherheit und getrennte Infrastruktur

## Native Grenzen

`ClassicEditing` steuert Beiträge, Seiten, eigene Beitragstypen und Widgets über die öffentlichen Core-Filter. Die optionale externe Classic-Editor-Erweiterung wird zusätzlich über ihre Editor-Verfügbarkeitsfilter eingebunden. Ihr früher Einstellungs-Cache wird nicht nachträglich überschrieben. Die alte eingebettete Plugin-Kopie wurde entfernt. WordPress-Dateien bleiben unverändert; gespeicherte Blockinhalte werden weiter nativ gerendert.

ACF-Einstellungsseiten verwenden `manage_options`. Der kanonische `acf/save_post`-Guard läuft mit Priorität 5 vor ACFs Speicherung bei 10. Die Formvalidierung ergänzt diesen Guard; ACF behält seinen nativen Noncevertrag. Ein gültiger Token ersetzt keine Berechtigung. Normale Datensätze behalten ihre eigenen Regeln. Der requestlokale Legacy-Optionscache wird nach Blog-ID getrennt und beim nativen Blogwechsel aktualisiert. Erfolgreiche native Add-/Update-/Delete-Hooks für genau die drei bekannten Legacykeys verwerfen ausschliesslich diesen Lesecache. Sie ändern weder Site-Icon noch Rechte oder fremde Optionen. Die fehlende alte Favicon-Option verändert ein unabhängig konfiguriertes natives Site-Icon nicht; ein ausdrücklich gespeicherter Nullwert kann es weiterhin entfernen.

Members-Regeln werden vor Head/ACF-Ausgabe berücksichtigt; Passwortschutz bleibt nativ und unterbindet zusätzliche Feld-/Bild-/Auszugsausgabe. URLs, Attribute und Texte werden am jeweiligen Ausgabepunkt escaped. Absichtlich erlaubter Lead-Richtext verwendet KSES. Der Rohdatenhelfer entscheidet eindeutig zwischen Post-, Benutzer-, Term- und Optionsspeicher und wechselt nicht wegen eines leeren Wertes zu einer anderen Quelle.

Das Theme fügt keine SVG-Uploadfreigabe hinzu. Die alte fehlerhafte Sanitizer-Kopie wurde am Integrationspunkt entfernt. Für Uploadbedarf ist eine getrennt gepflegte, konkret geprüfte Lösung nötig. Die eigenen vertrauten Theme-SVGs und installierte freie Iconquellen sind davon unabhängig. Debugausgabe ist an `WP_DEBUG` und `manage_options` gebunden und darf keine Geheimnisse enthalten.

Öffentliche GETs führen keine Inhaltsmigrationen durch. Der Menüadapter speichert kein fertiges besucherspezifisches HTML; der frühere `ctmenu`-Queryparameter bewirkt keine Löschung. Core verwaltet aktuelle Seite, Sprache, Benutzerkontext und Menüfilter im jeweiligen Request.

## Redis

Redis bleibt beim Hosting und bei der WordPress-Installation. WordPress lädt seinen Object-Cache-Drop-in vor dem Theme. Skye verwendet native Cache-/Options-/Metadaten-APIs und installiert keinen zweiten Anbieter. Genau ein zuständiger Drop-in, eigene Website-/Umgebungskonfiguration und geeignete Redis-Zugriffsrechte sind erforderlich. Ein Namenspräfix verhindert Schlüsselkollisionen, ersetzt aber keine Zugriffstrennung. Das Theme schreibt weder `wp-config.php` noch `object-cache.php` und installiert keinen Serverdienst. Redis-Objektcache und vollständiger Seitencache sind verschiedene Verträge.

## ACF PRO

ACF PRO als unverändertes Plugin separat installieren, lizenzieren und aktualisieren. Automatisierung kann den offiziellen Composer-Endpunkt verwenden; Zugangsdaten/Lizenz bleiben in privater Deployment-Konfiguration. Eigene themebezogene Felddefinitionen können nach `acf-json/` bzw. in die vorhandene Integration. Fachliche Datenmodelle können in ein Projektplugin gehören. Skye enthält weder PRO-Code noch einen Lizenzaktivator und ändert keine ACF-Originaldateien.

Die veröffentlichten Herstellerregeln sehen PRO-Bundling in kostenlosen Themes nicht vor und verweisen für Premium-Bundling auf eine vorherige Abstimmung. Der getrennte Installationsweg vermeidet die Kopplung von Pluginupdates und Themeupdates. Individuelle Lizenzverträge werden dadurch nicht bewertet.

## Auslieferung und Tests

Der requestlokale Leser für lokale CSS-/JavaScriptquellen prüft auch Cachetreffer gegen den aktuellen logischen Root, kanonischen Pfad und die Dateidentität. Ein begrenzter Streamread vergleicht den geöffneten Descriptor vor und nach dem Lesen und anschliessend nochmals den Dateipfad. Atomarer Austausch, Löschung und Symlink-Escapes liefern keine alten oder fremden Bytes. Grenzen: 1 MiB je Datei, 128 gecachte Quellen und 8 MiB gecachte Bytes pro Request. Negative Ergebnisse werden nicht dauerhaft gespeichert. Der Vertrag setzt unveränderliche, atomar bereitgestellte Quellen voraus; Änderungen innerhalb derselben Datei mit identischen Statdaten werden damit nicht erkannt. Die Prüfung ersetzt keine Provider-, Rechte- oder Registrierungsprüfung. Herkunft: `wptheme.francescaschori/lib/performance-sources.php`, Commit `16ab27a7307659502fb9e6ea0f64273c640d9395`; native Regressionen im [Prüfnachweis](testing.md).

`PathIdentity` dient dem Quellleser und den Branding-Paketstyles gemeinsam. Die gezielte Cacheinvalidierung erfasst auch logische Zwischenverzeichnisse, deren zulässige Linkziele und Root-Vorfahren. Die Auflösung bleibt auf 64 Pfadstufen, 256 Präfixe, 32 Links und 16 KiB Pfadtext begrenzt. Ein abschliessendes natives `realpath()` muss zum frisch aufgelösten Ergebnis passen. Es gibt keine Verzeichnisinventur oder globale Löschung des PHP-Realpath-Caches. Eigentums- und Dateitypprüfungen bleiben beim jeweiligen Aufrufer.

Entwicklungsprogramme, Paketmanagerdateien und Tests werden über `.gitattributes` vom Release ausgeschlossen. Der Release ergänzt nur die vollständig gebauten Assets samt Lizenzen. Ein Git-Webcheckout benötigt zusätzlich serverseitige Sperren für `.git`, Tests, Tools und Abhängigkeiten; Exportregeln sperren keinen Webserver. Der bevorzugte Weg ist der geprüfte [Releaseexport](release.md); konkrete Webserverregeln bleiben bei der Hostingkonfiguration. Die lokale HTTP-Prüfung kontrolliert ihren eigenen gesperrten Router, keinen produktiven Server.

Test-Mail, externe HTTP-Aufrufe und automatische Cronausführung sind in der lokalen WordPress-Installation unterbunden. Die Tests verwenden keine produktiven Accounts, Nachrichten, Kalender, Zahlungen oder Druckjobs. Vollständige Absicherung beliebiger Drittanbieter oder unbekannter künftiger Versionen wird nicht behauptet.

## Quellen und Herkunft

Geprüft am 03.11.2026: [Core-Editorfilter](https://developer.wordpress.org/reference/hooks/use_block_editor_for_post/), [klassische Widgets](https://developer.wordpress.org/block-editor/how-to-guides/widgets/opting-out/), [Capabilities](https://developer.wordpress.org/reference/functions/current_user_can/), [ACF-Speicherpriorität](https://www.advancedcustomfields.com/resources/acf-save_post/), [Object-Cache-Bootstrap](https://developer.wordpress.org/reference/functions/wp_start_object_cache/), [Redis-Installation](https://github.com/rhubarbgroup/redis-cache/blob/develop/INSTALL.md), [ACF-Bundling](https://www.advancedcustomfields.com/resources/including-acf-within-a-plugin-or-theme/), [ACF-Composer](https://www.advancedcustomfields.com/resources/installing-acf-pro-with-composer/).

Die historischen Optionsfunktionen wurden über umbenannte Pfade verfolgt: `register_options` in `9a25b9d` (21.11.2016), `get_theme_options` in `b6516ee` (03.02.2021), `unset_theme_options` in `ded6902` (10.12.2020); `rs_base` in `371d6ad` (16.06.2025). Neue Sicherheitsverträge verwenden `@since 3.0.0`.
