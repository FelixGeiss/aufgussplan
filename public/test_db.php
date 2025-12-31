<?php
/**
 * Datenbankverbindung Test-Script
 *
 * Dieses Script testet systematisch alle Aspekte der Datenbankverbindung.
 * Es ist besonders hilfreich für Anfänger, um zu verstehen:
 * - Wie man eine Datenbankverbindung herstellt
 * - Wie man SQL-Abfragen ausführt
 * - Wie man Fehler behandelt
 * - Wie man Daten aus der Datenbank liest
 *
 * Verwendung:
 * 1. Stelle sicher, dass XAMPP läuft (Apache + MySQL)
 * 2. Erstelle die Datenbank 'aufgussplan' in phpMyAdmin
 * 3. Führe die SQL-Schemas aus (siehe README.md)
 * 4. Rufe diese Seite im Browser auf: http://localhost/aufgussplan/test_db.php
 *
 * Grüne Häkchen = Alles funktioniert
 * Rote Kreuze = Probleme, die behoben werden müssen
 */

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/db/connection.php';

/**
 * Hauptfunktion für den Datenbanktest
 *
 * Führt mehrere Tests in logischer Reihenfolge aus:
 * 1. Verbindungstest
 * 2. Einfache Abfrage
 * 3. Tabellen prüfen
 * 4. Beispieldaten anzeigen
 */
