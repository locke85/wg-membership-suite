# webGefaehrte Membership Suite

> Für die Übernahme der Webgefaehrte-Cockpits aus dem Plattform-Repo gelten der
> [Übergabevertrag](docs/governance/platform-membership-community-boundary.md)
> und der
> [Migrationstask](docs/tasks/task-migrate-webgefaehrte-cockpit-into-membership-suite-01.md).
> Die Community-Ablage ist derzeit ausdrücklich geparkt.

Zweck des Plugins:

- Migration der Membership-, Checkout-, Login-, 2FA-, LMS-, Admin- und Frontend-Logik aus einem GeneratePress-Child-Theme in ein wiederverwendbares Plugin
- Erhalt bestehender `wg_*`-Kompatibilitaet
- Erhalt bestehender CPT-Slugs, Meta Keys, Shortcodes und Rollenlogik

## Abhaengigkeiten

Pflicht:

- WordPress

Optional und defensiv behandelt:

- Contact Form 7
- CF7 Multi-Step
- GeneratePress
- GenerateBlocks

Hinweis:

- GeneratePress und GenerateBlocks sind Frontend-Kontext, aber keine harte Voraussetzung.
- Contact Form 7 fehlt darf die Website nicht brechen.

## CPTs

- `wg_angebot`
- `wg_bestellung`
- `wg_rechnung`

## Historisches Finanzarchiv

Historische MemberPress-Finanzdaten werden getrennt von den operativen CPTs in
versionierten, append-only Archivtabellen gehalten. Der Import ist standardmaessig
ein Dry-run und kann erst nach bestaetigtem DB-Vollstaendigkeits- und Mapping-Gate
angewendet werden:

```sh
wp wg-finance memberpress-import --source-dir=/protected/export --manifest=/protected/export/manifest.json
```

Die Adminsuche liegt fuer berechtigte Rollen unter `Werkzeuge > Finanzarchiv`.
Eine auf Bestellungen zugeschnittene, unveraenderliche Sicht ist zusaetzlich
unter `Bestellungen > Historische Bestellungen` erreichbar. Sie filtert nach
Bestellung/Rechnung, Kunde, Produkt, Jahr, Status und Zeitraum und exportiert
die gefilterte Liste auditierbar als CSV, ohne operative `wg_bestellung`-Posts
oder Checkout-Aktionen zu erzeugen.
Originaldokumente werden nur aus `WG_FINANCE_ARCHIVE_ROOT` und nach erneuter
SHA-256-Pruefung ausgeliefert. Details stehen im
[Finanzarchiv-Runbook](docs/runbooks/memberpress-finance-archive-and-cutover.md).

Fachliche Systemgrenze: Kundenrechnungen bis einschliesslich 2025 wurden in
DATEV Unternehmen online durch den Steuerberater verarbeitet. Die operative
Erstellung, der Versand und die Zahlungszuordnung wechselten bereits zum
1. August 2025 zu Finom; erst seit 2026 entfaellt der zusaetzliche
DATEV-Rechnungsupload. Seit August 2025 werden die Rechnungen direkt in Finom
erstellt, versandt und Kontobewegungen zugeordnet;
Alt-Rechnungen werden nicht nach Finom hochgeladen. Historische Bestellungen
bleiben in der Suite als unveraenderliche Analyseprojektion fuer Registrierung,
Kundenumsatz, Laufzeiten, Pausen sowie Produkt- und Erweiterungsanalysen
auswertbar. Die v6-Analyse verbindet diese Historie read-only mit den
operativen `wg_bestellung`-Datensaetzen ab 2026. MemberPress-Umsatz und
Membership-Suite-Bestellwert bleiben getrennt; nur der kommerzielle Lifetime
Value addiert beide klar bezeichneten Groessen. Finom-Zahlungs- und
Rechnungsstatus werden nicht repliziert. Siehe
[Finanzsystemgrenze und historische Analysen](docs/finance/finance-system-boundary-and-historical-analytics.md).

