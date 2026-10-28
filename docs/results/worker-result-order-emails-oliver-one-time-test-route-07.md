# Ergebnis: Bestellmails und einmalige Testroute

Status: `nicht freigegeben: authentifizierter Mailtransport fehlt`  
Zielsite: `tanzlehrer-recruiting.de`, Multisite-Blog `8`  
Stand: `2026-09-04`

## Erreicht

- Die erfolgreiche Checkout-Verarbeitung erzeugt eine Käufermail und eine
  getrennte Bestellbenachrichtigung für den site-lokal konfigurierten
  Angebotsverantwortlichen.
- WordPress-Nutzer 30 wurde read-only als Oliver mit gültiger E-Mail-Adresse
  bestätigt und anschließend als Angebotsverantwortlicher konfiguriert.
- Die alte CF7-Mail von Checkout-Schritt 3 an `[_site_admin_email]` wird nur
  für dieses Abschlussformular übersprungen, damit kein drittes, inhaltlich
  überholtes Duplikat entsteht. Andere CF7-Formulare sind nicht betroffen.
- Versandversuche werden pro Bestellung mit Nachrichtenart, Zeit, Erfolg,
  Testroutenstatus sowie Hashes der vorgesehenen und tatsächlichen Empfänger
  protokolliert. Mailtext und Klartext-Empfänger werden nicht im Log abgelegt.
- Der Übergabeentwurf für Oliver liegt unter
  `docs/handoffs/email-oliver-pilot-ready-uebergabe.md`. Er wurde nicht
  versendet.

## Fehlgeschlagene Zustellabnahme

Jan hat bestätigt, dass keine der beiden an ihn umgeleiteten Nachrichten im
Postfach angekommen ist. Der vorherige Wert `sent=true` belegt nur den
erfolgreichen Rückgabewert von `wp_mail()`, nicht die Annahme durch einen
authentifizierten SMTP-Relay oder die Zustellung im Postfach. Die frühere
Statusbewertung `abgeschlossen` ist damit aufgehoben.

Flamingo zeigt die gespeicherte CF7-Übermittlung. Die dort sichtbaren Zeilen
mit dem Vorlagenbetreff `[your-subject]` sind weder die Käufermail noch die
Oliver-Mail und liefern keinen Nachweis für ausgehenden Transport.

Der secret-freie Live-Audit zeigt:

- PHPMailer läuft mit `mailer=mail`, `host=localhost`, Port `25` und ohne
  Authentifizierung.
- Es ist kein SMTP-Plugin aktiv und keine bekannte SMTP-Option vorhanden.
- Die vorhandenen Konstanten `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`,
  `SMTP_PORT`, `SMTP_SECURE`, `SMTP_FROM` und `SMTP_FROM_NAME` sind sämtlich
  nicht definiert.
- Das aktive Custom-Functionality-Plugin enthält bereits den passenden
  `phpmailer_init`-Adapter; er wird erst aktiv, wenn die drei verpflichtenden
  SMTP-Konstanten sicher in der Serverkonfiguration vorhanden sind.
- Die Domain veröffentlicht einen SPF-Eintrag, aber keinen DMARC-TXT-Eintrag.

## Einmalige Testroute

Die inzwischen verbrauchte Testroute war begrenzt auf:

- Trigger-Nutzer: `1` (`locke85`)
- Nur direkte Frontend-Bestellung, keine Bestellung im Kundenauftrag
- Nur eine Bestell-ID größer als `49566`
- Ziel beider Membership-Suite-Nachrichten: `jan@webgefaehrte.de`
- Betreffkennzeichnung: `[TEST KÄUFER]` und `[TEST OLIVER]`

Bestellung `49635` hat die Route atomar beansprucht, das Testziel an genau
dieser Bestellung für beide Nachrichten gespeichert und die globale Armierung
gelöscht. Künftige Bestellungen werden daher wieder regulär an Käufer und
Oliver zugestellt.

## Nachweise

- PHP-Lint für beide geänderten Runtime-Dateien und alle drei Hilfsskripte:
  bestanden.
