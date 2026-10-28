# Task: Passwort-Reset auf `/login-secure/` reparieren

## Status

Plugin-Korrekturen am `2026-08-17` implementiert und mit dem lokalen
WordPress-Kompatibilitäts-Harness verifiziert. Der produktive WordPress-Content-
Rollout, die Konfiguration eines authentifizierten Mailtransports und die
Abnahme gegen die produktive Umgebung stehen noch aus. Die dafür nötigen
Schritte sind im
[Passwort-Reset-Rollout-Runbook](../runbooks/password-reset-rollout.md)
dokumentiert.

Implementiert sind:

- eigener Reset-Link über `retrieve_password_notification_email`
- explizite Request-, Versand-, Reset-, Fehler- und Abschlusszustände
- Prüfung ungültiger und abgelaufener Schlüssel schon beim Seitenaufruf
- sichtbare und zugängliche Nonce- und Mailtransportfehler
- Post/Redirect/Get für beide POST-Formulare
- identische öffentliche Antwort für existierende und unbekannte Konten
- secret-freies `wp_mail_failed`-Logging
- automatisierter Harness für die acht geforderten Fälle

## Ziel

Der Passwort-Reset auf
`https://workspace.webgefaehrte.de/login-secure/` muss ohne WordPress-Core-
Zwischenseite vollständig funktionieren:

1. Nutzer öffnen das Resetformular zuverlässig.
2. Eine Reset-Anfrage wird über einen betriebsfähigen Mailtransport versendet.
3. Der Link in der Mail führt mit `key` und `login` zurück auf die konfigurierte
   Membership-Suite-Seite.
4. Fehler, Erfolg und abgelaufene Schlüssel bleiben sichtbar und verständlich.
5. Das neue Passwort kann gesetzt und anschließend zur Anmeldung verwendet
   werden.

## Verifizierter Ist-Stand

### Nicht ursächlich

- `/login-secure/` liefert über normales DNS und vertrauenswürdiges TLS HTTP
  `200`.
- Die Membership Suite ist auf `wp9` aktiv (`0.1.0`).
- Die produktive Datei
  `includes/Shortcodes/Password_Reset_Form.php` ist bytegleich mit dem Stand in
  diesem Repository (SHA-256:
  `99dd0f5a4026c5345cc5819fa7c2d276672dd948b37466f771b0e7d6821c6c0a`).
- `login_page_id=173308`; die Reset-Seite fällt bewusst über den Legacy-Slug
  `login-secure` auf dieselbe veröffentlichte Seite zurück.
- Das Formular `[wg_password_reset_form]` wird aus dem veröffentlichten
  GenerateBlocks-Overlay `Password Reset` (Post-ID `200189`) gerendert.
- Nonce und Eingabefeld sind im ausgelieferten HTML vorhanden.

## Ursachen

### P0: Der Overlay-Trigger ist nicht mit dem Overlay verbunden

Die veröffentlichte Login-Seite (Page-ID `173308`) speichert folgenden Link:

```html
<a href="#" type="internal" id="#pw-reset">Passwort zurücksetzen</a>
```

Das Resetformular liegt dagegen in diesem initial geschlossenen Overlay:

```html
<div
  id="gb-overlay-200189"
  data-gb-overlay
  data-gb-overlay-trigger-type="click"
  aria-hidden="true">
```

GenerateBlocks Pro `2.5.0` öffnet ein Click-Overlay ausschließlich über ein
Element mit `data-gb-overlay="<overlay-id>"`. Der aktuelle Link besitzt dieses
Attribut nicht. `id="#pw-reset"` ist außerdem als Trigger wirkungslos; der
Hash gehört nicht in ein HTML-ID-Attribut. Ein Klick öffnet deshalb das
Resetformular nicht.

### P0: Auf Hetzner existiert kein betriebsfähiger Mailtransport

Der WordPress-Mailer steht zur Laufzeit auf:

- Mailer: PHP `mail()`
- Host: `localhost`
- Port: `25`
- SMTP-Authentifizierung: aus
- SMTP-Benutzer/Passwort: nicht konfiguriert
- PHP `sendmail_path`: `/usr/sbin/sendmail -t -i`

Auf `wp9` fehlt `/usr/sbin/sendmail`. Es sind außerdem weder Postfix, Exim,
msmtp, ssmtp, nullmailer noch Sendmail installiert. Die vorhandenen
`phpmailer_init`-Hooks konfigurieren zur Laufzeit keinen SMTP-Transport.

