# Ergebnis: Angebotsauswahl an Checkout-Schritt 1 übergeben

Status: `abgeschlossen`  
Zielsite: `tanzlehrer-recruiting.de`, Multisite-Blog `8`  
Abnahme: `2026-09-04`

## Umsetzung

Die gemeinsame Angebotsdetail-Vorlage bleibt unverändert. Beim Rendern einer
veröffentlichten Einzelansicht des CPT `wg_angebot` ergänzt die Membership
Suite jeden Link zu Checkout-Schritt 1 um die ID des aktuell geöffneten Abos:

- eRecruiting: `/schreibtisch/angebote/1-produkt-auswaehlen/?angebot=48912`
- Teamautomat: `/schreibtisch/angebote/1-produkt-auswaehlen/?angebot=48911`

Checkout-Schritt 1 übernimmt den Parameter nur für das vorhandene Feld
`wg_angebote_abo_id`, wenn die ID zu einem veröffentlichten Angebot der
konfigurierten Abo-Kategorie gehört. Erweiterungen, unbekannte IDs, Arrays und
Fremdlinks werden abgewiesen. Bereits gesetzte URL-Parameter werden idempotent
ersetzt statt dupliziert.

## Rückrollpunkt und Read-back

- Vorher-Hash: `e434cf1fb5bf7a62c9522cd58b61f664314356b722242cf8f044be86e7a7fa38`
- Live-/Read-back-Hash: `24ad789e0fa6c2c2333ca7ee0b931a3ec3f128679b254682ddc3ceb7a376c677`
- Serverseitiger Rückrollpunkt:
  `Step_1.php.before-offer-prefill-20260904`
- Temporärer Inventur-Runner und temporärer Remote-Bericht wurden entfernt.

## Abnahme

- PHP-Lint: Implementierung und Test bestanden.
- `tests/checkout-offer-prefill-harness.php`: bestanden; geprüft wurden
  relative und absolute Checkout-Links, der site-lokale verschachtelte Pfad,
  Fremdhost, anderer Checkout-Schritt, Idempotenz, vorhandene Query/Fragment,
  gültiges Abo, Erweiterung, unbekannte ID und Array-Eingabe.
- Bestehender Session-Regressionscheck
  `tests/checkout-mapped-domain-state-harness.php`: bestanden.
- Kanonischer Live-Read-back: beide Angebotsseiten enthalten ihre jeweils
  richtige ID; Schritt 1 markiert 48912 beziehungsweise 48911 als ausgewählt,
  bei 50001 keine Auswahl.
- Authentifizierter Browserlauf: Link auf eRecruiting angeklickt, Ziel-URL mit
  `angebot=48912`, sichtbares Feld `Dein Abo wählen` auf eRecruiting; Formular
  nicht abgesendet.
- Bestellbestand vor/nach der Abnahme: unverändert `3`.
- Anonyme QA: Status 200, keine defekten Bilder, Seiten- oder Konsolenfehler.
  Der bereits bestehende mobile Horizontal-Overflow betrifft alle drei
  geprüften Vorlagen und bleibt als getrennte Layout-Aufgabe sichtbar.

## Evidenz

- Vorher-/Nachher-Inventur:
  `sites/tanzlehrer-recruiting/artifacts/checkout-offer-prefill/2026-09-04/`
- Browserbericht:
  `sites/tanzlehrer-recruiting/artifacts/browser-qa/2026-09-04-checkout-offer-prefill/browser-qa-summary.md`
