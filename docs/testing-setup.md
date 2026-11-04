# Isolierte WordPress-Testinstallation

`tools/testing/setup-site.py` richtet eine ausdrücklich verwaltete lokale SQLite-Testinstallation ein. Das Werkzeug startet keinen Webserver und greift auf keine bestehende produktive Installation zu. Redis und ACF PRO werden nicht installiert. Die Quellen von WordPress, SQLite Database Integration und optional Classic Editor bleiben unverändert.

## Voraussetzungen und Aufruf

Im Repository-Verzeichnis ausführen. Benötigt werden Python 3.10+, eine echte `.git/`-Verzeichnisstruktur, Symlink-Unterstützung und PHP ab 8.3 mit PDO SQLite, SQLite ab 3.37.0, mbstring, DOM und GD. Linux und macOS werden damit unterstützt; ein fehlendes Werkzeug oder eine fehlende Erweiterung führt zum Fehler statt zu einem übersprungenen Test.

```sh
export SKYE_PHP_BINARY=php
export SKYE_SITE_ROOT="$PWD/.git/runtime/site/wordpress"
python3 tools/testing/setup-site.py --classic-editor
"$SKYE_PHP_BINARY" tests/site-verify.php
"$SKYE_PHP_BINARY" tests/site-verify.php --activate-classic-plugin
"$SKYE_PHP_BINARY" tests/site-verify.php
```

`--classic-editor` installiert das originale Plugin 1.7.0, aktiviert es aber nicht. Die erste Prüfung läuft ohne aktives Classic-Editor-Plugin. Der zweite Befehl aktiviert es ausdrücklich mit konkurrierenden Editorpräferenzen für die folgende Prüfung. Ein erneuter Setup-Lauf schaltet dieses bekannte Testplugin wieder in die inaktive Anfangsphase.

Für die Mindestversion einen eigenen leeren Installationspfad und eine eigene Ergebnisdatei verwenden:

```sh
python3 tools/testing/setup-site.py \
  --root .git/runtime/site-68 \
  --fixture-output .git/runtime/site-68/fixture.json \
  --wordpress-version 6.8.12 \
  --classic-editor
```

Die CI setzt `--php-binary php` beziehungsweise `SKYE_PHP_BINARY`. Der Standard ist WordPress 7.1.4, der unterstützte zweite Prüfstand 6.8.12. Eine neue Version wird erst nach Quellenprüfung und Aktualisierung des festen Prüfsummenkatalogs aufgenommen. Es gibt keinen ungeprüften `latest`-Download.

Anschliessend kann ein eigener Terminalprozess den ausdrücklich lokalen Server starten:

```sh
"$SKYE_PHP_BINARY" -S 127.0.0.1:8973 -t "$SKYE_SITE_ROOT" tests/server.php
```

Die Testkonfiguration verwendet fest `http://127.0.0.1:8973`. Der Serverrouter und die Browserprogramme bleiben eigene Prüfwerkzeuge. Eine abgeschlossene Einrichtung allein belegt keinen Browser-/Geräte- oder MySQL-/MariaDB-Lauf.

## Eigentum, Daten und Wiederholung

Alle Setup-Pfade müssen unter diesem Checkout in `.git/runtime/` liegen. Bestehende Fremdverzeichnisse, symbolisch umgeleitete Kontrollpfade, fremde/geänderte WordPress-Konfigurationen und konkurrierende Datenbank-Drop-ins werden abgewiesen. Das Werkzeug löscht keine vorhandene Website und überschreibt keine Originalpakete. Eine Versionsänderung oder eine Änderung generierter Hilfsdateien benötigt einen neuen leeren Testpfad. Ein eigener Marker und der Konfigurationshash erkennen Wiederholungen derselben Einrichtung.