Die Adminansicht liegt unter `Werkzeuge > Membership-Analysen`. Sie ist nur
fuer Administratoren mit Archivberechtigung zugaenglich und wird erst nach
erneuter Eingabe des aktuellen WordPress-Passworts fuer 15 Minuten
eingeblendet. Der JSON-Export ist an dieselbe entsperrte Sitzung gebunden.
Aktive, vor 2026 begonnene MemberPress-Abos und die zugehoerigen eindeutigen
Bestandskunden werden fuer 2026 separat ausgewiesen; sie erhoehen weder Umsatz
noch Steuer ohne eine tatsaechliche Bestellung beziehungsweise Finom-Buchung.
Zusaetzlich zeigt die Jahresauswertung die bestaetigten aggregierten
Finom-Geldeingaenge von 45.089,80 EUR fuer August bis Dezember 2025 und
48.918,54 EUR fuer 2026 YTD bis 22. August. Diese Cashflow-Snapshots werden
nicht als Netto/USt., Kunden-LTV oder Produktumsatz ausgegeben.
Die Admin-Hauptansicht fasst die Quellsysteme dafuer als `Historie / YTD`
zusammen. Quellen-, Steuer- und Bestelldetails, Mitglieder/LTV, Produkte und
Paketkandidaten bleiben in zugaenglichen Aufklappbereichen erhalten.
Vor einem Import kann derselbe Kennzahlenvertrag ohne Writes ausgefuehrt werden:

```sh
wp wg-finance memberpress-analyze --source-dir=/protected/export --manifest=/protected/export/manifest.json
```

## Shortcodes

- `[render_etappe_progress]`
- `[wg_punkte]`
- `[wg_punkte_transaktionen]`
- `[user_avatar_form]`
- `[benutzerdaten_vorschau]`
- `[bestelluebersicht]`
- `[wg_login_form]`
- `[wg_password_reset_form]`
- `[user_pdf_downloads]`

## Login-Pfad bei aktivierter 2FA

Ist die Membership-2FA aktiv, führt WordPress erzeugte Anmelde-, Abmelde- und
Sitzungsbestätigungs-URLs auf die konfigurierte Membership-Login-Seite. Der
direkte Core-Pfad `/wp-login.php` liefert eine neutrale 404-Antwort und nennt
den konfigurierten Pfad nicht. Direkte Anmelde-POSTs werden vor der
Passwortprüfung abgewiesen. Nur `postpass` für passwortgeschützte Beiträge
bleibt als eng begrenzte Core-Aktion zulässig; WordPress erzeugt auch dafür
den konfigurierten Pfad.

Dieser Anwendungsschutz reduziert Standardbot-Traffic. Er ersetzt weder eine
Ratenbegrenzung am Proxy noch Ressourcenlimits oder die Kontoabsicherung durch
2FA. Bei deaktivierter Membership-2FA bleibt das WordPress-Standardverhalten
unverändert.

Lokale Prüfung:

```sh
php tests/custom-login-endpoint-harness.php
```

Der Passwort-Reset verarbeitet beide Formulare per Post/Redirect/Get. Für die
produktive Einrichtung und SMTP-Abnahme gilt das
[Passwort-Reset-Rollout-Runbook](docs/runbooks/password-reset-rollout.md).

Der lokale Kompatibilitäts-Harness läuft ohne WordPress-Installation:

```sh
php tests/password-reset-harness.php
```

Der gemeinsame SMTP-Adapter wird unter **Einstellungen > Membership Suite >
E-Mail-Versand (SMTP)** eingerichtet. Das Passwort wird dort einmalig erfasst,
getrennt von der normalen Settings-Option verschlüsselt gespeichert und nie
wieder angezeigt. Die Schaltfläche **Speichern und Testmail senden** gilt nur
dann als erfolgreich, wenn PHPMailer die Nachricht über authentifiziertes SMTP
angenommen hat. Der lokale Transport-Harness läuft mit:

```sh
php tests/smtp-transport-harness.php
```

## Etappen- und Checkout-Standard

Version 1.9 bündelt die auf einer gemappten Multisite-Domain abgenommenen
Etappen-Metaboxen, responsiven Etappenstopp-Assets, Quizstatus-Anzeige,
Checkout-Session, Angebotsvorbelegung und getrennten Bestellmails. Redaktionelle
Stopptexte, Quiz-IDs, Angebote, Empfänger und GeneratePress-Elemente bleiben
weiterhin site-lokal und werden nicht automatisch synchronisiert.

