# Übergabevertrag: Membership Suite, Plattform und Community-Ablage

## Status und Zweck

Stand: 9. August 2026

Dieses Dokument ist die verbindliche Repo-Übergabe für Worker, die die
Webgefaehrte-Cockpits in die Membership Suite integrieren oder angrenzende
Produktarbeit durchführen. Es fasst die getroffenen Entscheidungen zusammen
und verhindert, dass Plattformkern, Produkt-Runtime und Community-Publikation
erneut vermischt werden.

## Beteiligte Ablagen

- Dieses Ziel-Repo:
  `~/Developer/membership-suite`
- Plattform- und Quell-Repo:
  `~/Developer/Agent-model-for-websites`
- geparkte Community-Ablage:
  `~/wGCloud/Community`

Auf dem vorherigen Rechner lagen diese Verzeichnisse unter
`/Users/jczichos/...`. Implementierungen dürfen Benutzernamen nicht fest
codieren; sie verwenden Repo-Roots und konfigurierbare Pfade.

## Warum der Migrationstask hier liegt

Der Umzug wird im Ziel-Repo gesteuert, weil hier nach Abschluss Folgendes
zusammengehört:

- Membership-, Rollen- und Freischaltungslogik;
- geschützte Cockpit-Oberflächen;
- produktspezifische REST- und Persistenzlogik;
- Expertenadapter und freigegebene Runtime-Builds;
- Plugin-Bootstrap, Tests, Paketierung, Release und Rollback.

Der führende Task ist:

- `docs/tasks/task-migrate-webgefaehrte-cockpit-into-membership-suite-01.md`

Im Quell-Repo wird kein konkurrierender Ausführungstask angelegt. Dort bleiben
Analysebericht, Mapping, Plattformverträge und historische Evidenz.

## Verbindliche Repo-Grenze

### In dieses Repo übernehmen

Fachlich gehören hierher:

- die gemeinsame Webgefaehrte-Cockpit-Shell;
- Login-, Rollen-, Capability- und Expertenfreischaltung;
- produktspezifische REST-Routen und Run-/Review-/Handoff-Speicherung;
- konkrete Adapter für Andrea, Martin, Sarah, Tim und weitere
  Webgefaehrte-Funktionen;
- freigegebene produktive Build-Konfigurationen und Schemas;
- Cockpit-UI-Assets sowie PHP-, Contract-, Fixture- und Browser-Tests.

Aktuelle Quellen liegen insbesondere unter:

- `Agent-model-for-websites/sites/webgefaehrte/deploy/wg-*cockpit.php`
- `Agent-model-for-websites/sites/webgefaehrte/data/member-expert-cockpit/`
- zugehörigen Dateien unter
  `Agent-model-for-websites/sites/webgefaehrte/scripts/`

Die Liste ist vor der Migration per Referenzsuche zu vervollständigen.

### Im Plattform-Repo belassen

- globale Specs und der wiederverwendbare Cockpit-Vertrag;
- globale Tasks, Standards und Skills;
- siteübergreifende Contracts, Analyse-, Audit- und Optimierungslogik;
- historische Tasks, Results und Abnahmeberichte;
- nur tatsächlich produktunabhängige Analysekerne.

Wenn eine alte Datei Analyseberechnung und Webgefaehrte-UI vermischt, wird sie
vor dem Umzug entlang dieser Grenze geteilt. Generisch klingende Hilfsfunktionen
werden nicht ohne nachgewiesenen zweiten Verbraucher extrahiert.

## Aktueller Stand

- Die Repos wurden analysiert und klassifiziert.
- Produktiver Cockpit-Code wurde noch nicht in dieses Repo verschoben.
- Bestehende relative Pfade koppeln Deploydateien, Builds, Tests und operative
  Evidenz an `sites/webgefaehrte/`.
