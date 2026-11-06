# Markenfarben im Backend und Kundenlogo im Login

Ab Skye 3.4 übernimmt die WordPress-Verwaltung die vorhandene Websitepalette. Primäre Buttons, aktive Menüeinträge, Fokuszustände und weitere native Bedienelemente verwenden die Markenfarben. Das Login zeigt das Kundenlogo und verlinkt auf die Website. Fehlt ein gültiges Bild, erscheint der Websitename.

Es gibt keine zweite redaktionelle Farbeinstellung und keine separate Logoauswahl für den Login. Authentifizierung, Formularfelder, Rollen, Fehlerhinweise und gespeicherte Benutzer-Farbschemata bleiben unter WordPress-Kontrolle. Das Branding schreibt keine Optionen oder Benutzermetadaten. Die Backend-Styles werden nicht im öffentlichen Frontend geladen.

## Farbquelle und Build

Der normale Node-14-Build erzeugt `_branding` im nativen `dist/assets.json`. Die Standardquelle ist das kleine `backend.css`: Dessen `--skye-source-*`-Variablen verwenden dieselben Sass-Werte aus `assets/styles/general/_variables.scss` wie die Website. Es werden keine Farben aus Bildern geraten oder beim Seitenaufruf aus vollständigem Frontend-CSS gelesen.

`assets/manifest.json` beschreibt die Quellstylesheet-Datei und die vorhandenen CSS-Variablen je Rolle. Der Extraktor `tools/build/branding.cjs` liest unverändertes, kompiliertes CSS: höchstens 2 MiB, statische Hexwerte in `:root`, auch innerhalb unbedingter `@layer`. Fehlende Werte, berechnete Farben, bedingte Rootfarben und mehrfach unterschiedlich definierte Werte brechen den Build ab; das letzte vollständige Manifest bleibt erhalten. Der Extraktor ersetzt keine CSS-Cascade-Engine.

Ein Projekt mit eigenen Farbvariablen verwendet denselben Extraktor in seinem bestehenden Publisher:

```js
const {extractBranding} = require('./branding.cjs');
const palette = extractBranding(compiledWebsiteCss, {
	primary: '--project-primary',
	secondary: '--project-secondary',
	background: '--project-background',
	text: '--project-text'
});
// Publish palette as _branding in the existing native assets.json.
```

Der Publisher verändert die öffentlichen CSS-Bytes dafür nicht. Bei einem eigenständigen Kundenbuild kann die eigene Skye-Hilfsdatei mit Herkunft und SHA-256 übernommen werden; zusätzliche npm-Pakete oder ein manueller Buildparameter sind nicht nötig.

## Logo und Projektadapter

`skye_brand_identity()` liefert die bestehende Identität ohne Bild-HTML. Die Reihenfolge bleibt natives `custom_logo`, danach das bisherige `theme_logo` aus den Themeoptionen. `skye_brand_model()` verwendet denselben Resolver und erhält seinen bisherigen Frontendvertrag.

Verwendet ein Projekt bereits eine andere Logoquelle, bindet ein eigener Adapter deren vorhandenen Producer über `skye_branding_logo` an. Der Filter erhält die bisherige URL und liefert eine öffentliche absolute HTTP(S)-Bild-URL zurück. Die Kundenprojekte können damit ihr bestehendes ACF-Logo oder ihren bereits vorhandenen statischen Logofallback übernehmen. Kein zusätzliches CMS-Feld ist nötig.

`skye_branding_colors` erhält die vier Farben `primary`, `secondary`, `background` und `text`. Ein Adapter muss alle vier als sechsstellige Hexwerte liefern. Unvollständige oder ungültige Werte behalten die nativen WordPress-Farben. Das Login-Logo und sein Textfallback funktionieren unabhängig von einer gültigen Palette.

Skye liest die kleinen Paketstyles `backend.css` und `login.css` aus dem tatsächlichen Parent- beziehungsweise Standalone-Build. Ein Child-Manifest steuert seine eigene Palette, ersetzt diese Paketstyles aber nicht versehentlich durch das vollständige Frontend-CSS.

## Ausgabe und Grenzen