## Settings

Option-Key:

- `wg_membership_suite_settings`

Getrennte SMTP-Optionen:

- `wg_membership_suite_smtp`: nicht geheime site-lokale Transportparameter
- `wg_membership_suite_smtp_secret`: verschlüsseltes Passwort, kein Autoload
- `wg_membership_suite_smtp_last_test`: secret-freier letzter Teststatus

Relevante Werte und Datentypen:

- `cf7_step2_form_id`:
  Integer
- `cf7_step3_form_id`:
  Integer
- `login_page_id`:
  Integer
- `password_reset_page_id`:
  Integer
- `checkout_page_ids[step_1]`:
  Integer
- `checkout_page_ids[step_2]`:
  Integer
- `checkout_page_ids[step_3]`:
  Integer
- `checkout_page_ids[step_4]`:
  Integer
- `site_enabled`:
  Boolean
- `two_factor_enabled`:
  Boolean
- `allowed_countries`:
  Array von Strings
- `allowed_payment_methods`:
  Array von Keys
- `debug_mode`:
  Boolean

Legacy-Fallbacks bleiben erhalten:

- Checkout-Slugs
- `/login-secure/`
- CF7 Step-2 Legacy-ID/Titel
- CF7 Step-3 Titel-/Formstruktur-Erkennung

## Site-Abgleich im Admin

Der Abgleich erscheint unter `Einstellungen > Membership-Abgleich`. Er liest
ein vom Plattform-Repo erzeugtes, site-spezifisches Manifest ausschließlich
als Datei und speichert nur Entscheidungen in
`wg_membership_suite_parity_reviews_v1`.

Die Site-Konfiguration stellt zwei Werte bereit:

```php
define('WG_MEMBERSHIP_SUITE_PARITY_SITE_ID', 'site-id');
define('WG_MEMBERSHIP_SUITE_PARITY_MANIFEST_PATH', '/geschuetzter/pfad/comparison.json');
```

Alternativ existieren die Filter
`wg_membership_suite_parity_site_id` und
`wg_membership_suite_parity_manifest_path`. Das Panel führt keine
Synchronisation aus. Der Export ist nur für Administratoren erreichbar,
enthält keine Manifestwerte und wird mit dem WordPress-Auth-Salt signiert.

Lokaler Contract-Test:

```sh
php tests/parity-review-harness.php
```

## Zentrale globale Kompatibilitaetsfunktionen

Beispiele:

- `wg_debug_log()`
- `wg_build_angebot_pricing()`
- `wg_get_prefill_user_data()`
- `wg_save_user_profile_data()`
- `wg_store_order_context()`
- `wg_get_order_context()`
- `wg_process_checkout_order_submission()`
- `wg_finalize_bestellung_account_flow()`
- `wg_get_bestellung_admin_edit_link()`
- `wg_get_order_resolution_link()`
- `wg_login_form_shortcode()`
- `wg_password_reset_form_shortcode()`
- `wg_user_has_2fa_enabled()`
- `wg_session_requires_2fa()`

Die Wrapper liegen in `includes/Compatibility.php` und sind `function_exists()`-geschuetzt.

## Zentrale Hooks und Integrationen

Checkout / CF7:

- `wpcf7_init`
- `wpcf7_form_tag`
- `wpcf7_form_elements`
- `wpcf7_before_send_mail`

WordPress Login / 2FA:

- `login_url`
- `site_url`
- `wp_login`
- `login_redirect`
- `rest_api_init`

Admin / Profile / CPTs:

- `add_meta_boxes`
- `save_post`
- `show_user_profile`
- `edit_user_profile`
- `personal_options_update`
- `edit_user_profile_update`

Frontend / Assets:

- `render_block`
- `wp_enqueue_scripts`
- `template_redirect`
- `wp_ajax_benutzerdaten_vorschau_refresh`
- `rest_api_init` fuer `custom/v1/spickzettel-url`

