# Ergebnis: Checkout-Übergang Schritt 1 zu Schritt 2

Status: `abgeschlossen`  
Zielsite: `tanzlehrer-recruiting.de`, Multisite-Blog `8`  
Abnahme: `2026-09-04`

## Ursache

Contact Form 7 Multi-Step Module 4.7 speicherte den Formularzustand im
Cookie-Pfad mit WordPress `COOKIE_DOMAIN=.webgefaehrte.de`. Diese Domain deckt
die gemappte Domain `tanzlehrer-recruiting.de` nicht ab. Deshalb erreichte der
Zustand aus Schritt 1 den zweiten Schritt nicht und das Modul erzeugte korrekt
seine Meldung `Please fill out the form on the previous page.`. Der bereits von
der Membership Suite gespeicherte letzte Angebotskontext war vorhanden, kann
den eigenen Zustandsvertrag des Mehrschritt-Moduls aber nicht ersetzen.

## Korrektur

`includes/Checkout/Step_1.php` aktiviert über den vorhandenen Filter
`cf7msm_force_session` ausschließlich bei einer nicht passenden Cookie-Domain
den Session-Speicher des Mehrschritt-Moduls. Der Session-Cookie wird dabei
hostgebunden, sicher, HttpOnly und mit SameSite Lax konfiguriert. Für die
Netzwerk-Hauptdomain und passende Subdomains bleibt das bisherige Verhalten
unverändert. Die Formulare, Seiten und Nutzerdaten wurden nicht umgeschrieben.

## Live-Nachweis und Rückrollpunkt

- Vorher-Hash: `b30d12e722b8f3411ee05bd322bd14b9a668b4c7a2bdbcb4e166f4d7227ca2c3`
- Live-/Read-back-Hash: `e434cf1fb5bf7a62c9522cd58b61f664314356b722242cf8f044be86e7a7fa38`
- Rückrollpunkt: `Step_1.php.before-checkout-mapped-domain-state-20260904`
- `locke85`: letzter Angebotskontext `48912`, Erweiterung `0`, kein
  Checkout-Token
- Veröffentlichte Bestellungen vor und nach dem Test: `3`
- Temporärer Inventur-Runner und temporärer Remote-Bericht wurden entfernt.

## Abnahme

- PHP-Lint für Implementierung und Test: bestanden.
- `tests/checkout-mapped-domain-state-harness.php`: bestanden; umfasst
  Hauptdomain, Netzwerk-Subdomain, gemappte Domain, vorhandenes Force-Signal,
  ungültigen Host und Cookie-Parameter.
- Authentifizierter Browserlauf: E-Recruiting-Angebot `48912` in Schritt 1
  gewählt; Schritt 2 öffnete mit sichtbaren Vertragsdatenfeldern; Fehlertext
  nicht vorhanden; Schritt 2 nicht abgesendet.
- Anonyme QA: Schritt 2 bestand auf Desktop und Mobil ohne Overflow, defekte
  Bilder, Seiten- oder Konsolenfehler. Schritt 1 weist mobil einen unabhängigen
  Horizontal-Overflow auf; der Checkout-State-Patch verändert weder Markup
  noch Styles und ist dafür nicht ursächlich.

## Evidenz

- Site-Inventur nach Browserabnahme:
  `sites/tanzlehrer-recruiting/artifacts/checkout-step-state/2026-09-04T10-00-00+0200/inventory-after-browser.php`
- Bytegenauer Live-Read-back:
  `sites/tanzlehrer-recruiting/artifacts/checkout-step-state/2026-09-04T10-00-00+0200/remote-after/Step_1-browser-readback.php`
- Browserbericht:
  `sites/tanzlehrer-recruiting/artifacts/browser-qa/2026-09-04-checkout-step-state/browser-qa-summary.md`
