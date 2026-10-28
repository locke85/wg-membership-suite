# Task: Authentifiziertes SMTP für Oliver mit einmaliger Passworterfassung

Status: `wartet auf Olivers einmalige Passworteingabe`

Zielsite: `tanzlehrer-recruiting.de`, Multisite-Blog `8`  
Angebotsverantwortlicher: WordPress-Nutzer `30` (Oliver)

## Ziel

Die Membership Suite versendet Bestell-, Login- und Systemmails über einen
authentifizierten SMTP-Transport. Oliver kann das SMTP-Passwort einmalig in
einer geschützten Adminansicht erfassen. Es wird verschlüsselt, niemals wieder
im Formular ausgegeben und erscheint weder in Logs, Exporten noch
Übergabedokumenten. Nach der Erfassung löst Oliver selbst eine Testmail an sein
angemeldetes Administratorkonto aus.

## Scope

- Wiederverwendbarer Membership-Suite-SMTP-Adapter für PHPMailer.
- Site-lokale, nicht geheime SMTP-Konfiguration für Host, Port, STARTTLS,
  Benutzer, Absender und Absendername.
- Getrennte verschlüsselte Secret-Option mit sitegebundener Schlüsselableitung
  aus den WordPress-Salts.
- Adminformular nur für `manage_options`; Passwortfeld bleibt nach Speicherung
  leer und meldet nur, ob ein Secret vorhanden ist.
- Gemeinsame Aktion „Speichern und Testmail senden“ mit secret-freiem
  Ergebnisstatus.
- Vorbelegung für Oliver: `kunden.tanzschulservice.de`, Port `587`, STARTTLS,
  Benutzer/Absender `oliver.fleidl@tanzschule-muenchen-dt.de`, Name
  `Tanzlehrer Recruiting`. Der Server wird aus dem MX der Absenderdomain
  abgeleitet und technisch verifiziert.
- Ergänzung der Übergabemail um den einmaligen Erfassungsschritt.

## Grenzen

- Kein SMTP-Passwort in Git, Chat, SFTP-Konfiguration, Task, Result, Logs oder
  Artefakten.
- Keine Mailbox wird durch den Worker gelesen.
- Vor Olivers Eingabe wird kein Passwort erfunden, übertragen oder gespeichert.
- Die Testaktion belegt die Annahme durch den SMTP-Relay, nicht automatisch den
  Posteingang.
- Die fehlgeschlagene Bestellung `49635` wird erst nach bestandenem SMTP-Test
  gezielt erneut versendet; es wird keine neue Bestellung erzeugt.

## Rückrollpunkt

Vor dem Live-Deploy werden die betroffenen Plugin-Dateien serverseitig
gesichert und gehasht. Die neue site-lokale SMTP-Konfiguration und die
Secret-Option werden mit ihrem Vorherzustand erfasst. Ein Rollback entfernt
Adapter, Adminpanel und neue Optionen beziehungsweise stellt die gesicherten
Dateien wieder her.

## Ausführbare Abnahme

1. Der lokale Harness prüft Sanitizing, Verschlüsselungs-Roundtrip,
   Site-Bindung, leere Passwortausgabe und PHPMailer-Konfiguration.
2. Ohne Secret bleibt SMTP deaktiviert; PHP `mail()` wird nicht als
   authentifizierter Erfolg ausgegeben.
3. Oliver besitzt auf Blog `8` nachweislich `manage_options` und sieht die
   einmalige SMTP-Erfassung in Einstellungen > Membership Suite.
4. Live-Read-back bestätigt nur die nicht geheimen Werte und
   `secret_present=false`; kein Artefakt enthält die Adresse zusammen mit einem
   Passwort oder ein anderes Secret.
5. Nach Olivers Eingabe zeigt PHPMailer `smtp`, Port `587`, STARTTLS,
   Authentifizierung und gesetzte Zugangsdaten ausschließlich als boolesche
   Zustände.
6. Die von Oliver ausgelöste Testmail wird vom SMTP-Relay angenommen; Oliver
   bestätigt den Eingang. Erst danach werden die zwei Bestellmails aus
   Bestellung `49635` erneut an Jan getestet.

## Ergebnis

`docs/results/worker-result-smtp-transport-oliver-one-time-setup-08.md`
