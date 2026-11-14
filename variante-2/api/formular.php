<?php
/**
 * TSV Fischach Schwimmen – Formular-Backend (STUB, standardmäßig AUS)
 * =====================================================================
 * Ein einzelnes Skript ohne Datenbank. Es prüft die Eingaben und verschickt sie
 * als Mail an die zuständige Funktionsadresse. Personendaten werden auf dem Server nicht gespeichert.
 * Wartelisten-Mails haben zusätzlich den Anhang anmeldung.csv, den die Schwimmkurs-App
 * direkt einliest (Kinder › Aus Excel/CSV, mehrere Anhänge auf einmal möglich).
 *
 * Schutz vor Missbrauch (siehe DATENSCHUTZ-KONZEPT.md, Abschnitt „Spam-Schutz“):
 *   - Auswahlfelder (Kurs, Angebot, Thema) nur aus festen Listen.
 *   - Mails an die eingetragene Adresse (Eingangsbestätigung, Infomail-Bestätigung) haben festen
 *     Betreff und Text. So lässt sich über das Formular kein eigener Inhalt an Fremde verschicken.
 *   - Mengenbegrenzung je Besucher, je Empfängeradresse und insgesamt (GRENZEN). Gezählt wird
 *     in api/daten/ mit HMAC-Prüfwerten statt IP- oder Mailadressen; Einträge verfallen nach 1–3 Tagen.
 *   - Zeitfalle: main.js holt beim ersten Klick ins Formular einen signierten Zeitstempel. Wer
 *     schneller als MIN_SEKUNDEN absendet, ist ein Bot. Ohne JavaScript gilt die strengere Grenze.
 *   - Honeypot-Feld „website“.
 *   - Infomail-Bestätigung erst per Knopf (POST), damit Mail-Scanner nicht automatisch bestätigen.
 *
 * Vor dem Einschalten (siehe DATENSCHUTZ-KONZEPT.md):
 *   1. AV-Vertrag mit Strato abschließen, Datenschutzerklärung freigeben lassen.
 *   2. Funktionsadressen unten prüfen; ABSENDER muss ein Postfach dieser Domain sein.
 *   3. config.beispiel.php nach config.php kopieren und dort GEHEIMNIS auf eine lange
 *      Zufallszeichenkette setzen (für Bestätigungslink, Zeitstempel und Zähler). config.php kommt
 *      nur auf den Server, nie ins Git.
 *   4. AKTIV auf true setzen und mit jedem Formular einmal testen.
 *
 * Antwortet mit JSON, wenn der Browser "Accept: application/json" schickt (main.js),
 * sonst mit einer Weiterleitung auf danke.html (Formular ohne JavaScript).
 */

declare(strict_types=1);

const AKTIV      = false;
const DOMAIN     = 'https://tsv-fischach-schwimmen.de';
const ABSENDER   = 'website@tsv-fischach-schwimmen.de';
const OPTIN_TAGE = 3;

// Mengenbegrenzung: [erlaubte Anzahl, Zeitfenster in Sekunden]
const GRENZEN = [
    'besucher'         => [5, 3600],    // Absendungen je Besucher (IP) und Stunde
    'besucher_ohne_js' => [2, 3600],    // dasselbe ohne gültigen Zeitstempel (kein JavaScript oder Bot)
    'adresse'          => [2, 86400],   // Mails an dieselbe eingetragene Adresse pro Tag
    'gesamt'           => [60, 3600],   // Notbremse: alle Absendungen zusammen pro Stunde
];
const MIN_SEKUNDEN = 3;          // schneller tippt kein Mensch ein Formular aus
const MAX_FORM_ALTER = 86400;    // Zeitstempel älter als ein Tag: Seite neu laden
const DATEN = __DIR__ . '/daten';

// Geheimnis aus config.php (nicht im Git). Ohne config.php gibt es nur den lokalen Testwert.
if (is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
}
if (!defined('GEHEIMNIS')) {
    define('GEHEIMNIS', getenv('FORMULAR_TESTMODUS') ? 'nur-lokaler-test' : '');
}