## Migrationshinweise aus dem Child Theme

- Die reduzierte Theme-`functions.php` bleibt Bridge und enthaelt keine alte Business-Logik mehr.
- Bestehende globale `wg_*`-Funktionsnamen werden im Plugin weiter bereitgestellt.
- Der REST-Endpoint `custom/v1/spickzettel-url` liegt in der Membership Suite.
- Die Nonce-Injection `window.wpRestNonce` ist site-spezifisch und bleibt in der jeweiligen Theme-`functions.php`.
- Keine destruktive Datenmigration.
- Keine Datenloeschung bei Deaktivierung.
- Bestehende Bestellungen, Angebote, Rechnungen und User-Meta bleiben lesbar.
- Rollenlogik `abonnent_<angebot_id>` bleibt unveraendert.
- Erweiterungen erzeugen keine Rollen.
- Die alte AJAX-Step-3-Bestelluebersicht wurde bewusst nicht wieder eingefuehrt.

## Spickzettel REST-Endpoint

- Namespace: `custom/v1`
- Route: `/spickzettel-url`
- Methode: `GET`
- Runtime-Ort: Membership Suite
- Site-spezifische `window.wpRestNonce`-Bereitstellung gehoert nicht ins Plugin.
- Der Endpoint liefert nur fuer den aktuell eingeloggten Nutzer die eigene `spickzettel_url`.
- Response-Struktur bleibt kompatibel:
  `user_id` und `url`

## Multisite-Status

- Single Site wird unterstuetzt.
- Multisite mit site-lokaler Aktivierung wird unterstuetzt.
- Multisite mit Network Activation wird unterstuetzt.
- Fachliche Settings bleiben pro Site.
- CF7-Formular-IDs muessen pro Site gesetzt werden.
- Page-IDs muessen pro Site gesetzt werden.
- Bei Network Activation werden bestehende Sites initialisiert.
- Neue Sites werden ueber `wp_initialize_site` initialisiert, wenn das Plugin network activated ist.
- Der Schalter `Membership Suite auf dieser Site aktiv` ist ein site-lokaler Runtime-Schalter.
- `site_enabled = 1` aktiviert die Membership-Suite-Runtime auf dieser Site.
- `site_enabled = 0` registriert Frontend-, Checkout-, Shortcode-, 2FA- und LMS-Runtime-Module auf dieser Site nicht.
- CPTs, Settings und Admin-Grundfunktionen bleiben erreichbar.
- Es werden keine Daten, Optionen, Rollen oder Inhalte geloescht.
- Der Schalter ersetzt nicht die WordPress-Plugin-Aktivierung.
- Deaktivierung loescht keine Daten.
- Es gibt keine destruktive Migration.

Technische Network-Information:

- `wg_membership_suite_network_activated`
  ist eine Network-Option und wird technisch ueber `get_site_option()`, `update_site_option()` und `delete_site_option()` verwaltet, um die netzwerkweite Aktivierung zu markieren.

Bewusst site-lokal bleibende Settings:

- `cf7_step2_form_id`
- `cf7_step3_form_id`
- `login_page_id`
- `password_reset_page_id`
- `checkout_page_ids`
- `allowed_countries`
- `allowed_payment_methods`
- `two_factor_enabled`
- `debug_mode`
- `site_enabled`

## Verhalten bei Deaktivierung

- Deaktivierung loescht keine Daten.
- `uninstall.php` fuehrt keine destruktive Bereinigung aus.
- Network Deactivation flusht Rewrites pro Site, loescht aber keine Inhalte, Rollen oder Optionen.

## Manuelle Testcheckliste

Allgemein:

- Plugin aktivieren ohne Fatal Error
- Plugin deaktivieren ohne Fatal Error
- Permalinks nach Aktivierung einmal speichern
- Debug-Modus aus
- Debug-Modus an

Settings:

- Integer-IDs speichern
- Leere IDs ohne Fatal Error
- Legacy-Fallbacks bei leerer Konfiguration
- `site_enabled = 0`
- `site_enabled = 1` nach vorheriger Deaktivierung

CPTs:

