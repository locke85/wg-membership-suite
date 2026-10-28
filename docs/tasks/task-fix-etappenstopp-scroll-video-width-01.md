# Task: Etappenstopp-Scrollposition und Medienbreite reparieren

Stand: 2026-09-02  
Zielsite: `tanzlehrer-recruiting.de`  
Referenzroute: `/schreibtisch/etappen/e06/`  
Produktiv-Blog-ID: `8`

## Ziel

Beim Öffnen eines Etappenstopps bleibt die auslösende Zeile im sichtbaren
Lesebereich. Aktionsauslöser werden semantisch als Buttons ohne URL-Fragment
gerendert; damit existiert kein Navigationsziel mehr, das GeneratePress als
Sprung zum Seitenanfang behandeln kann. Nach dem dynamischen Laden bleiben Video,
Transkript und alle weiteren Stoppinhalte vollständig innerhalb der verfügbaren
Inhaltsbreite. Die Korrektur gilt zentral für alle Etappenstopps.

## Belegter Vorherzustand

- E06-S16, Beitrag `49589`, wird über `.toggle-box[data-action="load"]`
  dynamisch in `#v-box-49589` geladen.
- Der gespeicherte GenerateBlocks-Block auf Seite `48538` ist ein
  `generateblocks/text` mit `tagName: "a"` und `htmlAttributes.href: "#"`.
- Unmittelbar vor dem Klick lag `scrollY` bei `359.375`; danach bei `0`.
- Nach dem Laden betrug die Dokumentbreite `2481px` bei `2099px` Viewport.
- Der dynamische Box-/Tabs-Zweig wuchs durch das intrinsisch `1920px` breite
  Video über den `1320px` breiten Inhaltscontainer hinaus.

## Scope

- Zentraler Frontend-Adapter in der Membership Suite für Etappenrouten.
- Server-seitige Normalisierung ausschließlich der als Link gespeicherten
  `toggle-box`- und `open-spickzettel`-Aktionsblöcke mit `href="#"` zu
  `<button type="button">` vor der GenerateBlocks-Ausgabe.
- Unveränderte echte Links und unveränderte Loader-Datenattribute.
- Semantisch begrenzte CSS-Regeln für `min-width: 0`, Containerbreite und
  responsive Videos, iframes, WordPress-/MediaElement-Wrapper und Captions.
- Ein fokussierter PHP-/CSS-/Browser-Regressionsharness.
- Minimaler Live-Hotfix ausgehend vom tatsächlichen Pluginstand `1.6.1`, mit
  Remote-Backup, Hash-Read-back und angemeldeter Browserabnahme.

## Grenzen

- Keine Änderung an Seiten-, Beitrags-, GenerateBlocks- oder GP-Element-Inhalten.
- Keine Umbenennung der sichtbaren Platzhaltertexte und keine neuen Medien.
- Kein vollständiger Rollout der lokal bereits weiterentwickelten Pluginlinie
  `1.7.0`; live gehen nur der zentrale Asset-Owner und das neue Stylesheet.
- Keine Änderung an Quiz-, Fortschritts-, Rollen-, Checkout- oder
  Membership-Daten.
- Die Korrektur darf horizontales Scrollen nicht pauschal am Dokument
  verstecken; die verursachenden Container und Medien werden begrenzt.

## Rückrollpunkt

Vor dem Upload werden die produktiven Dateien `includes/Frontend/Assets.php`
und `includes/Plugin.php` gesichert. Das neue Stylesheet ist separat
entfernbar. Ein Rollback stellt die gesicherten PHP-Dateien wieder her und
entfernt nur `assets/css/etappenstopps.css`.

## Ausführbare Abnahme

```zsh
php tests/etappenstopp-frontend-assets-harness.php
node tests/etappenstopp-browser-harness.mjs
php -l includes/Frontend/Assets.php
php -l includes/Plugin.php
```

Live werden mindestens E06-S16 und ein weiterer vorhandener Etappenstopp
angemeldet auf Desktop und echtem Mobilformat geprüft:

1. Der Klick öffnet den dynamischen Inhalt genau einmal.
2. Der Auslöser ist ein `BUTTON` mit `type="button"` und besitzt kein `href`.
3. `location.hash` wird nicht auf `#top` beziehungsweise `#` geändert; die
   Triggerposition weicht nach dem Laden höchstens `2px` ab.
4. `document.scrollWidth <= document.clientWidth`.
5. Video-/iframe-/MediaElement-Breite überschreitet den dynamischen Container
   nicht; Video-Seitenverhältnis und Bedienbarkeit bleiben erhalten.
6. Schließen und erneutes Öffnen funktionieren, ebenso Tastaturaktivierung.
7. Browserkonsole, Page Errors und defekte Medien bleiben bei `0`.

## Evidenzziel

`docs/results/worker-result-etappenstopp-scroll-video-width-01.md`
