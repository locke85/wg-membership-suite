# Membership Suite Tasks

Dieses Verzeichnis enthält repo-weite Produkt- und Migrationstasks der
Webgefaehrte Membership Suite.

- `task-migrate-webgefaehrte-cockpit-into-membership-suite-01.md`: übernimmt
  die produktbezogene Cockpit-Implementierung kontrolliert aus dem
  Plattform-Repo in dieses Ziel-Repo.
- `task-fix-login-secure-password-reset-01.md`: dokumentiert die produktive
  Diagnose des defekten Passwort-Reset-Flows auf `/login-secure/` sowie die
  erforderlichen Content-, Mailtransport-, Routing- und Testkorrekturen.
- `task-automate-finom-monthly-finance-analytics-01.md`: plant die
  nachvollziehbare Monatsaktualisierung der Finance Analytics wahlweise ueber
  Finom/Zapier, freigegebene USt-VA-/Monatsabschluss-Artefakte oder einen
  abgestuften Hybrid ohne Finanzschattensystem.
- `task-membership-suite-parity-review-panel-01.md`: implementiert das
  geschützte Admin-Review für siteübergreifende Abweichungen und den
  signierten, nicht mutierenden Entscheidungsexport.
- `task-smtp-transport-oliver-one-time-setup-08.md`: richtet authentifiziertes
  SMTP mit einmaliger, nicht wieder ausgegebener Passworterfassung und
  kontrollierter Testmail für Oliver ein.
- `task-harden-custom-login-endpoint-10.md`: schließt bei aktivem eigenem
  Login den direkten Core-Anmeldeweg, ohne die Adresse als alleinigen
  Brute-Force- oder Überlastungsschutz zu behandeln.

Vor der Ausführung gilt der verbindliche
[Übergabevertrag](../governance/platform-membership-community-boundary.md).
Er definiert die Repo-Grenze, die Migrations-Gates und die Parkregel für die
Community-Ablage.

Historische Tasks und Results verbleiben in ihrem jeweiligen Ursprungsrepo.
Neue Arbeiten an Membership-, Rollen-, Freischaltungs- und konkreter
Webgefaehrte-Cockpit-Logik werden hier geplant.
