# Task: eRecruiting-Fixes in den Membership-Suite-Standard übernehmen

Status: `umgesetzt; authentifizierte Editor-Abnahme offen`

Führender Site-Vertrag:
`~/Developer/Agent-model-for-websites/sites/seosharingsystem/tasks/task-erecruiting-parity-and-template-standardization-01.md`

## Ziel

Alle auf `tanzlehrer-recruiting.de` bestätigten, siteübergreifend nutzbaren
Membership-Funktionen werden als getesteter Produktstandard konsolidiert. Die
gemeinsame WordPress-Installation stellt denselben Codevertrag für Blog 8 und
die Schablone auf Blog 9 bereit, ohne bloglokale Inhalte oder Konfigurationen
zu vermischen.

## Scope

- Konsolidierung der vorhandenen Klassen und Assets für Etappen-Metaboxen,
  Etappenstopp-Interaktion, Quizabschlussstatus, Checkout-Zustand,
  Angebotsvorbelegung, Bestellmails, SMTP und Parity-Review.
- Prüfung des vollständigen Live-Pluginbestands gegen das Produkt-Repository;
  keine Versionsanhebung, solange nicht alle ausgelieferten Dateien und
  Bootstrap-Registrierungen zusammenpassen.
- Site-neutrale Hooks, Capability-, Nonce-, Sanitizing- und
  Multisite-Grenzen; site-spezifische IDs und Empfänger bleiben Optionen oder
  Inhaltsdaten.
- Regressionstests für die auf eRecruiting gefundenen Ursachen, insbesondere
  gemappte Cookie-Domains, semantisch falsche Loaderlinks, fehlende Metaboxen,
  Caption-Dubletten, Mehrfachquizstatus und unauthentifizierten Mailtransport.
- Versionierter, rückrollbarer Deploy des konsolidierten Standards in die
  gemeinsame WordPress-Installation.

## Grenzen

- Keine eRecruiting-Post-IDs, Quiz-IDs, Nutzer-IDs, Mailadressen oder
  Angebots-IDs als globaler Produktdefault.
- Keine automatische Reparatur redaktioneller Inhalte durch das Plugin.
- Keine automatische Schablonen-Synchronisation und keine Anwendung offener
  Parity-Entscheidungen.
- Keine Secrets in Repository, Logs, Exporten oder Tests.
- Unabhängige, bereits im schmutzigen Worktree vorhandene Produktänderungen
  werden weder verworfen noch ungeprüft mit veröffentlicht.

## Rückrollpunkt

Vor dem Deploy werden die vollständige installierte Pluginfassung, Dateihashes
und aktive Versionsnummer gesichert. Ein Rollback stellt diesen Snapshot
atomar wieder her; site-lokale Optionen und Inhaltsdaten bleiben unberührt.

## Ausführbare Abnahme

1. `php -l` besteht für alle geänderten PHP-Dateien.
2. Alle fokussierten Harnesses für Metaboxen, Etappenstopps, Checkout,
   Bestellmails, SMTP und Parity bestehen; negative Berechtigungs-,
   Konfigurations- und Eingabepfade sind enthalten.
3. Repository und gebautes Release enthalten dieselben erwarteten Klassen,
   Assets, Bootstrap-Registrierungen und Versionswerte.
4. Live-Read-back stimmt bytegenau mit dem freigegebenen Release überein.
5. Blog 8 behält die bereits abgenommenen Funktionen; Blog 9 erhält denselben
   Plattformvertrag ohne Übernahme site-spezifischer Werte.
6. Authentifizierte Editor- und Frontend-QA auf beiden Blogs belegt das
   tatsächlich geänderte Verhalten; ein reiner Codevergleich genügt nicht.
7. Parallele Requests können das additive Finance-Schema-Upgrade nicht
   gleichzeitig starten; ein veralteter Upgrade-Lock wird nach fünf Minuten
   kontrolliert neu übernommen.
8. Der vorhandene Spickzettel-Ablauf wird einschließlich REST-Nonce,
   Overlay-Ereignis und Popup zentral aus der Membership Suite bereitgestellt,
   damit veraltete site-lokale GP-Hooks keine Funktionslücke verursachen.

## Ergebnis

`docs/results/worker-result-promote-erecruiting-fixes-to-membership-suite-standard-09.md`