// Wohin welche Anfrage geht
const EMPFAENGER = [
    'warteliste' => 'schwimmkurs@tsv-fischach-schwimmen.de',
    'erwachsene' => 'bleib.fit@tsv-fischach-schwimmen.de',
    'infomail'   => 'kontakt@tsv-fischach-schwimmen.de',
    'kontakt'    => [
        'allgemein'         => 'kontakt@tsv-fischach-schwimmen.de',
        'schnuppern'        => 'schnuppern@tsv-fischach-schwimmen.de',
        'erwachsene'        => 'bleib.fit@tsv-fischach-schwimmen.de',
        'kleidung'          => 'kontakt@tsv-fischach-schwimmen.de',
        'abteilungsleitung' => 'leitung@tsv-fischach-schwimmen.de',
    ],
];

// Erlaubte Felder je Formular: name => [Pflicht?, max. Länge, Bezeichnung]
const FELDER = [
    'warteliste' => [
        'kurs'              => [true, 40, 'Kurs'],
        'kind_name'         => [true, 80, 'Name des Kindes'],
        'kind_geburtsdatum' => [true, 10, 'Geburtsdatum'],
        'einschulung'       => [true, 20, 'Einschulungsjahr'],
        'wohnort'           => [true, 80, 'Wohnort (PLZ, Ort)'],
        'name'              => [true, 80, 'Erziehungsberechtigte Person'],
        'email'             => [true, 120, 'E-Mail'],
        'telefon'           => [false, 40, 'Telefon'],
        'nachricht'         => [false, 800, 'Hinweise'],
    ],
    'erwachsene' => [
        'angebot'   => [true, 40, 'Angebot'],
        'name'      => [true, 80, 'Name'],
        'email'     => [true, 120, 'E-Mail'],
        'telefon'   => [false, 40, 'Telefon'],
        'niveau'    => [false, 60, 'Schwimmniveau'],
        'nachricht' => [false, 800, 'Nachricht'],
        'infomail'  => [false, 3, 'Infomail gewünscht'],
    ],
    'kontakt' => [
        'thema'     => [true, 40, 'Thema'],
        'name'      => [true, 80, 'Name'],
        'email'     => [true, 120, 'E-Mail'],
        'nachricht' => [true, 2000, 'Nachricht'],
    ],
    'infomail' => [
        'email' => [true, 120, 'E-Mail'],
    ],
];
// Auswahlfelder: nur diese Werte (müssen zu den <option>/<input value> der Seiten passen)
const AUSWAHL = [
    'warteliste' => ['kurs' => ['Seepferdchenkurs', 'Robby-Kurs']],
    'erwachsene' => ['angebot' => ['Wassergymnastik', 'Erwachsenenschwimmen'], 'infomail' => ['', 'ja']],
    'kontakt'    => ['thema' => ['allgemein', 'schnuppern', 'erwachsene', 'kleidung', 'abteilungsleitung']],
];
const THEMEN = ['schwimmkurse', 'erwachsene', 'verein'];

// ---------------------------------------------------------------------------

function antwort(bool $ok, string $text, int $code = 200, array $extra = []): never {
    $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    http_response_code($code);
    header('Cache-Control: no-store');
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $text] + $extra, JSON_UNESCAPED_UNICODE);
    } elseif ($ok) {
        header('Location: ' . DOMAIN . '/danke.html', true, 303);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $text;
    }
    exit;
}

/** Kleine HTML-Seite für den Bestätigungslink der Infomail (ohne JavaScript). */
function seite(string $titel, string $inhalt, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    $t = htmlspecialchars($titel);
    echo "<!doctype html><html lang=\"de\"><head><meta charset=\"utf-8\">"
        . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><meta name=\"robots\" content=\"noindex\">"
        . "<title>$t · TSV Fischach Schwimmen</title><link rel=\"stylesheet\" href=\"../assets/css/style.css\"></head>"
        . "<body><main class=\"section\"><div class=\"wrap prose\"><h1>$t</h1>$inhalt"
        . "<p><a href=\"" . DOMAIN . "/\">Zur Startseite</a></p></div></main></body></html>";
    exit;
}

function sauber(string $s, int $max): string {
    $s = trim(str_replace(["\r", "\0"], '', $s));
    return mb_substr($s, 0, $max);
}

function zeile(string $s): string {
    // Kopfzeilen dürfen keine Zeilenumbrüche enthalten (Header-Injection)
    return preg_replace('/[\r\n]+/', ' ', $s);
}

