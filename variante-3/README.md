# TSV Fischach Schwimmen – Variante 3 (neu gedacht)

Dritter Entwurf, Stand 15.11.2026. Nichts davon ist online. Technisch wie Variante 2
(statisches HTML, ein Python-Skript, kein Framework, kein CMS), aber mit neuer Struktur
und deutlich mehr Gestaltung und Bewegung.

## Was anders ist als in Variante 1 und 2

**Struktur nach Besuchern statt nach Vereinsgliederung.** Sieben Hauptseiten statt zehn:

| Seite | Inhalt | ersetzt in Variante 2 |
| --- | --- | --- |
| `index.html` | Wegweiser („Wer möchte ins Wasser?“), Aktuell, der Weg vom Robby-Kurs bis zum Kader, Wochenplan, Werte, Bad, Infomail | Startseite |
| `schwimmen-lernen.html` | Seepferdchen, Aufbaukurs, Robby, Kursliste, Ablauf der Warteliste, häufige Fragen | Schwimmkurse |
| `training.html` | Gruppen I–IV und Kader als Stufen mit Zeiten, Schnuppertraining | Training |
| `erwachsene.html` | Wassergymnastik, Erwachsenenschwimmen | Erwachsene |
| `zeiten-preise.html` | Wochenplan, alle Preise, Mitgliedschaft, Formulare zum Herunterladen | Preise + Zeiten der Startseite |
| `verein.html` | Tintenfisch, Team, Bad und Anfahrt, Vereinskleidung | Verein + Vereinskleidung + Anfahrt |
| `anmelden.html` | Alle vier Formulare als Reiter (Warteliste, Erwachsene, Nachricht, Infomail) und die Mailadressen | Warteliste + Kontakt + Infomail |

Dazu Impressum, Datenschutz, 404 und Danke. Inhaltlich ist nichts weggefallen; neu ist nur die
Rolle „Ehrenvorsitz“ im Team (stand auf der alten Seite) und der Abschnitt „Häufige Fragen“,
der vorhandene Angaben bündelt.

**Praktische Extras**

- Wegweiser auf der Startseite: drei Fragen, jeweils mit Angebot, Preis, Ablauf und Knopf zur Anmeldung.
- Wochenplan mit Filter (Schwimmkurse, Training, Erwachsene), Markierung „Heute“ und der Zeile
  „Laut Plan gerade im Wasser / als Nächstes“. Die Uhrzeit wird im Browser berechnet (Zeitzone
  Europe/Berlin), Ferien kennt der Plan nicht.
- Sprungmarken in die Formulare: `anmelden.html#warteliste`, `#erwachsene`, `#nachricht`, `#infomail`;
  `#nachricht-schnuppern` (oder `-kleidung`, `-abteilungsleitung`, `-erwachsene`) wählt gleich das Thema vor.
- Mailadressen mit „Adresse kopieren“, Fortschrittsbalken im Wartelisten-Formular.
- Heller und dunkler Modus, Umschalter im Fuß.

**Bewegung** (alles in `src/assets/css/style.css` Abschnitt 12 und `src/assets/js/main.js`):
Wellen, aufsteigende Blasen und Lichtstrahlen im Kopfbereich, die treibende Krake (winkt beim
Überfahren), Einblenden beim Scrollen, hochzählende Zahlen, die Bahnleine oben als Scroll-Fortschritt,
die Leine der Stufen, die sich füllt, Spritzer auf Knöpfen, weiche Seitenwechsel. Wer im Gerät
„Bewegung reduzieren“ eingestellt hat, bekommt eine ruhige Seite. Ohne JavaScript ist alles sichtbar
und bedienbar: Reiter werden zu Abschnitten untereinander, der Wochenplan zur fertigen Übersicht.

## Ordner

