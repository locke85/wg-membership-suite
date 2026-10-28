# Task: Finom-Monatsabschluss automatisiert in Finance Analytics uebernehmen

## Status

Geplant. Erstellt am 22. August 2026 nach der manuellen Aufnahme der
Finom-History-Summen fuer August bis Dezember 2025 und Januar bis August 2026.

## Ziel

Die Finance Analytics sollen nach jedem abgeschlossenen Monat reproduzierbare
aktuelle Finom-Kennzahlen erhalten, ohne in der Membership Suite ein
Rechnungs-, Zahlungs-, OPOS- oder Steuernebenbuch aufzubauen.

Mindestens auszuweisen sind:

- Abdeckungsmonat und Abschlussstatus;
- Finom-Geldeingang;
- Ausgangsrechnungen brutto, netto und ausgewiesene Umsatzsteuer, sofern die
  jeweilige Quelle diese Werte belastbar liefert;
- Zahl und Status der Ausgangsrechnungen;
- Datenstand, Quellsystem, externe Referenz und SHA-256 des Eingangsartefakts;
- Korrekturen als neue Version statt Ueberschreiben abgeschlossener Monate.

## Ausgangslage

- Operativer Finom-Cutover war am 1. August 2025.
- Bis Ende 2025 wurden Kundenrechnungen zusaetzlich in DATEV Unternehmen
  online verarbeitet; seit 2026 entfaellt dieser Upload.
- Die Analytics v5 enthalten vorerst zwei manuell bestaetigte,
  personenbezugsfreie Finom-Cashflow-Snapshots. Sie sind ausdruecklich keine
  Netto-/USt-, Kunden-LTV- oder Produktdaten.
- Fuer das Repository `monatsabschluss` existierte bei Task-Erstellung kein
  freigegebener Treffer in der Cross-Repo-Registry fuer die Problemklasse
  `finance-monthly-close`. Vor einer Implementierung muessen Repo-Root,
  Artefaktpfad, Schema, Freigabestatus und erlaubte Leserechte deshalb
  explizit verifiziert werden; Pfade und Feldnamen duerfen nicht geraten
  werden.

## Variante A: Finom ueber Zapier

Zu pruefende Finom-Trigger sind `Neue Rechnung erstellt`,
`Rechnungsstatus geaendert` und `Zahlung eingegangen`. Zapier darf nur an einen
dedizierten Membership-Suite-Endpunkt liefern.

Diese Trigger sowie der JSON-Download ausgehender Rechnungen sind in der
offiziellen Finom-Zapier-Dokumentation vom 12. Juni 2026 ausgewiesen
(abgerufen am 22. August 2026):
`https://help.finom.co/de/articles/6062246-wie-verbinde-ich-mein-finom-konto-mit-zapier`.

Technischer Mindestvertrag:

- HMAC-signierter Request mit Zeitstempel und Replay-Schutz;
- strikt allowlistetes Schema ohne Bankkonto-, Karten- oder unnoetige
  Kontaktdaten;
- idempotenter Schluessel aus Finom-Rechnungs-/Transaktions-ID und
  Ereignisversion;
- append-only Rohereignis mit Hash sowie davon getrennte Monatsprojektion;
- Ereignisse duerfen verspätet und in anderer Reihenfolge eintreffen;
- Storno, Gutschrift, Teilzahlung und Statuskorrektur muessen als neue
  Ereignisse verarbeitet werden;
- taeglicher oder monatlicher Vollstaendigkeitsabgleich gegen einen
  Finom-Gesamtexport; Zapier-Ereignisse allein sind kein Abschlussnachweis;
- keine Rueckschreibung nach Finom.

Vorteil: aktuelle Rechnungs-, Kunden- und gegebenenfalls Produktreferenzen
koennen LTV-, Kundenbewegungs- und Produktanalysen speisen. Nachteil: laufende
Integrationskosten, Eventreihenfolge, Backfill und Vollstaendigkeitskontrolle.

## Variante B: freigegebene USt-VA-/Monatsabschluss-Artefakte

Im Repository `monatsabschluss` ist zuerst read-only zu inventarisieren:

- welche freigegebenen Monatsartefakte existieren;
- ob sie Ausgangsumsatz netto, Umsatzsteuer, Brutto und Berichtsperiode
  getrennt enthalten;
- ob ein Maschinenformat und stabiler Abschlussstatus vorhanden sind;
- wie Korrekturmeldungen und berichtigte Monate versioniert werden;
- ob Artefakte bereits Hash, Provenienz und steuerliche Freigabe tragen.

