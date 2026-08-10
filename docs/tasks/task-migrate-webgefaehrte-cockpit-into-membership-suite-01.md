# Task: Webgefaehrte-Cockpits in die Membership Suite migrieren

## Status

Geplant

## Eigentümer und Ablageentscheidung

Dieser Task liegt bewusst im **Ziel-Repo** `membership-suite`, weil dort die
künftige Produktstruktur, die Tests, die Release-Verantwortung und der
produktive Cutover zusammengeführt werden müssen.

Das Repo `Agent-model-for-websites` ist in diesem Task Quell-Repo und behält
den Plattformkern sowie die historische Engineering-Evidenz. Dort soll kein
zweiter konkurrierender Migrationstask entstehen.

Portable lokale Repo-Pfade:

- Ziel: `~/Developer/membership-suite`
- Quelle: `~/Developer/Agent-model-for-websites`

## Ausgangslage

Die Repo-Analyse ist dokumentiert in:

- Quelle:
  `docs/results/worker-result-repo-structure-membership-suite-transition-01.md`
- Mapping:
  `docs/results/repo-structure-membership-suite-transition-mapping-01.json`

Die konkrete Cockpit-Produktlogik liegt heute überwiegend unter
`sites/webgefaehrte/` im Quell-Repo. Dazu gehören UI, Rollen- und
Zugriffsprüfungen, REST-Routen, Run-Speicherung, Expertenadapter, Builds,
Fixtures und Tests. Mehrere Dateien laden Daten über relative Pfade aus
`sites/webgefaehrte/data/member-expert-cockpit/`; einzelne Abläufe schreiben
Evidenz nach `sites/webgefaehrte/artifacts/`.

Ein bloßes Verschieben der Dateien ist deshalb nicht zulässig. Der Umzug muss
als getestete Produktmigration mit paralleler Absicherung erfolgen.

## Ziel

Die Webgefaehrte-spezifische Cockpit-Implementierung wird in der Membership
Suite als klar abgegrenztes Produktmodul betrieben. Nach Abschluss gilt:

1. Membership-, Rollen-, Freischaltungs- und Cockpit-Runtime befinden sich im
   Ziel-Repo.
2. Cockpit-Datenpfade sind über einen expliziten Loader/Resolver definiert und
   nicht an die Struktur des Quell-Repos gebunden.
3. Die vorhandenen Experten-Cockpits verhalten sich im Staging und produktiv
   nachweislich gleich oder besser.
4. Das Quell-Repo enthält nur globale Specs, Contracts, Analysekerne und die
   historische Evidenz.
5. Alte produktive Deploypfade werden erst nach erfolgreichem Cutover entfernt.

## Abgrenzung: Community-Ablage ist geparkt

`~/wGCloud/Community` ist **nicht Teil dieses Tasks**.

- Keine neuen Community-Ordner oder Spiegelungen anlegen.
- Keine vorhandenen Community-Dateien ändern, verschieben oder löschen.
- Keinen automatischen Sync zur Community-Ablage implementieren.
- Community-Publikation und Zugriffsmodell bleiben ein separater Folgeprozess.

Die vorhandene Community-Struktur und ihre bisherigen Spiegelungen bleiben
unverändert geparkt.

## Zielstruktur im Membership-Repo

Die Struktur darf bei der Implementierung leicht angepasst werden, wenn die
bestehende Plugin-Bootstrap-Logik eine einfachere Lösung verlangt. Die
Verantwortungsgrenzen müssen jedoch erhalten bleiben:

```text
wg-membership-suite/
  includes/
    Cockpit/
      Access/                 Rollen, Capabilities, Freischaltung
      Api/                    REST-Routen und Berechtigungsverträge
      Runtime/                Loader, Run-Speicherung, Handoff
      Experts/                Webgefaehrte-spezifische Fachadapter
      Ui/                     Shell und serverseitige UI-Komponenten
  data/
    cockpit/                  versionierte, freigegebene Builds
  assets/
    cockpit/                  CSS/JS/Bilder der Cockpit-Oberflächen
  tests/
    cockpit/                  PHP-, Contract-, Fixture- und Browser-Tests
  docs/
    cockpit/                  Betrieb, Datenpfade und Cutover
```

Keine zusätzliche Paket- oder Framework-Schicht einführen, solange es keinen
konkreten zweiten Verbraucher gibt.

## Quellartefakte und vorgesehene Aktion

### Als Produktmodul übernehmen