- Deshalb ist ein Datei-Copy ohne Loader-, Test- und Cutover-Konzept verboten.
- Der aktuelle Arbeitsbaum enthält bereits fremde Änderungen, insbesondere an
  `includes/Frontend/Avatar.php`; Worker müssen sie erhalten.

## Verbindlicher Migrationsablauf

1. Vollständiges Manifest der Includes, Hooks, Routen, Rollen, Capabilities,
   Persistenz-Keys, Datenpfade, Builds und Tests erstellen.
2. Cockpit-Modulstruktur und einen zentralen Datenpfad-/Build-Resolver in
   diesem Repo anlegen.
3. Das Modul zunächst ohne produktiven Cutover integrieren.
4. Einen repräsentativen Expertenadapter mit Build und Tests kopieren.
5. Fixture-, Zugriffs-, REST- und UI-Parität gegen den Bestand prüfen.
6. Danach Shell, Zugriffsschutz, Persistenz und übrige Adapter als
   zusammenhängende Einheit migrieren.
7. Installierbares Plugin-Paket im Staging mit realistischem Rollenkontext
   prüfen.
8. Rollback auf den bisherigen Deploypfad vorbereiten und testen.
9. Erst nach bestandenem Gate produktiv umstellen.
10. Erst nach stabilem Cutover die alten Produktdateien im Quell-Repo
    entfernen oder mit terminiertem Deprecation-Status dokumentieren.

Der Zielzustand darf nicht zurück in
`sites/webgefaehrte/data/member-expert-cockpit/` lesen. Historische Dokumente
werden nicht rückwirkend in dieses Repo verschoben.

## Community-Ablage: ausdrücklich geparkt

`~/wGCloud/Community` ist nicht Teil des Migrationstasks. Worker
dürfen dort im Rahmen dieser Arbeit keine Dateien oder Ordner anlegen, ändern,
verschieben, löschen oder automatisch synchronisieren.

Die bereits vorhandene Struktur und Spiegelungen bleiben unverändert. Das
langfristige Ziel der Ablage ist eine verständliche Veröffentlichungs- und
Arbeitsoberfläche für ausdrücklich freigegebene Mitgliederunterlagen. Sie ist
kein Runtime-Verzeichnis und kein drittes Code-Repo. Eine weitere Befüllung
benötigt einen separaten Auftrag mit Freigabe-, Datenschutz- und Drift-Regeln.

Insbesondere niemals in die allgemeine Community-Ablage übertragen:

- Secrets, Tokens oder Credential-Dokumente;
- Deploydateien und Plugin-Builds;
- interne Snapshots, Live-Responses und technische Shadow-Artefakte;
- private RAG-Quellen;
- personenbezogene Member- oder Kundendaten.

## Qualitäts-, Sicherheits- und Cutover-Gates

- Bestehende fremde Änderungen in beiden Repos nicht überschreiben.
- Syntax, Plugin-Aktivierung und bestehende Membership-Funktionen prüfen.
- Anonyme, eingeloggte, freigeschaltete und Admin-Zugriffe testen.
- REST-Permissions, Ownership und Persistenzkompatibilität prüfen.
- Fehlende oder ungültige Builds müssen kontrolliert fehlschlagen.
- Das Release-Paket darf keine privaten Daten oder lokalen Entwicklerpfade
  enthalten.
- Staging-Parität und getesteter Rollback sind Voraussetzung für Produktion.
- Ein bestandener Syntax- oder Unit-Test allein autorisiert keinen Cutover.

## Referenzen im Quell-Repo

- `docs/governance/platform-membership-community-boundary.md`
- `docs/results/worker-result-repo-structure-membership-suite-transition-01.md`
- `docs/results/repo-structure-membership-suite-transition-mapping-01.json`

Bei einem Widerspruch zwischen Inventar, Task und realer Runtime wird der
produktive Eingriff gestoppt. Sichere Analyse- und Pilotarbeiten können
fortgesetzt werden; die Abweichung wird im Migrationsmanifest dokumentiert.
