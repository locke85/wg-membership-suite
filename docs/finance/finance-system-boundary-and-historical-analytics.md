# Finanzsystemgrenze und historische Membership-Analysen

Stand: 22. August 2026  
Status: verbindliche fachliche Festlegung des Betreibers

## Systemgrenze

### Kundenrechnungen bis einschliesslich 2025

Alle Kundenrechnungen bis einschliesslich 31. Dezember 2025 wurden in DATEV
Unternehmen online hochgeladen und dort durch den Steuerberater verarbeitet.
DATEV Unternehmen online und die beim Steuerberater vorhandene Verarbeitung
sind fuer diesen Zeitraum der fachliche Buchhaltungsnachweis.

Die MemberPress-Migration ersetzt oder korrigiert diesen Bestand nicht. Sie
haelt die Quellbeziehungen und Originalnachweise fuer Kundenservice,
Nachvollziehbarkeit und historische Auswertungen vor.

### Operativer Finom-Wechsel ab August 2025

Seit dem 1. August 2025 werden Kundenrechnungen direkt in Finom erstellt, aus
Finom versandt und dort den Kontobewegungen zugeordnet. Bis einschliesslich
31. Dezember 2025 wurden diese Kundenrechnungen weiterhin in DATEV Unternehmen
online hochgeladen und vom Steuerberater verarbeitet. Erst seit dem
1. Januar 2026 ist dieser zusaetzliche Rechnungsupload nicht mehr vorgesehen.

Finom ist ab dem 1. August 2025 das operative System fuer Rechnungserstellung,
Versand und Zahlungszuordnung. Die Membership Suite darf diese Funktionen und
Statusfuehrung nicht nachbilden.

### Keine Altbelegmigration nach Finom

Historische MemberPress-/DATEV-Rechnungen bis 2025 werden nicht nach Finom
hochgeladen. Es werden dort weder rueckdatierte Rechnungen erzeugt noch
historische Zahlungsstatus rekonstruiert. Originaldateien und strukturierte
Quellnachweise verbleiben im geschuetzten Migrationsarchiv; berechtigte
Kundenservice-Zugriffe koennen ueber die Membership Suite vermittelt werden.

## Was im Zielsystem ausgewertet werden soll

Die Membership Suite uebernimmt historische kommerzielle Fakten, keine zweite
Buchhaltung. Bestellungen, Mitgliedschaften, Abos, Erweiterungen und belegte
Statusereignisse bleiben als unveraenderliche Analyseprojektion auswertbar.

Zulaessige Fragestellungen sind insbesondere:

- Wer hat sich wann registriert?
- Wann erfolgte die erste und letzte Bestellung eines Mitglieds?
- Welchen historischen Customer Lifetime Value hat ein Mitglied?
- Welche belegten Aktiv-, Kuendigungs- und Pausenintervalle gab es?
- Welche Produkte und Erweiterungen wurden am haeufigsten gebucht?
- Welche Erweiterungen wurden regelmaessig zusammen mit einem Basisprodukt
  gebucht und sind Kandidaten fuer kuenftige Pakete?
- Wie entwickeln sich Kohorten nach Registrierungs-, Erstkauf- oder
  Produktzeitraum?

Diese Analyseprojektion darf keine Rechnung versenden, keine offene Forderung
berechnen, keinen Zahlungsstatus an Finom zurueckschreiben und keine
buchhalterische Abstimmung ersetzen.

## Verbindliche Kennzahlendefinitionen

### Registrierung und Kauf

- `account_registered_at`: `registered_at` des MemberPress-Mitglieds.
- `first_order_at`: fruehester belegter MemberPress-Transaktionszeitpunkt.
- `first_completed_order_at`: frueheste Transaktion mit Quellstatus
  `complete`.
- Registrierung und erster Kauf sind getrennte Ereignisse und duerfen nicht
  gleichgesetzt werden.

### Customer Lifetime Value

Der technische Name lautet `historical_customer_revenue`, solange weder
Deckungsbeitraege noch vollstaendige Kosten enthalten sind. Er wird je
Waehrung berechnet als:

```text
Summe Brutto abgeschlossener Bestellungen
- Summe eindeutig belegter Erstattungen
```

