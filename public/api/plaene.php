<?php
/**
 * API für Plan-Verwaltung
 *
 * Diese Datei stellt REST-ähnliche Endpunkte für die Verwaltung von Plänen bereit:
 * - GET: Alle Pläne abrufen
 * - POST: Neuen Plan erstellen
 * - PUT: Plan aktualisieren
 * - DELETE: Plan löschen
 */

// CORS-Header für JavaScript-Aufrufe
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// OPTIONS-Anfragen für CORS beantworten
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Session für Sicherheit starten
session_start();

// Konfiguration laden
require_once __DIR__ . '/../../src/config/config.php';

// Datenbankverbindung für PHP-Operationen
require_once __DIR__ . '/../../src/db/connection.php';

// Aufguss-Modell für Plan-Operationen
require_once __DIR__ . '/../../src/models/aufguss.php';

$aufgussModel = new Aufguss();

/**
 * Hauptlogik basierend auf HTTP-Methode
 */
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Alle Pläne abrufen
            handleGetPlans($aufgussModel);
            break;

        case 'POST':
            // Neuen Plan erstellen
            handleCreatePlan($aufgussModel);
            break;

        case 'PUT':
            // Plan aktualisieren
            handleUpdatePlan($aufgussModel);
            break;

        case 'DELETE':
            // Plan löschen
            handleDeletePlan($aufgussModel);
            break;

        default:
            sendResponse(false, 'HTTP-Methode nicht unterstützt', null, 405);
    }
} catch (Exception $e) {
    error_log('API-Fehler in Pläene.php: ' . $e->getMessage());
    sendResponse(false, 'Interner Serverfehler', null, 500);
}

/**
 * GET: Alle Pläne abrufen
 */
function handleGetPlans($aufgussModel) {
    $Pläene = $aufgussModel->getAllPlans();
    sendResponse(true, 'Pläne erfolgreich abgerufen', ['Pläene' => $Pläene]);
}

/**
 * POST: Neuen Plan erstellen
 */
function handleCreatePlan($aufgussModel) {
    // JSON-Daten aus dem Request-Body lesen
    $input = json_decode(file_get_contents('php://input'), true);

    // Alternative: Form-Data verarbeiten
    if (!$input) {
        $input = $_POST;
    }

    // Validierung - Name kann leer sein, dann wird ein Platzhalter verwendet
    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        $name = 'Unbenannter Plan';
    }

    // Plan erstellen
    $planId = $aufgussModel->createPlan([
        'name' => $name,
        'beschreibung' => trim($input['beschreibung'] ?? '')
    ]);

    if ($planId) {
        sendResponse(true, 'Plan erfolgreich erstellt', ['plan_id' => $planId]);
    } else {
        sendResponse(false, 'Fehler beim Erstellen des Plans', null, 500);
    }
}

/**
 * PUT: Plan aktualisieren
 */
function handleUpdatePlan($aufgussModel) {
    // JSON-Daten aus dem Request-Body lesen
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['id'])) {
        sendResponse(false, 'Plan-ID ist erforderlich', null, 400);
        return;
    }

    // Validierung - Name kann leer sein, dann wird ein Platzhalter verwendet
    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        $name = 'Unbenannter Plan';
    }

    // Plan aktualisieren
    $success = $aufgussModel->updatePlan($input['id'], [
        'name' => $name,
        'beschreibung' => trim($input['beschreibung'] ?? '')
    ]);

    if ($success) {
        sendResponse(true, 'Plan erfolgreich aktualisiert');
    } else {
        sendResponse(false, 'Fehler beim Aktualisieren des Plans', null, 500);
    }
}

/**
 * DELETE: Plan löschen
 */
function handleDeletePlan($aufgussModel) {
    // Plan-ID aus Query-Parameter oder Request-Body
    $planId = $_GET['id'] ?? null;

    if (!$planId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $planId = $input['id'] ?? null;
    }

    if (!$planId) {
        sendResponse(false, 'Plan-ID ist erforderlich', null, 400);
        return;
    }

    // Plan löschen
    $success = $aufgussModel->deletePlan($planId);

    if ($success) {
        sendResponse(true, 'Plan erfolgreich gelöscht');
    } else {
        sendResponse(false, 'Fehler beim Löschen des Plans', null, 500);
    }
}

/**
 * Hilfsfunktion für API-Antworten
 */
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