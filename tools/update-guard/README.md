# Geprüfte Pluginupdates 1.0.1

Dieser eigene MU-Baustein begrenzt Updates ausdrücklich konfigurierter Plugins auf vollständige geprüfte Originaldateien **und** den dazu abgenommenen lokalen Integrationsstand. Er funktioniert unabhängig vom aktiven Theme. Er verändert weder Pluginoriginale noch Core und implementiert keine zweite Auslieferungsprüfung.

Das Werkzeug ist für Single Sites mit WordPress 6.8.12 beziehungsweise 7.1.4, PHP 8.3, ZIP-Erweiterung und nativem direktem Dateisystemtransport vorgesehen. Multisite und andere Dateisystemtransporte werden bei geschützten Eingriffen abgewiesen. Die tatsächlichen Prüfergebnisse stehen weiter unten; eine Installation auf einer Stage oder Produktion ist damit nicht erfolgt.

## Installation und Profil

Den Ordner mit `bootstrap.php` und `lib/` separat nach `wp-content/mu-plugins/skye-update-guard/` bereitstellen. `mu-loader.php` als `wp-content/mu-plugins/skye-verified-provider-updates.php` bereitstellen. Generator, Tests und Dokumentation werden nicht auf den Webserver kopiert. Die beiden Konfigurationskonstanten müssen bereits vor dem MU-Loader feststehen, beispielsweise in der bestehenden eigenen Konfigurationsintegration:

```php
define( 'SKYE_UPDATE_GUARD_PLUGINS', array( 'gravityforms/gravityforms.php' ) );
define( 'SKYE_UPDATE_GUARD_PROFILE', '/private/config/reviewed-plugin-updates.json' );
```

Der Profilpfad ist eine lokale Datei. Die getrennte Liste der geschützten Basenamen bleibt auch bei einem fehlenden oder ungültigen Profil bekannt. Nur diese Plugins werden blockiert. Automatische Core-Sicherheitsupdates und ungeschützte Plugins behalten ihre nativen Entscheidungen und Updatewege. Eine Liste mit Gravity Forms schützt keine anderen gebundenen Plugins.

Ein Originalpaket allein ist keine Kompatibilitätsfreigabe. Den Generator erst nach der nativen und tatsächlichen FPM-Abnahme der betroffenen Kundenintegration ausführen:

```sh
set -e
profile_tmp="$(mktemp /private/config/reviewed-plugin-updates.XXXXXX)"
trap 'rm -f "$profile_tmp"' EXIT
php tools/update-guard/generate.php \
  --template=project \
  --stylesheet=project \
  --integration-root=/site/wp-content \
  --integration-file=themes/project/lib/provider-adapter.php \
  --integration-file=mu-plugins/project-foundation.php \
  gravityforms/gravityforms.php 3.2.2 /private/originals/gravityforms-3.2.2 \
  > "$profile_tmp"
chmod 600 "$profile_tmp"
mv "$profile_tmp" /private/config/reviewed-plugin-updates.json
```

Mehrere `plugin/main.php exact-version original-directory`-Tripel erzeugen weitere explizite Versionen beziehungsweise Plugins. Alle Tripel dieses Aufrufs werden an denselben geprüften Integrationssatz und dieselbe aktive Themeidentität gebunden. Für unterschiedliche Integrationsstände sind getrennte geprüfte Datensätze erforderlich. Die Datei vor der Bereitstellung vollständig prüfen; eine Teil-/Fehlerausgabe niemals über das aktive Profil schreiben. Ausgabe und Bereitstellung erfolgen über eine eigene temporäre Datei und atomaren Austausch.

Der Generator liest jede Originaldatei und führt kein Provider-PHP aus. ZIP-Kompression und Zeitstempel gehören nicht zur Identität. Pfade und SHA-256-Digests aller regulären Dateien werden verglichen; zusätzliche, fehlende oder geänderte Dateien verweigern das Paket. Symlinks und andere Dateitypen werden nicht freigegeben. Die explizite Versionsangabe wird bei der Installation zusätzlich mit dem nativ gelesenen Pluginheader verglichen.

