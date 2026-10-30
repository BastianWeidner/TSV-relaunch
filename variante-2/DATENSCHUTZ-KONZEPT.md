# Datenschutz-Konzept Variante 2

Entwurf vom 29.10.2026. Kein Rechtsrat; bitte vom Vorstand (ggf. Datenschutzbeauftragte/r
des Hauptvereins) prüfen lassen, bevor ein Formular eingeschaltet wird.

## Grundsatz

- Keine Cookies, kein Tracking, keine eingebetteten Fremddienste (Karten, Videos, Fonts).
  Deshalb braucht die Seite **keinen Cookie-Banner**.
- Formulare speichern **nichts auf dem Server**. `api/formular.php` verschickt nur eine Mail
  an die Funktionsadresse. Gespeichert wird im Postfach, das die Abteilung ohnehin hat.
- Keine Mitgliederdaten auf der Website. Team nur mit Rollen; Namen und Fotos nur mit
  schriftlicher Einwilligung.

## Verarbeitungen im Überblick

| Formular | Daten | Zweck | Rechtsgrundlage | Empfänger | Löschen |
| --- | --- | --- | --- | --- | --- |
| Warteliste Schwimmkurs | Kind: Name, Geburtsdatum, Wohnort (PLZ, Ort), Einschulungsjahr, Kurs. Eltern: Name, E-Mail, Telefon (freiwillig), Hinweis (freiwillig) | Platzvergabe nach Eingang, Vorrang Einzugsgebiet | Art. 6 (1) b + a DSGVO | schwimmkurs@ (Kursleitung) | nach Kurs bzw. Abmeldung, spätestens 24 Monate |
| Wassergymnastik / Erwachsenenschwimmen | Name, E-Mail, Telefon (freiw.), Niveau, Nachricht | Platzvergabe | Art. 6 (1) b | bleib.fit@ (Trainer) | wenn erledigt, spätestens 12 Monate |
| Kontakt | Thema, Name, E-Mail, Nachricht | Beantwortung | Art. 6 (1) f | kontakt@ / schnuppern@ | wenn erledigt |
| Infomail | E-Mail, Themen, Zeitpunkte Anmeldung/Bestätigung | Information über Kurse | Art. 6 (1) a (Einwilligung, Double-Opt-in) | kontakt@ | bei Abmeldung |
| Server-Logs | IP, Zeit, URL, Browser | Betrieb, Sicherheit | Art. 6 (1) f | Strato | laut Strato (prüfen) |

## Kinderdaten (Warteliste)

- Datensparsam: Wohnort nur als PLZ und Ort (für den Vorrang des Einzugsgebiets, stichprobenartig prüfbar), keine Straße, keine Gesundheitsdaten (Hinweis am Freitextfeld), kein Foto.
- Pflicht-Häkchen: erziehungsberechtigt + Einverständnis, mit Löschfrist im Text.
- Zugriff nur Kursleitung. Das Postfach schwimmkurs@ sollte nur diese Personen haben,
  mit eigenem Passwort, nicht weitergeleitet an private Freemail-Adressen.
- Wo wird die Warteliste geführt? Empfehlung: in einer Tabelle, die **nur** die Kursleitung
  sieht (z. B. ein eigenes, nicht veröffentlichtes Blatt). Die öffentliche Kursliste der
  Website ist ein **getrenntes Blatt ohne Personendaten** (siehe README).
- Einmal im Jahr aufräumen: Einträge älter als 24 Monate löschen.

## Infomail mit Double-Opt-in, ohne Datenbank

1. Formular → Server verschickt Bestätigungslink an die angegebene Adresse.
   Der Link enthält eine signierte Prüfsumme (HMAC), 3 Tage gültig. Nichts wird gespeichert.
2. Klick → Server schickt eine Mail "bitte aufnehmen" an kontakt@. **Diese Mail ist der
   Nachweis der Einwilligung**, bitte aufbewahren.
3. Versand der Infomail: als BCC aus dem Vereinspostfach, oder später ein
   deutscher/europäischer Newsletter-Dienst mit AV-Vertrag (z. B. CleverReach, Brevo).
   Bei BCC unbedingt auf "BCC" achten.
4. Abmeldung per Mail genügt; jede Infomail enthält den Hinweis dazu.

## Google Sheet für die Kursliste

- Nur ein eigenes Blatt "Website" mit sechs Spalten veröffentlichen (kurs, termin, beginn,
  plaetze_frei, status, hinweis). Teilnehmer- und Wartelisten nie im selben Blatt.
- Das Sheet wird beim Bauen der Seite abgerufen, nicht im Browser der Besucher. Es fließen
  also keine Besucherdaten zu Google. Das steht so in der Datenschutzerklärung.
- `build.py` verwirft alle anderen Spalten und warnt bei verdächtigen Spaltennamen.

## Vor dem Einschalten der Formulare (Checkliste)

- [ ] AV-Vertrag mit Strato im Kundenmenü abschließen
- [ ] Postfächer schwimmkurs@, schnuppern@, bleib.fit@, leitung@, kontakt@ und website@ (Absender) existieren
- [ ] `api/config.beispiel.php` als `api/config.php` speichern, `GEHEIMNIS` auf lange Zufallszeichenkette setzen (nur auf dem Server, nie ins Git); in `api/formular.php` `AKTIV = true`
- [ ] Datenschutzerklärung (`datenschutz.html`) vom Vorstand freigegeben, Platzhalter gefüllt
- [ ] Je Formular ein Testeintrag, Mail kommt an, Bestätigung kommt an
- [ ] Eintrag ins Verzeichnis von Verarbeitungstätigkeiten des Vereins (Tabelle oben genügt als Grundlage)
- [ ] Festlegen, wer die Warteliste führt und wer jährlich löscht

## Fotos

- Kein Bild von erkennbaren Kindern ohne schriftliche Einwilligung der Eltern.
  (Das Foto mit Schwimmnudel zeigt Bastian selbst als Kind, Einwilligung liegt vor.)
- Lieber Rückenansichten, Gruppen von weitem, Wasser und Bad.
