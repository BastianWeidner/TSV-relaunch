# TSV Fischach Schwimmen – Variante 2 (Neugestaltung)

Statische Website ohne Framework und ohne CMS. Ein kleines Python-Skript setzt
Kopf, Menü, Fuß und die Daten (Trainingszeiten, Kursliste, Hinweis) in jede Seite ein.
Auf dem Server liegen danach nur fertige HTML-, CSS-, Bild- und Schriftdateien.
Einzige Ausnahme: `api/formular.php` für die Formulare, standardmäßig **ausgeschaltet**.

Stand: 29.10.2026, Entwurf. Nichts davon ist online.

## Ordner

```
build.py                 Baut src/ nach dist/ (nur Python 3 Standardbibliothek)
src/pages/               Eine Datei pro Seite (Inhalt)
src/partials/            Kopf, Menü, Fuß, Welle, Infomail-Formular
src/data/zeiten.json     Alle Trainingszeiten – EINZIGE Stelle zum Ändern
src/data/hinweis.json    Gelber "Aktuell"-Kasten auf der Startseite
src/data/site.json       Link zum Google Sheet (Kursliste)
src/data/kurse-beispiel.csv  Beispiel-Kursliste, solange kein Sheet verknüpft ist
src/assets/              CSS, JS (eine Datei), Bilder (WebP), Schriften (lokal, OFL)
src/static/              .htaccess (HTTPS, Weiterleitungen alter URLs, Sicherheit), robots.txt
api/formular.php         Formular-Backend (Stub, AKTIV = false)
dist/                    Fertige Seite zum Hochladen (wird bei jedem Build neu erzeugt)
DATENSCHUTZ-KONZEPT.md   Was die Formulare speichern, wer was sieht, was vor dem Einschalten fehlt
```

## Bauen und ansehen

```bash
python3 build.py                         # baut dist/ (holt die Kursliste aus dem Google Sheet)
python3 build.py --beispiel              # ohne Internet: Beispiel-Kursliste
cd dist && php -S localhost:8000         # lokal ansehen (oder: python3 -m http.server)
FORMULAR_TESTMODUS=1 php -S localhost:8000   # Formulare testen: Mails landen in der Konsole
python3 build.py --release               # bricht ab, solange gelb gestreifte Platzhalter offen sind
```

Gelb gestreifte Stellen (`class="todo"`) sind Platzhalter, die vor dem Livegang ausgefüllt
werden müssen. Der Build zählt sie.

## Häufige Änderungen

- **Trainingszeit ändern:** `src/data/zeiten.json` bearbeiten, `python3 build.py`, hochladen.
  Startseite, Trainingsseite und Erwachsenenseite passen sich automatisch an.
- **Hinweis "Aktuell":** `src/data/hinweis.json`. Mit `"titel": ""` verschwindet der Kasten.
- **Kursliste:** im Google Sheet pflegen, dann `python3 build.py` (siehe unten).
- **Preise:** `src/pages/preise.html` und die Produktkarten auf `index.html`.

## Kursliste aus Google Sheets

Die Kursleitung pflegt eine Tabelle, die Website zeigt den Stand. Besucher laden dabei
**nichts** von Google: Das Sheet wird beim Bauen abgerufen und als fertiges HTML eingebaut.

1. Im Sheet ein eigenes Tabellenblatt **"Website"** anlegen, nur mit diesen Spalten:
   `kurs, termin, beginn, plaetze_frei, status, hinweis`
   - `status`: `offen`, `warteliste`, `voll` oder `ausgesetzt`
   - Teilnehmerlisten, Namen, Mails bleiben in anderen Blättern und werden **nicht** veröffentlicht.
2. Datei → Freigeben → Im Web veröffentlichen → nur Blatt "Website" → Format CSV → Veröffentlichen.
3. Den Link in `src/data/site.json` bei `kurse_csv_url` eintragen.
4. `python3 build.py` → die Kursliste ist eingebaut.

Schutz: `build.py` übernimmt nur die sechs erlaubten Spalten und warnt laut, wenn das Blatt
Spalten wie "Name", "E-Mail" oder "Geburtsdatum" enthält.

Wenn die Liste sich ohne neuen Upload aktualisieren soll, geht das später mit einem
GitHub-Action-Zeitplan (baut z. B. täglich und lädt per SFTP hoch). Ein Abruf direkt im
Browser der Besucher ist absichtlich nicht eingebaut, weil dann jede IP-Adresse an Google ginge.

## Hochladen zu Strato

Inhalt von `dist/` per SFTP in das Webverzeichnis laden (inklusive `.htaccess`).
Die `.htaccess` leitet alle alten Joomla-Adressen auf die neuen Seiten um.

## Formulare

Alle vier Formulare (Warteliste, Erwachsenenkurse, Kontakt, Infomail) sind fertig gestaltet
und prüfen die Eingaben im Browser. Abgeschickt wird an `api/formular.php`; das Skript ist
aus (`AKTIV = false`) und antwortet "noch nicht freigeschaltet". In der Vorschau steht dann
"Es wurde nichts gesendet". Einschalten erst nach den Schritten in `DATENSCHUTZ-KONZEPT.md`.

## Bilder

Verwendet: Logo (freigestellt aus Bastians Datei), Hallenbad, Hallenbad im Winter, Freibad
(2×), Sprung, Badekappen, Badekappen-Entwurf, Schlüsselanhänger.
Das Kinderfoto mit Schwimmnudel zeigt Bastian als Kind (Einwilligung liegt vor, 29.10.2026) und steht auf der Schwimmkurs-Seite.
Der springende Schwimmer ist Bastian selbst, die Einwilligung liegt vor (29.10.2026).
Eine Vektordatei des Logos gibt es nicht; die freigestellte Bilddatei wird verwendet.

## Schriften

Barlow Condensed (Überschriften) und Nunito (Text), beide SIL Open Font License,
lokal eingebunden (keine Google-Fonts-Verbindung).

## Formulare zum Herunterladen (Beitrittserklärung, Datenschutz-Einwilligung)

Die Felder stehen auf der Preisseite (Abschnitt „Formulare“), die Trainingsseite verlinkt dorthin.
PDF nach `src/static/downloads/` legen (`beitrittserklaerung.pdf`, `dsgvo-einwilligung.pdf`), bauen, hochladen.
Solange eine Datei fehlt, zeigt die Seite „PDF folgt in Kürze“.

Empfehlung: die PDFs auf Strato ablegen statt auf Google Drive. Dann bleiben die Links stabil (auch wenn
jemand den Drive umräumt oder das Konto wechselt), es hängt kein privates Konto dran, und Besucher landen
nicht bei Google. Ein Drive-Link geht trotzdem: in `src/data/site.json` bei `datei` den vollständigen Link eintragen
(Freigabe „Jeder mit dem Link: Betrachter“).