```
build.py                 Baut src/ nach dist/ (nur Python 3 Standardbibliothek)
src/pages/               Eine Datei pro Seite (Inhalt)
src/partials/            Kopf, Menü, Fuß, Wellen, Blasen, Infomail-Formular
src/data/zeiten.json     Alle Trainingszeiten – EINZIGE Stelle zum Ändern
src/data/hinweis.json    Kasten „Aktuell“ auf der Startseite
src/data/site.json       Link zum Google Sheet (Kursliste), Downloads
src/data/kurse-beispiel.csv  Beispiel-Kursliste, solange kein Sheet erreichbar ist
src/data/zeiten-app.json  (optional) Trainingszeiten aus der Schwimmgruppen-App
src/data/kurse-app.csv   (optional) Kursliste aus der Schwimmkurs-App
src/assets/              CSS, JS (eine Datei), Bilder (WebP), Schriften (lokal, OFL)
src/static/              .htaccess (HTTPS, Weiterleitungen alter URLs, Sicherheit), robots.txt, downloads/
api/formular.php         Formular-Backend (AKTIV = false), api/.htaccess sperrt alles andere
dist/                    Fertige Seite zum Hochladen (wird bei jedem Build neu erzeugt)
DATENSCHUTZ-KONZEPT.md   Was die Formulare speichern, Spam-Schutz, Checkliste vor dem Einschalten
```

## Bauen und ansehen

```bash
python3 build.py                         # baut dist/ (holt die Kursliste aus dem Google Sheet)
python3 build.py --beispiel              # ohne Internet: Beispiel-Kursliste
cd dist && php -S localhost:8000         # lokal ansehen (oder: python3 -m http.server)
FORMULAR_TESTMODUS=1 php -S localhost:8000   # Formulare testen: Mails landen in der Konsole
python3 build.py --release               # bricht ab, solange gelb gestreifte Platzhalter offen sind
python3 build.py --pages                 # Vorschau für GitHub Pages (Banner, noindex, Formulare aus)
```

## Häufige Änderungen

- **Trainingszeit ändern:** `src/data/zeiten.json`, dann bauen. Startseite, Wochenplan, Trainings- und
  Erwachsenenseite passen sich an. `"kat"` (kurse, training, erwachsene) bestimmt Farbe und Filter.
- **Kasten „Aktuell“:** `src/data/hinweis.json`. Mit `"titel": ""` verschwindet er.
- **Kursliste:** Google Sheet oder `kurse-app.csv`, genau wie in Variante 2 (siehe dortige README,
  Abschnitte „Daten aus den Schwimm-Apps“ und „Kursliste aus Google Sheets“). Die Spaltenprüfung ist dieselbe.
- **Preise:** `src/pages/zeiten-preise.html`, dazu die Preisangaben im Wegweiser (`index.html`)
  und auf `schwimmen-lernen.html`.
- **Formulare zum Herunterladen:** PDFs liegen in `src/static/downloads/` (aus Variante 1 übernommen),
  eingetragen in `src/data/site.json`.

## Formulare und Sicherheit

`api/formular.php` ist der Stand **nach** dem Sicherheits-Audit vom 14.11.2026 (in Variante 2 als
Pull Request #3 offen): feste Auswahllisten, feste Texte für Mails an eingetragene Adressen,
Mengenbegrenzung je Besucher, je Adresse und insgesamt, Zeitfalle, Honeypot, Infomail-Bestätigung
per Knopf. So taugt das Formular nicht als Spam-Schleuder. Details und die Checkliste vor dem
Einschalten stehen in `DATENSCHUTZ-KONZEPT.md`.

- Das Skript ist aus (`AKTIV = false`). In der Vorschau steht dann „Es wurde nichts gesendet“.
- Das Geheimnis liegt in `api/config.php` (nicht im Git, Vorlage `config.beispiel.php`).
- Die Zähler liegen in `api/daten/` (nicht im Git, wird auf dem Server automatisch angelegt).
- `.htaccess`: HTTPS ohne www, HSTS (erst kurz), CSP ohne `unsafe-inline` für Skripte (die Hashes
  trägt `build.py` ein), Notizen und Sicherungsdateien gesperrt, alte Joomla-Adressen leiten auf
  die neuen Seiten um.

Geprüft am 15.11.2026 mit `FORMULAR_TESTMODUS=1`: Kontakt und Warteliste kommen an, die dritte
Infomail-Anfrage ohne JavaScript bekommt 429, an dieselbe Adresse gehen höchstens zwei Mails,
ein Thema außerhalb der Liste wird abgelehnt.

## Schriften und Bilder

Bricolage Grotesque (Überschriften) und Figtree (Text), beide SIL Open Font License, lokal
eingebunden (keine Google-Fonts-Verbindung). Bilder wie in Variante 2; Einwilligungen siehe dortige README.

## Offen vor dem Livegang

Wie bei Variante 2: Strato-AV-Vertrag, Datenschutzerklärung vom Vorstand gegenlesen lassen
(zwei gelb markierte Stellen), Teamnamen nur mit Einwilligung, HSTS nach dem Test hochsetzen.
