# Task: Etappenstopp-Caption-Abstand

Status: `abgeschlossen`

## Ziel

Das gemeinsame Etappenstopp-Stylesheet definiert einen belastbaren unteren
Außenabstand für Video-Captions, sodass Caption und nachfolgender Transkript-
Bereich visuell getrennt bleiben.

## Scope

- `assets/css/etappenstopps.css`
- fokussierte responsive Browser-Fixture

## Grenzen

- Keine Umschreibung von `post_content`.
- Keine Änderung der Caption-Texte, Videomedien oder Transkript-Inhalte.
- Keine Route- oder Block-ID-spezifische Regel.

## Abnahme

1. Desktop- und Mobile-Fixture messen einen positiven `margin-bottom` am
   `figcaption` und unveränderte Medien-/Containerbreiten.
2. Live-Read-back bestätigt den CSS-Hash.
3. Angemeldete Frontend-Abnahme misst Caption-Abstand und nachfolgenden
   Transkriptbereich.

## Plattformvertrag

`Agent-model-for-websites/sites/tanzlehrer-recruiting/tasks/task-video-caption-spacing-and-quiz-own-answer-06.md`
