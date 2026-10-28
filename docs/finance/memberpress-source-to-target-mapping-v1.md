# MemberPress Source-to-Target-Mapping v1

Stand: 22. August 2026  
Status: freigegeben und produktiv angewendet am 22. August 2026

Systemgrenze: Rechnungen bis einschliesslich 2025 wurden in DATEV Unternehmen
online verarbeitet. Erstellung, Versand und Zahlungszuordnung wechselten
bereits zum 1. August 2025 zu Finom; ab 2026 entfiel der zusaetzliche
DATEV-Upload. Historische Rechnungen werden nicht nach Finom hochgeladen.
Details und Analysebegriffe stehen in
`finance-system-boundary-and-historical-analytics.md`.

## Reale Quelle

Die Quelle `https://seo-sharing-system.de` wurde authentifiziert und ausschliesslich
mit `GET` ueber WordPress REST und MemberPress Developer Tools inventarisiert.
Aktiv sind MemberPress, MemberPress Developer Tools und MemberPress PDF Invoice.
Die API stellt `memberships`, `subscriptions`, `transactions` und `members` bereit.

Der REST-Zugang stellt das WordPress-Tabellenpraefix, Custom-Table-Spalten,
`postmeta`, MemberPress-Events und den Dateibestand des PDF-Add-ons nicht bereit.
Die zusaetzliche read-only Datenbankinventur hat Quelle Blog 3 mit Praefix
`wp_irpchfpaou_3_`, 657 Transaktionen, 79 Abos, 6.857 Events und 615
PDF-Invoice-Nummernzeilen bestaetigt. Ziel ist Blog 9 mit Praefix
`wp_irpchfpaou_9_`. Ein konsistenter DB- und Dateisystemexport muss das Mapping
vor Freigabe weiterhin ergaenzen.

In Blog 3 wurden keine PDF-Media-Attachments und keine `.pdf`-Pfade in
`postmeta` oder Optionswerten gefunden. Das PDF-Add-on verwaltet somit in der
Datenbank Rechnungsnummern, aber keine nachgewiesenen Originaldateien. Eine
abschliessende Aussage bleibt bis zur read-only Dateisysteminventur gesperrt.

## Mapping

| Quelle | Zielobjekt | Schluessel | Beziehungen und Hinweise |
| --- | --- | --- | --- |
| `mp/v1/memberships` | `membership_product` | MemberPress `id` | Historischer Produkt-, Preis-, Steuer-, Intervall- und Textsnapshot. |
| `mp/v1/members` | `customer` | MemberPress `id` | Registrierung, historische Empfaengeridentitaet und exaktes E-Mail-Mapping zum Zielkonto. |
| `mp/v1/memberships`, Titel beginnt exakt mit `Erweiterung` | `extension` | MemberPress `id` | Deterministische vorlaeufige Regel; 21 von 39 Produkten. Vor Apply fachlich bestaetigen. |
| `mp/v1/subscriptions` | `subscription` | MemberPress `id` | Status, Zeitraum, Gateway, Betrags- und Steuersnapshot; Relation zum Produkt. |
| `mp/v1/transactions` | `order` | MemberPress `id` | Bestellung als historischer Transaktionssnapshot; Relation zu Produkt und Abo. |
| `mp/v1/transactions`, Status ungleich `refunded` | `payment` | MemberPress `id` | Zahlungsstatus und vorhandene Providerreferenz; Relation zur Bestellung. |
| `mp/v1/transactions`, Status `refunded` | `refund` | MemberPress `id` | Erstattung ohne erfundene Parent-Beziehung; Quelle liefert hier `parent_transaction_id=0`. |
| `mp/v1/transactions.invoice_number` nicht leer | `invoice` | MemberPress `id` | Gleiche Rechnungsnummer, Empfaenger-, Produkt-, Betrag- und Steuersnapshot; Relation zur Bestellung. |
| exakter E-Mail-Treffer | `customer_user_id` | normalisierte E-Mail | Nur exakte Zuordnung. Kein Fuzzy Matching und keine Uebernahme der Quell-User-ID. |
| fehlender/ein uneindeutiger Kunde | Review Queue | gehashte Quellreferenz | `customer_unresolved`; niemals automatisch aehnlichen Kunden zuordnen. |
| Originaldatei-Manifest | Invoice-Nachweis | Transaktions-ID + SHA-256 | Nur relative Archivpfade unter `WG_FINANCE_ARCHIVE_ROOT`; Hash wird vor jedem Download neu geprueft. |
| normalisierter DB-Eventexport | `membership_event` | reale Event-ID | `event_type`, Zeitpunkt, Mitglied, Abo und Produkt; nur diese Ereignisse duerfen als belegte Pause/Statusaenderung gelten. |

