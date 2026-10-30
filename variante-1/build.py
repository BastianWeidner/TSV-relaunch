#!/usr/bin/env python3
"""Baut die Website der Abteilung Schwimmen aus src/ nach dist/ (nur Standardbibliothek).

Nutzung:
    python3 build.py              # normaler Build für den Server
    python3 build.py --vorschau   # nach vorschau/, Links zeigen auf .../index.html (Öffnen ohne Webserver)

Jede Seite in src/seiten/ ist ein HTML-Baustein. Die erste Zeile ist ein Kommentar mit
JSON-Angaben, z. B.:
    <!-- {"titel": "Gruppe I", "bereich": "gruppen", "titelbild": "hallenbad.jpg"} -->

Im Baustein werden diese Platzhalter ersetzt:
    {{root}}               relativer Pfad zur Startseite, z. B. "../../"
    {{zeiten}}             komplette Wochentabelle aus src/daten/zeiten.json
    {{zeiten:SCHLUESSEL}}  Trainingszeiten eines Angebots, z. B. {{zeiten:gruppe-i}}
    {{baustein:NAME}}      Inhalt von src/bausteine/NAME.html
"""
import html, json, re, shutil, sys
from pathlib import Path

BASIS = Path(__file__).resolve().parent
SRC = BASIS / "src"
VORSCHAU = "--vorschau" in sys.argv
DIST = BASIS / ("vorschau" if VORSCHAU else "dist")

# Hauptmenü wie auf der alten Seite: (Titel, Pfad, Bereich, Untermenü)
MENUE = [
    ("Home", "", "home", []),
    ("Schwimmkurse", "schwimmkurse/", "schwimmkurse", [
        ("Robby-Kurs", "schwimmkurse/robby-kurs/"),
        ("Seepferdchenkurse", "schwimmkurse/seepferdchenkurse/"),
    ]),
    ("Gruppen", "gruppen/", "gruppen", [
        ("Gruppe I", "gruppen/gruppe-i/"),
        ("Gruppe II", "gruppen/gruppe-ii/"),
        ("Gruppe III", "gruppen/gruppe-iii/"),
        ("Gruppe IV", "gruppen/gruppe-iv/"),
        ("A/B-Kader", "gruppen/a-b-kader/"),
    ]),
    ("Wassergymnastik", "wassergymnastik/", "wassergymnastik", [
        ("Erwachsenenschwimmen", "wassergymnastik/erwachsenenschwimmen/"),
    ]),
    ("Kosten", "alle-kosten-auf-einem-blick/", "kosten", []),
]

WOCHENTAGE = ["Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag", "Sonntag"]


def lade_zeiten():
    zeiten = json.loads((SRC / "daten" / "zeiten.json").read_text(encoding="utf-8"))
    return sorted(zeiten, key=lambda z: (WOCHENTAGE.index(z["tag"]), z["von"]))


def zeit_text(z):
    return f'{z["von"]}–{z["bis"]}<span class="uhr"> Uhr</span>'


def wochentabelle(zeiten, root):
    zeilen = []
    for tag in WOCHENTAGE:
        eintraege = [z for z in zeiten if z["tag"] == tag]
        if not eintraege:
            zeilen.append(f'<tr class="frei"><th scope="row">{tag}</th><td colspan="2">–</td></tr>')
            continue
        for i, z in enumerate(eintraege):
            kopf = f'<th scope="row" rowspan="{len(eintraege)}">{tag}</th>' if i == 0 else ""
            name = html.escape(z["angebot"])
            if z.get("seite"):
                name = f'<a href="{root}{z["seite"]}">{name}</a>'
            hinweis = f' <span class="hinweis">({html.escape(z["hinweis"])})</span>' if z.get("hinweis") else ""
            zeilen.append(f"<tr>{kopf}<td>{name}{hinweis}</td><td class=\"zeit\">{zeit_text(z)}</td></tr>")
    return ('<div class="tabelle-rahmen"><table class="zeiten">'
            "<thead><tr><th scope=\"col\">Wochentag</th><th scope=\"col\">Gruppe</th><th scope=\"col\">Zeit</th></tr></thead>"
            f"<tbody>{''.join(zeilen)}</tbody></table></div>")


