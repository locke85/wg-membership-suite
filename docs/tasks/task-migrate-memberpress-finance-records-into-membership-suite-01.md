# Task: MemberPress-Finanzdaten in die Membership Suite migrieren

## Status

Abgeschlossen und produktiv auf Blog 9 importiert am 22. August 2026.
REST-Finanzexport, vollstaendiger DB-Eventexport, Tabellenabgleich,
Dokumentinventur, EUR- und Mappingfreigabe sowie verschluesseltes finales
Quellarchiv sind abgeschlossen. Zielmodell, Dry-run, Adminzugriff,
Reprint-Gates und Membership-Analysen v3 einschliesslich
Jahresauswertung sind implementiert und gegen den echten Export geprueft.
Plugin 1.1.0 wurde fuer den Import und Folgeversion 1.1.2 fuer die
Bestellansicht auf dem Multisite-Host bereitgestellt. Der Produktivlauf
legte 9.000 Archivobjekte an; der unmittelbar wiederholte Lauf erkannte alle
9.000 Objekte als unveraendert und erzeugte keine Dubletten. Siehe
`docs/results/worker-result-memberpress-finance-migration-01.md`.

## Ziel

Alle bestell-, rechnungs- und buchhaltungsrelevanten Historien der bisherigen
MemberPress-Installation von `seo-sharing-system.de` revisionssicher in die
Membership Suite ueberfuehren. Nach erfolgreich abgenommenem Cutover kann die
Netzwerkseite technisch stillgelegt werden, ohne dass Rechnungen,
Bestellungen, Abos, Erweiterungen, Zahlungen, Erstattungen oder ihre
Nachweise verloren gehen.

Die fehlende MemberPress-Lizenz ist kein Grund, historische Daten zu verlieren
oder das Plugin wieder produktiv einzusetzen. Die Migration muss mit
read-only Datenbank- und Dateiexporten funktionieren.

Das Ergebnis muss insbesondere erlauben:

1. historische Rechnungen und zugehoerige Buchungen nachvollziehbar
   vorhalten;
2. Abos, Erweiterungen und deren Statushistorie von der Bestellung bis zur
   Kuendigung, Erneuerung, Zahlung oder Erstattung nachvollziehen;
3. vorhandene historische Rechnungspdfs unveraendert abrufen;
4. wenn kein Original-PDF vorliegt, ein inhaltlich identisches Mehrstueck aus
   einem versionierten, archivierten Rechnungsdatensatz erzeugen zu koennen;
5. die bestehende Finom-Integration sinnvoll abzugrenzen, statt die
   MemberPress-PDF- oder Rechnungs-Engine nachzubauen.
6. historische Bestellungen und Membership-Verlaeufe fuer Anmeldung,
   Kundenwert, Aktiv-/Pausenzeiten, Produktpopularitaet und
   Erweiterungs-/Paketpotenziale reproduzierbar auszuwerten.

## Verbindliche fachliche Ergaenzung vom 22. August 2026

- Bis einschliesslich 2025 wurden alle Kundenrechnungen in DATEV Unternehmen
  online hochgeladen und vom Steuerberater dort verarbeitet.
- Seit dem 1. August 2025 werden Kundenrechnungen direkt in Finom erstellt,
  versandt und den Kontobewegungen zugeordnet. Bis Ende 2025 erfolgte weiterhin
  die Verarbeitung ueber DATEV Unternehmen online; erst ab 2026 ist kein
  weiterer Rechnungsupload mehr erforderlich.
- Historische Rechnungen werden nicht nach Finom hochgeladen oder dort
  rueckwirkend neu erzeugt.
- Die Membership Suite behaelt historische Bestell-, Abo-, Produkt- und
  Erweiterungsfakten als unveraenderliche Analyseprojektion. Sie wird dadurch
  nicht zum operativen Rechnungs-, Zahlungs- oder OPOS-System neben Finom.
- Kennzahlendefinitionen und Systemgrenzen stehen verbindlich unter
  `docs/finance/finance-system-boundary-and-historical-analytics.md`.

## Verbindliche Ausgangslage

Vor der Arbeit lesen und pruefen:

