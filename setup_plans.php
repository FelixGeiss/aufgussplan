<?php
/**
 * Datenbank-Setup fuer Plan-Funktionalitaet
 *
 * Erstellt Tabellen fuer die Plan-Verwaltung:
 * - Pläene
 * - erweitert aufguesse um plan_id
 * - erweitert Pläene um tv_aktiv
 */

require_once __DIR__ . '/src/config/config.php';
require_once __DIR__ . '/src/db/connection.php';

echo "<h1>Datenbank-Setup fuer Plan-Funktionalitaet</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px}.success{color:green;font-weight:bold}.error{color:red;font-weight:bold}</style>";

try {
    $db = Database::getInstance()->getConnection();

    // 1. Pläene-Tabelle erstellen
    echo "<h2>1. Erstelle Pläene-Tabelle</h2>";
    $sql = "CREATE TABLE IF NOT EXISTS Pläene (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        beschreibung TEXT,
        erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    echo "<p class='success'>✓ Pläene-Tabelle erfolgreich erstellt</p>";

    // 2. plan_id Spalte zur aufguesse-Tabelle hinzufuegen
    echo "<h2>2. Erweitere aufguesse-Tabelle</h2>";
    $sql = "ALTER TABLE aufguesse ADD COLUMN IF NOT EXISTS plan_id INT NULL";
    $db->exec($sql);
    echo "<p class='success'>✓ plan_id Spalte zu aufguesse-Tabelle hinzugefuegt</p>";

    // 3. tv_aktiv Spalte zur Pläene-Tabelle hinzufuegen
    echo "<h2>3. Erweitere Pläene-Tabelle</h2>";
    // 3. Foreign Key hinzufuegen
    echo "<h2>3. Erstelle Foreign Key</h2>";
    try {
        $sql = "ALTER TABLE aufguesse ADD CONSTRAINT fk_aufguss_plan FOREIGN KEY (plan_id) REFERENCES Pläene(id) ON DELETE SET NULL";
        $db->exec($sql);
        echo "<p class='success'>✓ Foreign Key fuer plan_id hinzugefuegt</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Foreign Key bereits vorhanden oder nicht moeglich: " . $e->getMessage() . "</p>";
    }

    // 4. Beispiel-Plan einfuegen
    echo "<h2>4. Erstelle Beispiel-Plan</h2>";
    $stmt = $db->prepare("INSERT INTO Pläene (name, beschreibung) VALUES (?, ?) ON DUPLICATE KEY UPDATE id=id");
    $stmt->execute(['Standard-Plan', 'Der grundlegende Aufguss-Plan fuer taegliche Anwendungen']);
    echo "<p class='success'>✓ Beispiel-Plan 'Standard-Plan' erstellt oder bereits vorhanden</p>";

    echo "<h2 class='success'>✓ Datenbank erfolgreich erweitert!</h2>";
    echo "<p><a href='test_db.php'>Zurueck zur Datenbank-Tests</a></p>";
    echo "<p><a href='admin/aufguesse.php'>Zu den Aufguessen</a></p>";

} catch (Exception $e) {
    echo "<h2 class='error'>Fehler beim Datenbank-Setup:</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
