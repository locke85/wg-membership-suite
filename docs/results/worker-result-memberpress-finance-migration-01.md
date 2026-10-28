# Ergebnis: MemberPress-Finanzmigration 01

Stand: 22. August 2026  
Ergebnis: Migration einschliesslich vollstaendigem Eventexport,
Jahresanalyse v3, Produktiv-Apply und Idempotenznachweis abgeschlossen. Der
finale Quellbestand bleibt verschluesselt erhalten.

## Bestand und Projektion

| Klasse | Quelle | vorgesehene Zielprojektion | Befund |
| --- | ---: | ---: | --- |
| Mitgliedschaftsprodukte | 39 | 18 | 21 explizit als `Erweiterung …` bezeichnete Produkte werden getrennt projiziert. |
| Erweiterungen | 21 klassifiziert | 21 | Freigegebene Regel: Produkttitel beginnt exakt mit `Erweiterung`. |
| Abos | 79 | 79 | 29 aktiv, 36 gekuendigt, 12 ausstehend, 2 suspendiert. |
| Bestellungen | 657 Transaktionen | 657 | Transaktionszeitraum 24.02.2020 bis 13.01.2026. |
| Zahlungen | 656 | 656 | 650 `complete`, 6 `pending`; Status ist kein neuer Zahlungsnachweis. |
| Erstattungen | 1 | 1 | Quelle liefert keine Parent-Referenz; keine Beziehung wird geraten. |
| Rechnungen | 606 Nummern | 606 | 51 Transaktionen ohne Rechnungsnummer bleiben ohne erfundene Rechnung. |
| Originaldateien | 0 | 0 | Die vollstaendige Inventur fand keine Originaldateien. Bis 2025 bleibt DATEV Unternehmen online der Belegnachweis; es werden keine Altbelege nach Finom kopiert. |
| Kunden | 105 API-Mitglieder, 35 in Transaktionen | 105 historische Kundenobjekte | Produktiv ohne offene Zuordnungsreviews importiert; kein unsicheres Matching ueber Namen. |

Die produktive Raidboxes-Datenbank wurde am 22. August 2026 zusaetzlich
read-only ueber phpMyAdmin inventarisiert. Die Netzwerkdatenbank heisst
`bek0i0_wpdb`, das echte Tabellenpraefix der Quellsite ist
`wp_irpchfpaou_3_`. Gefunden wurden unter anderem 657 Transaktionen, 79 Abos,
6.857 Events und 615 Zeilen in `mpdf_invoice_numbers`. Davon sind 606 mit einer
vorhandenen Transaktion verknuepft und neun verwaist; nur die 606 verknuepften
Zeilen werden projiziert, die neun Altzeilen bleiben Review-Faelle. Der
anonymisierte Befund liegt in
`artifacts/memberpress-finance-migration/source-database-inventory-20260822.json`.
Die Multisite-Zuordnung ist ebenfalls verifiziert: Quelle ist Blog 3, Ziel
`seosharingsystem.de` ist Blog 9 mit Praefix `wp_irpchfpaou_9_`. Auf Blog 9
existieren aktuell noch keine `wg_finance_*`-Tabellen; es wurde somit kein
verdeckter oder vorzeitiger Produktivimport vorgenommen.

Auch die PDF-Datenbankinventur ist negativ und konsistent mit dem REST-Befund:
Blog 3 enthaelt weder PDF-Media-Attachments noch `.pdf`-Referenzen in Postmeta
oder Optionswerten. Das belegt noch nicht, dass im Dateisystem keine dynamisch
erzeugten Altdateien liegen; deshalb bleibt die geschuetzte Dateiinventur offen.

Die anonymisierten Quellsummen betragen `228520.20` fuer abgeschlossene,
`5127.72` fuer ausstehende und `614.04` fuer die erstattete Transaktion. Die
Waehrung wird von der realen MemberPress-API nicht geliefert; deshalb sind
diese Werte noch keine fachlich freigegebene Waehrungssumme.

## Implementierung

