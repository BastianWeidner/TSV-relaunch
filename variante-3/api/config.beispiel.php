<?php
// Vorlage: als config.php speichern (gleicher Ordner), Wert ersetzen, nur auf den Server laden.
// config.php steht in .gitignore und darf nie ins Git.
// Zufallswert erzeugen z. B. mit: php -r "echo bin2hex(random_bytes(32));"
const GEHEIMNIS = 'HIER-EINE-LANGE-ZUFAELLIGE-ZEICHENKETTE-EINTRAGEN';
