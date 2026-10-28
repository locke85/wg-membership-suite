# Runbook: MemberPress-Finanzarchiv und Cutover

Stand: 22. August 2026

## Sicherheitsvertrag

- Quelle nur mit einem read-only DB-Benutzer, read-only Dateizugriff oder den
  dokumentierten REST-`GET`-Routen lesen.
- MemberPress und Add-ons fuer die Migration weder aktivieren noch lizenzieren.
- Keine Klartext-Exports, Credentials oder Kundenreports in Git oder im Webroot.
- `WG_FINANCE_ARCHIVE_ROOT` zeigt auf das entschluesselte, geschuetzte
  Dokumentarchiv ausserhalb des Webroots. Ohne diese Konstante gibt es keinen
  Originaldownload.
- Import und Einsicht verwenden getrennte Capabilities:
  `wg_import_finance_archive`, `wg_view_finance_archive` und
  `wg_reprint_finance_archive`.

## M0: Inventur vervollstaendigen

1. Beim Hoster einen konsistenten read-only Dump und einen Dateiexport der
   Quell-Netzwerkseite anfordern. Der direkte Raidboxes-phpMyAdmin-Zugang ist
   verifiziert; der automatische Gesamtbackup-Download umfasst dagegen die
   gesamte 17,8-GB-Box und ersetzt keinen gezielten, manifestierten Export.
2. Fuer diese Quelle sind Datenbank `bek0i0_wpdb` und Site-Praefix
   `wp_irpchfpaou_3_` am 22. August 2026 read-only bestaetigt. Vor jedem Lauf
   erneut gegen die echte Konfiguration pruefen. Details stehen im
   anonymisierten Datenbankinventar unter `artifacts/memberpress-finance-migration/`.
   Das Ziel ist Multisite-Blog 9 mit Praefix `wp_irpchfpaou_9_`; dort waren bei
   der Inventur noch keine `wg_finance_*`-Tabellen vorhanden.
3. Uploads und nicht oeffentliche MemberPress-PDF-Verzeichnisse inventarisieren.
4. Rechnungsnummer, Transaktions-ID und Datei anhand dokumentierter
   Quellbeziehungen verknuepfen; unklare Dateien bleiben Review-Faelle.
5. Das technisch aus `mepr_options.currency_code` belegte `EUR`, Mapping und
   Aufbewahrungsannahmen fachlich sowie steuerlich freigeben. Die neun
   verwaisten `mpdf_invoice_numbers`-Zeilen bleiben bis zur Einordnung Reviews.

## M1: verschluesselter Export

Der vorhandene REST-Teilexport wird reproduzierbar erzeugt mit:

```sh
scripts/memberpress-finance-export.sh \
  --base-url https://seo-sharing-system.de \
  --keychain-service WEBGEFAEHRTE_WP_APPLICATION_PASSWORD \
  --output-dir "$HOME/Documents/memberpress-finance-archive"
```

Das Anwendungspasswort und der separat erzeugte Archivschluessel liegen nur im
macOS-Schluesselbund. Das Skript erzeugt ein AES-256-CBC/PBKDF2-Archiv und eine
SHA-256-Sidecar-Datei. Der vollstaendige DB-/Dateiexport wird getrennt mit
Zeitpunkt, Tabellen, Zeilen, Dateihashes und Restore-Anweisung manifestiert.

Restore-Pruefung in einem temporaeren Verzeichnis:

1. Sidecar-Hash gegen die verschluesselte Datei pruefen.
2. Mit dem Keychain-Eintrag `MEMBERSHIP_SUITE_MEMBERPRESS_ARCHIVE_KEY`
   entschluesseln.
3. Archiv entpacken und jeden JSON-/Dateihash gegen `manifest.json` pruefen.
4. Keine entschluesselten Daten nach dem Test im Temp-Verzeichnis belassen.

## Dry-run und Import

```sh
wp wg-finance memberpress-import \
  --source-dir=/protected/memberpress-export \
  --manifest=/protected/memberpress-export/manifest.json
```

Der Befehl schreibt ohne `--apply` nichts. Fuer einen Apply muessen im Manifest
`mapping_approved=true`, `database_export_complete=true`,
`events_export_complete=true`, `document_inventory_complete=true`, eine
bestaetigte ISO-`default_currency` ungleich `XXX` und `currency_approved=true`
stehen. Fehlt nur eines dieser Gates, scheitert der Lauf vor dem ersten
DB-Write:

```sh
wp wg-finance memberpress-import \
  --source-dir=/protected/memberpress-export \
  --manifest=/protected/memberpress-export/manifest.json \
  --apply
```

Eine anonymisierte Vorabanalyse ohne Zielwrites liefert:

```sh
wp wg-finance memberpress-analyze \
  --source-dir=/protected/memberpress-export \
  --manifest=/protected/memberpress-export/manifest.json
```

Sie enthaelt keine Kundenliste, aber Summen, Produktnutzung,
Erweiterungs-/Basisprodukt-Ueberschneidungen, Datenstand und Warnungen. Nach
einem Staging-Import steht die personenbezogene Ansicht nur Nutzern mit
`wg_view_finance_archive` unter `Werkzeuge > Membership-Analysen` zur Verfuegung.

