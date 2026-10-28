# Ergebnis: Etappen- und Etappenstopp-Felder im Editor

Stand: 2026-09-02  
Status: **complete**

## Ursache

Die fachlichen Metaboxen waren in der aktuellen Membership Suite nicht
registriert. Ihre letzte belegte Definition lag im früheren GeneratePress-
Child-Theme. Bestehende Inhalte behielten deshalb ihre Postmeta-Werte; neue
Seiten und Beiträge erhielten jedoch keine strukturierte Eingabemaske. Die
generische WordPress-Box `Individuelle Felder` war kein Ersatz, weil ihre
begrenzte Schlüsselliste die alten `wg_*`-Felder bei neuen Inhalten nicht
anbot.

## Umsetzung

- Neue Klasse `includes/Admin/Etappe_Meta_Boxes.php`.
- Neun Felder für Beiträge: Stoptitel, Video, Spickzettel und Besenwagen
  einschließlich `wg_besenwagen_code`.
- Zwölf Felder für Seiten: Etappentitel, Introtext/-bild, Überschriften und
  fünf Ablaufwerte.
- Bestehende Schlüssel und historische Metabox-IDs bleiben unverändert.
- Speicherung ist auf den passenden Post-Type begrenzt und prüft Nonce,
  Berechtigung, Autosave und Revision; Text, Textarea und Attachment-ID werden
  typgerecht normalisiert.
- Die lokale unveröffentlichte Pluginlinie `1.7.0` lädt die Klasse künftig
  regulär. Für den produktiven Erstrollout wurde davon getrennt ein minimaler
  Hotfix `1.6.1` aus dem tatsächlichen Remote-Stand gebaut.

## Verifikation

Alle acht vorhandenen `*-harness.php`-Tests sind grün. Der neue Harness meldet
`Etappe meta boxes harness: 12 scenarios passed`. PHP-Lint ist für Klasse,
lokalen Bootstrap sowie beide Live-Hotfix-Bootstrapdateien fehlerfrei.

Live auf `tanzlehrer-recruiting.de`, Blog-ID `8`:

- E06-S16 (`49589`): strukturierte Box vorhanden, alle neun Felder genau
  einmal vorhanden, alle neuen Fachwerte weiterhin leer.
- E01-S01 (`1`): alle neun Felder vorhanden;
  `wg_besenwagen_code = [chained-quiz 32]` unverändert.
- E01 (`47885`): strukturierte Etappenbox und alle zwölf Felder vorhanden;
  bestehende Werte und Bildvorschau werden angezeigt.
- Pluginliste: aktiv, Version `1.6.1`.
- Alle drei Editoren: keine PHP-Fehlerseite und `0` sichtbare
  `.block-editor-warning`.
- Öffentliche E01-Route: erreichbar, Etappeninhalt sichtbar,
  `Quiz not found` kommt nicht vor.

Für E06-S16 wurde bewusst noch keine Quiz-ID gespeichert, weil für diesen
Stopp keine Ziel-ID vorgegeben oder im Zielblog validiert wurde. Der Rollout
hat keine Inhalte oder Metawerte verändert.
