<?php
/**
 * API: Umfrage-Bewertungen Löschen
 *
 * Erwartet: DELETE mit JSON {"kriterium": "...", "aufguss_name_id": 12|null, "plan_ids": [1,2]}
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db/connection.php';

if (!is_admin_logged_in()) {
    sendResponse(false, 'Nicht angemeldet', null, 401);
}
if (!has_permission('statistik')) {
    sendResponse(false, 'Keine Berechtigung', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendResponse(false, 'Nur DELETE erlaubt', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    sendResponse(false, 'Ungueltige Anfrage', null, 400);
}

$kriterium = trim((string)($input['kriterium'] ?? ''));
if ($kriterium === '') {
    sendResponse(false, 'kriterium fehlt', null, 400);
}

$aufgussNameId = $input['aufguss_name_id'] ?? null;
if ($aufgussNameId !== null && $aufgussNameId !== '') {
    if (!is_numeric($aufgussNameId)) {
        sendResponse(false, 'aufguss_name_id ungueltig', null, 400);
    }
    $aufgussNameId = (int)$aufgussNameId;
} else {
    $aufgussNameId = null;
}

$planIds = $input['plan_ids'] ?? null;
$planFilter = '';
$params = [$kriterium, $aufgussNameId];

if (is_array($planIds)) {
    $cleanPlanIds = [];
    foreach ($planIds as $planId) {
        if (is_numeric($planId) && (int)$planId > 0) {
            $cleanPlanIds[] = (int)$planId;
        }
    }
    if (!empty($cleanPlanIds)) {
        $placeholders = implode(',', array_fill(0, count($cleanPlanIds), '?'));
        $planFilter = " AND plan_id IN (" . $placeholders . ")";
        $params = array_merge($params, $cleanPlanIds);
    }
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare(
    "DELETE FROM umfrage_bewertungen
     WHERE kriterium = ?
       AND aufguss_name_id <=> ?"
    . $planFilter
);
$stmt->execute($params);

sendResponse(true, 'Bewertungen geloescht', ['deleted' => $stmt->rowCount()]);

// JSON-Antwort senden und Request beenden.
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