`pending`, fehlgeschlagene oder ungeklaerte Transaktionen werden nicht als
Umsatz gezaehlt. Eine Erstattung ohne eindeutige Ursprungsbeziehung bleibt
separat sichtbar. Betraege verschiedener oder unbekannter Waehrungen werden
nicht addiert. Die Anzeige darf umgangssprachlich `Customer Lifetime Value`
heissen, muss aber Definition, Waehrung und Datenstand nennen.

### Pausen und Laufzeiten

Eine `Pause` darf nur aus einem expliziten MemberPress-Statusereignis oder
einem fachlich bestaetigten Intervall entstehen. Ohne Events werden lediglich
beobachtete Luecken zwischen gedeckten Mitgliedschaftszeitraeumen als
`observed_inactivity_gap` ausgewiesen. Eine solche Luecke ist keine behauptete
Kuendigung oder vertragliche Pause.

Auswertbar sind:

- aktive Tage je Mitgliedschaft;
- Anzahl und Dauer belegter Pausen;
- Anzahl und Dauer nur beobachteter Inaktivitaetsluecken;
- Zeit von Registrierung bis Erstkauf;
- Zeit vom Erstkauf bis zur letzten gedeckten Periode.

### Aktiver Bestand zum Jahreswechsel 2025/2026

- Ein `aktives Bestandsabo 2026` ist ein vor dem 1. Januar 2026 begonnenes
  Abo, dessen letzter migrierter MemberPress-Status `active` lautet.
- `Bestandskunden 2026` zaehlt die dazugehoerigen eindeutigen Mitglieder;
  mehrere aktive Abos eines Mitglieds werden hier nur einmal gezaehlt.
- `suspended`, `cancelled`, `pending` und andere nicht aktive Statuswerte
  werden nicht als aktiver Bestand ausgewiesen.
- Diese Retention-Kennzahlen belegen weder eine Rechnung noch einen
  Zahlungseingang im Jahr 2026. Umsatz und Steuer 2026 entstehen weiterhin
  nur aus tatsaechlichen operativen Bestellungen beziehungsweise Finom.

### Produkt- und Erweiterungsanalyse

- Buchungshaeufigkeit wird getrennt nach `complete`, `pending` und `refunded`
  ausgewiesen.
- Mehrfachbuchungen desselben Mitglieds werden sowohl als Transaktionen als
  auch als eindeutige Kunden ausgewiesen.
- Die Erweiterungsquote ist die Zahl abgeschlossener Bestellungen mit einer
  Erweiterung geteilt durch die abgeschlossenen Bestellungen des zugehoerigen
  Basisprodukts.
- Paketkandidaten werden anhand gemeinsamer Buchungen, Attach Rate und Zahl
  eindeutiger Kunden priorisiert. Die Kennzahl zeigt Nutzungsmuster, keine
  Kausalitaet oder automatische Produktempfehlung.
- Die vorlaeufige Titelregel `Erweiterung …` muss vor der ersten produktiven
  Analyse fachlich freigegeben oder durch ein explizites Produktmapping
  ersetzt werden.

## Technischer Vertrag gegen ein Schattensystem

Die historischen Tabellen sind ein eingefrorener Analyse- und
Nachweisbestand. Folgende Grenzen sind technisch und organisatorisch bindend:

- append-only Import aus dem manifestierten MemberPress-Export;
- jeder Bericht zeigt Quellsystem, Import-Batch und Datenstand;
- keine manuelle Bearbeitung historischer Betraege oder Statuswerte;
- Korrekturen nur als neue, begruendete Version mit Auditereignis;
- keine OPOS-, Mahn-, Rechnungs-, Steuer- oder Zahlungslogik;
- keine Rueckschreibungen an Finom oder DATEV;
- Daten ab August 2025 werden nicht aus Finom in eine parallele Finanzhistorie
  repliziert; fuer laufende Finanzfragen wird auf Finom verwiesen;
- operative `wg_bestellung`-Datensaetze ab 2026 duerfen bei jedem Reportaufruf
  read-only als Bestellwertprojektion einbezogen werden. `completed` bedeutet
  dabei abgeschlossener Checkout, nicht bezahlte Finom-Rechnung;
- Membership-Zugangsstatus darf als eigene Produktdomaene gespeichert werden,
  aber nicht als behaupteter Finom-Zahlungsstatus.

