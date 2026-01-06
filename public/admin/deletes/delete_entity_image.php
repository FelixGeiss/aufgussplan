<?php
/**
 * Entity-Bild Löschen
 */

// Session fuer Sicherheit starten
session_start();

// Konfiguration laden
require_once __DIR__ . '/../../../src/config/config.php';

// Datenbankverbindung
require_once __DIR__ . '/../../../src/db/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $entityType = $payload['entity_type'] ?? '';
    $entityId = (int)($payload['entity_id'] ?? 0);

    if (!in_array($entityType, ['plan'], true)) {
        throw new Exception('Invalid entity type');
    }

    if (!$entityId) {
        throw new Exception('Invalid entity ID');
    }

    // Tabelle und Spalte basierend auf Entity-Type bestimmen
    switch ($entityType) {
        case 'plan':
            $table = 'plaene';
            $column = 'hintergrund_bild';
            break;
        default:
            throw new Exception('Invalid entity type');
    }

    // Aktuelles Bild laden
    $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE id = ?");
    $stmt->execute([$entityId]);
    $currentImage = $stmt->fetchColumn();

    if ($currentImage) {
        $uploadBaseDir = UPLOAD_PATH;
        $imagePath = $uploadBaseDir . $currentImage;

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    // Datenbank aktualisieren
    $stmt = $db->prepare("UPDATE {$table} SET {$column} = NULL WHERE id = ?");
    $success = $stmt->execute([$entityId]);

    if (!$success) {
        throw new Exception('Fehler beim Aktualisieren der Datenbank');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Entity image delete error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
