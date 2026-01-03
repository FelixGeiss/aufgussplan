<?php
/**
 * API for storing and retrieving next-aufguss settings per plan.
 *
 * GET  -> ?plan_id=ID returns settings
 * POST -> saves settings
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$storageDir = __DIR__ . '/../../storage';
$storageFile = $storageDir . '/next_aufguss_settings.json';

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

function loadSettings($storageFile) {
    if (!file_exists($storageFile)) {
        return [];
    }
    $raw = file_get_contents($storageFile);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeSettings($storageDir, $storageFile, $data) {
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }
    file_put_contents($storageFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $planId = $_GET['plan_id'] ?? null;
    if ($planId === null || $planId === '') {
        sendResponse(false, 'plan_id is required', null, 400);
    }
    $allSettings = loadSettings($storageFile);
    $settings = $allSettings[(string)$planId] ?? null;
    if (!$settings) {
        $settings = [
            'enabled' => true,
            'lead_seconds' => 5,
            'highlight_enabled' => true,
            'clock_enabled' => false
        ];
    }
    sendResponse(true, 'Settings loaded', [
        'plan_id' => (string)$planId,
        'settings' => $settings
    ]);
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

    $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
    $leadSeconds = isset($input['lead_seconds']) ? max(1, (int)$input['lead_seconds']) : 5;
    $highlightEnabled = isset($input['highlight_enabled']) ? (bool)$input['highlight_enabled'] : true;
    $clockEnabled = isset($input['clock_enabled']) ? (bool)$input['clock_enabled'] : false;

    $allSettings = loadSettings($storageFile);
    $allSettings[(string)$planId] = [
        'enabled' => $enabled,
        'lead_seconds' => $leadSeconds,
        'highlight_enabled' => $highlightEnabled,
        'clock_enabled' => $clockEnabled,
        'updated_at' => date('c')
    ];
    writeSettings($storageDir, $storageFile, $allSettings);

    sendResponse(true, 'Settings saved', [
        'plan_id' => (string)$planId,
        'settings' => $allSettings[(string)$planId]
    ]);
}

sendResponse(false, 'HTTP method not supported', null, 405);
?>