Prerequisites sind ausdrücklich benannte eigene Dateien unter `WP_CONTENT_DIR/themes/<theme>/` oder `WP_CONTENT_DIR/mu-plugins/`. Core, Konfiguration, Uploads, private Dateien und andere Wurzeln sind nicht zulässig. Keine Versionsbereiche und keine Freigabe ausschliesslich aufgrund einer Versionsnummer. Pro Versionssatz gelten maximal 4096 Originaldateien, 64 MiB Gesamtinhalt und 16 MiB je Datei; Prerequisites sind auf 32 Dateien, 2 MiB je Datei und 16 MiB zusammen begrenzt. Profile sind auf 4 MiB, 16 Plugins und 16 Versionen pro Plugin begrenzt.

Jeder Versionssatz bindet ausserdem `theme.template` und `theme.stylesheet` an die nativ aktiven Optionen und die öffentlichen nativen Themefunktionen. Bei einem Standalone-Theme sind beide Identitäten gleich; ein Child-Theme erhält seinen eigenen Stylesheetnamen und den tatsächlichen Parentnamen. Unveränderte Dateien eines inzwischen inaktiven Themes und alleinige MU-Prerequisites genügen nicht. Der Wächter führt dafür kein Theme-PHP aus. Eine semantisch andere Reihenfolge gültiger JSON-Objektschlüssel wird akzeptiert; zusätzliche oder fehlende Felder werden abgewiesen.

Ab 1.0.1 muss zusätzlich der frisch gespeicherte Themebezug passen. `template` und `stylesheet` sind autoloaded Optionen: Ein langer Updaterrequest kann noch alte Werte lesen, obwohl ein anderer Prozess das Theme bereits gewechselt hat. Auch `get_template()` und `get_stylesheet()` teilen diese Sicht. Deshalb ergänzt jede mögliche Freigabe nach den bestehenden Funktions-/Dateiprüfungen einen vorbereiteten, rein lesenden Datenbankzugriff auf genau diese zwei Coreoptionen. Der Zugriff liefert höchstens zwei Werte von je 160 Bytes; fehlende, ungültige oder fehlerhaft gelesene Werte verweigern die Freigabe. Die vorhandenen öffentlichen Filter bleiben wirksam. Es gibt keinen Optionscache-Schreibzugriff, keinen WordPress-/Redisflush und keinen zusätzlichen Frontendhook. Profilformat und Ereignisschnittstelle bleiben unverändert.

## Updatefolge

`auto_update_plugin` verweigert unbekannte geschützte Angebote, behält bei einem passenden Profil den bisherigen Entscheid bei und aktiviert keine zuvor abgeschalteten automatischen Updates.

Bei geschützten Updates prüft `upgrader_pre_download` den vollständigen Inhalt vor der nativen Deaktivierung: bestehende native Fehler bleiben erhalten; der native Downloader verwendet die ursprünglichen HTTP-/Authentifizierungsfilter und dieselbe Signaturoption wie `WP_Upgrader::run()`. Lokale Pakete und von anderen Filtern gelieferte Downloads werden kopiert und nicht verändert oder gelöscht. Eine begrenzte ZIP-Prüfung verweigert Traversal, Links, doppelte Einträge und übermässige Expansion. Die eigentliche Entpackung übernimmt `unzip_file()` im eigenen privaten Tempverzeichnis ausserhalb der öffentlichen WordPresswurzeln. Danach werden alle Originaldateien und Integrationsvoraussetzungen geprüft. Core erhält nur den geprüften lokalen Archivpfad.

`upgrader_pre_install` verweigert eine fehlende oder inzwischen unpassende Freigabe vor Core-Priorität 10. Dort deaktiviert Core bei manuellen Einzelupdates das Plugin. `upgrader_source_selection` prüft die tatsächlich von Core entpackten Dateien und den exakten Zielordner erneut. Es erkennt auch ZIP-Installation/Überschreiben ohne Plugin-Schlüssel am geschützten Zielverzeichnis. Dieser Installationsweg deaktiviert das Plugin nativ vorher nicht.

Eine eigene späte Ablehnung nach nativer Deaktivierung reaktiviert ausschliesslich das zuvor aktive und weiterhin vollständig identische geprüfte Original über `activate_plugin(..., silent: true)`. Aktivierungshooks werden dabei nicht ausgelöst. Der ursprüngliche lokale Aktivierungszustand wird wiederhergestellt, soweit keine fremden Aktivierungsänderungen dazwischenliegen. Kann das nicht sicher geschehen, bleibt ein kritischer Wiederherstellungsfehler sichtbar. Es gibt keine automatische Reaktivierung veränderter oder unbekannter Originale.

