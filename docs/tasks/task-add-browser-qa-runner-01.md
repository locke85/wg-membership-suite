# Task: Reproduzierbaren Browser-QA-Runner ergänzen

## Ziel

Das Plugin-Repository stellt einen lokalen Browser-QA-Runner bereit, der
öffentliche Seiten auf Desktop und Mobil ohne Anmeldung prüft und einen
sanitisierten Bericht mit Screenshots erzeugt.

## Scope

- `control-plane/browser-qa/browserQa.mjs`
- `package.json` und Lockfile für die fest gepinnte Playwright-Version
- fokussierter Test auf einer öffentlichen Membership-Site

## Grenzen

- Keine Cookies, Zugangsdaten, Formulareingaben oder persistenten
  Browserprofile.
- Nur GET- und HEAD-Anfragen; alle anderen Browseranfragen werden blockiert.
- Keine Installation, Konfiguration oder Änderung einer WordPress-Site.
- Berichte enthalten keine Query-Werte, Tokens, Cookies oder Header.

## Abnahme

```sh
npm run browser-qa:preflight
npm run browser-qa -- \
  --url https://landingheld.de/ \
  --output-dir artifacts/browser-qa/<timestamp>
```

Der Lauf erzeugt `browser-qa-report.json`, `browser-qa-summary.md` und je URL
Desktop- sowie Mobile-Screenshots. Ein fehlgeschlagener Seitencheck endet mit
Exit-Code 1, ein Infrastrukturfehler mit Exit-Code 2.
