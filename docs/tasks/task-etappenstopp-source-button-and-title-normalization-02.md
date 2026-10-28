# Task: Etappenstopp-Quellbutton und Stoptitel normalisieren

Stand: 2026-09-02  
Zielsite: `tanzlehrer-recruiting.de`  
Blog-ID: `8`
Status: `abgeschlossen`

## Ziel

Die Aktionsausloeser in allen sechs Etappenseiten besitzen ihren endgueltigen
semantischen und visuellen Vertrag direkt im gespeicherten GenerateBlocks-
Inhalt. Der temporaere serverseitige Link-zu-Button-Filter wird entfernt. Die
Ausloeser bleiben echte Buttons ohne `href`, uebernehmen aber nicht die rote
Customizer-CTA-Darstellung, sondern die belegte Textaktions-Darstellung des
vorherigen Komponentenstands.

Das Custom Field `wg_stop_title` aller 16 Etappenstopp-Beitraege wird auf das
Schema `Stop x` beziehungsweise `Stop x – Beschreibung` normalisiert. `x` ist
die aus dem kanonischen Beitragstitel `Eyy-Sxx` belegte Stopnummer. Bestehende
Beschreibungen bleiben erhalten; bei bislang leeren Feldern wird die
Beschreibung aus dem Beitragstitel uebernommen, sofern dort eine vorhanden ist.

## Scope

- Seiten `47885`, `48512`, `48532`, `48534`, `48536`, `48538`.
- Nur GenerateBlocks-Textbloecke der Aktionsklassen `toggle-box` und
  `open-spickzettel` mit `tagName: "a"` und `href: "#"`.
- Direkte Migration von Tag, HTML-Attributen und den bestehenden
  GenerateBlocks-`styles`-/`css`-Attributen der Komponente; keine neue globale
  CSS-Ausnahme, kein Inline-Style und kein Customizer-Override.
- Entfernung des temporaeren `render_block`-Umwandlungsfilters nach
  erfolgreicher Inhaltsmigration.
- Posts `1`, `45890` bis `45900`, `48927`, `48929`, `48940`, `48942`,
  `48944`, `48946`, `48948`, `48950`, `49589` gemaess Inventar.

## Grenzen

- Keine Aenderung an Customizer-Werten oder am siteweiten CTA-Buttondesign.
- Keine Aenderung an Video-, Spickzettel-, Quiz- oder Besenwagen-Inhalten.
- Keine Aenderung an WordPress-Beitragstiteln, Slugs, Status oder Kategorien.
- Keine automatische Ableitung einer Beschreibung, wenn der Beitragstitel nur
  `Eyy-Sxx` enthaelt.
- Writes nur bei exaktem Vorherwert und erwarteter Seitenquellsignatur.

## Rueckrollpunkt

Vor jedem Write werden vollstaendiges `post_content`, `wg_stop_title`,
Poststatus, Modified-Zeitpunkt und SHA-256 gesichert. WordPress-Revisions-IDs
und der Dateistand des Membership-Suite-Adapters werden nach dem Write
zurueckgelesen. Ein Rollback stellt nur die sechs Seiteninhalte, die 16
Metawerte und den vorherigen Adapterstand wieder her.

## Ausfuehrbare Abnahme

1. Inventar bestaetigt sechs Etappenseiten und 16 Etappenstopp-Beitraege.
2. Gespeicherter Seiteninhalt enthaelt fuer die Zielklassen keinen
   `tagName: "a"`/`href: "#"`-Vertrag mehr.
3. Der Frontend-DOM zeigt native Buttons ohne `href`; kein serverseitiger
   Umwandlungsfilter und kein Scroll-Restaurierungs-JavaScript ist aktiv.
4. Computed Styles der Textaktionen entsprechen der vorherigen Linkvariante,
   nicht dem roten Customizer-CTA-Button.
5. Alle 16 `wg_stop_title`-Werte beginnen exakt mit `Stop 1` bis `Stop 16`;
   jede Nummer kommt genau einmal vor.
6. E01 bis E06 zeigen die normalisierten Titel; Stopps oeffnen per Klick und
   Tastatur ohne Hash- oder Scrollsprung.
7. Dynamische Medien bleiben auf Desktop und echtem Mobilformat innerhalb der
   Inhaltsbreite.
8. Geaenderte Seiten werden im Gutenberg-Editor mit `0`
   `.block-editor-warning` und `0` Wiederherstellungsaktionen geprueft.
9. Remote-/REST-Read-back, Vorher-/Nachher-Hashes und Browser-QA werden unter
   einem site-spezifischen Artefaktpfad dokumentiert.

## Evidenzziel

`docs/results/worker-result-etappenstopp-source-button-and-title-normalization-02.md`