Damit kann `retrieve_password()` keine Reset-Mail zustellen. Der Code in
`Password_Reset_Form.php:31-35` reicht den Fehler zwar an `$error` weiter, die
Meldung liegt aber im geschlossenen Overlay und ist für den Nutzer praktisch
unsichtbar.

### P1: Die Reset-Mail führt nicht zur eigenen Resetmaske

`Password_Reset_Form::register()` registriert nur den Filter
`lostpassword_url`. Dieser Filter ändert den Link zur Anforderungsmaske, nicht
den Link in der versendeten Reset-Mail.

Es existiert kein Filter für `retrieve_password_message` oder
`retrieve_password_notification_email`. WordPress erzeugt deshalb weiterhin
einen Core-Link nach:

```text
/wp-login.php?action=rp&key=...&login=...
```

Der eigene Resetmodus in `Password_Reset_Form.php:22-25` erwartet `key` und
`login` dagegen auf der konfigurierten Membership-Suite-Seite. Dieser Zweig
wird von einer durch das Plugin erzeugten Mail derzeit nicht adressiert.

### P1: Rückmeldungen verschwinden nach dem POST wieder im Overlay

Das Overlay wird initial mit `aria-hidden="true"` ausgeliefert. Nach einem
Formular-POST wird die Seite neu aufgebaut, ohne das Overlay bei `$message`
oder `$error` automatisch zu öffnen. Selbst bei funktionierendem Mailtransport
wirkt der Vorgang deshalb wie ein stiller Fehler.

Zusätzlich entsteht aktuell ungültiges Markup in der Form
`<p><form>...</form></p>`, weil der Shortcode innerhalb eines Absatzblocks
gerendert wird. Browser reparieren dieses Markup unterschiedlich.

### P2: Ungültige Nonces bleiben ohne Rückmeldung

Wenn ein Reset-POST weder einen gültigen Request- noch Reset-Nonce enthält,
durchläuft `Password_Reset_Form::render()` keinen Fehlerzweig. Besonders bei
zwischengespeicherten oder abgelaufenen Seiten sieht der Nutzer dann nur
erneut das Formular.

### P2: Mailfehler sind nicht beobachtbar

Für `wp_mail_failed` ist kein Handler registriert, und auf der Instanz wurde
kein passendes Mail-/SMTP-Log gefunden. Produktionsfehler lassen sich daher
nur aus dem Frontend-Verhalten ableiten.

## Erforderliche Korrekturen

### 1. Resetformular ohne versteckten Overlay-Zustand anbieten

Bevorzugte Lösung:

- `[wg_password_reset_form]` auf einer eigenen veröffentlichten Seite oder in
  einem normalen, inline sichtbaren Abschnitt rendern.
- `password_reset_page_id` explizit auf diese Seite setzen.
- Auf `/login-secure/` einen normalen Link zu dieser Seite verwenden.
- Den Shortcode nicht in einen Absatzblock einschließen.

Falls das Overlay bewusst beibehalten wird, muss der Trigger mindestens auf
das tatsächliche Overlay zeigen:

```html
<a
  href="#pw-reset"
  data-gb-overlay="gb-overlay-200189">
  Passwort zurücksetzen
</a>
```

GenerateBlocks ergänzt daraus die ARIA-Attribute. Zusätzlich muss das Overlay
bei Request-POST, Reset-POST, `$message` oder `$error` automatisch geöffnet
werden. Nur den `href` zu korrigieren reicht nicht aus.

Die aktuelle Trigger-Fehlkonfiguration liegt in WordPress-Content, nicht in
diesem Plugin-Repository. Der Content-Fix benötigt deshalb einen separat
dokumentierten produktiven Page-/Overlay-Rollout.

### 2. Authentifizierten Mailtransport auf Hetzner bereitstellen

- Einen betreibbaren SMTP-Transport oder einen bewusst administrierten MTA
  konfigurieren.
- Zugangsdaten ausschließlich über Server-Secrets bzw. den vorgesehenen
  Credential-Workflow bereitstellen; keine Secrets in Git oder WordPress-
  Seiteninhalt speichern.
- Absenderdomain, SPF, DKIM und DMARC passend zur verwendeten Domain prüfen.
- Mit einem synthetischen Testkonto und einer freigegebenen Test-Mailbox
  Zustellung und Linkinhalt prüfen.

Der Plugin-Code darf eine erfolgreiche Zustellung erst melden, wenn `wp_mail`
erfolgreich zurückkehrt. Für Nutzer sollte die Antwort aus Datenschutzgründen
dennoch unabhängig von der Existenz eines Kontos gleich formuliert sein.

