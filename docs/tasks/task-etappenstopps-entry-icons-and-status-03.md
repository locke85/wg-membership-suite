# Task: Etappenstopp-Eintragsicons und Quizstatus

Status: `abgeschlossen`

## Ziel

Die Membership Suite ergänzt die bereits von GenerateBlocks gerenderten
Etappenstopp-Steuerelemente zentral um die vorgegebenen Zustandsicons, richtet
die drei Zeitwerte einheitlich aus und zeigt vor dem Stopptitel den belegten
Besenwagen-Abschluss des aktuellen Nutzers.

## Scope

- `includes/LMS/Quiz_Status.php`: alle im Stopp-Feld vorhandenen
  Chained-Quiz-IDs auswerten; abgeschlossen ist der Stopp nur, wenn jede ID für
  den aktuellen Nutzer als abgeschlossen belegt ist.
- `includes/Integrations/GenerateBlocks.php`: vorhandene GB-Icon-Platzhalter
  der drei Eintragsbuttons füllen und das Status-Icon am dynamischen Stoptitel
  rendern.
- `includes/Frontend/Assets.php`, `assets/css/etappenstopps.css` und
  `assets/js/etappenstopps.js`: zentrale Zeitspalte sowie tatsächlichen
  offenen/geschlossenen Zustand abbilden.
- Synthetische PHP- und Browser-Harnesses für Status, SVGs, Zustandswechsel,
  Tastaturbedienung, responsive Breite und Zeitspalte.

## Grenzen

- Keine Änderung von Quizfragen, Nutzerfortschritt oder Etappeninhalten.
- Keine automatische Synchronisation zwischen Sites.
- Keine dynamischen Werte im SVG und keine Abhängigkeit von generierten
  routespezifischen Blockklassen.
- Caption-Deduplizierung bleibt eine site-spezifische, hash-geschützte
  Inhaltsmigration im Plattform-Repo.

## Abnahme

1. `php tests/etappenstopp-icons-harness.php`
2. `php tests/etappenstopp-frontend-assets-harness.php`
3. `node tests/etappenstopp-browser-harness.mjs <artifact-dir>`
4. Live-Read-back der fünf Plugin-Dateien per SHA-256.
5. Angemeldete Frontend-Prüfung eines abgeschlossenen und eines nicht
   abgeschlossenen Stopps sowie aller sechs Etappen-Seiten.

## Plattformvertrag

`Agent-model-for-websites/sites/tanzlehrer-recruiting/tasks/task-etappenstopps-icons-times-caption-dedup-05.md`
