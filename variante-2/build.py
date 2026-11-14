#!/usr/bin/env python3
"""Baut die Website aus src/ nach dist/. Nur Python-Standardbibliothek.

    python3 build.py                 # baut mit den Daten aus src/data/
    python3 build.py --sheet URL     # holt die Kursliste vorher aus dem Google Sheet (CSV)
    python3 build.py --beispiel      # nimmt die Beispiel-Kursliste statt des Google Sheets
    python3 build.py --pages         # temporäre Vorschau für GitHub Pages: Banner, noindex,
                                     # Formulare aus, ohne PHP und .htaccess

Aus den Schwimm-Apps (Knopf „Für die Website“), falls vorhanden:
    src/data/zeiten-app.json   Trainingszeiten aus Schwimmgruppen, ersetzen die Termine dieser Gruppen
    src/data/kurse-app.csv     Kursliste aus Schwimmkurs, hat Vorrang vor dem Google Sheet
    python3 build.py --release       # bricht ab, solange noch Platzhalter (class="todo") drin sind

Seiten liegen in src/pages/. Die erste Zeile jeder Seite ist ein Kommentar mit
JSON: <!-- {"title": "...", "description": "...", "nav": "kurse"} -->
Bausteine: {{> name}} fügt src/partials/name.html ein.
Daten:     {{woche}}, {{zeiten:gruppe-1}}, {{kurse}}, {{kurs-status:Seepferdchenkurs}}, {{hinweis}}, {{downloads}}
"""
import base64
import csv
import datetime as dt
import hashlib
import html
import io
import json
import re
import shutil
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SRC = ROOT / "src"
DIST = ROOT / "dist"
SITE_NAME = "TSV Fischach Schwimmen"
TAGE = ["Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag", "Sonntag"]

# Nur diese Spalten aus dem Sheet kommen auf die Website. Alles andere wird verworfen,
# damit nie versehentlich Namen oder Mailadressen veröffentlicht werden.
KURS_SPALTEN = ["kurs", "termin", "beginn", "plaetze_frei", "status", "hinweis"]
VERDAECHTIG = re.compile(r"name|mail|telefon|handy|geburt|adresse|strasse|straße|eltern|kind", re.I)
STATUS = {
    "offen": "Plätze frei",
    "warteliste": "Warteliste offen",
    "voll": "Ausgebucht",
    "ausgesetzt": "Derzeit ausgesetzt",
}


def load_json(name):
    return json.loads((SRC / "data" / name).read_text(encoding="utf-8"))


def esc(s):
    return html.escape(str(s), quote=True)


# ---------- Kurse (Google Sheet oder Beispiel) ----------

APP_KURSE = "kurse-app.csv"
APP_ZEITEN = "zeiten-app.json"


def read_courses(sheet_url, app_file=None, fallback=False):
    sample = False
    if app_file:
        print(f"Kursliste aus der Schwimmkurs-App: {app_file.name}")
        text = app_file.read_text(encoding="utf-8-sig")
    elif sheet_url:
        print(f"Lade Kursliste: {sheet_url}")
        try:
            with urllib.request.urlopen(sheet_url, timeout=20) as r:
                text = r.read().decode("utf-8-sig")
        except OSError as e:
            if fallback:
                print(f"  WARNUNG: Kursliste nicht erreichbar ({e}), nehme die Beispiel-Kursliste.")
                return read_courses(None)
            sys.exit(f"Kursliste konnte nicht geladen werden ({e}).\n"
                     "Ist die Tabelle für 'Jeder mit dem Link' freigegeben? Ohne Internet: python3 build.py --beispiel")
    else:
        text = (SRC / "data" / "kurse-beispiel.csv").read_text(encoding="utf-8")
        sample = True
    reader = csv.DictReader(io.StringIO(text))
    header = [h.strip().lower() for h in (reader.fieldnames or [])]
    extra = [h for h in header if h not in KURS_SPALTEN]
    if extra:
        print(f"  Hinweis: Spalten ignoriert: {', '.join(extra)}")
    bad = [h for h in extra if VERDAECHTIG.search(h)]
    if bad:
        print(f"  WARNUNG: Das Tabellenblatt enthält vermutlich personenbezogene Spalten ({', '.join(bad)}).\n"
              "  Bitte nur ein eigenes Blatt 'Website' ohne Teilnehmerdaten veröffentlichen!")
    rows = []
    for raw in reader:
        row = {k.strip().lower(): (v or "").strip() for k, v in raw.items() if k}
        row = {k: row.get(k, "") for k in KURS_SPALTEN}
        if not row["kurs"]:
            continue
        row["status"] = row["status"].lower() if row["status"].lower() in STATUS else "warteliste"
        rows.append(row)
    return rows, sample


