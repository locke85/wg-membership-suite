# Task: Bestellmails an Käufer und Oliver mit einmaliger Testroute

Status: `blockiert: authentifizierter SMTP-Transport fehlt`

Zielsite: `tanzlehrer-recruiting.de`, Multisite-Blog `8`  
Angebotsverantwortlicher: WordPress-Nutzer `30`  
Einmaliger Testkäufer: WordPress-Nutzer `1` (`locke85`)

## Ziel

Nach einer erfolgreich verarbeiteten Bestellung erhält der Käufer seine
Bestell-/Zugangsmail und Oliver eine eigene Benachrichtigung zur neuen
Teilnahme. Für genau die nächste Bestellung des Testkäufers werden beide
Membership-Suite-Nachrichten ausschließlich an `jan@webgefaehrte.de` gesendet,
eindeutig als Käufer- beziehungsweise Oliver-Test gekennzeichnet und danach
automatisch aus dem Testmodus genommen.

Parallel entsteht ein versandfertiger, aber nicht automatisch versendeter
Übergabeentwurf für Oliver mit Prüfliste, den aus dem eigenen Durchlauf
entstandenen Korrekturen und dem Link zur Bestell-Landingpage.

## Scope

- Erfolgreicher Checkout-Abschluss in `Order_Finalizer`.
- Käufermail für bestehende und neue Konten.
- Neue Bestellbenachrichtigung an den site-lokal konfigurierten Nutzer `30`.
- Einmalige, auf Nutzer `1` und eine spätere Bestellung begrenzte Testroute.
- Versandprotokoll ohne Mailtexte oder Klartext-Empfänger in Bestell-Meta.
- Read-only-Inventur der vorhandenen CF7-Mailkonfiguration, Nutzerreferenzen
  und bisherigen Bestellzahl.

## Grenzen

- In dieser Task wird keine Bestellung erzeugt oder abgeschlossen.
- Die Übergabemail an Oliver wird nur als Entwurf erstellt und nicht versendet.
- Kein Mailboxzugriff und keine Prüfung von Posteingängen.
- Kein Catch-all-Filter für WordPress-Mails; nur die zwei expliziten
  Membership-Suite-Bestellmails dürfen umgeleitet werden.
- Das Initialpasswort einer echten neuen Kundenbestellung darf außerhalb der
  einmaligen, ausdrücklich aktivierten Testroute niemals an Dritte gehen.

## Rückrollpunkt

Vor Live-Writes werden Dateien, relevante Optionen, Nutzerreferenzen,
Bestellzahl und Hashes read-only erfasst. Dateien erhalten serverseitige
Backups. Die neue Owner-Option und Testroute werden mit ihrem exakten
Vorherzustand gesichert. Die Testroute gilt nur für eine Bestellung mit einer
Bestell-ID oberhalb des Aktivierungsstands und löscht ihre Armierung atomar bei
der ersten passenden Bestellung.

## Ausführbare Abnahme

1. Tests bilden Käufer- und Oliver-Mail, gültigen/fehlenden Owner,
   unbeteiligte Besteller, passende nächste Testbestellung, zwei umgeleitete
   Nachrichten und anschließende Deaktivierung ab.
2. Der normale Versand behält den echten Käufer als Empfänger und nutzt die
   gültige E-Mail des konfigurierten Nutzerkontos 30 für die Oliver-Mail.
3. Die einmalige Testroute greift nicht bei älteren Bestellungen oder anderen
   Nutzer-IDs; beide Testnachrichten tragen unterscheidbare Betreffe.
4. Ein vollständig abgefangener WordPress-Lauf prüft Nachrichtenerzeugung und
   Empfängerlogik, ohne eine externe Mail zu senden.
5. Live-Read-back bestätigt Owner 30, aktive Einmalroute für Nutzer 1,
   Aktivierungs-Bestell-ID, Dateihash und unveränderten Bestellbestand.
6. Die vom Nutzer ausgelöste Bestellung `49635` ist abgeschlossen. Das
   Versandprotokoll enthält genau die beiden erwarteten, von WordPress
   angenommenen Nachrichten `kaeufer` und `oliver`; beide wurden an das
   einmalige Testziel geroutet. Die globale Testarmierung ist entfernt.
   Der vom Nutzer bestätigte fehlende Eingang widerlegt jedoch eine
   funktionsfähige Zustellung. Vor der erneuten Abnahme muss eine freigegebene
   SMTP-Absenderidentität über den bereits vorhandenen CFP-Konstantenadapter
   konfiguriert werden.

## Ergebnis

`docs/results/worker-result-order-emails-oliver-one-time-test-route-07.md`