Native Enqueue- und Login-Hooks übernehmen die Auslieferung. Die Palette wird als begrenzte CSS-Variablen ergänzt. Logo-URLs werden weder heruntergeladen noch in HTML oder Stylesheetpfade umgedeutet; Steuerzeichen, Markup, Zugangsdaten und fremde Protokolle sind nicht zulässig. Die Login-Styles ändern keine Form-Actions, Nonces oder Authentifizierungsprüfungen.

Buttontext und Linktöne werden anhand der WCAG-sRGB-Luminanz auf mindestens 4,5:1 geprüft beziehungsweise angepasst. Für die weisse native Loginform gibt es eigene lesbare Text-/Linktöne; sie übernimmt nicht blind die Textfarbe eines dunklen Websitehintergrunds. Fokus und Forced-Colors-Textfallback bleiben sichtbar. Das ersetzt keine vollständige Geräte- oder Barrierefreiheitsabnahme.

### Vollständige native Menüzustände ab 3.4.1

WordPress hält ein offenes Hauptmenü über `li.opensub`, während der Mauszeiger bereits im Untermenü steht. Der Hauptlink selbst ist dann weder `:hover` noch `:focus`. Das Backendstylesheet umfasst deshalb diesen nativen Elternzustand, den direkten LI-Hover, aktuelle Einträge und die dazugehörigen Icons. Die Adminbar erhält die entsprechenden Fokus-, geöffneten Eltern- und Untermenüzustände. Sichtbarkeit, Ereignisbehandlung und Positionierung bleiben bei Core.

Gewöhnliche Links verwenden bewusst nur die Spezifität der nativen `a`- beziehungsweise `a:hover/:active/:focus`-Regeln. Höher spezifische Core-Regeln für Löschaktionen, Statusmeldungen und ausgewählte Tabs bleiben wirksam. Es gibt keine globale Entfernung von Outline oder Boxshadow. Die nativen WordPress-Farbvariablen einschliesslich `--wp-admin-theme-color-rgb` und `--wp-admin-theme-color--rgb` verwenden denselben geprüften Verwaltungston.

Core 6.8 verwendet auch für normale Linkbuttons noch feste Farben. Ein begrenzter Farb-Fallback nimmt Löschaktionen und deaktivierte Controls ausdrücklich aus. Nur unter der nativen `branch-6-8`-Klasse erhält der bestehende 2px-Fokusschatten diesen Ton; neuere Core-Zweige behalten ihre native variablenbasierte Geometrie.

Die zusätzlichen `admin-link`- und `admin-link-hover`-Tokens erreichen mindestens 4,5:1 auf Weiss, `#f0f0f0` und `#f0f0f1`; die vorhandenen Website- und Loginfarben bleiben erhalten. Das Paket wird weiterhin über die native Abhängigkeit `colors` nach dem tatsächlich gewählten Farbschema geladen. Es registriert kein zweites Farbschema und schreibt keine Benutzerpräferenz.

`tests/branding-native.php` prüft die tatsächlichen WordPress-Registrierungen und unabhängig berechneten Kontraste. `tests/browser-branding.cjs` verwendet unveränderte Core-Styles für Fresh, Modern und Blue sowie zwei unterschiedliche Markenpaletten. Echte Maus-/Fokusaktionen prüfen die Cascade an vorbereiteten nativen UI-Zuständen einschliesslich des geöffneten Elternmenüs. Die bestehende Browser-CI führt den Test mit ihren drei Engines aus; lokale Läufe und tatsächliche CI-/Kundenabnahmen sind getrennte Nachweise.

Primärquellen: [Admin-Assets](https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/), [Login-Assets](https://developer.wordpress.org/reference/hooks/login_enqueue_scripts/), [native Inline-Styles](https://developer.wordpress.org/reference/functions/wp_add_inline_style/), [Login-Link](https://developer.wordpress.org/reference/hooks/login_headerurl/), [zugänglicher Login-Text](https://developer.wordpress.org/reference/hooks/login_headertext/), [WCAG-Kontrastberechnung](https://www.w3.org/TR/WCAG22/#dfn-relative-luminance). API- und Quellprüfung am 05.11.2026; tatsächliche Läufe stehen im [Prüfnachweis](testing.md).
