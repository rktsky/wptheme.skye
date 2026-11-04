# Native Mailgrenzen auf Staging ab 3.1

`EnvironmentMail` registriert die öffentlichen Filter `pre_wp_mail` und
`gform_pre_send_email`. Nur wenn `wp_get_environment_type()` den nativen Wert
`staging` liefert, wird Mail standardmässig blockiert. `local`, `development`,
`production` und die native Produktionsvorgabe bleiben unverändert. Der
Staging-Filter kann andere Umgebungen nicht nachträglich einschalten.

Für eine ausdrücklich gewollte native Staging-Zustellung kann ein Projekt
`skye_block_staging_mail` auf `false` filtern. Das ist keine automatische
Mail-Testfreigabe; Empfänger und gewünschte externe Auswirkungen müssen zum
konkreten Auftrag gehören.

```php
add_filter( 'skye_block_staging_mail', '__return_false' );
```

`pre_wp_mail` liefert bei ansonsten unentschiedener Staging-Zustellung `false`:
es wurde keine Nachricht zugestellt. Vorherige Nicht-null-Entscheidungen anderer
nativer Provider bleiben erhalten. Der Callback empfängt keine Mailargumente.
Bei Gravity Forms wird ausschliesslich `abort_email = true` gesetzt. Vorhandene
Abortflags sowie alle anderen Transportwerte bleiben erhalten; bei Opt-out und
in anderen Umgebungen bleibt das gesamte Argument unverändert.

Die Klasse inspiziert und protokolliert keine Empfänger, Inhalte, Header,
Anhänge oder gespeicherten Einträge. Gravity Forms ist keine Abhängigkeit;
seinen öffentlichen Filter zu registrieren bleibt ohne Plugin wirkungslos.
Core-, Plugin- und Serverdateien werden nicht verändert. Direkte externe
Transport-APIs ausserhalb dieser nativen Hooks gehören nicht zu diesem Vertrag.

## Nachweis

`tests/environment-mail.php` verwendet unveränderte native Core-Hooks,
`wp_get_environment_type()` und die tatsächliche Funktion `wp_mail()`. Ein
zusätzlicher Testinterceptor stoppt jeden Transport, auch bei Produktions- und
Opt-out-Prüfungen. Es findet kein Testversand statt. 16 Assertions prüfen
Staging, Produktion/Local/Development, vorhandene Preemption-/Abortentscheidungen,
Opt-out, unbekannte Providerargumente und die reine Staging-Grenze.
PHP 8.3.36 mit WordPress 6.8.12 und 7.1.4: bestanden.

Primärquellen, gelesen am 04.11.2026:
[WordPress-Mailpreemption](https://developer.wordpress.org/reference/hooks/pre_wp_mail/),
[native Umgebungswerte](https://developer.wordpress.org/reference/functions/wp_get_environment_type/)
und [Gravity-Forms-Transportgrenze](https://docs.gravityforms.com/gform_pre_send_email/).