function b64(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function unb64(string $s): string|false {
    return base64_decode(strtr($s, '-_', '+/'), true);
}

/** $anhang = [Dateiname, Inhalt]: dann geht die Mail als multipart/mixed mit dieser Datei raus. */
function senden(string $an, string $betreff, string $text, ?string $antwortAn = null, ?array $anhang = null): bool {
    $kopf = ['From: TSV Fischach Schwimmen <' . ABSENDER . '>'];
    if ($antwortAn) {
        $kopf[] = 'Reply-To: ' . zeile($antwortAn);
    }
    if ($anhang) {
        [$datei, $inhalt] = $anhang;
        $grenze = 'tsv-' . bin2hex(random_bytes(12));
        $kopf[] = 'MIME-Version: 1.0';
        $kopf[] = "Content-Type: multipart/mixed; boundary=\"$grenze\"";
        $text = "--$grenze\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$text\r\n"
            . "--$grenze\r\nContent-Type: text/csv; charset=utf-8; name=\"$datei\"\r\n"
            . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$datei\"\r\n\r\n"
            . chunk_split(base64_encode($inhalt)) . "--$grenze--\r\n";
    } else {
        $kopf[] = 'Content-Type: text/plain; charset=utf-8';
        $kopf[] = 'Content-Transfer-Encoding: 8bit';
    }
    $betreff = '=?UTF-8?B?' . base64_encode(zeile($betreff)) . '?=';
    if (getenv('FORMULAR_TESTMODUS')) {  // lokal: Mail in die Fehlerausgabe statt senden
        error_log("MAIL an $an | $betreff\n" . implode("\n", $kopf) . "\n\n$text");
        return true;
    }
    return mail($an, $betreff, $text, implode("\r\n", $kopf), '-f' . ABSENDER);
}

/**
 * Anhang für die Schwimmkurs-App: eine Zeile, Spaltennamen so, dass die App sie ohne Zuordnen erkennt.
 * Semikolon und BOM, damit auch Excel die Datei richtig öffnet. Zellen, die mit = + - @ Tab oder CR
 * beginnen, bekommen ein ' davor, damit Excel sie nicht als Formel ausführt (CSV-Injection).
 */
function anmeldung_csv(array $w): string {
    $zeilen = [
        ['Anmeldedatum', 'Name des Kindes', 'Geburtsdatum', 'Einschulung', 'PLZ und Ort', 'Elternteil', 'E-Mail',
            'Telefon', 'Bemerkung Kurswunsch', 'Bemerkung'],
        [date('d.m.Y'), $w['kind_name'], $w['kind_geburtsdatum'], $w['einschulung'], $w['wohnort'], $w['name'],
            $w['email'], $w['telefon'], 'Kurswunsch: ' . $w['kurs'], str_replace("\n", ' ', $w['nachricht'])],
    ];
    $f = fopen('php://temp', 'r+');
    foreach ($zeilen as $z) {
        fputcsv($f, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $z), ';', '"', '', "\r\n");
    }
    rewind($f);
    return "\u{FEFF}" . stream_get_contents($f);
}

// ---------- Infomail-Token (Double-Opt-in ohne Datenbank) ----------

function token(string $email, array $themen, int $zeit): string {
    $daten = $email . '|' . implode(',', $themen) . '|' . $zeit;
    return b64($daten . '|' . hash_hmac('sha256', $daten, GEHEIMNIS));
}

/** Gibt [email, themen, zeit, sig] zurück oder null, wenn ungültig oder abgelaufen. */
function token_pruefen(string $token): ?array {
    $roh = unb64($token);
    $teile = $roh ? explode('|', $roh) : [];
    if (count($teile) < 4) {
        return null;
    }
    // Von hinten lesen: die Mailadresse selbst darf ein | enthalten
    $sig = array_pop($teile);
    $zeit = array_pop($teile);
    $themen = array_pop($teile);
    $email = implode('|', $teile);
    $erwartet = hash_hmac('sha256', "$email|$themen|$zeit", GEHEIMNIS);
    if (!hash_equals($erwartet, $sig) || !ctype_digit($zeit) || time() - (int) $zeit > OPTIN_TAGE * 86400) {
        return null;
    }
    return [$email, $themen, (int) $zeit, $sig];
}

