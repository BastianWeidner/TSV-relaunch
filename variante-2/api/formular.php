<?php
/**
 * TSV Fischach Schwimmen – Formular-Backend (STUB, standardmäßig AUS)
 * =====================================================================
 * Ein einzelnes Skript ohne Datenbank. Es prüft die Eingaben und verschickt sie
 * als Mail an die zuständige Funktionsadresse. Auf dem Server wird nichts gespeichert.
 *
 * Vor dem Einschalten (siehe DATENSCHUTZ-KONZEPT.md):
 *   1. AV-Vertrag mit Strato abschließen, Datenschutzerklärung freigeben lassen.
 *   2. Funktionsadressen unten prüfen; ABSENDER muss ein Postfach dieser Domain sein.
 *   3. config.beispiel.php nach config.php kopieren und dort GEHEIMNIS auf eine lange
 *      Zufallszeichenkette setzen (für den Infomail-Bestätigungslink). config.php kommt
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
const THEMEN = ['schwimmkurse', 'erwachsene', 'verein'];

// ---------------------------------------------------------------------------

function antwort(bool $ok, string $text, int $code = 200, array $extra = []): never {
    $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    http_response_code($code);
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

function sauber(string $s, int $max): string {
    $s = trim(str_replace(["\r", "\0"], '', $s));
    return mb_substr($s, 0, $max);
}

function zeile(string $s): string {
    // Kopfzeilen dürfen keine Zeilenumbrüche enthalten (Header-Injection)
    return preg_replace('/[\r\n]+/', ' ', $s);
}

function senden(string $an, string $betreff, string $text, ?string $antwortAn = null): bool {
    $kopf = [
        'From: TSV Fischach Schwimmen <' . ABSENDER . '>',
        'Content-Type: text/plain; charset=utf-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($antwortAn) {
        $kopf[] = 'Reply-To: ' . zeile($antwortAn);
    }
    $betreff = '=?UTF-8?B?' . base64_encode(zeile($betreff)) . '?=';
    if (getenv('FORMULAR_TESTMODUS')) {  // lokal: Mail in die Fehlerausgabe statt senden
        error_log("MAIL an $an | $betreff\n$text");
        return true;
    }
    return mail($an, $betreff, $text, implode("\r\n", $kopf), '-f' . ABSENDER);
}

function token(string $email, array $themen, int $zeit): string {
    $daten = $email . '|' . implode(',', $themen) . '|' . $zeit;
    $sig = hash_hmac('sha256', $daten, GEHEIMNIS);
    return rtrim(strtr(base64_encode($daten . '|' . $sig), '+/', '-_'), '=');
}

// ---------------------------------------------------------------------------

if (!AKTIV && !getenv('FORMULAR_TESTMODUS')) {
    antwort(false, 'Das Formular ist noch nicht freigeschaltet.', 503, ['demo' => true]);
}
if ((strlen(GEHEIMNIS) < 32 || str_starts_with(GEHEIMNIS, 'HIER-')) && !getenv('FORMULAR_TESTMODUS')) {
    antwort(false, 'Das Formular ist nicht vollständig eingerichtet.', 500);
}

// Bestätigungslink der Infomail (Double-Opt-in, ohne Datenbank: signiertes Token)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['bestaetigen'])) {
    $roh = base64_decode(strtr((string) $_GET['bestaetigen'], '-_', '+/'), true);
    $teile = $roh ? explode('|', $roh) : [];
    if (count($teile) !== 4) {
        antwort(false, 'Der Link ist ungültig.', 400);
    }
    [$email, $themen, $zeit, $sig] = $teile;
    $erwartet = hash_hmac('sha256', "$email|$themen|$zeit", GEHEIMNIS);
    if (!hash_equals($erwartet, $sig) || time() - (int) $zeit > OPTIN_TAGE * 86400) {
        antwort(false, 'Der Link ist ungültig oder abgelaufen. Bitte melde dich neu an.', 400);
    }
    senden(EMPFAENGER['infomail'], 'Infomail: Anmeldung bestätigt',
        "Bitte in den Infomail-Verteiler aufnehmen.\n\nE-Mail: $email\nThemen: $themen\n"
        . 'Angemeldet: ' . date('d.m.Y H:i', (int) $zeit) . "\nBestätigt: " . date('d.m.Y H:i') . "\n\n"
        . "Diese Mail ist der Nachweis der Einwilligung (Double-Opt-in). Bitte aufbewahren, solange die Adresse im Verteiler ist.");
    header('Location: ' . DOMAIN . '/infomail.html#bestaetigt', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
if (!filter_var($werte['email'], FILTER_VALIDATE_EMAIL)) {
    antwort(false, 'Bitte prüf die E-Mail-Adresse.', 422);
}

// ---------- Infomail: nur Bestätigungslink verschicken ----------
function optin_mail(string $email, array $themen): void {
    $link = DOMAIN . '/api/formular.php?bestaetigen=' . token($email, $themen, time());
    senden($email, 'Bitte bestätigen: Infomail TSV Fischach Schwimmen',
        "Hallo,\n\ndu hast die Infomail der Schwimmabteilung des TSV Fischach bestellt.\n"
        . "Bitte bestätige mit diesem Link (gültig " . OPTIN_TAGE . " Tage):\n\n$link\n\n"
        . "Wenn du das nicht warst, ignorier diese Mail einfach. Dann passiert nichts.\n\n"
        . "TSV Fischach, Abteilung Schwimmen\n" . DOMAIN . "/datenschutz.html");
}

if ($formular === 'infomail') {
    $themen = array_values(array_intersect(THEMEN, (array) ($_POST['themen'] ?? [])));
    if (!$themen) {
        antwort(false, 'Bitte wähle mindestens ein Thema.', 422);
    }
    optin_mail($werte['email'], $themen);
    antwort(true, 'Fast geschafft! Bitte klick auf den Link in der Mail, die wir dir gerade geschickt haben.');
}
if ($formular === 'erwachsene' && $werte['infomail'] === 'ja') {
    optin_mail($werte['email'], ['erwachsene']);
}

// ---------- Warteliste, Erwachsene, Kontakt: Mail an die Funktionsadresse ----------
$an = EMPFAENGER[$formular];
if (is_array($an)) {
    $an = $an[$werte['thema']] ?? $an['allgemein'];
}
$titel = [
    'warteliste' => 'Warteliste ' . $werte['kurs'],
    'erwachsene' => 'Anmeldung ' . ($werte['angebot'] ?? ''),
    'kontakt'    => 'Kontakt: ' . ($werte['thema'] ?? ''),
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

if (!senden($an, $titel, $text, $werte['email'])) {
    antwort(false, 'Das Senden hat nicht geklappt. Bitte schreib uns direkt eine Mail.', 500);
}

// Eingangsbestätigung an die absendende Person (ohne Wiederholung der Daten, damit das Formular
// nicht als Spam-Schleuder für fremde Adressen taugt)
if ($formular !== 'kontakt') {
    senden($werte['email'], 'Eingangsbestätigung: ' . $titel,
        "Hallo,\n\nvielen Dank, deine Anfrage ist bei der Schwimmabteilung des TSV Fischach angekommen. "
        . "Wir melden uns, sobald wir mehr wissen.\n\nTSV Fischach, Abteilung Schwimmen\n" . DOMAIN);
}

$danke = [
    'warteliste' => 'Danke! Dein Kind steht auf der Warteliste. Du bekommst gleich eine Bestätigung per Mail.',
    'erwachsene' => 'Danke für deine Anmeldung! Wir melden uns per Mail.',
    'kontakt'    => 'Danke für deine Nachricht! Wir melden uns per Mail.',
][$formular];
antwort(true, $danke);