Die neue `wp-config.php` enthält zufällige Salts und wird mit Modus `0600` erstellt. SQLite-Datenbank und Debuglog liegen ausserhalb des Webroots. Automatische Cron-Ausführung und Datei-/Paketupdates sind deaktiviert. Ein eigenes Test-MU-Plugin fängt Mail vor dem Transport ab und sperrt externe WordPress-HTTP-Aufrufe; nur der exakte lokale Testserver-Ursprung bleibt zulässig. Neu geschriebene WordPress-Diagnosen brechen die Einrichtung ab.

Das Theme wird als Symlink auf diesen Quellcheckout eingebunden. `tests/site-fixture.php` installiert die kleine native Datenmenge; ein eigener, nur lokal erzeugter CLI-Adapter aktiviert Skye und weist das native Hauptmenü zu. Ergebnis-IDs stehen standardmässig in `.git/runtime/site-fixture.json`, mit `--fixture-output` in der ausdrücklich genannten privaten Datei. Bestehende Ergebnisdateien eines anderen oder unmarkierten Testpfads werden nicht überschrieben. Konfiguration, Datenbank, Pakete und Zugangsdaten werden nicht ins Repository aufgenommen.

Die Archive werden vor dem Entpacken anhand fester SHA-256-Werte geprüft. Die Extraktion akzeptiert nur sichere relative Paketpfade und reguläre Dateien/Verzeichnisse. Links, verschlüsselte ZIP-Einträge, Traversal und übergrosse Archive werden zurückgewiesen. Das originale `db.copy` des SQLite-Plugins wird bytegleich als `wp-content/db.php` kopiert; seine vorgesehenen Pfad-Fallbacks machen Laufzeitpatches oder Text-Ersetzungen überflüssig. Quelle und Prüfsumme der verwendeten Originalarchive stehen im privaten `provenance.json` der Einrichtung.

## Geprüfte Quellen und Nachweise

Die Prüfsummen wurden am 03.11.2026 für die über HTTPS bezogenen Originalarchive festgehalten:

| Paket | Version | SHA-256 |
| --- | --- | --- |
| WordPress | 7.1.4 | `881e53a4681ff2cd911651b11cccdede5ae477ddf0765af5d6a1a8b219a1d835` |
| WordPress | 6.8.12 | `4bdd6bef38ac46f80acc0d3bf686d96708b5a44dab6bb66a0c97d3c3d740371e` |
| SQLite Database Integration | 3.1.3 | `f6ce35e8419d0b9326221a907627d905e25c2a5ba2afc86492811f14c851efe0` |
| Classic Editor | 1.7.0 | `7cc7799b0b7d820fbb7528dbff8027c736e52513992416cb37279f44d3d9dd77` |

Lokale Prüfung mit PHP 8.3.36 auf macOS: frische Einrichtung beider WordPress-Versionen, Wiederholung, native Themeprüfung ohne aktives Classic-Plugin sowie nach dessen originaler Aktivierung mit konkurrierenden Präferenzen. Die Einrichtung setzt dessen Ausgangszustand bei Wiederholung korrekt zurück. Über beide Installationen wurden 7.106 Originaldateien aus Core und beiden Plugins bytegleich zu ihren Archiven verglichen; die Datenbank-Drop-ins entsprechen ihren jeweiligen Originalvorlagen. Unsichere Root-Pfade und vorhandene Fremdverzeichnisse wurden abgewiesen, ihre Inhalte erhalten. Dies ist kein Nachweis einer bereits gelaufenen CI oder echter Mobilgeräte.

Primärquellen: [WordPress 7.1.4](https://wordpress.org/wordpress-7.1.4.tar.gz), [WordPress 6.8.12](https://wordpress.org/wordpress-6.8.12.tar.gz), [SQLite-Plugin](https://wordpress.org/plugins/sqlite-database-integration/), [SQLite-Originalarchiv](https://downloads.wordpress.org/plugin/sqlite-database-integration.3.1.3.zip), [Classic-Editor-Originalarchiv](https://downloads.wordpress.org/plugin/classic-editor.1.7.0.zip), [Mail-Preflight](https://developer.wordpress.org/reference/hooks/pre_wp_mail/), [HTTP-Preflight](https://developer.wordpress.org/reference/hooks/pre_http_request/).