- `wg_angebot` registriert
- `wg_bestellung` registriert
- `wg_rechnung` registriert
- Vorhandene Posts aller drei Typen lesbar

Checkout / CF7:

- Verhalten ohne Contact Form 7
- Verhalten ohne GenerateBlocks
- Step 1 Weiterleitung
- Step 2 Formularerkennung via gespeicherter Formular-ID
- Step 2 Legacy-Fallback
- Step 2 Prefill
- Step 2 Speichern
- Step 3 Formularerkennung via gespeicherter Formular-ID
- Step 3 Legacy-Fallback
- Step 3 serverseitige Ausgabe ueber `[bestelluebersicht]`
- Wiederholte Submits mit gleichem `checkout_token` erzeugen keine Duplikate
- Erweiterung wird in Summary und Bestellung separat gefuehrt
- Gast-/`wg_ot`-Flow, falls auf der Site genutzt

Bestellung / Account-Finalisierung:

- Exakter Dubletten-Treffer verknuepft bestehendes Konto
- Konfliktfall landet in `pending_admin_review`
- Admin-Resolve-Links funktionieren
- Neues Konto wird nur im Finalisierungspfad angelegt

Rollen:

- Rollenvergabe nur fuer Hauptangebot
- Keine Rollenvergabe fuer Erweiterungen

Zahlungsart und Preislogik:

- Angebots-Hard-Rule fuer `zahlungsform` greift
- Step-3-Summary und gespeicherte Bestellung zeigen dieselbe effektive Zahlungsart
- Zentrale Preislogik bleibt ueber `wg_build_angebot_pricing()`

Login / Reset / 2FA:

- `[wg_login_form redirect="/schreibtisch"]`
- Reset-Flow ueber `[wg_password_reset_form]`
- 2FA deaktiviert: keine stoerenden Rewrite-/Redirect-/REST-Seiteneffekte
- 2FA aktiviert: Pending-State, Verify, Resend
- Redirect nach erfolgreicher 2FA mit und ohne `redirect_to`

Frontend / Avatar / Downloads:

- `[user_avatar_form]`
- `[user_pdf_downloads]`
- Eingeloggter Nutzer ruft `custom/v1/spickzettel-url` mit gueltiger REST-Nonce auf
- Gast wird am Spickzettel-Endpoint abgelehnt
- Fehlende oder ungueltige REST-Nonce wird am Spickzettel-Endpoint abgelehnt
- Response-Struktur von `custom/v1/spickzettel-url` bleibt kompatibel
- `[benutzerdaten_vorschau]`
- Avatar negativer Test: falscher Dateityp
- Avatar negativer Test: Datei groesser als 2 MB
- Avatar negativer Test: ungueltige Nonce
- Avatar negativer Test: Gastzugriff
- AJAX-Refresh `benutzerdaten_vorschau_refresh` mit gueltiger Nonce
- AJAX-Refresh negativer Test: ohne Nonce
- AJAX-Refresh negativer Test: ungueltige Nonce
- AJAX-Refresh negativer Test: Gastzugriff

LMS / Punkte:

- `[render_etappe_progress]`
- `[wg_punkte]`
- `[wg_punkte_transaktionen]`
- Quiz-/Aktivitaets-Sync auf Zielseite

MediaElement:

- Normale Seiten ohne Medien laden keine Speed-Assets
- Medienseiten mit Audio/Video laden Speed-Assets
- Filter `wg_membership_load_mediaelement_speed_assets` kann das Verhalten ueberschreiben

Multisite:

- Multisite mit site-lokaler Aktivierung
- Multisite mit Network Activation
- Neue Site im Netzwerk nach Network Activation
- `site_enabled = 0` und wieder `1` auf einer einzelnen Site
- Network Deactivation loescht keine Daten

## Scope

WordPress plugin for WebGefaehrte membership, checkout, LMS access, user profiles, offers, orders, and integrations with GenerateBlocks and Contact Form 7.

## Architecture

- `wg-membership-suite`: Membership, checkout, LMS, login, orders, offers
- `custom-functionality-deployment`: shared site-wide functionality
- child theme `functions.php`: site-specific exceptions only

## Status

Private internal WordPress plugin.