Wenn beim Hoster kein WP-CLI-Zugang verfuegbar ist, kann derselbe Importvertrag
ueber `POST /wp-json/wg-membership/v1/finance-import` ausgefuehrt werden. Die
Route verlangt die Import-Capability, authentifiziertes HTTPS, alle Apply-Gates
und den exakten `source_export_sha256` zusaetzlich im Header
`X-WG-Migration-Confirm`. Die Antwort enthaelt nur Mengen und Hashes, keine
Kundendaten. Ein Wiederholungslauf bleibt idempotent.

Den Apply zuerst auf einer Staging-Kopie ausfuehren. Danach denselben Lauf
wiederholen: `inserted` muss null und `unchanged` muss dem Projektionsbestand
entsprechen. Ein geaenderter Snapshot erzeugt eine neue Version, kein Update.
Ein Fehler rollt die DB-Transaktion zurueck.

## Recherche, Original und Mehrstueck

Unter `Werkzeuge > Finanzarchiv` kann ein berechtigter Nutzer nach
Rechnungsnummer, exakter Quell-ID, Kunde und Zeitraum suchen. Originale werden
nur nach Realpath- und SHA-256-Pruefung ausgeliefert. Jeder Detailabruf,
Originaldownload und Reprint wird protokolliert.

Ein Mehrstueck ist nur moeglich, wenn kein Original vorhanden ist und
Rechnungsnummer, Ausstellungsdatum, Empfaengeradresse, Leistung, Netto, Steuer,
Brutto und bestaetigte Waehrung im historischen Snapshot vorhanden sind. Das
PDF traegt sichtbar `RECHNUNGSKOPIE`, nutzt dieselbe Rechnungsnummer und
protokolliert Snapshot-Hash sowie Templateversion. Es erzeugt weder Buchung
noch Rechnung noch Zahlung.

## Finom-Grenze

Die Abgrenzung ist fachlich festgelegt:

- Kundenrechnungen bis einschliesslich 2025 liegen in DATEV Unternehmen online
  und wurden dort vom Steuerberater verarbeitet.
- Seit 2026 werden Kundenrechnungen in Finom erstellt, versandt und den
  Kontobewegungen zugeordnet. Finom ist dafuer das operative System.
- Es erfolgt kein Upload historischer Rechnungen nach Finom.
- Das MemberPress-Archiv bleibt Nachweis- und Analysequelle fuer den
  historischen Zeitraum, aber fuehrt keine offenen Posten, Mahnungen,
  Zahlungszuordnungen oder neuen Rechnungen.

Die vollstaendige Entscheidung und die Kennzahlendefinitionen stehen unter
`docs/finance/finance-system-boundary-and-historical-analytics.md`.

## Historische Analyseabnahme

Vor dem Cutover zusaetzlich pruefen:

1. Registrierung, erster Kauf und erster abgeschlossener Kauf werden getrennt
   ausgewiesen.
2. Historischer Kundenumsatz zaehlt nur abgeschlossene Transaktionen abzueglich
   eindeutig belegter Erstattungen und wird je Waehrung ausgewiesen.
3. Vertragliche Pausen und nur beobachtete Inaktivitaetsluecken werden nicht
   vermischt.
4. Produktberichte zeigen Transaktionen und eindeutige Kunden sowie die
   Quellstatus getrennt.
5. Erweiterungs-/Paketberichte beruhen auf einem freigegebenen Produktmapping
   und zeigen Datenstand sowie Stichprobengroesse.
6. Laufende Finanzdaten ab 2026 werden nicht in diese historische Projektion
   repliziert.
7. Jeder Bericht nennt `historical-membership-analytics-v2`, Dataset-Hash und
   Import-Batches.

Der read-only REST-Abgleich ist fuer die Kundenzuordnung nicht vollstaendig:
Die Header melden 47 Nutzer auf der Quellsite und 20 auf der Zielsite, an den
REST-Client wird aber jeweils nur der Admin-Datensatz geliefert. Bei einem
Multisite-Bestand ist die sichtbare Site-Zuordnung zudem nicht mit der
netzwerkweiten Benutzerexistenz gleichzusetzen. Deshalb erfolgt der
entscheidende Abgleich erst im WordPress-Staging mit `get_user_by('email', …)`;
Namen und alte numerische User-IDs bleiben unzulaessige Ersatzschluessel.

## M4-Gates

Kein Stilllegen, Loeschen oder Umschalten der Quelle, bevor alle Punkte mit
Evidenz abgenommen sind:

- DB-/Dateiexport vollstaendig, verschluesselt und wiederherstellbar;
- Anzahl und Dezimalsummen je Objektklasse abgestimmt;
- alle Differenzen und 51 Transaktionen ohne Rechnungsnummer entschieden;
- Original-PDF-Stichprobe, Reprint-Stichprobe, Abo mit mehreren Zahlungen,
  Erweiterung, Zahlung und Erstattung bestanden;
- Fremdnutzer-, Capability-, Nonce-, Hashmanipulations- und Pfadtests bestanden;
- zweiter Import erzeugt keine Dubletten;
- Steuerverantwortliche Stelle bestaetigt Mapping, Aufbewahrung und Reprint;
- Supportpfad und Rollback dokumentiert.

Der Daten-Cutover wurde am 22. August 2026 abgeschlossen. Die Quellseite bleibt
unveraendert; Deaktivierung oder Loeschung sind nicht Teil dieses Cutovers und
erfordern eine eigene Betriebsfreigabe.
