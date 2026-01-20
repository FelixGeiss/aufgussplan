<?php
/**
 * Entity-Bild Upload-Script für Modal
 */

// Session für Sicherheit starten
session_start();

// Konfiguration laden
require_once __DIR__ . '/../../../src/config/config.php';

// Datenbankverbindung
require_once __DIR__ . '/../../../src/db/connection.php';

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
    if (!in_array($entityType, ['sauna', 'mitarbeiter', 'plan', 'duftmittel'])) {
        throw new Exception('Invalid entity type');
    }

    if (!$entityId) {
        throw new Exception('Invalid entity ID');
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
        case 'duftmittel':
            $table = 'duftmittel';
            $column = 'bild';
            $uploadSubDir = 'duftmittel';
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

    // Vorhandenes Bild auswaehlen (Upload ueberspringen)
    $existingImage = trim($_POST['existing_bild'] ?? '');
    if ($existingImage !== '') {
        $existingImage = basename($existingImage);
        $extension = strtolower(pathinfo($existingImage, PATHINFO_EXTENSION));
        $imageExts = ['jpg', 'jpeg', 'png', 'gif'];
        $videoExts = ['mp4', 'webm', 'ogg'];
        $allowedExts = $entityType === 'plan'
            ? array_merge($imageExts, $videoExts)
            : $imageExts;
        if (!in_array($extension, $allowedExts, true)) {
            throw new Exception('Ungueltiger Dateityp');
        }

        $existingPath = $uploadDir . $existingImage;
        if (!is_file($existingPath)) {
            throw new Exception('Ausgewaehlte Datei nicht gefunden');
        }

        $relativePath = $uploadSubDir . '/' . $existingImage;
        $stmt = $db->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?");
        $success = $stmt->execute([$relativePath, $entityId]);

        if (!$success) {
            throw new Exception('Fehler beim Aktualisieren der Datenbank');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // Datei-Upload pruefen
    if (!isset($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Keine Datei hochgeladen');
    }

    $file = $_FILES['bild'];

    // Dateigroesse pruefen (10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Datei ist zu gross (max. 10MB)');
    }

    // Dateityp pruefen
    $imageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $videoTypes = ['video/mp4', 'video/webm', 'video/ogg'];
    $allowedTypes = $entityType === 'plan'
        ? array_merge($imageTypes, $videoTypes)
        : $imageTypes;
    if (!in_array($file['type'], $allowedTypes, true)) {
        $allowedLabel = $entityType === 'plan'
            ? 'nur JPG, PNG, GIF, MP4, WEBM, OGG erlaubt'
            : 'nur JPG, PNG, GIF erlaubt';
        throw new Exception('Ungueltiger Dateityp (' . $allowedLabel . ')');
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