- `README.md`
- `includes/CPT/Rechnung_CPT.php`
- `includes/Checkout/Order_Finalizer.php`
- `includes/Checkout/Pricing.php`, soweit vorhanden
- `docs/governance/platform-membership-community-boundary.md`
- die tatsaechliche MemberPress-Quellinstallation inklusive Tabellenpraefix,
  `wp_posts`/`wp_postmeta`, `*_mepr_transactions`,
  `*_mepr_subscriptions`, `*_mepr_transaction_meta`,
  `*_mepr_subscription_meta`, Events und Uploads, soweit sie existieren
- die tatsaechliche Finom-Konfiguration, Dokumentation, Credentials-Bindung
  und vorhandenen Datenfluesse

Die Tabellenamen duerfen nicht geraten werden. Der Worker muss sie zuerst
read-only inventarisieren und mit dem tatsaechlichen WordPress-Tabellenpraefix
ermitteln. Weder MemberPress noch ein Add-on wird allein fuer die Migration
aktiviert oder lizenziert.

## Rechtlicher Rahmen und Grenzen

Dieser Task baut keine Steuer- oder Rechtsberatung. Er implementiert einen
technischen Nachweis- und Aufbewahrungsprozess, der vor Produktivsetzung durch
die steuerlich verantwortliche Stelle beziehungsweise Steuerberatung zu
validieren ist.

Als aktuelle primaere Ausgangspunkte sind mindestens zu pruefen und im
Ergebnisbericht mit Abrufdatum zu verlinken:

- `HGB Paragraf 257` fuer geordnete, verfuegbare und lesbare Aufbewahrung von
  Unterlagen; Buchungsbelege nach aktueller Fassung acht Jahre, bestimmte
  Handelsunterlagen zehn beziehungsweise sechs Jahre
- `UStG Paragraf 14b` fuer die Aufbewahrung von Rechnungen; nach aktueller Fassung
  acht Jahre
- `AO Paragraf 147` und die aktuelle GoBD-Fassung fuer Unveraenderbarkeit,
  Nachvollziehbarkeit, Verfuegbarkeit und Datenzugriff

Die Aufbewahrungslogik darf deshalb keine pauschale globale Loeschfrist
verwenden. Sie muss mindestens Dokumentklasse, relevanten Beginn der Frist,
berechnetes fruehestes Ablaufdatum, Rechtsgrundlage und einen Sperrgrund
(`litigation_hold`, `tax_audit_hold` oder gleichwertig) speichern. Ein
automatischer Loeschlauf gehoert nicht in diesen Sprint.

## Nicht-Ziele

- keine Reimplementierung von MemberPress oder dessen Rechnungsrenderer
- keine nachtraegliche Massenerstellung historischer Rechnungen in Finom
- keine automatische steuerliche Neubewertung alter Geschaeftsvorfaelle
- keine automatische Disposition, Mahnung oder Zahlungsabwicklung
- keine Loeschung der Quellseite, Quelldatenbank, Uploads oder Domain vor den
  expliziten Cutover-Gates
- kein Zugriff auf Zahlungsprovider, wenn kein dokumentierter und notwendiger
  read-only Exportpfad besteht

## Architekturentscheidung

### 1. Quellarchiv und Zielmodell trennen

Die MemberPress-Quelle bleibt als unveraenderlicher, verschluesselter und
gehashter Export erhalten. Das Zielmodell ist eine lesbare, normalisierte
Projektion fuer Betrieb, Kundenservice und Pruefung.

Der Worker implementiert mindestens:

- einen versionierten Migrationsmanifest pro Lauf
- originalgetreue Source-Snapshots je importiertem Finanzobjekt oder als
  referenzierbare, gehashte Exportdatei
- ein kanonisches, append-only Finanzarchivmodell
- stabile Verknuepfungen zwischen Quelle, Bestellung, Abo, Erweiterung,
  Zahlung, Erstattung und Rechnung
- Inhalte- und Dateihashes fuer Original-PDFs und migrierte Belege
- Auditdaten fuer Import, Korrektur, Einsicht und erneute PDF-Erzeugung

Keine vorhandene Buchung darf bei einem Wiederholungslauf dupliziert,
ueberschrieben oder umnummeriert werden.

### 2. Minimaler Zielbestand

