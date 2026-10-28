# Task: Etappen- und Etappenstopp-Felder im Editor bereitstellen

Stand: 2026-09-02  
Produkt: `webGefaehrte Membership Suite`  
Erster Zielblog: `tanzlehrer-recruiting.de` (Multisite-Blog-ID `8`)

Status: **lokal umgesetzt und auf dem ersten Zielblog als Hotfix 1.6.1
abgenommen**

## Ziel

Die aus der bisherigen Membership-Laufzeit bekannten Metafelder werden als
strukturierte, beschriftete Metaboxen in Gutenberg registriert. Neue
Etappenseiten und Etappenstopp-Beiträge können damit dieselben Daten pflegen
wie bestehende Inhalte, insbesondere `wg_besenwagen_code` für Shortcodes wie
`[chained-quiz 32]`.

## Scope

- Metabox `Etappeninhalte` für den Post-Type `page` mit den zwölf bestehenden
  `wg_etappe_*`, `wg_p_*`, `wg_img_*`, `wg_h4_*` und `wg_list_*`-Schlüsseln.
- Metabox `Zusatzinfos: Video, Spickzettel & Besenwagen` für `post` mit den
  neun bestehenden `wg_stop_*`, `wg_video_*`, `wg_spickzettel_*` und
  `wg_besenwagen_*`-Schlüsseln.
- Nonce-, Post-Type-, Autosave-/Revision- und Capability-Prüfung vor Writes.
- Feldtypgerechte Sanitization; Bildreferenzen bleiben Attachment-IDs.
- Media-Auswahl für das Etappenbild.
- Übernahme in den normalen Membership-Suite-Bootstrap.

## Grenzen

- Keine Änderung vorhandener Metawerte oder Beitragsinhalte durch Installation.
- Keine automatische Vergabe einer Chained-Quiz-ID.
- Keine Übernahme der Felder `enclosure` oder anderer WordPress-intern
  verwalteter Metadaten.
- Der Rollout auf `tanzlehrer-recruiting.de` darf nur die drei neuen bzw.
  minimal angepassten Plugin-Dateien ausliefern; unveröffentlichte Änderungen
  aus dem lokalen Plugin-Worktree bleiben ausgeschlossen.

## Rückrollpunkt

Vor dem Live-Upload werden SHA-256 und lokale Backups von
`wg-membership-suite.php` und `includes/Plugin.php` gesichert. Die neue Datei
`includes/Admin/Etappe_Meta_Boxes.php` ist der einzige zusätzliche Pfad.
Rollback: beide Bootstrap-Dateien aus dem Backup zurückspielen und die neue
Klassendatei entfernen beziehungsweise aus dem Ladevertrag nehmen. Metawerte
bleiben davon unberührt.

## Ausführbare Abnahme

```zsh
php -l includes/Admin/Etappe_Meta_Boxes.php
php -l includes/Plugin.php
php -l wg-membership-suite.php
php tests/etappe-meta-boxes-harness.php
```

Der Harness muss Feldvollständigkeit, getrennte Registrierung für `post` und
`page`, bestehende Werte im Render-Ergebnis, korrekte Sanitization und die
Ablehnung von falschem Post-Type, ungültigem Nonce, Autosave, Revision und
fehlender Berechtigung belegen.

## Live-Abnahme Tanzlehrer Recruiting

1. Eine bestehende Etappenseite zeigt `Etappeninhalte` mit ihren vorhandenen
   Werten.
2. E01-S01 zeigt die Etappenstopp-Metabox und unverändert
   `wg_besenwagen_code = [chained-quiz 32]`.
3. Der neue Entwurf E06-S16 (`post_id=49589`) zeigt alle neun Felder und ein
   leeres, beschreibbares Feld `wg_besenwagen_code`.
4. Es gibt keine PHP-Fehlerseite, keine neue Gutenberg-Blockwarnung und keine
   Änderung an den öffentlichen Seiten allein durch den Plugin-Rollout.

## Evidenzziel

`docs/results/worker-result-etappe-etappenstopp-editor-fields-01.md` sowie der
site-spezifische Rolloutbericht im Plattform-Repository.