- `sites/webgefaehrte/deploy/wg-marketer-cockpit.php`
- `sites/webgefaehrte/deploy/wg-andrea-sales-cockpit.php`
- `sites/webgefaehrte/deploy/wg-martin-legal-cockpit.php`
- `sites/webgefaehrte/deploy/wg-sarah-community-cockpit.php`
- `sites/webgefaehrte/deploy/wg-tim-web-design-cockpit.php`
- die zugehörigen versionierten Builds und Schemas unter
  `sites/webgefaehrte/data/member-expert-cockpit/`
- die zugehörigen Runtime-, Fixture-, PHP- und Browser-Tests unter
  `sites/webgefaehrte/scripts/`

Die Liste ist vor der Umsetzung erneut per Referenzsuche zu vervollständigen.
Dateien werden zuerst kopiert und verifiziert; eine Entfernung aus dem
Quell-Repo erfolgt ausschließlich im letzten Cutover-Schritt.

### Im Plattform-Repo belassen

- `specs/02_Cockpit-Architecture.md`
- globale `specs/agent_*.md`
- `control-plane/contracts/`
- siteübergreifende Analysekerne unter `control-plane/experts/`
- globale Standards und Skills
- historische Tasks, Results und Abnahmeberichte

Wenn ein Expertenadapter gleichzeitig allgemeine Analyseberechnung und
Webgefaehrte-UI enthält, wird er zuerst entlang dieser Grenze getrennt. Die
allgemeine Berechnung bleibt im Plattform-Repo; Produktadapter und UI wechseln
ins Ziel-Repo.

### Nicht übernehmen oder veröffentlichen

- Secrets und Credential-Dateien
- `KEYCHAIN_TOKENS.md`
- Release-ZIPs und andere Builds außerhalb eines definierten Release-Prozesses
- `.git`, `.DS_Store`, Caches und `__pycache__`
- operative Screenshots, Live-Responses und vollständige Artifact-Verzeichnisse
- private RAG-Quellen oder personenbezogene Daten

## Umsetzung

### Phase 1: Inventar und Verträge einfrieren

1. Alle direkten und indirekten Includes, Datenpfade, Hooks, Shortcodes,
   REST-Routen, Options-/Transient-Keys, Rollen und Capabilities inventarisieren.
2. Für jede Route und Member-Funktion einen bestehenden oder neuen
   Vertragstest festhalten.
3. Prüfen, welche Dateien wirklich versionierte Runtime-Inputs sind und welche
   nur operative Evidenz darstellen.
4. Bestehende Änderungen in beiden Arbeitsbäumen separat einordnen und nicht
   überschreiben.

Ergebnis: vollständiges Migrationsmanifest mit Quelle, Ziel, Abhängigkeiten,
Test und Cutover-Status.

### Phase 2: Zielstruktur und Loader

1. Cockpit-Modul im Plugin-Bootstrap ergänzen, standardmäßig noch deaktiviert.
2. Einen zentralen Datenpfad-/Build-Resolver implementieren.
3. Zugriffslogik auf WordPress-Capabilities und bestehende Membership-Rollen
   abbilden; keine neue parallele Rollenquelle schaffen.
4. Namespaces und Funktionspräfixe kollisionsfrei halten. Bestehende
   `wg_mc_*`-Aufrufe bei Bedarf über zeitlich begrenzte Compatibility-Wrapper
   unterstützen.

### Phase 3: Ein vertikaler Pilot

1. Einen kleinen, repräsentativen Expertenadapter auswählen.
2. Adapter, freigegebenen Build und relevante Tests in die Zielstruktur
   kopieren.
3. Fixture-Ausgabe, Zugriffsverhalten und REST-Verträge gegen den Bestand
   vergleichen.
4. Abweichungen dokumentieren; der Quellpfad bleibt weiterhin produktiv.

Der Pilot ist Gate für die restliche Migration.

### Phase 4: Shell und übrige Expertenmodule

1. Gemeinsame Shell, Navigation und Zugriffsschutz migrieren.
2. REST-Routen, Run-Speicherung, Review- und Handoff-Verhalten migrieren.
3. Übrige Expertenadapter mit ihren freigegebenen Builds und Tests übernehmen.
4. Testwerkzeuge auf repo-relative Zielpfade umstellen.
5. Operative Evidenz weiterhin außerhalb der Plugin-Quellen erzeugen und nicht
   als Runtime-Abhängigkeit behandeln.

### Phase 5: Staging und Cutover

