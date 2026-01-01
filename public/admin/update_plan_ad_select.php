<?php
/**
 * Plan-Werbung aus vorhandener Datei setzen
 */

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/db/connection.php';

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

    $planId = (int)($payload['plan_id'] ?? 0);
    $mediaPath = trim((string)($payload['media_path'] ?? ''));
    $mediaType = trim((string)($payload['media_type'] ?? ''));

    if ($planId <= 0) {
        throw new Exception('Ungueltige Plan-ID');
    }

    if ($mediaPath === '') {
        throw new Exception('Ungueltiger Dateipfad');
    }

    if (strpos($mediaPath, '..') !== false || !str_starts_with($mediaPath, 'werbung/')) {
        throw new Exception('Ungueltiger Dateipfad');
    }

    $fullPath = UPLOAD_PATH . $mediaPath;
    if (!file_exists($fullPath)) {
        throw new Exception('Datei nicht gefunden');
    }

    if ($mediaType !== 'video' && $mediaType !== 'image') {
        $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
        $mediaType = in_array($ext, ['mp4', 'webm', 'ogg'], true) ? 'video' : 'image';
    }

    $stmt = $db->prepare("UPDATE plaene SET werbung_media = ?, werbung_media_typ = ? WHERE id = ?");
    $stmt->execute([$mediaPath, $mediaType, $planId]);

    echo json_encode([
        'success' => true,
        'data' => [
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'media_name' => basename($mediaPath)
        ]
    ]);
} catch (Exception $e) {
    error_log('Plan ad select error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