- `tests/order-mail-routing-harness.php`: bestanden. Normalempfänger,
  Oliver-Empfänger, beide Testumleitungen, Betrefftrennung, automatische
  Deaktivierung, anderer Käufer, ältere Bestellung, fehlender Owner,
  Hash-Protokoll und CF7-Skip wurden geprüft.
- Vollständig abgefangener Live-`wp_mail`-Lauf: genau zwei Nachrichten,
  Käufer und Oliver, beide erfolgreich an WordPress übergeben; externer
  Transport absichtlich blockiert; Bestellbestand unverändert `3`.
- Live-Read-back: Owner `30`, Testroute aktiv, Trigger `1`, Grenz-ID `49566`,
  Bestellbestand `3`.
- Read-only-Abnahme nach der echten Bestellung: Bestellung `49635`, Status
  `completed`, Nutzer `1`, Bestellbestand `4`, Owner `30` und globale
  Testroute entfernt.
- Das Bestellprotokoll enthält genau zwei Einträge: `kaeufer` und `oliver`,
  beide `sent=true`, beide `routed_to_test=true`, beide mit demselben Hash des
  vorgesehenen Testziels. Die vorgesehenen Empfänger-Hashes stimmen mit den
  WordPress-Nutzern `1` beziehungsweise `30` überein.
- Live-Dateihashes:
  - `Order_Finalizer.php`:
    `40feeaac0023f097d818ca333f6d8584fcc1a3be2efa5d8a6494c32033a4fb35`
  - `Step_3_Order.php`:
    `a48bd109a480749410b8c8c8c73e4b3f3435390e86326d14b8e1cd03c7a40da6`
- Anonyme Browser-QA: Schritt 3 besteht auf Desktop und Mobil ohne harte
  Befunde. Der bereits bekannte mobile Horizontal-Overflow auf Angebotsseite
  und Schritt 1 bleibt als getrennte Layout-Aufgabe sichtbar.

## Echte Testbestellung

Jans Bestellung wurde am `2026-09-04` vollständig systemseitig geprüft:

1. neue Bestell-ID `49635` ist größer als `49566`,
2. genau zwei erfolgreiche Logeinträge `kaeufer` und `oliver`,
3. beide Einträge mit `routed_to_test=true`,
4. identischer Hash des Testempfängers,
5. globale Testroute gelöscht.

`sent=true` belegt nur, dass WordPress beide Nachrichten an PHP `mail()`
übergeben hat. Jan hat den fehlenden Eingang selbst bestätigt; damit ist die
Zustellabnahme fehlgeschlagen, ohne dass dafür ein Mailboxzugriff durch den
Worker stattgefunden hat.

## Benötigt für die Korrektur

Der Membership-Suite-SMTP-Adapter und die nicht geheimen Serverdaten für
Olivers Postfach sind live vorbereitet. Oliver gibt das Passwort einmalig in
der geschützten Membership-Suite-Adminansicht ein und löst dort eine Testmail
aus. Danach werden genau die beiden Nachrichten der Bestellung `49635` erneut
als gekennzeichneter Test an Jan gesendet. Erst die Bestätigung beider Eingänge
gibt den Regelversand an Käufer und Oliver frei.

Die Umsetzung und Abnahme bis zu diesem Übergabepunkt ist unter
`docs/results/worker-result-smtp-transport-oliver-one-time-setup-08.md`
dokumentiert.

## Rückrollpunkte

- `Order_Finalizer.php.before-order-mails-20260904`
- `Step_3_Order.php.before-order-mails-20260904`
- Vorher waren Owner-Option und Testroute nicht gesetzt.

## Evidenz

- `sites/tanzlehrer-recruiting/artifacts/order-mail-routing/2026-09-04/`
- `sites/tanzlehrer-recruiting/artifacts/order-mail-routing/2026-09-04/verification-after-order.php`
- `sites/tanzlehrer-recruiting/artifacts/order-mail-routing/2026-09-04/mail-transport-audit.php`
- `sites/tanzlehrer-recruiting/artifacts/browser-qa/2026-09-04-order-mail-routing/browser-qa-summary.md`