Die Objekte `order`, `subscription`, `payment`, `refund`, `membership_product`
und `extension` sind historische Analysefakten. Sie duerfen fuer Anmeldung,
Kaufverlauf, Kundenumsatz, Laufzeiten, Pausen sowie Produkt-/Paketanalysen
abgefragt werden, aber keine operativen Finom- oder DATEV-Zustaende ersetzen.

Jede Projektion wird ueber `source_system + object_type + source_id` logisch
identifiziert. Gleicher Quellhash wird uebersprungen. Ein geaenderter Quellstand
erzeugt eine neue Version mit derselben `logical_id`; bestehende Versionen werden
nicht ueberschrieben.

## Reale API-Feldfamilien

- Transaktionen: `id`, `amount`, `total`, `tax_*`, `trans_num`, `status`,
  `txn_type`, `gateway`, `created_at`, `expires_at`, `member`, `membership`,
  `subscription`, `parent_transaction_id`, `order_id`, `response`,
  `invoice_number`.
- Abos: `id`, `subscr_id`, `gateway`, `price`, `period`, `period_type`,
  `status`, `created_at`, `total`, `tax_*`, `member`, `membership`, `response`.
- Kunden: ID und Kontaktdaten, historisches Adressobjekt, Profilfelder sowie
  letzte Transaktionen und Abos. Der Ziel-Snapshot uebernimmt nur die fuer
  Empfaenger und Zuordnung erforderlichen Felder. Klartext-Vollstaende bleiben
  nur im verschluesselten Quellarchiv.
- Produkte: Titel/Inhalte, Preis, Zeitraum, Trial, Ablauf, Steuerklasse,
  Zahlungs- und Templateeinstellungen.

Die REST-API liefert keine Waehrung. Die produktive DB-Konfiguration wurde
deshalb gezielt gelesen und enthaelt in `mepr_options` den Wert
`currency_code = EUR`. Der bestehende REST-Dry-run speichert weiterhin `XXX`,
erzeugt `currency_missing` und sperrt Reprints, bis diese primaere Evidenz in
einem neuen Manifest mit `default_currency=EUR` und `currency_approved=true`
fachlich freigegeben ist.

Gateway-`token`, Kartenrest-/Ablaufdaten, rohe `response`-Payloads und
fachfremde MemberPress-Profilfelder werden nie in das Zielmodell uebernommen.
Der Ziel-Snapshot enthaelt stattdessen den Hash des originalen Quelldatensatzes;
dieser bleibt im verschluesselten Export referenzierbar.

Die reale REST-Inventur liefert keine Eventroute. `events.json` bleibt deshalb
im REST-Teilexport leer und `events_export_complete=false`. Die DB-Inventur
belegt 6.857 Zeilen in `wp_irpchfpaou_3_mepr_events` mit den Spalten `id`,
`event`, `args`, `evt_id`, `evt_id_type` und `created_at`. Unter 23 Eventtypen
gibt es keinen expliziten Pause-/Resume-Typ; insbesondere werden die 19
`subscription-expired`-Events nicht als Pause interpretiert. Bis zum
konsistenten Eventexport werden Aktivitaetsluecken ausschliesslich aus belegten
Transaktions-`created_at`-/`expires_at`-Intervallen abgeleitet und als
`observed_inactivity_gap`, nicht als Pause, bezeichnet.

## Aufbewahrung

| Dokumentklasse | Technischer Default | Fristbeginn | Rechtsgrundlage |
| --- | --- | --- | --- |
| Rechnung/Buchungsbeleg | 8 Jahre | Schluss des Entstehungs-/Ausstellungsjahres | HGB § 257 Abs. 4, AO § 147 Abs. 3, UStG § 14b |
| Vertrags-/Geschaeftsunterlage | 6 Jahre | Schluss des Entstehungsjahres | HGB § 257, AO § 147; Klassifikation pruefen |
| Produkt-Snapshot | keine automatische Aussonderung | offen | Zuordnung durch Steuerberatung |

Gespeichert werden Dokumentklasse, `retention_starts_on`,
`earliest_disposal_on`, Rechtsgrundlage und optional `hold_reason`. Erlaubte
Sperrgruende sind organisatorisch mindestens `litigation_hold` und
`tax_audit_hold`. Es existiert kein automatischer Loeschlauf.

Primaerquellen, abgerufen am 22. August 2026:

- [HGB § 257](https://www.gesetze-im-internet.de/hgb/__257.html)
- [UStG § 14b](https://www.gesetze-im-internet.de/ustg_1980/__14b.html)
- [AO § 147](https://www.gesetze-im-internet.de/ao_1977/__147.html)
- [BMF: GoBD-Aenderung vom 11. Maerz 2024](https://www.bundesfinanzministerium.de/Content/DE/Downloads/BMF_Schreiben/Weitere_Steuerthemen/Abgabenordnung/AO-Anwendungserlass/2024-03-11-aenderung-gobd.html)

Dies ist ein technischer Aufbewahrungsdefault, keine Steuer- oder Rechtsberatung.