def render_courses(rows, sample):
    if not rows:
        return ('<p class="callout">Gerade sind keine Kurstermine eingetragen. '
                'Trag dein Kind trotzdem in die Warteliste ein, wir melden uns.</p>')
    out = ['<div class="courses">']
    for r in rows:
        frei = ""
        if r["status"] == "offen" and r["plaetze_frei"] not in ("", "0"):
            frei = f' · {esc(r["plaetze_frei"])} frei'
        hinweis = f'<small>{esc(r["hinweis"])}</small>' if r["hinweis"] else ""
        leer = ("", "-", "–")
        when = " · ".join(x for x in (
            esc(r["termin"]) if r["termin"] not in leer else "",
            f'Beginn: {esc(r["beginn"])}' if r["beginn"] not in leer else "") if x)
        out.append(
            f'<div class="course"><b>{esc(r["kurs"])}</b>'
            f'<span class="when">{when}</span>'
            f'<span class="chip chip-{r["status"]}">{STATUS[r["status"]]}{frei}</span>{hinweis}</div>'
        )
    out.append("</div>")
    if sample:
        out.append('<p class="sample-flag">Beispieldaten. Sobald das Google Sheet verknüpft ist, steht hier der echte Stand.</p>')
    return "\n".join(out)


def course_status(rows, kurs):
    found = [r["status"] for r in rows if r["kurs"].lower() == kurs.lower()]
    for s in ("offen", "warteliste", "voll", "ausgesetzt"):
        if s in found:
            return f'<span class="chip chip-{s}">{STATUS[s]}</span>'
    return ""


# ---------- Trainingszeiten ----------

def merge_app_times(z):
    """Ersetzt die Termine der Gruppen, die in zeiten-app.json stehen. Namen, Seiten und Zusätze
    ("mit Anmeldung") bleiben aus zeiten.json; Gruppen, die die Website nicht kennt, werden übersprungen."""
    f = SRC / "data" / APP_ZEITEN
    if not f.exists():
        return z
    app = json.loads(f.read_text(encoding="utf-8"))
    termine = [t for t in app.get("termine", []) if t.get("tag") in TAGE]
    unbekannt = sorted({t["gruppe"] for t in termine} - set(z["gruppen"]))
    if unbekannt:
        print(f"  Hinweis: {APP_ZEITEN} nennt Gruppen ohne Eintrag in zeiten.json, nicht übernommen: {', '.join(unbekannt)}")
    termine = [t for t in termine if t["gruppe"] in z["gruppen"]]
    ersetzt = {t["gruppe"] for t in termine}
    zusatz = {(t["gruppe"], t["tag"]): t["zusatz"] for t in z["termine"] if t.get("zusatz")}
    neu = [t for t in z["termine"] if t["gruppe"] not in ersetzt]
    for t in termine:
        e = {k: t[k] for k in ("tag", "gruppe", "von", "bis")}
        if (t["gruppe"], t["tag"]) in zusatz:
            e["zusatz"] = zusatz[(t["gruppe"], t["tag"])]
        neu.append(e)
    neu.sort(key=lambda t: (TAGE.index(t["tag"]), t["von"]))
    print(f"Trainingszeiten aus der Schwimmgruppen-App (Stand {app.get('stand', '?')}): {len(ersetzt)} Gruppen")
    return {**z, "termine": neu}


def render_week(z):
    by_day = {}
    for t in z["termine"]:
        by_day.setdefault(t["tag"], []).append(t)
    days = [d for d in TAGE if d in by_day]
    out = [f'<div class="week" style="--days:{len(days)}">']
    for d in days:
        out.append(f'<section class="day" aria-label="{d}"><h3>{d}</h3><ol>')
        for t in sorted(by_day[d], key=lambda t: t["von"]):
            g = z["gruppen"][t["gruppe"]]
            cls = "slot extern" if g["fuer"] == "extern" else "slot"
            zusatz = f'<small>{esc(t["zusatz"])}</small>' if t.get("zusatz") else ""
            out.append(
                f'<li class="{cls}"><time>{t["von"]}–{t["bis"]}</time>'
                f'<a href="{g["seite"]}">{esc(g["name"])}</a>{zusatz}</li>'
            )
        out.append("</ol></section>")
    out.append("</div>")
    out.append('<div class="legend"><span class="chip chip-extern">Für alle, mit Anmeldung</span>'
               '<span class="chip chip-mitglieder">Nur für Mitglieder</span></div>')
    return "\n".join(out)


