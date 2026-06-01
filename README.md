# shift-x

Minimales MVP für Schichttausch (React + Tailwind Frontend, PHP + MariaDB Backend).

## Setup

1. Datenbank erstellen und Schema importieren:
   ```bash
   mysql -u <user> -p <db> < /tmp/workspace/AndyNope/shift-x/backend/schema.sql
   ```
2. Umgebungsvariablen setzen (optional):
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `MAIL_TO` (standardmäßig `private@andynope.com` für Tests)
   - `MAIL_FROM`
3. App starten:
   ```bash
   cd /tmp/workspace/AndyNope/shift-x/backend/public
   php -S 127.0.0.1:8080
   ```
4. Browser öffnen: `http://127.0.0.1:8080/index.html`

## Enthaltene Funktionen

- Registrierung: Benutzername, E-Mail, Passwort, Swissport-Mitarbeiternummer, SWP-Skill
- Login/Logout per Session
- Schichtabgabe in einen Pool (Deadline bis Vortag 08:00)
- Schichtübernahme aus dem Pool mit Skill-Prüfung (SWP Skill-Hierarchie, DNATA mind. Förderband)
- Versand einer Test-Mail bei Übernahme (`MAIL_TO`, CC an übernehmenden Mitarbeiter)
- Day-List Textimport (aus PDF kopierter Text) mit Erkennung von Datum, Zeit, Anbieter und Skill

## Hinweise

- Die im Day-List Text enthaltene `Pers.-Nr.` wird **nicht** als Swissport-Mitarbeiternummer übernommen.
- Mailversand nutzt PHP `mail()` (abhängig von Server-Konfiguration).