Der Worker kann bestehende CPTs erweitern oder eine kleine eigene Tabelle
einsetzen. Er muss die Wahl gegen die tatsaechlichen Datenmengen,
Abfragebeduerfnisse und Unveraenderbarkeitsanforderungen begruenden. Kein ORM,
Event-Sourcing-Framework oder externer Buchhaltungsdienst ohne belegten
Mehrwert.

Das Zielmodell muss mindestens abbilden:

| Objekt | Pflichtinformationen |
| --- | --- |
| Finanzvorgang | stabile Ziel-ID, Quellsystem, Quell-ID, Typ, Status, Zeitstempel, Import-Batch, Hash |
| Bestellung | Bestellnummer, Kunde, Angebots-/Produkt-Snapshot, Erweiterungen, Netto/Steuer/Brutto, Zahlungsart, Statushistorie |
| Abo | Quell-Abo-ID, Mitgliedschaft/Produkt, Start, Ende, Intervall, Statuswechsel, Bezug zu Transaktionen |
| Erweiterung | Produkt-/Leistungssnapshot, Preis-/Steuersnapshot, Laufzeit und Bestellung oder Abo |
| Zahlung/Erstattung | Betrag, Waehrung, Zeitpunkt, Status, Providerreferenz nur soweit vorhanden, Bezug zum Finanzvorgang |
| Rechnung/Korrektur | Rechnungsnummer, Ausstellungsdatum, Empfaenger- und Leistungsvermerk-Snapshot, Betraege, Steuer, Originaldatei oder Reprint-Datenstand |
| Aufbewahrung | Dokumentklasse, Fristbeginn, fruehestes Ablaufdatum, Rechtsgrundlage, Sperrgrund |

Produktnamen, Preise, Steuersaetze, Rechnungsadresse und Leistungsbeschreibung
muessen als historische Snapshots gespeichert werden. Sie duerfen bei einer
spaeteren Angebotsaenderung nicht aus dem heutigen WordPress-Angebot neu
berechnet werden.

### 3. Finom klar abgrenzen

Vor jeder Implementierung wird die vorhandene Finom-Integration inventarisiert:

- welche Rechnungen/Belege dort bereits existieren
- welche IDs, PDFs, Exporte oder APIs tatsaechlich verfuegbar sind
- ob Finom der laufende Rechnungsfuehrer, ein Exportziel oder nur eine
  Teilintegration ist
- wie Finom-Dokumente revisions- und zugriffssicher referenziert werden

Verbindliche Default-Entscheidung, bis der Capability-Check etwas anderes
belegt:

- **Historische MemberPress-Vorgaenge:** in der Membership Suite als
  Finanzarchiv mit Quellprovenienz erhalten; nicht massenhaft rueckwirkend in
  Finom erzeugen.
- **Vorhandene Finom-Rechnungen:** als externe, stabile Referenz mit
  Export-/Dokumentenhash und Finom-ID verknuepfen; nicht duplizieren.
- **Kuenftige Rechnungen:** Finom kann der Erzeuger und das Buchhaltungsziel
  bleiben, wenn der aktuelle Integrationsvertrag dies belegt. Die Membership
  Suite speichert dann nur die erforderliche Referenz, den Status und einen
  archivierten Nachweis.

Wenn Finom keine sichere, stabile Import- oder Dokumentreferenz fuer einen
konkreten historischen Fall bietet, bleibt das Membership-Finanzarchiv die
Quelle fuer diesen Fall. Es ist nicht zulaessig, eine Integration zu
erfinden.

Die fachliche Ergaenzung vom 22. August 2026 konkretisiert diesen Default:
Es erfolgt kein Altbeleg-Upload nach Finom. DATEV Unternehmen online bleibt
der verarbeitete Rechnungsbestand bis 2025; Finom fuehrt den operativen Prozess
ab 2026.

### 4. Historische PDFs und erneute Erzeugung

Reihenfolge der Nachweise:

1. Existiert ein historisches Original-PDF oder eine strukturierte
   E-Rechnung, wird es unveraendert mit Hash und Provenienz archiviert.
