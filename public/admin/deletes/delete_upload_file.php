<?php
/**
 * Datei direkt aus dem Upload-Ordner Löschen
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
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $type = trim((string)($payload['type'] ?? ''));
    $path = trim((string)($payload['path'] ?? ''));

    if (!in_array($type, ['plan', 'werbung', 'staerke'], true)) {
        throw new Exception('Ungueltiger Dateityp');
    }

    if ($path === '' || strpos($path, '..') !== false) {
        throw new Exception('Ungueltiger Dateipfad');
    }

    $expectedPrefix = $type . '/';
    if (!str_starts_with($path, $expectedPrefix)) {
        throw new Exception('Ungueltiger Dateipfad');
    }

    $fullPath = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        throw new Exception('Datei nicht gefunden');
    }

    unlink($fullPath);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Upload file delete error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