def render_group_times(z, key):
    items = [t for t in z["termine"] if t["gruppe"] == key]
    items.sort(key=lambda t: (TAGE.index(t["tag"]), t["von"]))
    out = []
    for t in items:
        zusatz = f'<small>{esc(t["zusatz"])}</small>' if t.get("zusatz") else ""
        cls = "slot extern" if z["gruppen"][key]["fuer"] == "extern" else "slot"
        out.append(f'<div class="{cls}"><b>{t["tag"]}</b><time>{t["von"]}–{t["bis"]} Uhr</time>{zusatz}</div>')
    return "\n".join(out)


def render_downloads(items):
    out = ['<div class="downloads">']
    for d in items:
        ziel = d["datei"]
        extern = ziel.startswith("http")
        lokal = SRC / "static" / ziel
        if extern or lokal.exists():
            info = "PDF" if extern else f"PDF · {max(1, lokal.stat().st_size // 1024)} KB"
            out.append(f'<a class="download" href="{esc(ziel)}"{" rel=\"noopener\"" if extern else " download"}>'
                       f'<b>{esc(d["titel"])}</b><span class="small muted">{esc(d["beschreibung"])}</span>'
                       f'<span class="chip">{info} herunterladen</span></a>')
        else:
            out.append(f'<div class="download is-missing"><b>{esc(d["titel"])}</b>'
                       f'<span class="small muted">{esc(d["beschreibung"])}</span>'
                       f'<span class="chip todo">PDF folgt in Kürze</span></div>')
    out.append("</div>")
    return "\n".join(out)


def render_notice(h):
    if not h.get("titel"):
        return ""
    return (f'<aside class="notice" aria-labelledby="aktuell"><span class="notice-badge">Aktuell</span>'
            f'<div><h2 id="aktuell">{esc(h["titel"])}</h2><p>{h["text"]}</p>'
            f'<p class="small">Stand: {esc(h["stand"])}</p></div></aside>')


# ---------- Seiten ----------

def include(text, depth=0):
    counter = {"n": 0}

    def repl(m):
        counter["n"] += 1
        part = (SRC / "partials" / f"{m.group(1)}.html").read_text(encoding="utf-8")
        part = part.replace("{{uid}}", f"{m.group(1)}-{depth}-{counter['n']}")
        return include(part, depth + 1)

    return re.sub(r"\{\{>\s*([\w-]+)\s*\}\}", repl, text)


PAGES_BANNER = (
    '<div class="preview-banner" role="note"><strong>Vorschau</strong> – Entwurf „Variante 2“, '
    'noch nicht die offizielle Website. Formulare sind hier noch nicht aktiv. '
    '<a href="../">Zur Auswahl der Varianten</a></div>')
PAGES_FORM_HINWEIS = ('<p class="callout form-inactive"><b>Noch nicht aktiv:</b> In dieser Vorschau wird '
                      'nichts gesendet. Schreib uns bis zum Start der neuen Website bitte eine Mail.</p>')


def pages_form(m):
    form = m.group(0).replace(' action="api/formular.php"', ' action="#" data-vorschau')
    form = re.sub(r'<button([^>]*)type="submit"', r'<button\1type="submit" disabled', form)
    return PAGES_FORM_HINWEIS + form


def csp_hashes(dist):
    """Trägt die SHA-256-Hashes aller Inline-Skripte in die CSP der .htaccess ein,
    damit die CSP ohne 'unsafe-inline' auskommt. Ändert sich ein Inline-Skript, einfach neu bauen."""
    skripte = set()
    for page in dist.glob("*.html"):
        for code in re.findall(r"<script>(.*?)</script>", page.read_text(encoding="utf-8"), re.S):
            skripte.add(base64.b64encode(hashlib.sha256(code.encode("utf-8")).digest()).decode())
    ht = dist / ".htaccess"
    text = ht.read_text(encoding="utf-8")
    text = text.replace("{{csp_script}}", " ".join(f"'sha256-{h}'" for h in sorted(skripte)))
    ht.write_text(text, encoding="utf-8")


