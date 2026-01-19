<?php
session_start();
require_once __DIR__ . '/../../../src/config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$allowedTypes = ['werbung', 'plan', 'staerke'];
try {
    $type = trim((string)($_POST['type'] ?? ''));
    if (!in_array($type, $allowedTypes, true)) {
        throw new RuntimeException('Ungültiger Upload-Typ.');
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Keine Datei hochgeladen.');
    }

    $file = $_FILES['file'];
    if ($file['size'] > 30 * 1024 * 1024) {
        throw new RuntimeException('Datei darf maximal 30MB groß sein.');
    }

    $imageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $videoTypes = ['video/mp4', 'video/webm', 'video/ogg'];
    $allowedMimeTypes = $type === 'plan' ? array_merge($imageTypes, $videoTypes) : $imageTypes;

    if (!in_array($file['type'], $allowedMimeTypes, true)) {
        throw new RuntimeException('Ungültiger Dateityp.');
    }

    $uploadDir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
    }

    $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', basename($file['name']));
    $filename = uniqid('adm_', true) . '_' . $safeName;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }

    $relativePath = $type . '/' . $filename;
    echo json_encode(['success' => true, 'path' => $relativePath]);
} catch (Throwable $e) {
    error_log('Overview upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
