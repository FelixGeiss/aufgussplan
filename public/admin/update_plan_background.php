<?php
/**
 * Plan-Hintergrundbild aus vorhandener Datei setzen
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
    $backgroundPath = trim((string)($payload['background_path'] ?? ''));

    if ($planId <= 0) {
        throw new Exception('Ungueltige Plan-ID');
    }

    if ($backgroundPath !== '') {
        if (strpos($backgroundPath, '..') !== false || !str_starts_with($backgroundPath, 'plan/')) {
            throw new Exception('Ungueltiger Dateipfad');
        }
        $fullPath = UPLOAD_PATH . $backgroundPath;
        if (!file_exists($fullPath)) {
            throw new Exception('Datei nicht gefunden');
        }
    } else {
        $backgroundPath = null;
    }

    $stmt = $db->prepare("UPDATE plaene SET hintergrund_bild = ? WHERE id = ?");
    $stmt->execute([$backgroundPath, $planId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Plan background select error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
