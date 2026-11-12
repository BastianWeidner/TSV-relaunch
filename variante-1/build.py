#!/usr/bin/env python3
"""Baut die Website der Abteilung Schwimmen aus src/ nach dist/ (nur Standardbibliothek).

Nutzung:
    python3 build.py              # normaler Build für den Server
    python3 build.py --vorschau   # nach vorschau/, Links zeigen auf .../index.html (Öffnen ohne Webserver)
    python3 build.py --pages      # Vorschau für GitHub Pages: Banner, noindex, alles relativ, ohne .htaccess

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
PAGES = "--pages" in sys.argv  # temporäre Vorschau unter einem Unterpfad (GitHub Pages)
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

PAGES_BANNER = (
    '<div role="note" style="background:#fff4c2;color:#3a3000;border-bottom:1px solid #c9a400;'
    'padding:.5rem 1rem;font:500 .9rem/1.4 Montserrat,system-ui,sans-serif;text-align:center">'
    '<strong>Vorschau</strong> – Entwurf „Variante 1“, noch nicht die offizielle Website. '
    'Gelb markierte Stellen werden vor dem Livegang gefüllt. '
    '<a href="{{root}}../" style="color:inherit">Zur Auswahl der Varianten</a></div>')

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
    if PAGES:  # unter /variante-1/ gibt es kein absolutes "/" der Website
        root = "../" * tiefe or "./"

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

    if PAGES:
        seite = seite.replace("</title>", '</title>\n<meta name="robots" content="noindex, nofollow">', 1)
        seite = re.sub(r"(<body[^>]*>)", lambda m: m.group(1) + "\n" + PAGES_BANNER.replace("{{root}}", root), seite, count=1)

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
        if PAGES and f.name == ".htaccess":
            continue  # GitHub Pages kennt keine .htaccess
        shutil.copy(f, DIST / f.name)
    if PAGES:
        (DIST / "robots.txt").write_text("User-agent: *\nDisallow: /\n", encoding="utf-8")

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
