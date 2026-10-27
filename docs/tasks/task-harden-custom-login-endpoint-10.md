# Task: Membership-Login gegen direkte Core-Anmeldungen härten

## Status

Offen. Dieser Task beschreibt die Korrektur und autorisiert weder einen
produktiven Rollout noch Änderungen an Traefik oder am Custom Functionality
Plugin.

## Ausgangslage

Die Membership Suite stellt bei aktivierter 2FA eine eigene Login-Adresse
bereit. Der bestehende Schutz erfüllt den erwarteten Vertrag noch nicht:

- `Config::is_two_factor_enabled()` ist die Voraussetzung für die
  Login-Umschreibung; der Standardwert ist `false`.
- Der Legacy-Fallback `login-secure` ist vorhersehbar und darf nicht als
  Geheimnis oder alleiniger Schutz betrachtet werden.
- `Two_Factor_Email::redirect_wp_login()` leitet direkte GET-Aufrufe von
  `/wp-login.php` an die konfigurierte Login-Adresse weiter und gibt diese
  dadurch preis.
- Dieselbe Methode nimmt alle POST-Aufrufe von der Umleitung aus. Ein direkter
  Anmeldeversuch gegen `/wp-login.php` erreicht deshalb weiterhin die
  WordPress-Authentifizierung.
- Die 2FA-Verarbeitung beginnt erst nach erfolgreicher
  Passwortauthentifizierung. Sie schützt ein Konto, verhindert aber nicht die
  durch ungültige Passwortversuche verursachte PHP- und WordPress-Last.

Die Verfügbarkeit der Website wird außerhalb dieses Repositories durch
Traefik-Ratenbegrenzung und begrenzte Apache-Prozesse geschützt. Die
Membership Suite ergänzt diesen Schutz durch ein konsistentes
Anwendungsverhalten.

## Ziel

Wenn der eigene Membership-Login aktiviert ist, gilt ein eindeutiger Vertrag:

1. Die konfigurierte Login-Seite unterstützt Anmeldung, Abmeldung, erneute
   Anmeldung und 2FA weiterhin vollständig.
2. Ein direkter GET-Aufruf von `/wp-login.php` verrät die konfigurierte
   Login-Adresse nicht.
3. Ein direkter Passwort-POST an `/wp-login.php` authentifiziert keinen Nutzer.
4. Erforderliche WordPress-Aktionen wie Passwort-Reset, Abmeldung,
   passwortgeschützte Beiträge und Sitzungsbestätigung werden ausdrücklich
   geprüft und entweder über den eigenen Pfad unterstützt oder eng begrenzt
   zugelassen.
5. Bei deaktivierter Membership-2FA bleibt das bisherige WordPress-Verhalten
   unverändert.

Die Änderung wird als zusätzlicher Schutz gegen Standardbots beschrieben. Sie
ersetzt weder Traefik-Ratenbegrenzung noch Apache-Ressourcenlimits oder
Kontoschutz durch 2FA.

## Scope

- `includes/Login/Two_Factor_Email.php`
- `includes/Shortcodes/Login_Form.php`, soweit das Formularziel betroffen ist
- `includes/Config.php`, nur falls ein eindeutiger Aktivierungsvertrag nötig ist
- fokussierte PHP-Harness-Tests unter `tests/`
- README- und Changelog-Ergänzung zur tatsächlichen Schutzwirkung
- ein dokumentierter, reversibler Test auf genau einer freigegebenen
  Membership-Site nach lokaler Abnahme

Die Implementierung soll direkte und intern umgeschriebene Requests anhand des
tatsächlich angeforderten Pfads unterscheiden. Eine Anfrage an die
konfigurierte Login-Seite darf nicht fälschlich als direkter Core-Aufruf
gesperrt werden.

## Grenzen

- Keine Änderung am CFP und keine zweite Login-Implementierung in einem
  anderen WordPress-Plugin.
- Keine Behauptung, die Login-Adresse sei geheim oder ein vollständiger
  Brute-Force-Schutz.
- Keine gemeinsame neue Geheimadresse für alle Sites und keine Speicherung
  von Login-Pfaden als Secret.
- Keine Änderung von Traefik, Apache, Containerlimits, DNS oder Firewall in
  diesem Task.
- Keine Sperre von WP-CLI oder legitimen AJAX-/REST-Aufrufen.
- Keine Benutzernamen, Passwörter, Cookies, Reset-Schlüssel oder IP-Adressen in
  Tests, Logs oder Ergebnisartefakten.
- Kein produktiver Test mit echten Nutzerkonten. Für die Abnahme ist ein
  ausdrücklich freigegebenes synthetisches Konto zu verwenden.
