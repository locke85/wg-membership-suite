# Ergebnis: Membership Suite Parity Review Panel

## Status

Der Parity-Review-Adapter ist lokal in Membership Suite 1.7.0 implementiert
und getestet. Er zeigt ein externes Vergleichsmanifest in einer
Admin-Zweispaltenansicht mit rechter Entscheidungsleiste, speichert nur
Reviewentscheidungen und exportiert sie kanonisch gehasht und HMAC-signiert.

## Produktänderung

- `includes/Admin/Parity_Review_Admin.php` lädt ausschließlich eine konfigurierte
  read-only Manifestdatei und bindet sie an die konfigurierte Site-ID.
- Zulässige Entscheidungen sind `sync`, `keep` und `other`; `keep` und
  `other` benötigen eine Begründung.
- Persistenz erfolgt ausschließlich in
  `wg_membership_suite_parity_reviews_v1`.
- Baseline- oder Membership-Suite-Versionswechsel übernehmen alte
  Entscheidungen nicht in den Export.
- REST- und Admin-Download-Export benötigen `manage_options`; der Admin-Write
  benötigt zusätzlich einen Nonce.
- Der Export enthält Entscheidungen, aber keine Baseline-/Istwerte oder
  Evidenzdetails. Die Signatur verwendet den WordPress-Auth-Salt, ohne ihn
  auszugeben.
- Plugin-Bootstrap, Runtime-Version, `readme.txt` und `plugin.json` stehen auf
  1.7.0. Der Konfigurationsvertrag ist im README dokumentiert.

## Verifikation

Bestanden:

- PHP-Syntax über alle PHP-Dateien des Plugin-Repos.
- Alle sieben vorhandenen `*-harness.php`-Regressionen.
- `php tests/parity-review-harness.php` mit Manifestfehlern, allen drei
  Entscheidungen, Reload, Pflichtbegründungen, Capability, Nonce, Export,
  Signatur und Stale-Baseline.
- Cross-Repo-Test: Der echte PHP-Export wird vom JavaScript-Vertrag des
  Plattform-Skills akzeptiert.
- Versionskonsistenz für Plugin-Header, Runtime-Konstante, Stable Tag und
  `plugin.json`.

## Offene Grenzen

- Keine Installation oder Aktivierung auf einer realen Site.
- Kein authentifizierter Admin-Reload, REST-Aufruf oder Download im Browser.
- Keine visuelle Desktop-/Mobile-QA des Panels.
- Kein Manifest-Deployment und keine produktive Entscheidungspersistenz.
- Der Adapter synchronisiert absichtlich nichts; bestätigte `sync`-Einträge
  müssen zuerst durch den globalen Skill in einen Site-Task überführt werden.