// ---------- Zeitfalle: signierter Zeitstempel ----------

function zeitstempel(): string {
    $t = (string) time();
    return b64($t . '|' . hash_hmac('sha256', "zeit|$t", GEHEIMNIS));
}

/** Alter des Formulars in Sekunden oder null, wenn kein gültiger Zeitstempel mitkam. */
function formular_alter(string $wert): ?int {
    $roh = $wert === '' ? false : unb64($wert);
    if (!$roh || substr_count($roh, '|') !== 1) {
        return null;
    }
    [$t, $sig] = explode('|', $roh);
    if (!ctype_digit($t) || !hash_equals(hash_hmac('sha256', "zeit|$t", GEHEIMNIS), $sig)) {
        return null;
    }
    return time() - (int) $t;
}

// ---------- Mengenbegrenzung (Dateien in api/daten/, keine Klardaten) ----------

function daten_ordner(): string {
    if (!is_dir(DATEN)) {
        @mkdir(DATEN, 0700, true);
    }
    if (!is_file(DATEN . '/.htaccess')) {
        @file_put_contents(DATEN . '/.htaccess', "Require all denied\n");
    }
    // Gelegentlich aufräumen: Zähler, die seit 3 Tagen nicht mehr geändert wurden
    if (random_int(1, 50) === 1) {
        foreach (glob(DATEN . '/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - 3 * 86400) {
                @unlink($f);
            }
        }
    }
    return DATEN;
}

/**
 * Zählt einen Vorgang für $art/$wert und sagt, ob er noch erlaubt ist.
 * Gespeichert wird nur ein HMAC-Prüfwert von $wert (nicht umkehrbar ohne GEHEIMNIS) und die Zeitpunkte.
 */
function erlaubt(string $art, string $wert, int $max, int $fenster): bool {
    $datei = daten_ordner() . '/' . $art . '-' . substr(hash_hmac('sha256', "$art|$wert", GEHEIMNIS), 0, 32) . '.json';
    $f = @fopen($datei, 'c+');
    if (!$f) {
        // Speicher nicht beschreibbar: lieber ablehnen als ungebremst senden
        error_log('formular.php: api/daten/ ist nicht beschreibbar');
        return false;
    }
    flock($f, LOCK_EX);
    $zeiten = json_decode(stream_get_contents($f) ?: '[]', true);
    $grenze = time() - $fenster;
    $zeiten = array_values(array_filter(is_array($zeiten) ? $zeiten : [], fn($z) => is_int($z) && $z > $grenze));
    $ok = count($zeiten) < $max;
    if ($ok) {
        $zeiten[] = time();
    }
    ftruncate($f, 0);
    rewind($f);
    fwrite($f, json_encode($zeiten));
    flock($f, LOCK_UN);
    fclose($f);
    return $ok;
}

function grenze(string $art, string $wert): bool {
    [$max, $fenster] = GRENZEN[$art];
    return erlaubt($art, $wert, $max, $fenster);
}

// ---------------------------------------------------------------------------

if (!AKTIV && !getenv('FORMULAR_TESTMODUS')) {
    antwort(false, 'Das Formular ist noch nicht freigeschaltet.', 503, ['demo' => true]);
}
if ((strlen(GEHEIMNIS) < 32 || str_starts_with(GEHEIMNIS, 'HIER-')) && !getenv('FORMULAR_TESTMODUS')) {
    antwort(false, 'Das Formular ist nicht vollständig eingerichtet.', 500);
}

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Zeitstempel für die Zeitfalle (main.js holt ihn beim ersten Klick ins Formular)
if ($methode === 'GET' && isset($_GET['zeit'])) {
    antwort(true, '', 200, ['t' => zeitstempel()]);
}

// Bestätigungslink der Infomail, Schritt 1: nur eine Seite mit Knopf zeigen (Mail-Scanner klicken keine Knöpfe)
if ($methode === 'GET' && isset($_GET['bestaetigen'])) {
    $token = (string) $_GET['bestaetigen'];
    if (!token_pruefen($token)) {
        seite('Link ungültig', '<p>Der Link ist ungültig oder abgelaufen. Bitte melde dich '
            . '<a href="' . DOMAIN . '/infomail.html">neu an</a>.</p>', 400);
    }
    seite('Infomail bestätigen',
        '<p>Bitte bestätige mit einem Klick, dass du die Infomail der Schwimmabteilung bekommen möchtest.</p>'
        . '<form method="post" action="formular.php"><input type="hidden" name="bestaetigen" value="'
        . htmlspecialchars($token) . '"><button class="btn" type="submit">Jetzt bestätigen</button></form>');
}

