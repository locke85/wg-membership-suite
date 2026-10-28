# Task: Checkout-Übergang von Schritt 1 zu Schritt 2 reparieren

Status: `abgeschlossen`

Zielsite: `tanzlehrer-recruiting.de`  
Betroffener Nutzerlauf: angemeldeter Nutzer `locke85`

## Ziel

Nach Auswahl des E-Recruiting-Angebots in Schritt 1 öffnet Schritt 2 unter
`/schreibtisch/angebote/2-daten-eingeben/` mit eindeutig übergebenem und
validiertem Angebot. Die Meldung `Please fill out the form on the previous
page.` darf bei einer gültigen Auswahl nicht erscheinen.

## Scope

- Membership-Suite-Checkout-Vertrag für Schritt 1, gespeicherten Angebotskontext
  und Schritt 2.
- Tatsächliche CF7-/Mehrschritt-Formularkonfiguration und site-lokale
  Checkout-Seiten auf Multisite-Blog 8.
- Eng begrenzte Plugin-Korrektur und deren Deployment auf die Zielsite, falls
  die Ursache im zentralen Plugin liegt.

## Grenzen

- Keine Bestellung wird abgeschlossen und keine Zahlung ausgelöst.
- Keine Änderung an bestehenden Bestellungen, Rollen oder Kundendaten.
- Keine testweise Übernahme eines ungültigen oder fremden Angebots.
- Vorhandene Änderungen anderer Arbeitsstränge im Plugin-Repo bleiben
  unberührt.

## Rückrollpunkt

Vor dem Live-Write werden die tatsächlich betroffenen Plugin-Dateien mit Hash
und Zeitstempel gesichert. Der Write ist nur bei passendem Vorher-Hash erlaubt.
Site-Optionen, Nutzer-Metadaten und Bestellzahlen werden vor und nach der
Korrektur read-only inventarisiert; diagnostische Daten enthalten keine
Passwörter, Cookies oder Formulardaten des Nutzers.

## Ausführbare Abnahme

1. Read-only-Inventur belegt Plugin-Version, Checkout-Seiten, CF7-Formulare,
   Angebots-ID/Kategorie und den gespeicherten letzten Angebotskontext von
   `locke85`.
2. Ein reproduzierbarer Test bildet den gültigen Übergang und den ungültigen
   Direkteinstieg in Schritt 2 getrennt ab.
3. Die Korrektur verändert nur den notwendigen Checkout-State-Vertrag und ist
   idempotent.
4. Authentifizierte Browser-Abnahme unter `locke85`: E-Recruiting in Schritt 1
   auswählen, Schritt 2 erreichen, keine Fehlermeldung; keine Bestellung
   absenden.
5. Anonyme Desktop-/Mobile-QA der betroffenen Routen bleibt ohne neue harte
   Fehler.

## Ergebnis

`docs/results/worker-result-checkout-step1-step2-offer-state-05.md`

## Abnahme am 4. September 2026

- Der authentifizierte Lauf wählte Angebot `48912` in Schritt 1 und erreichte
  Schritt 2 mit sichtbaren Vertragsdatenfeldern und ohne
  `Please fill out the form on the previous page.`.
- Schritt 2 wurde nicht abgesendet; der Bestand veröffentlichter Bestellungen
  blieb vor und nach der Abnahme unverändert bei `3`.
- Der ausgelieferte Patch wurde per SHA-256 zurückgelesen; der serverseitige
  Rückrollpunkt ist vorhanden und die temporäre Inventur wurde entfernt.
- Die anonyme Prüfung fand auf Schritt 1 einen bereits außerhalb des
  Checkout-State-Patches liegenden mobilen Horizontal-Overflow; Schritt 2
  bestand Desktop und Mobil ohne harte Befunde. Der Patch verändert weder
  Markup noch CSS und führt damit keinen neuen Darstellungsfehler ein.