Es wurden kleine eigene WordPress-Tabellen statt CPT/Postmeta gewaehlt. Fuer
mehr als 2.100 erwartete Projektionen mit mehreren Versionen, Relationen,
Hash- und Zeitraumssuche waere Postmeta schwer eindeutig zu indizieren und
append-only durchzusetzen. Das Modell umfasst:

- versionierte Batches und Quellhashes;
- append-only Finanzobjekte mit stabiler `logical_id` und eindeutigen
  Source-/Typ-/ID-/Versionsschluesseln;
- append-only Relationen, Review Queue und Auditlog;
- historische JSON-Snapshots, Dezimalbetraege, Dokumenthashes und
  Aufbewahrungsmetadaten;
- WP-CLI Dry-run/Apply, wobei Apply ohne Mapping-, DB-, Event-, Dokumentinventur-
  und Waehrungsfreigabe technisch vor dem ersten DB-Write scheitert;
- geschuetzte Adminsuche, gehashter Originaldownload und streng begrenzter,
  sichtbar gekennzeichneter PDF-Reprint.
- geschuetzte historische Membership-Analysen und einen anonymisierten
  JSON-Export fuer Registrierung, Erstkauf, historischen Kundenumsatz,
  Aktivitaetsluecken, Produktnutzung und Paketkandidaten.

Die vorhandenen CPTs bleiben unveraendert; insbesondere werden historische
MemberPress-Rechnungen nicht in aktuelle `wg_rechnung`-Datensaetze
umgerechnet oder neu nummeriert.

## Quellarchiv

Der authentifizierte REST-Export wurde ohne Schreibrequest erzeugt,
AES-256-CBC/PBKDF2-verschluesselt ausserhalb des Webroots abgelegt und mit
SHA-256 `088f72c8c4f9c7235eff73987fb9567937be8882c82d85079f5cafd1431332aa`
manifestiert. Der Schluessel liegt separat im macOS-Schluesselbund. Ein
Restore in ein temporaeres Verzeichnis sowie die Pruefung aller vier
JSON-Einzelhashes waren erfolgreich.

Der erste REST-Export war noch kein vollstaendiger Migrationsbestand. Praefix,
Tabellen, Mengen und Event-Schema wurden deshalb direkt in der produktiven
Datenbank verifiziert und im finalen, abgegrenzten Finanzdatensatz ergaenzt.

Der finale Bestand ergaenzt den REST-Export um alle 6.857 read-only aus der
produktiven DB exportierten MemberPress-Events. Er liegt als
`memberpress-finance-final-20260822T152721Z.tar.gz.enc` ausserhalb des Webroots;
SHA-256 ist
`2c5dfa8a2ddae9050a5c18882df6fab207277add5f29b8fd3fc7cad530e9acdf`.
Das freigegebene anonymisierte Manifest steht unter
`artifacts/memberpress-finance-migration/final-migration-manifest-20260822T152721Z.json`.

## Reales Dry-run- und Analyseergebnis

Der echte verschluesselte REST-Export wurde ohne Zielwrites durch den aktuellen
Importer und die Analyse-Engine verarbeitet. Alle Mengenchecks bestanden:

- 105 Kunden, 18 Basis-/sonstige Produkte, 21 Erweiterungen, 79 Abos,
  657 Bestellungen, 656 Zahlungen, 1 Erstattung und 606 Rechnungen;
- 105 registrierte Quellmitglieder, davon 35 mit mindestens einer Bestellung;
- 650 abgeschlossene, 6 ausstehende und 1 erstattete Bestellung;
- 35 nur beobachtete Inaktivitaetsluecken mit zusammen 5.179 Tagen;
- keine explizit belegten Pausen; die Datenbank enthaelt zwar 6.857 Events,
  aber keinen expliziten Pause-/Resume-Ereignistyp. Die 19
  `subscription-expired`-Events werden nicht zu Pausen umgedeutet;
- 124 Erweiterungs-/Basisprodukt-Paare mit mindestens einem gemeinsamen
  Kunden als noch fachlich zu filternde Paketkandidaten.

