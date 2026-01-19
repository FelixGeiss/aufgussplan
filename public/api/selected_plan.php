<?php
/**
 * API for storing and retrieving the currently selected plan for public display.
 *
 * GET  -> returns { plan_id }
 * POST -> sets { plan_id }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$storageDir = __DIR__ . '/../../storage';
$storageFile = $storageDir . '/selected_plan.json';

// Sendet eine JSON-Antwort mit Statuscode.
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

// Laedt die zuletzt gespeicherte Plan-Auswahl.
function readSelectedPlanData($storageFile) {
    if (!file_exists($storageFile)) {
        return ['plan_id' => null, 'updated_at' => null];
    }

    $raw = file_get_contents($storageFile);
    if ($raw === false || $raw === '') {
        return ['plan_id' => null, 'updated_at' => null];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['plan_id' => null, 'updated_at' => null];
    }

    $planId = $data['plan_id'] ?? null;
    $updatedAt = $data['updated_at'] ?? null;
    if ($planId === null || $planId === '') {
        return ['plan_id' => null, 'updated_at' => $updatedAt];
    }

    return [
        'plan_id' => (string)$planId,
        'updated_at' => $updatedAt ? (string)$updatedAt : null
    ];
}

// Speichert die aktuelle Plan-Auswahl als JSON.
function writeSelectedPlanId($storageDir, $storageFile, $planId) {
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $payload = [
        'plan_id' => (string)$planId,
        'updated_at' => date('c')
    ];

    file_put_contents($storageFile, json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $data = readSelectedPlanData($storageFile);
    sendResponse(true, 'Selected plan loaded', $data);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $planId = $input['plan_id'] ?? null;
    if ($planId === null || $planId === '') {
        sendResponse(false, 'plan_id is required', null, 400);
    }

    writeSelectedPlanId($storageDir, $storageFile, $planId);
    sendResponse(true, 'Selected plan saved', ['plan_id' => (string)$planId, 'updated_at' => date('c')]);
}

sendResponse(false, 'HTTP method not supported', null, 405);
?>
