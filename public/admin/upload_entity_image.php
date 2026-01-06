<?php
/**
 * Entity-Bild Upload-Script für Modal
 */

// Session für Sicherheit starten
session_start();

// Konfiguration laden
require_once __DIR__ . '/../../src/config/config.php';

// Datenbankverbindung
require_once __DIR__ . '/../../src/db/connection.php';

// Upload-Verzeichnis sicherstellen
$uploadBaseDir = UPLOAD_PATH;
if (!is_dir($uploadBaseDir)) {
    mkdir($uploadBaseDir, 0755, true);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $entityType = $_POST['entity_type'] ?? '';
    $entityId = (int)($_POST['entity_id'] ?? 0);

    // Validierung
    if (!in_array($entityType, ['sauna', 'mitarbeiter', 'plan'])) {
        throw new Exception('Invalid entity type');
    }

    if (!$entityId) {
        throw new Exception('Invalid entity ID');
    }

    // Datei-Upload prüfen
    if (!isset($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Keine Datei hochgeladen');
    }

    $file = $_FILES['bild'];

    // Dateigröße prüfen (10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Datei ist zu groß (max. 10MB)');
    }

    // Dateityp prüfen
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Ungültiger Dateityp (nur JPG, PNG, GIF erlaubt)');
    }

    // Tabelle und Spalte basierend auf Entity-Type bestimmen
    switch ($entityType) {
        case 'sauna':
            $table = 'saunen';
            $column = 'bild';
            $uploadSubDir = 'sauna';
            break;
        case 'mitarbeiter':
            $table = 'mitarbeiter';
            $column = 'bild';
            $uploadSubDir = 'mitarbeiter';
            break;
        case 'plan':
            $table = 'plaene';
            $column = 'hintergrund_bild';
            $uploadSubDir = 'plan';
            break;
        default:
            throw new Exception('Invalid entity type');
    }

    // Upload-Verzeichnis für Entity-Typ
    $uploadDir = $uploadBaseDir . $uploadSubDir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Upload-Verzeichnis für Entity-Typ
    $uploadDir = $uploadBaseDir . $uploadSubDir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Altes Bild nur Löschen, wenn es kein Plan-Hintergrund ist
    if ($entityType !== 'plan') {
        $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE id = ?");
        $stmt->execute([$entityId]);
        $oldImage = $stmt->fetchColumn();

        if ($oldImage && file_exists($uploadBaseDir . $oldImage)) {
            unlink($uploadBaseDir . $oldImage);
        }
    }

    // Neues Bild speichern
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . basename($file['name']);
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Fehler beim Speichern der Datei');
    }

    // Datenbank aktualisieren
    $relativePath = $uploadSubDir . '/' . $filename;
    $stmt = $db->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?");
    $success = $stmt->execute([$relativePath, $entityId]);

    if (!$success) {
        // Bei Datenbank-Fehler: Hochgeladene Datei löschen
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        throw new Exception('Fehler beim Aktualisieren der Datenbank');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Entity image upload error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

