# Task: Gewähltes Angebot an Checkout-Schritt 1 übergeben

Status: `abgeschlossen`

Zielsite: `tanzlehrer-recruiting.de`  
Betroffener Ablauf: `Schreibtisch > Angebote > Angebotsdetail > Angebot bestellen`

## Ziel

Der Bestelllink eines veröffentlichten Membership-Angebots führt mit der
eindeutigen Angebots-ID als URL-Parameter `angebot` zu Checkout-Schritt 1. Das
Feld `Dein Abo wählen` übernimmt ausschließlich ein gültiges veröffentlichtes
Abo aus diesem Parameter und zeigt es bereits ausgewählt an.

## Scope

- Dynamischer Bestelllink auf Einzelansichten des CPT `wg_angebot`.
- Validierte Vorbelegung des vorhandenen CF7-Felds
  `wg_angebote_abo_id` in Checkout-Schritt 1.
- Umsetzung in der Membership Suite und begrenztes Live-Deployment der
  betroffenen Datei auf Multisite-Blog 8.

## Grenzen

- Keine Bestellung, Zahlung oder Absendung von Checkout-Schritt 1 oder 2.
- Kein Vertrauen in beliebige Post-IDs aus der URL: Der Wert muss ein
  veröffentlichtes Angebot der Abo-Kategorie sein.
- Keine Umschreibung von Angebotsbeiträgen, GenerateBlocks-Inhalten oder
  Checkout-Formularen.
- Änderungen anderer Arbeitsstränge im Plugin-Repository bleiben unberührt.

## Rückrollpunkt

Vor dem Live-Write werden die ausgelieferte Plugin-Datei, ihr SHA-256-Hash und
der veröffentlichte Bestellbestand gelesen und gesichert. Der Upload ist nur
bei passendem Vorher-Hash zulässig; ein serverseitiges Backup der Datei bleibt
als unmittelbarer Rückrollpunkt erhalten.

## Ausführbare Abnahme

1. Reine Tests bestätigen URL-Erkennung, idempotente Parameterergänzung,
   Fremdlinks und bereits vorhandene Querywerte.
2. Ungültige, unveröffentlichte oder nicht zur Abo-Kategorie gehörende IDs
   werden nicht vorausgewählt.
3. Für mindestens zwei veröffentlichte Angebote enthält der live gerenderte
   Bestelllink die jeweils richtige ID.
4. Authentifizierter Browserlauf: Klick auf `Angebot bestellen` öffnet Schritt
   1; `Dein Abo wählen` zeigt exakt das zuvor betrachtete Angebot ausgewählt.
5. Der Lauf wird vor dem Absenden von Schritt 1 beendet; die Anzahl
   veröffentlichter Bestellungen bleibt unverändert.
6. Anonyme Desktop-/Mobile-QA der betroffenen Seiten dokumentiert Rendering,
   Links, Bilder, Konsolenfehler und Overflow.

## Ergebnis

`docs/results/worker-result-checkout-offer-link-prefill-06.md`

## Abnahme am 4. September 2026

- Die Live-Ausgabe setzt für eRecruiting `angebot=48912` und für Teamautomat
  `angebot=48911`; die kanonischen URLs liefern denselben Stand ohne
  Cache-Buster.
- Der authentifizierte Klick auf `Angebot bestellen` öffnete Checkout-Schritt
  1 mit sichtbarer Auswahl `eRecruiting für Tanzschul-Unternehmer`.
- Schritt 1 wurde nicht abgesendet. Der veröffentlichte Bestellbestand blieb
  vor und nach der Abnahme unverändert bei `3`.
- Die anonyme Desktop-/Mobile-QA fand den bereits zuvor dokumentierten mobilen
  Horizontal-Overflow auch in den Angebotsdetails. Die Änderung selbst fügt
  nur URL-Parameter und das HTML-Attribut `selected` hinzu und verändert keine
  Layout- oder Style-Regel.