Nur freigegebene Abschlussartefakte duerfen als Monatsaggregate importiert
werden. Arbeitsdateien, Entwuerfe oder aus PDFs per OCR geratene Werte sind
ausgeschlossen. Der Import speichert nur Kennzahlen, externe Referenz,
Quellhash, Berichtsmonat und Freigabestatus; das Ursprungsartefakt bleibt im
Monatsabschluss-Repo.

Vorteil: belastbare Monatswerte fuer Netto und Umsatzsteuer mit geringer
Systemkopplung. Grenze: aggregierte USt-VA-Daten koennen keine Kunden-LTV-,
Produkt-, Rechnungsstatus- oder Aboanalyse liefern.

## Empfohlene Zielarchitektur

Pragmatisch ist ein abgestufter Hybrid:

1. Freigegebener Monatsabschluss beziehungsweise USt-VA liefert nach
   Verfuegbarkeit die autoritativen Monatsaggregate fuer Netto und
   ausgewiesene Umsatzsteuer.
2. Finom-Zapier liefert optionale operative Rechnungs-/Kundenereignisse fuer
   Aktualitaet und nicht-buchhalterische Membership-Analysen.
3. Ein monatlicher Finom-Gesamtexport gleicht Ereigniszahl und Bruttosummen ab.
4. Bei Abweichung bleibt der Monat `provisional`; erst nach erfolgreichem
   Abgleich beziehungsweise freigegebenem Monatsabschluss wird er `closed`.
5. Finance Analytics zeigt Quelle und Status je Kennzahl. Werte verschiedener
   Quellen werden nie stillschweigend addiert oder ueberschrieben.

Falls lediglich Jahres-/Monatssummen benoetigt werden, ist Variante B ohne
Zapier die bevorzugte Minimalumsetzung. Zapier ist nur erforderlich, wenn
Kunden-, Produkt-, Abo- oder nahezu aktuelle Rechnungsanalysen den zusaetzlichen
Betriebsaufwand rechtfertigen.

## Umsetzungsschritte

1. Cross-Repo-Vertrag fuer `monatsabschluss` freigeben und reales Artefaktschema
   gegen mindestens zwei abgeschlossene Monate und eine Korrektur pruefen.
2. Finom-/Zapier-Feldinventur mit anonymisiertem Testereignis und Backfill-
   Moeglichkeit dokumentieren.
3. Kennzahlenmatrix erstellen: Kennzahl, autoritative Quelle, Aktualitaet,
   Abschlussstatus und erlaubte Verwendung.
4. Append-only Objekttypen `finom_invoice_event` und/oder
   `monthly_tax_summary` samt Schema-Version und Idempotenzregeln implementieren.
5. Dry-run, Konfliktbericht, Audit und Admin-only Passwortschutz fuer Import
   und Anzeige implementieren.
6. Parallelmonat gegen Finom, Monatsabschluss und Finance Analytics
   abstimmen; Differenzen muessen null oder fachlich erklaert sein.
7. Erst danach den manuellen Bildschirm-Snapshot fuer Folgemonate abloesen.

## Abnahmekriterien

- Kein Betrag wird ohne Quellsystem, Zeitraum, Hash und Status angezeigt.
- Wiederholte Events/Imports erzeugen keine Dubletten.
- Korrekturen ueberschreiben keinen abgeschlossenen Datensatz.
- Geldeingang, Rechnungsumsatz, Netto, Umsatzsteuer und offene Betraege bleiben
  getrennte Kennzahlen.
- Kunden-LTV und Produktanalyse verwenden nur datensatzgenaue Finom-Ereignisse,
  niemals USt-VA-Aggregate.
- Direkter Report und JSON-Export bleiben admin- und passwortgeschuetzt.
- Die Membership Suite sendet keine Rechnung, mahnt nicht und schreibt nicht
  nach Finom oder DATEV zurueck.
- Ein dokumentierter Restore-/Rebuild-Test reproduziert jeden Monatswert aus
  den versionierten Quellen.

## Nicht im Scope

- Steuerliche Bewertung oder Abgabe einer USt-VA;
- Zahlungsabgleich oder OPOS-Fuehrung in WordPress;
- Speicherung von Bankkonto-, Karten- oder vollstaendigen Finom-Rohdaten;
- automatisches Korrigieren oder Markieren von Finom-Rechnungen.