// Bestätigungslink der Infomail, Schritt 2: Knopf gedrückt
if ($methode === 'POST' && isset($_POST['bestaetigen'])) {
    $t = token_pruefen((string) $_POST['bestaetigen']);
    if (!$t) {
        seite('Link ungültig', '<p>Der Link ist ungültig oder abgelaufen. Bitte melde dich '
            . '<a href="' . DOMAIN . '/infomail.html">neu an</a>.</p>', 400);
    }
    [$email, $themen, $zeit, $sig] = $t;
    // Jeder Link bestätigt nur einmal; weitere Klicks lösen keine Mail mehr aus
    if (grenze('gesamt', 'alle') && erlaubt('optin', $sig, 1, (OPTIN_TAGE + 1) * 86400)) {
        senden(EMPFAENGER['infomail'], 'Infomail: Anmeldung bestätigt',
            "Bitte in den Infomail-Verteiler aufnehmen.\n\nE-Mail: $email\nThemen: $themen\n"
            . 'Angemeldet: ' . date('d.m.Y H:i', $zeit) . "\nBestätigt: " . date('d.m.Y H:i') . "\n\n"
            . "Diese Mail ist der Nachweis der Einwilligung (Double-Opt-in). Bitte aufbewahren, solange die Adresse im Verteiler ist.");
    }
    header('Location: ' . DOMAIN . '/infomail.html#bestaetigt', true, 303);
    exit;
}

if ($methode !== 'POST') {
    antwort(false, 'Nur POST erlaubt.', 405);
}

// Honeypot: Bots füllen das versteckte Feld aus. Wir tun so, als wäre alles gut.
if (!empty($_POST['website'])) {
    antwort(true, 'Danke!');
}

$formular = (string) ($_POST['formular'] ?? '');
if (!isset(FELDER[$formular])) {
    antwort(false, 'Unbekanntes Formular.', 400);
}

// Zeitfalle: zu schnell abgeschickt = Bot (wir tun so, als wäre alles gut)
$alter = formular_alter((string) ($_POST['t'] ?? ''));
if ($alter !== null && $alter < MIN_SEKUNDEN) {
    antwort(true, 'Danke!');
}
if ($alter !== null && $alter > MAX_FORM_ALTER) {
    antwort(false, 'Die Seite war sehr lange offen. Bitte lade sie neu und schick das Formular noch einmal ab.', 422);
}

if (($_POST['einwilligung'] ?? '') !== 'ja') {
    antwort(false, 'Bitte bestätige den Hinweis zum Datenschutz.', 422);
}

$werte = [];
foreach (FELDER[$formular] as $feld => [$pflicht, $max, $label]) {
    $wert = sauber((string) ($_POST[$feld] ?? ''), $max);
    if ($pflicht && $wert === '') {
        antwort(false, "Bitte füll das Feld „{$label}“ aus.", 422);
    }
    $werte[$feld] = $wert;
}
foreach (AUSWAHL[$formular] ?? [] as $feld => $erlaubt) {
    if (!in_array($werte[$feld], $erlaubt, true)) {
        antwort(false, 'Bitte wähle bei „' . FELDER[$formular][$feld][2] . '“ einen Eintrag aus der Liste.', 422);
    }
}
if (!filter_var($werte['email'], FILTER_VALIDATE_EMAIL)) {
    antwort(false, 'Bitte prüf die E-Mail-Adresse.', 422);
}
$themen = [];
if ($formular === 'infomail') {
    $themen = array_values(array_intersect(THEMEN, (array) ($_POST['themen'] ?? [])));
    if (!$themen) {
        antwort(false, 'Bitte wähle mindestens ein Thema.', 422);
    }
}

