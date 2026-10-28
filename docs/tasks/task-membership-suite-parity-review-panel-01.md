# Task: Membership Suite Parity Review Panel

## Status

Lokal umgesetzt am 2026-09-01. Installation und Browser-QA bleiben bis zum
site-spezifischen Rollout offen. Ergebnis:
[`worker-result-membership-suite-parity-review-panel-01.md`](../results/worker-result-membership-suite-parity-review-panel-01.md).

Führender Plattformvertrag:
`~/Developer/Agent-model-for-websites/docs/tasks/task_global_membership-suite-cross-site-parity_5.6_high_plan.md`

## Ziel

Die Membership Suite erhält eine geschützte Admin-Seite mit rechter
Review-Seitenleiste. Sie lädt ein extern bereitgestelltes, secret-freies
Parity-Manifest, zeigt jede Abweichung mit stabiler Diff-ID,
Baseline-Version, Evidenz und Auswirkung und speichert ausschließlich die
Entscheidung des Administrators.

## Scope

- Admin-Seite für Nutzer mit `manage_options`.
- Manifest-Loader über einen expliziten Pfadvertrag; kein Remote-Fetch und
  keine automatische WordPress-Inventur im Panel.
- Entscheidungen `sync`, `keep` und `other`; Begründung ist für `keep` und
  `other` Pflicht.
- Site-lokale, schema-versionierte Entscheidungspersistenz.
- Nonce- und Capability-Prüfung vor jedem Write.
- Sanitierter Export mit kanonischem Payload-Hash und HMAC-Signatur, ohne den
  Signaturschlüssel zu exportieren.
- Read-only REST- und Admin-Download-Export für berechtigte Administratoren.
- Keine Anwendung der gewählten Änderung.

## Grenzen

- Das Panel verändert weder Theme, Plugin-Dateien, Inhalte, Rollen,
  Einstellungen noch Membership-Daten.
- Es importiert kein beliebiges Manifest in die Datenbank. Das Manifest bleibt
  eine vom Deployment bereitgestellte read-only Datei.
- Keine Secrets, vollständigen Inhalte, Benutzer-, Bestell- oder
  Rechnungsdaten im Manifest oder Export.
- `sync` ist nur eine bestätigte Entscheidung; die Umsetzung erfolgt später
  durch den site-spezifischen Task aus dem Plattform-Repo.

## Schnittstelle

- Manifestpfad: Konstante `WG_MEMBERSHIP_SUITE_PARITY_MANIFEST_PATH` oder
  Filter `wg_membership_suite_parity_manifest_path`.
- Option: `wg_membership_suite_parity_reviews_v1`.
- REST: `wg-membership/v1/parity-review/export`, nur `GET`.
- Admin-Export: Nonce-geschützte `admin-post.php`-Aktion.
- Export enthält Schema, Site-ID, Baseline-Hash/-Version,
  Membership-Suite-Version, Entscheidungen, Payload-Hash,
  Signaturalgorithmus und Signatur.

## Ausführbare Abnahme

```zsh
php -l includes/Admin/Parity_Review_Admin.php
php -l wg-membership-suite.php
php -l includes/Plugin.php
php tests/parity-review-harness.php
```

Der Harness muss belegen:

- Manifestvalidierung und kontrollierter Fehler bei fehlendem/ungültigem
  Manifest.
- Persistenz aller drei Entscheidungen und Reload.
- Pflichtbegründung für `keep` und `other`.
- Capability- und Nonce-Ablehnung.
- Export enthält nur bekannte Diff-IDs, ist deterministisch hashbar und
  signiert.
- Keine Methode des Adapters verändert WordPress-/Theme-/Plugin-Konfiguration.

## Live-Gate

Plugin-Installation, Aktivierung, realer Admin-Reload, REST-Aufruf und
Browser-QA auf den Zielsites sind nicht durch diesen lokalen Produktvertrag
allein autorisiert. Sie erfolgen erst in einem separaten Rollout mit
site-spezifischer Freigabe und Rückrollpunkt.

## Ergebnis

Der Worker dokumentiert lokale Evidenz und offene Live-Grenzen unter
`docs/results/worker-result-membership-suite-parity-review-panel-01.md`.