1. Installierbares Plugin-Paket aus dem Ziel-Repo bauen.
2. Auf einer Staging-Instanz mit realistischem Rollen- und Site-Kontext testen.
3. Alt-/Neu-Parität für Login, Freischaltung, Shell, Expertenmodule,
   REST-Routen, Persistenz und Fehlerfälle dokumentieren.
4. Rollback auf den bisherigen Deploypfad vorbereiten und testen.
5. Erst nach bestandenem Gate den produktiven Bootstrap auf die Membership
   Suite umstellen.

### Phase 6: Quell-Repo bereinigen

1. Produktive Cockpit-Dateien erst nach stabilem Cutover aus
   `sites/webgefaehrte/deploy/`, `data/` und `scripts/` entfernen.
2. Historische Tasks, Results und relevante Abnahme-Evidenz am Ursprung
   belassen.
3. Verweise auf den neuen Produktpfad ergänzen.
4. Nur tatsächlich wiederverwendete Contracts oder Analysekerne in die
   globalen Plattformbereiche überführen.

## Verbindliche Tests und Gates

- PHP-Syntaxprüfung aller migrierten PHP-Dateien
- Plugin-Aktivierung und -Deaktivierung ohne Fatal Error
- bestehende Membership-Runtime ohne Regression
- anonyme, eingeloggte, freigeschaltete und Admin-Zugriffsfälle
- REST-Permission- und Ownership-Tests
- Run-/Transient-/Options-Kompatibilität
- Fixture- und Browser-Parität der Cockpit-Shell und Expertenmodule
- ungültige oder fehlende Build-Datei liefert einen kontrollierten Fehler
- Plugin-Paket enthält alle benötigten Runtime-Dateien und keine privaten Daten
- dokumentierter und getesteter Rollback

Ein bestandener Unit- oder Syntax-Test allein autorisiert keinen produktiven
Cutover.

## Sicherheits- und Datenschutzregeln

- Keine Secrets, Tokens oder Zugangsdaten in Terminalausgaben, Artefakten,
  Commits oder Plugin-Paketen.
- Private Fachquellen nicht in das Repo kopieren.
- Member-/Kundendaten nur in dafür vorgesehenen Laufzeitspeichern verarbeiten.
- Test-Fixtures müssen synthetisch oder ausdrücklich freigegeben sein.
- Bestehende produktive Daten werden nicht destruktiv migriert.

## Deliverables

1. Cockpit-Modulstruktur und dokumentierter Loader im Membership-Repo
2. Migrationsmanifest aller übernommenen und zurückgelassenen Artefakte
3. automatisierte Contract-, PHP-, Fixture- und Browser-Tests
4. installierbares, geprüftes Plugin-Paket
5. Staging-Paritätsbericht
6. Cutover- und Rollback-Runbook
7. Worker-Result unter
   `docs/results/worker-result-migrate-webgefaehrte-cockpit-into-membership-suite-01.md`
8. nach erfolgreichem Cutover dokumentierte Bereinigung im Quell-Repo

## Done Definition

Der Task ist erst abgeschlossen, wenn:

1. alle produktrelevanten Cockpit-Artefakte vollständig klassifiziert sind;
2. die benötigten Produktartefakte im Membership-Repo versioniert und getestet
   sind;
3. globale Plattformanteile nachweislich im Quell-Repo verblieben sind;
4. Staging-Parität und Rollback belegt sind;
5. der produktive Cutover erfolgreich war;
6. keine produktive Runtime mehr vom alten Cockpit-Quellpfad abhängt;
7. die alte Implementierung kontrolliert bereinigt oder mit begründetem,
   terminiertem Deprecation-Status dokumentiert ist;
8. `~/wGCloud/Community` unverändert geblieben ist.

## Abbruch- und Klärungspunkte

Nur unterbrechen, wenn:

- eine Datei nicht eindeutig als Plattformkern oder Produktlogik
  klassifizierbar ist und ihre Verschiebung einen Produktivpfad berührt;
- Rollen-/Capability-Verhalten zwischen beiden Implementierungen nicht
  äquivalent herstellbar ist;
- private oder personenbezogene Daten Teil eines vermeintlichen Runtime-Builds
  sind;
- Staging, Rollback oder ein erforderlicher Zielzugang nicht verfügbar ist.

Unklare Einzelartefakte blockieren nicht das sichere Inventar und den Pilot;
sie werden im Migrationsmanifest als Restpunkt geführt.
