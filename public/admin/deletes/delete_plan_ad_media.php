<?php
/**
 * Plan-Werbung Löschen
 */

session_start();

require_once __DIR__ . '/../../../src/config/config.php';
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

    $planId = (int)($payload['plan_id'] ?? 0);
    if ($planId <= 0) {
        throw new Exception('Ungueltige Plan-ID');
    }

    $stmt = $db->prepare("SELECT werbung_media FROM plaene WHERE id = ?");
    $stmt->execute([$planId]);
    $currentMedia = $stmt->fetchColumn();

    if ($currentMedia) {
        $uploadBaseDir = UPLOAD_PATH;
        $mediaPath = $uploadBaseDir . $currentMedia;
        if (file_exists($mediaPath)) {
            unlink($mediaPath);
        }
    }

    $stmt = $db->prepare("UPDATE plaene SET werbung_media = NULL, werbung_media_typ = NULL, werbung_aktiv = 0 WHERE id = ?");
    $stmt->execute([$planId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Plan ad delete error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
