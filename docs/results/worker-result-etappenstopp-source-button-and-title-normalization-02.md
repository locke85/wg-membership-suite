# Worker Result: Etappenstopp-Quellbuttons und Stoptitel

Status: `passed`  
Abgeschlossen: 2026-09-02  
Zielsite: `https://tanzlehrer-recruiting.de` (Multisite-Blog-ID `8`)  
Task: `docs/tasks/task-etappenstopp-source-button-and-title-normalization-02.md`

## Ergebnis

Die Korrektur ist live. Alle 18 Etappenstopp-Aktionsbloecke der sechs Seiten
E01 bis E06 sind nun bereits im gespeicherten GenerateBlocks-Inhalt native
`button type="button"` ohne `href`. Der zuvor notwendige
`render_block`-Umwandlungsfilter wurde aus der Membership Suite entfernt.

Die neutrale Textaktions-Darstellung wird direkt von den vorhandenen
GenerateBlocks-`styles`- und `css`-Attributen der Komponente getragen. Sie gilt
auch fuer Hover und Fokus. Es gibt keine neue globale CSS-Regel, keinen
Inline-Style und keine Aenderung an den Customizer-Werten. Die globale rote
CTA-Gestaltung bleibt fuer echte Standard-Buttons bestehen, greift aber nicht
mehr auf die Etappenstopp-Aktionen durch.

Alle 16 `wg_stop_title`-Werte sind auf `Stop x` beziehungsweise
`Stop x – Beschreibung` normalisiert. Die ausgelesenen Endwerte decken
`Stop 1` bis `Stop 16` jeweils genau einmal ab. Vorhandene Beschreibungen
blieben erhalten; bislang leere Felder wurden nur dort mit einer belegten
Beschreibung ergaenzt, wo der Beitragstitel sie lieferte.

## Ursache und Loesung

GeneratePress definiert im site-spezifischen Customizer-Stylesheet generische
Farben fuer jedes native `button`. Der vorangegangene Laufzeitfilter wandelte
die als `a href="#"` gespeicherten Loader zwar semantisch in Buttons um, der
gespeicherte GenerateBlocks-Block besass aber keinen eigenen Buttonzustand.
Darum gewann die globale Customizer-Kaskade.

Die Quellmigration aenderte Tag, HTML-Attribute und Blockstyles atomar. Die
bestehenden Klassen und Loaderdaten (`data-post-id`, `data-target`,
`data-action`) blieben unveraendert. Der installierte GenerateBlocks-2.x-
Vertrag wurde vorher gegen dessen `block.json` geprueft; `button` ist fuer
`generateblocks/text` ein offiziell registrierter Tag.

## Live-Read-back

- Seiten und Aktionsbloecke: `6/6` Seiten, jeweils `3` kontrollierte Bloecke.
- Finale Revisionen: `49603` bis `49608`.
- Inhalts-Read-back: `6/6` SHA-256-Werte entsprechen dem erwarteten
  serializer-treuen Inhalt.
- Metafelder: `16/16` Werte entsprechen dem erwarteten `Stop x`-Schema.
- Live-DOM aller Etappen: `BUTTON`, `type="button"`, kein `href`, kein
  `style`-Attribut.
- Computed Styles in Ruhe und Fokus: transparenter Hintergrund, kein Rand,
  `0px` Padding; die gemeinsam generierte Hover-/Fokusregel besitzt denselben
  Zustand. Der Customizer-Hintergrund `rgb(175, 17, 51)` ist nicht mehr
  wirksam.
- E06-Ladevorgang: dynamischer Inhalt wird per Klick geladen, Fragment bleibt
  leer; der Button bleibt auch fokussiert transparent.

## Verifikation

- Migrationsharness: `26` Szenarien bestanden.
- Membership-Suite-PHP-Harnesses: alle fuer den Task ausfuehrbaren Harnesses
  bestanden. `finance-export-dry-run.php` ist ein separates CLI-Werkzeug mit
  erforderlichem Exportziel und wurde nicht als Test ausgefuehrt.
- Etappenstopp-Assetharness: `8` Szenarien bestanden; kein temporaerer
  `render_block`-Filter und kein Scroll-Restaurierungs-JavaScript registriert.
- Lokaler Chromium-Harness: Desktop und Mobil bestanden, einschliesslich
  Buttonsemantik, Klick/Enter, unveraendertem Fragment und begrenzter
  Medienbreite.
- Authentifizierter Gutenberg-Editor: E01 bis E06 jeweils `0`
  `.block-editor-warning` und `0` Aktionen `Wiederherstellung versuchen`.
- Authentifizierte Frontend-Matrix: alle sechs Routen zeigen die
  normalisierten Titel; sichtbare Aktionsbuttons besitzen auf jeder Route den
  gleichen neutralen Komponentenstil und kein horizontaler Overflow wurde
  gemessen.
- Reproduzierbare oeffentliche Browser-QA: `6` URLs, `12` Viewports,
  `0` harte Fehler, HTTP `200`, jeweils genau eine H1, kein horizontaler
  Overflow und keine defekten Same-Origin-Bilder.

Die oeffentliche E04-QA meldet weiterhin die bereits vorhandene externe
Bildreferenz `https://wg_img_intro/` sowie fehlende Alt-Texte als Hinweise.
Diese Befunde sind unabhaengig von Button- und Stoptitelvertrag und wurden in
diesem Task nicht veraendert.

## Rollback und Betriebsnachweis

Vor beiden erfolgreichen Writes wurden vollstaendige Seiteninhalte,
Metafelder, Status, Modified-Zeitpunkte und Hashes gesichert. Die
Membership-Suite-Datei vor Entfernung des Filters und der produktive
SFTP-Read-back liegen ebenfalls im Artefaktordner.

Beim ersten Einmallauf lagen geschuetzte `.php`-Evidenzdateien kurzzeitig im
MU-Plugin-Stamm. WordPress lud sie dadurch selbst als Plugin und lieferte leere
Antworten. Die Dateien wurden unmittelbar lokal gesichert und remote entfernt;
Startseite, Login und E06 lieferten danach wieder HTTP `200` mit regulaerem
Inhalt. Der finale Runner speicherte Evidenz nur noch in einem nicht automatisch
geladenen Unterordner. Nach dem erfolgreichen Read-back wurden Runner,
Migrationsdatei und Remote-Evidenzordner vollstaendig entfernt.

Evidenz:

`/Users/Jan/Developer/Agent-model-for-websites/sites/tanzlehrer-recruiting/artifacts/etappenstopp-source-title/2026-09-02T13-55-00Z/`