Dasselbe gilt für einen früheren fremden `WP_Error` in der Quellenauswahl oder vor der Dateibehandlung. Bei erfolgreicher Wiederherstellung bleibt das ursprüngliche Fehlerobjekt unverändert. Fehler nach bereits begonnenem nativem Dateiaustausch werden nicht durch eine eigene blinde Reaktivierung behandelt; die nativen Backup-/Fehlerwege bleiben zuständig.

Eigene frühe Fehler werden zusätzlich im öffentlichen nativen `WP_Upgrader::$result` hinterlegt: Die nativen `Plugin_Upgrader::upgrade()`-/`install()`-Wrapper lesen dieses Feld, statt einen frühen Rückgabewert aus `run()` zu übernehmen. Ohne diese Ergänzung könnte eine tatsächliche Ablehnung als `null` beim Aufrufer ankommen. Fremde ungeschützte Updatewege werden dadurch nicht geändert.

Private eigene Tempverzeichnisse werden am Requestende mit dem nativen Dateisystem entfernt. Enthält die konfigurierte native Tempwurzel einen öffentlich erreichbaren WordPresspfad, wird der geschützte Download vorher verweigert. Es werden keine Lizenz-URLs, Zugangsdaten, Formulardaten, HTML oder Provider-Fehlermeldungen in eigene Optionen oder Ereignisse übernommen.

## Betriebsstatus und bestehende Projektkontrolle

Das Ereignis hat genau einen Arrayparameter:

```php
do_action( 'skye_update_guard_event', array(
    'plugin' => 'gravityforms/gravityforms.php',
    'version' => '3.2.2', // Leer, wenn keine überprüfte Version feststeht.
    'reason' => 'runtime_check_required',
    'status' => 'changed',
) );
```

`changed` wird ausschliesslich nach einem tatsächlich erfolgreichen geänderten Einzelpaket ausgelöst. Eine angeforderte Bulkliste ist kein Erfolgsnachweis. Das Projekt startet darauf seine vorhandene frische anonyme FPM-Auslieferungsprüfung. Das Ereignis behauptet keine erfolgreiche Laufzeitkompatibilität. Ein erneutes identisches Paket erzeugt kein `changed`.

`blocked` meldet eine Updateverweigerung und ist kein Seitenausfall. `restoration_failed` ist ein eigener kritischer Wächterfehler. Er darf durch späteren Auslieferungserfolg nicht verschwinden. Der Baustein hält begrenzte Verweigerungs-/Wiederherstellungsdatensätze ohne Autoload in `skye_update_guard_status`; ein fehlerhafter Schutzbereich wird in `skye_update_guard_configuration_error` festgehalten. Zusätzlich erhält jeder kritische Wiederherstellungsfehler einen ausschliesslich mit nativem `add_option()` angelegten Datensatz `skye_update_guard_critical_<sha256(plugin-basename)>`. Normale oder gleichzeitig laufende alte Requests überschreiben diesen Datensatz nicht. Site Health prüft ihn unabhängig vom normalen Statusarray. Backendhinweise sind nicht wegklickbar. Der eigene direkte Site-Health-Test `skye_update_guard` prüft ausschliesslich Profil-/Integrationsgültigkeit und Wächterfehler, ohne HTTP-Auslieferungsprobe.

Nach tatsächlicher Behebung bestätigt ein Betreiber die gespeicherten Hinweise ausdrücklich über die bestehende eigene Betriebsführung oder die nativen Options-APIs. Ein Delivery-Erfolg löscht sie nicht. Kein zusätzlicher Mailversand und keine externe Benachrichtigung werden installiert.

Profile und Prerequisites werden erst bei einem Update oder der administrativen Wächterprüfung gelesen. Normale Frontendrequests laden diese Daten nicht. Nur bei diesen administrativen Dateiidentitätsprüfungen wird der native PHP-Realpathcache aufgefrischt, damit ein externer atomarer Verzeichniswechsel keine alte Pfadidentität vortäuscht. WordPress-, Redis- und Anwendungscaches werden nicht geleert.

## Prüfungen

Die native Suite benötigt eine ausdrücklich markierte Wegwerf-Site aus `tools/testing/setup-site.py`. Sie erstellt nur eigene kleine Testplugins/ZIPs und eine eigene Integrationsdatei. Keine Gravity-Forms-Datei wird als Testopfer geändert.

```sh
php tools/update-guard/tests/native.php /absolute/disposable/wordpress
```