### 3. Reset-Link in der Mail auf die Membership-Suite-Seite routen

In `Password_Reset_Form::register()` einen Filter für
`retrieve_password_notification_email` oder `retrieve_password_message`
registrieren. Der Link muss mit `add_query_arg()` aus
`Config::get_password_reset_url()` und diesen Parametern gebaut werden:

- `action=rp`
- `key=<reset-key>`
- `login=<user-login>`

Parameter müssen URL-kodiert, die finale URL im Mailtext escaped und die
Multisite-/Network-Semantik getestet werden. Der existierende
`lostpassword_url`-Filter bleibt für den Einstieg in den Flow zuständig.

### 4. Formularzustände explizit und sichtbar behandeln

- Request, Versandbestätigung, ungültiger/abgelaufener Schlüssel, Eingabe des
  neuen Passworts und Abschluss als getrennte Zustände modellieren.
- Ungültige oder fehlende Nonces mit einer sichtbaren generischen Meldung
  beantworten.
- Fehlertexte nicht roh aus WordPress übernehmen, wenn sie die Existenz eines
  Kontos offenlegen können.
- Nach erfolgreichem POST das Post/Redirect/Get-Muster einsetzen, damit ein
  Reload keine erneute Mail oder Passwortänderung auslöst.
- Rückmeldungen mit `role="status"` bzw. `role="alert"` zugänglich ausgeben.

### 5. Mailfehler secret-frei protokollieren

Einen `wp_mail_failed`-Handler ergänzen, der mindestens Zeit, Site-ID,
Fehlercode und Kontext protokolliert. Empfängeradresse, Mailinhalt,
Reset-Schlüssel, Passwort und SMTP-Zugangsdaten dürfen nicht in Logs gelangen.

## Tests

Im Repository ist derzeit kein Composer-/PHPUnit-Testgerüst vorhanden. Für
die Korrektur ist mindestens ein kleiner WordPress-Integrationstest-Harness
anzulegen.

Erforderliche automatisierte Fälle:

1. `lostpassword_url` zeigt auf die konfigurierte Request-Seite.
2. Die Reset-Mail enthält die konfigurierte Seite sowie `action`, `key` und
   `login`, aber keinen Core-Reset-Link.
3. Ein gültiger Schlüssel rendert die Passwortfelder.
4. Ungültiger und abgelaufener Schlüssel erzeugen eine sichtbare Meldung.
5. Fehlender/ungültiger Nonce erzeugt eine sichtbare Meldung.
6. Mailtransportfehler werden sichtbar und secret-frei protokolliert.
7. Erfolg und Fehler bleiben nach einem POST sichtbar; es gibt keine
   Formularverschachtelung in `<p>`.
8. Ein gesetztes Passwort ermöglicht anschließend die Anmeldung.

Produktive Abnahme:

- Desktop- und Mobile-QA der Request-, Fehler-, Reset- und Erfolgszustände
- Test über normales DNS und vertrauenswürdiges TLS
- synthetisches Testkonto statt eines realen Nutzerkontos
- freigegebene Test-Mailbox oder lokaler Mail-Sink
- Reset-Mail genau einmal zugestellt
- Link öffnet die Membership-Suite-Maske
- Passwortänderung und anschließender Login erfolgreich
- keine Reset-Schlüssel, Cookies oder Mailinhalte in QA-Artefakten

## Browser-QA-Infrastruktur

Der read-only Browser-QA-Lauf konnte aus diesem Repository nicht gestartet
werden. Der Runner erwartete
`control-plane/browser-qa/browserQa.mjs` innerhalb von `membership-suite`, die
Datei existiert dort nicht. Der Lauf endete als Infrastrukturfehler (Exit 2),
bevor Chromium oder eine Seite geprüft wurde; es wurden keine Screenshots
erzeugt.

Vor der finalen Abnahme muss entweder die gemeinsame Control-Plane-Anbindung
für dieses Repository eingerichtet oder ein offiziell unterstützter Runner-
Einstieg dokumentiert werden.

## Sicherheits- und Änderungsgrenze dieser Diagnose

- Es wurde keine reale Reset-Anfrage abgesendet.
- Es wurde keine E-Mail erzeugt oder gelesen.
- Es wurden keine Passwörter, Reset-Schlüssel oder Mailboxdaten verarbeitet.
- Produktionsdaten und Plugin-Code wurden nicht verändert.
- Die Korrekturen in diesem Dokument benötigen einen separaten
  Implementierungs- und Rollout-Schritt mit Backup und Abnahme.