def build(sheet_url=None, release=False, beispiel=False, pages=False):
    site = load_json("site.json")
    zeiten = merge_app_times(load_json("zeiten.json"))
    hinweis = load_json("hinweis.json")
    app_kurse = SRC / "data" / APP_KURSE
    app_kurse = app_kurse if app_kurse.exists() and not beispiel and not sheet_url else None
    url = None if beispiel else (sheet_url or site.get("kurse_csv_url") or None)
    rows, sample = read_courses(url, app_kurse, fallback=pages and not release)
    today = dt.date.today().strftime("%d.%m.%Y")

    if DIST.exists():
        shutil.rmtree(DIST)
    shutil.copytree(SRC / "assets", DIST / "assets")
    shutil.copytree(SRC / "static", DIST, dirs_exist_ok=True)
    if pages:
        # GitHub Pages: kein PHP, keine .htaccess, nicht in Suchmaschinen
        (DIST / ".htaccess").unlink(missing_ok=True)
        (DIST / "robots.txt").write_text("User-agent: *\nDisallow: /\n", encoding="utf-8")
    else:
        # Nur was auf den Server gehört: kein config.beispiel.php, keine Notizen oder Sicherungskopien
        (DIST / "api").mkdir()
        for name in ("formular.php", "config.php", ".htaccess"):
            if (ROOT / "api" / name).exists():
                shutil.copy(ROOT / "api" / name, DIST / "api" / name)
        if not (ROOT / "api" / "config.php").exists():
            print("  Hinweis: api/config.php fehlt (Geheimnis). Ohne sie bleibt das Formular aus.")
    (DIST / "data").mkdir()
    (DIST / "data" / "kurse.json").write_text(
        json.dumps({"stand": today, "beispiel": sample, "kurse": rows}, ensure_ascii=False, indent=1), encoding="utf-8")

    todos = 0
    for page in sorted((SRC / "pages").glob("*.html")):
        text = page.read_text(encoding="utf-8")
        m = re.match(r"\s*<!--\s*(\{.*?\})\s*-->\s*", text, re.S)
        meta = json.loads(m.group(1)) if m else {}
        body = text[m.end():] if m else text
        full = "{{> head}}\n{{> header}}\n" + body + "\n{{> footer}}"
        full = include(full)
        title = meta.get("title", "")
        full = full.replace("{{title}}", esc(f"{title} · {SITE_NAME}" if title else SITE_NAME))
        full = full.replace("{{description}}", esc(meta.get("description", "")))
        base = meta.get("base") if not pages else None  # unter /variante-2/ gibt es kein absolutes "/"
        full = full.replace("{{base}}", (f'<base href="{esc(base)}">\n' if base else "")
                            + ('<meta name="robots" content="noindex, nofollow">\n' if pages else ""))
        full = full.replace("{{build_date}}", today)
        full = full.replace("{{woche}}", render_week(zeiten))
        full = full.replace("{{kurse}}", render_courses(rows, sample))
        full = full.replace("{{hinweis}}", render_notice(hinweis))
        full = full.replace("{{downloads}}", render_downloads(site.get("downloads", [])))
        full = re.sub(r"\{\{zeiten:([\w-]+)\}\}", lambda m: render_group_times(zeiten, m.group(1)), full)
        full = re.sub(r"\{\{kurs-status:([^}]+)\}\}", lambda m: course_status(rows, m.group(1)), full)
        if pages:
            full = re.sub(r"(<body[^>]*>)", lambda m: m.group(1) + "\n" + PAGES_BANNER, full, count=1)
            full = re.sub(r'<form class="form js-form".*?</form>', pages_form, full, flags=re.S)
        nav = meta.get("nav")
        if nav:
            full = full.replace(f'data-nav="{nav}"', f'data-nav="{nav}" aria-current="page"')
        left = re.findall(r"\{\{[^}]+\}\}", full)
        if left:
            sys.exit(f"{page.name}: unbekannte Platzhalter {left}")
        n = len(re.findall(r'class="[^"]*\btodo\b', full))
        if n:
            print(f"  {page.name}: {n} Platzhalter zum Ausfüllen")
            todos += n
        (DIST / page.name).write_text(full, encoding="utf-8")

    if not pages:
        csp_hashes(DIST)

    if pages:
        print(f"Fertig (GitHub-Pages-Vorschau): {DIST} ({todos} offene Platzhalter)")
        return
    urls = [p.name for p in sorted(DIST.glob("*.html")) if p.name not in ("404.html", "danke.html")]
    (DIST / "sitemap.xml").write_text(
        '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        + "".join(f"  <url><loc>{site['domain']}/{'' if u == 'index.html' else u}</loc></url>\n" for u in urls)
        + "</urlset>\n", encoding="utf-8")

    print(f"Fertig: {DIST} ({len(list(DIST.glob('*.html')))} Seiten, {todos} offene Platzhalter)")
    if release and todos:
        sys.exit("Release abgebrochen: Es sind noch Platzhalter (class=\"todo\") offen.")


if __name__ == "__main__":
    args = sys.argv[1:]
    url = args[args.index("--sheet") + 1] if "--sheet" in args else None
    build(url, release="--release" in args, beispiel="--beispiel" in args, pages="--pages" in args)