Aggregierte Analyseansichten duerfen materialisiert werden, wenn sie jederzeit
aus dem versionierten historischen Bestand reproduzierbar sind. Personenbezogene
Einzelansichten bleiben berechtigten Rollen vorbehalten; allgemeine Produkt-
und Paketberichte verwenden aggregierte Werte.

## Umsetzung in der Membership Suite

- `Historical_Analytics` erzeugt die Projektion `membership-analytics-v7` aus
  den jeweils neuesten append-only Objektversionen und der read-only
  `wg_bestellung`-Projektion ab 1. Januar 2026.
- Die v7-Projektion trennt intern historischen MemberPress-Umsatz, bestaetigte
  aggregierte Finom-Geldeingaenge, kalkulierten
  Membership-Suite-Bestellwert und den daraus addierten kommerziellen
  Lifetime Value. Sie enthaelt ausserdem Jahreszeilen fuer Registrierungen,
  neue kommerzielle Kunden, beide Bestellstroeme, aktive Bestandsabos/-kunden
  zum Jahreswechsel 2025/2026 und beobachtete Abgaenge.
- Die Finom-Snapshots decken `2025-08-01` bis `2025-12-31` mit 45.089,80 EUR
  und `2026-01-01` bis `2026-08-22` mit 48.918,54 EUR Geldeingang ab. Sie
  bleiben wegen fehlender strukturierter Rechnungszeilen von Netto, USt.,
  Kunden-LTV und Produktanalyse getrennt.
- Drei vom Betreiber bestaetigte Finom-era Neukundenstarts werden ohne Namen
  als Monatsaggregate abgebildet: einer im Februar 2026 und zwei im August
  2026. Damit bleibt die Akquisitionskennzahl fachlich vollstaendiger, ohne
  neben Finom ein personenbezogenes Kundenregister aufzubauen.
- Die primaere Adminansicht zeigt daraus eine gemeinsame Kennzahl
  `Historie / YTD`, ohne operative Suite-Bestellwerte hinzuzuaddieren. Die
  Quelltrennung bleibt fuer Audit und fachliche Kontrolle in einem
  geschlossenen Detailbereich sichtbar.
- Geldwerte werden in der Adminansicht ueber die WordPress-Lokalisierung
  formatiert. Nur die beiden grossen Summenkarten runden kaufmaennisch auf
  ganze Euro; Jahres- und Detailtabellen behalten zwei Nachkommastellen, und
  der JSON-Export behaelt kanonische Dezimalstrings.
- Alle 6.857 Quellereignisse bleiben im verschluesselten Nachweisarchiv. Die
  Analyse laedt daraus bewusst nur explizite Pause-/Resume-Typen; Login-, Kurs-
  und redundante Zahlungsevents belasten weder Arbeitsspeicher noch Kennzahlen.
- `Werkzeuge > Membership-Analysen` ist nur fuer Administratoren mit
  `manage_options` und der Archivberechtigung sichtbar. Vor jeder
  15-minuetigen Analysesitzung muss der Administrator sein aktuelles
  WordPress-Passwort erneut bestaetigen. Das Passwort wird weder gespeichert
  noch protokolliert; Fehlversuche werden begrenzt und auditiert.
- Der JSON-Export setzt dieselbe entsperrte Sitzung voraus und wird mit
  Dataset-Hash auditiert.
- `wp wg-finance memberpress-analyze` erzeugt vor einem Import ausschliesslich
  anonymisierte Aggregationen.
- Der reale REST-Dry-run umfasst 105 registrierte Mitglieder, 35 Mitglieder
  mit Bestellungen, 657 Bestellungen und 124 noch fachlich zu filternde
  Erweiterungs-/Basisprodukt-Paare.
- Die REST-Schnittstelle liefert keine Events. Die direkte Datenbankinventur
  hat jedoch 6.857 MemberPress-Events und 23 Ereignistypen belegt. Darunter
  befinden sich 19 `subscription-expired`-Events, aber kein expliziter
  Pause-/Resume-Typ. Deshalb bleiben explizite Pausen bei null; 35 beobachtete
  Inaktivitaetsluecken werden weiterhin lediglich als solche gekennzeichnet.