2. Fehlt die Originaldatei, bleiben die vollstaendigen historischen
   Rechnungsdaten, die verwendete Rechnungsnummer und ein versionierter
   Dokumenten-Snapshot erhalten.
3. Eine schlanke, eigene Reprint-Funktion darf daraus ein inhaltlich
   identisches Mehrstueck erzeugen. Sie muss sichtbar als `Rechnungskopie` oder
   `Mehrstueck der historischen Rechnung` gekennzeichnet sein, dieselbe
   Rechnungsnummer verwenden und den verwendeten Datenstand protokollieren.

Die Funktion darf keine neue Rechnung, neue Rechnungsnummer, neue Steuer,
neues Ausstellungsdatum oder vermeintliche Zahlung erzeugen. Vor der
Produktivsetzung ist mit der steuerlich verantwortlichen Stelle zu klaeren, ob
ein bestimmter Altfall nur als archiviertes Original oder auch als erzeugbares
Mehrstueck behandelt werden darf.

## Umsetzung in Meilensteinen

### M0: Read-only Inventur und Migrationsentscheidung

1. Quelldatenbank und Uploads mit minimalen, dokumentierten Read-Rechten
   inventarisieren.
2. Tabellen, Spalten, Meta-Key-Familien, Post-Typen, Rechnungspdfs,
   Zahlungs-/Erstattungsfelder und reale Datenmengen ermitteln.
3. MemberPress-Datenmodell gegen die Membership-Suite-Modelle mappen.
4. Finom-Bestand und bestehende Integrationspunkte inventarisieren.
5. Unklare Datenfelder, fehlende PDFs, nicht zuordenbare Kunden und doppelte
   Quell-IDs als offene Befunde ausgeben.

Ergebnis: ein freizugebendes Mapping, keine Writes.

### M1: Export, Sicherung und Vorab-Abgleich

1. Konsistenten Datenbankexport sowie getrennten Export der relevanten
   Uploads/Belege erzeugen.
2. Alle Exportartefakte hashen, mit Zeitpunkt, Tabellenumfang und Zeilenzahl
   dokumentieren und ausserhalb des oeffentlichen Webroots ablegen.
3. Import-Runner als dry-run implementieren. Er erstellt nur Reporte und
   keine Zielobjekte.
4. Quellen- und Zielzaehlungen mindestens nach Transaktionen, Abos,
   Rechnungen, Kunden, Erweiterungen, Zahlungen und Erstattungen vergleichen.

### M2: Finanzarchiv und idempotenter Import

1. Das minimale Zielmodell sowie Rechte- und Auditvertrag implementieren.
2. Idempotenten Import mit `source_system + source_id + object_type` und
   Hash-/Versionpruefung implementieren.
3. Historische Snapshots und vorhandene Originaldateien uebernehmen.
4. Jede nicht aufloesbare Zuordnung in eine sichtbare Review-Queue schreiben;
   niemals an einen aehnlichen Kunden raten.
5. Wiederholungslauf, Abbruch und Rollback gegen eine Staging-Kopie testen.

### M3: Recherche- und Reprintzugriff

1. Einen kleinen, geschuetzten Adminzugriff fuer Suche nach Rechnungsnummer,
   Kunde, Quell-ID und Zeitraum bereitstellen.
2. Originaldatei, strukturierte Daten und Verknuepfungen klar unterscheiden.
3. Reprint nur fuer berechtigte Rollen und nur aus vollstaendigem,
   freigegebenem historischen Datensatz erlauben.
4. Jeden Abruf und jede Reprint-Erzeugung mit Nutzer, Zeit, Objekt-ID,
   Datenstand und Ergebnis protokollieren.

Keine eigene allgemeine PDF-Design-Engine bauen. Ein schlankes,
versionsgebundenes Rechnungsdokument reicht; wenn Finom fuer kuenftige
Rechnungen der verifizierte Erzeuger ist, muss die Suite diesen nicht ersetzen.

### M4: Cutover, Abnahme und Stilllegung der Quelle

Die Quelle darf erst stillgelegt werden, wenn alle Gates bestanden sind:

1. Anzahl und Summen der Finanzobjekte stimmen je Klasse mit der Quelle ueberein
   oder jede Differenz ist einzeln dokumentiert und freigegeben.