Am 5. November 2026 bestanden für Version 1.0.0 jeweils **158 Aussagen** auf den unveränderten Original-Coreversionen WordPress 6.8.12 und 7.1.4 unter PHP 8.3.36 mit getrennten SQLite-Wegwerf-Sites. Dieser Ausgangsstand bleibt in Commit `75a31c6` erhalten. Die Suite verwendet die tatsächlichen Core-Upgrader und den tatsächlichen MU-Bootstrap, keine nachgebauten Updater-Gegenstellen. Normale Ablehnungen bewahren vollständige Originaldateien und die exakte native Aktivliste samt Reihenfolge. Die vollständigen Coreinventare blieben identisch; neue PHP-Warnungen, Notices, Deprecations und unbehandelte Fehler: 0. Eigene MU-/Paket-/Backup-Testreste: 0.

Die zusätzliche Zweiprozess-Gegenprobe gegen 1.0.0 bestätigte einen Fehler: Nach einem externen nativen Themewechsel las der warme Parent weiter alle vier alten Options-/Funktionswerte und liess ein eigentlich freigegebenes Pluginpaket für die inzwischen andere aktive Integration zu. Version 1.0.1 besteht je **193 Aussagen** auf denselben beiden unveränderten Coreversionen unter PHP 8.3.36. Ein externer Wechsel vor dem Update wird vor `pre_install` abgewiesen; ein Wechsel unmittelbar nach der ZIP-Freigabe wird in `pre_install` nochmals vor der nativen Priorität-10-Deaktivierung erkannt. Originaldateien und Aktivliste bleiben identisch. Der Parent behält seine alten öffentlichen Cachewerte nachweislich, während der Wächter den frischen Datenbankstand prüft. Separate native Kindprozesse stellen die ursprünglichen Themeoptionen auch im Fehlerfall wieder her. Coreinventare, PHP-Diagnosefreiheit und rückstandsfreie Fixturebereinigung bleiben geprüft. Dieser lokale 1.0.1-Nachweis behauptet noch keinen neuen Hosted-, MySQL-/Redis- oder Stage-/FPM-Lauf.

Geprüft sind Einzel-, Bulk- und automatische Updates, ZIP-Überschreiben ohne Plugin-Key, unbekannte/manipulierte/zusätzliche/fehlende Dateien, Traversal, Integrationsabweichung, falsches Quellziel, fremde Fehler vor beziehungsweise nach der nativen Deaktivierung und identische Fehlerobjekte im öffentlichen Upgraderresultat. Geänderte aktive Parent-/Child-Themebezüge werden trotz unveränderter gültiger Prerequisites und Pluginpakete getrennt vor der Deaktivierung abgewiesen. Unterschiedliche ZIP-Kompression, Zeitstempel und Entryreihenfolge lassen dieselben Originaldateien weiterhin zu. Jede Core-Suite erreicht einmal den echten `download_url()`-/Streamingpfad; ausschliesslich dessen Transportantwort ist synthetisch, es findet kein externer Download statt.