Der eingefrorene v2-Archiv-Analyse-Datenstand einschliesslich Abos und Jahresprojektion
traegt SHA-256
`1e9162b692ecd3dd24f8dfce1126c95556b8997cc89a7c4fabf936e27c3684c5`.
Die produktive MemberPress-Option `currency_code` ist direkt als `EUR`
verifiziert und im finalen Manifest mit `currency_approved=true` freigegeben.

Die v2-Archivprojektion wurde mit EUR gegen den finalen Export ausgefuehrt. Sie
trennt Netto-Umsatz, ausgewiesene Umsatzsteuer, Brutto nach Erstattungen,
Registrierungen, Erstkaeufer und konservativ beobachtete Abgaenge. Alle
Jahreswerte von 2020 bis 2026 bestanden den reproduzierbaren Dry-run.

## Finom-Entscheidung

Die fachliche Systemgrenze wurde durch den Betreiber konkretisiert:

- Bis einschliesslich 2025 wurden alle Kundenrechnungen in DATEV Unternehmen
  online hochgeladen und vom Steuerberater dort verarbeitet.
- Seit dem 1. August 2025 werden Kundenrechnungen direkt in Finom erstellt,
  versandt und den Kontobewegungen zugeordnet. Bis Ende 2025 erfolgte weiterhin
  die Verarbeitung ueber DATEV Unternehmen online; erst ab 2026 ist kein
  zusaetzlicher Rechnungsupload mehr erforderlich.
- Alt-Rechnungen werden nicht nach Finom hochgeladen und dort nicht
  rueckwirkend neu angelegt.
- MemberPress-Historie verbleibt als geschuetzter Nachweis- und Analysebestand
  in der Membership Suite beziehungsweise im verschluesselten Quellarchiv.

Damit ist Finom seit August 2025 das operative Rechnungs- und Zahlungssystem.
Die Zielprojektion
der Alt-Bestellungen ist kein OPOS- oder Rechnungsnebenbuch: Sie dient der
Auswertung von Registrierung, Bestellverlauf, historischem Kundenumsatz,
Mitgliedschafts-/Pausenintervallen sowie Produkt- und Erweiterungsnutzung. Die
verbindlichen Definitionen stehen in
`docs/finance/finance-system-boundary-and-historical-analytics.md`.

## Recht und Aufbewahrung