2. Stichproben pruefen mindestens alte Rechnung mit PDF, Rechnung ohne PDF mit
   Reprint, Abo mit mehreren Buchungen, Erweiterung, Zahlung und Erstattung.
3. Berechtigungs-, Manipulations-, Idempotenz- und Restore-Tests bestehen.
4. Ein verschluesseltes Quellarchiv inklusive Wiederherstellungsanleitung liegt
   ausserhalb des alten Webroots vor.
5. Die steuerlich verantwortliche Stelle bestaetigt Mapping, Aufbewahrungs-
   und Reprintverfahren oder dokumentiert verbleibende Auflagen.
6. Ein Support- und Kundenservicepfad fuer historische Rechnungsanfragen ist
   dokumentiert.

Erst danach wird ein separater, explizit bestaetigter Betriebsschritt
vorbereitet, der `seo-sharing-system.de` stilllegt. "Stilllegen" bedeutet
nicht automatisch Domainloeschung: DNS, Weiterleitungen, Imprint-/Kontakt-
Erreichbarkeit und die Rechtsgrundlage fuer verbleibende Daten muessen in
diesem Schritt bewusst entschieden werden.

## Sicherheit und Datenschutz

- Finanzdaten, Rechnungsadressen und Zahlungsreferenzen nur fuer eindeutig
  berechtigte Rollen sichtbar machen.
- Keine Zahlungsprovider-Geheimnisse, vollstaendigen Zahlungsinstrumentdaten
  oder Klartext-Credentials importieren oder protokollieren.
- Quell- und Zielarchive ausserhalb oeffentlicher Uploadpfade speichern.
- Backups verschluesseln, Zugriff dokumentieren und Wiederherstellung testen.
- Historische Daten nicht durch aktuelle Benutzer-, Angebots- oder
  Rechnungsdaten ueberschreiben.
- Datenminimierung und steuerliche Aufbewahrung gegeneinander dokumentieren;
  keine automatische Loeschung in diesem Sprint.

## Akzeptanzkriterien

- Die MemberPress-Quelle ist ohne gueltige MemberPress-Lizenz read-only
  inventarisiert und exportiert.
- Ein freigegebenes Source-to-Target-Mapping deckt alle real vorhandenen
  Finanzobjektklassen ab.
- Der Import ist idempotent, protokolliert und gegen eine Staging-Kopie
  getestet.
- Rechnungen, Bestellungen, Abos, Erweiterungen, Zahlungen und Erstattungen
  bleiben in ihren historischen Beziehungen nachvollziehbar.
- Original-PDFs bleiben unveraendert und gehasht erhalten; Reprints werden nur
  aus vollstaendigen, archivierten Daten erzeugt und klar als Kopie markiert.
- Finom ist auf Basis realer Integration und Dokumentfaehigkeiten abgegrenzt;
  keine erfundene oder rueckwirkend massenhaft erzeugte Finom-Finanzhistorie.
- Historische Bestellungen sind nach Registrierung/Erstkauf, Kunde,
  Produkt/Erweiterung, Zeitraum und Quellstatus auswertbar; Kundenumsatz und
  Pausen folgen den versionierten Definitionen und weisen Datenstand sowie
  Waehrung aus.
- Aufbewahrungsmetadaten, rechtliche Quellen, Sperrgrunde und Lesbarkeit sind
  dokumentiert.
- Die Quellseite wird nicht geloescht oder stillgelegt, bevor alle M4-Gates
  bestaetigt sind.

## Ergebnisdokumentation

Lege mindestens an:

- `docs/results/worker-result-memberpress-finance-migration-01.md`
- `docs/runbooks/memberpress-finance-archive-and-cutover.md`
- ein versioniertes Mapping unter `docs/finance/`
- ein Migrationsmanifest und anonymisierte Validierungsreports unter
  `artifacts/memberpress-finance-migration/`

Der Ergebnisbericht bleibt kompakt und nennt:

- Quellumfang und Zielumfang je Objektklasse
- nicht aufloesbare oder freigabepflichtige Differenzen
- Finom-Entscheidung mit Evidenz
- Nachweis der Reprint- und Restore-Tests
- Cutover-Status und verbleibende menschliche Freigaben