Der offizielle unveränderte [WP-CLI 2.12.0-PHAR](https://github.com/wp-cli/wp-cli/releases/tag/v2.12.0) wurde gegen den veröffentlichten SHA-256-Digest `ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c` geprüft. Echte Kindprozesse mit `--skip-themes --skip-plugins` belegen den geladenen MU-Wächter und einen abgewiesenen nativen Updateversuch ohne Datei-/Aktivverlust. `SKYE_WP_CLI` benennt für Wiederholungen den ausdrücklich bereitgestellten lokalen PHAR-Pfad; die Suite lädt kein Werkzeug stillschweigend herunter.

Der CI-Vertrag führt diese Suite in allen vier bestehenden PHP-/Core-Paaren aus, unmittelbar nach dem Fixture-Setup. Der Workflow lädt den unveränderten offiziellen WP-CLI-PHAR über den festen Releasepfad und prüft denselben Digest vor der Ausführung. Die Sitewurzel und das Profilsetup bleiben unverändert; der PHAR liegt getrennt unter `.git/runtime/`. Vollständiges Testprotokoll, begrenzte native Fehlerannotation und tatsächlicher fehlschlagender Exitstatus bleiben erhalten. Der Workflow wurde mit actionlint 1.7.12 geprüft; die tatsächlichen Hosted-Läufe werden nach Veröffentlichung des Branches separat bewertet.

Ein echter nativer Aktivierungsvalidierungsfehler belegt den kritischen Wiederherstellungszustand. Die konkurrierende Persistenzprüfung benutzt drei echte WordPress-Prozesse mit warmem altem Optionscache. Sie ruft den tatsächlichen atomaren Recorder gezielt per Reflection auf und prüft anschliessend den kritischen Datensatz und Site Health in einem frischen Prozess. Das ist kein MySQL-/Redis-Mehrprozessnachweis. Die Suite vervollständigt pro Fall die öffentlichen nativen Backup-Restore-/Delete-Shutdowncallbacks als Requestabschluss, bevor derselbe eigene Pluginname im nächsten Fall erneut verwendet wird.

Die vollständigen unveränderten Gravity-Forms-Originalverzeichnisse wurden zusätzlich mit dem tatsächlichen Generator rein lesend inventarisiert: 1100 Dateien für 3.2.1 und 1120 für 3.2.2. Ein eigener inaktiver Integrationssatz prüfte dabei die Ausgabe; fehlende Integrations-/Themeangaben wurden verweigert. Alle Originaldigests blieben unverändert, und es wurde kein Siteprofil geschrieben. Das ist kein Kompatibilitätsnachweis einer Kundenintegration und erzeugt ohne deren abgenommene Prerequisites keine freigegebene Registry. Keine Provider-PHP-Datei wurde zur Inventarisierung ausgeführt. Die native Suite führt keine echten GF-Pakete aus und behauptet keine FPM-/Stage-/Browser-/Geräteabnahme.

## Grenzen

Ein Prozess mit Schreibrechten ausserhalb des WordPress-Upgraders kann die Hooks umgehen oder den MU-Baustein entfernen. Änderungen über Root/SFTP/Deployment müssen deshalb ebenfalls durch die bestehende Projektkontrolle und die Deploymentabnahme geprüft werden. Der Wächter ersetzt weder diese Kontrolle noch einen Betriebskalender für zurückgehaltene Sicherheitsupdates. Eine neue Pluginversion wird nicht stillschweigend vertraut, sondern mit dem dazugehörigen Integrationsstand neu geprüft. Die Laufzeitprüfung muss die tatsächliche FPM-Ausführung und eine frische Ursprungsantwort belegen; CLI, Themeheader und ein beliebiger Cachetreffer genügen nicht.

## Gelesene Originalverträge

Offizielle WordPress-Quellen, geprüft am 5. November 2026:

- [MU-Ladefolge](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) und [WP-CLI skip-themes/skip-plugins](https://developer.wordpress.org/cli/commands/plugin/update/).
- [Auto-Update-Entscheidung](https://developer.wordpress.org/reference/hooks/auto_update_type/), [Pre-download](https://developer.wordpress.org/reference/hooks/upgrader_pre_download/), [nativer Downloader](https://developer.wordpress.org/reference/classes/wp_upgrader/download_package/) und [download_url](https://developer.wordpress.org/reference/functions/download_url/).
- [Installationsreihenfolge](https://developer.wordpress.org/reference/classes/wp_upgrader/install_package/), [Plugin-Deaktivierung](https://developer.wordpress.org/reference/classes/plugin_upgrader/deactivate_plugin_before_upgrade/) und [native stille Aktivierung](https://developer.wordpress.org/reference/functions/activate_plugin/).
- [ZIP-Installation](https://developer.wordpress.org/reference/classes/plugin_upgrader/install/), [Bulk-Update](https://developer.wordpress.org/reference/classes/plugin_upgrader/bulk_upgrade/), [Einzelresultat](https://developer.wordpress.org/reference/hooks/upgrader_install_package_result/) und [native Entpackung](https://developer.wordpress.org/reference/functions/unzip_file/).
- [Site Health](https://developer.wordpress.org/reference/hooks/site_status_tests/) und [native Backendhinweise](https://developer.wordpress.org/reference/functions/wp_admin_notice/).
- [Optionscache](https://developer.wordpress.org/reference/functions/get_option/) und [wp_load_alloptions](https://developer.wordpress.org/reference/functions/wp_load_alloptions/): auch `force_cache=true` erzwingt keinen gezielten frischen Datenbankzugriff. Der begrenzte Ausnahmepfad verwendet [wpdb::prepare](https://developer.wordpress.org/reference/classes/wpdb/prepare/) und [wpdb::get_results](https://developer.wordpress.org/reference/classes/wpdb/get_results/).