Die technischen Defaults wurden am 22. August 2026 gegen die offiziellen
Fassungen von [HGB § 257](https://www.gesetze-im-internet.de/hgb/__257.html),
[UStG § 14b](https://www.gesetze-im-internet.de/ustg_1980/__14b.html),
[AO § 147](https://www.gesetze-im-internet.de/ao_1977/__147.html) und die
[BMF-GoBD-Aenderung vom 11. Maerz 2024](https://www.bundesfinanzministerium.de/Content/DE/Downloads/BMF_Schreiben/Weitere_Steuerthemen/Abgabenordnung/AO-Anwendungserlass/2024-03-11-aenderung-gobd.html)
abgeglichen. Rechnungen/Buchungsbelege erhalten acht Jahre ab Jahresschluss;
andere Klassen werden getrennt behandelt. Ein Sperrgrund kann jede
Aussonderung blockieren. Es gibt keinen Loeschlauf. Die steuerliche
Verantwortung und Freigabe bleiben ausserhalb dieses technischen Ergebnisses.

## Produktivabschluss

- Plugin `1.1.0` ersetzte am 22. August 2026 die installierte Version `0.1.1`.
- Folgeversion `1.1.2` stellt die 657 Archivbestellungen read-only unter
  `Bestellungen > Historische Bestellungen` mit Filtern, Archivdetail und
  abgesichertem CSV-Export bereit, ohne operative Bestellposts anzulegen.
- Der authentifizierte Ziel-Dry-run meldete alle erwarteten Mengen und keine
  Reviews.
- Produktiv-Batch 1 legte 9.000 Archivobjekte an.
- Produktiv-Batch 2 legte nichts an und meldete alle 9.000 Objekte als
  `unchanged`.
- Die produktive Adminansicht `Werkzeuge > Membership-Analysen` wurde auf Blog
  9 geladen und zeigte die erwarteten 105 Mitglieder, 657 Bestellungen, 18
  Produkte, 21 Erweiterungen und die Jahreszeilen 2020 bis 2026.
- Die Folgeprojektion `membership-analytics-v5` bezieht operative
  `wg_bestellung`-Datensaetze ab 2026 bei jedem Reportaufruf read-only ein und
  trennt Bestellwerte konsequent von historischem Umsatz und Finom-Zahlungen.
- Der produktive v3-Lauf am 22. August 2026 zeigte 657 MemberPress- und vier
  Membership-Suite-Bestellungen. Drei abgeschlossene Suite-Bestellungen
  ergaben `1068.00 EUR` netto, `202.92 EUR` kalkulierte Umsatzsteuer und
  `1270.92 EUR` brutto; eine weitere Bestellung blieb separat `submitted`.
- Plugin `1.3.0` schuetzt die Analyseansicht und ihren JSON-Export zusaetzlich
  durch Administratorberechtigung und eine sitzungsgebundene erneute
  WordPress-Passwortpruefung. Die Projektion v4 weist vor 2026 gestartete,
  laut Migrationsstatus weiterhin aktive Abos und eindeutige Bestandskunden
  separat in 2026 aus, ohne daraus Umsatz oder Steuer abzuleiten.
- Version `1.3.0` wurde am 22. August 2026 produktiv eingespielt. Auf Blog 9
  wurden die gesperrte Passwortansicht und die Sperre des direkten
  JSON-Export-Endpunkts authentifiziert verifiziert, ohne ein Benutzerpasswort
  einzugeben. Der anonymisierte Nachweis liegt unter
  `artifacts/memberpress-finance-migration/analytics-v4-security-continuity-evidence-20260822.json`.
- Version `1.4.0` ergaenzt die bestaetigten Finom-Geldeingaenge von
  45.089,80 EUR fuer August bis Dezember 2025 und 48.918,54 EUR fuer 2026 YTD
  bis 22. August als eigene Cashflow-Kennzahl mit sichtbarem
  Abdeckungszeitraum. Die Werte werden nicht als Netto/USt., Kunden-LTV oder
  Produktumsatz umgedeutet.
- Version `1.4.0` wurde am 22. August 2026 produktiv eingespielt. Beide
  Cashflow-Werte erscheinen in Zusammenfassung und Jahresauswertung, beide
  Abdeckungszeitraeume sind sichtbar und die Projektion meldet
  `membership-analytics-v5`. Der anonymisierte Live-Nachweis liegt unter
  `artifacts/memberpress-finance-migration/analytics-v5-finom-live-evidence-20260822.json`.
- Folgeversion `1.5.0` vereinfacht die Hauptansicht auf sechs Kennzahlen und
  eine gemeinsame, absteigend sortierte Historie/YTD-Jahrestabelle. Die
  technische Quelltrennung bleibt bestehen, erscheint aber zusammen mit
  Methodik, Mitgliedern/LTV, Produkten und Paketkandidaten erst in
  barrierearmen Aufklappbereichen.
- Version `1.5.0` wurde am 22. August 2026 produktiv eingespielt. Die
  Hauptansicht zeigt 72.239,89 EUR fuer 2025 und 48.918,54 EUR fuer 2026 YTD,
  beginnt in der Jahrestabelle mit 2026 und laedt alle fuenf Detailbereiche
  geschlossen. Auf- und Zuklappen wurde in der authentifizierten Zielansicht
  geprueft. Der anonymisierte Nachweis liegt unter
  `artifacts/memberpress-finance-migration/analytics-v6-simplified-live-evidence-20260822.json`.
- Folgeversion `1.6.0` lokalisiert die sichtbaren Finanzwerte anhand der
  WordPress-Sprache. Die Summenkarten zeigen kaufmaennisch gerundete ganze
  Euro, waehrend Tabellen und Exporte centgenau bleiben. Aus der
  Betreiberbestaetigung werden fuer 2026 ausserdem drei Finom-era Neukunden
  als anonyme Monatsaggregate ergaenzt: einer im Februar und zwei im August.
- Version `1.6.0` wurde am 22. August 2026 produktiv eingespielt. Die beiden
  Summenkarten zeigen `72.240 €` und `48.919 €`, die Jahrestabelle behaelt
  `72.239,89 €` und `48.918,54 €`. Fuer 2026 erscheinen drei Neukunden;
  der geschlossene Quellenbereich weist alle drei als Finom-bestaetigt aus.
  Der anonymisierte Live-Nachweis liegt unter
  `artifacts/memberpress-finance-migration/analytics-v7-localized-acquisition-live-evidence-20260822.json`.
- Die neun verwaisten `mpdf_invoice_numbers`-Zeilen bleiben als dokumentierter
  Quellenbefund ausserhalb der Rechnungsprojektion.
- Der anonymisierte Zielnachweis liegt unter
  `artifacts/memberpress-finance-migration/target-apply-evidence-20260822.json`.

Ziel-Apply-Status: **COMPLETE**. Die Quelle wurde nicht veraendert, deaktiviert
oder geloescht; ihre spaetere Stilllegung ist eine getrennte, explizit
freizugebende Betriebsaktion.

## Nachgemeldeter Finom-Cutover und Datenluecke

Am 22. August 2026 wurde der operative Finom-Cutover durch den Betreiber auf
den 1. August 2025 korrigiert. Der erste gelieferte Finom-Bildschirmtext fuer
August bis Dezember 2025 zeigt 45.089,80 EUR eingehenden Verlauf und
5.546,59 EUR geplante Eingaenge. Nur 45 rechnungsbezogene Zahlungseintraege
ueber 32.447,94 EUR lassen sich darin textuell identifizieren; 12.641,86 EUR
bleiben ohne eindeutigen Rechnungsbezug. Netto, Umsatzsteuer, strukturierter
Rechnungsstatus, Kundenmapping und Produktzeilen fehlen. Deshalb wurde dieser
Text nicht als Umsatz importiert. Der anonymisierte Intake-Nachweis liegt unter
`artifacts/memberpress-finance-migration/finom-screen-text-intake-2025-08-01-to-2025-12-31.json`.

Ein zweiter Bildschirmtext fuer den Zeitraum 1. Januar bis 22. August 2026
belegt aggregiert 48.918,54 EUR eingehenden Finom-Verlauf und 14.817,42 EUR
geplante Eingaenge. Die Verlaufsliste umfasst laut Finom mehr als 100 Eintraege,
der uebermittelte Detailtext endet jedoch bereits innerhalb des Juli. Zudem
enthalten die geplanten Betraege offene Rechnungen aus 2024 und 2025. Der
YTD-Wert wird deshalb nur als Cashflow-Evidenz dokumentiert, nicht als Umsatz,
Steuer oder Kunden-LTV importiert. Der anonymisierte Nachweis liegt unter
`artifacts/memberpress-finance-migration/finom-screen-text-intake-2026-01-01-to-2026-08-22.json`.

## Ausgefuehrte technische Tests

- PHP-Syntaxpruefung aller Plugin- und Testdateien: bestanden.
- Bestehender Passwort-Reset-Harness: 8 Szenarien bestanden.
- Finanzarchiv-Harness: Mapping, Credential-Minimierung, kanonische Hashes,
  Wiederholungslauf ohne Dubletten, Reprint-Kennzeichnung und Reprint-Sperre bei
  unbekannter Waehrung sowie CLV-, Pausen-, Luecken- und Paketanalyse bestanden.
- Analytics-Zugriffsharness: Adminpflicht, gesperrter Sitzungsstart,
  Passwort-Fehlerpfad, 15-Minuten-Freigabe und passwortfreie Auditdaten
  bestanden.
- Echter Export- und Ziel-Dry-run: Mengen- und No-write-Checks bestanden.
- Produktiv-Apply und unmittelbar wiederholter Idempotenzlauf: bestanden.
- Shell-Syntax und JSON-Validierung: bestanden.
- Verschluesselter Restore und Hashabgleich: bestanden.

Der Produktivlauf wurde erst nach erfolgreichem Ziel-Dry-run ausgefuehrt. Der
zweite Lauf bestaetigte die konfliktfreie Wiederholbarkeit gegen die echten
WordPress-/MySQL-Zieltabellen.