function testDatabaseConnection() {
    // HTML-Kopf und grundlegendes Styling
    echo "<h1>Datenbankverbindung Test</h1>";
    echo "<style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        .warning { color: orange; }
        h2 { border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        ul { background: #f9f9f9; padding: 10px; border-radius: 5px; }
        li { margin: 5px 0; }
    </style>";

    try {
        /**
         * TEST 1: VERBINDUNG HERSTELLEN
         *
         * Prüft, ob die Datenbank-Konfiguration korrekt ist.
         * Wenn dieser Test fehlschlägt, sind meistens:
         * - XAMPP MySQL-Server nicht gestartet
         * - Falsche Zugangsdaten in config.php
         * - Datenbank 'aufgussplan' existiert nicht
         */
        echo "<h2>Test 1: Datenbankverbindung</h2>";
        echo "<p>Dieser Test prüft, ob die Verbindung zur MySQL-Datenbank funktioniert.</p>";

        // Singleton-Instanz der Database-Klasse holen
        $db = Database::getInstance()->getConnection();
        echo "<p class='success'>✅ Verbindung erfolgreich hergestellt!</p>";
        echo "<p class='info'>Die Zugangsdaten in config.php sind korrekt.</p>";

        /**
         * TEST 2: EINFACHE ABFRAGE
         *
         * Testet, ob grundlegende SQL-Abfragen funktionieren.
         * SELECT 1 ist die einfachste mögliche Abfrage.
         */
        echo "<h2>Test 2: Einfache SQL-Abfrage</h2>";
        echo "<p>Dieser Test führt eine einfache SELECT-Abfrage aus.</p>";

        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<p class='success'>✅ Abfrage erfolgreich: " . $result['test'] . "</p>";
        echo "<p class='info'>SQL-Abfragen können ausgeführt werden.</p>";

        /**
         * TEST 3: TABELLEN PRÜFEN
         *
         * Überprüft, ob alle erforderlichen Tabellen existieren.
         * Diese Tabellen müssen mit den SQL-Scripts erstellt werden.
         */
        echo "<h2>Test 3: Datenbanktabellen prüfen</h2>";
        echo "<p>Dieser Test prüft, ob alle nötigen Tabellen vorhanden sind.</p>";

        // Liste der erwarteten Tabellen
        $tables = ['mitarbeiter', 'saunen', 'duftmittel', 'aufguesse', 'plaene'];

        foreach ($tables as $table) {
            // SHOW TABLES LIKE sucht nach einer bestimmten Tabelle
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;

            if ($exists) {
                echo "<p class='success'>✅ Tabelle '$table' existiert</p>";

                // Zusätzliche Info: Wie viele Datensätze sind in der Tabelle?
                $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch()['count'];
                echo "<p class='info'>📊 Datensätze in '$table': $count</p>";

            } else {
                echo "<p class='error'>❌ Tabelle '$table' fehlt</p>";
                echo "<p class='warning'>Führe das SQL-Schema aus (siehe README.md)</p>";
            }
        }

        /**
         * TEST 4: BEISPIELDATEN ANZEIGEN
         *
         * Zeigt einige Beispieldaten aus den Tabellen an.
         * Hilft zu verstehen, wie die Daten strukturiert sind.
         */
        echo "<h2>Test 4: Beispieldaten prüfen</h2>";
        echo "<p>Dieser Test zeigt Beispiel-Daten aus deinen Tabellen.</p>";

        // MITARBEITER TABELLE
        echo "<h3>Mitarbeiter-Tabelle:</h3>";
        $mitarbeiter = $db->query("SELECT id, name FROM mitarbeiter LIMIT 3")->fetchAll();

        if ($mitarbeiter) {
            echo "<p class='success'>✅ Mitarbeiter-Daten gefunden:</p>";
            echo "<ul>";
            foreach ($mitarbeiter as $m) {
                echo "<li><strong>ID {$m['id']}:</strong> {$m['name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='info'>ℹ️ Keine Mitarbeiter-Daten gefunden</p>";
            echo "<p class='info'>Verwende den Admin-Bereich, um Mitarbeiter hinzuzufügen.</p>";
        }

        // SAUNEN TABELLE
        echo "<h3>Saunen-Tabelle:</h3>";
        $saunen = $db->query("SELECT id, name FROM saunen LIMIT 3")->fetchAll();

        if ($saunen) {
            echo "<p class='success'>✅ Saunen-Daten gefunden:</p>";
            echo "<ul>";
            foreach ($saunen as $s) {
                echo "<li><strong>ID {$s['id']}:</strong> {$s['name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='info'>ℹ️ Keine Saunen-Daten gefunden</p>";
            echo "<p class='info'>Saunen werden automatisch erstellt, wenn du Aufgüsse hinzufügst.</p>";
        }

        // DUFTMITTEL TABELLE
        echo "<h3>Duftmittel-Tabelle:</h3>";
        $duftmittel = $db->query("SELECT id, name FROM duftmittel LIMIT 3")->fetchAll();

        if ($duftmittel) {
            echo "<p class='success'>✅ Duftmittel-Daten gefunden:</p>";
            echo "<ul>";
            foreach ($duftmittel as $d) {
                echo "<li><strong>ID {$d['id']}:</strong> {$d['name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='info'>ℹ️ Keine Duftmittel-Daten gefunden</p>";
            echo "<p class='info'>Duftmittel werden automatisch erstellt, wenn du Aufgüsse hinzufügst.</p>";
        }

        // AUFGÜSSE TABELLE
        echo "<h3>Aufgüsse-Tabelle:</h3>";
        $aufguesse = $db->query("SELECT id, name FROM aufguss_namen LIMIT 3")->fetchAll();

        if ($aufguesse) {
            echo "<p class='success'>✅ Aufguss-Daten gefunden:</p>";
            echo "<ul>";
            foreach ($aufguesse as $a) {
                echo "<li><strong>ID {$a['id']}:</strong> {$a['name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='info'>ℹ️ Keine Aufguss-Daten gefunden</p>";
            echo "<p class='info'>Verwende den Admin-Bereich, um Aufgüsse zu planen.</p>";
        }

        // PLÄNE TABELLE
        echo "<h3>Pläne-Tabelle:</h3>";
        $plaene = $db->query("SELECT id, name FROM plaene LIMIT 3")->fetchAll();

        if ($plaene) {
            echo "<p class='success'>✅ Plan-Daten gefunden:</p>";
            echo "<ul>";
            foreach ($plaene as $p) {
                echo "<li><strong>ID {$p['id']}:</strong> {$p['name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='info'>ℹ️ Keine Plan-Daten gefunden</p>";
            echo "<p class='info'>Pläne werden automatisch erstellt, wenn du Aufgüsse hinzufügst.</p>";
        }

        // ERFOLGSMELDUNG
        echo "<h2 class='success'>🎉 Alle Tests erfolgreich!</h2>";
        echo "<p class='success'>Deine Datenbank ist bereit für die Aufgussplan-Anwendung.</p>";
        echo "<p><a href='index.php'>Zur öffentlichen Anzeige</a> | <a href='admin/'>Zum Admin-Bereich</a></p>";

    } catch (Exception $e) {
        // FEHLERBEHANDLUNG
        echo "<h2 class='error'>❌ Fehler aufgetreten:</h2>";
        echo "<p class='error'>Fehlermeldung: " . htmlspecialchars($e->getMessage()) . "</p>";

        echo "<h3>Was könnte das Problem sein?</h3>";
        echo "<p class='info'>Überprüfe diese Punkte:</p>";
        echo "<ul class='info'>";
        echo "<li><strong>XAMPP läuft:</strong> Ist der MySQL-Server gestartet?</li>";
        echo "<li><strong>Datenbank existiert:</strong> Gibt es die Datenbank 'aufgussplan' in phpMyAdmin?</li>";
        echo "<li><strong>Zugangsdaten:</strong> Sind DB_USER und DB_PASS in config.php korrekt?</li>";
        echo "<li><strong>Tabellen:</strong> Wurden die SQL-Scripts ausgeführt?</li>";
        echo "<li><strong>PHP-Version:</strong> Verwende PHP 7.4+ mit PDO-MySQL-Erweiterung</li>";
        echo "</ul>";

        echo "<h3>So behebst du das Problem:</h3>";
        echo "<ol>";
        echo "<li>Starte XAMPP Control Panel</li>";
        echo "<li>Klicke auf 'Start' bei MySQL</li>";
        echo "<li>Öffne phpMyAdmin (http://localhost/phpmyadmin)</li>";
        echo "<li>Erstelle Datenbank 'aufgussplan' (utf8mb4_unicode_ci)</li>";
        echo "<li>Führe das SQL-Schema aus (siehe README.md)</li>";
        echo "<li>Lade diese Seite neu</li>";
        echo "</ol>";
    }
}

// Den Test ausführen
testDatabaseConnection();
?>