// Mengenbegrenzung erst nach der Prüfung, damit Tippfehler niemanden aussperren
$besucher = $alter === null ? 'besucher_ohne_js' : 'besucher';
if (!grenze('gesamt', 'alle') || !grenze($besucher, $ip)) {
    antwort(false, 'Gerade kommen zu viele Anfragen an. Bitte versuch es in einer Stunde noch einmal '
        . 'oder schreib uns direkt eine Mail.', 429);
}
// Mails an die eingetragene Adresse: höchstens GRENZEN['adresse'] pro Tag, danach still auslassen
$adresse_frei = fn(): bool => grenze('adresse', mb_strtolower($werte['email']));

// ---------- Infomail: nur Bestätigungslink verschicken (fester Text) ----------
function optin_mail(string $email, array $themen): void {
    $link = DOMAIN . '/api/formular.php?bestaetigen=' . token($email, $themen, time());
    senden($email, 'Bitte bestätigen: Infomail TSV Fischach Schwimmen',
        "Hallo,\n\ndu hast die Infomail der Schwimmabteilung des TSV Fischach bestellt.\n"
        . "Bitte bestätige über diesen Link (gültig " . OPTIN_TAGE . " Tage):\n\n$link\n\n"
        . "Wenn du das nicht warst, ignorier diese Mail einfach. Dann passiert nichts.\n\n"
        . "TSV Fischach, Abteilung Schwimmen\n" . DOMAIN . "/datenschutz.html");
}

if ($formular === 'infomail') {
    if ($adresse_frei()) {
        optin_mail($werte['email'], $themen);
    }
    antwort(true, 'Fast geschafft! Bitte klick auf den Link in der Mail, die wir dir gerade geschickt haben.');
}

// ---------- Warteliste, Erwachsene, Kontakt: Mail an die Funktionsadresse ----------
$an = EMPFAENGER[$formular];
if (is_array($an)) {
    $an = $an[$werte['thema']];
}
$titel = [
    'warteliste' => 'Warteliste ' . $werte['kurs'],
    'erwachsene' => 'Anmeldung ' . $werte['angebot'],
    'kontakt'    => 'Kontakt: ' . $werte['thema'],
][$formular];

$text = "Neue Anfrage über die Website (" . date('d.m.Y H:i') . ")\n\n";
foreach (FELDER[$formular] as $feld => [, , $label]) {
    if ($werte[$feld] !== '') {
        $text .= str_pad($label . ':', 30) . $werte[$feld] . "\n";
    }
}
$text .= "\nEinwilligung Datenschutz: ja\n";
if ($formular === 'warteliste') {
    $text .= "\nBitte in die Warteliste übernehmen. Löschfrist: nach Kursteilnahme bzw. spätestens 24 Monate.\n";
}

$anhang = $formular === 'warteliste' ? ['anmeldung.csv', anmeldung_csv($werte)] : null;
if ($formular === 'warteliste') {
    $text .= "Anhang anmeldung.csv: in der Schwimmkurs-App unter Kinder › Aus Excel/CSV einlesen.\n";
}
if (!senden($an, $titel, $text, $werte['email'], $anhang)) {
    antwort(false, 'Das Senden hat nicht geklappt. Bitte schreib uns direkt eine Mail.', 500);
}

// Eingangsbestätigung an die absendende Person: fester Betreff und Text, keine Formulardaten,
// damit das Formular nicht als Spam-Schleuder für fremde Adressen taugt
if ($formular !== 'kontakt' && $adresse_frei()) {
    senden($werte['email'], 'Eingangsbestätigung: TSV Fischach Schwimmen',
        "Hallo,\n\nvielen Dank, deine Anfrage ist bei der Schwimmabteilung des TSV Fischach angekommen. "
        . "Wir melden uns, sobald wir mehr wissen.\n\n"
        . "Wenn du nichts abgeschickt hast, ignorier diese Mail bitte.\n\nTSV Fischach, Abteilung Schwimmen\n" . DOMAIN);
}
if ($formular === 'erwachsene' && $werte['infomail'] === 'ja' && $adresse_frei()) {
    optin_mail($werte['email'], ['erwachsene']);
}

$danke = [
    'warteliste' => 'Danke! Dein Kind steht auf der Warteliste. Du bekommst gleich eine Bestätigung per Mail.',
    'erwachsene' => 'Danke für deine Anmeldung! Wir melden uns per Mail.',
    'kontakt'    => 'Danke für deine Nachricht! Wir melden uns per Mail.',
][$formular];
antwort(true, $danke);
