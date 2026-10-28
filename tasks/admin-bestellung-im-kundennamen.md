# Task: Bestellung im Namen eines Kunden anlegen

## Ziel

Ein berechtigter interner Worker kann ausgehend von
`/schreibtisch/angebote/` eine Bestellung für einen bereits vorhandenen Kunden
anlegen. Dabei findet keine Anmeldung und keine Session-Übernahme als Kunde
statt. Die Aktion wird mit der Identität des ausführenden Workers protokolliert.
Nach erfolgreicher Verarbeitung erhält der Kunde eine Bestellbestätigung.

## Testfall

- Kunde: **Gerda Bornschier**
- Angebot: **Marketing OS - Growth**
- Umgebung: ausschließlich lokale Entwicklungs- oder Staging-Umgebung
- Vor dem Test muss Gerda Bornschier über die eindeutige WordPress-User-ID
  ausgewählt werden. Eine Namensübereinstimmung allein darf keine Bestellung
  auslösen.
- Der Mailversand muss im Test abgefangen werden (Mail-Log/Test-Mailbox); keine
  reale Nachricht versenden, bis der Test ausdrücklich freigegeben wurde.

## Umsetzung

1. Ergänze auf der Angebotsseite für berechtigte Worker die Aktion
   „Für Kunden bestellen“.
2. Öffne einen internen Bestelldialog, in dem der Worker:
   - einen bestehenden Kunden sucht und anhand von User-ID, Name und E-Mail
     eindeutig auswählt,
   - das von der Angebotsseite übernommene Angebot kontrolliert,
   - Preis, Abrechnungsintervall und Zahlungsart vor dem Absenden prüft,
   - die Bestellung ausdrücklich bestätigt.
3. Verarbeite die Bestellung serverseitig über die bestehende zentrale
   Bestelllogik (`Order_Finalizer`). Der öffentliche Checkout darf weder
   simuliert noch im Namen des Kunden abgeschickt werden.
4. Verknüpfe die neue `wg_bestellung` direkt mit dem ausgewählten Bestandskonto,
   ergänze Produkt und Angebotsrolle und setze den Auftrag erst nach vollständig
   erfolgreicher Verarbeitung auf `completed`.
5. Versende anschließend eine Bestellbestätigung an die im ausgewählten Konto
   hinterlegte E-Mail-Adresse. Betreff und Inhalt müssen mindestens
   Bestellnummer, Produkt, Bruttopreis und den Hinweis enthalten, dass die
   Bestellung im Auftrag angelegt wurde. Bestehende Zugangsdaten bleiben
   unverändert.
6. Hinterlege an der Bestellung einen Audit-Eintrag mit Worker-User-ID,
   Zeitstempel, Kunden-User-ID, Angebots-ID und dem Ursprung
   `admin_on_behalf_of_customer`.

## Sicherheits- und Fehleranforderungen

- Zugriff nur für eine explizite Capability (keine bloße Prüfung auf
  eingeloggte Benutzer); standardmäßig für Administratoren verfügbar.
- POST-Request mit Nonce und serverseitiger Prüfung sämtlicher IDs.
- Kunde und veröffentlichtes Angebot müssen unmittelbar vor dem Anlegen erneut
  geladen werden.
- Keine Änderung von Kundenstammdaten und kein Erzeugen oder Zurücksetzen eines
  Passworts.
- Doppelklicks und wiederholte Requests dürfen nicht zu Doppelbestellungen oder
  doppelten E-Mails führen. Dafür einen einmaligen Idempotency-Key speichern.
- Schlägt das Anlegen oder Verknüpfen fehl, wird keine Bestätigung gesendet und
  ein verständlicher Fehler angezeigt. Schlägt nur der Mailversand fehl, bleibt
  die Bestellung erhalten, der Mailstatus wird als Fehler protokolliert und die
  Nachricht kann kontrolliert erneut versendet werden.
- Preis- und Produktdaten werden serverseitig aus dem Angebot übernommen, nicht
  aus editierbaren Browserfeldern.

## Akzeptanzkriterien

- Ein Worker kann **Gerda Bornschier** per eindeutiger User-ID auswählen und
  **Marketing OS - Growth** für sie bestellen, ohne sich als sie anzumelden.
- Genau eine `wg_bestellung` mit eindeutiger Bestellnummer wird erzeugt und mit
  Gerdas bestehendem Konto sowie dem richtigen Angebot verknüpft.
- Das Angebot wird ihrem Konto und die zugehörige Rolle wird dem Benutzer
  zugeordnet; vorhandene Produkte und Rollen bleiben bestehen.
- Die Bestellung enthält den vollständigen Audit-Eintrag des Workers.
- Genau eine abgefangene Bestellbestätigung enthält die korrekte Empfängeradresse,
  Bestellnummer, Produktbezeichnung und den serverseitig berechneten Bruttopreis.
- Wiederholtes Absenden desselben Requests erzeugt weder eine zweite Bestellung
  noch eine zweite E-Mail.
- Ein unberechtigter Benutzer, eine ungültige Nonce, eine manipulierte Kunden-ID
  oder Angebots-ID werden abgewiesen.

## Technische Tests

- Unit-/Integrationstest für Capability-, Nonce- und ID-Prüfung.
- Integrationstest für den Erfolgsfall mit vorhandener Kundin und Angebot.
- Test auf Idempotenz bei identischem Request-Key.
- Test, dass Bestandskonto, Passwort und vorhandene Produktzuordnungen nicht
  überschrieben werden.
- Test für fehlgeschlagene Bestellanlage sowie separat für fehlgeschlagenen
  Mailversand.
- Manueller Staging-Test des oben beschriebenen Testfalls mit abgefangenem
  Mailversand und dokumentierter Bestell-ID.

## Nicht Bestandteil

- Anmeldung oder Impersonation des Kunden
- Automatisiertes Ausfüllen/Absenden des öffentlichen Checkout-Formulars
- Anlegen eines neuen Kundenkontos innerhalb dieses Ablaufs
- Reale Bestellung oder realer Mailversand im Produktivsystem ohne gesonderte
  Freigabe
