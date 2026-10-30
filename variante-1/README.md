# TSV Fischach Schwimmen – Website, Variante 1 (nah am Original)

Statische Seite aus HTML und CSS, ohne Framework, ohne CMS, ohne JavaScript, ohne Cookies.
Struktur, Menü und Inhalte folgen der alten Joomla-Seite; Trainingszeiten aus der Tabelle der alten Startseite.

## Ordner

| Ordner / Datei | Inhalt |
| --- | --- |
| `src/seiten/` | Eine Datei pro Seite. Ordnerstruktur = spätere Adresse (`gruppen/gruppe-i/index.html` → `/gruppen/gruppe-i/`) |
| `src/daten/zeiten.json` | **Alle Trainingszeiten an einer Stelle.** Startseite und Unterseiten werden daraus erzeugt |
| `src/bausteine/` | Wiederkehrende Blöcke (Anmeldung Gruppen, Anmeldung Erwachsene) |
| `src/vorlage.html` | Kopf mit Menü und Fußzeile für alle Seiten |
| `src/assets/` | CSS, Schrift (Montserrat, lokal, OFL-Lizenz), Bilder |
| `src/statisch/` | `.htaccess` (HTTPS, Weiterleitungen alter Adressen) und `robots.txt` |
| `build.py` | Baut alles nach `dist/` (nur Python 3, keine Pakete nötig) |
| `dist/` | **Das kommt auf den Server** |
| `vorschau/` | Gleiche Seite, Links auf `index.html`, zum Doppelklicken ohne Webserver |

## Bauen

```
python3 build.py              # erzeugt dist/
python3 build.py --vorschau   # erzeugt vorschau/
```

Das Skript zählt die noch offenen Platzhalter je Seite (gelb markiert, Klasse `platzhalter`).
Vor dem Livegang sollte dort 0 stehen.

## Häufige Änderungen

- **Trainingszeit ändern:** nur `src/daten/zeiten.json` bearbeiten, dann bauen.
- **Ausblick / Saisonstart / Warteliste:** `src/seiten/index.html`, Kasten „Unser Ausblick“.
- **Seepferdchen-Kurszeiten:** stehen direkt in `src/seiten/schwimmkurse/seepferdchenkurse/index.html`.
- **Preise:** `src/seiten/alle-kosten-auf-einem-blick/index.html` (Kurspreise stehen zusätzlich auf der Seepferdchen-Seite).
- **Neue Seite:** Datei in `src/seiten/...` anlegen; erste Zeile ist ein Kommentar mit JSON (`titel`, `bereich`, `titelbild`, `beschreibung`). Für das Menü `MENUE` in `build.py` ergänzen.

## Hochladen zu Strato

Inhalt von `dist/` per SFTP in das Webverzeichnis der Domain laden, **inklusive der versteckten Datei `.htaccess`**.
PHP und Datenbank werden nicht gebraucht.

## Alte Adressen

`/schwimmkurse/robby-kurs`, `/gruppen/gruppe-i` usw. bleiben gleich (Apache hängt den Schrägstrich an).
`/alle-kosten-auf-einem-blick` bleibt gleich. `/index.php/impressum`, `/index.php/datenschutz`,
`/index.php/haftungsausschluss` und alte Blog-Adressen leiten per `.htaccess` weiter.

## Bilder

Alle Fotos sind verkleinert und ohne Metadaten (kein GPS). Startsprung- und Kinderfoto (mit Nudel) zeigen Bastian, beide freigegeben. Das Logo ist der Tintenfisch mit Fischacher Wappen, freigestellt aus dem
hellgrauen Bild; eine Vektor-Datei (SVG/PDF) wäre schärfer.