- Keine Erweiterung des 2FA-Umfangs außerhalb des Login-Routings. Eine
  getrennte Prüfung der vollständigen 2FA-Abdeckung bleibt eigene Folgearbeit.

## Erforderliche Korrektur

1. Direkte Aufrufe von `/wp-login.php` und intern auf `wp-login.php`
   umgeschriebene Aufrufe der konfigurierten Login-Seite zuverlässig
   unterscheiden.
2. Direkte GET-Aufrufe bei aktivem eigenem Login mit einer neutralen Antwort
   beenden, die weder per `Location`-Header noch im Antworttext die neue
   Adresse verrät.
3. Direkte Credential-POSTs auf dem Core-Pfad vor der eigentlichen
   Passwortprüfung abweisen. Die Antwort bleibt generisch und gibt weder
   Konten noch die gültige Login-Adresse preis.
4. Die zulässigen WordPress-Aktionen explizit auflisten und testen. Ein
   pauschales Freigeben aller POSTs ist nicht zulässig.
5. Generierte Login-, Abmelde- und Reset-URLs auf die konfigurierte Seite
   führen, ohne Schleifen oder einen Rückfall auf die direkte Core-Anmeldung.
6. Das Verhalten mit Slug-Rewrite und mit einer konfigurierten Page-ID prüfen.
7. Fehler sicher behandeln: Kann die Request-Art nicht eindeutig bestimmt
   werden, darf die Korrektur keinen offenen alternativen Loginweg erzeugen.

## Ausführbare Abnahme

Automatisierte Tests belegen mindestens:

1. Bei deaktivierter 2FA bleiben direkter Core-GET und -POST unverändert.
2. Bei aktiviertem eigenem Login rendert ein GET auf den konfigurierten Pfad
   das erwartete Loginformular.
3. Ein direkter GET auf `/wp-login.php` liefert eine neutrale Ablehnung ohne
   Weiterleitung und ohne Nennung des gültigen Pfads.
4. Ein direkter Credential-POST auf `/wp-login.php` ruft die
   Passwortauthentifizierung nicht auf und erzeugt keine Sitzung.
5. Ein POST über den konfigurierten Login-Pfad erreicht die reguläre
   Authentifizierung; falsche und richtige Testdaten werden korrekt behandelt.
6. Der 2FA-Ablauf nach gültigem Passwort bleibt funktionsfähig.
7. Abmeldung, verlorenes Passwort, Reset-Link, erneute Anmeldung,
   `interim-login`, `reauth` und der Umgang mit passwortgeschützten Beiträgen
   sind entweder erfolgreich oder mit einer begründeten, dokumentierten
   Ausnahme abgedeckt.
8. WP-CLI sowie nicht betroffene AJAX- und REST-Aufrufe bleiben unverändert.
9. Slug- und Page-ID-Konfiguration funktionieren auf Single-Site und im
   unterstützten Multisite-Kontext.
10. Antworten und Testartefakte enthalten keine Zugangsdaten oder
    personenbezogenen Angriffsdaten.

Nach den Harness-Tests erfolgt auf genau einer freigegebenen Membership-Site
eine Browser-Abnahme für Desktop und Mobil. Dabei werden Anmeldung, 2FA,
Abmeldung und Passwort-Reset mit einem synthetischen Konto geprüft. Zusätzlich
wird per HTTP-Nachweis bestätigt, dass der Core-Pfad den eigenen Login-Pfad
nicht verrät. Es werden keine Last- oder Passwortangriffe gegen die
Produktivseite ausgeführt.

## Zusammenspiel mit dem Server-Schutz

Der produktive Schutz ist erst vollständig, wenn Traefik sowohl direkte
Anmelde-POSTs auf `/wp-login.php` als auch den konfigurierten Membership-Pfad
begrenzt. Da Traefik den ursprünglichen öffentlichen Pfad sieht, erfasst eine
Regel nur für `/wp-login.php` intern umgeschriebene Membership-Aufrufe nicht.
Diese Serverprüfung gehört in das Hetzner-Administrations-Repository und wird
nicht durch diesen Task umgesetzt.

## Rückweg

Die Änderung muss als kleiner, isolierter Login-Patch rückbaubar bleiben. Vor
einem produktiven Test werden Pluginstand, Einstellungen und veröffentlichte
Login-/Reset-Seiten dokumentiert. Bei einer Störung wird nur dieser Patch auf
den letzten freigegebenen Pluginstand zurückgesetzt; Inhalte, Nutzerkonten und
Passwörter bleiben unverändert.
