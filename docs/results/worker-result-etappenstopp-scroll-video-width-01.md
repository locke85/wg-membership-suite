# Worker Result: Etappenstopp-Scrollposition und Medienbreite

Status: `passed`  
Abgeschlossen: 2026-09-02  
Zielsite: `https://tanzlehrer-recruiting.de` (Multisite-Blog-ID `8`)  
Task: `docs/tasks/task-fix-etappenstopp-scroll-video-width-01.md`

## Korrigiertes Ergebnis

Der Root-Cause-Fix ist produktiv. Aktionsausloeser der Etappenstopps werden
serverseitig als echte `<button type="button">` ohne `href` ausgegeben. Damit
existiert kein leeres URL-Fragment mehr, das GeneratePress beziehungsweise ein
weiterer Handler als Sprung nach `#top` auswerten kann.

Der zuvor ausgerollte JavaScript-Adapter, der Hash und Scrollposition
nachtraeglich wiederherstellte, war nur Symptombehandlung. Er wurde aus Quelle
und Live-Installation entfernt. Die responsive Begrenzung der dynamisch
geladenen Medien bleibt als eigenstaendiger zweiter Fix bestehen.

## Belegte Ursache

Die Seite E06 (`post_id 48538`) speichert ihre Video- und Besenwagen-Ausloeser
direkt im GenerateBlocks-Query-Loop als:

- Blocktyp `generateblocks/text`
- `tagName: "a"`
- `className: "toggle-box"`
- `htmlAttributes.href: "#"`
- Loaderdaten wie `data-post-id`, `data-target` und `data-action`

Das Element ist keine Navigation, sondern oeffnet dynamischen Inhalt. Der
semantisch falsche Link war deshalb die Ursache des Sprungs. `preventDefault()`
im spaeter registrierten Loader beseitigte diesen fehlerhaften Vertrag nicht.

## Implementierung

- `includes/Frontend/Assets.php` filtert ausschließlich gerenderte
  `generateblocks/text`-Aktionsbloecke der Klassen `toggle-box` und
  `open-spickzettel`, sofern sie `tagName: "a"` und exakt `href="#"`
  besitzen.
- Die Ausgabe wird zu `<button type="button">`; `href` entfaellt.
- Alle sichtbaren Inhalte, Klassen und Loader-Datenattribute bleiben erhalten.
- Echte Fragment- oder Ziel-Links bleiben unveraendert.
- `assets/css/etappenstopps.css` begrenzt weiterhin den Tabs-/Grid-/Accordion-
  Zweig und Video-, iframe- sowie WordPress-Medienwrapper auf die verfuegbare
  Inhaltsbreite.
- Es gibt keinen globalen `overflow-x:hidden`-Workaround und keinen
  Scroll-Restaurierungs-Code mehr.

## Live-Read-back

Nach dem korrigierten Rollout liefert E06 fuer Beitrag `49589`:

- Videoausloeser: `BUTTON`, `type="button"`, kein `href`,
  `data-target="v-box-49589"`, `data-action="load"`
- Besenwagenausloeser: `BUTTON`, `type="button"`, kein `href`,
  `data-target="b-box-49589"`, `data-action="toggle"`

E01 liefert denselben Vertrag unter anderem fuer Beitrag `1`.

Beim echten E06-Klick blieben `scrollY = 534.375`, die Triggeroberkante
`872.626953125px` und ein leeres Fragment vor/nach dem Laden identisch. Der
dynamische Inhalt wurde geladen; Dokumentbreite und Viewportbreite waren beide
`2709px`, Boxbreite `1212.01171875px`, Videobreite `1148.02734375px`.

Beim echten E01-Klick blieben `scrollY = 755.625`, Triggeroberkante
`678.37890625px` und Fragment unveraendert. Der Inhalt mit `6793` Textzeichen
wurde geladen; Dokument `2709/2709px`, Box `1212.01171875px`, Video
`1148.02734375px`.

Der mit dem ersten Ansatz eingefuehrte produktive JavaScript-Asset wurde
entfernt. Im Remote-Verzeichnis `assets/js/` verbleibt nur das unabhaengige
`wg-2fa.js`; die Etappenrouten binden kein `etappenstopps.js` mehr ein.

SHA-256 von Quelle und produktivem Read-back:

- `includes/Frontend/Assets.php`:
  `cad5dfee7442e82125f5a71dbed1f847bd22e2b5c59619cbc41c271a900755b6`
- `assets/css/etappenstopps.css`:
  `8e4b0350fb1af5ab380977311ad64dffb864e113e0eed3f0efb97854c0969f43`

## Tests

- Alle neun PHP-Harnesses bestanden.
- Etappenstopp-Asset-/Renderharness: `15` Szenarien bestanden, darunter
  Buttonausgabe, entferntes `href`, erhaltene Loaderattribute, unveraenderte
  echte Links und Nicht-Etappenrouten.
- Echter Browser-Harness: Desktop `1440px` und Mobil `390px` bestanden;
  Ausloeser ist jeweils ein Button ohne `href`, Klick und Enter funktionieren,
  Fragment und Triggerposition bleiben unveraendert, Medien bleiben in der Box.
- Oeffentliche Live-QA E01/E06: `4/4` Viewports bestanden, HTTP `200`, jeweils
  eine H1, kein horizontaler Overflow, keine defekten Bilder, keine Page- oder
  Console-Errors. Der bekannte Hinweis auf ein Bild ohne Alt-Attribut ist nicht
  Teil dieses Tasks.
- PHP-/JavaScript-Syntax und fokussierter Diff-Check bestanden.

## Evidenz und Rollback

Evidenz und Vorher-/Nachherdateien:

`sites/tanzlehrer-recruiting/artifacts/etappenstopp-interaction/2026-09-02T09-08-54Z/`

Der Stand unmittelbar vor der Root-Cause-Korrektur liegt unter
`root-cause-remote-before/`, der produktive Read-back unter
`root-cause-remote-after/`. Ein Rollback stellt dortiges `Assets.php` und CSS
wieder her und spielt den gesicherten alten JavaScript-Adapter nur dann erneut
ein, wenn bewusst zum verworfenen Symptombehandlungsstand zurueckgekehrt werden
soll.
