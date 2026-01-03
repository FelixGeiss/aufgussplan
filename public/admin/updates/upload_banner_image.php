<?php
/**
 * Banner image upload (uploads/werbung)
 */

session_start();

require_once __DIR__ . '/../../../src/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Keine Datei hochgeladen');
    }

    $file = $_FILES['banner'];
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Datei ist zu gross (max. 10MB)');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes, true)) {
        throw new Exception('Ungueltiger Dateityp (nur JPG, PNG, GIF erlaubt)');
    }

    $uploadBaseDir = UPLOAD_PATH;
    if (!is_dir($uploadBaseDir)) {
        mkdir($uploadBaseDir, 0755, true);
    }

    $uploadSubDir = 'werbung';
    $uploadDir = $uploadBaseDir . $uploadSubDir . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid() . '_' . basename($file['name']);
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Fehler beim Speichern der Datei');
    }

    $relativePath = $uploadSubDir . '/' . $filename;
    echo json_encode([
        'success' => true,
        'data' => [
            'path' => $relativePath
        ]
    ]);
} catch (Exception $e) {
    error_log('Banner upload error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
