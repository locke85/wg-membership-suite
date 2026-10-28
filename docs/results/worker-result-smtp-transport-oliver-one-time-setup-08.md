# Ergebnis: SMTP für Oliver mit einmaliger Passworterfassung

Status: `wartet auf Olivers einmalige Passworteingabe`  
Zielsite: `tanzlehrer-recruiting.de`, Multisite-Blog `8`  
Stand: `2026-09-04`

## Umgesetzt

- Die Membership Suite enthält einen wiederverwendbaren SMTP-Adapter für
  authentifiziertes SMTP mit STARTTLS beziehungsweise SSL/TLS.
- Nicht geheime Transportparameter und das SMTP-Passwort liegen in getrennten
  site-lokalen Optionen. Das Secret wird mit einem aus WordPress-Salts und
  Blog-ID abgeleiteten Schlüssel verschlüsselt und nicht automatisch geladen.
- Unter **Einstellungen > Membership Suite > E-Mail-Versand (SMTP)** kann ein
  Administrator das Passwort einmalig eingeben. Das Passwortfeld wird nach
  der Speicherung immer leer gerendert; eine neue Eingabe ersetzt das Secret.
- **Speichern und Testmail senden** speichert nur dann
  `accepted_by_smtp`, wenn `wp_mail()` erfolgreich war und PHPMailer
  tatsächlich mit `mailer=smtp` und aktivierter Authentifizierung lief.
- Der Teststatus enthält nur Status, Zeitpunkt und ausführende Nutzer-ID,
  niemals Empfänger, Nachrichtentext, Passwort oder Serverantwort.

## Live-Konfiguration für Oliver

- Plugin-Liveversion: `1.6.2` als enger SMTP-Patch auf Basis des zuvor
  installierten Stands `1.6.1`.
- SMTP-Server: `kunden.tanzschulservice.de`
- Port: `587`
- Verschlüsselung: STARTTLS
- Benutzer und Absender: `oliver.fleidl@tanzschule-muenchen-dt.de`
- Absendername: `Tanzlehrer Recruiting`
- SMTP bleibt deaktiviert, bis Oliver das Passwort selbst erfasst.
- Oliver, WordPress-Nutzer `30`, besitzt auf Blog `8` die erforderliche
  Berechtigung `manage_options`.
- Die Absenderdomain nennt `kunden.tanzschulservice.de` mit Priorität `100`
  als MX. Für die Domain sind weder ein abweichender Submission-SRV noch ein
  eigener `smtp`-/`mail`-Host veröffentlicht; deshalb bleibt der bereits
  technisch bestätigte MX-Host die belastbare Vorbelegung.

Der lokale Repository-Stand trägt `1.8.0`, weil er zusätzlich noch nicht auf
dieser Site veröffentlichte Repository-Funktionen enthält. Für den Live-Patch
wurden diese fremden Änderungen nicht mit ausgerollt.

## Nachweise

- Alle PHP-Harnesses des Membership-Suite-Repositories bestanden.
- `tests/smtp-transport-harness.php` prüft Sanitizing,
  Verschlüsselungs-Roundtrip, Site-Bindung, Konfiguration von PHPMailer sowie
  das leere und secret-freie Passwortfeld.
- Live-Read-back bestätigt exakt ein leeres, erforderliches Passwortfeld und
  die Schaltfläche **Speichern und Testmail senden** für Nutzer `30`.
- Der finale Live-Read-back zeigt den Membership-Suite-Callback mit Priorität
  `999`. Ohne Secret bleibt PHPMailer erwartungsgemäß im bisherigen
  `mail`-Modus; erst Olivers Speichern aktiviert authentifiziertes SMTP.
- Ein synthetischer Live-Roundtrip bestätigt Verschlüsselung und Entschlüsselung
  ohne Datenbank-Write; die echte Secret-Option blieb unverändert leer.
- Der Zielserver ist aus der WordPress-Runtime erreichbar: SMTP-Greeting `220`,
  EHLO `250`, STARTTLS `220`, TLS-Verbindung erfolgreich.
- Der erneute Live-Read-back bestätigt nach der kontrollierten Migration die
  Domain `tanzschule-muenchen-dt.de` für Benutzer und Absender, weiterhin
  `secret_present=false` und `enabled=false`.
- Öffentliche Zielsite nach Deployment: HTTP `200`.
- Live-Dateihashes:
  - Bootstrap: `eb7c3f5d4792d7319f0a3c55bcd7cad8a1ba85830710ca0b5101194126e38e03`
  - Plugin-Registrierung: `1f46f60c14a7658adbe97dad9d4618a03d7fe9d2b1d696d9d2e8a3cc046fde97`
  - Settings-Seite: `cb551780fdfe61347df8e95ed6aed810d924b11c401bd1124c10ec9453b41612`
  - SMTP-Adapter: `8f4ee31a4728cbf2a4a03672914dfaaf6b324d15b5c3fd2b86b44b74a1ede8b7`

## Offene Abnahme

1. Oliver öffnet den direkten SMTP-Abschnitt aus der Übergabemail.
2. Er gibt das Passwort des Postfachs ausschließlich dort ein und löst die
   Testmail aus.
3. Oliver bestätigt Jan den tatsächlichen Eingang.
4. Danach werden die beiden Bestellmails der Bestellung `49635` erneut als
   begrenzter Test an Jan versendet und erst nach bestätigtem Eingang für den
   Regelbetrieb freigegeben.

Ein Worker hat weder ein SMTP-Passwort noch eine Mailbox gelesen oder
gespeichert. Die Übergabemail wurde ergänzt, aber nicht versendet.

## Rückrollpunkte

- `wg-membership-suite.php.before-smtp-20260904`
- `includes/Plugin.php.before-smtp-20260904`
- `includes/Admin/Settings_Page.php.before-smtp-20260904`
- Die neue Datei `includes/Integrations/SMTP.php` kann entfernt werden, nachdem
  die drei gesicherten Dateien wiederhergestellt wurden.

## Evidenz

- `sites/tanzlehrer-recruiting/artifacts/order-mail-routing/2026-09-04/configure-oliver-smtp.php`
- `sites/tanzlehrer-recruiting/artifacts/order-mail-routing/2026-09-04/mail-transport-after-smtp-deploy.php`
- `sites/tanzlehrer-recruiting/artifacts/order-mail-routing/2026-09-04/smtp-live-before/`
- `sites/tanzlehrer-recruiting/artifacts/order-mail-routing/2026-09-04/smtp-live-build/`
