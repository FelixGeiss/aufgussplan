<?php
/**
 * Konfigurationsdatei für das Aufgussplan-System
 *
 * Diese Datei enthält alle wichtigen Einstellungen für die Anwendung.
 * Sie wird von allen anderen PHP-Dateien eingebunden und definiert
 * Konstanten, die im gesamten System verwendet werden.
 *
 * Als Anfänger solltest du wissen:
 * - Konstanten (define()) sind Werte, die sich während der Laufzeit nicht ändern
 * - Diese Datei wird mit require_once eingebunden (siehe andere PHP-Dateien)
 * - Alle Pfad-Angaben verwenden DIRECTORY_SEPARATOR für verschiedene Betriebssysteme
 */

/**
 * ============================================================================
 * DATENBANK-KONFIGURATION
 * ============================================================================
 *
 * Hier werden die Zugangsdaten für die MySQL-Datenbank festgelegt.
 * Diese Daten musst du anpassen, wenn du die Anwendung installierst.
 */

// Der Server, auf dem die Datenbank läuft (meistens 'localhost' bei XAMPP)
define('DB_HOST', 'localhost');

// Der Name deiner Datenbank (die du in phpMyAdmin erstellt hast)
define('DB_NAME', 'aufgussplan');

// Der Benutzername für den Datenbankzugriff
define('DB_USER', 'root');

// Das Passwort für den Datenbankbenutzer (leer bei XAMPP Standardinstallation)
define('DB_PASS', '');

/**
 * ============================================================================
 * URL- UND PFAD-KONFIGURATION
 * ============================================================================
 *
 * Diese Einstellungen definieren, wo sich die Anwendung befindet und
 * wie die verschiedenen Verzeichnisse strukturiert sind.
 */

// Die Basis-URL der Anwendung (wichtig für Links und Redirects)
// Beispiel: 'http://localhost/AufgussManager/' oder 'https://meine-sauna.de/'
define('BASE_URL', 'http://localhost/AufgussManager/');

// Der absolute Pfad zum Hauptverzeichnis des Projekts auf dem Server
// dirname(__DIR__, 2) geht 2 Ebenen nach oben vom aktuellen Verzeichnis
define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);

// Pfad zum public-Verzeichnis (das einzige, was über den Browser erreichbar ist)
define('PUBLIC_PATH', ROOT_PATH . 'public' . DIRECTORY_SEPARATOR);

// Pfad zum src-Verzeichnis (enthält den PHP-Code, nicht browser-erreichbar)
define('SRC_PATH', ROOT_PATH . 'src' . DIRECTORY_SEPARATOR);

// Pfad zum Upload-Verzeichnis (für hochgeladene Bilder)
define('UPLOAD_PATH', PUBLIC_PATH . 'uploads' . DIRECTORY_SEPARATOR);

/**
 * ============================================================================
 * UPLOAD-KONFIGURATION
 * ============================================================================
 *
 * Einstellungen für das Hochladen von Dateien (z.B. Mitarbeiter-Bilder)
 */

// Maximale Dateigröße für Uploads in Bytes (5MB = 5 * 1024 * 1024 Bytes)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Erlaubte Dateitypen für Bilder (MIME-Types)
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

/**
 * ============================================================================
 * SESSION-KONFIGURATION
 * ============================================================================
 *
 * Sessions sind wie "Erinnerungen" des Servers an einen bestimmten Benutzer.
 * Wichtig für Login-Funktionalität und Admin-Bereich.
 */

// Wie lange eine Session gültig bleibt (in Sekunden)
// 3600 Sekunden = 1 Stunde
define('SESSION_LIFETIME', 3600);

/**
 * ============================================================================
 * ZEIT- UND SPRACHE-EINSTELLUNGEN
 * ============================================================================
 */

// Zeitzone für korrekte Datum/Zeit-Anzeige
// 'Europe/Berlin' für Deutschland
date_default_timezone_set('Europe/Berlin');

/**
 * ============================================================================
 * FEHLERBEHANDLUNG
 * ============================================================================
 *
 * Während der Entwicklung zeigen wir alle Fehler an, um Probleme zu finden.
 * Im Produktivbetrieb sollte dies aus Sicherheitsgründen ausgeschaltet werden.
 */

// Fehler im Browser anzeigen (true = anzeigen, false = verstecken)
ini_set('display_errors', 1);

// Start-Up-Fehler ebenfalls anzeigen
ini_set('display_startup_errors', 1);

// Welche Fehler sollen gemeldet werden? (E_ALL = alle Fehler)
error_reporting(E_ALL);

/**
 * ============================================================================
 * AUTOLOADER FÜR KLASSEN
 * ============================================================================
 *
 * Der Autoloader lädt automatisch PHP-Klassen, wenn sie benötigt werden.
 * So müssen wir nicht jede Klasse manuell mit require_once einbinden.
 *
 * Wie es funktioniert:
 * 1. Wenn eine Klasse verwendet wird (z.B. new Aufguss())
 * 2. Sucht PHP automatisch im src-Verzeichnis nach Aufguss.php
 * 3. Bindet die Datei automatisch ein
 */

// Automatisches Laden von Klassen
spl_autoload_register(function ($className) {
    // Namespace-Trenner (\) durch Verzeichnis-Trenner (/) ersetzen
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);

    // Vollständigen Pfad zur Klassendatei erstellen
    $file = SRC_PATH . $className . '.php';

    // Datei einbinden, falls sie existiert
    if (file_exists($file)) {
        require_once $file;
    }
});
?>