def angebotszeiten(zeiten, schluessel):
    eintraege = [z for z in zeiten if z["schluessel"] == schluessel]
    if not eintraege:
        sys.exit(f"Fehler: keine Trainingszeiten für '{schluessel}' in zeiten.json")
    punkte = "".join(
        f'<li><span class="tag">{z["tag"]}</span> {zeit_text(z)}'
        + (f' <span class="hinweis">({html.escape(z["hinweis"])})</span>' if z.get("hinweis") else "")
        + "</li>"
        for z in eintraege)
    return f'<ul class="terminliste">{punkte}</ul>'


def menue_html(bereich, root):
    teile = []
    for titel, pfad, b, unter in MENUE:
        aktiv = ' class="aktiv" aria-current="page"' if b == bereich else ""
        link = f'<a href="{root}{pfad}"{aktiv}>{titel}</a>'
        if unter:
            punkte = "".join(f'<li><a href="{root}{p}">{t}</a></li>' for t, p in unter)
            teile.append(f'<li class="hat-unter">{link}<ul>{punkte}</ul></li>')
        else:
            teile.append(f"<li>{link}</li>")
    return "".join(teile)


def baue_seite(datei, vorlage, zeiten):
    text = datei.read_text(encoding="utf-8")
    m = re.match(r"\s*<!--\s*(\{.*?\})\s*-->\s*", text, re.S)
    if not m:
        sys.exit(f"Fehler: {datei} beginnt nicht mit einem JSON-Kommentar")
    meta = json.loads(m.group(1))
    inhalt = text[m.end():]

    rel = datei.relative_to(SRC / "seiten")
    tiefe = len(rel.parts) - 1
    root = meta.get("root") or "../" * tiefe or "./"

    while "{{baustein:" in inhalt:  # Bausteine dürfen selbst Bausteine enthalten
        inhalt = re.sub(r"\{\{baustein:([\w-]+)\}\}",
                        lambda m: (SRC / "bausteine" / f"{m.group(1)}.html").read_text(encoding="utf-8"), inhalt)
    inhalt = inhalt.replace("{{zeiten}}", wochentabelle(zeiten, root))
    inhalt = re.sub(r"\{\{zeiten:([\w-]+)\}\}", lambda m: angebotszeiten(zeiten, m.group(1)), inhalt)

    titelbild = ""
    if meta.get("titelbild"):
        titelbild = (f'<div class="titelbild"><img src="{{{{root}}}}assets/img/{meta["titelbild"]}" '
                     f'alt="{html.escape(meta.get("bildtext", ""))}" width="1920" height="1080"></div>')

    titel = meta["titel"]
    seitentitel = "TSV Fischach – Abteilung Schwimmen" if titel == "Home" else f"{titel} – TSV Fischach Schwimmen"
    seite = (vorlage
             .replace("{{seitentitel}}", html.escape(seitentitel))
             .replace("{{beschreibung}}", html.escape(meta.get("beschreibung", "")))
             .replace("{{koerperklasse}}", meta.get("bereich", ""))
             .replace("{{menue}}", menue_html(meta.get("bereich"), root))
             .replace("{{titelbild}}", titelbild)
             .replace("{{inhalt}}", inhalt)
             .replace("{{root}}", root))

    if VORSCHAU:
        # ohne Webserver gibt es keine Verzeichnis-Indexseiten, also index.html anhängen
        seite = re.sub(r'href="(\.\.?/[^"#:]*/)(#[^"]*)?"', r'href="\1index.html\2"', seite)

    ziel = DIST / rel
    ziel.parent.mkdir(parents=True, exist_ok=True)
    ziel.write_text(seite, encoding="utf-8")

    offen = len(re.findall(r'class="platzhalter"', seite))
    return rel, offen


def main():
    DIST.mkdir(exist_ok=True)
    for alt in DIST.iterdir():  # Inhalt leeren, Ordner selbst behalten
        shutil.rmtree(alt) if alt.is_dir() else alt.unlink()
    shutil.copytree(SRC / "assets", DIST / "assets")
    for f in (SRC / "statisch").iterdir():
        shutil.copy(f, DIST / f.name)

    zeiten = lade_zeiten()
    vorlage = (SRC / "vorlage.html").read_text(encoding="utf-8")
    gesamt = 0
    for datei in sorted((SRC / "seiten").rglob("*.html")):
        rel, offen = baue_seite(datei, vorlage, zeiten)
        gesamt += offen
        print(f"  {rel}" + (f"  ({offen} Platzhalter offen)" if offen else ""))
    print(f"Fertig: {DIST}" + (f" – noch {gesamt} Platzhalter zu füllen" if gesamt else ""))


if __name__ == "__main__":
    main()
