# Rollout-Runbook: Passwort-Reset

Dieses Runbook beschreibt die Schritte außerhalb des Plugin-Repositories. Es
enthält bewusst keine Zugangsdaten, echten Nutzerkonten oder Reset-Schlüssel.

## 1. WordPress-Inhalt vorbereiten

1. Eine veröffentlichte, nicht gecachte Seite ausschließlich für den
   Passwort-Reset anlegen.
2. `[wg_password_reset_form]` als eigenständigen Shortcode-Block oder in einem
   normalen inline sichtbaren Abschnitt einfügen. Den Shortcode nicht in einen
   Absatzblock einschließen.
3. Die Page-ID unter **Einstellungen > Membership Suite > Passwort-Reset-Seite
   (Page-ID)** als `password_reset_page_id` speichern.
4. Den Link „Passwort zurücksetzen“ auf `/login-secure/` als normalen Link auf
   diese Seite setzen.

Wird das bestehende Overlay vorübergehend beibehalten, muss der Trigger
mindestens `data-gb-overlay="gb-overlay-200189"` tragen. Die separate sichtbare
Seite bleibt die bevorzugte Lösung, weil Rückmeldungen nach Redirect dann
nicht von einem geschlossenen Overlay verdeckt werden.

## 2. Mailtransport konfigurieren

1. Einen authentifizierten SMTP-Transport verwenden; PHP `mail()` ist kein
   produktiver Zustellnachweis.
2. Auf einer Membership-Suite-Site unter **Einstellungen > Membership Suite >
   E-Mail-Versand (SMTP)** Host, Port, STARTTLS, Benutzer, Absenderadresse und
   Absendername festlegen.
3. Das SMTP-Passwort wird vom zuständigen Site-Administrator einmalig in diesem
   Formular eingegeben. Es wird in einer getrennten, sitegebunden
   verschlüsselten Secret-Option gespeichert, im Formular nie wieder
   ausgegeben und weder in Logs noch in Exporte übernommen. Eine spätere
   Eingabe ersetzt das bisherige Secret kontrolliert.
4. Das Passwort niemals per E-Mail, Chat, Änderungswunsch oder Task
   übermitteln. Wenn ein hostseitiger Secret-Manager verfügbar ist, bleibt er
   gegenüber der Adminerfassung der bevorzugte Ablageort.
5. **Speichern und Testmail senden** sendet genau eine Nachricht an das
   angemeldete Administratorkonto. Ein Erfolg wird nur als
   `accepted_by_smtp` gespeichert, wenn PHPMailer tatsächlich im
   authentifizierten SMTP-Modus lief.
6. Absenderdomain und From-Adresse festlegen und SPF, DKIM sowie DMARC prüfen.
7. Der Empfänger bestätigt den Eingang der Testnachricht. Erst danach werden
   Bestell-, 2FA- oder Passwort-Reset-Mails produktiv freigegeben.
8. Das WordPress-/PHP-Fehlerlog auf `event: wp_mail_failed` prüfen. Der Eintrag
   darf nur Zeitpunkt, Site-ID, Fehlercodes und Kontext enthalten.

## 3. Plugin ausrollen

1. Vor Änderung des WordPress-Inhalts und Plugin-Rollout ein Backup erstellen.
2. Die neue Plugin-Version zunächst in einer Staging-Umgebung aktivieren.
3. Lokal oder im Build ausführen:

   ```sh
   php tests/password-reset-harness.php
   ```

4. Erst nach erfolgreicher Staging-Abnahme nach Produktion ausrollen.

## 4. Produktive Abnahme

Für Desktop und echtes Mobile jeweils prüfen:

- Reset-Seite ist inline sichtbar und ohne Overlay-Zwischenschritt erreichbar.
- Ungültiger oder abgelaufener Link zeigt einen verständlichen Alert.
- Fehlende oder abgelaufene Nonce zeigt einen verständlichen Alert.
- Eine Anfrage mit dem synthetischen Konto liefert genau eine Mail.
- Die Mail enthält die konfigurierte Membership-Suite-Seite mit `action=rp`,
  `key` und `login`, aber keinen `/wp-login.php?action=rp`-Link.
- Der Link zeigt die beiden Passwortfelder; das Passwort lässt sich setzen.
- Anschließende Anmeldung mit dem neuen Passwort funktioniert.
- Reload nach Mailanfrage oder Passwortänderung wiederholt keinen POST.
- Normales DNS und vertrauenswürdiges TLS funktionieren ohne Warnung.
- Screenshots, Browserlogs und andere QA-Artefakte enthalten keine Schlüssel,
  Cookies, Passwörter, Mailinhalte oder echten Mailadressen.

Bei einem Fehler den Rollout stoppen. Plugin-/Content-Version aus dem Backup
zurückrollen; SMTP-Secrets nicht in Tickets oder Logs kopieren.
