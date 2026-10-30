# TSV Fischach Schwimmen – Website-Relaunch

Neue Website für die Abteilung Schwimmen des TSV Fischach e.V. (tsv-fischach-schwimmen.de).
Statisches HTML und CSS, kein CMS, kein Framework. Zur Auswahl stehen zwei Entwürfe:

| Ordner | Variante | Kurz gesagt |
| --- | --- | --- |
| [`variante-1/`](variante-1/) | Nah am Original | Struktur, Menü und Adressen wie die alte Joomla-Seite. Kein JavaScript, kein PHP, keine Formulare. |
| [`variante-2/`](variante-2/) | Neugestaltung | Neues Design und Menü, Kursliste aus Google Sheets (beim Bauen), Formulare über `api/formular.php` (standardmäßig aus). |

Beide sind Entwürfe (Stand 30.10.2026), noch nichts davon ist online. Gelb markierte Stellen sind Platzhalter, die vor dem Livegang gefüllt werden müssen. Details stehen in der README des jeweiligen Ordners.

## Bauen

Nötig ist nur Python 3, keine weiteren Pakete.

```bash
cd variante-1
python3 build.py              # erzeugt dist/
python3 build.py --vorschau   # erzeugt vorschau/ (Doppelklick ohne Webserver)

cd variante-2
python3 build.py              # erzeugt dist/, holt die Kursliste aus dem Google Sheet
python3 build.py --beispiel   # ohne Internet, mit Beispiel-Kursliste
python3 build.py --release    # bricht ab, solange Platzhalter offen sind
```

Ansehen: `cd dist && python3 -m http.server` und dann http://localhost:8000 öffnen.

## Hochladen zu Strato

Den **Inhalt** von `dist/` der gewählten Variante per SFTP ins Webverzeichnis laden, inklusive der versteckten Datei `.htaccess`.

`dist/` und `vorschau/` sind absichtlich **nicht** im Git: Sie werden bei jedem Build komplett neu erzeugt, und so gibt es keine veraltete Kopie neben den Quellen. Vor jedem Upload also einmal bauen.

## Geheimnisse und Personendaten

- Ins Repo gehören keine Zugangsdaten. Variante 2 liest das Geheimnis für den Infomail-Bestätigungslink aus `variante-2/api/config.php`. Diese Datei steht in `.gitignore`; die Vorlage ist `api/config.beispiel.php`. Ohne echte `config.php` startet das Formular nicht.
- SFTP-Passwörter und Strato-Zugänge bleiben außerhalb des Repos.
- Keine Mitgliederdaten im Repo: keine Teilnehmer- oder Wartelisten, keine ausgefüllten Formulare. Namen erscheinen nur dort, wo sie auf die Seite gehören (Impressum, Ansprechpersonen) und freigegeben sind.
- Die Kursliste in Variante 2 übernimmt aus dem Google Sheet nur sechs erlaubte Spalten und warnt, wenn das Blatt Namen, Mails oder Geburtsdaten enthält.
